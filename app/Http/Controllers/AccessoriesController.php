<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\DataExporter;
use App\Support\ImageUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccessoriesController extends Controller
{
    private array $typeLabel = [
        'package_inclusion' => 'Package Inclusion', 'optional_addon' => 'Optional Add-On',
        'internal_operational' => 'Internal / Operational',
    ];

    private array $unitCondLabel = [
        'excellent' => 'Excellent', 'good' => 'Good', 'serviceable' => 'Serviceable', 'damaged' => 'Damaged',
    ];

    private array $unitStatusLabel = [
        'available' => 'Available', 'allocated' => 'Allocated', 'in_field' => 'In Field',
        'inspection_pending' => 'Inspection Pending', 'under_maintenance' => 'Under Maintenance', 'retired' => 'Retired',
    ];

    public function index(Request $request): View|JsonResponse|StreamedResponse|Response
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';
        $canManage = in_array($role, config('filmspec.manage_roles'), true);

        if ($request->has('get_equipment_accessories')) {
            $eid = (int) $request->query('get_equipment_accessories');
            $rows = DB::table('accessories as a')
                ->join('equipment_accessory_links as eal', 'eal.accessory_id', '=', 'a.accessory_id')
                ->where('eal.equipment_id', $eid)
                ->orderBy('a.accessory_name')
                ->select('a.*')
                ->get();

            return response()->json($rows);
        }

        if ($request->has('get_linked_ids')) {
            $aid = (int) $request->query('get_linked_ids');
            $ids = DB::table('equipment_accessory_links')->where('accessory_id', $aid)->pluck('equipment_id');

            return response()->json($ids);
        }

        if ($request->has('get_links_detail')) {
            $aid = (int) $request->query('get_links_detail');
            $rows = DB::table('equipment_accessory_links')->where('accessory_id', $aid)
                ->select('equipment_id', 'included_qty')->get();

            return response()->json($rows);
        }

        if ($request->has('get_units')) {
            $aid = (int) $request->query('get_units');
            $rows = DB::table('accessory_units')->where('accessory_id', $aid)->orderBy('asset_tag')->get();

            return response()->json($rows);
        }

        if ($request->isMethod('post') && $canManage && $request->filled('ajax_action')) {
            return $this->handleAjaxAction($request);
        }

        if ($request->filled('export')) {
            return $this->export($request);
        }

        $search = $request->query('q', '');
        $inclFilter = $request->query('incl', '');

        $accessories = $this->filteredQuery($request)->orderBy('a.accessory_name')->select('a.*')->get();

        foreach ($accessories as $acc) {
            $linked = DB::table('equipment_accessory_links as eal')
                ->join('equipment as e', 'e.equipment_id', '=', 'eal.equipment_id')
                ->where('eal.accessory_id', $acc->accessory_id)
                ->where('e.availability_status', '!=', 'retired')
                ->orderBy('e.equipment_name')
                ->pluck('e.equipment_name');
            $acc->equipment_count = $linked->count();
            $acc->linked_equipment = $linked->implode(', ');

            $inUse = (int) DB::table('booking_accessories as ba')
                ->join('bookings as b', 'ba.booking_id', '=', 'b.booking_id')
                ->where('ba.accessory_id', $acc->accessory_id)
                ->whereNotIn('b.booking_status', ['cancelled', 'completed'])
                ->sum('ba.quantity');
            $acc->in_use = $inUse;
            $acc->available = max(0, (int) ($acc->quantity ?? 1) - $inUse);
            $acc->unit_count = (int) DB::table('accessory_units')->where('accessory_id', $acc->accessory_id)->where('status', '!=', 'retired')->count();
        }

        $allEquipment = DB::table('equipment as e')
            ->join('equipment_categories as ec', 'e.category_id', '=', 'ec.category_id')
            ->where('e.availability_status', '!=', 'retired')
            ->orderBy('ec.category_name')->orderBy('e.equipment_name')
            ->select('e.equipment_id', 'e.equipment_name', 'ec.category_name')
            ->get();

        $stats = [
            'total' => (int) DB::table('accessories')->count(),
            'included' => (int) DB::table('accessories')->where('is_included', 1)->count(),
            'addon' => (int) DB::table('accessories')->where('is_included', 0)->count(),
        ];

        return view('accessories', [
            'msg' => null, 'canManage' => $canManage,
            'accessories' => $accessories, 'allEquipment' => $allEquipment, 'stats' => $stats,
            'search' => $search, 'inclFilter' => $inclFilter,
            'typeLabel' => $this->typeLabel, 'unitCondLabel' => $this->unitCondLabel, 'unitStatusLabel' => $this->unitStatusLabel,
        ]);
    }

    /** Same filters index() applies, shared with export() so the two can never drift apart. */
    private function filteredQuery(Request $request)
    {
        $search = $request->query('q', '');
        $inclFilter = $request->query('incl', '');

        $query = DB::table('accessories as a');
        if ($search) {
            $query->where(function ($w) use ($search) {
                $w->where('a.accessory_name', 'like', "%$search%")
                    ->orWhere('a.description', 'like', "%$search%");
            });
        }
        if ($inclFilter !== '') {
            $query->where('a.is_included', (int) $inclFilter);
        }

        return $query;
    }

    /**
     * Export ▾ — this page renders as a card grid, but export flattens it to the same tabular
     * shape as every other page (there's no sane way to export a card layout as CSV/Excel).
     */
    private function export(Request $request): StreamedResponse|Response
    {
        $headers = ['Accessory', 'Category', 'Rate/Day', 'Stock', 'In Use', 'Available', 'Linked Equipment'];

        $rows = $this->filteredQuery($request)->orderBy('a.accessory_name')->select('a.*')->get()
            ->map(function ($acc) {
                $linked = DB::table('equipment_accessory_links as eal')
                    ->join('equipment as e', 'e.equipment_id', '=', 'eal.equipment_id')
                    ->where('eal.accessory_id', $acc->accessory_id)
                    ->where('e.availability_status', '!=', 'retired')
                    ->orderBy('e.equipment_name')
                    ->pluck('e.equipment_name');
                $inUse = (int) DB::table('booking_accessories as ba')
                    ->join('bookings as b', 'ba.booking_id', '=', 'b.booking_id')
                    ->where('ba.accessory_id', $acc->accessory_id)
                    ->whereNotIn('b.booking_status', ['cancelled', 'completed'])
                    ->sum('ba.quantity');
                $stock = (int) ($acc->quantity ?? 1);

                return [
                    $acc->accessory_name,
                    $acc->is_included ? 'Included' : 'Add-on',
                    '₱' . number_format((float) $acc->daily_rate, 2),
                    $stock,
                    $inUse,
                    max(0, $stock - $inUse),
                    $linked->implode(', ') ?: '—',
                ];
            })
            ->all();

        return DataExporter::respond($request->query('export'), 'Accessories', $headers, $rows, 'accessories-export');
    }

    private function handleAjaxAction(Request $request): JsonResponse
    {
        $action = $request->input('ajax_action');

        if ($action === 'add_accessory') {
            $name = trim($request->input('accessory_name', ''));
            $desc = trim($request->input('description', ''));
            $type = in_array($request->input('accessory_type'), array_keys($this->typeLabel), true) ? $request->input('accessory_type') : 'optional_addon';
            $tracking = $request->input('tracking_method') === 'individual' ? 'individual' : 'quantity';
            // Package Inclusion never carries its own charge; is_included stays in sync with
            // accessory_type so every existing is_included read elsewhere keeps working.
            $incl = $type === 'package_inclusion' ? 1 : 0;
            $rate = $incl ? 0.0 : (float) $request->input('daily_rate', 0);
            $qty = max(1, (int) $request->input('quantity', 1));

            if (! $name) {
                return response()->json(['success' => false, 'error' => 'Accessory name required']);
            }

            $imgPath = '';
            if ($request->hasFile('accessory_image')) {
                $up = ImageUpload::handle($request->file('accessory_image'), 'accessories');
                if ($up['success']) $imgPath = $up['path'];
            }

            $newId = DB::table('accessories')->insertGetId([
                'accessory_name' => $name, 'description' => $desc, 'daily_rate' => $rate,
                'is_included' => $incl, 'accessory_type' => $type, 'tracking_method' => $tracking,
                // Individually-tracked accessories don't ask for a stock number up front — units
                // are added afterward via accessory_units, so quantity stays a nominal 1 here
                // rather than double-counting against the real per-unit count.
                'quantity' => $tracking === 'individual' ? 1 : $qty, 'image_path' => $imgPath,
            ]);
            foreach ((array) $request->input('equipment_ids', []) as $eid) {
                $eid = (int) $eid;
                if ($eid) {
                    $incQty = (int) $request->input("included_qty.$eid", 0) ?: null;
                    DB::table('equipment_accessory_links')->insertOrIgnore(['equipment_id' => $eid, 'accessory_id' => $newId, 'included_qty' => $incQty]);
                }
            }

            return response()->json([
                'success' => true, 'accessory_id' => $newId, 'accessory_name' => $name, 'description' => $desc,
                'daily_rate' => $rate, 'is_included' => $incl, 'accessory_type' => $type, 'tracking_method' => $tracking,
                'quantity' => $qty, 'image_path' => $imgPath,
            ]);
        }

        if ($action === 'edit_accessory') {
            $aid = (int) $request->input('accessory_id');
            $name = trim($request->input('accessory_name', ''));
            $desc = trim($request->input('description', ''));
            $type = in_array($request->input('accessory_type'), array_keys($this->typeLabel), true) ? $request->input('accessory_type') : 'optional_addon';
            $tracking = $request->input('tracking_method') === 'individual' ? 'individual' : 'quantity';
            $incl = $type === 'package_inclusion' ? 1 : 0;
            $rate = $incl ? 0.0 : (float) $request->input('daily_rate', 0);
            $qty = max(1, (int) $request->input('quantity', 1));

            if (! $aid || ! $name) {
                return response()->json(['success' => false, 'error' => 'Missing data']);
            }

            $row = DB::table('accessories')->where('accessory_id', $aid)->first();
            $imgPath = $row->image_path ?? '';
            if ($request->hasFile('accessory_image')) {
                $up = ImageUpload::handle($request->file('accessory_image'), 'accessories');
                if ($up['success']) {
                    if ($imgPath) ImageUpload::deleteOld($imgPath);
                    $imgPath = $up['path'];
                }
            }

            DB::table('accessories')->where('accessory_id', $aid)->update([
                'accessory_name' => $name, 'description' => $desc, 'daily_rate' => $rate,
                'is_included' => $incl, 'accessory_type' => $type, 'tracking_method' => $tracking,
                'quantity' => $tracking === 'individual' ? $row->quantity : $qty, 'image_path' => $imgPath,
            ]);
            DB::table('equipment_accessory_links')->where('accessory_id', $aid)->delete();
            foreach ((array) $request->input('equipment_ids', []) as $eid) {
                $eid = (int) $eid;
                if ($eid) {
                    $incQty = (int) $request->input("included_qty.$eid", 0) ?: null;
                    DB::table('equipment_accessory_links')->insertOrIgnore(['equipment_id' => $eid, 'accessory_id' => $aid, 'included_qty' => $incQty]);
                }
            }

            return response()->json([
                'success' => true, 'image_path' => $imgPath, 'accessory_name' => $name,
                'description' => $desc, 'daily_rate' => $rate, 'is_included' => $incl,
                'accessory_type' => $type, 'tracking_method' => $tracking,
            ]);
        }

        if ($action === 'delete_accessory') {
            $aid = (int) $request->input('accessory_id');
            $row = DB::table('accessories')->where('accessory_id', $aid)->first();
            if ($row && $row->image_path) {
                ImageUpload::deleteOld($row->image_path);
            }
            DB::table('accessories')->where('accessory_id', $aid)->delete();
            ActivityLog::record($request->user()->user_id, 'delete', 'accessory', 'Deleted accessory: ' . ($row->accessory_name ?? "ID $aid"), $aid);

            return response()->json(['success' => true]);
        }

        if ($action === 'save_links') {
            $aid = (int) $request->input('accessory_id');
            $eids = array_filter(array_map('intval', (array) $request->input('equipment_ids', [])));
            DB::table('equipment_accessory_links')->where('accessory_id', $aid)->delete();
            foreach ($eids as $eid) {
                DB::table('equipment_accessory_links')->insertOrIgnore(['equipment_id' => $eid, 'accessory_id' => $aid]);
            }

            return response()->json(['success' => true, 'count' => count($eids)]);
        }

        if ($action === 'add_unit') {
            $aid = (int) $request->input('accessory_id');
            $tag = trim($request->input('asset_tag', ''));
            if (! $aid || ! $tag) {
                return response()->json(['success' => false, 'error' => 'Asset tag is required.']);
            }
            if (DB::table('accessory_units')->where('asset_tag', $tag)->exists()) {
                return response()->json(['success' => false, 'error' => 'This asset tag is already in use.']);
            }

            $unitId = DB::table('accessory_units')->insertGetId([
                'accessory_id' => $aid, 'asset_tag' => $tag,
                'serial_no' => trim($request->input('serial_no', '')) ?: null,
                'condition' => in_array($request->input('condition'), array_keys($this->unitCondLabel), true) ? $request->input('condition') : 'good',
                'status' => in_array($request->input('status'), array_keys($this->unitStatusLabel), true) ? $request->input('status') : 'available',
                'location' => trim($request->input('location', '')) ?: null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            ActivityLog::record($request->user()->user_id, 'create', 'accessory', "Added physical unit $tag", $aid);

            return response()->json(['success' => true, 'unit_id' => $unitId]);
        }

        if ($action === 'update_unit') {
            $unitId = (int) $request->input('unit_id');
            $unit = DB::table('accessory_units')->where('unit_id', $unitId)->first();
            if (! $unit) {
                return response()->json(['success' => false, 'error' => 'Unit not found.']);
            }

            DB::table('accessory_units')->where('unit_id', $unitId)->update([
                'condition' => in_array($request->input('condition'), array_keys($this->unitCondLabel), true) ? $request->input('condition') : $unit->condition,
                'status' => in_array($request->input('status'), array_keys($this->unitStatusLabel), true) ? $request->input('status') : $unit->status,
                'location' => trim($request->input('location', '')) ?: null,
                'updated_at' => now(),
            ]);
            ActivityLog::record($request->user()->user_id, 'update', 'accessory', "Updated physical unit {$unit->asset_tag}", $unit->accessory_id);

            return response()->json(['success' => true]);
        }

        if ($action === 'retire_unit') {
            $unitId = (int) $request->input('unit_id');
            $unit = DB::table('accessory_units')->where('unit_id', $unitId)->first();
            if (! $unit) {
                return response()->json(['success' => false, 'error' => 'Unit not found.']);
            }
            DB::table('accessory_units')->where('unit_id', $unitId)->update(['status' => 'retired', 'updated_at' => now()]);
            ActivityLog::record($request->user()->user_id, 'update', 'accessory', "Retired physical unit {$unit->asset_tag}", $unit->accessory_id);

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'error' => 'Unknown action']);
    }
}
