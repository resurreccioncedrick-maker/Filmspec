<?php

namespace App\Http\Controllers;

use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Calendar Data (Part 17) — an analytics read of the shoot calendar, distinct from the
 * booking calendar widget already on the Dashboard (which is for creating/viewing individual
 * bookings, not for reading density/utilization).
 *
 * In month mode the page renders a day grid; in the wider modes (3/6/12/all) it renders a
 * monthly summary table instead, since a multi-month grid doesn't read well.
 */
class CalendarDataController extends Controller
{
    private array $statusBadge = [
        'pending' => 'badge-yellow', 'confirmed' => 'badge-blue', 'ongoing' => 'badge-green',
        'pending_inspection' => 'badge-purple', 'returned' => 'badge-orange', 'completed' => 'badge-gray',
    ];

    public function index(Request $request): View
    {
        $period = ReportPeriod::resolve($request);
        $from = Carbon::parse(substr($period['from'], 0, 10))->startOfDay();
        $to = Carbon::parse(substr($period['to'], 0, 10))->startOfDay();

        $status = in_array($request->query('status'), ['confirmed', 'pending'], true) ? $request->query('status') : '';
        $projectType = trim((string) $request->query('project_type', ''));
        $clientId = (int) $request->query('client_id', 0);

        $projectTypeOptions = ['commercial' => 'Commercial', 'indie_film' => 'Indie Film', 'tv_network' => 'TV Network',
            'music_video' => 'Music Video', 'interview' => 'Interview', 'other' => 'Other'];
        $clientOptions = DB::table('clients')->orderBy('company_name')->get(['client_id', 'company_name', 'contact_person']);

        [$dayMap, $bookings] = $this->buildDayMap($from, $to, $status, $projectType, $clientId);

        $totalDays = (int) $from->diffInDays($to) + 1;
        // Utilization/Busiest-Day/Longest-Gap are all "primary" scheduling facts and must not
        // be inflated by bookings that were never confirmed — $day['count'] here is deliberately
        // the CONFIRMED count only (see buildDayMap()), so every KPI derived from it below is
        // confirmed-only by construction, not a per-KPI filter that's easy to forget on one of
        // them.
        $shootDays = collect($dayMap)->filter(fn ($d) => $d['count'] > 0)->count();
        // Tentative (pending-only) shoot days are tracked separately and never folded into the
        // utilization/busiest/gap figures above.
        $tentativeDays = collect($dayMap)->filter(fn ($d) => $d['pending_count'] > 0)->count();

        $byWeekday = array_fill(0, 7, 0);
        $weekdayOccurrences = array_fill(0, 7, 0);
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $key = $cursor->toDateString();
            $byWeekday[$cursor->dayOfWeek] += $dayMap[$key]['count'] ?? 0;
            $weekdayOccurrences[$cursor->dayOfWeek]++;
            $cursor->addDay();
        }
        $weekdayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $busiestWeekdayIdx = array_search(max($byWeekday), $byWeekday, true);

        $busiestDay = collect($dayMap)->sortByDesc('count')->first();
        $busiestDayCount = $busiestDay['count'] ?? 0;

        $longestGap = $this->longestGap($dayMap, $from, $to);

        $kpis = [
            'shoot_days' => $shootDays,
            'total_days' => $totalDays,
            'utilization' => $totalDays ? round($shootDays / $totalDays * 100, 1) : 0.0,
            'busiest_weekday' => $weekdayNames[$busiestWeekdayIdx] ?? '—',
            'busiest_weekday_count' => $byWeekday[$busiestWeekdayIdx] ?? 0,
            'busiest_weekday_occurrences' => $weekdayOccurrences[$busiestWeekdayIdx] ?? 0,
            'busiest_day_date' => $busiestDayCount > 0 ? ($busiestDay['date'] ?? null) : null,
            'busiest_day_count' => $busiestDayCount,
            'longest_gap' => $longestGap,
            'tentative_days' => $tentativeDays,
        ];

        $grid = $period['mode'] === 'month' ? $this->monthGrid($from, $dayMap) : null;
        $monthlySummary = $period['mode'] !== 'month' ? $this->monthlySummary($dayMap, $from, $to) : null;

