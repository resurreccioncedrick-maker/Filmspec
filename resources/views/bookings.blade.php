@extends('layouts.app')

@section('pageTitle', 'Bookings')

@section('breadcrumb')
<span>Bookings</span>
@endsection

@section('topbarActions')
@include('partials.export-dropdown', ['id' => 'Bookings'])
<button onclick="openModal('modalAddBooking')" class="btn btn-primary btn-sm"> New Booking</button>
@endsection

@section('content')
@php
  $role = auth()->user()->role->role_name ?? '';
  $hasRole = fn (array $roles) => in_array($role, $roles, true);
  $bookingsBase = route('bookings');
@endphp

<!-- Leaflet map assets (used in booking modals) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
.modal .leaflet-pane, .modal .leaflet-top, .modal .leaflet-bottom { z-index: 400; }
.modal .leaflet-control { z-index: 400; }
.modal-body { overflow: visible; }
</style>

@if ($msg)
<div class="alert alert-{{ $msg['type'] }}" data-autohide>
  <i data-feather="{{ $msg['type'] === 'success' ? 'check-circle' : 'alert-circle' }}"></i>
  {!! $msg['text'] !!}
</div>
@endif

@if ($pendingApprovalCount > 0)
<div style="background:rgba(251,146,60,.1);border:1px solid rgba(251,146,60,.4);border-radius:8px;padding:10px 16px;margin-bottom:14px;display:flex;align-items:center;gap:10px">
  <i data-feather="alert-circle" style="width:16px;height:16px;color:#fb923c;flex-shrink:0"></i>
  <span style="font-size:13px"><strong>{{ $pendingApprovalCount }}</strong> booking{{ $pendingApprovalCount !== 1 ? 's' : '' }} awaiting your approval. Scroll down or filter by status to review.</span>
</div>
@endif

@if ($clientFilter && $clientFilterName)
<div style="background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.3);border-radius:8px;padding:10px 16px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between">
  <span style="font-size:13px;color:var(--accent)">
    <i data-feather="filter" style="width:13px;height:13px;vertical-align:middle;margin-right:4px"></i>
    Showing bookings for <strong>{{ $clientFilterName }}</strong>
  </span>
  <a href="{{ $bookingsBase }}{{ $payFilter ? '?pay=' . $payFilter : '' }}" class="btn btn-sm btn-secondary">Clear Filter</a>
</div>
@endif

<!-- Status quick filter tabs -->
<div class="tabs" style="margin-bottom:18px">
  @php $cq = $clientFilter ? '&client=' . $clientFilter : ''; @endphp
  <a href="{{ $bookingsBase }}{{ $clientFilter ? '?client=' . $clientFilter : '' }}" class="tab-btn {{ ! $statusFilter ? 'active' : '' }}">All <span class="badge badge-gray" style="margin-left:4px">{{ $total }}</span></a>
  @php
    $statusTabLabel = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'ongoing' => 'In Field', 'pending_inspection' => 'Inspection', 'returned' => 'Returned', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
  @endphp
  @foreach ($counts as $s => $c)
  <a href="{{ $bookingsBase }}?status={{ $s }}{{ $cq }}" class="tab-btn {{ $statusFilter === $s ? 'active' : '' }}">
    {{ $statusTabLabel[$s] ?? ucfirst($s) }} <span class="badge {{ $statusBadge[$s] ?? 'badge-gray' }}" style="margin-left:4px">{{ $c }}</span>
  </a>
  @endforeach
  <a href="{{ $bookingsBase }}?status=archived{{ $cq }}" class="tab-btn {{ $statusFilter === 'archived' ? 'active' : '' }}">
    Archived <span class="badge badge-gray" style="margin-left:4px">{{ $archivedCount }}</span>
  </a>
</div>

