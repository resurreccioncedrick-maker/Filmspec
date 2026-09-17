<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\BookingCosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class IncidentsController extends Controller
{
    private array $typeBadge = ['damaged' => 'badge-red', 'missing' => 'badge-orange', 'malfunction' => 'badge-yellow', 'late_return' => 'badge-purple'];

    private array $typeLabel = ['damaged' => 'Damaged', 'missing' => 'Missing', 'malfunction' => 'Malfunction', 'late_return' => 'Late Return'];

    private array $statusBadge = ['open' => 'badge-yellow', 'resolved' => 'badge-green', 'closed' => 'badge-gray'];

    private array $causeLabel = ['negligence' => 'Negligence', 'accident' => 'Accident', 'lifespan' => 'End of Lifespan', 'unknown' => 'Unknown'];

    private array $resLabel = ['repair' => 'For Repair', 'replacement' => 'Replacement', 'write_off' => 'Write-Off', 'installment_payment' => 'Installment', 'pending' => 'Pending'];

    private array $damageTypes = ['scratches' => 'Scratches', 'cracked_broken' => 'Cracked / Broken Part', 'electronic_malfunction' => 'Electronic Malfunction', 'missing_part' => 'Missing Part', 'others' => 'Others'];

    private array $maintTypeBadge = ['preventive' => 'badge-blue', 'corrective' => 'badge-orange', 'calibration' => 'badge-purple', 'cleaning' => 'badge-green'];

    private array $maintTypeLabel = ['preventive' => 'Preventive', 'corrective' => 'Corrective', 'calibration' => 'Calibration', 'cleaning' => 'Cleaning'];

    private array $maintStatBadge = ['pending' => 'badge-yellow', 'in_progress' => 'badge-blue', 'completed' => 'badge-green', 'cancelled' => 'badge-gray'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';
        $canPost = in_array($role, ['super_admin', 'admin', 'operations_manager', 'traffic'], true);
        $canManage = in_array($role, config('filmspec.manage_roles'), true);

        $msg = null;
        if ($request->isMethod('post') && $canPost) {
            $msg = $this->handleAction($request, $user->user_id, $canManage);
        }

        $tab = $request->query('tab', 'all');
        $search = $request->query('q', '');
        $page = max(1, (int) $request->query('p', 1));
        $perPage = 20;

        $incQuery = DB::table('incident_reports as ir')
            ->join('bookings as b', 'ir.booking_id', '=', 'b.booking_id')
            ->join('equipment as e', 'ir.equipment_id', '=', 'e.equipment_id')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id');
        if ($tab !== 'all') {
            $incQuery->where('ir.status', $tab);
        }
        if ($search) {
            $incQuery->where(function ($w) use ($search) {
                $w->where('b.booking_reference', 'like', "%$search%")
                    ->orWhere('e.equipment_name', 'like', "%$search%")
                    ->orWhere('ir.incident_number', 'like', "%$search%")
                    ->orWhere('c.contact_person', 'like', "%$search%")
                    ->orWhere('c.company_name', 'like', "%$search%");
            });
        }

        $total = (clone $incQuery)->count();
        $pages = max(1, (int) ceil($total / $perPage));

        $incidents = $incQuery
            ->leftJoin('users as u', 'ir.reported_by', '=', 'u.user_id')
            ->orderByDesc('ir.created_at')
            ->select('ir.*', 'e.equipment_name', 'e.brand', 'e.serial_number as equip_serial',
                'b.booking_reference', 'b.project_title', 'b.shoot_location',
                'c.contact_person', 'c.company_name',
                DB::raw("CONCAT(u.first_name,' ',u.last_name) AS reported_by_name"))
            ->forPage($page, $perPage)
            ->get();

        // damage_types now lives in incident_damage_types; re-attach it to each row as the
        // same JSON-string shape the edit-modal JS (openEditIncident -> JSON.parse) expects,
        // so the page's own Js::from($ir) embed keeps working unchanged.
        $incidentIds = $incidents->pluck('incident_id')->all();
        $damageTypesByIncident = [];
        if ($incidentIds) {
            DB::table('incident_damage_types')->whereIn('incident_id', $incidentIds)
                ->orderBy('id')->get(['incident_id', 'damage_type'])
                ->each(function ($row) use (&$damageTypesByIncident) {
                    $damageTypesByIncident[$row->incident_id][] = $row->damage_type;
                });
        }
        foreach ($incidents as $ir) {
            $ir->damage_types = json_encode($damageTypesByIncident[$ir->incident_id] ?? []);
        }

        $kpis = [
            'total' => (int) DB::table('incident_reports')->count(),
            'open' => (int) DB::table('incident_reports')->where('status', 'open')->count(),
            'resolved' => (int) DB::table('incident_reports')->where('status', 'resolved')->count(),
            'charges' => (float) DB::table('incident_reports')->sum('charge_amount'),
        ];
        $tabCounts = [
            'all' => $kpis['total'],
            'open' => $kpis['open'],
            'resolved' => $kpis['resolved'],
            'closed' => (int) DB::table('incident_reports')->where('status', 'closed')->count(),
        ];

        $activeBookings = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->whereIn('b.booking_status', ['confirmed', 'ongoing', 'returned', 'pending_inspection', 'completed'])
            ->orderByDesc('b.shoot_date_start')
            ->select('b.booking_id', 'b.booking_reference', 'b.project_title', 'b.shoot_location',
                'c.contact_person', 'c.company_name')
            ->limit(150)
            ->get();

        $allEquipment = DB::table('equipment')
            ->where('condition_status', '!=', 'retired')
            ->orderBy('equipment_name')
            ->select('equipment_id', 'equipment_name', 'brand', 'serial_number')
            ->get();

        $bookingIds = $activeBookings->pluck('booking_id')->all();

        $bookingCrewMap = [];
        if ($bookingIds) {
            $crewRows = DB::table('booking_crew as bc')
                ->join('crew_members as cm', 'bc.crew_id', '=', 'cm.crew_id')
                ->leftJoin('crew_positions as cp', 'bc.position_id', '=', 'cp.position_id')
                ->whereIn('bc.booking_id', $bookingIds)
                ->where('bc.assignment_status', '!=', 'declined')
                ->orderBy('cp.position_name')->orderBy('cm.last_name')
                ->select('bc.booking_id', 'cm.first_name', 'cm.last_name', DB::raw("COALESCE(cp.position_name,'Crew') AS position_name"))
                ->get();
            foreach ($crewRows as $row) {
                $bookingCrewMap[$row->booking_id][] = $row;
            }
        }

        $bookingEquipMap = [];
        if ($bookingIds) {
            $equipRows = DB::table('booking_equipment as be')
                ->join('equipment as e', 'be.equipment_id', '=', 'e.equipment_id')
                ->whereIn('be.booking_id', $bookingIds)
                ->select('be.booking_id', 'e.equipment_id', 'e.equipment_name', 'e.brand', 'e.serial_number')
                ->get();
            foreach ($equipRows as $row) {
                $bookingEquipMap[$row->booking_id][] = $row;
            }
        }

        $maintTab = $request->query('mtab', 'pending');
        $maintQuery = DB::table('maintenance_schedules as ms')
            ->join('equipment as e', 'ms.equipment_id', '=', 'e.equipment_id')
            ->leftJoin('crew_members as cm', 'ms.assigned_crew_id', '=', 'cm.crew_id')
            ->leftJoin('incident_reports as ir', 'ms.incident_id', '=', 'ir.incident_id');
        if ($maintTab !== 'all') {
            $maintQuery->where('ms.status', $maintTab);
        }
        $maintenances = $maintQuery
            ->orderBy('ms.scheduled_date')->orderByDesc('ms.created_at')
            ->select('ms.*', 'e.equipment_name', 'e.brand', 'e.serial_number as equip_serial',
                DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS assigned_crew_name"), 'ir.incident_number')
            ->limit(100)
            ->get();

        $maintCounts = [
            'pending' => (int) DB::table('maintenance_schedules')->where('status', 'pending')->count(),
            'in_progress' => (int) DB::table('maintenance_schedules')->where('status', 'in_progress')->count(),
            'completed' => (int) DB::table('maintenance_schedules')->where('status', 'completed')->count(),
            'all' => (int) DB::table('maintenance_schedules')->count(),
        ];

        $crewForMaint = DB::table('crew_members as cm')
            ->leftJoin('crew_positions as cp', 'cm.primary_position_id', '=', 'cp.position_id')
            ->where('cm.status', 'active')
            ->orderBy('cm.last_name')->orderBy('cm.first_name')
            ->select('cm.crew_id', 'cm.first_name', 'cm.last_name', 'cp.position_name')
            ->get();

        $viewTab = $request->query('view', 'incidents');

        return view('incidents', [
            'msg' => $msg, 'canManage' => $canManage,
            'tab' => $tab, 'search' => $search, 'page' => $page, 'pages' => $pages,
            'incidents' => $incidents, 'kpis' => $kpis, 'tabCounts' => $tabCounts,
            'activeBookings' => $activeBookings, 'allEquipment' => $allEquipment,
            'bookingCrewMap' => $bookingCrewMap, 'bookingEquipMap' => $bookingEquipMap,
            'maintTab' => $maintTab, 'maintenances' => $maintenances, 'maintCounts' => $maintCounts,
            'crewForMaint' => $crewForMaint, 'viewTab' => $viewTab,
            'typeBadge' => $this->typeBadge, 'typeLabel' => $this->typeLabel, 'statusBadge' => $this->statusBadge,
            'causeLabel' => $this->causeLabel, 'resLabel' => $this->resLabel, 'damageTypes' => $this->damageTypes,
            'maintTypeBadge' => $this->maintTypeBadge, 'maintTypeLabel' => $this->maintTypeLabel, 'maintStatBadge' => $this->maintStatBadge,
        ]);
    }

    public function print(int $id): View|\Illuminate\Http\RedirectResponse
    {
        $ir = DB::table('incident_reports as ir')
            ->join('bookings as b', 'ir.booking_id', '=', 'b.booking_id')
            ->join('equipment as e', 'ir.equipment_id', '=', 'e.equipment_id')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->leftJoin('users as u', 'ir.reported_by', '=', 'u.user_id')
            ->where('ir.incident_id', $id)
            ->select('ir.*', 'e.equipment_name', 'e.brand', 'e.serial_number as equip_serial',
                'b.booking_reference', 'b.project_title', 'b.shoot_location',
                'c.contact_person', 'c.company_name',
                DB::raw("CONCAT(u.first_name,' ',u.last_name) AS reported_by_name"))
            ->first();

        if (! $ir) {
            return redirect()->route('incidents');
        }

        $damageTypesSelected = DB::table('incident_damage_types')->where('incident_id', $id)->pluck('damage_type')->all();
        $crewLineup = json_decode($ir->crew_lineup ?? '{}', true) ?: [];

        $crewPositions = [
            'head_crew' => 'Head Crew', 'lcrew_1' => 'L/Crew 1', 'lcrew_2' => 'L/Crew 2',
            'lcrew_3' => 'L/Crew 3', 'lcrew_4' => 'L/Crew 4', 'lcrew_5' => 'L/Crew 5',
            'lcrew_6' => 'L/Crew 6', 'lcrew_7' => 'L/Crew 7', 'lcrew_8' => 'L/Crew 8',
            'truck_driver' => 'Truck Driver', 'fb_driver' => 'FB Driver',
        ];

        return view('incident-print', [
            'ir' => $ir, 'damageTypesSelected' => $damageTypesSelected, 'crewLineup' => $crewLineup,
            'damageTypes' => $this->damageTypes, 'crewPositions' => $crewPositions,
            'typeLabel' => $this->typeLabel, 'causeLabel' => $this->causeLabel, 'resLabel' => $this->resLabel,
        ]);
    }

    private function generateIrNumber(): string
    {
        $year = now()->year;
        $count = (int) DB::table('incident_reports')->whereYear('created_at', $year)->count();

        return 'IR-' . $year . '-' . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    private function buildLineup(Request $request, string $roleField, string $nameField): array
    {
        $roles = $request->input($roleField, []);
        $names = $request->input($nameField, []);
        $out = [];
        foreach ($roles as $idx => $r) {
            $r = trim((string) $r);
            $n = trim((string) ($names[$idx] ?? ''));
            if ($r || $n) {
                $out[] = ['role' => $r, 'name' => $n];
            }
        }

        return $out;
    }

    private function handleAction(Request $request, int $uid, bool $canManage): ?array
    {
        $action = $request->input('action', '');

        if ($action === 'create_maintenance') {
            $eid = (int) $request->input('equipment_id', 0);
            $date = $request->input('scheduled_date', now()->toDateString());
            $mtype = $request->input('maintenance_type', 'preventive');
            $cmId = (int) $request->input('assigned_crew_id', 0);
            $desc = trim((string) $request->input('description', ''));
            $iid = (int) $request->input('incident_id', 0);

            if ($eid && $date) {
                $scheduleId = DB::table('maintenance_schedules')->insertGetId([
                    'equipment_id' => $eid, 'scheduled_date' => $date, 'maintenance_type' => $mtype,
                    'assigned_crew_id' => $cmId ?: null, 'description' => $desc, 'incident_id' => $iid ?: null,
                    'created_by' => $uid,
                ]);
                ActivityLog::record($uid, 'create', 'maintenance', "Maintenance schedule created for equipment ID $eid", $scheduleId);

                return ['type' => 'success', 'text' => 'Maintenance schedule created.'];
            }

            return ['type' => 'danger', 'text' => 'Equipment and scheduled date are required.'];
        }

        if ($action === 'update_maintenance') {
            $schId = (int) $request->input('schedule_id', 0);
            $status = $request->input('status', 'pending');
            $notes = trim((string) $request->input('notes', ''));
            if ($schId) {
                $update = ['status' => $status, 'notes' => $notes];
                if (in_array($status, ['completed', 'cancelled'], true)) {
                    $update['completed_at'] = now();
                }
                DB::table('maintenance_schedules')->where('schedule_id', $schId)->update($update);

                return ['type' => 'success', 'text' => 'Maintenance schedule updated.'];
            }
        }

        if ($action === 'delete_maintenance') {
            $schId = (int) $request->input('schedule_id', 0);
            if ($schId && $canManage) {
                DB::table('maintenance_schedules')->where('schedule_id', $schId)->delete();

                return ['type' => 'success', 'text' => 'Schedule deleted.'];
            }
        }

        if ($action === 'create_incident') {
            $bid = (int) $request->input('booking_id', 0);
            $eid = (int) $request->input('equipment_id', 0);
            $itype = $request->input('incident_type', 'damaged');
            $idate = $request->input('incident_date', now()->toDateString());
            $itime = trim((string) $request->input('incident_time', ''));
            $desc = trim((string) $request->input('description', ''));
            $cause = $request->input('cause', 'unknown');
            $charge = (float) $request->input('charge_amount', 0);
            $payMode = $request->input('payment_mode', 'lump_sum');
            $dtypes = array_values(array_unique(array_filter($request->input('damage_types', []))));
            $dothers = trim((string) $request->input('damage_others_note', ''));
            $dop = trim((string) $request->input('dop_name', ''));
            $headCrew = trim((string) $request->input('head_crew_name', ''));
            $custodian = trim((string) $request->input('custodian_name', ''));
            $lineup = json_encode($this->buildLineup($request, 'crew_lineup_role', 'crew_lineup_name'));
            $extraSigs = json_encode($this->buildLineup($request, 'extra_sig_role', 'extra_sig_name'));

            if ($bid && $eid) {
                $irNum = $this->generateIrNumber();
                $incidentId = DB::table('incident_reports')->insertGetId([
                    'booking_id' => $bid, 'equipment_id' => $eid, 'reported_by' => $uid,
                    'incident_type' => $itype, 'incident_date' => $idate, 'incident_time' => $itime ?: null,
                    'description' => $desc, 'cause' => $cause, 'charge_amount' => $charge,
                    'payment_mode' => $payMode, 'status' => 'open', 'incident_number' => $irNum,
                    'damage_others_note' => $dothers,
                    'dop_name' => $dop, 'head_crew_name' => $headCrew, 'custodian_name' => $custodian,
                    'crew_lineup' => $lineup, 'extra_signatories' => $extraSigs,
                ]);
                if ($dtypes) {
                    DB::table('incident_damage_types')->insert(array_map(
                        fn ($t) => ['incident_id' => $incidentId, 'damage_type' => $t], $dtypes
                    ));
                }
                ActivityLog::record($uid, 'create', 'incident', "Incident report $irNum filed", $incidentId);
                // A charge set at filing time (staff/auto-filed paths, unlike the
                // always-zero crew self-report) must land in the booking's real total —
                // this was previously only wired up on BookingDetailController's separate
                // incident editor, never here. Always recompute, not just when charge > 0,
                // to stay correct regardless of what else changed the booking's total since.
                BookingCosting::updateBookingTotal($bid);
                $printUrl = route('incident-print', $incidentId);

                return ['type' => 'success', 'text' => "Report <strong>$irNum</strong> filed. <a href='$printUrl' target='_blank' style='text-decoration:underline'>Print &rarr;</a>"];
            }

            return ['type' => 'danger', 'text' => 'Booking and equipment are required.'];
        }

        if ($action === 'update_incident') {
            $iid = (int) $request->input('incident_id', 0);
            $itype = $request->input('incident_type', 'damaged');
            $idate = $request->input('incident_date', now()->toDateString());
            $itime = trim((string) $request->input('incident_time', ''));
            $desc = trim((string) $request->input('description', ''));
            $cause = $request->input('cause', 'unknown');
            $charge = (float) $request->input('charge_amount', 0);
            $payMode = $request->input('payment_mode', 'lump_sum');
            $dtypes = array_values(array_unique(array_filter($request->input('damage_types', []))));
            $dothers = trim((string) $request->input('damage_others_note', ''));
            $dop = trim((string) $request->input('dop_name', ''));
            $headCrew = trim((string) $request->input('head_crew_name', ''));
            $custodian = trim((string) $request->input('custodian_name', ''));
            $lineup = json_encode($this->buildLineup($request, 'crew_lineup_role', 'crew_lineup_name'));
            $extraSigs = json_encode($this->buildLineup($request, 'extra_sig_role', 'extra_sig_name'));

            $bookingId = (int) DB::table('incident_reports')->where('incident_id', $iid)->value('booking_id');

            DB::table('incident_reports')->where('incident_id', $iid)->update([
                'incident_type' => $itype, 'incident_date' => $idate, 'incident_time' => $itime ?: null,
                'description' => $desc, 'cause' => $cause, 'charge_amount' => $charge, 'payment_mode' => $payMode,
                'damage_others_note' => $dothers,
                'dop_name' => $dop, 'head_crew_name' => $headCrew, 'custodian_name' => $custodian,
                'crew_lineup' => $lineup, 'extra_signatories' => $extraSigs,
            ]);
            DB::table('incident_damage_types')->where('incident_id', $iid)->delete();
            if ($dtypes) {
                DB::table('incident_damage_types')->insert(array_map(
                    fn ($t) => ['incident_id' => $iid, 'damage_type' => $t], $dtypes
                ));
            }
            if ($bookingId) {
                BookingCosting::updateBookingTotal($bookingId);
            }

            return ['type' => 'success', 'text' => 'Incident report updated.'];
        }

        if ($action === 'resolve_incident') {
            $iid = (int) $request->input('incident_id', 0);
            $resolution = $request->input('resolution', 'pending');
            $charge = (float) $request->input('charge_amount', 0);
            $newStatus = $request->input('status', 'resolved');
            $notes = trim((string) $request->input('notes', ''));

            $bookingId = (int) DB::table('incident_reports')->where('incident_id', $iid)->value('booking_id');

            $update = ['resolution' => $resolution, 'charge_amount' => $charge, 'status' => $newStatus, 'notes' => $notes];
            if (in_array($newStatus, ['resolved', 'closed'], true)) {
                $update['resolved_at'] = now();
            }
            DB::table('incident_reports')->where('incident_id', $iid)->update($update);
            if ($bookingId) {
                BookingCosting::updateBookingTotal($bookingId);
            }

            return ['type' => 'success', 'text' => 'Incident status updated.'];
        }

        return null;
    }
}
