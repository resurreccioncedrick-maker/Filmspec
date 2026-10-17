<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// One-time backfill: creates placeholder Physical Units for any equipment/accessory that
// already has a stock count but zero units recorded, so Stock Qty and Physical Units never
// disagree. Safe to run more than once — only acts on items that still have zero units.
class BackfillPhysicalUnits extends Command
{
    protected $signature = 'units:backfill';

    protected $description = 'Create placeholder Physical Units for equipment/accessories whose stock count has no matching units yet';

    public function handle(): int
    {
        $now = now();

        $eqRows = DB::table('equipment as e')
            ->leftJoin('equipment_units as u', function ($j) {
                $j->on('u.equipment_id', '=', 'e.equipment_id')->where('u.status', '!=', 'retired');
            })
            ->groupBy('e.equipment_id', 'e.stock_quantity')
            ->havingRaw('COUNT(u.unit_id) = 0 AND e.stock_quantity > 0')
            ->select('e.equipment_id', 'e.stock_quantity')
            ->get();

        $eqUnitsInserted = 0;
        foreach ($eqRows as $row) {
            $batch = [];
            for ($n = 1; $n <= (int) $row->stock_quantity; $n++) {
                $batch[] = [
                    'equipment_id' => $row->equipment_id, 'asset_tag' => "EQ{$row->equipment_id}-{$n}",
                    'condition' => 'good', 'status' => 'available',
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
            DB::table('equipment_units')->insert($batch);
            $eqUnitsInserted += count($batch);
        }
        $this->info("Equipment: backfilled {$eqRows->count()} items, {$eqUnitsInserted} units inserted.");

        $accRows = DB::table('accessories as a')
            ->leftJoin('accessory_units as u', function ($j) {
                $j->on('u.accessory_id', '=', 'a.accessory_id')->where('u.status', '!=', 'retired');
            })
            ->groupBy('a.accessory_id', 'a.quantity')
            ->havingRaw('COUNT(u.unit_id) = 0 AND a.quantity > 0')
            ->select('a.accessory_id', 'a.quantity')
            ->get();

        $accUnitsInserted = 0;
        foreach ($accRows as $row) {
            $batch = [];
            for ($n = 1; $n <= (int) $row->quantity; $n++) {
                $batch[] = [
                    'accessory_id' => $row->accessory_id, 'asset_tag' => "ACC{$row->accessory_id}-{$n}",
                    'condition' => 'good', 'status' => 'available',
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
            DB::table('accessory_units')->insert($batch);
            $accUnitsInserted += count($batch);
        }
        $this->info("Accessories: backfilled {$accRows->count()} items, {$accUnitsInserted} units inserted.");

        return self::SUCCESS;
    }
}
