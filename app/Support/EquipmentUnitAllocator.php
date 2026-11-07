<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Allocates specific physical equipment_units to a booking_equipment line at the moment
 * equipment is added to a booking — not later, at Equipment Checklist check-out time, which is
 * all that existed before. Lets the Incident Report form (and anything else) know which real
 * unit(s) are on a booking right from the start, instead of only finding out once the gear is
 * physically checked out.
 */
class EquipmentUnitAllocator
{
    /**
     * Auto-assigns up to $qty currently-'available' units of $equipmentId to the given
     * booking_equipment row, flips each to 'allocated', and records the link in
     * booking_equipment_units. Silently allocates fewer than $qty if the catalog doesn't have
     * enough individually-tracked units available right now — allocation is a convenience, not
     * a hard availability gate (EquipmentAvailability::check() already covers that at submit
     * time); equipment with no individually-tracked units at all is a no-op.
     */
    public static function allocate(int $bkEquipId, int $equipmentId, int $qty): void
    {
        if ($qty < 1) {
            return;
        }

        $units = DB::table('equipment_units')
            ->where('equipment_id', $equipmentId)
            ->where('status', 'available')
            ->orderBy('asset_tag')
            ->limit($qty)
            ->pluck('unit_id');

        if ($units->isEmpty()) {
            return;
        }

        $now = now();
        DB::table('booking_equipment_units')->insert(
            $units->map(fn ($unitId) => [
                'bk_equip_id' => $bkEquipId, 'equipment_unit_id' => $unitId, 'allocated_at' => $now,
            ])->all()
        );
        DB::table('equipment_units')->whereIn('unit_id', $units)->update(['status' => 'allocated']);
    }

    /**
     * Releases every unit allocated to this booking_equipment line back to 'available' and
     * removes the pivot rows — used wherever a booking's equipment line is removed/cleared.
     * Only releases units still sitting at 'allocated' (not 'in_field'/'under_maintenance'/etc.,
     * which reflect real-world state this release shouldn't clobber).
     */
    public static function release(int $bkEquipId): void
    {
        $unitIds = DB::table('booking_equipment_units')->where('bk_equip_id', $bkEquipId)->pluck('equipment_unit_id');
        if ($unitIds->isEmpty()) {
            return;
        }

        DB::table('equipment_units')->whereIn('unit_id', $unitIds)->where('status', 'allocated')->update(['status' => 'available']);
        DB::table('booking_equipment_units')->where('bk_equip_id', $bkEquipId)->delete();
    }
}
