<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Full demo dataset for FilmSpec — realistic Philippine film-production catalog, staff, crew,
 * clients and a multi-month history of bookings carried through their whole lifecycle (CE ->
 * confirm -> crew/equipment/transport -> attendance -> payments -> completion), so the app
 * looks like a company has actually been running it rather than a handful of disconnected rows.
 *
 * Deliberately NOT idempotent — run once against a freshly-truncated database (see
 * database/seeders/ResetDatabase.php), never appended to an already-seeded one.
 */
class DemoDataSeeder extends Seeder
{
    private Carbon $today;
    private array $ids = [];

    public function run(): void
    {
        $this->today = Carbon::parse('2026-09-25');

        $this->seedRolesAndUsers();
        $this->seedCatalog();
        $this->seedCrew();
        $this->seedFleet();
        $this->seedClients();
        $this->seedBookingsAndTransactions();
        $this->seedSupportingData();

        $this->command?->info('Demo data seeded.');
    }

    // ---------------------------------------------------------------- images

    private function putImage(string $path, string $svg): void
    {
        if (! Storage::disk('public')->exists($path)) {
            Storage::disk('public')->put($path, $svg);
        }
    }

    private function iconSvg(string $bg, string $fg, string $glyph): string
    {
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240">
  <rect width="240" height="240" rx="18" fill="{$bg}"/>
  <text x="120" y="145" font-family="Arial, sans-serif" font-size="96" font-weight="700"
        text-anchor="middle" fill="{$fg}">{$glyph}</text>
</svg>
SVG;
    }

    private function avatarSvg(string $initials, string $bg): string
    {
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">
  <rect width="200" height="200" fill="{$bg}"/>
  <circle cx="100" cy="80" r="34" fill="#ffffff" fill-opacity=".85"/>
  <path d="M40 176c8-38 40-58 60-58s52 20 60 58" fill="#ffffff" fill-opacity=".85"/>
  <text x="100" y="196" font-family="Arial, sans-serif" font-size="22" font-weight="700"
        text-anchor="middle" fill="#ffffff">{$initials}</text>
</svg>
SVG;
    }

    // ---------------------------------------------------------------- roles/users

    private function seedRolesAndUsers(): void
    {
        $roleIds = [];
        foreach ([
            'super_admin' => 'Full system access',
            'admin' => 'Administrative access',
            'operations_manager' => 'Operations oversight',
            'traffic' => 'Booking & scheduling coordination',
            'accounting' => 'Billing and financial records',
            'client' => 'Production company / client account',
            'crew' => 'Crew portal account',
        ] as $name => $desc) {
            $roleIds[$name] = DB::table('roles')->insertGetId([
                'role_name' => $name, 'description' => $desc,
            ]);
        }
        $this->ids['roles'] = $roleIds;

        $hash = Hash::make('Password123!');
        $staff = [
            ['Cedrick', 'Resurreccion', 'super_admin', 'cedrick@filmspec.ph'],
            ['Andrea', 'Reyes', 'admin', 'andrea.reyes@filmspec.ph'],
            ['Marco', 'Villanueva', 'operations_manager', 'marco.villanueva@filmspec.ph'],
            ['Bea', 'Santiago', 'traffic', 'bea.santiago@filmspec.ph'],
            ['Ryan', 'Cruz', 'traffic', 'ryan.cruz@filmspec.ph'],
            ['Liza', 'Fernandez', 'accounting', 'liza.fernandez@filmspec.ph'],
        ];
        $staffIds = [];
        foreach ($staff as [$fn, $ln, $role, $email]) {
            $staffIds[$role][] = DB::table('users')->insertGetId([
                'role_id' => $roleIds[$role], 'first_name' => $fn, 'last_name' => $ln,
                'email' => $email, 'password_hash' => $hash, 'phone' => '09' . random_int(150000000, 999999999),
                'is_active' => 1, 'created_at' => $this->today->copy()->subMonths(8), 'updated_at' => $this->today,
            ]);
        }
        $this->ids['staff'] = $staffIds;
        $this->ids['uid'] = [
            'super_admin' => $staffIds['super_admin'][0],
            'admin' => $staffIds['admin'][0],
            'ops' => $staffIds['operations_manager'][0],
            'traffic1' => $staffIds['traffic'][0],
            'traffic2' => $staffIds['traffic'][1],
            'accounting' => $staffIds['accounting'][0],
        ];
    }

    // ---------------------------------------------------------------- catalog

