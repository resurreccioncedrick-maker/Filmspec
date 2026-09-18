@extends('layouts.app')

@section('pageTitle', 'Field Requests')

@section('breadcrumb')
<span>Field Requests</span>
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

<div class="stats-grid" style="margin-bottom:20px">
  <div class="stat-card">
    <div class="stat-value" style="color:var(--green)">{{ $stats['approved'] }}</div>
    <div class="stat-label">Awaiting Dispatch</div>
  </div>
  <div class="stat-card">
    <div class="stat-value" style="color:var(--blue)">{{ $stats['dispatched'] }}</div>
    <div class="stat-label">Out for Delivery</div>
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
          <td><span class="badge {{ $statusBadges[$r->status] }}">{{ $statusLabels[$r->status] }}</span></td>
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
            @if ($r->status === 'approved')
            <button class="btn btn-primary btn-sm" style="font-size:.72rem;padding:4px 10px"
                    onclick="openDispatch({{ $r->request_id }}, '{{ addslashes($itemLabel) }}')">Dispatch</button>
            @elseif ($r->status === 'dispatched')
            <form method="POST" onsubmit="return confirm('Mark this delivered?')">
              @csrf
              <input type="hidden" name="action" value="deliver_field_request">
              <input type="hidden" name="request_id" value="{{ $r->request_id }}">
              <button type="submit" class="btn btn-success btn-sm" style="font-size:.72rem;padding:4px 10px">Mark Delivered</button>
            </form>
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

<!-- DISPATCH MODAL -->
<div class="modal-overlay" id="modalDispatch">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <h3 id="dispatchTitle"><i data-feather="truck" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Dispatch</h3>
      <button class="modal-close" onclick="closeModal('modalDispatch')">&times;</button>
    </div>
    <form method="POST">
      @csrf
      <input type="hidden" name="action" value="dispatch_field_request">
      <input type="hidden" name="request_id" id="dispatchReqId">
      <div class="modal-body">
        <div id="dispatchDesc" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:7px;padding:10px 13px;margin-bottom:14px;font-size:12.5px;color:#1d4ed8"></div>
        <div class="form-group">
          <label>Vehicle</label>
          <select name="vehicle_rate_id" class="form-control">
            <option value="">— No vehicle tracked —</option>
            @foreach ($vehicleRates as $v)
            <option value="{{ $v->vehicle_id }}">{{ $v->label }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group">
          <label>Driver <span class="req">*</span></label>
          <select name="driver_crew_id" class="form-control" required>
            <option value="">Select a driver / crew member…</option>
            @foreach ($activeDrivers as $d)
            <option value="{{ $d->crew_id }}">{{ $d->name }}{{ $d->position_name ? ' — ' . $d->position_name : '' }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group">
          <label>ETA <span class="req">*</span></label>
          <input type="datetime-local" name="eta" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalDispatch')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="truck"></i> Confirm Dispatch</button>
      </div>
    </form>
  </div>
</div>

@endsection

@push('scripts')
<script>
function openDispatch(reqId, desc) {
  document.getElementById('dispatchReqId').value = reqId;
  document.getElementById('dispatchDesc').textContent = 'Dispatching: ' + desc;
  openModal('modalDispatch');
}
</script>
@endpush
