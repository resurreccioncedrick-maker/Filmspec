@extends('layouts.app')

@section('pageTitle', 'Billing & Payments')

@section('breadcrumb')
<span>Billing</span>
@endsection

@if ($canRecord)
@section('topbarActions')
<button onclick="resetPaymentModal(); openModal('modalAddPayment')" class="btn btn-primary btn-sm"><i data-feather="plus"></i> Record Payment</button>
@endsection
@endif

@section('content')
@php
  $billingBase = route('billing');
  $bookingUrl = fn ($id) => route('booking-detail', $id);
@endphp

@if ($msg)
<div class="alert alert-{{ $msg['type'] }}" data-autohide>
  <i data-feather="{{ $msg['type'] === 'success' ? 'check-circle' : 'alert-circle' }}"></i>
  {!! $msg['text'] !!}
</div>
@endif

<!-- KPIs -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:22px">
  <div class="stat-card green">
    <div class="stat-icon"><i data-feather="dollar-sign"></i></div>
    <div class="stat-value">₱{{ number_format($totalCollected, 2) }}</div>
    <div class="stat-label">Total Payments Collected <span style="font-weight:400;color:var(--muted)">· All time</span></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="trending-up"></i></div>
    <div class="stat-value">₱{{ number_format($monthCollected, 2) }}</div>
    <div class="stat-label">Payments Received This Month</div>
  </div>
  <div class="stat-card orange">
    <div class="stat-icon"><i data-feather="clock"></i></div>
    <div class="stat-value">₱{{ number_format($outstanding, 2) }}</div>
    <div class="stat-label">Outstanding Receivables</div>
  </div>
  <div class="stat-card {{ $overdueCount > 0 ? 'red' : '' }}">
    <div class="stat-icon"><i data-feather="alert-triangle"></i></div>
    <div class="stat-value">{{ $overdueCount }}</div>
    <div class="stat-label">Overdue Accounts <span style="font-weight:400;color:var(--muted)">· As of today</span></div>
  </div>
</div>

<!-- Tabs -->
<div class="tabs">
  <button class="tab-btn {{ $tab === 'payments' ? 'active' : '' }}" data-tab="tab-payments">
    <i data-feather="credit-card" style="width:14px;height:14px;margin-right:6px;vertical-align:middle"></i>
    Payment Records
  </button>
  <button class="tab-btn {{ $tab === 'unbilled' ? 'active' : '' }}" data-tab="tab-unbilled">
    <i data-feather="inbox" style="width:14px;height:14px;margin-right:6px;vertical-align:middle"></i>
    Unbilled
    @if ($pendingPayBkgs->isNotEmpty())
    <span class="badge badge-orange" style="margin-left:5px">{{ $pendingPayBkgs->count() }}</span>
    @endif
  </button>
  <button class="tab-btn {{ $tab === 'soa' ? 'active' : '' }}" data-tab="tab-soa">
    <i data-feather="file-text" style="width:14px;height:14px;margin-right:6px;vertical-align:middle"></i>
    Final Billing
    @if ($dueSoonCount > 0)
    <span class="badge badge-yellow" style="margin-left:5px;font-size:.65rem">{{ $dueSoonCount }} due soon</span>
    @endif
  </button>
  <button class="tab-btn {{ $tab === 'overdue' ? 'active' : '' }}" data-tab="tab-overdue">
    <i data-feather="alert-triangle" style="width:14px;height:14px;margin-right:6px;vertical-align:middle"></i>
    Overdue
    @if ($overdueCount > 0)
    <span class="badge badge-red" style="margin-left:5px">{{ $overdueCount }}</span>
    @endif
  </button>
  <button class="tab-btn {{ $tab === 'cancellations' ? 'active' : '' }}" data-tab="tab-cancellations">
    <i data-feather="x-circle" style="width:14px;height:14px;margin-right:6px;vertical-align:middle"></i>
    Cancellation Charges
    @if ($cancellationCharges->isNotEmpty())
    <span class="badge badge-gray" style="margin-left:5px">{{ $cancellationCharges->count() }}</span>
    @endif
  </button>
  <button class="tab-btn {{ $tab === 'discounts' ? 'active' : '' }}" data-tab="tab-discounts">
    <i data-feather="percent" style="width:14px;height:14px;margin-right:6px;vertical-align:middle"></i>
    Discounts
    @if ($pendingDiscounts->isNotEmpty())
    <span class="badge badge-yellow" style="margin-left:5px">{{ $pendingDiscounts->count() }}</span>
    @endif
  </button>
</div>

<div data-tab-panes>

