@extends('layouts.app')

@section('pageTitle', 'Incident Reports')

@section('breadcrumb')
<span>Incident Reports</span>
@endsection

@section('topbarActions')
<button onclick="resetNewIncidentForm(); openModal('modalNewIncident')" class="btn btn-primary btn-sm"><i data-feather="plus"></i> New Incident Report</button>
@endsection

@section('content')
@php $incBase = route('incidents'); @endphp

@if ($msg)
<div class="alert alert-{{ $msg['type'] }}" data-autohide>
  <i data-feather="{{ $msg['type'] === 'success' ? 'check-circle' : 'alert-circle' }}"></i>
  {!! $msg['text'] !!}
</div>
@endif

<!-- KPI Row -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:22px">
  <div class="stat-card orange">
    <div class="stat-icon"><i data-feather="alert-triangle"></i></div>
    <div class="stat-value">{{ $kpis['total'] }}</div>
    <div class="stat-label">Total Incidents</div>
  </div>
  <div class="stat-card" style="--sb:#dc2626">
    <div class="stat-icon" style="background:#fee2e2;color:#dc2626"><i data-feather="alert-circle"></i></div>
    <div class="stat-value">{{ $kpis['open'] }}</div>
    <div class="stat-label">Open</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon"><i data-feather="check-circle"></i></div>
    <div class="stat-value">{{ $kpis['resolved'] }}</div>
    <div class="stat-label">Resolved</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="dollar-sign"></i></div>
    <div class="stat-value">₱{{ number_format($kpis['charges']/1000,1) }}k</div>
    <div class="stat-label">Total Charges</div>
  </div>
</div>

<!-- View Switcher: Incidents vs Maintenance -->
<div class="tabs" style="margin-bottom:18px">
  <a href="{{ $incBase }}?view=incidents&tab={{ $tab }}" class="tab-btn {{ $viewTab === 'incidents' ? 'active' : '' }}">
    <i data-feather="alert-triangle" style="width:14px;height:14px;margin-right:4px;vertical-align:middle"></i>Incident Reports
    <span class="badge badge-orange" style="margin-left:4px">{{ $kpis['total'] }}</span>
  </a>
  <a href="{{ $incBase }}?view=maintenance&mtab={{ $maintTab }}" class="tab-btn {{ $viewTab === 'maintenance' ? 'active' : '' }}">
    <i data-feather="tool" style="width:14px;height:14px;margin-right:4px;vertical-align:middle"></i>Maintenance Schedules
    <span class="badge badge-blue" style="margin-left:4px">{{ $maintCounts['pending'] + $maintCounts['in_progress'] }}</span>
  </a>
</div>

@if ($viewTab === 'incidents')
<!-- Sub-tabs for incident status -->
<div class="tabs" style="margin-bottom:18px">
  @foreach (['all' => 'All', 'open' => 'Open', 'resolved' => 'Resolved', 'closed' => 'Closed'] as $k => $l)
  <a href="{{ $incBase }}?view=incidents&tab={{ $k }}" class="tab-btn {{ $tab === $k ? 'active' : '' }}">
    {{ $l }} <span class="badge {{ $k === 'open' ? 'badge-yellow' : ($k === 'resolved' ? 'badge-green' : 'badge-gray') }}" style="margin-left:4px">{{ $tabCounts[$k] }}</span>
  </a>
  @endforeach
</div>

