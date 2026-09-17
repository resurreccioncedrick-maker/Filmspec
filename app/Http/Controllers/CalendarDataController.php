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

        [$dayMap, $bookings] = $this->buildDayMap($from, $to);

        $totalDays = (int) $from->diffInDays($to) + 1;
        $shootDays = collect($dayMap)->filter(fn ($d) => $d['count'] > 0)->count();

        $byWeekday = array_fill(0, 7, 0);
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $key = $cursor->toDateString();
            $byWeekday[$cursor->dayOfWeek] += $dayMap[$key]['count'] ?? 0;
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
            'busiest_day_date' => $busiestDayCount > 0 ? ($busiestDay['date'] ?? null) : null,
            'busiest_day_count' => $busiestDayCount,
            'longest_gap' => $longestGap,
        ];

        $grid = $period['mode'] === 'month' ? $this->monthGrid($from, $dayMap) : null;
        $monthlySummary = $period['mode'] !== 'month' ? $this->monthlySummary($dayMap, $from, $to) : null;

        // Deliberately NOT sourced from $bookings above: that set is bounded by the selected
        // (backward-looking) period window, so under 3m/6m/12m/all — whose "to" is today —
        // it would always be empty. "Upcoming" means from today forward regardless of which
        // historical window is being browsed.
        $upcoming = $this->upcomingShoots();

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
        ]);
    }

    /** The next 10 shoots from today forward, independent of the selected analytics period. */
    private function upcomingShoots(int $limit = 10): \Illuminate\Support\Collection
    {
        $rows = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('b.booking_status', '!=', 'cancelled')
            ->where('b.shoot_date_start', '>=', now()->toDateString())
            ->orderBy('b.shoot_date_start')
            ->limit($limit)
            ->select('b.booking_id', 'b.booking_reference', 'b.project_title', 'b.booking_status',
                'b.shoot_date_start', 'b.shoot_date_end', 'c.company_name', 'c.contact_person')
            ->get();

        $rows->each(function ($b) {
            $b->client_name = $b->company_name ?: $b->contact_person;
        });

        return $rows;
    }

    /**
     * @return array{0: array<string, array{date:string,count:int,items:\Illuminate\Support\Collection}>, 1: \Illuminate\Support\Collection}
     */
    private function buildDayMap(Carbon $from, Carbon $to): array
    {
        $bookings = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('b.booking_status', '!=', 'cancelled')
            ->where('b.shoot_date_start', '<=', $to->toDateString())
            ->where('b.shoot_date_end', '>=', $from->toDateString())
            ->select('b.booking_id', 'b.booking_reference', 'b.project_title', 'b.booking_status',
                'b.shoot_date_start', 'b.shoot_date_end', 'c.company_name', 'c.contact_person')
            ->get();

        $bookings->each(function ($b) {
            $b->client_name = $b->company_name ?: $b->contact_person;
        });

        $dayMap = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $dayMap[$cursor->toDateString()] = ['date' => $cursor->toDateString(), 'count' => 0, 'items' => collect()];
            $cursor->addDay();
        }

        foreach ($bookings as $b) {
            $start = Carbon::parse($b->shoot_date_start)->max($from);
            $end = Carbon::parse($b->shoot_date_end)->min($to);
            $d = $start->copy();
            while ($d->lte($end)) {
                $key = $d->toDateString();
                if (isset($dayMap[$key])) {
                    $dayMap[$key]['count']++;
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
            $months[$cursor->format('Y-m')] = ['shoot_days' => 0, 'booking_days' => 0, 'busiest_count' => 0, 'busiest_date' => null];
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
        }

        return collect($months)->map(fn ($v, $k) => (object) array_merge($v, [
            'label' => Carbon::createFromFormat('Y-m', $k)->format('M Y'),
        ]))->values();
    }
}