<!-- PAYMENTS TAB -->
<div id="tab-payments" class="tab-pane {{ $tab === 'payments' ? 'active' : '' }}">
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Payment Records <span class="badge badge-blue" style="margin-left:8px">{{ $totalPay }}</span></h2>
    </div>
    <div class="card-body" style="padding-bottom:0">
      <form method="GET" action="{{ $billingBase }}">
        <input type="hidden" name="tab" value="payments">
        <div class="filter-bar" style="flex-wrap:wrap;gap:8px">
          <div class="search-input-wrap">
            <i data-feather="search"></i>
            <input type="text" name="q" class="form-control" placeholder="Search reference, client, receipt no…"
                   value="{{ $search }}">
          </div>
          <select name="ptype" class="form-control" style="width:auto">
            <option value="">All Types</option>
            <option value="downpayment" {{ $payTypeFilter === 'downpayment' ? 'selected' : '' }}>Downpayment</option>
            <option value="progress" {{ $payTypeFilter === 'progress' ? 'selected' : '' }}>Progress</option>
            <option value="final" {{ $payTypeFilter === 'final' ? 'selected' : '' }}>Final</option>
          </select>
          <select name="pmethod" class="form-control" style="width:auto">
            <option value="">All Methods</option>
            <option value="cash" {{ $payMethFilter === 'cash' ? 'selected' : '' }}>Cash</option>
            <option value="gcash" {{ $payMethFilter === 'gcash' ? 'selected' : '' }}>GCash</option>
          </select>
          <select name="rctype" class="form-control" style="width:auto">
            <option value="">All Receipts</option>
            <option value="official_receipt" {{ $rcTypeFilter === 'official_receipt' ? 'selected' : '' }}>OR (VAT)</option>
            <option value="acknowledgement_receipt" {{ $rcTypeFilter === 'acknowledgement_receipt' ? 'selected' : '' }}>AR (Non-VAT)</option>
          </select>
          <button type="submit" class="btn btn-primary btn-sm"><i data-feather="filter"></i> Filter</button>
          @if ($search || $payTypeFilter || $payMethFilter || $rcTypeFilter)
          <a href="{{ $billingBase }}?tab=payments" class="btn btn-secondary btn-sm">Clear</a>
          @endif
          @include('partials.export-dropdown', ['id' => 'BillingPayments', 'exportValue' => 'payments'])
        </div>
      </form>
    </div>
    <div class="table-wrap">
      @if ($payments->isEmpty())
      <div class="empty-state"><i data-feather="credit-card"></i><h3>No payments recorded yet</h3></div>
      @else
      <table>
        <thead>
          <tr>
            <th>Receipt No.</th>
            <th>Booking Ref</th>
            <th>Client</th>
            <th>Type</th>
            <th>Method</th>
            <th>Amount</th>
            <th>Date</th>
            <th>Receipt Type</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($payments as $pay)
          @php $isReversal = (float) $pay->amount < 0; @endphp
          <tr style="{{ $pay->is_voided ? 'opacity:.6' : '' }}">
            <td style="font-family:monospace;font-size:.83rem;font-weight:600;{{ $pay->is_voided ? 'text-decoration:line-through' : '' }}">
              {{ $pay->receipt_number ?: ($isReversal ? 'REVERSAL' : '—') }}
            </td>
            <td>
              <a href="{{ $bookingUrl($pay->booking_id) }}" style="color:var(--accent);font-weight:600">
                {{ $pay->booking_reference }}
              </a>
            </td>
            <td>{{ $pay->company_name ?: $pay->contact_person }}</td>
            <td><span class="badge {{ $ptBadge[$pay->payment_type] ?? 'badge-gray' }}">{{ ucfirst($pay->payment_type) }}</span></td>
            <td><span class="badge {{ $pmBadge[$pay->payment_method] ?? 'badge-gray' }}">{{ ucwords(str_replace('_', ' ', $pay->payment_method)) }}</span></td>
            <td style="font-weight:700;color:{{ $isReversal ? 'var(--red)' : 'var(--accent)' }}">₱{{ number_format($pay->amount, 2) }}</td>
            <td>{{ \Illuminate\Support\Carbon::parse($pay->payment_date)->format('M j, Y') }}</td>
            <td>
              @if ($pay->is_voided)
              <span class="badge badge-red" style="font-size:.65rem" title="{{ $pay->void_reason }}">Voided</span>
              @elseif ($isReversal)
              <span class="badge badge-gray" style="font-size:.65rem">Reversal</span>
              @else
              <span class="badge {{ $rcBadge[$pay->receipt_type] ?? 'badge-gray' }}" style="font-size:.65rem">
                {{ $pay->receipt_type === 'official_receipt' ? 'OR (VAT)' : 'AR (Non-VAT)' }}
              </span>
              @endif
            </td>
            <td style="white-space:nowrap">
              <a href="{{ route('billing-print', ['print_receipt' => $pay->payment_id]) }}" class="btn btn-outline btn-sm" title="Print / Save as PDF">
                <i data-feather="printer"></i>
              </a>
              @if ($canRecord && ! $pay->is_voided && ! $isReversal)
              <button type="button" class="btn btn-outline btn-sm" style="color:var(--red);border-color:var(--red)" title="Void Payment"
                      onclick="openVoidPayment({{ $pay->payment_id }}, {{ json_encode($pay->receipt_number ?: ('#'.$pay->payment_id)) }})">
                <i data-feather="rotate-ccw"></i>
              </button>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @if ($payPages > 1)
      <div style="padding:14px 16px">
        <div class="pagination">
          @for ($pi = 1; $pi <= $payPages; $pi++)
          <a href="{{ $billingBase }}?tab=payments&p={{ $pi }}&q={{ urlencode($search) }}&ptype={{ urlencode($payTypeFilter) }}&pmethod={{ urlencode($payMethFilter) }}&rctype={{ urlencode($rcTypeFilter) }}"
             class="page-btn {{ $pi == $page ? 'active' : '' }}">{{ $pi }}</a>
          @endfor
        </div>
      </div>
      @endif
      @endif
    </div>
  </div>
</div>

<!-- UNBILLED TAB -->
<div id="tab-unbilled" class="tab-pane {{ $tab === 'unbilled' ? 'active' : '' }}">
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Confirmed / Ongoing Bookings Not Yet Fully Paid <span class="badge badge-orange" style="margin-left:8px">{{ $pendingPayBkgs->count() }}</span></h2>
      @include('partials.export-dropdown', ['id' => 'BillingUnbilled', 'exportValue' => 'unbilled'])
    </div>
    <div class="table-wrap">
      @if ($pendingPayBkgs->isEmpty())
      <div class="empty-state"><i data-feather="check-circle"></i><h3>Nothing unbilled right now</h3></div>
      @else
      <table>
        <thead>
          <tr>
            <th>Booking Ref</th>
            <th>Client</th>
            <th>Status</th>
            <th style="text-align:right">Total</th>
            <th style="text-align:right">Paid</th>
            <th style="text-align:right">Balance</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($pendingPayBkgs as $bkg)
          @php $balance = max(0, (float) $bkg->final_amount - (float) $bkg->paid_so_far); @endphp
          <tr>
            <td><a href="{{ $bookingUrl($bkg->booking_id) }}" style="font-weight:600;color:var(--accent)">{{ $bkg->booking_reference }}</a></td>
            <td>{{ $bkg->company_name ?: $bkg->contact_person }}</td>
            <td><span class="badge {{ $payBadge[$bkg->payment_status] ?? 'badge-gray' }}">{{ $payLabel[$bkg->payment_status] ?? ucfirst($bkg->payment_status) }}</span></td>
            <td style="text-align:right">₱{{ number_format($bkg->final_amount, 2) }}</td>
            <td style="text-align:right;color:var(--green)">₱{{ number_format($bkg->paid_so_far, 2) }}</td>
            <td style="text-align:right;font-weight:700;color:var(--red)">₱{{ number_format($balance, 2) }}</td>
            <td>
              @if ($canRecord)
              <button class="btn btn-sm btn-primary" onclick="openPayForBooking({{ $bkg->booking_id }})">
                <i data-feather="credit-card" style="width:12px;height:12px"></i>
              </button>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @endif
    </div>
  </div>
