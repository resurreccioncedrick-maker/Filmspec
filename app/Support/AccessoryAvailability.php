<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Same "available units right now" math HomeController's eq_detail branch and
 * AccessoriesController's admin stats both need — individually tracked accessories count real
 * accessory_units at status=available; quantity-tracked ones subtract what's already committed
 * to other active bookings from the plain quantity column. Centralized here so CartController's
 * add-to-cart validation can never disagree with what the client was shown.
 */
class AccessoryAvailability
{
    /** @return array<int,int> accessory_id => available units right now */
    public static function bulkAvailable(array $accessoryIds): array
    {
        $accessoryIds = array_values(array_unique(array_map('intval', $accessoryIds)));
        if (! $accessoryIds) {
            return [];
        }

        $accessories = DB::table('accessories')
            ->whereIn('accessory_id', $accessoryIds)
            ->select('accessory_id', 'tracking_method', 'quantity')
            ->get()->keyBy('accessory_id');

        $unitAvailCounts = DB::table('accessory_units')
            ->whereIn('accessory_id', $accessoryIds)
            ->where('status', 'available')
            ->select('accessory_id', DB::raw('COUNT(*) as c'))
            ->groupBy('accessory_id')
            ->pluck('c', 'accessory_id');

        $inUseByAcc = DB::table('booking_accessories as ba')
            ->join('bookings as b', 'ba.booking_id', '=', 'b.booking_id')
            ->whereIn('ba.accessory_id', $accessoryIds)
            ->whereNotIn('b.booking_status', ['cancelled', 'completed'])
            ->select('ba.accessory_id', DB::raw('SUM(ba.quantity) as q'))
            ->groupBy('ba.accessory_id')
            ->pluck('q', 'accessory_id');

        $out = [];
        foreach ($accessoryIds as $id) {
            $acc = $accessories->get($id);
            $method = $acc->tracking_method ?? 'quantity';
            $out[$id] = $method === 'individual'
                ? (int) ($unitAvailCounts[$id] ?? 0)
                : max(0, (int) ($acc->quantity ?? 1) - (int) ($inUseByAcc[$id] ?? 0));
        }

        return $out;
    }

    public static function available(int $accessoryId): int
    {
        return self::bulkAvailable([$accessoryId])[$accessoryId] ?? 0;
    }
}
