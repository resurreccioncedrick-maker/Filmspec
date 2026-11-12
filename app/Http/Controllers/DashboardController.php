<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $role = $request->user()->role->role_name ?? '';
        $canSeeFinancials = in_array('billing', config("filmspec.role_permissions.$role", []), true);

        $totalBookings = (int) DB::table('bookings')->where('booking_status', '!=', 'cancelled')->count();
        $activeBookings = (int) DB::table('bookings')->whereIn('booking_status', ['confirmed', 'ongoing'])->count();
        $pendingApprovalCount = (int) DB::table('bookings')->where('approval_status', 'pending_approval')->count();
        $totalRevenue = $canSeeFinancials ? (float) DB::table('payments')
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount') : null;
        $totalEquipment = (int) DB::table('equipment')->where('availability_status', '!=', 'retired')->count();
        $availableEquipment = (int) DB::table('equipment')->where('availability_status', 'available')->count();
        $rentedEquipment = (int) DB::table('equipment')->where('availability_status', 'rented')->count();
        $underMaintenance = (int) DB::table('equipment')->where('availability_status', 'under_repair')->count();
        $activeCrewCount = (int) DB::table('crew_members')->where('status', 'active')->count();
        $openIncidents = (int) DB::table('incident_reports')->where('status', 'open')->count();
        $upcomingCount = (int) DB::table('bookings')
            ->whereIn('booking_status', ['confirmed', 'ongoing'])
            ->whereBetween('shoot_date_start', [now(), now()->addDays(7)])
            ->count();

        $overdueReturns = (int) DB::table('bookings')
            ->where('booking_status', 'ongoing')
            ->where('shoot_date_end', '<', now()->toDateString())
            ->count();

        $unresolvedPaymentIssues = $canSeeFinancials ? (int) DB::table('statement_of_accounts as s')
            ->join('bookings as b', 's.booking_id', '=', 'b.booking_id')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('s.due_date', '<', now()->toDateString())
            ->where('s.status', '!=', 'paid')
            ->where('s.balance', '>', 0)
            ->count() : null;

        $today = now()->toDateString();
        $todayShoots = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->select('b.booking_id', 'b.booking_reference', 'b.project_title', 'b.shoot_date_start', 'b.shoot_date_end', 'b.booking_status', 'c.company_name', 'c.contact_person')
            ->whereIn('b.booking_status', ['confirmed', 'ongoing'])
            ->whereRaw('? BETWEEN b.shoot_date_start AND b.shoot_date_end', [$today])
            ->orderBy('b.shoot_date_start')
            ->get();

        $todayBookingIds = $todayShoots->pluck('booking_id');
        $unitsDueOutToday = (int) DB::table('booking_equipment as be')
            ->join('bookings as b', 'be.booking_id', '=', 'b.booking_id')
            ->where('b.booking_status', 'confirmed')
            ->where('b.shoot_date_start', $today)
            ->sum('be.quantity');
        $dueBackToday = (int) DB::table('booking_equipment as be')
            ->join('bookings as b', 'be.booking_id', '=', 'b.booking_id')
            ->where('b.booking_status', 'ongoing')
            ->where('b.shoot_date_end', $today)
            ->sum('be.quantity');
        $crewOnScheduleToday = $todayBookingIds->isEmpty() ? 0 : (int) DB::table('booking_crew')
            ->whereIn('booking_id', $todayBookingIds)->distinct('crew_id')->count('crew_id');

        $recentBookings = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->select('b.booking_id', 'b.booking_reference', 'b.project_title', 'b.shoot_date_start', 'b.booking_status', 'b.payment_status', 'b.approval_status', 'b.final_amount', 'c.company_name', 'c.contact_person')
            ->orderByDesc('b.created_at')
            ->limit(7)
            ->get();
        if (! $canSeeFinancials) {
            $recentBookings->each(fn ($b) => $b->final_amount = null);
        }

        $requestsAwaitingReview = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('b.approval_status', 'pending_approval')
            ->select('b.booking_id', 'b.booking_reference', 'b.project_title', 'b.created_at', 'c.company_name', 'c.contact_person')
            ->orderBy('b.created_at')
            ->limit(10)
            ->get();
        $requestsAwaitingReview->each(function ($r) {
            $r->client_name = $r->company_name ?: $r->contact_person;
            $r->days_waiting = (int) now()->diffInDays(\Carbon\Carbon::parse($r->created_at));
        });

        $upcomingConfirmed = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->select('b.booking_id', 'b.booking_reference', 'b.project_title', 'b.shoot_date_start', 'b.booking_status', 'c.company_name', 'c.contact_person')
            ->whereIn('b.booking_status', ['confirmed', 'ongoing'])
            ->whereBetween('b.shoot_date_start', [now(), now()->addDays(7)])
            ->orderBy('b.shoot_date_start')
            ->limit(10)
            ->get();
        $upcomingConfirmed->each(fn ($b) => $b->client_name = $b->company_name ?: $b->contact_person);

        $upcomingAgenda = $upcomingConfirmed
            ->groupBy(fn ($b) => \Carbon\Carbon::parse($b->shoot_date_start)->toDateString())
            ->map(fn ($group, $date) => ['date' => $date, 'count' => $group->count()])
            ->take(3)
            ->values();

        $financialTrend = collect();
        if ($canSeeFinancials) {
            $trendRows = DB::table('payments')
                ->where('payment_date', '>=', now()->subMonths(5)->startOfMonth())
                ->selectRaw("DATE_FORMAT(payment_date,'%Y-%m') AS sort_key, SUM(amount) AS total")
                ->groupBy('sort_key')
                ->get()->keyBy('sort_key');
            for ($i = 5; $i >= 0; $i--) {
                $m = now()->subMonths($i);
                $key = $m->format('Y-m');
                $financialTrend->push((object) [
                    'label' => $m->format('M'),
                    'total' => (float) ($trendRows[$key]->total ?? 0),
                ]);
            }
        }
        $prevMonthRevenue = $canSeeFinancials ? (float) DB::table('payments')
            ->whereMonth('payment_date', now()->subMonthNoOverflow()->month)
            ->whereYear('payment_date', now()->subMonthNoOverflow()->year)
            ->sum('amount') : 0.0;
        $revenueDeltaPct = ($canSeeFinancials && $prevMonthRevenue > 0)
            ? round((($totalRevenue - $prevMonthRevenue) / $prevMonthRevenue) * 100, 1) : null;

        $currentMonth = $request->query('month', now()->format('Y-m'));

        $calendarBookings = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->select('b.booking_id', 'b.booking_reference', 'b.project_title', 'b.shoot_date_start', 'b.shoot_date_end', 'b.booking_status', 'c.contact_person', 'c.company_name')
            ->whereIn('b.booking_status', ['confirmed', 'ongoing', 'pending'])
            ->orderBy('b.shoot_date_start')
            ->get();

        $rentedAll = DB::table('equipment as e')
            ->join('equipment_categories as ec', 'e.category_id', '=', 'ec.category_id')
            ->select('e.equipment_name', 'e.brand', 'ec.category_name')
            ->where('e.availability_status', 'rented')
            ->orderBy('e.equipment_name')
            ->limit(8)
            ->get();

        $recentActivity = DB::table('activity_logs as al')
            ->leftJoin('users as u', 'al.user_id', '=', 'u.user_id')
            ->leftJoin('roles as r', 'u.role_id', '=', 'r.role_id')
            ->select('al.action', 'al.module', 'al.description', 'al.created_at', DB::raw("CONCAT(u.first_name,' ',u.last_name) AS user_name"), 'u.first_name', 'r.role_name')
            ->orderByDesc('al.created_at')
            ->limit(10)
            ->get();

        $statusBadge = [
            'pending' => 'badge-yellow',
            'confirmed' => 'badge-blue',
            'ongoing' => 'badge-green',
            'completed' => 'badge-gray',
            'cancelled' => 'badge-red',
        ];
        $payBadge = [
            'unpaid' => 'badge-red',
            'partial' => 'badge-orange',
            'paid' => 'badge-green',
        ];

        $hour = (int) now()->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $dashUserName = $request->user()->first_name ?? 'there';

        $roleColors = [
            'super_admin' => '#7c3aed',
            'operations_manager' => '#16a34a',
            'traffic' => '#d97706',
            'accounting' => '#0891b2',
            'client' => '#94a3b8',
        ];

        return view('dashboard', compact(
            'totalBookings', 'activeBookings', 'pendingApprovalCount', 'totalRevenue',
            'totalEquipment', 'availableEquipment', 'rentedEquipment', 'underMaintenance', 'activeCrewCount',
            'openIncidents', 'upcomingCount', 'overdueReturns', 'todayShoots', 'recentBookings', 'currentMonth',
            'calendarBookings', 'rentedAll', 'recentActivity', 'statusBadge', 'payBadge',
            'greeting', 'dashUserName', 'roleColors', 'canSeeFinancials',
            'unitsDueOutToday', 'dueBackToday', 'crewOnScheduleToday',
            'requestsAwaitingReview', 'upcomingConfirmed', 'financialTrend', 'revenueDeltaPct', 'upcomingAgenda',
            'unresolvedPaymentIssues'
        ));
    }
}