<div class="card">
  <div class="card-header">
    <h2 class="card-title">Incident Reports</h2>
  </div>
  <div class="card-body" style="padding-bottom:0">
    <form method="GET">
      <input type="hidden" name="tab" value="{{ $tab }}">
      <div class="filter-bar">
        <div class="search-input-wrap">
          <i data-feather="search"></i>
          <input type="text" name="q" class="form-control" placeholder="Search IR#, equipment, booking, client…" value="{{ $search }}">
        </div>
        <button type="submit" class="btn btn-primary btn-sm"><i data-feather="filter"></i> Filter</button>
      </div>
    </form>
  </div>

  <div class="table-wrap">
    @if ($incidents->isEmpty())
    <div class="empty-state">
      <i data-feather="shield"></i>
      <h3>No incident reports found</h3>
      <p>Incidents are filed when equipment is returned damaged, missing, or malfunctioning.</p>
    </div>
    @else
    <table>
      <thead>
        <tr>
          <th>IR #</th>
          <th>Date</th>
          <th>Equipment</th>
          <th>Booking</th>
          <th>Type</th>
          <th>Charge</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      @foreach ($incidents as $ir)
      @php $irNum = $ir->incident_number ?: '#'.$ir->incident_id; @endphp
      <tr>
        <td><span style="font-family:monospace;font-size:.85rem;font-weight:700">{{ $irNum }}</span></td>
        <td style="white-space:nowrap;font-size:.83rem">{{ date('M j, Y', strtotime($ir->incident_date)) }}</td>
        <td>
          <div style="font-weight:600">{{ $ir->equipment_name }}</div>
          <div style="font-size:.75rem;color:var(--muted)">{{ $ir->brand }}</div>
        </td>
        <td>
          <a href="{{ route('booking-detail', $ir->booking_id) }}" style="font-size:.83rem;color:var(--accent);font-family:monospace">{{ $ir->booking_reference }}</a>
          <div style="font-size:.75rem;color:var(--muted)">{{ $ir->company_name ?: $ir->contact_person }}</div>
        </td>
        <td><span class="badge {{ $typeBadge[$ir->incident_type] ?? 'badge-gray' }}">{{ $typeLabel[$ir->incident_type] ?? ucfirst($ir->incident_type) }}</span></td>
        <td style="font-weight:700;color:var(--red)">
          {{ $ir->charge_amount > 0 ? '₱'.number_format($ir->charge_amount,2) : '—' }}
        </td>
        <td><span class="badge {{ $statusBadge[$ir->status] ?? 'badge-gray' }}">{{ ucfirst($ir->status) }}</span></td>
        <td>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <a href="{{ route('incident-print', $ir->incident_id) }}?pdf=1"
               class="btn btn-sm btn-outline" title="Print / Save as PDF">
              <i data-feather="printer"></i>
            </a>
            <button onclick="openEditIncident({{ Illuminate\Support\Js::from($ir)->toHtml() }})"
                    class="btn btn-sm btn-outline" title="Edit">
              <i data-feather="edit-2"></i>
            </button>
            @if ($ir->status === 'open')
            <button onclick="openResolveIncident({{ $ir->incident_id }}, '{{ addslashes($irNum) }}', {{ $ir->charge_amount }})"
                    class="btn btn-sm btn-success" title="Resolve">
              <i data-feather="check"></i>
            </button>
            @endif
          </div>
        </td>
      </tr>
      @endforeach
      </tbody>
    </table>

    @if ($pages > 1)
    <div style="display:flex;gap:6px;padding:14px 18px;align-items:center;flex-wrap:wrap">
      @for ($p = 1; $p <= $pages; $p++)
      <a href="?tab={{ $tab }}&q={{ urlencode($search) }}&p={{ $p }}"
         class="btn btn-sm {{ $p === $page ? 'btn-primary' : 'btn-outline' }}">{{ $p }}</a>
      @endfor
    </div>
    @endif
    @endif
  </div>
</div>

@endif {{-- end incidents view --}}

@if ($viewTab === 'maintenance')
<!-- ════ MAINTENANCE SCHEDULES VIEW ════ -->

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:10px">
  <div class="tabs" style="margin-bottom:0">
    @foreach (['pending' => 'Pending', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'all' => 'All'] as $mk => $ml)
    <a href="{{ $incBase }}?view=maintenance&mtab={{ $mk }}" class="tab-btn {{ $maintTab === $mk ? 'active' : '' }}">
      {{ $ml }}
      <span class="badge {{ $mk === 'pending' ? 'badge-yellow' : ($mk === 'in_progress' ? 'badge-blue' : ($mk === 'completed' ? 'badge-green' : 'badge-gray')) }}" style="margin-left:4px">{{ $maintCounts[$mk] ?? 0 }}</span>
    </a>
    @endforeach
  </div>
  <button onclick="resetNewMaintenanceForm(); openModal('modalNewMaintenance')" class="btn btn-primary btn-sm">
    <i data-feather="plus"></i> New Schedule
  </button>
</div>

<div class="card">
  @if ($maintenances->isEmpty())
  <div class="empty-state">
    <i data-feather="tool"></i>
    <h3>No maintenance schedules</h3>
    <p>Create maintenance schedules to assign equipment upkeep tasks to crew members.</p>
  </div>
  @else
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Equipment</th><th>Type</th><th>Scheduled Date</th><th>Assigned Crew</th><th>Linked IR</th><th>Status</th><th style="text-align:right">Actions</th></tr>
      </thead>
      <tbody>
        @foreach ($maintenances as $mt)
        <tr>
          <td>
            <div style="font-weight:600">{{ $mt->equipment_name }}</div>
            <div style="font-size:.75rem;color:var(--muted)">{{ $mt->brand }}{{ $mt->equip_serial ? ' · '.$mt->equip_serial : '' }}</div>
          </td>
          <td><span class="badge {{ $maintTypeBadge[$mt->maintenance_type] ?? 'badge-gray' }}">{{ $maintTypeLabel[$mt->maintenance_type] ?? $mt->maintenance_type }}</span></td>
          <td style="font-size:.85rem;white-space:nowrap;font-family:monospace">
            @php $schDate = strtotime($mt->scheduled_date); $isOverdue = $schDate < time() && !in_array($mt->status, ['completed','cancelled']); @endphp
            <span style="color:{{ $isOverdue ? 'var(--red)' : 'var(--text)' }}">{{ date('M j, Y', $schDate) }}</span>
            @if ($isOverdue)<span class="badge badge-red" style="margin-left:4px;font-size:.6rem">Overdue</span>@endif
          </td>
          <td style="font-size:.85rem">{{ $mt->assigned_crew_name ?: '—' }}</td>
          <td style="font-size:.82rem;font-family:monospace">
            @if ($mt->incident_number)<span style="color:var(--accent)">{{ $mt->incident_number }}</span>@else —@endif
          </td>
          <td><span class="badge {{ $maintStatBadge[$mt->status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$mt->status)) }}</span></td>
          <td>
            <div style="display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap">
              <button onclick="openUpdateMaintenance({{ Illuminate\Support\Js::from($mt)->toHtml() }})"
                      class="btn btn-sm btn-outline" title="Update Status"><i data-feather="edit-2"></i></button>
              @if ($canManage)
              <form method="POST" onsubmit="return confirm('Delete this maintenance schedule?')">
                @csrf
                <input type="hidden" name="action" value="delete_maintenance">
                <input type="hidden" name="schedule_id" value="{{ $mt->schedule_id }}">
                <button type="submit" class="btn btn-sm btn-outline" style="color:var(--red);border-color:var(--red)" title="Delete"><i data-feather="trash-2"></i></button>
              </form>
              @endif
            </div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif
