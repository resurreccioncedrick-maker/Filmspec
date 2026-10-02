<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ChecklistController extends Controller
{
    // Printable check-out/check-in record for a booking's equipment — same query shape as
    // index()'s $equipLines so this record always matches what staff see/edit on-screen.
    public function print(Request $request): View|RedirectResponse
    {
        $bid = (int) $request->query('booking_id', 0);
        if (! $bid) {
            return redirect()->route('bookings');
        }

        $booking = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('b.booking_id', $bid)
            ->select('b.*', 'c.company_name', 'c.contact_person', 'c.client_type')
            ->first();
        if (! $booking) {
            return redirect()->route('bookings');
        }

        $equipQuery = DB::table('booking_equipment as be')
            ->join('equipment as e', 'be.equipment_id', '=', 'e.equipment_id')
            ->join('equipment_categories as ec', 'e.category_id', '=', 'ec.category_id')
            ->leftJoin('equipment_checklist as co', function ($j) use ($bid) {
                $j->on('co.equipment_id', '=', 'be.equipment_id')->where('co.booking_id', $bid)->where('co.direction', 'out')
                    ->whereNull('co.equipment_unit_id')->whereNull('co.unit_seq');
            })
            ->leftJoin('equipment_checklist as ci', function ($j) use ($bid) {
                $j->on('ci.equipment_id', '=', 'be.equipment_id')->where('ci.booking_id', $bid)->where('ci.direction', 'in')
                    ->whereNull('ci.equipment_unit_id')->whereNull('ci.unit_seq');
            })
            ->leftJoin('users as uo', 'co.checked_by', '=', 'uo.user_id')
            ->leftJoin('users as ui', 'ci.checked_by', '=', 'ui.user_id')
            ->where('be.booking_id', $bid)
            ->select(
                DB::raw("'equipment' as item_type"), 'be.equipment_id as ref_id', 'be.quantity',
                'e.equipment_name as item_name', 'e.brand', 'ec.category_name',
                'co.quantity_actual as co_qty', 'co.condition_out', 'co.checked as co_checked',
                'co.notes as co_notes', 'co.checked_at as co_at',
                DB::raw("CONCAT(uo.first_name,' ',uo.last_name) as co_by_name"),
                'ci.quantity_actual as ci_qty', 'ci.condition_in', 'ci.checked as ci_checked',
                'ci.notes as ci_notes', 'ci.checked_at as ci_at',
                DB::raw("CONCAT(ui.first_name,' ',ui.last_name) as ci_by_name")
            );

        // Accessories dispatched via Field Requests (or added directly) share the same
        // equipment_checklist table (accessory_id column, equipment_id left null) so they show
        // up on the same physical check-out/check-in record instead of a disconnected one.
        // Only the legacy whole-line row (no unit identity) feeds this print sheet — a per-unit
        // breakdown is a screen concern, not the printed record's.
        $accQuery = DB::table('booking_accessories as ba')
            ->join('accessories as a', 'ba.accessory_id', '=', 'a.accessory_id')
            ->leftJoin('equipment_checklist as co', function ($j) use ($bid) {
                $j->on('co.accessory_id', '=', 'ba.accessory_id')->where('co.booking_id', $bid)->where('co.direction', 'out')
                    ->whereNull('co.accessory_unit_id')->whereNull('co.unit_seq');
            })
            ->leftJoin('equipment_checklist as ci', function ($j) use ($bid) {
                $j->on('ci.accessory_id', '=', 'ba.accessory_id')->where('ci.booking_id', $bid)->where('ci.direction', 'in')
                    ->whereNull('ci.accessory_unit_id')->whereNull('ci.unit_seq');
            })
            ->leftJoin('users as uo', 'co.checked_by', '=', 'uo.user_id')
            ->leftJoin('users as ui', 'ci.checked_by', '=', 'ui.user_id')
            ->where('ba.booking_id', $bid)
            ->select(
                DB::raw("'accessory' as item_type"), 'ba.accessory_id as ref_id', 'ba.quantity',
                'a.accessory_name as item_name', DB::raw("'' as brand"), DB::raw("'Accessory' as category_name"),
                'co.quantity_actual as co_qty', 'co.condition_out', 'co.checked as co_checked',
                'co.notes as co_notes', 'co.checked_at as co_at',
                DB::raw("CONCAT(uo.first_name,' ',uo.last_name) as co_by_name"),
                'ci.quantity_actual as ci_qty', 'ci.condition_in', 'ci.checked as ci_checked',
                'ci.notes as ci_notes', 'ci.checked_at as ci_at',
                DB::raw("CONCAT(ui.first_name,' ',ui.last_name) as ci_by_name")
            );

        $equipLines = $equipQuery->unionAll($accQuery)->orderBy('category_name')->orderBy('item_name')->get();

        $totalItems = $equipLines->count();
        $outDone = $equipLines->filter(fn ($e) => $e->co_checked)->count();
        $inDone = $equipLines->filter(fn ($e) => $e->ci_checked)->count();
        $damaged = $equipLines->filter(fn ($e) => in_array($e->condition_in, ['damaged', 'missing'], true))->count();

        // This print sheet uses its own self-contained badge classes (ok/warn/bad/none), distinct
        // from the main Checklist screen's badge-green/badge-blue/... — not $this->condBadge.
        $printBadge = ['excellent' => 'ok', 'good' => 'ok', 'fair' => 'warn', 'damaged' => 'bad', 'missing' => 'bad'];

        return view('checklist-print', [
            'booking' => $booking, 'bid' => $bid, 'equipLines' => $equipLines,
            'totalItems' => $totalItems, 'outDone' => $outDone, 'inDone' => $inDone, 'damaged' => $damaged,
            'condOut' => $this->condOut, 'condIn' => $this->condIn, 'condBadge' => $printBadge,
        ]);
    }

    private array $condOut = ['excellent' => 'Excellent', 'good' => 'Good', 'fair' => 'Fair'];

    private array $condIn = ['excellent' => 'Excellent', 'good' => 'Good', 'fair' => 'Fair', 'damaged' => 'Damaged', 'missing' => 'Missing'];

    private array $condBadge = ['excellent' => 'badge-green', 'good' => 'badge-blue', 'fair' => 'badge-yellow', 'damaged' => 'badge-red', 'missing' => 'badge-red'];

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';
        // Scoped to this page: release/return still needs an office role, but Traffic staff are
        // included here (config('filmspec.checklist_office_roles')) without touching the
        // app-wide manage_roles grant used everywhere else.
        $canManage = in_array($role, config('filmspec.checklist_office_roles'), true);

        $bid = (int) $request->query('booking_id', 0);
        $dir = $request->query('dir', 'out');
        if (! $bid) {
            return redirect()->route('bookings');
        }

        $booking = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->leftJoin('users as ar', 'b.field_arrival_confirmed_by', '=', 'ar.user_id')
            ->where('b.booking_id', $bid)
            ->select('b.*', 'c.company_name', 'c.contact_person', 'c.client_type', DB::raw("CONCAT(ar.first_name,' ',ar.last_name) as arrival_confirmed_by_name"))
            ->first();
        if (! $booking) {
            return redirect()->route('bookings');
        }

        $ceConfirmed = DB::table('cost_estimates')->where('booking_id', $bid)->orderByDesc('ce_id')->value('status') === 'confirmed';
        // Confirming the CE only sends it to the client (cost_approval_status becomes
        // 'pending_client') — it is not the client's approval. Equipment must not be checked out
        // until the client has actually approved, which is a separate, later event.
        $costApproved = ($booking->cost_approval_status ?? null) === 'client_approved';
        if ($dir === 'out' && $booking->booking_status === 'confirmed') {
            if (! $ceConfirmed) {
                $request->session()->flash('bd_flash', [
                    'type' => 'danger',
                    'text' => "Equipment can't be checked out yet — the cost estimate hasn't been confirmed. "
                        . 'Use <strong>Confirm CE</strong> on the booking page first.',
                ]);

                return redirect()->route('booking-detail', $bid);
            }
            if (! $costApproved) {
                $request->session()->flash('bd_flash', [
                    'type' => 'danger',
                    'text' => "Equipment can't be checked out yet — the client hasn't approved the cost estimate. "
                        . 'Wait for client approval on the booking page first.',
                ]);

                return redirect()->route('booking-detail', $bid);
            }
            // "No transport" and "nobody has decided about transport yet" used to look
            // identical — this column is only ever set once the Assign Transport form is
            // actually submitted, whatever the outcome.
            if (empty($booking->transport_confirmed_at)) {
                $request->session()->flash('bd_flash', [
                    'type' => 'danger',
                    'text' => "Equipment can't be checked out yet — transport hasn't been reviewed. "
                        . 'Open Add/Edit Transport on the booking page first, even to confirm none is needed.',
                ]);

                return redirect()->route('booking-detail', $bid);
            }
        }

        $msg = null;
        if ($request->isMethod('post') && $canManage) {
            $msg = $this->saveChecklist($request, $bid, $user->user_id);
            $dir = $request->input('direction', $dir);
        }

        // Base line items only — deliberately no equipment_checklist join here. Once a
        // multi-quantity item has several per-unit rows sharing the same equipment_id/direction,
        // joining them onto this one-row-per-line-item query would fan it out into duplicates.
        $equipQuery = DB::table('booking_equipment as be')
            ->join('equipment as e', 'be.equipment_id', '=', 'e.equipment_id')
            ->join('equipment_categories as ec', 'e.category_id', '=', 'ec.category_id')
            ->where('be.booking_id', $bid)
            ->select(
                DB::raw("'equipment' as item_type"), 'be.equipment_id as ref_id', 'be.quantity',
                'e.equipment_name as item_name', 'e.brand', 'e.model', 'e.image_path', 'ec.category_name'
            );

        $accQuery = DB::table('booking_accessories as ba')
            ->join('accessories as a', 'ba.accessory_id', '=', 'a.accessory_id')
            ->where('ba.booking_id', $bid)
            ->select(
                DB::raw("'accessory' as item_type"), 'ba.accessory_id as ref_id', 'ba.quantity',
                'a.accessory_name as item_name', DB::raw("'' as brand"), DB::raw("'' as model"),
                DB::raw('NULL as image_path'), DB::raw("'Accessory' as category_name")
            );

        $equipLines = $equipQuery->unionAll($accQuery)->orderBy('category_name')->orderBy('item_name')->get();

        $this->hydrateChecklistState($equipLines, $bid);

        $totalItems = $equipLines->count();
        $outChecked = $equipLines->filter(fn ($e) => $e->co_checked)->count();
        $inChecked = $equipLines->filter(fn ($e) => $e->ci_checked)->count();
        $damaged = $equipLines->filter(fn ($e) => $e->is_multi
            ? $e->any_damaged_in
            : in_array($e->condition_in, ['damaged', 'missing'], true))->count();

        $crewCount = (int) DB::table('booking_crew')->where('booking_id', $bid)->count();
        // Transport being assigned doesn't automatically mean a driver is required — some
        // transport is just a delivery/courier fee. Staff mark that explicitly on the Assign
        // Transport form (driver_required).
        $driverNeeded = ! empty($booking->vehicle_rate_id) && (bool) ($booking->driver_required ?? false);
        $driverCount = 0;
        if ($driverNeeded) {
            $driverCount = (int) DB::table('booking_crew as bc')
                ->join('crew_positions as cp', 'bc.position_id', '=', 'cp.position_id')
                ->where('bc.booking_id', $bid)->where(DB::raw('LOWER(cp.position_name)'), 'like', '%driver%')
                ->count();
        }
        // Payment collection is accounting's own workspace (Billing & POS) — it no longer gates
        // equipment release.
        $transportConfirmed = ! empty($booking->transport_confirmed_at);
        $gateOk = $totalItems > 0 && $crewCount > 0 && $transportConfirmed && (! $driverNeeded || $driverCount > 0) && $costApproved;

        return view('checklist', [
            'msg' => $msg, 'canManage' => $canManage, 'booking' => $booking, 'bid' => $bid, 'dir' => $dir,
            'equipLines' => $equipLines, 'totalItems' => $totalItems, 'outChecked' => $outChecked,
            'inChecked' => $inChecked, 'damaged' => $damaged,
            'crewCount' => $crewCount, 'driverNeeded' => $driverNeeded, 'driverCount' => $driverCount,
            'costApproved' => $costApproved, 'transportConfirmed' => $transportConfirmed,
            'gateOk' => $gateOk,
            'condOut' => $this->condOut, 'condIn' => $this->condIn, 'condBadge' => $this->condBadge,
            'fieldArrivalConfirmedAt' => $booking->field_arrival_confirmed_at,
            'fieldArrivalConfirmedByName' => $booking->arrival_confirmed_by_name,
        ]);
    }

    /**
     * Mutates $equipLines in place, adding co_checked/ci_checked/condition_out/condition_in/etc
     * to every line (quantity=1, from the legacy whole-line row) and a 'slots' array to every
     * quantity>1 line (one entry per physical unit — a registered equipment_units/accessory_units
     * row where one exists, otherwise a synthetic "Unit N of M" slot), so one damaged unit no
     * longer hides its siblings' state.
     */
    private function hydrateChecklistState($equipLines, int $bid): void
    {
        $eqIds = $equipLines->where('item_type', 'equipment')->pluck('ref_id')->all();
        $accIds = $equipLines->where('item_type', 'accessory')->pluck('ref_id')->all();

        $aggRows = DB::table('equipment_checklist')
            ->where('booking_id', $bid)
            ->whereNull('equipment_unit_id')->whereNull('accessory_unit_id')->whereNull('unit_seq')
            ->where(function ($q) use ($eqIds, $accIds) {
                $q->whereIn('equipment_id', $eqIds ?: [0])->orWhereIn('accessory_id', $accIds ?: [0]);
            })
            ->get();
        $aggLookup = [];
        foreach ($aggRows as $r) {
            $key = ($r->equipment_id ? 'eq_' . $r->equipment_id : 'acc_' . $r->accessory_id) . '|' . $r->direction;
            $aggLookup[$key] = $r;
        }

        // Excludes retired units (same convention EquipmentController::recalcFromUnits() already
        // uses) and orders 'available' first — without this, a retired/under_maintenance unit
        // could get offered as one of the N checkable slots purely because it has a lower
        // unit_id, silently hiding an actually-available unit of the same model instead.
        $unitRows = DB::table('equipment_units')->whereIn('equipment_id', $eqIds ?: [0])
            ->where('status', '!=', 'retired')
            ->orderByRaw("status = 'available' desc")->orderBy('unit_id')
            ->get()->groupBy('equipment_id');
        $accUnitRows = DB::table('accessory_units')->whereIn('accessory_id', $accIds ?: [0])
            ->where('status', '!=', 'retired')
            ->orderByRaw("status = 'available' desc")->orderBy('unit_id')
            ->get()->groupBy('accessory_id');

        $unitChecklistRows = DB::table('equipment_checklist')
            ->where('booking_id', $bid)
            ->where(function ($q) {
                $q->whereNotNull('equipment_unit_id')->orWhereNotNull('accessory_unit_id')->orWhereNotNull('unit_seq');
            })
            ->where(function ($q) use ($eqIds, $accIds) {
                $q->whereIn('equipment_id', $eqIds ?: [0])->orWhereIn('accessory_id', $accIds ?: [0]);
            })
            ->get();
        $unitLookup = [];
        foreach ($unitChecklistRows as $r) {
            $base = $r->equipment_id ? 'eq_' . $r->equipment_id : 'acc_' . $r->accessory_id;
            $slotKey = $r->equipment_unit_id ? 'u' . $r->equipment_unit_id
                : ($r->accessory_unit_id ? 'u' . $r->accessory_unit_id : 's' . $r->unit_seq);
            $unitLookup[$base . '|' . $r->direction . '|' . $slotKey] = $r;
        }

        $unitIncidents = DB::table('incident_reports')
            ->where('booking_id', $bid)->where('status', '!=', 'resolved')
            ->where(function ($q) {
                $q->whereNotNull('equipment_unit_id')->orWhereNotNull('accessory_unit_id');
            })
            ->get()
            ->keyBy(fn ($r) => 'u' . ($r->equipment_unit_id ?: $r->accessory_unit_id));

        foreach ($equipLines as $eq) {
            $isAcc = $eq->item_type === 'accessory';
            $base = ($isAcc ? 'acc_' : 'eq_') . $eq->ref_id;
            $eq->line_key = $base;
            $eq->is_multi = $eq->quantity > 1;
            $eq->any_damaged_in = false;

            if (! $eq->is_multi) {
                $out = $aggLookup[$base . '|out'] ?? null;
                $in = $aggLookup[$base . '|in'] ?? null;
                $eq->co_checked = (bool) ($out->checked ?? false);
                $eq->co_qty = $out->quantity_actual ?? null;
                $eq->condition_out = $out->condition_out ?? null;
                $eq->co_notes = $out->notes ?? null;
                $eq->ci_checked = (bool) ($in->checked ?? false);
                $eq->ci_qty = $in->quantity_actual ?? null;
                $eq->condition_in = $in->condition_in ?? null;
                $eq->ci_notes = $in->notes ?? null;
                $eq->slots = [];
                continue;
            }

            $realUnits = ($isAcc ? ($accUnitRows[$eq->ref_id] ?? collect()) : ($unitRows[$eq->ref_id] ?? collect()))
                ->take($eq->quantity);
            $slots = [];
            foreach ($realUnits as $u) {
                $slots[] = (object) [
                    'slotKey' => 'u' . $u->unit_id, 'unitPk' => $u->unit_id,
                    'tag' => $u->asset_tag, 'sn' => $u->serial_no ?: 'No serial on file',
                ];
            }
            for ($i = count($slots) + 1; $i <= $eq->quantity; $i++) {
                $slots[] = (object) [
                    'slotKey' => 's' . $i, 'unitPk' => null,
                    'tag' => 'Unit ' . $i . ' of ' . $eq->quantity, 'sn' => 'No individual serial on file',
                ];
            }

            $outDone = 0;
            $inDone = 0;
            foreach ($slots as $s) {
                $outRow = $unitLookup[$base . '|out|' . $s->slotKey] ?? null;
                $inRow = $unitLookup[$base . '|in|' . $s->slotKey] ?? null;
                $s->co_checked = (bool) ($outRow->checked ?? false);
                $s->condition_out = $outRow->condition_out ?? 'good';
                $s->co_notes = $outRow->notes ?? '';
                $s->ci_checked = (bool) ($inRow->checked ?? false);
                $s->condition_in = $inRow->condition_in ?? 'good';
                $s->ci_notes = $inRow->notes ?? '';
                if ($s->co_checked) {
                    $outDone++;
                }
                if ($s->ci_checked) {
                    $inDone++;
                    if (in_array($s->condition_in, ['damaged', 'missing'], true)) {
                        $eq->any_damaged_in = true;
                    }
                }
                $ir = $s->unitPk ? ($unitIncidents['u' . $s->unitPk] ?? null) : null;
                $s->incident_number = $ir->incident_number ?? null;
            }

            $eq->slots = $slots;
            $eq->agg_out_checked = $outDone;
            $eq->agg_in_checked = $inDone;
            $eq->co_checked = $outDone >= $eq->quantity;
            $eq->ci_checked = $inDone >= $eq->quantity;
            $eq->co_qty = $outDone;
            $eq->ci_qty = $inDone;
            $eq->condition_out = null;
            $eq->condition_in = null;
            $eq->co_notes = null;
            $eq->ci_notes = null;
        }
    }

    private function saveChecklist(Request $request, int $bid, int $uid): array
    {
        $d = $request->input('direction', 'out');
        $items = (array) $request->input('items', []);

        foreach ($items as $key => $item) {
            // "eq_<id>" / "acc_<id>" — whole-line legacy key (quantity=1 items).
            // "eq_<id>_u<unitId>" / "acc_<id>_u<unitId>" — a genuinely registered physical unit.
            // "eq_<id>_s<seq>" / "acc_<id>_s<seq>" — an untracked multi-quantity item's Nth copy.
            if (! preg_match('/^(eq|acc)_(\d+)(?:_(u|s)(\d+))?$/', (string) $key, $m)) {
                continue;
            }
            $isAcc = $m[1] === 'acc';
            $refId = (int) $m[2];
            $unitKind = $m[3] ?? null;
            $unitVal = isset($m[4]) ? (int) $m[4] : null;
            $fkCol = $isAcc ? 'accessory_id' : 'equipment_id';

            $unitCol = null;
            $unitColVal = null;
            $seqVal = null;
            if ($unitKind === 'u') {
                $unitCol = $isAcc ? 'accessory_unit_id' : 'equipment_unit_id';
                $unitColVal = $unitVal;
            } elseif ($unitKind === 's') {
                $seqVal = $unitVal;
            }

            $checked = ! empty($item['checked']) ? 1 : 0;
            $qact = isset($item['quantity_actual']) ? (int) $item['quantity_actual'] : ($unitKind ? $checked : 0);
            $notes = $item['notes'] ?? '';
            $insertExtra = [
                'equipment_unit_id' => $unitCol === 'equipment_unit_id' ? $unitColVal : null,
                'accessory_unit_id' => $unitCol === 'accessory_unit_id' ? $unitColVal : null,
                'unit_seq' => $seqVal,
            ];

            if ($d === 'out') {
                $cond = $item['condition_out'] ?? 'good';
                $existing = $this->checklistRowId($bid, $fkCol, $refId, 'out', $unitCol, $unitColVal, $seqVal);
                if ($existing) {
                    DB::table('equipment_checklist')->where('checklist_id', $existing)->update([
                        'checked' => $checked, 'quantity_actual' => $qact, 'condition_out' => $cond,
                        'notes' => $notes, 'checked_by' => $uid, 'checked_at' => now(),
                    ]);
                } else {
                    $qexp = (int) ($item['quantity_expected'] ?? 1);
                    DB::table('equipment_checklist')->insert(array_merge([
                        'booking_id' => $bid, $fkCol => $refId, 'direction' => 'out', 'quantity_expected' => $qexp,
                        'quantity_actual' => $qact, 'condition_out' => $cond, 'checked' => $checked,
                        'notes' => $notes, 'checked_by' => $uid, 'checked_at' => now(),
                    ], $insertExtra));
                }

                if ($checked && ! $isAcc) {
                    $existTx = DB::table('equipment_transactions')->where('booking_id', $bid)->where('equipment_id', $refId)->where('transaction_type', 'checkout')->value('transaction_id');
                    if (! $existTx) {
                        DB::table('equipment_transactions')->insert([
                            'booking_id' => $bid, 'equipment_id' => $refId, 'transaction_type' => 'checkout',
                            'transaction_date' => now(), 'condition_out' => $cond, 'notes' => $notes, 'handled_by' => $uid,
                        ]);
                        DB::table('equipment')->where('equipment_id', $refId)->update(['availability_status' => 'rented']);
                    }
                }
            } else {
                $cond = $item['condition_in'] ?? 'good';
                $existing = $this->checklistRowId($bid, $fkCol, $refId, 'in', $unitCol, $unitColVal, $seqVal);
                if ($existing) {
                    DB::table('equipment_checklist')->where('checklist_id', $existing)->update([
                        'checked' => $checked, 'quantity_actual' => $qact, 'condition_in' => $cond,
                        'notes' => $notes, 'checked_by' => $uid, 'checked_at' => now(),
                    ]);
                } else {
                    $qexp = (int) ($item['quantity_expected'] ?? 1);
                    DB::table('equipment_checklist')->insert(array_merge([
                        'booking_id' => $bid, $fkCol => $refId, 'direction' => 'in', 'quantity_expected' => $qexp,
                        'quantity_actual' => $qact, 'condition_in' => $cond, 'checked' => $checked,
                        'notes' => $notes, 'checked_by' => $uid, 'checked_at' => now(),
                    ], $insertExtra));
                }

                $unitLabel = $seqVal ? "unit {$seqVal} of this line — not individually serialized" : null;

                if ($checked && ! $isAcc) {
                    $existTx = DB::table('equipment_transactions')->where('booking_id', $bid)->where('equipment_id', $refId)->where('transaction_type', 'checkin')->value('transaction_id');
                    if (! $existTx) {
                        $newEquipStatus = in_array($cond, ['damaged', 'missing'], true) ? 'under_repair' : 'available';
                        DB::table('equipment_transactions')->insert([
                            'booking_id' => $bid, 'equipment_id' => $refId, 'transaction_type' => 'checkin',
                            'transaction_date' => now(), 'condition_in' => $cond, 'notes' => $notes, 'handled_by' => $uid,
                        ]);
                        DB::table('equipment')->where('equipment_id', $refId)->update(['availability_status' => $newEquipStatus]);
                    }

                    // Deliberately OUTSIDE the $existTx guard above — that guard only protects the
                    // once-per-equipment-model transaction/availability-status side effect. A
                    // second, third… physical unit of the SAME model coming back damaged still
                    // needs its own incident; nesting this inside $existTx used to mean only the
                    // first unit checked in for a given equipment_id could ever raise one.
                    if (in_array($cond, ['damaged', 'missing'], true)) {
                        $this->createReturnIncident(
                            $bid, $uid, $cond,
                            equipmentId: $refId,
                            equipmentUnitId: $unitCol === 'equipment_unit_id' ? $unitColVal : null,
                            unitLabel: $unitLabel
                        );
                    }
                }

                // Accessories skip equipment_transactions/availability_status (they aren't
                // serialized/tracked there), but a damaged/missing one is exactly as much an
                // incident as damaged/missing equipment.
                if ($checked && $isAcc && in_array($cond, ['damaged', 'missing'], true)) {
                    $this->createReturnIncident(
                        $bid, $uid, $cond,
                        accessoryId: $refId,
                        accessoryUnitId: $unitCol === 'accessory_unit_id' ? $unitColVal : null,
                        unitLabel: $unitLabel
                    );
                }
            }
        }

        if ($d === 'out') {
            $curStatus = DB::table('bookings')->where('booking_id', $bid)->value('booking_status');
            if ($curStatus === 'confirmed') {
                $anyReleased = (int) DB::table('equipment_transactions')->where('booking_id', $bid)->where('transaction_type', 'checkout')->count();
                if ($anyReleased > 0) {
                    DB::table('bookings')->where('booking_id', $bid)->update(['booking_status' => 'ongoing', 'updated_at' => now()]);
                    ActivityLog::record($uid, 'status_change', 'booking', "Booking #$bid moved to ongoing via checklist-out", $bid);
                }
            }
        } else {
            // Booking-status transitions stay gated on equipment only (matching the existing
            // process). A line item now counts as "back" once ALL of its units are checked in —
            // for a quantity=1 item that's still just the one legacy row, unchanged.
            $lines = DB::table('booking_equipment')->where('booking_id', $bid)->select('equipment_id', 'quantity')->get();
            $totalEquip = $lines->count();
            $returnedCount = 0;
            foreach ($lines as $line) {
                $checkedUnits = (int) DB::table('equipment_checklist')
                    ->where('booking_id', $bid)->where('equipment_id', $line->equipment_id)
                    ->where('direction', 'in')->where('checked', 1)->count();
                if ($checkedUnits >= max(1, (int) $line->quantity)) {
                    $returnedCount++;
                }
            }
            if ($totalEquip > 0 && $returnedCount >= $totalEquip) {
                $curStatus = DB::table('bookings')->where('booking_id', $bid)->value('booking_status');
                if (in_array($curStatus, ['ongoing', 'confirmed'], true)) {
                    // Matches BookingDetailController::checkin()'s per-item path: all equipment
                    // back in moves the booking to pending_inspection, not straight to returned —
                    // "returned" only happens once staff deliberately confirm the inspection (or
                    // skip it via Complete Booking).
                    DB::table('bookings')->where('booking_id', $bid)->update(['booking_status' => 'pending_inspection', 'updated_at' => now()]);
                    ActivityLog::record($uid, 'status_change', 'booking', "Booking #$bid pending inspection — all equipment checked in via checklist", $bid);
                }
            }
        }

        ActivityLog::record($uid, 'checklist' . $d, 'booking', "Checklist $d saved for booking #$bid", $bid);

        return ['type' => 'success', 'text' => 'Checklist <strong>' . strtoupper($d) . '</strong> saved successfully.'];
    }

    private function checklistRowId(int $bid, string $fkCol, int $refId, string $dir, ?string $unitCol, ?int $unitColVal, ?int $seqVal): ?int
    {
        $q = DB::table('equipment_checklist')->where('booking_id', $bid)->where($fkCol, $refId)->where('direction', $dir);
        if ($unitCol) {
            $q->where($unitCol, $unitColVal);
        } elseif ($seqVal) {
            $q->where('unit_seq', $seqVal);
        } else {
            $q->whereNull('equipment_unit_id')->whereNull('accessory_unit_id')->whereNull('unit_seq');
        }

        return $q->value('checklist_id');
    }

    private function createReturnIncident(
        int $bid,
        int $uid,
        string $cond,
        ?int $equipmentId = null,
        ?int $accessoryId = null,
        ?int $equipmentUnitId = null,
        ?int $accessoryUnitId = null,
        ?string $unitLabel = null
    ): void {
        // A genuinely registered unit gets its own incident regardless of whether a sibling unit
        // of the same model already has one open — that's the entire point of unit tracking.
        // Only the legacy no-unit-identity case (or a synthetic, unregistered unit) falls back to
        // "at most one open incident per line item," since there's no real record to distinguish
        // a second synthetic copy beyond a text label.
        $dedupe = DB::table('incident_reports')->where('booking_id', $bid)->where('status', '!=', 'resolved');
        if ($equipmentUnitId) {
            $dedupe->where('equipment_unit_id', $equipmentUnitId);
        } elseif ($accessoryUnitId) {
            $dedupe->where('accessory_unit_id', $accessoryUnitId);
        } else {
            $dedupe
                ->when($equipmentId, fn ($q) => $q->where('equipment_id', $equipmentId)->whereNull('equipment_unit_id'))
                ->when($accessoryId, fn ($q) => $q->where('accessory_id', $accessoryId)->whereNull('accessory_unit_id'));
        }
        if ($dedupe->exists()) {
            return;
        }

        $itemDesc = $accessoryId
            ? 'accessory check-in: ' . (DB::table('accessories')->where('accessory_id', $accessoryId)->value('accessory_name') ?? "#$accessoryId")
            : 'equipment check-in';
        if ($unitLabel) {
            $itemDesc .= " ($unitLabel)";
        }

        $irYear = date('Y');
        $irCount = (int) DB::table('incident_reports')->whereYear('created_at', $irYear)->count();
        $irNum = 'IR-' . $irYear . '-' . str_pad((string) ($irCount + 1), 4, '0', STR_PAD_LEFT);
        DB::table('incident_reports')->insert([
            'booking_id' => $bid, 'equipment_id' => $equipmentId, 'accessory_id' => $accessoryId,
            'equipment_unit_id' => $equipmentUnitId, 'accessory_unit_id' => $accessoryUnitId,
            'incident_number' => $irNum, 'reported_by' => $uid, 'incident_type' => $cond,
            'incident_date' => now()->toDateString(),
            'description' => "Auto-created on $itemDesc: condition reported as $cond.",
            'status' => 'open',
        ]);
    }
}
