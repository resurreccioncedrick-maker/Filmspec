@extends('layouts.app')

@section('pageTitle', 'Client Detail')

@section('breadcrumb')
<a href="{{ route('clients') }}">Clients</a>
<span>{{ $client->company_name ?: $client->contact_person }}</span>
@endsection

@section('content')
@php
  $displayName = $client->company_name ?: $client->contact_person;
  $et = $client->entity_type ?? 'individual';
  $activeTab = request('tab', 'overview');
  $tabs = ['overview' => 'Overview', 'bookings' => 'Bookings', 'billing' => 'Billing', 'documents' => 'Documents', 'activity' => 'Activity'];
  $isActive = (bool) ($client->is_active ?? true);
  $completedCount = (int) ($bookingStats->completed ?? 0);
  $eligibleForRegular = $client->client_type === 'first_time' && $completedCount >= 4;
@endphp

<style>
.cd-header { background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:20px 24px;margin-bottom:18px;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;box-shadow:var(--shadow-sm); }
.cd-name { font-family:var(--font-display);font-size:22px;color:var(--text);display:flex;align-items:center;gap:10px;flex-wrap:wrap; }
.cd-meta { font-size:12.5px;color:var(--muted);margin-top:6px;display:flex;align-items:center;gap:8px;flex-wrap:wrap; }
.cd-tabs { display:flex;gap:2px;border-bottom:1px solid var(--border);margin-bottom:18px; }
.cd-tab { padding:10px 16px;font-size:13px;font-weight:600;color:var(--muted);text-decoration:none;border-bottom:2px solid transparent; }
.cd-tab.active { color:var(--accent);border-bottom-color:var(--accent); }
.cd-info-grid { display:grid;grid-template-columns:1fr 1fr;gap:16px; }
.cd-field { padding:10px 0;border-bottom:1px solid var(--border); }
.cd-field .lbl { font-size:10.5px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;font-weight:700; }
.cd-field .val { font-size:13.5px;color:var(--text);margin-top:3px;font-weight:600; }
.cd-more-wrap { position:relative;display:inline-flex; }
.cd-more-menu { display:none;position:absolute;top:calc(100% + 4px);right:0;min-width:200px;background:var(--surface);border:1px solid var(--border);border-radius:8px;box-shadow:var(--shadow-md);z-index:20;overflow:hidden; }
.cd-more-menu.open { display:block; }
.cd-more-menu button, .cd-more-menu a { display:flex;align-items:center;gap:8px;width:100%;padding:9px 14px;font-size:12.5px;font-weight:600;color:var(--text);background:none;border:none;text-align:left;cursor:pointer;text-decoration:none; }
.cd-more-menu button:hover, .cd-more-menu a:hover { background:var(--s2); }
.cd-more-menu .text-danger { color:var(--red); }
.cd-more-menu .text-danger:hover { background:var(--redl); }
</style>

@if (! $isActive)
<div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:12px 16px;border-radius:8px;margin-bottom:14px;display:flex;align-items:center;gap:10px;font-size:13px">
  <i data-feather="alert-triangle" style="width:16px;height:16px;flex-shrink:0"></i>
  <div><strong>This client is deactivated</strong> — they cannot be booked for new requests until reactivated.
  @if ($client->deactivation_reason) Reason: {{ $client->deactivation_reason }}@endif</div>
</div>
@endif

