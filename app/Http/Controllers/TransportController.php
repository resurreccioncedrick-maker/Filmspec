<?php

namespace App\Http\Controllers;

use App\Support\DataExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransportController extends Controller
{
    private array $rateBasisLabel = ['per_trip' => 'Per Trip', 'per_day' => 'Per Day', 'round_trip' => 'Round Trip'];

    private array $fleetStatusLabel = [
        'available' => 'Available', 'assigned' => 'Assigned / Dispatched',
        'maintenance' => 'Maintenance', 'out_of_service' => 'Out of Service',
    ];

    private array $fleetStatusBadge = [
        'available' => 'badge-green', 'assigned' => 'badge-blue',
        'maintenance' => 'badge-orange', 'out_of_service' => 'badge-red',
    ];

    public function index(Request $request): View|StreamedResponse|Response
    {
        $role = $request->user()->role->role_name ?? '';
        $canManage = in_array($role, ['super_admin', 'admin', 'operations_manager'], true);

        $msg = null;
        if ($request->isMethod('post') && $canManage) {
            $msg = $this->handleAction($request);
        }

        if ($request->filled('export')) {
            return $this->export($request);
        }

        $tab = in_array($request->query('tab'), ['rates', 'fleet', 'assignments'], true) ? $request->query('tab') : 'rates';

        $vehicleRates = DB::table('vehicle_rates')->orderBy('base_rate')->get();

        $fleet = DB::table('fleet_vehicles as fv')
            ->join('vehicle_rates as vr', 'fv.vehicle_type_id', '=', 'vr.vehicle_id')
            ->orderBy('fv.plate_no')
            ->select('fv.*', 'vr.label as type_label')
            ->get();

        // "Current Assignment" for the Fleet table = this vehicle's most recent active
        // transport_assignments row (today or in the future), same "what's it doing right now"
        // concept the mockup's Fleet table shows per vehicle.
        foreach ($fleet as $v) {
            $active = DB::table('transport_assignments as ta')
                ->join('bookings as b', 'ta.booking_id', '=', 'b.booking_id')
                ->leftJoin('crew_members as cm', 'ta.driver_crew_id', '=', 'cm.crew_id')
                ->where('ta.fleet_vehicle_id', $v->fleet_vehicle_id)
                ->where(function ($w) {
                    $w->whereNull('ta.dispatch_date')->orWhere('ta.dispatch_date', '>=', now()->subDay()->toDateString());
                })
                ->orderByDesc('ta.dispatch_date')
                ->select('b.booking_reference', DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS driver_name"))
                ->first();
            $v->current_booking_ref = $active->booking_reference ?? null;
            $v->driver_name = $active && trim((string) $active->driver_name) !== '' ? trim($active->driver_name) : null;
        }

        $assignments = DB::table('transport_assignments as ta')
            ->join('bookings as b', 'ta.booking_id', '=', 'b.booking_id')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->join('fleet_vehicles as fv', 'ta.fleet_vehicle_id', '=', 'fv.fleet_vehicle_id')
            ->join('vehicle_rates as vr', 'fv.vehicle_type_id', '=', 'vr.vehicle_id')
            ->leftJoin('crew_members as cm', 'ta.driver_crew_id', '=', 'cm.crew_id')
            ->orderByDesc('ta.dispatch_date')
            ->select(
                'ta.*', 'b.booking_reference', 'b.project_title', 'c.company_name', 'c.contact_person',
                'fv.plate_no', 'vr.label as vehicle_label', DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS driver_name")
            )
            ->limit(100)
            ->get();

        $bookingsNeedingTransport = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->whereIn('b.booking_status', ['confirmed', 'ongoing'])
            ->whereNotIn('b.booking_id', function ($q) {
                $q->select('booking_id')->from('transport_assignments');
            })
            ->orderBy('b.shoot_date_start')
            ->select('b.booking_id', 'b.booking_reference', 'b.project_title', 'b.shoot_date_start', 'c.company_name', 'c.contact_person')
            ->limit(50)
            ->get();

        $activeDrivers = DB::table('crew_members as cm')
            ->leftJoin('crew_positions as cp', 'cm.primary_position_id', '=', 'cp.position_id')
            ->where('cm.status', 'active')
            ->select('cm.crew_id', DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS name"), 'cp.position_name')
            ->orderByRaw("(LOWER(cp.position_name) LIKE '%driver%') DESC")
            ->orderBy('cm.last_name')
            ->get();

        $stats = [
            'vehicle_types' => (int) DB::table('vehicle_rates')->where('is_active', 1)->count(),
            'fleet_total' => $fleet->count(),
            'available' => $fleet->where('status', 'available')->count(),
            'assigned' => $fleet->where('status', 'assigned')->count(),
            'maintenance' => $fleet->where('status', 'maintenance')->count(),
            'out_of_service' => $fleet->where('status', 'out_of_service')->count(),
        ];

        return view('transport', [
            'msg' => $msg, 'canManage' => $canManage, 'vehicleRates' => $vehicleRates, 'tab' => $tab,
            'fleet' => $fleet, 'assignments' => $assignments, 'bookingsNeedingTransport' => $bookingsNeedingTransport,
            'activeDrivers' => $activeDrivers, 'stats' => $stats,
            'rateBasisLabel' => $this->rateBasisLabel, 'fleetStatusLabel' => $this->fleetStatusLabel, 'fleetStatusBadge' => $this->fleetStatusBadge,
        ]);
    }

    private function export(Request $request): StreamedResponse|Response
    {
        $headers = ['Label', 'Type Key', 'Description', 'Base Rate', 'Status'];

        $rows = DB::table('vehicle_rates')->orderBy('base_rate')->get()
            ->map(fn ($v) => [
                $v->label,
                $v->vehicle_type,
                $v->description ?: '—',
                '₱' . number_format((float) $v->base_rate, 2),
                $v->is_active ? 'Active' : 'Inactive',
            ])
            ->all();

        return DataExporter::respond($request->query('export'), 'Transport', $headers, $rows, 'transport-export');
    }

    private function handleAction(Request $request): ?array
    {
        $action = $request->input('action', '');

        if ($action === 'add_vehicle_rate') {
            $label = trim($request->input('label', ''));
            $vtype = trim($request->input('vehicle_type', ''));
            $desc = trim($request->input('description', ''));
            $rate = max(0, (float) $request->input('base_rate', 0));
            $basis = in_array($request->input('rate_basis'), array_keys($this->rateBasisLabel), true) ? $request->input('rate_basis') : 'per_trip';

            if ($label && $vtype && $rate > 0) {
                DB::table('vehicle_rates')->insert([
                    'vehicle_type' => $vtype, 'label' => $label, 'description' => $desc, 'base_rate' => $rate, 'rate_basis' => $basis,
                ]);

                return ['type' => 'success', 'text' => 'Vehicle type added.'];
            }

            return ['type' => 'danger', 'text' => 'Label, type key, and base rate are required.'];
        }

        if ($action === 'edit_vehicle_rate') {
            $vid = (int) $request->input('vehicle_id', 0);
            $label = trim($request->input('label', ''));
            $vtype = trim($request->input('vehicle_type', ''));
            $desc = trim($request->input('description', ''));
            $rate = max(0, (float) $request->input('base_rate', 0));
            $basis = in_array($request->input('rate_basis'), array_keys($this->rateBasisLabel), true) ? $request->input('rate_basis') : 'per_trip';

            if ($vid && $label && $vtype && $rate > 0) {
                DB::table('vehicle_rates')->where('vehicle_id', $vid)->update([
                    'vehicle_type' => $vtype, 'label' => $label, 'description' => $desc, 'base_rate' => $rate, 'rate_basis' => $basis,
                ]);

                return ['type' => 'success', 'text' => 'Vehicle type updated.'];
            }

            return null;
        }

        if ($action === 'add_fleet_vehicle') {
            $vtid = (int) $request->input('vehicle_type_id', 0);
            $plate = strtoupper(trim($request->input('plate_no', '')));
            if (! $vtid || ! $plate) {
                return ['type' => 'danger', 'text' => 'Vehicle type and plate number are required.'];
            }
            if (DB::table('fleet_vehicles')->where('plate_no', $plate)->exists()) {
                return ['type' => 'danger', 'text' => 'A fleet vehicle with this plate number already exists.'];
            }

            DB::table('fleet_vehicles')->insert([
                'vehicle_type_id' => $vtid, 'plate_no' => $plate,
                'brand' => trim($request->input('brand', '')) ?: null, 'model' => trim($request->input('model', '')) ?: null,
                'year' => $request->input('year') ?: null, 'color' => trim($request->input('color', '')) ?: null,
                'status' => in_array($request->input('status'), array_keys($this->fleetStatusLabel), true) ? $request->input('status') : 'available',
                'notes' => trim($request->input('notes', '')) ?: null,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return ['type' => 'success', 'text' => "Vehicle <strong>$plate</strong> added to the fleet."];
        }

        if ($action === 'edit_fleet_vehicle') {
            $fvid = (int) $request->input('fleet_vehicle_id', 0);
            $plate = strtoupper(trim($request->input('plate_no', '')));
            if (! $fvid || ! $plate) {
                return ['type' => 'danger', 'text' => 'Plate number is required.'];
            }
            if (DB::table('fleet_vehicles')->where('plate_no', $plate)->where('fleet_vehicle_id', '!=', $fvid)->exists()) {
                return ['type' => 'danger', 'text' => 'Another fleet vehicle already uses this plate number.'];
            }

            DB::table('fleet_vehicles')->where('fleet_vehicle_id', $fvid)->update([
                'vehicle_type_id' => (int) $request->input('vehicle_type_id'), 'plate_no' => $plate,
                'brand' => trim($request->input('brand', '')) ?: null, 'model' => trim($request->input('model', '')) ?: null,
                'year' => $request->input('year') ?: null, 'color' => trim($request->input('color', '')) ?: null,
                'status' => in_array($request->input('status'), array_keys($this->fleetStatusLabel), true) ? $request->input('status') : 'available',
                'notes' => trim($request->input('notes', '')) ?: null,
                'updated_at' => now(),
            ]);

            return ['type' => 'success', 'text' => 'Vehicle updated.'];
        }

        if ($action === 'create_assignment') {
            $bid = (int) $request->input('booking_id', 0);
            $fvid = (int) $request->input('fleet_vehicle_id', 0);
            if (! $bid || ! $fvid) {
                return ['type' => 'danger', 'text' => 'Booking and vehicle are required.'];
            }
            $vehicle = DB::table('fleet_vehicles')->where('fleet_vehicle_id', $fvid)->first();
            if (! $vehicle) {
                return ['type' => 'danger', 'text' => 'Vehicle not found.'];
            }
            if ($vehicle->status === 'out_of_service' || $vehicle->status === 'maintenance') {
                return ['type' => 'danger', 'text' => 'This vehicle is not currently available for assignment.'];
            }

            DB::table('transport_assignments')->insert([
                'booking_id' => $bid, 'fleet_vehicle_id' => $fvid,
                'driver_crew_id' => (int) $request->input('driver_crew_id', 0) ?: null,
                'dispatch_date' => $request->input('dispatch_date') ?: null,
                'departure_time' => $request->input('departure_time') ?: null,
                'destination' => trim($request->input('destination', '')) ?: null,
                'notes' => trim($request->input('notes', '')) ?: null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('fleet_vehicles')->where('fleet_vehicle_id', $fvid)->update(['status' => 'assigned', 'updated_at' => now()]);

            return ['type' => 'success', 'text' => 'Transport assignment created.'];
        }

        if ($action === 'complete_assignment') {
            $aid = (int) $request->input('assignment_id', 0);
            $assignment = DB::table('transport_assignments')->where('assignment_id', $aid)->first();
            if (! $assignment) {
                return ['type' => 'danger', 'text' => 'Assignment not found.'];
            }
            DB::table('fleet_vehicles')->where('fleet_vehicle_id', $assignment->fleet_vehicle_id)->update(['status' => 'available', 'updated_at' => now()]);
            DB::table('transport_assignments')->where('assignment_id', $aid)->delete();

            return ['type' => 'success', 'text' => 'Assignment closed — vehicle marked available again.'];
        }

        if ($action === 'toggle_vehicle_rate') {
            $vid = (int) $request->input('vehicle_id', 0);
            $stat = (int) $request->input('is_active');
            DB::table('vehicle_rates')->where('vehicle_id', $vid)->update(['is_active' => $stat]);

            return ['type' => 'success', 'text' => 'Vehicle status updated.'];
        }

        if ($action === 'delete_vehicle_rate') {
            $vid = (int) $request->input('vehicle_id', 0);
            $inUse = (int) DB::table('bookings')->where('vehicle_rate_id', $vid)->count();
            if ($inUse > 0) {
                return ['type' => 'danger', 'text' => "Cannot delete: vehicle type is used in $inUse booking(s)."];
            }
            DB::table('vehicle_rates')->where('vehicle_id', $vid)->delete();

            return ['type' => 'success', 'text' => 'Vehicle type deleted.'];
        }

        return null;
    }
}