</div>

<!-- ══ NEW MAINTENANCE MODAL ══ -->
<div class="modal-overlay" id="modalNewMaintenance">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <h3 class="modal-title"><i data-feather="tool" style="width:16px;height:16px;margin-right:8px;vertical-align:middle"></i>New Maintenance Schedule</h3>
      <button class="modal-close"><i data-feather="x"></i></button>
    </div>
    <form method="POST">
      @csrf
      <input type="hidden" name="action" value="create_maintenance">
      <div class="modal-body">
        <div class="form-group">
          <label>Equipment *</label>
          <div style="position:relative">
            <input type="text" id="nm_equipSearch" class="form-control" placeholder="Search equipment…" autocomplete="off"
                   oninput="openNMEquipSearch(this.value)" onfocus="openNMEquipSearch(this.value)" onblur="closeNMEquipSearch(200)">
            <input type="hidden" name="equipment_id" id="nm_equipment">
            <div class="crew-slot-drop" id="nm_equipDrop"></div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Maintenance Type *</label>
            <select name="maintenance_type" class="form-control" required>
              <option value="preventive">Preventive</option>
              <option value="corrective">Corrective</option>
              <option value="calibration">Calibration</option>
              <option value="cleaning">Cleaning</option>
            </select>
          </div>
          <div class="form-group">
            <label>Scheduled Date *</label>
            <input type="date" name="scheduled_date" class="form-control" value="{{ date('Y-m-d', strtotime('+1 day')) }}" required>
          </div>
        </div>
        <div class="form-group">
          <label>Assign Crew Member <span style="color:var(--muted);font-weight:400">(optional)</span></label>
          <select name="assigned_crew_id" class="form-control">
            <option value="">— No Assignment —</option>
            @foreach ($crewForMaint as $cm)
            <option value="{{ $cm->crew_id }}">{{ $cm->last_name }}, {{ $cm->first_name }}{{ $cm->position_name ? ' — '.$cm->position_name : '' }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group">
          <label>Link to Incident Report <span style="color:var(--muted);font-weight:400">(optional)</span></label>
          <select name="incident_id" class="form-control">
            <option value="">— Not linked —</option>
            @foreach ($incidents as $ir)
            @if ($ir->status === 'open')
            <option value="{{ $ir->incident_id }}">{{ ($ir->incident_number ?: '#'.$ir->incident_id).' — '.$ir->equipment_name }}</option>
            @endif
            @endforeach
          </select>
          <div style="font-size:.75rem;color:var(--muted);margin-top:4px">Linking to an incident report tracks follow-through on corrective maintenance.</div>
        </div>
        <div class="form-group">
          <label>Description / Instructions</label>
          <textarea name="description" class="form-control" rows="3" placeholder="What needs to be done…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Create Schedule</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ UPDATE MAINTENANCE STATUS MODAL ══ -->
<div class="modal-overlay" id="modalUpdateMaintenance">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <h3 class="modal-title"><i data-feather="refresh-cw" style="width:16px;height:16px;margin-right:8px;vertical-align:middle"></i>Update Maintenance Status</h3>
      <button class="modal-close"><i data-feather="x"></i></button>
    </div>
    <form method="POST">
      @csrf
      <input type="hidden" name="action" value="update_maintenance">
      <input type="hidden" name="schedule_id" id="updMaint_id">
      <div class="modal-body">
        <div style="font-size:.85rem;color:var(--muted);margin-bottom:12px" id="updMaint_label"></div>
        <div class="form-group">
          <label>New Status</label>
          <select name="status" id="updMaint_status" class="form-control">
            <option value="pending">Pending</option>
            <option value="in_progress">In Progress</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div class="form-group">
          <label>Notes / Completion Remarks</label>
          <textarea name="notes" id="updMaint_notes" class="form-control" rows="3" placeholder="Describe what was done…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="check"></i> Save</button>
      </div>
    </form>
  </div>
</div>

@endif {{-- end maintenance view --}}

<!-- ══ NEW INCIDENT MODAL ══ -->
<div class="modal-overlay" id="modalNewIncident">
  <div class="modal" style="max-width:680px">
    <div class="modal-header">
      <h3 class="modal-title"><i data-feather="alert-triangle" style="width:16px;height:16px;margin-right:8px;vertical-align:middle"></i>New Incident Report</h3>
      <button class="modal-close"><i data-feather="x"></i></button>
    </div>
    <form method="POST" onsubmit="return prepNewIncident()">
      @csrf
      <input type="hidden" name="action" value="create_incident">
      <div class="modal-body" style="max-height:75vh;overflow-y:auto">

        <div class="form-row">
          <div class="form-group">
            <label>Booking *</label>
            <div style="position:relative">
              <input type="text" id="newIR_bookingSearch" class="form-control" placeholder="Search booking reference or client…" autocomplete="off"
                     oninput="openIRBookingSearch(this.value)" onfocus="openIRBookingSearch(this.value)" onblur="closeIRBookingSearch(200)">
              <input type="hidden" name="booking_id" id="newIR_booking">
              <div class="crew-slot-drop" id="newIR_bookingDrop"></div>
            </div>
          </div>
          <div class="form-group">
            <label>Equipment *</label>
            <div style="position:relative">
              <input type="text" id="newIR_equipSearch" class="form-control" placeholder="Select a booking first" autocomplete="off" disabled
                     oninput="openIREquipSearch(this.value)" onfocus="openIREquipSearch(this.value)" onblur="closeIREquipSearch(200)">
              <input type="hidden" name="equipment_id" id="newIR_equipment">
              <div class="crew-slot-drop" id="newIR_equipDrop"></div>
            </div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Incident Type *</label>
            <select name="incident_type" class="form-control" required>
              <option value="damaged">Damaged</option>
              <option value="missing">Missing</option>
              <option value="malfunction">Malfunction</option>
              <option value="late_return">Late Return</option>
            </select>
          </div>
          <div class="form-group">
            <label>Cause</label>
            <select name="cause" class="form-control">
              <option value="negligence">Negligence</option>
              <option value="accident">Accident</option>
              <option value="lifespan">End of Lifespan</option>
              <option value="unknown" selected>Unknown</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Incident Date *</label>
            <input type="date" name="incident_date" class="form-control" value="{{ date('Y-m-d') }}" required>
          </div>
          <div class="form-group">
            <label>Time</label>
            <input type="time" name="incident_time" class="form-control">
          </div>
        </div>

        <!-- Damage Types -->
        <div class="form-group">
          <label>Damage Description (check all that apply)</label>
          <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:6px">
            @foreach ($damageTypes as $val => $lbl)
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:500">
              <input type="checkbox" name="damage_types[]" value="{{ $val }}"
                     style="width:15px;height:15px;accent-color:var(--accent)">
              {{ $lbl }}
            </label>
            @endforeach
          </div>
          <input type="text" name="damage_others_note" class="form-control" style="margin-top:8px"
                 placeholder="If Others — describe here">
        </div>

        <div class="form-group">
          <label>Incident Written Report *</label>
          <textarea name="description" class="form-control" rows="3" required
                    placeholder="Describe what happened in detail…"></textarea>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Charge Amount (₱)</label>
            <input type="number" name="charge_amount" class="form-control" step="0.01" min="0" value="0">
          </div>
          <div class="form-group">
            <label>Payment Mode</label>
            <select name="payment_mode" class="form-control">
              <option value="lump_sum">Lump Sum</option>
              <option value="installment">Installment</option>
            </select>
          </div>
        </div>

        <hr style="border:none;border-top:1px solid var(--border);margin:14px 0">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
          <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)">Signatories</div>
          <button type="button" onclick="addSignatoryRow('newIR_extraSignRows')" class="btn btn-sm btn-outline" style="padding:4px 10px;font-size:12px">
            <i data-feather="plus" style="width:12px;height:12px"></i> Add Signatory
          </button>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label style="font-size:12px;color:var(--muted)">DOP (Director of Photography)</label>
            <input type="text" name="dop_name" class="form-control" placeholder="Full name">
          </div>
          <div class="form-group">
            <label style="font-size:12px;color:var(--muted)">Head Crew</label>
            <input type="text" name="head_crew_name" class="form-control" placeholder="Full name">
          </div>
        </div>
        <div class="form-group" style="max-width:50%;margin-bottom:8px">
          <label style="font-size:12px;color:var(--muted)">Custodian</label>
          <input type="text" name="custodian_name" class="form-control" placeholder="Full name">
        </div>
        <div id="newIR_extraSignRows"></div>

        <hr style="border:none;border-top:1px solid var(--border);margin:14px 0">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
          <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)">Crew Line-Up</div>
          <button type="button" onclick="addCrewRow('newIR_crewContainer')" class="btn btn-sm btn-outline" style="padding:4px 10px;font-size:12px">
            <i data-feather="plus" style="width:12px;height:12px"></i> Add Crew
          </button>
        </div>
        <div id="newIR_crewContainer" style="display:flex;flex-direction:column;gap:6px"></div>
        <div style="margin-top:6px;font-size:11.5px;color:var(--muted)">Crew members will auto-populate when a booking is selected above.</div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="save"></i> File Report</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ EDIT INCIDENT MODAL ══ -->