    private function seedCatalog(): void
    {
        // Categories already exist (Camera/Lens/Lighting/Audio/Grip/Electrical/Other) — reuse.
        $cats = DB::table('equipment_categories')->pluck('category_id', 'category_name');
        if ($cats->isEmpty()) {
            foreach (['Camera', 'Lens', 'Lighting', 'Audio', 'Grip', 'Electrical', 'Other'] as $name) {
                DB::table('equipment_categories')->insert(['category_name' => $name]);
            }
            $cats = DB::table('equipment_categories')->pluck('category_id', 'category_name');
        }
        $this->ids['cats'] = $cats;

        $catIcon = [
            'Camera' => ['#0060C7', '#fff', 'CAM'], 'Lens' => ['#0891b2', '#fff', 'LNS'],
            'Lighting' => ['#d97706', '#fff', 'LGT'], 'Audio' => ['#7c3aed', '#fff', 'AUD'],
            'Grip' => ['#334155', '#fff', 'GRP'], 'Electrical' => ['#dc2626', '#fff', 'ELC'],
            'Other' => ['#64748b', '#fff', 'GEN'],
        ];
        foreach ($catIcon as $cat => [$bg, $fg, $glyph]) {
            $this->putImage("assets/demo/equipment/cat-" . Str::slug($cat) . ".svg", $this->iconSvg($bg, $fg, $glyph));
        }

        $equipment = [
            // name, brand, model, category, daily_rate, requires_operator, condition
            ['ARRI Alexa Mini LF', 'ARRI', 'Alexa Mini LF', 'Camera', 18000, 1, 'excellent'],
            ['RED Komodo 6K', 'RED', 'Komodo 6K', 'Camera', 14000, 1, 'excellent'],
            ['Sony FX6', 'Sony', 'FX6', 'Camera', 9500, 0, 'excellent'],
            ['Sony FX3', 'Sony', 'FX3', 'Camera', 6500, 0, 'good'],
            ['Canon EOS C300 Mark III', 'Canon', 'C300 Mark III', 'Camera', 8500, 0, 'good'],
            ['Canon EOS R5 C', 'Canon', 'EOS R5 C', 'Camera', 5500, 0, 'good'],
            ['Blackmagic URSA Mini Pro 12K', 'Blackmagic Design', 'URSA Mini Pro 12K', 'Camera', 7000, 1, 'good'],
            ['DJI Ronin 4D', 'DJI', 'Ronin 4D', 'Camera', 9000, 1, 'excellent'],
            ['Canon CN-E 24-105mm T2.8', 'Canon', 'CN-E 24-105mm', 'Lens', 3500, 0, 'good'],
            ['Sigma Cine 18-35mm T2', 'Sigma', 'Cine 18-35mm', 'Lens', 2800, 0, 'good'],
            ['Zeiss Supreme Prime 35mm', 'Zeiss', 'Supreme Prime 35mm', 'Lens', 4200, 0, 'excellent'],
            ['Canon EF 70-200mm f/2.8', 'Canon', 'EF 70-200mm', 'Lens', 2200, 0, 'good'],
            ['Sony 24-70mm GM', 'Sony', '24-70mm GM', 'Lens', 2400, 0, 'good'],
            ['Aputure 600d Pro', 'Aputure', '600d Pro', 'Lighting', 3800, 0, 'excellent'],
            ['Aputure 300x', 'Aputure', '300x', 'Lighting', 2400, 0, 'good'],
            ['ARRI SkyPanel S60-C', 'ARRI', 'SkyPanel S60-C', 'Lighting', 5200, 0, 'excellent'],
            ['Godox VL300', 'Godox', 'VL300', 'Lighting', 1800, 0, 'good'],
            ['Litepanel Astra 6X', 'Litepanels', 'Astra 6X', 'Lighting', 2200, 0, 'good'],
            ['4-Bank Kino Flo', 'Kino Flo', 'Diva-Lite 4Bank', 'Lighting', 2600, 0, 'fair'],
            ['Sennheiser MKH416', 'Sennheiser', 'MKH416', 'Audio', 1800, 1, 'excellent'],
            ['Rode NTG5', 'Rode', 'NTG5', 'Audio', 1200, 0, 'good'],
            ['Sound Devices MixPre-10', 'Sound Devices', 'MixPre-10 II', 'Audio', 2800, 1, 'excellent'],
            ['Zoom H6', 'Zoom', 'H6', 'Audio', 900, 0, 'good'],
            ['Sennheiser EW 100 G4 Wireless', 'Sennheiser', 'EW 100 G4', 'Audio', 1500, 0, 'good'],
            ['Manfrotto 545B Tripod', 'Manfrotto', '545B', 'Grip', 900, 0, 'good'],
            ['Sachtler Video 20 Fluid Head', 'Sachtler', 'Video 20', 'Grip', 1600, 0, 'good'],
            ['DJI Ronin-S Gimbal', 'DJI', 'Ronin-S', 'Grip', 2000, 0, 'good'],
            ['C-Stand w/ Sandbag (set of 4)', 'Matthews', 'C-Stand Kit', 'Grip', 1200, 0, 'good'],
            ['Slider Rail 4ft', 'Rhino', 'Slider EVO', 'Grip', 1500, 0, 'good'],
            ['Generator 5kVA Silent', 'Honda', 'EU50iS', 'Electrical', 3500, 1, 'good'],
            ['Distro Box 60A', 'Lex', 'Distro 60A', 'Electrical', 1500, 0, 'good'],
            ['Stinger Cable Set', 'Generic', 'Stinger Set', 'Electrical', 600, 0, 'fair'],
            ['DJI Mavic 3 Cine Drone', 'DJI', 'Mavic 3 Cine', 'Other', 6500, 1, 'excellent'],
            ['Field Monitor 17" HDR', 'SmallHD', 'Cine 17', 'Other', 2400, 0, 'good'],
            ['Teradek Bolt 4K Wireless Video', 'Teradek', 'Bolt 4K', 'Other', 3200, 0, 'good'],
        ];

        $equipIds = [];
        foreach ($equipment as [$name, $brand, $model, $cat, $rate, $reqOp, $cond]) {
            $eid = DB::table('equipment')->insertGetId([
                'category_id' => $cats[$cat], 'equipment_name' => $name, 'brand' => $brand, 'model' => $model,
                'serial_number' => strtoupper(Str::random(3)) . '-' . random_int(10000, 99999),
                'description' => "Professional {$cat} gear — {$brand} {$model}.",
                'daily_rate' => $rate, 'condition_status' => $cond, 'availability_status' => 'available',
                'date_acquired' => $this->today->copy()->subMonths(random_int(4, 30))->toDateString(),
                'image_path' => "assets/demo/equipment/cat-" . Str::slug($cat) . ".svg",
                'requires_operator' => $reqOp, 'stock_quantity' => random_int(1, 3),
                'created_at' => $this->today->copy()->subMonths(random_int(4, 30)), 'updated_at' => $this->today,
            ]);
            $equipIds[$name] = $eid;

            // Per-unit tracking for a subset (the ones with stock_quantity > 1 get real unit rows).
            // equipment_units.condition uses a narrower vocabulary than equipment.condition_status
            // (no 'fair'/'under_repair'/'retired'), so map onto the closest equivalent.
            $unitCond = ['excellent' => 'excellent', 'good' => 'good', 'fair' => 'serviceable', 'under_repair' => 'damaged', 'retired' => 'damaged'][$cond] ?? 'good';
            $qty = DB::table('equipment')->where('equipment_id', $eid)->value('stock_quantity');
            for ($u = 1; $u <= $qty; $u++) {
                DB::table('equipment_units')->insert([
                    'equipment_id' => $eid, 'asset_tag' => 'EQ-' . str_pad($eid, 3, '0', STR_PAD_LEFT) . '-' . $u,
                    'serial_no' => strtoupper(Str::random(8)), 'condition' => $unitCond,
                    'status' => 'available', 'location' => 'FilmSpec Warehouse — Quezon City',
                    'date_acquired' => $this->today->copy()->subMonths(random_int(4, 30))->toDateString(),
                    'created_at' => $this->today, 'updated_at' => $this->today,
                ]);
            }
        }
        $this->ids['equipment'] = $equipIds;

        $accIcon = [
            'package_inclusion' => ['#16a34a', '#fff', 'INC'], 'optional_addon' => ['#0060C7', '#fff', 'ADD'],
            'internal_operational' => ['#64748b', '#fff', 'OPS'],
        ];
        foreach ($accIcon as $type => [$bg, $fg, $glyph]) {
            $this->putImage("assets/demo/accessories/type-{$type}.svg", $this->iconSvg($bg, $fg, $glyph));
        }

        $accessories = [
            // name, rate, type, tracking, linked equipment names (or null)
            ['V-Mount Battery 190Wh', 300, 'package_inclusion', 'individual', ['ARRI Alexa Mini LF', 'RED Komodo 6K', 'Sony FX6']],
            ['Battery Charger (Dual)', 0, 'package_inclusion', 'quantity', null],
            ['256GB CFexpress Card', 400, 'optional_addon', 'individual', ['ARRI Alexa Mini LF', 'RED Komodo 6K']],
            ['128GB SD Card V90', 250, 'optional_addon', 'individual', ['Sony FX3', 'Canon EOS R5 C']],
            ['Matte Box Kit', 800, 'optional_addon', 'quantity', ['ARRI Alexa Mini LF', 'RED Komodo 6K']],
            ['ND Filter Set (Variable)', 500, 'optional_addon', 'quantity', null],
            ['Wireless Follow Focus', 1200, 'optional_addon', 'individual', null],
            ['Boom Pole 12ft', 400, 'optional_addon', 'quantity', ['Sennheiser MKH416', 'Rode NTG5']],
            ['Lav Mic Kit (x2)', 900, 'optional_addon', 'quantity', ['Sennheiser EW 100 G4 Wireless']],
            ['Softbox Diffuser', 350, 'package_inclusion', 'quantity', ['Aputure 600d Pro', 'Aputure 300x']],
            ['Light Stand (Heavy Duty)', 250, 'package_inclusion', 'quantity', null],
            ['Gimbal Battery Grip', 500, 'optional_addon', 'individual', ['DJI Ronin-S Gimbal', 'DJI Ronin 4D']],
            ['Drone Extra Battery Set', 1500, 'optional_addon', 'individual', ['DJI Mavic 3 Cine Drone']],
            ['Rain Cover Kit', 300, 'optional_addon', 'quantity', null],
            ['Transport Case (Pelican)', 0, 'internal_operational', 'quantity', null],
            ['Cleaning Kit', 0, 'internal_operational', 'quantity', null],
            ['HDMI/SDI Cable Set', 200, 'package_inclusion', 'quantity', ['Field Monitor 17" HDR']],
            ['Gaffer Tape (per roll)', 150, 'optional_addon', 'quantity', null],
        ];

        $accIds = [];
        foreach ($accessories as [$name, $rate, $type, $tracking, $linkNames]) {
            $isIncluded = $type === 'package_inclusion' ? 1 : 0;
            $qty = $tracking === 'individual' ? random_int(3, 8) : random_int(5, 20);
            $aid = DB::table('accessories')->insertGetId([
                'accessory_name' => $name, 'description' => "{$name} — available for confirmed bookings.",
                'daily_rate' => $rate, 'is_included' => $isIncluded, 'accessory_type' => $type,
                'tracking_method' => $tracking, 'image_path' => "assets/demo/accessories/type-{$type}.svg",
                'quantity' => $qty, 'quantity_in_use' => 0, 'is_active' => 1,
                'is_public' => $type === 'internal_operational' ? 0 : 1,
                'created_at' => $this->today->copy()->subMonths(random_int(3, 10)),
            ]);
            $accIds[$name] = $aid;

            if ($tracking === 'individual') {
                for ($u = 1; $u <= $qty; $u++) {
                    DB::table('accessory_units')->insert([
                        'accessory_id' => $aid, 'asset_tag' => 'AC-' . str_pad($aid, 3, '0', STR_PAD_LEFT) . '-' . $u,
                        'serial_no' => strtoupper(Str::random(8)), 'condition' => 'good', 'status' => 'available',
                        'location' => 'FilmSpec Warehouse — Quezon City', 'created_at' => $this->today, 'updated_at' => $this->today,
                    ]);
                }
            }

            if ($linkNames) {
                foreach ($linkNames as $eqName) {
                    if (isset($equipIds[$eqName])) {
                        DB::table('equipment_accessory_links')->insert([
                            'equipment_id' => $equipIds[$eqName], 'accessory_id' => $aid, 'included_qty' => 1,
                        ]);
                    }
                }
            }
        }
        $this->ids['accessories'] = $accIds;
    }