        // Day-detail side panel (month view only) — defaults to today when it falls inside the
        // displayed month, else the busiest day, so the panel is never empty on first load.
        $selectedDay = null;
        $selectedDayData = null;
        if ($grid) {
            $requestedDay = $request->query('day');
            $selectedDay = ($requestedDay && isset($dayMap[$requestedDay])) ? $requestedDay
                : (isset($dayMap[now()->toDateString()]) ? now()->toDateString() : ($kpis['busiest_day_date'] ?? $from->toDateString()));
            $d = $dayMap[$selectedDay] ?? ['count' => 0, 'pending_count' => 0, 'items' => collect()];
            $selectedDayData = [
                'date' => $selectedDay,
                'count' => $d['count'], 'pending_count' => $d['pending_count'],
                'confirmed' => $d['items']->filter(fn ($b) => $b->booking_status !== 'pending')->values(),
                'pending' => $d['items']->filter(fn ($b) => $b->booking_status === 'pending')->values(),
            ];
        }

        // "This Month at a Glance" (month view only) — a plain tally over the displayed month,
        // independent of the day-detail selection above.
        $monthGlance = null;
        if ($grid) {
            $monthDayMap = collect($dayMap)->filter(fn ($d, $k) => substr($k, 0, 7) === $from->format('Y-m'));
            $monthBookingIds = $monthDayMap->flatMap(fn ($d) => $d['items']->pluck('booking_id'))->unique();
            $confirmedDays = $monthDayMap->filter(fn ($d) => $d['count'] > 0);
            $pendingOnlyDays = $monthDayMap->filter(fn ($d) => $d['count'] === 0 && $d['pending_count'] > 0);
            $monthGlance = [
                'total_bookings' => $monthBookingIds->count(),
                'confirmed_bookings' => $monthDayMap->flatMap(fn ($d) => $d['items']->where('booking_status', '!=', 'pending')->pluck('booking_id'))->unique()->count(),
                'pending_bookings' => $monthDayMap->flatMap(fn ($d) => $d['items']->where('booking_status', 'pending')->pluck('booking_id'))->unique()->count(),
                'shoot_days' => $confirmedDays->count(),
                'pending_only_days' => $pendingOnlyDays->count(),
                'free_days' => $monthDayMap->count() - $confirmedDays->count() - $pendingOnlyDays->count(),
                'avg_per_shoot_day' => $confirmedDays->count() ? round($confirmedDays->sum('count') / $confirmedDays->count(), 1) : 0.0,
                'busiest_week' => $this->busiestWeek($grid),
            ];
        }

        // Deliberately NOT sourced from $bookings above: that set is bounded by the selected
        // (backward-looking) period window, so under 3m/6m/12m/all — whose "to" is today —
        // it would always be empty. "Upcoming" means from today forward regardless of which
        // historical window is being browsed.
        $upcoming = $this->upcomingShoots(10, $status, $projectType, $clientId);

        // "vs last period" pill on Shoot Days / Utilization only — Busiest Day, Busiest Day of
        // Week and Longest Gap are point-in-time facts about this specific window, not a trend,
        // so a percentage on them wouldn't mean anything. Shown as a POINT difference (e.g.
        // "+4pt"), not a relative percent change — utilization is already a percentage, so
        // running it through a percent-of-a-percent formula would read as far more dramatic
        // than what actually happened.
        $calDelta = null;
        $prevPeriod = ReportPeriod::previous($period);
        if ($prevPeriod) {
            $prevFrom = Carbon::parse(substr($prevPeriod['from'], 0, 10))->startOfDay();
            $prevTo = Carbon::parse(substr($prevPeriod['to'], 0, 10))->startOfDay();
            [$prevDayMap] = $this->buildDayMap($prevFrom, $prevTo);
            $prevTotalDays = (int) $prevFrom->diffInDays($prevTo) + 1;
            $prevShootDays = collect($prevDayMap)->filter(fn ($d) => $d['count'] > 0)->count();
            $prevUtilization = $prevTotalDays ? round($prevShootDays / $prevTotalDays * 100, 1) : 0.0;
            $pointDiff = (int) round($kpis['utilization'] - $prevUtilization);
            if ($pointDiff !== 0) {
                $calDelta = ['dir' => $pointDiff > 0 ? 'up' : 'down', 'pct' => abs($pointDiff), 'suffix' => 'pt'];
            }
        }

