<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fills in ONLY missing equipment/accessory/crew images — never touches a row that already has
 * one. A distinct icon-on-gradient card per row (can't fetch real product photos or photos of
 * real people), varied by name so a grid of similar items doesn't look like the same image
 * repeated.
 */
class FixMissingImages extends Command
{
    protected $signature = 'demo:fix-missing-images {--force : Skip the confirmation prompt}';

    protected $description = 'Add a placeholder image to any equipment/accessory/crew row that has none';

    private const PALETTE = [
        ['#3b82f6', '#1d4ed8'], ['#0ea5e9', '#0369a1'], ['#06b6d4', '#0e7490'],
        ['#10b981', '#047857'], ['#84cc16', '#4d7c0f'], ['#f59e0b', '#b45309'],
        ['#f97316', '#c2410c'], ['#ef4444', '#b91c1c'], ['#ec4899', '#be185d'],
        ['#a855f7', '#7e22ce'], ['#8b5cf6', '#6d28d9'], ['#6366f1', '#4338ca'],
        ['#14b8a6', '#0f766e'], ['#64748b', '#334155'],
    ];

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
        if (! $this->option('force') && ! $this->confirm('Add placeholder images to equipment/accessory/crew rows that are missing one?')) {
            $this->warn('Cancelled.');

            return self::FAILURE;
        }

        $eq = $this->fixEquipment();
        $ac = $this->fixAccessories();
        $cr = $this->fixCrew();
        $veh = $this->fixVehicles();

        $this->info("Equipment: {$eq}, Accessories: {$ac}, Crew: {$cr}, Vehicles: {$veh}.");

        return self::SUCCESS;
    }

    // "Missing" means either the column is empty, OR it points at a path with no real file
    // behind it — the ordinary case here, since uploaded photos lived on the app container's
    // ephemeral filesystem before a persistent volume existed and were lost on redeploy, while
    // the database (restored separately from a SQL-only backup) still references the old paths.
    private function broken($rows, string $col)
    {
        return $rows->filter(fn ($r) => ! $r->{$col} || ! Storage::disk('public')->exists($r->{$col}));
    }

    private function fixEquipment(): int
    {
        $cats = DB::table('equipment_categories')->pluck('category_name', 'category_id');
        $rows = $this->broken(DB::table('equipment')->get(), 'image_path');
        foreach ($rows as $r) {
            $catName = $cats[$r->category_id] ?? 'Other';
            $path = 'assets/fixed/equipment/eq-' . $r->equipment_id . '.svg';
            $this->putCard($path, $catName, $r->equipment_name . ' ' . $r->brand);
            DB::table('equipment')->where('equipment_id', $r->equipment_id)->update(['image_path' => $path]);
        }

        return $rows->count();
    }

    private function fixAccessories(): int
    {
        $rows = $this->broken(DB::table('accessories')->get(), 'image_path');
        foreach ($rows as $r) {
            $path = 'assets/fixed/accessories/ac-' . $r->accessory_id . '.svg';
            $this->putCard($path, 'Other', $r->accessory_name);
            DB::table('accessories')->where('accessory_id', $r->accessory_id)->update(['image_path' => $path]);
        }

        return $rows->count();
    }

    private function fixCrew(): int
    {
        $rows = $this->broken(DB::table('crew_members')->get(), 'photo_path');
        foreach ($rows as $i => $r) {
            $initials = mb_strtoupper(mb_substr($r->first_name, 0, 1) . mb_substr($r->last_name, 0, 1));
            [$c1] = self::PALETTE[abs(crc32($r->first_name . $r->last_name)) % count(self::PALETTE)];
            $path = 'assets/fixed/crew/cr-' . $r->crew_id . '.svg';
            $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">
  <rect width="200" height="200" fill="{$c1}"/>
  <circle cx="100" cy="80" r="34" fill="#ffffff" fill-opacity=".85"/>
  <path d="M40 176c8-38 40-58 60-58s52 20 60 58" fill="#ffffff" fill-opacity=".85"/>
  <text x="100" y="196" font-family="Arial, sans-serif" font-size="22" font-weight="700" text-anchor="middle" fill="#ffffff">{$initials}</text>
</svg>
SVG;
            Storage::disk('public')->put($path, $svg);
            DB::table('crew_members')->where('crew_id', $r->crew_id)->update(['photo_path' => $path]);
        }

        return $rows->count();
    }

    private function fixVehicles(): int
    {
        $rows = DB::table('fleet_vehicles')->get();
        foreach ($rows as $r) {
            $path = 'assets/fixed/vehicles/veh-' . $r->fleet_vehicle_id . '.svg';
            if (! Storage::disk('public')->exists($path)) {
                $this->putCard($path, 'Vehicle', $r->plate_no . ' ' . $r->brand);
            }
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
}