<div class="modal-overlay" id="modalEditIncident">
  <div class="modal" style="max-width:680px">
    <div class="modal-header">
      <h3 class="modal-title"><i data-feather="edit-2" style="width:16px;height:16px;margin-right:8px;vertical-align:middle"></i>Edit Incident Report — <span id="editIR_num"></span></h3>
      <button class="modal-close"><i data-feather="x"></i></button>
    </div>
    <form method="POST">
      @csrf
      <input type="hidden" name="action" value="update_incident">
      <input type="hidden" name="incident_id" id="editIR_id">
      <div class="modal-body" style="max-height:75vh;overflow-y:auto">

        <div class="form-row">
          <div class="form-group">
            <label>Incident Type *</label>
            <select name="incident_type" id="editIR_type" class="form-control" required>
              <option value="damaged">Damaged</option>
              <option value="missing">Missing</option>
              <option value="malfunction">Malfunction</option>
              <option value="late_return">Late Return</option>
            </select>
          </div>
          <div class="form-group">
            <label>Cause</label>
            <select name="cause" id="editIR_cause" class="form-control">
              <option value="negligence">Negligence</option>
              <option value="accident">Accident</option>
              <option value="lifespan">End of Lifespan</option>
              <option value="unknown">Unknown</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Incident Date *</label>
            <input type="date" name="incident_date" id="editIR_date" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Time</label>
            <input type="time" name="incident_time" id="editIR_time" class="form-control">
          </div>
        </div>

        <div class="form-group">
          <label>Damage Description (check all that apply)</label>
          <div id="editIR_dtypes" style="display:flex;flex-wrap:wrap;gap:10px;margin-top:6px">
            @foreach ($damageTypes as $val => $lbl)
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:500">
              <input type="checkbox" name="damage_types[]" value="{{ $val }}" class="editIR_dtype"
                     style="width:15px;height:15px;accent-color:var(--accent)">
              {{ $lbl }}
            </label>
            @endforeach
          </div>
          <input type="text" name="damage_others_note" id="editIR_dothers" class="form-control" style="margin-top:8px"
                 placeholder="If Others — describe here">
        </div>

        <div class="form-group">
          <label>Incident Written Report *</label>
          <textarea name="description" id="editIR_desc" class="form-control" rows="3" required></textarea>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Charge Amount (₱)</label>
            <input type="number" name="charge_amount" id="editIR_charge" class="form-control" step="0.01" min="0">
          </div>
          <div class="form-group">
            <label>Payment Mode</label>
            <select name="payment_mode" id="editIR_paymode" class="form-control">
              <option value="lump_sum">Lump Sum</option>
              <option value="installment">Installment</option>
            </select>
          </div>
        </div>

        <hr style="border:none;border-top:1px solid var(--border);margin:14px 0">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
          <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)">Signatories</div>
          <button type="button" onclick="addSignatoryRow('editIR_extraSignRows')" class="btn btn-sm btn-outline" style="padding:4px 10px;font-size:12px">
            <i data-feather="plus" style="width:12px;height:12px"></i> Add Signatory
          </button>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label style="font-size:12px;color:var(--muted)">DOP (Director of Photography)</label>
            <input type="text" name="dop_name" id="editIR_dop" class="form-control">
          </div>
          <div class="form-group">
            <label style="font-size:12px;color:var(--muted)">Head Crew</label>
            <input type="text" name="head_crew_name" id="editIR_hcrew" class="form-control">
          </div>
        </div>
        <div class="form-group" style="max-width:50%;margin-bottom:8px">
          <label style="font-size:12px;color:var(--muted)">Custodian</label>
          <input type="text" name="custodian_name" id="editIR_custodian" class="form-control">
        </div>
        <div id="editIR_extraSignRows"></div>

        <hr style="border:none;border-top:1px solid var(--border);margin:14px 0">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
          <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)">Crew Line-Up</div>
          <button type="button" onclick="addCrewRow('editIR_crewContainer')" class="btn btn-sm btn-outline" style="padding:4px 10px;font-size:12px">
            <i data-feather="plus" style="width:12px;height:12px"></i> Add Crew
          </button>
        </div>
        <div id="editIR_crewContainer" style="display:flex;flex-direction:column;gap:6px"></div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ RESOLVE MODAL ══ -->