        return view('calendar-data', [
            'period' => $period, 'kpis' => $kpis, 'grid' => $grid,
            'monthlySummary' => $monthlySummary, 'upcoming' => $upcoming,
            'statusBadge' => $this->statusBadge, 'calDelta' => $calDelta,
            'status' => $status, 'projectType' => $projectType, 'clientId' => $clientId,
            'projectTypeOptions' => $projectTypeOptions, 'clientOptions' => $clientOptions,
            'selectedDay' => $selectedDay, 'selectedDayData' => $selectedDayData,
            'monthGlance' => $monthGlance,
        ]);
    }

    /** The next N shoots from today forward, independent of the selected analytics period. */
    private function upcomingShoots(int $limit = 10, string $status = '', string $projectType = '', int $clientId = 0): \Illuminate\Support\Collection
    {
        $rows = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('b.booking_status', '!=', 'cancelled')
            ->where('b.shoot_date_start', '>=', now()->toDateString())
            ->when($status === 'confirmed', fn ($q) => $q->where('b.booking_status', '!=', 'pending'))
            ->when($status === 'pending', fn ($q) => $q->where('b.booking_status', 'pending'))
            ->when($projectType !== '', fn ($q) => $q->where('b.project_type', $projectType))
            ->when($clientId > 0, fn ($q) => $q->where('b.client_id', $clientId))
            ->orderBy('b.shoot_date_start')
            ->limit($limit)
            ->select('b.booking_id', 'b.booking_reference', 'b.project_title', 'b.booking_status',
                'b.shoot_date_start', 'b.shoot_date_end', 'c.company_name', 'c.contact_person')
            ->get();

        $rows->each(function ($b) {
            $b->client_name = $b->company_name ?: $b->contact_person;
        });

        if ($rows->isEmpty()) {
            return $rows;
        }

        $ids = $rows->pluck('booking_id');
        $crewCounts = DB::table('booking_crew')->whereIn('booking_id', $ids)
            ->select('booking_id')->selectRaw('COUNT(DISTINCT crew_id) AS c')->groupBy('booking_id')
            ->pluck('c', 'booking_id');
        $equipCounts = DB::table('booking_equipment')->whereIn('booking_id', $ids)
            ->select('booking_id')->selectRaw('SUM(quantity) AS c')->groupBy('booking_id')
            ->pluck('c', 'booking_id');
        $rows->each(function ($b) use ($crewCounts, $equipCounts) {
            $b->crew_count = (int) ($crewCounts[$b->booking_id] ?? 0);
            $b->equipment_count = (int) ($equipCounts[$b->booking_id] ?? 0);
        });

        return $rows;
    }

    /**
     * Sums each Sunday-first week row of the month grid (same boundaries as monthGrid()) and
     * returns the busiest one's date range, clipped to the days actually in the displayed month.
     */
    private function busiestWeek(array $grid): ?array
    {
        $best = null;
        $bestCount = -1;
        foreach ($grid as $week) {
            $count = array_sum(array_column($week, 'count'));
            $inMonth = array_values(array_filter($week, fn ($d) => $d['in_month']));
            if (empty($inMonth)) {
                continue;
            }
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = ['from' => $inMonth[0]['date'], 'to' => end($inMonth)['date'], 'count' => $count];
            }
        }

        return $bestCount > 0 ? $best : null;
    }

    /**
     * @return array{0: array<string, array{date:string,count:int,items:\Illuminate\Support\Collection}>, 1: \Illuminate\Support\Collection}
     */
    private function buildDayMap(Carbon $from, Carbon $to, string $status = '', string $projectType = '', int $clientId = 0): array
    {
        $bookings = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('b.booking_status', '!=', 'cancelled')
            ->where('b.shoot_date_start', '<=', $to->toDateString())
            ->where('b.shoot_date_end', '>=', $from->toDateString())
            ->when($status === 'confirmed', fn ($q) => $q->where('b.booking_status', '!=', 'pending'))
            ->when($status === 'pending', fn ($q) => $q->where('b.booking_status', 'pending'))
            ->when($projectType !== '', fn ($q) => $q->where('b.project_type', $projectType))
            ->when($clientId > 0, fn ($q) => $q->where('b.client_id', $clientId))
            ->select('b.booking_id', 'b.booking_reference', 'b.project_title', 'b.booking_status',
                'b.shoot_date_start', 'b.shoot_date_end', 'c.company_name', 'c.contact_person')
            ->get();

        $bookings->each(function ($b) {
            $b->client_name = $b->company_name ?: $b->contact_person;
        });

        $dayMap = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            // 'count' is deliberately the CONFIRMED count (booking_status != pending/cancelled)
            // — every KPI in index() reads 'count' directly, so defining it as confirmed-only
            // here means utilization/busiest-day/longest-gap can't accidentally include pending
            // bookings just by forgetting a filter downstream. Pending is tracked in parallel
            // via 'pending_count', never merged into 'count'.
            $dayMap[$cursor->toDateString()] = [
                'date' => $cursor->toDateString(), 'count' => 0, 'pending_count' => 0, 'items' => collect(),
            ];
            $cursor->addDay();
        }

        foreach ($bookings as $b) {
            $isPending = $b->booking_status === 'pending';
            $start = Carbon::parse($b->shoot_date_start)->max($from);
            $end = Carbon::parse($b->shoot_date_end)->min($to);
            $d = $start->copy();
            while ($d->lte($end)) {
                $key = $d->toDateString();
                if (isset($dayMap[$key])) {
                    if ($isPending) {
                        $dayMap[$key]['pending_count']++;
                    } else {
                        $dayMap[$key]['count']++;
                    }
                    $dayMap[$key]['items']->push($b);
                }
                $d->addDay();
            }
        }

        return [$dayMap, $bookings];
    }

    /** Longest unbroken run of zero-booking days within the window. */
    private function longestGap(array $dayMap, Carbon $from, Carbon $to): int
    {
        $longest = 0;
        $current = 0;
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            if (($dayMap[$cursor->toDateString()]['count'] ?? 0) === 0) {
                $current++;
                $longest = max($longest, $current);
            } else {
                $current = 0;
            }
            $cursor->addDay();
        }

        return $longest;
    }

    /** Sunday-first grid padded to full weeks, for the month view. */
    private function monthGrid(Carbon $monthStart, array $dayMap): array
    {
        $firstOfMonth = $monthStart->copy()->startOfMonth();
        $lastOfMonth = $monthStart->copy()->endOfMonth();
        $gridStart = $firstOfMonth->copy()->subDays($firstOfMonth->dayOfWeek);
        $gridEnd = $lastOfMonth->copy()->addDays(6 - $lastOfMonth->dayOfWeek);

        $weeks = [];
        $week = [];
        $cursor = $gridStart->copy();
        while ($cursor->lte($gridEnd)) {
            $key = $cursor->toDateString();
            $week[] = [
                'date' => $key,
                'day' => $cursor->day,
                'in_month' => $cursor->month === $firstOfMonth->month,
                'is_today' => $cursor->isToday(),
                'count' => $dayMap[$key]['count'] ?? 0,
                'pending_count' => $dayMap[$key]['pending_count'] ?? 0,
                'items' => $dayMap[$key]['items'] ?? collect(),
            ];
            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
            $cursor->addDay();
        }

        return $weeks;
    }

    private function monthlySummary(array $dayMap, Carbon $from, Carbon $to): \Illuminate\Support\Collection
    {
        $months = [];
        $cursor = $from->copy()->startOfMonth();
        while ($cursor->lte($to)) {
            $months[$cursor->format('Y-m')] = ['shoot_days' => 0, 'booking_days' => 0, 'busiest_count' => 0, 'busiest_date' => null, 'tentative_days' => 0];
            $cursor->addMonthNoOverflow();
        }

        foreach ($dayMap as $date => $d) {
            $mKey = substr($date, 0, 7);
            if (! isset($months[$mKey])) {
                continue;
            }
            if ($d['count'] > 0) {
                $months[$mKey]['shoot_days']++;
                $months[$mKey]['booking_days'] += $d['count'];
                if ($d['count'] > $months[$mKey]['busiest_count']) {
                    $months[$mKey]['busiest_count'] = $d['count'];
                    $months[$mKey]['busiest_date'] = $date;
                }
            }
            if ($d['pending_count'] > 0) {
                $months[$mKey]['tentative_days']++;
            }
        }

        return collect($months)->map(fn ($v, $k) => (object) array_merge($v, [
            'label' => Carbon::createFromFormat('Y-m', $k)->format('M Y'),
        ]))->values();
    }
}
