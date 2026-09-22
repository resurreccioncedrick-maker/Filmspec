<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Receipt {{ $payment->receipt_number ?: '#' . $payment->payment_id }} — FilmSpec</title>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family:'DM Sans',sans-serif; font-size:13px; color:#0f172a; background:white; padding:24px; }
    .doc { max-width:680px; margin:0 auto; }

    .doc-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:28px; padding-bottom:18px; border-bottom:3px solid #003D80; }
    .brand { display:flex; align-items:center; gap:10px; }
    .brand-sub { font-size:10px; color:#64748b; margin-top:2px; }

    .doc-title { text-align:right; }
    .doc-type { font-family:'Syne',sans-serif; font-size:1.1rem; font-weight:800; color:#003D80; text-transform:uppercase; letter-spacing:1px; }
    .doc-ref { font-size:11px; color:#64748b; margin-top:4px; }

    .meta-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:22px; }
    .meta-block label { font-size:10px; text-transform:uppercase; letter-spacing:.08em; color:#94a3b8; font-weight:600; display:block; margin-bottom:3px; }
    .meta-block value { font-size:13px; font-weight:600; color:#0f172a; }

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
      .no-print { display:none !important; }
    }

    @media (max-width:640px) {
      .doc-header { flex-direction:column; gap:14px; }
      .doc-title { text-align:left; }
      .meta-grid { grid-template-columns:1fr; }
      .sig-area { grid-template-columns:1fr; gap:24px; }
    }
  </style>
</head>
<body>

<div class="no-print" style="margin-bottom:20px;display:flex;gap:10px">
  <button onclick="window.print()" style="padding:8px 18px;background:#003D80;color:white;border:none;border-radius:6px;cursor:pointer;font-family:inherit">Print / Save as PDF</button>
  @if ($role === 'client')
  <a href="{{ route('client-booking-detail', $payment->booking_id) }}" style="padding:8px 18px;background:#f1f5f9;color:#0f172a;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center">&larr; Booking</a>
  @else
  <a href="{{ route('booking-detail', $payment->booking_id) }}" style="padding:8px 18px;background:#f1f5f9;color:#0f172a;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-flex;align-items:center">&larr; Booking</a>
  @endif
</div>

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
      <div class="doc-type">{{ $receiptTypeLabel[$payment->receipt_type] ?? 'Receipt' }}</div>
      <div class="doc-ref">No. {{ $payment->receipt_number ?: 'N/A' }} &nbsp;·&nbsp; {{ \Illuminate\Support\Carbon::parse($payment->payment_date)->format('F j, Y') }}</div>
    </div>
  </div>

  <div class="meta-grid">
    <div class="meta-block"><label>Received From</label><value>{{ $payment->company_name ?: $payment->contact_person }}</value></div>
    <div class="meta-block"><label>Booking Reference</label><value>{{ $payment->booking_reference }}</value></div>
    <div class="meta-block"><label>Project</label><value>{{ $payment->project_title ?: '—' }}</value></div>
    <div class="meta-block"><label>Payment Method</label><value>{{ $methodLabel[$payment->payment_method] ?? ($payment->payment_method ? ucwords(str_replace('_',' ',$payment->payment_method)) : '—') }}</value></div>
    <div class="meta-block"><label>Reference No.</label><value>{{ $payment->reference_number ?: '—' }}</value></div>
    <div class="meta-block"><label>Payment Type</label><value>{{ $typeLabel[$payment->payment_type] ?? ($payment->payment_type ? ucfirst($payment->payment_type) : '—') }}</value></div>
    @if ($payment->received_by_name && trim($payment->received_by_name) !== '')
    <div class="meta-block"><label>Received By</label><value>{{ $payment->received_by_name }}</value></div>
    @endif
  </div>

  <div class="total-row">
    <table class="total-table">
      <tr><td style="padding:8px 0">Amount Received</td><td>₱{{ number_format($payment->amount,2) }}</td></tr>
      @if ($payment->is_vat)
      <tr><td style="padding:8px 0;color:#64748b">Net of VAT</td><td>₱{{ number_format($payment->amount/1.12,2) }}</td></tr>
      <tr><td style="padding:8px 0;color:#64748b">VAT (12%)</td><td>₱{{ number_format($payment->amount-($payment->amount/1.12),2) }}</td></tr>
      @endif
      <tr class="grand-total"><td>TOTAL AMOUNT PAID</td><td>₱{{ number_format($payment->amount,2) }}</td></tr>
    </table>
  </div>

  @if ($payment->notes)
  <p style="margin-top:14px;font-size:12px;color:#475569"><strong>Notes:</strong> {{ $payment->notes }}</p>
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
    @if ($payment->is_vat)
    This serves as an Official Receipt for VAT purposes.
    @endif
  </div>
</div>

</body>
</html>