<div class="modal-overlay" id="modalResolveIncident">
  <div class="modal" style="max-width:460px">
    <div class="modal-header">
      <h3 class="modal-title"><i data-feather="check-circle" style="width:16px;height:16px;margin-right:8px;vertical-align:middle"></i>Resolve — <span id="resolveIR_num"></span></h3>
      <button class="modal-close"><i data-feather="x"></i></button>
    </div>
    <form method="POST">
      @csrf
      <input type="hidden" name="action" value="resolve_incident">
      <input type="hidden" name="incident_id" id="resolveIR_id">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label>Resolution</label>
            <select name="resolution" class="form-control">
              <option value="repair">For Repair</option>
              <option value="replacement">Replacement</option>
              <option value="write_off">Write-Off</option>
              <option value="installment_payment">Installment Payment</option>
              <option value="pending">Pending</option>
            </select>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status" class="form-control">
              <option value="resolved">Resolved</option>
              <option value="closed">Closed</option>
              <option value="open">Keep Open</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Final Charge Amount (₱)</label>
          <input type="number" name="charge_amount" id="resolveIR_charge" class="form-control" step="0.01" min="0">
        </div>
        <div class="form-group">
          <label>Notes / Resolution Details</label>
          <textarea name="notes" class="form-control" rows="3" placeholder="e.g. Client paid ₱5,000 cash, equipment sent for repair…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-success"><i data-feather="check"></i> Save Resolution</button>
      </div>
    </form>
  </div>