</div>

<!-- SOA TAB -->
<div id="tab-soa" class="tab-pane {{ $tab === 'soa' ? 'active' : '' }}">
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Final Billing <span class="badge badge-blue" style="margin-left:8px">{{ $soaList->count() }}</span></h2>
    </div>
    <div class="card-body" style="padding-bottom:0">
      <form method="GET" action="{{ $billingBase }}">
        <input type="hidden" name="tab" value="soa">
        <div class="filter-bar" style="flex-wrap:wrap;gap:8px">
          <div class="search-input-wrap">
            <i data-feather="search"></i>
            <input type="text" name="q_soa" class="form-control" placeholder="Search booking ref, client, FB ref…"
                   value="{{ $soaSearch }}">
          </div>
          <select name="sstatus" class="form-control" style="width:auto">
            <option value="">All Statuses</option>
            <option value="draft" {{ $soaStatus === 'draft' ? 'selected' : '' }}>Draft</option>
            <option value="issued" {{ $soaStatus === 'issued' ? 'selected' : '' }}>Issued</option>
            <option value="paid" {{ $soaStatus === 'paid' ? 'selected' : '' }}>Paid</option>
            <option value="overdue" {{ $soaStatus === 'overdue' ? 'selected' : '' }}>Overdue</option>
          </select>
          <button type="submit" class="btn btn-primary btn-sm"><i data-feather="filter"></i> Filter</button>
          @if ($soaSearch || $soaStatus)
          <a href="{{ $billingBase }}?tab=soa" class="btn btn-secondary btn-sm">Clear</a>
          @endif
          @include('partials.export-dropdown', ['id' => 'BillingSoa', 'exportValue' => 'soa'])
        </div>
      </form>
    </div>
    <div class="table-wrap">
      @if ($soaList->isEmpty())
      <div class="empty-state"><i data-feather="file-text"></i><h3>No final billings generated yet</h3></div>
      @else
      <table>
        <thead>
          <tr>
            <th>FB Ref</th>
            <th>Booking</th>
            <th>Client</th>
            <th>Total Charges</th>
            <th>Paid</th>
            <th>Balance</th>
            <th>Due Date</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($soaList as $soa)
          @php
            $isOverdue = $soa->status !== 'paid' && $soa->due_date && strtotime($soa->due_date) < time();
            $daysUntilDue = $soa->due_date ? (int) floor((strtotime($soa->due_date) - time()) / 86400) : null;
            $isDueSoon = ! $isOverdue && $soa->status === 'issued' && $soa->balance > 0 && $daysUntilDue !== null && $daysUntilDue >= 0 && $daysUntilDue <= 7;
          @endphp
          <tr style="{{ $isOverdue ? 'background:#fff5f5' : ($isDueSoon ? 'background:#fffbeb' : '') }}">
            <td style="font-family:monospace;font-weight:600;font-size:.83rem">
              {{ $soa->soa_reference }}
            </td>
            <td>
              <a href="{{ $bookingUrl($soa->booking_id) }}" style="color:var(--accent);font-weight:600">
                {{ $soa->booking_reference }}
              </a>
            </td>
            <td>{{ $soa->company_name ?: $soa->contact_person }}</td>
            <td>₱{{ number_format($soa->total_charges, 2) }}</td>
            <td style="color:var(--green);font-weight:600">₱{{ number_format($soa->total_payments, 2) }}</td>
            <td style="font-weight:700;color:{{ $soa->balance > 0 ? 'var(--red)' : 'var(--green)' }}">
              ₱{{ number_format($soa->balance, 2) }}
            </td>
            <td style="font-size:.83rem;color:{{ $isOverdue ? 'var(--red)' : ($isDueSoon ? '#d97706' : 'inherit') }}">
              {{ $soa->due_date ? \Illuminate\Support\Carbon::parse($soa->due_date)->format('M j, Y') : '—' }}
              @if ($isOverdue)
              <span class="badge badge-red" style="font-size:.6rem;margin-left:4px">OVERDUE</span>
              @elseif ($isDueSoon)
              <span class="badge badge-yellow" style="font-size:.6rem;margin-left:4px">DUE IN {{ $daysUntilDue }}d</span>
              @endif
            </td>
            <td><span class="badge {{ $soaBadge[$soa->status] ?? 'badge-gray' }}">{{ ucfirst($soa->status) }}</span></td>
            <td style="white-space:nowrap">
              <a href="{{ route('billing-print', ['print_soa' => $soa->soa_id]) }}" class="btn btn-outline btn-sm" title="Print Statement of Account">
                <i data-feather="printer"></i>
              </a>
              <a href="{{ route('billing-print', ['print_invoice' => $soa->soa_id]) }}" class="btn btn-outline btn-sm" title="View Invoice">
                <i data-feather="file-text"></i>
              </a>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @endif
    </div>
  </div>
</div>

