<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\ImageUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EquipmentController extends Controller
{
    private array $condBadge = [
        'excellent' => 'badge-green', 'good' => 'badge-blue', 'fair' => 'badge-yellow', 'under_repair' => 'badge-red',
    ];

    private array $availBadge = [
        'available' => 'badge-green', 'booked' => 'badge-red', 'rented' => 'badge-yellow',
        'under_repair' => 'badge-red', 'retired' => 'badge-gray',
    ];

    private array $availLabel = [
        'available' => 'Available', 'booked' => 'Booked', 'rented' => 'In Use',
        'under_repair' => 'Under Repair', 'retired' => 'Retired',
    ];

    public function index(Request $request): View|JsonResponse
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

        if ($request->isMethod('post') && $canManage && $request->filled('ajax_action')) {
            return $this->handleAjaxAction($request);
        }

        $msg = null;
        if ($request->isMethod('post') && $canManage) {
            $msg = $this->handleAction($request, $user, $role);
        }

        $positions = DB::table('crew_positions')->orderBy('position_name')->get();

        $catFilter = (int) $request->query('cat', 0);
        $statusFilter = $request->query('status', '');
        $viewMode = $request->query('view', 'table');
        $search = $request->query('q', '');
        $page = max(1, (int) $request->query('p', 1));
        $perPage = $viewMode === 'grid' ? 18 : 20;

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

        $total = (clone $query)->count('e.equipment_id');
        $pages = max(1, (int) ceil($total / $perPage));
        $offset = ($page - 1) * $perPage;

        $equipment = (clone $query)
            ->select('e.*', 'ec.category_name')
            ->selectRaw('(SELECT COUNT(*) FROM equipment_operators eo WHERE eo.equipment_id = e.equipment_id) AS operator_count')
            ->selectRaw("(SELECT b.booking_reference
                FROM booking_equipment be
                JOIN bookings b ON be.booking_id = b.booking_id
                WHERE be.equipment_id = e.equipment_id
                  AND b.booking_status IN ('confirmed','pending')
                  AND b.shoot_date_start >= CURDATE()
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
            ->where('b.shoot_date_start', '>=', DB::raw('CURDATE()'))
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
            'condBadge' => $this->condBadge, 'availBadge' => $this->availBadge, 'availLabel' => $this->availLabel,
        ]);
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
