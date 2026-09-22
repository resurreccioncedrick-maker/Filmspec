<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\BookingCosting;
use App\Support\DataExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cross-booking staff queue for field follow-up requests (equipment/accessory/crew) once
 * they're approved — Dispatch (assign vehicle + driver + ETA) and Deliver (mark arrived) both
 * live here instead of on the per-booking page, since staff coordinate logistics across many
 * simultaneous shoots at once. Approve/Reject stay on the per-booking Requests tab
 * (BookingDetailController), where staff already has full booking context.
 */
class FieldRequestsController extends Controller
{
    public function index(Request $request): View|StreamedResponse|Response
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';
        // Route middleware (can_access:field_requests) already blocks the page for other
        // roles, but every other action-handling controller in this app also double-gates the
        // POST branch in-controller — matching that convention here.
        $canPost = in_array('field_requests', config("filmspec.role_permissions.$role", []), true);

        $msg = null;
        if ($request->isMethod('post') && $canPost) {
            $msg = $this->handleAction($request, $user->user_id);
        }

        if ($request->filled('export')) {
            return $this->export($request);
        }

        $statusFilter = $request->query('status', 'active');

        $query = $this->filteredQuery($request);

        $requests = $query->orderByRaw("FIELD(r.status,'approved','dispatched','pending','delivered','rejected')")
            ->orderByDesc('r.created_at')
            ->select(
                'r.*', 'b.booking_reference', 'b.shoot_date_start', 'b.shoot_date_end', 'b.shoot_location',
                'c.company_name', 'c.contact_person',
                'e.equipment_name', 'acc.accessory_name', 'pos.position_name',
                DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS crew_name"),
                'vr.label as vehicle_label',
                DB::raw("CONCAT(drv.first_name,' ',drv.last_name) AS driver_name")
            )
            ->get();

        // Awaiting Dispatch/Out for Delivery apply only to physical resources (equipment/
        // accessories) — crew items are already committed at approval time and just need a
        // driver/ETA, tracked separately below as "Ready for Dispatch".
        $stats = [
            'pending' => (int) DB::table('booking_equipment_requests')->where('status', 'pending')->count(),
            'approved' => (int) DB::table('booking_equipment_requests')->where('status', 'approved')
                ->whereIn('item_type', ['equipment', 'accessory'])->count(),
            'ready_crew' => (int) DB::table('booking_equipment_requests')->where('status', 'approved')
                ->where('item_type', 'crew')->count(),
            'dispatched' => (int) DB::table('booking_equipment_requests')->where('status', 'dispatched')->count(),
            'overdue' => (int) DB::table('booking_equipment_requests')->where('status', 'dispatched')
                ->where('eta', '<', now())->count(),
        ];

        $vehicleRates = DB::table('vehicle_rates')->where('is_active', 1)->orderBy('base_rate')->get();
        $activeDrivers = DB::table('crew_members as cm')
            ->leftJoin('crew_positions as cp', 'cm.primary_position_id', '=', 'cp.position_id')
            ->where('cm.status', 'active')
            ->select('cm.crew_id', DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS name"), 'cp.position_name')
            ->orderByRaw("(LOWER(cp.position_name) LIKE '%driver%') DESC")
            ->orderBy('cm.last_name')
            ->get();

        return view('field-requests', [
            'msg' => $msg, 'requests' => $requests, 'stats' => $stats, 'statusFilter' => $statusFilter,
            'vehicleRates' => $vehicleRates, 'activeDrivers' => $activeDrivers,
        ]);
    }

    /** Same filters index() applies, shared with export() so the two can never drift apart. */
    private function filteredQuery(Request $request)
    {
        $statusFilter = $request->query('status', 'active');

        $query = DB::table('booking_equipment_requests as r')
            ->join('bookings as b', 'r.booking_id', '=', 'b.booking_id')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->leftJoin('equipment as e', 'r.equipment_id', '=', 'e.equipment_id')
            ->leftJoin('accessories as acc', 'r.accessory_id', '=', 'acc.accessory_id')
            ->leftJoin('crew_positions as pos', 'r.position_id', '=', 'pos.position_id')
            ->leftJoin('crew_members as cm', 'r.crew_id', '=', 'cm.crew_id')
            ->leftJoin('vehicle_rates as vr', 'r.vehicle_rate_id', '=', 'vr.vehicle_id')
            ->leftJoin('crew_members as drv', 'r.driver_crew_id', '=', 'drv.crew_id');

        if ($statusFilter === 'active') {
            $query->whereIn('r.status', ['approved', 'dispatched']);
        } elseif ($statusFilter !== 'all') {
            $query->where('r.status', $statusFilter);
        }

        return $query;
    }

