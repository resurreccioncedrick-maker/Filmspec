<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\BookingCosting;
use App\Support\MessageReadTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;

class BookingDetailController extends Controller
{
    private array $statusBadge = [
        'pending' => 'badge-yellow', 'confirmed' => 'badge-blue', 'ongoing' => 'badge-green',
        'pending_inspection' => 'badge-purple', 'returned' => 'badge-orange', 'completed' => 'badge-gray',
        'cancelled' => 'badge-red',
    ];

    private array $payBadge = [
        'unpaid' => 'badge-yellow', 'partial' => 'badge-orange', 'paid' => 'badge-green',
        'overdue' => 'badge-red', 'refunded' => 'badge-purple', 'cancelled' => 'badge-gray',
    ];

    private array $payLabel = [
        'unpaid' => 'Unpaid', 'partial' => 'Partially Paid', 'paid' => 'Fully Paid',
        'overdue' => 'Overdue', 'refunded' => 'Refunded', 'cancelled' => 'Cancelled',
    ];

    public function show(Request $request, int $id): View|RedirectResponse|JsonResponse
    {
        $booking = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->join('users as u', 'b.created_by', '=', 'u.user_id')
            ->leftJoin('users as a', 'b.approved_by', '=', 'a.user_id')
            ->leftJoin('vehicle_rates as vr', 'b.vehicle_rate_id', '=', 'vr.vehicle_id')
            ->where('b.booking_id', $id)
            ->select('b.*', 'c.company_name', 'c.contact_person', 'c.email as client_email', 'c.phone as client_phone',
                'c.client_type', 'c.is_vat_registered', 'c.entity_type', 'c.user_id as client_user_id',
                DB::raw("CONCAT(u.first_name,' ',u.last_name) AS created_by_name"),
                DB::raw("CONCAT(a.first_name,' ',a.last_name) AS approved_by_name"),
                'vr.label as vehicle_label', 'vr.vehicle_type as vehicle_type_key')
            ->first();

        if (! $booking) {
            return redirect()->route('bookings');
        }

        if ($request->boolean('get_suggested_accessories')) {
            return response()->json($this->suggestedAccessories($id));
        }

        $role = $request->user()->role->role_name ?? '';
        $isAdmin = in_array($role, ['super_admin', 'admin', 'operations_manager', 'traffic'], true);

        $equipmentLines = DB::table('booking_equipment as be')
            ->join('equipment as e', 'be.equipment_id', '=', 'e.equipment_id')
            ->join('equipment_categories as ec', 'e.category_id', '=', 'ec.category_id')
            ->where('be.booking_id', $id)
            ->select('be.*', 'e.equipment_name', 'e.brand', 'e.model', 'e.availability_status', 'ec.category_name')
            ->selectRaw("(SELECT COUNT(*) FROM equipment_transactions et WHERE et.booking_id=be.booking_id AND et.equipment_id=be.equipment_id AND et.transaction_type='checkout') AS checked_out")
            ->selectRaw("(SELECT COUNT(*) FROM equipment_transactions et WHERE et.booking_id=be.booking_id AND et.equipment_id=be.equipment_id AND et.transaction_type='checkin') AS checked_in")
            ->selectRaw("(SELECT et.transaction_date FROM equipment_transactions et WHERE et.booking_id=be.booking_id AND et.equipment_id=be.equipment_id AND et.transaction_type='checkout' ORDER BY et.transaction_id DESC LIMIT 1) AS checkout_time")
            ->selectRaw("(SELECT et.transaction_date FROM equipment_transactions et WHERE et.booking_id=be.booking_id AND et.equipment_id=be.equipment_id AND et.transaction_type='checkin' ORDER BY et.transaction_id DESC LIMIT 1) AS checkin_time")
            ->selectRaw("(SELECT et.condition_in FROM equipment_transactions et WHERE et.booking_id=be.booking_id AND et.equipment_id=be.equipment_id AND et.transaction_type='checkin' ORDER BY et.transaction_id DESC LIMIT 1) AS return_condition")
            ->get();

        $crewLines = DB::table('booking_crew as bc')
            ->join('crew_members as cm', 'bc.crew_id', '=', 'cm.crew_id')
            ->leftJoin('crew_positions as cp', 'bc.position_id', '=', 'cp.position_id')
            ->leftJoin('equipment as e', 'bc.equipment_id', '=', 'e.equipment_id')
            ->where('bc.booking_id', $id)
            ->orderBy('cp.position_name')->orderBy('cm.last_name')
            ->select('bc.*', DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS crew_name"), 'cm.phone as crew_phone', 'cm.employment_type', 'cp.position_name', 'e.equipment_name as linked_equipment_name')
            ->get();

        $assignedCrewIds = $crewLines->pluck('crew_id')->map(fn ($v) => (int) $v)->values();
        $equipAssignedCrew = [];
        $equipCoverage = [];
        foreach ($crewLines as $cl) {
            $eqId = (int) ($cl->equipment_id ?? 0);
            if ($eqId) {
                $equipAssignedCrew[$eqId][] = ['name' => $cl->crew_name, 'position' => $cl->position_name ?? null];
                $equipCoverage[$eqId][] = $cl->crew_name;
            }
        }

        $equipOpRequirements = DB::table('booking_equipment as be')
            ->join('equipment as e', 'be.equipment_id', '=', 'e.equipment_id')
            ->leftJoin('equipment_operators as eo', 'eo.equipment_id', '=', 'be.equipment_id')
            ->leftJoin('crew_positions as cp', 'eo.position_id', '=', 'cp.position_id')
            ->where('be.booking_id', $id)
            ->groupBy('be.bk_equip_id', 'be.equipment_id', 'e.equipment_name', 'be.quantity')
            ->select('be.bk_equip_id', 'be.equipment_id', 'e.equipment_name', 'be.quantity')
            ->selectRaw("GROUP_CONCAT(DISTINCT cp.position_name ORDER BY cp.position_name SEPARATOR ', ') AS required_positions")
            ->selectRaw("GROUP_CONCAT(DISTINCT eo.position_id ORDER BY eo.position_id SEPARATOR ',') AS required_position_ids")
            ->selectRaw('COUNT(DISTINCT eo.position_id) AS req_count')
            ->get();

        // Catalog rows, shared by Part 9's
        // quick-add panel on the Equipment tab.
        $catalogEquipment = DB::table('equipment')
            ->where('condition_status', '!=', 'retired')
            ->orderBy('equipment_name')
            ->select('equipment_id', 'equipment_name', 'brand', 'daily_rate')
            ->get();

        $ceHistory = DB::table('cost_estimates as ce')
            ->leftJoin('users as cu', 'ce.confirmed_by', '=', 'cu.user_id')
            ->where('ce.booking_id', $id)->orderByDesc('ce.ce_id')
            ->select('ce.*', DB::raw("CONCAT(cu.first_name,' ',cu.last_name) AS confirmed_by_name"))
            ->get();
        $ce = $ceHistory->first();
        $ceBreakdown = BookingCosting::breakdown($id, $ce);

        // Origin (client vs staff) resolved via proposed_by -> users -> roles, the same
        // authorship join used for the comment feed below.
        $discounts = DB::table('booking_discounts as bd')
            ->join('users as pu', 'bd.proposed_by', '=', 'pu.user_id')
            ->join('roles as pr', 'pu.role_id', '=', 'pr.role_id')
            ->leftJoin('users as au', 'bd.approved_by', '=', 'au.user_id')
            ->where('bd.booking_id', $id)->orderByDesc('bd.discount_id')
            ->select(
                'bd.*',
                DB::raw("CONCAT(pu.first_name,' ',pu.last_name) AS proposed_by_name"),
                'pr.role_name as proposed_by_role',
                DB::raw("CONCAT(au.first_name,' ',au.last_name) AS approved_by_name")
            )
            ->get();

        $comments = DB::table('booking_comments as bc')
            ->join('users as u', 'bc.user_id', '=', 'u.user_id')
            ->join('roles as r', 'u.role_id', '=', 'r.role_id')
            ->where('bc.booking_id', $id)
            ->orderBy('bc.created_at')
            ->select('bc.*', DB::raw("CONCAT(u.first_name,' ',u.last_name) AS author_name"), 'r.role_name as author_role')
            ->get();

        $unreadComments = MessageReadTracker::unreadCount(Auth::id(), 'booking', $id, 'booking_comments', 'booking_id');
        MessageReadTracker::markRead(Auth::id(), 'booking', $id, $comments->last()->created_at ?? null);

        // Documents (Part 18) — IDs, permits, etc. attached to this booking.
        $documents = DB::table('documents as d')
            ->leftJoin('users as u', 'd.uploaded_by', '=', 'u.user_id')
            ->where('d.booking_id', $id)
            ->orderByDesc('d.created_at')
            ->select('d.*', DB::raw("CONCAT(u.first_name,' ',u.last_name) AS uploaded_by_name"))
            ->get();

        $bookingAccessories = DB::table('booking_accessories as ba')
            ->join('accessories as a', 'ba.accessory_id', '=', 'a.accessory_id')
            ->where('ba.booking_id', $id)
            ->orderBy('a.accessory_name')
            ->select('ba.*', 'a.accessory_name', 'a.description', 'a.is_included as acc_included', 'a.quantity as stock_qty')
            ->get();
        $bookingAccessoriesCount = $bookingAccessories->count();

        $allAccessoriesList = DB::table('accessories as a')
            ->whereNotIn('a.accessory_id', function ($q) use ($id) {
                $q->select('accessory_id')->from('booking_accessories')->where('booking_id', $id);
            })
            ->orderBy('a.accessory_name')
            ->select('a.accessory_id', 'a.accessory_name', 'a.daily_rate', 'a.is_included')
            // For an individually-tracked accessory, a.quantity is a nominal 1 (its real stock is
            // in accessory_units) — same distinction addBookingAccessory() applies server-side —
            // so the "available" count shown here must come from accessory_units, not a.quantity,
            // or it wrongly caps display at 1 unit regardless of how many units actually exist.
            ->selectRaw("CASE WHEN a.tracking_method = 'individual'
                THEN (SELECT COUNT(*) FROM accessory_units au WHERE au.accessory_id = a.accessory_id AND au.status != 'retired')
                ELSE a.quantity END AS quantity")
            ->selectRaw('COALESCE((SELECT SUM(ba2.quantity) FROM booking_accessories ba2
                JOIN bookings b2 ON ba2.booking_id=b2.booking_id
                WHERE ba2.accessory_id=a.accessory_id
                AND b2.booking_status NOT IN (\'cancelled\',\'completed\')
                AND ba2.booking_id != ?),0) AS qty_in_use', [$id])
            ->get();
        $payments = DB::table('payments')->where('booking_id', $id)->orderByDesc('payment_date')->get();
        $incidents = DB::table('incident_reports as ir')
            ->join('equipment as e', 'ir.equipment_id', '=', 'e.equipment_id')
            ->where('ir.booking_id', $id)
            ->orderByDesc('ir.incident_date')->orderByDesc('ir.created_at')
            ->select('ir.*', 'e.equipment_name')
            ->get();
        $openIncidents = $incidents->where('status', 'open');
        $openIncidentCount = $openIncidents->count();
        $openChargeTotal = (float) $openIncidents->sum('charge_amount');
        $totalReturnedItems = $equipmentLines->filter(fn ($l) => (int) $l->checked_in > 0)->count();

        $quotationLog = DB::table('quotation_log as ql')
            ->leftJoin('users as u', 'ql.logged_by', '=', 'u.user_id')
            ->where('ql.booking_id', $id)
            ->orderByDesc('ql.log_date')
            ->select('ql.*', DB::raw("CONCAT(u.first_name,' ',u.last_name) AS user_name"))
            ->get();

        $soa = DB::table('statement_of_accounts')->where('booking_id', $id)->orderByDesc('created_at')->first();

        $cancellations = DB::table('booking_cancellations as bc')
            ->leftJoin('users as u', 'u.user_id', '=', 'bc.requested_by')
            ->where('bc.booking_id', $id)
            ->orderByDesc('bc.created_at')
            ->select('bc.*', DB::raw("CONCAT(u.first_name,' ',u.last_name) AS requested_by_name"))
            ->get();
        $pendingCancellation = $cancellations->firstWhere('status', 'pending');

        $extensionRequests = DB::table('booking_extension_requests as er')
            ->leftJoin('users as u', 'er.requested_by', '=', 'u.user_id')
            ->where('er.booking_id', $id)->orderByDesc('er.created_at')
            ->select('er.*', DB::raw("CONCAT(u.first_name,' ',u.last_name) AS requested_by_name"))
            ->get();
        $pendingExtension = $extensionRequests->firstWhere('status', 'pending');
        $extensionRequestsCount = $extensionRequests->count();
        $pendingExtensionCount = $extensionRequests->where('status', 'pending')->count();

        $equipRequests = DB::table('booking_equipment_requests as eqr')
            ->leftJoin('equipment as e', 'eqr.equipment_id', '=', 'e.equipment_id')
            ->leftJoin('accessories as acc', 'eqr.accessory_id', '=', 'acc.accessory_id')
            ->leftJoin('crew_positions as pos', 'eqr.position_id', '=', 'pos.position_id')
            ->leftJoin('crew_members as cm', 'eqr.crew_id', '=', 'cm.crew_id')
            ->leftJoin('users as u', 'eqr.requested_by', '=', 'u.user_id')
            ->leftJoin('vehicle_rates as vr', 'eqr.vehicle_rate_id', '=', 'vr.vehicle_id')
            ->leftJoin('crew_members as drv', 'eqr.driver_crew_id', '=', 'drv.crew_id')
            ->where('eqr.booking_id', $id)->orderByDesc('eqr.created_at')
            ->select(
                'eqr.*', 'e.equipment_name', 'e.brand', 'e.model',
                'acc.accessory_name', 'pos.position_name',
                DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS crew_name"),
                DB::raw("CONCAT(u.first_name,' ',u.last_name) AS requested_by_name"),
                'vr.label as vehicle_label',
                DB::raw("CONCAT(drv.first_name,' ',drv.last_name) AS driver_name")
            )
            ->get();
        $pendingEquipRequests = $equipRequests->where('status', 'pending');
        $equipRequestsCount = $equipRequests->count();
        $pendingEquipRequestsCount = $pendingEquipRequests->count();

        // Field-added equipment/accessories — cost was actually added to the booking at
        // Dispatch (see FieldRequestsController::dispatch()), so anything still 'approved'
        // (awaiting dispatch) hasn't affected the total yet and is left out here.
        $fieldAddNumDays = max(1, (new \DateTime($booking->shoot_date_start))->diff(new \DateTime($booking->shoot_date_end))->days + 1);
        $fieldAdditions = $equipRequests
            ->whereIn('item_type', ['equipment', 'accessory'])
            ->whereIn('status', ['dispatched', 'delivered'])
            ->map(function ($r) use ($fieldAddNumDays) {
                $r->item_name = $r->item_type === 'accessory' ? $r->accessory_name : $r->equipment_name;
                $r->line_cost = (float) $r->quantity * $fieldAddNumDays * (float) $r->daily_rate;
                $r->followed_up_at = $r->delivered_at ?? $r->approved_at ?? $r->created_at;

                return $r;
            })
            ->sortByDesc('followed_up_at')
            ->values();

        $feedback = DB::table('booking_feedback as bf')
            ->join('users as u', 'bf.submitted_by', '=', 'u.user_id')
            ->where('bf.booking_id', $id)
            ->select('bf.*', DB::raw("CONCAT(u.first_name,' ',u.last_name) AS submitted_by_name"))
            ->first();

        $ds = $booking->shoot_date_start;
        $de = $booking->shoot_date_end;

        $availEquip = DB::table('equipment as e')
            ->join('equipment_categories as ec', 'e.category_id', '=', 'ec.category_id')
            ->where('e.availability_status', 'available')
            ->whereNotIn('e.equipment_id', function ($q) use ($id, $ds, $de) {
                $q->select('be.equipment_id')->from('booking_equipment as be')
                    ->join('bookings as b', 'be.booking_id', '=', 'b.booking_id')
                    ->where('be.booking_id', '!=', $id)
                    ->whereNotIn('b.booking_status', ['cancelled', 'completed'])
                    ->where('b.shoot_date_start', '<=', $de)
                    ->where('b.shoot_date_end', '>=', $ds);
            })
            ->orderBy('ec.category_name')->orderBy('e.equipment_name')
            ->select('e.equipment_id', 'e.equipment_name', 'e.brand', 'e.daily_rate', 'ec.category_name')
            ->get();

        $availCrew = DB::table('crew_members as cm')
            ->leftJoin('crew_positions as cp', 'cm.primary_position_id', '=', 'cp.position_id')
            ->where('cm.status', 'active')
            ->select('cm.crew_id', DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS name"), 'cm.base_rate_12hr', 'cm.employment_type', 'cm.primary_position_id', 'cp.position_name')
            ->selectRaw('(SELECT b2.booking_reference FROM booking_crew bc2 JOIN bookings b2 ON bc2.booking_id = b2.booking_id
                WHERE bc2.crew_id = cm.crew_id AND bc2.booking_id != ? AND b2.booking_status NOT IN (\'cancelled\',\'completed\')
                AND b2.shoot_date_start <= ? AND b2.shoot_date_end >= ? LIMIT 1) AS busy_on_booking', [$id, $de, $ds])
            ->selectRaw("(SELECT CONCAT(cu.date_from,' – ',cu.date_to, IF(cu.reason != '' AND cu.reason IS NOT NULL, CONCAT(': ',cu.reason), ''))
                FROM crew_unavailability cu WHERE cu.crew_id = cm.crew_id AND cu.date_from <= ? AND cu.date_to >= ? LIMIT 1) AS unavailability_reason", [$de, $ds])
            ->orderByRaw('(busy_on_booking IS NOT NULL OR unavailability_reason IS NOT NULL) ASC')
            ->orderBy('cm.last_name')
            ->get();

        $positions = DB::table('crew_positions')->orderBy('position_name')->get();
        $vehicleRates = DB::table('vehicle_rates')->where('is_active', 1)->orderBy('base_rate')->get();

        // Transport being assigned doesn't automatically mean a driver is required — some
        // transport is just a delivery/courier fee. Staff say so explicitly on the Assign
        // Transport form (driver_required), matching ChecklistController's gate.
        $driverNeeded = ! empty($booking->vehicle_rate_id) && (bool) ($booking->driver_required ?? false);
        $driverCount = DB::table('booking_crew as bc')
            ->join('crew_positions as cp', 'bc.position_id', '=', 'cp.position_id')
            ->where('bc.booking_id', $id)->where(DB::raw('LOWER(cp.position_name)'), 'like', '%driver%')
            ->count();

        $msg = $request->session()->get('bd_flash');

        return view('booking-detail', [
            'booking' => $booking, 'id' => $id, 'isAdmin' => $isAdmin, 'role' => $role,
            'equipmentLines' => $equipmentLines, 'crewLines' => $crewLines, 'equipAssignedCrew' => $equipAssignedCrew,
            'equipCoverage' => $equipCoverage, 'equipOpRequirements' => $equipOpRequirements,
            'catalogEquipment' => $catalogEquipment,
            'assignedCrewIds' => $assignedCrewIds, 'ce' => $ce, 'ceHistory' => $ceHistory, 'ceBreakdown' => $ceBreakdown,
            'discounts' => $discounts,
            'comments' => $comments, 'unreadComments' => $unreadComments, 'bookingAccessoriesCount' => $bookingAccessoriesCount,
            'documents' => $documents,
            'bookingAccessories' => $bookingAccessories, 'allAccessoriesList' => $allAccessoriesList,
            'payments' => $payments, 'soa' => $soa, 'quotationLog' => $quotationLog, 'incidents' => $incidents, 'openIncidentCount' => $openIncidentCount,
            'openChargeTotal' => $openChargeTotal, 'totalReturnedItems' => $totalReturnedItems,
            'cancellations' => $cancellations, 'pendingCancellation' => $pendingCancellation,
            'extensionRequests' => $extensionRequests, 'pendingExtension' => $pendingExtension,
            'extensionRequestsCount' => $extensionRequestsCount, 'pendingExtensionCount' => $pendingExtensionCount,
            'equipRequests' => $equipRequests, 'pendingEquipRequests' => $pendingEquipRequests,
            'equipRequestsCount' => $equipRequestsCount, 'pendingEquipRequestsCount' => $pendingEquipRequestsCount,
            'fieldAdditions' => $fieldAdditions,
            'feedback' => $feedback,
            'availEquip' => $availEquip, 'availCrew' => $availCrew, 'positions' => $positions, 'vehicleRates' => $vehicleRates,
            'driverNeeded' => $driverNeeded, 'driverCount' => $driverCount,
            'statusBadge' => $this->statusBadge, 'payBadge' => $this->payBadge, 'payLabel' => $this->payLabel,
            'incTypeBadge' => ['damaged' => 'badge-red', 'missing' => 'badge-red', 'malfunction' => 'badge-orange', 'late_return' => 'badge-yellow'],
            'msg' => $msg,
        ]);
    }

    public function act(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';
        $uid = $user->user_id;
        $action = $request->input('action', '');

        $booking = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('b.booking_id', $id)
            ->select('b.*', 'c.client_type', 'c.payment_terms')
            ->first();
        if (! $booking) {
            return redirect()->route('bookings');
        }

        $msg = null;

        if ($action === 'add_equipment' && in_array($role, ['super_admin', 'admin', 'operations_manager', 'traffic'], true)) {
            $msg = $this->addEquipment($request, $id, $booking);
        } elseif ($action === 'assign_transport' && in_array($role, ['super_admin', 'admin', 'operations_manager', 'traffic'], true)) {
            $msg = $this->assignTransport($request, $id, $booking, $uid);
        } elseif ($action === 'field_add_equipment' && in_array($role, ['super_admin', 'admin', 'operations_manager', 'traffic'], true) && $booking->booking_status === 'ongoing') {
            $msg = $this->fieldAddEquipment($request, $id, $uid);
        } elseif ($action === 'batch_add_crew' && in_array($role, ['super_admin', 'admin', 'operations_manager', 'traffic'], true)) {
            $msg = $this->batchAddCrew($request, $id, $booking);
        } elseif ($action === 'add_crew' && in_array($role, ['super_admin', 'admin', 'operations_manager', 'traffic'], true)) {
            $msg = $this->addCrew($request, $id, $booking);
        } elseif ($action === 'update_crew_status' && in_array($role, ['super_admin', 'admin', 'operations_manager', 'traffic'], true)) {
            $msg = $this->updateCrewStatus($request, $id);
        } elseif ($action === 'remove_crew' && in_array($role, ['super_admin', 'admin', 'operations_manager', 'traffic'], true)) {
            $msg = $this->removeCrew($request, $id, $uid);
        } elseif ($action === 'generate_ce' && in_array($role, ['super_admin', 'admin', 'operations_manager', 'traffic'], true)) {
            BookingCosting::generateCostEstimate($id, $uid);
            $msg = ['type' => 'success', 'text' => 'Cost estimate generated.'];
        } elseif ($action === 'confirm_ce' && in_array($role, ['super_admin', 'admin', 'operations_manager'], true)) {
            // The booking itself must be approved before its cost estimate can be confirmed —
            // otherwise a project that was never approved could still have a confirmed CE.
            $msg = ($booking->approval_status ?? null) !== 'approved'
                ? ['type' => 'danger', 'text' => 'Cannot confirm the cost estimate — approve the booking first.']
                : BookingCosting::confirmCe($id, $uid, $request->input('confirmation_note'));
        } elseif ($action === 'issue_ce' && in_array($role, ['super_admin', 'admin', 'operations_manager', 'traffic'], true)) {
            $msg = ($booking->approval_status ?? null) !== 'approved'
                ? ['type' => 'danger', 'text' => 'Cannot mark the cost estimate as issued — approve the booking first.']
                : BookingCosting::issueCe($id);
        } elseif ($action === 'update_ce_pricing' && in_array($role, ['super_admin', 'admin', 'operations_manager'], true)) {
            $msg = $this->updateCePricing($request, $id, $uid);
        } elseif ($action === 'update_project_details' && in_array($role, ['super_admin', 'admin', 'operations_manager', 'traffic'], true)) {
            $msg = $this->updateProjectDetails($request, $id, $uid);
        } elseif ($action === 'clear_fs_equipment' && in_array($role, config('filmspec.manage_roles'), true)) {
            $msg = $this->clearFsEquipment($id, $uid);
        } elseif ($action === 'clear_crew' && in_array($role, config('filmspec.manage_roles'), true)) {
            $msg = $this->clearCrew($id, $uid);
        } elseif ($action === 'email_ce_to_client' && in_array($role, config('filmspec.all_staff'), true)) {
            $msg = $this->emailCeToClient($id, $booking, $uid);
        } elseif ($action === 'erase_ce' && in_array($role, config('filmspec.manage_roles'), true)) {
            $msg = $this->eraseCe($request, $id, $uid);
        } elseif ($action === 'close_project' && in_array($role, ['super_admin', 'admin', 'operations_manager', 'traffic'], true)) {
            $msg = $this->closeProject($request, $id, $uid);
        } elseif ($action === 'post_comment' && in_array($role, ['super_admin', 'admin', 'operations_manager', 'traffic', 'accounting'], true)) {
            $msg = $this->postComment($request, $id, $uid);
        } elseif ($action === 'duplicate_booking' && in_array($role, ['super_admin', 'admin', 'operations_manager', 'traffic'], true)) {
            return $this->duplicateBooking($request, $id, $booking, $user, $role);
        } elseif ($action === 'checkout' && in_array($role, ['operations_manager', 'admin', 'super_admin'], true)) {
            $msg = $this->checkout($request, $id, $booking, $uid);
        } elseif ($action === 'checkin' && in_array($role, ['operations_manager', 'admin', 'super_admin'], true)) {
            $msg = $this->checkin($request, $id, $booking, $uid);
        } elseif ($action === 'bulk_checkout' && in_array($role, ['operations_manager', 'admin', 'super_admin'], true)) {
            $msg = $this->bulkCheckout($id, $booking, $uid);
        } elseif ($action === 'bulk_checkin' && in_array($role, ['operations_manager', 'admin', 'super_admin'], true)) {
            $msg = $this->bulkCheckin($id, $booking, $uid);
        } elseif ($action === 'extend_rental' && in_array($role, ['operations_manager', 'admin', 'super_admin'], true)) {
            $msg = $this->extendRental($request, $id, $booking, $uid);
        } elseif ($action === 'confirm_inspection' && in_array($role, ['operations_manager', 'admin', 'super_admin'], true)) {
            $msg = $this->confirmInspection($id, $booking);
        } elseif ($action === 'complete_booking' && in_array($role, ['operations_manager', 'admin', 'super_admin'], true)) {
            $msg = $this->completeBooking($id, $booking, $uid);
        } elseif ($action === 'record_payment' && in_array($role, ['admin', 'operations_manager', 'super_admin'], true)) {
            $msg = $this->recordPayment($request, $id, $uid);
        } elseif ($action === 'update_incident' && in_array($role, ['operations_manager', 'admin', 'super_admin'], true)) {
            $msg = $this->updateIncident($request, $id);
        } elseif ($action === 'add_booking_accessory' && in_array($role, ['super_admin', 'admin', 'operations_manager', 'traffic'], true)) {
            $msg = $this->addBookingAccessory($request, $id);
        } elseif ($action === 'remove_booking_accessory' && in_array($role, ['super_admin', 'admin', 'operations_manager', 'traffic'], true)) {
            $msg = $this->removeBookingAccessory($request, $id);
        } elseif ($action === 'request_cancellation' && in_array($role, ['super_admin', 'admin', 'operations_manager', 'traffic'], true)) {
            $msg = $this->requestCancellation($request, $id, $booking, $role, $uid);
        } elseif ($action === 'approve_cancellation' && in_array($role, ['super_admin', 'admin', 'operations_manager'], true)) {
            $msg = $this->approveCancellation($request, $id, $booking, $uid);
        } elseif ($action === 'reject_cancellation' && in_array($role, ['super_admin', 'admin', 'operations_manager'], true)) {
            $msg = $this->rejectCancellation($request, $id, $uid);
        } elseif ($action === 'approve_extension' && in_array($role, ['super_admin', 'admin', 'operations_manager'], true)) {
            $msg = $this->approveExtension($request, $id, $booking, $uid);
        } elseif ($action === 'reject_extension' && in_array($role, ['super_admin', 'admin', 'operations_manager'], true)) {
            $msg = $this->rejectExtension($request, $id, $uid);
        } elseif ($action === 'approve_discount' && in_array($role, ['super_admin', 'admin', 'operations_manager'], true)) {
            $msg = BookingCosting::approveDiscount($id, (int) $request->input('discount_id'), $uid, trim((string) $request->input('review_notes', '')) ?: null);
        } elseif ($action === 'reject_discount' && in_array($role, ['super_admin', 'admin', 'operations_manager'], true)) {
            $msg = BookingCosting::rejectDiscount($id, (int) $request->input('discount_id'), $uid, trim((string) $request->input('review_notes', '')) ?: null);
        } elseif ($action === 'set_discount' && in_array($role, ['super_admin', 'admin', 'operations_manager'], true)) {
            $msg = BookingCosting::setDiscount(
                $id, $uid,
                (string) $request->input('discount_type', 'flat'),
                (float) $request->input('discount_value', 0),
                trim((string) $request->input('reason', ''))
            );
        } elseif ($action === 'approve_field_request' && in_array($role, ['super_admin', 'admin', 'operations_manager'], true)) {
            $msg = $this->approveFieldRequest($request, $id, $booking, $uid);
        } elseif ($action === 'reject_field_request' && in_array($role, ['super_admin', 'admin', 'operations_manager'], true)) {
            $msg = $this->rejectFieldRequest($request, $id, $uid);
        } elseif ($action === 'approve_booking' && in_array($role, ['super_admin', 'operations_manager', 'traffic'], true)) {
            DB::table('bookings')->where('booking_id', $id)->update([
                'booking_status' => 'confirmed', 'approval_status' => 'approved', 'approved_by' => $uid, 'updated_at' => now(),
            ]);
            DB::table('quotation_log')->insert([
                'booking_id' => $id, 'log_type' => 'confirmed', 'logged_by' => $uid,
                'previous_status' => 'pending', 'new_status' => 'confirmed', 'remarks' => 'Booking approved by admin', 'log_date' => now(),
            ]);
            ActivityLog::record($uid, 'approve', 'booking', "Booking {$booking->booking_reference} approved and confirmed", $id);
            BookingCosting::generateCostEstimate($id, $uid);
            $msg = ['type' => 'success', 'text' => "Booking <strong>{$booking->booking_reference}</strong> approved and confirmed. Cost estimate generated."];
        } elseif ($action === 'reject_booking' && in_array($role, ['super_admin', 'operations_manager', 'traffic'], true)) {
            $reason = $request->input('rejection_reason', 'No reason provided');
            DB::table('equipment')->whereIn('equipment_id', function ($q) use ($id) {
                $q->select('equipment_id')->from('booking_equipment')->where('booking_id', $id);
            })->update(['availability_status' => 'available']);
            DB::table('bookings')->where('booking_id', $id)->update([
                'booking_status' => 'cancelled', 'approval_status' => 'rejected', 'cancelled_by' => $uid,
                'cancellation_reason' => $reason, 'updated_at' => now(),
            ]);
            DB::table('quotation_log')->insert([
                'booking_id' => $id, 'log_type' => 'cancelled', 'logged_by' => $uid,
                'previous_status' => 'pending', 'new_status' => 'cancelled', 'remarks' => $reason, 'log_date' => now(),
            ]);
            ActivityLog::record($uid, 'reject', 'booking', "Booking {$booking->booking_reference} rejected: $reason", $id);
            $msg = ['type' => 'success', 'text' => 'Booking rejected. Any reserved equipment returned to available.'];
        }

        if ($msg) {
            $request->session()->flash('bd_flash', $msg);
        }

        // Lets a form embedded on the CE page (ce-preview, mode=booking) reuse this exact same
        // action set (add_equipment, batch_add_crew, add_booking_accessory, assign_transport,
        // set_discount, update_project_details, ...) instead of duplicating the business logic
        // there, and land back on the CE page instead of Booking Detail afterward. Deliberately
        // a closed enum, not an arbitrary URL, to rule out an open redirect.
        if ($request->input('return_to') === 'ce_preview') {
            return redirect()->route('ce-preview', array_filter([
                'booking_id' => $id,
                'ce_id' => $request->input('return_ce_id'),
                'view' => $request->input('return_view', 'internal'),
            ]));
        }

        return redirect()->route('booking-detail', $id);
    }

    private function addEquipment(Request $request, int $id, $booking): ?array
    {
        $eid = (int) $request->input('equipment_id');
        $qty = (int) $request->input('quantity');
        $days = (int) $request->input('days');
        $rate = (float) $request->input('daily_rate');
        $notes = $request->input('notes', '');

        if (DB::table('booking_equipment')->where('booking_id', $id)->where('equipment_id', $eid)->exists()) {
            return ['type' => 'error', 'text' => 'This equipment is already added to this booking.'];
        }

        $conflictError = \App\Support\EquipmentAvailability::check($eid, $qty, $id, $booking);
        if ($conflictError) {
            return $conflictError;
        }

        DB::table('booking_equipment')->insert([
            'booking_id' => $id, 'equipment_id' => $eid, 'quantity' => $qty, 'days' => $days, 'daily_rate' => $rate, 'notes' => $notes,
        ]);
        BookingCosting::updateBookingTotal($id);
        BookingCosting::generateCostEstimate($id, Auth::id());

        if (($booking->cost_approval_status ?? null) === 'client_approved') {
            DB::table('bookings')->where('booking_id', $id)->update(['cost_approval_status' => 'pending_client', 'updated_at' => now()]);
        }

        return ['type' => 'success', 'text' => 'Equipment added and cost estimate updated.'];
    }

    private function assignTransport(Request $request, int $id, $booking, int $uid): array
    {
        $zone = $request->input('location_zone', '');
        $vid = (int) $request->input('vehicle_rate_id', 0);
        // Transport cost is manually entered by traffic/admin — the rate×multiplier figure
        // below is only a reference suggestion shown in the UI, not the source of truth.
        $rawCost = $request->input('transport_cost');
        if ($rawCost === null || $rawCost === '' || ! is_numeric($rawCost) || (float) $rawCost < 0) {
            return ['type' => 'danger', 'text' => 'Enter a valid transport cost (0 or more).'];
        }
        $transCost = round((float) $rawCost, 2);
        $driverCid = (int) $request->input('driver_crew_id', 0);
        $driverRate = (float) $request->input('driver_rate', 0);
        // Not every transport arrangement needs a FilmSpec driver — some is just a delivery/
        // courier fee. Staff say so explicitly here rather than the release gate assuming a
        // driver is always required whenever transport is on the booking.
        $driverRequired = $request->boolean('driver_required');

        // Still needed for transport_multiplier, which CePreviewController also uses to scale
        // the equipment total on the CE document — not just the transport line itself.
        $zoneMultMap = ['manila' => 1.0, 'luzon' => 1.5, 'luzon_far' => 2.0];
        $mult = $zoneMultMap[$zone] ?? 1.0;

        DB::table('bookings')->where('booking_id', $id)->update([
            'location_zone' => $zone ?: null, 'transport_multiplier' => $mult,
            'transportation_cost' => $transCost, 'vehicle_rate_id' => $vid ?: null,
            'driver_required' => $vid ? $driverRequired : false,
            // Set on every save, whatever the outcome (a real vehicle or an explicit "no
            // transport needed") — this is what the release gate actually checks, not whether
            // a vehicle happens to be selected.
            'transport_confirmed_at' => now(),
        ]);

        $parts = [];
        if ($transCost > 0) $parts[] = 'Cost: <strong>₱' . number_format($transCost, 2) . '</strong>';
        $zoneLabelMap = ['manila' => 'Metro Manila / NCR', 'luzon' => 'Luzon Near (61–150 km)', 'luzon_far' => 'Luzon Far (151 km+)'];
        if ($zone) $parts[] = 'Zone: <strong>' . e($zoneLabelMap[$zone] ?? $zone) . '</strong>';
        if ($vid) {
            $vLabel = DB::table('vehicle_rates')->where('vehicle_id', $vid)->value('label');
            if ($vLabel) $parts[] = 'Vehicle: <strong>' . e($vLabel) . '</strong>';
        }

        $bkDays = max(1, (int) round((strtotime($booking->shoot_date_end) - strtotime($booking->shoot_date_start)) / 86400) + 1);
        if ($driverCid) {
            $crewStatus = DB::table('crew_members')->where('crew_id', $driverCid)->value('status');
            if ($crewStatus === 'active') {
                if ($driverRate <= 0) {
                    $driverRate = (float) DB::table('crew_members')->where('crew_id', $driverCid)->value('base_rate_12hr');
                }
                $dpid = DB::table('crew_positions')->whereRaw('LOWER(position_name) LIKE ?', ['%driver%'])->value('position_id');
                $exists = DB::table('booking_crew')->where('booking_id', $id)->where('crew_id', $driverCid)->exists();
                if (! $exists) {
                    DB::table('booking_crew')->insert([
                        'booking_id' => $id, 'crew_id' => $driverCid, 'position_id' => $dpid, 'rate_used' => $driverRate,
                        'hours_worked' => $bkDays, 'notes' => 'Driver', 'equipment_id' => null,
                    ]);
                } else {
                    DB::table('booking_crew')->where('booking_id', $id)->where('crew_id', $driverCid)->update([
                        'rate_used' => $driverRate, 'hours_worked' => $bkDays,
                    ]);
                }
                $driverName = DB::table('crew_members')->where('crew_id', $driverCid)->selectRaw("CONCAT(first_name,' ',last_name) AS n")->value('n');
                $parts[] = 'Driver: <strong>' . e($driverName) . '</strong>';
                ActivityLog::record($uid, 'assign_transport', 'booking', "Transport assigned to booking $id — driver: $driverName", $id);
            }
        } else {
            ActivityLog::record($uid, 'assign_transport', 'booking', "Transport assigned to booking $id — no driver", $id);
        }

        BookingCosting::generateCostEstimate($id, $uid);
        if (($booking->cost_approval_status ?? null) === 'client_approved') {
            DB::table('bookings')->where('booking_id', $id)->update(['cost_approval_status' => 'pending_client', 'updated_at' => now()]);
        }

        return ['type' => 'success', 'text' => 'Transport saved. ' . implode(' · ', $parts)];
    }

    private function fieldAddEquipment(Request $request, int $id, int $uid): array
    {
        $booking = DB::table('bookings')->where('booking_id', $id)->first();
        $eid = (int) $request->input('equipment_id');
        $qty = max(1, (int) $request->input('quantity'));
        $days = max(1, (int) $request->input('days'));
        $rate = (float) $request->input('daily_rate');
        $notes = $request->input('notes', '');
        $cid = (int) $request->input('crew_id');
        $posid = (int) $request->input('position_id');
        $crate = (float) $request->input('crew_rate');
        $cnotes = $request->input('crew_notes', '');

        if (! $eid || ! $cid || ! $posid) {
            return ['type' => 'error', 'text' => 'Equipment, crew member, and position are all required for field additions.'];
        }

        $crewStatus = DB::table('crew_members')->where('crew_id', $cid)->value('status');
        $dupEquip = DB::table('booking_equipment')->where('booking_id', $id)->where('equipment_id', $eid)->exists();

        if ($dupEquip) {
            return ['type' => 'error', 'text' => 'This equipment is already on this booking.'];
        }
        $availError = \App\Support\EquipmentAvailability::check($eid, $qty, $id, $booking);
        if ($availError) {
            return $availError;
        }
        if ($crewStatus !== 'active') {
            return ['type' => 'error', 'text' => 'Crew member is not active (status: ' . ucfirst($crewStatus ?? 'unknown') . ').'];
        }

        DB::table('booking_equipment')->insert([
            'booking_id' => $id, 'equipment_id' => $eid, 'quantity' => $qty, 'days' => $days, 'daily_rate' => $rate, 'notes' => $notes,
        ]);
        DB::table('booking_crew')->insert([
            'booking_id' => $id, 'crew_id' => $cid, 'position_id' => $posid, 'rate_used' => $crate,
            'hours_worked' => $days, 'notes' => $cnotes, 'equipment_id' => $eid,
        ]);
        DB::table('equipment_transactions')->insert([
            'booking_id' => $id, 'equipment_id' => $eid, 'transaction_type' => 'checkout', 'transaction_date' => now(),
            'condition_out' => 'good', 'notes' => 'Field addition during ongoing booking', 'handled_by' => $uid,
        ]);
        DB::table('equipment')->where('equipment_id', $eid)->update(['availability_status' => 'rented']);
        DB::table('equipment_checklist')->insertOrIgnore([
            'booking_id' => $id, 'equipment_id' => $eid, 'direction' => 'out', 'quantity_expected' => $qty,
            'quantity_actual' => $qty, 'condition_out' => 'good', 'checked' => 1, 'checked_by' => $uid, 'checked_at' => now(),
        ]);
        BookingCosting::updateBookingTotal($id);
        BookingCosting::generateCostEstimate($id, $uid);

        if (($booking->cost_approval_status ?? null) === 'client_approved') {
            DB::table('bookings')->where('booking_id', $id)->update(['cost_approval_status' => 'pending_client', 'updated_at' => now()]);
        }

        $eqName = DB::table('equipment')->where('equipment_id', $eid)->value('equipment_name');
        $posName = DB::table('crew_positions')->where('position_id', $posid)->value('position_name');
        ActivityLog::record($uid, 'field_add', 'booking', 'Field add: ' . ($eqName ?? 'equipment') . " with operator ($posName) for booking $id", $id);

        return ['type' => 'success', 'text' => '<strong>' . e($eqName ?? 'Equipment') . '</strong> added and immediately released to field. Operator assigned as ' . e($posName ?? 'Crew') . '. Cost estimate updated.'];
    }

    private function checkout(Request $request, int $id, $booking, int $uid): array
    {
        $eid = (int) $request->input('equipment_id');
        $condOut = $request->input('condition_out', 'good');
        $notes = $request->input('notes', '');

        $ceConfirmed = DB::table('cost_estimates')->where('booking_id', $id)->orderByDesc('ce_id')->value('status') === 'confirmed';
        if ($booking->booking_status === 'confirmed' && ! $ceConfirmed) {
            return ['type' => 'error', 'text' => 'Cannot release — the cost estimate must be <strong>confirmed</strong> first. Use Confirm CE, then try again.'];
        }
        // Confirming the CE only sends it to the client (cost_approval_status becomes
        // 'pending_client') — it is not the client's approval. Matches the gate already
        // enforced on the Checklist OUT page (ChecklistController::index()).
        $costApproved = ($booking->cost_approval_status ?? null) === 'client_approved';
        if ($booking->booking_status === 'confirmed' && ! $costApproved) {
            return ['type' => 'error', 'text' => 'Cannot release — the client has not approved the cost estimate yet.'];
        }

        $crewCount = DB::table('booking_crew')->where('booking_id', $id)->count();
        if ($crewCount === 0) {
            return ['type' => 'error', 'text' => 'Cannot release — no crew assigned. Assign at least one crew member (operator, driver, etc.) before releasing equipment.'];
        }

        // Transport must have been explicitly reviewed at least once (even if the answer was
        // "no transport needed") — otherwise a booking nobody has looked at looks identical to
        // one that genuinely doesn't need transport, and release would slip through unnoticed.
        if (empty($booking->transport_confirmed_at)) {
            return ['type' => 'error', 'text' => 'Cannot release — transport hasn\'t been reviewed yet. Open Add/Edit Transport, even to confirm none is needed.'];
        }
        // Transport being assigned doesn't automatically mean a driver is required — staff mark
        // that explicitly on the Assign Transport form (driver_required).
        $driverNeeded = ! empty($booking->vehicle_rate_id) && (bool) ($booking->driver_required ?? false);
        if ($driverNeeded) {
            $driverCount = DB::table('booking_crew as bc')
                ->join('crew_positions as cp', 'bc.position_id', '=', 'cp.position_id')
                ->where('bc.booking_id', $id)->where(DB::raw('LOWER(cp.position_name)'), 'like', '%driver%')
                ->count();
            if (! $driverCount) {
                return ['type' => 'error', 'text' => 'Cannot release — a <strong>Driver</strong> must be assigned because this booking\'s transport requires one. Add a crew member with the Driver position, then release.'];
            }
        }

        if ($booking->client_type === 'first_time' && (float) $booking->final_amount > 0) {
            $paidAmt = (float) DB::table('payments')->where('booking_id', $id)->sum('amount');
            $required50 = (float) $booking->final_amount * 0.5;
            if ($paidAmt < $required50) {
                return ['type' => 'error', 'text' => 'Cannot release: New customer must pay at least 50% (₱' . number_format($required50, 2) . ') before equipment is released. Paid so far: ₱' . number_format($paidAmt, 2) . '.'];
            }
        }

        DB::table('equipment_transactions')->insert([
            'booking_id' => $id, 'equipment_id' => $eid, 'transaction_type' => 'checkout', 'transaction_date' => now(),
            'condition_out' => $condOut, 'notes' => $notes, 'handled_by' => $uid,
        ]);
        DB::table('equipment')->where('equipment_id', $eid)->update(['availability_status' => 'rented']);
        if ($booking->booking_status === 'confirmed') {
            DB::table('bookings')->where('booking_id', $id)->update(['booking_status' => 'ongoing', 'updated_at' => now()]);
            DB::table('quotation_log')->insert([
                'booking_id' => $id, 'log_type' => 'confirmed', 'logged_by' => $uid,
                'previous_status' => 'confirmed', 'new_status' => 'ongoing', 'log_date' => now(),
            ]);
        }
        $this->markChecklist($id, $eid, 'out', $uid, ['condition_out' => $condOut]);
        ActivityLog::record($uid, 'release', 'booking', "Equipment ID $eid released for booking $id", $id);

        return ['type' => 'success', 'text' => 'Equipment released to field. Checklist OUT recorded.'];
    }

    private function checkin(Request $request, int $id, $booking, int $uid): array
    {
        $eid = (int) $request->input('equipment_id');
        $condIn = $request->input('condition_in');
        $notes = $request->input('notes', '');

        DB::table('equipment_transactions')->insert([
            'booking_id' => $id, 'equipment_id' => $eid, 'transaction_type' => 'checkin', 'transaction_date' => now(),
            'condition_in' => $condIn, 'notes' => $notes, 'handled_by' => $uid,
        ]);
        $newEquipStatus = in_array($condIn, ['damaged', 'missing'], true) ? 'under_repair' : 'available';
        DB::table('equipment')->where('equipment_id', $eid)->update(['availability_status' => $newEquipStatus]);

        $today = now()->toDateString();
        $expectedEnd = $booking->shoot_date_end;
        $lateDays = 0;
        if ($today > $expectedEnd) {
            $lateDays = (int) max(0, floor((strtotime($today) - strtotime($expectedEnd)) / 86400));
        }
        if ($lateDays > 0) {
            $line = DB::table('booking_equipment')->where('booking_id', $id)->where('equipment_id', $eid)->select('daily_rate', 'quantity')->first();
            $penalty = $lateDays * ($line->daily_rate ?? 0) * ($line->quantity ?? 1);
            $penaltyDesc = "Late return: $lateDays day(s) × ₱" . number_format($line->daily_rate ?? 0, 2) . ' × ' . ($line->quantity ?? 1) . ' unit(s)';
            DB::table('incident_reports')->insert([
                'booking_id' => $id, 'equipment_id' => $eid, 'reported_by' => $uid, 'incident_type' => 'late_return',
                'incident_date' => now()->toDateString(), 'description' => $penaltyDesc, 'cause' => 'accident',
                'charge_amount' => $penalty, 'payment_mode' => 'lump_sum', 'status' => 'open',
            ]);
        }

        if (in_array($condIn, ['damaged', 'missing'], true)) {
            DB::table('incident_reports')->insert([
                'booking_id' => $id, 'equipment_id' => $eid, 'reported_by' => $uid, 'incident_type' => $condIn,
                'incident_date' => now()->toDateString(),
                'description' => $request->input('description', 'Reported during check-in'),
                'cause' => $request->input('cause', 'unknown'),
                'charge_amount' => (float) $request->input('charge_amount', 0),
                'payment_mode' => $request->input('payment_mode', 'lump_sum'), 'status' => 'open',
            ]);
        }

        $this->markChecklist($id, $eid, 'in', $uid, ['condition_in' => $condIn]);
        BookingCosting::updateBookingTotal($id);

        $stillOut = DB::table('booking_equipment as be')
            ->where('be.booking_id', $id)
            ->whereRaw("(SELECT COUNT(*) FROM equipment_transactions et WHERE et.booking_id=? AND et.equipment_id=be.equipment_id AND et.transaction_type='checkout') > 0", [$id])
            ->whereRaw("(SELECT COUNT(*) FROM equipment_transactions et WHERE et.booking_id=? AND et.equipment_id=be.equipment_id AND et.transaction_type='checkin') = 0", [$id])
            ->count();
        if ($stillOut === 0 && in_array($booking->booking_status, ['ongoing', 'confirmed'], true)) {
            DB::table('bookings')->where('booking_id', $id)->update(['booking_status' => 'pending_inspection', 'updated_at' => now()]);
            ActivityLog::record($uid, 'status_change', 'booking', "Booking {$booking->booking_reference} pending inspection — all equipment checked in", $id);
        }

        $lateNote = $lateDays > 0 ? " (late by $lateDays day(s), penalty added)" : '';

        return ['type' => 'success', 'text' => "Equipment returned (condition: $condIn)$lateNote. Checklist IN updated."];
    }

    private function bulkCheckout(int $id, $booking, int $uid): array
    {
        $ceConfirmed = DB::table('cost_estimates')->where('booking_id', $id)->orderByDesc('ce_id')->value('status') === 'confirmed';
        if ($booking->booking_status === 'confirmed' && ! $ceConfirmed) {
            return ['type' => 'error', 'text' => 'Cannot release — the cost estimate must be confirmed first.'];
        }
        // Same client-approval gate as checkout() / ChecklistController::index() — confirming the
        // CE only sends it to the client, it does not mean the client approved it.
        $costApproved = ($booking->cost_approval_status ?? null) === 'client_approved';
        if ($booking->booking_status === 'confirmed' && ! $costApproved) {
            return ['type' => 'error', 'text' => 'Cannot release — the client has not approved the cost estimate yet.'];
        }
        if (DB::table('booking_crew')->where('booking_id', $id)->count() === 0) {
            return ['type' => 'error', 'text' => 'Cannot release — no crew assigned.'];
        }
        if (empty($booking->transport_confirmed_at)) {
            return ['type' => 'error', 'text' => 'Cannot release — transport hasn\'t been reviewed yet.'];
        }
        if (! empty($booking->vehicle_rate_id) && (bool) ($booking->driver_required ?? false)) {
            $driverCount = DB::table('booking_crew as bc')
                ->join('crew_positions as cp', 'bc.position_id', '=', 'cp.position_id')
                ->where('bc.booking_id', $id)->where(DB::raw('LOWER(cp.position_name)'), 'like', '%driver%')
                ->count();
            if (! $driverCount) {
                return ['type' => 'error', 'text' => 'Cannot release — a Driver must be assigned because this booking\'s transport requires one.'];
            }
        }
        if ($booking->client_type === 'first_time' && (float) $booking->final_amount > 0) {
            $paidAmt = (float) DB::table('payments')->where('booking_id', $id)->sum('amount');
            if ($paidAmt < (float) $booking->final_amount * 0.5) {
                return ['type' => 'error', 'text' => 'Cannot release — 50% downpayment required for new clients.'];
            }
        }

        $pendingItems = DB::table('booking_equipment as be')
            ->where('be.booking_id', $id)
            ->whereRaw("(SELECT COUNT(*) FROM equipment_transactions et WHERE et.booking_id=? AND et.equipment_id=be.equipment_id AND et.transaction_type='checkout') = 0", [$id])
            ->select('be.equipment_id', 'be.quantity')
            ->get();

        $released = 0;
        foreach ($pendingItems as $item) {
            $eid = (int) $item->equipment_id;
            DB::table('equipment_transactions')->insert([
                'booking_id' => $id, 'equipment_id' => $eid, 'transaction_type' => 'checkout', 'transaction_date' => now(),
                'condition_out' => 'good', 'handled_by' => $uid,
            ]);
            DB::table('equipment')->where('equipment_id', $eid)->update(['availability_status' => 'rented']);
            $this->markChecklist($id, $eid, 'out', $uid, ['condition_out' => 'good'], (int) $item->quantity);
            $released++;
        }
        if ($released > 0 && $booking->booking_status === 'confirmed') {
            DB::table('bookings')->where('booking_id', $id)->update(['booking_status' => 'ongoing', 'updated_at' => now()]);
        }
        ActivityLog::record($uid, 'bulk_release', 'booking', "Bulk released $released item(s) for booking $id", $id);

        return ['type' => 'success', 'text' => "Released <strong>$released</strong> equipment item(s) to field."];
    }

    private function bulkCheckin(int $id, $booking, int $uid): array
    {
        $today = now()->toDateString();
        $inField = DB::table('booking_equipment as be')
            ->where('be.booking_id', $id)
            ->whereRaw("(SELECT COUNT(*) FROM equipment_transactions et WHERE et.booking_id=? AND et.equipment_id=be.equipment_id AND et.transaction_type='checkout') > 0", [$id])
            ->whereRaw("(SELECT COUNT(*) FROM equipment_transactions et WHERE et.booking_id=? AND et.equipment_id=be.equipment_id AND et.transaction_type='checkin') = 0", [$id])
            ->select('be.equipment_id', 'be.quantity', 'be.daily_rate')
            ->get();

        $returned = 0;
        foreach ($inField as $item) {
            $eid = (int) $item->equipment_id;
            $qty = (int) $item->quantity;
            DB::table('equipment_transactions')->insert([
                'booking_id' => $id, 'equipment_id' => $eid, 'transaction_type' => 'checkin', 'transaction_date' => now(),
                'condition_in' => 'good', 'handled_by' => $uid,
            ]);
            DB::table('equipment')->where('equipment_id', $eid)->update(['availability_status' => 'available']);
            $lateDays = $today > $booking->shoot_date_end ? (int) max(0, floor((strtotime($today) - strtotime($booking->shoot_date_end)) / 86400)) : 0;
            if ($lateDays > 0) {
                $penalty = $lateDays * (float) $item->daily_rate * $qty;
                $penaltyDesc = "Late return: $lateDays day(s) × ₱" . number_format((float) $item->daily_rate, 2) . " × $qty unit(s)";
                DB::table('incident_reports')->insert([
                    'booking_id' => $id, 'equipment_id' => $eid, 'reported_by' => $uid, 'incident_type' => 'late_return',
                    'incident_date' => now()->toDateString(), 'description' => $penaltyDesc, 'cause' => 'accident',
                    'charge_amount' => $penalty, 'payment_mode' => 'lump_sum', 'status' => 'open',
                ]);
            }
            $this->markChecklist($id, $eid, 'in', $uid, ['condition_in' => 'good'], $qty);
            $returned++;
        }
        if ($returned > 0) {
            BookingCosting::updateBookingTotal($id);
            DB::table('bookings')->where('booking_id', $id)->update(['booking_status' => 'pending_inspection', 'updated_at' => now()]);
            ActivityLog::record($uid, 'bulk_return', 'booking', "Bulk returned $returned item(s) for booking $id", $id);
        }

        return ['type' => 'success', 'text' => "Returned <strong>$returned</strong> equipment item(s). Booking moved to pending inspection."];
    }

    private function extendRental(Request $request, int $id, $booking, int $uid): ?array
    {
        $extraDays = max(1, (int) $request->input('extra_days', 0));
        if ($booking->booking_status !== 'ongoing' || $extraDays <= 0) {
            return null;
        }

        $newEnd = date('Y-m-d', strtotime($booking->shoot_date_end . " +$extraDays days"));
        DB::table('bookings')->where('booking_id', $id)->update(['shoot_date_end' => $newEnd, 'updated_at' => now()]);
        // subtotal is a STORED GENERATED column (quantity*days*daily_rate) — only `days` is set explicitly.
        // $extraDays is already forced through (int) above before interpolating into the raw expression.
        DB::table('booking_equipment')->where('booking_id', $id)->update(['days' => DB::raw("days + $extraDays")]);
        BookingCosting::generateCostEstimate($id, $uid);
        ActivityLog::record($uid, 'update', 'booking', "Extended rental by $extraDays day(s). New end: $newEnd", $id);

        return ['type' => 'success', 'text' => "Rental extended by <strong>$extraDays day(s)</strong>. New end date: <strong>" . date('M j, Y', strtotime($newEnd)) . '</strong>. Cost estimate updated.'];
    }

    private function confirmInspection(int $id, $booking): ?array
    {
        if ($booking->booking_status !== 'pending_inspection') {
            return null;
        }
        $openIncidents = DB::table('incident_reports')->where('booking_id', $id)->where('status', 'open')->count();
        if ($openIncidents > 0) {
            return ['type' => 'warning', 'text' => "There are <strong>$openIncidents open incident(s)</strong> on this booking. Please resolve them before closing inspection."];
        }
        DB::table('bookings')->where('booking_id', $id)->update(['booking_status' => 'returned', 'updated_at' => now()]);
        ActivityLog::record(Auth::id(), 'status_change', 'booking', "Booking {$booking->booking_reference} inspection confirmed — marked returned", $id);

        return ['type' => 'success', 'text' => 'Inspection confirmed. Booking marked as <strong>Returned</strong>.'];
    }

    private function completeBooking(int $id, $booking, int $uid): array
    {
        // Only reachable from the UI at 'pending_inspection' (Skip Inspection) or 'returned'
        // (normal path) — matches confirmInspection()'s own status guard, and prevents a
        // direct POST from jumping a booking straight to 'completed' from an earlier stage
        // (e.g. 'confirmed'/'ongoing') without equipment ever having been returned.
        if (! in_array($booking->booking_status, ['pending_inspection', 'returned'], true)) {
            return ['type' => 'danger', 'text' => 'Booking cannot be completed from its current status.'];
        }

        $prevStatus = $booking->booking_status;
        DB::table('bookings')->where('booking_id', $id)->update(['booking_status' => 'completed', 'updated_at' => now()]);
        DB::table('crew_members as cm')
            ->join('booking_crew as bc', 'cm.crew_id', '=', 'bc.crew_id')
            ->where('bc.booking_id', $id)->where('bc.assignment_status', 'confirmed')
            ->update(['cm.total_shoots' => DB::raw('cm.total_shoots + 1')]);
        DB::table('quotation_log')->insert([
            'booking_id' => $id, 'log_type' => 'completed', 'logged_by' => $uid,
            'previous_status' => $prevStatus, 'new_status' => 'completed', 'remarks' => 'Booking marked complete', 'log_date' => now(),
        ]);

        $stillOut = DB::table('booking_equipment as be')
            ->where('be.booking_id', $id)
            ->whereRaw("(SELECT COUNT(*) FROM equipment_transactions et WHERE et.booking_id=? AND et.equipment_id=be.equipment_id AND et.transaction_type='checkin') = 0", [$id])
            ->whereRaw("(SELECT COUNT(*) FROM equipment_transactions et WHERE et.booking_id=? AND et.equipment_id=be.equipment_id AND et.transaction_type='checkout') > 0", [$id])
            ->pluck('be.equipment_id');
        foreach ($stillOut as $eid) {
            DB::table('equipment_transactions')->insert([
                'booking_id' => $id, 'equipment_id' => $eid, 'transaction_type' => 'checkin', 'transaction_date' => now(),
                'condition_in' => 'good', 'notes' => 'Auto-returned on booking completion', 'handled_by' => $uid,
            ]);
            DB::table('equipment')->where('equipment_id', $eid)->update(['availability_status' => 'available']);
        }

        $clientId = (int) $booking->client_id;
        $completedCount = DB::table('bookings')->where('client_id', $clientId)->where('booking_status', 'completed')->count();
        $promoted = false;
        if ($completedCount >= 5) {
            $currentType = DB::table('clients')->where('client_id', $clientId)->value('client_type');
            if ($currentType === 'first_time') {
                // clients.payment_terms real enum is 50_downpayment/90_days/6_months/2_weeks_crew —
                // '90_days' is the closest match to the deferred-payment terms this promotion intends
                DB::table('clients')->where('client_id', $clientId)->update(['client_type' => 'regular', 'payment_terms' => '90_days']);
                $promoted = true;
            }
        }

        $finalAmt = (float) (DB::table('bookings')->where('booking_id', $id)->value('final_amount') ?? 0);
        $paidAmt = (float) DB::table('payments')->where('booking_id', $id)->sum('amount');
        $balance = max(0, $finalAmt - $paidAmt);
        $soaSt = $balance <= 0 ? 'paid' : 'issued';
        $fbRef = 'FB-' . date('Y') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
        $clientPayTerms = (string) DB::table('clients')->where('client_id', $clientId)->value('payment_terms');
        $soaDueDays = $clientPayTerms === '90_days' ? 90 : 7;

        $existSoa = DB::table('statement_of_accounts')->where('booking_id', $id)->value('soa_id');
        if ($existSoa) {
            DB::table('statement_of_accounts')->where('booking_id', $id)->update([
                'total_charges' => $finalAmt, 'total_payments' => $paidAmt, 'balance' => $balance, 'status' => $soaSt,
            ]);
        } else {
            DB::table('statement_of_accounts')->insert([
                'booking_id' => $id, 'soa_reference' => $fbRef, 'prepared_by' => $uid,
                'total_charges' => $finalAmt, 'total_payments' => $paidAmt, 'balance' => $balance,
                'soa_date' => now()->toDateString(), 'due_date' => now()->addDays($soaDueDays)->toDateString(), 'status' => $soaSt,
            ]);
        }

        $billingLink = " <a href='" . route('billing', ['tab' => 'soa']) . "' style='text-decoration:underline'>View Final Billing →</a>";

        return ['type' => 'success', 'text' => 'Booking completed. Final billing generated.' . ($promoted ? ' Client promoted to <strong>Regular</strong>.' : '') . $billingLink];
    }

    private function recordPayment(Request $request, int $id, int $uid): array
    {
        $ptype = $request->input('payment_type', 'final');
        $pmethod = $request->input('payment_method', 'cash');
        $amount = (float) $request->input('amount', 0);
        $ref = $request->input('reference_number', '');
        $pdate = $request->input('payment_date', now()->toDateString());
        $isVat = $request->boolean('is_vat') ? 1 : 0;
        $rctype = $isVat ? 'official_receipt' : 'acknowledgement_receipt';
        $rcPrefix = $isVat ? 'OR' : 'AR';
        $notes = $request->input('notes', '');

        if ($amount <= 0) {
            return ['type' => 'danger', 'text' => 'Payment amount must be greater than zero.'];
        }

        // The remaining-balance check and the insert both happen inside the same
        // booking-row-locked transaction — otherwise two near-simultaneous payment
        // submissions for the same booking could each read the same stale "amount paid
        // so far", both pass the "doesn't exceed the balance" check, and both insert,
        // together overpaying the booking. Locking the bookings row for this id
        // serializes any concurrent recordPayment() calls for the same booking.
        $error = DB::transaction(function () use ($rctype, $rcPrefix, $id, $ptype, $pmethod, $amount, $ref, $pdate, $uid, $isVat, $notes) {
            $bkTotal = (float) (DB::table('bookings')->where('booking_id', $id)->lockForUpdate()->value('final_amount') ?? 0);
            $bkPaid = (float) DB::table('payments')->where('booking_id', $id)->sum('amount');
            $remaining = round($bkTotal - $bkPaid, 2);
            if ($bkTotal > 0 && $amount > $remaining + 0.005) {
                return ['type' => 'danger', 'text' => 'Payment of <strong>₱' . number_format($amount, 2) . '</strong> exceeds the remaining balance of <strong>₱' . number_format(max(0, $remaining), 2) . '</strong>.'];
            }

            $rcSeq = (int) DB::table('payments')->where('receipt_type', $rctype)->lockForUpdate()->count() + 1;
            $rcnum = $rcPrefix . '-' . str_pad((string) $rcSeq, 5, '0', STR_PAD_LEFT);

            DB::table('payments')->insert([
                'booking_id' => $id, 'payment_type' => $ptype, 'payment_method' => $pmethod, 'amount' => $amount,
                'reference_number' => $ref, 'payment_date' => $pdate, 'received_by' => $uid, 'is_vat' => $isVat,
                'receipt_number' => $rcnum, 'receipt_type' => $rctype, 'notes' => $notes,
            ]);

            return null;
        });

        if ($error !== null) {
            return $error;
        }

        $paid = (float) DB::table('payments')->where('booking_id', $id)->sum('amount');
        $total = (float) (DB::table('bookings')->where('booking_id', $id)->value('final_amount') ?? 0);
        $currentPaySt = (string) DB::table('bookings')->where('booking_id', $id)->value('payment_status');
        if (! in_array($currentPaySt, ['refunded', 'cancelled'], true)) {
            $payStatus = $paid <= 0 ? 'unpaid' : ($total > 0 && $paid >= $total ? 'paid' : 'partial');
            DB::table('bookings')->where('booking_id', $id)->update(['payment_status' => $payStatus, 'updated_at' => now()]);
        }

        $balance = max(0, $total - $paid);
        $soaSt2 = $balance <= 0 ? 'paid' : 'issued';
        $soaId = DB::table('statement_of_accounts')->where('booking_id', $id)->value('soa_id');
        if ($soaId) {
            DB::table('statement_of_accounts')->where('soa_id', $soaId)->update([
                'total_payments' => $paid, 'balance' => $balance, 'status' => $soaSt2,
            ]);
        }

        ActivityLog::record($uid, 'create', 'payment', 'Payment ₱' . number_format($amount, 2) . " recorded for booking $id", $id);

        return ['type' => 'success', 'text' => 'Payment of <strong>₱' . number_format($amount, 2) . '</strong> recorded.'];
    }

    private function batchAddCrew(Request $request, int $id, $booking): array
    {
        $bcCids = $request->input('bc_crew_id', []);
        $bcPids = $request->input('bc_pos_id', []);
        $bcRates = $request->input('bc_rate', []);
        $bcEqs = $request->input('bc_eq_link', []);
        $bcNotes = $request->input('bc_notes', []);
        $added = 0;
        $skipReasons = [];
        $bkDays = max(1, (int) round((strtotime($booking->shoot_date_end) - strtotime($booking->shoot_date_start)) / 86400) + 1);

        foreach ($bcCids as $i => $rawCid) {
            $cid = (int) $rawCid;
            if (! $cid) {
                continue;
            }
            $posid = (int) ($bcPids[$i] ?? 0);
            $rate = (float) ($bcRates[$i] ?? 0);
            $eqLink = (int) ($bcEqs[$i] ?? 0);
            $note = $bcNotes[$i] ?? '';

            $cName = DB::table('crew_members')->where('crew_id', $cid)->selectRaw("CONCAT(first_name,' ',last_name) AS n")->value('n');
            if ($rate <= 0) {
                $rate = (float) DB::table('crew_members')->where('crew_id', $cid)->value('base_rate_12hr');
            }
            if (DB::table('booking_crew')->where('booking_id', $id)->where('crew_id', $cid)->exists()) {
                $skipReasons[] = '<strong>' . e($cName) . '</strong>: already assigned';
                continue;
            }
            $crewStatus = DB::table('crew_members')->where('crew_id', $cid)->value('status');
            if ($crewStatus !== 'active') {
                $skipReasons[] = '<strong>' . e($cName) . '</strong>: ' . ucfirst($crewStatus);
                continue;
            }
            $conflictRef = DB::table('booking_crew as bc')
                ->join('bookings as b', 'bc.booking_id', '=', 'b.booking_id')
                ->where('bc.crew_id', $cid)->where('bc.booking_id', '!=', $id)
                ->whereNotIn('b.booking_status', ['cancelled', 'completed'])
                ->where('b.shoot_date_start', '<=', $booking->shoot_date_end)
                ->where('b.shoot_date_end', '>=', $booking->shoot_date_start)
                ->value('b.booking_reference');
            if ($conflictRef) {
                $skipReasons[] = '<strong>' . e($cName) . '</strong>: busy on ' . $conflictRef;
                continue;
            }
            $unavail = DB::table('crew_unavailability')->where('crew_id', $cid)
                ->where('date_from', '<=', $booking->shoot_date_end)->where('date_to', '>=', $booking->shoot_date_start)
                ->select('date_from', 'date_to')->first();
            if ($unavail) {
                $skipReasons[] = '<strong>' . e($cName) . '</strong>: marked off ' . $unavail->date_from . ' – ' . $unavail->date_to;
                continue;
            }

            DB::table('booking_crew')->insert([
                'booking_id' => $id, 'crew_id' => $cid, 'position_id' => $posid ?: null, 'rate_used' => $rate,
                'hours_worked' => $bkDays, 'notes' => $note, 'equipment_id' => $eqLink ?: null,
            ]);
            $added++;
        }

        if ($added > 0) {
            BookingCosting::generateCostEstimate($id, Auth::id());
            if (($booking->cost_approval_status ?? null) === 'client_approved') {
                DB::table('bookings')->where('booking_id', $id)->update(['cost_approval_status' => 'pending_client', 'updated_at' => now()]);
            }
            ActivityLog::record(Auth::id(), 'assign_crew', 'booking', "Batch assigned $added crew member(s) to booking $id", $id);
            $skipDetail = $skipReasons ? '<br><span style="font-size:.8rem">Skipped: ' . implode(', ', $skipReasons) . '</span>' : '';

            return ['type' => 'success', 'text' => "<strong>$added</strong> crew member(s) assigned.$skipDetail Cost estimate updated."];
        }

        $skipDetail = $skipReasons ? '<br><span style="font-size:.8rem">' . implode('<br>', $skipReasons) . '</span>' : '';

        return ['type' => 'error', 'text' => 'No crew assigned.' . $skipDetail];
    }

    private function addCrew(Request $request, int $id, $booking): array
    {
        // Historical-analytics protection (Part 11 panelist revision): a completed shoot's crew
        // roster is what Crew Analytics reports against — changing it after the fact would
        // silently rewrite history for a shoot that already happened.
        if (($booking->booking_status ?? null) === 'completed') {
            return ['type' => 'error', 'text' => 'This booking is completed — its crew roster is locked to protect historical analytics.'];
        }

        $cid = (int) $request->input('crew_id');
        $posid = (int) $request->input('position_id');
        $rate = (float) $request->input('rate_used');
        $notes = $request->input('notes', '');
        $eqLink = (int) $request->input('equipment_id_link', 0);

        if (DB::table('booking_crew')->where('booking_id', $id)->where('crew_id', $cid)->exists()) {
            return ['type' => 'error', 'text' => 'This crew member is already assigned to this booking.'];
        }

        $crewStatus = DB::table('crew_members')->where('crew_id', $cid)->value('status');
        if ($crewStatus !== 'active') {
            return ['type' => 'error', 'text' => 'This crew member is not active (status: ' . ucfirst($crewStatus) . ').'];
        }

        $crewConflictRef = DB::table('booking_crew as bc')
            ->join('bookings as b', 'bc.booking_id', '=', 'b.booking_id')
            ->where('bc.crew_id', $cid)->where('bc.booking_id', '!=', $id)
            ->whereNotIn('b.booking_status', ['cancelled', 'completed'])
            ->where('b.shoot_date_start', '<=', $booking->shoot_date_end)
            ->where('b.shoot_date_end', '>=', $booking->shoot_date_start)
            ->value('b.booking_reference');
        $unavailBlock = DB::table('crew_unavailability')->where('crew_id', $cid)
            ->where('date_from', '<=', $booking->shoot_date_end)->where('date_to', '>=', $booking->shoot_date_start)
            ->select('date_from', 'date_to', 'reason')->first();

        $cName = DB::table('crew_members')->where('crew_id', $cid)->selectRaw("CONCAT(first_name,' ',last_name) AS n")->value('n');
        if ($crewConflictRef) {
            return ['type' => 'error', 'text' => '<strong>' . e($cName) . '</strong> is already assigned to booking <strong>' . e($crewConflictRef) . '</strong> on overlapping dates.'];
        }
        if ($unavailBlock) {
            $bReason = $unavailBlock->reason ? ' (' . e($unavailBlock->reason) . ')' : '';

            return ['type' => 'error', 'text' => '<strong>' . e($cName) . '</strong> has a personal unavailability block on <strong>' . e($unavailBlock->date_from . ' – ' . $unavailBlock->date_to) . '</strong>' . $bReason . '. Remove the block first if this assignment is approved.'];
        }

        $bkDays = max(1, (int) round((strtotime($booking->shoot_date_end) - strtotime($booking->shoot_date_start)) / 86400) + 1);
        DB::table('booking_crew')->insert([
            'booking_id' => $id, 'crew_id' => $cid, 'position_id' => $posid, 'rate_used' => $rate,
            'hours_worked' => $bkDays, 'notes' => $notes, 'equipment_id' => $eqLink ?: null,
        ]);
        BookingCosting::generateCostEstimate($id, Auth::id());
        if (($booking->cost_approval_status ?? null) === 'client_approved') {
            DB::table('bookings')->where('booking_id', $id)->update(['cost_approval_status' => 'pending_client', 'updated_at' => now()]);
        }
        $posName = DB::table('crew_positions')->where('position_id', $posid)->value('position_name');
        $eqName = $eqLink ? DB::table('equipment')->where('equipment_id', $eqLink)->value('equipment_name') : null;
        $extra = $eqName ? " → assigned to <strong>$eqName</strong>" : '';

        return ['type' => 'success', 'text' => 'Crew member assigned as ' . e($posName ?? 'Crew') . $extra . '. Cost estimate updated.'];
    }

    private function updateCrewStatus(Request $request, int $id): array
    {
        $bcid = (int) $request->input('bk_crew_id');
        $status = $request->input('assignment_status');
        DB::table('booking_crew')->where('bk_crew_id', $bcid)->where('booking_id', $id)->update(['assignment_status' => $status]);

        return ['type' => 'success', 'text' => 'Crew status updated.'];
    }

    private function removeCrew(Request $request, int $id, int $uid): array
    {
        // Same historical-analytics protection as addCrew() — see its comment.
        if (DB::table('bookings')->where('booking_id', $id)->value('booking_status') === 'completed') {
            return ['type' => 'error', 'text' => 'This booking is completed — its crew roster is locked to protect historical analytics.'];
        }

        $bcid = (int) $request->input('bk_crew_id');
        $crewName = DB::table('booking_crew as bc')
            ->join('crew_members as cm', 'bc.crew_id', '=', 'cm.crew_id')
            ->where('bc.bk_crew_id', $bcid)->where('bc.booking_id', $id)
            ->selectRaw("CONCAT(cm.first_name,' ',cm.last_name) AS n")->value('n');

        if (! $crewName) {
            return ['type' => 'error', 'text' => 'Crew assignment not found.'];
        }

        DB::table('booking_crew')->where('bk_crew_id', $bcid)->where('booking_id', $id)->delete();
        BookingCosting::generateCostEstimate($id, $uid);
        ActivityLog::record($uid, 'remove_crew', 'booking', 'Removed ' . $crewName . " from booking $id", $id);

        return ['type' => 'success', 'text' => '<strong>' . e($crewName) . '</strong> removed from booking. Cost estimate updated.'];
    }

    private function requestCancellation(Request $request, int $id, $booking, string $role, int $uid): array
    {
        $reqType = $request->input('request_type', 'client_request');
        $reason = $request->input('reason', '');

        $hasReleased = DB::table('equipment_transactions')->where('booking_id', $id)->where('transaction_type', 'checkout')->count();
        if ($hasReleased > 0 && $reqType !== 'on_field') {
            $reqType = 'on_field';
        }

        if ($reqType === 'on_field' && in_array($role, ['super_admin', 'admin', 'operations_manager'], true)) {
            $finalAmt = (float) $booking->final_amount;
            $paidAmt = (float) DB::table('payments')->where('booking_id', $id)->sum('amount');
            $penaltyAmt = round($finalAmt * 0.50, 2);
            $refundAmt = max(0, round($paidAmt - $penaltyAmt, 2));

            DB::table('booking_cancellations')->insert([
                'booking_id' => $id, 'requested_by' => $uid, 'request_type' => 'on_field', 'reason' => $reason,
                'status' => 'approved', 'penalty_rate' => 0.5000, 'penalty_amount' => $penaltyAmt,
                'refund_amount' => $refundAmt, 'approved_by' => $uid, 'approved_at' => now(),
            ]);
            DB::table('bookings')->where('booking_id', $id)->update([
                'booking_status' => 'cancelled', 'cancelled_by' => $uid, 'cancellation_reason' => $reason,
                'payment_status' => $refundAmt > 0 ? 'refunded' : 'cancelled', 'updated_at' => now(),
            ]);
            DB::table('equipment')->whereIn('equipment_id', function ($q) use ($id) {
                $q->select('equipment_id')->from('booking_equipment')->where('booking_id', $id);
            })->update(['availability_status' => 'available']);
            DB::table('quotation_log')->insert([
                'booking_id' => $id, 'log_type' => 'cancelled', 'logged_by' => $uid,
                'previous_status' => $booking->booking_status, 'new_status' => 'cancelled',
                'remarks' => 'On-field cancellation — 50% penalty applied', 'log_date' => now(),
            ]);
            ActivityLog::record($uid, 'cancel', 'booking', "On-field cancellation booking $id, penalty ₱$penaltyAmt", $id);

            return ['type' => 'success', 'text' => "On-field cancellation processed. Penalty: <strong>₱" . number_format($penaltyAmt, 2) . '</strong>.' . ($refundAmt > 0 ? ' Refund due: <strong>₱' . number_format($refundAmt, 2) . '</strong>.' : '')];
        }

        if (DB::table('booking_cancellations')->where('booking_id', $id)->where('status', 'pending')->exists()) {
            return ['type' => 'danger', 'text' => 'A cancellation request is already pending for this booking.'];
        }

        DB::table('booking_cancellations')->insert([
            'booking_id' => $id, 'requested_by' => $uid, 'request_type' => $reqType, 'reason' => $reason,
        ]);
        ActivityLog::record($uid, 'cancel_request', 'booking', "Cancellation requested for booking $id", $id);

        return ['type' => 'success', 'text' => 'Cancellation request submitted for admin review.'];
    }

    private function approveCancellation(Request $request, int $id, $booking, int $uid): array
    {
        $canId = (int) $request->input('cancellation_id');
        $penaltyPct = min(100, max(0, (float) $request->input('penalty_percent', 0)));
        $notes = $request->input('notes', '');

        $finalAmt = (float) $booking->final_amount;
        $paidAmt = (float) DB::table('payments')->where('booking_id', $id)->sum('amount');
        $penaltyAmt = round($finalAmt * ($penaltyPct / 100), 2);
        $refundAmt = max(0, round($paidAmt - $penaltyAmt, 2));
        $penaltyRate = round($penaltyPct / 100, 4);

        DB::table('booking_cancellations')->where('cancellation_id', $canId)->where('booking_id', $id)->update([
            'status' => 'approved', 'penalty_rate' => $penaltyRate, 'penalty_amount' => $penaltyAmt,
            'refund_amount' => $refundAmt, 'approved_by' => $uid, 'approved_at' => now(), 'notes' => $notes,
        ]);
        DB::table('bookings')->where('booking_id', $id)->update([
            'booking_status' => 'cancelled', 'cancelled_by' => $uid,
            'payment_status' => $refundAmt > 0 ? 'refunded' : 'cancelled', 'updated_at' => now(),
        ]);
        DB::table('equipment')->whereIn('equipment_id', function ($q) use ($id) {
            $q->select('equipment_id')->from('booking_equipment')->where('booking_id', $id);
        })->update(['availability_status' => 'available']);
        DB::table('quotation_log')->insert([
            'booking_id' => $id, 'log_type' => 'cancelled', 'logged_by' => $uid,
            'previous_status' => $booking->booking_status, 'new_status' => 'cancelled',
            'remarks' => 'Cancellation approved', 'log_date' => now(),
        ]);
        ActivityLog::record($uid, 'approve_cancellation', 'booking', "Approved cancellation booking $id, penalty ₱$penaltyAmt", $id);

        return ['type' => 'success', 'text' => "Cancellation approved. Penalty: <strong>₱" . number_format($penaltyAmt, 2) . '</strong>.' . ($refundAmt > 0 ? ' Refund due: <strong>₱' . number_format($refundAmt, 2) . '</strong>.' : '')];
    }

    private function rejectCancellation(Request $request, int $id, int $uid): array
    {
        $canId = (int) $request->input('cancellation_id');
        $notes = $request->input('notes', '');

        DB::table('booking_cancellations')->where('cancellation_id', $canId)->where('booking_id', $id)->update([
            'status' => 'rejected', 'approved_by' => $uid, 'approved_at' => now(), 'notes' => $notes,
        ]);
        ActivityLog::record($uid, 'reject_cancellation', 'booking', "Rejected cancellation request for booking $id", $id);

        return ['type' => 'success', 'text' => 'Cancellation request rejected. Booking remains active.'];
    }

    private function postComment(Request $request, int $id, int $uid): array
    {
        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            return ['type' => 'danger', 'text' => 'Comment cannot be empty.'];
        }

        DB::table('booking_comments')->insert([
            'booking_id' => $id, 'user_id' => $uid, 'body' => $body,
            'is_internal' => $request->boolean('is_internal'), 'created_at' => now(),
        ]);

        return ['type' => 'success', 'text' => 'Comment posted.'];
    }

    private function duplicateBooking(Request $request, int $sourceId, $sourceBooking, $user, string $role): RedirectResponse
    {
        $ds = $request->input('new_shoot_date_start', '');
        $de = $request->input('new_shoot_date_end', '');

        $fail = function (string $text) use ($request, $sourceId) {
            $request->session()->flash('bd_flash', ['type' => 'danger', 'text' => $text]);

            return redirect()->route('booking-detail', $sourceId);
        };

        if (! $ds || ! $de) {
            return $fail('New shoot start and end dates are required.');
        }
        if (strtotime($de) < strtotime($ds)) {
            return $fail('End date must be on or after the start date.');
        }
        if (! in_array($role, ['super_admin', 'admin'], true) && strtotime($ds) < strtotime('+48 hours')) {
            return $fail('Bookings must be made at least <strong>48 hours in advance</strong>.');
        }
        if ((strtotime($de) - strtotime($ds)) / 86400 > 90) {
            return $fail('Rental period cannot exceed 3 months (90 days).');
        }

        $clientId = $sourceBooking->client_id;
        $overlap = DB::table('bookings')->where('client_id', $clientId)
            ->whereNotIn('booking_status', ['completed', 'cancelled'])
            ->where(function ($w) use ($ds, $de) {
                $w->whereBetween('shoot_date_start', [$ds, $de])
                    ->orWhereBetween('shoot_date_end', [$ds, $de])
                    ->orWhereRaw('? BETWEEN shoot_date_start AND shoot_date_end', [$ds]);
            })->exists();
        if ($overlap) {
            return $fail('This client already has a booking with overlapping dates.');
        }

        $ref = $this->generateBookingRef();
        if (DB::table('bookings')->where('booking_reference', $ref)->exists()) {
            $ref = 'FS-' . date('Y') . '-' . str_pad((string) (time() % 10000), 4, '0', STR_PAD_LEFT);
        }

        $zone = $sourceBooking->location_zone;
        $zoneMultMap = ['manila' => 1.0, 'luzon' => 1.5, 'luzon_far' => 2.0];
        $multiplier = $zoneMultMap[$zone] ?? 1.0;
        $vid = $sourceBooking->vehicle_rate_id;
        // Transport cost is manually entered, not derived — a duplicated booking carries over
        // what the source booking was actually charged, not a freshly-recomputed guess.
        $transCost = (float) ($sourceBooking->transportation_cost ?? 0);

        $approvalSt = $role === 'traffic' ? 'pending_approval' : 'approved';

        $newId = DB::table('bookings')->insertGetId([
            'booking_reference' => $ref, 'client_id' => $clientId,
            'booking_type' => $sourceBooking->booking_type, 'project_title' => $sourceBooking->project_title,
            'project_type' => $sourceBooking->project_type, 'shoot_date_start' => $ds, 'shoot_date_end' => $de,
            'shoot_location' => $sourceBooking->shoot_location, 'transportation_cost' => $transCost,
            'delivery_address' => $sourceBooking->delivery_address, 'notes' => $sourceBooking->notes,
            'created_by' => $user->user_id, 'location_zone' => $zone, 'transport_multiplier' => $multiplier,
            'location_lat' => $sourceBooking->location_lat, 'location_lng' => $sourceBooking->location_lng,
            'vehicle_rate_id' => $vid, 'driver_required' => (bool) ($sourceBooking->driver_required ?? false), 'approval_status' => $approvalSt,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('quotation_log')->insert([
            'booking_id' => $newId, 'log_type' => 'created', 'logged_by' => $user->user_id,
            'new_status' => 'pending', 'log_date' => now(),
            'remarks' => "Duplicated from booking {$sourceBooking->booking_reference}",
        ]);

        $newDays = max(1, (int) round((strtotime($de) - strtotime($ds)) / 86400) + 1);

        $eqCopied = 0;
        $eqSkipped = [];
        $copiedEquipIds = [];
        foreach (DB::table('booking_equipment')->where('booking_id', $sourceId)->get() as $line) {
            $status = DB::table('equipment')->where('equipment_id', $line->equipment_id)->value('availability_status');
            $conflict = DB::table('booking_equipment as be')
                ->join('bookings as b', 'be.booking_id', '=', 'b.booking_id')
                ->where('be.equipment_id', $line->equipment_id)
                ->whereNotIn('b.booking_status', ['cancelled', 'completed'])
                ->where('b.shoot_date_start', '<=', $de)->where('b.shoot_date_end', '>=', $ds)
                ->exists();
            $name = DB::table('equipment')->where('equipment_id', $line->equipment_id)->value('equipment_name');
            if ($status === 'available' && ! $conflict) {
                DB::table('booking_equipment')->insert([
                    'booking_id' => $newId, 'equipment_id' => $line->equipment_id, 'quantity' => $line->quantity,
                    'days' => $newDays, 'daily_rate' => $line->daily_rate, 'notes' => $line->notes,
                ]);
                $eqCopied++;
                $copiedEquipIds[] = $line->equipment_id;
            } else {
                $eqSkipped[] = $name;
            }
        }

        $crewCopied = 0;
        $crewSkipped = [];
        foreach (DB::table('booking_crew')->where('booking_id', $sourceId)->get() as $line) {
            $crewStatus = DB::table('crew_members')->where('crew_id', $line->crew_id)->value('status');
            $conflict = DB::table('booking_crew as bc')
                ->join('bookings as b', 'bc.booking_id', '=', 'b.booking_id')
                ->where('bc.crew_id', $line->crew_id)
                ->whereNotIn('b.booking_status', ['cancelled', 'completed'])
                ->where('b.shoot_date_start', '<=', $de)->where('b.shoot_date_end', '>=', $ds)
                ->exists();
            $unavail = DB::table('crew_unavailability')->where('crew_id', $line->crew_id)
                ->where('date_from', '<=', $de)->where('date_to', '>=', $ds)->exists();
            $name = DB::table('crew_members')->where('crew_id', $line->crew_id)->selectRaw("CONCAT(first_name,' ',last_name) AS n")->value('n');
            if ($crewStatus === 'active' && ! $conflict && ! $unavail) {
                DB::table('booking_crew')->insert([
                    'booking_id' => $newId, 'crew_id' => $line->crew_id, 'position_id' => $line->position_id,
                    'rate_used' => $line->rate_used, 'hours_worked' => $newDays, 'notes' => $line->notes,
                    'equipment_id' => in_array($line->equipment_id, $copiedEquipIds, true) ? $line->equipment_id : null,
                ]);
                $crewCopied++;
            } else {
                $crewSkipped[] = $name;
            }
        }

        BookingCosting::generateCostEstimate($newId, $user->user_id);
        ActivityLog::record($user->user_id, 'create', 'booking', "Booking $ref created by duplicating {$sourceBooking->booking_reference}", $newId);

        $summary = "Booking <strong>$ref</strong> created from <strong>{$sourceBooking->booking_reference}</strong> — copied $eqCopied equipment item(s) and $crewCopied crew member(s).";
        if ($eqSkipped) {
            $summary .= ' Skipped equipment (unavailable or conflicting): ' . e(implode(', ', array_filter($eqSkipped))) . '.';
        }
        if ($crewSkipped) {
            $summary .= ' Skipped crew (unavailable or conflicting): ' . e(implode(', ', array_filter($crewSkipped))) . '.';
        }

        $request->session()->flash('bd_flash', ['type' => 'success', 'text' => $summary]);

        return redirect()->route('booking-detail', $newId);
    }

    private function generateBookingRef(): string
    {
        $year = date('Y');
        $count = (int) DB::table('bookings')->whereYear('created_at', $year)->count();

        return 'FS-' . $year . '-' . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    private function approveExtension(Request $request, int $id, $booking, int $uid): ?array
    {
        $extId = (int) $request->input('extension_id');
        $notes = $request->input('admin_notes', '');
        $ext = DB::table('booking_extension_requests')->where('extension_id', $extId)->where('booking_id', $id)->first();
        if (! $ext || $ext->status !== 'pending') {
            return null;
        }

        $newEnd = $ext->requested_end_date;
        DB::table('bookings')->where('booking_id', $id)->update(['shoot_date_end' => $newEnd, 'updated_at' => now()]);

        $newDays = max(1, (new \DateTime($booking->shoot_date_start))->diff(new \DateTime($newEnd))->days + 1);

        // subtotal is a STORED GENERATED column (quantity*days*daily_rate) — only `days` is set explicitly
        DB::table('booking_equipment')->where('booking_id', $id)->update(['days' => $newDays]);

        $accLines = DB::table('booking_accessories')->where('booking_id', $id)->get();
        foreach ($accLines as $al) {
            $newAccSub = $al->quantity * $newDays * (float) $al->daily_rate;
            DB::table('booking_accessories')->where('ba_id', $al->ba_id)->update(['days' => $newDays, 'subtotal' => $newAccSub]);
        }

        DB::table('booking_extension_requests')->where('extension_id', $extId)->update([
            'status' => 'approved', 'approved_by' => $uid, 'approved_at' => now(), 'admin_notes' => $notes,
        ]);
        BookingCosting::updateBookingTotal($id);
        BookingCosting::generateCostEstimate($id, $uid);
        ActivityLog::record($uid, 'approve', 'booking', "Extension approved: booking $id extended to $newEnd", $id);

        return ['type' => 'success', 'text' => "Rental extended to <strong>$newEnd</strong>. Costs recalculated."];
    }

    private function rejectExtension(Request $request, int $id, int $uid): array
    {
        $extId = (int) $request->input('extension_id');
        $notes = $request->input('admin_notes', '');

        DB::table('booking_extension_requests')->where('extension_id', $extId)->where('booking_id', $id)->update([
            'status' => 'rejected', 'approved_by' => $uid, 'approved_at' => now(), 'admin_notes' => $notes,
        ]);
        ActivityLog::record($uid, 'reject', 'booking', "Extension request rejected for booking $id", $id);

        return ['type' => 'success', 'text' => 'Extension request rejected.'];
    }

    /**
     * Approving an equipment/accessory field request only marks it approved — the actual
     * booking_equipment/booking_accessories insert (and the physical checkout side-effects for
     * equipment) happens at the Dispatch step on the cross-booking Field Requests queue
     * (FieldRequestsController), matching fieldAddEquipment()'s "released to field" semantics.
     * Crew is different: there's no physical inventory checkout for a person, so approving a
     * crew request immediately books them (admin picks the real crew_id here — the client only
     * requested a position/role) via booking_crew, same as approve elsewhere in this class.
     */
    private function approveFieldRequest(Request $request, int $id, $booking, int $uid): ?array
    {
        $reqId = (int) $request->input('request_id');
        $notes = $request->input('admin_notes', '');
        $req = DB::table('booking_equipment_requests')->where('request_id', $reqId)->where('booking_id', $id)->first();
        if (! $req || $req->status !== 'pending') {
            return null;
        }

        if ($req->item_type === 'crew') {
            $crewId = (int) $request->input('crew_id', 0);
            if (! $crewId) {
                return ['type' => 'danger', 'text' => 'Select a crew member to assign to this role.'];
            }
            $crew = DB::table('crew_members')->where('crew_id', $crewId)->first();
            if (! $crew || $crew->status !== 'active') {
                return ['type' => 'danger', 'text' => 'Selected crew member is not active.'];
            }

            $numDays = max(1, (new \DateTime($booking->shoot_date_start))->diff(new \DateTime($booking->shoot_date_end))->days + 1);
            $rate = (float) $crew->base_rate_12hr;
            $exists = DB::table('booking_crew')->where('booking_id', $id)->where('crew_id', $crewId)->exists();
            if (! $exists) {
                // Same double-booking + personal-unavailability checks as addCrew()/batchAddCrew() —
                // approving a field request must not be able to book a crew member who is already
                // committed elsewhere on overlapping dates.
                $crewConflictRef = DB::table('booking_crew as bc')
                    ->join('bookings as b', 'bc.booking_id', '=', 'b.booking_id')
                    ->where('bc.crew_id', $crewId)->where('bc.booking_id', '!=', $id)
                    ->whereNotIn('b.booking_status', ['cancelled', 'completed'])
                    ->where('b.shoot_date_start', '<=', $booking->shoot_date_end)
                    ->where('b.shoot_date_end', '>=', $booking->shoot_date_start)
                    ->value('b.booking_reference');
                if ($crewConflictRef) {
                    return ['type' => 'danger', 'text' => '<strong>' . e(trim($crew->first_name . ' ' . $crew->last_name)) . '</strong> is already assigned to booking <strong>' . e($crewConflictRef) . '</strong> on overlapping dates.'];
                }
                $unavailBlock = DB::table('crew_unavailability')->where('crew_id', $crewId)
                    ->where('date_from', '<=', $booking->shoot_date_end)->where('date_to', '>=', $booking->shoot_date_start)
                    ->select('date_from', 'date_to', 'reason')->first();
                if ($unavailBlock) {
                    $bReason = $unavailBlock->reason ? ' (' . e($unavailBlock->reason) . ')' : '';

                    return ['type' => 'danger', 'text' => '<strong>' . e(trim($crew->first_name . ' ' . $crew->last_name)) . '</strong> has a personal unavailability block on <strong>' . e($unavailBlock->date_from . ' – ' . $unavailBlock->date_to) . '</strong>' . $bReason . '.'];
                }

                DB::table('booking_crew')->insert([
                    'booking_id' => $id, 'crew_id' => $crewId, 'position_id' => $req->position_id,
                    'rate_used' => $rate, 'hours_worked' => $numDays, 'notes' => 'Field request: ' . ($req->reason ?? ''),
                ]);
            } else {
                DB::table('booking_crew')->where('booking_id', $id)->where('crew_id', $crewId)
                    ->update(['rate_used' => $rate, 'hours_worked' => $numDays]);
            }

            DB::table('booking_equipment_requests')->where('request_id', $reqId)->update([
                'status' => 'approved', 'crew_id' => $crewId, 'approved_by' => $uid, 'approved_at' => now(), 'admin_notes' => $notes,
            ]);
            BookingCosting::generateCostEstimate($id, $uid);
            $crewName = trim($crew->first_name . ' ' . $crew->last_name);
            ActivityLog::record($uid, 'approve', 'booking', "Field request approved: assigned crew $crewName to booking $id", $id);

            return ['type' => 'success', 'text' => e($crewName) . ' assigned. Costs recalculated.'];
        }

        // equipment / accessory — no insert yet, that happens at Dispatch
        DB::table('booking_equipment_requests')->where('request_id', $reqId)->update([
            'status' => 'approved', 'approved_by' => $uid, 'approved_at' => now(), 'admin_notes' => $notes,
        ]);
        $label = $req->item_type === 'accessory' ? 'Accessory' : 'Equipment';
        ActivityLog::record($uid, 'approve', 'booking', "Field request approved ($label) for booking $id", $id);

        return ['type' => 'success', 'text' => "$label request approved. It will be added to the booking when dispatched to the field."];
    }

    private function rejectFieldRequest(Request $request, int $id, int $uid): ?array
    {
        $reqId = (int) $request->input('request_id');
        $notes = $request->input('admin_notes', '');
        $req = DB::table('booking_equipment_requests')->where('request_id', $reqId)->where('booking_id', $id)->first();
        if (! $req || $req->status !== 'pending') {
            return null;
        }

        DB::table('booking_equipment_requests')->where('request_id', $reqId)->where('booking_id', $id)->update([
            'status' => 'rejected', 'approved_by' => $uid, 'approved_at' => now(), 'admin_notes' => $notes,
        ]);
        ActivityLog::record($uid, 'reject', 'booking', 'Field request rejected for booking ' . $id, $id);

        $label = ucfirst($req->item_type);

        return ['type' => 'success', 'text' => "$label request rejected."];
    }

    private function suggestedAccessories(int $id): array
    {
        $onBooking = DB::table('booking_accessories')->where('booking_id', $id)->pluck('accessory_id');
        $eids = DB::table('booking_equipment')->where('booking_id', $id)->pluck('equipment_id');
        if ($eids->isEmpty()) {
            return [];
        }

        return DB::table('accessories as a')
            ->join('equipment_accessory_links as eal', 'eal.accessory_id', '=', 'a.accessory_id')
            ->join('equipment as e', 'e.equipment_id', '=', 'eal.equipment_id')
            ->whereIn('eal.equipment_id', $eids)
            ->whereNotIn('a.accessory_id', $onBooking)
            ->groupBy('a.accessory_id', 'a.accessory_name', 'a.daily_rate', 'a.is_included', 'a.quantity')
            ->orderBy('a.accessory_name')
            ->select('a.accessory_id', 'a.accessory_name', 'a.daily_rate', 'a.is_included', 'a.quantity')
            ->selectRaw('COALESCE((SELECT SUM(ba2.quantity) FROM booking_accessories ba2
                JOIN bookings b2 ON ba2.booking_id=b2.booking_id
                WHERE ba2.accessory_id=a.accessory_id
                AND b2.booking_status NOT IN (\'cancelled\',\'completed\')
                AND ba2.booking_id != ?),0) AS qty_in_use', [$id])
            ->selectRaw('GROUP_CONCAT(DISTINCT e.equipment_name ORDER BY e.equipment_name SEPARATOR \', \') AS linked_to')
            ->get()
            ->toArray();
    }

    private function addBookingAccessory(Request $request, int $id): array
    {
        $aid = (int) $request->input('accessory_id', 0);
        $qty = max(1, (int) $request->input('quantity', 1));
        $days = max(1, (int) $request->input('days', 1));
        $notes = $request->input('notes', '');

        if (! $aid) {
            return ['type' => 'error', 'text' => 'Please select an accessory.'];
        }

        $acc = DB::table('accessories')->where('accessory_id', $aid)->first();
        if (! $acc) {
            return ['type' => 'error', 'text' => 'Accessory not found.'];
        }
        if (DB::table('booking_accessories')->where('booking_id', $id)->where('accessory_id', $aid)->exists()) {
            return ['type' => 'error', 'text' => 'This accessory is already added to this booking.'];
        }

        $inUse = (int) DB::table('booking_accessories as ba')
            ->join('bookings as b', 'ba.booking_id', '=', 'b.booking_id')
            ->where('ba.accessory_id', $aid)
            ->whereNotIn('b.booking_status', ['cancelled', 'completed'])
            ->where('ba.booking_id', '!=', $id)
            ->sum('ba.quantity');
        // accessories.quantity is only meaningful for tracking_method='quantity' — an
        // individually-tracked accessory keeps quantity at a nominal 1 (its real stock lives in
        // accessory_units), matching AccessoriesController::index()'s total_units logic. Using
        // the raw quantity column here would wrongly cap an individually-tracked accessory at 1
        // unit total regardless of how many accessory_units rows actually exist.
        $stock = ($acc->tracking_method ?? 'quantity') === 'individual'
            ? max(1, (int) DB::table('accessory_units')->where('accessory_id', $aid)->where('status', '!=', 'retired')->count())
            : max(1, (int) $acc->quantity);
        if ($inUse + $qty > $stock) {
            $avail = max(0, $stock - $inUse);

            return ['type' => 'error', 'text' => "Only <strong>$avail</strong> unit(s) available for these dates (stock: $stock, currently out: $inUse)."];
        }

        $rate = (float) $acc->daily_rate;
        $sub = round($rate * $qty * $days, 2);
        DB::table('booking_accessories')->insert([
            'booking_id' => $id, 'accessory_id' => $aid, 'quantity' => $qty, 'days' => $days,
            'daily_rate' => $rate, 'is_included' => (int) $acc->is_included, 'subtotal' => $sub, 'notes' => $notes,
        ]);
        BookingCosting::updateBookingTotal($id);
        BookingCosting::generateCostEstimate($id, Auth::id());

        if (DB::table('bookings')->where('booking_id', $id)->value('cost_approval_status') === 'client_approved') {
            DB::table('bookings')->where('booking_id', $id)->update(['cost_approval_status' => 'pending_client', 'updated_at' => now()]);
        }

        return ['type' => 'success', 'text' => '<strong>' . e($acc->accessory_name) . '</strong> added to booking. Cost estimate updated.'];
    }

    private function removeBookingAccessory(Request $request, int $id): ?array
    {
        $baid = (int) $request->input('ba_id', 0);
        if (! $baid) {
            return null;
        }

        $accName = DB::table('booking_accessories as ba')
            ->join('accessories as a', 'ba.accessory_id', '=', 'a.accessory_id')
            ->where('ba.ba_id', $baid)->where('ba.booking_id', $id)
            ->value('a.accessory_name');

        DB::table('booking_accessories')->where('ba_id', $baid)->where('booking_id', $id)->delete();
        BookingCosting::updateBookingTotal($id);
        BookingCosting::generateCostEstimate($id, Auth::id());

        return ['type' => 'success', 'text' => '<strong>' . e($accName ?? 'Accessory') . '</strong> removed from booking.'];
    }

    private function updateCePricing(Request $request, int $id, int $uid): array
    {
        $mode = (string) $request->input('pricing_mode', 'no_discount');
        $validModes = ['no_discount', 'package_price', 'discount_percent', 'discount_flat'];
        if (! in_array($mode, $validModes, true)) {
            return ['type' => 'danger', 'text' => 'Invalid pricing mode.'];
        }

        $vatExempt = $request->boolean('vat_exempt');
        $pricingInput = null;

        if ($mode !== 'no_discount') {
            $inputRaw = $request->input('pricing_input', '');
            $pricingInput = $inputRaw === '' ? null : (float) $inputRaw;
            if ($pricingInput === null || $pricingInput <= 0) {
                return ['type' => 'danger', 'text' => 'Enter an amount greater than zero for this pricing mode.'];
            }
            if ($mode === 'discount_percent' && $pricingInput > 100) {
                return ['type' => 'danger', 'text' => 'Discount percentage cannot exceed 100%.'];
            }
        }

        BookingCosting::applyPricingMode($id, $uid, $mode, $pricingInput, $vatExempt);

        $labels = [
            'no_discount' => 'Full itemized total (no package pricing)',
            'package_price' => 'Package price of ₱' . number_format($pricingInput, 2),
            'discount_percent' => number_format($pricingInput, 2) . '% discount off the itemized total',
            'discount_flat' => '₱' . number_format($pricingInput, 2) . ' flat discount off the itemized total',
        ];

        return ['type' => 'success', 'text' => 'Pricing updated: <strong>' . $labels[$mode] . '</strong>. Cost estimate recalculated.'];
    }

    // CE project details (Part 7). The contact_* fields are per-booking overrides — a blank
    // one is stored as NULL so the CE document falls back to the client record.
    private function updateProjectDetails(Request $request, int $id, int $uid): array
    {
        $ceType = (string) $request->input('ce_type', 'fs_front');
        if (! in_array($ceType, ['fs_front', 'client_direct', 'partner_front'], true)) {
            return ['type' => 'danger', 'text' => 'Invalid CE type.'];
        }

        $dueDate = trim((string) $request->input('ce_due_date', ''));
        if ($dueDate !== '' && ! strtotime($dueDate)) {
            return ['type' => 'danger', 'text' => 'Invalid due date.'];
        }

        $email = trim((string) $request->input('ce_contact_email', ''));
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['type' => 'danger', 'text' => 'Invalid contact email address.'];
        }

        DB::table('bookings')->where('booking_id', $id)->update([
            'ce_due_date' => $dueDate !== '' ? $dueDate : null,
            'ce_type' => $ceType,
            'ce_director_dop' => trim((string) $request->input('ce_director_dop', '')) ?: null,
            'ce_contact_person' => trim((string) $request->input('ce_contact_person', '')) ?: null,
            'ce_contact_number' => trim((string) $request->input('ce_contact_number', '')) ?: null,
            'ce_contact_email' => $email ?: null,
            'ce_prepared_by' => trim((string) $request->input('ce_prepared_by', '')) ?: null,
            'updated_at' => now(),
        ]);

        ActivityLog::record($uid, 'update', 'bookings', 'Updated CE project details.', $id);

        return ['type' => 'success', 'text' => 'Project details updated.'];
    }

    // ── CE workflow (Part 9) ───────────────────────────────────────────────────────────

    // Streams the CE as CSV (opens in Excel). Same streamDownload + fputcsv pattern as the
    // Reports exports; no spreadsheet library needed.
    public function ceExport(Request $request, int $id)
    {
        $booking = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('b.booking_id', $id)
            ->select('b.*', 'c.company_name', 'c.contact_person')
            ->first();
        abort_unless($booking, 404);

        $ce = DB::table('cost_estimates')->where('booking_id', $id)->orderByDesc('ce_id')->first();
        $bd = BookingCosting::breakdown($id, $ce);
        $lines = BookingCosting::lines($id);
        $ceTypeLabels = ['fs_front' => 'FS Front', 'client_direct' => 'Client Direct', 'partner_front' => 'Partner Front'];

        // Legacy references already start with "CE-"; the newer YY-MM-NN ones don't.
        $ref = $ce->ce_reference ?? $booking->booking_reference;
        $filename = (str_starts_with($ref, 'CE-') ? $ref : 'CE-' . $ref) . '-' . date('Ymd') . '.csv';

        return response()->streamDownload(function () use ($booking, $ce, $bd, $lines, $ceTypeLabels) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['COST ESTIMATE', $ce->ce_reference ?? '—']);
            fputcsv($out, ['Booking', $booking->booking_reference]);
            fputcsv($out, ['Production House', $booking->company_name ?: $booking->contact_person]);
            fputcsv($out, ['Project', $booking->project_title]);
            fputcsv($out, ['Type', $ceTypeLabels[$booking->ce_type ?? 'fs_front'] ?? 'FS Front']);
            fputcsv($out, ['Director / DOP', $booking->ce_director_dop ?: '—']);
            fputcsv($out, ['Shoot Dates', $booking->shoot_date_start . ' to ' . $booking->shoot_date_end]);
            fputcsv($out, ['Due Date', $booking->ce_due_date ?: '—']);
            fputcsv($out, []);

            foreach ($lines['groups'] as $cat => $rows) {
                fputcsv($out, [strtoupper($cat) . ' (FS)']);
                fputcsv($out, ['Qty', 'Item', 'Days', 'Rate/Day', 'Amount']);
                $sub = 0;
                foreach ($rows as $r) {
                    $amt = $r->quantity * $r->days * $r->daily_rate;
                    $sub += $amt;
                    fputcsv($out, [$r->quantity, $r->equipment_name . ($r->brand ? " ($r->brand)" : ''), $r->days, $r->daily_rate, $amt]);
                }
                fputcsv($out, ['', 'Subtotal — ' . strtoupper($cat) . ' (FS)', '', '', $sub]);
                fputcsv($out, []);
            }

            if ($lines['crew']->isNotEmpty()) {
                fputcsv($out, ['CREW TALENT FEE']);
                fputcsv($out, ['Name', 'Position', 'Rate', 'Units', 'Amount']);
                foreach ($lines['crew'] as $c) {
                    fputcsv($out, [$c->crew_name, $c->position_name ?: '—', $c->rate_used, $c->hours_worked, $c->rate_used * $c->hours_worked]);
                }
                fputcsv($out, []);
            }

            fputcsv($out, ['TOTALS']);
            fputcsv($out, ['FS equipment', $bd['fs_equipment']]);
            fputcsv($out, ['Net items (ex. transport)', $bd['net_items']]);
            fputcsv($out, ['Transportation', $bd['transportation']]);
            fputcsv($out, ['Sub total (equipment CE)', $bd['equip_subtotal']]);
            fputcsv($out, ['Crew TF', $bd['crew_tf']]);
            if ($bd['pricing_mode'] !== 'no_discount') {
                fputcsv($out, ['Discount on packaged cost', $bd['discount_on_packaged_cost']]);
                if (($bd['not_discounted'] ?? 0) > 0) {
                    fputcsv($out, ['Not discounted (billed on top)', $bd['not_discounted']]);
                }
                fputcsv($out, ['Total discounted packaged cost', $bd['equip_net']]);
            }
            fputcsv($out, ['Equipment CE grand total', $bd['equip_grand']]);
            fputcsv($out, ['Crew CE grand total', $bd['crew_grand']]);
            fputcsv($out, ['Summary grand total (incl. VAT)', $bd['summary_grand']]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    // Renders the CE as an HTML email body — the client reads the figures without clicking,
    // and the link takes them to the portal to approve or reject.
    private function buildCeEmailHtml($booking, $ce, array $bd, array $lines, string $portalUrl, string $clientName): string
    {
        $peso = fn ($n) => '₱' . number_format((float) $n, 2);
        $row = fn ($l, $v, $strong = false) => '<tr><td style="padding:4px 8px;' . ($strong ? 'font-weight:700;' : '') . '">' . e($l)
            . '</td><td style="padding:4px 8px;text-align:right;font-family:monospace;' . ($strong ? 'font-weight:700;' : '') . '">' . $peso($v) . '</td></tr>';

        $h = '<div style="font-family:Arial,Helvetica,sans-serif;color:#0f172a;max-width:680px">';
        $h .= '<h2 style="color:#003D80;margin:0 0 4px">Cost Estimate ' . e($ce->ce_reference ?? '') . '</h2>';
        $h .= '<div style="color:#64748b;font-size:13px;margin-bottom:14px">'
            . e($booking->project_title ?: $booking->booking_reference) . ' · '
            . e($clientName) . '</div>';

        foreach ($lines['groups'] as $cat => $rows) {
            $h .= '<h4 style="color:#003D80;margin:14px 0 4px;font-size:13px">' . e(strtoupper($cat)) . ' (FS)</h4>';
            $h .= '<table style="width:100%;border-collapse:collapse;font-size:13px">';
            foreach ($rows as $r) {
                $h .= '<tr><td style="padding:3px 8px">' . e($r->quantity . '× ' . $r->equipment_name)
                    . '</td><td style="padding:3px 8px;text-align:right;font-family:monospace">'
                    . $peso($r->quantity * $r->days * $r->daily_rate) . '</td></tr>';
            }
            $h .= '</table>';
        }

        if ($lines['crew']->isNotEmpty()) {
            $h .= '<h4 style="color:#003D80;margin:14px 0 4px;font-size:13px">CREW</h4><table style="width:100%;border-collapse:collapse;font-size:13px">';
            foreach ($lines['crew'] as $c) {
                $h .= '<tr><td style="padding:3px 8px">' . e($c->crew_name . ($c->position_name ? ' — ' . $c->position_name : ''))
                    . '</td><td style="padding:3px 8px;text-align:right;font-family:monospace">' . $peso($c->rate_used * $c->hours_worked) . '</td></tr>';
            }
            $h .= '</table>';
        }

        $h .= '<h4 style="color:#003D80;margin:18px 0 4px;font-size:13px">TOTALS</h4>';
        $h .= '<table style="width:100%;border-collapse:collapse;font-size:13px;border-top:1px solid #e2e8f0">';
        $h .= $row('Sub total (equipment CE)', $bd['equip_subtotal']);
        $h .= $row('Crew TF', $bd['crew_tf']);
        if ($bd['pricing_mode'] !== 'no_discount') {
            $h .= $row('Discount on packaged cost', $bd['discount_on_packaged_cost']);
        }
        $h .= $row('Equipment CE grand total', $bd['equip_grand'], true);
        $h .= $row('Crew CE grand total', $bd['crew_grand'], true);
        $h .= $row('Summary grand total (incl. VAT)', $bd['summary_grand'], true);
        $h .= '</table>';

        $h .= '<p style="margin:20px 0"><a href="' . e($portalUrl) . '" style="background:#003D80;color:#fff;padding:10px 18px;'
            . 'border-radius:6px;text-decoration:none;font-weight:700;font-size:13px">Review &amp; respond</a></p>';
        $h .= '<p style="color:#64748b;font-size:12px">Reply to this email if anything needs adjusting.</p></div>';

        return $h;
    }

    // Same shape as AuthController::sendMail() — a mail failure is logged and reported, never
    // allowed to break the request.
    private function sendMail(string $to, string $subject, string $html): bool
    {
        try {
            Mail::html($html, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('CE mail failed: ' . $e->getMessage());

            return false;
        }
    }

    private function emailCeToClient(int $id, $booking, int $uid): array
    {
        $ce = DB::table('cost_estimates')->where('booking_id', $id)->orderByDesc('ce_id')->first();
        if (! $ce || $ce->status !== 'confirmed') {
            return ['type' => 'danger', 'text' => 'Confirm the cost estimate before emailing it to the client.'];
        }

        // act()'s shared $booking query doesn't carry the client columns, so load them here
        // rather than widening a query every other action depends on.
        $client = DB::table('clients')->where('client_id', $booking->client_id)
            ->select('company_name', 'contact_person', 'email')->first();

        // Part 7's per-booking override wins; otherwise the client record.
        $to = trim((string) ($booking->ce_contact_email ?: ($client->email ?? '')));
        if (! $to || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['type' => 'danger', 'text' => 'No valid client email on this booking. Add one under Project Details.'];
        }

        $clientName = ($client->company_name ?? '') ?: ($client->contact_person ?? '');
        $bd = BookingCosting::breakdown($id, $ce);
        $html = $this->buildCeEmailHtml($booking, $ce, $bd, BookingCosting::lines($id), route('client-booking-detail', $id), $clientName);
        $subject = 'Cost Estimate ' . ($ce->ce_reference ?? '') . ' — ' . ($booking->project_title ?: $booking->booking_reference);

        if (! $this->sendMail($to, $subject, $html)) {
            return ['type' => 'danger', 'text' => 'Could not send the email — nothing was changed. Check the mail settings and try again.'];
        }

        DB::table('bookings')->where('booking_id', $id)->update([
            'cost_approval_status' => 'pending_client', 'updated_at' => now(),
        ]);
        DB::table('quotation_log')->insert([
            'booking_id' => $id, 'log_type' => 'modified', 'logged_by' => $uid,
            'new_status' => 'pending_client', 'remarks' => 'Cost estimate ' . ($ce->ce_reference ?? '') . ' emailed to ' . $to,
        ]);
        ActivityLog::record($uid, 'update', 'booking', "Emailed CE {$ce->ce_reference} to $to.", $id);

        return ['type' => 'success', 'text' => 'Cost estimate emailed to <strong>' . e($to) . '</strong> and marked pending client approval.'];
    }

    private function eraseCe(Request $request, int $id, int $uid): array
    {
        $ceId = (int) $request->input('ce_id', 0);
        $ce = DB::table('cost_estimates')->where('ce_id', $ceId)->where('booking_id', $id)->first();
        if (! $ce) {
            return ['type' => 'danger', 'text' => 'Cost estimate not found on this booking.'];
        }
        // Confirmed CEs are immutable history (Part 2a) — they may already be with a client.
        if ($ce->status === 'confirmed') {
            return ['type' => 'danger', 'text' => 'Confirmed cost estimates cannot be erased — they are a record of what was quoted.'];
        }
        if (DB::table('cost_estimates')->where('booking_id', $id)->count() <= 1) {
            return ['type' => 'danger', 'text' => 'This is the only cost estimate on the booking — it cannot be erased.'];
        }

        DB::table('cost_estimates')->where('ce_id', $ceId)->delete();
        ActivityLog::record($uid, 'delete', 'booking', "Erased draft CE {$ce->ce_reference}.", $id);

        return ['type' => 'success', 'text' => 'Draft <strong>' . e($ce->ce_reference) . '</strong> erased.'];
    }

    private function closeProject(Request $request, int $id, int $uid): array
    {
        $mode = $request->input('mode');
        if ($mode === 'confirm') {
            $approvalStatus = DB::table('bookings')->where('booking_id', $id)->value('approval_status');
            if ($approvalStatus !== 'approved') {
                return ['type' => 'danger', 'text' => 'Cannot confirm the cost estimate — approve the booking first.'];
            }

            return BookingCosting::confirmCe($id, $uid);
        }
        if ($mode === 'draft') {
            BookingCosting::generateCostEstimate($id, $uid);

            return ['type' => 'success', 'text' => 'Saved as a draft cost estimate.'];
        }

        return ['type' => 'danger', 'text' => 'Unknown close option.'];
    }

    // Removes the FilmSpec equipment lines only. Refuses outright if anything has been checked
    // out — deleting those lines would orphan their equipment_transactions rows and strand the
    // gear as 'rented' with nothing explaining why.
    private function clearFsEquipment(int $id, int $uid): array
    {
        $checkedOut = DB::table('booking_equipment as be')
            ->join('equipment as e', 'be.equipment_id', '=', 'e.equipment_id')
            ->where('be.booking_id', $id)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('equipment_transactions as et')
                ->whereColumn('et.booking_id', 'be.booking_id')
                ->whereColumn('et.equipment_id', 'be.equipment_id')
                ->where('et.transaction_type', 'checkout'))
            ->pluck('e.equipment_name');

        if ($checkedOut->isNotEmpty()) {
            return ['type' => 'danger', 'text' => 'Cannot clear — already checked out: <strong>'
                . e($checkedOut->implode(', ')) . '</strong>. Check the equipment back in first.'];
        }

        $count = DB::table('booking_equipment')->where('booking_id', $id)->count();
        if (! $count) {
            return ['type' => 'danger', 'text' => 'There are no FilmSpec equipment lines to clear.'];
        }

        DB::table('booking_equipment')->where('booking_id', $id)->delete();
        BookingCosting::generateCostEstimate($id, $uid);
        ActivityLog::record($uid, 'delete', 'booking', "Cleared $count FilmSpec equipment line(s).", $id);

        return ['type' => 'success', 'text' => "Cleared $count FilmSpec equipment line(s). Crew was left untouched."];
    }

    private function clearCrew(int $id, int $uid): array
    {
        $count = DB::table('booking_crew')->where('booking_id', $id)->count();
        if (! $count) {
            return ['type' => 'danger', 'text' => 'There is no crew to clear.'];
        }

        DB::table('booking_crew')->where('booking_id', $id)->delete();
        BookingCosting::generateCostEstimate($id, $uid);
        ActivityLog::record($uid, 'delete', 'booking', "Cleared $count crew assignment(s).", $id);

        return ['type' => 'success', 'text' => "Cleared $count crew assignment(s). Equipment and project details were left untouched."];
    }

    private function updateIncident(Request $request, int $id): array
    {
        $iid = (int) $request->input('incident_id');
        $resolution = $request->input('resolution', 'pending');
        $charge = (float) $request->input('charge_amount', 0);
        $status = $request->input('incident_status', 'open');
        $desc = trim($request->input('description', ''));
        $cause = $request->input('cause', 'unknown');

        $payload = [
            'description' => $desc, 'cause' => $cause, 'resolution' => $resolution,
            'charge_amount' => $charge, 'status' => $status,
        ];
        if (in_array($status, ['resolved', 'closed'], true)) {
            $payload['resolved_at'] = now();
        }

        DB::table('incident_reports')->where('incident_id', $iid)->where('booking_id', $id)->update($payload);
        BookingCosting::updateBookingTotal($id);

        return ['type' => 'success', 'text' => 'Incident updated.'];
    }

    private function markChecklist(int $id, int $eid, string $direction, int $uid, array $condition, ?int $qty = null): void
    {
        $qty ??= (int) DB::table('booking_equipment')->where('booking_id', $id)->where('equipment_id', $eid)->value('quantity');
        $existing = DB::table('equipment_checklist')->where('booking_id', $id)->where('equipment_id', $eid)->where('direction', $direction)->value('checklist_id');
        $payload = array_merge(['checked' => 1, 'quantity_actual' => $qty, 'checked_by' => $uid, 'checked_at' => now()], $condition);
        if ($existing) {
            DB::table('equipment_checklist')->where('checklist_id', $existing)->update($payload);
        } else {
            DB::table('equipment_checklist')->insert(array_merge($payload, [
                'booking_id' => $id, 'equipment_id' => $eid, 'direction' => $direction, 'quantity_expected' => $qty,
            ]));
        }
    }
}