</div>

<script>
// Equipment per booking (pre-loaded)
const BOOKING_EQUIP = {!! json_encode($bookingEquipMap) !!};

// Active bookings, for the New Incident booking search (pre-loaded)
const ACTIVE_BOOKINGS = {!! $activeBookings->values()->toJson() !!};

// Crew per booking (pre-loaded)
const BOOKING_CREW = {!! json_encode($bookingCrewMap) !!};

function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ── Booking type-to-search (New Incident) ───────────────────────────────────
function _irBookingLabel(bk) {
  return bk.booking_reference + ' — ' + (bk.project_title || bk.company_name || bk.contact_person || '');
}
function openIRBookingSearch(q) {
  const lq = (q || '').toLowerCase().trim();
  const pool = ACTIVE_BOOKINGS.filter((bk) => {
    if (!lq) return true;
    const name = (bk.project_title || '') + ' ' + (bk.company_name || '') + ' ' + (bk.contact_person || '');
    return (bk.booking_reference || '').toLowerCase().includes(lq) || name.toLowerCase().includes(lq);
  }).slice(0, 30);
  const drop = document.getElementById('newIR_bookingDrop');
  if (!drop) return;
  drop.innerHTML = pool.length
    ? pool.map((bk) => `<div class="csd-item" data-id="${bk.booking_id}" data-label="${esc(_irBookingLabel(bk))}">
        <div class="csd-ref">${esc(bk.booking_reference)}</div>
        <div class="csd-sub">${esc(bk.project_title || bk.company_name || bk.contact_person || '')}</div>
      </div>`).join('')
    : '<div class="csd-empty">No matching bookings</div>';
  positionSearchDrop(document.getElementById('newIR_bookingSearch'), drop);
  drop.style.display = 'block';
  drop.querySelectorAll('.csd-item').forEach((el) => {
    el.addEventListener('mousedown', (e) => {
      e.preventDefault();
      document.getElementById('newIR_booking').value = el.dataset.id;
      document.getElementById('newIR_bookingSearch').value = el.dataset.label;
      drop.style.display = 'none';
      selectBookingForIncident(el.dataset.id);
    });
  });
}
function closeIRBookingSearch(ms) {
  setTimeout(() => { const d = document.getElementById('newIR_bookingDrop'); if (d) d.style.display = 'none'; }, ms);
}

function selectBookingForIncident(bookingId) {
  const equip = BOOKING_EQUIP[bookingId] || [];
  const eqSearch = document.getElementById('newIR_equipSearch');
  document.getElementById('newIR_equipment').value = '';
  eqSearch.value = '';
  eqSearch.disabled = !bookingId;
  eqSearch.placeholder = !bookingId ? 'Select a booking first' : (equip.length ? 'Search equipment or accessory…' : 'No equipment on this booking');
  const crewContainer = document.getElementById('newIR_crewContainer');
  if (crewContainer) populateCrewFromBooking(bookingId, crewContainer);
}

