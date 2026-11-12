<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class EquipmentUnitAllocator
{
    public const RESERVABLE_STATUSES = ['available', 'allocated'];

    private static function lineBooking(string $table, string $key, int $lineId): array
    {
        $row = DB::table($table . ' as l')
            ->join('bookings as b', 'b.booking_id', '=', 'l.booking_id')
            ->where('l.' . $key, $lineId)
            ->first(['b.booking_id', 'b.shoot_date_start', 'b.shoot_date_end']);

        return $row ? [(int) $row->booking_id, $row->shoot_date_start, $row->shoot_date_end] : [0, null, null];
    }

    private static function busyUnitIds(string $pivot, string $lineTable, string $lineKey, string $unitCol,
        int $excludeLineId, int $bookingId, $start, $end): array
    {
        return DB::table($pivot . ' as p')
            ->join($lineTable . ' as l', 'l.' . $lineKey, '=', 'p.' . $lineKey)
            ->join('bookings as b', 'b.booking_id', '=', 'l.booking_id')
            ->where('p.' . $lineKey, '!=', $excludeLineId)
            ->where(function ($w) use ($bookingId, $start, $end) {
                $w->where('b.booking_id', $bookingId)
                    ->orWhere(function ($o) use ($start, $end) {
                        $o->whereNotIn('b.booking_status', ['cancelled', 'completed']);
                        if ($start && $end) {
                            $o->where('b.shoot_date_start', '<=', $end)->where('b.shoot_date_end', '>=', $start);
                        }
                    });
            })
            ->distinct()->pluck('p.' . $unitCol)->map(fn ($v) => (int) $v)->all();
    }

    public static function busyEquipmentUnitIds(int $excludeLineId, int $bookingId, $start, $end): array
    {
        return self::busyUnitIds('booking_equipment_units', 'booking_equipment', 'bk_equip_id', 'equipment_unit_id', $excludeLineId, $bookingId, $start, $end);
    }

    public static function busyAccessoryUnitIds(int $excludeLineId, int $bookingId, $start, $end): array
    {
        return self::busyUnitIds('booking_accessory_units', 'booking_accessories', 'ba_id', 'accessory_unit_id', $excludeLineId, $bookingId, $start, $end);
    }

    public static function reservableEquipmentUnits(array $equipmentIds, int $bookingId, $start, $end)
    {
        return DB::table('equipment_units')
            ->whereIn('equipment_id', $equipmentIds ?: [0])
            ->whereIn('status', self::RESERVABLE_STATUSES)
            ->whereNotIn('unit_id', self::busyEquipmentUnitIds(0, $bookingId, $start, $end) ?: [0])
            ->orderBy('asset_tag')
            ->get(['unit_id', 'equipment_id', 'asset_tag']);
    }

    public static function reservableAccessoryUnits(array $accessoryIds, int $bookingId, $start, $end)
    {
        return DB::table('accessory_units')
            ->whereIn('accessory_id', $accessoryIds ?: [0])
            ->whereIn('status', self::RESERVABLE_STATUSES)
            ->whereNotIn('unit_id', self::busyAccessoryUnitIds(0, $bookingId, $start, $end) ?: [0])
            ->orderBy('asset_tag')
            ->get(['unit_id', 'accessory_id', 'asset_tag']);
    }

    private static function settleStatus(string $unitTable, string $pivot, string $lineTable, string $lineKey, string $unitCol, $unitIds): void
    {
        $stillHeld = DB::table($pivot . ' as p')
            ->join($lineTable . ' as l', 'l.' . $lineKey, '=', 'p.' . $lineKey)
            ->join('bookings as b', 'b.booking_id', '=', 'l.booking_id')
            ->whereIn('p.' . $unitCol, $unitIds)
            ->whereNotIn('b.booking_status', ['cancelled', 'completed'])
            ->pluck('p.' . $unitCol)->all();
        DB::table($unitTable)->whereIn('unit_id', $unitIds)->whereNotIn('unit_id', $stillHeld ?: [0])
            ->where('status', 'allocated')->update(['status' => 'available']);
    }

    public static function allocate(int $bkEquipId, int $equipmentId, int $qty): void
    {
        if ($qty < 1) {
            return;
        }

        [$bookingId, $start, $end] = self::lineBooking('booking_equipment', 'bk_equip_id', $bkEquipId);
        $units = DB::table('equipment_units')
            ->where('equipment_id', $equipmentId)
            ->whereIn('status', self::RESERVABLE_STATUSES)
            ->whereNotIn('unit_id', self::busyEquipmentUnitIds($bkEquipId, $bookingId, $start, $end) ?: [0])
            ->orderByRaw("status = 'available' desc")->orderBy('asset_tag')
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
        DB::table('equipment_units')->whereIn('unit_id', $units)->where('status', 'available')->update(['status' => 'allocated']);
    }

    public static function release(int $bkEquipId): void
    {
        $unitIds = DB::table('booking_equipment_units')->where('bk_equip_id', $bkEquipId)->pluck('equipment_unit_id');
        if ($unitIds->isEmpty()) {
            return;
        }

        DB::table('booking_equipment_units')->where('bk_equip_id', $bkEquipId)->delete();
        self::settleStatus('equipment_units', 'booking_equipment_units', 'booking_equipment', 'bk_equip_id', 'equipment_unit_id', $unitIds);
    }

    public static function allocateSpecific(int $bkEquipId, array $unitIds): bool
    {
        $alreadyOnThisLine = DB::table('booking_equipment_units')->where('bk_equip_id', $bkEquipId)->pluck('equipment_unit_id')->all();

        $picked = array_values(array_filter(array_map('intval', $unitIds)));
        $unitIds = array_values(array_unique($picked));
        if (! $unitIds || count($unitIds) !== count($picked)) {
            return false;
        }

        [$bookingId, $start, $end] = self::lineBooking('booking_equipment', 'bk_equip_id', $bkEquipId);
        $busy = self::busyEquipmentUnitIds($bkEquipId, $bookingId, $start, $end);
        $validIds = DB::table('equipment_units')
            ->whereIn('unit_id', $unitIds)
            ->where(fn ($w) => $w->where(fn ($f) => $f->whereIn('status', self::RESERVABLE_STATUSES)->whereNotIn('unit_id', $busy ?: [0]))
                ->orWhereIn('unit_id', $alreadyOnThisLine ?: [0]))
            ->pluck('unit_id');
        if ($validIds->count() !== count($unitIds)) {
            return false;
        }

        self::release($bkEquipId);
        $now = now();
        DB::table('booking_equipment_units')->insert(
            $validIds->map(fn ($unitId) => [
                'bk_equip_id' => $bkEquipId, 'equipment_unit_id' => $unitId, 'allocated_at' => $now,
            ])->all()
        );
        DB::table('equipment_units')->whereIn('unit_id', $validIds)->where('status', 'available')->update(['status' => 'allocated']);

        return true;
    }

    public static function allocateSpecificAccessory(int $baId, array $unitIds): bool
    {
        $alreadyOnThisLine = DB::table('booking_accessory_units')->where('ba_id', $baId)->pluck('accessory_unit_id')->all();

        $picked = array_values(array_filter(array_map('intval', $unitIds)));
        $unitIds = array_values(array_unique($picked));
        if (! $unitIds || count($unitIds) !== count($picked)) {
            return false;
        }

        [$bookingId, $start, $end] = self::lineBooking('booking_accessories', 'ba_id', $baId);
        $busy = self::busyAccessoryUnitIds($baId, $bookingId, $start, $end);
        $validIds = DB::table('accessory_units')
            ->whereIn('unit_id', $unitIds)
            ->where(fn ($w) => $w->where(fn ($f) => $f->whereIn('status', self::RESERVABLE_STATUSES)->whereNotIn('unit_id', $busy ?: [0]))
                ->orWhereIn('unit_id', $alreadyOnThisLine ?: [0]))
            ->pluck('unit_id');
        if ($validIds->count() !== count($unitIds)) {
            return false;
        }

        self::releaseAccessory($baId);
        $now = now();
        DB::table('booking_accessory_units')->insert(
            $validIds->map(fn ($unitId) => [
                'ba_id' => $baId, 'accessory_unit_id' => $unitId, 'allocated_at' => $now,
            ])->all()
        );
        DB::table('accessory_units')->whereIn('unit_id', $validIds)->where('status', 'available')->update(['status' => 'allocated']);

        return true;
    }

    public static function detachEquipmentUnit(int $unitId): void
    {
        $lineIds = DB::table('booking_equipment_units')->where('equipment_unit_id', $unitId)->pluck('bk_equip_id');
        if ($lineIds->isEmpty()) {
            return;
        }
        DB::table('booking_equipment_units')->where('equipment_unit_id', $unitId)->delete();
        DB::table('booking_equipment')->whereIn('bk_equip_id', $lineIds)->update(['unit_confirmed' => 0]);
    }

    public static function detachAccessoryUnit(int $unitId): void
    {
        $lineIds = DB::table('booking_accessory_units')->where('accessory_unit_id', $unitId)->pluck('ba_id');
        if ($lineIds->isEmpty()) {
            return;
        }
        DB::table('booking_accessory_units')->where('accessory_unit_id', $unitId)->delete();
        DB::table('booking_accessories')->whereIn('ba_id', $lineIds)->update(['unit_confirmed' => 0]);
    }

    public static function allocateAccessory(int $baId, int $accessoryId, int $qty): void
    {
        if ($qty < 1) {
            return;
        }

        [$bookingId, $start, $end] = self::lineBooking('booking_accessories', 'ba_id', $baId);
        $units = DB::table('accessory_units')
            ->where('accessory_id', $accessoryId)
            ->whereIn('status', self::RESERVABLE_STATUSES)
            ->whereNotIn('unit_id', self::busyAccessoryUnitIds($baId, $bookingId, $start, $end) ?: [0])
            ->orderByRaw("status = 'available' desc")->orderBy('asset_tag')
            ->limit($qty)
            ->pluck('unit_id');

        if ($units->isEmpty()) {
            return;
        }

        $now = now();
        DB::table('booking_accessory_units')->insert(
            $units->map(fn ($unitId) => [
                'ba_id' => $baId, 'accessory_unit_id' => $unitId, 'allocated_at' => $now,
            ])->all()
        );
        DB::table('accessory_units')->whereIn('unit_id', $units)->where('status', 'available')->update(['status' => 'allocated']);
    }

    public static function releaseAccessory(int $baId): void
    {
        $unitIds = DB::table('booking_accessory_units')->where('ba_id', $baId)->pluck('accessory_unit_id');
        if ($unitIds->isEmpty()) {
            return;
        }

        DB::table('booking_accessory_units')->where('ba_id', $baId)->delete();
        self::settleStatus('accessory_units', 'booking_accessory_units', 'booking_accessories', 'ba_id', 'accessory_unit_id', $unitIds);
    }

    public static function releaseAllForBooking(int $bookingId): void
    {
        DB::table('booking_equipment')->where('booking_id', $bookingId)->pluck('bk_equip_id')
            ->each(fn ($bkEquipId) => self::release($bkEquipId));
        DB::table('booking_accessories')->where('booking_id', $bookingId)->pluck('ba_id')
            ->each(fn ($baId) => self::releaseAccessory($baId));
    }
}
