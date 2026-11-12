<?php

namespace App\Http\Controllers;

use App\Support\BookingCosting;
use App\Support\DataExporter;
use App\Support\EquipmentUnitAllocator;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CePreviewController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        if ($request->filled('booking_id') && $request->filled('export')) {
            return $this->exportDocument($request);
        }

        if ($request->filled('booking_id')) {
            return $this->bookingMode($request);
        }

        $uid = Auth::id();

        $cartItems = DB::table('booking_cart as c')
            ->leftJoin('equipment as e', 'c.equipment_id', '=', 'e.equipment_id')
            ->leftJoin('equipment_categories as ec', 'e.category_id', '=', 'ec.category_id')
            ->leftJoin('crew_members as cm', 'c.crew_id', '=', 'cm.crew_id')
            ->leftJoin('crew_positions as cp', 'c.position_id', '=', 'cp.position_id')
            ->where('c.user_id', $uid)
            ->select('c.*', 'e.equipment_name', 'e.brand', 'e.model', 'e.daily_rate', 'e.requires_operator', 'ec.category_name', DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS crew_name"), 'cm.base_rate_12hr', 'cp.position_name')
            ->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('home');
        }

        $equipLines = $cartItems->where('item_type', 'equipment')->values();
        $equipTotal = (float) $equipLines->sum(fn ($e) => (float) $e->daily_rate * $e->quantity * $e->days);

        $estimatedCrewLines = [];
        foreach ($equipLines as $eq) {
            if ($eq->requires_operator) {
                $ops = DB::table('equipment_operators as eo')
                    ->join('crew_positions as cp', 'eo.position_id', '=', 'cp.position_id')
                    ->leftJoin('crew_members as cm', function ($j) {
                        $j->on('cm.primary_position_id', '=', 'eo.position_id')->where('cm.status', 'active');
                    })
                    ->where('eo.equipment_id', $eq->equipment_id)
                    ->groupBy('eo.position_id', 'cp.position_name')
                    ->select('eo.position_id', 'cp.position_name', DB::raw('COALESCE(AVG(cm.base_rate_12hr),0) AS avg_rate'))
                    ->get();
                foreach ($ops as $op) {
                    $days = (int) $eq->days;
                    $avgRate = (float) $op->avg_rate;
                    $estimatedCrewLines[] = (object) [
                        'equipment_name' => $eq->equipment_name,
                        'position_name' => $op->position_name,
                        'avg_rate' => $avgRate,
                        'days' => $days,
                        'est_total' => $avgRate * $days,
                    ];
                }
            }
        }

        $user = Auth::user();
        $clientName = trim($user->first_name . ' ' . $user->last_name);
        $ceNumber = 'DRAFT-' . date('Ymd');

        $baseTransRate = (float) (DB::table('system_settings')->where('setting_key', 'base_transportation_rate')->value('setting_value') ?: 0);

        $vatRate = (float) config('filmspec.vat_rate');
        $grand = max(0, $equipTotal);
        $vat = round($grand * $vatRate / (1 + $vatRate), 2);
        $subtotal = $grand - $vat;

        $infoRows = [
            'PRODUCTION HOUSE' => strtoupper($clientName),
            'PROJECT NAME' => '(TO BE PROVIDED ON BOOKING)',
            'CONTACT PERSON' => strtoupper($clientName),
            'SHOOT DATE(S)' => '(TO BE PROVIDED ON BOOKING)',
            'LOCATION' => '(TO BE PROVIDED ON BOOKING)',
        ];

        $mode = 'cart';
        $preparedByName = '';
        $crewTotal = 0.0;
        $ceBd = null;
        $ceBdDiscounted = false;
        $cePricingMode = 'no_discount';
        $cePricingInput = null;
        $accLines = [];
        $accTotal = 0.0;
        $transCost = 0.0;
        $transZone = '';
        $transMult = 1.0;
        $zoneLabels = [];

        return view('ce-preview', compact(
            'mode', 'equipLines', 'equipTotal', 'estimatedCrewLines', 'crewTotal', 'clientName',
            'ceNumber', 'baseTransRate', 'grand', 'vat', 'subtotal', 'infoRows', 'ceBd', 'ceBdDiscounted',
            'cePricingMode', 'cePricingInput', 'accLines', 'accTotal', 'transCost', 'transZone', 'transMult', 'zoneLabels',
            'preparedByName'
        ));
    }

    private function exportDocument(Request $request)
    {
        $data = $this->bookingMode($request, true);
        if ($data instanceof RedirectResponse) {
            return $data;
        }

        $data['isClientView'] = true;
        $format = (string) $request->query('export');
        $filenameBase = 'CE-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $data['ceNumber']);

        if ($format === 'pdf') {
            $html = view('exports.ce-document-pdf', $data)->render();

            return Pdf::loadHTML($html)->setPaper('a4', 'portrait')->download("$filenameBase.pdf");
        }

        return DataExporter::respondSections($format, 'Cost Estimate ' . $data['ceNumber'], $this->exportSections($data), $filenameBase);
    }

    private function exportSections(array $d): array
    {
        $vatRate = (float) config('filmspec.vat_rate');
        $eqNet = $d['ceBd'] ? $d['ceBd']['equip_net'] : $d['equipTotal'] - round($d['equipTotal'] * $vatRate / (1 + $vatRate), 2);
        $eqVat = $d['ceBd'] ? $d['ceBd']['equip_vat'] : round($d['equipTotal'] * $vatRate / (1 + $vatRate), 2);
        $eqGrand = $d['ceBd'] ? $d['ceBd']['equip_grand'] : $d['equipTotal'];
        $crewNet = $d['ceBd'] ? $d['ceBd']['crew_net'] : $d['crewTotal'] - round($d['crewTotal'] * $vatRate / (1 + $vatRate), 2);
        $crewVat = $d['ceBd'] ? $d['ceBd']['crew_vat'] : round($d['crewTotal'] * $vatRate / (1 + $vatRate), 2);
        $crewGrand = $d['ceBd'] ? $d['ceBd']['crew_grand'] : $d['crewTotal'];
        $money = fn ($v) => '₱' . number_format((float) $v, 2);

        $infoLines = [];
        foreach ($d['infoRows'] as $l => $v) {
            $infoLines[] = [$l, $v];
        }

        $equipRows = [];
        foreach ($d['equipGroups'] as $catName => $catLines) {
            $equipRows[] = [strtoupper($catName) . ' (FS):', '', '', '', ''];
            foreach ($catLines as $eq) {
                $rate = (float) ($eq->daily_rate ?? 0);
                $qty = (int) ($eq->quantity ?? 1);
                $equipRows[] = [(string) $qty, $eq->equipment_name . ($eq->brand ? ' (' . $eq->brand . ')' : ''), '1', $money($rate), $money($qty * $rate)];
            }
        }
        $equipRows[] = ['', '', '', 'Total (Equipment Only):', $money($d['equipTotal'])];

        $crewRows = [];
        foreach ($d['crewLines'] as $cl) {
            $clRate = (float) ($cl->rate_used ?? 0);
            $clDays = (int) ($cl->hours_worked ?? 1);
            $crewRows[] = ['1', trim(($cl->position_name ?? 'Crew') . ($cl->crew_name ? ' - ' . $cl->crew_name : '')), (string) $clDays, $money($clRate), $money($clRate * $clDays)];
        }
        $crewRows[] = ['', '', '', 'TOTAL (CREW TF):', $money($d['crewTotal'])];

        return [
            ['title' => 'CE# ' . $d['ceNumber'] . ' (E) — Equipment', 'rows' => $infoLines],
            ['title' => null, 'headers' => ['QTY', 'EQUIPMENT (S)', 'DAY(S)', 'RATE / DAY', 'AMOUNT'], 'rows' => $equipRows],
            ['title' => null, 'rows' => [
                ['Net Amount (ex-VAT):', $money($eqNet)],
                ['VAT:', $money($eqVat)],
                ['EQUIPMENT CE GRAND TOTAL:', $money($eqGrand)],
                ['Amount in Words:', Money::amtWords($eqGrand) . ' PESOS ONLY'],
            ]],
            ['title' => 'CE# ' . $d['ceNumber'] . ' (M) — Crew TF', 'rows' => $infoLines],
            ['title' => null, 'headers' => ['QTY', 'POSITION / NAME', 'DAY(S)', 'RATE / 12H', 'AMOUNT'], 'rows' => $crewRows],
            ['title' => null, 'rows' => [
                ['Net Amount (ex-VAT):', $money($crewNet)],
                ['VAT:', $money($crewVat)],
                ['CREW CE GRAND TOTAL:', $money($crewGrand)],
                ['Amount in Words:', Money::amtWords($crewGrand) . ' PESOS ONLY'],
            ]],
            ['title' => 'CE# ' . $d['ceNumber'] . ' (S) — Summary', 'rows' => $infoLines],
            ['title' => null, 'rows' => [
                ['Grip Equipment (FS)', $money($d['equipTotal'])],
                ['Personnel / Crew TF', $money($d['crewTotal'])],
                ['Accessories / Add-ons', $money($d['accTotal'])],
                ['Transportation', $money($d['ceBd'] ? $d['ceBd']['transportation'] : $d['transCost'])],
                ['Net Amount (ex-VAT)', $money($d['subtotal'])],
                ['VAT — Included', $money($d['vat'])],
                ['GRAND TOTAL (VAT Incl.)', $money($d['grand'])],
                ['Amount in Words:', Money::amtWords($d['grand']) . ' PESOS ONLY'],
            ]],
        ];
    }

    private function bookingMode(Request $request, bool $asData = false)
    {
        $bid = (int) $request->query('booking_id');
        $uid = Auth::id();
        $role = Auth::user()->role->role_name ?? 'client';

        $isClientView = $role === 'client' || $request->query('view') === 'client';

        $bookingQuery = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('b.booking_id', $bid);

        if ($role === 'client') {
            $bookingQuery->where('c.user_id', $uid);
        }

        $booking = $bookingQuery
            ->select('b.*', 'c.company_name', 'c.contact_person', 'c.email as client_email', 'c.phone as client_phone', 'c.entity_type')
            ->first();
        if (! $booking) {
            return $role === 'client' ? redirect()->route('home') : redirect()->route('bookings');
        }

        $ceId = (int) $request->query('ce_id');
        $ce = $ceId
            ? DB::table('cost_estimates')->where('booking_id', $bid)->where('ce_id', $ceId)->first()
            : null;
        if (! $ce) {
            $ce = DB::table('cost_estimates')->where('booking_id', $bid)->orderByDesc('ce_id')->first();
        }

        $lines = BookingCosting::lines($bid);
        $equipGroups = $lines['groups'];
        $crewLines = $lines['crew'];

        $accLines = DB::table('booking_accessories as ba')
            ->join('accessories as a', 'ba.accessory_id', '=', 'a.accessory_id')
            ->where('ba.booking_id', $bid)
            ->orderBy('a.accessory_name')
            ->select('ba.*', 'a.accessory_name')
            ->get();

        $equipUnitTags = DB::table('booking_equipment_units as beu')
            ->join('equipment_units as eu', 'beu.equipment_unit_id', '=', 'eu.unit_id')
            ->where('beu.bk_equip_id', '>', 0)
            ->whereIn('beu.bk_equip_id', DB::table('booking_equipment')->where('booking_id', $bid)->pluck('bk_equip_id'))
            ->select('beu.bk_equip_id', 'eu.asset_tag')
            ->get()->groupBy('bk_equip_id')->map(fn ($g) => $g->pluck('asset_tag')->implode(', '));
        $accUnitTags = DB::table('booking_accessory_units as bau')
            ->join('accessory_units as au', 'bau.accessory_unit_id', '=', 'au.unit_id')
            ->whereIn('bau.ba_id', $accLines->pluck('ba_id'))
            ->select('bau.ba_id', 'au.asset_tag')
            ->get()->groupBy('ba_id')->map(fn ($g) => $g->pluck('asset_tag')->implode(', '));
        $accTotal = (float) $accLines->sum('subtotal');

        $allEquipLines = collect($equipGroups)->flatten(1);
        $equipUnitsByEquipId = DB::table('equipment_units')
            ->whereIn('equipment_id', $allEquipLines->pluck('equipment_id')->unique()->all() ?: [0])
            ->where('status', '!=', 'retired')
            ->orderByRaw("status = 'available' desc")->orderBy('asset_tag')
            ->get(['unit_id', 'equipment_id', 'asset_tag', 'status']);
        $allocatedEqUnitIds = DB::table('booking_equipment_units')
            ->whereIn('bk_equip_id', $allEquipLines->pluck('bk_equip_id')->all() ?: [0])
            ->get(['bk_equip_id', 'equipment_unit_id'])
            ->groupBy('bk_equip_id')->map(fn ($g) => $g->pluck('equipment_unit_id')->all());
        $unitOption = function ($u, array $allocatedHere, array $busy) {
            $here = in_array((int) $u->unit_id, array_map('intval', $allocatedHere), true);
            $reservable = in_array($u->status, EquipmentUnitAllocator::RESERVABLE_STATUSES, true);
            $isBusy = in_array((int) $u->unit_id, $busy, true);

            return (object) [
                'unit_id' => $u->unit_id, 'asset_tag' => $u->asset_tag,
                'status' => (! $here && $reservable && $isBusy) ? 'booked_overlap' : $u->status,
                'selectable' => $here || ($reservable && ! $isBusy),
            ];
        };
        $equipUnitOptions = [];
        foreach ($allEquipLines as $eqLine) {
            $allocatedHere = $allocatedEqUnitIds[$eqLine->bk_equip_id] ?? [];
            $busy = EquipmentUnitAllocator::busyEquipmentUnitIds((int) $eqLine->bk_equip_id, $bid, $booking->shoot_date_start, $booking->shoot_date_end);
            $equipUnitOptions[$eqLine->bk_equip_id] = $equipUnitsByEquipId
                ->where('equipment_id', $eqLine->equipment_id)
                ->map(fn ($u) => $unitOption($u, $allocatedHere, $busy))->values();
        }

        $accUnitsByAccId = DB::table('accessory_units')
            ->whereIn('accessory_id', $accLines->pluck('accessory_id')->unique()->all() ?: [0])
            ->where('status', '!=', 'retired')
            ->orderByRaw("status = 'available' desc")->orderBy('asset_tag')
            ->get(['unit_id', 'accessory_id', 'asset_tag', 'status']);
        $allocatedAccUnitIds = DB::table('booking_accessory_units')
            ->whereIn('ba_id', $accLines->pluck('ba_id')->all() ?: [0])
            ->get(['ba_id', 'accessory_unit_id'])
            ->groupBy('ba_id')->map(fn ($g) => $g->pluck('accessory_unit_id')->all());
        $accUnitOptions = [];
        foreach ($accLines as $acLine) {
            $allocatedHere = $allocatedAccUnitIds[$acLine->ba_id] ?? [];
            $busy = EquipmentUnitAllocator::busyAccessoryUnitIds((int) $acLine->ba_id, $bid, $booking->shoot_date_start, $booking->shoot_date_end);
            $accUnitOptions[$acLine->ba_id] = $accUnitsByAccId
                ->where('accessory_id', $acLine->accessory_id)
                ->map(fn ($u) => $unitOption($u, $allocatedHere, $busy))->values();
        }

        $equipBaseTotal = 0.0;
        foreach ($equipGroups as $catLines) {
            foreach ($catLines as $eq) {
                $equipBaseTotal += (float) $eq->quantity * (float) $eq->days * (float) $eq->daily_rate;
            }
        }
        $crewTotal = (float) DB::table('booking_crew')->where('booking_id', $bid)
            ->selectRaw('COALESCE(SUM(rate_used * hours_worked),0) AS t')->value('t');

        $transCost = (float) ($booking->transportation_cost ?? 0);
        $transZone = $booking->location_zone ?? '';
        $transMult = max(1.0, (float) ($booking->transport_multiplier ?? 1.0));
        $equipTotal = $equipBaseTotal * $transMult;

        $zoneLabels = [
            'manila' => 'Metro Manila / NCR (0–60 km)',
            'luzon' => 'Luzon Near (61–150 km)',
            'luzon_far' => 'Luzon Far (151 km+)',
        ];

        $ceDiscount = $ce ? (float) ($ce->discount ?? 0) : 0;
        $ceOtherCharges = $ce ? (float) ($ce->other_charges ?? 0) : 0;
        $cePricingMode = $ce ? ($ce->pricing_mode ?? 'no_discount') : 'no_discount';
        $cePricingInput = $ce && $ce->pricing_input !== null ? (float) $ce->pricing_input : null;
        $ceVatExempt = $ce ? (bool) $ce->vat_exempt : false;

        $ceBd = $ce ? BookingCosting::breakdown($bid, $ce) : null;
        $ceBdDiscounted = $ceBd && $ceBd['pricing_mode'] !== 'no_discount';

        $itemTotal = $equipTotal + $crewTotal + $accTotal + $transCost;

        if ($cePricingMode !== 'no_discount' && $cePricingInput !== null) {
            $netBase = match ($cePricingMode) {
                'package_price' => $cePricingInput,
                'discount_percent' => $itemTotal * (1 - min(100, $cePricingInput) / 100),
                'discount_flat' => $itemTotal - $cePricingInput,
                default => $itemTotal,
            };
            $netBase = max(0, $netBase);
            $vatRate = $ceVatExempt ? 0.0 : (float) config('filmspec.vat_rate');
            $vat = round($netBase * $vatRate, 2);
            $subtotal = $netBase;
            $grand = $netBase + $vat;
        } elseif ($ce) {
            $subtotal = (float) $ce->subtotal;
            $vat = (float) $ce->vat_amount;
            $grand = (float) $ce->grand_total;
        } else {
            $vatRate = (float) config('filmspec.vat_rate');
            $grand = max(0, $itemTotal + $ceOtherCharges - $ceDiscount);
            $vat = round($grand * $vatRate / (1 + $vatRate), 2);
            $subtotal = $grand - $vat;
        }

        $projectName = $booking->project_title ?: 'PROJECT';
        $clientName = $booking->company_name ?: $booking->contact_person;
        $shootDate = $booking->shoot_date_start ? date('F j, Y', strtotime($booking->shoot_date_start)) : '—';
        $location = $booking->shoot_location ?? '';
        $ceNumber = $ce ? $ce->ce_reference : ('CE-' . date('Y') . '-' . str_pad((string) $bid, 4, '0', STR_PAD_LEFT));
        $preparedByName = $booking->ce_prepared_by ?? '';

        $ceTypeLabels = ['fs_front' => 'FS FRONT', 'client_direct' => 'CLIENT DIRECT', 'partner_front' => 'PARTNER FRONT'];
        $infoRows = [
            'PRODUCTION HOUSE' => strtoupper((string) $clientName),
            'PROJECT NAME' => $projectName !== 'PROJECT' ? '"' . strtoupper($projectName) . '"' : '—',
            'CONTACT PERSON' => strtoupper((string) (($booking->ce_contact_person ?? '') ?: ($booking->contact_person ?? ''))),
            'SHOOT DATE(S)' => $shootDate !== '—' ? strtoupper($shootDate) : '—',
            'LOCATION' => $location !== '' ? strtoupper($location) : '—',
        ];
        $ceContactNo = ($booking->ce_contact_number ?? '') ?: ($booking->client_phone ?? '');
        $ceContactEmail = ($booking->ce_contact_email ?? '') ?: ($booking->client_email ?? '');
        if ($ceContactNo) {
            $infoRows['CONTACT NUMBER'] = strtoupper($ceContactNo);
        }
        if ($ceContactEmail) {
            $infoRows['EMAIL'] = $ceContactEmail;
        }
        if (! empty($booking->ce_director_dop)) {
            $infoRows['DIRECTOR / DOP'] = strtoupper($booking->ce_director_dop);
        }
        if (! empty($booking->ce_due_date)) {
            $infoRows['DUE DATE'] = strtoupper(date('F j, Y', strtotime($booking->ce_due_date)));
        }
        $infoRows['TYPE'] = $ceTypeLabels[$booking->ce_type ?? 'fs_front'] ?? 'FS FRONT';

        $mode = 'booking';

        $ceVersion = 1;
        $isSuperseded = false;
        if ($ce) {
            $ceVersion = 1 + (int) DB::table('cost_estimates')
                ->where('booking_id', $bid)->where('ce_id', '<', $ce->ce_id)->count();
            $isSuperseded = $ce->status === 'confirmed' && DB::table('cost_estimates')
                ->where('booking_id', $bid)->where('status', 'confirmed')->where('ce_id', '>', $ce->ce_id)->exists();
        }

        $isAdmin = in_array($role, ['super_admin', 'operations_manager', 'traffic'], true);
        $canManage = $isAdmin && ! $isClientView && ! $isSuperseded
            && in_array($booking->booking_status, ['pending', 'confirmed', 'ongoing'], true);
        $canDiscount = $canManage && in_array($role, ['super_admin', 'operations_manager'], true);

        $availEquip = collect();
        $availUnitsByEquipId = collect();
        $availAccessoryUnitsByAccId = collect();
        $availCrew = collect();
        $positions = collect();
        $vehicleRates = collect();
        $allAccessoriesList = collect();
        $accDays = 1;
        if ($canManage) {
            $ds = $booking->shoot_date_start;
            $de = $booking->shoot_date_end;

            $availEquip = DB::table('equipment as e')
                ->join('equipment_categories as ec', 'e.category_id', '=', 'ec.category_id')
                ->where('e.availability_status', 'available')
                ->whereNotIn('e.equipment_id', function ($q) use ($bid, $ds, $de) {
                    $q->select('be.equipment_id')->from('booking_equipment as be')
                        ->join('bookings as b', 'be.booking_id', '=', 'b.booking_id')
                        ->where('be.booking_id', '!=', $bid)
                        ->whereNotIn('b.booking_status', ['cancelled', 'completed'])
                        ->where('b.shoot_date_start', '<=', $de)->where('b.shoot_date_end', '>=', $ds);
                })
                ->select('e.equipment_id', 'e.equipment_name', 'e.brand', 'e.daily_rate', 'ec.category_name')
                ->orderBy('e.equipment_name')
                ->get();

            $availUnitsByEquipId = EquipmentUnitAllocator::reservableEquipmentUnits($availEquip->pluck('equipment_id')->all(), $bid, $ds, $de)
                ->groupBy('equipment_id');

            $availCrew = DB::table('crew_members as cm')
                ->leftJoin('crew_positions as cp', 'cm.primary_position_id', '=', 'cp.position_id')
                ->where('cm.status', 'active')
                ->select('cm.crew_id', DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS name"), 'cm.base_rate_12hr', 'cm.employment_type', 'cm.primary_position_id', 'cp.position_name')
                ->selectRaw('(SELECT b2.booking_reference FROM booking_crew bc2 JOIN bookings b2 ON bc2.booking_id = b2.booking_id
                    WHERE bc2.crew_id = cm.crew_id AND bc2.booking_id != ? AND b2.booking_status NOT IN (\'cancelled\',\'completed\')
                    AND b2.shoot_date_start <= ? AND b2.shoot_date_end >= ? LIMIT 1) AS busy_on_booking', [$bid, $de, $ds])
                ->orderBy('name')
                ->get();

            $positions = DB::table('crew_positions')->orderBy('position_name')->get();
            $vehicleRates = DB::table('vehicle_rates')->where('is_active', 1)->orderBy('base_rate')->get();

            $allAccessoriesList = DB::table('accessories as a')
                ->whereNotIn('a.accessory_id', function ($q) use ($bid) {
                    $q->select('accessory_id')->from('booking_accessories')->where('booking_id', $bid);
                })
                ->orderBy('a.accessory_name')
                ->select('a.accessory_id', 'a.accessory_name', 'a.daily_rate', 'a.is_included', 'a.quantity')
                ->selectRaw('COALESCE((SELECT SUM(ba2.quantity) FROM booking_accessories ba2
                    JOIN bookings b2 ON ba2.booking_id=b2.booking_id
                    WHERE ba2.accessory_id=a.accessory_id
                    AND b2.booking_status NOT IN (\'cancelled\',\'completed\')
                    AND ba2.booking_id != ?),0) AS qty_in_use', [$bid])
                ->get();

            $availAccessoryUnitsByAccId = EquipmentUnitAllocator::reservableAccessoryUnits($allAccessoriesList->pluck('accessory_id')->all(), $bid, $ds, $de)
                ->groupBy('accessory_id');

            $accDays = max(1, (int) ((strtotime($de) - strtotime($ds)) / 86400) + 1);
        }

        $viewData = [
            'mode' => $mode, 'bid' => $bid, 'ceId' => $ce->ce_id ?? 0, 'role' => $role, 'isClientView' => $isClientView,
            'equipGroups' => $equipGroups, 'crewLines' => $crewLines, 'accLines' => $accLines, 'accTotal' => $accTotal,
            'equipUnitTags' => $equipUnitTags, 'accUnitTags' => $accUnitTags,
            'equipUnitOptions' => $equipUnitOptions, 'accUnitOptions' => $accUnitOptions,
            'allocatedEqUnitIds' => $allocatedEqUnitIds, 'allocatedAccUnitIds' => $allocatedAccUnitIds,
            'availUnitsByEquipId' => $availUnitsByEquipId, 'availAccessoryUnitsByAccId' => $availAccessoryUnitsByAccId,
            'justActed' => $request->session()->has('bd_flash'),
            'equipBaseTotal' => $equipBaseTotal, 'equipTotal' => $equipTotal, 'crewTotal' => $crewTotal,
            'transCost' => $transCost, 'transZone' => $transZone, 'transMult' => $transMult, 'zoneLabels' => $zoneLabels,
            'cePricingMode' => $cePricingMode, 'cePricingInput' => $cePricingInput, 'ceVatExempt' => $ceVatExempt,
            'ceDiscount' => $ceDiscount,
            'ceBd' => $ceBd, 'ceBdDiscounted' => $ceBdDiscounted, 'grand' => $grand, 'vat' => $vat, 'subtotal' => $subtotal,
            'clientName' => $clientName, 'ceNumber' => $ceNumber, 'infoRows' => $infoRows, 'preparedByName' => $preparedByName,
            'ceVersion' => $ceVersion, 'isSuperseded' => $isSuperseded, 'ceStatus' => $ce->status ?? 'draft',
            'booking' => $booking, 'canManage' => $canManage, 'canDiscount' => $canDiscount,
            'availEquip' => $availEquip, 'availCrew' => $availCrew, 'positions' => $positions,
            'vehicleRates' => $vehicleRates, 'allAccessoriesList' => $allAccessoriesList, 'accDays' => $accDays,
            'actionUrl' => route('booking-detail.act', $bid),
            'equipLines' => [], 'estimatedCrewLines' => [], 'baseTransRate' => 0,
        ];

        return $asData ? $viewData : view('ce-preview', $viewData);
    }
}