// ── Equipment type-to-search (New Incident, scoped to the selected booking) ─
function openIREquipSearch(q) {
  const bookingId = document.getElementById('newIR_booking').value;
  const equip = BOOKING_EQUIP[bookingId] || [];
  const lq = (q || '').toLowerCase().trim();
  const pool = equip.filter((e) => {
    if (!lq) return true;
    return (e.equipment_name || '').toLowerCase().includes(lq)
      || (e.brand || '').toLowerCase().includes(lq)
      || (e.serial_number || '').toLowerCase().includes(lq);
  }).slice(0, 30);
  const drop = document.getElementById('newIR_equipDrop');
  if (!drop) return;
  drop.innerHTML = pool.length
    ? pool.map((e) => `<div class="csd-item" data-id="${e.equipment_id}" data-label="${esc(e.equipment_name)}${e.brand ? ' — ' + esc(e.brand) : ''}${e.serial_number ? ' (' + esc(e.serial_number) + ')' : ''}">
        <div class="csd-ref">${esc(e.equipment_name)}</div>
        <div class="csd-sub">${esc(e.brand || '')}${e.serial_number ? ' &middot; ' + esc(e.serial_number) : ''}</div>
      </div>`).join('')
    : `<div class="csd-empty">${bookingId ? 'No matching equipment on this booking' : 'Select a booking first'}</div>`;
  positionSearchDrop(document.getElementById('newIR_equipSearch'), drop);
  drop.style.display = 'block';
  drop.querySelectorAll('.csd-item').forEach((el) => {
    el.addEventListener('mousedown', (e) => {
      e.preventDefault();
      document.getElementById('newIR_equipment').value = el.dataset.id;
      document.getElementById('newIR_equipSearch').value = el.dataset.label;
      drop.style.display = 'none';
    });
  });
}
function closeIREquipSearch(ms) {
  setTimeout(() => { const d = document.getElementById('newIR_equipDrop'); if (d) d.style.display = 'none'; }, ms);
}

// ── Equipment type-to-search (New Maintenance Schedule, all equipment) ──────
const ALL_EQUIPMENT_LIST = {!! $allEquipment->values()->toJson() !!};

function openNMEquipSearch(q) {
  const lq = (q || '').toLowerCase().trim();
  const pool = ALL_EQUIPMENT_LIST.filter((eq) => {
    if (!lq) return true;
    return (eq.equipment_name || '').toLowerCase().includes(lq)
      || (eq.brand || '').toLowerCase().includes(lq)
      || (eq.serial_number || '').toLowerCase().includes(lq);
  }).slice(0, 30);
  const drop = document.getElementById('nm_equipDrop');
  if (!drop) return;
  drop.innerHTML = pool.length
    ? pool.map((eq) => `<div class="csd-item" data-id="${eq.equipment_id}" data-label="${esc(eq.equipment_name)} — ${esc(eq.brand || '')}">
        <div class="csd-ref">${esc(eq.equipment_name)}</div>
        <div class="csd-sub">${esc(eq.brand || '')}${eq.serial_number ? ' &middot; ' + esc(eq.serial_number) : ''}</div>
      </div>`).join('')
    : '<div class="csd-empty">No matching equipment</div>';
  positionSearchDrop(document.getElementById('nm_equipSearch'), drop);
  drop.style.display = 'block';
  drop.querySelectorAll('.csd-item').forEach((el) => {
    el.addEventListener('mousedown', (e) => {
      e.preventDefault();
      document.getElementById('nm_equipment').value = el.dataset.id;
      document.getElementById('nm_equipSearch').value = el.dataset.label;
      drop.style.display = 'none';
    });
  });
}
function closeNMEquipSearch(ms) {
  setTimeout(() => { const d = document.getElementById('nm_equipDrop'); if (d) d.style.display = 'none'; }, ms);
}
function resetNewMaintenanceForm() {
  document.getElementById('nm_equipment').value = '';
  document.getElementById('nm_equipSearch').value = '';
}

function prepNewIncident() {
  if (!document.getElementById('newIR_booking').value) {
    alert('Please select a booking from the search.');
    return false;
  }
  if (!document.getElementById('newIR_equipment').value) {
    alert('Please select equipment from the search.');
    return false;
  }
  return true;
}

function resetNewIncidentForm() {
  document.getElementById('newIR_booking').value = '';
  document.getElementById('newIR_bookingSearch').value = '';
  document.getElementById('newIR_equipment').value = '';
  document.getElementById('newIR_equipSearch').value = '';
  document.getElementById('newIR_equipSearch').disabled = true;
  document.getElementById('newIR_equipSearch').placeholder = 'Select a booking first';
}

function populateCrewFromBooking(bookingId, container) {
  const crew = BOOKING_CREW[bookingId] || [];
  container.innerHTML = '';
  if (crew.length > 0) {
    crew.forEach(c => addCrewRowEl(container, c.position_name, c.first_name + ' ' + c.last_name));
  } else {
    addCrewRowEl(container, '', '');
  }
  if (typeof feather !== 'undefined') feather.replace();
}

function addCrewRow(containerId, role, name) {
  const container = typeof containerId === 'string' ? document.getElementById(containerId) : containerId;
  addCrewRowEl(container, role || '', name || '');
  if (typeof feather !== 'undefined') feather.replace();
}

function ucInput(inp) {
  inp.addEventListener('input', () => {
    const p = inp.selectionStart; inp.value = inp.value.toUpperCase();
    try { inp.setSelectionRange(p,p); } catch(e) {}
  });
}

function addCrewRowEl(container, role, name) {
  const div = document.createElement('div');
  div.className = 'crew-dyn-row';
  div.style.cssText = 'display:flex;gap:8px;align-items:center';
  div.innerHTML =
    `<input type="text" name="crew_lineup_role[]" value="${esc(role)}" placeholder="Position/Role" class="form-control" style="flex:0 0 38%">` +
    `<input type="text" name="crew_lineup_name[]" value="${esc(name)}" placeholder="Full name" class="form-control" style="flex:1">` +
    `<button type="button" onclick="this.closest('.crew-dyn-row').remove()" class="btn btn-sm btn-outline" style="padding:6px 10px;flex-shrink:0;color:var(--red);border-color:var(--red)" title="Remove">&times;</button>`;
  container.appendChild(div);
  div.querySelectorAll('input[type="text"]').forEach(ucInput);
}

