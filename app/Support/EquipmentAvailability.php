<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Shared by BookingDetailController (addEquipment/fieldAddEquipment) and
 * FieldRequestsController (dispatch) — decides whether $qty more of a given equipment can be
 * committed to a booking's date range, without duplicating the same query three times.
 *
 * Single-unit equipment (stock_quantity===1, the overwhelming majority) keeps this app's
 * original behavior exactly: availability_status is a meaningful whole-item flag, and a
 * conflict names the one other booking holding it.
 *
 * Multi-unit equipment can't be gated on availability_status at all — every checkout path
 * unconditionally sets it to 'rented' the moment ANY unit is checked out (there's no per-unit
 * counter), so treating that as a hard block would refuse a booking that only needs one of
 * several spare units still genuinely free. Decided purely by summing what's already committed
 * to OTHER active, date-overlapping bookings against real stock_quantity.
 */
class EquipmentAvailability
{
    /**
     * @param  object  $booking  must expose shoot_date_start/shoot_date_end
     * @return array{type: string, text: string}|null null means available
     */
    public static function check(int $equipmentId, int $qty, int $excludeBookingId, object $booking): ?array
    {
        $equip = DB::table('equipment')->where('equipment_id', $equipmentId)->first();
        if (! $equip) {
            return ['type' => 'error', 'text' => 'Equipment not found.'];
        }
        $stock = max(1, (int) $equip->stock_quantity);

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
