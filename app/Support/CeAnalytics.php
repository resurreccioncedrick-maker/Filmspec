<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CeAnalytics
{
    public static function onlyLatestConfirmed($query)
    {
        return $query->where('ce.status', 'confirmed')
            ->whereRaw('ce.ce_id = (SELECT MAX(ce2.ce_id) FROM cost_estimates ce2 WHERE ce2.booking_id = ce.booking_id AND ce2.status = "confirmed")');
    }

    public static function confirmedCes(string $from, string $to): Collection
    {
        $rows = self::onlyLatestConfirmed(
            DB::table('cost_estimates as ce')
                ->join('bookings as b', 'ce.booking_id', '=', 'b.booking_id')
                ->join('clients as c', 'b.client_id', '=', 'c.client_id')
                ->leftJoin('users as u', 'ce.confirmed_by', '=', 'u.user_id')
        )
            ->where('b.booking_status', '!=', 'cancelled')
            ->whereBetween('ce.confirmed_at', [$from, $to])
            ->orderByDesc('ce.confirmed_at')
            ->select('ce.ce_id', 'ce.ce_reference', 'ce.grand_total', 'ce.subtotal', 'ce.crew_total',
                'ce.outsourced_total', 'ce.equipment_total', 'ce.accessories_total', 'ce.transport_total',
                'ce.discount', 'ce.confirmed_at', 'ce.confirmation_note',
                'b.booking_id', 'b.project_title', 'b.booking_reference',
                'b.shoot_date_start', 'b.shoot_date_end',
                'c.company_name', 'c.contact_person',
                DB::raw("CONCAT(u.first_name,' ',u.last_name) AS confirmed_by_name"))
            ->get();

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
            'fs_equipment_listed' => (float) $confirmedCes->sum(fn ($r) => (float) $r->equipment_total + (float) $r->accessories_total),
            'discount_total' => (float) $confirmedCes->sum('discount'),
        ];
        $outsourcedTotal = (float) $confirmedCes->sum('outsourced_total');
        $f['net_total'] = $f['packaged_cost'] - $f['crew_total'] - $outsourcedTotal;

        $pcDivisor = $f['packaged_cost'] ?: 1;
        $f['crew_pct'] = round($f['crew_total'] / $pcDivisor * 100, 1);
        $f['net_pct'] = round($f['net_total'] / $pcDivisor * 100, 1);
        $f['avg_per_ce'] = $f['count'] ? round($f['net_total'] / $f['count'], 2) : 0.0;

        return $f;
    }

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
