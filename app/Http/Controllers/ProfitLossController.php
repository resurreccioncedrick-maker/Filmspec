<?php

namespace App\Http\Controllers;

use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Profit & Loss (Part 13).
 *
 * Basis, decided with the user: revenue is CASH — payments actually received in the period.
 * Costs are attributed to the SHOOT they belong to, because we don't record when crew and
 * partners were paid. That means a job shot in August but paid in September puts its revenue
 * in September and its costs in August; the page says so plainly rather than hiding it.
 *
 * `crew_salary_log` (pay_date + amount) is the right long-term source for true crew cash-out
 * and is currently empty — when it's in use, crew cost can move onto a real payment date.
 */
class ProfitLossController extends Controller
{
    public function index(Request $request): View|StreamedResponse
    {
        $period = ReportPeriod::resolve($request);
        $from = substr($period['from'], 0, 10);
        $to = substr($period['to'], 0, 10);

        $pl = $this->statement($from, $to);

        // Monthly trend over the chart window, independent of the table period (Part 11).
        $months = ReportPeriod::chartMonthKeys($period['chart_months']);
        $monthly = collect($months)->map(function ($key) {
            $start = Carbon::createFromFormat('Y-m-d', $key . '-01')->startOfMonth();
            $row = $this->statement($start->toDateString(), $start->copy()->endOfMonth()->toDateString());

            return (object) [
                'label' => $start->format('M Y'),
                'revenue' => $row['revenue_total'],
                'costs' => $row['direct_total'] + $row['opex_total'],
                'net' => $row['net_profit'],
            ];
        });

        if ($request->query('export') === 'pl') {
            return $this->exportCsv($pl, $monthly, $period['label']);
        }

        // "vs last period" pills on Revenue and Net Profit — not the margin percentages, which
        // are already a comparison (cost relative to revenue) in their own right.
        $prevPeriod = ReportPeriod::previous($period);
        $plDeltas = ['revenue_total' => null, 'net_profit' => null];
        if ($prevPeriod) {
            $prevPl = $this->statement(substr($prevPeriod['from'], 0, 10), substr($prevPeriod['to'], 0, 10));
            $plDeltas['revenue_total'] = ReportPeriod::delta($pl['revenue_total'], $prevPl['revenue_total']);
            $plDeltas['net_profit'] = ReportPeriod::delta($pl['net_profit'], $prevPl['net_profit']);
        }

        return view('profit-loss', [
            'period' => $period, 'pl' => $pl, 'monthly' => $monthly, 'plDeltas' => $plDeltas,
        ]);
    }

