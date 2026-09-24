@extends('layouts.app')

@section('pageTitle', 'Field Resource Requests')

@section('breadcrumb')
<span>Field Resource Requests</span>
@endsection

@section('topbarActions')
@include('partials.export-dropdown', ['id' => 'FieldRequests'])
@endsection

@section('content')
@php
  $base = route('field-requests');
  $statusLabels = ['pending' => 'Pending Review', 'approved' => 'Approved', 'dispatched' => 'Dispatched', 'delivered' => 'Delivered', 'rejected' => 'Rejected'];
  $statusBadges = ['pending' => 'badge-yellow', 'approved' => 'badge-green', 'dispatched' => 'badge-blue', 'delivered' => 'badge-gray', 'rejected' => 'badge-red'];
@endphp

@if ($msg)
<div class="alert alert-{{ $msg['type'] }}" data-autohide>
  <i data-feather="{{ $msg['type'] === 'success' ? 'check-circle' : 'alert-circle' }}"></i>
  {!! $msg['text'] !!}
</div>
@endif

<div style="font-size:.8rem;color:var(--muted);margin-bottom:16px">
  Every approved field request (equipment, accessory, or crew follow-up) across all active bookings, in one place. Approve/Reject a new request from the booking's own Requests tab — once approved, dispatch and delivery are tracked here.
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
  <div class="stat-card {{ $stats['pending'] > 0 ? 'orange' : '' }}">
    <div class="stat-icon" style="{{ $stats['pending'] > 0 ? 'background:var(--orangel);color:var(--orange)' : '' }}"><i data-feather="file-text"></i></div>
    <div class="stat-value">{{ $stats['pending'] }}</div>
    <div class="stat-label">Awaiting Review</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="package"></i></div>
    <div class="stat-value" style="color:var(--green)">{{ $stats['approved'] }}</div>
    <div class="stat-label">Allocation Needed <span style="font-weight:400;color:var(--muted)">· Equipment/Accessories</span></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="users"></i></div>
    <div class="stat-value" style="color:var(--accent)">{{ $stats['ready_crew'] }}</div>
    <div class="stat-label">Ready for Dispatch <span style="font-weight:400;color:var(--muted)">· Crew</span></div>
  </div>
  <div class="stat-card {{ $stats['overdue'] > 0 ? 'red' : '' }}">
    <div class="stat-icon" style="{{ $stats['overdue'] > 0 ? 'background:var(--redl);color:var(--red)' : '' }}"><i data-feather="alert-triangle"></i></div>
    <div class="stat-value">{{ $stats['overdue'] }}</div>
    <div class="stat-label">Overdue <span style="font-weight:400;color:var(--muted)">· Past ETA, not delivered</span></div>
  </div>
</div>

<div class="tabs" style="margin-bottom:18px">
  <a href="{{ $base }}?status=active" class="tab-btn {{ $statusFilter === 'active' ? 'active' : '' }}">Active</a>
  @foreach ($statusLabels as $key => $label)
  <a href="{{ $base }}?status={{ $key }}" class="tab-btn {{ $statusFilter === $key ? 'active' : '' }}">{{ $label }}</a>
  @endforeach
  <a href="{{ $base }}?status=all" class="tab-btn {{ $statusFilter === 'all' ? 'active' : '' }}">All</a>
</div>