<div class="cd-header">
  <div>
    <div class="cd-name">
      {{ $displayName }}
      <span class="badge {{ $typeBadge[$client->client_type] ?? 'badge-gray' }}">{{ $typeLabel[$client->client_type] ?? ucfirst($client->client_type) }}</span>
      <span class="badge {{ $isActive ? 'badge-green' : 'badge-red' }}">{{ $isActive ? 'Active' : 'Inactive' }}</span>
      @if (($client->status ?? 'approved') === 'pending')<span class="badge badge-yellow">Pending Approval</span>@endif
      @if (($client->status ?? 'approved') === 'rejected')<span class="badge badge-red">Rejected</span>@endif
      @if ($client->is_vat_registered)<span class="badge badge-purple">VAT Registered</span>@endif
    </div>
    <div class="cd-meta">
      {{ $entityTypeLabel[$et] ?? ucfirst($et) }}
      @if ($client->company_name && $client->contact_person)· Contact: {{ $client->contact_person }}@endif
      · Portal Account:
      @if ($portalAccount)
        <span style="color:var(--green);font-weight:600">{{ $portalAccount->is_active ? 'Verified' : 'Inactive' }}</span>
      @else
        <span style="color:var(--muted)">Not Registered</span>
      @endif
    </div>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    @if ($isActive)
    <a href="{{ route('bookings') }}?client={{ $id }}" class="btn btn-primary btn-sm"><i data-feather="plus" style="width:13px;height:13px"></i> New Client Request</a>
    @endif
    @if ($canManage)
    <button type="button" class="btn btn-outline btn-sm" onclick="openModal('modalEditProfile')"><i data-feather="edit-2" style="width:13px;height:13px"></i> Edit Profile</button>
    @if ($eligibleForRegular)
    {{-- Two actions available (Mark as Regular + Deactivate) — worth a dropdown. --}}
    <div class="cd-more-wrap">
      <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('cdMoreMenu').classList.toggle('open')"><i data-feather="more-horizontal" style="width:13px;height:13px"></i> More</button>
      <div class="cd-more-menu" id="cdMoreMenu">
        <form method="POST" action="{{ route('client-detail.action', $id) }}">
          @csrf
          <input type="hidden" name="action" value="mark_regular_client">
          <button type="submit" onclick="return confirm('Mark this client as a Regular Client?')"><i data-feather="star" style="width:13px;height:13px"></i> Mark as Regular Client</button>
        </form>
        <button type="button" onclick="document.getElementById('cdMoreMenu').classList.remove('open');openModal('modalDeactivateClient')" class="text-danger"><i data-feather="user-x" style="width:13px;height:13px"></i> Deactivate Client</button>
      </div>
    </div>
    @elseif ($isActive)
    {{-- Only one action available — show it directly, no point wrapping a single item in a menu. --}}
    <button type="button" class="btn btn-outline btn-sm" style="color:var(--red);border-color:var(--red)" onclick="openModal('modalDeactivateClient')"><i data-feather="user-x" style="width:13px;height:13px"></i> Deactivate Client</button>
    @else
    <form method="POST" action="{{ route('client-detail.action', $id) }}">
      @csrf
      <input type="hidden" name="action" value="reactivate_client">
      <button type="submit" class="btn btn-outline btn-sm" onclick="return confirm('Reactivate this client?')"><i data-feather="user-check" style="width:13px;height:13px"></i> Reactivate Client</button>
    </form>
    @endif
    @endif
  </div>
</div>

<div class="cd-tabs">
  @foreach ($tabs as $k => $l)
  <a href="{{ route('client-detail', $id) }}?tab={{ $k }}" class="cd-tab {{ $activeTab === $k ? 'active' : '' }}">{{ $l }}</a>
  @endforeach
</div>

@if (session('cd_flash'))
@php $flash = session('cd_flash'); @endphp
<div class="alert alert-{{ $flash['type'] }}" data-autohide>
  <i data-feather="{{ $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' }}"></i>
  {{ $flash['text'] }}
</div>
@endif

@if ($activeTab === 'overview')
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
  <div class="card" style="margin-bottom:0">
    <div class="card-header"><h2 class="card-title"><i data-feather="user" style="width:14px;height:14px"></i> Contact Information</h2></div>
    <div class="card-body">
      <div class="cd-field"><div class="lbl">Email</div><div class="val">{{ $client->email ?: '—' }}</div></div>
      <div class="cd-field"><div class="lbl">Phone</div><div class="val">{{ $client->phone ?: '—' }}</div></div>
      <div class="cd-field" style="border-bottom:none"><div class="lbl">Address</div><div class="val">{{ $client->address ?: '—' }}</div></div>
    </div>
  </div>
  <div class="card" style="margin-bottom:0">
    <div class="card-header"><h2 class="card-title"><i data-feather="briefcase" style="width:14px;height:14px"></i> Client Classification</h2></div>
    <div class="card-body">
      <div class="cd-field"><div class="lbl">Entity Type</div><div class="val">{{ $entityTypeLabel[$et] ?? ucfirst($et) }}</div></div>
      <div class="cd-field"><div class="lbl">Client Status</div><div class="val">{{ $typeLabel[$client->client_type] ?? ucfirst($client->client_type) }}</div></div>
      <div class="cd-field" style="border-bottom:none">
        <div class="lbl">Billing Profile</div>
        @if ($canSeeBilling)
        <div class="val">{{ $termLabel[$client->payment_terms] ?? '—' }} · {{ (float) $client->discount_pct }}% loyalty discount</div>
        @else
        <div class="val" style="color:var(--muted);font-weight:400">Restricted — see Billing tab</div>
        @endif
      </div>
    </div>
  </div>
  <div class="card" style="margin-bottom:0;grid-column:1/-1">
    <div class="card-header"><h2 class="card-title"><i data-feather="bar-chart-2" style="width:14px;height:14px"></i> Operational Summary</h2></div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr)">
      <div class="cd-field" style="padding:16px 16px;border-right:1px solid var(--border);border-bottom:none"><div class="lbl">Total Bookings</div><div class="val" style="font-size:20px">{{ (int) $bookingStats->total }}</div></div>
      <div class="cd-field" style="padding:16px 16px;border-right:1px solid var(--border);border-bottom:none"><div class="lbl">Completed</div><div class="val" style="font-size:20px;color:var(--green)">{{ (int) $bookingStats->completed }}</div></div>
      <div class="cd-field" style="padding:16px 16px;border-right:1px solid var(--border);border-bottom:none"><div class="lbl">Active</div><div class="val" style="font-size:20px;color:var(--accent)">{{ (int) $bookingStats->active }}</div></div>
      <div class="cd-field" style="padding:16px 16px;border-bottom:none"><div class="lbl">Last Booking</div><div class="val" style="font-size:14px">{{ $bookingStats->last_booking_date ? \Carbon\Carbon::parse($bookingStats->last_booking_date)->format('M j, Y') : '—' }}</div></div>
    </div>
  </div>
