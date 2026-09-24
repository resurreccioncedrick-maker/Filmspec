<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Equipment Checklist Record — FilmSpec</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{--accent:#003D80;--acclight:#D0E8FF;--text:#0f172a;--sub:#475569;--muted:#94a3b8;--border:#e2e8f0;--surface:#ffffff;--bg:#f8fafc}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh}

.top-bar{background:var(--accent);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.top-bar-logo{font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:2px;display:flex;align-items:center;gap:10px}
.top-bar-actions{display:flex;gap:8px;flex-wrap:wrap}
.tbtn{padding:11px 18px;border-radius:8px;font-size:13.5px;font-weight:700;cursor:pointer;border:1px solid rgba(255,255,255,.35);background:rgba(255,255,255,.12);color:#fff;font-family:'DM Sans',sans-serif;transition:all .15s;min-height:44px}
.tbtn:hover{background:rgba(255,255,255,.25)}
.tbtn.danger{background:#c0392b;border-color:#c0392b;display:inline-flex;align-items:center;gap:7px}

.wrap{max-width:820px;margin:20px auto;padding:0 16px 40px}
.sheet{background:#fff;border:1px solid #d1d5db;border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.06)}

.hdr{display:flex;flex-wrap:wrap;border-bottom:2px solid var(--accent)}
.hdr-left{flex:1;min-width:200px;background:var(--accent);color:#fff;padding:18px 20px;display:flex;flex-direction:column;justify-content:center}
.hdr-left .brand{font-family:'Bebas Neue',sans-serif;font-size:32px;letter-spacing:3px;line-height:1}
.hdr-left .sub{font-size:11px;color:rgba(255,255,255,.8);margin-top:4px;letter-spacing:1px}
.hdr-right{flex:1;min-width:200px;background:#E5F0FF;padding:18px 20px;display:flex;flex-direction:column;justify-content:center}
.hdr-right .doctype{font-family:'Bebas Neue',sans-serif;font-size:21px;letter-spacing:1.5px;color:var(--accent);line-height:1}
.hdr-right .ref{font-family:'JetBrains Mono',monospace;font-size:13px;color:var(--accent);font-weight:700;margin-top:5px}

.info{padding:14px 20px;border-bottom:1px solid var(--border);display:grid;grid-template-columns:1fr 1fr;gap:12px 20px}
@media(max-width:480px){.info{grid-template-columns:1fr}}
.info-item .lbl{font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px}
.info-item .val{font-size:14.5px;font-weight:600;color:var(--text)}

.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:0;border-bottom:1px solid var(--border)}
@media(max-width:480px){.summary{grid-template-columns:repeat(2,1fr)}}
.summary div{padding:14px;text-align:center;border-right:1px solid var(--border);border-bottom:1px solid var(--border)}
.summary div:nth-child(4n){border-right:none}
@media(max-width:480px){.summary div:nth-child(2n){border-right:none}}
.summary .num{font-family:'JetBrains Mono',monospace;font-size:20px;font-weight:800;color:var(--accent)}
.summary .lbl{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-top:3px}

.items{padding:8px 20px 4px}
.eq-card{border:1px solid var(--border);border-radius:10px;padding:14px 16px;margin:12px 0}
.eq-top{display:flex;align-items:baseline;justify-content:space-between;gap:10px;flex-wrap:wrap}
.eq-name{font-size:16px;font-weight:700;color:var(--text)}
.eq-cat{font-size:12.5px;color:var(--muted);margin-top:2px}
.eq-qty{font-family:'JetBrains Mono',monospace;font-size:12.5px;color:var(--sub);white-space:nowrap}
.eq-split{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:12px}
@media(max-width:520px){.eq-split{grid-template-columns:1fr}}
.eq-dir{background:var(--bg);border-radius:8px;padding:11px 13px}
.eq-dir .dir-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--sub);margin-bottom:8px}
.eq-dir-row{display:flex;justify-content:space-between;align-items:center;font-size:12.5px;color:var(--sub);margin-top:5px}
.eq-dir-row:first-of-type{margin-top:0}
.eq-dir-row b{color:var(--text)}
.badge{display:inline-flex;align-items:center;padding:4px 11px;border-radius:20px;font-size:11.5px;font-weight:700}
.badge.ok{background:#dcfce7;color:#166534}
.badge.warn{background:#fef9c3;color:#854d0e}
.badge.bad{background:#fee2e2;color:#991b1b}
.badge.none{background:#f1f5f9;color:var(--muted)}
.eq-note{margin-top:10px;padding:9px 12px;background:#fffbeb;border-radius:6px;font-size:12.5px;color:#92400e}
.empty-state{padding:40px 20px;text-align:center;color:var(--muted);font-size:14px}

.footer{padding:20px;border-top:1px solid var(--border);display:grid;grid-template-columns:1fr 1fr;gap:40px}
@media(max-width:480px){.footer{grid-template-columns:1fr;gap:30px}}
.sig{border-top:1px solid var(--text);padding-top:6px;font-size:11.5px;color:var(--sub);text-align:center;margin-top:40px}

@media print{.top-bar,.no-print{display:none!important}.wrap{margin:0;padding:0;max-width:100%}.sheet{box-shadow:none;border:1px solid #ccc;border-radius:0}}
</style>
</head>
<body>

<div class="top-bar no-print">
  <div class="top-bar-logo">
    <img src="{{ asset('assets/images/logo.png') }}" alt="FilmSpec" style="height:24px;object-fit:contain;background:rgba(255,255,255,.92);padding:2px 8px;border-radius:4px">
    <span>Equipment Checklist Record</span>
  </div>
  <div class="top-bar-actions">
    <button class="tbtn" onclick="window.print()">Print</button>
    <button class="tbtn danger" onclick="window.print()">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Save as PDF
    </button>
    <a href="{{ $backUrl ?? route('booking-detail', $bid) }}"><button class="tbtn">&larr; Booking</button></a>
  </div>
</div>

<div class="wrap">
  <div class="sheet">
    <div class="hdr">
      <div class="hdr-left">
        <div class="brand">FILMSPEC</div>
        <div class="sub">EQUIPMENT CHECK-OUT / CHECK-IN RECORD</div>
      </div>
      <div class="hdr-right">
        <div class="doctype">CHECKLIST RECORD</div>
        <div class="ref">{{ $booking->booking_reference }}</div>
      </div>
    </div>

    <div class="info">
      <div class="info-item"><div class="lbl">Client</div><div class="val">{{ $booking->company_name ?: $booking->contact_person }}</div></div>
      <div class="info-item"><div class="lbl">Project</div><div class="val">{{ $booking->project_title ?: '—' }}</div></div>
      <div class="info-item"><div class="lbl">Shoot Dates</div><div class="val">{{ \Carbon\Carbon::parse($booking->shoot_date_start)->format('M j, Y') }} – {{ \Carbon\Carbon::parse($booking->shoot_date_end)->format('M j, Y') }}</div></div>
      <div class="info-item"><div class="lbl">Booking Status</div><div class="val">{{ ucfirst($booking->booking_status) }}</div></div>
      <div class="info-item" style="grid-column:1/-1"><div class="lbl">Printed</div><div class="val">{{ now()->format('M j, Y g:i A') }}</div></div>
    </div>

    <div class="summary">
      <div><div class="num">{{ $totalItems }}</div><div class="lbl">Total Items</div></div>
      <div><div class="num">{{ $outDone }}/{{ $totalItems }}</div><div class="lbl">Checked Out</div></div>
      <div><div class="num">{{ $inDone }}/{{ $totalItems }}</div><div class="lbl">Checked In</div></div>
      <div><div class="num" style="{{ $damaged ? 'color:#991b1b' : '' }}">{{ $damaged }}</div><div class="lbl">Damaged / Missing</div></div>
    </div>

    <div class="items">
      @if (!count($equipLines))
      <div class="empty-state">No equipment lines on this booking.</div>
      @endif
      @foreach ($equipLines as $e)
      <div class="eq-card">
        <div class="eq-top">
          <div>
            <div class="eq-name">{{ $e->item_name }}</div>
            <div class="eq-cat">{{ $e->category_name }}{{ $e->brand ? ' · '.$e->brand : '' }}</div>
          </div>
          <div class="eq-qty">Qty Expected: <b style="color:var(--text)">{{ (int) $e->quantity }}</b></div>
        </div>
        <div class="eq-split">
          <div class="eq-dir">
            <div class="dir-lbl">Check-Out</div>
            <div class="eq-dir-row"><span>Qty</span><b>{{ $e->co_checked ? (int) $e->co_qty : '—' }}</b></div>
            <div class="eq-dir-row"><span>Condition</span>@if ($e->co_checked && $e->condition_out)<span class="badge {{ $condBadge[$e->condition_out] ?? 'none' }}">{{ $condOut[$e->condition_out] ?? ucfirst($e->condition_out) }}</span>@else<span class="badge none">Not yet</span>@endif</div>
            <div class="eq-dir-row"><span>By</span><b>{{ $e->co_checked && trim((string) $e->co_by_name) ? trim($e->co_by_name) : '—' }}</b></div>
            <div class="eq-dir-row"><span>Date/Time</span><span style="font-family:'JetBrains Mono',monospace;font-size:11px">{{ $e->co_at ? \Carbon\Carbon::parse($e->co_at)->format('M j, g:i A') : '—' }}</span></div>
          </div>
          <div class="eq-dir">
            <div class="dir-lbl">Check-In</div>
            <div class="eq-dir-row"><span>Qty</span><b>{{ $e->ci_checked ? (int) $e->ci_qty : '—' }}</b></div>
            <div class="eq-dir-row"><span>Condition</span>@if ($e->ci_checked && $e->condition_in)<span class="badge {{ $condBadge[$e->condition_in] ?? 'none' }}">{{ $condIn[$e->condition_in] ?? ucfirst($e->condition_in) }}</span>@else<span class="badge none">Not yet</span>@endif</div>
            <div class="eq-dir-row"><span>By</span><b>{{ $e->ci_checked && trim((string) $e->ci_by_name) ? trim($e->ci_by_name) : '—' }}</b></div>
            <div class="eq-dir-row"><span>Date/Time</span><span style="font-family:'JetBrains Mono',monospace;font-size:11px">{{ $e->ci_at ? \Carbon\Carbon::parse($e->ci_at)->format('M j, g:i A') : '—' }}</span></div>
          </div>
        </div>
        @if ($e->co_notes || $e->ci_notes)
        <div class="eq-note">
          @if ($e->co_notes)<b>Out note:</b> {{ $e->co_notes }}<br>@endif
          @if ($e->ci_notes)<b>In note:</b> {{ $e->ci_notes }}@endif
        </div>
        @endif
      </div>
      @endforeach
    </div>

    <div class="footer">
      <div>
        <div style="font-size:11.5px;color:var(--muted);margin-bottom:4px">Released by (FilmSpec staff):</div>
        <div class="sig">(Signature over printed name)</div>
      </div>
      <div>
        <div style="font-size:11.5px;color:var(--muted);margin-bottom:4px">Received / Returned by (Client or crew representative):</div>
        <div class="sig">(Signature over printed name)</div>
      </div>
    </div>
  </div>
</div>

</body>
</html>
