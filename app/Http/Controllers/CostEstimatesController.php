<?php

namespace App\Http\Controllers;

use App\Support\CeAnalytics;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CostEstimatesController extends Controller
{
    private array $statusBadge = [
        'draft' => 'badge-gray', 'submitted' => 'badge-blue', 'approved' => 'badge-green',
        'revised' => 'badge-orange', 'confirmed' => 'badge-green',
    ];

    public function index(Request $request): View|StreamedResponse
    {
        $period = ReportPeriod::resolve($request);
        $tab = $request->query('tab', 'all');
        $search = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('p', 1));
        $perPage = 25;

        $base = fn () => DB::table('cost_estimates as ce')
            ->join('bookings as b', 'ce.booking_id', '=', 'b.booking_id')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id');

        // There is no 'cancelled' CE status — a CE is cancelled in practice when its booking
        // is, so that tab keys off booking_status while the others exclude cancelled bookings.
        $applyTab = function ($q, string $tab) {
            if ($tab === 'confirmed') {
                return $q->where('ce.status', 'confirmed')->where('b.booking_status', '!=', 'cancelled');
            }
            if ($tab === 'draft') {
                return $q->where('ce.status', '!=', 'confirmed')->where('b.booking_status', '!=', 'cancelled');
            }
            if ($tab === 'cancelled') {
                return $q->where('b.booking_status', 'cancelled');
            }

            return $q;
        };

        $applySearch = function ($q) use ($search) {
            if ($search === '') {
                return $q;
            }

            return $q->where(function ($w) use ($search) {
                $w->where('ce.ce_reference', 'like', "%$search%")
                    ->orWhere('b.booking_reference', 'like', "%$search%")
                    ->orWhere('b.project_title', 'like', "%$search%")
                    ->orWhere('c.company_name', 'like', "%$search%")
                    ->orWhere('c.contact_person', 'like', "%$search%");
            });
        };

        $listQuery = $applySearch($applyTab($base(), $tab));
        $total = (clone $listQuery)->count('ce.ce_id');
        $pages = max(1, (int) ceil($total / $perPage));

        $rows = (clone $listQuery)
            ->leftJoin('users as u', 'ce.confirmed_by', '=', 'u.user_id')
            ->select(
                'ce.ce_id', 'ce.ce_reference', 'ce.status', 'ce.generated_at', 'ce.confirmed_at',
                'ce.grand_total', 'ce.subtotal', 'ce.crew_total',
                'b.booking_id', 'b.booking_reference', 'b.project_title', 'b.booking_status',
                'b.shoot_date_start', 'b.shoot_date_end', 'b.ce_director_dop',
                'c.company_name', 'c.contact_person',
                DB::raw("CONCAT(u.first_name,' ',u.last_name) AS confirmed_by_name")
            )
            ->orderByDesc('ce.generated_at')->orderByDesc('ce.ce_id')
            ->forPage($page, $perPage)
            ->get();

        // COALESCE won't fall through an empty-string company_name — resolve in PHP (Part 3a).
        $rows->each(function ($r) {
            $r->client_name = $r->company_name ?: $r->contact_person;
            $r->is_revision = (bool) preg_match('/-R\d+$/', (string) $r->ce_reference);
        });

        $tabCounts = [];
        foreach (['all', 'confirmed', 'draft', 'cancelled'] as $t) {
            $tabCounts[$t] = (int) $applyTab($base(), $t)->count('ce.ce_id');
        }

        $monthStart = now()->startOfMonth();
        $kpis = [
            'this_month' => (int) $base()->where('ce.generated_at', '>=', $monthStart)->count('ce.ce_id'),
            'drafts' => $tabCounts['draft'],
            'confirmed_month' => (int) $base()->where('ce.status', 'confirmed')
                ->where('b.booking_status', '!=', 'cancelled')
                ->where('ce.confirmed_at', '>=', $monthStart)->count('ce.ce_id'),
            'confirmed_value' => (float) $base()->where('ce.status', 'confirmed')
                ->where('b.booking_status', '!=', 'cancelled')
                ->where('ce.confirmed_at', '>=', $monthStart)->sum('ce.grand_total'),
        ];

        // Financial dashboard, moved here from Reports in Part 11 — the reference tool keeps
        // the KPI row, recap chart and CE list together on its Cost Estimate page.
        $confirmedCes = CeAnalytics::confirmedCes($period['from'], $period['to']);
        $ceFinancials = CeAnalytics::financials($confirmedCes);
        $ceByClient = CeAnalytics::byClient($confirmedCes);

        // "vs last period" pills on the 3 headline cards — null for 'all' mode or when there's
        // nothing to compare against, in which case the view just omits the pill.
        $prevPeriod = ReportPeriod::previous($period);
        $ceDeltas = ['packaged_cost' => null, 'crew_total' => null, 'net_total' => null];
        if ($prevPeriod) {
            $prevFinancials = CeAnalytics::financials(CeAnalytics::confirmedCes($prevPeriod['from'], $prevPeriod['to']));
            foreach ($ceDeltas as $key => $_) {
                $ceDeltas[$key] = ReportPeriod::delta($ceFinancials[$key], $prevFinancials[$key]);
            }
        }

        // Chart window is independent of the table period (see ReportPeriod).
        $chartMonths = $period['chart_months'];
        $chartCes = CeAnalytics::confirmedCes(
            ReportPeriod::chartFrom($chartMonths)->toDateTimeString(),
            now()->endOfDay()->toDateTimeString()
        );
        $ceMonthly = CeAnalytics::monthly($chartCes, ReportPeriod::chartMonthKeys($chartMonths));

        if ($request->query('export') === 'ce_financials') {
            return $this->exportFinancials($confirmedCes);
        }

        return view('cost-estimates', [
            'rows' => $rows, 'tab' => $tab, 'search' => $search,
            'page' => $page, 'pages' => $pages, 'total' => $total,
            'tabCounts' => $tabCounts, 'kpis' => $kpis,
            'statusBadge' => $this->statusBadge,
            'pageTotal' => (float) $rows->sum('grand_total'),
            'period' => $period, 'ceFinancials' => $ceFinancials,
            'ceMonthly' => $ceMonthly, 'ceByClient' => $ceByClient,
            'confirmedCount' => $confirmedCes->count(), 'ceDeltas' => $ceDeltas,
        ]);
    }

    // Moved from ReportsController in Part 11 so the export follows its data.
    private function exportFinancials($confirmedCes): StreamedResponse
    {
        return response()->streamDownload(function () use ($confirmedCes) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['CE Reference', 'Booking', 'Project', 'Client', 'Confirmed',
                'Gross (PHP)', 'Package Cost (PHP)', 'Crew (PHP)', 'Net (PHP)']);
            foreach ($confirmedCes as $ce) {
                // outsourced_total is still subtracted here (not displayed) so Net stays accurate
                // for old confirmed CEs from before outsourced/partner equipment was removed as a
                // feature -- see CeAnalytics::financials() for the same reasoning.
                $net = (float) $ce->subtotal - (float) $ce->crew_total - (float) $ce->outsourced_total;
                fputcsv($out, [$ce->ce_reference, $ce->booking_reference, $ce->project_title, $ce->client_name,
                    date('Y-m-d', strtotime($ce->confirmed_at)), $ce->grand_total, $ce->subtotal,
                    $ce->crew_total, $net]);
            }
            fclose($out);
        }, 'ce-financials-' . date('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
