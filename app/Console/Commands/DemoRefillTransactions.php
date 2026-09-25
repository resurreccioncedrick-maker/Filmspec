<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rebuilds all TRANSACTIONAL demo data (clients, crew, bookings and everything under them) while
 * leaving accounts (users), the equipment/accessory catalog, and transport (vehicle_rates/
 * fleet_vehicles) completely untouched — a narrower version of DemoDataSeeder for a database
 * that already has a real catalog and real login accounts worth keeping.
 *
 * Existing client-role/crew-role user accounts are re-attached to freshly generated demo
 * client/crew profiles (same login email/password, new business-facing name/company), so nobody
 * loses the ability to sign in.
 */
class DemoRefillTransactions extends Command
{
    protected $signature = 'demo:refill-transactions {--force : Skip the confirmation prompt}';

    protected $description = 'Wipe and regenerate demo clients/crew/bookings, keeping accounts, catalog and transport as-is';

    private Carbon $today;
    private array $ids = [];

    private const TRUNCATE = [
        'terms_acceptances', 'documents', 'booking_comments', 'client_support_messages',
        'data_erasure_requests', 'booking_equipment_requests', 'booking_feedback', 'reminders',
        'booking_discounts', 'transport_assignments', 'incident_reports', 'crew_attendance',
        'booking_accessories', 'booking_equipment', 'booking_crew', 'cost_estimates', 'payments',
        'bookings', 'crew_qualifications', 'crew_unavailability', 'crew_members', 'clients',
    ];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will wipe clients/crew/bookings and all booking-linked data, keeping accounts/catalog/transport. Continue?')) {
            $this->warn('Cancelled.');