    // ---------------------------------------------------------------- crew

    private function seedCrew(): void
    {
        $positions = [
            ['Director of Photography', 'Camera'], ['Camera Operator', 'Camera'],
            ['1st Assistant Camera', 'Camera'], ['2nd Assistant Camera / DIT', 'Camera'],
            ['Gaffer', 'Lighting'], ['Best Boy Electric', 'Lighting'],
            ['Key Grip', 'Grip'], ['Grip', 'Grip'],
            ['Sound Recordist', 'Audio'], ['Boom Operator', 'Audio'],
            ['Production Assistant', 'Production'], ['Drone Pilot', 'Camera'],
            ['Driver', 'Logistics/Transport'], ['Head Crew', 'Production'],
        ];
        $posIds = [];
        foreach ($positions as [$name, $dept]) {
            $posIds[$name] = DB::table('crew_positions')->insertGetId([
                'position_name' => $name, 'department' => $dept,
                'description' => "{$name} for on-location film production.",
                'responsibilities' => "Reports to the DOP/Head Crew; handles {$name} duties on set.",
            ]);
        }
        $this->ids['positions'] = $posIds;

        $crew = [
            ['Allen', 'Mallare', 'Director of Photography', 'freelance', 4500],
            ['Ricardo', 'Bautista', 'Gaffer', 'freelance', 3200],
            ['Cristina', 'Lopez', 'Head Crew', 'staff', 3800],
            ['Marco', 'Dizon', '1st Assistant Camera', 'freelance', 2500],
            ['Anna', 'Reyes', '2nd Assistant Camera / DIT', 'on_call', 2200],
            ['Jolo', 'Mallare', 'Driver', 'on_call', 1200],
            ['Paolo', 'Ignacio', 'Key Grip', 'freelance', 2800],
            ['Miguel', 'Torres', 'Grip', 'on_call', 1800],
            ['Samantha', 'Cruz', 'Sound Recordist', 'freelance', 3000],
            ['Diego', 'Ramos', 'Boom Operator', 'on_call', 1800],
            ['Kevin', 'Aquino', 'Camera Operator', 'freelance', 3500],
            ['Rico', 'Santos', 'Best Boy Electric', 'on_call', 2000],
            ['Ella', 'Navarro', 'Production Assistant', 'on_call', 1200],
            ['Ramon', 'Aquino', 'Drone Pilot', 'freelance', 4000],
        ];
        $crewIds = [];
        $palette = ['#0060C7', '#16a34a', '#d97706', '#7c3aed', '#dc2626', '#0891b2', '#334155'];
        foreach ($crew as $i => [$fn, $ln, $posName, $emp, $rate]) {
            $bg = $palette[$i % count($palette)];
            $initials = mb_substr($fn, 0, 1) . mb_substr($ln, 0, 1);
            $photoPath = "assets/demo/crew/{$initials}-{$i}.svg";
            $this->putImage($photoPath, $this->avatarSvg($initials, $bg));

            $cid = DB::table('crew_members')->insertGetId([
                'first_name' => $fn, 'last_name' => $ln, 'email' => strtolower("{$fn}.{$ln}") . '@filmspec-crew.ph',
                'phone' => '09' . random_int(150000000, 999999999), 'primary_position_id' => $posIds[$posName],
                'employment_type' => $emp, 'base_rate_12hr' => $rate, 'overtime_rate' => round($rate * 0.15, 2),
                'double_pay_rate' => $rate * 2, 'monthly_salary' => $emp === 'staff' ? $rate * 22 : 0,
                'total_shoots' => 0, 'status' => 'active', 'date_joined' => $this->today->copy()->subMonths(random_int(3, 24))->toDateString(),
                'photo_path' => $photoPath, 'created_at' => $this->today->copy()->subMonths(random_int(3, 24)), 'updated_at' => $this->today,
            ]);
            $crewIds[$fn . ' ' . $ln] = $cid;
        }
        $this->ids['crew'] = $crewIds;

        // A couple of qualifications for realism.
        DB::table('crew_qualifications')->insert([
            ['crew_id' => $crewIds['Allen Mallare'], 'title' => 'Certified Drone Pilot (CAAP)', 'issuing_body' => 'CAAP', 'issue_date' => $this->today->copy()->subYear(), 'expiry_date' => $this->today->copy()->addYear(), 'created_at' => $this->today, 'updated_at' => $this->today],
            ['crew_id' => $crewIds['Ramon Aquino'], 'title' => 'Certified Drone Pilot (CAAP)', 'issuing_body' => 'CAAP', 'issue_date' => $this->today->copy()->subMonths(6), 'expiry_date' => $this->today->copy()->addMonths(18), 'created_at' => $this->today, 'updated_at' => $this->today],
            ['crew_id' => $crewIds['Samantha Cruz'], 'title' => 'Basic Occupational Safety Training', 'issuing_body' => 'DOLE', 'issue_date' => $this->today->copy()->subMonths(10), 'expiry_date' => null, 'created_at' => $this->today, 'updated_at' => $this->today],
        ]);

        // One unavailability block for realism.
        DB::table('crew_unavailability')->insert([
            'crew_id' => $crewIds['Paolo Ignacio'], 'date_from' => $this->today->copy()->addDays(10)->toDateString(),
            'date_to' => $this->today->copy()->addDays(14)->toDateString(), 'reason' => 'Prior commitment on another production',
            'reason_category' => 'existing_commitment', 'created_by' => $this->ids['uid']['traffic1'], 'created_at' => $this->today,
        ]);
    }