    /** The whole statement for one date window. */
    private function statement(string $from, string $to): array
    {
        $toEnd = $to . ' 23:59:59';

        // ── Revenue: cash actually received ──────────────────────────────────────────
        $payments = DB::table('payments')
            ->whereBetween('payment_date', [$from, $to])
            ->selectRaw('payment_type, COUNT(*) AS n, COALESCE(SUM(amount),0) AS total')
            ->groupBy('payment_type')->get();

        // Build the breakdown from the rows that actually exist, not a fixed list of the three
        // enum values: this data has payments with an empty payment_type, and a hardcoded list
        // silently dropped them — every line showed ₱0.00 while the total showed the real
        // figure. A statement whose lines don't add up to its total is worse than useless.
        $typeLabels = ['downpayment' => 'Downpayments', 'progress' => 'Progress payments', 'final' => 'Final payments'];
        $revenueByType = [];
        foreach ($payments as $row) {
            $key = (string) ($row->payment_type ?? '');
            $revenueByType[] = [
                'label' => $typeLabels[$key] ?? ($key !== '' ? ucfirst($key) : 'Unclassified payments'),
                'count' => (int) $row->n,
                'amount' => (float) $row->total,
            ];
        }
        // Keep the familiar order, with anything unrecognised last.
        $order = array_keys($typeLabels);
        usort($revenueByType, function ($a, $b) use ($typeLabels, $order) {
            $ia = array_search(array_search($a['label'], $typeLabels, true), $order, true);
            $ib = array_search(array_search($b['label'], $typeLabels, true), $order, true);

            return ($ia === false ? PHP_INT_MAX : $ia) <=> ($ib === false ? PHP_INT_MAX : $ib);
        });

        $revenueTotal = (float) $payments->sum('total');

        // ── Direct costs: attributed to shoots falling in the window ─────────────────
        $shootIds = DB::table('bookings')
            ->whereBetween('shoot_date_start', [$from, $to])
            ->where('booking_status', '!=', 'cancelled')
            ->pluck('booking_id');

        $crewCost = 0.0;
        $transportCost = 0.0;

        if ($shootIds->isNotEmpty()) {
            $crewCost = (float) DB::table('booking_crew')->whereIn('booking_id', $shootIds)
                ->selectRaw('COALESCE(SUM(rate_used * hours_worked),0) AS t')->value('t');
            $transportCost = (float) DB::table('bookings')->whereIn('booking_id', $shootIds)
                ->sum('transportation_cost');
        }

        $directTotal = $crewCost + $transportCost;

        // ── Operating expenses ──────────────────────────────────────────────────────
        $repairSpend = (float) DB::table('repair_purchase_tickets')
            ->where('status', 'completed')
            ->whereNotNull('completed_date')
            ->whereBetween('completed_date', [$from, $to])
            ->sum('actual_cost');

        $opexTotal = $repairSpend;

        $grossProfit = $revenueTotal - $directTotal;
        $netProfit = $grossProfit - $opexTotal;
        $divisor = $revenueTotal ?: 1;   // avoid divide-by-zero in an empty period

        return [
            'from' => $from, 'to' => $to,
            'revenue_by_type' => $revenueByType,
            'revenue_total' => $revenueTotal,
            'payment_count' => (int) $payments->sum('n'),
            'crew_cost' => $crewCost,
            'transport_cost' => $transportCost,
            'direct_total' => $directTotal,
            'gross_profit' => $grossProfit,
            'gross_margin' => round($grossProfit / $divisor * 100, 1),
            'repair_spend' => $repairSpend,
            'opex_total' => $opexTotal,
            'net_profit' => $netProfit,
            'net_margin' => round($netProfit / $divisor * 100, 1),
            'shoot_count' => $shootIds->count(),
        ];
    }

    private function exportCsv(array $pl, $monthly, string $label): StreamedResponse
    {
        return response()->streamDownload(function () use ($pl, $monthly, $label) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['PROFIT & LOSS', $label]);
            fputcsv($out, ['Basis', 'Revenue = payments received; costs attributed to the shoot']);
            fputcsv($out, ['Period', $pl['from'] . ' to ' . $pl['to']]);
            fputcsv($out, []);
            fputcsv($out, ['REVENUE']);
            foreach ($pl['revenue_by_type'] as $r) {
                fputcsv($out, [$r['label'], $r['count'], $r['amount']]);
            }
            fputcsv($out, ['Total revenue', '', $pl['revenue_total']]);
            fputcsv($out, []);
            fputcsv($out, ['DIRECT COSTS']);
            fputcsv($out, ['Crew talent fees', '', $pl['crew_cost']]);
            fputcsv($out, ['Transportation', '', $pl['transport_cost']]);
            fputcsv($out, ['Total direct costs', '', $pl['direct_total']]);
            fputcsv($out, []);
            fputcsv($out, ['Gross profit', $pl['gross_margin'] . '%', $pl['gross_profit']]);
            fputcsv($out, []);
            fputcsv($out, ['OPERATING EXPENSES']);
            fputcsv($out, ['Repairs & purchases', '', $pl['repair_spend']]);
            fputcsv($out, ['Total operating expenses', '', $pl['opex_total']]);
            fputcsv($out, []);
            fputcsv($out, ['NET PROFIT', $pl['net_margin'] . '%', $pl['net_profit']]);
            fputcsv($out, []);
            fputcsv($out, ['MONTHLY TREND']);
            fputcsv($out, ['Month', 'Revenue', 'Costs', 'Net']);
            foreach ($monthly as $m) {
                fputcsv($out, [$m->label, $m->revenue, $m->costs, $m->net]);
            }
            fclose($out);
        }, 'profit-loss-' . date('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
