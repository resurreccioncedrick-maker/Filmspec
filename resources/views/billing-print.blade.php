<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{{ $printReceipt ? 'Receipt' : 'Final Billing' }} — FilmSpec</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'DM Sans',sans-serif; font-size:13px; color:#0f172a; background:white; padding:24px; }
    .doc { max-width:680px; margin:0 auto; }

    .doc-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:28px; padding-bottom:18px; border-bottom:3px solid #003D80; }
    .brand { display:flex; align-items:center; gap:10px; }
    .brand-icon { width:44px; height:44px; background:linear-gradient(135deg,#003D80,#0060C7); border-radius:8px; display:flex; align-items:center; justify-content:center; color:white; font-family:'Syne',sans-serif; font-weight:800; font-size:16px; }
    .brand-name { font-family:'Syne',sans-serif; font-size:1.3rem; font-weight:800; color:#0d1f3c; }
    .brand-sub { font-size:10px; color:#64748b; margin-top:2px; }

    .doc-title { text-align:right; }
    .doc-type { font-family:'Syne',sans-serif; font-size:1.1rem; font-weight:800; color:#003D80; text-transform:uppercase; letter-spacing:1px; }
    .doc-ref { font-size:11px; color:#64748b; margin-top:4px; }

    .meta-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:22px; }
    .meta-block label { font-size:10px; text-transform:uppercase; letter-spacing:.08em; color:#94a3b8; font-weight:600; display:block; margin-bottom:3px; }
    .meta-block value { font-size:13px; font-weight:600; color:#0f172a; }

    table { width:100%; border-collapse:collapse; margin-bottom:18px; }
    th { background:#E5F0FF; color:#003D80; font-size:11px; text-transform:uppercase; letter-spacing:.06em; padding:8px 12px; text-align:left; border-bottom:1px solid #A8D0FF; }
    td { padding:9px 12px; border-bottom:1px solid #e2e8f0; font-size:12px; vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    tfoot td { font-weight:700; padding:10px 12px; }
    tfoot tr:last-child td { background:#E5F0FF; font-size:14px; color:#003D80; }

    .total-row { display:flex; justify-content:flex-end; }
    .total-table { min-width:280px; }
    .total-table td:first-child { color:#475569; }
    .total-table td:last-child { text-align:right; font-weight:600; }
    .grand-total td { background:#0d1f3c; color:white !important; font-family:'Syne',sans-serif; font-weight:800; font-size:15px; padding:12px 14px; }

    .footer-note { margin-top:28px; padding-top:14px; border-top:1px solid #e2e8f0; font-size:10px; color:#94a3b8; text-align:center; }
    .sig-area { display:grid; grid-template-columns:1fr 1fr; gap:40px; margin-top:40px; }
    .sig-line { border-top:1px solid #94a3b8; padding-top:6px; font-size:11px; color:#475569; text-align:center; }

    @media print {
      body { padding:0; }
      .no-print, .print-controls { display:none !important; }
    }
  </style>
</head>
<body>
<div class="no-print" style="margin-bottom:20px;display:flex;gap:10px">
  <button onclick="window.print()" style="padding:8px 18px;background:#003D80;color:white;border:none;border-radius:6px;cursor:pointer;font-family:inherit">Print</button>
  <button onclick="window.print()" style="padding:8px 18px;background:#c0392b;color:white;border:none;border-radius:6px;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:6px">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
    Save as PDF
  </button>
  <button onclick="closePrintView()" style="padding:8px 18px;background:#f1f5f9;color:#0f172a;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer;font-family:inherit">Close</button>
</div>

<script>
  // window.close() is silently refused by the browser unless this tab was opened by script
  // (window.open()/target="_blank"). Most links into this page are plain same-tab navigation,
  // so fall back to going back — or to the booking itself if there's no history to return to.
  function closePrintView() {
    if (window.opener) { window.close(); return; }
    if (window.history.length > 1) { window.history.back(); return; }
    window.location.href = @json($fallbackBookingId ? route('booking-detail', $fallbackBookingId) : route('billing'));
  }
</script>

<div class="doc">
  <div class="doc-header">
    <div class="brand">
      <img src="{{ asset('assets/images/logo.png') }}" alt="FilmSpec" style="height:48px;max-width:180px;object-fit:contain">
      <div>
        <div class="brand-sub">Equipment Resource Ecosystem</div>
        <div class="brand-sub">TIN: {{ config('filmspec.company_tin') }}</div>
      </div>
    </div>
    <div class="doc-title">
      <div class="doc-type">{{ $printReceipt ? $type : 'Final Billing' }}</div>
      <div class="doc-ref">
        @if ($printReceipt)
          No. {{ $pay->receipt_number ?: 'N/A' }} &nbsp;·&nbsp; {{ date('F j, Y', strtotime($pay->payment_date)) }}
        @else
          {{ $soa->soa_reference }} &nbsp;·&nbsp; {{ date('F j, Y', strtotime($soa->soa_date)) }}
        @endif
      </div>
    </div>
  </div>

  @if ($printReceipt)
  <!-- RECEIPT -->
  <div class="meta-grid">
    <div class="meta-block"><label>Received From</label><value>{{ $pay->company_name ?: $pay->contact_person }}</value></div>
    <div class="meta-block"><label>Booking Reference</label><value>{{ $pay->booking_reference }}</value></div>
    <div class="meta-block"><label>Project</label><value>{{ $pay->project_title ?: '—' }}</value></div>
    <div class="meta-block"><label>Payment Method</label><value>{{ ucwords(str_replace('_',' ',$pay->payment_method)) }}</value></div>
    <div class="meta-block"><label>Reference No.</label><value>{{ $pay->reference_number ?: '—' }}</value></div>
    <div class="meta-block"><label>Payment Type</label><value>{{ ucfirst($pay->payment_type) }}</value></div>
  </div>

  <div class="total-row">
    <table class="total-table">
      <tr><td style="padding:8px 0">Amount Received</td><td>₱{{ number_format($pay->amount,2) }}</td></tr>
      @if ($pay->is_vat)
      <tr><td style="padding:8px 0;color:#64748b">Net of VAT</td><td>₱{{ number_format($pay->amount/1.12,2) }}</td></tr>
      <tr><td style="padding:8px 0;color:#64748b">VAT (12%)</td><td>₱{{ number_format($pay->amount-($pay->amount/1.12),2) }}</td></tr>
      @endif
      <tr class="grand-total"><td>TOTAL AMOUNT PAID</td><td>₱{{ number_format($pay->amount,2) }}</td></tr>
    </table>
  </div>

  @if ($pay->notes)
  <p style="margin-top:14px;font-size:12px;color:#475569"><strong>Notes:</strong> {{ $pay->notes }}</p>
  @endif

  @else
  <!-- SOA -->
  <div class="meta-grid">
    <div class="meta-block"><label>Client</label><value>{{ $soa->company_name ?: $soa->contact_person }}</value></div>
    <div class="meta-block"><label>Booking Reference</label><value>{{ $soa->booking_reference }}</value></div>
    <div class="meta-block"><label>Project</label><value>{{ $soa->project_title ?: '—' }}</value></div>
    <div class="meta-block"><label>Shoot Period</label><value>{{ date('M j', strtotime($soa->shoot_date_start)) }} – {{ date('M j, Y', strtotime($soa->shoot_date_end)) }}</value></div>
    <div class="meta-block"><label>Billing Date</label><value>{{ date('F j, Y', strtotime($soa->soa_date)) }}</value></div>
    <div class="meta-block"><label>Due Date</label><value>{{ $soa->due_date ? date('F j, Y', strtotime($soa->due_date)) : '—' }}</value></div>
  </div>

  @if ($equipLines->isNotEmpty())
  <p style="font-weight:700;margin-bottom:6px;color:#003D80">Equipment</p>
  <table>
    <thead><tr><th>Item</th><th>Qty</th><th>Days</th><th>Rate/Day</th><th style="text-align:right">Subtotal</th></tr></thead>
    <tbody>
      @foreach ($equipLines as $el)
      <tr>
        <td>{{ $el->equipment_name }} {{ $el->brand ? '('.$el->brand.')' : '' }}</td>
        <td>{{ $el->quantity }}</td>
        <td>{{ $el->days }}</td>
        <td>₱{{ number_format($el->daily_rate,2) }}</td>
        <td style="text-align:right">₱{{ number_format($el->subtotal,2) }}</td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr><td colspan="4" style="text-align:right">Equipment Total</td>
          <td style="text-align:right">₱{{ number_format($equipLines->sum('subtotal'),2) }}</td></tr>
    </tfoot>
  </table>
  @endif

  @if ($crewLines->isNotEmpty())
  <p style="font-weight:700;margin-bottom:6px;color:#003D80">Crew Services</p>
  <table>
    <thead><tr><th>Crew</th><th>Position</th><th>Rate</th><th style="text-align:right">Subtotal</th></tr></thead>
    <tbody>
      @foreach ($crewLines as $cl)
      <tr>
        <td>{{ $cl->crew_name }}</td>
        <td>{{ $cl->position_name ?? '—' }}</td>
        <td>₱{{ number_format($cl->rate_used,2) }}/12hr</td>
        <td style="text-align:right">₱{{ number_format($cl->subtotal,2) }}</td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr><td colspan="3" style="text-align:right">Crew Total</td>
          <td style="text-align:right">₱{{ number_format($crewLines->sum('subtotal'),2) }}</td></tr>
    </tfoot>
  </table>
  @endif

  @if (!empty($soa->transportation_cost) && $soa->transportation_cost > 0)
  <p style="font-weight:700;margin-bottom:6px;color:#003D80">Transportation</p>
  <table>
    <thead><tr><th>Description</th><th style="text-align:right">Amount</th></tr></thead>
    <tbody>
      <tr>
        <td>Delivery / Transportation{{ $soa->delivery_address ? ' — '.$soa->delivery_address : '' }}</td>
        <td style="text-align:right">₱{{ number_format($soa->transportation_cost,2) }}</td>
      </tr>
    </tbody>
    <tfoot>
      <tr><td style="text-align:right">Transportation Total</td>
          <td style="text-align:right">₱{{ number_format($soa->transportation_cost,2) }}</td></tr>
    </tfoot>
  </table>
  @endif

  @if ($incidentCharges->isNotEmpty())
  <p style="font-weight:700;margin-bottom:6px;color:#c0392b">Incident Charges &amp; Penalties</p>
  <table>
    <thead><tr><th>Type</th><th>Item</th><th>Description</th><th style="text-align:right">Charge</th></tr></thead>
    <tbody>
      @foreach ($incidentCharges as $inc)
      <tr>
        <td>{{ ucwords(str_replace('_',' ',$inc->incident_type)) }}</td>
        <td>{{ $inc->equipment_name }}</td>
        <td style="font-size:11px;color:#64748b">{{ $inc->description ?: '—' }}</td>
        <td style="text-align:right;color:#c0392b">₱{{ number_format($inc->charge_amount,2) }}</td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr><td colspan="3" style="text-align:right">Total Charges</td>
          <td style="text-align:right;color:#c0392b">₱{{ number_format($incidentCharges->sum('charge_amount'),2) }}</td></tr>
    </tfoot>
  </table>
  @endif

  @if ($payLines->isNotEmpty())
  <p style="font-weight:700;margin-bottom:6px;color:#003D80">Payments Received</p>
  <table>
    <thead><tr><th>Date</th><th>Type</th><th>Method</th><th>Ref No.</th><th style="text-align:right">Amount</th></tr></thead>
    <tbody>
      @foreach ($payLines as $pl)
      <tr>
        <td>{{ date('M j, Y', strtotime($pl->payment_date)) }}</td>
        <td>{{ ucfirst($pl->payment_type) }}</td>
        <td>{{ ucwords(str_replace('_',' ',$pl->payment_method)) }}</td>
        <td style="font-family:monospace">{{ $pl->reference_number ?: '—' }}</td>
        <td style="text-align:right;color:#16a34a">₱{{ number_format($pl->amount,2) }}</td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr><td colspan="4" style="text-align:right">Total Paid</td>
          <td style="text-align:right;color:#16a34a">₱{{ number_format($soa->total_payments,2) }}</td></tr>
    </tfoot>
  </table>
  @endif

  <div class="total-row" style="margin-top:8px">
    <table class="total-table">
      <tr><td style="padding:8px 0">Total Charges</td><td>₱{{ number_format($soa->total_charges,2) }}</td></tr>
      <tr><td style="padding:8px 0;color:#16a34a">Total Paid</td><td style="color:#16a34a">₱{{ number_format($soa->total_payments,2) }}</td></tr>
      <tr class="grand-total">
        <td>BALANCE DUE</td>
        <td>₱{{ number_format($soa->balance,2) }}</td>
      </tr>
    </table>
  </div>
  @endif

  <div class="sig-area">
    <div>
      <div style="height:36px"></div>
      <div class="sig-line">Prepared by / Cashier</div>
    </div>
    <div>
      <div style="height:36px"></div>
      <div class="sig-line">Received by / Client Signature</div>
    </div>
  </div>

  <div class="footer-note">
    FilmSpec — Equipment Resource Ecosystem &nbsp;·&nbsp; This is a computer-generated document.
    @if ($printReceipt && $pay->is_vat)
    This serves as an Official Receipt for VAT purposes.
    @endif
  </div>
</div>

<script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