    // ---------------------------------------------------------------- fleet

    private function seedFleet(): void
    {
        $rates = DB::table('vehicle_rates')->pluck('vehicle_id', 'vehicle_type');
        if ($rates->isEmpty()) {
            $defaults = [
                ['van', '10-Seater Van', 'Standard crew/equipment van', 2500, 'per_trip'],
                ['pickup', 'Pickup Truck', 'Equipment hauling', 1800, 'per_trip'],
                ['sedan', 'Sedan', 'Talent/crew transport', 1200, 'per_trip'],
                ['truck', '6-Wheeler Truck', 'Large lighting/grip packages', 3500, 'per_trip'],
                ['multicab', 'Multicab', 'Light loads within Metro Manila', 800, 'per_trip'],
                ['truck', '10-Wheeler Truck', 'Full production package', 5000, 'per_trip'],
            ];
            foreach ($defaults as [$type, $label, $desc, $rate, $basis]) {
                DB::table('vehicle_rates')->insert(['vehicle_type' => $type, 'label' => $label, 'description' => $desc, 'base_rate' => $rate, 'rate_basis' => $basis, 'is_active' => 1, 'created_at' => $this->today]);
            }
            $rates = DB::table('vehicle_rates')->pluck('vehicle_id', 'vehicle_type');
        }
        $this->ids['vehicleRates'] = DB::table('vehicle_rates')->get()->keyBy('label');

        $vehicleIcon = $this->iconSvg('#334155', '#fff', 'VEH');
        $this->putImage('assets/demo/vehicles/vehicle.svg', $vehicleIcon);

        $vehicles = [
            ['10-Seater Van', 'Toyota', 'HiAce', 'ABC-1234', 2022, 'White'],
            ['10-Seater Van', 'Nissan', 'NV350 Urvan', 'DEF-5678', 2021, 'Silver'],
            ['Pickup Truck', 'Toyota', 'Hilux', 'GHI-9012', 2020, 'Gray'],
            ['Sedan', 'Toyota', 'Vios', 'JKL-3456', 2023, 'White'],
            ['6-Wheeler Truck', 'Isuzu', 'ELF', 'MNO-7890', 2019, 'Blue'],
            ['Multicab', 'Suzuki', 'Multicab', 'PQR-2345', 2018, 'White'],
        ];
        $fleetIds = [];
        foreach ($vehicles as [$label, $brand, $model, $plate, $year, $color]) {
            $rateRow = $this->ids['vehicleRates'][$label] ?? null;
            if (! $rateRow) {
                continue;
            }
            $fid = DB::table('fleet_vehicles')->insertGetId([
                'vehicle_type_id' => $rateRow->vehicle_id, 'brand' => $brand, 'model' => $model,
                'plate_no' => $plate, 'year' => $year, 'color' => $color, 'status' => 'available',
                'created_at' => $this->today, 'updated_at' => $this->today,
            ]);
            $fleetIds[$plate] = $fid;
        }
        $this->ids['fleet'] = $fleetIds;
    }