            return self::FAILURE;
        }

        $this->today = Carbon::parse('2026-09-25');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (self::TRUNCATE as $table) {
            DB::table($table)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $this->info('Cleared ' . count(self::TRUNCATE) . ' transactional tables.');

        $this->loadExistingCatalog();
        $this->seedCrew();
        $this->seedClients();
        $this->seedBookingsAndTransactions();
        $this->seedSupportingData();

        $this->info('Demo transactional data rebuilt — accounts, catalog and transport untouched.');

        return self::SUCCESS;
    }

    private function loadExistingCatalog(): void
    {
        $this->ids['equipment'] = DB::table('equipment')->pluck('equipment_id', 'equipment_name');
        $this->ids['accessories'] = DB::table('accessories')->pluck('accessory_id', 'accessory_name');
        $this->ids['positions'] = DB::table('crew_positions')->pluck('position_id', 'position_name');
        $this->ids['fleet'] = DB::table('fleet_vehicles')->pluck('fleet_vehicle_id', 'plate_no');

        $this->ids['clientUsers'] = DB::table('users as u')->join('roles as r', 'u.role_id', '=', 'r.role_id')
            ->where('r.role_name', 'client')->pluck('u.user_id')->all();
        $this->ids['crewUsers'] = DB::table('users as u')->join('roles as r', 'u.role_id', '=', 'r.role_id')
            ->where('r.role_name', 'crew')->pluck('u.user_id')->all();

        $staff = DB::table('users as u')->join('roles as r', 'u.role_id', '=', 'r.role_id')
            ->whereIn('r.role_name', ['super_admin', 'operations_manager', 'traffic', 'accounting'])
            ->select('u.user_id', 'r.role_name')->get();
        $this->ids['uid'] = [
            'ops' => $staff->firstWhere('role_name', 'operations_manager')->user_id ?? $staff->first()->user_id,
            'traffic1' => $staff->where('role_name', 'traffic')->first()->user_id ?? $staff->first()->user_id,
            'accounting' => $staff->firstWhere('role_name', 'accounting')->user_id ?? $staff->first()->user_id,
        ];
    }

    // ---------------------------------------------------------------- crew

    private function seedCrew(): void
    {
        $crew = [
            ['Allen', 'Mallare', 'Director of Photography', 'freelance', 4500],
            ['Ricardo', 'Bautista', 'Gaffer', 'freelance', 3200],
            ['Cristina', 'Lopez', 'Head Crew', 'staff', 3800],
            ['Marco', 'Dizon', '1st Assistant Camera', 'freelance', 2500],
            ['Anna', 'Reyes', '2nd Assistant Camera / DIT', 'on_call', 2200],
            ['Paolo', 'Ignacio', 'Key Grip', 'freelance', 2800],
            ['Samantha', 'Cruz', 'Sound Recordist', 'freelance', 3000],
            ['Kevin', 'Aquino', 'Camera Operator', 'freelance', 3500],
            ['Ella', 'Navarro', 'Production Assistant', 'on_call', 1200],
            ['Ramon', 'Aquino', 'Drone Pilot', 'freelance', 4000],
        ];
        $posNames = array_keys($this->ids['positions']->all());
        $crewUsers = $this->ids['crewUsers'];
        $crewIds = [];
        foreach ($crew as $i => [$fn, $ln, $posName, $emp, $rate]) {
            $posId = $this->ids['positions'][$posName] ?? $this->ids['positions'][$posNames[0]];
            $uid = $crewUsers[$i] ?? null; // first N crew rows re-attach to real crew accounts
            $cid = DB::table('crew_members')->insertGetId([
                'user_id' => $uid, 'first_name' => $fn, 'last_name' => $ln,
                'email' => strtolower("{$fn}.{$ln}") . '@filmspec-crew.ph', 'phone' => '09' . random_int(150000000, 999999999),
                'primary_position_id' => $posId, 'employment_type' => $emp, 'base_rate_12hr' => $rate,
                'overtime_rate' => round($rate * 0.15, 2), 'double_pay_rate' => $rate * 2,
                'monthly_salary' => $emp === 'staff' ? $rate * 22 : 0, 'total_shoots' => 0, 'status' => 'active',
                'date_joined' => $this->today->copy()->subMonths(random_int(3, 24))->toDateString(),
                'created_at' => $this->today->copy()->subMonths(random_int(3, 24)), 'updated_at' => $this->today,
            ]);
            $crewIds[$fn . ' ' . $ln] = $cid;
        }
        $this->ids['crew'] = $crewIds;

        $names = array_keys($crewIds);
        DB::table('crew_qualifications')->insert([
            ['crew_id' => $crewIds[$names[0]], 'title' => 'Certified Drone Pilot (CAAP)', 'issuing_body' => 'CAAP', 'issue_date' => $this->today->copy()->subYear(), 'expiry_date' => $this->today->copy()->addYear(), 'created_at' => $this->today, 'updated_at' => $this->today],
            ['crew_id' => $crewIds[$names[6]], 'title' => 'Basic Occupational Safety Training', 'issuing_body' => 'DOLE', 'issue_date' => $this->today->copy()->subMonths(10), 'expiry_date' => null, 'created_at' => $this->today, 'updated_at' => $this->today],
        ]);
        DB::table('crew_unavailability')->insert([
            'crew_id' => $crewIds[$names[5]], 'date_from' => $this->today->copy()->addDays(10)->toDateString(),
            'date_to' => $this->today->copy()->addDays(14)->toDateString(), 'reason' => 'Prior commitment on another production',
            'reason_category' => 'existing_commitment', 'created_by' => $this->ids['uid']['traffic1'], 'created_at' => $this->today,
        ]);
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
        $clientUsers = $this->ids['clientUsers'];
        $clientIds = [];
        foreach ($companies as $i => [$name, $entityType, $contact, $terms, $discPct]) {
            $uid = $clientUsers[$i] ?? null; // first N companies re-attach to real client accounts
            $email = $uid
                ? DB::table('users')->where('user_id', $uid)->value('email')
                : Str::slug($contact) . '@example.com';
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
        $equipNames = array_keys($this->ids['equipment']->all());
        $accNames = array_keys($this->ids['accessories']->all());
        $crewNames = array_keys($this->ids['crew']);

        $bookingSpecs = [];
        for ($m = -5; $m <= 2; $m++) {
            $monthStart = $this->today->copy()->startOfMonth()->addMonthsNoOverflow($m);
            $count = match (true) {
                $m < 0 => random_int(5, 8),
                $m === 0 => 6,
                default => random_int(3, 5),
            };
            for ($n = 0; $n < $count; $n++) {
                $start = $monthStart->copy()->addDays(random_int(0, 26));
                $end = $start->copy()->addDays(random_int(0, 2));
                $bookingSpecs[] = [
                    'client' => $clientNames[array_rand($clientNames)],
                    'title' => $projectTitles[array_rand($projectTitles)] . ' — ' . $clientNames[array_rand($clientNames)],
                    'type' => $projectTypes[array_rand($projectTypes)],
                    'start' => $start, 'end' => $end, 'status' => $this->pickStatus($m, $start),
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
                'booking_status' => $spec['status'], 'is_archived' => 0,
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
                continue;
            }

            $lineEquip = (array) array_rand(array_flip($equipNames), min(count($equipNames), random_int(2, 5)));
            $equipTotal = 0.0;
            $days = $spec['start']->diffInDays($spec['end']) + 1;
            foreach ($lineEquip as $eqName) {
                $eqId = $this->ids['equipment'][$eqName];
                $rate = (float) DB::table('equipment')->where('equipment_id', $eqId)->value('daily_rate');
                $qty = random_int(1, 2);
                DB::table('booking_equipment')->insert([
                    'booking_id' => $bookingId, 'equipment_id' => $eqId, 'quantity' => $qty, 'days' => $days, 'daily_rate' => $rate,
                ]);
                $equipTotal += $qty * $days * $rate;
            }

            $lineAcc = (array) array_rand(array_flip($accNames), min(count($accNames), random_int(1, 3)));
            $accTotal = 0.0;
            foreach ($lineAcc as $accName) {
                $accId = $this->ids['accessories'][$accName];
                $row = DB::table('accessories')->where('accessory_id', $accId)->first();
                $qty = random_int(1, 2);
                $rate = (float) $row->daily_rate;
                DB::table('booking_accessories')->insert([
                    'booking_id' => $bookingId, 'accessory_id' => $accId, 'quantity' => $qty, 'days' => $days,
                    'daily_rate' => $rate, 'is_included' => $row->is_included, 'subtotal' => $rate * $qty * $days,
                    'added_at' => $spec['created'],
                ]);
                $accTotal += $rate * $qty * $days;
            }

            $lineCrew = (array) array_rand(array_flip($crewNames), min(count($crewNames), random_int(3, 6)));
            $crewTotal = 0.0;
            foreach ($lineCrew as $crewName) {
                $crewId = $this->ids['crew'][$crewName];
                $posId = DB::table('crew_members')->where('crew_id', $crewId)->value('primary_position_id');
                $rate = (float) DB::table('crew_members')->where('crew_id', $crewId)->value('base_rate_12hr');
                DB::table('booking_crew')->insert([
                    'booking_id' => $bookingId, 'crew_id' => $crewId, 'position_id' => $posId,
                    'assignment_status' => 'confirmed', 'hours_worked' => $days, 'rate_used' => $rate, 'subtotal' => $rate * $days,
                ]);
                $crewTotal += $rate * $days;
                DB::table('crew_members')->where('crew_id', $crewId)->increment('total_shoots');
            }

            $subtotal = $equipTotal + $accTotal + $crewTotal;
            $vat = round($subtotal * 0.12, 2);
            $grand = $subtotal + $vat;

            $ceRef = $yearPrefix . '-' . str_pad($ceSeq++, 4, '0', STR_PAD_LEFT);
            $ceStatus = $spec['status'] === 'pending' ? 'draft' : 'confirmed';
            DB::table('cost_estimates')->insert([
                'booking_id' => $bookingId, 'ce_reference' => $ceRef, 'generated_by' => $this->ids['uid']['traffic1'],
                'confirmed_by' => $ceStatus === 'confirmed' ? $this->ids['uid']['ops'] : null,
                'confirmed_at' => $ceStatus === 'confirmed' ? $spec['created']->copy()->addDay() : null,
                'equipment_total' => $equipTotal, 'crew_total' => $crewTotal, 'other_charges' => 0, 'discount' => 0,
                'subtotal' => $subtotal, 'vat_rate' => 12.00, 'vat_amount' => $vat, 'grand_total' => $grand,
                'status' => $ceStatus, 'pricing_mode' => 'no_discount', 'vat_exempt' => 0,
                'accessories_total' => $accTotal, 'outsourced_total' => 0, 'transport_total' => 0,
                'generated_at' => $spec['created'],
            ]);

            DB::table('bookings')->where('booking_id', $bookingId)->update([
                'total_amount' => $subtotal, 'vat_amount' => $vat, 'final_amount' => $grand,
                'cost_approval_status' => $ceStatus === 'confirmed' ? 'client_approved' : null,
                'cost_approved_at' => $ceStatus === 'confirmed' ? $spec['created']->copy()->addDays(2) : null,
            ]);

            if ($ceStatus !== 'confirmed') {
                continue;
            }

            $this->seedPayments($bookingId, $grand, $spec['status'], $spec['created'], $spec['start']);

            if (in_array($spec['status'], ['completed', 'returned', 'ongoing'], true)) {
                $this->seedAttendance($bookingId, $lineCrew, $spec['start'], $spec['end']);
            }
        }
    }

    private function pickStatus(int $monthOffset, Carbon $start): string
    {
        if ($monthOffset < 0) {
            $roll = random_int(1, 100);

            return $roll <= 85 ? 'completed' : 'cancelled';
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
        if ($status === 'pending') {
            return;
        }
        $downpayment = round($grand * 0.5, 2);
        $methods = ['cash', 'gcash', 'bank_transfer'];

        DB::table('payments')->insert([
            'booking_id' => $bookingId, 'payment_type' => 'downpayment', 'payment_method' => $methods[array_rand($methods)],
            'amount' => $downpayment, 'reference_number' => 'REF-' . strtoupper(Str::random(8)),
            'payment_date' => $created->copy()->addDays(2)->toDateString(), 'received_by' => $this->ids['uid']['accounting'],
            'is_vat' => 1, 'receipt_number' => 'OR-' . random_int(10000, 99999), 'receipt_type' => 'official_receipt',
            'created_at' => $created->copy()->addDays(2),
        ]);

        $paymentStatus = 'partial';
        if (in_array($status, ['completed', 'returned', 'pending_inspection'], true)) {
            DB::table('payments')->insert([
                'booking_id' => $bookingId, 'payment_type' => 'final', 'payment_method' => $methods[array_rand($methods)],
                'amount' => round($grand - $downpayment, 2), 'reference_number' => 'REF-' . strtoupper(Str::random(8)),
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
        $completedIds = DB::table('bookings')->where('booking_status', 'completed')->pluck('booking_id')->values();
        $confirmedIds = DB::table('bookings')->whereIn('booking_status', ['confirmed', 'ongoing'])->pluck('booking_id');
        $anyEquipment = $this->ids['equipment']->values()->all();

        $incidentTypes = ['damaged', 'malfunction', 'late_return'];
        $causes = ['accident', 'lifespan', 'negligence', 'unknown'];
        for ($i = 0; $i < min(5, $completedIds->count()); $i++) {
            $bid = $completedIds->random();
            $shootDate = DB::table('bookings')->where('booking_id', $bid)->value('shoot_date_end');
            DB::table('incident_reports')->insert([
                'booking_id' => $bid, 'equipment_id' => $anyEquipment[array_rand($anyEquipment)],
                'reported_by' => $this->ids['uid']['traffic1'], 'incident_type' => $incidentTypes[array_rand($incidentTypes)],
                'incident_date' => $shootDate, 'description' => 'Minor issue noted during equipment check-in after the shoot.',
                'cause' => $causes[array_rand($causes)], 'resolution' => 'repair', 'charge_amount' => 0,
                'status' => random_int(0, 1) ? 'resolved' : 'open',
                'incident_number' => 'INC-' . $this->today->format('Y') . '-' . str_pad($i + 1, 3, '0', STR_PAD_LEFT),
                'created_at' => $this->today->copy()->subDays(random_int(5, 60)),
            ]);
        }

        foreach (DB::table('bookings')->inRandomOrder()->limit(4)->pluck('booking_id') as $bid) {
            DB::table('booking_discounts')->insert([
                'booking_id' => $bid, 'discount_type' => 'percent', 'discount_value' => 5, 'computed_amount' => 0,
                'reason' => 'Returning client loyalty discount', 'status' => 'approved',
                'proposed_by' => $this->ids['uid']['traffic1'], 'approved_by' => $this->ids['uid']['ops'],
                'approved_at' => $this->today->copy()->subDays(random_int(1, 30)),
                'created_at' => $this->today->copy()->subDays(random_int(2, 31)),
            ]);
        }

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

        $comments = [
            'Great service, crew was professional and on time.',
            'Equipment was in excellent condition, will book again.',
            'Smooth process from booking to return. Highly recommend.',
            'Good experience overall, minor delay on delivery.',
            'The team was very accommodating with our last-minute changes.',
        ];
        foreach ($completedIds->shuffle()->take(min(8, $completedIds->count())) as $bid) {
            $clientId = DB::table('bookings')->where('booking_id', $bid)->value('client_id');
            $userId = DB::table('clients')->where('client_id', $clientId)->value('user_id');
            if (! $userId) {
                continue;
            }
            DB::table('booking_feedback')->insert([
                'booking_id' => $bid, 'submitted_by' => $userId, 'rating' => random_int(4, 5),
                'comment' => $comments[array_rand($comments)], 'submitted_at' => $this->today->copy()->subDays(random_int(1, 40)),
            ]);
        }

        foreach ($confirmedIds->take(3) as $bid) {
            DB::table('booking_equipment_requests')->insert([
                'booking_id' => $bid, 'equipment_id' => $anyEquipment[array_rand($anyEquipment)],
                'item_type' => 'equipment', 'quantity' => 1, 'reason' => 'Additional backup unit requested on-site',
                'status' => 'pending', 'daily_rate' => 1000, 'requested_by' => $this->ids['uid']['traffic1'],
                'created_at' => $this->today->copy()->subDays(random_int(0, 3)),
            ]);
        }
    }
}
