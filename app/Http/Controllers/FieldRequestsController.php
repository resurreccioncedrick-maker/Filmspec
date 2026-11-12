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

class FieldRequestsController extends Controller
{
    public function index(Request $request): View|StreamedResponse|Response
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';
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

        $tabCounts = DB::table('booking_equipment_requests')->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        $vehicleRates = DB::table('vehicle_rates')->where('is_active', 1)->orderBy('base_rate')->get();
        $activeDrivers = DB::table('crew_members as cm')
            ->leftJoin('crew_positions as cp', 'cm.primary_position_id', '=', 'cp.position_id')
            ->where('cm.status', 'active')
            ->select('cm.crew_id', 'cm.primary_position_id as position_id', DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS name"), 'cp.position_name')
            ->orderByRaw("(LOWER(cp.position_name) LIKE '%driver%') DESC")
            ->orderBy('cm.last_name')
            ->get();

        return view('field-requests', [
            'msg' => $msg, 'requests' => $requests, 'stats' => $stats, 'statusFilter' => $statusFilter,
            'vehicleRates' => $vehicleRates, 'activeDrivers' => $activeDrivers, 'tabCounts' => $tabCounts,
        ]);
    }

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
        $costChanged = false;

        if ($req->item_type === 'crew' && ! $req->crew_id) {
            $assignCid = (int) $request->input('assign_crew_id', 0);
            if (! $assignCid) {
                return ['type' => 'danger', 'text' => 'Select a crew member to assign to this role before dispatching.'];
            }
            $assignee = DB::table('crew_members')->where('crew_id', $assignCid)->first();
            if (! $assignee || $assignee->status !== 'active') {
                return ['type' => 'danger', 'text' => 'Selected crew member is not active.'];
            }
            $conflictRef = DB::table('booking_crew as bc')
                ->join('bookings as b', 'bc.booking_id', '=', 'b.booking_id')
                ->where('bc.crew_id', $assignCid)->where('bc.booking_id', '!=', $req->booking_id)
                ->whereNotIn('b.booking_status', ['cancelled', 'completed'])
                ->where('b.shoot_date_start', '<=', $booking->shoot_date_end)
                ->where('b.shoot_date_end', '>=', $booking->shoot_date_start)
                ->value('b.booking_reference');
            if ($conflictRef) {
                return ['type' => 'danger', 'text' => 'This crew member is already booked on ' . e($conflictRef) . ' during this period.'];
            }
            $unavail = DB::table('crew_unavailability')->where('crew_id', $assignCid)
                ->where('date_from', '<=', $booking->shoot_date_end)->where('date_to', '>=', $booking->shoot_date_start)
                ->exists();
            if ($unavail) {
                return ['type' => 'danger', 'text' => 'This crew member has marked themselves unavailable during this period.'];
            }

            $numDaysForCrew = max(1, (new \DateTime($booking->shoot_date_start))->diff(new \DateTime($booking->shoot_date_end))->days + 1);
            $rate = (float) $assignee->base_rate_12hr;
            if (! DB::table('booking_crew')->where('booking_id', $req->booking_id)->where('crew_id', $assignCid)->exists()) {
                DB::table('booking_crew')->insert([
                    'booking_id' => $req->booking_id, 'crew_id' => $assignCid, 'position_id' => $req->position_id,
                    'rate_used' => $rate, 'hours_worked' => $numDaysForCrew, 'subtotal' => $rate * $numDaysForCrew,
                    'notes' => 'Field request: ' . ($req->reason ?? ''),
                ]);
            }
            DB::table('booking_equipment_requests')->where('request_id', $reqId)->update(['crew_id' => $assignCid]);
            $req->crew_id = $assignCid;
            BookingCosting::generateCostEstimate($req->booking_id, $uid);
            $costChanged = true;
            $assigneeName = trim($assignee->first_name . ' ' . $assignee->last_name);
            ActivityLog::record($uid, 'assign', 'booking', "Field request #$reqId: assigned crew $assigneeName to booking {$req->booking_id}", $req->booking_id);
        }

