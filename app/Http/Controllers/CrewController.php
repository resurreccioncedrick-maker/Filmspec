<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\BookingCosting;
use App\Support\DataExporter;
use App\Support\ImageUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrewController extends Controller
{
    private array $statusBadge = ['active' => 'badge-green', 'inactive' => 'badge-gray', 'blacklisted' => 'badge-red'];

    private array $typeBadge = ['staff' => 'badge-blue', 'freelance' => 'badge-purple', 'on_call' => 'badge-yellow'];

    private array $typeLabel = ['staff' => 'Staff', 'freelance' => 'Freelance', 'on_call' => 'On Call'];

    public function index(Request $request): View|JsonResponse|StreamedResponse|Response
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';
        $canManage = in_array($role, config('filmspec.manage_roles'), true);

        if ($request->has('get_crew_detail')) {
            return $this->crewDetail((int) $request->query('get_crew_detail'));
        }

        if ($request->isMethod('post') && $canManage && $request->filled('ajax_action')) {
            return $this->handleAjaxAction($request);
        }

        $msg = null;
        if ($request->isMethod('post') && $canManage) {
            $msg = $this->handleAction($request);
        }

        if ($request->filled('export')) {
            return $this->export($request);
        }

        $tab = in_array($request->query('tab'), ['members', 'positions', 'schedule', 'attendance'], true) ? $request->query('tab') : 'members';

        $statusFilter = $request->query('status', '');
        $posFilter = (int) $request->query('pos', 0);
        $etFilter = $request->query('et', '');
        $search = $request->query('q', '');
        $page = max(1, (int) $request->query('p', 1));
        $perPage = 20;

        $query = $this->filteredQuery($request);

        $total = (clone $query)->count('cm.crew_id');
        $pages = max(1, (int) ceil($total / $perPage));

        $crew = (clone $query)
            ->select('cm.*', 'cp.position_name', 'cp.department', 'lu.email as linked_email')
            ->orderBy('cm.last_name')->orderBy('cm.first_name')
            ->forPage($page, $perPage)
            ->get();

        $positions = DB::table('crew_positions')->orderBy('department')->orderBy('position_name')->get();

        $stats = [
            'total' => (int) DB::table('crew_members')->where('status', 'active')->count(),
            'staff' => (int) DB::table('crew_members')->where('employment_type', 'staff')->where('status', 'active')->count(),
            'freelance' => (int) DB::table('crew_members')->where('employment_type', 'freelance')->where('status', 'active')->count(),
            'on_call' => (int) DB::table('crew_members')->where('employment_type', 'on_call')->where('status', 'active')->count(),
        ];

        $upcomingBlocks = [];
        $rawBlocks = DB::table('crew_unavailability as cu')
            ->join('crew_members as cm', 'cu.crew_id', '=', 'cm.crew_id')
            ->where('cu.date_to', '>=', DB::raw('CURDATE()'))
            ->where('cu.date_from', '<=', DB::raw('DATE_ADD(CURDATE(), INTERVAL 90 DAY)'))
            ->orderBy('cu.date_from')
            ->select('cu.*', DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS crew_name"))
            ->get();
        foreach ($rawBlocks as $b) {
            $upcomingBlocks[$b->crew_id][] = $b;
        }

        $allUpcomingBlocks = DB::table('crew_unavailability')
            ->where('date_to', '>=', DB::raw('CURDATE()'))
            ->orderBy('crew_id')->orderBy('date_from')
            ->get();

        // Per-crew-member current/upcoming booking, so staff can see at a glance who's on a
        // shoot right now (or coming up) without cross-referencing the Bookings page — the
        // registry previously showed no schedule information at all.
        $currentAssignments = [];
        $rawAssignments = DB::table('booking_crew as bc')
            ->join('bookings as b', 'bc.booking_id', '=', 'b.booking_id')
            ->whereNotIn('b.booking_status', ['cancelled', 'completed'])
            ->where('b.shoot_date_end', '>=', DB::raw('CURDATE()'))
            ->orderBy('b.shoot_date_start')
            ->select('bc.crew_id', 'bc.assignment_status', 'b.booking_reference', 'b.booking_status',
                'b.shoot_date_start', 'b.shoot_date_end')
            ->get();
        foreach ($rawAssignments as $a) {
            // Earliest (soonest-starting) assignment per crew member only — a quick-glance
            // column, not a full itinerary; the crew member's own detail still shows everything.
            if (! isset($currentAssignments[$a->crew_id])) {
                $currentAssignments[$a->crew_id] = $a;
            }
        }

        // Positions tab — how many crew members currently hold each position as their primary
        // role, so staff can see at a glance which positions are actually staffed.
        $positionsWithCounts = DB::table('crew_positions as cp')
            ->leftJoin('crew_members as cm', function ($j) {
                $j->on('cm.primary_position_id', '=', 'cp.position_id')->where('cm.status', 'active');
            })
            ->groupBy('cp.position_id', 'cp.position_name', 'cp.department', 'cp.description')
            ->orderBy('cp.department')->orderBy('cp.position_name')
            ->select('cp.*')
            ->selectRaw('COUNT(cm.crew_id) AS crew_count')
            ->get();

        // Schedule tab — a month calendar of confirmed/ongoing crew assignments, same
        // day-map-building approach as Dashboard's and Calendar Analytics' calendars.
        $scheduleMonth = $request->query('smonth', now()->format('Y-m'));
        $scheduleAssignments = DB::table('booking_crew as bc')
            ->join('bookings as b', 'bc.booking_id', '=', 'b.booking_id')
            ->join('crew_members as cm', 'bc.crew_id', '=', 'cm.crew_id')
            ->whereNotIn('b.booking_status', ['cancelled', 'completed'])
            ->whereNotIn('bc.assignment_status', ['declined', 'replaced', 'back_out'])
            ->select('bc.crew_id', 'b.booking_id', 'b.booking_reference', 'b.project_title',
                'b.shoot_date_start', 'b.shoot_date_end', 'b.booking_status',
                DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS crew_name"))
            ->get();

        return view('crew', [
            'msg' => $msg, 'canManage' => $canManage, 'tab' => $tab,
            'stats' => $stats, 'crew' => $crew, 'positions' => $positions, 'positionsWithCounts' => $positionsWithCounts,
            'total' => $total, 'pages' => $pages, 'page' => $page,
            'statusFilter' => $statusFilter, 'posFilter' => $posFilter, 'etFilter' => $etFilter, 'search' => $search,
            'upcomingBlocks' => $upcomingBlocks, 'allUpcomingBlocks' => $allUpcomingBlocks,
            'currentAssignments' => $currentAssignments,
            'scheduleMonth' => $scheduleMonth, 'scheduleAssignments' => $scheduleAssignments,
            'statusBadge' => $this->statusBadge, 'typeBadge' => $this->typeBadge, 'typeLabel' => $this->typeLabel,
        ]);
    }

    /** Same filters index() applies, shared with export() so the two can never drift apart. */
    private function filteredQuery(Request $request)
    {
        $statusFilter = $request->query('status', '');
        $posFilter = (int) $request->query('pos', 0);
        $etFilter = $request->query('et', '');
        $search = $request->query('q', '');

        $query = DB::table('crew_members as cm')
            ->leftJoin('crew_positions as cp', 'cm.primary_position_id', '=', 'cp.position_id')
            ->leftJoin('users as lu', 'cm.user_id', '=', 'lu.user_id');
        if ($statusFilter) $query->where('cm.status', $statusFilter);
        if ($posFilter) $query->where('cm.primary_position_id', $posFilter);
        if ($etFilter) $query->where('cm.employment_type', $etFilter);
        if ($search) {
            $query->where(function ($w) use ($search) {
                $w->where('cm.first_name', 'like', "%$search%")
                    ->orWhere('cm.last_name', 'like', "%$search%")
                    ->orWhere('cm.phone', 'like', "%$search%")
                    ->orWhere('cp.position_name', 'like', "%$search%");
            });
        }

        return $query;
    }

    /** Export ▾ — reuses filteredQuery() unbounded (no page/perPage) so it always matches what's on screen. */
    private function export(Request $request): StreamedResponse|Response
    {
        $headers = ['Crew Member', 'Position', 'Department', 'Type', 'Rate (12hr)', 'Phone', 'Status'];

        $rows = $this->filteredQuery($request)
            ->select('cm.first_name', 'cm.last_name', 'cp.position_name', 'cp.department',
                'cm.employment_type', 'cm.base_rate_12hr', 'cm.phone', 'cm.status')
            ->orderBy('cm.last_name')->orderBy('cm.first_name')
            ->get()
            ->map(fn ($c) => [
                trim($c->first_name . ' ' . $c->last_name),
                $c->position_name ?: '—',
                $c->department ?: '—',
                $this->typeLabel[$c->employment_type] ?? ucfirst($c->employment_type),
                '₱' . number_format((float) $c->base_rate_12hr, 2),
                $c->phone ?: '—',
                ucfirst($c->status),
            ])
            ->all();

        return DataExporter::respond($request->query('export'), 'Crew Registry', $headers, $rows, 'crew-export');
    }

    /**
     * Feeds the Crew Management detail panel — one fetch returns everything all five sub-tabs
     * need (Overview/Qualifications/Schedule/Attendance/History) so switching tabs is instant,
     * client-side, with no extra round trips.
     */
    private function crewDetail(int $cid): JsonResponse
    {
        $cm = DB::table('crew_members as cm')
            ->leftJoin('crew_positions as cp', 'cm.primary_position_id', '=', 'cp.position_id')
            ->leftJoin('users as lu', 'cm.user_id', '=', 'lu.user_id')
            ->where('cm.crew_id', $cid)
            ->select('cm.*', 'cp.position_name', 'cp.department', 'lu.email as linked_email')
            ->first();
        if (! $cm) {
            return response()->json(['error' => 'Crew member not found.'], 404);
        }

        $qualifications = DB::table('crew_qualifications')->where('crew_id', $cid)
            ->orderByDesc('expiry_date')->orderBy('title')->get();

        $schedule = DB::table('booking_crew as bc')
            ->join('bookings as b', 'bc.booking_id', '=', 'b.booking_id')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('bc.crew_id', $cid)
            ->whereNotIn('b.booking_status', ['cancelled'])
            ->whereNotIn('bc.assignment_status', ['declined', 'replaced', 'back_out'])
            ->orderByDesc('b.shoot_date_start')
            ->select('bc.bk_crew_id', 'b.booking_id', 'b.booking_reference', 'b.project_title', 'b.shoot_date_start',
                'b.shoot_date_end', 'b.booking_status', 'c.company_name', 'c.contact_person', 'bc.assignment_status')
            ->limit(30)
            ->get();

        $attendance = DB::table('crew_attendance as ca')
            ->join('bookings as b', 'ca.booking_id', '=', 'b.booking_id')
            ->where('ca.crew_id', $cid)
            ->orderByDesc('ca.attendance_date')
            ->select('ca.*', 'b.booking_reference', 'b.project_title')
            ->limit(30)
            ->get();

        $history = DB::table('activity_logs as al')
            ->leftJoin('users as u', 'al.user_id', '=', 'u.user_id')
            ->where('al.module', 'crew')->where('al.record_id', $cid)
            ->orderByDesc('al.created_at')
            ->select('al.action', 'al.description', 'al.created_at', DB::raw("CONCAT(u.first_name,' ',u.last_name) AS by_name"))
            ->limit(50)
            ->get();

        // For the Schedule tab's "Mark Withdrawn" replacement picker — every other active crew
        // member, so a withdrawal and its replacement can be chosen in one step.
        $otherActiveCrew = DB::table('crew_members')
            ->where('status', 'active')->where('crew_id', '!=', $cid)
            ->orderBy('last_name')
            ->selectRaw("crew_id, CONCAT(first_name,' ',last_name) AS name")
            ->get();

        return response()->json([
            'crew' => $cm, 'qualifications' => $qualifications,
            'schedule' => $schedule, 'attendance' => $attendance, 'history' => $history,
            'otherActiveCrew' => $otherActiveCrew,
            'statusBadge' => $this->statusBadge, 'typeBadge' => $this->typeBadge, 'typeLabel' => $this->typeLabel,
        ]);
    }

    private function handleAjaxAction(Request $request): JsonResponse
    {
        $action = $request->input('ajax_action');
        $uid = $request->user()->user_id;

        if ($action === 'add_qualification') {
            $cid = (int) $request->input('crew_id');
            $title = trim($request->input('title', ''));
            if (! $cid || ! $title) {
                return response()->json(['success' => false, 'error' => 'Qualification title is required.']);
            }

            $qid = DB::table('crew_qualifications')->insertGetId([
                'crew_id' => $cid, 'title' => $title,
                'issuing_body' => trim($request->input('issuing_body', '')) ?: null,
                'issue_date' => $request->input('issue_date') ?: null,
                'expiry_date' => $request->input('expiry_date') ?: null,
                'notes' => trim($request->input('notes', '')) ?: null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $crewName = DB::table('crew_members')->where('crew_id', $cid)->selectRaw("CONCAT(first_name,' ',last_name) AS name")->value('name');
            ActivityLog::record($uid, 'create', 'crew', "Added qualification \"$title\" for $crewName", $cid);

            return response()->json(['success' => true, 'qualification_id' => $qid]);
        }

        if ($action === 'delete_qualification') {
            $qid = (int) $request->input('qualification_id');
            $row = DB::table('crew_qualifications')->where('qualification_id', $qid)->first();
            if (! $row) {
                return response()->json(['success' => false, 'error' => 'Qualification not found.']);
            }
            DB::table('crew_qualifications')->where('qualification_id', $qid)->delete();
            $crewName = DB::table('crew_members')->where('crew_id', $row->crew_id)->selectRaw("CONCAT(first_name,' ',last_name) AS name")->value('name');
            ActivityLog::record($uid, 'delete', 'crew', "Removed qualification \"{$row->title}\" from $crewName", $row->crew_id);

            return response()->json(['success' => true]);
        }

        // Withdrawing before a shoot is a crew-ASSIGNMENT change, not an attendance log entry —
        // moved out of the day-of Attendance form per the panelist notes. Requires a replacement
        // in the same step, same conflict checks batchAddCrew()/FieldRequestsController::dispatch()
        // already use for assigning anyone else to a booking.
        if ($action === 'mark_withdrawn') {
            $bkCrewId = (int) $request->input('bk_crew_id');
            $replacementId = (int) $request->input('replacement_crew_id');
            $original = DB::table('booking_crew')->where('bk_crew_id', $bkCrewId)->first();
            if (! $original) {
                return response()->json(['success' => false, 'error' => 'Crew assignment not found.']);
            }
            if (! $replacementId) {
                return response()->json(['success' => false, 'error' => 'A replacement crew member is required.']);
            }
            if ($replacementId === $original->crew_id) {
                return response()->json(['success' => false, 'error' => 'Replacement must be a different crew member.']);
            }

            $booking = DB::table('bookings')->where('booking_id', $original->booking_id)->first();
            if (! $booking) {
                return response()->json(['success' => false, 'error' => 'Booking not found.']);
            }
            $replacement = DB::table('crew_members')->where('crew_id', $replacementId)->first();
            if (! $replacement || $replacement->status !== 'active') {
                return response()->json(['success' => false, 'error' => 'Selected replacement is not an active crew member.']);
            }
            if (DB::table('booking_crew')->where('booking_id', $original->booking_id)->where('crew_id', $replacementId)->exists()) {
                return response()->json(['success' => false, 'error' => 'That crew member is already on this booking.']);
            }
            $conflictRef = DB::table('booking_crew as bc')
                ->join('bookings as b', 'bc.booking_id', '=', 'b.booking_id')
                ->where('bc.crew_id', $replacementId)->where('bc.booking_id', '!=', $original->booking_id)
                ->whereNotIn('bc.assignment_status', ['declined', 'replaced', 'back_out'])
                ->whereNotIn('b.booking_status', ['cancelled', 'completed'])
                ->where('b.shoot_date_start', '<=', $booking->shoot_date_end)
                ->where('b.shoot_date_end', '>=', $booking->shoot_date_start)
                ->value('b.booking_reference');
            if ($conflictRef) {
                return response()->json(['success' => false, 'error' => 'This crew member is already booked on ' . $conflictRef . ' during this period.']);
            }
            $unavail = DB::table('crew_unavailability')->where('crew_id', $replacementId)
                ->where('date_from', '<=', $booking->shoot_date_end)->where('date_to', '>=', $booking->shoot_date_start)
                ->exists();
            if ($unavail) {
                return response()->json(['success' => false, 'error' => 'This crew member has marked themselves unavailable during this period.']);
            }

            $originalName = DB::table('crew_members')->where('crew_id', $original->crew_id)->selectRaw("CONCAT(first_name,' ',last_name) AS name")->value('name');
            $replacementName = trim($replacement->first_name . ' ' . $replacement->last_name);

            DB::transaction(function () use ($original, $replacementId, $replacement, $booking) {
                DB::table('booking_crew')->where('bk_crew_id', $original->bk_crew_id)->update(['assignment_status' => 'back_out']);
                DB::table('booking_crew')->insert([
                    'booking_id' => $original->booking_id, 'crew_id' => $replacementId, 'position_id' => $original->position_id,
                    'rate_used' => $replacement->base_rate_12hr, 'hours_worked' => $original->hours_worked,
                    'assignment_status' => 'confirmed', 'notes' => 'Replacement — original crew member withdrew before the shoot.',
                ]);
            });
            BookingCosting::generateCostEstimate($original->booking_id, $uid);
            ActivityLog::record($uid, 'update', 'crew', "Withdrawn from {$booking->booking_reference}, replaced by $replacementName", $original->crew_id);
            ActivityLog::record($uid, 'assign', 'crew', "Assigned to {$booking->booking_reference} as a replacement for $originalName", $replacementId);

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'error' => 'Unknown action']);
    }

    private function handleAction(Request $request): ?array
    {
        $action = $request->input('action', '');
        $uid = $request->user()->user_id;

        if ($action === 'add_crew') {
            $phone = trim($request->input('phone', ''));
            if ($phone !== '' && ! preg_match('/^09\d{9}$/', $phone)) {
                return ['type' => 'danger', 'text' => 'Phone must be 11 digits starting with 09 (e.g. 09171234567).'];
            }

            $photoPath = '';
            if ($request->hasFile('photo')) {
                $up = ImageUpload::handle($request->file('photo'), 'crew');
                if ($up['success']) $photoPath = $up['path'];
            }

            $fn = strtoupper(trim($request->input('first_name', '')));
            $ln = strtoupper(trim($request->input('last_name', '')));
            $pos = (int) $request->input('primary_position_id', 0);

            $cid = DB::table('crew_members')->insertGetId([
                'first_name' => $fn, 'last_name' => $ln,
                'email' => trim($request->input('email', '')), 'phone' => $phone,
                'address' => trim($request->input('address', '')),
                'primary_position_id' => $pos ?: null,
                'employment_type' => $request->input('employment_type', 'freelance'),
                'base_rate_12hr' => (float) $request->input('base_rate_12hr', 0),
                'overtime_rate' => (float) $request->input('overtime_rate', 0),
                'double_pay_rate' => (float) $request->input('double_pay_rate', 0),
                'monthly_salary' => (float) $request->input('monthly_salary', 0),
                'date_joined' => $request->input('date_joined') ?: now()->toDateString(),
                'profile_notes' => $request->input('profile_notes', ''),
                'photo_path' => $photoPath,
            ]);
            ActivityLog::record($uid, 'create', 'crew', "Added crew: $fn $ln", $cid);

            return ['type' => 'success', 'text' => "Crew member <strong>$fn $ln</strong> added successfully."];
        }

        if ($action === 'edit_crew') {
            $phone = trim($request->input('phone', ''));
            if ($phone !== '' && ! preg_match('/^09\d{9}$/', $phone)) {
                return ['type' => 'danger', 'text' => 'Phone must be 11 digits starting with 09 (e.g. 09171234567).'];
            }

            $cid = (int) $request->input('crew_id');
            $old = DB::table('crew_members')->where('crew_id', $cid)->first();
            $photoPath = $old->photo_path ?? '';
            if ($request->hasFile('photo')) {
                $up = ImageUpload::handle($request->file('photo'), 'crew');
                if ($up['success']) {
                    ImageUpload::deleteOld($old->photo_path ?? null);
                    $photoPath = $up['path'];
                }
            }

            $fn = strtoupper(trim($request->input('first_name', '')));
            $ln = strtoupper(trim($request->input('last_name', '')));
            $pos = (int) $request->input('primary_position_id', 0);

            DB::table('crew_members')->where('crew_id', $cid)->update([
                'first_name' => $fn, 'last_name' => $ln,
                'email' => trim($request->input('email', '')), 'phone' => $phone,
                'address' => trim($request->input('address', '')),
                'primary_position_id' => $pos ?: null,
                'employment_type' => $request->input('employment_type', 'freelance'),
                'base_rate_12hr' => (float) $request->input('base_rate_12hr', 0),
                'overtime_rate' => (float) $request->input('overtime_rate', 0),
                'double_pay_rate' => (float) $request->input('double_pay_rate', 0),
                'monthly_salary' => (float) $request->input('monthly_salary', 0),
                'profile_notes' => $request->input('profile_notes', ''),
                'status' => $request->input('status', 'active'),
                'photo_path' => $photoPath,
            ]);
            ActivityLog::record($uid, 'update', 'crew', "Updated crew: $fn $ln", $cid);

            return ['type' => 'success', 'text' => 'Crew member updated successfully.'];
        }

        if ($action === 'delete_crew') {
            $cid = (int) $request->input('crew_id');
            $cm = DB::table('crew_members')->where('crew_id', $cid)->first();
            if (! $cm) {
                return ['type' => 'error', 'text' => 'Crew member not found.'];
            }

            $onBookings = (int) DB::table('booking_crew')->where('crew_id', $cid)->count();
            if ($onBookings > 0) {
                return ['type' => 'error', 'text' => "Cannot delete — this crew member is assigned to $onBookings booking(s). Remove them from all bookings first, or set their status to Inactive."];
            }

            ImageUpload::deleteOld($cm->photo_path ?? null);
            DB::table('crew_members')->where('crew_id', $cid)->delete();
            $name = e($cm->first_name . ' ' . $cm->last_name);
            ActivityLog::record($uid, 'delete', 'crew', "Deleted crew member $name", $cid);

            return ['type' => 'success', 'text' => "Crew member <strong>$name</strong> has been deleted."];
        }

        if ($action === 'add_unavailability') {
            $cid = (int) $request->input('crew_id');
            $dateFrom = $request->input('date_from', '');
            $dateTo = $request->input('date_to') ?: $dateFrom;
            $reason = trim($request->input('reason', ''));

            if ($cid && $dateFrom && $dateTo && strtotime($dateTo) >= strtotime($dateFrom)) {
                DB::table('crew_unavailability')->insert([
                    'crew_id' => $cid, 'date_from' => $dateFrom, 'date_to' => $dateTo,
                    'reason' => $reason, 'reason_category' => trim($request->input('reason_category', '')) ?: null,
                    'internal_note' => trim($request->input('internal_note', '')) ?: null, 'created_by' => $uid,
                ]);
                $crewName = DB::table('crew_members')->where('crew_id', $cid)->selectRaw("CONCAT(first_name,' ',last_name) AS name")->value('name');
                ActivityLog::record($uid, 'create', 'crew', "Blocked availability for $crewName: $dateFrom – $dateTo", $cid);

                return ['type' => 'success', 'text' => 'Unavailability block added.'];
            }

            return ['type' => 'danger', 'text' => 'Invalid date range — end date must be on or after start date.'];
        }

        if ($action === 'remove_unavailability') {
            $unavailId = (int) $request->input('unavailability_id');
            if ($unavailId) {
                $row = DB::table('crew_unavailability')->where('unavailability_id', $unavailId)->first();
                DB::table('crew_unavailability')->where('unavailability_id', $unavailId)->delete();
                if ($row) {
                    $crewName = DB::table('crew_members')->where('crew_id', $row->crew_id)->selectRaw("CONCAT(first_name,' ',last_name) AS name")->value('name');
                    ActivityLog::record($uid, 'delete', 'crew', "Removed unavailability block for $crewName: {$row->date_from} – {$row->date_to}", $row->crew_id);
                }

                return ['type' => 'success', 'text' => 'Unavailability block removed.'];
            }

            return null;
        }

        if ($action === 'link_crew_account') {
            $cid = (int) $request->input('crew_id');
            $email = trim(strtolower((string) $request->input('email', '')));
            $cm = DB::table('crew_members')->where('crew_id', $cid)->first();
            if (! $cm) {
                return ['type' => 'error', 'text' => 'Crew member not found.'];
            }
            if ($cm->user_id) {
                return ['type' => 'error', 'text' => 'This crew member already has a linked login.'];
            }
            if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['type' => 'danger', 'text' => 'Enter a valid email address.'];
            }
            if (DB::table('users')->where('email', $email)->exists()) {
                return ['type' => 'danger', 'text' => 'That email is already in use by another account.'];
            }

            $crewRoleId = DB::table('roles')->where('role_name', 'crew')->value('role_id');
            if (! $crewRoleId) {
                return ['type' => 'error', 'text' => "No 'crew' role exists in this system — cannot create a login."];
            }

            // Shown once in the success message below — this app has no outbound mail
            // configured beyond the CE-approval flow, so staff hand this over directly
            // (same reasoning DocumentController/ImageUpload use for not wiring email here).
            $tempPassword = Str::random(4) . '-' . Str::random(4);

            $newUserId = DB::table('users')->insertGetId([
                'role_id' => $crewRoleId, 'first_name' => $cm->first_name, 'last_name' => $cm->last_name,
                'email' => $email, 'password_hash' => Hash::make($tempPassword), 'phone' => $cm->phone,
                'is_active' => 1,
            ]);
            DB::table('crew_members')->where('crew_id', $cid)->update(['user_id' => $newUserId]);
            ActivityLog::record($uid, 'update', 'crew', "Linked a login for {$cm->first_name} {$cm->last_name} ($email)", $cid);

            return ['type' => 'success', 'persist' => true, 'text' => "Login created for <strong>{$cm->first_name} {$cm->last_name}</strong>.<br>Email: <strong>$email</strong><br>Temporary password: <strong>$tempPassword</strong><br>Hand these to them directly — this won't be shown again."];
        }

        if ($action === 'unlink_crew_account') {
            $cid = (int) $request->input('crew_id');
            $cm = DB::table('crew_members')->where('crew_id', $cid)->first();
            if (! $cm || ! $cm->user_id) {
                return ['type' => 'error', 'text' => 'No linked login to remove.'];
            }
            DB::table('crew_members')->where('crew_id', $cid)->update(['user_id' => null]);
            ActivityLog::record($uid, 'update', 'crew', "Unlinked the login for {$cm->first_name} {$cm->last_name}", $cid);

            return ['type' => 'success', 'text' => 'Login unlinked. The account itself was not deleted.'];
        }

        if ($action === 'add_position') {
            $pn = trim($request->input('position_name', ''));
            $dept = trim($request->input('department', ''));
            if ($pn) {
                DB::table('crew_positions')->insertOrIgnore([
                    'position_name' => $pn, 'department' => $dept,
                    'description' => trim($request->input('description', '')) ?: null,
                    'responsibilities' => trim($request->input('responsibilities', '')) ?: null,
                ]);

                return ['type' => 'success', 'text' => "Position <strong>" . e($pn) . "</strong> added."];
            }

            return null;
        }

        return null;
    }
}
