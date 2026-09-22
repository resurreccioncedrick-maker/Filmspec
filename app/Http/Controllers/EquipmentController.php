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

class EquipmentController extends Controller
{
    // Display-only relabel — the stored enum values (excellent/good/fair/under_repair,
    // available/booked/rented/under_repair/retired) are unchanged everywhere they're compared
    // or filtered on (75+ call sites across the app), only the human-facing label/badge text
    // changes: condition Fair→Serviceable, under_repair(as condition)→Damaged; availability
    // booked→Allocated, rented→"In Field", under_repair(as status)→"Under Maintenance".
    private array $condBadge = [
        'excellent' => 'badge-green', 'good' => 'badge-blue', 'fair' => 'badge-yellow', 'under_repair' => 'badge-red',
    ];

    private array $condLabel = [
        'excellent' => 'Excellent', 'good' => 'Good', 'fair' => 'Serviceable', 'under_repair' => 'Damaged',
    ];

    private array $availBadge = [
        'available' => 'badge-green', 'booked' => 'badge-red', 'rented' => 'badge-yellow',
        'under_repair' => 'badge-red', 'retired' => 'badge-gray',
    ];

    private array $availLabel = [
        'available' => 'Available', 'booked' => 'Allocated', 'rented' => 'In Field',
        'under_repair' => 'Under Maintenance', 'retired' => 'Retired',
    ];

    // Physical Units (equipment_units) is a brand-new per-unit table, so it uses the new
    // vocabulary directly in its own stored values — no legacy values to stay compatible with.
    private array $unitCondLabel = [
        'excellent' => 'Excellent', 'good' => 'Good', 'serviceable' => 'Serviceable', 'damaged' => 'Damaged',
    ];

    private array $unitCondBadge = [
        'excellent' => 'badge-green', 'good' => 'badge-blue', 'serviceable' => 'badge-yellow', 'damaged' => 'badge-red',
    ];

    private array $unitStatusLabel = [
        'available' => 'Available', 'allocated' => 'Allocated', 'in_field' => 'In Field',
        'inspection_pending' => 'Inspection Pending', 'under_maintenance' => 'Under Maintenance', 'retired' => 'Retired',
    ];

    private array $unitStatusBadge = [
        'available' => 'badge-green', 'allocated' => 'badge-blue', 'in_field' => 'badge-yellow',
        'inspection_pending' => 'badge-purple', 'under_maintenance' => 'badge-red', 'retired' => 'badge-gray',
    ];

    public function index(Request $request): View|JsonResponse|StreamedResponse|Response
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';
        $canManage = in_array($role, config('filmspec.manage_roles'), true);

        if ($request->has('get_operators')) {
            $eid = (int) $request->query('get_operators');
            $ids = DB::table('equipment_operators')->where('equipment_id', $eid)->pluck('position_id');

            return response()->json($ids);
        }

        if ($request->has('get_accessories')) {
            $eid = (int) $request->query('get_accessories');
            $rows = DB::table('accessories as a')
                ->join('equipment_accessory_links as eal', 'eal.accessory_id', '=', 'a.accessory_id')
                ->where('eal.equipment_id', $eid)
                ->orderBy('a.accessory_name')
                ->select('a.*')
                ->get();

            return response()->json($rows);
        }

        if ($request->has('get_units')) {
            $eid = (int) $request->query('get_units');
            $rows = DB::table('equipment_units')->where('equipment_id', $eid)->orderBy('asset_tag')->get();

            return response()->json($rows);
        }

        if ($request->isMethod('post') && $canManage && $request->filled('ajax_action')) {
            return $this->handleAjaxAction($request);
        }

        $msg = null;
        if ($request->isMethod('post') && $canManage) {
            $msg = $this->handleAction($request, $user, $role);
        }

        if ($request->filled('export')) {
            return $this->export($request);
        }

        $positions = DB::table('crew_positions')->orderBy('position_name')->get();