<!-- OVERDUE ACCOUNTS TAB -->
<div id="tab-overdue" class="tab-pane {{ $tab === 'overdue' ? 'active' : '' }}">
  <div class="card">
    <div class="card-header">
      <h2 class="card-title" style="color:var(--red)">
        <i data-feather="alert-triangle" style="width:15px;height:15px;margin-right:6px;vertical-align:middle"></i>
        Overdue Accounts
        @if ($overdueDetailList->isNotEmpty())
        <span style="font-size:.8rem;color:var(--muted);font-weight:400;margin-left:8px">
          — <strong style="color:var(--red)">₱{{ number_format($overdueDetailList->sum('balance'), 2) }}</strong> outstanding
        </span>
        @endif
      </h2>
    </div>
    <div class="card-body" style="padding-bottom:0">
      <form method="GET" action="{{ $billingBase }}">
        <input type="hidden" name="tab" value="overdue">
        <div class="filter-bar" style="flex-wrap:wrap;gap:8px">
          <div class="search-input-wrap">
            <i data-feather="search"></i>
            <input type="text" name="q_od" class="form-control" placeholder="Search client or booking ref…"
                   value="{{ $odSearch }}">
          </div>
          <button type="submit" class="btn btn-primary btn-sm"><i data-feather="filter"></i> Filter</button>
          @if ($odSearch)
          <a href="{{ $billingBase }}?tab=overdue" class="btn btn-secondary btn-sm">Clear</a>
          @endif
          @include('partials.export-dropdown', ['id' => 'BillingOverdue', 'exportValue' => 'overdue'])
        </div>
      </form>
    </div>
    <div class="table-wrap">
      @if ($overdueDetailList->isEmpty())
      <div class="empty-state"><i data-feather="check-circle"></i><h3>No overdue accounts</h3><p>All billings are current.</p></div>
      @else
      <table>
        <thead>
          <tr>
            <th>Client</th>
            <th>Contact</th>
            <th>Booking</th>
            <th>FB Ref</th>
            <th>Total Charges</th>
            <th>Paid</th>
            <th>Balance Due</th>
            <th>Due Date</th>
            <th>Days Overdue</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($overdueDetailList as $od)
          <tr style="background:#fff5f5">
            <td>
              <div style="font-weight:600">{{ $od->company_name ?: $od->contact_person }}</div>
              @if ($od->company_name && $od->contact_person)
              <div style="font-size:.75rem;color:var(--muted)">{{ $od->contact_person }}</div>
              @endif
            </td>
            <td>
              @if ($od->client_phone)
              <div style="font-size:.83rem">{{ $od->client_phone }}</div>
              @endif
              @if ($od->client_email)
              <div style="font-size:.75rem;color:var(--muted)">{{ $od->client_email }}</div>
              @endif
            </td>
            <td>
              <a href="{{ $bookingUrl($od->booking_id) }}" style="color:var(--accent);font-weight:600">
                {{ $od->booking_reference }}
              </a>
              @if ($od->project_title)
              <div style="font-size:.72rem;color:var(--muted)">{{ $od->project_title }}</div>
              @endif
            </td>
            <td style="font-family:monospace;font-size:.83rem">{{ $od->soa_reference }}</td>
            <td>₱{{ number_format($od->total_charges, 2) }}</td>
            <td style="color:var(--green)">₱{{ number_format($od->total_payments, 2) }}</td>
            <td style="font-weight:800;color:var(--red);font-size:1rem">₱{{ number_format($od->balance, 2) }}</td>
            <td style="font-size:.83rem;color:var(--red)">{{ \Illuminate\Support\Carbon::parse($od->due_date)->format('M j, Y') }}</td>
            <td>
              <span class="badge badge-red" style="font-size:.7rem">{{ $od->days_overdue }} day{{ $od->days_overdue !== 1 ? 's' : '' }}</span>
            </td>
            <td style="white-space:nowrap">
              <a href="{{ $bookingUrl($od->booking_id) }}" class="btn btn-outline btn-sm" title="View Booking">
                <i data-feather="eye"></i>
              </a>
              <a href="{{ route('billing-print', ['print_soa' => $od->soa_id]) }}" target="_blank" class="btn btn-outline btn-sm" title="Print / Save as PDF">
                <i data-feather="printer"></i>
              </a>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @endif
    </div>
  </div>
</div>