        if ($req->item_type === 'equipment' && $req->equipment_id) {
            $alreadyOnThisBooking = DB::table('booking_equipment')->where('booking_id', $req->booking_id)->where('equipment_id', $req->equipment_id)->exists();
            if (! $alreadyOnThisBooking) {
                $availError = \App\Support\EquipmentAvailability::check($req->equipment_id, (int) $req->quantity, $req->booking_id, $booking);
                if ($availError) {
                    return ['type' => 'danger', 'text' => $availError['text'] . ' Reject this request or resolve the conflict before dispatching.'];
                }
            }
        }

        $claimed = DB::table('booking_equipment_requests')->where('request_id', $reqId)->where('status', 'approved')->update([
            'status' => 'dispatched', 'vehicle_rate_id' => $vehicleId ?: null, 'driver_crew_id' => $driverCid, 'eta' => $eta,
        ]);
        if (! $claimed) {
            return ['type' => 'danger', 'text' => 'This request is no longer awaiting dispatch.'];
        }

        $numDays = max(1, (new \DateTime($booking->shoot_date_start))->diff(new \DateTime($booking->shoot_date_end))->days + 1);

        if ($req->item_type === 'equipment' && $req->equipment_id) {
            $existing = DB::table('booking_equipment')->where('booking_id', $req->booking_id)->where('equipment_id', $req->equipment_id)->first();
            if ($existing) {
                $addQty = (int) $req->quantity;
                DB::table('booking_equipment')->where('bk_equip_id', $existing->bk_equip_id)->update(['quantity' => DB::raw("quantity + $addQty")]);
                \App\Support\EquipmentUnitAllocator::allocate($existing->bk_equip_id, $req->equipment_id, $addQty);
            } else {
                $bkEquipId = DB::table('booking_equipment')->insertGetId([
                    'booking_id' => $req->booking_id, 'equipment_id' => $req->equipment_id, 'quantity' => $req->quantity,
                    'days' => $numDays, 'daily_rate' => $req->daily_rate,
                ], 'bk_equip_id');
                \App\Support\EquipmentUnitAllocator::allocate($bkEquipId, $req->equipment_id, (int) $req->quantity);
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
            $costChanged = true;
        } elseif ($req->item_type === 'accessory' && $req->accessory_id) {
            $subtotal = (float) $req->quantity * $numDays * (float) $req->daily_rate;
            $existing = DB::table('booking_accessories')->where('booking_id', $req->booking_id)->where('accessory_id', $req->accessory_id)->first();
            if ($existing) {
                $addQty = (int) $req->quantity;
                $addSubtotal = (float) $subtotal;
                DB::table('booking_accessories')->where('ba_id', $existing->ba_id)->update([
                    'quantity' => DB::raw("quantity + $addQty"),
                    'subtotal' => DB::raw("subtotal + $addSubtotal"),
                ]);
                \App\Support\EquipmentUnitAllocator::allocateAccessory($existing->ba_id, $req->accessory_id, $addQty);
            } else {
                $baId = DB::table('booking_accessories')->insertGetId([
                    'booking_id' => $req->booking_id, 'accessory_id' => $req->accessory_id, 'quantity' => $req->quantity,
                    'days' => $numDays, 'daily_rate' => $req->daily_rate, 'is_included' => 0, 'subtotal' => $subtotal,
                    'notes' => 'Field request dispatch',
                ], 'ba_id');
                \App\Support\EquipmentUnitAllocator::allocateAccessory($baId, $req->accessory_id, (int) $req->quantity);
            }
            DB::table('equipment_checklist')->insertOrIgnore([
                'booking_id' => $req->booking_id, 'accessory_id' => $req->accessory_id, 'direction' => 'out',
                'quantity_expected' => $req->quantity, 'quantity_actual' => $req->quantity, 'condition_out' => 'good',
                'checked' => 1, 'checked_by' => $uid, 'checked_at' => now(),
            ]);
            BookingCosting::generateCostEstimate($req->booking_id, $uid);
            $costChanged = true;
        }

        if ($costChanged && ($booking->cost_approval_status ?? null) === 'client_approved') {
            DB::table('bookings')->where('booking_id', $req->booking_id)->update(['cost_approval_status' => 'pending_client', 'updated_at' => now()]);
        }

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