function addSignatoryRow(containerId, role, name) {
  const container = document.getElementById(containerId);
  const div = document.createElement('div');
  div.className = 'sig-extra-row';
  div.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:8px';
  div.innerHTML =
    `<input type="text" name="extra_sig_role[]" value="${esc(role||'')}" placeholder="Role / Title" class="form-control" style="flex:0 0 38%">` +
    `<input type="text" name="extra_sig_name[]" value="${esc(name||'')}" placeholder="Full name" class="form-control" style="flex:1">` +
    `<button type="button" onclick="this.closest('.sig-extra-row').remove()" class="btn btn-sm btn-outline" style="padding:6px 10px;flex-shrink:0;color:var(--red);border-color:var(--red)" title="Remove">&times;</button>`;
  container.appendChild(div);
  div.querySelectorAll('input[type="text"]').forEach(ucInput);
}

function openEditIncident(ir) {
  document.getElementById('editIR_id').value       = ir.incident_id;
  document.getElementById('editIR_num').textContent= ir.incident_number || '#'+ir.incident_id;
  document.getElementById('editIR_type').value     = ir.incident_type;
  document.getElementById('editIR_cause').value    = ir.cause;
  document.getElementById('editIR_date').value     = ir.incident_date;
  document.getElementById('editIR_time').value     = ir.incident_time || '';
  document.getElementById('editIR_desc').value     = ir.description;
  document.getElementById('editIR_charge').value   = ir.charge_amount;
  document.getElementById('editIR_paymode').value  = ir.payment_mode || 'lump_sum';
  document.getElementById('editIR_dop').value      = ir.dop_name || '';
  document.getElementById('editIR_hcrew').value    = ir.head_crew_name || '';
  document.getElementById('editIR_custodian').value= ir.custodian_name || '';
  document.getElementById('editIR_dothers').value  = ir.damage_others_note || '';

  // Damage type checkboxes
  const dtypes = ir.damage_types ? JSON.parse(ir.damage_types) : [];
  document.querySelectorAll('.editIR_dtype').forEach(cb => { cb.checked = dtypes.includes(cb.value); });

  // Extra signatories
  const extraSignDiv = document.getElementById('editIR_extraSignRows');
  extraSignDiv.innerHTML = '';
  const extraSigs = ir.extra_signatories ? JSON.parse(ir.extra_signatories) : [];
  extraSigs.forEach(s => addSignatoryRow('editIR_extraSignRows', s.role, s.name));

  // Crew lineup (support both old keyed-object format and new array-of-objects format)
  const crewCont = document.getElementById('editIR_crewContainer');
  crewCont.innerHTML = '';
  let lineup = [];
  if (ir.crew_lineup) {
    try { lineup = JSON.parse(ir.crew_lineup); } catch(e) {}
    if (!Array.isArray(lineup)) {
      lineup = Object.entries(lineup).map(([role, name]) => ({role, name}));
    }
  }
  lineup.forEach(row => addCrewRowEl(crewCont, row.role || '', row.name || ''));
  if (lineup.length === 0) addCrewRowEl(crewCont, '', '');
  if (typeof feather !== 'undefined') feather.replace();

  openModal('modalEditIncident');
}

function openResolveIncident(id, num, charge) {
  document.getElementById('resolveIR_id').value      = id;
  document.getElementById('resolveIR_num').textContent = num;
  document.getElementById('resolveIR_charge').value  = charge;
  openModal('modalResolveIncident');
}

function openUpdateMaintenance(mt) {
  document.getElementById('updMaint_id').value    = mt.schedule_id;
  document.getElementById('updMaint_status').value= mt.status;
  document.getElementById('updMaint_notes').value = mt.notes || '';
  document.getElementById('updMaint_label').textContent = mt.equipment_name + ' — ' + (mt.maintenance_type || '');
  openModal('modalUpdateMaintenance');
}

// Add one blank crew row to new incident modal on page load
document.addEventListener('DOMContentLoaded', () => {
  addCrewRowEl(document.getElementById('newIR_crewContainer'), '', '');

  // Auto-open new incident modal when redirected from booking detail
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('new') === '1') {
    const bookingId = urlParams.get('booking_id');
    openModal('modalNewIncident');
    if (bookingId) {
      const bk = ACTIVE_BOOKINGS.find((b) => String(b.booking_id) === String(bookingId));
      document.getElementById('newIR_booking').value = bookingId;
      document.getElementById('newIR_bookingSearch').value = bk ? _irBookingLabel(bk) : '';
      selectBookingForIncident(bookingId);
    }
  }
});
</script>
@endsection
