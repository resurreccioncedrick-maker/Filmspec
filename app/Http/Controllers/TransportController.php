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

        $vehicleRates = DB::table('vehicle_rates')->orderBy('base_rate')->get();

        return view('transport', [
            'msg' => $msg, 'canManage' => $canManage, 'vehicleRates' => $vehicleRates,
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

            if ($label && $vtype && $rate > 0) {
                DB::table('vehicle_rates')->insert([
                    'vehicle_type' => $vtype, 'label' => $label, 'description' => $desc, 'base_rate' => $rate,
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

            if ($vid && $label && $vtype && $rate > 0) {
                DB::table('vehicle_rates')->where('vehicle_id', $vid)->update([
                    'vehicle_type' => $vtype, 'label' => $label, 'description' => $desc, 'base_rate' => $rate,
                ]);

                return ['type' => 'success', 'text' => 'Vehicle type updated.'];
            }

            return null;
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
