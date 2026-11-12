<?php

namespace App\Console\Commands;

use App\Support\EquipmentUnitAllocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseStaleUnitAllocations extends Command
{
    protected $signature = 'demo:release-stale-unit-allocations {--force : Skip the confirmation prompt}';

    protected $description = 'Release equipment/accessory units still allocated to an already-completed/cancelled booking';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Release units still allocated to completed/cancelled bookings?')) {
            $this->warn('Cancelled.');

            return self::FAILURE;
        }

        $staleBookingIds = DB::table('bookings')->whereIn('booking_status', ['completed', 'cancelled'])->pluck('booking_id');

        $eqDone = 0;
        DB::table('booking_equipment')->whereIn('booking_id', $staleBookingIds)
            ->whereIn('bk_equip_id', DB::table('booking_equipment_units')->select('bk_equip_id'))
            ->pluck('bk_equip_id')
            ->each(function ($bkEquipId) use (&$eqDone) {
                EquipmentUnitAllocator::release($bkEquipId);
                $eqDone++;
            });

        $accDone = 0;
        DB::table('booking_accessories')->whereIn('booking_id', $staleBookingIds)
            ->whereIn('ba_id', DB::table('booking_accessory_units')->select('ba_id'))
            ->pluck('ba_id')
            ->each(function ($baId) use (&$accDone) {
                EquipmentUnitAllocator::releaseAccessory($baId);
                $accDone++;
            });

        $this->info("Released units for {$eqDone} equipment line(s) and {$accDone} accessory line(s) tied to completed/cancelled bookings.");

        return self::SUCCESS;
    }
}