    // ---------------------------------------------------------------- clients

    private function seedClients(): void
    {
        $companies = [
            ['Sunrise Coffee Co.', 'company', 'Marisol Tan', '50_downpayment', 0],
            ['Northstar Productions', 'company', 'Andres Reyes', '50_downpayment', 0],
            ['Bright Media Agency', 'company', 'Karen Uy', '90_days', 5],
            ['Zenith Films PH', 'company', 'Oliver Santos', '50_downpayment', 0],
            ['Manila Ad Collective', 'company', 'Grace Lim', '50_downpayment', 0],
            ['Horizon TVC Studio', 'company', 'Patrick Ong', '50_downpayment', 0],
            ['Bayan Broadcasting Network', 'company', 'Teresa Cordero', '2_weeks_crew', 0],
            ['Golden Harvest Beverages', 'company', 'Leo Mercado', '50_downpayment', 0],
            ['Wildflower Creative House', 'company', 'Nina Castillo', '50_downpayment', 0],
            ['De La Salle University — Film Dept', 'student', 'Miko Dela Cruz', '50_downpayment', 10],
            ['UP Film Institute', 'student', 'Sofia Ramirez', '50_downpayment', 10],
            ['Metro Fashion Week Inc.', 'company', 'Isabel Garcia', '90_days', 5],
            ['Vantage Realty Group', 'company', 'Jerome Villareal', '50_downpayment', 0],
            ['Independent — Juan Dela Cruz', 'individual', 'Juan Dela Cruz', '50_downpayment', 0],
        ];

        $roleId = $this->ids['roles']['client'];
        $hash = Hash::make('Password123!');
        $clientIds = [];
        foreach ($companies as $i => [$name, $entityType, $contact, $terms, $discPct]) {
            $email = Str::slug(explode(' ', $contact)[0] . '.' . explode(' ', $contact)[count(explode(' ', $contact)) - 1]) . '@example.com';
            $contactParts = explode(' ', $contact);
            $uid = DB::table('users')->insertGetId([
                'role_id' => $roleId, 'first_name' => $contactParts[0], 'last_name' => end($contactParts),
                'email' => $email, 'password_hash' => $hash, 'phone' => '09' . random_int(150000000, 999999999),
                'is_active' => 1, 'created_at' => $this->today->copy()->subMonths(random_int(2, 9)), 'updated_at' => $this->today,
            ]);
            $cid = DB::table('clients')->insertGetId([
                'user_id' => $uid, 'company_name' => $entityType === 'individual' ? '' : $name,
                'contact_person' => $entityType === 'individual' ? $name : $contact,
                'email' => $email, 'phone' => '09' . random_int(150000000, 999999999),
                'address' => 'Metro Manila, Philippines', 'tin' => (string) random_int(100000000, 999999999),
                'client_type' => $i < 3 ? 'first_time' : 'regular', 'status' => 'approved', 'is_active' => 1,
                'payment_terms' => $terms, 'is_vat_registered' => $entityType === 'company' ? 1 : 0,
                'entity_type' => $entityType, 'discount_pct' => $discPct,
                'created_at' => $this->today->copy()->subMonths(random_int(2, 9)), 'updated_at' => $this->today,
            ]);
            $clientIds[$name] = $cid;
        }
        $this->ids['clients'] = $clientIds;
    }

    // ---------------------------------------------------------------- bookings + transactions

