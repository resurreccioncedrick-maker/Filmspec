@extends('layouts.app')

@section('pageTitle', 'Booking Detail')

@section('breadcrumb')
<a href="{{ route('bookings') }}">Bookings</a><span class="bc-sep">/</span><span>{{ $booking->booking_reference }}</span>
@endsection

@section('content')
@php
  $actionUrl = route('booking-detail.act', $id);
@endphp
<style>
.srch-list{border:1px solid var(--border);border-radius:6px;background:var(--surface);max-height:200px;overflow-y:auto;margin-top:5px}
.srch-item{padding:9px 12px;cursor:pointer;border-bottom:1px solid var(--border);transition:background .1s}
.srch-item:last-child{border-bottom:none}
.srch-item:hover,.srch-item.sel{background:rgba(59,130,246,.08)}
.srch-item .si-name{font-weight:600;font-size:13px;color:var(--text)}
.srch-item .si-sub{font-size:11px;color:var(--muted);margin-top:1px}
.srch-empty{padding:10px 13px;font-size:.8rem;color:var(--muted);text-align:center}
</style>

@if ($msg)
<div class="alert alert-{{ $msg['type'] }}" data-autohide>
  <i data-feather="{{ $msg['type'] === 'success' ? 'check-circle' : 'alert-circle' }}"></i>
  {!! $msg['text'] !!}
</div>
@endif