        $catFilter = (int) $request->query('cat', 0);
        $statusFilter = $request->query('status', '');
        $viewMode = $request->query('view', 'table');
        $search = $request->query('q', '');
        $page = max(1, (int) $request->query('p', 1));
        $perPage = $viewMode === 'grid' ? 18 : 20;

        $query = $this->filteredQuery($request);

        $total = (clone $query)->count('e.equipment_id');
        $pages = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $equipment = (clone $query)
            ->select('e.*', 'ec.category_name')
            ->selectRaw('(SELECT COUNT(*) FROM equipment_operators eo WHERE eo.equipment_id = e.equipment_id) AS operator_count')
            ->selectRaw("(SELECT COUNT(*) FROM equipment_units eu WHERE eu.equipment_id = e.equipment_id AND eu.status != 'retired') AS unit_count")
            ->selectRaw("(SELECT b.booking_reference
                FROM booking_equipment be
                JOIN bookings b ON be.booking_id = b.booking_id
                WHERE be.equipment_id = e.equipment_id
                  AND b.booking_status IN ('confirmed','pending')
                  AND b.shoot_date_end >= CURDATE()
                  AND (SELECT COUNT(*) FROM equipment_transactions et
                       WHERE et.booking_id = b.booking_id AND et.equipment_id = e.equipment_id
                       AND et.transaction_type = 'checkout') = 0
                ORDER BY b.shoot_date_start ASC LIMIT 1) AS allocated_to")
            ->orderBy('ec.category_name')->orderBy('e.equipment_name')
            ->forPage($page, $perPage)
            ->get();

        $categories = DB::table('equipment_categories')->orderBy('category_name')->get();

        $allocatedCount = (int) DB::table('equipment as e')
            ->join('booking_equipment as be', 'be.equipment_id', '=', 'e.equipment_id')
            ->join('bookings as b', 'be.booking_id', '=', 'b.booking_id')
            ->where('e.availability_status', 'available')
            ->whereIn('b.booking_status', ['confirmed', 'pending'])
            ->where('b.shoot_date_end', '>=', DB::raw('CURDATE()'))
            ->whereRaw('(SELECT COUNT(*) FROM equipment_transactions et
                WHERE et.booking_id = b.booking_id AND et.equipment_id = e.equipment_id
                AND et.transaction_type = \'checkout\') = 0')
            ->distinct()
            ->count('e.equipment_id');

        $stats = [
            'total' => (int) DB::table('equipment')->where('availability_status', '!=', 'retired')->count(),
            'available' => (int) DB::table('equipment')->where('availability_status', 'available')->count(),
            'allocated' => $allocatedCount,
            'booked' => (int) DB::table('equipment')->where('availability_status', 'booked')->count(),
            'rented' => (int) DB::table('equipment')->where('availability_status', 'rented')->count(),
            'repair' => (int) DB::table('equipment')->where('availability_status', 'under_repair')->count(),
        ];

