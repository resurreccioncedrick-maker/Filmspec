@extends('layouts.app')

@section('pageTitle', 'Transport')

@section('breadcrumb')
<span>Transport</span>
@endsection

@section('topbarActions')
@include('partials.export-dropdown', ['id' => 'Transport'])
@endsection

@section('content')
@php $transportBase = route('transport'); @endphp

@if ($msg)
<div class="alert alert-{{ $msg['type'] }}" style="margin-bottom:18px">{{ $msg['text'] }}</div>
@endif

<!-- KPIs -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:20px">
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="tag"></i></div>
    <div class="stat-value">{{ $stats['vehicle_types'] }}</div>
    <div class="stat-label">Vehicle Types <span style="font-weight:400;color:var(--muted)">· Active</span></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="truck"></i></div>
    <div class="stat-value">{{ $stats['fleet_total'] }}</div>
    <div class="stat-label">Fleet Vehicles</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon" style="background:var(--greenl);color:var(--green)"><i data-feather="check-circle"></i></div>
    <div class="stat-value">{{ $stats['available'] }}</div>
    <div class="stat-label">Available</div>
  </div>
  <div class="stat-card" style="--sb:var(--accent)">
    <div class="stat-icon"><i data-feather="navigation"></i></div>
    <div class="stat-value">{{ $stats['assigned'] }}</div>
    <div class="stat-label">Assigned / In Transit</div>
  </div>
  <div class="stat-card {{ $stats['maintenance'] + $stats['out_of_service'] > 0 ? 'orange' : '' }}">
    <div class="stat-icon" style="{{ $stats['maintenance'] + $stats['out_of_service'] > 0 ? 'background:var(--orangel);color:var(--orange)' : '' }}"><i data-feather="tool"></i></div>
    <div class="stat-value">{{ $stats['maintenance'] }}</div>
    <div class="stat-label">Under Maintenance <span style="font-weight:400;color:var(--muted)">· {{ $stats['out_of_service'] }} out of service</span></div>
  </div>
</div>

<!-- Tabs -->
<div class="tabs" style="margin-bottom:18px">
  <a href="{{ $transportBase }}?tab=rates" class="tab-btn {{ $tab === 'rates' ? 'active' : '' }}"><i data-feather="tag" style="width:13px;height:13px;margin-right:5px;vertical-align:middle"></i>Vehicle Types &amp; Rates</a>
  <a href="{{ $transportBase }}?tab=fleet" class="tab-btn {{ $tab === 'fleet' ? 'active' : '' }}"><i data-feather="truck" style="width:13px;height:13px;margin-right:5px;vertical-align:middle"></i>Fleet <span class="badge badge-gray" style="margin-left:4px">{{ $stats['fleet_total'] }}</span></a>
  <a href="{{ $transportBase }}?tab=assignments" class="tab-btn {{ $tab === 'assignments' ? 'active' : '' }}"><i data-feather="navigation" style="width:13px;height:13px;margin-right:5px;vertical-align:middle"></i>Transport Assignments <span class="badge badge-gray" style="margin-left:4px">{{ $assignments->count() }}</span></a>
</div>