<!-- Booking Header Card -->
<div class="card" style="margin-bottom:22px;overflow:visible;position:relative;z-index:2">
  <div class="card-body">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px">
      <div>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
          <h2 style="font-family:var(--font-display);font-size:1.5rem;font-weight:800;color:var(--blue-900);letter-spacing:-.02em">
            {{ $booking->booking_reference }}
          </h2>
          @php $statusLabel2 = ['pending' => 'Awaiting Review', 'confirmed' => 'Confirmed', 'ongoing' => 'In Field', 'pending_inspection' => 'Inspection', 'returned' => 'Returned', 'completed' => 'Completed', 'cancelled' => 'Cancelled']; @endphp
          <span class="badge {{ $statusBadge[$booking->booking_status] ?? 'badge-gray' }}" style="font-size:.8rem">
            {{ $statusLabel2[$booking->booking_status] ?? ucfirst($booking->booking_status) }}
          </span>
          <span class="badge {{ $payBadge[$booking->payment_status] ?? 'badge-gray' }}" style="font-size:.8rem">
            {{ $payLabel[$booking->payment_status] ?? ucfirst($booking->payment_status) }}
          </span>
          @if ($booking->client_type === 'first_time')
          <span class="badge badge-orange" style="font-size:.75rem">New Customer</span>
          @endif
          @if ($isAdmin)
          <button onclick="openModal('modalDuplicateBooking')" class="btn btn-outline btn-sm" style="margin-left:auto" title="Create a new booking pre-filled from this one">
            <i data-feather="copy" style="width:13px;height:13px"></i> Duplicate Booking
          </button>
          @endif
        </div>
        <div style="display:flex;gap:24px;flex-wrap:wrap;font-size:.875rem;color:var(--text-secondary)">
          <span><i data-feather="user" style="width:13px;height:13px;margin-right:4px;vertical-align:middle"></i>
            <strong>{{ $booking->company_name ?: $booking->contact_person }}</strong>
            @if ($booking->company_name)({{ $booking->contact_person }})@endif
            @if ($booking->client_type === 'regular')
            <span class="badge badge-green" style="font-size:.65rem;margin-left:4px">Regular</span>
            @endif
          </span>
          <span><i data-feather="mail" style="width:13px;height:13px;margin-right:4px;vertical-align:middle"></i>
            {{ $booking->client_email ?? '—' }}
          </span>
          <span><i data-feather="phone" style="width:13px;height:13px;margin-right:4px;vertical-align:middle"></i>
            {{ $booking->client_phone ?? '—' }}
          </span>
        </div>
      </div>
      <div style="text-align:right">
        @if ($booking->final_amount > 0)
        <div style="font-family:var(--font-display);font-size:1.8rem;font-weight:800;color:var(--blue-700)">
          ₱{{ number_format($booking->final_amount, 2) }}
        </div>
        <div style="font-size:.78rem;color:var(--text-muted)">Total Amount (inc. VAT)</div>
        @endif
      </div>
    </div>

    <!-- Meta row -->
    <div style="display:flex;gap:20px;flex-wrap:wrap;margin-top:16px;padding-top:14px;border-top:1px solid var(--border);font-size:.83rem">
      <div>
        <div style="color:var(--text-muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px">Project</div>
        <div style="font-weight:600">{{ $booking->project_title ?: '—' }}</div>
      </div>
      <div>
        <div style="color:var(--text-muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px">Type</div>
        <div><span class="badge badge-blue">{{ ucwords(str_replace('_', ' ', $booking->project_type)) }}</span></div>
      </div>
      <div>
        <div style="color:var(--text-muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px">Shoot Dates</div>
        <div style="font-weight:600">
          {{ \Carbon\Carbon::parse($booking->shoot_date_start)->format('M j') }} –
          {{ \Carbon\Carbon::parse($booking->shoot_date_end)->format('M j, Y') }}
        </div>
      </div>
      <div>
        <div style="color:var(--text-muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px">Location</div>
        <div>{{ $booking->shoot_location ?: '—' }}</div>
      </div>
      @if (! empty($booking->delivery_address))
      <div>
        <div style="color:var(--text-muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px">Delivery Address</div>
        <div>{{ $booking->delivery_address }}</div>
      </div>
      @endif
      @if (! empty($booking->transportation_cost) && $booking->transportation_cost > 0)
      @php $zoneLabels = ['manila' => 'Metro Manila / NCR (0–60 km)', 'luzon' => 'Luzon Near (61–150 km)', 'luzon_far' => 'Luzon Far (151 km+)']; @endphp
      <div>
        <div style="color:var(--text-muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px">Transportation</div>
        <div style="font-weight:600;color:var(--accent)">₱{{ number_format($booking->transportation_cost, 2) }}</div>
        @if (! empty($booking->vehicle_label))
        <div style="font-size:.75rem;color:var(--text);font-weight:600;margin-top:2px">{{ $booking->vehicle_label }}</div>
        @endif
        @if (! empty($booking->location_zone))
        <div style="font-size:.7rem;color:var(--muted)">{{ $zoneLabels[$booking->location_zone] ?? $booking->location_zone }} &nbsp;·&nbsp; {{ $booking->transport_multiplier }}×</div>
        @endif
      </div>
      @endif
      <div>
        <div style="color:var(--text-muted);font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;margin-bottom:2px">Created By</div>
        <div>{{ $booking->created_by_name ?? '—' }}</div>
      </div>
    </div>

    <!-- Booking Status Pipeline -->
    @php
      // Kept in ongoing -> pending_inspection -> returned order (not the Returned-before-
      // Inspection order the panelist's notes suggested): confirmInspection() in
      // BookingDetailController only flips status to 'returned' AFTER inspection is confirmed
      // (see bulkCheckin()/confirmInspection() — 'returned' means "inspection passed, accepted
      // back," not "physically received"). Reordering the display without changing that real
      // state machine would make the pipeline show "Returned" as done before it actually is.
      $pipeline = ['pending' => 0, 'confirmed' => 1, 'ongoing' => 2, 'pending_inspection' => 3, 'returned' => 4, 'completed' => 5];
      $currentStep = $pipeline[$booking->booking_status] ?? ($booking->booking_status === 'cancelled' ? -1 : 0);
      $isCancelled = $booking->booking_status === 'cancelled';
      // Payment status has its own dedicated summary card further down the page (Total /
      // Paid / Balance / Status) — it's deliberately not a step here, since "paid" isn't a
      // production-pipeline stage and mixing it in understated the real payment state.
      $steps = [
        ['key' => 'pending', 'label' => 'Submitted', 'icon' => 'file-text'],
        ['key' => 'confirmed', 'label' => 'Confirmed', 'icon' => 'check-circle'],
        ['key' => 'ongoing', 'label' => 'In Field', 'icon' => 'truck'],
        ['key' => 'pending_inspection', 'label' => 'Inspection', 'icon' => 'search'],
        ['key' => 'returned', 'label' => 'Returned', 'icon' => 'package'],
        ['key' => 'completed', 'label' => 'Completed', 'icon' => 'check-square'],
      ];
    @endphp
    <div style="margin-top:18px;padding-top:16px;border-top:1px solid var(--border)">
      @if ($isCancelled)
      <div style="display:flex;align-items:center;gap:8px;padding:10px 14px;background:#fef2f2;border:1px solid #fecaca;border-radius:7px;font-size:13px;color:#991b1b">
        <i data-feather="x-circle" style="width:15px;height:15px;flex-shrink:0"></i>
        <div><strong>Booking Cancelled</strong>@if ($booking->cancellation_reason) — {{ $booking->cancellation_reason }}@endif</div>
      </div>
      @else
      <div style="display:flex;align-items:center;gap:0">
        @foreach ($steps as $i => $step)
        @php
          $isDone = ($pipeline[$step['key']] ?? 99) <= $currentStep;
          $isCurrent = ($pipeline[$step['key']] ?? 99) === $currentStep;
          $dotBg = $isDone ? '#16a34a' : ($isCurrent ? '#1d4ed8' : '#e2e8f0');
          $dotColor = ($isDone || $isCurrent) ? '#fff' : '#94a3b8';
          $labelColor = $isDone ? '#16a34a' : ($isCurrent ? '#1d4ed8' : '#94a3b8');
        @endphp
        @if ($i > 0)
        <div style="flex:1;height:2px;background:{{ $isDone ? '#16a34a' : '#e2e8f0' }};min-width:12px"></div>
        @endif
        <div style="display:flex;flex-direction:column;align-items:center;gap:5px;flex-shrink:0">
          <div style="width:30px;height:30px;border-radius:50%;background:{{ $dotBg }};display:flex;align-items:center;justify-content:center;transition:all .2s">
            <i data-feather="{{ $step['icon'] }}" style="width:13px;height:13px;color:{{ $dotColor }};stroke-width:2.5"></i>
          </div>
          <div style="font-size:10px;font-weight:{{ $isCurrent ? '700' : '500' }};color:{{ $labelColor }};white-space:nowrap;letter-spacing:.02em">{{ $step['label'] }}</div>
        </div>
        @endforeach
      </div>
      @endif
    </div>

    <!-- Downpayment notice -->
    @php
      $_dpPaid = $payments->sum('amount');
      $_dpRequired = (float) $booking->final_amount * 0.5;
    @endphp
    @if ($booking->client_type === 'first_time' && in_array($booking->booking_status, ['pending', 'confirmed']) && $_dpPaid < $_dpRequired)
    <div style="background:#1c1917;border:1px solid #b45309;color:#fbbf24;padding:10px 14px;border-radius:6px;font-size:12px;display:flex;align-items:center;gap:10px;margin-top:14px">
      <i data-feather="alert-triangle" style="width:15px;height:15px;flex-shrink:0"></i>
      <div>
        <strong>New Customer — 50% Downpayment Required</strong>
        @if ($booking->final_amount > 0)
        &nbsp;·&nbsp; Required: <strong>₱{{ number_format($booking->final_amount * 0.5, 2) }}</strong>
        @endif
        before equipment can be released.
      </div>
    </div>
    @endif

    @php
      $st = $booking->booking_status;
      $costApproval = $booking->cost_approval_status ?? null;
      $hasCrew = $crewLines->count() > 0;
      $hasTransport = (float) ($booking->transportation_cost ?? 0) > 0;
      $ceConfirmed = $ce && $ce->status === 'confirmed';
      $costBlocked = ($st === 'confirmed' && ! $ceConfirmed);
    @endphp

    @if ($costBlocked && $isAdmin)
    <div style="background:#18120a;border:1.5px solid #b91c1c;color:#fca5a5;padding:11px 15px;border-radius:7px;font-size:12.5px;display:flex;align-items:center;gap:10px;margin-top:12px">
      <i data-feather="lock" style="width:15px;height:15px;flex-shrink:0;color:#f87171"></i>
      <div>{!! $ce ? '<strong>Cost estimate not confirmed.</strong> Confirm the CE above before releasing any equipment.' : '<strong>No cost estimate yet.</strong> Generate and confirm a cost estimate before releasing any equipment.' !!}</div>
    </div>
    @endif

    <!-- Stage-aware action bar -->
    <div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap;align-items:center">

      @if (($st === 'pending' || ($booking->approval_status ?? '') === 'pending_approval') && $isAdmin)
        <button onclick="openModal('modalApproveBooking')" class="btn btn-success btn-sm"><i data-feather="check-circle"></i> Approve</button>
        <button onclick="openModal('modalRejectBooking')" class="btn btn-danger btn-sm"><i data-feather="x-circle"></i> Reject</button>
        <form method="POST" action="{{ $actionUrl }}" style="display:inline">
          @csrf
          <input type="hidden" name="action" value="generate_ce">
          <button type="submit" class="btn btn-outline btn-sm"><i data-feather="file-text"></i> {{ $ce ? 'Regenerate CE' : 'Generate CE' }}</button>
        </form>
        @if ($ce)
        <a href="{{ route('ce-preview', ['booking_id' => $id]) }}" class="btn btn-outline btn-sm"><i data-feather="eye"></i> View CE</a>
        @if ($ce->status === 'confirmed')
        <span class="badge badge-green" style="align-self:center"><i data-feather="check" style="width:11px;height:11px"></i> Confirmed</span>
        @elseif (in_array($role, ['super_admin', 'admin', 'operations_manager'], true))
        <form method="POST" action="{{ $actionUrl }}" style="display:inline">
          @csrf
          <input type="hidden" name="action" value="confirm_ce">
          <button type="submit" class="btn btn-success btn-sm"><i data-feather="check"></i> Confirm CE</button>
        </form>
        @endif
        @endif
        @if ($pendingCancellation)
        <span class="btn btn-outline btn-sm" style="border-color:#d97706;color:#d97706;margin-left:auto;cursor:default"><i data-feather="clock"></i> Cancellation Pending</span>
        @else
        <button onclick="openModal('modalRequestCancel')" class="btn btn-outline btn-sm" style="border-color:var(--red);color:var(--red);margin-left:auto"><i data-feather="x-circle"></i> Cancel</button>
        @endif

      @elseif ($st === 'confirmed' && $isAdmin)
        {{-- Assign Transport now lives only on the Cost Estimate tab (delegates back to this
             page's modalAssignTransport via postMessage). --}}
        @if ($ce)
        @if ($ce->status === 'confirmed')
        <span class="badge badge-green" style="align-self:center"><i data-feather="check" style="width:11px;height:11px"></i> Confirmed</span>
        @elseif (in_array($role, ['super_admin', 'admin', 'operations_manager'], true))
        <form method="POST" action="{{ $actionUrl }}" style="display:inline">
          @csrf
          <input type="hidden" name="action" value="confirm_ce">
          <button type="submit" class="btn btn-success btn-sm"><i data-feather="check"></i> Confirm CE</button>
        </form>
        @endif
        @endif
        <button onclick="openModal('modalRecordPayment')" class="btn btn-outline btn-sm" style="border-color:var(--success);color:var(--success)"><i data-feather="credit-card"></i> Record Payment</button>

        <div class="action-menu-wrap">
          <button type="button" class="btn-icon" onclick="toggleActionMenu(this)" title="More actions"><i data-feather="more-vertical"></i></button>
          <div class="action-menu">
            {{-- Add Equipment / Assign Crew now live only on the Cost Estimate tab (which
                 delegates back to this page's own modals via postMessage — see the
                 window.addEventListener('message', ...) listener below). --}}
            @if ($hasCrew && $costApproval !== 'client_approved')
            @if ($ce && $ce->status === 'confirmed')
            <form method="POST" action="{{ $actionUrl }}"
                  onsubmit="return confirm('Email this cost estimate to the client and mark it pending their approval?')">
              @csrf
              <input type="hidden" name="action" value="email_ce_to_client">
              <button type="submit"><i data-feather="send"></i> {{ $costApproval === 'pending_client' ? 'Resend Cost to Client' : 'Send Cost to Client' }}</button>
            </form>
            @else
            <button type="button" disabled title="Confirm the cost estimate above before sending it to the client"><i data-feather="send"></i> Send Cost to Client</button>
            @endif
            @endif
            @if ($ce)
            <a href="{{ route('ce-preview', ['booking_id' => $id]) }}"><i data-feather="file-text"></i> View Cost Estimate</a>
            @endif
            <div class="action-menu-divider"></div>
            @if ($ce && $ce->status === 'confirmed')
            <a href="{{ route('checklist', ['booking_id' => $id, 'dir' => 'out']) }}"><i data-feather="clipboard"></i> Checklist OUT</a>
            @else
            <button type="button" disabled title="Confirm the cost estimate before equipment can be released"><i data-feather="lock"></i> Checklist OUT</button>
            @endif
            <form method="POST" action="{{ $actionUrl }}">
              @csrf
              <input type="hidden" name="action" value="generate_ce">
              <button type="submit"><i data-feather="refresh-cw"></i> {{ $ce ? 'Regen Cost Estimate' : 'Generate Cost Estimate' }}</button>
            </form>
          </div>
        </div>

        @if ($pendingCancellation)
        <span class="btn btn-outline btn-sm" style="border-color:#d97706;color:#d97706;margin-left:auto;cursor:default"><i data-feather="clock"></i> Cancellation Pending</span>
        @else
        <button onclick="openModal('modalRequestCancel')" class="btn btn-outline btn-sm" style="border-color:var(--red);color:var(--red);margin-left:auto"><i data-feather="x-circle"></i> Cancel</button>
        @endif

      @elseif ($st === 'ongoing' && $isAdmin)
        <a href="{{ route('checklist', ['booking_id' => $id, 'dir' => 'out']) }}" class="btn btn-outline btn-sm"><i data-feather="log-out"></i> Checklist OUT</a>
        <a href="{{ route('checklist', ['booking_id' => $id, 'dir' => 'in']) }}" class="btn btn-outline btn-sm"><i data-feather="log-in"></i> Checklist IN</a>
        <button onclick="openFieldAddModal()" class="btn btn-outline btn-sm" style="border-color:#d97706;color:#d97706"><i data-feather="plus-circle"></i> Field Add</button>
        <a href="{{ route('attendance', ['booking_id' => $id]) }}" class="btn btn-outline btn-sm"><i data-feather="users"></i> Attendance</a>
        <button onclick="openModal('modalExtendRental')" class="btn btn-outline btn-sm"><i data-feather="calendar"></i> Extend Rental</button>
        <button onclick="openModal('modalRecordPayment')" class="btn btn-outline btn-sm" style="border-color:var(--success);color:var(--success)"><i data-feather="credit-card"></i> Record Payment</button>
        @if ($pendingCancellation)
        <span class="btn btn-outline btn-sm" style="border-color:#d97706;color:#d97706;margin-left:auto;cursor:default"><i data-feather="clock"></i> Cancellation Pending</span>
        @else
        <button onclick="openModal('modalOnFieldCancel')" class="btn btn-danger btn-sm" style="margin-left:auto"><i data-feather="x-octagon"></i> On-Field Cancel</button>
        @endif

      @elseif ($st === 'pending_inspection' && $isAdmin)
        @if ($openIncidentCount > 0)
        <button type="button" onclick="document.querySelector('[data-tab=&quot;tab-incidents&quot;]').click()" class="btn btn-danger btn-sm"><i data-feather="alert-triangle"></i> {{ $openIncidentCount }} Open Incident{{ $openIncidentCount !== 1 ? 's' : '' }}</button>
        @endif
        <a href="{{ route('incidents', ['new' => 1, 'booking_id' => $id]) }}" class="btn btn-outline btn-sm" style="border-color:#dc2626;color:#dc2626"><i data-feather="alert-triangle"></i> Log Incident</a>
        <button onclick="openModal('modalRecordPayment')" class="btn btn-outline btn-sm" style="border-color:var(--success);color:var(--success)"><i data-feather="credit-card"></i> Record Payment</button>
        <form method="POST" action="{{ $actionUrl }}" style="display:inline" onsubmit="return confirm('Confirm inspection complete?\n\nThis will mark the booking as Returned. All open incidents must be closed first.')">
          @csrf
          <input type="hidden" name="action" value="confirm_inspection">
          <button type="submit" class="btn btn-primary btn-sm"><i data-feather="search"></i> Confirm Inspection</button>
        </form>
        <form method="POST" action="{{ $actionUrl }}" style="display:inline" onsubmit="return confirm('Skip inspection and complete this booking?\n\nThis will generate the final billing. Open incidents will remain.')">
          @csrf
          <input type="hidden" name="action" value="complete_booking">
          <button type="submit" class="btn btn-success btn-sm"><i data-feather="check-square"></i> Complete Booking</button>
        </form>

      @elseif ($st === 'returned' && $isAdmin)
        <a href="{{ route('checklist', ['booking_id' => $id, 'dir' => 'in']) }}" class="btn btn-outline btn-sm"><i data-feather="log-in"></i> Checklist IN</a>
        @if ($openIncidentCount > 0)
        <button type="button" onclick="document.querySelector('[data-tab=&quot;tab-incidents&quot;]').click()" class="btn btn-danger btn-sm"><i data-feather="alert-triangle"></i> {{ $openIncidentCount }} Open Incident{{ $openIncidentCount !== 1 ? 's' : '' }}</button>
        @endif
        <button onclick="openModal('modalRecordPayment')" class="btn btn-outline btn-sm" style="border-color:var(--success);color:var(--success)"><i data-feather="credit-card"></i> Record Payment</button>
        @if ($ce)
        <a href="{{ route('ce-preview', ['booking_id' => $id]) }}" class="btn btn-outline btn-sm"><i data-feather="file-text"></i> View CE</a>
        @endif
        <form method="POST" action="{{ $actionUrl }}" style="display:inline" onsubmit="return confirm('Complete this booking?\n\nThis will generate the final billing and close all equipment transactions.')">
          @csrf
          <input type="hidden" name="action" value="complete_booking">
          <button type="submit" class="btn btn-success btn-sm"><i data-feather="check-square"></i> Complete Booking</button>
        </form>

      @elseif ($st === 'completed' && $isAdmin)
        <a href="{{ route('billing') }}" class="btn btn-primary btn-sm"><i data-feather="dollar-sign"></i> View Final Billing</a>
        <button onclick="openModal('modalRecordPayment')" class="btn btn-outline btn-sm" style="border-color:var(--success);color:var(--success)"><i data-feather="credit-card"></i> Record Payment</button>
        @if ($soa)
        <a href="{{ route('billing-print', ['print_soa' => $soa->soa_id]) }}" class="btn btn-outline btn-sm"><i data-feather="printer"></i> Print / Save PDF</a>
        @endif
        @if ($ce)
        <a href="{{ route('ce-preview', ['booking_id' => $id]) }}" class="btn btn-outline btn-sm"><i data-feather="file-text"></i> View CE</a>
        @endif
      @endif
    </div>
  </div>
</div>

<!-- Pre-release checklist -->
@if ($booking->booking_status === 'confirmed' && $isAdmin)
@php
  $crewCount = $crewLines->count();
  $equipCount = $equipmentLines->count();
  $paidAmt = $payments->sum('amount');
  $required50 = $booking->client_type === 'first_time' ? ($booking->final_amount * 0.5) : 0;
  $payOk = $booking->client_type !== 'first_time' || $paidAmt >= $required50;
  $crewOk = $crewCount > 0;
  $equipOk = $equipCount > 0;
  $driverOk = ! $driverNeeded || $driverCount > 0;
  $allOk = $crewOk && $equipOk && $payOk && $driverOk;
  $totalConds = 2 + ($driverNeeded ? 1 : 0) + ($booking->client_type === 'first_time' ? 1 : 0);
  $metConds = ($equipOk ? 1 : 0) + ($crewOk ? 1 : 0) + ($driverNeeded && $driverOk ? 1 : 0) + ($booking->client_type === 'first_time' && $payOk ? 1 : 0);
@endphp
<div class="card" style="margin-bottom:18px;border:1.5px solid {{ $allOk ? '#bbf7d0' : '#fecaca' }};overflow:hidden">
  <div style="padding:10px 16px;display:flex;align-items:center;gap:10px;background:{{ $allOk ? '#f0fdf4' : '#fef2f2' }};border-bottom:1.5px solid {{ $allOk ? '#bbf7d0' : '#fecaca' }}">
    <i data-feather="{{ $allOk ? 'check-circle' : 'alert-triangle' }}" style="width:15px;height:15px;color:{{ $allOk ? '#16a34a' : '#d97706' }};flex-shrink:0"></i>
    <span style="font-weight:700;font-size:.88rem;color:{{ $allOk ? '#15803d' : '#92400e' }};flex:1">{{ $allOk ? 'Ready to Release' : 'Release Readiness' }}</span>
    @if (! $allOk)
    <span style="font-size:.72rem;font-weight:700;background:#fee2e2;color:#dc2626;border-radius:12px;padding:2px 10px;white-space:nowrap">{{ $metConds }}/{{ $totalConds }} conditions met</span>
    @else
    <span style="font-size:.72rem;font-weight:600;color:#16a34a">All conditions met</span>
    @endif
  </div>
  @php
    $chkRow = function (bool $ok, string $label, string $detail, bool $warn = false) {
        $clr = $ok ? '#16a34a' : ($warn ? '#b45309' : '#dc2626');
        $bg = $ok ? '' : ($warn ? 'rgba(245,158,11,.04)' : 'rgba(239,68,68,.04)');
        $icon = $ok ? 'check-circle' : ($warn ? 'alert-circle' : 'x-circle');
        echo '<div style="display:flex;align-items:center;gap:12px;padding:9px 16px;border-bottom:1px solid var(--border2);background:' . $bg . '">'
            . '<i data-feather="' . $icon . '" style="width:13px;height:13px;color:' . $clr . ';flex-shrink:0"></i>'
            . '<span style="font-weight:600;font-size:.82rem;color:var(--text)">' . $label . '</span>'
            . '<span style="margin-left:auto;font-size:.78rem;font-weight:' . ($ok ? '400' : '600') . ';color:' . $clr . '">' . $detail . '</span>'
            . '</div>';
    };
  @endphp
  {!! $chkRow($equipOk, 'Equipment', $equipOk ? "$equipCount item" . ($equipCount != 1 ? 's' : '') . ' added' : 'No equipment added', ! $equipOk) !!}
  {!! $chkRow($crewOk, 'Crew', $crewOk ? "$crewCount member" . ($crewCount != 1 ? 's' : '') . ' assigned' : 'No crew assigned') !!}
  @if ($driverNeeded)
  {!! $chkRow($driverOk, 'Driver', $driverOk ? 'Assigned' : 'Driver not yet assigned') !!}
  @endif
  @if ($booking->client_type === 'first_time')
  {!! $chkRow($payOk, '50% Downpayment', $payOk ? 'Paid' : '₱' . number_format($required50 - $paidAmt, 2) . ' outstanding') !!}
  @endif
</div>
@endif

@if (($booking->cost_approval_status ?? null) === 'pending_client')
<div class="card" style="margin-bottom:18px;border-left:4px solid #f59e0b">
  <div class="card-body" style="padding:14px 18px">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
      <i data-feather="clock" style="width:15px;height:15px;color:#d97706"></i>
      <span style="font-weight:700;font-size:13px;color:#92400e">Awaiting Client Cost Approval</span>
    </div>
    <p style="font-size:12.5px;color:#78350f;margin:0">The full cost breakdown (equipment + crew + transport) has been sent to the client. Equipment cannot be released until they approve.</p>
  </div>
</div>
@elseif (($booking->cost_approval_status ?? null) === 'client_rejected')
<div class="card" style="margin-bottom:18px;border-left:4px solid #ef4444">
  <div class="card-body" style="padding:14px 18px">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
      <i data-feather="x-circle" style="width:15px;height:15px;color:#dc2626"></i>
      <span style="font-weight:700;font-size:13px;color:#991b1b">Client Rejected Cost Estimate</span>
    </div>
    <p style="font-size:12.5px;color:#7f1d1d;margin:0">Adjust the crew and transport assignment, then resend for client approval.</p>
  </div>
</div>
@elseif (($booking->cost_approval_status ?? null) === 'client_approved' && $st === 'confirmed')
<div class="card" style="margin-bottom:18px;border-left:4px solid #22c55e">
  <div class="card-body" style="padding:14px 18px;display:flex;align-items:center;gap:8px">
    <i data-feather="check-circle" style="width:15px;height:15px;color:#16a34a"></i>
    <span style="font-weight:700;font-size:13px;color:#15803d">Client approved the cost estimate — equipment release is authorized.</span>
  </div>
</div>
@endif

<!-- Tabs -->
<div class="tabs">
  @if ($ce)
  <button class="tab-btn active" data-tab="tab-cost-estimate">
    Cost Estimate
    @if ($ce->status === 'confirmed')<span class="badge badge-green" style="margin-left:4px">Confirmed</span>@else<span class="badge badge-gray" style="margin-left:4px">Draft</span>@endif
  </button>
  @else
  {{-- No CE generated yet — the CE page's "Edit Client & Project Info" isn't reachable until
       a CE exists, so this small pre-CE form is the only place to set these fields early. --}}
  <button class="tab-btn active" data-tab="tab-project-details">Project Details</button>
  @endif
  <button class="tab-btn" data-tab="tab-payments">Payments <span class="badge badge-blue" style="margin-left:4px">{{ $payments->count() }}</span></button>
  <div class="tab-divider"></div>
  <button class="tab-btn" data-tab="tab-incidents">Incidents <span class="badge {{ $incidents->count() > 0 ? 'badge-red' : 'badge-gray' }}" style="margin-left:4px">{{ $incidents->count() }}</span></button>
  <button class="tab-btn" data-tab="tab-cancellations">Cancellations
    @if ($pendingCancellation)<span class="badge badge-yellow" style="margin-left:4px">1</span>
    @elseif ($cancellations->count())<span class="badge badge-gray" style="margin-left:4px">{{ $cancellations->count() }}</span>@endif
  </button>
  <button class="tab-btn" data-tab="tab-requests">Extension &amp; Resource Requests
    @php $totalPendingReq = $pendingExtensionCount + $pendingEquipRequestsCount; $totalAllReq = $extensionRequestsCount + $equipRequestsCount; @endphp
    @if ($totalPendingReq > 0)<span class="badge badge-yellow" style="margin-left:4px">{{ $totalPendingReq }}</span>
    @elseif ($totalAllReq > 0)<span class="badge badge-gray" style="margin-left:4px">{{ $totalAllReq }}</span>@endif
  </button>
  <div class="tab-divider"></div>
  <button class="tab-btn" data-tab="tab-log">Activity Log</button>
  <button class="tab-btn" data-tab="tab-comments">Comments <span class="badge badge-blue" style="margin-left:4px">{{ $comments->count() }}</span>@if ($unreadComments > 0)<span class="badge badge-orange" style="margin-left:4px" title="New since you last viewed">{{ $unreadComments }} new</span>@endif</button>
  <button class="tab-btn" data-tab="tab-documents">Documents <span class="badge badge-blue" style="margin-left:4px">{{ $documents->count() }}</span></button>
</div>

<div data-tab-panes>

@if (! $ce)
<!-- PROJECT DETAILS TAB — only shown pre-CE; once a CE exists, its "Edit Client & Project Info"
     modal on the Cost Estimate tab covers the same fields. -->
<div id="tab-project-details" class="tab-pane active">
  <div class="card" id="card-project-details">
    <div class="card-header">
      <h2 class="card-title">Project Details</h2>
    </div>
    <div class="card-body">
      @php
        $ceTypeOptions = ['fs_front' => 'FS Front', 'client_direct' => 'Client Direct', 'partner_front' => 'Partner Front'];
      @endphp
      @if (in_array($role, ['super_admin', 'admin', 'operations_manager', 'traffic'], true) && $booking->booking_status !== 'cancelled')
      <form method="POST" action="{{ $actionUrl }}">
        @csrf
        <input type="hidden" name="action" value="update_project_details">
        <div class="form-row">
          <div class="form-group">
            <label>Type</label>
            <select name="ce_type" class="form-control">
              @foreach ($ceTypeOptions as $k => $label)
              <option value="{{ $k }}" {{ ($booking->ce_type ?? 'fs_front') === $k ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group">
            <label>Due Date <span style="font-weight:400;color:var(--text-muted);font-size:11px">(leave blank for none)</span></label>
            <input type="date" name="ce_due_date" class="form-control" value="{{ $booking->ce_due_date ?? '' }}">
          </div>
        </div>
        <div class="form-group">
          <label>Director / DOP</label>
          <input type="text" name="ce_director_dop" class="form-control" value="{{ $booking->ce_director_dop ?? '' }}" placeholder="e.g. Direk Peter Frac">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Contact Person</label>
            <input type="text" name="ce_contact_person" class="form-control" value="{{ $booking->ce_contact_person ?? '' }}"
                   placeholder="{{ $booking->contact_person ?: '— from client record —' }}">
          </div>
          <div class="form-group">
            <label>Contact Number</label>
            <input type="text" name="ce_contact_number" class="form-control" value="{{ $booking->ce_contact_number ?? '' }}"
                   placeholder="{{ $booking->client_phone ?: '— from client record —' }}">
          </div>
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="ce_contact_email" class="form-control" value="{{ $booking->ce_contact_email ?? '' }}"
                 placeholder="{{ $booking->client_email ?: '— from client record —' }}">
        </div>
        <div style="font-size:11px;color:var(--text-muted);margin-bottom:10px">
          Leave a contact field blank to use the client record on the CE document.
        </div>
        <button type="submit" class="btn btn-primary btn-sm"><i data-feather="save"></i> Save Project Details</button>
      </form>
      @else
      <div style="font-size:13px">
        <div><strong>Type:</strong> {{ $ceTypeOptions[$booking->ce_type ?? 'fs_front'] ?? 'FS Front' }}</div>
        <div><strong>Due Date:</strong> {{ $booking->ce_due_date ? date('M j, Y', strtotime($booking->ce_due_date)) : '—' }}</div>
        <div><strong>Director / DOP:</strong> {{ $booking->ce_director_dop ?: '—' }}</div>
        <div><strong>Contact:</strong> {{ $booking->ce_contact_person ?: ($booking->contact_person ?: '—') }}</div>
      </div>
      @endif
    </div>
  </div>
</div>
@endif

@if ($ce)
<!-- COST ESTIMATE TAB — embeds the full CE editor (equipment/crew/accessories/transport/discount
     all live here now) so it's the one place to build and manage the quote for this booking. -->
<div id="tab-cost-estimate" class="tab-pane active">
  <div class="card" style="margin-bottom:0">
    <div class="card-header">
      <h2 class="card-title"><i data-feather="file-text" style="width:15px;height:15px;margin-right:6px;vertical-align:middle"></i>Cost Estimate</h2>
      <a href="{{ route('ce-preview', ['booking_id' => $id]) }}" class="btn btn-outline btn-sm" target="_blank">Open Full Page <i data-feather="external-link" style="width:12px;height:12px"></i></a>
    </div>
    <div class="card-body" style="padding:0">
      <iframe src="{{ route('ce-preview', ['booking_id' => $id]) }}" style="width:100%;height:1400px;border:none;display:block"></iframe>
    </div>
  </div>
</div>
@endif

<!-- PAYMENTS TAB -->
<div id="tab-payments" class="tab-pane">
  @php
    $payPaid = $payments->sum('amount');
    $payTotal = (float) $booking->final_amount;
    $payBalance = max(0, $payTotal - $payPaid);
    $payStatus = $booking->payment_status;
    $soaDue = $soa->due_date ?? null;
    $daysUntilDuePay = $soaDue ? (int) floor((strtotime($soaDue) - time()) / 86400) : null;
    $payIsOverdue = $soaDue && strtotime($soaDue) < time() && $payBalance > 0;
    $payIsDueSoon = ! $payIsOverdue && $soaDue && $daysUntilDuePay !== null && $daysUntilDuePay >= 0 && $daysUntilDuePay <= 7 && $payBalance > 0;
  @endphp
  <div class="card" style="margin-bottom:16px;border-left:4px solid {{ $payIsOverdue ? 'var(--red)' : ($payIsDueSoon ? '#d97706' : ($payBalance <= 0 ? 'var(--green)' : 'var(--border)')) }}">
    <div class="card-body" style="padding:16px 20px">
      <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:center">
        <div style="flex:1;min-width:120px">
          <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:3px">Total Amount</div>
          <div style="font-weight:700;font-size:1.1rem;color:var(--text)">₱{{ number_format($payTotal, 2) }}</div>
        </div>
        <div style="flex:1;min-width:120px">
          <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:3px">Paid</div>
          <div style="font-weight:700;font-size:1.1rem;color:var(--green)">₱{{ number_format($payPaid, 2) }}</div>
        </div>
        <div style="flex:1;min-width:120px">
          <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:3px">Balance</div>
          <div style="font-weight:800;font-size:1.2rem;color:{{ $payBalance > 0 ? 'var(--red)' : 'var(--green)' }}">₱{{ number_format($payBalance, 2) }}</div>
        </div>
        <div style="flex:1;min-width:140px">
          <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:3px">Status</div>
          <span class="badge {{ $payBadge[$payStatus] ?? 'badge-gray' }}" style="font-size:.78rem">{{ $payLabel[$payStatus] ?? ucfirst($payStatus) }}</span>
          @if ($payIsOverdue)
          <div style="font-size:.7rem;color:var(--red);margin-top:4px;font-weight:600">Overdue by {{ abs($daysUntilDuePay) }} day{{ abs($daysUntilDuePay) !== 1 ? 's' : '' }}</div>
          @elseif ($payIsDueSoon)
          <div style="font-size:.7rem;color:#d97706;margin-top:4px;font-weight:600">Due in {{ $daysUntilDuePay }} day{{ $daysUntilDuePay !== 1 ? 's' : '' }}</div>
          @endif
        </div>
        @if ($soaDue)
        <div style="flex:1;min-width:120px">
          <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:3px">
            Due Date {{ $booking->client_type === 'first_time' ? '(7-day COD)' : '(90-day terms)' }}
          </div>
          <div style="font-weight:600;font-size:.9rem;color:{{ $payIsOverdue ? 'var(--red)' : ($payIsDueSoon ? '#d97706' : 'var(--text)') }}">
            {{ \Carbon\Carbon::parse($soaDue)->format('M j, Y') }}
          </div>
        </div>
        @endif
        @if ($soa)
        <div>
          <a href="{{ route('billing-print', ['print_soa' => $soa->soa_id]) }}" class="btn btn-outline btn-sm"><i data-feather="printer"></i> Print / Save PDF</a>
        </div>
        @endif
      </div>
    </div>
  </div>


  {{-- Preview List and the itemized cost breakdown that used to live here are now the Cost
       Estimate tab's job (it embeds the CE editor, which shows the same equipment listing and
       totals). What's left below is what has no home there: field additions, the client's
       response to the quote, and the pricing-mode/VAT-exempt controls. --}}
  <div class="card" id="card-package-totals" style="margin-bottom:16px">
    <div class="card-header">
      <h2 class="card-title">Pricing &amp; Client Response</h2>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <a href="{{ route('booking-detail.ce-export', $booking->booking_id) }}"
           class="btn btn-outline btn-sm"><i data-feather="download"></i> Export CSV</a>
        @if (in_array($role, config('filmspec.all_staff'), true))
        <form method="POST" action="{{ $actionUrl }}" style="display:inline"
              onsubmit="return confirm('Email this cost estimate to the client and mark it pending their approval?')">
          @csrf
          <input type="hidden" name="action" value="email_ce_to_client">
          <button type="submit" class="btn btn-outline btn-sm"><i data-feather="mail"></i> Email to Client</button>
        </form>
        @endif
        @if (in_array($role, ['super_admin', 'admin', 'operations_manager', 'traffic'], true))
        <button type="button" class="btn btn-outline btn-sm" onclick="openModal('modalCloseProject')">
          <i data-feather="check-square"></i> Close Project
        </button>
        @endif
      </div>
    </div>
    <div class="card-body">
      @php
        $pricingModeLabels = ['no_discount' => 'Full itemized total', 'package_price' => 'Package Price', 'discount_percent' => 'Discount %', 'discount_flat' => 'Discount ₱'];
        $curMode = $ce->pricing_mode ?? 'no_discount';
      @endphp

      @if ($fieldAdditions->isNotEmpty())
      {{-- Equipment/accessories requested and delivered to the field mid-shoot, on top of the
           original booking — already folded into the totals above (added at Dispatch), listed
           out here so it's clear which items these are and when each was followed up. --}}
      <div style="margin-bottom:14px">
        <div style="font-size:11px;font-weight:700;letter-spacing:.5px;color:var(--blue-700);margin-bottom:6px">
          FIELD ADDITIONS
          <span style="float:right;font-weight:400;color:var(--text-muted)">{{ $fieldAdditions->count() }} item{{ $fieldAdditions->count() === 1 ? '' : 's' }}</span>
        </div>
        @foreach ($fieldAdditions as $fa)
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;font-size:12.5px;padding:5px 0;border-bottom:1px solid var(--border)">
          <div>
            <span style="font-family:monospace;color:var(--text-muted)">{{ $fa->quantity }}×</span> {{ $fa->item_name ?: ucfirst($fa->item_type) }}
            <span class="badge {{ $fa->status === 'delivered' ? 'badge-green' : 'badge-blue' }}" style="margin-left:4px;font-size:.65rem">{{ ucfirst($fa->status) }}</span>
            <div style="font-size:.72rem;color:var(--muted)">
              {{ $fa->status === 'delivered' ? 'Delivered' : 'Dispatched' }} {{ \Carbon\Carbon::parse($fa->followed_up_at)->format('M j, Y g:ia') }}
            </div>
          </div>
          <span style="font-family:monospace;white-space:nowrap;font-weight:600">₱{{ number_format($fa->line_cost, 2) }}</span>
        </div>
        @endforeach
        <div style="display:flex;justify-content:space-between;gap:16px;padding-top:6px;font-weight:700;font-size:12.5px">
          <span>Field additions total</span>
          <span style="font-family:monospace">₱{{ number_format($fieldAdditions->sum('line_cost'), 2) }}</span>
        </div>
      </div>
      @endif

      {{-- Client response — the closest signal we have to their "Check inbox": we can't read
           a mailbox, but the portal approve/reject flow already records the answer. --}}
      @php
        $carLabels = ['pending_client' => 'Awaiting client response', 'client_approved' => 'Approved by client', 'client_rejected' => 'Rejected by client'];
        $carColors = ['pending_client' => 'badge-yellow', 'client_approved' => 'badge-green', 'client_rejected' => 'badge-red'];
        $lastSent = collect($quotationLog)->first(fn ($q) => str_contains((string) ($q->remarks ?? ''), 'emailed to'));
      @endphp
      @if ($booking->cost_approval_status || $lastSent)
      <div style="margin-bottom:12px;padding:8px 10px;background:var(--s2);border-radius:6px;font-size:12px">
        <strong>Client response:</strong>
        <span class="badge {{ $carColors[$booking->cost_approval_status] ?? 'badge-gray' }}" style="margin-left:4px">
          {{ $carLabels[$booking->cost_approval_status] ?? 'Not sent yet' }}
        </span>
        @if ($lastSent)
        <div style="color:var(--text-muted);margin-top:3px">Last emailed {{ date('M j, Y g:i A', strtotime($lastSent->log_date)) }}</div>
        @endif
      </div>
      @endif

      @if ($ce)
      <div style="margin-bottom:14px;font-size:12px;color:var(--text-muted)">
        Mode: <strong style="color:var(--text)">{{ $pricingModeLabels[$curMode] ?? $curMode }}</strong>
        @if ($curMode !== 'no_discount' && $ce->pricing_input !== null)
          — {{ $curMode === 'discount_percent' ? number_format($ce->pricing_input, 2).'%' : '₱'.number_format($ce->pricing_input, 2) }}
        @endif
        @if ($ce->vat_exempt)<span class="badge badge-gray" style="margin-left:6px">VAT-exempt</span>@endif
      </div>
      @endif

      @if (in_array($role, ['super_admin', 'admin', 'operations_manager'], true) && $booking->booking_status !== 'cancelled')
      <form method="POST" action="{{ $actionUrl }}">
        @csrf
        <input type="hidden" name="action" value="update_ce_pricing">
        <div class="form-row">
          <div class="form-group">
            <label>Pricing Mode</label>
            <select name="pricing_mode" id="pricingModeSelect" class="form-control" onchange="togglePricingInput()">
              <option value="no_discount" {{ $curMode === 'no_discount' ? 'selected' : '' }}>No Discount — Full Itemized Total</option>
              <option value="package_price" {{ $curMode === 'package_price' ? 'selected' : '' }}>Package Price (all-in, incl. crew)</option>
              <option value="discount_percent" {{ $curMode === 'discount_percent' ? 'selected' : '' }}>Discount %</option>
              <option value="discount_flat" {{ $curMode === 'discount_flat' ? 'selected' : '' }}>Discount ₱</option>
            </select>
          </div>
          <div class="form-group" id="pricingInputWrap" style="{{ $curMode === 'no_discount' ? 'display:none' : '' }}">
            <label id="pricingInputLabel">Amount</label>
            <input type="number" name="pricing_input" id="pricingInputField" class="form-control" step="0.01" min="0" value="{{ $ce->pricing_input ?? '' }}">
          </div>
        </div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;font-weight:500;cursor:pointer">
            <input type="checkbox" name="vat_exempt" value="1" {{ ($ce && $ce->vat_exempt) ? 'checked' : '' }}>
            VAT-exempt (0% instead of 12%)
          </label>
        </div>
        <button type="submit" class="btn btn-primary btn-sm"><i data-feather="tag"></i> Apply Pricing</button>
      </form>
      @endif
    </div>
  </div>

  @php
    $canReviewDiscounts = in_array($role, ['super_admin', 'admin', 'operations_manager', 'accounting', 'traffic'], true);
    $discStatusBadge = ['pending' => 'badge-yellow', 'approved' => 'badge-green', 'rejected' => 'badge-red'];
  @endphp
  @php
    $hasPendingDiscount = $discounts->where('status', 'pending')->isNotEmpty();
    $canSetDiscount = $canReviewDiscounts && ! $hasPendingDiscount && $booking->booking_status !== 'cancelled';
  @endphp
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Discounts</h2>
      <div style="display:flex;align-items:center;gap:8px">
        @if ($discounts->isNotEmpty())
        <span class="badge badge-gray">{{ $discounts->count() }}</span>
        @endif
        @if ($canSetDiscount)
        <button type="button" class="btn btn-outline btn-sm" onclick="openModal('modalSetDiscount')" title="For when a client calls in and agrees to a discount on the spot — applies immediately, no separate approval needed">
          <i data-feather="percent"></i> Set Discount
        </button>
        @endif
      </div>
    </div>
    <div class="card-body">
      @if ($discounts->isEmpty())
      <div class="empty-state" style="padding:16px 0"><i data-feather="percent"></i><h3 style="font-size:13px">No discounts proposed or requested for this booking yet</h3></div>
      @else
      @foreach ($discounts as $d)
      <div style="padding:10px 0;{{ ! $loop->last ? 'border-bottom:1px solid var(--border)' : '' }}">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap">
          <div>
            <span class="badge {{ $discStatusBadge[$d->status] ?? 'badge-gray' }}">{{ ucfirst($d->status) }}</span>
            <strong style="margin-left:6px">
              @if ($d->discount_type === 'percent') {{ number_format($d->discount_value, 2) }}% off
              @elseif ($d->discount_type === 'package') Package price: ₱{{ number_format($d->discount_value, 2) }}
              @else ₱{{ number_format($d->discount_value, 2) }} off
              @endif
            </strong>
            <span style="color:var(--text-muted);font-size:12px">→ ₱{{ number_format($d->computed_amount, 2) }} off</span>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px">
              {{ $d->proposed_by_role === 'client' ? 'Requested by client' : 'Proposed by ' . trim($d->proposed_by_name) }}
              &middot; {{ date('M j, Y g:i A', strtotime($d->created_at)) }}
            </div>
            @if ($d->reason)
            <div style="font-size:12px;margin-top:3px">{{ $d->reason }}</div>
            @endif
            @if ($d->status !== 'pending')
            <div style="font-size:12px;color:var(--text-muted);margin-top:3px">
              {{ ucfirst($d->status) }} by {{ trim($d->approved_by_name) ?: '—' }} on {{ $d->approved_at ? date('M j, Y g:i A', strtotime($d->approved_at)) : '—' }}
              @if ($d->review_notes) — {{ $d->review_notes }} @endif
            </div>
            @endif
          </div>
          @if ($d->status === 'pending' && $canReviewDiscounts)
          <div style="display:flex;gap:6px;flex-shrink:0">
            <form method="POST" action="{{ $actionUrl }}" onsubmit="return confirm('Approve this discount and apply it to the cost estimate?')">
              @csrf
              <input type="hidden" name="action" value="approve_discount">
              <input type="hidden" name="discount_id" value="{{ $d->discount_id }}">
              <button type="submit" class="btn btn-outline btn-sm"><i data-feather="check"></i> Approve</button>
            </form>
            <form method="POST" action="{{ $actionUrl }}" onsubmit="return confirm('Reject this discount proposal?')">
              @csrf
              <input type="hidden" name="action" value="reject_discount">
              <input type="hidden" name="discount_id" value="{{ $d->discount_id }}">
              <button type="submit" class="btn btn-outline btn-sm"><i data-feather="x"></i> Reject</button>
            </form>
          </div>
          @endif
        </div>
      </div>
      @endforeach
      @endif
    </div>
  </div>

  <div class="card" id="paymentsCard">
    <div class="card-header">
      <h2 class="card-title">Payment History</h2>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      @if ($payments->count() > 0)
      <div class="search-input-wrap" style="min-width:160px">
        <i data-feather="search" style="width:13px;height:13px"></i>
        <input type="text" id="paySearchTbl" placeholder="Search payments…" oninput="listFilter({rowSelector:'#paymentsCard tbody tr', searchId:'paySearchTbl', filterId:'payTypeFilter', pagerId:'payPager', pageSize:8})">
      </div>
      <select id="payTypeFilter" class="form-control" style="width:auto;font-size:12px;padding:7px 10px" onchange="listFilter({rowSelector:'#paymentsCard tbody tr', searchId:'paySearchTbl', filterId:'payTypeFilter', pagerId:'payPager', pageSize:8})">
        <option value="">All types</option>
        <option value="downpayment">Downpayment</option>
        <option value="progress">Progress</option>
        <option value="final">Final</option>
      </select>
      @endif
      @if (($isAdmin || $role === 'accounting') && $booking->booking_status !== 'cancelled')
      <button onclick="openModal('modalRecordPayment')" class="btn btn-primary btn-sm"><i data-feather="plus"></i> Record Payment</button>
      @endif
      </div>
    </div>
    <div class="table-wrap">
      @if ($payments->count() === 0)
      <div class="empty-state"><i data-feather="credit-card"></i><h3>No payments yet</h3>
        <p>Use the "Record Payment" button above to log a payment.</p>
      </div>
      @else
      <table>
        <thead><tr><th>Date</th><th>Type</th><th>Method</th><th>Amount</th><th>Receipt</th><th>Reference</th></tr></thead>
        <tbody>
          @foreach ($payments as $pay)
          <tr data-filter="{{ $pay->payment_type }}">
            <td>{{ \Carbon\Carbon::parse($pay->payment_date)->format('M j, Y') }}</td>
            <td><span class="badge badge-blue">{{ ucfirst($pay->payment_type) }}</span></td>
            <td>{{ ucwords(str_replace('_', ' ', $pay->payment_method)) }}</td>
            <td style="font-weight:700;color:var(--blue-700)">₱{{ number_format($pay->amount, 2) }}</td>
            <td style="font-size:.8rem">
              <a href="{{ route('payment-receipt', $pay->payment_id) }}" target="_blank" style="color:var(--accent);font-weight:600;text-decoration:none">{{ $pay->receipt_number ?: 'View' }}</a>
              <span style="color:var(--text-muted)">({{ $pay->is_vat ? 'OR' : 'AR' }})</span>
            </td>
            <td style="font-size:.8rem;color:var(--text-muted)">{{ $pay->reference_number ?: '—' }}</td>
          </tr>
          @endforeach
        </tbody>
        <tfoot>
          <tr style="background:var(--blue-50)">
            <td colspan="3" style="text-align:right;font-weight:700;padding:12px 16px">Total Paid</td>
            <td style="font-weight:800;color:var(--success);padding:12px 16px">₱{{ number_format($payments->sum('amount'), 2) }}</td>
            <td colspan="2"></td>
          </tr>
        </tfoot>
      </table>
      @endif
    </div>
    @if ($payments->count() > 8)
    <div class="pager" id="payPager" data-page="1">
      <span class="pager-info"></span>
      <div class="pager-btns"></div>
    </div>
    <script>document.addEventListener('DOMContentLoaded', () => listFilter({rowSelector:'#paymentsCard tbody tr', searchId:'paySearchTbl', filterId:'payTypeFilter', pagerId:'payPager', pageSize:8}));</script>
    @endif
  </div>
</div>

<!-- INCIDENTS TAB -->
<div id="tab-incidents" class="tab-pane">
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Incident & Penalty Reports</h2>
      @if ($incidents->count())
      <div style="display:flex;gap:14px;align-items:center;font-size:.83rem;flex-wrap:wrap">
        <div class="search-input-wrap" style="min-width:160px">
          <i data-feather="search" style="width:13px;height:13px"></i>
          <input type="text" id="incSearchTbl" placeholder="Search incidents…" oninput="listFilter({rowSelector:'#tab-incidents .table-wrap tbody tr', searchId:'incSearchTbl', filterId:'incStatusFilter'})">
        </div>
        <select id="incStatusFilter" class="form-control" style="width:auto;font-size:12px;padding:7px 10px" onchange="listFilter({rowSelector:'#tab-incidents .table-wrap tbody tr', searchId:'incSearchTbl', filterId:'incStatusFilter'})">
          <option value="">All statuses</option>
          <option value="open">Open</option>
          <option value="resolved">Resolved</option>
          <option value="closed">Closed</option>
        </select>
        @if ($openIncidentCount > 0)
        <span style="color:var(--red);font-weight:600">{{ $openIncidentCount }} open</span>
        @endif
        <span style="color:var(--muted)">Total charges: <strong style="color:var(--red)">₱{{ number_format($incidents->sum('charge_amount'), 2) }}</strong></span>
      </div>
      @endif
    </div>
    @if ($openIncidentCount > 0)
    <div style="padding:10px 16px;background:#fef2f2;border-bottom:1px solid #fecaca;font-size:12.5px;color:#991b1b;display:flex;align-items:center;gap:8px">
      <i data-feather="alert-triangle" style="width:13px;height:13px;flex-shrink:0"></i>
      <span><strong>{{ $openIncidentCount }} incident{{ $openIncidentCount !== 1 ? 's' : '' }} need attention.</strong> Fill in the description and charge amount for each open report, then resolve them before completing the booking.</span>
    </div>
    @endif
    <div class="table-wrap">
      @if ($incidents->count() === 0)
      <div class="empty-state"><i data-feather="shield"></i><h3>No incidents reported</h3><p>All equipment returned in good condition.</p></div>
      @else
      <table>
        <thead>
          <tr><th>Equipment</th><th>Type</th><th>Date</th><th>Description</th><th>Charge</th><th>Status</th>
          @if ($isAdmin)<th>Actions</th>@endif</tr>
        </thead>
        <tbody>
          @foreach ($incidents as $inc)
          @php $needsDetails = $inc->status === 'open' && $inc->charge_amount == 0; @endphp
          <tr data-filter="{{ $inc->status }}" style="{{ $inc->status === 'open' ? 'background:var(--redl,#fef2f2)' : '' }}">
            <td>
              <div style="font-weight:600">{{ $inc->equipment_name }}</div>
              @if ($inc->incident_number)<div style="font-size:.7rem;color:var(--muted)">{{ $inc->incident_number }}</div>@endif
            </td>
            <td>
              <span class="badge {{ $incTypeBadge[$inc->incident_type] ?? 'badge-gray' }}">{{ ucwords(str_replace('_', ' ', $inc->incident_type)) }}</span>
              @if ($needsDetails)<div style="margin-top:4px"><span class="badge badge-yellow" style="font-size:.65rem">Needs Details</span></div>@endif
            </td>
            <td style="font-size:.83rem">{{ \Carbon\Carbon::parse($inc->incident_date)->format('M j, Y') }}</td>
            <td style="font-size:.8rem;max-width:220px;color:{{ $inc->description ? 'var(--text)' : 'var(--muted)' }}">
              {{ $inc->description ?: '' }}@if (! $inc->description)<em>No description yet</em>@endif
            </td>
            <td style="font-weight:700;color:{{ $inc->charge_amount > 0 ? 'var(--red)' : 'var(--muted)' }}">
              {{ $inc->charge_amount > 0 ? '₱' . number_format($inc->charge_amount, 2) : '—' }}
            </td>
            <td>
              <span class="badge {{ $inc->status === 'resolved' ? 'badge-green' : ($inc->status === 'open' ? 'badge-red' : 'badge-gray') }}">{{ ucfirst($inc->status) }}</span>
            </td>
            @if ($isAdmin)
            <td style="white-space:nowrap">
              @if ($inc->status !== 'closed')
              <button class="btn btn-outline btn-sm"
                      onclick="openIncidentUpdate({{ $inc->incident_id }}, '{{ addslashes($inc->equipment_name) }}', {{ $inc->charge_amount }}, '{{ addslashes($inc->resolution ?? '') }}', '{{ $inc->status }}', '{{ addslashes($inc->description ?? '') }}', '{{ addslashes($inc->cause ?? '') }}')">
                <i data-feather="edit-2"></i>
              </button>
              @endif
              @if ($inc->incident_number)
              <a href="{{ route('incident-print', $inc->incident_id) }}?pdf=1" target="_blank" class="btn btn-outline btn-sm" title="Print / Save as PDF"><i data-feather="printer"></i></a>
              @endif
              @if ($inc->status === 'closed')—@endif
            </td>
            @endif
          </tr>
          @endforeach
        </tbody>
      </table>
      @endif
    </div>
  </div>
</div>

<!-- ACTIVITY LOG TAB -->
<div id="tab-log" class="tab-pane">
  <div class="card" id="logCard">
    <div class="card-header">
      <h2 class="card-title">Booking Activity Log</h2>
      @if ($quotationLog->count() > 0)
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <div class="search-input-wrap" style="min-width:160px">
          <i data-feather="search" style="width:13px;height:13px"></i>
          <input type="text" id="logSearchTbl" placeholder="Search activity…" oninput="listFilter({rowSelector:'#logCard .timeline-item', searchId:'logSearchTbl', filterId:'logTypeFilter', pagerId:'logPager', pageSize:8})">
        </div>
        <select id="logTypeFilter" class="form-control" style="width:auto;font-size:12px;padding:7px 10px" onchange="listFilter({rowSelector:'#logCard .timeline-item', searchId:'logSearchTbl', filterId:'logTypeFilter', pagerId:'logPager', pageSize:8})">
          <option value="">All actions</option>
          @foreach ($quotationLog->pluck('log_type')->unique()->sort() as $lt)
          <option value="{{ $lt }}">{{ ucfirst($lt) }}</option>
          @endforeach
        </select>
      </div>
      @endif
    </div>
    <div class="card-body">
      @if ($quotationLog->count() === 0)
      <div class="empty-state"><i data-feather="list"></i><h3>No log entries yet</h3></div>
      @else
      <ul class="timeline">
        @foreach ($quotationLog as $log)
        <li class="timeline-item" data-filter="{{ $log->log_type }}">
          <div class="timeline-dot"><i data-feather="git-commit"></i></div>
          <div class="timeline-content">
            <div class="timeline-title">
              <span class="badge badge-blue" style="margin-right:6px">{{ ucfirst($log->log_type) }}</span>
              @if ($log->previous_status && $log->new_status)
              <span style="color:var(--text-muted);font-size:.83rem">{{ ucfirst($log->previous_status) }} &rarr; <strong>{{ ucfirst($log->new_status) }}</strong></span>
              @endif
            </div>
            <div class="timeline-meta">
              {{ $log->user_name ?? 'System' }} &nbsp;·&nbsp;
              {{ \Carbon\Carbon::parse($log->log_date)->format('M j, Y g:i a') }}
              @if ($log->remarks)
              &nbsp;·&nbsp; {{ $log->remarks }}
              @endif
            </div>
          </div>
        </li>
        @endforeach
      </ul>
      @endif
    </div>
    @if ($quotationLog->count() > 8)
    <div class="pager" id="logPager" data-page="1">
      <span class="pager-info"></span>
      <div class="pager-btns"></div>
    </div>
    <script>document.addEventListener('DOMContentLoaded', () => listFilter({rowSelector:'#logCard .timeline-item', searchId:'logSearchTbl', filterId:'logTypeFilter', pagerId:'logPager', pageSize:8}));</script>
    @endif
  </div>
</div>

<!-- COMMENTS TAB -->
<div id="tab-comments" class="tab-pane">
  <div class="card" id="commentsCard">
    <div class="card-header">
      <h2 class="card-title">Comments</h2>
      @if ($comments->count() > 0)
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <div class="search-input-wrap" style="min-width:160px">
          <i data-feather="search" style="width:13px;height:13px"></i>
          <input type="text" id="cmtSearchTbl" placeholder="Search comments…" oninput="listFilter({rowSelector:'#commentsCard .comment-row', searchId:'cmtSearchTbl', filterId:'cmtVisFilter', pagerId:'cmtPager', pageSize:6})">
        </div>
        <select id="cmtVisFilter" class="form-control" style="width:auto;font-size:12px;padding:7px 10px" onchange="listFilter({rowSelector:'#commentsCard .comment-row', searchId:'cmtSearchTbl', filterId:'cmtVisFilter', pagerId:'cmtPager', pageSize:6})">
          <option value="">Everyone</option>
          <option value="internal">Internal only</option>
          <option value="visible">Client-visible</option>
        </select>
      </div>
      @endif
    </div>
    <div class="card-body">
      @if ($comments->count() === 0)
      <div class="empty-state"><i data-feather="message-circle"></i><h3>No comments yet</h3><p>Notes here are visible to the client unless marked internal.</p></div>
      @else
      <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:18px">
        @foreach ($comments as $c)
        <div class="comment-row" data-filter="{{ $c->is_internal ? 'internal' : 'visible' }}" style="padding:12px 14px;border-radius:8px;background:{{ $c->is_internal ? '#1c1917' : 'var(--s2)' }};border:1px solid {{ $c->is_internal ? '#b45309' : 'var(--border)' }}">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px">
            <div style="font-weight:600;font-size:13px">
              {{ $c->author_name }}
              <span class="badge {{ $c->author_role === 'client' ? 'badge-purple' : 'badge-blue' }}" style="margin-left:6px;font-size:.65rem">{{ ucfirst(str_replace('_',' ',$c->author_role)) }}</span>
              @if ($c->is_internal)
              <span class="badge badge-orange" style="margin-left:4px;font-size:.65rem">Internal Only</span>
              @endif
            </div>
            <span style="font-size:11px;color:var(--muted)">{{ \Illuminate\Support\Carbon::parse($c->created_at)->format('M j, Y g:i a') }}</span>
          </div>
          <div style="font-size:13.5px;white-space:pre-wrap">{{ $c->body }}</div>
        </div>
        @endforeach
      </div>
      @if ($comments->count() > 6)
      <div class="pager" id="cmtPager" data-page="1" style="margin:0 0 18px;border-radius:8px;border:1px solid var(--border)">
        <span class="pager-info"></span>
        <div class="pager-btns"></div>
      </div>
      <script>document.addEventListener('DOMContentLoaded', () => listFilter({rowSelector:'#commentsCard .comment-row', searchId:'cmtSearchTbl', filterId:'cmtVisFilter', pagerId:'cmtPager', pageSize:6}));</script>
      @endif
      @endif

      @if ($isAdmin || $role === 'accounting')
      <form method="POST" action="{{ $actionUrl }}">
        @csrf
        <input type="hidden" name="action" value="post_comment">
        <div class="form-group">
          <textarea name="body" class="form-control" rows="3" placeholder="Write a comment…" required></textarea>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center">
          <label style="display:flex;align-items:center;gap:6px;font-weight:400;font-size:13px;color:var(--muted)">
            <input type="checkbox" name="is_internal" value="1"> Internal only (not visible to client)
          </label>
          <button type="submit" class="btn btn-primary btn-sm"><i data-feather="send"></i> Post Comment</button>
        </div>
      </form>
      @endif
    </div>
  </div>
</div>

<!-- DOCUMENTS TAB -->
<div id="tab-documents" class="tab-pane">
  @include('partials.documents-card')
</div>

<!-- CANCELLATIONS TAB -->
<div id="tab-cancellations" class="tab-pane">
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Cancellation History</h2>
    </div>
    <div class="card-body">
      @if ($pendingCancellation && in_array($role, ['super_admin', 'admin', 'operations_manager'], true))
      <div style="background:#1c1917;border:1px solid #b45309;color:#fbbf24;padding:14px 16px;border-radius:8px;margin-bottom:16px">
        <div style="font-weight:600;margin-bottom:6px;display:flex;align-items:center;gap:6px">
          <i data-feather="alert-triangle" style="width:15px;height:15px"></i>
          Pending Cancellation Request
        </div>
        <div style="font-size:13px;margin-bottom:12px">
          Requested by <strong>{{ $pendingCancellation->requested_by_name }}</strong>
          on {{ \Illuminate\Support\Carbon::parse($pendingCancellation->created_at)->format('M j, Y g:i a') }}<br>
          Reason: {{ $pendingCancellation->reason ?: '—' }}
        </div>
        <div style="display:flex;gap:8px">
          <button onclick="openApproveCancellation({{ $pendingCancellation->cancellation_id }})" class="btn btn-sm btn-danger">
            <i data-feather="check" style="width:13px;height:13px"></i> Approve
          </button>
          <button onclick="openRejectCancellation({{ $pendingCancellation->cancellation_id }})" class="btn btn-sm btn-secondary">
            <i data-feather="x" style="width:13px;height:13px"></i> Reject
          </button>
        </div>
      </div>
      @endif

      @if ($cancellations->isEmpty())
      <div class="empty-state"><i data-feather="x-circle"></i><h3>No cancellations</h3><p>No cancellation requests for this booking.</p></div>
      @else
      <table class="data-table">
        <thead>
          <tr><th>Type</th><th>Requested By</th><th>Date</th><th>Status</th><th>Penalty</th><th>Refund</th><th>Notes</th></tr>
        </thead>
        <tbody>
          @php
            $cBadge = ['pending' => 'badge-yellow', 'approved' => 'badge-green', 'rejected' => 'badge-red'];
            $cType = ['client_request' => 'Client Request', 'admin_cancel' => 'Admin Cancel', 'on_field' => 'On-Field'];
          @endphp
          @foreach ($cancellations as $c)
          <tr>
            <td><span class="badge badge-gray">{{ $cType[$c->request_type] ?? $c->request_type }}</span></td>
            <td>{{ $c->requested_by_name }}</td>
            <td>{{ \Illuminate\Support\Carbon::parse($c->created_at)->format('M j, Y') }}</td>
            <td><span class="badge {{ $cBadge[$c->status] ?? 'badge-gray' }}">{{ ucfirst($c->status) }}</span></td>
            <td>{{ $c->penalty_amount > 0 ? '₱' . number_format($c->penalty_amount, 2) . ' (' . round($c->penalty_rate * 100) . '%)' : '—' }}</td>
            <td>{!! $c->refund_amount > 0 ? '<span style="color:var(--green)">₱' . number_format($c->refund_amount, 2) . '</span>' : '—' !!}</td>
            <td style="font-size:12px;color:var(--muted)">{{ $c->notes ?: ($c->reason ?: '—') }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @endif
    </div>
  </div>
</div>

@php
  $statusLabelsReq = ['pending' => 'Pending Review', 'approved' => 'Approved', 'rejected' => 'Rejected'];
  $statusBadgesReq = ['pending' => 'badge-yellow', 'approved' => 'badge-green', 'rejected' => 'badge-red'];
  $statusLabelsField = $statusLabelsReq + ['dispatched' => 'Dispatched', 'delivered' => 'Delivered'];
  $statusBadgesField = $statusBadgesReq + ['dispatched' => 'badge-blue', 'delivered' => 'badge-gray'];
  $fieldItemLabel = fn ($r) => match ($r->item_type) {
      'accessory' => $r->accessory_name,
      'crew' => ($r->crew_name && trim($r->crew_name) !== '') ? $r->crew_name : ($r->position_name . ' (unassigned)'),
      default => $r->equipment_name,
  };
@endphp
<div id="tab-requests" class="tab-pane">

  <!-- Extension Requests -->
  <div class="card" style="margin-bottom:16px" id="extRequestsCard">
    <div class="card-header">
      <h2 class="card-title">Rental Extension Requests</h2>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      @if ($extensionRequests->count() > 0)
      <select id="extStatusFilter" class="form-control" style="width:auto;font-size:12px;padding:7px 10px" onchange="listFilter({rowSelector:'#extRequestsCard tbody tr', filterId:'extStatusFilter'})">
        <option value="">All statuses</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
      </select>
      @endif
      @if ($pendingExtension)<span class="badge badge-yellow">1 Pending</span>@endif
      </div>
    </div>
    @if ($extensionRequests->isEmpty())
    <div class="empty-state"><i data-feather="calendar"></i><h3>No extension requests</h3></div>
    @else
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Requested By</th><th>Current End</th><th>Requested End</th><th>Reason</th><th>Status</th><th>Notes</th><th>Action</th></tr>
        </thead>
        <tbody>
          @foreach ($extensionRequests as $er)
          <tr data-filter="{{ $er->status }}">
            <td style="font-weight:600">{{ $er->requested_by_name }}
              <div style="font-size:.72rem;color:var(--muted)">{{ \Illuminate\Support\Carbon::parse($er->created_at)->format('M j g:ia') }}</div>
            </td>
            <td>{{ \Illuminate\Support\Carbon::parse($er->current_end_date)->format('M j, Y') }}</td>
            <td style="font-weight:700;color:var(--blue-700)">{{ \Illuminate\Support\Carbon::parse($er->requested_end_date)->format('M j, Y') }}</td>
            <td style="font-size:.82rem;color:var(--text-secondary);max-width:180px">{{ $er->reason ?: '—' }}</td>
            <td><span class="badge {{ $statusBadgesReq[$er->status] }}">{{ $statusLabelsReq[$er->status] }}</span></td>
            <td style="font-size:.82rem;color:var(--muted)">{{ $er->admin_notes ?: '—' }}</td>
            <td>
              @if ($er->status === 'pending' && $isAdmin)
              <div style="display:flex;gap:5px">
                <button onclick="openApproveReq('approve_extension',{{ $er->extension_id }},0,'Approve extension to {{ \Illuminate\Support\Carbon::parse($er->requested_end_date)->format('M j, Y') }}')"
                        class="btn btn-success btn-sm" style="font-size:.72rem;padding:4px 10px">Approve</button>
                <button onclick="openRejectReq('reject_extension',{{ $er->extension_id }},0)"
                        class="btn btn-danger btn-sm" style="font-size:.72rem;padding:4px 10px">Reject</button>
              </div>
              @else
              <span style="font-size:.75rem;color:var(--muted)">{{ $er->approved_at ? \Illuminate\Support\Carbon::parse($er->approved_at)->format('M j') : '—' }}</span>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>

  <!-- Field Requests (equipment / accessory / crew follow-ups) -->
  <div class="card" id="fieldRequestsCard">
    <div class="card-header">
      <h2 class="card-title">Field Requests</h2>
      <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
      @if ($equipRequests->count() > 0)
      <div class="search-input-wrap" style="min-width:160px">
        <i data-feather="search" style="width:13px;height:13px"></i>
        <input type="text" id="fieldReqSearchTbl" placeholder="Search requests…" oninput="listFilter({rowSelector:'#fieldRequestsCard tbody tr', searchId:'fieldReqSearchTbl', filterId:'fieldReqStatusFilter'})">
      </div>
      <select id="fieldReqStatusFilter" class="form-control" style="width:auto;font-size:12px;padding:7px 10px" onchange="listFilter({rowSelector:'#fieldRequestsCard tbody tr', searchId:'fieldReqSearchTbl', filterId:'fieldReqStatusFilter'})">
        <option value="">All statuses</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
        <option value="dispatched">Dispatched</option>
        <option value="delivered">Delivered</option>
      </select>
      @endif
      @if ($pendingEquipRequests->count() > 0)<span class="badge badge-yellow">{{ $pendingEquipRequests->count() }} Pending</span>@endif
      </div>
    </div>
    <div style="padding:0 20px;font-size:.78rem;color:var(--muted)">Equipment, accessory, and crew follow-ups requested by the client for this booking. Approved items are dispatched (vehicle + driver + ETA) and marked delivered from the <a href="{{ route('field-requests') }}" style="color:var(--accent)">Field Requests queue</a>.</div>
    @if ($equipRequests->isEmpty())
    <div class="empty-state"><i data-feather="camera"></i><h3>No field requests</h3></div>
    @else
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Requested By</th><th>Type</th><th>Item</th><th>Qty</th><th>Reason</th><th>Status</th><th>Dispatch</th><th>Notes</th><th>Action</th></tr>
        </thead>
        <tbody>
          @foreach ($equipRequests as $r)
          <tr data-filter="{{ $r->status }}">
            <td style="font-weight:600">{{ $r->requested_by_name }}
              <div style="font-size:.72rem;color:var(--muted)">{{ \Illuminate\Support\Carbon::parse($r->created_at)->format('M j g:ia') }}</div>
            </td>
            <td><span class="badge badge-gray" style="font-size:.68rem;text-transform:capitalize">{{ $r->item_type }}</span></td>
            <td>
              <div style="font-weight:600">{{ $fieldItemLabel($r) }}</div>
              @if ($r->item_type === 'equipment')<div style="font-size:.72rem;color:var(--muted)">{{ trim($r->brand . ' ' . $r->model) }}</div>@endif
            </td>
            <td>{{ $r->quantity }}</td>
            <td style="font-size:.82rem;color:var(--text-secondary);max-width:160px">{{ $r->reason ?: '—' }}</td>
            <td><span class="badge {{ $statusBadgesField[$r->status] }}">{{ $statusLabelsField[$r->status] }}</span></td>
            <td style="font-size:.78rem;color:var(--muted)">
              @if (in_array($r->status, ['dispatched', 'delivered'], true))
                {{ $r->vehicle_label ?: '—' }}@if($r->driver_name)<br>{{ trim($r->driver_name) }}@endif
                @if($r->eta)<br>ETA {{ \Illuminate\Support\Carbon::parse($r->eta)->format('M j g:ia') }}@endif
              @else
                —
              @endif
            </td>
            <td style="font-size:.82rem;color:var(--muted)">{{ $r->admin_notes ?: '—' }}</td>
            <td>
              @if ($r->status === 'pending' && $isAdmin)
              <div style="display:flex;gap:5px">
                <button onclick="openApproveReq('approve_field_request',0,{{ $r->request_id }},'Approve {{ addslashes($fieldItemLabel($r)) }} (x{{ $r->quantity }})','{{ $r->item_type }}',{{ (int) $r->position_id }})"
                        class="btn btn-success btn-sm" style="font-size:.72rem;padding:4px 10px">Approve</button>
                <button onclick="openRejectReq('reject_field_request',0,{{ $r->request_id }})"
                        class="btn btn-danger btn-sm" style="font-size:.72rem;padding:4px 10px">Reject</button>
              </div>
              @else
              <span style="font-size:.75rem;color:var(--muted)">{{ $r->approved_at ? \Illuminate\Support\Carbon::parse($r->approved_at)->format('M j') : '—' }}</span>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>
</div>

</div>

<!-- Client Feedback (read-only — client submits this from their booking page) -->
@if ($booking->booking_status === 'completed')
<div class="card" style="margin-top:22px">
  <div class="card-header">
    <h2 class="card-title"><i data-feather="star" style="width:15px;height:15px;margin-right:6px;vertical-align:middle"></i>Client Review</h2>
    @if ($feedback)
    <span class="badge badge-green" style="font-size:11px">Reviewed {{ \Illuminate\Support\Carbon::parse($feedback->submitted_at)->format('M j, Y') }}</span>
    @else
    <span class="badge badge-gray" style="font-size:11px">Awaiting client review</span>
    @endif
  </div>
  <div class="card-body">
    @if ($feedback)
    <div style="display:flex;gap:20px;align-items:flex-start">
      <div style="text-align:center;min-width:80px">
        <div style="font-size:2.5rem;font-weight:800;color:var(--accent);line-height:1">{{ $feedback->rating }}</div>
        <div style="color:#f59e0b;font-size:1.2rem;margin:4px 0">{{ str_repeat('★', (int) $feedback->rating) . str_repeat('☆', 5 - (int) $feedback->rating) }}</div>
        <div style="font-size:10px;color:var(--muted)">/ 5 stars</div>
      </div>
      <div style="flex:1">
        @if ($feedback->comment)
        <p style="font-style:italic;color:var(--text);margin-bottom:8px">"{{ $feedback->comment }}"</p>
        @endif
        <div style="font-size:11px;color:var(--muted)">
          Submitted by client on {{ \Illuminate\Support\Carbon::parse($feedback->submitted_at)->format('M j, Y g:i a') }}
        </div>
      </div>
    </div>
    @else
    <p style="font-size:13px;color:var(--muted)">The client has not submitted a review yet. They can rate their experience from their booking page within 30 days of completion.</p>
    @endif
  </div>
</div>
@endif

<!-- APPROVE BOOKING MODAL -->
<div class="modal-overlay" id="modalApproveBooking">
  <div class="modal" style="max-width:520px">
    <div class="modal-header" style="background:var(--success,#15803d);color:#fff;border-radius:10px 10px 0 0">
      <h3 class="modal-title" style="color:#fff"><i data-feather="check-circle" style="width:16px;height:16px;margin-right:8px;vertical-align:middle"></i>Approve Booking</h3>
      <button class="modal-close" style="color:#fff"><i data-feather="x"></i></button>
    </div>
    <form method="POST" action="{{ $actionUrl }}">
      @csrf
      <input type="hidden" name="action" value="approve_booking">
      <div class="modal-body">
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px 16px;margin-bottom:16px">
          <div style="font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#16a34a;font-weight:700;margin-bottom:10px">Booking Summary</div>
          <div style="display:grid;grid-template-columns:120px 1fr;gap:4px 8px;font-size:13px">
            <span style="color:#64748b;font-weight:600">Reference</span>
            <span style="font-weight:700;font-family:monospace">{{ $booking->booking_reference }}</span>
            <span style="color:#64748b;font-weight:600">Client</span>
            <span>{{ $booking->company_name ?: $booking->contact_person }}</span>
            <span style="color:#64748b;font-weight:600">Project</span>
            <span>{{ $booking->project_title ?: '—' }}</span>
            <span style="color:#64748b;font-weight:600">Shoot Dates</span>
            <span>{{ \Carbon\Carbon::parse($booking->shoot_date_start)->format('M j') }} – {{ \Carbon\Carbon::parse($booking->shoot_date_end)->format('M j, Y') }}</span>
            <span style="color:#64748b;font-weight:600">Equipment</span>
            <span>{{ $equipmentLines->count() }} item(s) on booking</span>
          </div>
        </div>
        @if ($booking->client_type === 'first_time')
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:7px;padding:11px 14px;font-size:12.5px;color:#92400e;margin-bottom:12px">
          <strong>⚠ New Customer:</strong> 50% downpayment (₱{{ number_format($booking->final_amount * 0.5, 2) }}) must be collected before equipment can be released.
        </div>
        @endif
        <p style="font-size:13px;color:#475569;line-height:1.6">Approving will move this booking to <strong>Confirmed</strong> status. You can then assign crew, add equipment, and release items for the shoot.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-success"><i data-feather="check-circle"></i> Confirm Approval</button>
      </div>
    </form>
  </div>
</div>

<!-- REJECT BOOKING MODAL -->
<div class="modal-overlay" id="modalRejectBooking">
  <div class="modal" style="max-width:480px">
    <div class="modal-header" style="background:var(--red,#dc2626);color:#fff;border-radius:10px 10px 0 0">
      <h3 class="modal-title" style="color:#fff"><i data-feather="x-circle" style="width:16px;height:16px;margin-right:8px;vertical-align:middle"></i>Reject Booking</h3>
      <button class="modal-close" style="color:#fff"><i data-feather="x"></i></button>
    </div>
    <form method="POST" action="{{ $actionUrl }}">
      @csrf
      <input type="hidden" name="action" value="reject_booking">
      <div class="modal-body">
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:#991b1b">
          <strong>{{ $booking->booking_reference }}</strong> — {{ $booking->project_title ?: 'No project title' }}
        </div>
        <div class="form-group" style="margin-bottom:12px">
          <label style="font-size:12px;font-weight:700;color:#374151;margin-bottom:5px;display:block">Rejection Reason *</label>
          <select name="rejection_reason" class="form-control" id="rejectReasonSel" onchange="toggleCustomReason(this.value)" required>
            <option value="">— Select a reason —</option>
            <option value="Dates not available">Dates not available</option>
            <option value="Equipment not available for requested dates">Equipment unavailable</option>
            <option value="Incomplete booking information">Incomplete booking information</option>
            <option value="Outside service area">Outside service area</option>
            <option value="Budget requirements not met">Budget requirements not met</option>
            <option value="__other__">Other (specify below)</option>
          </select>
        </div>
        <div class="form-group" id="customReasonWrap" style="display:none;margin-bottom:0">
          <label style="font-size:12px;font-weight:700;color:#374151;margin-bottom:5px;display:block">Specify Reason</label>
          <textarea name="rejection_reason_custom" id="customReasonTxt" class="form-control" rows="3" placeholder="Describe the reason for rejection…" style="resize:vertical"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-danger" onclick="return prepareReject()"><i data-feather="x-circle"></i> Reject Booking</button>
      </div>
    </form>
  </div>
</div>

<!-- ADD EQUIPMENT MODAL -->
<div class="modal-overlay" id="modalAddEquip">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <h3 class="modal-title"><i data-feather="camera" style="width:16px;height:16px;margin-right:8px;vertical-align:middle"></i>Add Equipment</h3>
      <button class="modal-close"><i data-feather="x"></i></button>
    </div>
    <form method="POST" action="{{ $actionUrl }}" id="formAddEquip">
      @csrf
      <input type="hidden" name="action" value="add_equipment">
      <div class="modal-body">
        <div class="form-group">
          <label>Equipment *</label>
          <input type="text" id="equipSearch" class="form-control" placeholder="Search by name, category, brand…" autocomplete="off" oninput="filterEquipList(this.value)">
          <div class="srch-list" id="equipList"></div>
          <input type="hidden" name="equipment_id" id="selectedEquipId">
        </div>
        <div class="form-row">
          <div class="form-group"><label>Quantity</label><input type="number" name="quantity" class="form-control" value="1" min="1" required></div>
          <div class="form-group"><label>Days</label><input type="number" name="days" class="form-control" value="1" min="1" required></div>
          <div class="form-group"><label>Daily Rate (₱)</label><input type="number" name="daily_rate" id="equipRate" class="form-control" step="0.01" min="0" required></div>
        </div>
        <div class="form-group"><label>Notes</label><input type="text" name="notes" class="form-control" placeholder="e.g. with tripod, no battery"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="plus"></i> Add Line</button>
      </div>
    </form>
  </div>
</div>

<!-- CLOSE PROJECT — Part 9 -->
<div class="modal-overlay" id="modalCloseProject">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <h3><i data-feather="check-square" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Close Project</h3>
      <button class="modal-close" onclick="closeModal('modalCloseProject')">&times;</button>
    </div>
    <div class="modal-body">
      <div style="font-size:1rem;font-weight:700;margin-bottom:4px">
        &ldquo;{{ $booking->project_title ?: $booking->booking_reference }}&rdquo;
      </div>
      <div style="font-size:.85rem;color:var(--text-muted);margin-bottom:16px">
        @if ($ce && $ce->status === 'confirmed')
          The latest cost estimate is already confirmed. What would you like to do before closing?
        @else
          The latest cost estimate is still a draft. What would you like to do before closing?
        @endif
      </div>

      <form method="POST" action="{{ $actionUrl }}" style="margin-bottom:8px">
        @csrf
        <input type="hidden" name="action" value="close_project">
        <input type="hidden" name="mode" value="confirm">
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"
                @if ($ce && $ce->status === 'confirmed') disabled title="Already confirmed" @endif>
          Re-save &amp; close · marks it confirmed
        </button>
      </form>

      <form method="POST" action="{{ $actionUrl }}" style="margin-bottom:8px">
        @csrf
        <input type="hidden" name="action" value="close_project">
        <input type="hidden" name="mode" value="draft">
        <button type="submit" class="btn btn-outline" style="width:100%;justify-content:center;border-color:var(--red);color:var(--red)">
          Save &amp; move to draft
        </button>
      </form>

      <a href="{{ route('bookings') }}" class="btn btn-outline" style="width:100%;justify-content:center;border-color:var(--red);color:var(--red);margin-bottom:8px">
        Close without saving
      </a>

      <button type="button" class="btn" style="width:100%;justify-content:center;background:none;color:var(--text-muted)"
              onclick="closeModal('modalCloseProject')">
        Cancel — keep working
      </button>
    </div>
  </div>
</div>

<!-- SET DISCOUNT MODAL -->
<div class="modal-overlay" id="modalSetDiscount">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <h3><i data-feather="percent" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Set Discount</h3>
      <button class="modal-close" onclick="closeModal('modalSetDiscount')">&times;</button>
    </div>
    <form method="POST" action="{{ $actionUrl }}">
      @csrf
      <input type="hidden" name="action" value="set_discount">
      <div class="modal-body">
        <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:14px">
          For when a client calls in and agrees to a discount directly — this applies immediately, with no separate approval step, and is recorded under your name.
        </div>
        <div class="form-group">
          <label>Discount Type</label>
          <select name="discount_type" id="setDiscType" onchange="toggleSetDiscInput()" class="form-control">
            <option value="flat">Flat amount (₱)</option>
            <option value="percent">Percentage (%)</option>
            <option value="package">Package price (₱)</option>
          </select>
        </div>
        <div class="form-group">
          <label id="setDiscValueLabel">Amount (₱)</label>
          <input type="number" name="discount_value" id="setDiscValue" step="0.01" min="0.01" class="form-control" required>
          <div id="setDiscPackageNote" style="display:none;font-size:.72rem;color:var(--text-muted);margin-top:4px">
            Enter the total the client should pay (before VAT rules apply as usual) — the discount is calculated automatically from the current itemized total (₱{{ number_format($bd['listed_total'] ?? 0, 2) }}).
          </div>
        </div>
        <div class="form-group">
          <label>Reason</label>
          <textarea name="reason" class="form-control" rows="2" placeholder="e.g. Client called, agreed to a loyalty discount"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalSetDiscount')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="check"></i> Set &amp; Apply</button>
      </div>
    </form>
  </div>
</div>


<!-- FIELD ADD EQUIPMENT MODAL -->
<div class="modal-overlay" id="modalFieldAdd">
  <div class="modal" style="max-width:620px">
    <div class="modal-header" style="background:#fffbeb;border-bottom:2px solid #fbbf24">
      <h3 class="modal-title" style="color:#d97706"><i data-feather="plus-circle" style="width:16px;height:16px;margin-right:8px;vertical-align:middle"></i>Add Equipment to Field</h3>
      <button class="modal-close"><i data-feather="x"></i></button>
    </div>
    <div style="padding:9px 20px;font-size:12px;background:#fffbeb;border-bottom:1px solid #fde68a;color:#92400e;display:flex;align-items:center;gap:8px">
      <i data-feather="alert-triangle" style="width:13px;height:13px;flex-shrink:0"></i>
      <span>Booking is <strong>ongoing</strong>. Added equipment will be <strong>immediately released to field</strong> and the cost estimate will update. An operator assignment is required.</span>
    </div>
    <form method="POST" action="{{ $actionUrl }}" id="formFieldAdd">
      @csrf
      <input type="hidden" name="action" value="field_add_equipment">
      <div class="modal-body">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:10px">Equipment</div>
        <div class="form-group">
          <label>Equipment *</label>
          <input type="text" id="faEquipSearch" class="form-control" placeholder="Search by name, category, brand…" autocomplete="off" oninput="filterFaEquipList(this.value)">
          <div class="srch-list" id="faEquipList"></div>
          <input type="hidden" name="equipment_id" id="faEquipId">
        </div>
        <div class="form-row">
          <div class="form-group"><label>Qty</label><input type="number" name="quantity" class="form-control" value="1" min="1" required></div>
          <div class="form-group"><label>Days</label><input type="number" name="days" class="form-control" value="1" min="1" required></div>
          <div class="form-group"><label>Rate/Day (₱)</label><input type="number" name="daily_rate" id="faEquipRate" class="form-control" step="0.01" min="0" required></div>
        </div>
        <div class="form-group"><label>Equipment Notes</label><input type="text" name="notes" class="form-control" placeholder="e.g. with tripod, extra battery"></div>

        <hr style="margin:16px 0;border:none;border-top:1px solid var(--border)">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:10px">Operator <span style="color:var(--red)">— required</span></div>

        <div class="form-group">
          <label>Crew Member *</label>
          <input type="text" id="faCrewSearch" class="form-control" placeholder="Search crew by name or position…" autocomplete="off" oninput="filterFaCrewList(this.value)">
          <div class="srch-list" id="faCrewList"></div>
          <input type="hidden" name="crew_id" id="faCrewId">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Position *</label>
            <select name="position_id" class="form-control" required>
              <option value="">— Select —</option>
              @foreach ($positions as $pos)<option value="{{ $pos->position_id }}">{{ $pos->position_name }}</option>@endforeach
            </select>
          </div>
          <div class="form-group"><label>Rate (₱/12hr)</label><input type="number" name="crew_rate" id="faCrewRate" class="form-control" step="0.01" min="0" required></div>
        </div>
        <div class="form-group"><label>Crew Notes</label><input type="text" name="crew_notes" class="form-control" placeholder="Optional notes"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary" style="background:#d97706;border-color:#d97706"><i data-feather="send"></i> Add to Field</button>
      </div>
    </form>
  </div>
</div>

<!-- ASSIGN TRANSPORT MODAL -->
@php
  $driverCrewList = $availCrew->filter(fn ($c) => stripos($c->position_name ?? '', 'driver') !== false);
  if ($driverCrewList->isEmpty()) $driverCrewList = $availCrew;
  $_atZone = $booking->location_zone ?? '';
  $_atTransCost = number_format((float) ($booking->transportation_cost ?? 0), 2, '.', '');
  $_atVid = (int) ($booking->vehicle_rate_id ?? 0);
@endphp
<div class="modal-overlay" id="modalAssignTransport">
  <div class="modal" style="max-width:520px">
    <div class="modal-header" style="background:#f0f9ff;border-bottom:1px solid #bae6fd">
      <div class="modal-title" style="color:#0369a1"><i data-feather="truck" style="width:16px;height:16px;margin-right:8px;vertical-align:middle"></i>Assign Transport</div>
      <button class="modal-close">&times;</button>
    </div>
    <form method="POST" action="{{ $actionUrl }}">
      @csrf
      <input type="hidden" name="action" value="assign_transport">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:14px">
        <div class="form-group" style="margin-bottom:0">
          <label>Location Zone <span style="font-size:.75rem;font-weight:400;color:var(--muted)">— determines transport cost multiplier</span></label>
          <select name="location_zone" id="atZone" class="form-control" onchange="atRecalc()">
            <option value="">— No zone / no transport —</option>
            <option value="manila" {{ $_atZone === 'manila' ? 'selected' : '' }} data-mult="1.0">Metro Manila / NCR (0–60 km) — 1×</option>
            <option value="luzon" {{ $_atZone === 'luzon' ? 'selected' : '' }} data-mult="1.5">Luzon Near (61–150 km) — 1.5×</option>
            <option value="luzon_far" {{ $_atZone === 'luzon_far' ? 'selected' : '' }} data-mult="2.0">Luzon Far (151 km+) — 2×</option>
          </select>
        </div>

        @if ($vehicleRates->count())
        <div class="form-group" style="margin-bottom:0">
          <label>Transport Vehicle <span style="font-size:.75rem;font-weight:400;color:var(--muted)">— optional, shows a suggested cost below</span></label>
          <select name="vehicle_rate_id" id="atVehicle" class="form-control" onchange="atRecalc()">
            <option value="0">— No vehicle —</option>
            @foreach ($vehicleRates as $vr)
            <option value="{{ (int) $vr->vehicle_id }}" data-rate="{{ $vr->base_rate }}" {{ $_atVid === (int) $vr->vehicle_id ? 'selected' : '' }}>
              {{ $vr->label }} — ₱{{ number_format($vr->base_rate, 2) }} base
            </option>
            @endforeach
          </select>
        </div>
        @endif

        <div class="form-group" style="margin-bottom:0">
          <label>Transport Cost (₱) <span style="font-size:.75rem;font-weight:400;color:var(--muted)">— enter the actual price manually</span></label>
          <input type="number" name="transport_cost" id="atCost" class="form-control" step="0.01" min="0" required value="{{ $_atTransCost }}" placeholder="0.00">
          <div id="atCostNote" style="font-size:.73rem;color:var(--muted);margin-top:3px"></div>
        </div>

        <hr style="margin:4px 0;border-color:var(--border)">
        <div style="font-size:.78rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.05em">Driver — Optional</div>

        <div class="form-group" style="margin-bottom:0">
          <label>Assign Driver <span style="font-size:.75rem;font-weight:400;color:var(--muted)">— leave blank if no driver needed</span></label>
          <select name="driver_crew_id" id="atDriver" class="form-control"
                  onchange="const r=parseFloat(this.options[this.selectedIndex].dataset.rate||0);document.getElementById('atDriverRate').value=r?r.toFixed(2):''">
            <option value="">— No driver —</option>
            @foreach ($driverCrewList as $c)
            @php
              $driverAlready = $assignedCrewIds->contains((int) $c->crew_id);
              $driverBusy = ! empty($c->busy_on_booking);
              $driverOff = ! empty($c->unavailability_reason);
              $driverBlocked = $driverBusy || $driverOff;
              $driverNote = $driverAlready ? ' — already assigned' : ($driverBusy ? ' — Busy: ' . $c->busy_on_booking : ($driverOff ? ' — Off: ' . $c->unavailability_reason : ''));
              $driverStyle = $driverBlocked ? 'color:#9ca3af' : ($driverAlready ? 'color:var(--muted)' : '');
            @endphp
            <option value="{{ (int) $c->crew_id }}" data-rate="{{ $c->base_rate_12hr }}" {{ $driverBlocked ? 'disabled' : '' }} style="{{ $driverStyle }}">
              {{ $c->name }}{{ $c->position_name ? ' (' . $c->position_name . ')' : '' }}{{ $driverNote }}
            </option>
            @endforeach
          </select>
        </div>

        <div class="form-group" style="margin-bottom:0">
          <label>Driver Rate (₱/12hr) <span style="font-size:.75rem;font-weight:400;color:var(--muted)">— leave blank to use registry rate</span></label>
          <input type="number" id="atDriverRate" name="driver_rate" class="form-control" step="0.01" min="0" placeholder="auto">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary" style="background:#0369a1;border-color:#0369a1"><i data-feather="save"></i> Save Transport</button>
      </div>
    </form>
  </div>
</div>

<!-- ASSIGN CREW MODAL -->
<style>
.crew-slot-btn{display:block;width:100%;padding:8px 10px;border-radius:8px;border:1.5px solid transparent;background:transparent;cursor:pointer;transition:background .14s,border-color .14s;margin-bottom:4px;text-align:left}
.crew-slot-btn:hover{background:var(--hover,rgba(0,0,0,.04));border-color:var(--border)}
.crew-slot-btn.active{background:var(--bluelt,#eff6ff);border-color:var(--blue2,#93c5fd)}
.crew-card{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:9px;border:1.5px solid var(--border2,#e2e8f0);background:#fff;cursor:pointer;transition:border-color .12s,background .12s,box-shadow .12s;margin-bottom:6px;user-select:none}
.crew-card:hover{border-color:var(--blue2,#93c5fd);background:#f8fbff;box-shadow:0 1px 6px rgba(59,130,246,.08)}
.crew-card.selected{border-color:var(--blue,#3b82f6);background:#eff6ff;box-shadow:0 2px 8px rgba(59,130,246,.12)}
.crew-avatar{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.7rem;color:#fff;flex-shrink:0;letter-spacing:.3px}
.crew-slot-drop{display:none;position:absolute;left:0;right:0;top:calc(100% + 3px);background:var(--surface);border:1px solid var(--border);border-radius:8px;box-shadow:0 6px 24px rgba(0,30,80,.14);z-index:9999;max-height:190px;overflow-y:auto;min-width:210px}
.csd-item{padding:8px 12px;cursor:pointer;border-bottom:1px solid var(--border);transition:background .1s}
.csd-item:last-child{border-bottom:none}
.csd-item:hover{background:var(--acclight,#e5f0ff)}
.csd-name{font-weight:600;font-size:.82rem;color:var(--text)}
.csd-sub{font-size:.71rem;color:var(--muted);margin-top:1px}
.csd-empty{padding:10px 13px;font-size:.8rem;color:var(--muted);text-align:center}
</style>
@php
  $_crewModalSlots = $equipOpRequirements->filter(fn ($r) => $r->req_count > 0)->values();
@endphp
<div class="modal-overlay" id="modalAddCrew">
  <div class="modal" style="max-width:1040px;width:96vw;max-height:90vh;display:flex;flex-direction:column">
    <div class="modal-header" style="flex-shrink:0">
      <h3 class="modal-title"><i data-feather="users" style="width:16px;height:16px;margin-right:8px;vertical-align:middle"></i>Assign Crew Members</h3>
      <button class="modal-close"><i data-feather="x"></i></button>
    </div>
    <form method="POST" action="{{ $actionUrl }}" id="formAddCrew" style="display:flex;flex-direction:column;overflow:hidden;flex:1;min-height:0">
      @csrf
      <input type="hidden" name="action" value="batch_add_crew">

      <div style="display:flex;flex:1;overflow:hidden;min-height:0">

        <div style="width:260px;flex-shrink:0;border-right:1px solid var(--border);background:var(--surface,#f8fafc);display:flex;flex-direction:column;overflow:hidden">
          <div style="padding:10px 10px 6px;flex-shrink:0">
            <div style="position:relative">
              <i data-feather="search" style="width:13px;height:13px;position:absolute;left:9px;top:50%;transform:translateY(-50%);color:var(--muted);pointer-events:none"></i>
              <input type="text" id="slotSearch" placeholder="Search slots…" oninput="filterSlots(this.value)"
                     style="width:100%;padding:6px 8px 6px 28px;font-size:.78rem;border:1px solid var(--border);border-radius:7px;background:var(--bg);color:var(--text);box-sizing:border-box;outline:none">
            </div>
          </div>
          <div style="overflow-y:auto;flex:1;padding:0 10px 14px" id="slotScrollArea">

          @if ($_crewModalSlots->count())
          <div style="font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--muted);margin-bottom:9px;padding:0 4px">Equipment Slots</div>
          @foreach ($_crewModalSlots as $slot)
          @php
            $slotCovered = $equipCoverage[(int) $slot->equipment_id] ?? [];
            $isCovered = ! empty($slotCovered);
            $slotPosIds = array_values(array_filter(array_map('intval', explode(',', $slot->required_position_ids ?? ''))));
            $slotJson = e(json_encode([
              'equipment_id' => (int) $slot->equipment_id,
              'equipment_name' => $slot->equipment_name,
              'position_ids' => $slotPosIds,
              'positions_label' => $slot->required_positions ?? '',
            ]));
          @endphp
          <button type="button" class="crew-slot-btn {{ $isCovered ? 'covered' : '' }}" data-equip-id="{{ (int) $slot->equipment_id }}" onclick='fillCrewSlot({{ $slotJson }})'>
            <div style="display:flex;align-items:center;gap:9px">
              <div style="width:30px;height:30px;border-radius:7px;background:{{ $isCovered ? '#dcfce7' : '#eff6ff' }};display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i data-feather="{{ $isCovered ? 'check-circle' : 'camera' }}" style="width:13px;height:13px;color:{{ $isCovered ? '#16a34a' : '#3b82f6' }}"></i>
              </div>
              <div style="flex:1;min-width:0">
                <div style="font-weight:600;font-size:.78rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--text)">{{ $slot->equipment_name }}</div>
                <div style="font-size:.68rem;margin-top:2px;color:{{ $isCovered ? '#16a34a' : 'var(--muted)' }}">
                  {{ $isCovered ? implode(', ', $slotCovered) : ($slot->required_positions ?: 'Operator needed') }}
                </div>
              </div>
              @if (! $isCovered)<div style="width:7px;height:7px;border-radius:50%;background:#f59e0b;flex-shrink:0;margin-left:2px"></div>@endif
            </div>
          </button>
          @endforeach
          <div style="margin:10px 4px 12px;border-top:1px solid var(--border)"></div>
          @endif

          <button type="button" class="crew-slot-btn active" id="slotGeneral" onclick="clearCrewSlotCtx()">
            <div style="display:flex;align-items:center;gap:9px">
              <div style="width:30px;height:30px;border-radius:7px;background:var(--surface2,#f1f5f9);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i data-feather="users" style="width:13px;height:13px;color:var(--muted)"></i>
              </div>
              <div>
                <div style="font-weight:600;font-size:.78rem;color:var(--text)">General</div>
                <div style="font-size:.68rem;color:var(--muted)">Not equipment-specific</div>
              </div>
            </div>
          </button>

          @if ($driverNeeded && ! $driverCount)
          <div style="margin-top:14px;background:#fffbeb;border:1px solid #fde68a;border-radius:7px;padding:10px;font-size:.72rem;color:#92400e;line-height:1.45">
            <i data-feather="alert-triangle" style="width:11px;height:11px;vertical-align:middle;margin-right:3px"></i>
            <strong>Driver needed</strong><br>Use the Assign Driver button on the booking.
          </div>
          @endif
          </div>
        </div>

        <div style="flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0">

          <div id="cfgSlotBanner" style="display:none;padding:9px 16px;background:#eff6ff;border-bottom:1px solid #bfdbfe;flex-shrink:0">
            <div style="display:flex;align-items:center;gap:8px">
              <i data-feather="camera" style="width:13px;height:13px;color:#3b82f6;flex-shrink:0"></i>
              <span style="font-size:.78rem;color:#1d4ed8;flex:1">
                Operator for: <strong id="cfgSlotName"></strong>
                &ensp;<span id="cfgSlotPos" style="background:#dbeafe;border-radius:4px;padding:1px 7px;font-size:.68rem;font-weight:700;color:#1e40af"></span>
              </span>
              <button type="button" onclick="clearCrewSlotCtx()" style="background:none;border:none;cursor:pointer;color:#3b82f6;font-size:20px;line-height:1;padding:0 2px;flex-shrink:0">×</button>
            </div>
          </div>

          <div style="padding:12px 14px;border-bottom:1px solid var(--border);flex-shrink:0;display:flex;gap:8px;align-items:center">
            <div style="flex:1;position:relative">
              <i data-feather="search" style="width:13px;height:13px;position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--muted);pointer-events:none"></i>
              <input type="text" id="cfgCrewSearch" class="form-control" placeholder="Search by name…" autocomplete="off" oninput="filterCfgCrew(this.value)" style="padding-left:30px;height:36px;font-size:.83rem">
            </div>
            <select id="cfgPosition" class="form-control" onchange="onCfgPositionChange()" style="width:160px;flex-shrink:0;height:36px;font-size:.82rem">
              <option value="">All positions</option>
              @foreach ($positions as $pos)
              <option value="{{ $pos->position_id }}" data-name="{{ $pos->position_name }}">{{ $pos->position_name }}</option>
              @endforeach
            </select>
          </div>

          <div id="cfgCrewList" style="flex:1;overflow-y:auto;padding:12px 14px"></div>

          <div style="padding:10px 14px;border-top:1px solid var(--border);background:var(--surface,#f8fafc);flex-shrink:0;display:flex;align-items:center;gap:10px">
            <i data-feather="link" style="width:13px;height:13px;color:var(--muted);flex-shrink:0"></i>
            <select id="cfgEquipLink" class="form-control" style="flex:1;font-size:.82rem;height:34px">
              <option value="">— General assignment —</option>
              @foreach ($equipmentLines as $el)
              <option value="{{ $el->equipment_id }}">{{ $el->equipment_name }}
                @foreach ($equipOpRequirements as $req)
                  @if ((int) $req->equipment_id === (int) $el->equipment_id && $req->required_positions) — needs: {{ $req->required_positions }}@endif
                @endforeach
              </option>
              @endforeach
            </select>
            <div id="cfgSelectedBadge" style="display:none;background:var(--blue,#3b82f6);color:#fff;border-radius:20px;padding:3px 12px;font-size:.73rem;font-weight:700;white-space:nowrap;flex-shrink:0">0 selected</div>
          </div>
        </div>
      </div>

      <div id="batchInputs" style="display:none"></div>

      <div class="modal-footer" style="flex-shrink:0">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" id="btnAssignAll" class="btn btn-primary" disabled onclick="return prepareCrewBatch()">
          <i data-feather="user-check" style="width:14px;height:14px"></i> Assign <span id="assignAllCount">0</span> Selected
        </button>
      </div>
    </form>
  </div>
</div>

<!-- RELEASE EQUIPMENT MODAL -->
<div class="modal-overlay" id="modalRelease">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <div class="modal-title"><i data-feather="log-out"></i> Release Equipment</div>
      <button class="modal-close">&times;</button>
    </div>
    <form method="POST" action="{{ $actionUrl }}">
      @csrf
      <input type="hidden" name="action" value="checkout">
      <input type="hidden" name="equipment_id" id="rel_eid">
      <div class="modal-body">
        <div style="font-size:.83rem;color:var(--muted);margin-bottom:14px">Releasing: <strong id="rel_ename" style="color:var(--text)"></strong></div>
        <div class="form-group">
          <label>Condition Before Release *</label>
          <select name="condition_out" class="form-control" required>
            <option value="excellent">Excellent</option>
            <option value="good" selected>Good</option>
            <option value="fair">Fair</option>
          </select>
        </div>
        <div class="form-group">
          <label>Release Notes</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Accessories included, special instructions…"></textarea>
        </div>
        <div style="background:rgba(59,130,246,.07);border:1px solid rgba(59,130,246,.2);border-radius:7px;padding:10px 12px;font-size:.8rem;color:var(--blue)">
          <i data-feather="info" style="width:13px;height:13px;margin-right:4px;vertical-align:middle"></i>
          Releasing will mark the equipment as <strong>In Field</strong> and set booking to <strong>Ongoing</strong>.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="log-out"></i> Confirm Release</button>
      </div>
    </form>
  </div>
</div>

<!-- RETURN EQUIPMENT MODAL -->
<div class="modal-overlay" id="modalReturn">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <div class="modal-title"><i data-feather="log-in"></i> Return Equipment</div>
      <button class="modal-close">&times;</button>
    </div>
    <form method="POST" action="{{ $actionUrl }}">
      @csrf
      <input type="hidden" name="action" value="checkin">
      <input type="hidden" name="equipment_id" id="ret_eid">
      <div class="modal-body">
        <div style="font-size:.83rem;color:var(--muted);margin-bottom:14px">Returning: <strong id="ret_ename" style="color:var(--text)"></strong></div>
        <div id="latePenaltyInfo" style="display:none;background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.25);border-radius:7px;padding:10px 12px;font-size:.82rem;color:#b45309;margin-bottom:14px">
          <i data-feather="clock" style="width:13px;height:13px;margin-right:4px;vertical-align:middle"></i>
          <strong>Late Return:</strong> <span id="latePenaltyText"></span>
        </div>
        <div class="form-group">
          <label>Condition Upon Return *</label>
          <select name="condition_in" id="ret_condition" class="form-control" required onchange="toggleDamageFields(this.value)">
            <option value="excellent">Excellent</option>
            <option value="good" selected>Good</option>
            <option value="fair">Fair</option>
            <option value="damaged">Damaged</option>
            <option value="missing">Missing</option>
          </select>
        </div>
        <div id="damageFields" style="display:none;background:rgba(248,113,113,.06);border:1px solid rgba(248,113,113,.2);border-radius:8px;padding:14px;margin-bottom:14px">
          <div style="font-size:.78rem;font-weight:700;color:var(--red);text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px">Incident Details</div>
          <div class="form-row">
            <div class="form-group">
              <label>Cause</label>
              <select name="cause" class="form-control">
                <option value="negligence">Negligence</option>
                <option value="accident" selected>Accident</option>
                <option value="lifespan">Normal Wear</option>
                <option value="unknown">Unknown</option>
              </select>
            </div>
            <div class="form-group"><label>Charge Amount (₱)</label><input type="number" name="charge_amount" id="ret_charge" class="form-control" step="0.01" min="0" value="0"></div>
          </div>
          <div class="form-group"><label>Description</label><textarea name="description" class="form-control" rows="2" placeholder="Describe the damage or how the item went missing…"></textarea></div>
          <div class="form-group">
            <label>Payment Mode</label>
            <select name="payment_mode" class="form-control">
              <option value="lump_sum">Lump Sum</option>
              <option value="installment">Installment</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label>Return Notes</label><textarea name="notes" class="form-control" rows="2" placeholder="Accessories returned, missing items, other observations…"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="log-in"></i> Confirm Return</button>
      </div>
    </form>
  </div>
</div>

<!-- EXTEND RENTAL MODAL -->
<div class="modal-overlay" id="modalExtendRental">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <h3><i data-feather="calendar" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Extend Rental</h3>
      <button class="modal-close" onclick="closeModal('modalExtendRental')">&times;</button>
    </div>
    <form method="POST" action="{{ $actionUrl }}">
      @csrf
      <input type="hidden" name="action" value="extend_rental">
      <div class="modal-body">
        <div style="background:var(--blue-50);border:1px solid var(--blue-200);color:var(--blue-700);padding:10px 12px;border-radius:6px;font-size:12px;margin-bottom:14px">
          Current end date: <strong>{{ \Carbon\Carbon::parse($booking->shoot_date_end)->format('M j, Y') }}</strong>
        </div>
        <div class="form-group">
          <label>Extra Days to Add <span style="color:var(--red)">*</span></label>
          <input type="number" name="extra_days" class="form-control" min="1" max="60" value="1" required>
          <div style="font-size:.75rem;color:var(--text-muted);margin-top:4px">Equipment day counts and subtotals will update automatically.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalExtendRental')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="calendar" style="width:13px;height:13px;margin-right:4px;vertical-align:middle"></i>Extend Rental</button>
      </div>
    </form>
  </div>
</div>

<!-- RECORD PAYMENT MODAL -->
@php
  $paidSoFar = $payments->sum('amount');
  $remaining = max(0, $booking->final_amount - $paidSoFar);
@endphp
<div class="modal-overlay" id="modalRecordPayment">
  <div class="modal" style="max-width:500px">
    <div class="modal-header">
      <h3><i data-feather="credit-card" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Record Payment</h3>
      <button class="modal-close" onclick="closeModal('modalRecordPayment')">&times;</button>
    </div>
    <form method="POST" action="{{ $actionUrl }}">
      @csrf
      <input type="hidden" name="action" value="record_payment">
      <div class="modal-body">
        @if ($booking->final_amount > 0)
        <div style="display:flex;gap:16px;margin-bottom:14px;flex-wrap:wrap">
          <div style="flex:1;background:var(--blue-50);border:1px solid var(--blue-200);border-radius:6px;padding:10px 14px">
            <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:3px">Total Amount</div>
            <div style="font-weight:700;color:var(--blue-700)">₱{{ number_format($booking->final_amount, 2) }}</div>
          </div>
          <div style="flex:1;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:10px 14px">
            <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:3px">Paid So Far</div>
            <div style="font-weight:700;color:var(--success)">₱{{ number_format($paidSoFar, 2) }}</div>
          </div>
          <div style="flex:1;background:#fff7ed;border:1px solid #fed7aa;border-radius:6px;padding:10px 14px">
            <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:3px">Balance</div>
            <div style="font-weight:700;color:var(--orange)">₱{{ number_format($remaining, 2) }}</div>
          </div>
        </div>
        @endif
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group">
            <label>Payment Type <span style="color:var(--red)">*</span></label>
            <select name="payment_type" class="form-control" required>
              <option value="downpayment">Downpayment</option>
              <option value="progress">Progress Payment</option>
              <option value="final" selected>Final Payment</option>
            </select>
          </div>
          <div class="form-group">
            <label>Payment Method <span style="color:var(--red)">*</span></label>
            <select name="payment_method" id="bdPayMethod" class="form-control" required onchange="toggleBdRefField()">
              <option value="cash" selected>Cash</option>
              <option value="gcash">GCash</option>
            </select>
          </div>
          <div class="form-group">
            <label>Amount <span style="color:var(--red)">*</span></label>
            <input type="number" name="amount" class="form-control" step="0.01" min="0.01" value="{{ $remaining ?: '' }}" required>
          </div>
          <div class="form-group">
            <label>Payment Date <span style="color:var(--red)">*</span></label>
            <input type="date" name="payment_date" class="form-control" value="{{ date('Y-m-d') }}" required>
          </div>
          <div class="form-group" id="bdRefWrap" style="display:none">
            <label>GCash Reference / Transaction ID</label>
            <input type="text" name="reference_number" class="form-control" placeholder="GCash transaction ID">
          </div>
        </div>
        <div class="form-group">
          <label>Notes</label>
          <input type="text" name="notes" class="form-control" placeholder="Optional notes">
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label style="display:flex;align-items:center;gap:8px;font-weight:500;cursor:pointer">
            <input type="checkbox" name="is_vat" value="1">
            Issue as Official Receipt (VAT)
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalRecordPayment')">Cancel</button>
        <button type="submit" class="btn btn-success"><i data-feather="check" style="width:13px;height:13px;margin-right:4px;vertical-align:middle"></i>Save Payment</button>
      </div>
    </form>
  </div>
</div>

<!-- INCIDENT UPDATE MODAL -->
<div class="modal-overlay" id="modalIncident">
  <div class="modal" style="max-width:520px">
    <div class="modal-header" style="background:#fef2f2;border-bottom:2px solid #fecaca">
      <div class="modal-title" style="color:#991b1b"><i data-feather="alert-triangle" style="width:15px;height:15px;margin-right:7px;vertical-align:middle"></i>Update Incident Report</div>
      <button class="modal-close">&times;</button>
    </div>
    <form method="POST" action="{{ $actionUrl }}">
      @csrf
      <input type="hidden" name="action" value="update_incident">
      <input type="hidden" name="incident_id" id="inc_id">
      <div class="modal-body">
        <div style="font-size:.83rem;color:var(--muted);margin-bottom:16px;padding:8px 12px;background:var(--s3);border-radius:6px">
          Equipment: <strong id="inc_equip" style="color:var(--text)"></strong>
        </div>
        <div class="form-group">
          <label>Description <span style="color:var(--red)">*</span></label>
          <textarea name="description" id="inc_description" class="form-control" rows="3" placeholder="Describe the damage, what happened, where it was found…" style="resize:vertical"></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Cause</label>
            <select name="cause" id="inc_cause" class="form-control">
              <option value="unknown">Unknown</option>
              <option value="accident">Accident</option>
              <option value="negligence">Negligence</option>
              <option value="wear">Normal Wear</option>
              <option value="weather">Weather/Environment</option>
              <option value="theft">Theft</option>
            </select>
          </div>
          <div class="form-group"><label>Charge Amount (₱)</label><input type="number" name="charge_amount" id="inc_charge" class="form-control" step="0.01" min="0" placeholder="0.00"></div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Resolution</label>
            <select name="resolution" id="inc_resolution" class="form-control">
              <option value="pending">Pending</option>
              <option value="repair">Repair</option>
              <option value="replacement">Replacement</option>
              <option value="write_off">Write Off</option>
              <option value="installment_payment">Installment Payment</option>
            </select>
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="incident_status" id="inc_status" class="form-control">
              <option value="open">Open</option>
              <option value="resolved">Resolved</option>
              <option value="closed">Closed</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Save Incident</button>
      </div>
    </form>
  </div>
</div>

<!-- REQUEST CANCELLATION MODAL -->
<div class="modal-overlay" id="modalRequestCancel">
  <div class="modal" style="max-width:460px">
    <div class="modal-header">
      <h3><i data-feather="x-circle" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Request Cancellation</h3>
      <button class="modal-close" onclick="closeModal('modalRequestCancel')">&times;</button>
    </div>
    <form method="POST" action="{{ $actionUrl }}">
      @csrf
      <input type="hidden" name="action" value="request_cancellation">
      <input type="hidden" name="request_type" value="client_request">
      <div class="modal-body">
        <div style="background:#172554;border:1px solid #1d4ed8;color:#93c5fd;padding:10px 12px;border-radius:6px;font-size:12px;margin-bottom:14px">
          <i data-feather="info" style="width:13px;height:13px;vertical-align:middle;margin-right:4px"></i>
          This will submit a cancellation request for admin approval. No penalty is automatic — admin sets the terms upon approval.
        </div>
        <div class="form-group">
          <label>Reason for Cancellation <span class="req">*</span></label>
          <textarea name="reason" class="form-control" rows="3" placeholder="Explain why the booking needs to be cancelled…" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalRequestCancel')">Cancel</button>
        <button type="submit" class="btn btn-danger">Submit Request</button>
      </div>
    </form>
  </div>
</div>

<!-- ON-FIELD CANCELLATION MODAL -->
<div class="modal-overlay" id="modalOnFieldCancel">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <h3><i data-feather="x-octagon" style="width:16px;height:16px;margin-right:6px;vertical-align:middle;color:#ef4444"></i>On-Field Cancellation</h3>
      <button class="modal-close" onclick="closeModal('modalOnFieldCancel')">&times;</button>
    </div>
    <form method="POST" action="{{ $actionUrl }}">
      @csrf
      <input type="hidden" name="action" value="request_cancellation">
      <input type="hidden" name="request_type" value="on_field">
      <div class="modal-body">
        <div style="background:#450a0a;border:1px solid #b91c1c;color:#fca5a5;padding:12px 14px;border-radius:6px;font-size:12px;margin-bottom:14px">
          <i data-feather="alert-octagon" style="width:13px;height:13px;vertical-align:middle;margin-right:4px"></i>
          <strong>Warning:</strong> On-field cancellation immediately applies a <strong>50% penalty</strong> on the booking total
          @if ($booking->final_amount > 0)
          (₱{{ number_format($booking->final_amount * 0.5, 2) }})
          @endif.
          All equipment will be marked as returned/available. This cannot be undone.
        </div>
        <div class="form-group">
          <label>Reason <span class="req">*</span></label>
          <textarea name="reason" class="form-control" rows="3" placeholder="Reason for on-field cancellation…" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalOnFieldCancel')">Cancel</button>
        <button type="submit" class="btn btn-danger"
          onclick="return confirm('Apply 50% penalty and cancel this booking immediately?')">
          <i data-feather="x-octagon" style="width:13px;height:13px;margin-right:4px;vertical-align:middle"></i>Confirm On-Field Cancel
        </button>
      </div>
    </form>
  </div>
</div>

@if ($isAdmin)
<!-- DUPLICATE BOOKING MODAL -->
<div class="modal-overlay" id="modalDuplicateBooking">
  <div class="modal" style="max-width:460px">
    <div class="modal-header">
      <h3><i data-feather="copy" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Duplicate Booking</h3>
      <button class="modal-close" onclick="closeModal('modalDuplicateBooking')">&times;</button>
    </div>
    <form method="POST" action="{{ $actionUrl }}">
      @csrf
      <input type="hidden" name="action" value="duplicate_booking">
      <div class="modal-body">
        <div style="background:#052e16;border:1px solid #16a34a;color:#86efac;padding:10px 12px;border-radius:6px;font-size:12px;margin-bottom:14px">
          Creates a new <strong>Awaiting Review</strong> draft booking for {{ $booking->company_name ?: $booking->contact_person }}, copying
          project details, {{ $equipmentLines->count() }} equipment item(s), and {{ $crewLines->count() }} crew
          member(s) from this booking. Items no longer available or with conflicting dates will be skipped — you'll
          see which ones on the new booking. Nothing here is confirmed until the new booking is reviewed and approved.
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>New Shoot Start Date <span class="req">*</span></label>
            <input type="date" name="new_shoot_date_start" class="form-control" required>
          </div>
          <div class="form-group">
            <label>New Shoot End Date <span class="req">*</span></label>
            <input type="date" name="new_shoot_date_end" class="form-control" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalDuplicateBooking')">Cancel</button>
        <button type="submit" class="btn btn-primary">
          <i data-feather="copy" style="width:13px;height:13px;margin-right:4px;vertical-align:middle"></i>Create Duplicate
        </button>
      </div>
    </form>
  </div>
</div>
@endif

<!-- APPROVE CANCELLATION MODAL -->
<div class="modal-overlay" id="modalApproveCancellation">
  <div class="modal" style="max-width:460px">
    <div class="modal-header">
      <h3><i data-feather="check" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Approve Cancellation</h3>
      <button class="modal-close" onclick="closeModal('modalApproveCancellation')">&times;</button>
    </div>
    <form method="POST" action="{{ $actionUrl }}">
      @csrf
      <input type="hidden" name="action" value="approve_cancellation">
      <input type="hidden" name="cancellation_id" id="appr_can_id">
      <div class="modal-body">
        <div style="background:#052e16;border:1px solid #16a34a;color:#86efac;padding:10px 12px;border-radius:6px;font-size:12px;margin-bottom:14px">
          Booking total: <strong>₱{{ number_format($booking->final_amount ?? 0, 2) }}</strong>.
          Set the penalty percentage below to compute the refund.
        </div>
        <div class="form-group">
          <label>Penalty Percentage (%)</label>
          <input type="number" name="penalty_percent" class="form-control" min="0" max="100" step="5" value="0"
                 placeholder="0 = no penalty, 50 = half, 100 = full forfeiture">
          <div style="font-size:11px;color:var(--muted);margin-top:4px">For pre-shoot cancellations, typically 0–25%. On-field: 50%.</div>
        </div>
        <div class="form-group">
          <label>Admin Notes</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Optional remarks…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalApproveCancellation')">Back</button>
        <button type="submit" class="btn btn-danger"
          onclick="return confirm('Approve this cancellation? This will cancel the booking.')">
          Approve &amp; Cancel Booking
        </button>
      </div>
    </form>
  </div>
</div>

<!-- REJECT CANCELLATION MODAL -->
<div class="modal-overlay" id="modalRejectCancellation">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <h3><i data-feather="x" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Reject Cancellation</h3>
      <button class="modal-close" onclick="closeModal('modalRejectCancellation')">&times;</button>
    </div>
    <form method="POST" action="{{ $actionUrl }}">
      @csrf
      <input type="hidden" name="action" value="reject_cancellation">
      <input type="hidden" name="cancellation_id" id="rej_can_id">
      <div class="modal-body">
        <div class="form-group">
          <label>Reason for Rejection</label>
          <textarea name="notes" class="form-control" rows="3" placeholder="Explain why the cancellation request is rejected…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalRejectCancellation')">Back</button>
        <button type="submit" class="btn btn-primary">Reject Request</button>
      </div>
    </form>
  </div>
</div>

<!-- APPROVE CLIENT REQUEST MODAL -->
<div class="modal-overlay" id="modalApproveReq">
  <div class="modal" style="max-width:440px">
    <div class="modal-header" style="background:var(--success,#15803d);color:#fff;border-radius:10px 10px 0 0">
      <h3 class="modal-title" style="color:#fff"><i data-feather="check-circle" style="width:15px;height:15px;margin-right:7px;vertical-align:middle"></i>Approve Request</h3>
      <button class="modal-close" style="color:#fff" onclick="closeModal('modalApproveReq')"><i data-feather="x"></i></button>
    </div>
    <form method="POST" action="{{ $actionUrl }}">
      @csrf
      <input type="hidden" name="action" id="approveReqAction" value="">
      <input type="hidden" name="extension_id" id="approveReqExtId" value="">
      <input type="hidden" name="request_id" id="approveReqReqId" value="">
      <div class="modal-body">
        <div id="approveReqDesc" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:7px;padding:10px 13px;margin-bottom:14px;font-size:12.5px;color:#15803d"></div>
        <div class="form-group" id="approveReqCrewWrap" style="display:none">
          <label>Assign Crew Member <span class="req">*</span></label>
          <select name="crew_id" id="approveReqCrewSelect" class="form-control">
            <option value="">Select an available crew member…</option>
          </select>
          <div style="font-size:.72rem;color:var(--muted);margin-top:4px">Only active crew for the requested role are listed; those already busy on an overlapping booking are disabled.</div>
        </div>
        <div class="form-group">
          <label>Admin Notes (optional)</label>
          <textarea name="admin_notes" class="form-control" rows="2" placeholder="Add any notes for the client"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalApproveReq')">Cancel</button>
        <button type="submit" class="btn btn-success"><i data-feather="check-circle"></i> Confirm Approval</button>
      </div>
    </form>
  </div>
</div>

<!-- REJECT CLIENT REQUEST MODAL -->
<div class="modal-overlay" id="modalRejectReq">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <h3 class="modal-title"><i data-feather="x-circle" style="width:15px;height:15px;margin-right:7px;vertical-align:middle"></i>Reject Request</h3>
      <button class="modal-close" onclick="closeModal('modalRejectReq')"><i data-feather="x"></i></button>
    </div>
    <form method="POST" action="{{ $actionUrl }}">
      @csrf
      <input type="hidden" name="action" id="rejectReqAction" value="">
      <input type="hidden" name="extension_id" id="rejectReqExtId" value="">
      <input type="hidden" name="request_id" id="rejectReqReqId" value="">
      <div class="modal-body">
        <div class="form-group">
          <label>Reason for Rejection <span class="req">*</span></label>
          <textarea name="admin_notes" class="form-control" rows="3" placeholder="Explain to the client why this request was rejected" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalRejectReq')">Cancel</button>
        <button type="submit" class="btn btn-danger"><i data-feather="x-circle"></i> Reject</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
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

function openApproveReq(action, extId, reqId, desc, itemType, positionId) {
  document.getElementById('approveReqAction').value = action;
  document.getElementById('approveReqExtId').value  = extId || '';
  document.getElementById('approveReqReqId').value  = reqId || '';
  document.getElementById('approveReqDesc').textContent = desc;

  const crewWrap = document.getElementById('approveReqCrewWrap');
  const crewSel  = document.getElementById('approveReqCrewSelect');
  if (itemType === 'crew') {
    const pool = (typeof crewData !== 'undefined' ? crewData : []).filter(c => !positionId || parseInt(c.primary_position_id) === parseInt(positionId));
    crewSel.innerHTML = '<option value="">Select an available crew member…</option>' + pool.map(c => {
      const busy = c.busy_on_booking || c.unavailability_reason;
      return `<option value="${c.crew_id}" ${busy ? 'disabled' : ''}>${_escH(c.name)}${c.position_name ? ' — ' + _escH(c.position_name) : ''}${busy ? ' (unavailable)' : ''}</option>`;
    }).join('');
    crewWrap.style.display = '';
  } else {
    crewWrap.style.display = 'none';
    crewSel.value = '';
  }
  openModal('modalApproveReq');
}
function openRejectReq(action, extId, reqId) {
  document.getElementById('rejectReqAction').value = action;
  document.getElementById('rejectReqExtId').value  = extId || '';
  document.getElementById('rejectReqReqId').value  = reqId || '';
  openModal('modalRejectReq');
}

function openApproveCancellation(canId) {
  document.getElementById('appr_can_id').value = canId;
  openModal('modalApproveCancellation');
}
function openRejectCancellation(canId) {
  document.getElementById('rej_can_id').value = canId;
  openModal('modalRejectCancellation');
}

function openIncidentUpdate(iid, ename, charge, resolution, status, description, cause) {
  document.getElementById('inc_id').value           = iid;
  document.getElementById('inc_equip').textContent  = ename;
  document.getElementById('inc_charge').value       = charge || '';
  document.getElementById('inc_resolution').value   = resolution || 'pending';
  document.getElementById('inc_status').value       = status || 'open';
  document.getElementById('inc_description').value  = description || '';
  document.getElementById('inc_cause').value        = cause || 'unknown';
  openModal('modalIncident');
}

function toggleBdRefField() {
  const isGcash = document.getElementById('bdPayMethod')?.value === 'gcash';
  document.getElementById('bdRefWrap').style.display = isGcash ? '' : 'none';
}

function openRelease(eid, ename) {
  document.getElementById('rel_eid').value = eid;
  document.getElementById('rel_ename').textContent = ename;
  openModal('modalRelease');
}
function openReturn(eid, ename, shootEnd, dailyRate, qty) {
  document.getElementById('ret_eid').value = eid;
  document.getElementById('ret_ename').textContent = ename;
  document.getElementById('ret_condition').value = 'good';
  document.getElementById('damageFields').style.display = 'none';
  const today    = new Date(); today.setHours(0,0,0,0);
  const expected = new Date(shootEnd + 'T00:00:00');
  const lateDays = Math.max(0, Math.floor((today - expected) / 86400000));
  const info     = document.getElementById('latePenaltyInfo');
  const text     = document.getElementById('latePenaltyText');
  if (lateDays > 0) {
    const penalty = lateDays * parseFloat(dailyRate) * parseInt(qty);
    text.textContent = lateDays + ' day(s) overdue — penalty: ₱' +
      penalty.toLocaleString('en-PH', {minimumFractionDigits:2}) +
      ' (' + lateDays + ' × ₱' + parseFloat(dailyRate).toLocaleString('en-PH',{minimumFractionDigits:2}) + ' × ' + qty + ' unit(s))';
    info.style.display = 'block';
  } else {
    info.style.display = 'none';
  }
  openModal('modalReturn');
}
function toggleDamageFields(val) {
  document.getElementById('damageFields').style.display = (val === 'damaged' || val === 'missing') ? 'block' : 'none';
}

// Shows a reference suggestion (rate × zone multiplier) next to the cost field — it never
// writes into #atCost itself, so it can't clobber a value the admin already typed. The cost
// is always a manual entry; this is just a guide.
function atRecalc() {
    const zone    = document.getElementById('atZone');
    const vehicle = document.getElementById('atVehicle');
    const note    = document.getElementById('atCostNote');
    if (!zone) return;
    const mult  = parseFloat(zone.options[zone.selectedIndex]?.dataset?.mult || 0);
    const vRate = vehicle ? parseFloat(vehicle.options[vehicle.selectedIndex]?.dataset?.rate || 0) : 0;
    if (mult > 0 && vRate > 0) {
        const calc = (vRate * mult).toFixed(2);
        note.innerHTML = 'Suggested: ₱' + parseFloat(vRate).toLocaleString('en-PH') + ' × ' + mult + '× = <strong>₱' + parseFloat(calc).toLocaleString('en-PH') + '</strong> — <a href="#" onclick="document.getElementById(\'atCost\').value=\'' + calc + '\';return false;" style="color:#0369a1">use this</a>';
    } else {
        note.textContent = mult > 0 ? 'No vehicle selected — enter transport cost manually.' : '';
    }
}

// ── Assign Crew modal ──────────────────────────────────────────────────────
const alreadyAssignedIds = new Set({!! $assignedCrewIds->toJson() !!});

let cfgSlotCtx = null;

function openAddCrewModal() {
  cfgSlotCtx = null;
  _cfgReset(false);
  _renderCfgCrewList('');
  document.querySelectorAll('.crew-slot-btn').forEach(b => b.classList.remove('active'));
  const gen = document.getElementById('slotGeneral');
  if (gen) gen.classList.add('active');
  openModal('modalAddCrew');
}

function filterSlots(q) {
  q = q.toLowerCase().trim();
  document.querySelectorAll('#slotScrollArea .crew-slot-btn').forEach(btn => {
    const text = btn.textContent.toLowerCase();
    btn.style.display = (!q || text.includes(q)) ? '' : 'none';
  });
}

function fillCrewSlot(slot) {
  cfgSlotCtx = slot;
  document.querySelectorAll('.crew-slot-btn').forEach(b => b.classList.remove('active'));
  const btn = document.querySelector(`.crew-slot-btn[data-equip-id="${slot.equipment_id}"]`);
  if (btn) btn.classList.add('active');
  document.getElementById('cfgSlotBanner').style.display = '';
  document.getElementById('cfgSlotName').textContent = slot.equipment_name;
  const posTag = document.getElementById('cfgSlotPos');
  if (posTag) posTag.textContent = slot.positions_label || '';
  document.getElementById('cfgEquipLink').value = slot.equipment_id;
  const posEl = document.getElementById('cfgPosition');
  if (slot.position_ids && slot.position_ids.length) posEl.value = slot.position_ids[0];
  onCfgPositionChange();
}

function clearCrewSlotCtx() {
  cfgSlotCtx = null;
  document.querySelectorAll('.crew-slot-btn').forEach(b => b.classList.remove('active'));
  const gen = document.getElementById('slotGeneral');
  if (gen) gen.classList.add('active');
  document.getElementById('cfgSlotBanner').style.display = 'none';
  document.getElementById('cfgEquipLink').value = '';
  document.getElementById('cfgPosition').value = '';
  onCfgPositionChange();
}

function onCfgPositionChange() {
  document.getElementById('cfgCrewSearch').value = '';
  const hasPosFilter = !!document.getElementById('cfgPosition').value;
  document.getElementById('cfgPosFilterNote')?.style && (document.getElementById('cfgPosFilterNote').style.display = hasPosFilter ? '' : 'none');
  _renderCfgCrewList('');
}

function filterCfgCrew(q) {
  const lq = q.toLowerCase();
  document.querySelectorAll('#cfgCrewList .crew-card').forEach(card => {
    const text = card.textContent.toLowerCase();
    card.style.display = (!q || text.includes(lq)) ? '' : 'none';
  });
}

function _availCrewBase() {
  return crewData.filter(c => !alreadyAssignedIds.has(c.crew_id)
    && !(c.position_name||'').toLowerCase().includes('driver'));
}

function _getCfgCrewFiltered(q) {
  const selPosId = parseInt(document.getElementById('cfgPosition').value || 0);
  const lq = q.toLowerCase();
  return _availCrewBase().filter(c => {
    if (selPosId && parseInt(c.primary_position_id || 0) !== selPosId) return false;
    if (q && !c.name.toLowerCase().includes(lq) && !(c.position_name||'').toLowerCase().includes(lq)) return false;
    return true;
  });
}

const _avatarPalette = ['#3b82f6','#8b5cf6','#ec4899','#f59e0b','#10b981','#06b6d4','#ef4444','#84cc16','#f97316','#6366f1'];
function _crewInitials(name) {
  const p = name.trim().split(/\s+/);
  return p.length >= 2 ? (p[0][0] + p[p.length-1][0]).toUpperCase() : name.slice(0,2).toUpperCase();
}
function _crewColor(id) { return _avatarPalette[id % _avatarPalette.length]; }

function _crewItemsHtml(arr) {
  if (!arr.length) return '<div style="padding:32px 16px;text-align:center;font-size:.83rem;color:var(--muted)">'
    + '<div style="font-size:32px;margin-bottom:8px;opacity:.25">👤</div>No crew available for this selection</div>';
  return arr.map(c => {
    const rate     = parseFloat(c.base_rate_12hr) || 0;
    const initials = _crewInitials(c.name);
    const color    = _crewColor(c.crew_id);
    const posLabel = _escH(c.position_name || c.employment_type || '—');
    const rateStr  = '₱' + rate.toLocaleString('en-PH',{minimumFractionDigits:2}) + '/12hr';
    const isBusy   = c.busy_on_booking && c.busy_on_booking !== 'null';
    const isOff    = c.unavailability_reason && c.unavailability_reason !== 'null';
    const isBlocked = isBusy || isOff;
    const busyBadge = isBusy
      ? '<span style="font-size:.65rem;font-weight:700;background:#fef3c7;color:#92400e;border:1px solid #fcd34d;border-radius:4px;padding:1px 6px;white-space:nowrap;flex-shrink:0">Busy · ' + _escH(c.busy_on_booking) + '</span>'
      : (isOff ? '<span style="font-size:.65rem;font-weight:700;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;border-radius:4px;padding:1px 6px;white-space:nowrap;flex-shrink:0">Off · ' + _escH(c.unavailability_reason) + '</span>' : '');
    const cardStyle = isBlocked ? 'opacity:.55;pointer-events:none;' : '';
    return '<label class="crew-card" data-id="' + c.crew_id + '" style="' + cardStyle + '">'
      + '<input type="checkbox" value="' + c.crew_id + '" data-rate="' + rate + '" data-name="' + _escH(c.name) + '" onchange="onCrewCheckChange(this)" style="display:none"' + (isBlocked ? ' disabled' : '') + '>'
      + '<div class="crew-avatar" style="background:' + color + '">' + initials + '</div>'
      + '<div style="flex:1;min-width:0">'
      + '<div style="font-weight:600;font-size:.85rem;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + _escH(c.name) + '</div>'
      + '<div style="font-size:.72rem;color:var(--muted);margin-top:2px">' + posLabel + ' <span style="opacity:.4">·</span> ' + rateStr + '</div>'
      + '</div>'
      + busyBadge
      + (isBlocked ? '' : '<input type="number" class="crew-rate-inp form-control" step="0.01" min="0" value="' + rate.toFixed(2) + '" style="width:96px;display:none;font-size:.8rem;padding:4px 7px;flex-shrink:0;height:32px" onclick="event.stopPropagation()" oninput="event.stopPropagation()" placeholder="Rate">')
      + '</label>';
  }).join('');
}

function _renderCfgCrewList(q) {
  const list     = document.getElementById('cfgCrewList');
  if (!list) return;
  const selPosId = parseInt(document.getElementById('cfgPosition').value || 0);
  const items    = _getCfgCrewFiltered('');

  if (items.length) {
    list.innerHTML = _crewItemsHtml(items.slice(0, 60));
  } else if (selPosId) {
    const fallback = _availCrewBase();
    if (fallback.length) {
      list.innerHTML = '<div style="padding:5px 10px;font-size:.72rem;color:#b45309;background:#fffbeb;border-bottom:1px solid #fde68a">'
        + 'No crew with this as primary position — showing all available</div>'
        + _crewItemsHtml(fallback.slice(0, 60));
    } else {
      list.innerHTML = '<div style="padding:14px;text-align:center;font-size:.82rem;color:var(--muted)">No crew available</div>';
    }
  } else {
    list.innerHTML = '<div style="padding:14px;text-align:center;font-size:.82rem;color:var(--muted)">No crew available</div>';
  }

  if (q) filterCfgCrew(q);
  _updateCrewSelectedBadge();
}

function onCrewCheckChange(chk) {
  const card    = chk.closest('.crew-card');
  const rateInp = card ? card.querySelector('.crew-rate-inp') : null;
  if (card) card.classList.toggle('selected', chk.checked);
  if (rateInp) rateInp.style.display = chk.checked ? '' : 'none';
  _updateCrewSelectedBadge();
}

function _updateCrewSelectedBadge() {
  const n     = document.querySelectorAll('#cfgCrewList input[type=checkbox]:checked').length;
  const badge = document.getElementById('cfgSelectedBadge');
  const btn   = document.getElementById('btnAssignAll');
  const nEl   = document.getElementById('assignAllCount');
  if (badge) { badge.textContent = n + ' selected'; badge.style.display = n ? '' : 'none'; }
  if (btn)   btn.disabled = n === 0;
  if (nEl)   nEl.textContent = n;
}

function _cfgReset(keepSlot) {
  document.getElementById('cfgCrewSearch').value = '';
  document.querySelectorAll('#cfgCrewList .crew-card').forEach(card => {
    const chk = card.querySelector('input[type=checkbox]');
    const ri  = card.querySelector('.crew-rate-inp');
    if (chk) chk.checked = false;
    card.classList.remove('selected');
    if (ri) ri.style.display = 'none';
  });
  _updateCrewSelectedBadge();
  if (!keepSlot) {
    document.getElementById('cfgPosition').value = '';
    document.getElementById('cfgEquipLink').value = '';
    document.getElementById('cfgSlotBanner').style.display = 'none';
  }
}

function prepareCrewBatch() {
  const checked = Array.from(document.querySelectorAll('#cfgCrewList input[type=checkbox]:checked'));
  if (!checked.length) { alert('Select at least one crew member.'); return false; }

  const posEl   = document.getElementById('cfgPosition');
  const posId   = parseInt(posEl.value || 0);
  const eqEl    = document.getElementById('cfgEquipLink');
  const eqId    = parseInt(eqEl.value || 0);

  const badRates = [];
  const entries  = [];
  checked.forEach(chk => {
    const rateInp = chk.closest('.crew-card')?.querySelector('.crew-rate-inp');
    const rate    = parseFloat(rateInp?.value) || parseFloat(chk.dataset.rate) || 0;
    if (rate <= 0) { badRates.push(chk.dataset.name); return; }
    entries.push({ crew_id: parseInt(chk.value), rate, posId, eqId });
  });

  if (badRates.length) {
    alert('Rate missing or zero for: ' + badRates.join(', ') + '. Set a rate before assigning.');
    return false;
  }
  if (!entries.length) return false;

  const container = document.getElementById('batchInputs');
  container.innerHTML = '';
  entries.forEach(e => {
    [['bc_crew_id[]',e.crew_id],['bc_pos_id[]',e.posId],['bc_rate[]',e.rate],['bc_eq_link[]',e.eqId],['bc_notes[]','']].forEach(([n,v]) => {
      const inp = document.createElement('input'); inp.type='hidden'; inp.name=n; inp.value=v; container.appendChild(inp);
    });
  });
  return true;
}

// ── Equipment catalog quick-add panel (Part 9) ─────────────────────────────
function filterCatalogPanel() {
  const q = (document.getElementById('cat_filter').value || '').toLowerCase().trim();
  document.querySelectorAll('#catalogPanel [data-cat-row]').forEach(row => {
    const hay = row.dataset.catRow;
    row.style.display = (!q || hay.includes(q)) ? '' : 'none';
  });
}

// ── Package pricing mode toggle ─────────────────────────────────────────────
function togglePricingInput() {
  const sel = document.getElementById('pricingModeSelect');
  const wrap = document.getElementById('pricingInputWrap');
  const label = document.getElementById('pricingInputLabel');
  if (!sel || !wrap || !label) return;
  if (sel.value === 'no_discount') { wrap.style.display = 'none'; return; }
  wrap.style.display = '';
  const labels = { package_price: 'Package Price (₱, all-in incl. crew)', discount_percent: 'Discount (%)', discount_flat: 'Discount (₱)' };
  label.textContent = labels[sel.value] || 'Amount';
}

// ── Set Discount modal: only the field for the selected type is ever shown ─
function toggleSetDiscInput() {
  const sel = document.getElementById('setDiscType');
  const input = document.getElementById('setDiscValue');
  const label = document.getElementById('setDiscValueLabel');
  const note = document.getElementById('setDiscPackageNote');
  if (!sel || !input || !label) return;
  const labels = { flat: 'Amount (₱)', percent: 'Percent off (%)', package: 'Target package price (₱)' };
  label.textContent = labels[sel.value] || 'Amount';
  input.max = sel.value === 'percent' ? '100' : '';
  if (note) note.style.display = sel.value === 'package' ? '' : 'none';
}

// ── Inline crew slot search (Equipment Slots section) ──────────────────────
function _slotPool(posId, q) {
  let pool = crewData.filter(c => !alreadyAssignedIds.has(c.crew_id));
  if (posId) {
    const byPos = pool.filter(c => parseInt(c.primary_position_id||0) === posId);
    if (byPos.length) pool = byPos;
  }
  if (q) {
    const lq = q.toLowerCase();
    pool = pool.filter(c => c.name.toLowerCase().includes(lq) || (c.position_name||'').toLowerCase().includes(lq));
  }
  return pool.slice(0, 20);
}

function _slotDropHtml(items) {
  if (!items.length) return '<div class="csd-empty">No crew found</div>';
  return items.map(c => {
    const rate   = parseFloat(c.base_rate_12hr)||0;
    const isBusy = c.busy_on_booking && c.busy_on_booking !== 'null';
    const busyTag = isBusy ? ` <span style="font-size:.62rem;background:#fef3c7;color:#92400e;border:1px solid #fcd34d;border-radius:3px;padding:0 4px">Busy · ${_escH(c.busy_on_booking)}</span>` : '';
    return `<div class="csd-item${isBusy?' csd-busy':''}" data-id="${c.crew_id}" data-name="${_escH(c.name)}" data-rate="${rate}" style="${isBusy?'opacity:.5;pointer-events:none;':''}">
      <div class="csd-name">${_escH(c.name)}${busyTag}</div>
      <div class="csd-sub">${_escH(c.position_name||'—')} &nbsp;·&nbsp; ₱${rate.toLocaleString('en-PH',{minimumFractionDigits:0})}/12hr</div>
    </div>`;
  }).join('');
}

function _attachSlotDropClicks(drop, eid) {
  drop.querySelectorAll('.csd-item').forEach(el => {
    el.addEventListener('mousedown', e => {
      e.preventDefault();
      document.getElementById('slotCid_'+eid).value = el.dataset.id;
      document.getElementById('slotRid_'+eid).value = el.dataset.rate;
      document.getElementById('slotRv_'+eid).value  = parseFloat(el.dataset.rate).toFixed(2);
      document.querySelector('#slotW_'+eid+' .slot-txt').value = el.dataset.name;
      drop.style.display = 'none';
    });
  });
}

function openSlot(inp, eid) {
  const posId = parseInt(inp.dataset.posid||0);
  const drop  = document.getElementById('slotD_'+eid);
  drop.innerHTML = _slotDropHtml(_slotPool(posId, inp.value));
  drop.style.display = 'block';
  _attachSlotDropClicks(drop, eid);
}
function closeSlot(eid, ms) {
  setTimeout(() => { const d = document.getElementById('slotD_'+eid); if (d) d.style.display='none'; }, ms);
}
function prepSlot(eid) {
  if (!document.getElementById('slotCid_'+eid).value) {
    alert('Please select a crew member from the search.'); return false;
  }
  return true;
}

// ── General crew search ─────────────────────────────────────────────────────
function openGen(q) {
  const posId = parseInt(document.getElementById('genPos')?.value||0);
  let pool = crewData.filter(c => !alreadyAssignedIds.has(c.crew_id)
    && !(c.position_name||'').toLowerCase().includes('driver'));
  if (posId) { const f = pool.filter(c=>parseInt(c.primary_position_id||0)===posId); if(f.length) pool=f; }
  if (q) { const lq=q.toLowerCase(); pool=pool.filter(c=>c.name.toLowerCase().includes(lq)||(c.position_name||'').toLowerCase().includes(lq)); }
  pool = pool.slice(0,20);
  const drop = document.getElementById('genDrop');
  if (!drop) return;
  drop.innerHTML = _slotDropHtml(pool);
  drop.style.display = 'block';
  drop.querySelectorAll('.csd-item').forEach(el => {
    el.addEventListener('mousedown', e => {
      e.preventDefault();
      document.getElementById('genCid').value = el.dataset.id;
      document.getElementById('genRid').value = el.dataset.rate;
      document.getElementById('genRateVis').value = parseFloat(el.dataset.rate).toFixed(2);
      document.getElementById('genCrewTxt').value = el.dataset.name;
      drop.style.display = 'none';
    });
  });
}
function closeGen(ms) {
  setTimeout(() => { const d = document.getElementById('genDrop'); if (d) d.style.display='none'; }, ms);
}
function prepGen() {
  if (!document.getElementById('genCid')?.value) {
    alert('Please select a crew member from the search.'); return false;
  }
  return true;
}

// ── Accessories tab: suggested accessories ──────────────────────────────────
(function(){
  const btn = document.querySelector('[data-tab="tab-accessories"]');
  if (!btn) return;
  btn.addEventListener('click', loadAccSuggestions);
  if (btn.classList.contains('active')) loadAccSuggestions();
})();

function loadAccSuggestions() {
  const wrap = document.getElementById('accSuggestWrap');
  const list = document.getElementById('accSuggestList');
  if (!wrap || !list) return;
  fetch('{{ route('booking-detail', $id) }}?get_suggested_accessories=1')
    .then(r => r.json())
    .then(items => {
      if (!items.length) { wrap.style.display='none'; return; }
      wrap.style.display = '';
      list.innerHTML = items.map(a => {
        const avail = Math.max(0, parseInt(a.quantity||1) - parseInt(a.qty_in_use||0));
        const rateStr = parseFloat(a.daily_rate) > 0 ? '₱'+parseFloat(a.daily_rate).toFixed(2)+'/day' : 'Included';
        const disabled = avail < 1;
        return `<button type="button" class="btn btn-outline btn-sm"
                  style="font-size:.75rem;gap:4px;${disabled?'opacity:.4;pointer-events:none':''}"
                  onclick="quickAddAcc(${a.accessory_id},'${a.accessory_name.replace(/'/g,"\\'")}')">
          <i data-feather="plus" style="width:11px;height:11px"></i>
          ${escHtmlAcc(a.accessory_name)}
          <span style="opacity:.6">· ${rateStr}</span>
          ${disabled ? '<span style="color:var(--red)">(Out of stock)</span>' : ''}
        </button>`;
      }).join('');
      if (typeof feather !== 'undefined') feather.replace();
    })
    .catch(() => {});
}

function quickAddAcc(aid, name) {
  const sel = document.getElementById('accSelectAdd');
  if (!sel) return;
  sel.value = aid;
  sel.dispatchEvent(new Event('change'));
  document.querySelector('[data-tab="tab-accessories"]')?.click();
  sel.closest('form')?.querySelector('[type=submit]')?.focus();
}

function escHtmlAcc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        const tab = btn.dataset.tab;
        document.getElementById(tab)?.classList.add('active');
        if (window.feather) feather.replace();
    });
});

// Arriving from Field Resource Requests' "Review" link (#tab-requests) — jump straight to that
// tab instead of landing on the default one, so staff don't have to hunt for it.
if (location.hash) {
    const target = document.querySelector('.tab-btn[data-tab="' + location.hash.slice(1) + '"]');
    if (target) target.click();
}

// The Cost Estimate tab embeds the CE editor in an iframe. Its Add Equipment / Assign Crew /
// Assign Transport buttons no longer carry their own modals — they postMessage up to this page
// and reuse these same modals (openAddEquipModal/openAddCrewModal/modalAssignTransport), so
// there's exactly one implementation of each workflow instead of two that could drift apart.
window.addEventListener('message', (e) => {
    if (! e.data || e.data.source !== 'ce-editor') return;
    if (e.data.action === 'openAddEquip') openAddEquipModal();
    else if (e.data.action === 'openAddCrew') openAddCrewModal();
    else if (e.data.action === 'openAssignTransport') openModal('modalAssignTransport');
});

function toggleCustomReason(val) {
    document.getElementById('customReasonWrap').style.display = val === '__other__' ? 'block' : 'none';
}
function prepareReject() {
    const sel = document.getElementById('rejectReasonSel');
    if (!sel.value) { alert('Please select a rejection reason.'); return false; }
    if (sel.value === '__other__') {
        const custom = document.getElementById('customReasonTxt').value.trim();
        if (!custom) { alert('Please enter a rejection reason.'); return false; }
        sel.value = custom;
        sel.name = '';
        document.querySelector('[name="rejection_reason_custom"]').name = 'rejection_reason';
    }
    return true;
}

// ── Searchable equipment/crew lists ──────────────────────────────────────────
const equipData = {!! $availEquip->values()->toJson() !!};
const crewData  = {!! $availCrew->values()->toJson() !!};

function _escH(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function renderEquipList(items) {
  const list = document.getElementById('equipList');
  if (!items.length) { list.innerHTML = '<div class="srch-empty">No equipment found</div>'; return; }
  list.innerHTML = items.map(i => {
    const sub = '[' + _escH(i.category_name) + '] ' + (i.brand ? _escH(i.brand) + ' · ' : '') + '₱' + parseFloat(i.daily_rate).toLocaleString('en-PH',{minimumFractionDigits:2}) + '/day';
    return '<div class="srch-item" data-id="' + i.equipment_id + '" data-rate="' + i.daily_rate + '" onclick="selectEquip(this)">'
      + '<div class="si-name">' + _escH(i.equipment_name) + '</div>'
      + '<div class="si-sub">' + sub + '</div>'
      + '</div>';
  }).join('');
}
function filterEquipList(q) {
  const lq = q.toLowerCase();
  renderEquipList(equipData.filter(i =>
    i.equipment_name.toLowerCase().includes(lq) ||
    (i.category_name||'').toLowerCase().includes(lq) ||
    (i.brand||'').toLowerCase().includes(lq)
  ));
}
function selectEquip(el) {
  document.getElementById('selectedEquipId').value = el.dataset.id;
  document.getElementById('equipRate').value = el.dataset.rate;
  document.getElementById('equipSearch').value = el.querySelector('.si-name').textContent;
  document.querySelectorAll('#equipList .srch-item').forEach(i => i.classList.remove('sel'));
  el.classList.add('sel');
}
function openAddEquipModal() {
  document.getElementById('equipSearch').value = '';
  document.getElementById('selectedEquipId').value = '';
  document.getElementById('equipRate').value = '';
  renderEquipList(equipData);
  openModal('modalAddEquip');
}

function renderFaEquipList(items) {
  const list = document.getElementById('faEquipList');
  if (!list) return;
  if (!items.length) { list.innerHTML = '<div class="srch-empty">No equipment found</div>'; return; }
  list.innerHTML = items.map(i =>
    '<div class="srch-item" data-id="'+i.equipment_id+'" data-rate="'+i.daily_rate+'" onclick="selectFaEquip(this)">'
    +'<div class="si-name">'+_escH(i.equipment_name)+'</div>'
    +'<div class="si-sub">['+_escH(i.category_name||'')+'] '+(i.brand?_escH(i.brand)+' · ':'')+' ₱'+parseFloat(i.daily_rate).toLocaleString('en-PH',{minimumFractionDigits:2})+'/day</div>'
    +'</div>'
  ).join('');
}
function filterFaEquipList(q) {
  const lq = q.toLowerCase();
  renderFaEquipList(equipData.filter(i =>
    i.equipment_name.toLowerCase().includes(lq) ||
    (i.category_name||'').toLowerCase().includes(lq) ||
    (i.brand||'').toLowerCase().includes(lq)
  ));
}
function selectFaEquip(el) {
  document.getElementById('faEquipId').value    = el.dataset.id;
  document.getElementById('faEquipRate').value  = el.dataset.rate;
  document.getElementById('faEquipSearch').value = el.querySelector('.si-name').textContent;
  document.querySelectorAll('#faEquipList .srch-item').forEach(i => i.classList.remove('sel'));
  el.classList.add('sel');
}
function renderFaCrewList(items) {
  const list = document.getElementById('faCrewList');
  if (!list) return;
  if (!items.length) { list.innerHTML = '<div class="srch-empty">No crew found</div>'; return; }
  list.innerHTML = items.map(i => {
    const isBusy   = i.busy_on_booking && i.busy_on_booking !== 'null';
    const isOff    = i.unavailability_reason && i.unavailability_reason !== 'null';
    const isBlocked = isBusy || isOff;
    const busyTag  = isBusy ? ' <span style="font-size:.62rem;background:#fef3c7;color:#92400e;border:1px solid #fcd34d;border-radius:3px;padding:0 4px">Busy · '+_escH(i.busy_on_booking)+'</span>'
                   : (isOff ? ' <span style="font-size:.62rem;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;border-radius:3px;padding:0 4px">Off · '+_escH(i.unavailability_reason)+'</span>' : '');
    return '<div class="srch-item'+(isBlocked?' srch-busy':'')+'" data-id="'+i.crew_id+'" data-rate="'+i.base_rate_12hr+'" onclick="'+(isBlocked?'':'selectFaCrew(this)')+'" style="'+(isBlocked?'opacity:.5;pointer-events:none;':'')+'">'
      +'<div class="si-name">'+_escH(i.name)+busyTag+'</div>'
      +'<div class="si-sub">'+_escH(i.position_name||i.employment_type)+' · ₱'+parseFloat(i.base_rate_12hr).toLocaleString('en-PH',{minimumFractionDigits:2})+'/12hr</div>'
      +'</div>';
  }).join('');
}
function filterFaCrewList(q) {
  const lq = q.toLowerCase();
  renderFaCrewList(crewData.filter(i =>
    i.name.toLowerCase().includes(lq) ||
    (i.position_name||'').toLowerCase().includes(lq)
  ));
}
function selectFaCrew(el) {
  document.getElementById('faCrewId').value     = el.dataset.id;
  document.getElementById('faCrewRate').value   = el.dataset.rate;
  document.getElementById('faCrewSearch').value = el.querySelector('.si-name').textContent;
  document.querySelectorAll('#faCrewList .srch-item').forEach(i => i.classList.remove('sel'));
  el.classList.add('sel');
}
function openFieldAddModal() {
  ['faEquipSearch','faEquipId','faEquipRate','faCrewSearch','faCrewId','faCrewRate'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
  renderFaEquipList(equipData);
  renderFaCrewList(crewData);
  openModal('modalFieldAdd');
}
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('formFieldAdd')?.addEventListener('submit', function(e) {
    if (!document.getElementById('faEquipId').value) { e.preventDefault(); alert('Please select an equipment item.'); return; }
    if (!document.getElementById('faCrewId').value) { e.preventDefault(); alert('An operator assignment is required for field additions.'); return; }
  });
  document.getElementById('formAddEquip')?.addEventListener('submit', function(e) {
    if (!document.getElementById('selectedEquipId').value) {
      e.preventDefault();
      alert('Please select an equipment item from the list.');
      document.getElementById('equipSearch').focus();
    }
  });
});
</script>
@endpush
@endsection