</div>
@endif

@if ($activeTab === 'bookings')
<div class="card">
  <div class="card-header"><h2 class="card-title">Bookings <span class="badge badge-gray" style="margin-left:4px">{{ $bookings->count() }}</span></h2></div>
  @if ($bookings->isEmpty())
  <div class="empty-state" style="padding:32px 0"><i data-feather="calendar"></i><h3>No bookings yet</h3></div>
  @else
  <div class="table-wrap">
    <table>
      <thead><tr><th>Ref</th><th>Project</th><th>Shoot Dates</th><th>Status</th><th>Payment</th><th style="text-align:right">Amount</th></tr></thead>
      <tbody>
        @foreach ($bookings as $b)
        <tr style="cursor:pointer" onclick="window.location='{{ route('booking-detail', $b->booking_id) }}'">
          <td style="font-family:var(--font-mono);font-size:11px;font-weight:700;color:var(--accent)">{{ $b->booking_reference }}</td>
          <td>{{ $b->project_title ?: '—' }}</td>
          <td style="font-size:11.5px;white-space:nowrap">{{ \Carbon\Carbon::parse($b->shoot_date_start)->format('M j, Y') }}</td>
          <td><span class="badge {{ $statusBadge[$b->booking_status] ?? '' }}">{{ ucfirst(str_replace('_', ' ', $b->booking_status)) }}</span></td>
          <td><span class="badge badge-gray">{{ ucfirst($b->payment_status ?? '—') }}</span></td>
          <td style="text-align:right;font-weight:700">{{ $canSeeBilling && $b->final_amount > 0 ? '₱' . number_format($b->final_amount, 2) : '—' }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif
</div>
@endif

@if ($activeTab === 'billing')
<div class="card">
  <div class="card-header"><h2 class="card-title"><i data-feather="credit-card" style="width:14px;height:14px"></i> Billing Profile</h2></div>
  @if (! $canSeeBilling)
  <div class="empty-state" style="padding:32px 0"><i data-feather="lock"></i><h3>Restricted</h3><p>You don't have billing access to view or edit this client's billing profile.</p></div>
  @else
  <div class="card-body">
    <div class="stats-grid" style="grid-template-columns:repeat(2,1fr);margin-bottom:20px">
      <div class="stat-card green"><div class="stat-value">₱{{ number_format($billing['total_paid'], 2) }}</div><div class="stat-label">Total Paid</div></div>
      <div class="stat-card orange"><div class="stat-value">₱{{ number_format($billing['outstanding'], 2) }}</div><div class="stat-label">Outstanding</div></div>
    </div>
    <form method="POST" action="{{ route('client-detail.billing', $id) }}" style="max-width:420px">
      @csrf
      <div class="form-group">
        <label>Payment Terms</label>
        <select name="payment_terms" class="form-control">
          @foreach ($termLabel as $tk => $tl)
          <option value="{{ $tk }}" {{ $client->payment_terms === $tk ? 'selected' : '' }}>{{ $tl }}</option>
          @endforeach
        </select>
      </div>
      <div class="form-group">
        <label>Loyalty Discount (%) <span style="font-size:.72rem;font-weight:400;color:var(--text-muted)">— applied automatically on CE</span></label>
        <input type="number" name="discount_pct" class="form-control" min="0" max="100" step="0.5" value="{{ (float) $client->discount_pct }}">
      </div>
      <button type="submit" class="btn btn-primary btn-sm"><i data-feather="save" style="width:13px;height:13px"></i> Save Billing Profile</button>
    </form>
  </div>
  @endif
</div>
@endif

@if ($activeTab === 'documents')
@include('partials.documents-card')
@endif

@if ($activeTab === 'activity')
<div class="card">
  <div class="card-header"><h2 class="card-title"><i data-feather="activity" style="width:14px;height:14px"></i> Activity History</h2></div>
  @if ($activity->isEmpty())
  <div class="empty-state" style="padding:32px 0"><i data-feather="activity"></i><h3>No activity recorded for this client</h3></div>
  @else
  <div class="table-wrap">
    <table>
      <thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th></tr></thead>
      <tbody>
        @foreach ($activity as $a)
        <tr>
          <td style="font-size:11.5px;color:var(--muted);white-space:nowrap">{{ \App\Support\Dates::relTime($a->created_at) }}</td>
          <td style="font-weight:600">{{ $a->user_name ?: 'System' }}</td>
          <td><span class="badge badge-blue" style="font-size:10px">{{ str_replace('_', ' ', $a->action) }}</span></td>
          <td style="font-size:12.5px;color:var(--sub)">{{ $a->description }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif
</div>
@endif

@if ($canManage)
<!-- Edit Profile Modal -->
<div id="modalEditProfile" class="modal-overlay">
  <div class="modal" style="max-width:600px">
    <div class="modal-header">
      <h3><i data-feather="edit-2" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Edit Profile</h3>
      <button class="modal-close" onclick="closeModal('modalEditProfile')">&times;</button>
    </div>
    <form method="POST" action="{{ route('client-detail.profile', $id) }}">
      @csrf
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label>Company Name</label>
            <input type="text" name="company_name" class="form-control" value="{{ $client->company_name }}">
          </div>
          <div class="form-group">
            <label>Contact Person <span class="req">*</span></label>
            <input type="text" name="contact_person" class="form-control" value="{{ $client->contact_person }}" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control" value="{{ $client->email }}">
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control" value="{{ $client->phone }}">
          </div>
        </div>
        <div class="form-group">
          <label>Address</label>
          <input type="text" name="address" class="form-control" value="{{ $client->address }}">
        </div>
        <div class="form-group">
          <label>Entity Type</label>
          <select name="entity_type" class="form-control">
            @foreach ($entityTypeLabel as $k => $l)
            <option value="{{ $k }}" {{ $et === $k ? 'selected' : '' }}>{{ $l }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group">
          <label>Notes</label>
          <textarea name="notes" class="form-control" rows="2">{{ $client->notes }}</textarea>
        </div>
        <div style="font-size:.75rem;color:var(--text-muted);background:var(--s2);border-radius:6px;padding:8px 10px">
          Regular Client status, Payment Terms, and Loyalty Discount aren't edited here — see the header's More menu and the Billing tab.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditProfile')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="save" style="width:14px;height:14px;margin-right:4px;vertical-align:middle"></i>Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Deactivate Client Modal -->
<div id="modalDeactivateClient" class="modal-overlay">
  <div class="modal" style="max-width:460px">
    <div class="modal-header">
      <h3><i data-feather="user-x" style="width:16px;height:16px;margin-right:6px;vertical-align:middle;color:var(--red)"></i>Deactivate Client</h3>
      <button class="modal-close" onclick="closeModal('modalDeactivateClient')">&times;</button>
    </div>
    <form method="POST" action="{{ route('client-detail.action', $id) }}">
      @csrf
      <input type="hidden" name="action" value="deactivate_client">
      <div class="modal-body">
        <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:12px">
          <strong>{{ $displayName }}</strong> will no longer be able to make new bookings, but all historical data
          (bookings, invoices, payments) will be preserved. This can be reversed at any time.
        </p>
        <div class="form-group">
          <label>Reason for deactivation <span class="req">*</span></label>
          <textarea name="reason" class="form-control" rows="3" required placeholder="e.g. No longer active, payment dispute, requested by client…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalDeactivateClient')">Cancel</button>
        <button type="submit" class="btn btn-danger"><i data-feather="user-x" style="width:14px;height:14px;margin-right:4px;vertical-align:middle"></i>Deactivate Client</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
document.addEventListener('click', function (e) {
  const wrap = document.querySelector('.cd-more-wrap');
  const menu = document.getElementById('cdMoreMenu');
  if (wrap && menu && !wrap.contains(e.target)) menu.classList.remove('open');
});
</script>
@endpush
@endif

@endsection
