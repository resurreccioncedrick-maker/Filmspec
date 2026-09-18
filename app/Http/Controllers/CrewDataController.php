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

class CrewDataController extends Controller
{
    public function index(Request $request): View|StreamedResponse|Response
    {
        if ($request->filled('export')) {
            return $this->export($request);
        }

        $period = ReportPeriod::resolve($request);
        $search = trim((string) $request->query('q', ''));

        [$byPerson, $byRole, $crewLines, $bookingIds] = $this->crewByPersonAndRole($period, $search);

        $spendTotal = (float) $crewLines->sum(fn ($r) => $r->rate_used * $r->paid_days);

        // Chart window is independent of the table period (see ReportPeriod).
        $chartMonths = $period['chart_months'];
        $chartKeys = ReportPeriod::chartMonthKeys($chartMonths);
        $chartCes = CeAnalytics::confirmedCes(
            ReportPeriod::chartFrom($chartMonths)->toDateTimeString(),
            now()->endOfDay()->toDateTimeString()
        );
        $chartBookingIds = $chartCes->pluck('booking_id');
        $chartCrewLines = DB::table('booking_crew')
            ->whereIn('booking_id', $chartBookingIds)
            ->select('booking_id', 'crew_id', 'rate_used', 'hours_worked')
            ->get();
        $chartNoShowDays = DB::table('crew_attendance')
            ->whereIn('booking_id', $chartBookingIds)
            ->whereIn('status', ['no_show', 'back_out'])
            ->select('booking_id', 'crew_id')
            ->get()
            ->countBy(fn ($r) => $r->booking_id . '-' . $r->crew_id);
        foreach ($chartCrewLines as $line) {
            $bad = $chartNoShowDays->get($line->booking_id . '-' . $line->crew_id, 0);
            $line->paid_days = max(0.0, (float) $line->hours_worked - $bad);
        }

        $monthly = collect($chartKeys)->map(function ($key) use ($chartCes, $chartCrewLines) {
            $ids = $chartCes->filter(fn ($c) => \Illuminate\Support\Carbon::parse($c->confirmed_at)->format('Y-m') === $key)
                ->pluck('booking_id');

            return (object) [
                'sort_key' => $key,
                'label' => \Illuminate\Support\Carbon::createFromFormat('Y-m', $key)->format('M Y'),
                'spend' => (float) $chartCrewLines->whereIn('booking_id', $ids)->sum(fn ($r) => $r->rate_used * $r->paid_days),
            ];
        });

        $monthsInWindow = max(1, $monthly->count());
        $crewBooked = $crewLines->pluck('crew_id')->unique()->count();

        // "vs last period" pills on Crew Spend and Crew Booked — not Average Per Month, which
        // is already an average across the chart window, so comparing it to itself wouldn't
        // mean much. Null for 'all' mode, where the view just omits the pill.
        $prevPeriod = ReportPeriod::previous($period);
        $crewDeltas = ['spend' => null, 'crew_booked' => null];
        if ($prevPeriod) {
            $prev = $this->spendAndCrewBooked($prevPeriod['from'], $prevPeriod['to']);
            $crewDeltas['spend'] = ReportPeriod::delta($spendTotal, $prev['spend']);
            $crewDeltas['crew_booked'] = ReportPeriod::delta($crewBooked, $prev['crew_booked']);
        }

        return view('crew-data', [
            'period' => $period, 'search' => $search,
            'byPerson' => $byPerson, 'byRole' => $byRole, 'monthly' => $monthly,
            'crewDeltas' => $crewDeltas,
            'kpis' => [
                'spend' => $spendTotal,
                'shoots' => $bookingIds->count(),
                // Distinct people booked across the window, not line count.
                'crew_booked' => $crewBooked,
                'avg_per_month' => round((float) $monthly->sum('spend') / $monthsInWindow, 2),
                'chart_months' => $chartMonths,
            ],
        ]);
    }