<!-- CANCELLATION CHARGES TAB -->
<div id="tab-cancellations" class="tab-pane {{ $tab === 'cancellations' ? 'active' : '' }}">
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Cancellation Charges
        @if ($cancellationCharges->isNotEmpty())
        <span style="font-size:.8rem;color:var(--muted);font-weight:400;margin-left:8px">
          — <strong style="color:var(--red)">₱{{ number_format($cancellationCharges->sum('penalty_amount'), 2) }}</strong> penalties &nbsp;·&nbsp; <strong style="color:var(--green)">₱{{ number_format($cancellationCharges->sum('refund_amount'), 2) }}</strong> refunds
        </span>
        @endif
      </h2>
    </div>
    <div class="card-body" style="padding-bottom:0">
      <form method="GET" action="{{ $billingBase }}">
        <input type="hidden" name="tab" value="cancellations">
        <div class="filter-bar" style="flex-wrap:wrap;gap:8px">
          <div class="search-input-wrap">
            <i data-feather="search"></i>
            <input type="text" name="q_cc" class="form-control" placeholder="Search client or booking ref…"
                   value="{{ $ccSearch }}">
          </div>
          <select name="ctype" class="form-control" style="width:auto">
            <option value="">All Types</option>
            <option value="client_request" {{ $ccType === 'client_request' ? 'selected' : '' }}>Client Request</option>
            <option value="admin_cancel" {{ $ccType === 'admin_cancel' ? 'selected' : '' }}>Admin Cancel</option>
            <option value="on_field" {{ $ccType === 'on_field' ? 'selected' : '' }}>On-Field</option>
          </select>
          <button type="submit" class="btn btn-primary btn-sm"><i data-feather="filter"></i> Filter</button>
          @if ($ccSearch || $ccType)
          <a href="{{ $billingBase }}?tab=cancellations" class="btn btn-secondary btn-sm">Clear</a>
          @endif
          @include('partials.export-dropdown', ['id' => 'BillingCancellations', 'exportValue' => 'cancellations'])
        </div>
      </form>
    </div>
    <div class="table-wrap">
      @if ($cancellationCharges->isEmpty())
      <div class="empty-state"><i data-feather="x-circle"></i><h3>No cancellation charges</h3><p>No approved cancellations with penalties.</p></div>
      @else
      <table>
        <thead>
          <tr>
            <th>Booking</th>
            <th>Client</th>
            <th>Type</th>
            <th>Penalty %</th>
            <th>Penalty Amount</th>
            <th>Refund Due</th>
            <th>Approved By</th>
            <th>Date</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @php $ctLabel = ['client_request' => 'Client Request', 'admin_cancel' => 'Admin Cancel', 'on_field' => 'On-Field (50%)']; @endphp
          @foreach ($cancellationCharges as $cc)
          <tr>
            <td>
              <a href="{{ $bookingUrl($cc->booking_id) }}" style="color:var(--accent);font-weight:600">
                {{ $cc->booking_reference }}
              </a>
              @if ($cc->project_title)
              <div style="font-size:.72rem;color:var(--muted)">{{ $cc->project_title }}</div>
              @endif
            </td>
            <td>{{ $cc->company_name ?: $cc->contact_person }}</td>
            <td><span class="badge badge-gray" style="font-size:.7rem">{{ $ctLabel[$cc->request_type] ?? $cc->request_type }}</span></td>
            <td style="font-weight:600">{{ round($cc->penalty_rate * 100) }}%</td>
            <td style="font-weight:700;color:var(--red)">₱{{ number_format($cc->penalty_amount, 2) }}</td>
            <td style="font-weight:600;color:{{ $cc->refund_amount > 0 ? 'var(--green)' : 'var(--muted)' }}">
              {{ $cc->refund_amount > 0 ? '₱' . number_format($cc->refund_amount, 2) : '—' }}
            </td>
            <td style="font-size:.83rem">{{ $cc->approved_by_name ?? '—' }}</td>
            <td style="font-size:.83rem">{{ $cc->approved_at ? \Illuminate\Support\Carbon::parse($cc->approved_at)->format('M j, Y') : '—' }}</td>
            <td>
              <a href="{{ $bookingUrl($cc->booking_id) }}" class="btn btn-outline btn-sm">
                <i data-feather="eye"></i>
              </a>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @endif
    </div>
  </div>
</div>