    private function seedBookingsAndTransactions(): void
    {
        $projectTitles = [
            'Brand TVC', 'Autumn Promo Shoot', 'Product Launch Film', 'Corporate AVP',
            'Music Video', 'Behind the Scenes Doc', 'Fashion Editorial', 'Interview Series',
            'Social Media Campaign', 'Wedding Highlight Film', 'Short Film', 'Real Estate Walkthrough',
        ];
        $projectTypes = ['commercial', 'commercial', 'commercial', 'music_video', 'indie_film', 'tv_network', 'interview', 'other'];
        $clientNames = array_keys($this->ids['clients']);
        $equipNames = array_keys($this->ids['equipment']);
        $accNames = array_keys($this->ids['accessories']);
        $crewNames = array_keys($this->ids['crew']);
        $positions = $this->ids['positions'];
        $fleetPlates = array_keys($this->ids['fleet']);

        // Timeline: 5 months of history (mostly completed) + this month (mixed) + 2 months
        // ahead (confirmed/pending upcoming) — a realistic operational spread.
        $bookingSpecs = [];
        $seq = 1;
        for ($m = -5; $m <= 2; $m++) {
            $monthStart = $this->today->copy()->startOfMonth()->addMonthsNoOverflow($m);
            $count = match (true) {
                $m < 0 => random_int(5, 8),
                $m === 0 => 6,
                default => random_int(3, 5),
            };
            for ($n = 0; $n < $count; $n++) {
                $dayOffset = random_int(0, 26);
                $start = $monthStart->copy()->addDays($dayOffset);
                if ($m === 0 && $start->gt($this->today)) {
                    // future days this month are fine (upcoming), no adjustment needed
                }
                $duration = random_int(0, 2); // 1-3 day shoots
                $end = $start->copy()->addDays($duration);

                $status = $this->pickStatus($m, $start);
                $bookingSpecs[] = [
                    'seq' => $seq++, 'client' => $clientNames[array_rand($clientNames)],
                    'title' => $projectTitles[array_rand($projectTitles)] . ' — ' . $clientNames[array_rand($clientNames)],
                    'type' => $projectTypes[array_rand($projectTypes)],
                    'start' => $start, 'end' => $end, 'status' => $status,
                    'created' => $start->copy()->subDays(random_int(7, 21)),
                ];
            }
        }

        $yearPrefix = 'CE-' . $this->today->format('Y');
        $ceSeq = 1;
        $bookingRefSeq = 1;

        foreach ($bookingSpecs as $spec) {
            $clientId = $this->ids['clients'][$spec['client']];
            $bookingRef = 'FS-' . $this->today->format('Y') . '-' . str_pad($bookingRefSeq++, 4, '0', STR_PAD_LEFT);

            $director = $crewNames[array_rand($crewNames)];
            $isArchived = $spec['status'] === 'completed' && $spec['start']->lt($this->today->copy()->subMonths(4));

            $bookingId = DB::table('bookings')->insertGetId([
                'booking_reference' => $bookingRef, 'client_id' => $clientId, 'booking_type' => 'package',
                'project_title' => $spec['title'], 'ce_director_dop' => $director,
                'ce_contact_person' => DB::table('clients')->where('client_id', $clientId)->value('contact_person'),
                'ce_contact_number' => '09' . random_int(150000000, 999999999),
                'ce_contact_email' => DB::table('clients')->where('client_id', $clientId)->value('email'),
                'ce_prepared_by' => 'Cedrick Resurreccion',
                'project_type' => $spec['type'], 'ce_type' => 'fs_front',
                'shoot_date_start' => $spec['start']->toDateString(), 'shoot_date_end' => $spec['end']->toDateString(),
                'ce_due_date' => $spec['start']->copy()->subDays(3)->toDateString(),
                'shoot_location' => 'Metro Manila, Philippines',
                'booking_status' => $spec['status'], 'is_archived' => $isArchived ? 1 : 0,
                'archived_at' => $isArchived ? $spec['end']->copy()->addDays(30) : null,
                'payment_status' => 'unpaid', 'approval_status' => 'approved',
                'approved_by' => $this->ids['uid']['ops'], 'approved_at' => $spec['created']->copy()->addDay(),
                'created_by' => $this->ids['uid']['traffic1'],
                'created_at' => $spec['created'], 'updated_at' => $spec['start'],
            ]);

            if ($spec['status'] === 'cancelled') {
                DB::table('bookings')->where('booking_id', $bookingId)->update([
                    'cancellation_reason' => 'Client rescheduled to a later date',
                    'cancelled_by' => $this->ids['uid']['traffic1'],
                ]);
                continue; // no CE/crew/payments for a cancelled booking
            }

            // ---- Equipment + accessory lines ----
            $lineEquip = (array) array_rand(array_flip($equipNames), random_int(2, 5));
            $equipTotal = 0.0;
            foreach ($lineEquip as $eqName) {
                $eqId = $this->ids['equipment'][$eqName];
                $rate = (float) DB::table('equipment')->where('equipment_id', $eqId)->value('daily_rate');
                $qty = random_int(1, 2);
                $days = $spec['start']->diffInDays($spec['end']) + 1;
                DB::table('booking_equipment')->insert([
                    'booking_id' => $bookingId, 'equipment_id' => $eqId, 'quantity' => $qty, 'days' => $days,
                    'daily_rate' => $rate, 'notes' => null,
                ]);
                $equipTotal += $qty * $days * $rate;
            }

            $lineAcc = (array) array_rand(array_flip($accNames), random_int(1, 3));
            $accTotal = 0.0;
            foreach ($lineAcc as $accName) {
                $accId = $this->ids['accessories'][$accName];
                $row = DB::table('accessories')->where('accessory_id', $accId)->first();
                $qty = random_int(1, 2);
                $days = $spec['start']->diffInDays($spec['end']) + 1;
                $rate = (float) $row->daily_rate;
                DB::table('booking_accessories')->insert([
                    'booking_id' => $bookingId, 'accessory_id' => $accId, 'quantity' => $qty, 'days' => $days,
                    'daily_rate' => $rate, 'is_included' => $row->is_included, 'subtotal' => $rate * $qty * $days,
                    'added_at' => $spec['created'],
                ]);
                $accTotal += $rate * $qty * $days;
            }

            // ---- Crew assignments ----
            $lineCrew = (array) array_rand(array_flip($crewNames), random_int(3, 6));
            $crewTotal = 0.0;
            $days = $spec['start']->diffInDays($spec['end']) + 1;
            foreach ($lineCrew as $crewName) {
                $crewId = $this->ids['crew'][$crewName];
                $posId = DB::table('crew_members')->where('crew_id', $crewId)->value('primary_position_id');
                $rate = (float) DB::table('crew_members')->where('crew_id', $crewId)->value('base_rate_12hr');
                $assignStatus = in_array($spec['status'], ['completed', 'returned'], true) ? 'confirmed' : 'confirmed';
                DB::table('booking_crew')->insert([
                    'booking_id' => $bookingId, 'crew_id' => $crewId, 'position_id' => $posId,
                    'assignment_status' => $assignStatus, 'hours_worked' => $days, 'rate_used' => $rate,
                    'subtotal' => $rate * $days,
                ]);
                $crewTotal += $rate * $days;
                DB::table('crew_members')->where('crew_id', $crewId)->increment('total_shoots');
            }

            $subtotal = $equipTotal + $accTotal + $crewTotal;
            $vat = round($subtotal * 0.12, 2);
            $grand = $subtotal + $vat;

            // ---- Cost Estimate ----
            $ceRef = $yearPrefix . '-' . str_pad($ceSeq++, 4, '0', STR_PAD_LEFT);
            $ceStatus = in_array($spec['status'], ['pending'], true) ? 'draft' : 'confirmed';
            $ceId = DB::table('cost_estimates')->insertGetId([
                'booking_id' => $bookingId, 'ce_reference' => $ceRef, 'generated_by' => $this->ids['uid']['traffic1'],
                'confirmed_by' => $ceStatus === 'confirmed' ? $this->ids['uid']['ops'] : null,
                'confirmed_at' => $ceStatus === 'confirmed' ? $spec['created']->copy()->addDay() : null,
                'equipment_total' => $equipTotal, 'crew_total' => $crewTotal, 'other_charges' => 0,
                'discount' => 0, 'subtotal' => $subtotal, 'vat_rate' => 12.00, 'vat_amount' => $vat,
                'grand_total' => $grand, 'status' => $ceStatus, 'pricing_mode' => 'no_discount',
                'vat_exempt' => 0, 'accessories_total' => $accTotal, 'outsourced_total' => 0,
                'transport_total' => 0, 'generated_at' => $spec['created'],
            ]);

            DB::table('bookings')->where('booking_id', $bookingId)->update([
                'total_amount' => $subtotal, 'vat_amount' => $vat, 'final_amount' => $grand,
                'cost_approval_status' => $ceStatus === 'confirmed' ? 'client_approved' : null,
                'cost_approved_at' => $ceStatus === 'confirmed' ? $spec['created']->copy()->addDays(2) : null,
            ]);

            if ($ceStatus !== 'confirmed') {
                continue; // still a draft/pending booking — no transport, payments, attendance yet
            }

            // ---- Transport ----
            if (random_int(1, 100) <= 70) {
                $plate = $fleetPlates[array_rand($fleetPlates)];
                $driverName = $crewNames[array_rand($crewNames)];
                DB::table('transport_assignments')->insert([
                    'booking_id' => $bookingId, 'fleet_vehicle_id' => $this->ids['fleet'][$plate],
                    'driver_crew_id' => $this->ids['crew'][$driverName], 'dispatch_date' => $spec['start']->toDateString(),
                    'departure_time' => '06:00:00', 'destination' => 'Metro Manila, Philippines',
                    'created_at' => $spec['created'], 'updated_at' => $spec['created'],
                ]);
            }

            // ---- Payments (proper transactions) ----
            $this->seedPayments($bookingId, $grand, $spec['status'], $spec['created'], $spec['start']);

            // ---- Attendance (for shoots that have happened) ----
            if (in_array($spec['status'], ['completed', 'returned', 'ongoing'], true)) {
                $this->seedAttendance($bookingId, $lineCrew, $spec['start'], $spec['end']);
            }
        }
    }

