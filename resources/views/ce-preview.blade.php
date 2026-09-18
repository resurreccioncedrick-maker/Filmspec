<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Cost Estimate — FilmSpec</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
:root{--accent:#003D80;--acclight:#D0E8FF;--text:#0f172a;--sub:#475569;--muted:#94a3b8;--border:#e2e8f0;--surface:#ffffff;--bg:#f8fafc}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;padding:0}

.top-bar{background:var(--accent);color:#fff;padding:12px 28px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.top-bar-logo{font-family:'Bebas Neue',sans-serif;font-size:22px;letter-spacing:2px}
.top-bar-actions{display:flex;gap:8px}
.tbtn{padding:7px 16px;border-radius:5px;font-size:12.5px;font-weight:600;cursor:pointer;border:1px solid rgba(255,255,255,.35);background:rgba(255,255,255,.12);color:#fff;font-family:'DM Sans',sans-serif;transition:all .15s}
.tbtn:hover{background:rgba(255,255,255,.25)}
.tbtn.primary{background:#fff;color:var(--accent);border-color:#fff}
.tbtn.primary:hover{background:#D0E8FF}

.ce-wrap{max-width:860px;margin:24px auto;padding:0 16px 40px}
.ce-wrap.cart-wide{max-width:1080px}

.checkout-grid{display:grid;grid-template-columns:1fr 340px;gap:22px;align-items:start;margin-bottom:14px}
.checkout-side{position:sticky;top:20px}
@media (max-width:820px){ .checkout-grid{grid-template-columns:1fr} .checkout-side{position:static} }

@media (max-width:640px){
  /* Booking Details form — 16px avoids Safari's auto-zoom-on-focus, 44px
     tap targets. Field ids are untouched (doSubmit() reads them by id). */
  #bf_title,#bf_type,#bf_start,#bf_end,#bf_location,#bf_notes{
    font-size:16px!important;min-height:44px;padding:11px 12px!important;
  }
  #bf_notes{min-height:64px}
  #submit_lock_btn{padding:10px 18px!important;font-size:13px!important}

  /* Map — shrink from the desktop 340px so it doesn't eat half the screen;
     Leaflet is re-measured via invalidateSize() (see script block). */
  #submitBookingMap{height:200px!important}

  /* Live Cost Preview sidebar */
  .checkout-side .agree-row{gap:12px}
  .checkout-side .agree-row input[type=checkbox]{width:20px;height:20px}
  .checkout-side .agree-link{font-size:14px}
  #submitBtn{width:100%;min-height:48px;font-size:14.5px}

  /* Sheet tabs */
  .sheet-tabs{overflow-x:auto}
  .stab{padding:11px 16px;font-size:13px;min-height:44px;display:flex;align-items:center}

  /* CE breakdown — header/info/totals/footer are fixed-width CSS grids with
     zero responsive handling today; stack them. The .ce-tbl equipment/crew
     tables already scroll horizontally (existing overflow-x:auto wrappers),
     this just makes their text legible instead of a full card rebuild. */
  .ce-header{grid-template-columns:1fr}
  .ce-header-left,.ce-header-right{padding:14px 16px}
  .ce-info-row{grid-template-columns:1fr;min-height:0}
  .ce-info-lbl{border-right:none;border-bottom:1px solid var(--border);padding:6px 12px 3px}
  .ce-info-val{padding:3px 12px 8px;font-size:13.5px}
  .ce-tbl th{font-size:10.5px;padding:8px 10px}
  .ce-tbl td{font-size:13px;padding:8px 10px}
  .ce-totals table{max-width:none}
  .ce-footer{grid-template-columns:1fr;gap:26px}
}

.ce-sheet{background:#fff;border:1px solid #d1d5db;border-radius:4px;overflow:hidden;margin-bottom:28px;box-shadow:0 4px 20px rgba(0,0,0,.06)}

.ce-header{display:grid;grid-template-columns:1fr 1fr;border-bottom:2px solid #003D80}
.ce-header-left{background:#003D80;color:#fff;padding:14px 18px;display:flex;flex-direction:column;justify-content:center}
.ce-header-left .brand{font-family:'Bebas Neue',sans-serif;font-size:36px;letter-spacing:3px;line-height:1}
.ce-header-left .addr{font-size:9px;color:rgba(255,255,255,.7);margin-top:4px;line-height:1.5}
.ce-header-right{background:#E5F0FF;padding:14px 18px;display:flex;flex-direction:column;justify-content:space-between}
.ce-type{font-family:'Bebas Neue',sans-serif;font-size:26px;letter-spacing:2px;color:#003D80;line-height:1}
.ce-num{font-family:'JetBrains Mono',monospace;font-size:12px;color:#003D80;font-weight:700;margin-top:3px}
.ce-meta{display:grid;grid-template-columns:auto 1fr;gap:2px 10px;font-size:11px;margin-top:6px}
.ce-meta .lbl{color:var(--muted);font-weight:600}
.ce-meta .val{color:#003D80;font-weight:700;font-family:'JetBrains Mono',monospace}

.ce-info{border-bottom:1px solid var(--border)}
.ce-info-row{display:grid;grid-template-columns:160px 1fr;border-bottom:1px solid #f1f5f9;min-height:26px;align-items:center}
.ce-info-row:last-child{border-bottom:none}
.ce-info-lbl{padding:5px 12px;font-size:10.5px;font-weight:700;color:var(--sub);text-transform:uppercase;letter-spacing:.5px;background:#f8fafc;border-right:1px solid var(--border)}
.ce-info-val{padding:5px 14px;font-size:12.5px;font-weight:600;color:var(--text)}

.ce-section-hdr{background:#003D80;color:#fff;padding:7px 14px;font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase}

.ce-tbl{width:100%;border-collapse:collapse}
.ce-tbl th{background:#D0E8FF;color:#003D80;font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;padding:7px 10px;text-align:left;border:1px solid #A8D0FF}
.ce-tbl th.right{text-align:right}
.ce-tbl td{padding:6px 10px;font-size:12px;border:1px solid #e2e8f0;vertical-align:middle}
.ce-tbl td.right{text-align:right;font-family:'JetBrains Mono',monospace;font-size:12px}
.ce-tbl td.mono{font-family:'JetBrains Mono',monospace;font-size:12px}
.ce-tbl tr:nth-child(even) td{background:#f9fbff}
.ce-tbl tr:hover td{background:#E5F0FF}
.ce-tbl .subtot td{background:#D0E8FF!important;font-weight:700;color:#003D80}
.ce-tbl .grandtot td{background:#003D80!important;color:#fff!important;font-weight:800;font-size:13px}
.ce-tbl .grandtot td.right{font-family:'JetBrains Mono',monospace}

.ce-totals{padding:14px 16px;background:#f8fafc;border-top:2px solid var(--border)}
.ce-totals table{width:100%;max-width:380px;margin-left:auto;border-collapse:collapse}
.ce-totals td{padding:5px 10px;font-size:12.5px}
.ce-totals td.lbl{color:var(--sub);font-weight:600}
.ce-totals td.val{text-align:right;font-family:'JetBrains Mono',monospace;font-weight:600;color:var(--text)}
.ce-totals .vat td.lbl{color:var(--muted)}
.ce-totals .vat td.val{color:var(--muted)}
.ce-totals .grand td{background:#003D80;color:#fff;font-weight:800;font-size:14px;padding:9px 12px}
.ce-totals .grand td.val{font-family:'JetBrains Mono',monospace;text-align:right}

.ce-words{padding:10px 16px;border-top:1px solid var(--border);font-size:11.5px;font-style:italic;color:#003D80;font-weight:700;background:#E5F0FF}

.ce-footer{padding:14px 16px;border-top:1px solid var(--border);display:grid;grid-template-columns:1fr 1fr;gap:40px}
.ce-sig{border-top:1px solid var(--text);padding-top:5px;font-size:10.5px;color:var(--sub);text-align:center;margin-top:32px}

.ce-notes{padding:10px 14px;background:#fffbeb;border-top:1px solid #fde68a;font-size:10.5px;color:#92400e;line-height:1.7}

.ce-summary-row{display:grid;grid-template-columns:1fr auto;align-items:center;padding:8px 14px;border-bottom:1px solid var(--border)}
.ce-summary-row:last-child{border-bottom:none}
.ce-summary-lbl{font-size:12.5px;font-weight:600;color:var(--sub)}
.ce-summary-val{font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:700;color:#003D80}
.ce-summary-row.grand .ce-summary-lbl{color:var(--text);font-size:14px}
.ce-summary-row.grand .ce-summary-val{font-size:16px;color:#003D80}

.sheet-tabs{display:flex;gap:0;margin-bottom:0;border-bottom:none}
.stab{padding:9px 24px;font-size:12.5px;font-weight:600;cursor:pointer;color:var(--muted);border:1px solid var(--border);border-bottom:none;border-radius:6px 6px 0 0;margin-right:4px;background:var(--bg);transition:all .15s}
.stab.on{background:#fff;color:#003D80;border-color:#003D80;border-bottom:1px solid #fff;margin-bottom:-1px}

.sheet-content{display:none}
.sheet-content.on{display:block}

.action-bar{background:#fff;border:1px solid var(--border);border-radius:8px;padding:20px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.05)}
.action-bar-note{font-size:13px;color:var(--sub);line-height:1.5}
.action-bar-btns{display:flex;gap:8px;flex-wrap:wrap}
.abtn{padding:10px 22px;border-radius:6px;font-size:13.5px;font-weight:700;cursor:pointer;border:none;font-family:'DM Sans',sans-serif;transition:all .15s}
.abtn.primary{background:#003D80;color:#fff}
.abtn.primary:hover{background:#004499;transform:translateY(-1px);box-shadow:0 4px 14px rgba(0,61,128,.25)}
.abtn.outline{background:#fff;color:var(--sub);border:1.5px solid var(--border)}
.abtn.outline:hover{border-color:#003D80;color:#003D80}

@media print{.top-bar,.action-bar,.sheet-tabs,.no-print{display:none!important}.ce-wrap{margin:0;padding:0}.ce-sheet{box-shadow:none;border:1px solid #ccc;page-break-inside:avoid}.sheet-content{display:block!important}}

.agree-wrap{margin-top:14px;padding:14px 16px;background:#f8fafc;border-radius:7px;border:1.5px solid #e2e8f0;display:flex;flex-direction:column;gap:9px}
.agree-row{display:flex;align-items:flex-start;gap:10px;font-size:13px;color:var(--sub);line-height:1.5;cursor:pointer}
.agree-row input[type=checkbox]{width:16px;height:16px;margin-top:2px;accent-color:#003D80;flex-shrink:0;cursor:pointer}
.agree-link{color:#003D80;font-weight:600;text-decoration:underline;cursor:pointer;background:none;border:none;font-size:13px;font-family:'DM Sans',sans-serif;padding:0}
.agree-link:hover{color:#004499}

.doc-mo{display:none;position:fixed;inset:0;background:rgba(0,30,80,.55);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center}
.doc-mo.on{display:flex}
.doc-box{background:#fff;border-radius:12px;width:700px;max-width:96vw;max-height:88vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,.22)}
.doc-head{padding:18px 22px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;background:#003D80}
.doc-head h3{font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:1.5px;color:#fff}
.doc-close{background:rgba(255,255,255,.15);border:none;font-size:20px;color:#fff;cursor:pointer;line-height:1;padding:4px 9px;border-radius:5px;transition:background .15s}
.doc-close:hover{background:rgba(255,255,255,.3)}
.doc-body{padding:24px 26px;overflow-y:auto;font-size:13px;color:#475569;line-height:1.8}
.doc-body h4{font-size:12px;font-weight:700;color:#003D80;margin:20px 0 6px;text-transform:uppercase;letter-spacing:.8px;border-bottom:1px solid #e2e8f0;padding-bottom:4px}
.doc-body h4:first-child{margin-top:0}
.doc-body p{margin-bottom:10px}
.doc-body ul{margin:4px 0 10px 20px}
.doc-body ul li{margin-bottom:5px}
.doc-body strong{color:#0f172a}
.doc-foot{padding:14px 22px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:8px;flex-shrink:0;background:#f8fafc}
.doc-accept-btn{padding:9px 22px;background:#003D80;color:#fff;border:none;border-radius:6px;font-size:13.5px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:background .15s}
.doc-accept-btn:hover{background:#004499}
</style>
</head>
<body>
@php
  // Single source for the fallback VAT-inclusive extraction used throughout this page (only
  // exercised before BookingCosting has computed a real $ceBd) — reads the configurable rate
  // instead of a hardcoded 12/112, matching BookingCosting::extractVat() and CartController.
  $vatRate = (float) config('filmspec.vat_rate');
@endphp

<div class="top-bar no-print">
  <div class="top-bar-logo" style="display:flex;align-items:center;gap:10px">
    <img src="{{ asset('assets/images/logo.png') }}" alt="FilmSpec" style="height:26px;object-fit:contain;background:rgba(255,255,255,.92);padding:2px 8px;border-radius:4px">
    <span>— Cost Estimate</span>
  </div>
  <div class="top-bar-actions">
    @if ($mode === 'booking' && $role !== 'client')
    <span style="display:inline-flex;border:1px solid rgba(255,255,255,.35);border-radius:6px;overflow:hidden;margin-right:6px">
      <a href="?booking_id={{ $bid }}{{ $ceId ? '&ce_id='.$ceId : '' }}&view=internal"
         style="padding:7px 12px;font-size:12px;font-weight:600;text-decoration:none;{{ $isClientView ? 'color:rgba(255,255,255,.75)' : 'background:#fff;color:#003D80' }}">INTERNAL</a>
      <a href="?booking_id={{ $bid }}{{ $ceId ? '&ce_id='.$ceId : '' }}&view=client"
         style="padding:7px 12px;font-size:12px;font-weight:600;text-decoration:none;{{ $isClientView ? 'background:#c0392b;color:#fff' : 'color:rgba(255,255,255,.75)' }}">FOR CLIENT</a>
    </span>
    @endif
    <button class="tbtn" onclick="window.print()">Print</button>
    <button onclick="window.print()" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:#c0392b;color:white;border:none;border-radius:6px;font-size:13px;font-family:'DM Sans',sans-serif;font-weight:500;cursor:pointer">
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Save as PDF
    </button>
    @if ($mode === 'booking' && $role === 'client')
    <a href="{{ route('client-booking-detail', $bid) }}"><button class="tbtn">&larr; Booking</button></a>
    @elseif ($mode === 'booking')
    <a href="{{ route('booking-detail', $bid) }}"><button class="tbtn">&larr; Booking</button></a>
    @else
    <a href="{{ route('home') }}"><button class="tbtn">&larr; Back to Cart</button></a>
    @endif
  </div>
</div>

<div class="ce-wrap {{ $mode === 'cart' ? 'cart-wide' : '' }}">

@if ($mode === 'booking' && $isClientView)
<div style="background:#fdecea;border:1px solid #f5b7b1;color:#c0392b;padding:10px 16px;border-radius:8px;margin-bottom:14px;font-weight:700;letter-spacing:1px;font-size:13px;text-align:center">
  FOR CLIENT ONLY
</div>
@endif

@if ($mode === 'cart')
<div class="checkout-grid no-print">
<div>
<!-- Step 1: Booking Details — inline form, replaces old modal -->
<div class="no-print" style="background:#fff;border:1px solid var(--border);border-radius:10px;padding:22px 24px;margin-bottom:0;box-shadow:0 2px 8px rgba(0,0,0,.06)">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;gap:12px;flex-wrap:wrap">
    <div>
      <div style="font-family:'Bebas Neue',sans-serif;font-size:20px;letter-spacing:1px;color:#003D80">Step 1 — Booking Details</div>
      <div style="font-size:12px;color:var(--sub);margin-top:2px">Fill in your project info and pin your shoot location — this determines your zone rate.</div>
    </div>
    <a href="{{ route('home') }}"><button class="abtn outline" style="font-size:12px;padding:7px 14px">&larr; Edit List</button></a>
  </div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
    <div style="display:flex;flex-direction:column;gap:4px">
      <label style="font-size:10px;color:#64748b;text-transform:uppercase;font-weight:600;letter-spacing:.5px">Project Title *</label>
      <input type="text" id="bf_title" placeholder="e.g. TV Commercial — Brand Name"
             style="background:#f8fafc;border:1.5px solid #e2e8f0;color:#0f172a;padding:9px 12px;border-radius:7px;font-size:13px;outline:none;width:100%;font-family:'DM Sans',sans-serif">
    </div>
    <div style="display:flex;flex-direction:column;gap:4px">
      <label style="font-size:10px;color:#64748b;text-transform:uppercase;font-weight:600;letter-spacing:.5px">Project Type</label>
      <select id="bf_type" style="background:#f8fafc;border:1.5px solid #e2e8f0;color:#0f172a;padding:9px 12px;border-radius:7px;font-size:13px;outline:none;width:100%;font-family:'DM Sans',sans-serif">
        <option value="commercial">Commercial / TVC</option>
        <option value="film">Film / Movie</option>
        <option value="documentary">Documentary</option>
        <option value="event">Event Coverage</option>
        <option value="corporate">Corporate Video</option>
        <option value="music_video">Music Video</option>
        <option value="other">Other</option>
      </select>
    </div>
    <div style="display:flex;flex-direction:column;gap:4px">
      <label style="font-size:10px;color:#64748b;text-transform:uppercase;font-weight:600;letter-spacing:.5px">Shoot Start Date *</label>
      <input type="date" id="bf_start"
             min="{{ date('Y-m-d', strtotime('+2 days')) }}"
             onchange="bfSyncEndMin()"
             style="background:#f8fafc;border:1.5px solid #e2e8f0;color:#0f172a;padding:9px 12px;border-radius:7px;font-size:13px;outline:none;width:100%;font-family:'DM Sans',sans-serif">
    </div>
    <div style="display:flex;flex-direction:column;gap:4px">
      <label style="font-size:10px;color:#64748b;text-transform:uppercase;font-weight:600;letter-spacing:.5px">Shoot End Date *</label>
      <input type="date" id="bf_end"
             min="{{ date('Y-m-d', strtotime('+2 days')) }}"
             onchange="bfSyncEndMin()"
             style="background:#f8fafc;border:1.5px solid #e2e8f0;color:#0f172a;padding:9px 12px;border-radius:7px;font-size:13px;outline:none;width:100%;font-family:'DM Sans',sans-serif">
    </div>
    <div style="grid-column:1/-1;display:flex;flex-direction:column;gap:4px">
      <label style="font-size:10px;color:#64748b;text-transform:uppercase;font-weight:600;letter-spacing:.5px">
        Shoot Location
        <span style="font-size:9px;color:#94a3b8;font-weight:400;text-transform:none;margin-left:6px">— scroll to zoom · click map to drop pin · drag pin to adjust</span>
      </label>
      <div id="submitMapContainer" style="position:relative;border-radius:8px;overflow:hidden;border:1.5px solid #e2e8f0;margin-top:2px">
        <div id="submitBookingMap" style="height:340px"></div>
        <button type="button" id="submit_lock_btn" onclick="toggleSubmitMapLock()"
          style="display:none;position:absolute;bottom:12px;left:50%;transform:translateX(-50%);z-index:500;background:rgba(0,96,199,.92);color:#fff;border:none;border-radius:8px;padding:7px 16px;font-size:12px;font-weight:600;cursor:pointer;font-family:'DM Sans',sans-serif;align-items:center;gap:7px;box-shadow:0 2px 12px rgba(0,0,0,.28);backdrop-filter:blur(4px);letter-spacing:.02em;white-space:nowrap">
          <span id="submit_lock_icon" style="display:flex;align-items:center"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg></span><span id="submit_lock_text">Lock Location</span>
        </button>
      </div>
      <div id="submitZoneInfo" style="display:none;margin-top:6px"></div>
      <div style="position:relative;margin-top:6px">
        <input type="text" id="bf_location" placeholder="Location name (auto-filled from map pin, or type here)"
               autocomplete="off"
               oninput="onSubmitLocationTyped()"
               onkeydown="onSubmitLocationKeydown(event)"
               onblur="setTimeout(closeSubmitLocationSuggest, 150)"
               style="background:#f8fafc;border:1.5px solid #e2e8f0;color:#0f172a;padding:9px 12px;border-radius:7px;font-size:13px;outline:none;width:100%;font-family:'DM Sans',sans-serif;box-sizing:border-box">
        <div id="bf_location_suggest" style="display:none;position:absolute;left:0;right:0;top:calc(100% + 4px);z-index:600;background:#fff;border:1.5px solid #e2e8f0;border-radius:8px;box-shadow:0 8px 24px rgba(15,23,42,.12);max-height:260px;overflow-y:auto;font-family:'DM Sans',sans-serif"></div>
      </div>
      <input type="hidden" id="bf_lat">
      <input type="hidden" id="bf_lng">
      <input type="hidden" id="bf_zone">
      <input type="hidden" id="bf_transport_multiplier" value="1">
      <input type="hidden" id="bf_transport_cost" value="0">
    </div>
    <div style="grid-column:1/-1;display:flex;flex-direction:column;gap:4px">
      <label style="font-size:10px;color:#64748b;text-transform:uppercase;font-weight:600;letter-spacing:.5px">Notes / Special Requests</label>
      <textarea id="bf_notes" rows="2" placeholder="Call time, access requirements, special instructions…"
                style="background:#f8fafc;border:1.5px solid #e2e8f0;color:#0f172a;padding:9px 12px;border-radius:7px;font-size:13px;outline:none;width:100%;font-family:'DM Sans',sans-serif;resize:vertical"></textarea>
    </div>
  </div>
</div>
</div>
<div class="checkout-side">

<!-- Step 2: Live Cost Preview — updates as location zone is detected -->
<div class="no-print" style="background:#fff;border:2px solid #003D80;border-radius:10px;padding:20px 22px;margin-bottom:0;box-shadow:0 2px 10px rgba(0,61,128,.1)">
  <div style="font-family:'Bebas Neue',sans-serif;font-size:18px;letter-spacing:1px;color:#003D80;margin-bottom:12px">Step 2 — Cost Preview</div>
  <div style="display:flex;flex-direction:column;gap:5px">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #f1f5f9">
      <span style="font-size:13px;font-weight:600;color:var(--sub)">Equipment <span id="live-eq-note" style="font-size:11px;color:var(--muted);font-weight:400"></span></span>
      <span style="font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:700;color:#003D80" id="live-eq-val">₱{{ number_format($equipTotal, 2) }}</span>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #f1f5f9">
      <span style="font-size:13px;font-weight:600;color:var(--sub)">Personnel / Crew TF <span style="font-size:11px;font-weight:400;color:var(--muted)">(assigned after booking)</span></span>
      <span style="font-size:13px;font-weight:700;color:var(--muted)">TBD</span>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #f1f5f9">
      <span style="font-size:13px;font-weight:600;color:var(--sub)">Transportation <span id="live-trans-note" style="font-size:11px;color:var(--muted);font-weight:400">— pin location above to calculate</span></span>
      <span style="font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:700;color:var(--muted)" id="live-trans-val">—</span>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 8px;border-radius:6px;background:#f8fafc;margin-top:2px">
      <span style="font-size:13px;font-weight:700;color:var(--text)">Equipment Subtotal</span>
      <span style="font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:700" id="live-sub">₱{{ number_format($subtotal, 2) }}</span>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;color:var(--muted)">
      <span style="font-size:12px">{{ round($vatRate * 100) }}% VAT</span>
      <span style="font-family:'JetBrains Mono',monospace;font-size:12px" id="live-vat">₱{{ number_format($vat, 2) }}</span>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0 4px;border-top:2px solid #003D80;margin-top:6px">
      <span style="font-size:16px;font-weight:800;color:#003D80">Equipment Total</span>
      <span style="font-family:'JetBrains Mono',monospace;font-size:18px;font-weight:800;color:#003D80" id="live-grand">₱{{ number_format($grand, 2) }}</span>
    </div>
  </div>
  <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border)">
    <div style="font-size:11.5px;color:var(--muted);margin-bottom:12px">Equipment cost only — crew TF and transportation will be determined by admin after booking confirmation.</div>
    <div class="agree-wrap">
      <label class="agree-row">
        <input type="checkbox" id="chkTc" onchange="updateSubmitBtn()">
        <span>I have read and agree to the <button class="agree-link" onclick="openDocMo('tcMo')">Terms and Conditions</button></span>
      </label>
      <label class="agree-row">
        <input type="checkbox" id="chkPp" onchange="updateSubmitBtn()">
        <span>I have read and agree to the <button class="agree-link" onclick="openDocMo('ppMo')">Privacy Policy</button></span>
      </label>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-top:12px">
      <div style="font-size:11px;color:var(--muted)">Both agreements must be accepted before submitting.</div>
      <button id="submitBtn" onclick="doSubmit()" class="abtn primary" disabled style="opacity:.45;cursor:not-allowed;transition:all .2s">Submit Booking Request</button>
    </div>
  </div>
</div>
</div>
</div>

<div class="no-print" style="font-size:11px;color:var(--muted);text-align:center;margin-bottom:8px;letter-spacing:.06em;text-transform:uppercase;padding:4px 0">— Full Cost Breakdown —</div>
@endif

<!-- Sheet Tabs -->
<div class="sheet-tabs">
  <div class="stab on" onclick="showSheet('equipment',this)">Equipment (E)</div>
  <div class="stab" onclick="showSheet('crew',this)">Crew TF (M)</div>
  <div class="stab" onclick="showSheet('summary',this)">Summary (S)</div>
</div>

<!-- ═══ SHEET: EQUIPMENT ═══ -->
<div class="ce-sheet sheet-content on" id="sheet-equipment">

  <div class="ce-header">
    <div class="ce-header-left" style="background:#fff;border-right:2px solid #003D80">
      <img src="{{ asset('assets/images/logo.png') }}" alt="FilmSpec" style="height:46px;max-width:170px;object-fit:contain;display:block;margin-bottom:8px">
      <div class="addr" style="color:#64748b">Gate 1, 9110 La Campana St. cor Trabajo St., Olympia, Makati City<br>Cel: +63927 5056461 · Tel: +632 70004683</div>
    </div>
    <div class="ce-header-right">
      <div>
        <div class="ce-type">COST ESTIMATE</div>
        <div class="ce-num">CE# {{ $ceNumber }}(E)</div>
      </div>
      <div class="ce-meta">
        <span class="lbl">DATE:</span><span class="val">{{ date('F d, Y') }}</span>
        <span class="lbl">DUE DATE:</span><span class="val">{{ date('F d, Y', strtotime('+30 days')) }}</span>
        <span class="lbl">FS FRONT:</span><span class="val">PREPARED BY: Glen Resurreccion</span>
      </div>
    </div>
  </div>

  <div class="ce-info">
    @foreach ($infoRows as $l => $v)
    <div class="ce-info-row">
      <div class="ce-info-lbl">{{ $l }}:</div>
      <div class="ce-info-val">{{ $v }}</div>
    </div>
    @endforeach
  </div>

  <div class="ce-section-hdr">DETAILED COST BREAKDOWN — LIGHT & GRIPS (FS)</div>
  <div style="overflow-x:auto">
  <table class="ce-tbl">
    <thead>
      <tr>
        <th style="width:50px">QTY</th>
        <th>EQUIPMENT (S)</th>
        <th class="right" style="width:80px">DAY(S)</th>
        <th class="right" style="width:110px">RATE / DAY</th>
        <th class="right" style="width:120px">AMOUNT</th>
      </tr>
    </thead>
    <tbody>
      @if ($mode === 'booking')
        @foreach ($equipGroups as $catName => $catLines)
        @php $groupSub = 0; @endphp
        <tr>
          <td colspan="5" style="background:#f9fbff;font-weight:700;color:#003D80;letter-spacing:.5px">
            {{ strtoupper($catName) }} (FS)
            <span style="float:right;font-weight:400;color:var(--muted);font-size:11px">{{ count($catLines) }} item{{ count($catLines) === 1 ? '' : 's' }}</span>
          </td>
        </tr>
        @foreach ($catLines as $eq)
        @php
          $rate = (float) ($eq->daily_rate ?? 0);
          $qty = (int) ($eq->quantity ?? 1);
          $groupSub += $qty * $rate;
        @endphp
        <tr>
          <td class="mono" style="text-align:center">{{ $qty }}</td>
          <td>{{ $eq->equipment_name . ($eq->brand ? ' (' . $eq->brand . ')' : '') }}</td>
          <td class="right">1</td>
          <td class="right">₱{{ number_format($rate, 2) }}</td>
          <td class="right">₱{{ number_format($qty * $rate, 2) }}</td>
        </tr>
        @endforeach
        <tr>
          <td colspan="4" style="text-align:right;color:var(--muted);font-size:11px">Subtotal — {{ strtoupper($catName) }} (FS):</td>
          <td class="right" style="color:var(--muted);font-size:11px">₱{{ number_format($groupSub, 2) }}</td>
        </tr>
        @endforeach
        @if (count($equipGroups) === 0)
        <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:16px;font-style:italic">No equipment items</td></tr>
        @endif
      @else
      @foreach ($equipLines as $eq)
      @php
        $rate = (float) ($eq->daily_rate ?? 0);
        $qty = (int) ($eq->quantity ?? 1);
      @endphp
       <tr>
        <td class="mono" style="text-align:center">{{ $qty }}</td>
        <td>{{ $eq->equipment_name . ($eq->brand ? ' (' . $eq->brand . ')' : '') }}</td>
        <td class="right eq-days-cell">1</td>
        <td class="right eq-rate" data-base="{{ $rate }}">₱{{ number_format($rate, 2) }}</td>
        <td class="right eq-amt" data-base="{{ $qty * $rate }}">₱{{ number_format($qty * $rate, 2) }}</td>
       </tr>
      @endforeach
      @if (count($equipLines) === 0)
       <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:16px;font-style:italic">No equipment items</td></tr>
      @endif
      @endif
    </tbody>
    <tfoot>
      @if ($mode === 'booking' && $transMult > 1.0)
      <tr>
        <td colspan="4" style="text-align:right;color:var(--muted);font-size:11px">Base Equipment Total:</td>
        <td class="right" style="color:var(--muted);font-size:11px">₱{{ number_format($equipBaseTotal, 2) }}</td>
      </tr>
      <tr>
        <td colspan="4" style="text-align:right;color:var(--sub);font-size:11px">Zone Multiplier ({{ $zoneLabels[$transZone] ?? $transZone }}):</td>
        <td class="right" style="color:var(--sub);font-size:11px">{{ number_format($transMult, 2) }}×</td>
      </tr>
      @endif
      <tr class="subtot">
        <td colspan="4" style="text-align:right;font-weight:700">Total (Equipment Only):</td>
        <td class="right" id="eq-tbl-total">₱{{ number_format($equipTotal, 2) }}</td>
       </tr>
    </tfoot>
   </table>
  </div>

  <div class="ce-totals">
    <table>
      @php
        $eqNet   = $ceBd ? $ceBd['equip_net']   : $equipTotal - round($equipTotal * $vatRate / (1 + $vatRate), 2);
        $eqVat   = $ceBd ? $ceBd['equip_vat']   : round($equipTotal * $vatRate / (1 + $vatRate), 2);
        $eqGrand = $ceBd ? $ceBd['equip_grand'] : $equipTotal;
      @endphp
      <tr>
        <td class="lbl">Net Amount (ex-VAT):</td>
        <td class="val" id="eq-sub-val">₱{{ number_format($eqNet, 2) }}</td>
      </tr>
      <tr class="vat">
        <td class="lbl">{{ $ceBdDiscounted ? 'VAT (' . round($vatRate * 100) . '% added)' : 'VAT (' . round($vatRate * 100) . '/' . (100 + round($vatRate * 100)) . ')' }}:</td>
        <td class="val" id="eq-vat-val">₱{{ number_format($eqVat, 2) }}</td>
      </tr>
      @if ($ceBdDiscounted)
      <tr class="vat">
        <td class="lbl">Discount on packaged cost:</td>
        <td class="val">−₱{{ number_format($ceBd['discount_on_packaged_cost'], 2) }}</td>
      </tr>
      @endif
      <tr class="grand">
        <td class="lbl">{{ $mode === 'booking' ? 'EQUIPMENT CE GRAND TOTAL' : 'GRAND TOTAL (E) — VAT Incl.' }}:</td>
        <td class="val" id="eq-grand-val">₱{{ number_format($eqGrand, 2) }}</td>
      </tr>
    </table>
  </div>
  <div class="ce-words" id="eq-words">
    Amount in Words: {{ \App\Support\Money::amtWords($eqGrand) }} PESOS ONLY
  </div>

  <div class="ce-notes">
    *** All Equipment Returned will be charged as Regular after Pull Out ***<br>
    *** For more inquiries please call FILM SPEC Cellphone No. 0927 5056461 ***
  </div>

  <div class="ce-footer">
    <div>
      <div style="font-size:10.5px;color:var(--muted);margin-bottom:4px">Prepared by:</div>
      <div class="ce-sig">Glen Resurreccion</div>
    </div>
    <div>
      <div style="font-size:10.5px;color:var(--muted);margin-bottom:4px">Received &amp; Conformed by:</div>
      <div class="ce-sig">(Signature over printed name)</div>
    </div>
  </div>
</div>

<!-- ═══ SHEET: CREW TF ═══ -->
<div class="ce-sheet sheet-content" id="sheet-crew">
  @if ($mode === 'cart')
  <div style="background:#eff6ff;border-bottom:2px solid #93c5fd;padding:10px 16px;font-size:12px;color:#1e40af;display:flex;align-items:center;gap:8px">
    <span style="font-size:16px">&#8505;</span>
    <span><strong>Estimated Crew TF</strong> — Operator assignments are finalized by FilmSpec admin after your booking is submitted. Rates shown are averages based on standard crew rates for the required positions and may vary.</span>
  </div>
  @endif

  <div class="ce-header">
    <div class="ce-header-left" style="background:#fff;border-right:2px solid #003D80">
      <img src="{{ asset('assets/images/logo.png') }}" alt="FilmSpec" style="height:46px;max-width:170px;object-fit:contain;display:block;margin-bottom:8px">
      <div class="addr" style="color:#64748b">Gate 1, 9110 La Campana St. cor Trabajo St., Olympia, Makati City<br>Cel: +63927 5056461 · Tel: +632 70004683</div>
    </div>
    <div class="ce-header-right">
      <div>
        <div class="ce-type">COST ESTIMATE</div>
        <div class="ce-num">CE# {{ $ceNumber }}(M)</div>
      </div>
      <div class="ce-meta">
        <span class="lbl">DATE:</span><span class="val">{{ date('F d, Y') }}</span>
        <span class="lbl">DUE DATE:</span><span class="val">{{ date('F d, Y', strtotime('+30 days')) }}</span>
        <span class="lbl">FS FRONT:</span><span class="val">PREPARED BY: Glen Resurreccion</span>
      </div>
    </div>
  </div>

  <div class="ce-info">
    @foreach ($infoRows as $l => $v)
    <div class="ce-info-row">
      <div class="ce-info-lbl">{{ $l }}:</div>
      <div class="ce-info-val">{{ $v }}</div>
    </div>
    @endforeach
  </div>

  <div class="ce-section-hdr">DETAILED COST BREAKDOWN — CREW TALENT FEE</div>
  <div style="padding:6px 12px;background:#fffbeb;border-bottom:1px solid #fde68a;font-size:11px;color:#92400e">
    @if ($mode === 'cart')
      {{ count($estimatedCrewLines) }} Position(s) estimated based on equipment requirements · Actual crew assigned after booking · OT after 12Hrs
    @else
      {{ count($crewLines) }} Crew member(s) · OT after 12Hrs · Double Pay after 22Hrs
    @endif
  </div>
  <div style="overflow-x:auto">
  <table class="ce-tbl">
    <thead>
       <tr>
        <th style="width:50px">QTY</th>
        <th>{{ $mode === 'cart' ? 'POSITION (ESTIMATED)' : 'POSITION / NAME' }}</th>
        <th class="right" style="width:80px">DAY(S)</th>
        <th class="right" style="width:110px">RATE / 12H</th>
        <th class="right" style="width:120px">AMOUNT</th>
       </tr>
    </thead>
    <tbody>
      @if ($mode === 'cart')
      @foreach ($estimatedCrewLines as $cl)
       <tr>
        <td class="mono" style="text-align:center">1</td>
        <td>
          <div style="font-weight:600">{{ $cl->position_name }}</div>
          <div style="font-size:11px;color:var(--muted)">For: {{ $cl->equipment_name }} · assigned after booking</div>
        </td>
        <td class="right"><span style="color:var(--muted)">TBD</span></td>
        <td class="right"><span style="color:var(--muted)">TBD</span></td>
        <td class="right"><span style="color:var(--muted)">TBD</span></td>
       </tr>
      @endforeach
      @if (count($estimatedCrewLines) === 0)
       <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:16px;font-style:italic">No operator requirements for selected equipment</td></tr>
      @endif
      @else
      @foreach ($crewLines as $cl)
      @php
        $clRate = (float) ($cl->rate_used ?? 0);
        $clDays = (int) ($cl->hours_worked ?? 1);
        $clSub = $clRate * $clDays;
      @endphp
       <tr>
        <td class="mono" style="text-align:center">1</td>
        <td>
          <div style="font-weight:600">{{ $cl->position_name ?? 'Crew' }}</div>
          @if ($cl->crew_name)
          <div style="font-size:11px;color:var(--muted)">{{ $cl->crew_name }}</div>
          @endif
        </td>
        <td class="right">{{ $clDays }}</td>
        <td class="right">₱{{ number_format($clRate, 2) }}</td>
        <td class="right">₱{{ number_format($clSub, 2) }}</td>
       </tr>
      @endforeach
      @if (count($crewLines) === 0)
       <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:16px;font-style:italic">No crew assigned</td></tr>
      @endif
      @endif
    </tbody>
    <tfoot>
      <tr class="subtot">
        <td colspan="4" style="text-align:right">TOTAL (CREW TF){{ $mode === 'cart' ? ' — TO BE DETERMINED' : '' }}:</td>
        <td class="right">
          @if ($mode === 'cart')
          <span style="color:var(--muted)">TBD</span>
          @else
          ₱{{ number_format($crewTotal, 2) }}
          @endif
        </td>
       </tr>
    </tfoot>
   </table>
  </div>

  <div class="ce-totals">
    <table>
      @php
        $crewNet   = $ceBd ? $ceBd['crew_net']   : $crewTotal - round($crewTotal * $vatRate / (1 + $vatRate), 2);
        $crewVat   = $ceBd ? $ceBd['crew_vat']   : round($crewTotal * $vatRate / (1 + $vatRate), 2);
        $crewGrand = $ceBd ? $ceBd['crew_grand'] : $crewTotal;
      @endphp
      <tr>
        <td class="lbl">Net Amount (ex-VAT):</td>
        <td class="val">
          @if ($mode === 'cart')
          <span style="color:var(--muted)">TBD</span>
          @else
          ₱{{ number_format($crewNet, 2) }}
          @endif
        </td>
      </tr>
      <tr class="vat">
        <td class="lbl">{{ $ceBdDiscounted ? 'VAT (' . round($vatRate * 100) . '% added)' : 'VAT (' . round($vatRate * 100) . '/' . (100 + round($vatRate * 100)) . ')' }}:</td>
        <td class="val">
          @if ($mode === 'cart')
          <span style="color:var(--muted)">TBD</span>
          @else
          ₱{{ number_format($crewVat, 2) }}
          @endif
        </td>
      </tr>
      <tr class="grand">
        <td class="lbl">{{ $mode === 'booking' ? 'CREW CE GRAND TOTAL' : 'GRAND TOTAL (M) — VAT Incl.' }}:</td>
        <td class="val">
          @if ($mode === 'cart')
          <span style="color:var(--muted);font-size:13px">TBD — Crew assigned after booking</span>
          @else
          ₱{{ number_format($crewGrand, 2) }}
          @endif
        </td>
      </tr>
    </table>
  </div>
  <div class="ce-words">
    @if ($mode === 'cart')
    Crew Talent Fee: <em style="color:var(--muted)">To Be Determined — admin will assign crew after booking is confirmed.</em>
    @else
    Amount in Words: {{ \App\Support\Money::amtWords($crewGrand) }} PESOS ONLY
    @endif
  </div>
  <div class="ce-notes">
    @if ($mode === 'cart')
    Crew Talent Fee is estimated based on average rates for required operator positions. Final amounts will be confirmed by FilmSpec after crew assignment. The Crew Talent Fee shall be due and payable in full upon receipt.
    @else
    The Crew Talent Fee shall be due and payable in full upon receipt.
    @endif
  </div>
  <div class="ce-footer">
    <div>
      <div style="font-size:10.5px;color:var(--muted);margin-bottom:4px">Prepared by:</div>
      <div class="ce-sig">Glen Resurreccion</div>
    </div>
    <div>
      <div style="font-size:10.5px;color:var(--muted);margin-bottom:4px">Received &amp; Conformed by:</div>
      <div class="ce-sig">(Signature over printed name)</div>
    </div>
  </div>
</div>

<!-- ═══ SHEET: SUMMARY ═══ -->
<div class="ce-sheet sheet-content" id="sheet-summary">

  <div class="ce-header">
    <div class="ce-header-left" style="background:#fff;border-right:2px solid #003D80">
      <img src="{{ asset('assets/images/logo.png') }}" alt="FilmSpec" style="height:46px;max-width:170px;object-fit:contain;display:block;margin-bottom:8px">
      <div class="addr" style="color:#64748b">Gate 1, 9110 La Campana St. cor Trabajo St., Olympia, Makati City<br>Cel: +63927 5056461 · Tel: +632 70004683</div>
    </div>
    <div class="ce-header-right">
      <div>
        <div class="ce-type">COST ESTIMATE</div>
        <div class="ce-num">CE# {{ $ceNumber }}(S) — SUMMARY</div>
      </div>
      <div class="ce-meta">
        <span class="lbl">DATE:</span><span class="val">{{ date('F d, Y') }}</span>
        <span class="lbl">DUE DATE:</span><span class="val">{{ date('F d, Y', strtotime('+30 days')) }}</span>
        <span class="lbl">FS FRONT:</span><span class="val">PREPARED BY: Glen Resurreccion</span>
      </div>
    </div>
  </div>

  <div class="ce-info">
    @foreach ($infoRows as $l => $v)
    <div class="ce-info-row">
      <div class="ce-info-lbl">{{ $l }}:</div>
      <div class="ce-info-val">{{ $v }}</div>
    </div>
    @endforeach
  </div>

  <div class="ce-section-hdr">SUMMARY OF ALL COSTS</div>

  <div>
    <div class="ce-summary-row">
      <div class="ce-summary-lbl">Grip Equipment (FS) <span id="sum-eq-note" style="font-size:10px;color:var(--muted);font-weight:400;margin-left:4px"></span></div>
      <div class="ce-summary-val" id="sum-eq-val">₱{{ number_format($equipTotal, 2) }}</div>
    </div>
    <div class="ce-summary-row">
      <div class="ce-summary-lbl">
        Personnel / Crew TF
        @if ($mode === 'cart')
        <span style="font-size:10px;color:var(--muted);font-weight:400">(TBD)</span>
        @endif
      </div>
      <div class="ce-summary-val">
        @if ($mode === 'cart')
        <span style="color:var(--muted);font-size:12px">TBD</span>
        @else
        ₱{{ number_format($crewTotal, 2) }}
        @endif
      </div>
    </div>
    @if ($mode === 'booking' && count($accLines) > 0)
    <div class="ce-summary-row">
      <div class="ce-summary-lbl">Accessories / Add-ons</div>
      <div class="ce-summary-val">₱{{ number_format($accTotal, 2) }}</div>
    </div>
    @endif
    <div class="ce-summary-row">
      <div class="ce-summary-lbl">
        Transportation
        @if ($mode === 'cart')
        <span id="sum-trans-note" style="font-size:10px;color:var(--muted);font-weight:400;margin-left:6px">based on shoot location</span>
        @elseif ($transZone)
        <span style="font-size:10px;color:var(--muted);font-weight:400;margin-left:6px">
          {{ $zoneLabels[$transZone] ?? $transZone }} &nbsp;·&nbsp; {{ $transMult }}×
        </span>
        @endif
      </div>
      <div class="ce-summary-val" id="sum-trans-val">{{ $mode === 'cart' ? '—' : ('₱' . number_format($ceBd ? $ceBd['transportation'] : $transCost, 2)) }}</div>
    </div>
    @if ($mode === 'booking' && $cePricingMode !== 'no_discount' && $cePricingInput !== null)
    @php
      $pricingLabels = [
        'package_price' => 'Package Price: ₱' . number_format($cePricingInput, 2) . ' (all-in, incl. crew)',
        'discount_percent' => number_format($cePricingInput, 2) . '% discount off itemized total',
        'discount_flat' => '₱' . number_format($cePricingInput, 2) . ' flat discount off itemized total',
      ];
    @endphp
    <div class="ce-summary-row" style="background:#eff6ff">
      <div class="ce-summary-lbl" style="color:#1e40af">Package Deal{{ $ceVatExempt ? ' (VAT-exempt)' : '' }}</div>
      <div class="ce-summary-val" style="color:#1e40af;font-size:12px">{{ $pricingLabels[$cePricingMode] ?? '' }}</div>
    </div>
    @endif
    @if ($ceBdDiscounted)
    <div class="ce-summary-row">
      <div class="ce-summary-lbl" style="color:var(--muted)">Discount on packaged cost</div>
      <div class="ce-summary-val" style="color:var(--muted)">−₱{{ number_format($ceBd['discount_on_packaged_cost'], 2) }}</div>
    </div>
    <div class="ce-summary-row">
      <div class="ce-summary-lbl">Total discounted packaged cost</div>
      <div class="ce-summary-val">₱{{ number_format($ceBd['equip_net'], 2) }}</div>
    </div>
    @endif
    @if ($ceBd)
    <div class="ce-summary-row">
      <div class="ce-summary-lbl" style="font-weight:700">Equipment CE grand total</div>
      <div class="ce-summary-val" style="font-weight:700">₱{{ number_format($ceBd['equip_grand'], 2) }}</div>
    </div>
    <div class="ce-summary-row">
      <div class="ce-summary-lbl" style="font-weight:700">Crew CE grand total</div>
      <div class="ce-summary-val" style="font-weight:700">₱{{ number_format($ceBd['crew_grand'], 2) }}</div>
    </div>
    @endif
    <div class="ce-summary-row" style="background:#f8fafc">
      <div class="ce-summary-lbl" style="font-weight:700;color:var(--text)">
        Net Amount (ex-VAT)
        @if ($mode === 'cart')
        <span style="font-size:10px;font-weight:400;color:var(--muted)">(equipment only)</span>
        @endif
      </div>
      <div class="ce-summary-val" id="sum-subtotal">₱{{ number_format($subtotal, 2) }}</div>
    </div>
    <div class="ce-summary-row" style="background:#fffbeb">
      <div class="ce-summary-lbl" style="color:#92400e">{{ ($cePricingMode !== 'no_discount' && $cePricingInput !== null) ? 'VAT (' . round($vatRate * 100) . '% added)' : 'VAT (' . round($vatRate * 100) . '/' . (100 + round($vatRate * 100)) . ') — Included' }}</div>
      <div class="ce-summary-val" style="color:#92400e" id="sum-vat">₱{{ number_format($vat, 2) }}</div>
    </div>
    <div class="ce-summary-row grand" style="background:#003D80;border-radius:0">
      <div class="ce-summary-lbl" style="color:#fff;font-size:15px">{{ $mode === 'cart' ? 'EQUIPMENT TOTAL (VAT Incl.)' : 'GRAND TOTAL (VAT Incl.)' }}</div>
      <div class="ce-summary-val" style="color:#fff;font-size:18px" id="sum-grand">₱{{ number_format($grand, 2) }}</div>
    </div>
  </div>

  <div class="ce-words">
    @if ($mode === 'cart')
    Equipment Amount in Words: {{ \App\Support\Money::amtWords($grand) }} PESOS ONLY<br>
    <span style="font-size:10px;color:var(--muted)">Crew TF and transportation costs will be added after booking confirmation.</span>
    @else
    Amount in Words: {{ \App\Support\Money::amtWords($grand) }} PESOS ONLY
    @endif
  </div>

  <div class="ce-notes">
    *** All Equipment Returned will be charged as a Regular after Pull Out ***<br>
    *** For more inquiries please call FILM SPEC Cellphone No. 0927 5056461 ***<br>
    <strong>Payment Terms:</strong> Regular Clients — 90-day or 6-month credit terms. New Customers — 50% downpayment required.
  </div>

  <div class="ce-footer">
    <div>
      <div style="font-size:10.5px;color:var(--muted);margin-bottom:4px">Prepared by:</div>
      <div class="ce-sig">Glen Resurreccion</div>
    </div>
    <div>
      <div style="font-size:10.5px;color:var(--muted);margin-bottom:4px">Received &amp; Conformed by:</div>
      <div class="ce-sig">(Signature over printed name)</div>
    </div>
  </div>
</div>

</div>

<!-- ═══ TERMS AND CONDITIONS MODAL ═══ -->
<div class="doc-mo" id="tcMo">
  <div class="doc-box">
    <div class="doc-head">
      <h3>Terms and Conditions</h3>
      <button class="doc-close" onclick="closeDocMo('tcMo')">&times;</button>
    </div>
    <div class="doc-body">
      <p>These Terms and Conditions govern your use of FilmSpec's equipment and crew rental services. By submitting a booking request, you confirm that you have read, understood, and agree to be bound by these terms.</p>

      <h4>1. Booking and Confirmation</h4>
      <p>All booking requests are subject to availability and admin approval. A booking is only confirmed once FilmSpec issues a written confirmation and the required downpayment (if applicable) has been received. FilmSpec reserves the right to decline any booking request without obligation to provide a reason.</p>

      <h4>2. Equipment Use and Responsibility</h4>
      <ul>
        <li>All equipment remains the property of FilmSpec at all times.</li>
        <li>The client assumes full responsibility for all FilmSpec equipment from the time of release until return.</li>
        <li>Equipment must be used only for the stated purpose and must not be sub-leased, transferred, or used by unauthorized persons.</li>
        <li>The client is liable for any damage, loss, or theft of equipment during the rental period, regardless of cause.</li>
        <li>All equipment must be returned in the same condition as released. Any damage found upon return will be assessed and charged accordingly.</li>
      </ul>

      <h4>3. Crew Assignment</h4>
      <ul>
        <li>FilmSpec assigns crew based on equipment requirements and availability.</li>
        <li>Crew members are under the direct coordination of FilmSpec during the rental period.</li>
        <li>The client must ensure a safe working environment for all FilmSpec crew on location.</li>
        <li>Crew talent fees are included in the cost estimate and are non-refundable once the shoot has commenced.</li>
      </ul>

      <h4>4. Payment Terms</h4>
      <ul>
        <li><strong>New Clients</strong> (fewer than 5 completed bookings): A 50% downpayment of the total amount is required before equipment can be released.</li>
        <li><strong>Regular Clients</strong>: Payment is due within 90 days from the date of equipment release.</li>
        <li>Overdue balances beyond 90 days will receive a formal notice. A 7-day grace period follows. Accounts exceeding the grace period may be subject to late fees and suspension of future bookings.</li>
        <li>Payments may be made via bank transfer, check, or other methods accepted by FilmSpec Accounting.</li>
      </ul>

      <h4>5. Cancellation Policy</h4>
      <ul>
        <li>Cancellations before equipment release are subject to admin review and may incur processing fees.</li>
        <li>If a booking is cancelled after equipment has been released and is already in the field, the client is obligated to pay 50% of the total rental fee.</li>
        <li>No refund will be issued for crew talent fees once a shoot has started.</li>
      </ul>

      <h4>6. Transportation and Delivery</h4>
      <p>Transportation costs are determined by the straight-line distance from Metro Manila (Intramuros) to the shoot location: 0–60 km (1×), 61–150 km (1.5×), 151 km and above (2×). Zone classification applies within Luzon only. The zone rate applied is as indicated in the cost estimate. The client is responsible for safe vehicle access at the shoot location.</p>

      <h4>7. Incident Reports</h4>
      <p>Any damage, malfunction, or loss of equipment must be reported to FilmSpec immediately. An incident report will be filed and the client will be notified of any applicable charges. Filing a false or misleading incident report may result in account termination.</p>

      <h4>8. Force Majeure</h4>
      <p>FilmSpec shall not be held liable for failure to fulfill obligations due to circumstances beyond reasonable control, including but not limited to natural disasters, government orders, or power failures. In such cases, FilmSpec will work with the client to reschedule at no additional charge.</p>

      <h4>9. Governing Law</h4>
      <p>These Terms and Conditions shall be governed by and construed in accordance with the laws of the Republic of the Philippines. Any disputes shall be settled through the appropriate courts of Makati City.</p>

      <h4>10. Amendments</h4>
      <p>FilmSpec reserves the right to update these Terms and Conditions at any time. Clients will be notified of material changes. Continued use of FilmSpec services following notification constitutes acceptance of the updated terms.</p>
    </div>
    <div class="doc-foot">
      <button class="doc-accept-btn" onclick="acceptDoc('tcMo','chkTc')">I Agree &amp; Close</button>
    </div>
  </div>
</div>

<!-- ═══ PRIVACY POLICY MODAL ═══ -->
<div class="doc-mo" id="ppMo">
  <div class="doc-box">
    <div class="doc-head">
      <h3>Privacy Policy</h3>
      <button class="doc-close" onclick="closeDocMo('ppMo')">&times;</button>
    </div>
    <div class="doc-body">
      <p>FilmSpec is committed to protecting your personal information. This Privacy Policy explains how we collect, use, store, and protect your data in compliance with the Data Privacy Act of 2012 (Republic Act No. 10173) of the Philippines.</p>

      <h4>1. Information We Collect</h4>
      <p>When you register and use FilmSpec services, we may collect the following:</p>
      <ul>
        <li><strong>Identity Information:</strong> Full name, company/organization name, contact person</li>
        <li><strong>Contact Information:</strong> Email address, phone number, physical address</li>
        <li><strong>Booking Information:</strong> Project details, shoot dates, locations, equipment and crew requirements</li>
        <li><strong>Financial Information:</strong> Payment records, statement of accounts, downpayment receipts</li>
        <li><strong>Location Data:</strong> Shoot location coordinates for zone-rate calculation (collected only during booking)</li>
        <li><strong>Activity Logs:</strong> Actions taken within the system, for audit and security purposes</li>
      </ul>

      <h4>2. How We Use Your Information</h4>
      <ul>
        <li>To process and manage your booking requests</li>
        <li>To assign appropriate equipment and crew for your production</li>
        <li>To generate cost estimates, invoices, and statements of account</li>
        <li>To communicate booking confirmations, updates, and payment notices</li>
        <li>To comply with legal and regulatory obligations</li>
        <li>To improve our services and internal operations</li>
      </ul>

      <h4>3. Data Sharing</h4>
      <p>FilmSpec does not sell or trade your personal information to third parties. Your data may be shared only with:</p>
      <ul>
        <li>FilmSpec staff directly involved in your booking (operations, traffic, accounting)</li>
        <li>Government authorities, when required by law</li>
        <li>Service providers who assist in system operations (e.g., email delivery), under strict data protection agreements</li>
      </ul>

      <h4>4. Data Retention</h4>
      <p>Your booking and financial records are retained for a minimum of 5 years in compliance with BIR and applicable regulations. Account information is retained for as long as your account is active or as needed to provide services.</p>

      <h4>5. Data Security</h4>
      <p>FilmSpec implements reasonable technical and organizational measures to protect your data against unauthorized access, alteration, disclosure, or destruction. This includes password hashing, session management, and role-based access controls within the system.</p>

      <h4>6. Your Rights</h4>
      <p>Under the Data Privacy Act, you have the right to:</p>
      <ul>
        <li>Be informed about how your data is processed</li>
        <li>Access a copy of your personal data held by FilmSpec</li>
        <li>Request correction of inaccurate data</li>
        <li>Request deletion of data no longer necessary for its original purpose</li>
        <li>File a complaint with the National Privacy Commission (NPC)</li>
      </ul>
      <p>To exercise these rights, contact us at: <strong>filmspec@filmspec.ph</strong></p>

      <h4>7. Cookies and Session Data</h4>
      <p>The FilmSpec system uses session cookies solely to maintain your login state. No tracking or third-party advertising cookies are used.</p>

      <h4>8. Changes to This Policy</h4>
      <p>FilmSpec may update this Privacy Policy from time to time. You will be notified of significant changes via email or an in-system notice. Continued use of our services after such notification constitutes your acceptance of the revised policy.</p>

      <h4>9. Contact</h4>
      <p>For privacy-related concerns, contact the FilmSpec Data Protection Officer at:<br>
      <strong>Gate 1, 9110 La Campana St. cor Trabajo St., Olympia, Makati City</strong><br>
      Tel: +632 70004683 · Cel: +63927 5056461</p>
    </div>
    <div class="doc-foot">
      <button class="doc-accept-btn" onclick="acceptDoc('ppMo','chkPp')">I Agree &amp; Close</button>
    </div>
  </div>
</div>

<script>
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;
const TILE_PROXY_URL = "{{ route('tile-proxy') }}";

function showSheet(id, el) {
    document.querySelectorAll('.sheet-content').forEach(s => s.classList.remove('on'));
    document.querySelectorAll('.stab').forEach(t => t.classList.remove('on'));
    document.getElementById('sheet-' + id).classList.add('on');
    el.classList.add('on');
}

function openDocMo(id) {
    document.getElementById(id).classList.add('on');
}
function closeDocMo(id) {
    document.getElementById(id).classList.remove('on');
}
function acceptDoc(moId, chkId) {
    const chk = document.getElementById(chkId);
    if (chk) chk.checked = true;
    closeDocMo(moId);
    updateSubmitBtn();
}
function updateSubmitBtn() {
    const tc  = document.getElementById('chkTc')?.checked;
    const pp  = document.getElementById('chkPp')?.checked;
    const btn = document.getElementById('submitBtn');
    if (!btn) return;
    const ok  = tc && pp;
    btn.disabled       = !ok;
    btn.style.opacity  = ok ? '1' : '.45';
    btn.style.cursor   = ok ? 'pointer' : 'not-allowed';
}
document.addEventListener('click', function(e) {
    ['tcMo','ppMo'].forEach(id => {
        const mo = document.getElementById(id);
        if (mo && e.target === mo) closeDocMo(id);
    });
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') ['tcMo','ppMo'].forEach(closeDocMo);
});

@if ($mode === 'cart')
// ── Base costs for live preview ───────────────────────────────────────────────
const VAT_RATE = {{ $vatRate }};
const _BASE_EQUIP = {{ $equipTotal }};   // per-day equipment total (cart days = 1)
const _BASE_CREW  = 0;
const _BASE_TRANS = {{ $baseTransRate }};

let _shootDays    = 1;
let _currentMult  = 1;
let _currentZone  = '';
let _currentZoneLabel = '';

function _pesoFmt(n) {
    return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function _numToWords(n) {
    n = Math.round(n);
    if (n <= 0) return 'ZERO';
    const ones = ['','ONE','TWO','THREE','FOUR','FIVE','SIX','SEVEN','EIGHT','NINE','TEN','ELEVEN',
        'TWELVE','THIRTEEN','FOURTEEN','FIFTEEN','SIXTEEN','SEVENTEEN','EIGHTEEN','NINETEEN'];
    const tens = ['','','TWENTY','THIRTY','FORTY','FIFTY','SIXTY','SEVENTY','EIGHTY','NINETY'];
    function three(x) {
        if (!x) return '';
        if (x < 20) return ones[x];
        if (x < 100) return tens[Math.floor(x/10)] + (x%10 ? ' '+ones[x%10] : '');
        return ones[Math.floor(x/100)] + ' HUNDRED' + (x%100 ? ' '+three(x%100) : '');
    }
    const parts = [];
    if (n >= 1000000) { parts.push(three(Math.floor(n/1000000)) + ' MILLION'); n %= 1000000; }
    if (n >= 1000)    { parts.push(three(Math.floor(n/1000)) + ' THOUSAND'); n %= 1000; }
    if (n > 0)        { parts.push(three(n)); }
    return parts.join(' ');
}

function updateLiveCost(multiplier, zone, label) {
    _currentMult      = multiplier;
    _currentZone      = zone;
    _currentZoneLabel = label;

    const eqAdj    = _BASE_EQUIP * _shootDays * multiplier;
    const transAdj = _BASE_TRANS * multiplier;
    const grand    = eqAdj + _BASE_CREW + transAdj;
    const vat      = grand * VAT_RATE / (1 + VAT_RATE);
    const sub      = grand - vat;

    const multNote = multiplier !== 1 ? '× ' + multiplier + ' (' + label + ')' : '';

    document.getElementById('live-eq-val').textContent    = _pesoFmt(eqAdj);
    document.getElementById('live-eq-note').textContent   = multNote;
    document.getElementById('live-trans-val').textContent  = _pesoFmt(transAdj);
    document.getElementById('live-trans-note').textContent = multiplier + '× rate · ' + label;
    document.getElementById('live-trans-val').style.color  = '#003D80';
    document.getElementById('live-sub').textContent        = _pesoFmt(sub);
    document.getElementById('live-vat').textContent        = _pesoFmt(vat);
    document.getElementById('live-grand').textContent      = _pesoFmt(grand);

    const eqN = document.getElementById('sum-eq-note');
    if (eqN) eqN.textContent = multNote;
    const eqV = document.getElementById('sum-eq-val');
    if (eqV) eqV.textContent = _pesoFmt(eqAdj);
    const tN = document.getElementById('sum-trans-note');
    if (tN) tN.textContent = multiplier + '× · ' + label;
    const tV = document.getElementById('sum-trans-val');
    if (tV) tV.textContent = _pesoFmt(transAdj);
    const sv = document.getElementById('sum-subtotal');
    if (sv) sv.textContent = _pesoFmt(sub);
    const vv = document.getElementById('sum-vat');
    if (vv) vv.textContent = _pesoFmt(vat);
    const gv = document.getElementById('sum-grand');
    if (gv) gv.textContent = _pesoFmt(grand);

    document.querySelectorAll('.eq-days-cell').forEach(el => { el.textContent = _shootDays; });
    document.querySelectorAll('.eq-rate').forEach(el => {
        el.textContent = '₱' + (parseFloat(el.dataset.base) * multiplier)
            .toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
    });
    document.querySelectorAll('.eq-amt').forEach(el => {
        el.textContent = '₱' + (parseFloat(el.dataset.base) * _shootDays * multiplier)
            .toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2});
    });
    const p = s => _pesoFmt(s);
    const eqVatAmt = eqAdj * VAT_RATE / (1 + VAT_RATE);
    const eqNetAmt = eqAdj - eqVatAmt;
    const el_t = document.getElementById('eq-tbl-total'); if (el_t) el_t.textContent = p(eqAdj);
    const el_s = document.getElementById('eq-sub-val');   if (el_s) el_s.textContent = p(eqNetAmt);
    const el_v = document.getElementById('eq-vat-val');   if (el_v) el_v.textContent = p(eqVatAmt);
    const el_g = document.getElementById('eq-grand-val'); if (el_g) el_g.textContent = p(eqAdj);
    const el_w = document.getElementById('eq-words');
    if (el_w) el_w.textContent = 'Amount in Words: ' + _numToWords(eqAdj) + ' PESOS ONLY';

    document.getElementById('bf_transport_cost').value = transAdj.toFixed(2);
}

const _SUBMIT_ZONE_CFG = {
    manila:    { label: 'Metro Manila / NCR (0–60 km)',  multiplier: 1.0, bg: '#E5F0FF', border: '#7BBEFF', color: '#004EA3' },
    luzon:     { label: 'Luzon Near (61–150 km)',         multiplier: 1.5, bg: '#fefce8', border: '#fde047', color: '#ca8a04' },
    luzon_far: { label: 'Luzon Far (151 km+)',            multiplier: 2.0, bg: '#fff7ed', border: '#fed7aa', color: '#c2410c' },
};

function _haversineKm(lat1, lon1, lat2, lon2) {
    const R = 6371, dLat = (lat2-lat1)*Math.PI/180, dLon = (lon2-lon1)*Math.PI/180;
    const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

const _MANILA_LAT = 14.5995, _MANILA_LNG = 120.9842;

function _submitDetectZoneByDistance(lat, lng) {
    const km = _haversineKm(lat, lng, _MANILA_LAT, _MANILA_LNG);
    if (km <= 60)  return 'manila';
    if (km <= 150) return 'luzon';
    return 'luzon_far';
}

const _SUBMIT_WATER_TYPES = new Set([
    'bay','sea','ocean','water','strait','gulf','fjord','lagoon','river','lake',
    'reservoir','pond','canal','stream','wetland','coastline','harbour','harbor',
    'marina','estuary','tidal','fishing'
]);

function _submitIsWater(data) {
    const addr     = data.address   || {};
    const cls      = data.class     || '';
    const typ      = data.type      || '';
    const addrType = (data.addresstype || '').toLowerCase();
    const extra    = data.extratags || {};

    if (_SUBMIT_WATER_TYPES.has(addrType)) return true;

    const waterAddrKeys = ['sea','ocean','bay','body_of_water','water','strait',
        'gulf','fjord','lagoon','river','lake','reservoir','pond','canal'];
    if (waterAddrKeys.some(k => k in addr)) return true;

    if (cls === 'waterway') return true;
    if ((cls === 'natural' || cls === 'leisure') && _SUBMIT_WATER_TYPES.has(typ)) return true;

    if (extra.natural && _SUBMIT_WATER_TYPES.has(extra.natural)) return true;
    if (extra.waterway || extra.water) return true;

    const cityKeys = ['city','town','municipality','city_district','suburb','neighbourhood','village','hamlet'];
    if (!cityKeys.some(k => k in addr) && cls === 'natural') return true;

    return false;
}

function _submitBuildAddress(data) {
    const a = data.address || {};
    const street   = [a.house_number, a.road || a.pedestrian || a.footway || a.path].filter(Boolean).join(' ');
    const barangay = a.suburb || a.neighbourhood || a.quarter || a.village || a.hamlet || a.city_district || '';
    const cityMun  = a.city || a.town || a.municipality || '';
    const province = a.state_district || a.province || a.county || '';
    const parts = [street, barangay, cityMun, province].filter(Boolean);
    if (parts.length >= 2) return parts.join(', ');
    return (data.display_name || '').split(',').slice(0, 5).join(',').trim();
}

let _submitLastValidLatLng = null;

function _submitTileIsWater(lat, lng) {
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
        img.src = TILE_PROXY_URL + '?z=' + zoom + '&x=' + tX + '&y=' + tY;
    });
}

async function _submitReverseGeocode(lat, lng, updateName = true) {
    const zoneEl = document.getElementById('submitZoneInfo');
    zoneEl.style.cssText = 'display:block;padding:8px 12px;border-radius:6px;background:#f1f5f9;border:1px solid #e2e8f0;font-size:12px;color:#64748b;margin-top:6px';
    zoneEl.textContent = 'Verifying location…';
    try {
        const [data, tileWater] = await Promise.all([
            fetch(
                'https://nominatim.openstreetmap.org/reverse?lat=' + lat + '&lon=' + lng + '&format=json&addressdetails=1&zoom=18&extratags=1',
                { headers: { 'Accept-Language': 'en', 'User-Agent': 'FilmSpec/1.0' } }
            ).then(r => r.json()),
            _submitTileIsWater(lat, lng)
        ]);

        if (_submitIsWater(data) || tileWater) {
            zoneEl.style.cssText = 'display:block;padding:8px 12px;border-radius:6px;background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;font-size:12px;margin-top:6px';
            zoneEl.textContent = '⚠ Cannot pin on a body of water. Please select a land location.';
            if (_submitLastValidLatLng && _submitMarker) _submitMarker.setLatLng(_submitLastValidLatLng);
            else if (_submitMarker) { _submitMarker.remove(); _submitMarker = null; }
            return;
        }

        _submitLastValidLatLng = L.latLng(lat, lng);
        document.getElementById('bf_lat').value = lat.toFixed(7);
        document.getElementById('bf_lng').value = lng.toFixed(7);
        const km   = Math.round(_haversineKm(lat, lng, _MANILA_LAT, _MANILA_LNG));
        const zone = _submitDetectZoneByDistance(lat, lng);
        const cfg  = _SUBMIT_ZONE_CFG[zone];
        document.getElementById('bf_zone').value                 = zone;
        document.getElementById('bf_transport_multiplier').value = cfg.multiplier;
        if (updateName) document.getElementById('bf_location').value = _submitBuildAddress(data);
        zoneEl.style.cssText = 'display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:6px;background:' + cfg.bg + ';border:1px solid ' + cfg.border + ';font-size:12px;margin-top:6px';
        zoneEl.innerHTML = '<span style="background:' + cfg.bg + ';border:1px solid ' + cfg.border + ';color:' + cfg.color + ';padding:2px 10px;border-radius:10px;font-weight:700">' + cfg.label + '</span>'
            + '<span style="color:#64748b">~' + km + ' km &middot; ' + cfg.multiplier + '&times; applies to transport</span>';
        updateLiveCost(cfg.multiplier, zone, cfg.label);
    } catch (e) {
        zoneEl.style.cssText = 'display:block;padding:8px 12px;border-radius:6px;background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;font-size:12px;margin-top:6px';
        zoneEl.textContent = 'Could not detect location — please check your connection.';
    }
}

let _submitMap = null, _submitMarker = null;
let _submitMapLocked = false;

function toggleSubmitMapLock() {
  _submitMapLocked = !_submitMapLocked;
  const btn  = document.getElementById('submit_lock_btn');
  const icon = document.getElementById('submit_lock_icon');
  const text = document.getElementById('submit_lock_text');
  const cont = document.getElementById('submitMapContainer');
  const SVG_LOCKED   = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
  const SVG_UNLOCKED = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>';
  if (_submitMapLocked) {
    if (_submitMap)    { _submitMap.dragging.disable(); _submitMap.scrollWheelZoom.disable(); _submitMap.doubleClickZoom.disable(); }
    if (_submitMarker)  _submitMarker.dragging.disable();
    btn.style.background   = 'rgba(22,163,74,.92)';
    icon.innerHTML         = SVG_LOCKED;
    text.textContent       = 'Location Locked — Click to Unlock';
    cont.style.borderColor = '#16a34a';
  } else {
    if (_submitMap)    { _submitMap.dragging.enable(); _submitMap.scrollWheelZoom.enable(); _submitMap.doubleClickZoom.enable(); }
    if (_submitMarker)  _submitMarker.dragging.enable();
    btn.style.background   = 'rgba(0,96,199,.92)';
    icon.innerHTML         = SVG_UNLOCKED;
    text.textContent       = 'Lock Location';
    cont.style.borderColor = '#e2e8f0';
  }
}

const _LUZON_BOUNDS = L.latLngBounds([[12.0, 119.5], [18.7, 122.5]]);

function _isInLuzon(lat, lng) {
    return lat >= 12.0 && lat <= 18.7 && lng >= 119.5 && lng <= 122.5;
}

// Shared by the map-click handler and the type-to-locate search below — creates the
// draggable marker on first use, just moves it on subsequent calls.
function _submitPlaceMarker(lat, lng) {
    const latlng = L.latLng(lat, lng);
    if (_submitMarker) _submitMarker.setLatLng(latlng);
    else {
        _submitMarker = L.marker(latlng, { draggable: true }).addTo(_submitMap);
        _submitMarker.on('dragend', () => {
            if (_submitMapLocked) return;
            const p = _submitMarker.getLatLng();
            if (!_isInLuzon(p.lat, p.lng)) {
                if (_submitLastValidLatLng) _submitMarker.setLatLng(_submitLastValidLatLng);
                else { _submitMarker.remove(); _submitMarker = null; }
                return;
            }
            _submitReverseGeocode(p.lat, p.lng);
        });
    }
    const lockBtn = document.getElementById('submit_lock_btn');
    if (lockBtn) lockBtn.style.display = 'flex';
}

function initSubmitMap() {
    if (_submitMap) { _submitMap.invalidateSize(); return; }
    _submitMap = L.map('submitBookingMap', {
        scrollWheelZoom: true,
        maxBounds: _LUZON_BOUNDS,
        maxBoundsViscosity: 1.0,
        minZoom: 7
    }).setView([14.5995, 120.9842], 9);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a>',
        maxZoom: 18
    }).addTo(_submitMap);
    _submitMap.on('click', function(e) {
        if (_submitMapLocked) return;
        const { lat, lng } = e.latlng;
        if (!_isInLuzon(lat, lng)) {
            const zoneEl = document.getElementById('submitZoneInfo');
            zoneEl.style.cssText = 'display:block;padding:8px 12px;border-radius:6px;background:#fee2e2;border:1px solid #fca5a5;font-size:12px;color:#b91c1c;margin-top:6px';
            zoneEl.textContent = '⚠ Please pin your location on land within Luzon.';
            return;
        }
        _submitPlaceMarker(lat, lng);
        _submitReverseGeocode(lat, lng);
    });
}

// Type-to-locate: debounced place search restricted to a Luzon viewbox, rendered as a custom
// two-line (name / full address) suggestion dropdown under the field — replaces relying on
// the browser's own remembered-entries autocomplete. Picking a suggestion reuses
// _submitReverseGeocode for the water check / zone detection / live cost update, with
// updateName=false since we already have the name from the suggestion itself.
let _submitGeocodeTimer = null;
let _submitSuggestions = [];

function onSubmitLocationTyped() {
    clearTimeout(_submitGeocodeTimer);
    const q = document.getElementById('bf_location').value.trim();
    if (q.length < 3) { closeSubmitLocationSuggest(); return; }
    _submitGeocodeTimer = setTimeout(() => _submitFetchSuggestions(q), 400);
}

function onSubmitLocationKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        if (_submitSuggestions.length) _submitPickSuggestion(0);
    } else if (e.key === 'Escape') {
        closeSubmitLocationSuggest();
    }
}

function closeSubmitLocationSuggest() {
    const box = document.getElementById('bf_location_suggest');
    box.style.display = 'none';
    box.innerHTML = '';
    _submitSuggestions = [];
}

async function _submitFetchSuggestions(query) {
    if (!_submitMap) return;
    try {
        const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=5&addressdetails=1&countrycodes=ph'
            + '&viewbox=119.5,18.7,122.5,12.0&bounded=1&q=' + encodeURIComponent(query);
        const results = await fetch(url, { headers: { 'Accept-Language': 'en', 'User-Agent': 'FilmSpec/1.0' } }).then(r => r.json());
        _submitSuggestions = results.filter(r => _isInLuzon(parseFloat(r.lat), parseFloat(r.lon)));
        _submitRenderSuggestions();
    } catch (e) {
        closeSubmitLocationSuggest();
    }
}

// Built via DOM nodes + textContent rather than innerHTML string-concat — display_name comes
// straight from Nominatim (third-party, untrusted), so it must never be interpolated as HTML.
function _submitRenderSuggestions() {
    const box = document.getElementById('bf_location_suggest');
    box.innerHTML = '';
    if (!_submitSuggestions.length) { closeSubmitLocationSuggest(); return; }
    const pinSvg = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#0060C7" stroke-width="2.5"><path d="M12 21s7-7.5 7-12a7 7 0 1 0-14 0c0 4.5 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/></svg>';

    _submitSuggestions.forEach((r, i) => {
        const parts = String(r.display_name || '').split(',');
        const primary = parts[0].trim();
        const secondary = parts.slice(1).join(',').trim();

        const row = document.createElement('div');
        row.style.cssText = 'padding:9px 12px;cursor:pointer;' + (i > 0 ? 'border-top:1px solid #f1f5f9;' : '');
        row.addEventListener('mousedown', () => _submitPickSuggestion(i));
        row.addEventListener('mouseover', () => row.style.background = '#f0f7ff');
        row.addEventListener('mouseout', () => row.style.background = '');

        const nameRow = document.createElement('div');
        nameRow.style.cssText = 'font-size:13px;font-weight:600;color:#0f172a;display:flex;align-items:center;gap:6px';
        const iconSpan = document.createElement('span');
        iconSpan.style.display = 'flex';
        iconSpan.innerHTML = pinSvg;
        const nameSpan = document.createElement('span');
        nameSpan.textContent = primary;
        nameRow.append(iconSpan, nameSpan);
        row.appendChild(nameRow);

        if (secondary) {
            const addrRow = document.createElement('div');
            addrRow.style.cssText = 'font-size:11.5px;color:#64748b;margin-top:1px;margin-left:18px';
            addrRow.textContent = secondary;
            row.appendChild(addrRow);
        }

        box.appendChild(row);
    });
    box.style.display = 'block';
}

function _submitPickSuggestion(i) {
    const r = _submitSuggestions[i];
    if (!r) return;
    const lat = parseFloat(r.lat), lng = parseFloat(r.lon);
    document.getElementById('bf_location').value = r.display_name.split(',').slice(0, 2).join(',').trim();
    closeSubmitLocationSuggest();
    _submitPlaceMarker(lat, lng);
    _submitMap.setView([lat, lng], 15);
    _submitReverseGeocode(lat, lng, false);
}

function bfSyncEndMin() {
    const sEl = document.getElementById('bf_start');
    const eEl = document.getElementById('bf_end');
    const s = sEl.value;
    if (s) {
        eEl.min = s;
        if (eEl.value && eEl.value < s) eEl.value = s;
    }
    const start = sEl.value;
    const end   = eEl.value;
    if (start && end && end >= start) {
        _shootDays = Math.round((new Date(end) - new Date(start)) / 86400000) + 1;
        updateLiveCost(_currentMult, _currentZone, _currentZoneLabel);
    }
}

document.addEventListener('DOMContentLoaded', initSubmitMap);
// Mobile CSS shrinks #submitBookingMap's height at narrow widths — Leaflet
// needs invalidateSize() after any container resize (orientation change,
// browser chrome show/hide) or it redraws misaligned/partially blank.
window.addEventListener('resize', () => { if (_submitMap) _submitMap.invalidateSize(); });
window.addEventListener('orientationchange', () => { if (_submitMap) setTimeout(() => _submitMap.invalidateSize(), 200); });

let _submitInFlight = false;
function doSubmit() {
    if (_submitInFlight) return;
    if (!document.getElementById('chkTc')?.checked || !document.getElementById('chkPp')?.checked) {
        alert('Please read and agree to both the Terms and Conditions and Privacy Policy before submitting.');
        return;
    }
    const title = document.getElementById('bf_title').value.trim();
    const start = document.getElementById('bf_start').value;
    const end   = document.getElementById('bf_end').value;
    if (!title || !start || !end) {
        alert('Please fill in Project Title, Start Date, and End Date.');
        return;
    }
    _submitInFlight = true;
    const submitBtn = document.querySelector('[onclick="doSubmit()"]');
    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Submitting…'; }
    const fd = new FormData();
    fd.append('_token',               CSRF_TOKEN);
    fd.append('action',               'submit_booking');
    fd.append('project_title',        title);
    fd.append('project_type',         document.getElementById('bf_type').value);
    fd.append('shoot_date_start',     start);
    fd.append('shoot_date_end',       end);
    fd.append('shoot_location',       document.getElementById('bf_location').value);
    fd.append('notes',                document.getElementById('bf_notes').value);
    fd.append('location_lat',         document.getElementById('bf_lat').value);
    fd.append('location_lng',         document.getElementById('bf_lng').value);
    fd.append('location_zone',        document.getElementById('bf_zone').value);
    fd.append('transport_multiplier', document.getElementById('bf_transport_multiplier').value);
    fd.append('transportation_cost',  document.getElementById('bf_transport_cost').value);
    fd.append('tc_accepted',          '1');
    fd.append('pp_accepted',          '1');
    fetch('/cart', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.ok) {
                const m = document.createElement('div');
                m.style.cssText = 'position:fixed;inset:0;background:rgba(0,30,80,.5);backdrop-filter:blur(4px);z-index:999;display:flex;align-items:center;justify-content:center';
                m.innerHTML = `<div style="background:#fff;border-radius:14px;width:400px;max-width:94vw;text-align:center;padding:30px;box-shadow:0 20px 60px rgba(0,0,0,.2)">
                    <div style="font-size:48px;margin-bottom:16px;color:#003D80">&#10003;</div>
                    <div style="font-family:'Bebas Neue',sans-serif;font-size:24px;letter-spacing:1px;color:#003D80;margin-bottom:12px">Booking Submitted!</div>
                    <div style="font-size:14px;color:#64748b;margin-bottom:8px">Your booking reference:</div>
                    <div style="font-family:'JetBrains Mono',monospace;font-size:20px;font-weight:700;color:#003D80;background:#f0f9ff;padding:12px;border-radius:8px;margin-bottom:20px">${d.booking_reference}</div>
                    <div style="font-size:13px;color:#64748b;margin-bottom:24px">Pending admin approval. You'll be notified by email.</div>
                    <button onclick="window.location.href='/'"
                            style="background:#003D80;color:#fff;border:none;padding:10px 24px;border-radius:6px;font-weight:600;cursor:pointer;font-size:14px">View My Bookings &rarr;</button>
                </div>`;
                document.body.appendChild(m);
            } else {
                _submitInFlight = false;
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Submit Booking'; }
                alert(d.error || 'Error submitting booking. Please try again.');
            }
        })
        .catch(() => {
            _submitInFlight = false;
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Submit Booking'; }
            alert('Network error. Please try again.');
        });
}
@endif
</script>

</body>
</html>
