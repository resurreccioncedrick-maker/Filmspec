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
</style>

<div class="cd-header">
  <div>
    <div class="cd-name">
      {{ $displayName }}
      <span class="badge {{ $typeBadge[$client->client_type] ?? 'badge-gray' }}">{{ $typeLabel[$client->client_type] ?? ucfirst($client->client_type) }}</span>
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
    <a href="{{ route('bookings') }}?client={{ $id }}" class="btn btn-primary btn-sm"><i data-feather="plus" style="width:13px;height:13px"></i> New Booking</a>
    @if ($canManage)
    <a href="{{ route('clients') }}" class="btn btn-outline btn-sm" onclick="return false" style="cursor:default" title="Use Edit on the Clients list"><i data-feather="edit-2" style="width:13px;height:13px"></i> Edit Profile</a>
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
<div class="card">
  <div class="card-header">
    <h2 class="card-title"><i data-feather="file-text" style="width:14px;height:14px"></i> Documents</h2>
    <a href="{{ route('client-documents', $id) }}" class="btn btn-outline btn-sm" target="_blank">Open Full Page <i data-feather="external-link" style="width:12px;height:12px"></i></a>
  </div>
  <div class="card-body" style="padding:0">
    <iframe src="{{ route('client-documents', $id) }}" style="width:100%;height:600px;border:none;display:block"></iframe>
  </div>
</div>
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

@endsection
