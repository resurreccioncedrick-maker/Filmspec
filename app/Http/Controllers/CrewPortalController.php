<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CrewPortalController extends Controller
{
    private array $typeBadge = ['damaged' => 'badge-red', 'missing' => 'badge-orange', 'malfunction' => 'badge-yellow', 'late_return' => 'badge-purple'];

    private array $typeLabel = ['damaged' => 'Damaged', 'missing' => 'Missing', 'malfunction' => 'Malfunction', 'late_return' => 'Late Return'];

    private array $statusBadge = ['open' => 'badge-yellow', 'resolved' => 'badge-green', 'closed' => 'badge-gray'];

    private array $maintBadge = ['pending' => 'badge-yellow', 'in_progress' => 'badge-blue', 'completed' => 'badge-green', 'cancelled' => 'badge-gray'];

    private array $maintLabel = ['preventive' => 'Preventive', 'corrective' => 'Corrective', 'calibration' => 'Calibration', 'cleaning' => 'Cleaning'];

    private array $bookingBadge = ['confirmed' => 'badge-blue', 'ongoing' => 'badge-green', 'pending_inspection' => 'badge-orange', 'returned' => 'badge-gray'];

    // Same set IncidentsController.php uses — kept in sync manually since it's a small,
    // rarely-changed fixed list, not worth a shared config entry for one array.
    private array $damageTypes = ['scratches' => 'Scratches', 'cracked_broken' => 'Cracked / Broken Part', 'electronic_malfunction' => 'Electronic Malfunction', 'missing_part' => 'Missing Part', 'others' => 'Others'];

    public function index(Request $request)
    {
        $user = Auth::user();
        $role = $user->role->role_name ?? null;

        if (! $user || $role !== 'crew') {
            return redirect()->route('dashboard');
        }

        $uid = $user->user_id;

        $msg = null;
        if ($request->isMethod('post')) {
            $msg = $this->handleAction($request, $uid);
        }

        // Every write action's form carries a hidden return_tab field so submitting stays on
        // the tab the crew member was on (e.g. Equipment Checklist, Attendance) instead of
        // bouncing back to the Today tab, which is the default when this page is opened fresh.
        $activeTab = $request->input('return_tab', 'dashboard');
        // Same idea one level deeper — which in-page sub-tab (Check-Out/Check-In on the
        // checklist page, Attendance/Timesheets on the attendance page) to reopen on.
        $activeSubTab = $request->input('return_subtab', '');
        // And one level deeper still — both the checklist and attendance pages now require
        // picking a specific booking before its marking UI appears, so remember which one.
        $activeBookingId = (int) $request->input('return_booking_id', 0);

        // Staff-authored reminders flagged "visible to crew" — reuses the existing Reminders
        // tool (config('filmspec.role_permissions') never granted crew that module) rather
        // than a separate announcements feature. Not scoped to a linked crew_members row —
        // any crew-role login sees these.
        $crewAnnouncements = DB::table('reminders')
            ->where('visible_to_crew', true)->where('is_done', false)
            ->orderBy('reminder_date')
            ->limit(10)
            ->get();

        $crewMember = DB::table('crew_members as cm')
            ->leftJoin('crew_positions as cp', 'cm.primary_position_id', '=', 'cp.position_id')
            ->where('cm.user_id', $uid)
            ->select('cm.*', 'cp.position_name')
            ->first();

        $myBookings = collect();
        $myMaintenances = collect();
        $myActiveBookings = collect();
        $bookingEquipMap = [];
        $myAttendance = collect();
        $myTimesheets = collect();
        $myChecklistBookings = collect();
        $myTeamBookings = collect();
        $allActiveCrew = collect();
        $fieldEquipMap = [];
        $fieldAccMap = [];
        $crewPositions = collect();
        $myFieldRequests = collect();

        if ($crewMember) {
            $cmId = $crewMember->crew_id;

            $myBookings = DB::table('booking_crew as bc')
                ->join('bookings as b', 'bc.booking_id', '=', 'b.booking_id')
                ->join('clients as c', 'b.client_id', '=', 'c.client_id')
                ->leftJoin('crew_positions as cp', 'bc.position_id', '=', 'cp.position_id')
                ->where('bc.crew_id', $cmId)
                ->whereNotIn('bc.assignment_status', ['declined', 'back_out'])
                ->whereIn('b.booking_status', ['confirmed', 'ongoing', 'pending_inspection', 'returned'])
                ->orderByDesc('b.shoot_date_start')
                ->select('b.booking_id', 'b.booking_reference', 'b.project_title', 'b.shoot_date_start',
                    'b.shoot_date_end', 'b.shoot_location', 'b.booking_status',
                    'bc.position_id', 'bc.rate_used', 'bc.hours_worked', 'cp.position_name',
                    'c.company_name', 'c.contact_person')
                ->limit(30)
                ->get();

            $myMaintenances = DB::table('maintenance_schedules as ms')
                ->join('equipment as e', 'ms.equipment_id', '=', 'e.equipment_id')
                ->where('ms.assigned_crew_id', $cmId)
                ->whereIn('ms.status', ['pending', 'in_progress'])
                ->orderBy('ms.scheduled_date')
                ->select('ms.*', 'e.equipment_name', 'e.brand', 'e.serial_number')
                ->limit(20)
                ->get();

            $myActiveBookings = DB::table('booking_crew as bc')
                ->join('bookings as b', 'bc.booking_id', '=', 'b.booking_id')
                ->where('bc.crew_id', $cmId)
                ->whereNotIn('bc.assignment_status', ['declined', 'back_out'])
                ->whereIn('b.booking_status', ['confirmed', 'ongoing', 'pending_inspection', 'returned'])
                ->orderByDesc('b.shoot_date_start')
                ->distinct()
                // shoot_date_start must be in the SELECT list to ORDER BY it under DISTINCT
                // (MySQL error 3065) — harmless to include, it's a per-booking column so it
                // doesn't affect the distinct-by-booking_id de-duplication.
                ->select('b.booking_id', 'b.booking_reference', 'b.project_title', 'b.shoot_date_start')
                ->limit(50)
                ->get();

            // Fix 3 — equipment picker scoped to whichever of the crew member's own active
            // bookings is selected, same shape as IncidentsController.php's $bookingEquipMap.
            $activeBookingIds = $myActiveBookings->pluck('booking_id')->all();
            $bookingEquipMap = [];
            if ($activeBookingIds) {
                $equipRows = DB::table('booking_equipment as be')
                    ->join('equipment as e', 'be.equipment_id', '=', 'e.equipment_id')
                    ->whereIn('be.booking_id', $activeBookingIds)
                    ->select('be.booking_id', 'e.equipment_id', 'e.equipment_name', 'e.brand', 'e.serial_number')
                    ->get();
                foreach ($equipRows as $row) {
                    $bookingEquipMap[$row->booking_id][] = $row;
                }
            }

            // Fix 5 — attendance/timesheet history, never previously surfaced to the crew member.
            $myAttendance = DB::table('crew_attendance as ca')
                ->join('bookings as b', 'ca.booking_id', '=', 'b.booking_id')
                ->where('ca.crew_id', $cmId)
                ->orderByDesc('ca.attendance_date')
                ->select('ca.*', 'b.booking_reference', 'b.project_title')
                ->limit(30)
                ->get();

            $myTimesheets = DB::table('crew_timesheets as ts')
                ->join('bookings as b', 'ts.booking_id', '=', 'b.booking_id')
                ->where('ts.crew_id', $cmId)
                ->orderByDesc('ts.timesheet_date')
                ->select('ts.*', 'b.booking_reference', 'b.project_title')
                ->limit(30)
                ->get();

            // Equipment checklist — trusted crew (whoever holds a crew login, per how these
            // accounts are provisioned) can check equipment OUT while a booking is 'confirmed'
            // and back IN while it's 'ongoing', mirroring ChecklistController's own direction
            // logic 1:1 so the auto status-transition rules stay consistent either way it's done.
            $checklistBookings = $myBookings->whereIn('booking_status', ['confirmed', 'ongoing']);
            $checklistBookingIds = $checklistBookings->pluck('booking_id')->all();
            if ($checklistBookingIds) {
                $equipRows = DB::table('booking_equipment as be')
                    ->join('equipment as e', 'be.equipment_id', '=', 'e.equipment_id')
                    ->leftJoin('equipment_checklist as co', function ($j) {
                        $j->on('co.equipment_id', '=', 'be.equipment_id')->on('co.booking_id', '=', 'be.booking_id')->where('co.direction', 'out');
                    })
                    ->leftJoin('equipment_checklist as ci', function ($j) {
                        $j->on('ci.equipment_id', '=', 'be.equipment_id')->on('ci.booking_id', '=', 'be.booking_id')->where('ci.direction', 'in');
                    })
                    ->whereIn('be.booking_id', $checklistBookingIds)
                    ->orderBy('e.equipment_name')
                    ->select('be.booking_id', 'be.equipment_id', 'be.quantity',
                        'e.equipment_name', 'e.brand', 'e.model',
                        'co.checked as co_checked', 'co.quantity_actual as co_qty', 'co.condition_out', 'co.notes as co_notes',
                        'ci.checked as ci_checked', 'ci.quantity_actual as ci_qty', 'ci.condition_in', 'ci.notes as ci_notes')
                    ->get();

                $itemsByBooking = [];
                foreach ($equipRows as $row) {
                    $itemsByBooking[$row->booking_id][] = $row;
                }
                foreach ($checklistBookings as $bk) {
                    $myChecklistBookings->push((object) [
                        'booking' => $bk,
                        'direction' => $bk->booking_status === 'confirmed' ? 'out' : 'in',
                        'items' => $itemsByBooking[$bk->booking_id] ?? [],
                    ]);
                }
            }

            // Team attendance — same trust model: whoever's on this booking's crew can mark
            // the whole field team's attendance for the day, not just their own, since only
            // one crew member (the one with the login) is typically present to do it.
            $teamBookings = $myBookings->whereIn('booking_status', ['confirmed', 'ongoing']);
            $teamBookingIds = $teamBookings->pluck('booking_id')->all();
            if ($teamBookingIds) {
                $today = now()->toDateString();
                $crewRows = DB::table('booking_crew as bc')
                    ->join('crew_members as cm', 'bc.crew_id', '=', 'cm.crew_id')
                    ->leftJoin('crew_positions as cp', 'bc.position_id', '=', 'cp.position_id')
                    ->whereIn('bc.booking_id', $teamBookingIds)
                    ->whereNotIn('bc.assignment_status', ['declined', 'back_out'])
                    ->orderBy('cp.position_name')->orderBy('cm.last_name')
                    ->select('bc.booking_id', 'bc.crew_id', DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS crew_name"), 'cm.phone', 'cp.position_name')
                    ->get();

                $existingAtt = DB::table('crew_attendance')
                    ->whereIn('booking_id', $teamBookingIds)->where('attendance_date', $today)
                    ->get()->keyBy(fn ($r) => $r->booking_id . '-' . $r->crew_id);

                $teamByBooking = [];
                foreach ($crewRows as $row) {
                    $row->existing_attendance = $existingAtt->get($row->booking_id . '-' . $row->crew_id);
                    $teamByBooking[$row->booking_id][] = $row;
                }
                foreach ($teamBookings as $bk) {
                    $myTeamBookings->push((object) [
                        'booking' => $bk,
                        'team' => $teamByBooking[$bk->booking_id] ?? [],
                    ]);
                }

                $allActiveCrew = DB::table('crew_members')
                    ->where('status', 'active')->orderBy('last_name')
                    ->selectRaw("crew_id, CONCAT(first_name,' ',last_name) AS name")
                    ->get();
            }

            // Field requests — same eligible-booking window as checklist/attendance above, and
            // the same request-then-staff-approves flow ClientBookingDetailController already
            // offers clients, just from the crew side (the people actually on set are just as
            // likely to need something sent out as the client is).
            $fieldReqBookingIds = $myBookings->whereIn('booking_status', ['confirmed', 'ongoing'])->pluck('booking_id')->unique()->values()->all();
            if ($fieldReqBookingIds) {
                $existingEquipByBooking = DB::table('booking_equipment')->whereIn('booking_id', $fieldReqBookingIds)->get()->groupBy('booking_id');
                $existingAccByBooking = DB::table('booking_accessories')->whereIn('booking_id', $fieldReqBookingIds)->get()->groupBy('booking_id');
                $allEquip = DB::table('equipment')->where('availability_status', 'available')
                    ->orderBy('equipment_name')->limit(100)
                    ->select('equipment_id', 'equipment_name', 'brand', 'model', 'daily_rate')->get();
                $allAcc = DB::table('accessories')->orderBy('accessory_name')->limit(100)
                    ->select('accessory_id', 'accessory_name', 'daily_rate')->get();

                foreach ($fieldReqBookingIds as $bid) {
                    $excludeEquipIds = ($existingEquipByBooking->get($bid) ?? collect())->pluck('equipment_id')->all();
                    $excludeAccIds = ($existingAccByBooking->get($bid) ?? collect())->pluck('accessory_id')->all();
                    $fieldEquipMap[$bid] = $allEquip->whereNotIn('equipment_id', $excludeEquipIds)->values();
                    $fieldAccMap[$bid] = $allAcc->whereNotIn('accessory_id', $excludeAccIds)->values();
                }
            }
            $crewPositions = DB::table('crew_positions')->orderBy('position_name')->get();

            $myFieldRequests = DB::table('booking_equipment_requests as r')
                ->join('bookings as b', 'r.booking_id', '=', 'b.booking_id')
                ->leftJoin('equipment as e', 'r.equipment_id', '=', 'e.equipment_id')
                ->leftJoin('accessories as acc', 'r.accessory_id', '=', 'acc.accessory_id')
                ->leftJoin('crew_positions as pos', 'r.position_id', '=', 'pos.position_id')
                ->where('r.requested_by', $uid)
                ->orderByDesc('r.created_at')
                ->select('r.*', 'b.booking_reference', 'b.project_title', 'e.equipment_name', 'acc.accessory_name', 'pos.position_name')
                ->limit(20)
                ->get();
        }

        $myIncidents = DB::table('incident_reports as ir')
            ->join('equipment as e', 'ir.equipment_id', '=', 'e.equipment_id')
            ->join('bookings as b', 'ir.booking_id', '=', 'b.booking_id')
            ->where('ir.reported_by', $uid)
            ->orderByDesc('ir.created_at')
            ->select('ir.*', 'e.equipment_name', 'b.booking_reference', 'b.project_title')
            ->limit(20)
            ->get();

        return view('crew-portal', [
            'msg' => $msg, 'user' => $user, 'crewMember' => $crewMember, 'activeTab' => $activeTab, 'activeSubTab' => $activeSubTab,
            'activeBookingId' => $activeBookingId, 'crewAnnouncements' => $crewAnnouncements,
            'myBookings' => $myBookings, 'myIncidents' => $myIncidents, 'myMaintenances' => $myMaintenances,
            'myActiveBookings' => $myActiveBookings, 'bookingEquipMap' => $bookingEquipMap,
            'myAttendance' => $myAttendance, 'myTimesheets' => $myTimesheets,
            'myChecklistBookings' => $myChecklistBookings, 'myTeamBookings' => $myTeamBookings, 'allActiveCrew' => $allActiveCrew,
            'fieldEquipMap' => $fieldEquipMap, 'fieldAccMap' => $fieldAccMap, 'crewPositions' => $crewPositions,
            'myFieldRequests' => $myFieldRequests,
            'typeBadge' => $this->typeBadge, 'typeLabel' => $this->typeLabel, 'statusBadge' => $this->statusBadge,
            'maintBadge' => $this->maintBadge, 'maintLabel' => $this->maintLabel, 'bookingBadge' => $this->bookingBadge,
            'damageTypes' => $this->damageTypes,
            'condOut' => ['excellent' => 'Excellent', 'good' => 'Good', 'fair' => 'Fair'],
            'condIn' => ['excellent' => 'Excellent', 'good' => 'Good', 'fair' => 'Fair', 'damaged' => 'Damaged', 'missing' => 'Missing'],
            'attStatusLabel' => ['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'no_show' => 'No Show', 'back_out' => 'Back Out'],
        ]);
    }

    // Printable check-out/check-in record, scoped to the crew member's own booking — reuses
    // the exact same view and query shape as ChecklistController::print() (the staff version)
    // so the two stay visually identical, just with an ownership check added and the "Back"
    // link pointed at the crew portal instead of the staff booking-detail page.
    public function printChecklist(Request $request)
    {
        $user = Auth::user();
        $role = $user->role->role_name ?? null;
        if (! $user || $role !== 'crew') {
            return redirect()->route('dashboard');
        }

        $crewMember = DB::table('crew_members')->where('user_id', $user->user_id)->first();
        $bid = (int) $request->query('booking_id', 0);
        if (! $bid || ! $crewMember) {
            return redirect()->route('crew-portal');
        }

        $ownsBooking = DB::table('booking_crew')->where('crew_id', $crewMember->crew_id)
            ->where('booking_id', $bid)->whereNotIn('assignment_status', ['declined', 'back_out'])->exists();
        if (! $ownsBooking) {
            return redirect()->route('crew-portal');
        }

        $booking = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('b.booking_id', $bid)
            ->select('b.*', 'c.company_name', 'c.contact_person', 'c.client_type')
            ->first();
        if (! $booking) {
            return redirect()->route('crew-portal');
        }

        $equipLines = DB::table('booking_equipment as be')
            ->join('equipment as e', 'be.equipment_id', '=', 'e.equipment_id')
            ->join('equipment_categories as ec', 'e.category_id', '=', 'ec.category_id')
            ->leftJoin('equipment_checklist as co', function ($j) use ($bid) {
                $j->on('co.equipment_id', '=', 'be.equipment_id')->where('co.booking_id', $bid)->where('co.direction', 'out');
            })
            ->leftJoin('equipment_checklist as ci', function ($j) use ($bid) {
                $j->on('ci.equipment_id', '=', 'be.equipment_id')->where('ci.booking_id', $bid)->where('ci.direction', 'in');
            })
            ->leftJoin('users as uo', 'co.checked_by', '=', 'uo.user_id')
            ->leftJoin('users as ui', 'ci.checked_by', '=', 'ui.user_id')
            ->where('be.booking_id', $bid)
            ->orderBy('ec.category_name')->orderBy('e.equipment_name')
            ->select(
                'be.equipment_id', 'be.quantity',
                'e.equipment_name', 'e.brand', 'e.model', 'ec.category_name',
                'co.quantity_actual as co_qty', 'co.condition_out', 'co.checked as co_checked',
                'co.notes as co_notes', 'co.checked_at as co_at',
                DB::raw("CONCAT(uo.first_name,' ',uo.last_name) as co_by_name"),
                'ci.quantity_actual as ci_qty', 'ci.condition_in', 'ci.checked as ci_checked',
                'ci.notes as ci_notes', 'ci.checked_at as ci_at',
                DB::raw("CONCAT(ui.first_name,' ',ui.last_name) as ci_by_name")
            )
            ->get();

        $totalItems = $equipLines->count();
        $outDone = $equipLines->filter(fn ($e) => $e->co_checked)->count();
        $inDone = $equipLines->filter(fn ($e) => $e->ci_checked)->count();
        $damaged = $equipLines->filter(fn ($e) => in_array($e->condition_in, ['damaged', 'missing'], true))->count();

        $printBadge = ['excellent' => 'ok', 'good' => 'ok', 'fair' => 'warn', 'damaged' => 'bad', 'missing' => 'bad'];

        return view('checklist-print', [
            'booking' => $booking, 'bid' => $bid, 'equipLines' => $equipLines,
            'totalItems' => $totalItems, 'outDone' => $outDone, 'inDone' => $inDone, 'damaged' => $damaged,
            'condOut' => ['excellent' => 'Excellent', 'good' => 'Good', 'fair' => 'Fair'],
            'condIn' => ['excellent' => 'Excellent', 'good' => 'Good', 'fair' => 'Fair', 'damaged' => 'Damaged', 'missing' => 'Missing'],
            'condBadge' => $printBadge,
            'backUrl' => route('crew-portal'),
        ]);
    }

    // Printable attendance record for one booking, scoped to the crew member's own
    // assignment — shows every attendance row ever logged for that booking (not just
    // today's), since a multi-day shoot can have attendance marked across several dates.
    public function printAttendance(Request $request)
    {
        $user = Auth::user();
        $role = $user->role->role_name ?? null;
        if (! $user || $role !== 'crew') {
            return redirect()->route('dashboard');
        }

        $crewMember = DB::table('crew_members')->where('user_id', $user->user_id)->first();
        $bid = (int) $request->query('booking_id', 0);
        if (! $bid || ! $crewMember) {
            return redirect()->route('crew-portal');
        }

        $ownsBooking = DB::table('booking_crew')->where('crew_id', $crewMember->crew_id)
            ->where('booking_id', $bid)->whereNotIn('assignment_status', ['declined', 'back_out'])->exists();
        if (! $ownsBooking) {
            return redirect()->route('crew-portal');
        }

        $booking = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('b.booking_id', $bid)
            ->select('b.*', 'c.company_name', 'c.contact_person', 'c.client_type')
            ->first();
        if (! $booking) {
            return redirect()->route('crew-portal');
        }

        $records = DB::table('crew_attendance as ca')
            ->join('crew_members as cm', 'ca.crew_id', '=', 'cm.crew_id')
            ->leftJoin('crew_positions as cp', 'cm.primary_position_id', '=', 'cp.position_id')
            ->leftJoin('crew_members as rep', 'ca.replacement_crew_id', '=', 'rep.crew_id')
            ->leftJoin('users as logger', 'ca.logged_by', '=', 'logger.user_id')
            ->where('ca.booking_id', $bid)
            ->orderBy('ca.attendance_date')->orderBy('cm.last_name')
            ->select('ca.*', DB::raw("CONCAT(cm.first_name,' ',cm.last_name) as crew_name"), 'cp.position_name',
                DB::raw("CONCAT(rep.first_name,' ',rep.last_name) as replacement_name"),
                DB::raw("CONCAT(logger.first_name,' ',logger.last_name) as logged_by_name"))
            ->get();

        $totalCrew = (int) DB::table('booking_crew')->where('booking_id', $bid)->whereNotIn('assignment_status', ['declined', 'back_out'])->count();
        $presentCount = $records->whereIn('status', ['present', 'late'])->count();
        $absentCount = $records->whereIn('status', ['absent', 'no_show'])->count();

        return view('attendance-print', [
            'booking' => $booking, 'bid' => $bid, 'records' => $records,
            'totalCrew' => $totalCrew, 'presentCount' => $presentCount, 'absentCount' => $absentCount,
            'attStatusLabel' => ['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'no_show' => 'No Show', 'back_out' => 'Back Out'],
            'backUrl' => route('crew-portal'),
        ]);
    }

    private function handleAction(Request $request, int $uid): ?array
    {
        $action = $request->input('action', '');

        $crewMember = DB::table('crew_members')->where('user_id', $uid)->first();

        if ($action === 'crew_file_incident' && $crewMember) {
            $bid = (int) $request->input('booking_id', 0);
            $eid = (int) $request->input('equipment_id', 0);
            $itype = $request->input('incident_type', 'damaged');
            $idate = $request->input('incident_date', now()->toDateString());
            $itime = trim((string) $request->input('incident_time', ''));
            $desc = trim((string) $request->input('description', ''));
            $cause = $request->input('cause', 'unknown');
            $dtypes = array_values(array_unique(array_filter($request->input('damage_types', []))));
            $dothers = trim((string) $request->input('damage_others_note', ''));

            if (! $bid || ! $eid || ! $desc) {
                return ['type' => 'danger', 'text' => 'Please fill in all required fields.'];
            }

            // Ownership check — a booking_id/equipment_id must actually be this crew member's
            // own (non-declined) assignment and that booking's own equipment, not just any ID
            // POSTed by the client. The booking/equipment <select>s only ever offer these in the
            // real UI, but the server re-validates rather than trusting that alone.
            $ownsBooking = DB::table('booking_crew')->where('crew_id', $crewMember->crew_id)
                ->where('booking_id', $bid)->whereNotIn('assignment_status', ['declined', 'back_out'])->exists();
            if (! $ownsBooking) {
                return ['type' => 'danger', 'text' => 'That booking is not assigned to you.'];
            }
            $equipOnBooking = DB::table('booking_equipment')->where('booking_id', $bid)->where('equipment_id', $eid)->exists();
            if (! $equipOnBooking) {
                return ['type' => 'danger', 'text' => 'That equipment is not part of this booking.'];
            }

            $year = now()->year;
            $count = (int) DB::table('incident_reports')->whereYear('created_at', $year)->count();
            $irNum = 'IR-' . $year . '-' . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);

            DB::table('incident_reports')->insert([
                'booking_id' => $bid, 'equipment_id' => $eid, 'reported_by' => $uid,
                'incident_type' => $itype, 'incident_date' => $idate,
                'incident_time' => $itime ?: null, 'description' => $desc, 'cause' => $cause,
                'charge_amount' => 0, 'status' => 'open', 'incident_number' => $irNum,
                'damage_others_note' => $dothers, 'submitted_by_crew' => 1,
            ]);

            $incidentId = DB::getPdo()->lastInsertId();
            if ($dtypes) {
                DB::table('incident_damage_types')->insert(array_map(
                    fn ($t) => ['incident_id' => $incidentId, 'damage_type' => $t], $dtypes
                ));
            }
            ActivityLog::record($uid, 'create', 'incident', "Crew filed incident report $irNum", $incidentId);

            return ['type' => 'success', 'text' => "Incident report <strong>$irNum</strong> filed. The admin will review and set any charges."];
        }

        if ($action === 'crew_save_checklist' && $crewMember) {
            $bid = (int) $request->input('booking_id', 0);
            $direction = $request->input('direction') === 'in' ? 'in' : 'out';
            // Simplified crew flow: a plain list of checked equipment_ids — no per-item
            // quantity/condition/notes fields. Every checked item is recorded as its full
            // expected quantity in 'good' condition; anything actually wrong with a specific
            // item is reported through the separate File Incident Report action instead,
            // which already exists and already handles condition/damage detail properly.
            $checkedIds = array_map('intval', (array) $request->input('items', []));

            $ownsBooking = DB::table('booking_crew')->where('crew_id', $crewMember->crew_id)
                ->where('booking_id', $bid)->whereNotIn('assignment_status', ['declined', 'back_out'])->exists();
            if (! $ownsBooking) {
                return ['type' => 'danger', 'text' => 'That booking is not assigned to you.'];
            }

            $bookingEquip = DB::table('booking_equipment')->where('booking_id', $bid)->get()->keyBy('equipment_id');

            foreach ($checkedIds as $eid) {
                if (! $bookingEquip->has($eid)) {
                    continue;
                }
                $qty = (int) $bookingEquip[$eid]->quantity;

                if ($direction === 'out') {
                    $existing = DB::table('equipment_checklist')->where('booking_id', $bid)->where('equipment_id', $eid)->where('direction', 'out')->value('checklist_id');
                    if ($existing) {
                        DB::table('equipment_checklist')->where('checklist_id', $existing)->update([
                            'checked' => 1, 'quantity_actual' => $qty, 'condition_out' => 'good', 'checked_by' => $uid, 'checked_at' => now(),
                        ]);
                    } else {
                        DB::table('equipment_checklist')->insert([
                            'booking_id' => $bid, 'equipment_id' => $eid, 'direction' => 'out', 'quantity_expected' => $qty,
                            'quantity_actual' => $qty, 'condition_out' => 'good', 'checked' => 1, 'checked_by' => $uid, 'checked_at' => now(),
                        ]);
                    }

                    $existTx = DB::table('equipment_transactions')->where('booking_id', $bid)->where('equipment_id', $eid)->where('transaction_type', 'checkout')->value('transaction_id');
                    if (! $existTx) {
                        DB::table('equipment_transactions')->insert([
                            'booking_id' => $bid, 'equipment_id' => $eid, 'transaction_type' => 'checkout',
                            'transaction_date' => now(), 'condition_out' => 'good', 'handled_by' => $uid,
                        ]);
                        DB::table('equipment')->where('equipment_id', $eid)->update(['availability_status' => 'rented']);
                    }
                } else {
                    $existing = DB::table('equipment_checklist')->where('booking_id', $bid)->where('equipment_id', $eid)->where('direction', 'in')->value('checklist_id');
                    if ($existing) {
                        DB::table('equipment_checklist')->where('checklist_id', $existing)->update([
                            'checked' => 1, 'quantity_actual' => $qty, 'condition_in' => 'good', 'checked_by' => $uid, 'checked_at' => now(),
                        ]);
                    } else {
                        DB::table('equipment_checklist')->insert([
                            'booking_id' => $bid, 'equipment_id' => $eid, 'direction' => 'in', 'quantity_expected' => $qty,
                            'quantity_actual' => $qty, 'condition_in' => 'good', 'checked' => 1, 'checked_by' => $uid, 'checked_at' => now(),
                        ]);
                    }

                    $existTx = DB::table('equipment_transactions')->where('booking_id', $bid)->where('equipment_id', $eid)->where('transaction_type', 'checkin')->value('transaction_id');
                    if (! $existTx) {
                        DB::table('equipment_transactions')->insert([
                            'booking_id' => $bid, 'equipment_id' => $eid, 'transaction_type' => 'checkin',
                            'transaction_date' => now(), 'condition_in' => 'good', 'handled_by' => $uid,
                        ]);
                        DB::table('equipment')->where('equipment_id', $eid)->update(['availability_status' => 'available']);
                    }
                }
            }

            if ($direction === 'out') {
                $curStatus = DB::table('bookings')->where('booking_id', $bid)->value('booking_status');
                if ($curStatus === 'confirmed') {
                    $anyReleased = (int) DB::table('equipment_transactions')->where('booking_id', $bid)->where('transaction_type', 'checkout')->count();
                    if ($anyReleased > 0) {
                        DB::table('bookings')->where('booking_id', $bid)->update(['booking_status' => 'ongoing', 'updated_at' => now()]);
                        ActivityLog::record($uid, 'status_change', 'booking', "Booking #$bid moved to ongoing via crew checklist-out", $bid);
                    }
                }
            } else {
                $totalEquip = (int) DB::table('booking_equipment')->where('booking_id', $bid)->count();
                $returnedCount = (int) DB::table('equipment_checklist')->where('booking_id', $bid)->where('direction', 'in')->where('checked', 1)->count();
                if ($totalEquip > 0 && $returnedCount >= $totalEquip) {
                    $curStatus = DB::table('bookings')->where('booking_id', $bid)->value('booking_status');
                    if (in_array($curStatus, ['ongoing', 'confirmed'], true)) {
                        DB::table('bookings')->where('booking_id', $bid)->update(['booking_status' => 'returned', 'updated_at' => now()]);
                        ActivityLog::record($uid, 'status_change', 'booking', "Booking #$bid marked returned via crew checklist-in", $bid);
                    }
                }
            }

            ActivityLog::record($uid, 'checklist' . $direction, 'booking', "Crew checklist $direction saved for booking #$bid", $bid);

            return ['type' => 'success', 'text' => 'Checklist <strong>' . strtoupper($direction) . '</strong> saved successfully.'];
        }

        if ($action === 'crew_mark_attendance' && $crewMember) {
            $bid = (int) $request->input('booking_id', 0);
            $date = $request->input('attendance_date') ?: now()->toDateString();

            $ownsBooking = DB::table('booking_crew')->where('crew_id', $crewMember->crew_id)
                ->where('booking_id', $bid)->whereNotIn('assignment_status', ['declined', 'back_out'])->exists();
            if (! $ownsBooking) {
                return ['type' => 'danger', 'text' => 'That booking is not assigned to you.'];
            }

            // A crew lead's own submitted date, otherwise unbounded — protects the payroll
            // no-show/back-out deduction logic in CrewDataController from arbitrarily
            // backdated/forward-dated entries outside the actual shoot.
            $shootRange = DB::table('bookings')->where('booking_id', $bid)->select('shoot_date_start', 'shoot_date_end')->first();
            if ($shootRange && ($date < $shootRange->shoot_date_start || $date > $shootRange->shoot_date_end)) {
                return ['type' => 'danger', 'text' => 'Attendance date must fall within this booking\'s shoot dates (' . $shootRange->shoot_date_start . ' to ' . $shootRange->shoot_date_end . ').'];
            }

            $logged = 0;
            DB::transaction(function () use ($request, $bid, $date, $uid, &$logged) {
                foreach ((array) $request->input('crew_status', []) as $cid => $status) {
                    $cid = (int) $cid;
                    // Only allow marking teammates actually assigned (non-declined) to this same
                    // booking — the form only ever offers these, but the server re-checks rather
                    // than trusting the POSTed crew_id list.
                    $memberOnBooking = DB::table('booking_crew')->where('booking_id', $bid)->where('crew_id', $cid)
                        ->where('assignment_status', '!=', 'declined')->exists();
                    if (! $memberOnBooking) {
                        continue;
                    }

                    $reason = $request->input("crew_reason.$cid", '');
                    $repId = (int) $request->input("crew_replacement.$cid", 0);

                    DB::table('crew_attendance')->updateOrInsert(
                        ['booking_id' => $bid, 'crew_id' => $cid, 'attendance_date' => $date],
                        ['status' => $status, 'reason' => $reason, 'replacement_crew_id' => $repId ?: null, 'logged_by' => $uid]
                    );
                    $logged++;

                    // Same booking_crew sync as the staff-side Log Attendance flow (see
                    // AttendanceController::handleAction) — without this, a crew lead marking a
                    // teammate back_out here never actually changed that teammate's real
                    // assignment status, so Schedule/roster views kept showing them as active.
                    $currentAssignment = DB::table('booking_crew')->where('booking_id', $bid)->where('crew_id', $cid)->value('assignment_status');
                    if ($status === 'back_out' && $currentAssignment !== 'back_out') {
                        DB::table('booking_crew')->where('booking_id', $bid)->where('crew_id', $cid)
                            ->update(['assignment_status' => 'back_out']);
                    } elseif ($status !== 'back_out' && $currentAssignment === 'back_out') {
                        DB::table('booking_crew')->where('booking_id', $bid)->where('crew_id', $cid)
                            ->update(['assignment_status' => 'confirmed']);
                    }
                }
            });
            ActivityLog::record($uid, 'log_attendance', 'crew', "Crew lead logged $logged attendance record(s) for booking #$bid");

            return ['type' => 'success', 'text' => "Attendance logged for <strong>$logged</strong> crew member(s)."];
        }

        if ($action === 'crew_request_field_item' && $crewMember) {
            $bid = (int) $request->input('booking_id', 0);
            $itemType = in_array($request->input('item_type'), ['equipment', 'accessory', 'crew'], true)
                ? $request->input('item_type') : 'equipment';
            $qty = max(1, (int) $request->input('quantity', 1));
            $reason = trim((string) $request->input('reason', ''));

            $booking = DB::table('bookings')->where('booking_id', $bid)->first();
            $ownsBooking = DB::table('booking_crew')->where('crew_id', $crewMember->crew_id)
                ->where('booking_id', $bid)->whereNotIn('assignment_status', ['declined', 'back_out'])->exists();
            if (! $booking || ! $ownsBooking || ! in_array($booking->booking_status, ['confirmed', 'ongoing'], true)) {
                return ['type' => 'danger', 'text' => 'That booking is not assigned to you or is not currently active.'];
            }

            if ($itemType === 'equipment') {
                $eid = (int) $request->input('equipment_id', 0);
                if (! $eid) {
                    return ['type' => 'danger', 'text' => 'Please select equipment.'];
                }
                $eqRate = (float) DB::table('equipment')->where('equipment_id', $eid)->value('daily_rate');
                DB::table('booking_equipment_requests')->insert([
                    'booking_id' => $bid, 'requested_by' => $uid, 'item_type' => 'equipment',
                    'equipment_id' => $eid, 'quantity' => $qty, 'reason' => $reason, 'daily_rate' => $eqRate, 'created_at' => now(),
                ]);
            } elseif ($itemType === 'accessory') {
                $aid = (int) $request->input('accessory_id', 0);
                if (! $aid) {
                    return ['type' => 'danger', 'text' => 'Please select an accessory.'];
                }
                $accRate = (float) DB::table('accessories')->where('accessory_id', $aid)->value('daily_rate');
                DB::table('booking_equipment_requests')->insert([
                    'booking_id' => $bid, 'requested_by' => $uid, 'item_type' => 'accessory',
                    'accessory_id' => $aid, 'quantity' => $qty, 'reason' => $reason, 'daily_rate' => $accRate, 'created_at' => now(),
                ]);
            } else { // crew — same convention as ClientBookingDetailController: a role/position, not a specific person
                $pid = (int) $request->input('position_id', 0);
                if (! $pid) {
                    return ['type' => 'danger', 'text' => 'Please select a role.'];
                }
                DB::table('booking_equipment_requests')->insert([
                    'booking_id' => $bid, 'requested_by' => $uid, 'item_type' => 'crew',
                    'position_id' => $pid, 'quantity' => 1, 'reason' => $reason, 'daily_rate' => 0, 'created_at' => now(),
                ]);
            }

            ActivityLog::record($uid, 'create', 'booking', "Crew requested a field item ($itemType) for booking #$bid", $bid);

            return ['type' => 'success', 'text' => 'Request submitted. The admin will review it shortly.'];
        }

        if ($action === 'update_maintenance_status') {
            $schId = (int) $request->input('schedule_id', 0);
            $status = $request->input('status', 'in_progress');
            $notes = trim((string) $request->input('notes', ''));

            if ($schId && $crewMember) {
                $update = ['status' => $status, 'notes' => $notes];
                if (in_array($status, ['completed', 'cancelled'], true)) {
                    $update['completed_at'] = now();
                }
                DB::table('maintenance_schedules')
                    ->where('schedule_id', $schId)
                    ->where('assigned_crew_id', $crewMember->crew_id)
                    ->update($update);

                return ['type' => 'success', 'text' => 'Maintenance status updated.'];
            }
        }

        return null;
    }
}