    /** Export ▾ — reuses filteredQuery(), already unbounded (no pagination on this page at all). */
    private function export(Request $request): StreamedResponse|Response
    {
        $headers = ['Booking', 'Client', 'Type', 'Item', 'Qty', 'Status', 'Dispatch Info'];

        $rows = $this->filteredQuery($request)
            ->orderByRaw("FIELD(r.status,'approved','dispatched','pending','delivered','rejected')")
            ->orderByDesc('r.created_at')
            ->select(
                'r.*', 'b.booking_reference', 'c.company_name', 'c.contact_person',
                'e.equipment_name', 'acc.accessory_name', 'pos.position_name',
                DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS crew_name"),
                'vr.label as vehicle_label',
                DB::raw("CONCAT(drv.first_name,' ',drv.last_name) AS driver_name")
            )
            ->get()
            ->map(function ($r) {
                $item = $r->item_type === 'equipment' ? $r->equipment_name
                    : ($r->item_type === 'accessory' ? $r->accessory_name : $r->position_name);
                $dispatch = $r->status === 'dispatched'
                    ? trim(($r->vehicle_label ?: '') . ' · ' . trim((string) $r->driver_name) . ($r->eta ? ' · ETA ' . Carbon::parse($r->eta)->format('M j, g:ia') : ''), ' ·')
                    : '—';

                return [
                    $r->booking_reference,
                    $r->company_name ?: $r->contact_person,
                    ucfirst($r->item_type),
                    $item ?: '—',
                    (int) $r->quantity,
                    ucfirst($r->status),
                    $dispatch ?: '—',
                ];
            })
            ->all();

        return DataExporter::respond($request->query('export'), 'Field Requests', $headers, $rows, 'field-requests-export');
    }

    private function handleAction(Request $request, int $uid): ?array
    {
        $action = $request->input('action', '');

        if ($action === 'dispatch_field_request') {
            return $this->dispatch($request, $uid);
        }
        if ($action === 'deliver_field_request') {
            return $this->deliver($request, $uid);
        }

        return null;
    }

