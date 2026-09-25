<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Rebuilds all TRANSACTIONAL demo data (clients, crew, bookings and everything under them) while
 * leaving accounts (users), the equipment/accessory catalog, and the vehicle_rates/fleet_vehicles
 * catalog completely untouched — a narrower version of DemoDataSeeder for a database that already
 * has a real catalog and real login accounts worth keeping.
 *
 * Existing client-role/crew-role user accounts are re-attached to freshly generated demo
 * client/crew profiles (same login email/password, new business-facing name/company), so nobody
 * loses the ability to sign in.
 *
 * v2: unlike the first version, this one keeps every generated row internally consistent with
 * the app's own release/approval gates instead of just filling columns — a booking that has had
 * equipment checked out always has cost_approval_status='client_approved' and (if it needs a
 * driver) a Driver crew member assigned, crew are never double-booked across overlapping shoots,
 * equipment.availability_status is recomputed from the actual generated checkout/checkin history
 * instead of left stale, and the handful of tables that were simply never populated before
 * (booking_comments, documents, client_support_messages, terms_acceptances, transport_assignments,
 * equipment_checklist/equipment_transactions) now get real, gate-consistent demo rows too.
 */
class DemoRefillTransactions extends Command
{
    protected $signature = 'demo:refill-transactions {--force : Skip the confirmation prompt}';

    protected $description = 'Wipe and regenerate demo clients/crew/bookings, keeping accounts, catalog and transport as-is';

    private Carbon $today;
    private array $ids = [];
    private array $crewBusy = []; // crew_id => [[Carbon start, Carbon end], ...]
    private int $incidentSeq = 1;

    private const TRUNCATE = [
        'terms_acceptances', 'documents', 'booking_comments', 'client_support_messages',
        'data_erasure_requests', 'booking_equipment_requests', 'booking_feedback', 'reminders',
        'booking_discounts', 'transport_assignments', 'incident_reports', 'equipment_checklist',
        'equipment_transactions', 'crew_attendance', 'booking_accessories', 'booking_equipment',
        'booking_crew', 'cost_estimates', 'payments', 'bookings', 'crew_qualifications',
        'crew_unavailability', 'crew_members', 'clients',
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

        // These are transactional/derived state, not catalog identity — a stale 'rented'/
        // 'assigned' left over from the data we just wiped must not survive the wipe.
        DB::table('equipment')->where('availability_status', 'rented')->update(['availability_status' => 'available']);
        DB::table('fleet_vehicles')->where('status', 'assigned')->update(['status' => 'available']);

        $this->loadExistingCatalog();
        $this->seedCrew();
        $this->seedClients();
        $this->seedBookingsAndTransactions();
        $this->recomputeEquipmentAvailability();
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
        $this->ids['vehicleRates'] = DB::table('vehicle_rates')->where('is_active', 1)->get();

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
        // A dedicated Driver is required for any booking that uses FilmSpec transport (the
        // release-readiness gate checks for a crew member holding the Driver position) — the
        // original 10-person roster had nobody in that position, which meant the driver-required
        // gate could never actually be satisfied by seeded data.
        $driverPosName = collect(array_keys($this->ids['positions']->all()))->first(fn ($n) => stripos($n, 'driver') !== false);
        if ($driverPosName) {
            $crew[] = ['Jolo', 'Ferrer', $driverPosName, 'on_call', 1500];
        }

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
        $this->ids['driverName'] = $driverPosName ? array_key_last($crewIds) : null;

        $names = array_keys($crewIds);
        DB::table('crew_qualifications')->insert([
            ['crew_id' => $crewIds[$names[0]], 'title' => 'Certified Drone Pilot (CAAP)', 'issuing_body' => 'CAAP', 'issue_date' => $this->today->copy()->subYear(), 'expiry_date' => $this->today->copy()->addYear(), 'created_at' => $this->today, 'updated_at' => $this->today],
            ['crew_id' => $crewIds[$names[6]], 'title' => 'Basic Occupational Safety Training', 'issuing_body' => 'DOLE', 'issue_date' => $this->today->copy()->subMonths(10), 'expiry_date' => null, 'created_at' => $this->today, 'updated_at' => $this->today],
        ]);
        DB::table('crew_unavailability')->insert([
            ['crew_id' => $crewIds[$names[5]], 'date_from' => $this->today->copy()->addDays(10)->toDateString(),
                'date_to' => $this->today->copy()->addDays(14)->toDateString(), 'reason' => 'Prior commitment on another production',
                'reason_category' => 'existing_commitment', 'created_by' => $this->ids['uid']['traffic1'], 'created_at' => $this->today],
            ['crew_id' => $crewIds[$names[1]], 'date_from' => $this->today->copy()->addDays(3)->toDateString(),
                'date_to' => $this->today->copy()->addDays(5)->toDateString(), 'reason' => 'Family emergency leave',
                'reason_category' => 'personal', 'created_by' => $this->ids['uid']['traffic1'], 'created_at' => $this->today],
        ]);
    }

