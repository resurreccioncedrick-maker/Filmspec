<?php

namespace App\Console\Commands;

use App\Support\EquipmentUnitAllocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time (but safe to re-run) backfill: allocates specific equipment_units/accessory_units to
 * every existing booking_equipment/booking_accessories row that predates EquipmentUnitAllocator
 * (introduced to run at insert-time going forward) and has no pivot row yet. Without this,
 * every booking created before the feature shipped keeps showing "every unit of this model"
 * in the Incident Report Unit picker instead of the one(s) actually on that booking.
 */
class BackfillUnitAllocations extends Command
{
    protected $signature = 'demo:backfill-unit-allocations {--force : Skip the confirmation prompt}';

    protected $description = 'Allocate specific units to existing booking_equipment/booking_accessories rows that have none yet';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Backfill unit allocation for every existing booking equipment/accessory line that has none?')) {
            $this->warn('Cancelled.');

            return self::FAILURE;
        }

        $eqDone = 0;
        // Only bookings still in play — allocating units to an already-completed/cancelled
        // booking would lock them away for good (nothing ever releases a finished booking again).
        $activeBookingIds = DB::table('bookings')->whereNotIn('booking_status', ['completed', 'cancelled'])->pluck('booking_id');

        $rows = DB::table('booking_equipment as be')
            ->leftJoin('booking_equipment_units as beu', 'be.bk_equip_id', '=', 'beu.bk_equip_id')
            ->whereNull('beu.id')
            ->whereIn('be.booking_id', $activeBookingIds)
            ->select('be.bk_equip_id', 'be.booking_id', 'be.equipment_id', 'be.quantity')
            ->distinct()
            ->get();
        foreach ($rows as $r) {
            // Prefer the Equipment Checklist's actually-recorded unit (ground truth — real
            // check-out happened against a specific unit) over guessing fresh from whatever's
            // 'available' right now. Just link the pivot without touching status: the
            // checklist's own unit already reflects that unit's true current state (in_field,
            // back to available, etc.) — this backfill shouldn't override it.
            $checklistUnits = DB::table('equipment_checklist')
                ->where('booking_id', $r->booking_id)->where('equipment_id', $r->equipment_id)
                ->where('direction', 'out')->whereNotNull('equipment_unit_id')
                ->distinct()->pluck('equipment_unit_id');

            if ($checklistUnits->isNotEmpty()) {
                $now = now();
                DB::table('booking_equipment_units')->insertOrIgnore(
                    $checklistUnits->take($r->quantity)->map(fn ($unitId) => [
                        'bk_equip_id' => $r->bk_equip_id, 'equipment_unit_id' => $unitId, 'allocated_at' => $now,
                    ])->all()
                );
            } else {
                EquipmentUnitAllocator::allocate((int) $r->bk_equip_id, (int) $r->equipment_id, (int) $r->quantity);
            }
            $eqDone++;
        }

        $accDone = 0;
        $accRows = DB::table('booking_accessories as ba')
            ->leftJoin('booking_accessory_units as bau', 'ba.ba_id', '=', 'bau.ba_id')
            ->whereNull('bau.id')
            ->whereIn('ba.booking_id', $activeBookingIds)
            ->select('ba.ba_id', 'ba.booking_id', 'ba.accessory_id', 'ba.quantity')
            ->distinct()
            ->get();
        foreach ($accRows as $r) {
            $checklistUnits = DB::table('equipment_checklist')
                ->where('booking_id', $r->booking_id)->where('accessory_id', $r->accessory_id)
                ->where('direction', 'out')->whereNotNull('accessory_unit_id')
                ->distinct()->pluck('accessory_unit_id');

            if ($checklistUnits->isNotEmpty()) {
                $now = now();
                DB::table('booking_accessory_units')->insertOrIgnore(
                    $checklistUnits->take($r->quantity)->map(fn ($unitId) => [
                        'ba_id' => $r->ba_id, 'accessory_unit_id' => $unitId, 'allocated_at' => $now,
                    ])->all()
                );
            } else {
                EquipmentUnitAllocator::allocateAccessory((int) $r->ba_id, (int) $r->accessory_id, (int) $r->quantity);
            }
            $accDone++;
        }

        $this->info("Processed {$eqDone} equipment line(s), {$accDone} accessory line(s). (Lines for gear with no individually-tracked units, or none currently 'available', are left unallocated — same as the live insert-time behavior.)");

        return self::SUCCESS;
    }
}
