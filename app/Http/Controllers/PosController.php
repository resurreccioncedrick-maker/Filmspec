<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\BookingCosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(Request $request): View
    {
        $role = $request->user()->role->role_name ?? '';
        $msg = null;

        if ($request->isMethod('post') && $request->input('action') === 'quick_sale'
            && in_array($role, ['super_admin', 'admin', 'operations_manager', 'accounting'], true)) {
            $msg = $this->quickSale($request);
        }

        $clients = DB::table('clients')->orderBy('company_name')->select('client_id', 'company_name', 'contact_person')->get();
        $equipment = DB::table('equipment as e')
            ->join('equipment_categories as ec', 'e.category_id', '=', 'ec.category_id')
            ->where('e.availability_status', 'available')
            ->orderBy('ec.category_name')->orderBy('e.equipment_name')
            ->select('e.*', 'ec.category_name')
            ->get();

        return view('pos', ['msg' => $msg, 'clients' => $clients, 'equipment' => $equipment]);
    }

    private function quickSale(Request $request): array
    {
        $clientId = (int) $request->input('client_id');
        $equipmentIds = (array) $request->input('equipment_ids', []);
        $days = max(1, (int) $request->input('days', 1));
        $projectTitle = $request->input('project_title') ?: ('POS Sale - ' . now()->toDateString());
        $amount = (float) $request->input('amount', 0);
        $pmethod = $request->input('payment_method', 'cash');
        $ref2 = $request->input('reference_number', '');
        $isVat = $request->boolean('is_vat') ? 1 : 0;
        $notes = $request->input('notes', '');
        $uid = $request->user()->user_id;

        if (! $clientId || ! $equipmentIds) {
            return ['type' => 'error', 'text' => 'Please select a client and at least one equipment item.'];
        }
        if ($amount <= 0) {
            return ['type' => 'error', 'text' => 'A walk-in sale must record a payment. Please enter the amount received.'];
        }

        $clientType = DB::table('clients')->where('client_id', $clientId)->value('client_type');

        try {
            return DB::transaction(function () use ($clientId, $equipmentIds, $days, $projectTitle, $amount, $pmethod, $ref2, $isVat, $notes, $clientType, $uid) {
                // booking_reference is varchar(20) — legacy's 'POS-' . date('Y-m-d-His') format produces
                // 22 characters and always failed to insert; shortened to fit while staying unique-per-second
                $ref = 'POS-' . now()->format('ymd-His');

                // A quick sale is a walk-in: the customer takes the gear immediately, so this mirrors
                // BookingDetailController::checkout() rather than a normal (future-dated) booking —
                // 'ongoing'/'rented' plus a real checkout transaction, not 'confirmed'/'booked'.
                // 'booked' was tried first, but nothing in the app ever moves equipment OUT of
                // 'booked' except the normal Release/Check-in flow on a 'confirmed' booking, and
                // nothing here ever routed staff there — every quick sale left its equipment stuck
                // unavailable forever. Booking status 'ongoing' is what lets staff find and Check In
                // this equipment later through the booking's own page, same as any other release.
                $bookingId = DB::table('bookings')->insertGetId([
                    'booking_reference' => $ref, 'client_id' => $clientId, 'booking_type' => 'package',
                    'project_title' => $projectTitle, 'shoot_date_start' => now()->toDateString(),
                    'shoot_date_end' => now()->addDays($days)->toDateString(),
                    'booking_status' => 'ongoing', 'created_by' => $uid,
                    'notes' => $notes,
                ]);

                foreach ($equipmentIds as $equipId) {
                    // Locked so a concurrent sale/booking can't read the same "available" row
                    // before either has committed its status change — the exact double-booking
                    // gap this method used to have (only the page-load dropdown filtered on
                    // availability; a resubmitted/crafted request skipped the check entirely).
                    $equip = DB::table('equipment')->where('equipment_id', (int) $equipId)->lockForUpdate()->first();
                    if (! $equip) {
                        continue;
                    }
                    if ($equip->availability_status !== 'available') {
                        throw new \RuntimeException('"' . $equip->equipment_name . '" is no longer available (status: ' . ucfirst($equip->availability_status) . '). Refresh and try again.');
                    }

                    // subtotal is a STORED GENERATED column (quantity*days*daily_rate) — only set the inputs
                    DB::table('booking_equipment')->insert([
                        'booking_id' => $bookingId, 'equipment_id' => $equip->equipment_id,
                        'quantity' => 1, 'days' => $days, 'daily_rate' => $equip->daily_rate,
                    ]);
                    DB::table('equipment_transactions')->insert([
                        'booking_id' => $bookingId, 'equipment_id' => $equip->equipment_id, 'transaction_type' => 'checkout',
                        'transaction_date' => now(), 'condition_out' => 'good', 'handled_by' => $uid,
                    ]);
                    DB::table('equipment')->where('equipment_id', $equip->equipment_id)->update(['availability_status' => 'rented']);
                }

                BookingCosting::updateBookingTotal($bookingId);
                $total = (float) (DB::table('bookings')->where('booking_id', $bookingId)->value('final_amount') ?? 0);

                // Same 50%-downpayment-for-first-time-clients rule BookingDetailController::
                // checkout()/bulkCheckout() enforce before releasing equipment — a walk-in sale
                // releases equipment immediately, so it has to be checked here instead, against
                // the payment being recorded right now rather than a running "paid so far" total.
                if ($clientType === 'first_time' && $total > 0) {
                    $required50 = $total * 0.5;
                    if ($amount < $required50) {
                        throw new \RuntimeException('New customer must pay at least 50% (₱' . number_format($required50, 2) . ') of the ₱' . number_format($total, 2) . ' total. Amount entered: ₱' . number_format($amount, 2) . '.');
                    }
                }

                $rctype = $isVat ? 'official_receipt' : 'acknowledgement_receipt';
                $rcPrefix = $isVat ? 'OR' : 'AR';
                $rcSeq = (int) DB::table('payments')->where('receipt_type', $rctype)->lockForUpdate()->count() + 1;
                $rcnum = $rcPrefix . '-' . str_pad((string) $rcSeq, 5, '0', STR_PAD_LEFT);
                DB::table('payments')->insert([
                    'booking_id' => $bookingId, 'payment_type' => $amount >= $total ? 'final' : 'downpayment',
                    'payment_method' => $pmethod, 'amount' => $amount, 'reference_number' => $ref2,
                    'payment_date' => now()->toDateString(), 'received_by' => $uid, 'is_vat' => $isVat,
                    'receipt_number' => $rcnum, 'receipt_type' => $rctype,
                ]);

                $payStatus = $amount <= 0 ? 'unpaid' : ($total > 0 && $amount >= $total ? 'paid' : 'partial');
                DB::table('bookings')->where('booking_id', $bookingId)->update(['payment_status' => $payStatus, 'updated_at' => now()]);

                ActivityLog::record($uid, 'create', 'booking', "POS walk-in sale $ref recorded (₱" . number_format($amount, 2) . ' paid)', $bookingId);

                return ['type' => 'success', 'text' => "POS Sale completed. Booking Reference: $ref. Payment of ₱" . number_format($amount, 2) . " recorded ($rcnum)."];
            });
        } catch (\RuntimeException $e) {
            return ['type' => 'error', 'text' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['type' => 'error', 'text' => 'Error processing POS sale. Please try again.'];
        }
    }
}
