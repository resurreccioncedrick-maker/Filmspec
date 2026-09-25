<?php

namespace App\Http\Controllers;

use App\Support\CeAnalytics;
use App\Support\DataExporter;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CostEstimatesController extends Controller
{
    private array $statusBadge = [
        'draft' => 'badge-gray', 'issued' => 'badge-blue',
        'confirmed' => 'badge-green', 'superseded' => 'badge-gray',
    ];

    public function index(Request $request): View|StreamedResponse|Response
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
        // 'confirmed' is deduplicated to the latest confirmed row per booking (CeAnalytics::
        // onlyLatestConfirmed) as defense-in-depth — BookingCosting::confirmCe() now demotes
        // every other confirmed row for a booking to 'superseded' at confirm time, so this
        // filter should never actually need to catch anything in normal operation.
        $applyTab = function ($q, string $tab) {
            if ($tab === 'confirmed') {
                return CeAnalytics::onlyLatestConfirmed($q)->where('b.booking_status', '!=', 'cancelled');
            }
            if ($tab === 'draft') {
                return $q->where('ce.status', 'draft')->where('b.booking_status', '!=', 'cancelled');
            }
            if ($tab === 'issued') {
                return $q->where('ce.status', 'issued')->where('b.booking_status', '!=', 'cancelled');
            }
            if ($tab === 'superseded') {
                return $q->where('ce.status', 'superseded')->where('b.booking_status', '!=', 'cancelled');
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
                'ce.confirmation_note', 'ce.grand_total', 'ce.subtotal', 'ce.crew_total',
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
        foreach (['all', 'confirmed', 'draft', 'issued', 'superseded', 'cancelled'] as $t) {
            $tabCounts[$t] = (int) $applyTab($base(), $t)->count('ce.ce_id');
        }

        $monthStart = now()->startOfMonth();
        $kpis = [
            'this_month' => (int) $base()->where('ce.generated_at', '>=', $monthStart)->count('ce.ce_id'),
            'drafts' => $tabCounts['draft'],
            'confirmed_month' => (int) CeAnalytics::onlyLatestConfirmed($base())
                ->where('b.booking_status', '!=', 'cancelled')
                ->where('ce.confirmed_at', '>=', $monthStart)->count('ce.ce_id'),
            'confirmed_value' => (float) CeAnalytics::onlyLatestConfirmed($base())
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
            return $this->exportFinancials($confirmedCes, $request->query('format', 'csv'), $period);
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

    // Moved from ReportsController in Part 11 so the export follows its data. Uses
    // respondSections (not respond) so the export can lead with an "Applied Filters" / generated
    // metadata block, per the panelist revision's export-traceability requirement — every export
    // is thus self-describing about which filters and revision rules produced it.
    private function exportFinancials($confirmedCes, string $format = 'csv', ?array $period = null): StreamedResponse|Response
    {
        $userId = Auth::id();
        $generatedBy = $userId ? trim((string) DB::table('users')->where('user_id', $userId)
            ->selectRaw("CONCAT(first_name,' ',last_name) AS n")->value('n')) : null;

        $infoRows = [
            ['Applied Period Filter', $period['label'] ?? 'All time'],
            ['Date Basis', 'CE Confirmation Date'],
            ['Revision Rule', 'Only the current active confirmed version of each CE is included — superseded and draft revisions are excluded'],
            ['VAT Basis', 'All amounts below are ex-VAT'],
            ['Generated', now()->format('M j, Y g:i A')],
            ['Generated By', $generatedBy ?: 'Unknown'],
        ];

        $headers = ['CE Reference', 'Booking', 'Project', 'Client', 'Confirmed',
            'Confirmed By', 'Gross (PHP)', 'Confirmed CE Value (ex-VAT)', 'Crew Quoted (PHP)', 'FilmSpec Portion (PHP)'];

        $rows = $confirmedCes->map(function ($ce) {
            // outsourced_total is still subtracted here (not displayed) so FilmSpec Portion
            // stays accurate for old confirmed CEs from before outsourced/partner equipment was
            // removed as a feature -- see CeAnalytics::financials() for the same reasoning.
            $net = (float) $ce->subtotal - (float) $ce->crew_total - (float) $ce->outsourced_total;

            return [$ce->ce_reference, $ce->booking_reference, $ce->project_title, $ce->client_name,
                date('Y-m-d', strtotime($ce->confirmed_at)), $ce->confirmed_by_name ?: '—',
                $ce->grand_total, $ce->subtotal, $ce->crew_total, $net];
        })->all();

        return DataExporter::respondSections($format, 'Cost Estimates — CE Analytics', [
            ['title' => 'Report Info', 'rows' => $infoRows],
            ['title' => 'Confirmed Cost Estimates', 'headers' => $headers, 'rows' => $rows],
        ], 'ce-analytics');
    }
}