    /**
     * Shared by index() and export() — the paid-days-minus-no-shows computation that produces
     * the by-person and by-role tables, factored out so export() can never drift from what's
     * actually shown on screen for the same period/search.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection, 2: \Illuminate\Support\Collection, 3: \Illuminate\Support\Collection}
     */
    private function crewByPersonAndRole(array $period, string $search): array
    {
        $confirmedCes = CeAnalytics::confirmedCes($period['from'], $period['to']);
        $bookingIds = $confirmedCes->pluck('booking_id');

        // Deliberately reads LIVE booking_crew assignments for confirmed bookings rather than
        // a frozen snapshot: booking_crew isn't versioned the way cost_estimates is, so this
        // can legitimately differ from the CE's stored crew_total if crew was reassigned
        // after confirmation.
        $crewLines = DB::table('booking_crew as bc')
            ->join('crew_members as cm', 'bc.crew_id', '=', 'cm.crew_id')
            ->join('bookings as b', 'bc.booking_id', '=', 'b.booking_id')
            ->leftJoin('crew_positions as cp', 'bc.position_id', '=', 'cp.position_id')
            ->whereIn('bc.booking_id', $bookingIds)
            ->select('bc.booking_id', 'bc.crew_id', 'bc.hours_worked', 'bc.rate_used',
                'cm.first_name', 'cm.last_name', 'cp.position_name', 'b.shoot_date_end')
            ->get();

        // Real attendance, not the planned assignment, decides whether a day actually got
        // paid: a booking_crew row's hours_worked/rate_used only ever record what was PLANNED
        // at assignment time and are never adjusted afterward, so a crew member logged
        // no_show/back_out on any shoot day for an assignment previously still counted as
        // fully worked and paid here. Each no_show/back_out attendance day knocks one day off
        // that assignment's paid days (rate_used is a per-day rate — see BookingDetailController's
        // addCrew()), floored at zero.
        $noShowDays = DB::table('crew_attendance')
            ->whereIn('booking_id', $bookingIds)
            ->whereIn('status', ['no_show', 'back_out'])
            ->select('booking_id', 'crew_id')
            ->get()
            ->countBy(fn ($r) => $r->booking_id . '-' . $r->crew_id);

        foreach ($crewLines as $line) {
            $bad = $noShowDays->get($line->booking_id . '-' . $line->crew_id, 0);
            $line->paid_days = max(0.0, (float) $line->hours_worked - $bad);
            $line->no_shows = $bad;
        }

        $byPerson = $crewLines->groupBy('crew_id')->map(function ($rows) {
            $first = $rows->first();

            return (object) [
                'name' => trim($first->first_name . ' ' . $first->last_name),
                'positions' => $rows->pluck('position_name')->filter()->unique()->implode(', '),
                'shoots' => $rows->pluck('booking_id')->unique()->count(),
                'days' => (float) $rows->sum('paid_days'),
                'paid' => (float) $rows->sum(fn ($r) => $r->rate_used * $r->paid_days),
                'no_shows' => (int) $rows->sum('no_shows'),
                'last_worked' => $rows->pluck('shoot_date_end')->filter()->max(),
            ];
        })->sortByDesc('paid')->values();

        $byRole = $crewLines->groupBy(fn ($r) => $r->position_name ?: 'Unassigned')->map(function ($rows, $role) {
            return (object) [
                'role' => $role,
                'shoots' => $rows->pluck('booking_id')->unique()->count(),
                'headcount' => $rows->pluck('crew_id')->unique()->count(),
                'paid' => (float) $rows->sum(fn ($r) => $r->rate_used * $r->paid_days),
            ];
        })->sortByDesc('paid')->values();

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $byPerson = $byPerson->filter(fn ($p) => str_contains(mb_strtolower($p->name), $needle)
                || str_contains(mb_strtolower($p->positions), $needle))->values();
            $byRole = $byRole->filter(fn ($r) => str_contains(mb_strtolower($r->role), $needle))->values();
        }

        return [$byPerson, $byRole, $crewLines, $bookingIds];
    }

    /** Export ▾ — two datasets (by-crew, by-role), same computation index() uses. */
    private function export(Request $request): StreamedResponse|Response
    {
        $period = ReportPeriod::resolve($request);
        $search = trim((string) $request->query('q', ''));
        $type = $request->query('export', 'by_person');

        [$byPerson, $byRole] = $this->crewByPersonAndRole($period, $search);

        if ($type === 'by_role') {
            $headers = ['Role', 'Shoots', 'Headcount', 'Paid'];
            $rows = $byRole->map(fn ($r) => [
                $r->role, $r->shoots, $r->headcount, '₱' . number_format($r->paid, 2),
            ])->all();
            $title = 'Crew Data — By Role';
            $filename = 'crew-data-by-role';
        } else {
            $headers = ['Crew Member', 'Positions', 'Shoots', 'Paid Days', 'Paid', 'No-Shows', 'Last Worked'];
            $rows = $byPerson->map(fn ($p) => [
                $p->name, $p->positions ?: '—', $p->shoots, $p->days,
                '₱' . number_format($p->paid, 2), $p->no_shows,
                $p->last_worked ? \Illuminate\Support\Carbon::parse($p->last_worked)->format('M j, Y') : '—',
            ])->all();
            $title = 'Crew Data — By Crew Member';
            $filename = 'crew-data-by-person';
        }

        return DataExporter::respond($request->query('format', 'csv'), $title, $headers, $rows, $filename);
    }

    /** Same paid-days-minus-no-shows logic as the main query, for an arbitrary window — used
     *  to compute the previous period's figures for the comparison pills. */
    private function spendAndCrewBooked(string $from, string $to): array
    {
        $bookingIds = CeAnalytics::confirmedCes($from, $to)->pluck('booking_id');

        $lines = DB::table('booking_crew')
            ->whereIn('booking_id', $bookingIds)
            ->select('booking_id', 'crew_id', 'hours_worked', 'rate_used')
            ->get();

        $noShowDays = DB::table('crew_attendance')
            ->whereIn('booking_id', $bookingIds)
            ->whereIn('status', ['no_show', 'back_out'])
            ->select('booking_id', 'crew_id')
            ->get()
            ->countBy(fn ($r) => $r->booking_id . '-' . $r->crew_id);

        foreach ($lines as $line) {
            $bad = $noShowDays->get($line->booking_id . '-' . $line->crew_id, 0);
            $line->paid_days = max(0.0, (float) $line->hours_worked - $bad);
        }

        return [
            'spend' => (float) $lines->sum(fn ($r) => $r->rate_used * $r->paid_days),
            'crew_booked' => $lines->pluck('crew_id')->unique()->count(),
        ];
    }
}
