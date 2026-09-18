<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\DataExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    private array $statusBadge = [
        'present' => 'badge-green', 'absent' => 'badge-red', 'late' => 'badge-yellow',
        'no_show' => 'badge-red', 'back_out' => 'badge-orange',
    ];

    private array $statusLabel = [
        'present' => 'Present', 'absent' => 'Absent', 'late' => 'Late',
        'no_show' => 'No Show', 'back_out' => 'Back Out',
    ];

    public function index(Request $request): View|StreamedResponse|Response
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';
        $canManage = in_array($role, config('filmspec.manage_roles'), true);

        $msg = null;
        if ($request->isMethod('post')) {
            $msg = $this->handleAction($request, $canManage);
        }

        if ($request->filled('export')) {
            return $this->export($request);
        }

        $filterBooking = (int) $request->query('booking_id', 0);
        $filterCrew = (int) $request->query('crew_id', 0);
        $filterDate = $request->query('date', '');
        $filterStatus = $request->query('status', '');
        $page = max(1, (int) $request->query('p', 1));
        $perPage = 25;

        $activeBookings = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->whereIn('b.booking_status', ['confirmed', 'ongoing'])
            ->orderByDesc('b.shoot_date_start')
            ->select('b.booking_id', 'b.booking_reference', 'b.project_title', 'b.shoot_date_start', 'c.company_name', 'c.contact_person')
            ->get();

        $bookingCrew = collect();
        if ($filterBooking) {
            $bookingCrew = DB::table('booking_crew as bc')
                ->join('crew_members as cm', 'bc.crew_id', '=', 'cm.crew_id')
                ->leftJoin('crew_positions as cp', 'bc.position_id', '=', 'cp.position_id')
                ->where('bc.booking_id', $filterBooking)
                ->where('bc.assignment_status', '!=', 'declined')
                ->orderBy('cp.position_name')->orderBy('cm.last_name')
                ->select('bc.*', DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS crew_name"), 'cm.phone', 'cp.position_name')
                ->get();

            $today = now()->toDateString();
            $existingAttByCrew = DB::table('crew_attendance')
                ->where('booking_id', $filterBooking)->where('attendance_date', $today)
                ->get()->keyBy('crew_id');
            foreach ($bookingCrew as $bc) {
                $bc->existing_attendance = $existingAttByCrew->get($bc->crew_id);
            }
        }

        $allActiveCrew = DB::table('crew_members')
            ->where('status', 'active')->orderBy('last_name')
            ->selectRaw("crew_id, CONCAT(first_name,' ',last_name) AS name, phone")
            ->get();

        $attQuery = $this->filteredAttendanceQuery($request);

        $attTotal = (clone $attQuery)->count();
        $attPages = max(1, (int) ceil($attTotal / $perPage));

        $attendance = (clone $attQuery)
            ->join('crew_members as cm', 'ca.crew_id', '=', 'cm.crew_id')
            ->leftJoin('crew_positions as cp', 'cm.primary_position_id', '=', 'cp.position_id')
            ->join('bookings as b', 'ca.booking_id', '=', 'b.booking_id')
            ->leftJoin('crew_members as rep', 'ca.replacement_crew_id', '=', 'rep.crew_id')
            ->leftJoin('users as logger', 'ca.logged_by', '=', 'logger.user_id')
            ->orderByDesc('ca.attendance_date')->orderByDesc('ca.created_at')
            ->select('ca.*', DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS crew_name"), 'cm.phone as crew_phone',
                'cp.position_name', 'b.booking_reference', 'b.project_title',
                DB::raw("CONCAT(rep.first_name,' ',rep.last_name) AS replacement_name"),
                DB::raw("CONCAT(logger.first_name,' ',logger.last_name) AS logged_by_name"))
            ->forPage($page, $perPage)
            ->get();

        $timesheets = collect();
        if ($filterBooking || $filterCrew) {
            $tsQuery = DB::table('crew_timesheets as ct')
                ->join('crew_members as cm', 'ct.crew_id', '=', 'cm.crew_id')
                ->leftJoin('crew_positions as cp', 'cm.primary_position_id', '=', 'cp.position_id')
                ->join('bookings as b', 'ct.booking_id', '=', 'b.booking_id');
            if ($filterBooking) $tsQuery->where('ct.booking_id', $filterBooking);
            if ($filterCrew) $tsQuery->where('ct.crew_id', $filterCrew);
            $timesheets = $tsQuery
                ->orderByDesc('ct.timesheet_date')
                ->select('ct.*', DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS crew_name"), 'cp.position_name', 'b.booking_reference')
                ->limit(50)
                ->get();
        }

        $totalDeployed = (int) DB::table('crew_attendance')
            ->where('status', 'present')->where('attendance_date', now()->toDateString())
            ->distinct()->count('crew_id');
        $totalNoShows = (int) DB::table('crew_attendance')
            ->whereIn('status', ['no_show', 'back_out'])
            ->where('attendance_date', '>=', now()->subDays(30)->toDateString())
            ->count();
        $totalOT = (int) DB::table('crew_timesheets')
            ->where('overtime_hours', '>', 0)
            ->where('timesheet_date', '>=', now()->subDays(30)->toDateString())
            ->count();

        return view('attendance', [
            'msg' => $msg, 'canManage' => $canManage,
            'activeBookings' => $activeBookings, 'bookingCrew' => $bookingCrew, 'allActiveCrew' => $allActiveCrew,
            'attendance' => $attendance, 'attTotal' => $attTotal, 'attPages' => $attPages, 'page' => $page,
            'timesheets' => $timesheets,
            'filterBooking' => $filterBooking, 'filterCrew' => $filterCrew, 'filterDate' => $filterDate, 'filterStatus' => $filterStatus,
            'totalDeployed' => $totalDeployed, 'totalNoShows' => $totalNoShows, 'totalOT' => $totalOT,
            'statusBadge' => $this->statusBadge, 'statusLabel' => $this->statusLabel,
        ]);
    }

    /** Same filters index() applies, shared with export() so the two can never drift apart. */
    private function filteredAttendanceQuery(Request $request)
    {
        $filterBooking = (int) $request->query('booking_id', 0);
        $filterCrew = (int) $request->query('crew_id', 0);
        $filterDate = $request->query('date', '');
        $filterStatus = $request->query('status', '');

        $attQuery = DB::table('crew_attendance as ca');
        if ($filterBooking) $attQuery->where('ca.booking_id', $filterBooking);
        if ($filterCrew) $attQuery->where('ca.crew_id', $filterCrew);
        if ($filterDate) $attQuery->where('ca.attendance_date', $filterDate);
        if ($filterStatus) $attQuery->where('ca.status', $filterStatus);

        return $attQuery;
    }

    /** Export ▾ — reuses filteredAttendanceQuery() unbounded (no page/perPage) so it always matches what's on screen. */
    private function export(Request $request): StreamedResponse|Response
    {
        $headers = ['Date', 'Crew Member', 'Position', 'Booking', 'Status', 'Replacement', 'Logged By'];

        $rows = $this->filteredAttendanceQuery($request)
            ->join('crew_members as cm', 'ca.crew_id', '=', 'cm.crew_id')
            ->leftJoin('crew_positions as cp', 'cm.primary_position_id', '=', 'cp.position_id')
            ->join('bookings as b', 'ca.booking_id', '=', 'b.booking_id')
            ->leftJoin('crew_members as rep', 'ca.replacement_crew_id', '=', 'rep.crew_id')
            ->leftJoin('users as logger', 'ca.logged_by', '=', 'logger.user_id')
            ->orderByDesc('ca.attendance_date')->orderByDesc('ca.created_at')
            ->select('ca.attendance_date', DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS crew_name"),
                'cp.position_name', 'b.booking_reference', 'ca.status',
                DB::raw("CONCAT(rep.first_name,' ',rep.last_name) AS replacement_name"),
                DB::raw("CONCAT(logger.first_name,' ',logger.last_name) AS logged_by_name"))
            ->get()
            ->map(fn ($a) => [
                Carbon::parse($a->attendance_date)->format('M j, Y'),
                $a->crew_name,
                $a->position_name ?: '—',
                $a->booking_reference,
                $this->statusLabel[$a->status] ?? ucfirst($a->status),
                trim((string) $a->replacement_name) ?: '—',
                trim((string) $a->logged_by_name) ?: '—',
            ])
            ->all();

        return DataExporter::respond($request->query('export'), 'Attendance', $headers, $rows, 'attendance-export');
    }

    // Printable attendance record for one booking — staff-side counterpart to
    // CrewPortalController::printAttendance(), same view, no crew-ownership check since
    // any staff member with access to this page can already see every booking's attendance.
    public function print(Request $request): View|RedirectResponse
    {
        $bid = (int) $request->query('booking_id', 0);
        if (! $bid) {
            return redirect()->route('attendance');
        }

        $booking = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('b.booking_id', $bid)
            ->select('b.*', 'c.company_name', 'c.contact_person', 'c.client_type')
            ->first();
        if (! $booking) {
            return redirect()->route('attendance');
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

        $totalCrew = (int) DB::table('booking_crew')->where('booking_id', $bid)->where('assignment_status', '!=', 'declined')->count();
        $presentCount = $records->whereIn('status', ['present', 'late'])->count();
        $absentCount = $records->whereIn('status', ['absent', 'no_show'])->count();

        return view('attendance-print', [
            'booking' => $booking, 'bid' => $bid, 'records' => $records,
            'totalCrew' => $totalCrew, 'presentCount' => $presentCount, 'absentCount' => $absentCount,
            'attStatusLabel' => $this->statusLabel,
            'backUrl' => route('attendance', ['booking_id' => $bid]),
        ]);
    }

    private function handleAction(Request $request, bool $canManage): ?array
    {
        $action = $request->input('action', '');
        $uid = $request->user()->user_id;

        if ($action === 'log_attendance' && $canManage) {
            $bid = (int) $request->input('booking_id');
            $date = $request->input('attendance_date');
            $logged = 0;

            $shootRange = DB::table('bookings')->where('booking_id', $bid)->select('shoot_date_start', 'shoot_date_end')->first();
            if ($shootRange && ($date < $shootRange->shoot_date_start || $date > $shootRange->shoot_date_end)) {
                return ['type' => 'danger', 'text' => 'Attendance date must fall within this booking\'s shoot dates (' . $shootRange->shoot_date_start . ' to ' . $shootRange->shoot_date_end . ').'];
            }

            foreach ((array) $request->input('crew_status', []) as $cid => $status) {
                $cid = (int) $cid;
                $reason = $request->input("crew_reason.$cid", '');
                $repId = (int) $request->input("crew_replacement.$cid", 0);

                DB::table('crew_attendance')->updateOrInsert(
                    ['booking_id' => $bid, 'crew_id' => $cid, 'attendance_date' => $date],
                    ['status' => $status, 'reason' => $reason, 'replacement_crew_id' => $repId ?: null, 'logged_by' => $uid]
                );
                $logged++;
            }
            ActivityLog::record($uid, 'log_attendance', 'crew', "Logged $logged attendance records for booking #$bid");

            return ['type' => 'success', 'text' => "Attendance logged for <strong>$logged</strong> crew members."];
        }

        if ($action === 'log_timesheet' && $canManage) {
            $bid = (int) $request->input('booking_id');
            $cid = (int) $request->input('crew_id');
            $date = $request->input('timesheet_date');

            DB::table('crew_timesheets')->updateOrInsert(
                ['booking_id' => $bid, 'crew_id' => $cid, 'timesheet_date' => $date],
                [
                    'time_in' => $request->input('time_in') ?: null,
                    'time_out' => $request->input('time_out') ?: null,
                    'total_hours' => (float) $request->input('total_hours', 0),
                    'overtime_hours' => (float) $request->input('overtime_hours', 0),
                    'is_double_pay' => (int) $request->input('is_double_pay', 0),
                    'remarks' => $request->input('remarks', ''),
                ]
            );
            ActivityLog::record($uid, 'log_timesheet', 'crew', "Timesheet logged for crew #$cid");

            return ['type' => 'success', 'text' => 'Timesheet entry saved.'];
        }

        return null;
    }
}