@if ($tab === 'rates')
<!-- Vehicle Types -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title"><i data-feather="truck" style="width:15px;height:15px;margin-right:6px;vertical-align:middle"></i>Vehicle Types &amp; Rates</h2>
    @if ($canManage)
    <button onclick="openModal('modalAddVehicle')" class="btn btn-primary btn-sm"><i data-feather="plus"></i> Add Vehicle Type</button>
    @endif
  </div>
  <p style="font-size:13px;color:var(--text-muted);padding:12px 18px 0">
    Define vehicle types (van, truck, etc.) with base rates. Assigned to bookings when a driver is dispatched.
  </p>
  @if ($vehicleRates->isNotEmpty())
  <div class="card-body" style="padding-bottom:0">
    <div class="filter-bar" style="flex-wrap:wrap;gap:8px">
      <div class="search-input-wrap">
        <i data-feather="search"></i>
        <input type="text" id="vSearchInput" class="form-control" placeholder="Search label or type key…"
               oninput="filterVehicleTable()">
      </div>
      <select id="vStatusFilter" class="form-control" style="width:auto" onchange="filterVehicleTable()">
        <option value="">All Statuses</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
      <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('vSearchInput').value='';document.getElementById('vStatusFilter').value='';filterVehicleTable()">Clear</button>
    </div>
  </div>
  @endif
  <div class="table-wrap">
    @if ($vehicleRates->isEmpty())
    <div class="empty-state"><i data-feather="truck"></i><h3>No vehicle types yet</h3><p>Add a vehicle type to enable transport pricing by vehicle.</p></div>
    @else
    <table id="vehicleTable">
      <thead>
        <tr><th>Label</th><th>Type Key</th><th>Description</th><th>Rate Basis</th><th>Base Transport Charge</th><th>Status</th>
          @if ($canManage)<th></th>@endif
        </tr>
      </thead>
      <tbody>
      @foreach ($vehicleRates as $vr)
      <tr data-label="{{ strtolower($vr->label . ' ' . $vr->vehicle_type . ' ' . ($vr->description ?? '')) }}"
          data-status="{{ $vr->is_active ? 'active' : 'inactive' }}"
          style="{{ ! $vr->is_active ? 'opacity:.5' : '' }}">
        <td style="font-weight:600">{{ $vr->label }}</td>
        <td><span class="badge badge-blue" style="font-size:.7rem">{{ $vr->vehicle_type }}</span></td>
        <td style="color:var(--text-muted);font-size:.85rem">{{ $vr->description ?: '—' }}</td>
        <td><span class="badge badge-gray" style="font-size:.7rem">{{ $rateBasisLabel[$vr->rate_basis] ?? 'Per Trip' }}</span></td>
        <td style="font-weight:700;color:var(--accent)">₱{{ number_format($vr->base_rate, 2) }}</td>
        <td>{!! $vr->is_active ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-gray">Inactive</span>' !!}</td>
        @if ($canManage)
        <td style="white-space:nowrap">
          <button onclick='openEditVehicle(@json($vr))' class="btn btn-sm btn-outline" style="font-size:.72rem;padding:3px 9px">Edit</button>
          <form method="POST" action="{{ $transportBase }}" style="display:inline">
            @csrf
            <input type="hidden" name="action" value="toggle_vehicle_rate">
            <input type="hidden" name="vehicle_id" value="{{ $vr->vehicle_id }}">
            <input type="hidden" name="is_active" value="{{ $vr->is_active ? 0 : 1 }}">
            <button type="submit" class="btn btn-sm btn-outline" style="font-size:.72rem;padding:3px 9px">{{ $vr->is_active ? 'Disable' : 'Enable' }}</button>
          </form>
          <form method="POST" action="{{ $transportBase }}" style="display:inline" onsubmit="return confirm('Delete this vehicle type?')">
            @csrf
            <input type="hidden" name="action" value="delete_vehicle_rate">
            <input type="hidden" name="vehicle_id" value="{{ $vr->vehicle_id }}">
            <button type="submit" class="btn btn-sm" style="font-size:.72rem;padding:3px 9px;color:var(--red);border:1px solid var(--red);background:none">Delete</button>
          </form>
        </td>
        @endif
      </tr>
      @endforeach
      </tbody>
    </table>
    @endif
  </div>
</div>
@endif

@if ($tab === 'fleet')
<!-- Fleet -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title"><i data-feather="truck" style="width:15px;height:15px;margin-right:6px;vertical-align:middle"></i>Fleet Vehicles</h2>
    @if ($canManage)
    <button onclick="openModal('modalAddFleet')" class="btn btn-primary btn-sm"><i data-feather="plus"></i> Add Vehicle</button>
    @endif
  </div>
  <p style="font-size:13px;color:var(--text-muted);padding:12px 18px 0">Actual physical vehicles, each tied to a vehicle type for pricing.</p>
  <div class="table-wrap">
    @if ($fleet->isEmpty())
    <div class="empty-state"><i data-feather="truck"></i><h3>No fleet vehicles yet</h3><p>Add a physical vehicle to start tracking assignments.</p></div>
    @else
    <table>
      <thead><tr><th>Vehicle</th><th>Type</th><th>Plate No.</th><th>Status</th><th>Current Assignment</th><th>Driver</th>@if ($canManage)<th></th>@endif</tr></thead>
      <tbody>
      @foreach ($fleet as $v)
      <tr>
        <td style="font-weight:600">{{ trim(($v->brand ?? '') . ' ' . ($v->model ?? '')) ?: '—' }}</td>
        <td><span class="badge badge-blue" style="font-size:.7rem">{{ $v->type_label }}</span></td>
        <td style="font-family:monospace">{{ $v->plate_no }}</td>
        <td><span class="badge {{ $fleetStatusBadge[$v->status] ?? 'badge-gray' }}">{{ $fleetStatusLabel[$v->status] ?? ucfirst($v->status) }}</span></td>
        <td>{{ $v->current_booking_ref ?? '—' }}</td>
        <td>{{ $v->driver_name ?? '—' }}</td>
        @if ($canManage)
        <td style="white-space:nowrap">
          <button onclick='openEditFleet(@json($v))' class="btn btn-sm btn-outline" style="font-size:.72rem;padding:3px 9px">Edit</button>
        </td>
        @endif
      </tr>
      @endforeach
      </tbody>
    </table>
    @endif
  </div>
</div>
@endif

@if ($tab === 'assignments')
<!-- Transport Assignments -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title"><i data-feather="navigation" style="width:15px;height:15px;margin-right:6px;vertical-align:middle"></i>Transport Assignments</h2>
    @if ($canManage)
    <button onclick="openModal('modalCreateAssignment')" class="btn btn-primary btn-sm"><i data-feather="plus"></i> Create Assignment</button>
    @endif
  </div>
  <p style="font-size:13px;color:var(--text-muted);padding:12px 18px 0">Bookings currently requiring transport, and their assigned vehicle/driver.</p>

  @if ($bookingsNeedingTransport->isNotEmpty())
  <div style="padding:10px 18px;background:#fffbeb;border-top:1px solid #fde68a;border-bottom:1px solid #fde68a;font-size:12.5px;color:#92400e">
    <i data-feather="alert-circle" style="width:13px;height:13px;vertical-align:middle;margin-right:4px"></i>
    {{ $bookingsNeedingTransport->count() }} confirmed/ongoing booking{{ $bookingsNeedingTransport->count() === 1 ? '' : 's' }} without a transport assignment yet.
  </div>
  @endif

  <div class="table-wrap">
    @if ($assignments->isEmpty())
    <div class="empty-state"><i data-feather="navigation"></i><h3>No transport assignments yet</h3></div>
    @else
    <table>
      <thead><tr><th>Booking</th><th>Client</th><th>Vehicle</th><th>Driver</th><th>Dispatch</th><th>Destination</th>@if ($canManage)<th></th>@endif</tr></thead>
      <tbody>
      @foreach ($assignments as $a)
      <tr>
        <td>
          <a href="{{ route('booking-detail', $a->booking_id) }}" style="font-weight:700;color:var(--accent)">{{ $a->booking_reference }}</a>
          <div style="font-size:.72rem;color:var(--muted)">{{ $a->project_title ?: '—' }}</div>
        </td>
        <td style="font-size:.85rem">{{ $a->company_name ?: $a->contact_person }}</td>
        <td>{{ $a->vehicle_label }} <span style="font-family:monospace;font-size:.75rem;color:var(--muted)">({{ $a->plate_no }})</span></td>
        <td>{{ trim((string) $a->driver_name) ?: '—' }}</td>
        <td style="font-size:.83rem;white-space:nowrap">
          {{ $a->dispatch_date ? \Carbon\Carbon::parse($a->dispatch_date)->format('M j, Y') : '—' }}
          {{ $a->departure_time ? \Carbon\Carbon::parse($a->departure_time)->format('g:ia') : '' }}
        </td>
        <td style="font-size:.85rem">{{ $a->destination ?: '—' }}</td>
        @if ($canManage)
        <td>
          <form method="POST" action="{{ $transportBase }}" onsubmit="return confirm('Close this assignment? The vehicle will be marked available again.')">
            @csrf
            <input type="hidden" name="action" value="complete_assignment">
            <input type="hidden" name="assignment_id" value="{{ $a->assignment_id }}">
            <button type="submit" class="btn btn-sm btn-outline" style="font-size:.72rem;padding:3px 9px">Close</button>
          </form>
        </td>
        @endif
      </tr>
      @endforeach
      </tbody>
    </table>
    @endif
  </div>
</div>
@endif

@if ($canManage)
<!-- Add Vehicle Type Modal -->
<div class="modal-overlay" id="modalAddVehicle">
  <div class="modal" style="max-width:440px">
    <div class="modal-header"><div class="modal-title"><i data-feather="plus-circle"></i> Add Vehicle Type</div><button class="modal-close" data-modal-close>&times;</button></div>
    <form method="POST" action="{{ $transportBase }}">
      @csrf
      <input type="hidden" name="action" value="add_vehicle_rate">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">
        <div class="form-group" style="margin-bottom:0"><label>Label <span style="color:var(--red)">*</span></label><input type="text" name="label" class="form-control" required placeholder="e.g. Closed Van"></div>
        <div class="form-group" style="margin-bottom:0"><label>Type Key <span style="color:var(--red)">*</span></label><input type="text" name="vehicle_type" class="form-control" required placeholder="e.g. van"><div style="font-size:.75rem;color:var(--text-muted);margin-top:4px">Short identifier (no spaces). Used internally.</div></div>
        <div class="form-group" style="margin-bottom:0"><label>Description</label><input type="text" name="description" class="form-control" placeholder="Optional notes"></div>
        <div class="form-group" style="margin-bottom:0">
          <label>Rate Basis</label>
          <select name="rate_basis" class="form-control">
            @foreach ($rateBasisLabel as $rk => $rl)
            <option value="{{ $rk }}" {{ $rk === 'per_trip' ? 'selected' : '' }}>{{ $rl }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0"><label>Base Transport Charge (₱) <span style="color:var(--red)">*</span></label><input type="number" name="base_rate" class="form-control" step="0.01" min="0.01" required placeholder="0.00"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Add Vehicle</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Vehicle Type Modal -->
<div class="modal-overlay" id="modalEditVehicle">
  <div class="modal" style="max-width:440px">
    <div class="modal-header"><div class="modal-title"><i data-feather="edit-2"></i> Edit Vehicle Type</div><button class="modal-close" data-modal-close>&times;</button></div>
    <form method="POST" action="{{ $transportBase }}">
      @csrf
      <input type="hidden" name="action" value="edit_vehicle_rate">
      <input type="hidden" name="vehicle_id" id="ev_id">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">
        <div class="form-group" style="margin-bottom:0"><label>Label <span style="color:var(--red)">*</span></label><input type="text" name="label" id="ev_label" class="form-control" required></div>
        <div class="form-group" style="margin-bottom:0"><label>Type Key <span style="color:var(--red)">*</span></label><input type="text" name="vehicle_type" id="ev_type" class="form-control" required></div>
        <div class="form-group" style="margin-bottom:0"><label>Description</label><input type="text" name="description" id="ev_desc" class="form-control"></div>
        <div class="form-group" style="margin-bottom:0">
          <label>Rate Basis</label>
          <select name="rate_basis" id="ev_basis" class="form-control">
            @foreach ($rateBasisLabel as $rk => $rl)
            <option value="{{ $rk }}">{{ $rl }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0"><label>Base Transport Charge (₱) <span style="color:var(--red)">*</span></label><input type="number" name="base_rate" id="ev_rate" class="form-control" step="0.01" min="0.01" required></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Add Fleet Vehicle Modal -->
<div class="modal-overlay" id="modalAddFleet">
  <div class="modal" style="max-width:460px">
    <div class="modal-header"><div class="modal-title"><i data-feather="plus-circle"></i> Add Vehicle to Fleet</div><button class="modal-close" data-modal-close>&times;</button></div>
    <form method="POST" action="{{ $transportBase }}">
      @csrf
      <input type="hidden" name="action" value="add_fleet_vehicle">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">
        <div class="form-group" style="margin-bottom:0">
          <label>Vehicle Type <span style="color:var(--red)">*</span></label>
          <select name="vehicle_type_id" class="form-control" required>
            <option value="">— Select —</option>
            @foreach ($vehicleRates->where('is_active', 1) as $vr)
            <option value="{{ $vr->vehicle_id }}">{{ $vr->label }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-row" style="display:flex;gap:10px">
          <div class="form-group" style="margin-bottom:0;flex:1"><label>Brand</label><input type="text" name="brand" class="form-control" placeholder="e.g. Mitsubishi"></div>
          <div class="form-group" style="margin-bottom:0;flex:1"><label>Model</label><input type="text" name="model" class="form-control" placeholder="e.g. L300"></div>
        </div>
        <div class="form-group" style="margin-bottom:0"><label>Plate Number <span style="color:var(--red)">*</span></label><input type="text" name="plate_no" class="form-control" required placeholder="e.g. ABC 1234"></div>
        <div class="form-row" style="display:flex;gap:10px">
          <div class="form-group" style="margin-bottom:0;flex:1"><label>Year</label><input type="number" name="year" class="form-control" placeholder="e.g. 2024"></div>
          <div class="form-group" style="margin-bottom:0;flex:1"><label>Color</label><input type="text" name="color" class="form-control" placeholder="e.g. White"></div>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label>Status</label>
          <select name="status" class="form-control">
            @foreach ($fleetStatusLabel as $sk => $sl)
            <option value="{{ $sk }}" {{ $sk === 'available' ? 'selected' : '' }}>{{ $sl }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0"><label>Notes</label><input type="text" name="notes" class="form-control" placeholder="Optional"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Save Vehicle</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Fleet Vehicle Modal -->
<div class="modal-overlay" id="modalEditFleet">
  <div class="modal" style="max-width:460px">
    <div class="modal-header"><div class="modal-title"><i data-feather="edit-2"></i> Edit Vehicle</div><button class="modal-close" data-modal-close>&times;</button></div>
    <form method="POST" action="{{ $transportBase }}">
      @csrf
      <input type="hidden" name="action" value="edit_fleet_vehicle">
      <input type="hidden" name="fleet_vehicle_id" id="ef_id">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">
        <div class="form-group" style="margin-bottom:0">
          <label>Vehicle Type <span style="color:var(--red)">*</span></label>
          <select name="vehicle_type_id" id="ef_type" class="form-control" required>
            @foreach ($vehicleRates as $vr)
            <option value="{{ $vr->vehicle_id }}">{{ $vr->label }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-row" style="display:flex;gap:10px">
          <div class="form-group" style="margin-bottom:0;flex:1"><label>Brand</label><input type="text" name="brand" id="ef_brand" class="form-control"></div>
          <div class="form-group" style="margin-bottom:0;flex:1"><label>Model</label><input type="text" name="model" id="ef_model" class="form-control"></div>
        </div>
        <div class="form-group" style="margin-bottom:0"><label>Plate Number <span style="color:var(--red)">*</span></label><input type="text" name="plate_no" id="ef_plate" class="form-control" required></div>
        <div class="form-row" style="display:flex;gap:10px">
          <div class="form-group" style="margin-bottom:0;flex:1"><label>Year</label><input type="number" name="year" id="ef_year" class="form-control"></div>
          <div class="form-group" style="margin-bottom:0;flex:1"><label>Color</label><input type="text" name="color" id="ef_color" class="form-control"></div>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label>Status</label>
          <select name="status" id="ef_status" class="form-control">
            @foreach ($fleetStatusLabel as $sk => $sl)
            <option value="{{ $sk }}">{{ $sl }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0"><label>Notes</label><input type="text" name="notes" id="ef_notes" class="form-control"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Create Transport Assignment Modal -->
<div class="modal-overlay" id="modalCreateAssignment">
  <div class="modal" style="max-width:480px">
    <div class="modal-header"><div class="modal-title"><i data-feather="navigation"></i> Create Transport Assignment</div><button class="modal-close" data-modal-close>&times;</button></div>
    <form method="POST" action="{{ $transportBase }}">
      @csrf
      <input type="hidden" name="action" value="create_assignment">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">
        <div class="form-group" style="margin-bottom:0">
          <label>Booking <span style="color:var(--red)">*</span></label>
          <select name="booking_id" class="form-control" required>
            <option value="">— Select booking —</option>
            @foreach ($bookingsNeedingTransport as $b)
            <option value="{{ $b->booking_id }}">{{ $b->booking_reference }} — {{ $b->company_name ?: $b->contact_person }} ({{ \Carbon\Carbon::parse($b->shoot_date_start)->format('M j, Y') }})</option>
            @endforeach
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label>Vehicle <span style="color:var(--red)">*</span></label>
          <select name="fleet_vehicle_id" class="form-control" required>
            <option value="">— Select vehicle —</option>
            @foreach ($fleet->where('status', 'available') as $v)
            <option value="{{ $v->fleet_vehicle_id }}">{{ $v->plate_no }} — {{ $v->type_label }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label>Driver</label>
          <select name="driver_crew_id" class="form-control">
            <option value="0">— No driver assigned yet —</option>
            @foreach ($activeDrivers as $d)
            <option value="{{ $d->crew_id }}">{{ $d->name }}{{ $d->position_name ? ' — '.$d->position_name : '' }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-row" style="display:flex;gap:10px">
          <div class="form-group" style="margin-bottom:0;flex:1"><label>Dispatch Date</label><input type="date" name="dispatch_date" class="form-control"></div>
          <div class="form-group" style="margin-bottom:0;flex:1"><label>Departure Time</label><input type="time" name="departure_time" class="form-control"></div>
        </div>
        <div class="form-group" style="margin-bottom:0"><label>Destination</label><input type="text" name="destination" class="form-control" placeholder="e.g. Makati, Metro Manila"></div>
        <div class="form-group" style="margin-bottom:0"><label>Notes</label><input type="text" name="notes" class="form-control" placeholder="Optional"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Create Assignment</button>
      </div>
    </form>
  </div>
</div>
@endif

@push('scripts')
<script>
function openEditVehicle(vr) {
  document.getElementById('ev_id').value    = vr.vehicle_id;
  document.getElementById('ev_label').value = vr.label;
  document.getElementById('ev_type').value  = vr.vehicle_type;
  document.getElementById('ev_desc').value  = vr.description || '';
  document.getElementById('ev_basis').value = vr.rate_basis || 'per_trip';
  document.getElementById('ev_rate').value  = parseFloat(vr.base_rate).toFixed(2);
  openModal('modalEditVehicle');
}

function openEditFleet(v) {
  document.getElementById('ef_id').value     = v.fleet_vehicle_id;
  document.getElementById('ef_type').value   = v.vehicle_type_id;
  document.getElementById('ef_brand').value  = v.brand || '';
  document.getElementById('ef_model').value  = v.model || '';
  document.getElementById('ef_plate').value  = v.plate_no;
  document.getElementById('ef_year').value   = v.year || '';
  document.getElementById('ef_color').value  = v.color || '';
  document.getElementById('ef_status').value = v.status;
  document.getElementById('ef_notes').value  = v.notes || '';
  openModal('modalEditFleet');
}

function filterVehicleTable() {
  const q      = (document.getElementById('vSearchInput')?.value || '').toLowerCase();
  const status = document.getElementById('vStatusFilter')?.value || '';
  document.querySelectorAll('#vehicleTable tbody tr').forEach(row => {
    const label  = (row.dataset.label  || '').toLowerCase();
    const rowSt  = row.dataset.status || '';
    const matchQ = !q      || label.includes(q);
    const matchS = !status || rowSt === status;
    row.style.display = (matchQ && matchS) ? '' : 'none';
  });
}
</script>
@endpush
@endsection
