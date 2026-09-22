<?php

namespace App\Http\Controllers;

use App\Support\DataExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function index(Request $request): View|StreamedResponse|Response
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $dbFrom = $dateFrom;
        $dbTo = $dateTo . ' 23:59:59';

        $revenueData = DB::table('payments')
            ->whereBetween('payment_date', [$dbFrom, $dbTo])
            ->selectRaw("DATE_FORMAT(payment_date,'%b %d') AS label, DATE_FORMAT(payment_date,'%Y-%m-%d') AS sort_key, SUM(amount) AS total, COUNT(*) AS transactions")
            ->groupBy('sort_key', 'label')->orderBy('sort_key')
            ->get();

        $bookingsByType = DB::table('bookings')
            ->where('booking_status', '!=', 'cancelled')
            ->whereBetween('created_at', [$dbFrom, $dbTo])
            ->selectRaw('project_type, COUNT(*) AS total')
            ->groupBy('project_type')->orderByDesc('total')
            ->get();
        $totalBookingsByType = $bookingsByType->sum('total') ?: 1;

        $topEquipment = DB::table('booking_equipment as be')
            ->join('equipment as e', 'be.equipment_id', '=', 'e.equipment_id')
            ->join('bookings as b', 'be.booking_id', '=', 'b.booking_id')
            ->whereIn('b.booking_status', ['completed', 'ongoing'])
            ->whereBetween('b.created_at', [$dbFrom, $dbTo])
            ->groupBy('e.equipment_id', 'e.equipment_name', 'e.brand')
            ->orderByDesc('rental_count')
            ->select('e.equipment_name', 'e.brand')
            ->selectRaw('COUNT(be.bk_equip_id) AS rental_count')
            ->selectRaw('SUM(IF(be.subtotal>0, be.subtotal, be.quantity*be.days*be.daily_rate)) AS total_revenue')
            ->limit(10)
            ->get();

        $crewPerf = DB::table('crew_members as cm')
            ->leftJoin('crew_positions as cp', 'cm.primary_position_id', '=', 'cp.position_id')
            ->leftJoin('crew_attendance as ca', 'cm.crew_id', '=', 'ca.crew_id')
            ->where('cm.status', 'active')
            ->groupBy('cm.crew_id', 'cm.first_name', 'cm.last_name', 'cp.position_name', 'cm.total_shoots')
            ->orderByDesc('cm.total_shoots')
            ->select('cm.first_name', 'cm.last_name', 'cp.position_name', 'cm.total_shoots')
            ->selectRaw("COALESCE(SUM(ca.status='present'),0) AS present")
            ->selectRaw("COALESCE(SUM(ca.status IN ('no_show','back_out')),0) AS no_shows")
            ->limit(10)
            ->get();

        // 'Sales' and 'Payments Collected' are deliberately two different figures from two
        // different tables — sales is billed booking value (what was contracted), collected is
        // actual cash received (from the payments table). Before this fix, "Period Sales" was
        // silently SUM(payments.amount) — the exact same query as "Payment Collection" below,
        // just grouped differently — so the two labels always showed the same number with no
        // real "sales" concept behind either of them.
        $kpis = [
            'period_sales' => (float) DB::table('bookings')
                ->where('booking_status', '!=', 'cancelled')
                ->whereBetween('created_at', [$dbFrom, $dbTo])->sum('final_amount'),
            'payments_collected' => (float) DB::table('payments')->whereBetween('payment_date', [$dbFrom, $dbTo])->sum('amount'),
            'total_revenue' => (float) DB::table('payments')->sum('amount'),
            'total_bookings' => (int) DB::table('bookings')->whereBetween('created_at', [$dbFrom, $dbTo])->count(),
            'completed' => (int) DB::table('bookings')->where('booking_status', 'completed')->whereBetween('created_at', [$dbFrom, $dbTo])->count(),
            'outstanding_bal' => (float) DB::table('statement_of_accounts')->where('status', '!=', 'paid')->sum('balance'),
            'open_incidents' => (int) DB::table('incident_reports')->where('status', 'open')->count(),
        ];

        $equipAvail = [
            'available' => (int) DB::table('equipment')->where('availability_status', 'available')->count(),
            'booked' => (int) DB::table('equipment')->where('availability_status', 'booked')->count(),
            'rented' => (int) DB::table('equipment')->where('availability_status', 'rented')->count(),
            'under_repair' => (int) DB::table('equipment')->where('availability_status', 'under_repair')->count(),
            'retired' => (int) DB::table('equipment')->where('availability_status', 'retired')->count(),
        ];
        $availTotal = array_sum($equipAvail) ?: 1;

        $topClients = DB::table('clients as c')
            ->leftJoin('bookings as b', function ($j) use ($dbFrom, $dbTo) {
                $j->on('b.client_id', '=', 'c.client_id')->whereBetween('b.created_at', [$dbFrom, $dbTo]);
            })
            ->groupBy('c.client_id', 'c.company_name', 'c.contact_person', 'c.client_type')
            ->orderByDesc('completed_count')->orderByDesc('total_spend')
            ->select('c.client_id', 'c.company_name', 'c.contact_person', 'c.client_type')
            ->selectRaw("COUNT(CASE WHEN b.booking_status='completed' THEN 1 END) AS completed_count")
            ->selectRaw('COUNT(b.booking_id) AS total_bookings')
            ->selectRaw("COALESCE(SUM(CASE WHEN b.booking_status='completed' THEN b.final_amount ELSE 0 END),0) AS total_spend")
            ->limit(10)
            ->get();

        $damagedReport = DB::table('incident_reports as ir')
            ->join('equipment as e', 'ir.equipment_id', '=', 'e.equipment_id')
            ->join('bookings as b', 'ir.booking_id', '=', 'b.booking_id')
            ->leftJoin('users as u', 'ir.reported_by', '=', 'u.user_id')
            ->whereIn('ir.incident_type', ['damaged', 'missing'])
            ->whereBetween('ir.incident_date', [$dbFrom, $dbTo])
            ->orderByDesc('ir.incident_date')
            ->select('ir.*', 'e.equipment_name', 'e.brand', 'b.booking_reference')
            ->selectRaw("CONCAT(u.first_name,' ',u.last_name) AS reported_by_name")
            ->limit(20)
            ->get();

        $collectionData = DB::table('payments')
            ->whereBetween('payment_date', [$dbFrom, $dbTo])
            ->selectRaw("DATE_FORMAT(payment_date,'%b %Y') AS label, DATE_FORMAT(payment_date,'%Y-%m') AS sort_key, SUM(amount) AS collected, COUNT(*) AS transactions")
            ->groupBy('sort_key', 'label')->orderBy('sort_key')
            ->get();

        $feedbackStats = DB::table('booking_feedback')
            ->selectRaw('COUNT(*) AS total, AVG(rating) AS avg_rating')
            ->selectRaw('SUM(rating=5) AS five_star, SUM(rating=4) AS four_star')
            ->selectRaw('SUM(rating=3) AS three_star, SUM(rating<=2) AS low_star')
            ->first();

        $recentFeedback = DB::table('booking_feedback as bf')
            ->join('bookings as b', 'bf.booking_id', '=', 'b.booking_id')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->orderByDesc('bf.submitted_at')
            ->select('bf.*', 'b.booking_reference', 'c.company_name', 'c.contact_person')
            ->limit(8)
            ->get();

        [$comparePreset, $compFrom, $compTo, $compareRevenueData] = $this->resolveComparison($request, $dateFrom, $dateTo);

        if ($request->has('export')) {
            return $this->export($request->query('export'), $request->query('format', 'csv'), compact(
                'revenueData', 'collectionData', 'bookingsByType', 'totalBookingsByType',
                'topEquipment', 'equipAvail', 'crewPerf', 'topClients', 'damagedReport'
            ));
        }

        return view('reports', [
            'dateFrom' => $dateFrom, 'dateTo' => $dateTo,
            'preset' => $request->query('preset', ''), 'compareActive' => $comparePreset,
            'compFrom' => $compFrom, 'compTo' => $compTo,
            'revenueData' => $revenueData, 'compareRevenueData' => $compareRevenueData,
            'bookingsByType' => $bookingsByType, 'totalBookingsByType' => $totalBookingsByType,
            'topEquipment' => $topEquipment, 'crewPerf' => $crewPerf, 'kpis' => $kpis,
            'equipAvail' => $equipAvail, 'availTotal' => $availTotal, 'topClients' => $topClients,
            'damagedReport' => $damagedReport, 'collectionData' => $collectionData,
            'feedbackStats' => $feedbackStats, 'recentFeedback' => $recentFeedback,
        ]);
    }

    private function resolveDateRange(Request $request): array
    {
        $preset = $request->query('preset', '');
        $dateFrom = $request->query('date_from', '');
        $dateTo = $request->query('date_to', '');

        if ($preset === '30d') {
            $dateFrom = now()->subDays(30)->toDateString();
            $dateTo = now()->toDateString();
        } elseif ($preset === '3m') {
            $dateFrom = now()->subMonths(3)->toDateString();
            $dateTo = now()->toDateString();
        } elseif ($preset === '6m') {
            $dateFrom = now()->subMonths(6)->toDateString();
            $dateTo = now()->toDateString();
        } elseif ($preset === 'year') {
            $dateFrom = now()->startOfYear()->toDateString();
            $dateTo = now()->toDateString();
        }

        if (! $dateFrom) $dateFrom = now()->subMonths(6)->toDateString();
        if (! $dateTo) $dateTo = now()->toDateString();

        return [$dateFrom, $dateTo];
    }

    private function resolveComparison(Request $request, string $dateFrom, string $dateTo): array
    {
        $comparePreset = $request->query('compare', '');
        $compFrom = $compTo = '';

        if ($comparePreset === 'prev') {
            $diff = strtotime($dateTo) - strtotime($dateFrom);
            $compTo = date('Y-m-d', strtotime($dateFrom) - 86400);
            $compFrom = date('Y-m-d', strtotime($compTo) - $diff);
        } elseif ($comparePreset === 'year') {
            $compFrom = date('Y-m-d', strtotime($dateFrom . ' -1 year'));
            $compTo = date('Y-m-d', strtotime($dateTo . ' -1 year'));
        }

        $compareRevenueData = collect();
        if ($compFrom && $compTo) {
            $compareRevenueData = DB::table('payments')
                ->whereBetween('payment_date', [$compFrom, $compTo . ' 23:59:59'])
                ->selectRaw("DATE_FORMAT(payment_date,'%b %d') AS label, DATE_FORMAT(payment_date,'%Y-%m-%d') AS sort_key, SUM(amount) AS total")
                ->groupBy('sort_key', 'label')->orderBy('sort_key')
                ->get();
        }

        return [$comparePreset, $compFrom, $compTo, $compareRevenueData];
    }

    private function export(string $type, string $format, array $data): StreamedResponse|Response
    {
        [$title, $headers, $rows] = match ($type) {
            'collection' => ['Collection', ['Month', 'Payments', 'Collected (PHP)'],
                $data['collectionData']->map(fn ($r) => [$r->label, $r->transactions, $r->collected])->all()],
            'bookings' => ['Bookings by Type', ['Project Type', 'Count', 'Share %'],
                $data['bookingsByType']->map(fn ($bt) => [ucfirst(str_replace('_', ' ', $bt->project_type)), $bt->total, round($bt->total / $data['totalBookingsByType'] * 100, 1)])->all()],
            'equipment' => ['Top Equipment', ['Equipment', 'Brand', 'Rentals', 'Total Revenue (PHP)'],
                $data['topEquipment']->map(fn ($te) => [$te->equipment_name, $te->brand ?? '', $te->rental_count, $te->total_revenue])->all()],
            'availability' => ['Equipment Availability', ['Status', 'Count'],
                collect(['Available' => $data['equipAvail']['available'], 'Booked' => $data['equipAvail']['booked'], 'In Use' => $data['equipAvail']['rented'], 'Under Repair' => $data['equipAvail']['under_repair'], 'Retired' => $data['equipAvail']['retired']])
                    ->map(fn ($v, $k) => [$k, $v])->values()->all()],
            'crew' => ['Crew Performance', ['Name', 'Position', 'Total Shoots', 'No Shows'],
                $data['crewPerf']->map(fn ($cp) => [$cp->first_name . ' ' . $cp->last_name, $cp->position_name ?? '', $cp->total_shoots, $cp->no_shows])->all()],
            'clients' => ['Top Clients', ['Client', 'Type', 'Completed', 'All Bookings', 'Spend (PHP)'],
                $data['topClients']->map(fn ($tc) => [$tc->company_name ?: $tc->contact_person, $tc->client_type, $tc->completed_count, $tc->total_bookings, $tc->total_spend])->all()],
            'incidents' => ['Incidents Report', ['Date', 'Equipment', 'Booking', 'Type', 'Charge (PHP)', 'Status'],
                $data['damagedReport']->map(fn ($dr) => [date('Y-m-d', strtotime($dr->incident_date)), $dr->equipment_name, $dr->booking_reference, $dr->incident_type, $dr->charge_amount, $dr->status])->all()],
            // 'ce_financials' moved to CostEstimatesController::exportFinancials() in Part 11.
            default => ['Daily Payments Trend', ['Date', 'Transactions', 'Payments Collected (PHP)'],
                $data['revenueData']->map(fn ($r) => [$r->label, $r->transactions, $r->total])->all()],
        };

        return DataExporter::respond($format, $title, $headers, $rows, 'filmspec-' . $type);
    }
}
