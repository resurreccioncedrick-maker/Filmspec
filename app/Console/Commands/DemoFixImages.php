<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Replaces the shared, repetitive per-category placeholder images (every "Audio" item showing
 * the identical giant "AUD" card) with a distinct icon-on-gradient card per individual
 * equipment/accessory/vehicle row — same idea (can't fetch real product photos), varied enough
 * that a grid of similar items doesn't look like the same image four times.
 */
class DemoFixImages extends Command
{
    protected $signature = 'demo:fix-images {--force : Skip the confirmation prompt}';

    protected $description = 'Regenerate distinct placeholder images per equipment/accessory/vehicle row';

    // Deliberately gentler, more varied tones than the old flat-primary category icons —
    // and enough of them that adjacent items in a grid rarely repeat.
    private const PALETTE = [
        ['#3b82f6', '#1d4ed8'], ['#0ea5e9', '#0369a1'], ['#06b6d4', '#0e7490'],
        ['#10b981', '#047857'], ['#84cc16', '#4d7c0f'], ['#f59e0b', '#b45309'],
        ['#f97316', '#c2410c'], ['#ef4444', '#b91c1c'], ['#ec4899', '#be185d'],
        ['#a855f7', '#7e22ce'], ['#8b5cf6', '#6d28d9'], ['#6366f1', '#4338ca'],
        ['#14b8a6', '#0f766e'], ['#64748b', '#334155'],
    ];

    // A simple representative icon (viewBox 0 0 100 100) per category/kind — line art, not text.
    private const ICONS = [
        'Camera' => '<rect x="18" y="34" width="54" height="38" rx="6"/><path d="M30 34l6-10h16l6 10"/><circle cx="45" cy="53" r="12" fill="none" stroke-width="4"/><rect x="72" y="42" width="12" height="18" rx="2"/>',
        'Lens' => '<circle cx="50" cy="50" r="30"/><circle cx="50" cy="50" r="19" fill="none" stroke-width="4"/><circle cx="50" cy="50" r="8"/>',
        'Lighting' => '<circle cx="50" cy="42" r="20"/><rect x="42" y="62" width="16" height="10" rx="2"/><path d="M50 8v8M22 20l6 6M78 20l-6 6" stroke-width="4" fill="none" stroke-linecap="round"/>',
        'Audio' => '<rect x="38" y="16" width="24" height="40" rx="12"/><path d="M26 46a24 24 0 0 0 48 0" fill="none" stroke-width="5" stroke-linecap="round"/><path d="M50 70v14M38 84h24" stroke-width="5" stroke-linecap="round"/>',
        'Grip' => '<rect x="46" y="10" width="8" height="60" rx="3"/><path d="M20 70h60M30 70V50M70 70V50" stroke-width="6" fill="none" stroke-linecap="round"/><circle cx="50" cy="10" r="7"/>',
        'Electrical' => '<path d="M54 6 24 54h20l-4 40 34-52H54z"/>',
        'Other' => '<rect x="16" y="16" width="68" height="68" rx="10" fill="none" stroke-width="5"/><path d="M16 38h68M38 38v46" stroke-width="4" fill="none"/>',
        'Vehicle' => '<path d="M10 62V46l10-18h56l10 18v16" fill="none" stroke-width="5" stroke-linejoin="round"/><rect x="6" y="58" width="88" height="14" rx="4"/><circle cx="26" cy="76" r="9"/><circle cx="74" cy="76" r="9"/><path d="M22 46h56" stroke-width="4"/>',
    ];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Regenerate all equipment/accessory/vehicle placeholder images?')) {
            $this->warn('Cancelled.');

            return self::FAILURE;
        }

        $count = 0;
        $count += $this->fixEquipment();
        $count += $this->fixAccessories();
        $count += $this->fixVehicles();
        $this->clearOldCategoryFiles();

        $this->info("Regenerated {$count} images.");

        return self::SUCCESS;
    }

    private function fixEquipment(): int
    {
        $cats = DB::table('equipment_categories')->pluck('category_name', 'category_id');
        $rows = DB::table('equipment')->get();
        foreach ($rows as $r) {
            $catName = $cats[$r->category_id] ?? 'Other';
            $path = 'assets/demo/equipment/eq-' . $r->equipment_id . '.svg';
            $this->putCard($path, $catName, $r->equipment_name . ' ' . $r->brand);
            DB::table('equipment')->where('equipment_id', $r->equipment_id)->update(['image_path' => $path]);
        }

        return $rows->count();
    }

    private function fixAccessories(): int
    {
        $rows = DB::table('accessories')->get();
        foreach ($rows as $r) {
            $path = 'assets/demo/accessories/ac-' . $r->accessory_id . '.svg';
            $this->putCard($path, 'Other', $r->accessory_name);
            DB::table('accessories')->where('accessory_id', $r->accessory_id)->update(['image_path' => $path]);
        }

        return $rows->count();
    }

    private function fixVehicles(): int
    {
        $rows = DB::table('fleet_vehicles')->get();
        foreach ($rows as $r) {
            $path = 'assets/demo/vehicles/veh-' . $r->fleet_vehicle_id . '.svg';
            $this->putCard($path, 'Vehicle', $r->plate_no . ' ' . $r->brand);
            // fleet_vehicles has no image column in this schema — vehicle imagery, if the UI
            // ever adds it, would read from this same deterministic path; nothing to update here.
        }

        return $rows->count();
    }

    private function putCard(string $path, string $category, string $seedText): void
    {
        [$c1, $c2] = self::PALETTE[abs(crc32($seedText)) % count(self::PALETTE)];
        $icon = self::ICONS[$category] ?? self::ICONS['Other'];
        $gradId = 'g' . abs(crc32($path));

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240">
  <defs>
    <linearGradient id="{$gradId}" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="{$c1}"/>
      <stop offset="1" stop-color="{$c2}"/>
    </linearGradient>
  </defs>
  <rect width="240" height="240" rx="18" fill="url(#{$gradId})"/>
  <g transform="translate(70,60) scale(1)" fill="#ffffff" fill-opacity=".92" stroke="#ffffff" stroke-opacity=".92">
    {$icon}
  </g>
</svg>
SVG;
        Storage::disk('public')->put($path, $svg);
    }

    private function clearOldCategoryFiles(): void
    {
        foreach (Storage::disk('public')->files('assets/demo/equipment') as $f) {
            if (str_contains($f, 'cat-')) {
                Storage::disk('public')->delete($f);
            }
        }
        foreach (Storage::disk('public')->files('assets/demo/accessories') as $f) {
            if (str_contains($f, 'type-')) {
                Storage::disk('public')->delete($f);
            }
        }
    }
}