<!-- DISCOUNTS TAB -->
<div id="tab-discounts" class="tab-pane {{ $tab === 'discounts' ? 'active' : '' }}">
  <div class="card" style="margin-bottom:16px">
    <div class="card-header">
      <h2 class="card-title">Pending Approval <span class="badge badge-yellow" style="margin-left:8px">{{ $pendingDiscounts->count() }}</span></h2>
      @include('partials.export-dropdown', ['id' => 'BillingDiscounts', 'exportValue' => 'discounts'])
    </div>
    <div class="table-wrap">
      @if ($pendingDiscounts->isEmpty())
      <div class="empty-state"><i data-feather="percent"></i><h3>No discounts awaiting approval</h3></div>
      @else
      <table>
        <thead>
          <tr>
            <th>Booking Ref</th>
            <th>Client</th>
            <th>Discount</th>
            <th>Reason</th>
            <th>Proposed By</th>
            <th>Date</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($pendingDiscounts as $d)
          <tr>
            <td><a href="{{ $bookingUrl($d->booking_id) }}" style="font-weight:600;color:var(--accent)">{{ $d->booking_reference }}</a></td>
            <td>{{ $d->company_name ?: $d->contact_person }}</td>
            <td style="font-weight:700">{{ $d->discount_type === 'percent' ? $d->discount_value.'%' : '₱'.number_format($d->discount_value, 2) }}</td>
            <td style="font-size:.83rem;color:var(--muted)">{{ $d->reason ?: '—' }}</td>
            <td style="font-size:.83rem">{{ $d->proposed_by_name ?? '—' }}</td>
            <td style="font-size:.83rem">{{ \Illuminate\Support\Carbon::parse($d->created_at)->format('M j, Y') }}</td>
            <td>
              <div style="display:flex;gap:5px">
                <button class="btn btn-sm btn-success" onclick="openApproveDiscountModal({{ $d->discount_id }}, {{ $d->booking_id }}, '{{ $d->discount_type === 'percent' ? $d->discount_value.'%' : '₱'.number_format($d->discount_value, 2) }}')">
                  <i data-feather="check" style="width:12px;height:12px"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="openRejectDiscountModal({{ $d->discount_id }}, {{ $d->booking_id }})">
                  <i data-feather="x" style="width:12px;height:12px"></i>
                </button>
              </div>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @endif
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Recent History</h2>
    </div>
    <div class="table-wrap">
      @if ($discountHistory->isEmpty())
      <div class="empty-state"><i data-feather="clock"></i><h3>No reviewed discounts yet</h3></div>
      @else
      <table>
        <thead>
          <tr>
            <th>Booking Ref</th>
            <th>Client</th>
            <th>Discount</th>
            <th>Status</th>
            <th>Reviewed By</th>
            <th>Date</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($discountHistory as $d)
          <tr>
            <td><a href="{{ $bookingUrl($d->booking_id) }}" style="font-weight:600;color:var(--accent)">{{ $d->booking_reference }}</a></td>
            <td>{{ $d->company_name ?: $d->contact_person }}</td>
            <td style="font-weight:600">{{ $d->discount_type === 'percent' ? $d->discount_value.'%' : '₱'.number_format($d->discount_value, 2) }}</td>
            <td><span class="badge {{ $d->status === 'approved' ? 'badge-green' : 'badge-red' }}">{{ ucfirst($d->status) }}</span></td>
            <td style="font-size:.83rem">{{ $d->reviewed_by_name ?? '—' }}</td>
            <td style="font-size:.83rem">{{ $d->approved_at ? \Illuminate\Support\Carbon::parse($d->approved_at)->format('M j, Y') : '—' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @endif
    </div>
  </div>
</div>

</div><!-- /tab panes -->

@if ($canRecord)
<!-- RECORD PAYMENT MODAL -->
<style>
.crew-slot-drop{display:none;position:absolute;left:0;right:0;top:calc(100% + 3px);background:var(--surface);border:1px solid var(--border);border-radius:8px;box-shadow:0 6px 24px rgba(0,30,80,.14);z-index:9999;max-height:220px;overflow-y:auto;min-width:210px}
.csd-item{padding:8px 12px;cursor:pointer;border-bottom:1px solid var(--border);transition:background .1s}
.csd-item:last-child{border-bottom:none}
.csd-item:hover{background:var(--acclight,#e5f0ff)}
.csd-empty{padding:12px;font-size:.8rem;color:var(--text-muted,var(--muted))}
.csd-ref{font-weight:700;font-size:.85rem;color:var(--text)}
.csd-sub{font-size:.75rem;color:var(--text-muted,var(--muted));margin-top:2px}

.rp-step-lbl{display:flex;align-items:center;gap:8px;font-size:.78rem;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.03em;margin:14px 0 8px}
.rp-step-n{display:flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;background:var(--accent);color:#fff;font-size:.68rem;font-weight:800;flex-shrink:0;text-transform:none;letter-spacing:0}
.rp-summary{background:var(--s2,rgba(59,130,246,.05));border:1px solid var(--border);border-radius:var(--radius-md);padding:4px 12px;margin-bottom:4px}
.rp-row{display:flex;align-items:center;justify-content:space-between;padding:7px 0;font-size:.83rem;color:var(--text-muted,var(--muted));border-bottom:1px solid var(--border)}
.rp-row:last-child{border-bottom:none}
.rp-row strong{color:var(--text);font-weight:700}
.rp-row-suggest strong{color:var(--accent)}
.rp-found{display:none;align-items:center;gap:5px;font-size:.74rem;color:var(--green);font-weight:600;margin-top:5px}
.rp-found.show{display:flex}
.rp-2col{display:grid;grid-template-columns:1fr 1fr;gap:0 24px}
.rp-2col .form-group{margin-bottom:12px}
@media (max-width:640px){.rp-2col{grid-template-columns:1fr}}
.rp-amt-wrap{position:relative}
.rp-amt-wrap .rp-peso{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-weight:700;pointer-events:none}
.rp-amt-wrap input{padding-left:26px !important;font-size:1.05rem;font-weight:700}
</style>
<div class="modal-overlay" id="modalAddPayment">
  <div class="modal" style="max-width:720px">
    <div class="modal-header">
      <h3 class="modal-title"><i data-feather="credit-card" style="width:18px;height:18px;margin-right:8px;vertical-align:middle"></i>Record Payment</h3>
      <button class="modal-close" data-modal-close><i data-feather="x"></i></button>
    </div>
    <form method="POST" action="{{ $billingBase }}" onsubmit="return prepPayment()">
      @csrf
      <input type="hidden" name="action" value="record_payment">
      <input type="hidden" name="payment_type" id="payType" value="downpayment">
      <div class="modal-body">
        <div class="rp-2col">

          <div>
            <div class="rp-step-lbl"><span class="rp-step-n">1</span> Select Booking / Invoice</div>
            <div class="form-group">
              <label>Booking Reference *</label>
              <div style="position:relative">
                <input type="text" id="bkgSearchTxt" class="form-control" placeholder="Search by reference or client name…" autocomplete="off"
                       oninput="openBkgSearch(this.value)" onfocus="openBkgSearch(this.value)" onblur="closeBkgSearch(200)">
                <input type="hidden" name="booking_id" id="bkgSelectedId">
                <div class="crew-slot-drop" id="bkgDrop"></div>
              </div>
              <div class="rp-found" id="bkgFoundNote"><i data-feather="check-circle" style="width:12px;height:12px"></i> Booking found</div>
            </div>

            <div id="balanceInfo" style="display:none">
              <div class="rp-step-lbl" style="margin-top:16px"><span class="rp-step-n">2</span> Billing Summary <span style="font-weight:400;color:var(--text-muted);font-size:.72rem">(Auto-filled)</span></div>
              <div class="rp-summary">
                <div class="rp-row"><span>Billing Total</span><strong id="bkTotal">—</strong></div>
                <div class="rp-row"><span>Total Paid to Date</span><strong id="bkPaid" style="color:var(--green)">—</strong></div>
                <div class="rp-row"><span>Outstanding Balance</span><strong id="bkBalance" style="color:var(--red)">—</strong></div>
                <div class="rp-row"><span>Payment Purpose</span><strong id="bkPurpose">—</strong></div>
                <div class="rp-row"><span>Document Type</span><strong id="bkDocType">—</strong></div>
                <div class="rp-row rp-row-suggest"><span>Suggested Amount Due</span><strong id="bkSuggested">—</strong></div>
              </div>
            </div>
          </div>

          <div>
            <div class="rp-step-lbl"><span class="rp-step-n">3</span> Payment Details</div>
            <div class="form-group">
              <label>Amount Received (₱) *</label>
              <div class="rp-amt-wrap">
                <span class="rp-peso">₱</span>
                <input type="number" name="amount" id="payAmount" class="form-control" step="0.01" min="0.01" required oninput="onPayAmountChange()">
              </div>
              <div id="payAmountMax" style="font-size:.72rem;color:var(--text-muted);margin-top:3px"></div>
            </div>
            <div class="form-group">
              <label>Payment Method *</label>
              <select name="payment_method" id="billingPayMethod" class="form-control" required onchange="toggleBillingRefField()">
                <option value="cash" selected>Cash</option>
                <option value="gcash">GCash</option>
              </select>
            </div>
            <div class="form-group" id="billingRefWrap" style="display:none">
              <label>GCash Reference / Transaction ID</label>
              <input type="text" name="reference_number" class="form-control" placeholder="GCash transaction ID">
            </div>
            <div class="form-group">
              <label>Payment Date *</label>
              <input type="date" name="payment_date" id="payDate" class="form-control" value="{{ now()->toDateString() }}" min="{{ now()->toDateString() }}" required>
            </div>
            <div class="form-group">
              <label>Notes (Optional)</label>
              <textarea name="notes" class="form-control" rows="2" placeholder="Additional payment notes…"></textarea>
            </div>
            <div id="payFullSettleNote" style="display:none;font-size:.78rem;color:var(--accent);background:rgba(59,130,246,.08);border-radius:6px;padding:8px 10px">
              <i data-feather="info" style="width:12px;height:12px;vertical-align:middle"></i> This payment will fully settle the outstanding balance.
            </div>
          </div>

        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Record Payment</button>
      </div>
    </form>
  </div>
</div>

<!-- VOID PAYMENT MODAL -->
<div class="modal-overlay" id="modalVoidPayment">
  <div class="modal" style="max-width:460px">
    <div class="modal-header">
      <h3 class="modal-title"><i data-feather="rotate-ccw" style="width:16px;height:16px;margin-right:6px;vertical-align:middle;color:var(--red)"></i>Void Payment</h3>
      <button class="modal-close" data-modal-close><i data-feather="x"></i></button>
    </div>
    <form method="POST" action="{{ $billingBase }}">
      @csrf
      <input type="hidden" name="action" value="void_payment">
      <input type="hidden" name="payment_id" id="voidPaymentId">
      <div class="modal-body">
        <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:13px">
          Voiding <strong id="voidPaymentLabel"></strong> does not delete it — it's flagged as voided and a matching reversing entry is recorded, so the booking's balance updates correctly and the full history stays visible for audit.
        </div>
        <div class="form-group">
          <label>Reason *</label>
          <textarea name="void_reason" class="form-control" rows="2" placeholder="e.g. Duplicate entry, wrong amount, client dispute…" required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-danger">
          <i data-feather="rotate-ccw" style="width:13px;height:13px;margin-right:4px;vertical-align:middle"></i>Void Payment
        </button>
      </div>
    </form>
  </div>
</div>

<!-- APPROVE DISCOUNT MODAL -->
<div class="modal-overlay" id="modalApproveDiscount">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <h3><i data-feather="check" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Approve Discount</h3>
      <button class="modal-close" onclick="closeModal('modalApproveDiscount')">&times;</button>
    </div>
    <form method="POST" action="{{ $billingBase }}">
      @csrf
      <input type="hidden" name="action" value="approve_discount">
      <input type="hidden" name="booking_id" id="approveDiscountBookingId">
      <input type="hidden" name="discount_id" id="approveDiscountId">
      <div class="modal-body">
        <div style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.3);border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:13px">
          Approving <strong id="approveDiscountLabel"></strong> will apply it to the cost estimate and recalculate the booking total. If the client already approved the cost, they'll need to re-approve.
        </div>
        <div class="form-group">
          <label>Notes</label>
          <textarea name="review_notes" class="form-control" rows="2" placeholder="Optional notes…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalApproveDiscount')">Cancel</button>
        <button type="submit" class="btn btn-success">
          <i data-feather="check" style="width:13px;height:13px;margin-right:4px;vertical-align:middle"></i>Approve Discount
        </button>
      </div>
    </form>
  </div>
</div>

<!-- REJECT DISCOUNT MODAL -->
<div class="modal-overlay" id="modalRejectDiscount">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <h3><i data-feather="x" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Reject Discount</h3>
      <button class="modal-close" onclick="closeModal('modalRejectDiscount')">&times;</button>
    </div>
    <form method="POST" action="{{ $billingBase }}">
      @csrf
      <input type="hidden" name="action" value="reject_discount">
      <input type="hidden" name="booking_id" id="rejectDiscountBookingId">
      <input type="hidden" name="discount_id" id="rejectDiscountId">
      <div class="modal-body">
        <div class="form-group">
          <label>Reason for rejection</label>
          <textarea name="review_notes" class="form-control" rows="2" placeholder="Optional — let the proposer know why…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalRejectDiscount')">Cancel</button>
        <button type="submit" class="btn btn-danger">
          <i data-feather="x" style="width:13px;height:13px;margin-right:4px;vertical-align:middle"></i>Reject Discount
        </button>
      </div>
    </form>
  </div>
</div>
@endif

@push('scripts')
<script>
function openVoidPayment(paymentId, label) {
  document.getElementById('voidPaymentId').value = paymentId;
  document.getElementById('voidPaymentLabel').textContent = label;
  openModal('modalVoidPayment');
}
function openApproveDiscountModal(discountId, bookingId, label) {
  document.getElementById('approveDiscountId').value = discountId;
  document.getElementById('approveDiscountBookingId').value = bookingId;
  document.getElementById('approveDiscountLabel').textContent = label;
  openModal('modalApproveDiscount');
}
function openRejectDiscountModal(discountId, bookingId) {
  document.getElementById('rejectDiscountId').value = discountId;
  document.getElementById('rejectDiscountBookingId').value = bookingId;
  openModal('modalRejectDiscount');
}

function toggleBillingRefField() {
  const isGcash = document.getElementById('billingPayMethod')?.value === 'gcash';
  document.getElementById('billingRefWrap').style.display = isGcash ? '' : 'none';
}

// ── Record Payment: searchable booking picker ───────────────────────────────
const pendingPayBkgs = {!! $pendingPayBkgs->values()->toJson() !!};

function _escH(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

function _bkgLabel(b) {
  return (b.booking_reference || '') + ' — ' + (b.company_name || b.contact_person || '');
}

function _bkgDropHtml(items) {
  if (!items.length) return '<div class="csd-empty">No matching bookings</div>';
  return items.map((b) => {
    const total = parseFloat(b.final_amount) || 0;
    const paid = parseFloat(b.paid_so_far) || 0;
    const amt = total > 0
      ? ` &middot; ₱${total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}, paid ₱${paid.toLocaleString('en-PH', { minimumFractionDigits: 2 })}`
      : '';
    return `<div class="csd-item" data-id="${b.booking_id}" data-total="${total}" data-paid="${paid}" data-vat="${b.is_vat_registered ? 1 : 0}" data-label="${_escH(_bkgLabel(b))}">
      <div class="csd-ref">${_escH(b.booking_reference)}</div>
      <div class="csd-sub">${_escH(b.company_name || b.contact_person || '')}${amt}</div>
    </div>`;
  }).join('');
}

function openBkgSearch(q) {
  const lq = (q || '').toLowerCase().trim();
  const pool = pendingPayBkgs.filter((b) => {
    if (!lq) return true;
    const name = (b.company_name || b.contact_person || '').toLowerCase();
    return (b.booking_reference || '').toLowerCase().includes(lq) || name.includes(lq);
  }).slice(0, 30);
  const drop = document.getElementById('bkgDrop');
  if (!drop) return;
  drop.innerHTML = _bkgDropHtml(pool);
  positionSearchDrop(document.getElementById('bkgSearchTxt'), drop);
  drop.style.display = 'block';
  drop.querySelectorAll('.csd-item').forEach((el) => {
    el.addEventListener('mousedown', (e) => {
      e.preventDefault();
      document.getElementById('bkgSelectedId').value = el.dataset.id;
      document.getElementById('bkgSearchTxt').value = el.dataset.label;
      drop.style.display = 'none';
      fillPaymentInfoFromData(parseFloat(el.dataset.total) || 0, parseFloat(el.dataset.paid) || 0, el.dataset.vat === '1');
    });
  });
}
function closeBkgSearch(ms) {
  setTimeout(() => { const d = document.getElementById('bkgDrop'); if (d) d.style.display = 'none'; }, ms);
}
function prepPayment() {
  if (!document.getElementById('bkgSelectedId').value) {
    alert('Please select a booking from the search.');
    return false;
  }
  return true;
}

// Module-level state for the currently-selected booking, so the amount field can be edited
// after selection and everything downstream (purpose, full-settle note) stays in sync.
let _rpTotal = 0, _rpPaid = 0, _rpBalance = 0;

function fillPaymentInfoFromData(total, paid, isVat) {
  _rpTotal = total; _rpPaid = paid; _rpBalance = Math.max(0, total - paid);
  const fmt = (n) => '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2 });

  document.getElementById('bkgFoundNote').classList.add('show');
  document.getElementById('balanceInfo').style.display = 'block';
  document.getElementById('bkTotal').textContent = fmt(total);
  document.getElementById('bkPaid').textContent = fmt(paid);
  document.getElementById('bkBalance').textContent = fmt(_rpBalance);
  document.getElementById('bkDocType').textContent = isVat ? 'Official Receipt (VAT)' : 'Acknowledgement Receipt';
  document.getElementById('bkSuggested').textContent = _rpBalance > 0 ? fmt(_rpBalance) : '—';
  document.getElementById('payAmountMax').textContent = _rpBalance > 0 ? 'Max: ' + fmt(_rpBalance) : '';

  document.getElementById('payAmount').value = _rpBalance > 0 ? _rpBalance.toFixed(2) : '';
  document.getElementById('payAmount').max = _rpBalance > 0 ? _rpBalance.toFixed(2) : '';
  onPayAmountChange();
}

// Payment Type/Purpose auto-derive from where this payment lands relative to the booking's
// billing state — first payment is a Downpayment, a payment that fully settles what's left is
// the Final Payment, anything else in between is a Progress Payment. Still just a *default*:
// the dropdown stays editable for the rare case staff need to override it.
function onPayAmountChange() {
  const amt = parseFloat(document.getElementById('payAmount').value) || 0;
  let purpose = 'Downpayment', type = 'downpayment';
  if (_rpPaid > 0) {
    if (amt >= _rpBalance - 0.005 && _rpBalance > 0) { purpose = 'Final Balance'; type = 'final'; }
    else { purpose = 'Progress Payment'; type = 'progress'; }
  }
  const purposeEl = document.getElementById('bkPurpose');
  if (purposeEl) purposeEl.textContent = purpose;
  const typeSel = document.getElementById('payType');
  if (typeSel) typeSel.value = type;

  const settleNote = document.getElementById('payFullSettleNote');
  if (settleNote) settleNote.style.display = (_rpBalance > 0 && amt >= _rpBalance - 0.005) ? 'block' : 'none';
}

function resetPaymentModal() {
  document.getElementById('bkgSelectedId').value = '';
  document.getElementById('bkgSearchTxt').value = '';
  document.getElementById('bkgFoundNote').classList.remove('show');
  document.getElementById('balanceInfo').style.display = 'none';
  document.getElementById('payAmount').value = '';
  document.getElementById('payAmountMax').textContent = '';
  document.getElementById('payFullSettleNote').style.display = 'none';
  document.getElementById('payType').value = 'downpayment';
  _rpTotal = 0; _rpPaid = 0; _rpBalance = 0;
}

function openPayForBooking(bookingId) {
  const b = pendingPayBkgs.find((x) => String(x.booking_id) === String(bookingId));
  if (b) {
    document.getElementById('bkgSelectedId').value = b.booking_id;
    document.getElementById('bkgSearchTxt').value = _bkgLabel(b);
    fillPaymentInfoFromData(parseFloat(b.final_amount) || 0, parseFloat(b.paid_so_far) || 0, !!b.is_vat_registered);
  }
  openModal('modalAddPayment');
}
</script>
@endpush
@endsection
