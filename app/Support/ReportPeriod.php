<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The period control shared by the Cost Estimates, Crew Data and Equipment Data pages.
 *
 * One implementation on purpose: three pages each rolling their own month arithmetic is how
 * the same control ends up meaning slightly different things on different pages.
 */
class ReportPeriod
{
    public const MODES = ['month' => 'Month', '3m' => '3 Months', '6m' => '6 Months', '12m' => '12 Months', 'all' => 'All'];

    /**
     * @return array{from:string,to:string,mode:string,label:string,month:string,prev:string,next:string,chart_months:int}
     */
    public static function resolve(Request $request): array
    {
        $mode = (string) $request->query('period', 'month');
        if (! array_key_exists($mode, self::MODES)) {
            $mode = 'month';
        }

        // The chart window is deliberately independent of the table period: the reference
        // shows an August table beside a "Mar–Aug" recap chart, so tying them together would
        // be wrong.
        $chartMonths = (int) $request->query('chart', 6) === 12 ? 12 : 6;

        $monthKey = (string) $request->query('m', '');
        if (! preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
            $monthKey = now()->format('Y-m');
        }
        $anchor = Carbon::createFromFormat('Y-m-d', $monthKey . '-01')->startOfMonth();

        switch ($mode) {
            case 'month':
                $from = $anchor->copy()->startOfMonth();
                $to = $anchor->copy()->endOfMonth();
                $label = $anchor->format('F Y');
                break;
            case '3m':
                $to = now()->endOfDay();
                $from = now()->subMonthsNoOverflow(3)->startOfDay();
                $label = $from->format('M Y') . ' – ' . $to->format('M Y');
                break;
            case '6m':
                $to = now()->endOfDay();
                $from = now()->subMonthsNoOverflow(6)->startOfDay();
                $label = $from->format('M Y') . ' – ' . $to->format('M Y');
                break;
            case '12m':
                $to = now()->endOfDay();
                $from = now()->subMonthsNoOverflow(12)->startOfDay();
                $label = $from->format('M Y') . ' – ' . $to->format('M Y');
                break;
            default: // all
                $earliest = DB::table('cost_estimates')->min('confirmed_at')
                    ?: DB::table('cost_estimates')->min('generated_at');
                $from = $earliest ? Carbon::parse($earliest)->startOfDay() : now()->subYears(5)->startOfDay();
                $to = now()->endOfDay();
                $label = 'All time';
        }

        return [
            'from' => $from->toDateTimeString(),
            'to' => $to->toDateTimeString(),
            'mode' => $mode,
            'label' => $label,
            'month' => $anchor->format('Y-m'),
            'prev' => $anchor->copy()->subMonthNoOverflow()->format('Y-m'),
            'next' => $anchor->copy()->addMonthNoOverflow()->format('Y-m'),
            'chart_months' => $chartMonths,
        ];
    }

    /**
     * The equal-length window immediately before the given period, for a "vs last period"
     * comparison badge. Null for 'all' mode, where "the period before all time" is meaningless
     * — callers should just omit the badge in that case rather than show a broken one.
     */
    public static function previous(array $period): ?array
    {
        if ($period['mode'] === 'all') {
            return null;
        }

        if ($period['mode'] === 'month') {
            $anchor = Carbon::createFromFormat('Y-m-d', $period['prev'] . '-01')->startOfMonth();

            return ['from' => $anchor->toDateTimeString(), 'to' => $anchor->copy()->endOfMonth()->toDateTimeString()];
        }

        // 3m/6m/12m: an equal-length window ending the instant the current one begins.
        $months = (int) rtrim($period['mode'], 'm');
        $to = Carbon::parse($period['from'])->subSecond();
        $from = Carbon::parse($period['from'])->subMonthsNoOverflow($months);

        return ['from' => $from->toDateTimeString(), 'to' => $to->toDateTimeString()];
    }

    /**
     * Percent change from $previous to $current, rounded to a whole number, with direction.
     * Null when there's nothing sensible to show (both zero, or previous is zero — a jump
     * from ₱0 is not a meaningful percentage, so callers show "New" instead of guessing one).
     */
    public static function delta(float $current, float $previous): ?array
    {
        if ($previous == 0.0) {
            return $current == 0.0 ? null : ['dir' => 'up', 'pct' => null];
        }

        $pct = round((($current - $previous) / abs($previous)) * 100);
        if ($pct === 0.0 || $pct === -0.0) {
            return null;
        }

        return ['dir' => $pct > 0 ? 'up' : 'down', 'pct' => (int) abs($pct)];
    }

    /** Start of the chart window — always relative to today, never to the selected month. */
    public static function chartFrom(int $months): Carbon
    {
        return now()->subMonthsNoOverflow($months - 1)->startOfMonth();
    }

    /**
     * Every month key in the chart window, so a month with no activity still renders as a gap
     * rather than being silently dropped from the axis.
     */
    public static function chartMonthKeys(int $months): array
    {
        $keys = [];
        $cursor = self::chartFrom($months);
        for ($i = 0; $i < $months; $i++) {
            $keys[] = $cursor->format('Y-m');
            $cursor->addMonthNoOverflow();
        }

        return $keys;
    }
}
