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

        $equipLines = DB::table('booking_equipment as be')
            ->join('equipment as e', 'be.equipment_id', '=', 'e.equipment_id')
            ->join('equipment_categories as ec', 'e.category_id', '=', 'ec.category_id')
            ->leftJoin('equipment_checklist as co', function ($j) use ($bid) {
                $j->on('co.equipment_id', '=', 'be.equipment_id')->where('co.booking_id', $bid)->where('co.direction', 'out');
            })
            ->leftJoin('equipment_checklist as ci', function ($j) use ($bid) {
                $j->on('ci.equipment_id', '=', 'be.equipment_id')->where('ci.booking_id', $bid)->where('ci.direction', 'in');
            })
            ->leftJoin('users as uo', 'co.checked_by', '=', 'uo.user_id')
            ->leftJoin('users as ui', 'ci.checked_by', '=', 'ui.user_id')
            ->where('be.booking_id', $bid)
            ->orderBy('ec.category_name')->orderBy('e.equipment_name')
            ->select(
                'be.equipment_id', 'be.quantity',
                'e.equipment_name', 'e.brand', 'e.model', 'ec.category_name',
                'co.quantity_actual as co_qty', 'co.condition_out', 'co.checked as co_checked',
                'co.notes as co_notes', 'co.checked_at as co_at',
                DB::raw("CONCAT(uo.first_name,' ',uo.last_name) as co_by_name"),
                'ci.quantity_actual as ci_qty', 'ci.condition_in', 'ci.checked as ci_checked',
                'ci.notes as ci_notes', 'ci.checked_at as ci_at',
                DB::raw("CONCAT(ui.first_name,' ',ui.last_name) as ci_by_name")
            )
            ->get();

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
        $canManage = in_array($role, config('filmspec.manage_roles'), true);

        $bid = (int) $request->query('booking_id', 0);
        $dir = $request->query('dir', 'out');
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

        $ceConfirmed = DB::table('cost_estimates')->where('booking_id', $bid)->orderByDesc('ce_id')->value('status') === 'confirmed';
        if ($dir === 'out' && ! $ceConfirmed && $booking->booking_status === 'confirmed') {
            $request->session()->flash('bd_flash', [
                'type' => 'danger',
                'text' => "Equipment can't be checked out yet — the cost estimate hasn't been confirmed. "
                    . 'Use <strong>Confirm CE</strong> on the booking page first.',
            ]);

            return redirect()->route('booking-detail', $bid);
        }

        $msg = null;
        if ($request->isMethod('post') && $canManage) {
            $msg = $this->saveChecklist($request, $bid, $user->user_id);
            $dir = $request->input('direction', $dir);
        }

        $equipLines = DB::table('booking_equipment as be')
            ->join('equipment as e', 'be.equipment_id', '=', 'e.equipment_id')
            ->join('equipment_categories as ec', 'e.category_id', '=', 'ec.category_id')
            ->leftJoin('equipment_checklist as co', function ($j) use ($bid) {
                $j->on('co.equipment_id', '=', 'be.equipment_id')->where('co.booking_id', $bid)->where('co.direction', 'out');
            })
            ->leftJoin('equipment_checklist as ci', function ($j) use ($bid) {
                $j->on('ci.equipment_id', '=', 'be.equipment_id')->where('ci.booking_id', $bid)->where('ci.direction', 'in');
            })
            ->where('be.booking_id', $bid)
            ->orderBy('ec.category_name')->orderBy('e.equipment_name')
            ->select(
                'be.bk_equip_id', 'be.equipment_id', 'be.quantity', 'be.days', 'be.daily_rate',
                'e.equipment_name', 'e.brand', 'e.model', 'e.image_path', 'ec.category_name',
                'co.checklist_id as co_id', 'co.checked as co_checked', 'co.quantity_actual as co_qty',
                'co.condition_out', 'co.notes as co_notes', 'co.checked_by as co_by', 'co.checked_at as co_at',
                'ci.checklist_id as ci_id', 'ci.checked as ci_checked', 'ci.quantity_actual as ci_qty',
                'ci.condition_in', 'ci.notes as ci_notes', 'ci.checked_by as ci_by', 'ci.checked_at as ci_at'
            )
            ->get();

        $totalItems = $equipLines->count();
        $outChecked = $equipLines->filter(fn ($e) => $e->co_checked)->count();
        $inChecked = $equipLines->filter(fn ($e) => $e->ci_checked)->count();
        $damaged = $equipLines->filter(fn ($e) => in_array($e->condition_in, ['damaged', 'missing'], true))->count();

        $crewCount = (int) DB::table('booking_crew')->where('booking_id', $bid)->count();
        $totalEquipQtyAll = (int) DB::table('booking_equipment')->where('booking_id', $bid)->sum('quantity');
        $transCost = (float) ($booking->transportation_cost ?? 0);
        $driverNeeded = $transCost > 0 || $totalEquipQtyAll >= 5;
        $driverCount = 0;
        if ($driverNeeded) {
            $driverCount = (int) DB::table('booking_crew as bc')
                ->join('crew_positions as cp', 'bc.position_id', '=', 'cp.position_id')
                ->where('bc.booking_id', $bid)->where(DB::raw('LOWER(cp.position_name)'), 'like', '%driver%')
                ->count();
        }
        $paidAmt = (float) DB::table('payments')->where('booking_id', $bid)->sum('amount');
        $req50 = (float) ($booking->final_amount ?? 0) * 0.5;
        $payGate = $booking->client_type !== 'first_time' || $paidAmt >= $req50;
        $gateOk = $totalItems > 0 && $crewCount > 0 && (! $driverNeeded || $driverCount > 0) && $payGate;

        return view('checklist', [
            'msg' => $msg, 'canManage' => $canManage, 'booking' => $booking, 'bid' => $bid, 'dir' => $dir,
            'equipLines' => $equipLines, 'totalItems' => $totalItems, 'outChecked' => $outChecked,
            'inChecked' => $inChecked, 'damaged' => $damaged,
            'crewCount' => $crewCount, 'driverNeeded' => $driverNeeded, 'driverCount' => $driverCount,
            'transCost' => $transCost, 'paidAmt' => $paidAmt, 'req50' => $req50, 'payGate' => $payGate, 'gateOk' => $gateOk,
            'condOut' => $this->condOut, 'condIn' => $this->condIn, 'condBadge' => $this->condBadge,
        ]);
    }

    private function saveChecklist(Request $request, int $bid, int $uid): array
    {
        $d = $request->input('direction', 'out');
        $items = (array) $request->input('items', []);

        foreach ($items as $eid => $item) {
            $eid = (int) $eid;
            $checked = ! empty($item['checked']) ? 1 : 0;
            $qact = (int) ($item['quantity_actual'] ?? 0);
            $notes = $item['notes'] ?? '';

            if ($d === 'out') {
                $cond = $item['condition_out'] ?? 'good';
                $existing = DB::table('equipment_checklist')->where('booking_id', $bid)->where('equipment_id', $eid)->where('direction', 'out')->value('checklist_id');
                if ($existing) {
                    DB::table('equipment_checklist')->where('checklist_id', $existing)->update([
                        'checked' => $checked, 'quantity_actual' => $qact, 'condition_out' => $cond,
                        'notes' => $notes, 'checked_by' => $uid, 'checked_at' => now(),
                    ]);
                } else {
                    $qexp = (int) ($item['quantity_expected'] ?? 1);
                    DB::table('equipment_checklist')->insert([
                        'booking_id' => $bid, 'equipment_id' => $eid, 'direction' => 'out', 'quantity_expected' => $qexp,
                        'quantity_actual' => $qact, 'condition_out' => $cond, 'checked' => $checked,
                        'notes' => $notes, 'checked_by' => $uid, 'checked_at' => now(),
                    ]);
                }

                if ($checked) {
                    $existTx = DB::table('equipment_transactions')->where('booking_id', $bid)->where('equipment_id', $eid)->where('transaction_type', 'checkout')->value('transaction_id');
                    if (! $existTx) {
                        DB::table('equipment_transactions')->insert([
                            'booking_id' => $bid, 'equipment_id' => $eid, 'transaction_type' => 'checkout',
                            'transaction_date' => now(), 'condition_out' => $cond, 'notes' => $notes, 'handled_by' => $uid,
                        ]);
                        DB::table('equipment')->where('equipment_id', $eid)->update(['availability_status' => 'rented']);
                    }
                }
            } else {
                $cond = $item['condition_in'] ?? 'good';
                $existing = DB::table('equipment_checklist')->where('booking_id', $bid)->where('equipment_id', $eid)->where('direction', 'in')->value('checklist_id');
                if ($existing) {
                    DB::table('equipment_checklist')->where('checklist_id', $existing)->update([
                        'checked' => $checked, 'quantity_actual' => $qact, 'condition_in' => $cond,
                        'notes' => $notes, 'checked_by' => $uid, 'checked_at' => now(),
                    ]);
                } else {
                    $qexp = (int) ($item['quantity_expected'] ?? 1);
                    DB::table('equipment_checklist')->insert([
                        'booking_id' => $bid, 'equipment_id' => $eid, 'direction' => 'in', 'quantity_expected' => $qexp,
                        'quantity_actual' => $qact, 'condition_in' => $cond, 'checked' => $checked,
                        'notes' => $notes, 'checked_by' => $uid, 'checked_at' => now(),
                    ]);
                }

                if ($checked) {
                    $existTx = DB::table('equipment_transactions')->where('booking_id', $bid)->where('equipment_id', $eid)->where('transaction_type', 'checkin')->value('transaction_id');
                    if (! $existTx) {
                        $newEquipStatus = in_array($cond, ['damaged', 'missing'], true) ? 'under_repair' : 'available';
                        DB::table('equipment_transactions')->insert([
                            'booking_id' => $bid, 'equipment_id' => $eid, 'transaction_type' => 'checkin',
                            'transaction_date' => now(), 'condition_in' => $cond, 'notes' => $notes, 'handled_by' => $uid,
                        ]);
                        DB::table('equipment')->where('equipment_id', $eid)->update(['availability_status' => $newEquipStatus]);

                        if (in_array($cond, ['damaged', 'missing'], true)) {
                            $irExists = DB::table('incident_reports')->where('booking_id', $bid)->where('equipment_id', $eid)->where('status', '!=', 'resolved')->value('incident_id');
                            if (! $irExists) {
                                $irYear = date('Y');
                                $irCount = (int) DB::table('incident_reports')->whereYear('created_at', $irYear)->count();
                                $irNum = 'IR-' . $irYear . '-' . str_pad((string) ($irCount + 1), 4, '0', STR_PAD_LEFT);
                                DB::table('incident_reports')->insert([
                                    'booking_id' => $bid, 'equipment_id' => $eid, 'incident_number' => $irNum,
                                    'reported_by' => $uid, 'incident_type' => $cond, 'incident_date' => now()->toDateString(),
                                    'description' => "Auto-created on equipment check-in: condition reported as $cond.",
                                    'status' => 'open',
                                ]);
                            }
                        }
                    }
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
            $totalEquip = (int) DB::table('booking_equipment')->where('booking_id', $bid)->count();
            $returnedCount = (int) DB::table('equipment_checklist')->where('booking_id', $bid)->where('direction', 'in')->where('checked', 1)->count();
            if ($totalEquip > 0 && $returnedCount >= $totalEquip) {
                $curStatus = DB::table('bookings')->where('booking_id', $bid)->value('booking_status');
                if (in_array($curStatus, ['ongoing', 'confirmed'], true)) {
                    // Matches BookingDetailController::checkin()'s per-item path: all equipment
                    // back in moves the booking to pending_inspection, not straight to returned —
                    // "returned" only happens once staff deliberately confirm the inspection (or
                    // skip it via Complete Booking). Bulk checklist saves used to jump straight to
                    // returned, silently bypassing that inspection gate.
                    DB::table('bookings')->where('booking_id', $bid)->update(['booking_status' => 'pending_inspection', 'updated_at' => now()]);
                    ActivityLog::record($uid, 'status_change', 'booking', "Booking #$bid pending inspection — all equipment checked in via checklist", $bid);
                }
            }
        }

        ActivityLog::record($uid, 'checklist' . $d, 'booking', "Checklist $d saved for booking #$bid", $bid);

        return ['type' => 'success', 'text' => 'Checklist <strong>' . strtoupper($d) . '</strong> saved successfully.'];
    }
}
