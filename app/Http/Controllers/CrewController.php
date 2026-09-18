<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\DataExporter;
use App\Support\ImageUpload;
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

    public function index(Request $request): View|StreamedResponse|Response
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';
        $canManage = in_array($role, config('filmspec.manage_roles'), true);

        $msg = null;
        if ($request->isMethod('post') && $canManage) {
            $msg = $this->handleAction($request);
        }

        if ($request->filled('export')) {
            return $this->export($request);
        }

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

        return view('crew', [
            'msg' => $msg, 'canManage' => $canManage,
            'stats' => $stats, 'crew' => $crew, 'positions' => $positions,
            'total' => $total, 'pages' => $pages, 'page' => $page,
            'statusFilter' => $statusFilter, 'posFilter' => $posFilter, 'etFilter' => $etFilter, 'search' => $search,
            'upcomingBlocks' => $upcomingBlocks, 'allUpcomingBlocks' => $allUpcomingBlocks,
            'currentAssignments' => $currentAssignments,
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
                    'reason' => $reason, 'created_by' => $uid,
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
                DB::table('crew_positions')->insertOrIgnore(['position_name' => $pn, 'department' => $dept]);

                return ['type' => 'success', 'text' => "Position <strong>" . e($pn) . "</strong> added."];
            }

            return null;
        }

        return null;
    }
}
