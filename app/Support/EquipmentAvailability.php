<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class EquipmentAvailability
{
    public static function check(int $equipmentId, int $qty, int $excludeBookingId, object $booking): ?array
    {
        $equip = DB::table('equipment')->where('equipment_id', $equipmentId)->first();
        if (! $equip) {
            return ['type' => 'error', 'text' => 'Equipment not found.'];
        }
        $stock = max(1, (int) $equip->stock_quantity);

        $underMaintenance = (int) DB::table('equipment_units')
            ->where('equipment_id', $equipmentId)
            ->where('status', 'under_maintenance')
            ->count();
        if ($underMaintenance > 0) {
            $stock = max(0, $stock - $underMaintenance);
            if ($stock === 0) {
                return ['type' => 'error', 'text' => 'All units of this equipment are currently under maintenance.'];
            }
            if ($qty > $stock) {
                return ['type' => 'error', 'text' => "Only <strong>$stock</strong> unit(s) of this equipment are free right now ($underMaintenance under maintenance)."];
            }
        }

        if ($stock === 1) {
            if ($equip->availability_status !== 'available') {
                return ['type' => 'error', 'text' => 'This equipment is not available. Current status: ' . ucfirst($equip->availability_status ?? 'unknown')];
            }
            $conflict = DB::table('booking_equipment as be')
                ->join('bookings as b', 'be.booking_id', '=', 'b.booking_id')
                ->where('be.equipment_id', $equipmentId)->where('be.booking_id', '!=', $excludeBookingId)
                ->whereNotIn('b.booking_status', ['cancelled', 'completed'])
                ->where('b.shoot_date_start', '<=', $booking->shoot_date_end)
                ->where('b.shoot_date_end', '>=', $booking->shoot_date_start)
                ->value('b.booking_reference');
            if ($conflict) {
                return ['type' => 'error', 'text' => 'This equipment is already allocated to booking <strong>' . e($conflict) . '</strong> on overlapping dates. Choose different equipment or adjust the dates.'];
            }

            return null;
        }

        if (in_array($equip->availability_status, ['under_repair', 'retired'], true)) {
            return ['type' => 'error', 'text' => 'This equipment is not available. Current status: ' . ucfirst($equip->availability_status)];
        }
        $inUse = (int) DB::table('booking_equipment as be')
            ->join('bookings as b', 'be.booking_id', '=', 'b.booking_id')
            ->where('be.equipment_id', $equipmentId)->where('be.booking_id', '!=', $excludeBookingId)
            ->whereNotIn('b.booking_status', ['cancelled', 'completed'])
            ->where('b.shoot_date_start', '<=', $booking->shoot_date_end)
            ->where('b.shoot_date_end', '>=', $booking->shoot_date_start)
            ->sum('be.quantity');
        if ($inUse + $qty > $stock) {
            $avail = max(0, $stock - $inUse);

            return ['type' => 'error', 'text' => "Only <strong>$avail</strong> unit(s) of this equipment are free for these overlapping dates (stock: $stock, already committed: $inUse)."];
        }

        return null;
    }
}