    private function pickStatus(int $monthOffset, Carbon $start): string
    {
        if ($monthOffset < 0) {
            $roll = random_int(1, 100);

            return $roll <= 85 ? 'completed' : ($roll <= 95 ? 'cancelled' : 'completed');
        }
        if ($monthOffset === 0) {
            if ($start->lt($this->today->copy()->subDays(3))) {
                return 'completed';
            }
            if ($start->lte($this->today)) {
                return ['ongoing', 'pending_inspection', 'returned'][array_rand(['ongoing', 'pending_inspection', 'returned'])];
            }

            return 'confirmed';
        }

        return random_int(1, 100) <= 20 ? 'pending' : 'confirmed';
    }

    private function seedPayments(int $bookingId, float $grand, string $status, Carbon $created, Carbon $shootStart): void
    {
        $downpayment = round($grand * 0.5, 2);
        $methods = ['cash', 'gcash', 'bank_transfer'];

        if ($status === 'pending') {
            return;
        }

        DB::table('payments')->insert([
            'booking_id' => $bookingId, 'payment_type' => 'downpayment', 'payment_method' => $methods[array_rand($methods)],
            'amount' => $downpayment, 'reference_number' => 'REF-' . strtoupper(Str::random(8)),
            'payment_date' => $created->copy()->addDays(2)->toDateString(), 'received_by' => $this->ids['uid']['accounting'],
            'is_vat' => 1, 'receipt_number' => 'OR-' . random_int(10000, 99999), 'receipt_type' => 'official_receipt',
            'created_at' => $created->copy()->addDays(2),
        ]);

        $paymentStatus = 'partial';

        if (in_array($status, ['completed', 'returned', 'pending_inspection'], true)) {
            $balance = round($grand - $downpayment, 2);
            DB::table('payments')->insert([
                'booking_id' => $bookingId, 'payment_type' => 'final', 'payment_method' => $methods[array_rand($methods)],
                'amount' => $balance, 'reference_number' => 'REF-' . strtoupper(Str::random(8)),
                'payment_date' => $shootStart->copy()->addDays(2)->toDateString(), 'received_by' => $this->ids['uid']['accounting'],
                'is_vat' => 1, 'receipt_number' => 'OR-' . random_int(10000, 99999), 'receipt_type' => 'official_receipt',
                'created_at' => $shootStart->copy()->addDays(2),
            ]);
            $paymentStatus = 'paid';
        }

        DB::table('bookings')->where('booking_id', $bookingId)->update(['payment_status' => $paymentStatus]);
    }

