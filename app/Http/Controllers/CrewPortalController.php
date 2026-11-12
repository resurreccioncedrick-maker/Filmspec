<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\EvidencePhotoUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class CrewPortalController extends Controller
{
    private array $typeBadge = ['damaged' => 'badge-red', 'missing' => 'badge-orange', 'malfunction' => 'badge-yellow', 'late_return' => 'badge-purple'];

    private array $typeLabel = ['damaged' => 'Damaged', 'missing' => 'Missing', 'malfunction' => 'Malfunction', 'late_return' => 'Late Return'];

    private array $statusBadge = ['open' => 'badge-yellow', 'resolved' => 'badge-green', 'closed' => 'badge-gray'];

    private array $maintBadge = ['pending' => 'badge-yellow', 'in_progress' => 'badge-blue', 'completed' => 'badge-green', 'cancelled' => 'badge-gray'];

    private array $maintLabel = ['preventive' => 'Preventive', 'corrective' => 'Corrective', 'calibration' => 'Calibration', 'cleaning' => 'Cleaning'];

    private array $bookingBadge = ['confirmed' => 'badge-blue', 'ongoing' => 'badge-green', 'pending_inspection' => 'badge-orange', 'returned' => 'badge-gray'];

    private array $damageTypes = ['scratches' => 'Scratches', 'cracked_broken' => 'Cracked / Broken Part', 'electronic_malfunction' => 'Electronic Malfunction', 'missing_part' => 'Missing Part', 'others' => 'Others'];

    public function checklistPoll(Request $request)
    {
        $user = Auth::user();
        $role = $user->role->role_name ?? null;
        if (! $user || $role !== 'crew') {
            return response()->json(['ok' => false], 403);
        }

        $crewMember = DB::table('crew_members')->where('user_id', $user->user_id)->first();
        if (! $crewMember) {
            return response()->json(['ok' => true, 'signature' => '']);
        }

        $rows = DB::table('booking_crew as bc')
            ->join('bookings as b', 'bc.booking_id', '=', 'b.booking_id')
            ->where('bc.crew_id', $crewMember->crew_id)
            ->where('bc.is_head_crew', 1)
            ->whereNotIn('bc.assignment_status', ['declined', 'back_out'])
            ->whereIn('b.booking_status', ['confirmed', 'ongoing'])
            ->where('b.cost_approval_status', 'client_approved')
            ->orderBy('b.booking_id')
            ->select('b.booking_id', 'b.booking_status', 'b.field_arrival_confirmed_at')
            ->get();

        $signature = $rows->map(fn ($r) => $r->booking_id . ':' . $r->booking_status . ':' . (empty($r->field_arrival_confirmed_at) ? '0' : '1'))
            ->implode('|');

        return response()->json(['ok' => true, 'signature' => md5($signature)]);
    }

    private function isHeadCrewOf(int $crewId, int $bookingId): bool
    {
        return $bookingId > 0 && DB::table('booking_crew')
            ->where('crew_id', $crewId)->where('booking_id', $bookingId)
            ->where('is_head_crew', 1)
            ->whereNotIn('assignment_status', ['declined', 'back_out'])
            ->exists();
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $role = $user->role->role_name ?? null;

        if (! $user || $role !== 'crew') {
            return redirect()->route('dashboard');
        }

        $uid = $user->user_id;

        $msg = null;
        if ($request->isMethod('post')) {
            $msg = $this->handleAction($request, $uid);
        }

        $activeTab = $request->input('return_tab', 'dashboard');
        $activeSubTab = $request->input('return_subtab', '');
        $activeBookingId = (int) $request->input('return_booking_id', 0);

        $crewAnnouncements = DB::table('reminders')
            ->where('visible_to_crew', true)->where('is_done', false)
            ->where('reminder_date', '>=', now()->toDateString())
            ->orderBy('reminder_date')
            ->limit(10)
            ->get();

        $crewMember = DB::table('crew_members as cm')
            ->leftJoin('crew_positions as cp', 'cm.primary_position_id', '=', 'cp.position_id')
            ->where('cm.user_id', $uid)
            ->select('cm.*', 'cp.position_name')
            ->first();

        $pendingProfileRequest = $crewMember
            ? DB::table('crew_profile_requests')->where('crew_id', $crewMember->crew_id)->where('status', 'pending')->first()
            : null;

        $myBookings = collect();
        $myMaintenances = collect();
        $myActiveBookings = collect();
        $bookingEquipMap = [];
        $equipmentUnitMap = [];
        $bookingEquipmentUnitMap = [];
        $bookingAccessoryUnitMap = [];
        $bookingAccMap = [];
        $accessoryUnitMap = [];
        $myAttendance = collect();
        $myTimesheets = collect();
        $myChecklistBookings = collect();
        $myTeamBookings = collect();
        $allActiveCrew = collect();
        $fieldEquipMap = [];
        $fieldAccMap = [];
        $crewPositions = collect();
        $myFieldRequests = collect();
        $headBookingIds = [];
        $isHeadCrew = false;

        if ($crewMember) {
            $cmId = $crewMember->crew_id;

            $myBookings = DB::table('booking_crew as bc')
                ->join('bookings as b', 'bc.booking_id', '=', 'b.booking_id')
                ->join('clients as c', 'b.client_id', '=', 'c.client_id')
                ->leftJoin('crew_positions as cp', 'bc.position_id', '=', 'cp.position_id')
                ->where('bc.crew_id', $cmId)
                ->whereNotIn('bc.assignment_status', ['declined', 'back_out'])
                ->whereIn('b.booking_status', ['confirmed', 'ongoing', 'pending_inspection', 'returned'])
                ->where('b.cost_approval_status', 'client_approved')
                ->orderByDesc('b.shoot_date_start')
                ->select('b.booking_id', 'b.booking_reference', 'b.project_title', 'b.shoot_date_start',
                    'b.shoot_date_end', 'b.shoot_location', 'b.booking_status',
                    'b.field_arrival_confirmed_at',
                    'bc.position_id', 'bc.rate_used', 'bc.hours_worked', 'cp.position_name',
                    'c.company_name', 'c.contact_person')
                ->limit(30)
                ->get();

            $headBookingIds = DB::table('booking_crew')
                ->where('crew_id', $cmId)->where('is_head_crew', 1)
                ->whereNotIn('assignment_status', ['declined', 'back_out'])
                ->whereIn('booking_id', $myBookings->pluck('booking_id')->all())
                ->pluck('booking_id')->map(fn ($v) => (int) $v)->all();
            $isHeadCrew = ! empty($headBookingIds);

            $myMaintenances = DB::table('maintenance_schedules as ms')
                ->join('equipment as e', 'ms.equipment_id', '=', 'e.equipment_id')
                ->where('ms.assigned_crew_id', $cmId)
                ->whereIn('ms.status', ['pending', 'in_progress'])
                ->orderBy('ms.scheduled_date')
                ->select('ms.*', 'e.equipment_name', 'e.brand', 'e.serial_number')
                ->limit(20)
                ->get();

            $myActiveBookings = DB::table('booking_crew as bc')
                ->join('bookings as b', 'bc.booking_id', '=', 'b.booking_id')
                ->where('bc.crew_id', $cmId)
                ->whereNotIn('bc.assignment_status', ['declined', 'back_out'])
                ->whereIn('b.booking_status', ['confirmed', 'ongoing', 'pending_inspection', 'returned'])
                ->where('b.cost_approval_status', 'client_approved')
                ->orderByDesc('b.shoot_date_start')
                ->distinct()
                ->select('b.booking_id', 'b.booking_reference', 'b.project_title', 'b.shoot_date_start')
                ->limit(50)
                ->get();

            $activeBookingIds = $myActiveBookings->pluck('booking_id')->all();
            $bookingEquipMap = [];
            if ($activeBookingIds) {
                $equipRows = DB::table('booking_equipment as be')
                    ->join('equipment as e', 'be.equipment_id', '=', 'e.equipment_id')
                    ->whereIn('be.booking_id', $activeBookingIds)
                    ->select('be.booking_id', 'e.equipment_id', 'e.equipment_name', 'e.brand', 'e.serial_number')
                    ->get();
                foreach ($equipRows as $row) {
                    $bookingEquipMap[$row->booking_id][] = $row;
                }

                $equipIdsForUnits = collect($equipRows)->pluck('equipment_id')->unique()->all();
                $equipmentUnitMap = [];
                if ($equipIdsForUnits) {
                    foreach (DB::table('equipment_units')->whereIn('equipment_id', $equipIdsForUnits)->orderBy('asset_tag')->get() as $u) {
                        $equipmentUnitMap[$u->equipment_id][] = ['unit_id' => $u->unit_id, 'asset_tag' => $u->asset_tag];
                    }
                }

                $bookingEquipmentUnitMap = [];
                $allocatedUnits = DB::table('booking_equipment_units as beu')
                    ->join('booking_equipment as be', 'beu.bk_equip_id', '=', 'be.bk_equip_id')
                    ->join('equipment_units as eu', 'beu.equipment_unit_id', '=', 'eu.unit_id')
                    ->whereIn('be.booking_id', $activeBookingIds)
                    ->select('be.booking_id', 'be.equipment_id', 'eu.unit_id', 'eu.asset_tag')
                    ->get();
                foreach ($allocatedUnits as $row) {
                    $bookingEquipmentUnitMap[$row->booking_id . '_' . $row->equipment_id][] = ['unit_id' => $row->unit_id, 'asset_tag' => $row->asset_tag];
                }

                $checkedOutUnits = DB::table('equipment_checklist as ec')
                    ->join('equipment_units as eu', 'ec.equipment_unit_id', '=', 'eu.unit_id')
                    ->whereIn('ec.booking_id', $activeBookingIds)
                    ->where('ec.direction', 'out')
                    ->whereNotNull('ec.equipment_unit_id')
                    ->select('ec.booking_id', 'ec.equipment_id', 'eu.unit_id', 'eu.asset_tag')
                    ->distinct()
                    ->get();
                foreach ($checkedOutUnits as $row) {
                    $key = $row->booking_id . '_' . $row->equipment_id;
                    if (! isset($bookingEquipmentUnitMap[$key])) {
                        $bookingEquipmentUnitMap[$key] = [];
                    }
                    $already = collect($bookingEquipmentUnitMap[$key])->pluck('unit_id')->all();
                    if (! in_array($row->unit_id, $already, true)) {
                        $bookingEquipmentUnitMap[$key][] = ['unit_id' => $row->unit_id, 'asset_tag' => $row->asset_tag];
                    }
                }

                $accRows = DB::table('booking_accessories as ba')
                    ->join('accessories as a', 'ba.accessory_id', '=', 'a.accessory_id')
                    ->whereIn('ba.booking_id', $activeBookingIds)
                    ->select('ba.booking_id', 'a.accessory_id', 'a.accessory_name')
                    ->get();
                foreach ($accRows as $row) {
                    $bookingAccMap[$row->booking_id][] = $row;
                }

                $accIdsForUnits = collect($accRows)->pluck('accessory_id')->unique()->all();
                if ($accIdsForUnits) {
                    foreach (DB::table('accessory_units')->whereIn('accessory_id', $accIdsForUnits)->orderBy('asset_tag')->get() as $u) {
                        $accessoryUnitMap[$u->accessory_id][] = ['unit_id' => $u->unit_id, 'asset_tag' => $u->asset_tag];
                    }
                }

                $bookingAccessoryUnitMap = [];
                $allocatedAccUnits = DB::table('booking_accessory_units as bau')
                    ->join('booking_accessories as ba', 'bau.ba_id', '=', 'ba.ba_id')
                    ->join('accessory_units as au', 'bau.accessory_unit_id', '=', 'au.unit_id')
                    ->whereIn('ba.booking_id', $activeBookingIds)
                    ->select('ba.booking_id', 'ba.accessory_id', 'au.unit_id', 'au.asset_tag')
                    ->get();
                foreach ($allocatedAccUnits as $row) {
                    $bookingAccessoryUnitMap[$row->booking_id . '_' . $row->accessory_id][] = ['unit_id' => $row->unit_id, 'asset_tag' => $row->asset_tag];
                }
            }

            $myAttendance = DB::table('crew_attendance as ca')
                ->join('bookings as b', 'ca.booking_id', '=', 'b.booking_id')
                ->where('ca.crew_id', $cmId)
                ->orderByDesc('ca.attendance_date')
                ->select('ca.*', 'b.booking_reference', 'b.project_title')
                ->limit(30)
                ->get();

            $myTimesheets = DB::table('crew_timesheets as ts')
                ->join('bookings as b', 'ts.booking_id', '=', 'b.booking_id')
                ->where('ts.crew_id', $cmId)
                ->orderByDesc('ts.timesheet_date')
                ->select('ts.*', 'b.booking_reference', 'b.project_title')
                ->limit(30)
                ->get();

            $checklistBookings = $myBookings->whereIn('booking_status', ['confirmed', 'ongoing', 'pending_inspection'])
                ->whereIn('booking_id', $headBookingIds);
            $checklistBookingIds = $checklistBookings->pluck('booking_id')->all();
            if ($checklistBookingIds) {
                $equipRows = DB::table('booking_equipment as be')
                    ->join('equipment as e', 'be.equipment_id', '=', 'e.equipment_id')
                    ->leftJoin('equipment_checklist as co', function ($j) {
                        $j->on('co.equipment_id', '=', 'be.equipment_id')->on('co.booking_id', '=', 'be.booking_id')->where('co.direction', 'out');
                    })
                    ->leftJoin('equipment_checklist as ci', function ($j) {
                        $j->on('ci.equipment_id', '=', 'be.equipment_id')->on('ci.booking_id', '=', 'be.booking_id')->where('ci.direction', 'in');
                    })
                    ->whereIn('be.booking_id', $checklistBookingIds)
                    ->orderBy('e.equipment_name')
                    ->select('be.booking_id', DB::raw("'equipment' as item_type"), 'be.equipment_id as ref_id', 'be.quantity',
                        'e.equipment_name as item_name', 'e.brand', 'e.model',
                        'co.checked as co_checked', 'co.quantity_actual as co_qty', 'co.condition_out', 'co.notes as co_notes',
                        'ci.checked as ci_checked', 'ci.quantity_actual as ci_qty', 'ci.condition_in', 'ci.notes as ci_notes')
                    ->get();

                $accRows = DB::table('booking_accessories as ba')
                    ->join('accessories as a', 'ba.accessory_id', '=', 'a.accessory_id')
                    ->leftJoin('equipment_checklist as co', function ($j) {
                        $j->on('co.accessory_id', '=', 'ba.accessory_id')->on('co.booking_id', '=', 'ba.booking_id')->where('co.direction', 'out');
                    })
                    ->leftJoin('equipment_checklist as ci', function ($j) {
                        $j->on('ci.accessory_id', '=', 'ba.accessory_id')->on('ci.booking_id', '=', 'ba.booking_id')->where('ci.direction', 'in');
                    })
                    ->whereIn('ba.booking_id', $checklistBookingIds)
                    ->orderBy('a.accessory_name')
                    ->select('ba.booking_id', DB::raw("'accessory' as item_type"), 'ba.accessory_id as ref_id', 'ba.quantity',
                        'a.accessory_name as item_name', DB::raw("'' as brand"), DB::raw("'' as model"),
                        'co.checked as co_checked', 'co.quantity_actual as co_qty', 'co.condition_out', 'co.notes as co_notes',
                        'ci.checked as ci_checked', 'ci.quantity_actual as ci_qty', 'ci.condition_in', 'ci.notes as ci_notes')
                    ->get();

                $allocEqTags = DB::table('booking_equipment_units as beu')
                    ->join('booking_equipment as be', 'beu.bk_equip_id', '=', 'be.bk_equip_id')
                    ->join('equipment_units as eu', 'beu.equipment_unit_id', '=', 'eu.unit_id')
                    ->whereIn('be.booking_id', $checklistBookingIds)
                    ->select('be.booking_id', 'be.equipment_id', 'eu.asset_tag')
                    ->get()->groupBy(fn ($r) => $r->booking_id . '_' . $r->equipment_id);
                $allocAccTags = DB::table('booking_accessory_units as bau')
                    ->join('booking_accessories as ba', 'bau.ba_id', '=', 'ba.ba_id')
                    ->join('accessory_units as au', 'bau.accessory_unit_id', '=', 'au.unit_id')
                    ->whereIn('ba.booking_id', $checklistBookingIds)
                    ->select('ba.booking_id', 'ba.accessory_id', 'au.asset_tag')
                    ->get()->groupBy(fn ($r) => $r->booking_id . '_' . $r->accessory_id);

                $itemsByBooking = [];
                foreach ($equipRows->concat($accRows) as $row) {
                    $tagMap = $row->item_type === 'accessory' ? $allocAccTags : $allocEqTags;
                    $tags = $tagMap->get($row->booking_id . '_' . $row->ref_id, collect())->pluck('asset_tag');
                    $row->unit_tags = $tags->isNotEmpty() ? $tags->implode(', ') : null;
                    $itemsByBooking[$row->booking_id][] = $row;
                }
                foreach ($checklistBookings as $bk) {
                    $myChecklistBookings->push((object) [
                        'booking' => $bk,
                        'direction' => empty($bk->field_arrival_confirmed_at) ? 'out' : 'in',
                        'items' => $itemsByBooking[$bk->booking_id] ?? [],
                    ]);
                }
            }

            $teamBookings = $myBookings->whereIn('booking_status', ['confirmed', 'ongoing']);
            $teamBookingIds = $teamBookings->pluck('booking_id')->all();
            if ($teamBookingIds) {
                $today = now()->toDateString();
                $crewRows = DB::table('booking_crew as bc')
                    ->join('crew_members as cm', 'bc.crew_id', '=', 'cm.crew_id')
                    ->leftJoin('crew_positions as cp', 'bc.position_id', '=', 'cp.position_id')
                    ->whereIn('bc.booking_id', $teamBookingIds)
                    ->whereNotIn('bc.assignment_status', ['declined', 'back_out'])
                    ->orderBy('cp.position_name')->orderBy('cm.last_name')
                    ->select('bc.booking_id', 'bc.crew_id', DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS crew_name"), 'cm.phone', 'cp.position_name')
                    ->get();

                $existingAtt = DB::table('crew_attendance')
                    ->whereIn('booking_id', $teamBookingIds)->where('attendance_date', $today)
                    ->get()->keyBy(fn ($r) => $r->booking_id . '-' . $r->crew_id);

                $teamByBooking = [];
                foreach ($crewRows as $row) {
                    $row->existing_attendance = $existingAtt->get($row->booking_id . '-' . $row->crew_id);
                    $teamByBooking[$row->booking_id][] = $row;
                }
                foreach ($teamBookings as $bk) {
                    $bkIsHead = in_array((int) $bk->booking_id, $headBookingIds, true);
                    $myTeamBookings->push((object) [
                        'booking' => $bk,
                        'is_head' => $bkIsHead,
                        'team' => $bkIsHead ? ($teamByBooking[$bk->booking_id] ?? []) : [],
                    ]);
                }

                $allActiveCrew = DB::table('crew_members')
                    ->where('status', 'active')->orderBy('last_name')
                    ->selectRaw("crew_id, CONCAT(first_name,' ',last_name) AS name")
                    ->get();
            }

            $fieldReqBookingIds = $myBookings->whereIn('booking_status', ['confirmed', 'ongoing'])->pluck('booking_id')->unique()->values()->all();
            if ($fieldReqBookingIds) {
                $existingEquipByBooking = DB::table('booking_equipment')->whereIn('booking_id', $fieldReqBookingIds)->get()->groupBy('booking_id');
                $existingAccByBooking = DB::table('booking_accessories')->whereIn('booking_id', $fieldReqBookingIds)->get()->groupBy('booking_id');
                $allEquip = DB::table('equipment')->where('availability_status', 'available')
                    ->orderBy('equipment_name')->limit(100)
                    ->select('equipment_id', 'equipment_name', 'brand', 'model', 'daily_rate')->get();
                $allAcc = DB::table('accessories')->orderBy('accessory_name')->limit(100)
                    ->select('accessory_id', 'accessory_name', 'daily_rate')->get();

                foreach ($fieldReqBookingIds as $bid) {
                    $excludeEquipIds = ($existingEquipByBooking->get($bid) ?? collect())->pluck('equipment_id')->all();
                    $excludeAccIds = ($existingAccByBooking->get($bid) ?? collect())->pluck('accessory_id')->all();
                    $fieldEquipMap[$bid] = $allEquip->whereNotIn('equipment_id', $excludeEquipIds)->values();
                    $fieldAccMap[$bid] = $allAcc->whereNotIn('accessory_id', $excludeAccIds)->values();
                }
            }
            $crewPositions = DB::table('crew_positions')->orderBy('position_name')->get();

            $myFieldRequests = DB::table('booking_equipment_requests as r')
                ->join('bookings as b', 'r.booking_id', '=', 'b.booking_id')
                ->leftJoin('equipment as e', 'r.equipment_id', '=', 'e.equipment_id')
                ->leftJoin('accessories as acc', 'r.accessory_id', '=', 'acc.accessory_id')
                ->leftJoin('crew_positions as pos', 'r.position_id', '=', 'pos.position_id')
                ->where('r.requested_by', $uid)
                ->orderByDesc('r.created_at')
                ->select('r.*', 'b.booking_reference', 'b.project_title', 'e.equipment_name', 'acc.accessory_name', 'pos.position_name')
                ->limit(20)
                ->get();
        }

        $myIncidents = DB::table('incident_reports as ir')
            ->leftJoin('equipment as e', 'ir.equipment_id', '=', 'e.equipment_id')
            ->leftJoin('accessories as acc', 'ir.accessory_id', '=', 'acc.accessory_id')
            ->join('bookings as b', 'ir.booking_id', '=', 'b.booking_id')
            ->where('ir.reported_by', $uid)
            ->orderByDesc('ir.created_at')
            ->select('ir.*', 'e.equipment_name', 'acc.accessory_name', 'b.booking_reference', 'b.project_title')
            ->limit(20)
            ->get();

        if (! $isHeadCrew && in_array($activeTab, ['attendance', 'checklist'], true)) {
            $activeTab = 'dashboard';
        }

        return view('crew-portal', [
            'msg' => $msg, 'user' => $user, 'crewMember' => $crewMember, 'activeTab' => $activeTab, 'activeSubTab' => $activeSubTab,
            'pendingProfileRequest' => $pendingProfileRequest,
            'activeBookingId' => $activeBookingId, 'crewAnnouncements' => $crewAnnouncements,
            'isHeadCrew' => $isHeadCrew, 'headBookingIds' => $headBookingIds,
            'myBookings' => $myBookings, 'myIncidents' => $myIncidents, 'myMaintenances' => $myMaintenances,
            'myActiveBookings' => $myActiveBookings, 'bookingEquipMap' => $bookingEquipMap, 'equipmentUnitMap' => $equipmentUnitMap,
            'bookingEquipmentUnitMap' => $bookingEquipmentUnitMap, 'bookingAccessoryUnitMap' => $bookingAccessoryUnitMap,
            'bookingAccMap' => $bookingAccMap, 'accessoryUnitMap' => $accessoryUnitMap,
            'myAttendance' => $myAttendance, 'myTimesheets' => $myTimesheets,
            'myChecklistBookings' => $myChecklistBookings, 'myTeamBookings' => $myTeamBookings, 'allActiveCrew' => $allActiveCrew,
            'fieldEquipMap' => $fieldEquipMap, 'fieldAccMap' => $fieldAccMap, 'crewPositions' => $crewPositions,
            'myFieldRequests' => $myFieldRequests,
            'typeBadge' => $this->typeBadge, 'typeLabel' => $this->typeLabel, 'statusBadge' => $this->statusBadge,
            'maintBadge' => $this->maintBadge, 'maintLabel' => $this->maintLabel, 'bookingBadge' => $this->bookingBadge,
            'damageTypes' => $this->damageTypes,
            'condOut' => ['excellent' => 'Excellent', 'good' => 'Good', 'fair' => 'Fair'],
            'condIn' => ['excellent' => 'Excellent', 'good' => 'Good', 'fair' => 'Fair', 'damaged' => 'Damaged', 'missing' => 'Missing'],
            'attStatusLabel' => ['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'no_show' => 'No Show', 'back_out' => 'Back Out'],
        ]);
    }

    public function printChecklist(Request $request)
    {
        $user = Auth::user();
        $role = $user->role->role_name ?? null;
        if (! $user || $role !== 'crew') {
            return redirect()->route('dashboard');
        }

        $crewMember = DB::table('crew_members')->where('user_id', $user->user_id)->first();
        $bid = (int) $request->query('booking_id', 0);
        if (! $bid || ! $crewMember) {
            return redirect()->route('crew-portal');
        }

        $ownsBooking = DB::table('booking_crew')->where('crew_id', $crewMember->crew_id)
            ->where('booking_id', $bid)->whereNotIn('assignment_status', ['declined', 'back_out'])->exists();
        if (! $ownsBooking) {
            return redirect()->route('crew-portal');
        }

        $booking = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('b.booking_id', $bid)
            ->select('b.*', 'c.company_name', 'c.contact_person', 'c.client_type')
            ->first();
        if (! $booking) {
            return redirect()->route('crew-portal');
        }

        $equipQuery = DB::table('booking_equipment as be')
            ->join('equipment as e', 'be.equipment_id', '=', 'e.equipment_id')
            ->join('equipment_categories as ec', 'e.category_id', '=', 'ec.category_id')
            ->leftJoin('equipment_checklist as co', function ($j) use ($bid) {
                $j->on('co.equipment_id', '=', 'be.equipment_id')->where('co.booking_id', $bid)->where('co.direction', 'out');
            })
            ->leftJoin('equipment_checklist as ci', function ($j) use ($bid) {
                $j->on('ci.equipment_id', '=', 'be.equipment_id')->where('ci.booking_id', $bid)->where('ci.direction', 'in');
            })
            ->leftJoin('users as uo', 'co.checked_by', '=', 'uo.user_id')
            ->leftJoin('users as ui', 'ci.checked_by', '=', 'ui.user_id')
            ->where('be.booking_id', $bid)
            ->select(
                DB::raw("'equipment' as item_type"), 'be.equipment_id as ref_id', 'be.quantity',
                'e.equipment_name as item_name', 'e.brand', 'ec.category_name',
                'co.quantity_actual as co_qty', 'co.condition_out', 'co.checked as co_checked',
                'co.notes as co_notes', 'co.checked_at as co_at',
                DB::raw("CONCAT(uo.first_name,' ',uo.last_name) as co_by_name"),
                'ci.quantity_actual as ci_qty', 'ci.condition_in', 'ci.checked as ci_checked',
                'ci.notes as ci_notes', 'ci.checked_at as ci_at',
                DB::raw("CONCAT(ui.first_name,' ',ui.last_name) as ci_by_name")
            );

        $accQuery = DB::table('booking_accessories as ba')
            ->join('accessories as a', 'ba.accessory_id', '=', 'a.accessory_id')
            ->leftJoin('equipment_checklist as co', function ($j) use ($bid) {
                $j->on('co.accessory_id', '=', 'ba.accessory_id')->where('co.booking_id', $bid)->where('co.direction', 'out');
            })
            ->leftJoin('equipment_checklist as ci', function ($j) use ($bid) {
                $j->on('ci.accessory_id', '=', 'ba.accessory_id')->where('ci.booking_id', $bid)->where('ci.direction', 'in');
            })
            ->leftJoin('users as uo', 'co.checked_by', '=', 'uo.user_id')
            ->leftJoin('users as ui', 'ci.checked_by', '=', 'ui.user_id')
            ->where('ba.booking_id', $bid)
            ->select(
                DB::raw("'accessory' as item_type"), 'ba.accessory_id as ref_id', 'ba.quantity',
                'a.accessory_name as item_name', DB::raw("'' as brand"), DB::raw("'Accessory' as category_name"),
                'co.quantity_actual as co_qty', 'co.condition_out', 'co.checked as co_checked',
                'co.notes as co_notes', 'co.checked_at as co_at',
                DB::raw("CONCAT(uo.first_name,' ',uo.last_name) as co_by_name"),
                'ci.quantity_actual as ci_qty', 'ci.condition_in', 'ci.checked as ci_checked',
                'ci.notes as ci_notes', 'ci.checked_at as ci_at',
                DB::raw("CONCAT(ui.first_name,' ',ui.last_name) as ci_by_name")
            );

        $equipLines = $equipQuery->unionAll($accQuery)->orderBy('category_name')->orderBy('item_name')->get();

        $totalItems = $equipLines->count();
        $outDone = $equipLines->filter(fn ($e) => $e->co_checked)->count();
        $inDone = $equipLines->filter(fn ($e) => $e->ci_checked)->count();
        $damaged = $equipLines->filter(fn ($e) => in_array($e->condition_in, ['damaged', 'missing'], true))->count();

        $printBadge = ['excellent' => 'ok', 'good' => 'ok', 'fair' => 'warn', 'damaged' => 'bad', 'missing' => 'bad'];

        return view('checklist-print', [
            'booking' => $booking, 'bid' => $bid, 'equipLines' => $equipLines,
            'totalItems' => $totalItems, 'outDone' => $outDone, 'inDone' => $inDone, 'damaged' => $damaged,
            'condOut' => ['excellent' => 'Excellent', 'good' => 'Good', 'fair' => 'Fair'],
            'condIn' => ['excellent' => 'Excellent', 'good' => 'Good', 'fair' => 'Fair', 'damaged' => 'Damaged', 'missing' => 'Missing'],
            'condBadge' => $printBadge,
            'backUrl' => route('crew-portal'),
        ]);
    }

    public function printAttendance(Request $request)
    {
        $user = Auth::user();
        $role = $user->role->role_name ?? null;
        if (! $user || $role !== 'crew') {
            return redirect()->route('dashboard');
        }

        $crewMember = DB::table('crew_members')->where('user_id', $user->user_id)->first();
        $bid = (int) $request->query('booking_id', 0);
        if (! $bid || ! $crewMember) {
            return redirect()->route('crew-portal');
        }

        $ownsBooking = DB::table('booking_crew')->where('crew_id', $crewMember->crew_id)
            ->where('booking_id', $bid)->whereNotIn('assignment_status', ['declined', 'back_out'])->exists();
        if (! $ownsBooking) {
            return redirect()->route('crew-portal');
        }

        $booking = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('b.booking_id', $bid)
            ->select('b.*', 'c.company_name', 'c.contact_person', 'c.client_type')
            ->first();
        if (! $booking) {
            return redirect()->route('crew-portal');
        }

        $records = DB::table('crew_attendance as ca')
            ->join('crew_members as cm', 'ca.crew_id', '=', 'cm.crew_id')
            ->leftJoin('crew_positions as cp', 'cm.primary_position_id', '=', 'cp.position_id')
            ->leftJoin('crew_members as rep', 'ca.replacement_crew_id', '=', 'rep.crew_id')
            ->leftJoin('users as logger', 'ca.logged_by', '=', 'logger.user_id')
            ->where('ca.booking_id', $bid)
            ->orderBy('ca.attendance_date')->orderBy('cm.last_name')
            ->select('ca.*', DB::raw("CONCAT(cm.first_name,' ',cm.last_name) as crew_name"), 'cp.position_name',
                DB::raw("CONCAT(rep.first_name,' ',rep.last_name) as replacement_name"),
                DB::raw("CONCAT(logger.first_name,' ',logger.last_name) as logged_by_name"))
            ->get();

        $totalCrew = (int) DB::table('booking_crew')->where('booking_id', $bid)->whereNotIn('assignment_status', ['declined', 'back_out'])->count();
        $presentCount = $records->whereIn('status', ['present', 'late'])->count();
        $absentCount = $records->whereIn('status', ['absent', 'no_show'])->count();

        return view('attendance-print', [
            'booking' => $booking, 'bid' => $bid, 'records' => $records,
            'totalCrew' => $totalCrew, 'presentCount' => $presentCount, 'absentCount' => $absentCount,
            'attStatusLabel' => ['present' => 'Present', 'late' => 'Late', 'absent' => 'Absent', 'no_show' => 'No Show', 'back_out' => 'Back Out'],
            'backUrl' => route('crew-portal'),
        ]);
    }

    private function handleAction(Request $request, int $uid): ?array
    {
        $action = $request->input('action', '');

        $crewMember = DB::table('crew_members')->where('user_id', $uid)->first();

        if ($action === 'crew_request_profile_change' && $crewMember) {
            $existing = DB::table('crew_profile_requests')
                ->where('crew_id', $crewMember->crew_id)->where('status', 'pending')->exists();
            if ($existing) {
                return ['type' => 'danger', 'text' => 'You already have a profile change request awaiting approval.'];
            }

            $editable = ['email' => 'email', 'phone' => 'phone', 'gcash_number' => 'gcash_number', 'address' => 'address'];
            $changes = [];
            foreach ($editable as $field => $column) {
                if (! $request->has($field)) {
                    continue;
                }
                $new = trim((string) $request->input($field, ''));
                $old = (string) ($crewMember->{$column} ?? '');
                if ($new !== $old) {
                    $changes[$field] = ['old' => $old, 'new' => $new];
                }
            }

            $wantsPasswordChange = $request->filled('new_password') || $request->filled('current_password') || $request->filled('confirm_password');
            $newPasswordHash = null;
            if ($wantsPasswordChange) {
                $curr = $request->input('current_password', '');
                $new = $request->input('new_password', '');
                $conf = $request->input('confirm_password', '');
                $hash = DB::table('users')->where('user_id', $uid)->value('password_hash');
                if (! Hash::check($curr, $hash)) {
                    return ['type' => 'danger', 'text' => 'Current password is incorrect.'];
                }
                if (strlen($new) < 8) {
                    return ['type' => 'danger', 'text' => 'New password must be at least 8 characters.'];
                }
                if ($new !== $conf) {
                    return ['type' => 'danger', 'text' => 'New password and confirmation do not match.'];
                }
                $newPasswordHash = Hash::make($new);
            }

            if (! $changes && ! $newPasswordHash) {
                return ['type' => 'danger', 'text' => 'No changes to submit.'];
            }

            DB::table('crew_profile_requests')->insert([
                'crew_id' => $crewMember->crew_id,
                'changes' => $changes ? json_encode($changes) : null,
                'password_change_requested' => $newPasswordHash ? 1 : 0,
                'new_password_hash' => $newPasswordHash,
                'status' => 'pending',
                'requested_at' => now(),
            ]);
            ActivityLog::record($uid, 'request', 'crew_profile', 'Requested a profile change', $crewMember->crew_id);

            return ['type' => 'success', 'text' => 'Your changes were submitted for approval. You\'ll see them reflected once reviewed.'];
        }

        if ($action === 'crew_file_incident' && $crewMember) {
            $bid = (int) $request->input('booking_id', 0);
            $itemType = $request->input('item_type') === 'accessory' ? 'accessory' : 'equipment';
            $eid = $itemType === 'equipment' ? (int) $request->input('equipment_id', 0) : 0;
            $aid = $itemType === 'accessory' ? (int) $request->input('accessory_id', 0) : 0;

            $unitField = $itemType === 'accessory' ? 'accessory_unit' : 'equipment_unit';
            $unitInput = (string) $request->input($unitField, '');
            $equipmentUnitId = null;
            $accessoryUnitId = null;
            if (str_starts_with($unitInput, 'u')) {
                $candidate = (int) substr($unitInput, 1);
                if ($candidate && $itemType === 'equipment' && DB::table('equipment_units')->where('unit_id', $candidate)->where('equipment_id', $eid)->exists()) {
                    $equipmentUnitId = $candidate;
                } elseif ($candidate && $itemType === 'accessory' && DB::table('accessory_units')->where('unit_id', $candidate)->where('accessory_id', $aid)->exists()) {
                    $accessoryUnitId = $candidate;
                }
            }
            $itype = $request->input('incident_type', 'damaged');
            $idate = $request->input('incident_date', now()->toDateString());
            $itime = trim((string) $request->input('incident_time', ''));
            $desc = trim((string) $request->input('description', ''));
            $cause = $request->input('cause', 'unknown');
            $dtypes = array_values(array_unique(array_filter($request->input('damage_types', []))));
            $dothers = trim((string) $request->input('damage_others_note', ''));
            $photoUpload = EvidencePhotoUpload::handle($request->file('photo'), 'incident-photos');
            if ($photoUpload['error']) {
                return ['type' => 'danger', 'text' => $photoUpload['error']];
            }

            $itemId = $itemType === 'accessory' ? $aid : $eid;
            if (! $bid || ! $itemId || ! $desc) {
                return ['type' => 'danger', 'text' => 'Please fill in all required fields.'];
            }

            $ownsBooking = DB::table('booking_crew')->where('crew_id', $crewMember->crew_id)
                ->where('booking_id', $bid)->whereNotIn('assignment_status', ['declined', 'back_out'])->exists();
            if (! $ownsBooking) {
                return ['type' => 'danger', 'text' => 'That booking is not assigned to you.'];
            }
            if ($itemType === 'equipment') {
                $itemOnBooking = DB::table('booking_equipment')->where('booking_id', $bid)->where('equipment_id', $eid)->exists();
                if (! $itemOnBooking) {
                    return ['type' => 'danger', 'text' => 'That equipment is not part of this booking.'];
                }
            } else {
                $itemOnBooking = DB::table('booking_accessories')->where('booking_id', $bid)->where('accessory_id', $aid)->exists();
                if (! $itemOnBooking) {
                    return ['type' => 'danger', 'text' => 'That accessory is not part of this booking.'];
                }
            }

            $dupe = DB::table('incident_reports')
                ->where('reported_by', $uid)->where('booking_id', $bid)
                ->where('equipment_id', $eid ?: null)->where('accessory_id', $aid ?: null)
                ->where('description', $desc)
                ->where('created_at', '>=', now()->subSeconds(20))
                ->orderByDesc('created_at')
                ->first();
            if ($dupe) {
                return ['type' => 'success', 'text' => "Incident report <strong>{$dupe->incident_number}</strong> filed. The admin will review and set any charges."];
            }

            $irNum = DB::transaction(function () use ($bid, $eid, $aid, $equipmentUnitId, $accessoryUnitId, $uid, $itype, $idate, $itime, $desc, $photoUpload, $cause, $dothers, $dtypes) {
                $year = now()->year;
                $count = (int) DB::table('incident_reports')->whereYear('created_at', $year)->count();
                $irNum = 'IR-' . $year . '-' . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);

                $incidentId = DB::table('incident_reports')->insertGetId([
                    'booking_id' => $bid, 'equipment_id' => $eid ?: null, 'accessory_id' => $aid ?: null,
                    'equipment_unit_id' => $equipmentUnitId, 'accessory_unit_id' => $accessoryUnitId, 'reported_by' => $uid,
                    'incident_type' => $itype, 'incident_date' => $idate,
                    'incident_time' => $itime ?: null, 'description' => $desc,
                    'photo_path' => $photoUpload['success'] ? $photoUpload['path'] : null,
                    'cause' => $cause,
                    'charge_amount' => 0, 'status' => 'open', 'incident_number' => $irNum,
                    'damage_others_note' => $dothers, 'submitted_by_crew' => 1,
                ]);

                if ($dtypes) {
                    DB::table('incident_damage_types')->insertOrIgnore(array_map(
                        fn ($t) => ['incident_id' => $incidentId, 'damage_type' => $t], $dtypes
                    ));
                }
                ActivityLog::record($uid, 'create', 'incident', "Crew filed incident report $irNum", $incidentId);

                return $irNum;
            });

            return ['type' => 'success', 'text' => "Incident report <strong>$irNum</strong> filed. The admin will review and set any charges."];
        }

        if ($action === 'crew_confirm_arrival' && $crewMember) {
            $bid = (int) $request->input('booking_id', 0);
            $ownsBooking = DB::table('booking_crew')->where('crew_id', $crewMember->crew_id)
                ->where('booking_id', $bid)->whereNotIn('assignment_status', ['declined', 'back_out'])->exists();
            if (! $ownsBooking) {
                return ['type' => 'danger', 'text' => 'That booking is not assigned to you.'];
            }
            if (! $this->isHeadCrewOf((int) $crewMember->crew_id, $bid)) {
                return ['type' => 'danger', 'text' => 'Only the Head Crew of this booking can do this.'];
            }

            DB::table('bookings')->where('booking_id', $bid)->update([
                'field_arrival_confirmed_at' => now(), 'field_arrival_confirmed_by' => $uid,
            ]);
            ActivityLog::record($uid, 'field_arrival_confirmed', 'booking', "Crew confirmed field arrival for booking #$bid", $bid);

            return ['type' => 'success', 'text' => 'Field arrival confirmed — office staff can now see it on the Checklist page.'];
        }

        if ($action === 'crew_undo_arrival' && $crewMember) {
            $bid = (int) $request->input('booking_id', 0);
            $ownsBooking = DB::table('booking_crew')->where('crew_id', $crewMember->crew_id)
                ->where('booking_id', $bid)->whereNotIn('assignment_status', ['declined', 'back_out'])->exists();
            if (! $ownsBooking) {
                return ['type' => 'danger', 'text' => 'That booking is not assigned to you.'];
            }
            if (! $this->isHeadCrewOf((int) $crewMember->crew_id, $bid)) {
                return ['type' => 'danger', 'text' => 'Only the Head Crew of this booking can do this.'];
            }

            DB::table('bookings')->where('booking_id', $bid)->update([
                'field_arrival_confirmed_at' => null, 'field_arrival_confirmed_by' => null,
            ]);
            ActivityLog::record($uid, 'field_arrival_confirmed', 'booking', "Crew undid field arrival confirmation for booking #$bid", $bid);

            return ['type' => 'success', 'text' => 'Arrival confirmation undone.'];
        }

        if ($action === 'crew_save_checklist' && $crewMember) {
            $bid = (int) $request->input('booking_id', 0);
            $direction = $request->input('direction') === 'in' ? 'in' : 'out';

            if ($direction === 'out') {
                return ['type' => 'danger', 'text' => 'Only office staff can release equipment. Use Confirm Field Arrival instead.'];
            }

            $rawChecked = (array) $request->input('items', []);
            $checkedEquipIds = [];
            $checkedAccIds = [];
            foreach ($rawChecked as $token) {
                if (preg_match('/^acc_(\d+)$/', (string) $token, $m)) {
                    $checkedAccIds[] = (int) $m[1];
                } elseif (preg_match('/^eq_(\d+)$/', (string) $token, $m)) {
                    $checkedEquipIds[] = (int) $m[1];
                } elseif (ctype_digit((string) $token)) {
                    $checkedEquipIds[] = (int) $token;
                }
            }

            $ownsBooking = DB::table('booking_crew')->where('crew_id', $crewMember->crew_id)
                ->where('booking_id', $bid)->whereNotIn('assignment_status', ['declined', 'back_out'])->exists();
            if (! $ownsBooking) {
                return ['type' => 'danger', 'text' => 'That booking is not assigned to you.'];
            }
            if (! $this->isHeadCrewOf((int) $crewMember->crew_id, $bid)) {
                return ['type' => 'danger', 'text' => 'Only the Head Crew of this booking can do this.'];
            }

            $bookingEquip = DB::table('booking_equipment')->where('booking_id', $bid)->get()->keyBy('equipment_id');

            foreach ($bookingEquip as $eid => $line) {
                $checked = in_array($eid, $checkedEquipIds, true) ? 1 : 0;
                $qty = (int) $line->quantity;

                $existing = DB::table('equipment_checklist')->where('booking_id', $bid)->where('equipment_id', $eid)->where('direction', 'in')->value('checklist_id');
                if ($existing) {
                    DB::table('equipment_checklist')->where('checklist_id', $existing)->update([
                        'checked' => $checked, 'quantity_actual' => $checked ? $qty : 0, 'condition_in' => 'good', 'checked_by' => $uid, 'checked_at' => now(),
                    ]);
                } elseif ($checked) {
                    DB::table('equipment_checklist')->insert([
                        'booking_id' => $bid, 'equipment_id' => $eid, 'direction' => 'in', 'quantity_expected' => $qty,
                        'quantity_actual' => $qty, 'condition_in' => 'good', 'checked' => 1, 'checked_by' => $uid, 'checked_at' => now(),
                    ]);
                }

                if ($checked) {
                    $existTx = DB::table('equipment_transactions')->where('booking_id', $bid)->where('equipment_id', $eid)->where('transaction_type', 'checkin')->value('transaction_id');
                    if (! $existTx) {
                        DB::table('equipment_transactions')->insert([
                            'booking_id' => $bid, 'equipment_id' => $eid, 'transaction_type' => 'checkin',
                            'transaction_date' => now(), 'condition_in' => 'good', 'handled_by' => $uid,
                        ]);
                        DB::table('equipment')->where('equipment_id', $eid)->update(['availability_status' => 'available']);
                    }
                }
            }

            $bookingAcc = DB::table('booking_accessories')->where('booking_id', $bid)->get()->keyBy('accessory_id');
            foreach ($bookingAcc as $aid => $line) {
                $checked = in_array($aid, $checkedAccIds, true) ? 1 : 0;
                $qty = (int) $line->quantity;

                $existing = DB::table('equipment_checklist')->where('booking_id', $bid)->where('accessory_id', $aid)->where('direction', 'in')->value('checklist_id');
                if ($existing) {
                    DB::table('equipment_checklist')->where('checklist_id', $existing)->update([
                        'checked' => $checked, 'quantity_actual' => $checked ? $qty : 0, 'condition_in' => 'good', 'checked_by' => $uid, 'checked_at' => now(),
                    ]);
                } elseif ($checked) {
                    DB::table('equipment_checklist')->insert([
                        'booking_id' => $bid, 'accessory_id' => $aid, 'direction' => 'in', 'quantity_expected' => $qty,
                        'quantity_actual' => $qty, 'condition_in' => 'good', 'checked' => 1, 'checked_by' => $uid, 'checked_at' => now(),
                    ]);
                }
            }

            $totalEquip = (int) DB::table('booking_equipment')->where('booking_id', $bid)->count();
            $returnedCount = (int) DB::table('equipment_checklist')->where('booking_id', $bid)->where('direction', 'in')->where('checked', 1)->count();
            $curStatus = DB::table('bookings')->where('booking_id', $bid)->value('booking_status');
            if ($totalEquip > 0 && $returnedCount >= $totalEquip) {
                if (in_array($curStatus, ['ongoing', 'confirmed'], true)) {
                    DB::table('bookings')->where('booking_id', $bid)->update(['booking_status' => 'pending_inspection', 'updated_at' => now()]);
                    ActivityLog::record($uid, 'status_change', 'booking', "Booking #$bid pending inspection — all equipment checked in via crew checklist", $bid);
                }
            } elseif ($curStatus === 'pending_inspection') {
                DB::table('bookings')->where('booking_id', $bid)->update(['booking_status' => 'ongoing', 'updated_at' => now()]);
                ActivityLog::record($uid, 'status_change', 'booking', "Booking #$bid reopened to ongoing — crew edited checklist after it was pending inspection", $bid);
            }

            ActivityLog::record($uid, 'checklistin', 'booking', "Crew checklist in saved for booking #$bid", $bid);

            return ['type' => 'success', 'text' => 'Checklist <strong>IN</strong> saved successfully.'];
        }

        if ($action === 'crew_mark_attendance' && $crewMember) {
            $bid = (int) $request->input('booking_id', 0);
            $date = $request->input('attendance_date') ?: now()->toDateString();

            $ownsBooking = DB::table('booking_crew')->where('crew_id', $crewMember->crew_id)
                ->where('booking_id', $bid)->whereNotIn('assignment_status', ['declined', 'back_out'])->exists();
            if (! $ownsBooking) {
                return ['type' => 'danger', 'text' => 'That booking is not assigned to you.'];
            }
            if (! $this->isHeadCrewOf((int) $crewMember->crew_id, $bid)) {
                return ['type' => 'danger', 'text' => 'Only the Head Crew of this booking can do this.'];
            }

            $shootRange = DB::table('bookings')->where('booking_id', $bid)->select('shoot_date_start', 'shoot_date_end')->first();
            if ($shootRange && ($date < $shootRange->shoot_date_start || $date > $shootRange->shoot_date_end)) {
                return ['type' => 'danger', 'text' => 'Attendance date must fall within this booking\'s shoot dates (' . $shootRange->shoot_date_start . ' to ' . $shootRange->shoot_date_end . ').'];
            }

            $logged = 0;
            DB::transaction(function () use ($request, $bid, $date, $uid, &$logged) {
                foreach ((array) $request->input('crew_status', []) as $cid => $status) {
                    $cid = (int) $cid;
                    $memberOnBooking = DB::table('booking_crew')->where('booking_id', $bid)->where('crew_id', $cid)
                        ->where('assignment_status', '!=', 'declined')->exists();
                    if (! $memberOnBooking) {
                        continue;
                    }

                    $reason = $request->input("crew_reason.$cid", '');
                    $repId = (int) $request->input("crew_replacement.$cid", 0);

                    DB::table('crew_attendance')->updateOrInsert(
                        ['booking_id' => $bid, 'crew_id' => $cid, 'attendance_date' => $date],
                        ['status' => $status, 'reason' => $reason, 'replacement_crew_id' => $repId ?: null, 'logged_by' => $uid]
                    );
                    $logged++;

                    $currentAssignment = DB::table('booking_crew')->where('booking_id', $bid)->where('crew_id', $cid)->value('assignment_status');
                    if ($status === 'back_out' && $currentAssignment !== 'back_out') {
                        DB::table('booking_crew')->where('booking_id', $bid)->where('crew_id', $cid)
                            ->update(['assignment_status' => 'back_out']);
                    } elseif ($status !== 'back_out' && $currentAssignment === 'back_out') {
                        DB::table('booking_crew')->where('booking_id', $bid)->where('crew_id', $cid)
                            ->update(['assignment_status' => 'confirmed']);
                    }
                }
            });
            ActivityLog::record($uid, 'log_attendance', 'crew', "Crew lead logged $logged attendance record(s) for booking #$bid");

            return ['type' => 'success', 'text' => "Attendance logged for <strong>$logged</strong> crew member(s)."];
        }

        if ($action === 'crew_request_field_item' && $crewMember) {
            $bid = (int) $request->input('booking_id', 0);
            $itemType = in_array($request->input('item_type'), ['equipment', 'accessory', 'crew'], true)
                ? $request->input('item_type') : 'equipment';
            $qty = max(1, (int) $request->input('quantity', 1));
            $reason = trim((string) $request->input('reason', ''));

            $booking = DB::table('bookings')->where('booking_id', $bid)->first();
            $ownsBooking = DB::table('booking_crew')->where('crew_id', $crewMember->crew_id)
                ->where('booking_id', $bid)->whereNotIn('assignment_status', ['declined', 'back_out'])->exists();
            if (! $booking || ! $ownsBooking || ! in_array($booking->booking_status, ['confirmed', 'ongoing'], true)) {
                return ['type' => 'danger', 'text' => 'That booking is not assigned to you or is not currently active.'];
            }

            if ($itemType === 'equipment') {
                $eid = (int) $request->input('equipment_id', 0);
                if (! $eid) {
                    return ['type' => 'danger', 'text' => 'Please select equipment.'];
                }
                $eqRate = (float) DB::table('equipment')->where('equipment_id', $eid)->value('daily_rate');
                DB::table('booking_equipment_requests')->insert([
                    'booking_id' => $bid, 'requested_by' => $uid, 'item_type' => 'equipment',
                    'equipment_id' => $eid, 'quantity' => $qty, 'reason' => $reason, 'daily_rate' => $eqRate, 'created_at' => now(),
                ]);
            } elseif ($itemType === 'accessory') {
                $aid = (int) $request->input('accessory_id', 0);
                if (! $aid) {
                    return ['type' => 'danger', 'text' => 'Please select an accessory.'];
                }
                $accRate = (float) DB::table('accessories')->where('accessory_id', $aid)->value('daily_rate');
                DB::table('booking_equipment_requests')->insert([
                    'booking_id' => $bid, 'requested_by' => $uid, 'item_type' => 'accessory',
                    'accessory_id' => $aid, 'quantity' => $qty, 'reason' => $reason, 'daily_rate' => $accRate, 'created_at' => now(),
                ]);
            } else {
                $pid = (int) $request->input('position_id', 0);
                if (! $pid) {
                    return ['type' => 'danger', 'text' => 'Please select a role.'];
                }
                DB::table('booking_equipment_requests')->insert([
                    'booking_id' => $bid, 'requested_by' => $uid, 'item_type' => 'crew',
                    'position_id' => $pid, 'quantity' => 1, 'reason' => $reason, 'daily_rate' => 0, 'created_at' => now(),
                ]);
            }

            ActivityLog::record($uid, 'create', 'booking', "Crew requested a field item ($itemType) for booking #$bid", $bid);

            return ['type' => 'success', 'text' => 'Request submitted. The admin will review it shortly.'];
        }

        if ($action === 'update_maintenance_status') {
            $schId = (int) $request->input('schedule_id', 0);
            $status = $request->input('status', 'in_progress');
            $notes = trim((string) $request->input('notes', ''));

            if ($schId && $crewMember) {
                $update = ['status' => $status, 'notes' => $notes];
                if (in_array($status, ['completed', 'cancelled'], true)) {
                    $update['completed_at'] = now();
                }
                if ($request->hasFile('photo')) {
                    $photoUpload = EvidencePhotoUpload::handle($request->file('photo'), 'maintenance-photos');
                    if ($photoUpload['error']) {
                        return ['type' => 'danger', 'text' => $photoUpload['error']];
                    }
                    if ($photoUpload['success']) {
                        $update['photo_path'] = $photoUpload['path'];
                    }
                }
                DB::table('maintenance_schedules')
                    ->where('schedule_id', $schId)
                    ->where('assigned_crew_id', $crewMember->crew_id)
                    ->update($update);

                return ['type' => 'success', 'text' => 'Maintenance status updated.'];
            }
        }

        return null;
    }

    public function incidentPhoto(int $id)
    {
        $user = Auth::user();
        $crewMember = DB::table('crew_members')->where('user_id', $user->user_id ?? 0)->first();
        abort_unless($crewMember, 404);

        $path = DB::table('incident_reports')->where('incident_id', $id)->where('reported_by', $user->user_id)->value('photo_path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    public function maintenancePhoto(int $id)
    {
        $user = Auth::user();
        $crewMember = DB::table('crew_members')->where('user_id', $user->user_id ?? 0)->first();
        abort_unless($crewMember, 404);

        $path = DB::table('maintenance_schedules')->where('schedule_id', $id)->where('assigned_crew_id', $crewMember->crew_id)->value('photo_path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

}
