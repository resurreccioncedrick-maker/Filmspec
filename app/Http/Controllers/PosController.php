<?php

namespace App\Http\Controllers;

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
        $uid = $request->user()->user_id;

        if (! $clientId || ! $equipmentIds) {
            return ['type' => 'error', 'text' => 'Please select a client and at least one equipment item.'];
        }

        try {
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
                'notes' => $request->input('notes', ''),
            ]);

            foreach ($equipmentIds as $equipId) {
                $equip = DB::table('equipment')->where('equipment_id', (int) $equipId)->first();
                if ($equip) {
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
            }

            BookingCosting::updateBookingTotal($bookingId);

            return ['type' => 'success', 'text' => "POS Sale completed. Booking Reference: $ref"];
        } catch (\Throwable $e) {
            return ['type' => 'error', 'text' => 'Error processing POS sale. Please try again.'];
        }
    }
}