<div class="card">
  <div class="card-header">
    <h2 class="card-title">Field Requests</h2>
  </div>
  @if ($requests->isEmpty())
  <div class="empty-state"><i data-feather="truck"></i><h3>Nothing here</h3><p>No field requests match this filter.</p></div>
  @else
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Booking</th><th>Client</th><th>Type</th><th>Item</th><th>Qty</th><th>Status</th><th>Dispatch Info</th><th>Action</th></tr>
      </thead>
      <tbody>
        @foreach ($requests as $r)
        @php
          $itemLabel = match ($r->item_type) {
              'accessory' => $r->accessory_name,
              'crew' => ($r->crew_name && trim($r->crew_name) !== '') ? ($r->crew_name . ' (' . $r->position_name . ')') : ($r->position_name . ' — unassigned'),
              default => $r->equipment_name,
          };
          // "Approved" is a decision state, not fulfillment readiness — show what's actually
          // still needed instead of the same flat label for every item type. Purely a display
          // label; the underlying request_status column is untouched.
          $isOverdue = $r->status === 'dispatched' && $r->eta && \Illuminate\Support\Carbon::parse($r->eta)->isPast();
          if ($isOverdue) {
              $rowStatusLabel = 'Delivery Overdue';
              $rowStatusBadge = 'badge-red';
          } elseif ($r->status === 'approved' && $r->item_type === 'crew') {
              $rowStatusLabel = 'Ready for Dispatch';
              $rowStatusBadge = 'badge-blue';
          } elseif ($r->status === 'approved') {
              $rowStatusLabel = 'Allocation Needed';
              $rowStatusBadge = 'badge-green';
          } else {
              $rowStatusLabel = $statusLabels[$r->status];
              $rowStatusBadge = $statusBadges[$r->status];
          }
        @endphp
        <tr>
          <td>
            <a href="{{ route('booking-detail', $r->booking_id) }}" style="font-weight:700;color:var(--accent)">{{ $r->booking_reference }}</a>
            <div style="font-size:.72rem;color:var(--muted)">{{ \Illuminate\Support\Carbon::parse($r->shoot_date_start)->format('M j') }} – {{ \Illuminate\Support\Carbon::parse($r->shoot_date_end)->format('M j') }}</div>
          </td>
          <td style="font-size:.85rem">{{ $r->company_name ?: $r->contact_person }}</td>
          <td><span class="badge badge-gray" style="font-size:.68rem;text-transform:capitalize">{{ $r->item_type }}</span></td>
          <td style="font-weight:600">{{ $itemLabel }}</td>
          <td>{{ $r->quantity }}</td>
          <td><span class="badge {{ $rowStatusBadge }}">{{ $rowStatusLabel }}</span></td>
          <td style="font-size:.78rem;color:var(--muted)">
            @if (in_array($r->status, ['dispatched', 'delivered'], true))
              {{ $r->vehicle_label ?: '—' }}@if($r->driver_name)<br>{{ trim($r->driver_name) }}@endif
              @if($r->eta)<br>ETA {{ \Illuminate\Support\Carbon::parse($r->eta)->format('M j, g:ia') }}@endif
              @if($r->status === 'delivered' && $r->delivered_at)<br><span style="color:var(--green,#16a34a)">Delivered {{ \Illuminate\Support\Carbon::parse($r->delivered_at)->format('M j, g:ia') }}</span>@endif
            @else
              —
            @endif
          </td>
          <td>
            @if ($r->status === 'pending')
            <a href="{{ route('booking-detail', $r->booking_id) }}#tab-requests" class="btn btn-outline btn-sm" style="font-size:.72rem;padding:4px 10px" title="Approve or reject with full booking context">
              <i data-feather="external-link" style="width:11px;height:11px"></i> Review
            </a>
            @elseif ($r->status === 'approved')
            <button class="btn btn-primary btn-sm" style="font-size:.72rem;padding:4px 10px"
                    onclick="openDispatch({{ $r->request_id }}, '{{ addslashes($itemLabel) }}', '{{ $r->item_type }}', {{ (int) $r->crew_id }}, {{ (int) ($r->position_id ?? 0) }})">Dispatch</button>
            @elseif ($r->status === 'dispatched')
            <button class="btn btn-success btn-sm" style="font-size:.72rem;padding:4px 10px"
                    onclick="openDeliverConfirm({{ $r->request_id }}, '{{ addslashes($itemLabel) }}')">Mark Delivered</button>
            @else
            <span style="font-size:.75rem;color:var(--muted)">—</span>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif
</div>