    /** True if $crewId has no other overlapping-date booking already registered this run. */
    private function crewIsFree(int $crewId, Carbon $start, Carbon $end): bool
    {
        foreach ($this->crewBusy[$crewId] ?? [] as [$bStart, $bEnd]) {
            if ($start->lte($bEnd) && $end->gte($bStart)) {
                return false;
            }
        }

        return true;
    }

    private function markCrewBusy(int $crewId, Carbon $start, Carbon $end): void
    {
        $this->crewBusy[$crewId][] = [$start, $end];
    }

    /** Picks up to $count crew names free for [$start,$end], never double-booking anyone. */
    private function pickAvailableCrew(array $pool, Carbon $start, Carbon $end, int $count, array $exclude = []): array
    {
        $free = array_values(array_filter($pool, function ($name) use ($start, $end, $exclude) {
            if (in_array($name, $exclude, true)) {
                return false;
            }

            return $this->crewIsFree($this->ids['crew'][$name], $start, $end);
        }));
        shuffle($free);
        $picked = array_slice($free, 0, min($count, count($free)));
        foreach ($picked as $name) {
            $this->markCrewBusy($this->ids['crew'][$name], $start, $end);
        }

        return $picked;
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
        $driverName = $this->ids['driverName'];
        $nonDriverCrewNames = $driverName ? array_values(array_diff($crewNames, [$driverName])) : $crewNames;

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
                    'transport' => false, 'forceNoDriver' => false, 'pendingApproval' => false,
                ];
            }
        }

        // Decide which bookings use FilmSpec transport (drives the driver-required gate) —
        // roughly a fifth of released/releasable bookings, plus two deliberate, clearly-labeled
        // demo scenarios: one 'confirmed' booking left without a driver so the blocked gate has
        // a real example, and one already-progressed booking with the driver correctly assigned
        // so the clean pass-through state has one too.
        $eligible = array_keys(array_filter($bookingSpecs, fn ($s) => in_array($s['status'], ['confirmed', 'ongoing', 'pending_inspection', 'returned', 'completed'], true)));
        shuffle($eligible);
        foreach (array_slice($eligible, 0, (int) ceil(count($eligible) * 0.18)) as $idx) {
            $bookingSpecs[$idx]['transport'] = true;
        }
        $firstConfirmed = collect($eligible)->first(fn ($idx) => $bookingSpecs[$idx]['status'] === 'confirmed');
        if ($firstConfirmed !== null && $driverName) {
            $bookingSpecs[$firstConfirmed]['transport'] = true;
            $bookingSpecs[$firstConfirmed]['forceNoDriver'] = true;
        }
        $firstOngoing = collect($eligible)->first(fn ($idx) => in_array($bookingSpecs[$idx]['status'], ['ongoing', 'pending_inspection'], true));
        if ($firstOngoing !== null && $driverName) {
            $bookingSpecs[$firstOngoing]['transport'] = true;
            $bookingSpecs[$firstOngoing]['forceNoDriver'] = false;
        }
        // One more 'confirmed' booking deliberately left awaiting client approval, so that UI
        // state (distinct from the driver-gate one above) has a real example too.
        $confirmedIdxs = collect($eligible)->filter(fn ($idx) => $bookingSpecs[$idx]['status'] === 'confirmed' && $idx !== $firstConfirmed)->values();
        if ($confirmedIdxs->isNotEmpty()) {
            $bookingSpecs[$confirmedIdxs->first()]['pendingApproval'] = true;
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
            $equipLines = [];
            foreach ($lineEquip as $eqName) {
                $eqId = $this->ids['equipment'][$eqName];
                $rate = (float) DB::table('equipment')->where('equipment_id', $eqId)->value('daily_rate');
                $qty = random_int(1, 2);
                DB::table('booking_equipment')->insert([
                    'booking_id' => $bookingId, 'equipment_id' => $eqId, 'quantity' => $qty, 'days' => $days, 'daily_rate' => $rate,
                ]);
                $equipTotal += $qty * $days * $rate;
                $equipLines[] = ['id' => $eqId, 'qty' => $qty];
            }

            $lineAcc = (array) array_rand(array_flip($accNames), min(count($accNames), random_int(1, 3)));
            $accTotal = 0.0;
            $accLines = [];
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
                $accLines[] = ['id' => $accId, 'qty' => $qty];
            }

            // A booking using FilmSpec transport needs its driver picked from the availability
            // pool too (same double-booking rule as regular crew) — decided before the general
            // crew pick so the driver reservation and the regular crew pool don't collide. If the
            // one driver on the roster is already busy on these dates, the booking simply doesn't
            // use FilmSpec transport rather than being left with transport assigned and no driver
            // able to satisfy the release gate (the exact contradiction this whole rebuild exists
            // to eliminate) — except for the one deliberate forceNoDriver demo booking, which stays
            // at 'confirmed' forever and is never actually released, so no contradiction arises.
            // No Driver position in the catalog at all means the release gate could never be
            // satisfied for any transport-using booking, so transport is simply never used.
            $useTransport = $spec['transport'] && $driverName;
            $lineCrew = [];
            $driverAssigned = false;
            if ($useTransport && ! $spec['forceNoDriver']) {
                if ($this->crewIsFree($this->ids['crew'][$driverName], $spec['start'], $spec['end'])) {
                    $lineCrew[] = $driverName;
                    $this->markCrewBusy($this->ids['crew'][$driverName], $spec['start'], $spec['end']);
                    $driverAssigned = true;
                } else {
                    $useTransport = false;
                }
            }
            $restPool = $useTransport ? $nonDriverCrewNames : $crewNames;
            $lineCrew = array_merge($lineCrew, $this->pickAvailableCrew($restPool, $spec['start'], $spec['end'], random_int(3, 6), $lineCrew));

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

            $transportCost = 0.0;
            $vehicleRateId = null;
            if ($useTransport && $this->ids['vehicleRates']->isNotEmpty()) {
                $vr = $this->ids['vehicleRates']->random();
                $vehicleRateId = $vr->vehicle_id;
                $transportCost = (float) $vr->base_rate;
            }

            $subtotal = $equipTotal + $accTotal + $crewTotal;
            $vat = round($subtotal * 0.12, 2);
            $grand = $subtotal + $vat + $transportCost;

            $ceRef = $yearPrefix . '-' . str_pad($ceSeq++, 4, '0', STR_PAD_LEFT);
            $ceStatus = $spec['status'] === 'pending' ? 'draft' : 'confirmed';
            DB::table('cost_estimates')->insert([
                'booking_id' => $bookingId, 'ce_reference' => $ceRef, 'generated_by' => $this->ids['uid']['traffic1'],
                'confirmed_by' => $ceStatus === 'confirmed' ? $this->ids['uid']['ops'] : null,
                'confirmed_at' => $ceStatus === 'confirmed' ? $spec['created']->copy()->addDay() : null,
                'equipment_total' => $equipTotal, 'crew_total' => $crewTotal, 'other_charges' => 0, 'discount' => 0,
                'subtotal' => $subtotal, 'vat_rate' => 12.00, 'vat_amount' => $vat, 'grand_total' => $grand,
                'status' => $ceStatus, 'pricing_mode' => 'no_discount', 'vat_exempt' => 0,
                'accessories_total' => $accTotal, 'outsourced_total' => 0, 'transport_total' => $transportCost,
                'generated_at' => $spec['created'],
            ]);

            // A booking whose equipment has actually been released (anything past 'confirmed')
            // MUST show client_approved here — that is exactly the gate the app enforces before
            // checkout, so seeded data must never contradict it. Only a still-'confirmed' booking
            // may legitimately still be awaiting approval, and only the one deliberately marked
            // 'pendingApproval' above is left that way; the rest default to approved once the CE
            // is confirmed, matching normal fast-moving demo bookings.
            if ($ceStatus !== 'confirmed') {
                $costApproval = null;
            } elseif ($spec['status'] !== 'confirmed') {
                $costApproval = 'client_approved';
            } else {
                $costApproval = $spec['pendingApproval'] ? 'pending_client' : 'client_approved';
            }

            DB::table('bookings')->where('booking_id', $bookingId)->update([
                'total_amount' => $subtotal, 'vat_amount' => $vat, 'final_amount' => $grand,
                'cost_approval_status' => $costApproval,
                'cost_approved_at' => $costApproval === 'client_approved' ? $spec['created']->copy()->addDays(2) : null,
                'vehicle_rate_id' => $vehicleRateId,
                'transportation_cost' => $transportCost,
                'location_zone' => $useTransport ? 'manila' : null,
                'transport_multiplier' => 1.00,
            ]);

            if ($ceStatus !== 'confirmed') {
                continue;
            }

            $this->seedPayments($bookingId, $grand, $spec['status'], $spec['created'], $spec['start']);

            $released = in_array($spec['status'], ['ongoing', 'pending_inspection', 'returned', 'completed'], true);
            if ($released) {
                $this->seedAttendance($bookingId, $lineCrew, $spec['start'], $spec['end']);
                $returned = in_array($spec['status'], ['pending_inspection', 'returned', 'completed'], true);
                $this->seedChecklistAndTransactions($bookingId, $equipLines, $accLines, $returned, $spec['start'], $spec['end']);
            }

            // Only a CURRENTLY active dispatch keeps a transport_assignments row — the real
            // TransportController deletes the row (freeing the vehicle) once an assignment is
            // completed, so a 'returned'/'completed' booking would have none left in reality.
            if ($useTransport && $vehicleRateId && in_array($spec['status'], ['confirmed', 'ongoing'], true)) {
                $this->seedTransportAssignment($bookingId, $vehicleRateId, $driverAssigned ? $this->ids['crew'][$driverName] : null, $spec['start']);
            }
        }
    }

    private function seedTransportAssignment(int $bookingId, int $vehicleRateId, ?int $driverCrewId, Carbon $dispatchDate): void
    {
        $fleetVehicle = DB::table('fleet_vehicles')->where('vehicle_type_id', $vehicleRateId)->where('status', 'available')->inRandomOrder()->first()
            ?? DB::table('fleet_vehicles')->where('status', 'available')->inRandomOrder()->first();
        if (! $fleetVehicle) {
            return;
        }
        DB::table('transport_assignments')->insert([
            'booking_id' => $bookingId, 'fleet_vehicle_id' => $fleetVehicle->fleet_vehicle_id,
            'driver_crew_id' => $driverCrewId, 'dispatch_date' => $dispatchDate->toDateString(),
            'departure_time' => '08:00:00', 'destination' => 'Client shoot location — Metro Manila',
            'notes' => null, 'created_at' => $this->today, 'updated_at' => $this->today,
        ]);
        DB::table('fleet_vehicles')->where('fleet_vehicle_id', $fleetVehicle->fleet_vehicle_id)->update(['status' => 'assigned', 'updated_at' => $this->today]);
    }

    /**
     * Mirrors ChecklistController::saveChecklist()'s real side effects (equipment_checklist +
     * equipment_transactions rows, and an auto-created incident_reports row on damaged/missing
     * check-in) so a completed/returned/ongoing booking's checklist page shows real history
     * instead of every item sitting unchecked despite the booking supposedly being done.
     */
    private function seedChecklistAndTransactions(int $bookingId, array $equipLines, array $accLines, bool $returned, Carbon $start, Carbon $end): void
    {
        $uid = $this->ids['uid']['ops'];
        $outConds = ['good', 'good', 'good', 'excellent', 'excellent', 'fair'];
        $checkedOutAt = $start->copy()->addHours(random_int(7, 10));
        $checkedInAt = $end->copy()->addHours(random_int(15, 19));

        foreach ($equipLines as $line) {
            $condOut = $outConds[array_rand($outConds)];
            DB::table('equipment_checklist')->insert([
                'booking_id' => $bookingId, 'equipment_id' => $line['id'], 'direction' => 'out',
                'quantity_expected' => $line['qty'], 'quantity_actual' => $line['qty'], 'condition_out' => $condOut,
                'checked' => 1, 'notes' => null, 'checked_by' => $uid, 'checked_at' => $checkedOutAt,
            ]);
            DB::table('equipment_transactions')->insert([
                'booking_id' => $bookingId, 'equipment_id' => $line['id'], 'transaction_type' => 'checkout',
                'transaction_date' => $checkedOutAt, 'condition_out' => $condOut, 'notes' => null, 'handled_by' => $uid,
            ]);

            if (! $returned) {
                continue;
            }

            $roll = random_int(1, 100);
            $condIn = $roll <= 90 ? 'good' : ($roll <= 97 ? 'fair' : ($roll <= 99 ? 'damaged' : 'missing'));
            DB::table('equipment_checklist')->insert([
                'booking_id' => $bookingId, 'equipment_id' => $line['id'], 'direction' => 'in',
                'quantity_expected' => $line['qty'], 'quantity_actual' => $line['qty'], 'condition_in' => $condIn,
                'checked' => 1, 'notes' => null, 'checked_by' => $uid, 'checked_at' => $checkedInAt,
            ]);
            DB::table('equipment_transactions')->insert([
                'booking_id' => $bookingId, 'equipment_id' => $line['id'], 'transaction_type' => 'checkin',
                'transaction_date' => $checkedInAt, 'condition_in' => $condIn, 'notes' => null, 'handled_by' => $uid,
            ]);

            if (in_array($condIn, ['damaged', 'missing'], true)) {
                DB::table('incident_reports')->insert([
                    'booking_id' => $bookingId, 'equipment_id' => $line['id'],
                    'reported_by' => $uid, 'incident_type' => $condIn, 'incident_date' => $checkedInAt->toDateString(),
                    'description' => 'Noted during equipment check-in after the shoot.',
                    'cause' => ['accident', 'lifespan', 'negligence', 'unknown'][array_rand(['accident', 'lifespan', 'negligence', 'unknown'])],
                    'resolution' => 'repair', 'charge_amount' => 0,
                    'status' => random_int(0, 1) ? 'resolved' : 'open',
                    'incident_number' => 'INC-' . $this->today->format('Y') . '-' . str_pad($this->incidentSeq++, 3, '0', STR_PAD_LEFT),
                    'created_at' => $checkedInAt,
                ]);
            }
        }

        // Accessories go through the same checklist screen but never generate equipment_
        // transactions/incidents (accessories aren't serialized inventory) — matches
        // ChecklistController::saveChecklist()'s `! $isAcc` guard exactly.
        foreach ($accLines as $line) {
            DB::table('equipment_checklist')->insert([
                'booking_id' => $bookingId, 'accessory_id' => $line['id'], 'direction' => 'out',
                'quantity_expected' => $line['qty'], 'quantity_actual' => $line['qty'], 'condition_out' => 'good',
                'checked' => 1, 'notes' => null, 'checked_by' => $uid, 'checked_at' => $checkedOutAt,
            ]);
            if ($returned) {
                DB::table('equipment_checklist')->insert([
                    'booking_id' => $bookingId, 'accessory_id' => $line['id'], 'direction' => 'in',
                    'quantity_expected' => $line['qty'], 'quantity_actual' => $line['qty'], 'condition_in' => 'good',
                    'checked' => 1, 'notes' => null, 'checked_by' => $uid, 'checked_at' => $checkedInAt,
                ]);
            }
        }
    }

    /** Derives equipment.availability_status from the checkout/checkin history just generated. */
    private function recomputeEquipmentAvailability(): void
    {
        $latest = DB::table('equipment_transactions')
            ->orderBy('equipment_id')->orderByDesc('transaction_id')
            ->get(['equipment_id', 'transaction_type', 'condition_in'])
            ->groupBy('equipment_id')->map(fn ($rows) => $rows->first());

        foreach ($latest as $equipmentId => $tx) {
            if ($tx->transaction_type === 'checkout') {
                DB::table('equipment')->where('equipment_id', $equipmentId)->update(['availability_status' => 'rented']);
            } else {
                $status = in_array($tx->condition_in, ['damaged', 'missing'], true) ? 'under_repair' : 'available';
                DB::table('equipment')->where('equipment_id', $equipmentId)->update(['availability_status' => $status]);
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
        $activeIds = DB::table('bookings')->where('booking_status', '!=', 'cancelled')->pluck('booking_id')->values();
        $anyEquipment = $this->ids['equipment']->values()->all();

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
        foreach ($completedIds->shuffle()->take(min(12, $completedIds->count())) as $bid) {
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

        $this->seedBookingComments($activeIds);
        $this->seedDocuments($activeIds);
        $this->seedSupportChat();
        $this->seedTermsAcceptances($activeIds);
    }

    private function seedBookingComments($activeIds): void
    {
        $internalNotes = [
            'Client confirmed shoot start time via phone — no changes to the call sheet.',
            'Double-check generator fuel level before dispatch.',
            'Client requested an extra hour of coverage — pending CE revision.',
        ];
        $clientNotes = [
            'Thank you for the quick turnaround on the equipment list!',
            'Can we confirm the crew call time for the shoot day?',
            'Everything looked great on set, thanks team.',
        ];
        foreach ($activeIds->shuffle()->take(min(10, $activeIds->count())) as $bid) {
            DB::table('booking_comments')->insert([
                'booking_id' => $bid, 'user_id' => $this->ids['uid']['traffic1'],
                'body' => $internalNotes[array_rand($internalNotes)], 'is_internal' => 1,
                'created_at' => $this->today->copy()->subDays(random_int(1, 30)),
            ]);
            $clientId = DB::table('bookings')->where('booking_id', $bid)->value('client_id');
            $userId = DB::table('clients')->where('client_id', $clientId)->value('user_id');
            if ($userId && random_int(0, 1)) {
                DB::table('booking_comments')->insert([
                    'booking_id' => $bid, 'user_id' => $userId,
                    'body' => $clientNotes[array_rand($clientNotes)], 'is_internal' => 0,
                    'created_at' => $this->today->copy()->subDays(random_int(1, 29)),
                ]);
            }
        }
    }

    private function seedDocuments($activeIds): void
    {
        $docs = [
            ['id', 'Valid Government ID.txt', "Sample demo document.\nCategory: Valid Government ID (front and back).\nThis is placeholder demo content, not a real ID scan."],
            ['permit', 'Barangay Shoot Permit.txt', "Sample demo document.\nCategory: Barangay filming permit for the shoot location.\nThis is placeholder demo content."],
            ['other', 'Signed Rental Agreement.txt', "Sample demo document.\nCategory: Signed equipment rental agreement.\nThis is placeholder demo content."],
            ['other', 'Certificate of Insurance.txt', "Sample demo document.\nCategory: Production insurance certificate on file.\nThis is placeholder demo content."],
        ];
        $bids = $activeIds->shuffle()->take(min(6, $activeIds->count()));
        foreach ($bids as $i => $bid) {
            [$category, $name, $content] = $docs[$i % count($docs)];
            $storedName = Str::random(40) . '.txt';
            Storage::disk('local')->put('documents/' . $storedName, $content);
            DB::table('documents')->insert([
                'booking_id' => $bid, 'client_id' => null, 'category' => $category,
                'original_name' => $name, 'stored_name' => $storedName, 'mime_type' => 'text/plain',
                'size_bytes' => strlen($content), 'note' => null, 'uploaded_by' => $this->ids['uid']['traffic1'],
                'created_at' => $this->today->copy()->subDays(random_int(1, 25)), 'updated_at' => $this->today,
            ]);
        }
    }

    private function seedSupportChat(): void
    {
        $threads = [
            [
                'Hi! Just following up — is the equipment list finalized for our shoot next week?',
                "Hi! Yes, we've finalized the equipment list and it's ready for pickup coordination. Let us know your preferred check-out time.",
            ],
            [
                'Can we add an extra lighting kit to our current booking?',
                "Sure thing — I've forwarded this to our traffic team, they'll update your cost estimate shortly.",
            ],
            [
                'What time should our crew arrive for equipment pickup tomorrow?',
                'Please have someone at our office by 8:00 AM — equipment release usually takes about 30 minutes.',
            ],
        ];
        $clientUsers = collect($this->ids['clientUsers'])->take(count($threads))->values();
        foreach ($threads as $i => [$clientMsg, $staffReply]) {
            $clientUserId = $clientUsers[$i] ?? null;
            $clientId = $clientUserId ? DB::table('clients')->where('user_id', $clientUserId)->value('client_id') : null;
            if (! $clientId) {
                continue;
            }
            DB::table('client_support_messages')->insert([
                'client_id' => $clientId, 'user_id' => $clientUserId, 'body' => $clientMsg,
                'is_internal' => 0, 'created_at' => $this->today->copy()->subHours(random_int(3, 48)),
            ]);
            DB::table('client_support_messages')->insert([
                'client_id' => $clientId, 'user_id' => $this->ids['uid']['traffic1'], 'body' => $staffReply,
                'is_internal' => 0, 'created_at' => $this->today->copy()->subHours(random_int(1, 2)),
            ]);
        }
    }

    private function seedTermsAcceptances($activeIds): void
    {
        $tcVersion = config('filmspec.tc_version', '1.0');
        $ppVersion = config('filmspec.pp_version', '1.0');
        foreach ($activeIds as $bid) {
            $booking = DB::table('bookings')->where('booking_id', $bid)->first();
            $clientId = $booking->client_id;
            $userId = DB::table('clients')->where('client_id', $clientId)->value('user_id');
            if (! $userId) {
                continue; // only bookings from clients with a real login went through online checkout
            }
            DB::table('terms_acceptances')->insert([
                'user_id' => $userId, 'booking_id' => $bid,
                'tc_version' => $tcVersion, 'pp_version' => $ppVersion,
                'accepted_at' => $booking->created_at, 'ip_address' => '127.0.0.1',
            ]);
        }
    }
}