<div class="card">
  <div class="card-header">
    <h2 class="card-title">Booking Records</h2>
  </div>
  <div class="card-body" style="padding-bottom:0">
    <form method="GET" action="{{ $bookingsBase }}">
      @if ($clientFilter)<input type="hidden" name="client" value="{{ $clientFilter }}">@endif
      <div class="filter-bar">
        <div class="search-input-wrap">
          <i data-feather="search"></i>
          <input type="text" name="q" class="form-control" placeholder="Search reference, client, project…" value="{{ $search }}">
        </div>
        <select name="status" class="form-control" style="width:auto">
          <option value="">All Status</option>
          @foreach (['pending', 'confirmed', 'ongoing', 'completed', 'cancelled'] as $s)
          <option value="{{ $s }}" {{ $statusFilter === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
          @endforeach
        </select>
        <select name="pay" class="form-control" style="width:auto">
          <option value="">All Payment</option>
          <option value="unpaid" {{ $payFilter === 'unpaid' ? 'selected' : '' }}>Pending</option>
          <option value="partial" {{ $payFilter === 'partial' ? 'selected' : '' }}>Partially Paid</option>
          <option value="paid" {{ $payFilter === 'paid' ? 'selected' : '' }}>Fully Paid</option>
          <option value="overdue" {{ $payFilter === 'overdue' ? 'selected' : '' }}>Overdue</option>
          <option value="refunded" {{ $payFilter === 'refunded' ? 'selected' : '' }}>Refunded</option>
          <option value="cancelled" {{ $payFilter === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">
          <i data-feather="filter"></i> Filter
        </button>
        @if ($statusFilter || $payFilter || $search)
        <a href="{{ $bookingsBase }}{{ $clientFilter ? '?client=' . $clientFilter : '' }}" class="btn btn-outline btn-sm">
          <i data-feather="x"></i> Clear
        </a>
        @endif
      </div>
    </form>
  </div>

  <div class="table-wrap">
    @if (empty($bookings) || count($bookings) === 0)
    <div class="card">
      <div class="empty-state">
        <i data-feather="calendar"></i>
        <h3>No bookings found</h3>
        <p>Create a new booking or adjust your filters.</p>
      </div>
    </div>
    @else
    <table>
      <thead>
        <tr>
          <th>Reference</th>
          <th>Client</th>
          <th>Project</th>
          <th>Shoot Start</th>
          <th>Shoot End</th>
          <th>Status</th>
          <th>Payment</th>
          <th>Amount</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($bookings as $bk)
        <tr>
          <td>
            <a href="{{ route('booking-detail', $bk->booking_id) }}" style="font-weight:700;color:var(--blue-600)">
              {{ $bk->booking_reference }}
            </a>
            @if ($bk->client_type === 'first_time')
            <span class="badge badge-orange" style="font-size:.63rem;margin-left:4px">NEW</span>
            @endif
            @php $apSt = $bk->approval_status ?? 'approved'; @endphp
            @if ($apSt === 'pending_approval')
            <span class="badge badge-orange" style="font-size:.6rem;display:block;margin-top:3px">Pending Approval</span>
            @elseif ($apSt === 'rejected')
            <span class="badge badge-red" style="font-size:.6rem;display:block;margin-top:3px" title="{{ $bk->approval_notes ?? '' }}">Rejected</span>
            @endif
          </td>
          <td>
            <div style="font-weight:600">{{ $bk->company_name ?: $bk->contact_person }}</div>
            @if ($bk->company_name)
            <div style="font-size:.78rem;color:var(--muted)">{{ $bk->contact_person }}</div>
            @endif
          </td>
          <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            {{ $bk->project_title ?: '—' }}
          </td>
          <td>{{ \Carbon\Carbon::parse($bk->shoot_date_start)->format('M j, Y') }}</td>
          <td>{{ \Carbon\Carbon::parse($bk->shoot_date_end)->format('M j, Y') }}</td>
          <td><span class="badge {{ $statusBadge[$bk->booking_status] }}">{{ ucfirst($bk->booking_status) }}</span></td>
          <td>@if ($bk->booking_status === 'cancelled') — @else <span class="badge {{ $payBadge[$bk->payment_status] ?? 'badge-gray' }}">{{ $payLabel[$bk->payment_status] ?? ucfirst($bk->payment_status) }}</span> @endif</td>
          <td style="font-weight:600;color:var(--accent)">
            {{ $bk->final_amount > 0 ? '₱' . number_format($bk->final_amount, 2) : '—' }}
            @if ($bk->pending_discount)
            <div><span class="badge badge-yellow" style="font-size:.65rem" title="Awaiting approval on the Billing page">Discount pending</span></div>
            @elseif ($bk->active_discount > 0)
            <div><span class="badge badge-green" style="font-size:.65rem">−₱{{ number_format($bk->active_discount, 2) }}</span></div>
            @endif
          </td>
          <td>
            @php
              $canDiscount = $hasRole(['admin', 'super_admin', 'operations_manager', 'traffic']) && ! $bk->pending_discount && $bk->booking_status !== 'cancelled';
              $canEdit = $hasRole(['admin', 'super_admin', 'operations_manager', 'traffic']);
              $canArchive = $hasRole(['admin', 'super_admin', 'operations_manager']) && in_array($bk->booking_status, ['pending', 'cancelled']) && ! $bk->is_archived;
              $canRestore = $hasRole(['admin', 'super_admin', 'operations_manager']) && $bk->is_archived;
              $canApproveReject = $hasRole(['admin', 'super_admin', 'operations_manager']) && ($bk->approval_status ?? 'approved') === 'pending_approval';
              $canResubmit = ($bk->approval_status ?? 'approved') === 'rejected' && $hasRole(['traffic', 'admin', 'operations_manager']);
              $hasMenuItems = $bk->latest_ce_id || $canDiscount || $canEdit || $canApproveReject || $canResubmit || $canArchive || $canRestore;
            @endphp
            <div style="display:flex;gap:6px;align-items:center">
              <a href="{{ route('booking-detail', $bk->booking_id) }}" class="btn btn-outline btn-sm">
                <i data-feather="eye"></i> View
              </a>
              @if ($hasMenuItems)
              <div class="action-menu-wrap">
                <button type="button" class="btn-icon" onclick="toggleActionMenu(this)" title="More actions">
                  <i data-feather="more-vertical"></i>
                </button>
                <div class="action-menu align-right">
                  @if ($bk->latest_ce_id)
                  <a href="{{ route('ce-preview', ['booking_id' => $bk->booking_id, 'ce_id' => $bk->latest_ce_id]) }}">
                    <i data-feather="file-text"></i> View Cost Estimate
                  </a>
                  @endif
                  @if ($canDiscount)
                  <button type="button" onclick="closeActionMenus(); openDiscountModal({{ $bk->booking_id }})">
                    <i data-feather="percent"></i> Propose Discount
                  </button>
                  @endif
                  @if ($canEdit)
                  <button type="button" onclick="closeActionMenus(); openEditBooking({{ \Illuminate\Support\Js::from($bk) }})">
                    <i data-feather="edit-2"></i> Edit Booking
                  </button>
                  @endif
                  @if ($canApproveReject)
                  <form method="POST" action="{{ $bookingsBase }}">
                    @csrf
                    <input type="hidden" name="action" value="approve_booking">
                    <input type="hidden" name="booking_id" value="{{ $bk->booking_id }}">
                    <button type="submit" class="text-success" onclick="return confirm('Approve and confirm this booking?')">
                      <i data-feather="check"></i> Approve Booking
                    </button>
                  </form>
                  <button type="button" class="text-danger" onclick="closeActionMenus(); openRejectModal({{ $bk->booking_id }})">
                    <i data-feather="x"></i> Reject Booking
                  </button>
                  @endif
                  @if ($canResubmit)
                  <form method="POST" action="{{ $bookingsBase }}">
                    @csrf
                    <input type="hidden" name="action" value="resubmit_booking">
                    <input type="hidden" name="booking_id" value="{{ $bk->booking_id }}">
                    <button type="submit">
                      <i data-feather="refresh-cw"></i> Resubmit for Approval
                    </button>
                  </form>
                  @endif
                  @if ($canArchive)
                  @if ($bk->latest_ce_id || $canDiscount || $canEdit || $canApproveReject || $canResubmit)
                  <div class="action-menu-divider"></div>
                  @endif
                  <form method="POST" action="{{ $bookingsBase }}"
                        onsubmit="return confirm('Archive booking {{ addslashes($bk->booking_reference) }}?\nIt will be hidden from the active list but can be restored anytime from the Archived tab.')">
                    @csrf
                    <input type="hidden" name="action" value="archive_booking">
                    <input type="hidden" name="booking_id" value="{{ $bk->booking_id }}">
                    <button type="submit" class="text-danger">
                      <i data-feather="archive"></i> Archive Booking
                    </button>
                  </form>
                  @endif
                  @if ($canRestore)
                  @if ($bk->latest_ce_id || $canDiscount || $canEdit || $canApproveReject || $canResubmit)
                  <div class="action-menu-divider"></div>
                  @endif
                  <form method="POST" action="{{ $bookingsBase }}"
                        onsubmit="return confirm('Restore booking {{ addslashes($bk->booking_reference) }} from the archive?')">
                    @csrf
                    <input type="hidden" name="action" value="unarchive_booking">
                    <input type="hidden" name="booking_id" value="{{ $bk->booking_id }}">
                    <button type="submit">
                      <i data-feather="rotate-ccw"></i> Restore Booking
                    </button>
                  </form>
                  @endif
                </div>
              </div>
              @endif
            </div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>

    @if ($pages > 1)
    <div style="padding:14px 16px">
      <div class="pagination">
        @for ($pi = 1; $pi <= $pages; $pi++)
        <a href="{{ $bookingsBase }}?p={{ $pi }}&status={{ $statusFilter }}&pay={{ $payFilter }}&q={{ urlencode($search) }}{{ $clientFilter ? '&client=' . $clientFilter : '' }}"
           class="page-btn {{ $pi == $page ? 'active' : '' }}">{{ $pi }}</a>
        @endfor
      </div>
    </div>
    @endif
    @endif
  </div>
</div>

<!-- ── ADD BOOKING MODAL ── -->
<div class="modal-overlay" id="modalAddBooking">
  <div class="modal" style="max-width:640px">
    <div class="modal-header">
      <h3 class="modal-title"><i data-feather="calendar" style="width:18px;height:18px;margin-right:8px;vertical-align:middle"></i>New Booking</h3>
      <button class="modal-close"><i data-feather="x"></i></button>
    </div>
    <form method="POST" action="{{ $bookingsBase }}">
      @csrf
      <input type="hidden" name="action" value="add_booking">
      <div class="modal-body">

        <div class="form-group">
          <label>Client *</label>
          <select name="client_id" id="clientSelect" class="form-control" onchange="toggleNewClient(this.value)">
            <option value="">— Select existing client —</option>
            @foreach ($clients as $cl)
            <option value="{{ $cl->client_id }}" data-type="{{ $cl->client_type }}" data-terms="{{ $cl->payment_terms ?? '50_downpayment' }}">{{ ($cl->company_name ? $cl->company_name . ' — ' : '') . $cl->contact_person }}</option>
            @endforeach
            <option value="new">+ Add New Client</option>
          </select>
        </div>

        <div id="clientTypeBanner" style="display:none;margin-bottom:12px"></div>

        <div id="newClientFields" style="display:none;background:rgba(59,130,246,.08);border-radius:var(--radius-md);padding:14px;margin-bottom:12px">
          <div style="font-size:.8rem;font-weight:700;color:var(--accent);margin-bottom:12px;text-transform:uppercase;letter-spacing:.06em">New Client Details</div>
          <div class="form-row">
            <div class="form-group" style="margin-bottom:0">
              <label>Contact Person *</label>
              <input type="text" name="new_client_name" class="form-control" placeholder="Full name">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label>Email</label>
              <input type="email" name="new_client_email" class="form-control" placeholder="email@example.com">
            </div>
          </div>
          <div class="form-row" style="margin-top:12px">
            <div class="form-group" style="margin-bottom:0">
              <label>Phone</label>
              <input type="text" name="new_client_phone" class="form-control" placeholder="+63 9XX XXX XXXX">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label>Client Type</label>
              <select name="new_client_type" class="form-control">
                <option value="first_time">New Customer</option>
                <option value="regular">Regular</option>
              </select>
            </div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Booking Type *</label>
            <select name="booking_type" class="form-control" required>
              <option value="package">Equipment + Crew</option>
            </select>
          </div>
          <div class="form-group">
            <label>Project Type *</label>
            <select name="project_type" class="form-control" required>
              <option value="commercial">Commercial / Advertisement</option>
              <option value="indie_film">Indie Film</option>
              <option value="tv_network">TV Network</option>
              <option value="music_video">Music Video</option>
              <option value="interview">Interview</option>
              <option value="other">Other</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label>Project Title</label>
          <input type="text" name="project_title" class="form-control" placeholder="e.g. Jollibee TVC — Summer 2026">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Shoot Start Date *</label>
            <input type="date" id="shoot_start" name="shoot_date_start" class="form-control" required
                   min="{{ date('Y-m-d', strtotime('+2 days')) }}"
                   onchange="checkDateRange('shoot_start','shoot_end','add_date_warn')">
          </div>
          <div class="form-group">
            <label>Shoot End Date *</label>
            <input type="date" id="shoot_end" name="shoot_date_end" class="form-control" required
                   onchange="checkDateRange('shoot_start','shoot_end','add_date_warn')">
          </div>
        </div>
        <div id="add_date_warn" style="display:none;background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c;padding:8px 12px;border-radius:6px;font-size:12px;margin-top:-8px;margin-bottom:10px">
          Rental period cannot exceed 3 months (90 days).
        </div>
        <div style="font-size:11px;color:var(--text-muted);margin-top:-6px;margin-bottom:8px">
          Minimum 48-hour advance notice required.
        </div>

        <div class="form-group">
          <label>Shoot Location <span style="font-size:.76rem;color:var(--muted);font-weight:400">— click map to pin (Philippines only)</span></label>
          <div id="addMapContainer" style="position:relative;border-radius:8px;overflow:hidden;border:2px solid var(--border);margin-bottom:6px">
            <div id="mapAddBooking" style="height:280px"></div>
            <button type="button" id="add_lock_btn" onclick="toggleMapLock('add')"
              style="display:none;position:absolute;bottom:12px;left:50%;transform:translateX(-50%);z-index:500;background:rgba(0,96,199,.92);color:#fff;border:none;border-radius:8px;padding:7px 16px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;align-items:center;gap:7px;box-shadow:0 2px 12px rgba(0,0,0,.28);backdrop-filter:blur(4px);letter-spacing:.02em;white-space:nowrap">
              <span id="add_lock_icon" style="display:flex;align-items:center"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg></span><span id="add_lock_text">Lock Location</span>
            </button>
          </div>
          <div id="addZoneInfo" style="display:none;padding:8px 12px;border-radius:6px;margin-bottom:8px;font-size:12.5px"></div>
          <input type="text" name="shoot_location" id="add_shoot_location" class="form-control"
                 placeholder="Address auto-fills when you pin — or type manually">
          <input type="hidden" name="delivery_address"     id="add_delivery_address">
          <input type="hidden" name="location_lat"         id="add_lat">
          <input type="hidden" name="location_lng"         id="add_lng">
          <input type="hidden" name="location_zone"        id="add_zone">
          <input type="hidden" name="transport_multiplier" id="add_multiplier" value="1">
          <input type="hidden" name="transportation_cost"  id="add_transport_cost" value="0">
        </div>

        <input type="hidden" name="vehicle_rate_id" id="add_vehicle_rate" value="">
        <input type="hidden" name="add_needs_transport" value="0">

        <div class="form-group">
          <label>Notes</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Special requirements, call time, etc."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Create Booking</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
const VEHICLE_RATES = {!! $vehicleRatesJson ?? '{}' !!};

function checkDateRange(startId, endId, warnId) {
  const s = document.getElementById(startId)?.value;
  const e = document.getElementById(endId)?.value;
  const w = document.getElementById(warnId);
  if (!w) return;
  if (s && e) {
    const days = (new Date(e) - new Date(s)) / 86400000;
    w.style.display = days > 90 ? 'block' : 'none';
  }
}

function updateTransportCost(prefix) {
  const sel       = document.getElementById(prefix === 'add' ? 'add_vehicle_rate' : 'edit_vehicle_rate');
  const zoneInput = document.getElementById(prefix + (prefix === 'add' ? '_zone' : '_zone'));
  const costInput = document.getElementById(prefix === 'add' ? 'add_transport_cost' : 'edit_transport_cost');
  const multInput = document.getElementById(prefix === 'add' ? 'add_multiplier' : 'edit_multiplier');
  if (!sel || !zoneInput || !costInput) return;
  const vid  = sel.value;
  const zone = zoneInput.value;
  const cfg  = ZONE_CONFIG[zone];
  const mult = cfg ? cfg.multiplier : parseFloat(multInput?.value || 1);
  if (vid && VEHICLE_RATES[vid]) {
    const cost = VEHICLE_RATES[vid].base_rate * mult;
    costInput.value = cost.toFixed(2);
  } else {
    const cost = BASE_TRANSPORT_RATE * mult;
    costInput.value = cost.toFixed(2);
  }
}

function toggleNewClient(val) {
  document.getElementById('newClientFields').style.display = val === 'new' ? 'block' : 'none';
  const banner = document.getElementById('clientTypeBanner');
  if (!val || val === 'new') { banner.style.display = 'none'; return; }
  const opt   = document.querySelector('#clientSelect option[value="' + val + '"]');
  const type  = opt ? opt.dataset.type  : '';
  const terms = opt ? opt.dataset.terms : '';
  if (!type) { banner.style.display = 'none'; return; }
  if (type === 'first_time') {
    banner.innerHTML = '<div style="background:#1c1917;border:1px solid #b45309;color:#fbbf24;padding:10px 14px;border-radius:6px;font-size:12px;display:flex;align-items:center;gap:8px"><i data-feather="alert-triangle" style="width:14px;height:14px;flex-shrink:0"></i><div><strong>New Customer</strong> — 50% downpayment required before shoot. Payment terms: ' + (terms === 'full_upfront' ? 'Full Upfront' : '50% Downpayment') + '.</div></div>';
  } else {
    banner.innerHTML = '<div style="background:#052e16;border:1px solid #16a34a;color:#4ade80;padding:10px 14px;border-radius:6px;font-size:12px;display:flex;align-items:center;gap:8px"><i data-feather="check-circle" style="width:14px;height:14px;flex-shrink:0"></i><div><strong>Regular Client</strong> — Pay Later eligible.</div></div>';
  }
  banner.style.display = 'block';
  if (typeof feather !== 'undefined') feather.replace();
}

document.addEventListener('DOMContentLoaded', () => checkDateRange('shoot_start', 'shoot_end', 'add_date_warn'));

function openEditBooking(bk) {
  document.getElementById('edit_bid').value             = bk.booking_id;
  document.getElementById('edit_btype').value           = bk.booking_type;
  document.getElementById('edit_ptype').value           = bk.project_type;
  document.getElementById('edit_ptitle').value          = bk.project_title      || '';
  document.getElementById('edit_bstart').value          = bk.shoot_date_start   || '';
  document.getElementById('edit_bend').value            = bk.shoot_date_end     || '';
  document.getElementById('edit_shoot_location').value   = bk.shoot_location     || '';
  document.getElementById('edit_lat').value             = bk.location_lat       || '';
  document.getElementById('edit_lng').value             = bk.location_lng       || '';
  document.getElementById('edit_zone').value            = bk.location_zone      || '';
  document.getElementById('edit_multiplier').value      = bk.transport_multiplier || 1;
  document.getElementById('edit_transport_cost').value  = bk.transportation_cost || 0;
  document.getElementById('edit_bnotes').value          = bk.notes              || '';
  document.getElementById('editBookingRef').textContent = bk.booking_reference;
  const evr = document.getElementById('edit_vehicle_rate');
  if (evr) evr.value = bk.vehicle_rate_id || '';
  const hasTrans = parseFloat(bk.transportation_cost) > 0 || bk.vehicle_rate_id;
  const editTransCb = document.getElementById('edit_needs_transport');
  if (editTransCb) {
    editTransCb.checked = !!hasTrans;
    document.getElementById('edit_transport_details').style.display = hasTrans ? 'block' : 'none';
  }
  _mapLocked.edit = false;
  const editLockBtn = document.getElementById('edit_lock_btn');
  if (editLockBtn) { editLockBtn.style.background='rgba(0,96,199,.92)'; document.getElementById('edit_lock_icon').innerHTML='<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>'; document.getElementById('edit_lock_text').textContent='Lock Location'; document.getElementById('editMapContainer').style.borderColor='var(--border)'; }
  openModal('modalEditBooking');
  const eLat = parseFloat(bk.location_lat) || 0;
  const eLng = parseFloat(bk.location_lng) || 0;
  setTimeout(() => initEditBookingMap(eLat, eLng, bk.location_zone, bk.transportation_cost), 120);
}

const BASE_TRANSPORT_RATE = {{ $baseTransportRate }};
const ZONE_CONFIG = {
  manila:    { label: 'Metro Manila / NCR (0–60 km)',  multiplier: 1.0, bg: '#eff6ff', border: '#93c5fd', color: '#1d4ed8' },
  luzon:     { label: 'Luzon Near (61–150 km)',         multiplier: 1.5, bg: '#fefce8', border: '#fde047', color: '#ca8a04' },
  luzon_far: { label: 'Luzon Far (151 km+)',            multiplier: 2.0, bg: '#fff7ed', border: '#fed7aa', color: '#c2410c' },
};

function _haversineKm(lat1, lon1, lat2, lon2) {
  const R = 6371, dLat = (lat2-lat1)*Math.PI/180, dLon = (lon2-lon1)*Math.PI/180;
  const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)**2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}
const _MANILA_LAT = 14.5995, _MANILA_LNG = 120.9842;

function _detectZone(lat, lng) {
  const km = _haversineKm(lat, lng, _MANILA_LAT, _MANILA_LNG);
  if (km <= 60)  return 'manila';
  if (km <= 150) return 'luzon';
  return 'luzon_far';
}

function _renderZoneInfo(elId, zone, cost) {
  const el  = document.getElementById(elId);
  const cfg = ZONE_CONFIG[zone];
  if (!cfg) { el.style.display = 'none'; return; }
  const costStr = BASE_TRANSPORT_RATE > 0
    ? '<strong style="color:' + cfg.color + ';margin-left:10px">Transport: ₱' + cost.toLocaleString('en-PH',{minimumFractionDigits:2}) + '</strong>'
    : '<span style="font-size:11px;color:var(--muted);margin-left:8px">Set base rate in Super Admin → System Settings</span>';
  el.innerHTML = '<div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px">'
    + '<span style="background:' + cfg.bg + ';border:1px solid ' + cfg.border + ';color:' + cfg.color + ';padding:3px 12px;border-radius:12px;font-weight:700;font-size:12px">'
    + cfg.label + '</span>'
    + '<span style="font-size:12px;color:var(--muted)">' + cfg.multiplier + '× multiplier</span>'
    + costStr + '</div>';
  el.style.cssText = 'display:block;padding:8px 12px;border-radius:6px;background:' + cfg.bg + ';border:1px solid ' + cfg.border + ';margin-bottom:8px;font-size:12.5px';
}

const _WATER_TYPES = new Set([
  'bay','sea','ocean','water','strait','gulf','fjord','lagoon','river','lake',
  'reservoir','pond','canal','stream','wetland','coastline','harbour','harbor',
  'marina','estuary','tidal','fishing'
]);

function _isWaterLocation(data) {
  const addr     = data.address   || {};
  const cls      = data.class     || '';
  const typ      = data.type      || '';
  const addrType = (data.addresstype || '').toLowerCase();
  const extra    = data.extratags || {};

  if (_WATER_TYPES.has(addrType)) return true;

  const waterAddrKeys = ['sea','ocean','bay','body_of_water','water','strait',
    'gulf','fjord','lagoon','river','lake','reservoir','pond','canal'];
  if (waterAddrKeys.some(k => k in addr)) return true;

  if (cls === 'waterway') return true;
  if ((cls === 'natural' || cls === 'leisure') && _WATER_TYPES.has(typ)) return true;

  if (extra.natural && _WATER_TYPES.has(extra.natural)) return true;
  if (extra.waterway || extra.water) return true;

  const cityKeys = ['city','town','municipality','city_district','suburb','neighbourhood','village','hamlet'];
  if (!cityKeys.some(k => k in addr) && cls === 'natural') return true;

  return false;
}

function _isOutsideLuzon(data) {
  const state = ((data.address || {}).state || '').toLowerCase();
  if (!state) return false;
  const nonLuzon = ['visaya','mindanao','zamboanga','davao','caraga','bangsamoro','soccsksargen'];
  return nonLuzon.some(s => state.includes(s));
}

function _buildPHAddress(data) {
  const a = data.address || {};
  const street   = [a.house_number, a.road || a.pedestrian || a.footway || a.path].filter(Boolean).join(' ');
  const barangay = a.suburb || a.neighbourhood || a.quarter || a.village || a.hamlet || a.city_district || '';
  const cityMun  = a.city || a.town || a.municipality || '';
  const province = a.state_district || a.province || a.county || '';
  const parts = [street, barangay, cityMun, province].filter(Boolean);
  if (parts.length >= 2) return parts.join(', ');
  return (data.display_name || '').split(',').slice(0, 5).join(',').trim();
}

let _addLastValidLatLng = null;
let _editLastValidLatLng = null;

function _invalidatePin(prefix, msg) {
  const zoneEl  = document.getElementById(prefix + 'ZoneInfo');
  const lockBtn = document.getElementById(prefix + '_lock_btn');
  const locInput= document.getElementById(prefix + '_shoot_location');
  zoneEl.style.cssText = 'display:block;padding:8px 12px;border-radius:6px;background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;font-size:12.5px;margin-bottom:8px';
  zoneEl.textContent = msg;
  if (locInput) locInput.value = '';
  if (prefix === 'add') {
    if (_addLastValidLatLng && _addMarker) { _addMarker.setLatLng(_addLastValidLatLng); }
    else if (_addMarker) { _addMarker.remove(); _addMarker = null; if (lockBtn) lockBtn.style.display = 'none'; }
  } else {
    if (_editLastValidLatLng && _editMarker) { _editMarker.setLatLng(_editLastValidLatLng); }
    else if (_editMarker) { _editMarker.remove(); _editMarker = null; if (lockBtn) lockBtn.style.display = 'none'; }
  }
}

function _tileIsWater(lat, lng) {
  const zoom = 14;
  const n    = Math.pow(2, zoom);
  const lr   = lat * Math.PI / 180;
  const tX   = Math.floor((lng + 180) / 360 * n);
  const tY   = Math.floor((1 - Math.log(Math.tan(lr) + 1 / Math.cos(lr)) / Math.PI) / 2 * n);
  const px   = Math.floor(((lng + 180) / 360 * n - tX) * 256);
  const py   = Math.floor(((1 - Math.log(Math.tan(lr) + 1 / Math.cos(lr)) / Math.PI) / 2 * n - tY) * 256);
  return new Promise(resolve => {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => {
      try {
        const c = document.createElement('canvas'); c.width = c.height = 256;
        const ctx = c.getContext('2d'); ctx.drawImage(img, 0, 0);
        const [r, g, b] = ctx.getImageData(px, py, 1, 1).data;
        resolve(b > 190 && g > 195 && b > r + 30 && g > r + 20 && b >= g - 25);
      } catch { resolve(false); }
    };
    img.onerror = () => resolve(false);
    img.src = "{{ route('tile-proxy') }}" + '?z=' + zoom + '&x=' + tX + '&y=' + tY;
  });
}

async function _reverseGeocode(lat, lng, prefix) {
  const zoneEl   = document.getElementById(prefix + 'ZoneInfo');
  const zoneInput= document.getElementById(prefix + '_zone');
  const multInput= document.getElementById(prefix + '_multiplier');
  const costInput= document.getElementById(prefix + '_transport_cost');
  const addrInput= document.getElementById(prefix + '_delivery_address');
  const locInput = document.getElementById(prefix + '_shoot_location');
  zoneEl.style.cssText = 'display:block';
  zoneEl.innerHTML = '<span style="color:var(--muted);font-style:italic">Verifying location…</span>';
  try {
    const [data, tileWater] = await Promise.all([
      fetch(
        'https://nominatim.openstreetmap.org/reverse?lat=' + lat + '&lon=' + lng + '&format=json&addressdetails=1&zoom=18&extratags=1',
        { headers: { 'Accept-Language': 'en', 'User-Agent': 'FilmSpec/1.0' } }
      ).then(r => r.json()),
      _tileIsWater(lat, lng)
    ]);

    if (_isWaterLocation(data) || tileWater) {
      _invalidatePin(prefix, '⚠ Cannot pin on a body of water. Please select a land location.');
      return;
    }
    if (_isOutsideLuzon(data)) {
      _invalidatePin(prefix, '⚠ FilmSpec currently operates within Luzon only. Please pin a location in Luzon.');
      return;
    }

    if (prefix === 'add') _addLastValidLatLng = L.latLng(lat, lng);
    else                   _editLastValidLatLng = L.latLng(lat, lng);
    document.getElementById(prefix + '_lat').value = lat.toFixed(7);
    document.getElementById(prefix + '_lng').value = lng.toFixed(7);

    const km   = Math.round(_haversineKm(lat, lng, _MANILA_LAT, _MANILA_LNG));
    const zone = _detectZone(lat, lng);
    const cfg  = ZONE_CONFIG[zone];
    zoneInput.value = zone;
    multInput.value = cfg.multiplier;
    const vSel = document.getElementById((prefix === 'add' ? 'add' : 'edit') + '_vehicle_rate');
    const vid  = vSel ? vSel.value : '';
    const cost = (vid && VEHICLE_RATES[vid]) ? VEHICLE_RATES[vid].base_rate * cfg.multiplier : BASE_TRANSPORT_RATE * cfg.multiplier;
    costInput.value = cost.toFixed(2);
    addrInput.value = data.display_name || '';
    if (locInput) locInput.value = _buildPHAddress(data);
    _renderZoneInfo(prefix + 'ZoneInfo', zone, cost);
    const zi = document.getElementById(prefix + 'ZoneInfo');
    if (zi) zi.innerHTML += ' <span style="font-size:11px;color:var(--muted)">&nbsp;~' + km + ' km from Manila</span>';
  } catch (e) {
    zoneEl.innerHTML = '<span style="color:var(--red);font-size:12px">Could not detect location — check internet connection.</span>';
  }
}

const PH_BOUNDS = L.latLngBounds([4.3, 116.4], [21.5, 127.2]);
const PH_CENTER = [12.8797, 121.7740];

const _mapLocked = { add: false, edit: false };

function toggleMapLock(prefix) {
  _mapLocked[prefix] = !_mapLocked[prefix];
  const locked   = _mapLocked[prefix];
  const map      = prefix === 'add' ? _addMap : _editMap;
  const marker   = prefix === 'add' ? _addMarker : _editMarker;
  const btn      = document.getElementById(prefix + '_lock_btn');
  const icon     = document.getElementById(prefix + '_lock_icon');
  const text     = document.getElementById(prefix + '_lock_text');
  const container= document.getElementById(prefix + 'MapContainer');

  const SVG_LOCKED   = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
  const SVG_UNLOCKED = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>';
  if (locked) {
    if (map) { map.dragging.disable(); map.scrollWheelZoom.disable(); map.doubleClickZoom.disable(); map.touchZoom.disable(); }
    if (marker) marker.dragging.disable();
    btn.style.background = 'rgba(22,163,74,.92)';
    icon.innerHTML = SVG_LOCKED;
    text.textContent = 'Location Locked — Click to Unlock';
    container.style.borderColor = '#16a34a';
  } else {
    if (map) { map.dragging.enable(); map.scrollWheelZoom.enable(); map.doubleClickZoom.enable(); map.touchZoom.enable(); }
    if (marker) marker.dragging.enable();
    btn.style.background = 'rgba(0,96,199,.92)';
    icon.innerHTML = SVG_UNLOCKED;
    text.textContent = 'Lock Location';
    container.style.borderColor = 'var(--border)';
  }
}

function _showLockBtn(prefix) {
  const btn = document.getElementById(prefix + '_lock_btn');
  if (btn) { btn.style.display = 'flex'; }
}

function toggleTransport(prefix) {
  const cb      = document.getElementById(prefix + '_needs_transport');
  const details = document.getElementById(prefix + '_transport_details');
  const costInp = document.getElementById(prefix + '_transport_cost');
  const vSel    = document.getElementById(prefix + '_vehicle_rate');
  if (cb.checked) {
    details.style.display = 'block';
  } else {
    details.style.display = 'none';
    if (costInp) costInp.value = '0';
    if (vSel)    vSel.value    = '';
  }
}

let _addMap = null, _addMarker = null;

function initAddBookingMap() {
  if (_addMap) { _addMap.invalidateSize(); return; }
  _addMap = L.map('mapAddBooking', {
    maxBounds: PH_BOUNDS,
    maxBoundsViscosity: 1.0,
    minZoom: 6,
  }).setView(PH_CENTER, 6);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(_addMap);
  _addMap.on('click', function(e) {
    if (_mapLocked.add) return;
    if (!PH_BOUNDS.contains(e.latlng)) {
      const z = document.getElementById('addZoneInfo');
      z.style.cssText = 'display:block;padding:8px 12px;border-radius:6px;background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;font-size:12.5px;margin-bottom:8px';
      z.textContent = 'Please pin a location within the Philippines only.';
      return;
    }
    if (_addMarker) _addMarker.setLatLng(e.latlng);
    else {
      _addMarker = L.marker(e.latlng, { draggable: true }).addTo(_addMap);
      _addMarker.on('dragend', () => {
        if (_mapLocked.add) return;
        const p = _addMarker.getLatLng(); _reverseGeocode(p.lat, p.lng, 'add');
      });
    }
    _showLockBtn('add');
    _reverseGeocode(e.latlng.lat, e.latlng.lng, 'add');
  });
}

let _editMap = null, _editMarker = null;

function initEditBookingMap(lat, lng, zone, transCost) {
  const hasPin = lat && lng;
  const center = hasPin ? [lat, lng] : PH_CENTER;
  const zoom   = hasPin ? 13 : 6;
  if (_editMap) {
    _editMap.invalidateSize();
    _editMap.setView(center, zoom);
    if (hasPin) {
      if (_editMarker) _editMarker.setLatLng([lat, lng]);
      else {
        _editMarker = L.marker([lat, lng], { draggable: true }).addTo(_editMap);
        _editMarker.on('dragend', () => { if (_mapLocked.edit) return; const p = _editMarker.getLatLng(); _reverseGeocode(p.lat, p.lng, 'edit'); });
      }
      _showLockBtn('edit');
    }
    if (zone) { const cfg = ZONE_CONFIG[zone]; _renderZoneInfo('editZoneInfo', zone, BASE_TRANSPORT_RATE * cfg.multiplier); }
    return;
  }
  _editMap = L.map('mapEditBooking', {
    maxBounds: PH_BOUNDS,
    maxBoundsViscosity: 1.0,
    minZoom: 6,
  }).setView(center, zoom);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(_editMap);
  if (hasPin) {
    _editMarker = L.marker([lat, lng], { draggable: true }).addTo(_editMap);
    _editMarker.on('dragend', () => { if (_mapLocked.edit) return; const p = _editMarker.getLatLng(); _reverseGeocode(p.lat, p.lng, 'edit'); });
    if (zone) { const cfg = ZONE_CONFIG[zone]; _renderZoneInfo('editZoneInfo', zone, BASE_TRANSPORT_RATE * cfg.multiplier); }
    _showLockBtn('edit');
  }
  _editMap.on('click', function(e) {
    if (_mapLocked.edit) return;
    if (!PH_BOUNDS.contains(e.latlng)) {
      const z = document.getElementById('editZoneInfo');
      z.style.cssText = 'display:block;padding:8px 12px;border-radius:6px;background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;font-size:12.5px;margin-bottom:8px';
      z.textContent = 'Please pin a location within the Philippines only.';
      return;
    }
    if (_editMarker) _editMarker.setLatLng(e.latlng);
    else {
      _editMarker = L.marker(e.latlng, { draggable: true }).addTo(_editMap);
      _editMarker.on('dragend', () => { if (_mapLocked.edit) return; const p = _editMarker.getLatLng(); _reverseGeocode(p.lat, p.lng, 'edit'); });
    }
    _showLockBtn('edit');
    _reverseGeocode(e.latlng.lat, e.latlng.lng, 'edit');
  });
}

document.addEventListener('DOMContentLoaded', () => {
  const addOverlay = document.getElementById('modalAddBooking');
  if (addOverlay) {
    new MutationObserver(mutations => {
      for (const m of mutations) {
        if (m.attributeName === 'class' && addOverlay.classList.contains('active')) {
          setTimeout(initAddBookingMap, 80);
          break;
        }
      }
    }).observe(addOverlay, { attributes: true });
  }
});
</script>
@endpush

<!-- ── EDIT BOOKING MODAL ── -->
<div class="modal-overlay" id="modalEditBooking">
  <div class="modal" style="max-width:640px">
    <div class="modal-header">
      <h3 class="modal-title">
        <i data-feather="edit-2" style="width:16px;height:16px;margin-right:8px;vertical-align:middle"></i>
        Edit Booking <span id="editBookingRef" style="color:var(--accent);font-size:.85rem;margin-left:4px"></span>
      </h3>
      <button class="modal-close"><i data-feather="x"></i></button>
    </div>
    <form method="POST" action="{{ $bookingsBase }}">
      @csrf
      <input type="hidden" name="action" value="edit_booking">
      <input type="hidden" name="booking_id" id="edit_bid">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label>Booking Type *</label>
            <select name="booking_type" id="edit_btype" class="form-control" required>
              <option value="package">Equipment + Crew</option>
            </select>
          </div>
          <div class="form-group">
            <label>Project Type *</label>
            <select name="project_type" id="edit_ptype" class="form-control" required>
              <option value="commercial">Commercial / Advertisement</option>
              <option value="indie_film">Indie Film</option>
              <option value="tv_network">TV Network</option>
              <option value="music_video">Music Video</option>
              <option value="interview">Interview</option>
              <option value="other">Other</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Project Title</label>
          <input type="text" name="project_title" id="edit_ptitle" class="form-control" placeholder="e.g. Jollibee TVC — Summer 2026">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Shoot Start Date *</label>
            <input type="date" name="shoot_date_start" id="edit_bstart" class="form-control" required
                   onchange="checkDateRange('edit_bstart','edit_bend','edit_date_warn')">
          </div>
          <div class="form-group">
            <label>Shoot End Date *</label>
            <input type="date" name="shoot_date_end" id="edit_bend" class="form-control" required
                   onchange="checkDateRange('edit_bstart','edit_bend','edit_date_warn')">
          </div>
        </div>
        <div id="edit_date_warn" style="display:none;background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c;padding:8px 12px;border-radius:6px;font-size:12px;margin-top:-8px;margin-bottom:10px">
          Rental period cannot exceed 3 months (90 days).
        </div>

        <div class="form-group">
          <label>Shoot Location <span style="font-size:.76rem;color:var(--muted);font-weight:400">— click map to update (Philippines only)</span></label>
          <div id="editMapContainer" style="position:relative;border-radius:8px;overflow:hidden;border:2px solid var(--border);margin-bottom:6px">
            <div id="mapEditBooking" style="height:280px"></div>
            <button type="button" id="edit_lock_btn" onclick="toggleMapLock('edit')"
              style="display:none;position:absolute;bottom:12px;left:50%;transform:translateX(-50%);z-index:500;background:rgba(0,96,199,.92);color:#fff;border:none;border-radius:8px;padding:7px 16px;font-size:12px;font-weight:600;cursor:pointer;font-family:inherit;align-items:center;gap:7px;box-shadow:0 2px 12px rgba(0,0,0,.28);backdrop-filter:blur(4px);letter-spacing:.02em;white-space:nowrap">
              <span id="edit_lock_icon" style="display:flex;align-items:center"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg></span><span id="edit_lock_text">Lock Location</span>
            </button>
          </div>
          <div id="editZoneInfo" style="display:none;padding:8px 12px;border-radius:6px;margin-bottom:8px;font-size:12.5px"></div>
          <input type="text" name="shoot_location" id="edit_shoot_location" class="form-control"
                 placeholder="Address auto-fills when you pin — or type manually">
          <input type="hidden" name="delivery_address"     id="edit_bdeliv">
          <input type="hidden" name="location_lat"         id="edit_lat">
          <input type="hidden" name="location_lng"         id="edit_lng">
          <input type="hidden" name="location_zone"        id="edit_zone">
          <input type="hidden" name="transport_multiplier" id="edit_multiplier" value="1">
          <input type="hidden" name="transportation_cost"  id="edit_transport_cost" value="0">
        </div>

        <div style="border:1.5px solid #bfdbfe;border-radius:8px;padding:14px;margin-bottom:12px;background:#f0f7ff">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0060C7" stroke-width="2" style="flex-shrink:0"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 5v3h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            <span style="font-size:12px;font-weight:700;color:#0060C7;letter-spacing:.04em;text-transform:uppercase">Transport Assignment</span>
            <span style="font-size:10px;background:#dbeafe;color:#1d4ed8;border-radius:10px;padding:1px 8px;font-weight:700;letter-spacing:.03em">ADMIN</span>
          </div>
          <label style="display:flex;align-items:center;gap:10px;cursor:pointer;margin-bottom:0">
            <input type="checkbox" id="edit_needs_transport" onchange="toggleTransport('edit')"
                   style="width:16px;height:16px;accent-color:#0060C7;flex-shrink:0">
            <div>
              <div style="font-size:13px;font-weight:700;color:var(--text)">Assign transport for this booking</div>
              <div style="font-size:11.5px;color:var(--muted);margin-top:2px">Review equipment volume and shoot distance, then select the appropriate vehicle. Fee is auto-calculated by zone.</div>
            </div>
          </label>
          <div id="edit_transport_details" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid #bfdbfe">
            <div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:6px;padding:9px 12px;margin-bottom:10px;font-size:12px;color:#1d4ed8;display:flex;gap:8px;align-items:flex-start">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              <span>Vehicle size should match the <strong>total equipment volume</strong> for this booking. Fee = vehicle base rate × zone multiplier. Charged separately on the invoice.</span>
            </div>
            @if ($vehicleRates->count())
            <label style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);font-weight:700;display:block;margin-bottom:4px">Vehicle Type</label>
            <select name="vehicle_rate_id" id="edit_vehicle_rate" class="form-control"
                    onchange="updateTransportCost('edit')">
              <option value="">— Select vehicle —</option>
              @foreach ($vehicleRates as $vr)
              <option value="{{ $vr->vehicle_id }}" data-rate="{{ $vr->base_rate }}">
                {{ $vr->label }} — ₱{{ number_format($vr->base_rate, 0) }}/base
              </option>
              @endforeach
            </select>
            @else
            <input type="hidden" name="vehicle_rate_id" id="edit_vehicle_rate" value="">
            @endif
          </div>
        </div>
        <div class="form-group">
          <label>Notes</label>
          <textarea name="notes" id="edit_bnotes" class="form-control" rows="2" placeholder="Special requirements, call time, etc."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Propose Discount Modal ── -->
<div id="modalProposeDiscount" class="modal-overlay">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <h3><i data-feather="percent" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Propose Discount</h3>
      <button class="modal-close" onclick="closeModal('modalProposeDiscount')">&times;</button>
    </div>
    <form method="POST" action="{{ $bookingsBase }}">
      @csrf
      <input type="hidden" name="action" value="propose_discount">
      <input type="hidden" name="booking_id" id="discountBookingId">
      <div class="modal-body">
        <div style="background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.3);border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:13px;color:#93c5fd">
          Requires approval on the Billing page before it affects the booking total.
        </div>
        <div class="form-group">
          <label>Discount Type</label>
          <div style="display:flex;gap:16px">
            <label style="display:flex;align-items:center;gap:6px;font-weight:400">
              <input type="radio" name="discount_type" value="flat" checked> Flat amount (₱)
            </label>
            <label style="display:flex;align-items:center;gap:6px;font-weight:400">
              <input type="radio" name="discount_type" value="percent"> Percentage (%)
            </label>
          </div>
        </div>
        <div class="form-group">
          <label>Value <span class="req">*</span></label>
          <input type="number" name="discount_value" class="form-control" min="0.01" step="0.01" required placeholder="e.g. 1500.00 or 10">
        </div>
        <div class="form-group">
          <label>Reason</label>
          <textarea name="reason" class="form-control" rows="2" placeholder="Optional — why this discount is being proposed…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalProposeDiscount')">Cancel</button>
        <button type="submit" class="btn btn-primary">
          <i data-feather="send" style="width:13px;height:13px;margin-right:4px;vertical-align:middle"></i>Submit for Approval
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ── Reject Booking Modal ── -->
<div id="modalRejectBooking" class="modal-overlay">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <h3><i data-feather="x-circle" style="width:16px;height:16px;margin-right:6px;vertical-align:middle;color:#ef4444"></i>Reject Booking</h3>
      <button class="modal-close" onclick="closeModal('modalRejectBooking')">&times;</button>
    </div>
    <form method="POST" action="{{ $bookingsBase }}">
      @csrf
      <input type="hidden" name="action" value="reject_booking">
      <input type="hidden" name="booking_id" id="rejectBookingId">
      <div class="modal-body">
        <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:13px;color:#fca5a5">
          The traffic team will see this rejection reason and can resubmit after correction.
        </div>
        <div class="form-group">
          <label>Reason for Rejection <span class="req">*</span></label>
          <textarea name="approval_notes" class="form-control" rows="3" required
                    placeholder="Explain what needs to be corrected before this booking can be approved…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalRejectBooking')">Cancel</button>
        <button type="submit" class="btn btn-danger">Reject Booking</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
function openRejectModal(id) {
  document.getElementById('rejectBookingId').value = id;
  openModal('modalRejectBooking');
}
function openDiscountModal(id) {
  document.getElementById('discountBookingId').value = id;
  openModal('modalProposeDiscount');
}

function closeActionMenus() {
  document.querySelectorAll('.action-menu.show').forEach(function (m) {
    m.classList.remove('show', 'drop-up');
  });
}

function toggleActionMenu(trigger) {
  var menu = trigger.nextElementSibling;
  var wasOpen = menu.classList.contains('show');
  closeActionMenus();
  if (wasOpen) return;

  menu.classList.add('show');
  var rect = menu.getBoundingClientRect();
  if (rect.bottom > window.innerHeight) {
    menu.classList.add('drop-up');
  }
}

document.addEventListener('click', function (e) {
  if (!e.target.closest('.action-menu-wrap')) closeActionMenus();
});
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') closeActionMenus();
});
</script>
@endpush
@endsection