<!-- DISPATCH MODAL — styled to match Booking Detail's own Assign Transport modal -->
<div class="modal-overlay" id="modalDispatch">
  <div class="modal" style="max-width:480px">
    <div class="modal-header" style="background:#f0f9ff;border-bottom:1px solid #bae6fd">
      <div class="modal-title" id="dispatchTitle" style="color:#0369a1"><i data-feather="truck" style="width:16px;height:16px;margin-right:8px;vertical-align:middle"></i>Dispatch to Field</div>
      <button class="modal-close" onclick="closeModal('modalDispatch')">&times;</button>
    </div>
    <form method="POST">
      @csrf
      <input type="hidden" name="action" value="dispatch_field_request">
      <input type="hidden" name="request_id" id="dispatchReqId">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">
        <div id="dispatchDesc" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:7px;padding:10px 13px;font-size:12.5px;color:#1d4ed8"></div>
        <div id="dispatchCrewNote" style="display:none;font-size:.75rem;color:var(--muted);background:var(--s2);border-radius:6px;padding:8px 10px">
          <i data-feather="info" style="width:11px;height:11px;vertical-align:middle"></i> This crew member is already assigned to the role — this step just records their transport to set.
        </div>
        <div id="dispatchAssignWrap" style="display:none" class="form-group">
          <label>Assign Crew Member <span class="req">*</span></label>
          <select name="assign_crew_id" id="dispatchAssignSelect" class="form-control">
            <option value="">Select who fills this role…</option>
          </select>
          <div style="font-size:.72rem;color:var(--muted);margin-top:3px">Checked for schedule conflicts and unavailability when you confirm.</div>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label>Vehicle <span style="font-size:.75rem;font-weight:400;color:var(--muted)">— optional</span></label>
          <select name="vehicle_rate_id" class="form-control">
            <option value="">— No vehicle tracked —</option>
            @foreach ($vehicleRates as $v)
            <option value="{{ $v->vehicle_id }}">{{ $v->label }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label>Driver <span class="req">*</span></label>
          <select name="driver_crew_id" class="form-control" required>
            <option value="">Select a driver / crew member…</option>
            @foreach ($activeDrivers as $d)
            <option value="{{ $d->crew_id }}">{{ $d->name }}{{ $d->position_name ? ' — ' . $d->position_name : '' }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label>ETA <span class="req">*</span></label>
          <input type="datetime-local" name="eta" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalDispatch')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="background:#0369a1;border-color:#0369a1"><i data-feather="truck"></i> Confirm Dispatch</button>
      </div>
    </form>
  </div>
</div>

<!-- DELIVER CONFIRMATION MODAL — replaces the native browser confirm() -->
<div class="modal-overlay" id="modalDeliverConfirm">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <div class="modal-title"><i data-feather="check-circle" style="width:16px;height:16px;margin-right:8px;vertical-align:middle;color:var(--green)"></i>Confirm Delivery</div>
      <button class="modal-close" onclick="closeModal('modalDeliverConfirm')">&times;</button>
    </div>
    <form method="POST">
      @csrf
      <input type="hidden" name="action" value="deliver_field_request">
      <input type="hidden" name="request_id" id="deliverReqId">
      <div class="modal-body">
        <p style="font-size:.85rem;color:var(--text-muted);margin:0 0 4px">
          Mark <strong id="deliverDesc"></strong> as delivered? This records the delivery timestamp on the request.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalDeliverConfirm')">Cancel</button>
        <button type="submit" class="btn btn-success"><i data-feather="check-circle"></i> Confirm Delivered</button>
      </div>
    </form>
  </div>
</div>

@endsection

@push('scripts')
<script>
// Same list the Driver dropdown uses — re-sorted client-side to put crew matching the
// requested position first when a crew request needs an actual assignment.
const ALL_ACTIVE_CREW = {!! $activeDrivers->values()->toJson() !!};

function openDispatch(reqId, desc, itemType, crewId, positionId) {
  const isCrew = itemType === 'crew';
  const needsAssign = isCrew && !crewId;
  document.getElementById('dispatchReqId').value = reqId;
  document.getElementById('dispatchDesc').textContent = (isCrew ? 'Crew: ' : 'Dispatching: ') + desc;
  document.getElementById('dispatchCrewNote').style.display = (isCrew && !needsAssign) ? 'block' : 'none';

  const assignWrap = document.getElementById('dispatchAssignWrap');
  const assignSelect = document.getElementById('dispatchAssignSelect');
  if (needsAssign) {
    const sorted = ALL_ACTIVE_CREW.slice().sort((a, b) => {
      const aMatch = positionId && a.position_id === positionId ? 0 : 1;
      const bMatch = positionId && b.position_id === positionId ? 0 : 1;
      return aMatch - bMatch;
    });
    assignSelect.innerHTML = '<option value="">Select who fills this role…</option>' +
      sorted.map(c => `<option value="${c.crew_id}">${c.name}${c.position_name ? ' — ' + c.position_name : ''}</option>`).join('');
    assignWrap.style.display = 'block';
    assignSelect.required = true;
  } else {
    assignWrap.style.display = 'none';
    assignSelect.required = false;
  }
  openModal('modalDispatch');
}

function openDeliverConfirm(reqId, desc) {
  document.getElementById('deliverReqId').value = reqId;
  document.getElementById('deliverDesc').textContent = desc;
  openModal('modalDeliverConfirm');
}
</script>
@endpush