    private function dispatch(Request $request, int $uid): array
    {
        $reqId = (int) $request->input('request_id');
        $req = DB::table('booking_equipment_requests')->where('request_id', $reqId)->first();
        if (! $req || $req->status !== 'approved') {
            return ['type' => 'danger', 'text' => 'This request is no longer awaiting dispatch.'];
        }

        $driverCid = (int) $request->input('driver_crew_id', 0);
        $eta = trim($request->input('eta', ''));
        if (! $driverCid || ! $eta) {
            return ['type' => 'danger', 'text' => 'A driver and ETA are required to dispatch.'];
        }
        $driver = DB::table('crew_members')->where('crew_id', $driverCid)->first();
        if (! $driver || $driver->status !== 'active') {
            return ['type' => 'danger', 'text' => 'Selected driver is not active.'];
        }
        $vehicleId = (int) $request->input('vehicle_rate_id', 0);

        $booking = DB::table('bookings')->where('booking_id', $req->booking_id)->first();

        // Same double-booking guard BookingDetailController::addEquipment()/fieldAddEquipment()
        // use — approveFieldRequest() never checked this, so without it here, dispatching a
        // field request was the one equipment-assignment path in the app that could hand the
        // same physical item to two overlapping bookings at once. Checked (and, for a brand
        // new line, rejected) BEFORE the atomic claim below, so a rejected dispatch leaves the
        // request sitting at 'approved' instead of wrongly flipping it to 'dispatched'.
        if ($req->item_type === 'equipment' && $req->equipment_id) {
            $alreadyOnThisBooking = DB::table('booking_equipment')->where('booking_id', $req->booking_id)->where('equipment_id', $req->equipment_id)->exists();
            // A top-up (this exact booking already has some of this equipment, from an earlier
            // dispatch) skips the availability check entirely — same special case this method
            // always had, since re-requesting more of an item this booking already holds isn't
            // a new commitment to check.
            if (! $alreadyOnThisBooking) {
                $availError = \App\Support\EquipmentAvailability::check($req->equipment_id, (int) $req->quantity, $req->booking_id, $booking);
                if ($availError) {
                    return ['type' => 'danger', 'text' => $availError['text'] . ' Reject this request or resolve the conflict before dispatching.'];
                }
            }
        }

        // Atomic claim: only proceed if this row is still 'approved' at the moment of the
        // update. A second near-simultaneous submit (double-click, browser back-and-resubmit)
        // will affect 0 rows here and bail out before touching booking_equipment/accessories —
        // closes the double-dispatch race that a plain check-then-act would leave open.
        $claimed = DB::table('booking_equipment_requests')->where('request_id', $reqId)->where('status', 'approved')->update([
            'status' => 'dispatched', 'vehicle_rate_id' => $vehicleId ?: null, 'driver_crew_id' => $driverCid, 'eta' => $eta,
        ]);
        if (! $claimed) {
            return ['type' => 'danger', 'text' => 'This request is no longer awaiting dispatch.'];
        }

        $numDays = max(1, (new \DateTime($booking->shoot_date_start))->diff(new \DateTime($booking->shoot_date_end))->days + 1);

        if ($req->item_type === 'equipment' && $req->equipment_id) {
            // subtotal is a STORED GENERATED column on booking_equipment — only qty/days/rate set explicitly
            $existing = DB::table('booking_equipment')->where('booking_id', $req->booking_id)->where('equipment_id', $req->equipment_id)->first();
            if ($existing) {
                // Explicit (int) cast before interpolating into the raw expression — defense in
                // depth, since a DB-fetched column isn't guaranteed to already be a true PHP int.
                $addQty = (int) $req->quantity;
                DB::table('booking_equipment')->where('bk_equip_id', $existing->bk_equip_id)->update(['quantity' => DB::raw("quantity + $addQty")]);
            } else {
                DB::table('booking_equipment')->insert([
                    'booking_id' => $req->booking_id, 'equipment_id' => $req->equipment_id, 'quantity' => $req->quantity,
                    'days' => $numDays, 'daily_rate' => $req->daily_rate,
                ]);
            }
            DB::table('equipment_transactions')->insert([
                'booking_id' => $req->booking_id, 'equipment_id' => $req->equipment_id, 'transaction_type' => 'checkout',
                'transaction_date' => now(), 'condition_out' => 'good', 'notes' => 'Field request dispatch', 'handled_by' => $uid,
            ]);
            DB::table('equipment')->where('equipment_id', $req->equipment_id)->update(['availability_status' => 'rented']);
            DB::table('equipment_checklist')->insertOrIgnore([
                'booking_id' => $req->booking_id, 'equipment_id' => $req->equipment_id, 'direction' => 'out',
                'quantity_expected' => $req->quantity, 'quantity_actual' => $req->quantity, 'condition_out' => 'good',
                'checked' => 1, 'checked_by' => $uid, 'checked_at' => now(),
            ]);
            BookingCosting::updateBookingTotal($req->booking_id);
            BookingCosting::generateCostEstimate($req->booking_id, $uid);
        } elseif ($req->item_type === 'accessory' && $req->accessory_id) {
            $subtotal = (float) $req->quantity * $numDays * (float) $req->daily_rate;
            $existing = DB::table('booking_accessories')->where('booking_id', $req->booking_id)->where('accessory_id', $req->accessory_id)->first();
            if ($existing) {
                // Explicit casts before interpolating into the raw expressions — defense in
                // depth, since DB-fetched columns aren't guaranteed to already be true PHP types.
                $addQty = (int) $req->quantity;
                $addSubtotal = (float) $subtotal;
                DB::table('booking_accessories')->where('ba_id', $existing->ba_id)->update([
                    'quantity' => DB::raw("quantity + $addQty"),
                    'subtotal' => DB::raw("subtotal + $addSubtotal"),
                ]);
            } else {
                DB::table('booking_accessories')->insert([
                    'booking_id' => $req->booking_id, 'accessory_id' => $req->accessory_id, 'quantity' => $req->quantity,
                    'days' => $numDays, 'daily_rate' => $req->daily_rate, 'is_included' => 0, 'subtotal' => $subtotal,
                    'notes' => 'Field request dispatch',
                ]);
            }
            BookingCosting::generateCostEstimate($req->booking_id, $uid);
        }
        // crew: already booked at approval — dispatch here only carries the driver/ETA

        $driverName = trim($driver->first_name . ' ' . $driver->last_name);
        ActivityLog::record($uid, 'dispatch', 'booking', "Field request #$reqId dispatched — driver: $driverName", $req->booking_id);

        return ['type' => 'success', 'text' => "Dispatched with driver {$driverName}."];
    }

    private function deliver(Request $request, int $uid): array
    {
        $reqId = (int) $request->input('request_id');
        $req = DB::table('booking_equipment_requests')->where('request_id', $reqId)->first();
        if (! $req || $req->status !== 'dispatched') {
            return ['type' => 'danger', 'text' => 'This request is not out for delivery.'];
        }

        DB::table('booking_equipment_requests')->where('request_id', $reqId)->update([
            'status' => 'delivered', 'delivered_at' => now(),
        ]);
        ActivityLog::record($uid, 'deliver', 'booking', "Field request #$reqId marked delivered", $req->booking_id);

        return ['type' => 'success', 'text' => 'Marked as delivered.'];
    }
}
