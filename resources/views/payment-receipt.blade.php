<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Receipt {{ $payment->receipt_number ?: '#' . $payment->payment_id }} — FilmSpec</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{--accent:#003D80;--acclight:#D0E8FF;--text:#0f172a;--sub:#475569;--muted:#94a3b8;--border:#e2e8f0;--surface:#ffffff;--bg:#f8fafc}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

.top-bar{background:var(--accent);color:#fff;padding:12px 28px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.top-bar-logo{font-family:'Bebas Neue',sans-serif;font-size:22px;letter-spacing:2px;display:flex;align-items:center;gap:10px}
.top-bar-actions{display:flex;gap:8px}
.tbtn{padding:7px 16px;border-radius:5px;font-size:12.5px;font-weight:600;cursor:pointer;border:1px solid rgba(255,255,255,.35);background:rgba(255,255,255,.12);color:#fff;font-family:'DM Sans',sans-serif;transition:all .15s;text-decoration:none;display:inline-flex;align-items:center}
.tbtn:hover{background:rgba(255,255,255,.25)}
.tbtn.primary{background:#fff;color:var(--accent);border-color:#fff}
.tbtn.primary:hover{background:var(--acclight)}

.rc-wrap{max-width:640px;margin:28px auto;padding:0 16px 40px}
.rc-sheet{background:#fff;border:1px solid #d1d5db;border-radius:4px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.06)}

.rc-header{display:grid;grid-template-columns:1fr 1fr;border-bottom:2px solid var(--accent)}
.rc-header-left{background:var(--accent);color:#fff;padding:16px 20px;display:flex;flex-direction:column;justify-content:center}
.rc-header-left .brand{font-family:'Bebas Neue',sans-serif;font-size:34px;letter-spacing:3px;line-height:1}
.rc-header-left .addr{font-size:9px;color:rgba(255,255,255,.7);margin-top:5px;line-height:1.5}
.rc-header-right{background:var(--acclight);padding:16px 20px;display:flex;flex-direction:column;justify-content:center;align-items:flex-end;text-align:right}
.rc-type{font-family:'Bebas Neue',sans-serif;font-size:26px;letter-spacing:2px;color:var(--accent);line-height:1}
.rc-num{font-family:'JetBrains Mono',monospace;font-size:13px;color:var(--accent);font-weight:700;margin-top:4px}

.rc-info-row{display:grid;grid-template-columns:170px 1fr;border-bottom:1px solid #f1f5f9;min-height:32px;align-items:center}
.rc-info-row:last-child{border-bottom:none}
.rc-info-lbl{padding:7px 16px;font-size:10.5px;font-weight:700;color:var(--sub);text-transform:uppercase;letter-spacing:.5px;background:#f8fafc;border-right:1px solid var(--border)}
.rc-info-val{padding:7px 16px;font-size:13px;font-weight:600;color:var(--text)}

.rc-amount{padding:22px 20px;background:var(--accent);color:#fff;text-align:center}
.rc-amount .lbl{font-size:10px;letter-spacing:1.5px;text-transform:uppercase;opacity:.75}
.rc-amount .val{font-family:'JetBrains Mono',monospace;font-size:32px;font-weight:800;margin-top:4px}

.rc-notes{padding:12px 16px;background:#fffbeb;border-top:1px solid #fde68a;font-size:11px;color:#92400e;line-height:1.7}
.rc-footer{padding:16px 20px;border-top:1px solid var(--border);font-size:10.5px;color:var(--muted);text-align:center;line-height:1.6}

@media print{.top-bar,.no-print{display:none!important}.rc-wrap{margin:0;padding:0}.rc-sheet{box-shadow:none;border:1px solid #ccc}}
</style>
</head>
<body>

<div class="top-bar no-print">
  <div class="top-bar-logo">
    <img src="{{ asset('assets/images/logo.png') }}" alt="FilmSpec" style="height:26px;object-fit:contain;background:rgba(255,255,255,.92);padding:2px 8px;border-radius:4px">
    <span>— Receipt</span>
  </div>
  <div class="top-bar-actions">
    <button class="tbtn primary" onclick="window.print()">Print / Save as PDF</button>
    @if ($role === 'client')
    <a href="{{ route('client-booking-detail', $payment->booking_id) }}" class="tbtn">&larr; Booking</a>
    @else
    <a href="{{ route('booking-detail', $payment->booking_id) }}" class="tbtn">&larr; Booking</a>
    @endif
  </div>
</div>

<div class="rc-wrap">
  <div class="rc-sheet">
    <div class="rc-header">
      <div class="rc-header-left">
        <div class="brand">FILMSPEC</div>
        <div class="addr">Film Equipment Rental &amp; Crew Management<br>Metro Manila, Philippines<br>TIN: {{ config('filmspec.company_tin') }}</div>
      </div>
      <div class="rc-header-right">
        <div class="rc-type">{{ $receiptTypeLabel[$payment->receipt_type] ?? 'Receipt' }}</div>
        <div class="rc-num">{{ $payment->receipt_number ?: 'No. —' }}</div>
      </div>
    </div>

    <div class="rc-info">
      <div class="rc-info-row">
        <div class="rc-info-lbl">Received From</div>
        <div class="rc-info-val">{{ $payment->company_name ?: $payment->contact_person }}</div>
      </div>
      <div class="rc-info-row">
        <div class="rc-info-lbl">Booking Reference</div>
        <div class="rc-info-val" style="font-family:'JetBrains Mono',monospace">{{ $payment->booking_reference }}</div>
      </div>
      <div class="rc-info-row">
        <div class="rc-info-lbl">Project</div>
        <div class="rc-info-val">{{ $payment->project_title ?: '—' }}</div>
      </div>
      <div class="rc-info-row">
        <div class="rc-info-lbl">Payment Date</div>
        <div class="rc-info-val">{{ \Illuminate\Support\Carbon::parse($payment->payment_date)->format('F j, Y') }}</div>
      </div>
      <div class="rc-info-row">
        <div class="rc-info-lbl">Payment Type</div>
        <div class="rc-info-val">{{ $typeLabel[$payment->payment_type] ?? ($payment->payment_type ? ucfirst($payment->payment_type) : '—') }}</div>
      </div>
      <div class="rc-info-row">
        <div class="rc-info-lbl">Payment Method</div>
        <div class="rc-info-val">{{ $methodLabel[$payment->payment_method] ?? ($payment->payment_method ? ucfirst($payment->payment_method) : '—') }}</div>
      </div>
      @if ($payment->reference_number)
      <div class="rc-info-row">
        <div class="rc-info-lbl">Reference No.</div>
        <div class="rc-info-val" style="font-family:'JetBrains Mono',monospace">{{ $payment->reference_number }}</div>
      </div>
      @endif
      @if ($payment->received_by_name && trim($payment->received_by_name) !== '')
      <div class="rc-info-row">
        <div class="rc-info-lbl">Received By</div>
        <div class="rc-info-val">{{ $payment->received_by_name }}</div>
      </div>
      @endif
    </div>

    <div class="rc-amount">
      <div class="lbl">Amount {{ $payment->is_vat ? '(VAT Inclusive)' : '' }}</div>
      <div class="val">&#8369;{{ number_format($payment->amount, 2) }}</div>
    </div>

    @if ($payment->notes)
    <div class="rc-notes">{{ $payment->notes }}</div>
    @endif

    <div class="rc-footer">
      This receipt was generated by the FilmSpec Operations Platform on {{ \Illuminate\Support\Carbon::parse($payment->created_at)->format('F j, Y g:ia') }}.
    </div>
  </div>
</div>

</body>
</html>