    private function seedAttendance(int $bookingId, array $lineCrew, Carbon $start, Carbon $end): void
    {
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            foreach ($lineCrew as $crewName) {
                $crewId = $this->ids['crew'][$crewName];
                $roll = random_int(1, 100);
                $status = $roll <= 90 ? 'present' : ($roll <= 96 ? 'late' : 'no_show');
                DB::table('crew_attendance')->insert([
                    'booking_id' => $bookingId, 'crew_id' => $crewId, 'status' => $status,
                    'attendance_date' => $cursor->toDateString(),
                    'reason' => $status === 'no_show' ? 'Personal emergency' : null,
                    'reason_category' => $status === 'no_show' ? 'personal' : null,
                    'logged_by' => $this->ids['uid']['traffic1'], 'created_at' => $cursor,
                ]);
            }
            $cursor->addDay();
        }
    }

    // ---------------------------------------------------------------- supporting data

    private function seedSupportingData(): void
    {
        $completedBookings = DB::table('bookings')->where('booking_status', 'completed')->pluck('booking_id', 'booking_reference');
        $confirmedBookings = DB::table('bookings')->whereIn('booking_status', ['confirmed', 'ongoing'])->pluck('booking_id');
        $anyEquipment = array_values($this->ids['equipment']);
        $anyCrew = array_values($this->ids['crew']);

        // Incident reports — a handful, tied to real completed bookings.
        $incidentTypes = ['damaged', 'malfunction', 'late_return'];
        $causes = ['accident', 'lifespan', 'negligence', 'unknown'];
        $completedIds = $completedBookings->values();
        for ($i = 0; $i < min(5, $completedIds->count()); $i++) {
            $bid = $completedIds->random();
            $eqId = $anyEquipment[array_rand($anyEquipment)];
            $shootDate = DB::table('bookings')->where('booking_id', $bid)->value('shoot_date_end');
            DB::table('incident_reports')->insert([
                'booking_id' => $bid, 'equipment_id' => $eqId, 'reported_by' => $this->ids['uid']['traffic1'],
                'incident_type' => $incidentTypes[array_rand($incidentTypes)],
                'incident_date' => $shootDate, 'description' => 'Minor issue noted during equipment check-in after the shoot.',
                'cause' => $causes[array_rand($causes)], 'resolution' => 'repair', 'charge_amount' => 0,
                'status' => random_int(0, 1) ? 'resolved' : 'open',
                'incident_number' => 'INC-' . $this->today->format('Y') . '-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'created_at' => $this->today->copy()->subDays(random_int(5, 60)),
            ]);
        }

        // Booking discounts — a few approved requests.
        $regularClientBookings = DB::table('bookings')->inRandomOrder()->limit(4)->pluck('booking_id');
        foreach ($regularClientBookings as $bid) {
            DB::table('booking_discounts')->insert([
                'booking_id' => $bid, 'discount_type' => 'percent', 'discount_value' => 5,
                'computed_amount' => 0, 'reason' => 'Returning client loyalty discount',
                'status' => 'approved', 'proposed_by' => $this->ids['uid']['traffic1'],
                'approved_by' => $this->ids['uid']['ops'], 'approved_at' => $this->today->copy()->subDays(random_int(1, 30)),
                'created_at' => $this->today->copy()->subDays(random_int(2, 31)),
            ]);
        }

        // Reminders.
        $reminders = [
            ['todo', 'high', 'Confirm generator rental for next week\'s shoots', 3],
            ['todo', 'normal', 'Follow up on pending client approvals', 1],
            ['partners_meeting', 'normal', 'Quarterly partner sync — equipment vendors', 7],
            ['todo', 'high', 'Renew crew insurance coverage', 14],
            ['todo', 'normal', 'Restock gaffer tape and consumables', 5],
        ];
        foreach ($reminders as [$type, $priority, $title, $daysOut]) {
            DB::table('reminders')->insert([
                'type' => $type, 'priority' => $priority, 'title' => $title,
                'reminder_date' => $this->today->copy()->addDays($daysOut)->toDateString(),
                'meeting_time' => $type === 'partners_meeting' ? '14:00:00' : null,
                'is_done' => 0, 'visible_to_crew' => 0, 'created_by' => $this->ids['uid']['ops'],
                'created_at' => $this->today, 'updated_at' => $this->today,
            ]);
        }

        // FAQs (only if empty).
        if (DB::table('faqs')->count() === 0) {
            $faqs = [
                ['How far in advance should I book equipment?', 'We recommend booking at least 5-7 business days in advance to guarantee availability.', 'Booking'],
                ['What payment methods do you accept?', 'Cash, GCash, and bank transfer. A 50% downpayment is required to confirm most bookings.', 'Payments'],
                ['Do you provide crew with the equipment?', 'Yes — camera, lighting, and grip packages can include qualified crew on request.', 'Crew'],
                ['What happens if equipment is damaged during a shoot?', 'Report it immediately through your booking. Our team assesses the incident and applies charges per our equipment policy.', 'Equipment'],
            ];
            foreach ($faqs as $i => [$q, $a, $cat]) {
                DB::table('faqs')->insert(['question' => $q, 'answer' => $a, 'category' => $cat, 'sort_order' => $i, 'is_active' => 1, 'created_at' => $this->today, 'updated_at' => $this->today]);
            }
        }

        // Feedback for a handful of completed bookings.
        $feedbackBookings = $completedIds->shuffle()->take(min(8, $completedIds->count()));
        $comments = [
            'Great service, crew was professional and on time.',
            'Equipment was in excellent condition, will book again.',
            'Smooth process from booking to return. Highly recommend.',
            'Good experience overall, minor delay on delivery.',
            'The team was very accommodating with our last-minute changes.',
        ];
        foreach ($feedbackBookings as $bid) {
            $clientId = DB::table('bookings')->where('booking_id', $bid)->value('client_id');
            $userId = DB::table('clients')->where('client_id', $clientId)->value('user_id');
            if (! $userId) {
                continue;
            }
            DB::table('booking_feedback')->insert([
                'booking_id' => $bid, 'submitted_by' => $userId, 'rating' => random_int(4, 5),
                'comment' => $comments[array_rand($comments)],
                'submitted_at' => $this->today->copy()->subDays(random_int(1, 40)),
            ]);
        }

        // A couple of field/resource requests still open.
        foreach ($confirmedBookings->take(3) as $bid) {
            DB::table('booking_equipment_requests')->insert([
                'booking_id' => $bid, 'equipment_id' => $anyEquipment[array_rand($anyEquipment)],
                'item_type' => 'equipment', 'quantity' => 1, 'reason' => 'Additional backup unit requested on-site',
                'status' => 'pending', 'daily_rate' => 1000, 'requested_by' => $this->ids['uid']['traffic2'],
                'created_at' => $this->today->copy()->subDays(random_int(0, 3)),
            ]);
        }
    }
}
