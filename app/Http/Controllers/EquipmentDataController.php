<?php

namespace App\Http\Controllers;

use App\Support\CeAnalytics;
use App\Support\DataExporter;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EquipmentDataController extends Controller
{
    private const DEFAULT_LIMIT = 15;

    public function index(Request $request): View|StreamedResponse|Response
    {
        if ($request->filled('export')) {
            return $this->export($request);
        }

        $period = ReportPeriod::resolve($request);
        $sort = in_array($request->query('sort'), ['pesos', 'quantity', 'days'], true)
            ? $request->query('sort') : 'pesos';
        $showAll = (bool) $request->query('all', false);

        $confirmedCes = CeAnalytics::confirmedCes($period['from'], $period['to']);
        $bookingIds = $confirmedCes->pluck('booking_id');
        $usage = $this->computeUsage($period, $sort, $bookingIds);

        $usageTotalCount = $usage->count();
        $usageShown = $showAll ? $usage : $usage->take(self::DEFAULT_LIMIT);

        // Grouped by category for display, preserving the chosen sort inside each group and
        // ordering groups by their own total so the biggest category leads.
        $byCategory = $usageShown->groupBy(fn ($r) => $r->category_name ?: 'Other')
            ->map(fn ($rows) => (object) [
                'rows' => $rows->values(),
                'earnings' => (float) $rows->sum('earnings'),
                'qty' => (int) $rows->sum('total_qty'),
            ])
            ->sortByDesc('earnings');

        $neverUsed = DB::table('equipment as e')
            ->leftJoin('equipment_categories as ec', 'e.category_id', '=', 'ec.category_id')
            ->whereNotIn('e.equipment_id', $usage->pluck('equipment_id'))
            ->where('e.condition_status', '!=', 'retired')
            ->orderBy('e.equipment_name')
            ->select('e.equipment_id', 'e.equipment_name', 'e.brand', 'ec.category_name')
            ->get();

        // Bookings on the calendar with no confirmed CE — the reference calls these out so
        // staff know why the numbers don't cover everything.
        $missingCe = DB::table('bookings as b')
            ->whereNotIn('b.booking_id', $bookingIds)
            ->whereNotIn('b.booking_status', ['cancelled'])
            ->whereBetween('b.shoot_date_start', [substr($period['from'], 0, 10), substr($period['to'], 0, 10)])
            ->orderBy('b.shoot_date_start')
            ->select('b.booking_id', 'b.booking_reference', 'b.project_title')
            ->get();

        $pastShoots = $this->pastShoots($confirmedCes, $bookingIds);

        // Earnings trend — same shape as Cost Estimates' and Crew Data's recap charts: the
        // chart window is independent of the table period (see ReportPeriod), so an August
        // table can sit beside a Mar–Aug trend without those two ranges fighting each other.
        $chartMonths = $period['chart_months'];
        $chartKeys = ReportPeriod::chartMonthKeys($chartMonths);
        $chartCes = CeAnalytics::confirmedCes(
            ReportPeriod::chartFrom($chartMonths)->toDateTimeString(),
            now()->endOfDay()->toDateTimeString()
        );
        $chartUsage = DB::table('booking_equipment as be')
            ->whereIn('be.booking_id', $chartCes->pluck('booking_id'))
            ->select('be.booking_id')
            ->selectRaw('SUM(IF(be.subtotal>0,be.subtotal,be.quantity*be.days*be.daily_rate)) AS earnings')
            ->groupBy('be.booking_id')
            ->get();
        $monthly = collect($chartKeys)->map(function ($key) use ($chartCes, $chartUsage) {
            $ids = $chartCes->filter(fn ($c) => \Illuminate\Support\Carbon::parse($c->confirmed_at)->format('Y-m') === $key)
                ->pluck('booking_id');

            return (object) [
                'sort_key' => $key,
                'label' => \Illuminate\Support\Carbon::createFromFormat('Y-m', $key)->format('M Y'),
                'earnings' => (float) $chartUsage->whereIn('booking_id', $ids)->sum('earnings'),
            ];
        });

        // "vs last period" pills on Earned/Shoots — not Distinct Items Used, which is a catalog
        // breadth count more than a trend anyone tracks period over period.
        $prevPeriod = ReportPeriod::previous($period);
        $equipDeltas = ['fs_earned' => null, 'shoots' => null];
        if ($prevPeriod) {
            $prevBookingIds = CeAnalytics::confirmedCes($prevPeriod['from'], $prevPeriod['to'])->pluck('booking_id');
            $prevEarned = (float) DB::table('booking_equipment')
                ->whereIn('booking_id', $prevBookingIds)
                ->selectRaw('SUM(IF(subtotal>0,subtotal,quantity*days*daily_rate)) AS earnings')
                ->value('earnings');
            $equipDeltas['fs_earned'] = ReportPeriod::delta((float) $usage->sum('earnings'), $prevEarned);
            $equipDeltas['shoots'] = ReportPeriod::delta($bookingIds->count(), $prevBookingIds->count());
        }

        return view('equipment-data', [
            'period' => $period, 'sort' => $sort, 'showAll' => $showAll,
            'pastShoots' => $pastShoots,
            'shootTotals' => [
                'shoots' => $pastShoots->count(),
                'matched' => (int) $pastShoots->sum('matched_count'),
            ],
            'byCategory' => $byCategory, 'usageTotalCount' => $usageTotalCount,
            'shownCount' => $usageShown->count(), 'defaultLimit' => self::DEFAULT_LIMIT,
            'neverUsed' => $neverUsed,
            'missingCe' => $missingCe,
            'monthly' => $monthly,
            'topEquipment' => $usage->take(8)->values(),
            'equipDeltas' => $equipDeltas,
            'kpis' => [
                'shoots' => $bookingIds->count(),
                'fs_earned' => (float) $usage->sum('earnings'),
                'chart_months' => $chartMonths,
            ],
        ]);
    }

    /**
     * Owned gear: what each item earned at CE rates, plus the quantity and day counts the
     * reference shows as "×16 · 5d". Shared by index() and export() so both stay identical.
     */
    private function computeUsage(array $period, string $sort, $bookingIds)
    {
        $usage = DB::table('booking_equipment as be')
            ->join('equipment as e', 'be.equipment_id', '=', 'e.equipment_id')
            ->leftJoin('equipment_categories as ec', 'e.category_id', '=', 'ec.category_id')
            ->whereIn('be.booking_id', $bookingIds)
            ->groupBy('e.equipment_id', 'e.equipment_name', 'e.brand', 'ec.category_name')
            ->select('e.equipment_id', 'e.equipment_name', 'e.brand', 'ec.category_name')
            ->selectRaw('COUNT(DISTINCT be.booking_id) AS shoots')
            ->selectRaw('SUM(be.quantity) AS total_qty')
            ->selectRaw('SUM(be.days) AS total_days')
            ->selectRaw('SUM(IF(be.subtotal>0,be.subtotal,be.quantity*be.days*be.daily_rate)) AS earnings')
            ->get();

        $sortKey = ['pesos' => 'earnings', 'quantity' => 'total_qty', 'days' => 'total_days'][$sort];

        return $usage->sortByDesc(fn ($r) => (float) $r->{$sortKey})->values();
    }

    /** Export ▾ — always the full unbounded usage list, same as "Show all" on screen. */
    private function export(Request $request): StreamedResponse|Response
    {
        $period = ReportPeriod::resolve($request);
        $sort = in_array($request->query('sort'), ['pesos', 'quantity', 'days'], true)
            ? $request->query('sort') : 'pesos';

        $bookingIds = CeAnalytics::confirmedCes($period['from'], $period['to'])->pluck('booking_id');
        $usage = $this->computeUsage($period, $sort, $bookingIds);

        $headers = ['Equipment', 'Category', 'Brand', 'Shoots', 'Total Qty', 'Total Days', 'Earnings'];
        $rows = $usage->map(fn ($u) => [
            $u->equipment_name, $u->category_name ?: 'Other', $u->brand,
            (int) $u->shoots, (int) $u->total_qty, (int) $u->total_days,
            '₱' . number_format((float) $u->earnings, 2),
        ])->all();

        return DataExporter::respond($request->query('export'), 'Equipment Data', $headers, $rows, 'equipment-data-export');
    }

    /**
     * One card per confirmed shoot: what the CE matched to the catalog.
     *
     * FS equipment lines carry an equipment_id foreign key so they are matched by
     * construction.
     *
     * One grouped query, not one per shoot.
     */
    private function pastShoots($confirmedCes, $bookingIds)
    {
        if ($bookingIds->isEmpty()) {
            return collect();
        }

        $equipByBooking = DB::table('booking_equipment as be')
            ->join('equipment as e', 'be.equipment_id', '=', 'e.equipment_id')
            ->whereIn('be.booking_id', $bookingIds)
            ->select('be.booking_id', 'be.quantity', 'be.days', 'e.equipment_name', 'e.brand')
            ->get()->groupBy('booking_id');

        $ceTypeLabels = ['fs_front' => 'FS FRONT', 'client_direct' => 'CLIENT DIRECT', 'partner_front' => 'PARTNER FRONT'];
        $bookingMeta = DB::table('bookings')->whereIn('booking_id', $bookingIds)
            ->select('booking_id', 'ce_type', 'ce_director_dop')->get()->keyBy('booking_id');

        return $confirmedCes->map(function ($ce) use ($equipByBooking, $ceTypeLabels, $bookingMeta) {
            $meta = $bookingMeta[$ce->booking_id] ?? null;
            $matched = $equipByBooking->get($ce->booking_id, collect());

            return (object) [
                'booking_id' => $ce->booking_id,
                'ce_id' => $ce->ce_id,
                'ce_reference' => $ce->ce_reference,
                'project_title' => $ce->project_title ?: $ce->booking_reference,
                'client_name' => $ce->client_name,
                'director' => $meta->ce_director_dop ?? null,
                'ce_type_label' => $ceTypeLabels[$meta->ce_type ?? 'fs_front'] ?? 'FS FRONT',
                'shoot_date_start' => $ce->shoot_date_start,
                'shoot_date_end' => $ce->shoot_date_end,
                'confirmed_at' => $ce->confirmed_at,
                'matched' => $matched->values(),
                'matched_count' => $matched->count(),
            ];
        })->values();
    }
}
