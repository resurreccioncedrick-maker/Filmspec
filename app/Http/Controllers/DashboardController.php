<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $totalBookings = (int) DB::table('bookings')->where('booking_status', '!=', 'cancelled')->count();
        $activeBookings = (int) DB::table('bookings')->whereIn('booking_status', ['confirmed', 'ongoing'])->count();
        $pendingApprovalCount = (int) DB::table('bookings')->where('approval_status', 'pending_approval')->count();
        $totalRevenue = (float) DB::table('payments')
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');
        $totalEquipment = (int) DB::table('equipment')->where('availability_status', '!=', 'retired')->count();
        $availableEquipment = (int) DB::table('equipment')->where('availability_status', 'available')->count();
        $rentedEquipment = (int) DB::table('equipment')->where('availability_status', 'rented')->count();
        $activeCrewCount = (int) DB::table('crew_members')->where('status', 'active')->count();
        $openIncidents = (int) DB::table('incident_reports')->where('status', 'open')->count();
        $upcomingCount = (int) DB::table('bookings')
            ->whereIn('booking_status', ['confirmed', 'ongoing'])
            ->whereBetween('shoot_date_start', [now(), now()->addDays(7)])
            ->count();

        $today = now()->toDateString();
        $todayShoots = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->select('b.booking_id', 'b.booking_reference', 'b.project_title', 'b.shoot_date_start', 'b.shoot_date_end', 'b.booking_status', 'c.company_name', 'c.contact_person')
            ->whereIn('b.booking_status', ['confirmed', 'ongoing'])
            ->whereRaw('? BETWEEN b.shoot_date_start AND b.shoot_date_end', [$today])
            ->orderBy('b.shoot_date_start')
            ->get();

        $recentBookings = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->select('b.booking_id', 'b.booking_reference', 'b.project_title', 'b.shoot_date_start', 'b.booking_status', 'b.payment_status', 'b.approval_status', 'b.final_amount', 'c.company_name', 'c.contact_person')
            ->orderByDesc('b.created_at')
            ->limit(7)
            ->get();

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
            'admin' => '#0060C7',
            'operations_manager' => '#16a34a',
            'traffic' => '#d97706',
            'accounting' => '#0891b2',
            'client' => '#94a3b8',
        ];

        return view('dashboard', compact(
            'totalBookings', 'activeBookings', 'pendingApprovalCount', 'totalRevenue',
            'totalEquipment', 'availableEquipment', 'rentedEquipment', 'activeCrewCount',
            'openIncidents', 'upcomingCount', 'todayShoots', 'recentBookings', 'currentMonth',
            'calendarBookings', 'rentedAll', 'recentActivity', 'statusBadge', 'payBadge',
            'greeting', 'dashUserName', 'roleColors'
        ));
    }
}
