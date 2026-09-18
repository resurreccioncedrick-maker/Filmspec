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
        <tr><th>Label</th><th>Type Key</th><th>Description</th><th>Base Rate</th><th>Status</th>
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

@if ($canManage)
<!-- Add Vehicle Modal -->
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
        <div class="form-group" style="margin-bottom:0"><label>Base Rate (₱) <span style="color:var(--red)">*</span></label><input type="number" name="base_rate" class="form-control" step="0.01" min="0.01" required placeholder="0.00"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Add Vehicle</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Vehicle Modal -->
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
        <div class="form-group" style="margin-bottom:0"><label>Base Rate (₱) <span style="color:var(--red)">*</span></label><input type="number" name="base_rate" id="ev_rate" class="form-control" step="0.01" min="0.01" required></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Save Changes</button>
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
  document.getElementById('ev_rate').value  = parseFloat(vr.base_rate).toFixed(2);
  openModal('modalEditVehicle');
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
