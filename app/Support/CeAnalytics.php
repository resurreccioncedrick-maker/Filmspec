<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Confirmed-CE analytics shared by the Cost Estimates, Crew Data and Equipment Data pages
 * (Part 11). Moved out of ReportsController when those sections became their own pages, so
 * all three read the same numbers from one implementation.
 */
class CeAnalytics
{
    /**
     * Only the latest CONFIRMED CE per booking counts, so a booking that was confirmed,
     * changed and re-confirmed (Part 2a's revision lifecycle) is never double-counted from
     * stale revision history.
     */
    public static function confirmedCes(string $from, string $to): Collection
    {
        $rows = DB::table('cost_estimates as ce')
            ->join('bookings as b', 'ce.booking_id', '=', 'b.booking_id')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('ce.status', 'confirmed')
            ->whereRaw('ce.ce_id = (SELECT MAX(ce2.ce_id) FROM cost_estimates ce2 WHERE ce2.booking_id = ce.booking_id AND ce2.status = "confirmed")')
            ->whereBetween('ce.confirmed_at', [$from, $to])
            ->orderByDesc('ce.confirmed_at')
            ->select('ce.ce_id', 'ce.ce_reference', 'ce.grand_total', 'ce.subtotal', 'ce.crew_total',
                'ce.outsourced_total', 'ce.equipment_total', 'ce.accessories_total', 'ce.transport_total',
                'ce.confirmed_at', 'b.booking_id', 'b.project_title', 'b.booking_reference',
                'b.shoot_date_start', 'b.shoot_date_end',
                'c.company_name', 'c.contact_person')
            ->get();

        // company_name is often an empty string rather than NULL, so SQL COALESCE won't fall
        // through to contact_person — use PHP's falsy-coalescing, as elsewhere in the app.
        $rows->each(function ($row) {
            $row->client_name = $row->company_name ?: $row->contact_person;
        });

        return $rows;
    }

    public static function financials(Collection $confirmedCes): array
    {
        $f = [
            'packaged_cost' => (float) $confirmedCes->sum('subtotal'),
            'crew_total' => (float) $confirmedCes->sum('crew_total'),
            'gross_total' => (float) $confirmedCes->sum('grand_total'),
            'count' => $confirmedCes->count(),
            // FS equipment at CE rates, before any package discount (Part 11 / item 26).
            'fs_equipment_listed' => (float) $confirmedCes->sum(fn ($r) => (float) $r->equipment_total + (float) $r->accessories_total),
        ];
        // Outsourced/partner equipment was removed as a feature (see BookingCosting) and is no
        // longer surfaced as its own metric, but old confirmed CEs from before the removal can
        // still carry a real outsourced_total — still subtracted here so net_total stays
        // historically accurate for those, even though it's always 0 for anything new.
        $outsourcedTotal = (float) $confirmedCes->sum('outsourced_total');
        $f['net_total'] = $f['packaged_cost'] - $f['crew_total'] - $outsourcedTotal;

        $pcDivisor = $f['packaged_cost'] ?: 1;   // avoid divide-by-zero on an empty period
        $f['crew_pct'] = round($f['crew_total'] / $pcDivisor * 100, 1);
        $f['net_pct'] = round($f['net_total'] / $pcDivisor * 100, 1);
        $f['avg_per_ce'] = $f['count'] ? round($f['net_total'] / $f['count'], 2) : 0.0;

        return $f;
    }

    /**
     * Monthly recap. When $monthKeys is given every month in the chart window is emitted,
     * so a quiet month renders as a gap on the axis instead of vanishing.
     */
    public static function monthly(Collection $confirmedCes, ?array $monthKeys = null): Collection
    {
        $grouped = $confirmedCes->groupBy(fn ($row) => Carbon::parse($row->confirmed_at)->format('Y-m'));
        $keys = $monthKeys ?? $grouped->keys()->sort()->values()->all();

        return collect($keys)->map(function ($key) use ($grouped) {
            $rows = $grouped->get($key, collect());
            $packaged = (float) $rows->sum('subtotal');
            $crew = (float) $rows->sum('crew_total');
            $outsourced = (float) $rows->sum('outsourced_total');

            return (object) [
                'sort_key' => $key,
                'label' => Carbon::createFromFormat('Y-m', $key)->format('M Y'),
                'packaged_cost' => $packaged, 'crew_total' => $crew,
                'net_total' => $packaged - $crew - $outsourced,
                'ce_count' => $rows->count(),
            ];
        })->values();
    }

    public static function byClient(Collection $confirmedCes): Collection
    {
        return $confirmedCes->groupBy('client_name')->map(function ($rows, $name) {
            $packaged = (float) $rows->sum('subtotal');
            $crew = (float) $rows->sum('crew_total');
            $outsourced = (float) $rows->sum('outsourced_total');

            return (object) [
                'client_name' => $name ?: '(Unnamed)', 'ce_count' => $rows->count(),
                'packaged_cost' => $packaged, 'net_total' => $packaged - $crew - $outsourced,
            ];
        })->sortByDesc('packaged_cost')->values();
    }
}