        return view('equipment', [
            'msg' => $msg, 'canManage' => $canManage,
            'stats' => $stats, 'categories' => $categories, 'positions' => $positions,
            'equipment' => $equipment, 'total' => $total, 'pages' => $pages, 'page' => $page, 'offset' => $offset,
            'catFilter' => $catFilter, 'statusFilter' => $statusFilter, 'viewMode' => $viewMode, 'search' => $search,
            'condBadge' => $this->condBadge, 'condLabel' => $this->condLabel,
            'availBadge' => $this->availBadge, 'availLabel' => $this->availLabel,
            'unitCondLabel' => $this->unitCondLabel, 'unitCondBadge' => $this->unitCondBadge,
            'unitStatusLabel' => $this->unitStatusLabel, 'unitStatusBadge' => $this->unitStatusBadge,
        ]);
    }

    /** Same filters index() applies, shared with export() so the two can never drift apart. */
    private function filteredQuery(Request $request)
    {
        $catFilter = (int) $request->query('cat', 0);
        $statusFilter = $request->query('status', '');
        $search = $request->query('q', '');

        $query = DB::table('equipment as e')
            ->join('equipment_categories as ec', 'e.category_id', '=', 'ec.category_id')
            ->where('e.availability_status', '!=', 'retired');
        if ($catFilter) $query->where('e.category_id', $catFilter);
        if ($statusFilter) $query->where('e.availability_status', $statusFilter);
        if ($search) {
            $query->where(function ($w) use ($search) {
                $w->where('e.equipment_name', 'like', "%$search%")
                    ->orWhere('e.brand', 'like', "%$search%")
                    ->orWhere('e.model', 'like', "%$search%")
                    ->orWhere('e.serial_number', 'like', "%$search%");
            });
        }

        return $query;
    }

    /** Export ▾ — reuses filteredQuery() unbounded (no page/perPage) so it always matches what's on screen. */
    private function export(Request $request): StreamedResponse|Response
    {
        $headers = ['Equipment', 'Category', 'Brand', 'Model', 'Serial', 'Rate/Day', 'Qty', 'Condition', 'Status'];

        $rows = $this->filteredQuery($request)
            ->select('e.equipment_name', 'ec.category_name', 'e.brand', 'e.model', 'e.serial_number',
                'e.daily_rate', 'e.stock_quantity', 'e.condition_status', 'e.availability_status')
            ->orderBy('ec.category_name')->orderBy('e.equipment_name')
            ->get()
            ->map(fn ($e) => [
                $e->equipment_name,
                $e->category_name,
                $e->brand,
                $e->model,
                $e->serial_number ?: '—',
                '₱' . number_format((float) $e->daily_rate, 2),
                (int) $e->stock_quantity,
                ucfirst(str_replace('_', ' ', $e->condition_status)),
                $this->availLabel[$e->availability_status] ?? ucfirst($e->availability_status),
            ])
            ->all();

        return DataExporter::respond($request->query('export'), 'Equipment', $headers, $rows, 'equipment-export');
    }

    private function handleAjaxAction(Request $request): JsonResponse
    {
        $action = $request->input('ajax_action');

        if ($action === 'add_accessory') {
            $eid = (int) $request->input('equipment_id');
            $name = trim($request->input('accessory_name', ''));
            $desc = trim($request->input('description', ''));
            $rate = (float) $request->input('daily_rate', 0);
            $incl = (int) $request->input('is_included', 1);

            if ($name && $eid) {
                $imgPath = '';
                if ($request->hasFile('accessory_image')) {
                    $up = ImageUpload::handle($request->file('accessory_image'), 'accessories');
                    if ($up['success']) $imgPath = $up['path'];
                }
                $newId = DB::table('accessories')->insertGetId([
                    'accessory_name' => $name, 'description' => $desc, 'daily_rate' => $rate,
                    'is_included' => $incl, 'image_path' => $imgPath,
                ]);
                if ($newId) {
                    DB::table('equipment_accessory_links')->insertOrIgnore(['equipment_id' => $eid, 'accessory_id' => $newId]);
                }

                return response()->json([
                    'success' => true, 'accessory_id' => $newId, 'accessory_name' => $name,
                    'description' => $desc, 'daily_rate' => $rate, 'is_included' => $incl, 'image_path' => $imgPath,
                ]);
            }

            return response()->json(['success' => false, 'error' => 'Accessory name required']);
        }

        if ($action === 'delete_accessory') {
            $aid = (int) $request->input('accessory_id');
            $eid = (int) $request->input('equipment_id', 0);
            if ($eid) {
                DB::table('equipment_accessory_links')->where('accessory_id', $aid)->where('equipment_id', $eid)->delete();
            }

            return response()->json(['success' => true]);
        }

        if ($action === 'add_unit') {
            $eid = (int) $request->input('equipment_id');
            $tag = trim($request->input('asset_tag', ''));
            if (! $eid || ! $tag) {
                return response()->json(['success' => false, 'error' => 'Asset tag is required.']);
            }
            if (DB::table('equipment_units')->where('asset_tag', $tag)->exists()) {
                return response()->json(['success' => false, 'error' => 'This asset tag is already in use.']);
            }

            $unitId = DB::table('equipment_units')->insertGetId([
                'equipment_id' => $eid, 'asset_tag' => $tag,
                'serial_no' => trim($request->input('serial_no', '')) ?: null,
                'condition' => in_array($request->input('condition'), array_keys($this->unitCondLabel), true) ? $request->input('condition') : 'good',
                'status' => in_array($request->input('status'), array_keys($this->unitStatusLabel), true) ? $request->input('status') : 'available',
                'location' => trim($request->input('location', '')) ?: null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            ActivityLog::record($request->user()->user_id, 'create', 'equipment', "Added physical unit $tag", $eid);

            return response()->json(['success' => true, 'unit_id' => $unitId]);
        }

        if ($action === 'update_unit') {
            $unitId = (int) $request->input('unit_id');
            $unit = DB::table('equipment_units')->where('unit_id', $unitId)->first();
            if (! $unit) {
                return response()->json(['success' => false, 'error' => 'Unit not found.']);
            }

            DB::table('equipment_units')->where('unit_id', $unitId)->update([
                'condition' => in_array($request->input('condition'), array_keys($this->unitCondLabel), true) ? $request->input('condition') : $unit->condition,
                'status' => in_array($request->input('status'), array_keys($this->unitStatusLabel), true) ? $request->input('status') : $unit->status,
                'location' => trim($request->input('location', '')) ?: null,
                'updated_at' => now(),
            ]);
            ActivityLog::record($request->user()->user_id, 'update', 'equipment', "Updated physical unit {$unit->asset_tag}", $unit->equipment_id);

            return response()->json(['success' => true]);
        }

        if ($action === 'retire_unit') {
            $unitId = (int) $request->input('unit_id');
            $unit = DB::table('equipment_units')->where('unit_id', $unitId)->first();
            if (! $unit) {
                return response()->json(['success' => false, 'error' => 'Unit not found.']);
            }
            DB::table('equipment_units')->where('unit_id', $unitId)->update(['status' => 'retired', 'updated_at' => now()]);
            ActivityLog::record($request->user()->user_id, 'update', 'equipment', "Retired physical unit {$unit->asset_tag}", $unit->equipment_id);

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'error' => 'Unknown action']);
    }

    private function handleAction(Request $request, $user, string $role): ?array
    {
        $action = $request->input('action', '');
        $uid = $user->user_id;

        if ($action === 'add') {
            $imgPath = '';
            if ($request->hasFile('image')) {
                $up = ImageUpload::handle($request->file('image'), 'equipment');
                if ($up['success']) $imgPath = $up['path'];
            }

            try {
                $eid = DB::table('equipment')->insertGetId([
                    'category_id' => (int) $request->input('category_id'),
                    'equipment_name' => $request->input('equipment_name', ''),
                    'brand' => $request->input('brand', ''),
                    'model' => $request->input('model', ''),
                    'serial_number' => $request->input('serial_number', '') ?: null,
                    'description' => $request->input('description', ''),
                    'daily_rate' => (float) $request->input('daily_rate', 0),
                    'condition_status' => $request->input('condition_status', 'good'),
                    'date_acquired' => $request->input('date_acquired') ?: null,
                    'notes' => $request->input('notes', ''),
                    'operator_note' => $request->input('operator_note', '') ?: null,
                    'image_path' => $imgPath,
                    'stock_quantity' => max(1, (int) $request->input('stock_quantity', 1)),
                    'requires_operator' => 1,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                return ['type' => 'danger', 'text' => 'Failed to add. ' . (str_contains($e->getMessage(), 'Duplicate') ? 'Serial number already exists.' : 'Database error.')];
            }

            $this->syncOperatorPositions($eid, (array) $request->input('operator_positions', []));
            ActivityLog::record($uid, 'create', 'equipment', 'Added: ' . $request->input('equipment_name', ''), $eid);

            return ['type' => 'success', 'text' => 'Equipment <strong>' . e($request->input('equipment_name', '')) . '</strong> added.'];
        }

        if ($action === 'edit') {
            $eid = (int) $request->input('equipment_id');
            $old = DB::table('equipment')->where('equipment_id', $eid)->first();
            $imgPath = $old->image_path ?? '';
            if ($request->hasFile('image')) {
                $up = ImageUpload::handle($request->file('image'), 'equipment');
                if ($up['success']) {
                    ImageUpload::deleteOld($old->image_path ?? null);
                    $imgPath = $up['path'];
                }
            }

            $newAvailStatus = $request->input('availability_status', 'available');
            if ($newAvailStatus !== 'under_repair' && $newAvailStatus !== 'retired') {
                $hasOpenIncident = DB::table('incident_reports')->where('equipment_id', $eid)->where('status', 'open')->exists();
                $hasActiveRepair = DB::table('repair_purchase_tickets')->where('equipment_id', $eid)
                    ->whereIn('status', ['requested', 'approved', 'in_progress'])->exists();
                if ($hasOpenIncident || $hasActiveRepair) {
                    return ['type' => 'danger', 'text' => 'Cannot change status — this equipment has an open incident or an active repair ticket. Resolve it first before making the item available again.'];
                }
            }

            try {
                DB::table('equipment')->where('equipment_id', $eid)->update([
                    'category_id' => (int) $request->input('category_id'),
                    'equipment_name' => $request->input('equipment_name', ''),
                    'brand' => $request->input('brand', ''),
                    'model' => $request->input('model', ''),
                    'serial_number' => $request->input('serial_number', '') ?: null,
                    'description' => $request->input('description', ''),
                    'daily_rate' => (float) $request->input('daily_rate', 0),
                    'condition_status' => $request->input('condition_status', 'good'),
                    'availability_status' => $request->input('availability_status', 'available'),
                    'notes' => $request->input('notes', ''),
                    'operator_note' => $request->input('operator_note', '') ?: null,
                    'image_path' => $imgPath,
                    'stock_quantity' => max(1, (int) $request->input('stock_quantity', 1)),
                    'requires_operator' => 1,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                return ['type' => 'danger', 'text' => 'Update failed.'];
            }

            $this->syncOperatorPositions($eid, (array) $request->input('operator_positions', []));
            ActivityLog::record($uid, 'update', 'equipment', 'Updated: ' . $request->input('equipment_name', ''), $eid);

            return ['type' => 'success', 'text' => 'Equipment updated.'];
        }

        if ($action === 'delete') {
            $eid = (int) $request->input('equipment_id');
            DB::table('equipment')->where('equipment_id', $eid)->update(['availability_status' => 'retired']);
            ActivityLog::record($uid, 'delete', 'equipment', "Retired equipment ID: $eid", $eid);

            return ['type' => 'success', 'text' => 'Equipment retired.'];
        }

        // Both actions below previously picked an arbitrary (`->first()`, not booking-specific)
        // booking_equipment row and logged a transaction against it without ever touching
        // availability_status — so the confirm dialog's promise ("mark as In Field") never
        // actually happened, and the transaction could get attributed to the wrong booking
        // entirely if this equipment had ever been used on more than one. Now scoped to the
        // one booking this action is actually valid for, and booking_status/availability_status
        // are kept in sync with what BookingDetailController::checkout()/checkin() do for the
        // normal release/return flow (the extra crew/driver/cost-approval gates that flow
        // enforces are deliberately not duplicated here — this stays the quick, no-frills path).
        if ($action === 'checkout' && $role === 'operations_manager') {
            $eid = (int) $request->input('equipment_id');
            $booking = DB::table('booking_equipment as be')
                ->join('bookings as b', 'be.booking_id', '=', 'b.booking_id')
                ->where('be.equipment_id', $eid)->where('b.booking_status', 'confirmed')
                ->orderBy('b.shoot_date_start')
                ->select('b.booking_id')
                ->first();
            if (! $booking) {
                return ['type' => 'danger', 'text' => 'No confirmed booking is waiting to release this equipment. Release it from that booking\'s own page instead.'];
            }

            DB::table('equipment_transactions')->insert([
                'booking_id' => $booking->booking_id, 'equipment_id' => $eid, 'transaction_type' => 'checkout',
                'transaction_date' => now(), 'condition_out' => 'good', 'handled_by' => $uid,
            ]);
            DB::table('equipment')->where('equipment_id', $eid)->update(['availability_status' => 'rented']);
            DB::table('bookings')->where('booking_id', $booking->booking_id)->update(['booking_status' => 'ongoing', 'updated_at' => now()]);
            ActivityLog::record($uid, 'checkout', 'equipment', "Checked out: $eid", $eid);

            return ['type' => 'success', 'text' => 'Equipment checked out.'];
        }

        if ($action === 'checkin' && $role === 'operations_manager') {
            $eid = (int) $request->input('equipment_id');
            $cond = $request->input('condition_in', 'good');
            $booking = DB::table('booking_equipment as be')
                ->join('bookings as b', 'be.booking_id', '=', 'b.booking_id')
                ->where('be.equipment_id', $eid)->where('b.booking_status', 'ongoing')
                ->orderBy('b.shoot_date_start')
                ->select('b.booking_id')
                ->first();
            if (! $booking) {
                return ['type' => 'danger', 'text' => 'This equipment has no active release to check back in.'];
            }

            DB::table('equipment_transactions')->insert([
                'booking_id' => $booking->booking_id, 'equipment_id' => $eid, 'transaction_type' => 'checkin',
                'transaction_date' => now(), 'condition_in' => $cond, 'handled_by' => $uid,
            ]);
            $newStatus = in_array($cond, ['damaged', 'missing'], true) ? 'under_repair' : 'available';
            DB::table('equipment')->where('equipment_id', $eid)->update(['availability_status' => $newStatus]);
            DB::table('bookings')->where('booking_id', $booking->booking_id)->update(['booking_status' => 'pending_inspection', 'updated_at' => now()]);
            ActivityLog::record($uid, 'checkin', 'equipment', "Checked in: $eid", $eid);

            return ['type' => 'success', 'text' => 'Equipment checked in.'];
        }

        if ($action === 'add_category') {
            $cn = trim($request->input('category_name', ''));
            $cdesc = trim($request->input('category_desc', ''));
            if ($cn) {
                DB::table('equipment_categories')->insertOrIgnore(['category_name' => $cn, 'description' => $cdesc]);

                return ['type' => 'success', 'text' => 'Category <strong>' . e($cn) . '</strong> added.'];
            }
        }

        return null;
    }

    private function syncOperatorPositions(int $eid, array $positionIds): void
    {
        DB::table('equipment_operators')->where('equipment_id', $eid)->delete();
        $positionIds = array_filter(array_map('intval', $positionIds));
        if ($positionIds) {
            foreach ($positionIds as $pid) {
                DB::table('equipment_operators')->insertOrIgnore(['equipment_id' => $eid, 'position_id' => $pid]);
            }
        } else {
            $defaultPos = DB::table('crew_positions')->where('position_name', 'like', '%Camera Operator%')->value('position_id');
            if ($defaultPos) {
                DB::table('equipment_operators')->insertOrIgnore(['equipment_id' => $eid, 'position_id' => $defaultPos]);
            }
        }
    }
}
