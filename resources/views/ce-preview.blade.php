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

.ce-export-wrap{position:relative}
.ce-export-menu{display:none;position:absolute;right:0;top:calc(100% + 6px);background:#fff;border:1px solid var(--border);border-radius:8px;padding:5px;min-width:190px;box-shadow:0 8px 24px rgba(0,0,0,.18);z-index:200}
.ce-export-menu.open{display:block}
.ce-export-opt{display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:6px;font-size:12.5px;color:var(--text);text-decoration:none;transition:background .12s}
.ce-export-opt:hover{background:var(--bg);color:var(--accent)}
.ce-export-opt svg{flex-shrink:0;color:var(--muted)}

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

{{-- Restored the earlier blue-branded on-screen look for the client-facing document (design
     only — same markup/classes/data as before, just re-themed). The black/white Arial styling
     this replaces was written to match FilmSpec's official Excel/PDF template; that template is
     untouched here (see exports/ce-document-pdf.blade.php) — only what the client sees on screen
     in ce-preview changes. --}}
.ce-sheet{background:#fff;border:1px solid #A8D0FF;border-radius:10px;overflow:hidden;margin-bottom:24px;box-shadow:0 2px 10px rgba(0,61,128,.08);font-family:'DM Sans',sans-serif;color:var(--text)}

.ce-header{display:grid;grid-template-columns:1fr 1fr;border-bottom:2px solid #003D80}
.ce-header-left{background:#fff;color:#000;padding:16px 20px;display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;border-right:2px solid #003D80}
.ce-header-left .addr{font-size:9.5px;font-weight:600;font-style:normal;margin-top:6px;line-height:1.6;color:var(--sub)}
.ce-header-right{background:#E5F0FF;padding:0;display:flex;flex-direction:column;justify-content:center}
.ce-type{font-family:'Bebas Neue',sans-serif;font-size:26px;letter-spacing:2px;color:#003D80;line-height:1;font-weight:400;background:transparent;padding:14px 18px 2px;text-align:left}
.ce-num{font-family:'JetBrains Mono',monospace;font-size:12px;color:#003D80;font-weight:700;font-style:normal;text-align:left;padding:0 18px 8px;border-bottom:none}
.ce-meta{display:grid;grid-template-columns:auto 1fr;gap:2px 10px;font-size:11px;padding:8px 18px 14px}
.ce-meta .lbl{color:#4a6fa5;font-weight:600;padding:2px 0;border-top:none;text-align:right}
.ce-meta .val{color:#003D80;font-weight:700;font-style:normal;padding:2px 0;border-top:none;text-align:left;font-family:'JetBrains Mono',monospace}
.ce-meta .fsfront{grid-column:1/-1;text-align:left;color:#c0392b;font-weight:700;font-style:normal;font-size:12px;padding:4px 0 0;border-top:none}

.ce-info{border-bottom:1.5px solid #A8D0FF}
.ce-info-row{display:grid;grid-template-columns:160px 1fr;border-bottom:1px solid var(--border,#e2e8f0);min-height:26px;align-items:center}
.ce-info-row:last-child{border-bottom:none}
.ce-info-lbl{padding:5px 14px;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.03em;background:#fff;border-right:none}
.ce-info-val{padding:5px 14px;font-size:12px;font-weight:600;font-style:normal;color:#0f172a}

/* On-screen "Client & Project Information" card (internal editor view) — same markup, its own
   softer variant so it doesn't need the client sheet's blue header/table treatment above. */
.ce-info.ce-info-soft{border-bottom:none}
.ce-info-soft .ce-info-row{grid-template-columns:150px 1fr;border-bottom:1px solid var(--border,#e2e8f0);min-height:40px}
.ce-info-soft .ce-info-row:nth-child(even){background:#f8fafc}
.ce-info-soft .ce-info-lbl{font-size:10.5px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;background:transparent;padding:10px 16px}
.ce-info-soft .ce-info-val{font-size:13px;font-weight:600;font-style:normal;color:#0f172a;padding:10px 16px}

.ce-section-hdr{background:#003D80;color:#fff;padding:7px 16px;font-size:10.5px;font-weight:700;font-style:normal;letter-spacing:1.2px;text-transform:uppercase;text-align:left;border-bottom:none}

.ce-tbl{width:100%;border-collapse:collapse;font-family:'DM Sans',sans-serif}
.ce-tbl th{background:#D0E8FF;color:#003D80;font-size:10px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;padding:7px 10px;text-align:left;border:1px solid #A8D0FF}
.ce-tbl th.right{text-align:right}
.ce-tbl td{padding:6px 10px;font-size:12px;border:1px solid var(--border,#e2e8f0);vertical-align:middle;color:var(--text)}
.ce-tbl td.right{text-align:right;font-family:'JetBrains Mono',monospace;font-size:11.5px}
.ce-tbl td.mono{font-family:'JetBrains Mono',monospace;font-size:11.5px}
.ce-tbl tr:nth-child(even) td{background:#f9fbff}
.ce-tbl tr:hover td{background:#f0f7ff}
.ce-tbl .subtot td{background:#D0E8FF!important;font-weight:700;color:#003D80;font-style:normal}
.ce-tbl .grandtot td{background:#003D80!important;color:#fff!important;font-weight:800;font-size:12px}
.ce-tbl .grandtot td.right{font-family:'JetBrains Mono',monospace}
.ce-tbl .catgroup td{background:#f0f7ff!important;color:#003D80!important;font-weight:700;font-style:normal;border:none;border-top:1px solid #A8D0FF;padding-top:8px}

.ce-totals{padding:12px 18px;background:#fff;border-top:none}
.ce-totals table{width:100%;max-width:380px;margin-left:auto;border-collapse:collapse}
.ce-totals td{padding:5px 10px;font-size:12px}
.ce-totals td.lbl{color:var(--sub);font-weight:600;font-style:normal}
.ce-totals td.val{text-align:right;font-family:'JetBrains Mono',monospace;font-weight:700;font-style:normal;color:#003D80}
.ce-totals .vat td.lbl{color:var(--sub)}
.ce-totals .vat td.val{color:#003D80}
.ce-totals .grand td{background:#003D80;color:#fff;font-weight:800;font-size:14px;padding:10px 12px;border-top:none}
.ce-totals .grand td.val{font-family:'JetBrains Mono',monospace;text-align:right}

.ce-words{padding:10px 18px;border-top:1px solid #A8D0FF;font-size:11.5px;font-style:italic;color:#003D80;font-weight:700;background:#E5F0FF;text-align:left;text-decoration:none}

.ce-footer{padding:20px 18px 16px;border-top:1px solid #A8D0FF;display:grid;grid-template-columns:1fr 1fr;gap:40px;font-family:'DM Sans',sans-serif}
.ce-sig{border-top:1px solid #94a3b8;padding-top:5px;font-size:10.5px;color:var(--sub);text-align:center;margin-top:32px}

.ce-notes{padding:9px 16px;background:#fdf6e8;border-top:1px solid #A8D0FF;font-size:10.5px;font-weight:600;font-style:normal;color:#92400e;line-height:1.7;text-align:center}

.ce-summary-row{display:grid;grid-template-columns:1fr auto;align-items:center;padding:6px 16px;border-bottom:1px solid var(--border,#e2e8f0)}
.ce-summary-row:last-child{border-bottom:none}
.ce-summary-lbl{font-size:12px;font-weight:600;font-style:normal;color:var(--sub)}
.ce-summary-val{font-family:'JetBrains Mono',monospace;font-size:12px;font-weight:700;font-style:normal;color:#003D80}
.ce-summary-row.grand{border-top:2px solid #003D80;background:#E5F0FF}
.ce-summary-row.grand .ce-summary-lbl{color:#003D80;font-size:13.5px}
.ce-summary-row.grand .ce-summary-val{font-size:15px;color:#003D80}

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

@media print{.top-bar,.action-bar,.sheet-tabs,.no-print,.addline-btn,.edit-mini-btn{display:none!important}.ce-wrap{margin:0;padding:0}.ce-sheet{box-shadow:none;border:1px solid #ccc;page-break-inside:avoid}.sheet-content{display:block!important}.editor-grid{grid-template-columns:1fr!important}.sum-panel{position:static!important;border-width:1.5px!important;box-shadow:none!important}.ecard{box-shadow:none!important;page-break-inside:avoid}}

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

/* ═══ Booking-mode editor layout (mode=booking) ═══ */
.bcrumb{font-size:12px;color:rgba(255,255,255,.75);margin-bottom:2px}
.bcrumb a{color:rgba(255,255,255,.85);text-decoration:none}
.bcrumb a:hover{text-decoration:underline}
.ce-editor-head{background:#fff;border:1px solid #d1d5db;border-radius:8px;padding:18px 22px;margin-bottom:16px;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;box-shadow:0 2px 8px rgba(0,0,0,.05)}
.ce-editor-title{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.ce-editor-title .num{font-family:'JetBrains Mono',monospace;font-size:20px;font-weight:800;color:#003D80}
.vbadge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.3px;background:#E5F0FF;color:#003D80;border:1px solid #A8D0FF}
.stbadge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.3px;text-transform:uppercase}
.stbadge.draft{background:#f1f5f9;color:#475569;border:1px solid #cbd5e1}
.stbadge.confirmed{background:#dcfce7;color:#166534;border:1px solid #86efac}
.stbadge.superseded{background:#f1f5f9;color:#94a3b8;border:1px solid #e2e8f0}
.rel-booking-row{display:flex;align-items:center;gap:8px;margin-top:8px;font-size:12.5px;color:var(--sub)}
.rel-booking-row a{color:#003D80;font-weight:700;text-decoration:none;font-family:'JetBrains Mono',monospace}
.rel-booking-row a:hover{text-decoration:underline}

.editor-grid{display:grid;grid-template-columns:1fr 340px;gap:16px;align-items:start}
@media (max-width:900px){.editor-grid{grid-template-columns:1fr}}

.ecard{background:#fff;border:1px solid #d1d5db;border-radius:8px;margin-bottom:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.05)}
.ecard-head{padding:12px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.ecard-head h3{font-size:13.5px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:8px}
.ecard-body{padding:16px 18px}
.addline-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 13px;background:#003D80;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif;transition:background .15s}
.addline-btn:hover{background:#004499}
.edit-mini-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;background:#fff;color:#003D80;border:1.5px solid #A8D0FF;border-radius:6px;font-size:11.5px;font-weight:700;cursor:pointer;font-family:'DM Sans',sans-serif}
.edit-mini-btn:hover{background:#f0f7ff}

.etbl{width:100%;border-collapse:collapse;font-size:12.5px}
.etbl th{background:#f8fafc;color:var(--sub);font-size:10px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;padding:7px 10px;text-align:left;border-bottom:1.5px solid var(--border)}
.etbl th.r,.etbl td.r{text-align:right}
.etbl td{padding:8px 10px;border-bottom:1px solid #f1f5f9}
.etbl tr:last-child td{border-bottom:none}
.etbl .amt{font-family:'JetBrains Mono',monospace;font-weight:600}

.sum-panel{background:#fff;border:2px solid #003D80;border-radius:8px;padding:18px 20px;margin-bottom:16px;box-shadow:0 2px 10px rgba(0,61,128,.1)}
.sum-row{display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #f1f5f9;font-size:12.5px}
.sum-row .lbl{color:var(--sub);font-weight:600}
.sum-row .val{font-family:'JetBrains Mono',monospace;font-weight:700;color:#0f172a}
.sum-row.grand{border-top:2px solid #003D80;border-bottom:none;margin-top:6px;padding-top:12px}
.sum-row.grand .lbl{font-size:14px;color:#003D80;font-weight:800}
.sum-row.grand .val{font-size:16px;color:#003D80}

.terms-box{background:#f8fafc;border:1px solid var(--border);border-radius:7px;padding:12px 14px;margin-bottom:16px;font-size:11.5px;color:var(--sub);line-height:1.6}
.client-preview-wrap{background:#f1f5f9;border:1px solid var(--border);border-radius:8px;padding:10px;margin-bottom:16px}
.client-preview-label{font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;display:flex;align-items:center;gap:6px}
{{-- The client-view document is a full desktop layout — embedding it at the sidebar's ~320px
     width with no scaling squished every 2-column grid and wrapped/truncated every cell (the
     bug reported). Render it at its natural width inside a fixed-size clipping viewport, then
     scale the whole thing down with a CSS transform so it's a proper shrunk thumbnail instead
     of a forced reflow — 960px natural width * 1/3 = 320px, matching the sidebar column. --}}
.client-preview-viewport{width:100%;height:560px;overflow:hidden;position:relative;border:1px solid var(--border);border-radius:6px;background:#fff}
.client-preview-frame{width:960px;height:2400px;border:none;transform:scale(.3333);transform-origin:top left;position:absolute;top:0;left:0}
</style>
</head>
<body>
@php
  // Single source for the fallback VAT-inclusive extraction used throughout this page (only
  // exercised before BookingCosting has computed a real $ceBd) — reads the configurable rate
  // instead of a hardcoded 12/112, matching BookingCosting::extractVat() and CartController.
  $vatRate = (float) config('filmspec.vat_rate');
  // ?tab_embed=1 is how Booking Detail's own Cost Estimate tab loads this page — unlike
  // ?embed=1 (the Client Preview thumbnail, which hides this whole bar), the tab still wants
  // Export reachable, just none of the navigation/toggle chrome that duplicates what the
  // surrounding Booking Detail page already has (breadcrumb, logo, Internal/For Client toggle,
  // Print, and a Back to Booking button pointing at the very page this is already embedded in).
  $tabEmbed = request()->boolean('tab_embed');
@endphp

{{-- ?embed=1 is how the Client Preview thumbnail (below) loads this same page — its own
     toolbar (Internal/For Client toggle, Print, Export, Back to Booking) has no room in a
     ~320px sidebar and duplicates buttons the surrounding page already has, so it's skipped
     entirely there. The thumbnail's "View" link opens the full page (no embed param) when a
     real, unscaled look is actually wanted. --}}
@unless (request()->boolean('embed'))
<div class="top-bar no-print">
  <div style="display:flex;flex-direction:column;gap:3px">
    @unless ($tabEmbed)
    @if ($mode === 'booking')
    <div class="bcrumb">
      @if ($role === 'client')
      <a href="{{ route('home') }}">My Bookings</a> &rsaquo;
      <a href="{{ route('client-booking-detail', $bid) }}">{{ $booking->booking_reference }}</a> &rsaquo;
      @else
      <a href="{{ route('bookings') }}">Bookings</a> &rsaquo;
      <a href="{{ route('booking-detail', $bid) }}">{{ $booking->booking_reference }}</a> &rsaquo;
      @endif
      Cost Estimate
    </div>
    @endif
    <div class="top-bar-logo" style="display:flex;align-items:center;gap:10px">
      <img src="{{ asset('assets/images/logo.png') }}" alt="FilmSpec" style="height:26px;object-fit:contain;background:rgba(255,255,255,.92);padding:2px 8px;border-radius:4px">
      <span>— Cost Estimate</span>
    </div>
    @else
    <div class="top-bar-logo" style="display:flex;align-items:center;gap:10px">
      <span>Cost Estimate</span>
    </div>
    @endunless
  </div>
  <div class="top-bar-actions">
    @unless ($tabEmbed)
    @if ($mode === 'booking' && $role !== 'client')
    <span style="display:inline-flex;border:1px solid rgba(255,255,255,.35);border-radius:6px;overflow:hidden;margin-right:6px">
      <a href="?booking_id={{ $bid }}{{ $ceId ? '&ce_id='.$ceId : '' }}&view=internal"
         style="padding:7px 12px;font-size:12px;font-weight:600;text-decoration:none;{{ $isClientView ? 'color:rgba(255,255,255,.75)' : 'background:#fff;color:#003D80' }}">INTERNAL</a>
      <a href="?booking_id={{ $bid }}{{ $ceId ? '&ce_id='.$ceId : '' }}&view=client"
         style="padding:7px 12px;font-size:12px;font-weight:600;text-decoration:none;{{ $isClientView ? 'background:#c0392b;color:#fff' : 'color:rgba(255,255,255,.75)' }}">FOR CLIENT</a>
    </span>
    @endif
    <button class="tbtn" onclick="window.print()">Print</button>
    @endunless
    @if ($mode === 'booking')
    {{-- Same Export ▾ pattern every other staff page uses (partials/export-dropdown.blade.php),
         hand-built here with inline SVGs instead of data-feather — this standalone document page
         never loads the feather-icons script the shared partial relies on (see Print above,
         which does the same for the same reason). Links straight to this URL + &export=fmt,
         handled by CePreviewController::exportDocument(). --}}
    @php
      $ceExportBase = collect(request()->query())->except('export')->all();
      $ceExportIcons = [
        'csv' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
        'xlsx' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 8l8 8M16 8l-8 8"/>',
        'pdf' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/>',
      ];
      $ceExportLabels = ['csv' => 'CSV (.csv)', 'xlsx' => 'Excel (.xlsx)', 'pdf' => 'PDF (.pdf)'];
    @endphp
    <div class="ce-export-wrap no-print">
      <button type="button" class="tbtn" onclick="ceToggleExportMenu()">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export
      </button>
      <div class="ce-export-menu" id="ceExportMenu">
        @foreach (['csv', 'xlsx', 'pdf'] as $fmt)
        <a href="{{ request()->url() . '?' . http_build_query(array_merge($ceExportBase, ['export' => $fmt])) }}" class="ce-export-opt">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $ceExportIcons[$fmt] !!}</svg>{{ $ceExportLabels[$fmt] }}
        </a>
        @endforeach
      </div>
    </div>
    @else
    <button onclick="window.print()" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:#c0392b;color:white;border:none;border-radius:6px;font-size:13px;font-family:'DM Sans',sans-serif;font-weight:500;cursor:pointer">
      <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Save as PDF
    </button>
    @endif
    @unless ($tabEmbed)
    @if ($mode === 'booking' && $role === 'client')
    <a href="{{ route('client-booking-detail', $bid) }}"><button class="tbtn">&larr; Booking</button></a>
    @elseif ($mode === 'booking')
    <a href="{{ route('booking-detail', $bid) }}"><button class="tbtn">&larr; Booking</button></a>
    @else
    <a href="{{ route('home') }}"><button class="tbtn">&larr; Back to Cart</button></a>
    @endif
    @endunless
  </div>
</div>
@endunless

<div class="ce-wrap {{ $mode === 'cart' ? 'cart-wide' : '' }}">

@if ($mode === 'booking' && $isClientView)
<div style="background:#fdecea;border:1px solid #f5b7b1;color:#c0392b;padding:10px 16px;border-radius:8px;margin-bottom:14px;font-weight:700;letter-spacing:1px;font-size:13px;text-align:center">
  FOR CLIENT ONLY
</div>
@endif

{{-- The Excel/PDF-matching official document (.ce-sheet tabs below) was already written to
     support both modes' data (see the safe cart-mode defaults passed in for booking mode), but
     was only ever wired to cart mode — booking-mode client view fell through to the internal
     editor cards instead. Client view (any mode) now gets the official document; the internal
     editor cards stay exactly as they were, for staff building the quote. --}}
@if ($mode === 'cart' || ($mode === 'booking' && $isClientView))
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

<!-- Sheet Tabs — mirrors the company's actual 3-sheet Excel workbook (Equipment / Crew TF / Summary) -->
<div class="sheet-tabs no-print">
  <div class="stab on" onclick="showSheet('equipment',this)">Equipment (E)</div>
  <div class="stab" onclick="showSheet('crew',this)">Crew TF (M)</div>
  <div class="stab" onclick="showSheet('summary',this)">Summary (S)</div>
</div>

<!-- ═══ SHEET: EQUIPMENT ═══ -->
<div class="ce-sheet sheet-content on" id="sheet-equipment">

  <div class="ce-header">
    <div class="ce-header-left">
      <img src="{{ asset('assets/images/logo.png') }}" alt="FilmSpec" style="height:46px;max-width:170px;object-fit:contain;display:block;margin:0 auto 4px">
      <div class="addr">Gate 1, 9110 La Campana St. cor Trabajo St., Olympia, Makati City<br>Cel No. +63927 5056461&nbsp;&nbsp;&nbsp;Tel No. : +632 70004683</div>
    </div>
    <div class="ce-header-right">
      <div class="ce-type">COST ESTIMATE</div>
      <div class="ce-num">CE# {{ $ceNumber }}(E)</div>
      <div class="ce-meta">
        <span class="lbl">Date:</span><span class="val">{{ date('F d, Y') }}</span>
        <span class="lbl">DUE Date:</span><span class="val">{{ date('F d, Y', strtotime('+30 days')) }}</span>
        <div class="fsfront">FS Front</div>
        <span class="lbl">PREPARED BY:</span><span class="val">{{ $preparedByName ?: '—' }}</span>
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

  <div class="ce-section-hdr">DETAILED COST BREAKDOWN</div>
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
        <tr class="catgroup">
          <td colspan="5">
            {{ strtoupper($catName) }} (FS):
            <span style="float:right;font-weight:400;font-style:normal;color:#000;font-size:10.5px">{{ count($catLines) }} item{{ count($catLines) === 1 ? '' : 's' }}</span>
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

  <div class="ce-footer" style="grid-template-columns:1fr 1fr;padding-bottom:0">
    <div style="font-size:11px;font-style:italic;font-weight:700">Prepared by: &nbsp;{{ $preparedByName ?: '—' }}</div>
    <div style="font-size:11px;font-style:italic;font-weight:700">Noted by: <span class="ce-sig" style="display:inline-block;min-width:160px;margin-top:0;border-top:none;padding-top:0"></span></div>
  </div>
  <div style="padding:18px 16px 16px;font-family:Arial,Helvetica,sans-serif">
    <div style="font-size:11px;font-style:italic;font-weight:700;margin-bottom:2px">Received &amp; Conformed by:</div>
    <div class="ce-sig" style="max-width:260px;margin-top:26px;text-align:center">(Signature over printed name)</div>
    <div style="font-size:11px;font-style:italic;font-weight:700;margin-top:16px">Date Received: <span class="ce-sig" style="display:inline-block;min-width:160px;margin-top:0;border-top:none;padding-top:0"></span></div>
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
    <div class="ce-header-left">
      <img src="{{ asset('assets/images/logo.png') }}" alt="FilmSpec" style="height:46px;max-width:170px;object-fit:contain;display:block;margin:0 auto 4px">
      <div class="addr">Gate 1, 9110 La Campana St. cor Trabajo St., Olympia, Makati City<br>Cel No. +63927 5056461&nbsp;&nbsp;&nbsp;Tel No. : +632 70004683</div>
    </div>
    <div class="ce-header-right">
      <div class="ce-type">COST ESTIMATE</div>
      <div class="ce-num">CE# {{ $ceNumber }}(M)</div>
      <div class="ce-meta">
        <span class="lbl">Date:</span><span class="val">{{ date('F d, Y') }}</span>
        <span class="lbl">DUE Date:</span><span class="val">{{ date('F d, Y', strtotime('+30 days')) }}</span>
        <div class="fsfront">FS Front</div>
        <span class="lbl">PREPARED BY:</span><span class="val">{{ $preparedByName ?: '—' }}</span>
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

  <div class="ce-section-hdr">DETAILED COST BREAKDOWN</div>
  <div style="padding:6px 14px 0;font-size:11px;font-weight:700;font-style:italic;color:#000;font-family:Arial,Helvetica,sans-serif">
    CREW TF :
    <div style="font-size:10.5px;font-weight:700;font-style:italic;margin-top:2px">
    @if ($mode === 'cart')
      {{ count($estimatedCrewLines) }} - Crew (Estimated based on equipment requirements · Actual crew assigned after booking · OT after 12Hrs)
    @else
      {{ count($crewLines) }} - Crew (OT after 12Hrs &amp; Double after 22Hrs)
    @endif
    </div>
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
  <div class="ce-footer" style="grid-template-columns:1fr 1fr;padding-bottom:0">
    <div style="font-size:11px;font-style:italic;font-weight:700">Prepared by: &nbsp;{{ $preparedByName ?: '—' }}</div>
    <div style="font-size:11px;font-style:italic;font-weight:700">Noted by: <span class="ce-sig" style="display:inline-block;min-width:160px;margin-top:0;border-top:none;padding-top:0"></span></div>
  </div>
  <div style="padding:18px 16px 16px;font-family:Arial,Helvetica,sans-serif">
    <div style="font-size:11px;font-style:italic;font-weight:700;margin-bottom:2px">Received &amp; Conformed by:</div>
    <div class="ce-sig" style="max-width:260px;margin-top:26px;text-align:center">(Signature over printed name)</div>
    <div style="font-size:11px;font-style:italic;font-weight:700;margin-top:16px">Date Received: <span class="ce-sig" style="display:inline-block;min-width:160px;margin-top:0;border-top:none;padding-top:0"></span></div>
  </div>
</div>

<!-- ═══ SHEET: SUMMARY ═══ -->
<div class="ce-sheet sheet-content" id="sheet-summary">

  <div class="ce-header">
    <div class="ce-header-left">
      <img src="{{ asset('assets/images/logo.png') }}" alt="FilmSpec" style="height:46px;max-width:170px;object-fit:contain;display:block;margin:0 auto 4px">
      <div class="addr">Gate 1, 9110 La Campana St. cor Trabajo St., Olympia, Makati City<br>Cel No. +63927 5056461&nbsp;&nbsp;&nbsp;Tel No. : +632 70004683</div>
    </div>
    <div class="ce-header-right">
      <div class="ce-type">COST ESTIMATE</div>
      <div class="ce-num">CE# {{ $ceNumber }}(S)</div>
      <div class="ce-meta">
        <span class="lbl">Date:</span><span class="val">{{ date('F d, Y') }}</span>
        <span class="lbl">DUE Date:</span><span class="val">{{ date('F d, Y', strtotime('+30 days')) }}</span>
        <div class="fsfront">FS Front</div>
        <span class="lbl">PREPARED BY:</span><span class="val">{{ $preparedByName ?: '—' }}</span>
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

  <div class="ce-section-hdr">SUMMARY</div>

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
    <div class="ce-summary-row">
      <div class="ce-summary-lbl">Package Deal{{ $ceVatExempt ? ' (VAT-exempt)' : '' }}</div>
      <div class="ce-summary-val" style="font-size:11px">{{ $pricingLabels[$cePricingMode] ?? '' }}</div>
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
    <div class="ce-summary-row">
      <div class="ce-summary-lbl" style="font-weight:700">
        Net Amount (ex-VAT)
        @if ($mode === 'cart')
        <span style="font-size:10px;font-weight:400">(equipment only)</span>
        @endif
      </div>
      <div class="ce-summary-val" id="sum-subtotal">₱{{ number_format($subtotal, 2) }}</div>
    </div>
    <div class="ce-summary-row">
      <div class="ce-summary-lbl">{{ ($cePricingMode !== 'no_discount' && $cePricingInput !== null) ? 'VAT (' . round($vatRate * 100) . '% added)' : 'VAT (' . round($vatRate * 100) . '/' . (100 + round($vatRate * 100)) . ') — Included' }}</div>
      <div class="ce-summary-val" id="sum-vat">₱{{ number_format($vat, 2) }}</div>
    </div>
    <div class="ce-summary-row grand">
      <div class="ce-summary-lbl" style="font-size:14px">{{ $mode === 'cart' ? 'EQUIPMENT TOTAL (VAT Incl.)' : 'GRAND TOTAL (VAT Incl.)' }}</div>
      <div class="ce-summary-val" style="font-size:15px" id="sum-grand">₱{{ number_format($grand, 2) }}</div>
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

  <div class="ce-footer" style="grid-template-columns:1fr 1fr;padding-bottom:0">
    <div style="font-size:11px;font-style:italic;font-weight:700">Prepared by: &nbsp;{{ $preparedByName ?: '—' }}</div>
    <div style="font-size:11px;font-style:italic;font-weight:700">Noted by: <span class="ce-sig" style="display:inline-block;min-width:160px;margin-top:0;border-top:none;padding-top:0"></span></div>
  </div>
  <div style="padding:18px 16px 16px;font-family:Arial,Helvetica,sans-serif">
    <div style="font-size:11px;font-style:italic;font-weight:700;margin-bottom:2px">Received &amp; Conformed by:</div>
    <div class="ce-sig" style="max-width:260px;margin-top:26px;text-align:center">(Signature over printed name)</div>
    <div style="font-size:11px;font-style:italic;font-weight:700;margin-top:16px">Date Received: <span class="ce-sig" style="display:inline-block;min-width:160px;margin-top:0;border-top:none;padding-top:0"></span></div>
  </div>
</div>
@else
{{-- ═══════════════════ BOOKING-MODE CE EDITOR (matches the reference mockup) ═══════════════════ --}}
@php $flash = session('bd_flash'); @endphp
@if ($flash)
<div class="no-print" style="background:{{ $flash['type'] === 'success' ? '#f0fdf4' : '#fef2f2' }};border:1px solid {{ $flash['type'] === 'success' ? '#bbf7d0' : '#fca5a5' }};color:{{ $flash['type'] === 'success' ? '#166534' : '#b91c1c' }};padding:10px 16px;border-radius:8px;margin-bottom:14px;font-size:13px;font-weight:600">
  {!! $flash['text'] !!}
</div>
@endif

<div class="ce-editor-head">
  <div>
    <div class="ce-editor-title">
      <span class="num">{{ $ceNumber }}</span>
      <span class="vbadge">Version {{ $ceVersion }}</span>
      @if ($isSuperseded)
      <span class="stbadge superseded">Superseded</span>
      @elseif ($ceStatus === 'confirmed')
      <span class="stbadge confirmed">Confirmed</span>
      @else
      <span class="stbadge draft">Draft</span>
      @endif
    </div>
    <div class="rel-booking-row">
      Related Booking: <a href="{{ $role === 'client' ? route('client-booking-detail', $bid) : route('booking-detail', $bid) }}">{{ $booking->booking_reference }}</a>
      <span class="stbadge draft" style="background:#eff6ff;color:#1e40af;border-color:#bfdbfe">{{ ucfirst($booking->booking_status) }}</span>
      <span style="color:var(--muted)">·</span>
      Payment: <span style="font-weight:700;color:#0f172a">{{ ucfirst($booking->payment_status ?? 'unpaid') }}</span>
    </div>
  </div>
</div>

<div class="editor-grid">
  <div>
    <!-- Client & Project Information -->
    <div class="ecard">
      <div class="ecard-head">
        <h3><i data-feather="user" style="width:14px;height:14px"></i> Client &amp; Project Information</h3>
        @if ($canManage)
        <button type="button" class="edit-mini-btn" onclick="openDocMo('moEditInfo')"><i data-feather="edit-2" style="width:11px;height:11px"></i> Edit</button>
        @endif
      </div>
      <div class="ecard-body" style="padding:0">
        <div class="ce-info ce-info-soft">
          @foreach ($infoRows as $l => $v)
          <div class="ce-info-row">
            <div class="ce-info-lbl">{{ $l }}:</div>
            <div class="ce-info-val">{{ $v }}</div>
          </div>
          @endforeach
        </div>
      </div>
    </div>

    <!-- Cost Breakdown -->
    <div class="ecard">
      <div class="ecard-head"><h3><i data-feather="camera" style="width:14px;height:14px"></i> Equipment Rental</h3>
        @if ($canManage)<button type="button" class="addline-btn" onclick="ceOpenAddEquip()"><i data-feather="plus" style="width:12px;height:12px"></i> Add Equipment</button>@endif
      </div>
      <div class="ecard-body">
        <div style="overflow-x:auto">
        <table class="etbl">
          <thead><tr><th style="width:44px">Qty</th><th>Item</th><th class="r" style="width:70px">Days</th><th class="r" style="width:100px">Rate / Day</th><th class="r" style="width:110px">Amount</th></tr></thead>
          <tbody>
          @php $anyEquip = false; @endphp
          @foreach ($equipGroups as $catLines)
          @foreach ($catLines as $eq)
          @php $anyEquip = true; $rowAmt = (float) $eq->quantity * (float) $eq->daily_rate; @endphp
          <tr>
            <td>{{ $eq->quantity }}</td>
            <td>{{ $eq->equipment_name }}{{ $eq->brand ? ' ('.$eq->brand.')' : '' }}</td>
            <td class="r">{{ $eq->days }}</td>
            <td class="r amt">₱{{ number_format($eq->daily_rate, 2) }}</td>
            <td class="r amt">₱{{ number_format($rowAmt, 2) }}</td>
          </tr>
          @endforeach
          @endforeach
          @if (! $anyEquip)
          <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:14px;font-style:italic">No equipment items</td></tr>
          @endif
          </tbody>
        </table>
        </div>
      </div>
    </div>

    <div class="ecard">
      <div class="ecard-head"><h3><i data-feather="users" style="width:14px;height:14px"></i> Crew Talent Fee</h3>
        @if ($canManage)<button type="button" class="addline-btn" onclick="ceOpenAddCrew()"><i data-feather="plus" style="width:12px;height:12px"></i> Add Crew</button>@endif
      </div>
      <div class="ecard-body">
        <div style="overflow-x:auto">
        <table class="etbl">
          <thead><tr><th>Position / Name</th><th class="r" style="width:70px">Days</th><th class="r" style="width:100px">Rate / 12H</th><th class="r" style="width:110px">Amount</th></tr></thead>
          <tbody>
          @forelse ($crewLines as $cl)
          @php $clAmt = (float) $cl->rate_used * (float) $cl->hours_worked; @endphp
          <tr>
            <td><div style="font-weight:600">{{ $cl->position_name ?? 'Crew' }}</div><div style="font-size:11px;color:var(--muted)">{{ $cl->crew_name }}</div></td>
            <td class="r">{{ $cl->hours_worked }}</td>
            <td class="r amt">₱{{ number_format($cl->rate_used, 2) }}</td>
            <td class="r amt">₱{{ number_format($clAmt, 2) }}</td>
          </tr>
          @empty
          <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:14px;font-style:italic">No crew assigned</td></tr>
          @endforelse
          </tbody>
        </table>
        </div>
      </div>
    </div>

    <div class="ecard">
      <div class="ecard-head"><h3><i data-feather="package" style="width:14px;height:14px"></i> Accessories &amp; Add-ons</h3>
        @if ($canManage)<button type="button" class="addline-btn" onclick="openDocMo('moAddAcc')"><i data-feather="plus" style="width:12px;height:12px"></i> Add Accessories</button>@endif
      </div>
      <div class="ecard-body">
        <div style="overflow-x:auto">
        <table class="etbl">
          <thead><tr><th style="width:44px">Qty</th><th>Item</th><th class="r" style="width:70px">Days</th><th class="r" style="width:100px">Rate / Day</th><th class="r" style="width:110px">Amount</th></tr></thead>
          <tbody>
          @forelse ($accLines as $ac)
          <tr>
            <td>{{ $ac->quantity }}</td>
            <td>{{ $ac->accessory_name }}{{ $ac->is_included ? ' (Included)' : '' }}</td>
            <td class="r">{{ $ac->days }}</td>
            <td class="r amt">₱{{ number_format($ac->daily_rate, 2) }}</td>
            <td class="r amt">₱{{ number_format($ac->subtotal, 2) }}</td>
          </tr>
          @empty
          <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:14px;font-style:italic">No accessories added</td></tr>
          @endforelse
          </tbody>
        </table>
        </div>
      </div>
    </div>

    <div class="ecard">
      <div class="ecard-head"><h3><i data-feather="truck" style="width:14px;height:14px"></i> Transportation</h3>
        @if ($canManage)<button type="button" class="addline-btn" onclick="ceOpenTransport()"><i data-feather="edit-2" style="width:12px;height:12px"></i> {{ $transCost > 0 ? 'Edit' : 'Add' }} Transport</button>@endif
      </div>
      <div class="ecard-body" style="font-size:12.5px">
        @if ($transCost > 0 || $transZone)
        <div style="display:flex;justify-content:space-between;align-items:center">
          <span>{{ $zoneLabels[$transZone] ?? 'Zone not set' }} @if ($transMult > 1) <span style="color:var(--muted)">· {{ $transMult }}× multiplier</span>@endif</span>
          <span class="amt" style="font-family:'JetBrains Mono',monospace;font-weight:700;color:#003D80">₱{{ number_format($transCost, 2) }}</span>
        </div>
        @else
        <span style="color:var(--muted);font-style:italic">No transportation assigned</span>
        @endif
      </div>
    </div>

    <div class="ecard">
      <div class="ecard-head"><h3><i data-feather="percent" style="width:14px;height:14px"></i> Discount</h3>
        @if ($canDiscount)<button type="button" class="addline-btn" onclick="openDocMo('moDiscount')"><i data-feather="plus" style="width:12px;height:12px"></i> Add Discount</button>@endif
      </div>
      <div class="ecard-body" style="font-size:12.5px">
        @if ($cePricingMode !== 'no_discount' && $cePricingInput !== null)
        @php
          $pricingLabels = [
            'package_price' => 'Package Price: ₱' . number_format($cePricingInput, 2) . ' (all-in, incl. crew)',
            'discount_percent' => number_format($cePricingInput, 2) . '% discount off itemized total',
            'discount_flat' => '₱' . number_format($cePricingInput, 2) . ' flat discount off itemized total',
          ];
        @endphp
        <span style="color:#1e40af;font-weight:600">{{ $pricingLabels[$cePricingMode] ?? '' }}</span>
        @else
        <span style="color:var(--muted);font-style:italic">No discount applied</span>
        @endif
      </div>
    </div>

    <div class="terms-box">
      <strong style="color:#0f172a">Payment Terms:</strong> Regular Clients — 90-day or 6-month credit terms. New Customers — 50% downpayment required.<br>
      *** All Equipment Returned will be charged as Regular after Pull Out *** &nbsp; For more inquiries call FILM SPEC Cellphone No. 0927 5056461.
    </div>

    <div class="ce-footer" style="border:1px solid var(--border);border-radius:8px;background:#fff;margin-bottom:16px">
      <div>
        <div style="font-size:10.5px;color:var(--muted);margin-bottom:4px;display:flex;align-items:center;gap:6px">
          Prepared by:
          @if ($canManage)
          <button type="button" class="edit-mini-btn" style="padding:1px 8px;font-size:10px" onclick="openDocMo('moEditInfo')"><i data-feather="edit-2" style="width:9px;height:9px"></i></button>
          @endif
        </div>
        <div class="ce-sig">{{ $preparedByName ?: '—' }}</div>
      </div>
      <div>
        <div style="font-size:10.5px;color:var(--muted);margin-bottom:4px">Received &amp; Conformed by:</div>
        <div class="ce-sig">(Signature over printed name)</div>
      </div>
    </div>
  </div>

  <div>
    <div class="sum-panel">
      <div class="sum-row"><span class="lbl">Equipment Subtotal</span><span class="val">₱{{ number_format($equipTotal, 2) }}</span></div>
      <div class="sum-row"><span class="lbl">Crew Subtotal</span><span class="val">₱{{ number_format($crewTotal, 2) }}</span></div>
      <div class="sum-row"><span class="lbl">Accessories Subtotal</span><span class="val">₱{{ number_format($accTotal, 2) }}</span></div>
      <div class="sum-row"><span class="lbl">Transportation Subtotal</span><span class="val">₱{{ number_format($transCost, 2) }}</span></div>
      @if ($cePricingMode !== 'no_discount' && $cePricingInput !== null)
      <div class="sum-row"><span class="lbl">Discount</span><span class="val">{{ $pricingLabels[$cePricingMode] ?? '—' }}</span></div>
      @endif
      <div class="sum-row" style="margin-top:6px;padding-top:10px;border-top:1px solid var(--border)"><span class="lbl">Net Amount (ex-VAT)</span><span class="val">₱{{ number_format($subtotal, 2) }}</span></div>
      <div class="sum-row"><span class="lbl">VAT ({{ round((float) config('filmspec.vat_rate') * 100) }}%)</span><span class="val">₱{{ number_format($vat, 2) }}</span></div>
      <div class="sum-row grand"><span class="lbl">GRAND TOTAL (VAT Incl.)</span><span class="val">₱{{ number_format($grand, 2) }}</span></div>
      <div style="margin-top:10px;font-size:10.5px;color:var(--muted);font-style:italic">{{ \App\Support\Money::amtWords($grand) }} PESOS ONLY</div>
    </div>

    @if (! $isClientView)
    <div class="client-preview-wrap no-print">
      <div class="client-preview-label" style="justify-content:space-between">
        <span style="display:flex;align-items:center;gap:6px">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          Client Preview — what the client sees
        </span>
        <a href="{{ route('ce-preview', ['booking_id' => $bid, 'ce_id' => $ceId, 'view' => 'client']) }}" target="_blank" style="font-size:10.5px;font-weight:700;color:var(--accent);text-decoration:none;text-transform:none;letter-spacing:0">View &rarr;</a>
      </div>
      <div class="client-preview-viewport">
        <iframe class="client-preview-frame" src="{{ route('ce-preview', ['booking_id' => $bid, 'ce_id' => $ceId, 'view' => 'client', 'embed' => 1]) }}" loading="lazy"></iframe>
      </div>
    </div>
    @endif
  </div>
</div>

@if ($canManage)
<!-- EDIT CLIENT & PROJECT INFO -->
<div class="doc-mo" id="moEditInfo"><div class="doc-box" style="width:520px">
  <div class="doc-head"><h3>Edit Client &amp; Project Info</h3><button class="doc-close" onclick="closeDocMo('moEditInfo')">&times;</button></div>
  <form method="POST" action="{{ $actionUrl }}">
    @csrf
    <input type="hidden" name="action" value="update_project_details">
    <input type="hidden" name="return_to" value="ce_preview"><input type="hidden" name="return_ce_id" value="{{ $ceId }}"><input type="hidden" name="return_view" value="{{ $isClientView ? 'client' : 'internal' }}">
    <div class="doc-body" style="display:flex;flex-direction:column;gap:10px">
      <div><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">CE Type</label>
        <select name="ce_type" style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px">
          <option value="fs_front" {{ ($booking->ce_type ?? 'fs_front') === 'fs_front' ? 'selected' : '' }}>FS Front</option>
          <option value="client_direct" {{ ($booking->ce_type ?? '') === 'client_direct' ? 'selected' : '' }}>Client Direct</option>
          <option value="partner_front" {{ ($booking->ce_type ?? '') === 'partner_front' ? 'selected' : '' }}>Partner Front</option>
        </select>
      </div>
      <div><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Due Date</label>
        <input type="date" name="ce_due_date" value="{{ $booking->ce_due_date ?? '' }}" style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px"></div>
      <div><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Director / DOP</label>
        <input type="text" name="ce_director_dop" value="{{ $booking->ce_director_dop ?? '' }}" style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px"></div>
      <div><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Contact Person</label>
        <input type="text" name="ce_contact_person" value="{{ $booking->ce_contact_person ?? '' }}" style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px"></div>
      <div><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Contact Number</label>
        <input type="text" name="ce_contact_number" value="{{ $booking->ce_contact_number ?? '' }}" style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px"></div>
      <div><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Contact Email</label>
        <input type="email" name="ce_contact_email" value="{{ $booking->ce_contact_email ?? '' }}" style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px"></div>
      <div><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Prepared By</label>
        <input type="text" name="ce_prepared_by" value="{{ $booking->ce_prepared_by ?? '' }}" placeholder="Staff name shown on the CE document" style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px"></div>
    </div>
    <div class="doc-foot"><button type="button" class="tbtn" style="background:#f1f5f9;color:#334155" onclick="closeDocMo('moEditInfo')">Cancel</button><button type="submit" class="doc-accept-btn">Save Changes</button></div>
  </form>
</div></div>

<!-- ADD EQUIPMENT -->
<div class="doc-mo" id="moAddEquip"><div class="doc-box" style="width:520px">
  <div class="doc-head"><h3>Add Equipment</h3><button class="doc-close" onclick="closeDocMo('moAddEquip')">&times;</button></div>
  <form method="POST" action="{{ $actionUrl }}">
    @csrf
    <input type="hidden" name="action" value="add_equipment">
    <input type="hidden" name="return_to" value="ce_preview"><input type="hidden" name="return_ce_id" value="{{ $ceId }}"><input type="hidden" name="return_view" value="{{ $isClientView ? 'client' : 'internal' }}">
    <input type="hidden" name="equipment_id" id="ceEquipId">
    <div class="doc-body" style="display:flex;flex-direction:column;gap:10px">
      <div style="position:relative">
        <label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Equipment *</label>
        <input type="text" id="ceEquipSearch" autocomplete="off" oninput="ceFilterEquip(this.value)" placeholder="Search by name, category, brand…"
               style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px">
        <div id="ceEquipList" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1.5px solid #e2e8f0;border-radius:6px;max-height:220px;overflow-y:auto;box-shadow:0 8px 24px rgba(15,23,42,.12)"></div>
      </div>
      <div style="display:flex;gap:8px">
        <div style="flex:1"><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Qty</label><input type="number" name="quantity" value="1" min="1" required style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px"></div>
        <div style="flex:1"><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Days</label><input type="number" name="days" value="1" min="1" required style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px"></div>
        <div style="flex:1"><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Rate/Day</label><input type="number" name="daily_rate" id="ceEquipRate" step="0.01" min="0" required style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px"></div>
      </div>
      <div><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Notes</label><input type="text" name="notes" placeholder="Optional" style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px"></div>
    </div>
    <div class="doc-foot"><button type="button" class="tbtn" style="background:#f1f5f9;color:#334155" onclick="closeDocMo('moAddEquip')">Cancel</button><button type="submit" class="doc-accept-btn">Add Line</button></div>
  </form>
</div></div>

<!-- ADD CREW -->
<div class="doc-mo" id="moAddCrew"><div class="doc-box" style="width:520px">
  <div class="doc-head"><h3>Add Crew Member</h3><button class="doc-close" onclick="closeDocMo('moAddCrew')">&times;</button></div>
  <form method="POST" action="{{ $actionUrl }}">
    @csrf
    <input type="hidden" name="action" value="batch_add_crew">
    <input type="hidden" name="return_to" value="ce_preview"><input type="hidden" name="return_ce_id" value="{{ $ceId }}"><input type="hidden" name="return_view" value="{{ $isClientView ? 'client' : 'internal' }}">
    <input type="hidden" name="bc_crew_id[]" id="ceCrewId">
    <input type="hidden" name="bc_notes[]" value="">
    <div class="doc-body" style="display:flex;flex-direction:column;gap:10px">
      <div style="position:relative">
        <label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Crew Member *</label>
        <input type="text" id="ceCrewSearch" autocomplete="off" oninput="ceFilterCrew(this.value)" placeholder="Search by name…"
               style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px">
        <div id="ceCrewList" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:20;background:#fff;border:1.5px solid #e2e8f0;border-radius:6px;max-height:220px;overflow-y:auto;box-shadow:0 8px 24px rgba(15,23,42,.12)"></div>
      </div>
      <div style="display:flex;gap:8px">
        <div style="flex:1"><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Position</label>
          <select name="bc_pos_id[]" style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px">
            <option value="0">— General —</option>
            @foreach ($positions as $pos)<option value="{{ $pos->position_id }}">{{ $pos->position_name }}</option>@endforeach
          </select>
        </div>
        <div style="flex:1"><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Rate/12hr</label><input type="number" name="bc_rate[]" id="ceCrewRate" step="0.01" min="0" style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px"></div>
      </div>
      <div><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Link to Equipment</label>
        <select name="bc_eq_link[]" style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px">
          <option value="0">— Not equipment-specific —</option>
          @foreach ($equipGroups as $catLines)@foreach ($catLines as $eq)<option value="{{ $eq->equipment_id ?? 0 }}">{{ $eq->equipment_name }}</option>@endforeach @endforeach
        </select>
      </div>
    </div>
    <div class="doc-foot"><button type="button" class="tbtn" style="background:#f1f5f9;color:#334155" onclick="closeDocMo('moAddCrew')">Cancel</button><button type="submit" class="doc-accept-btn">Add Crew</button></div>
  </form>
</div></div>

<!-- ADD ACCESSORY -->
<div class="doc-mo" id="moAddAcc"><div class="doc-box" style="width:480px">
  <div class="doc-head"><h3>Add Accessories</h3><button class="doc-close" onclick="closeDocMo('moAddAcc')">&times;</button></div>
  <form method="POST" action="{{ $actionUrl }}">
    @csrf
    <input type="hidden" name="action" value="add_booking_accessory">
    <input type="hidden" name="return_to" value="ce_preview"><input type="hidden" name="return_ce_id" value="{{ $ceId }}"><input type="hidden" name="return_view" value="{{ $isClientView ? 'client' : 'internal' }}">
    <div class="doc-body" style="display:flex;flex-direction:column;gap:10px">
      <div><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Accessory *</label>
        <select name="accessory_id" required style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px">
          <option value="">— Select accessory —</option>
          @foreach ($allAccessoriesList as $acc)
          @php $avail = max(0, (int) $acc->quantity - (int) $acc->qty_in_use); @endphp
          <option value="{{ $acc->accessory_id }}" {{ $avail < 1 ? 'disabled' : '' }}>
            {{ $acc->accessory_name }} {{ $acc->is_included ? '(Included)' : '(₱'.number_format($acc->daily_rate,2).'/day)' }} {{ $avail < 1 ? '— Out of stock' : "— $avail avail." }}
          </option>
          @endforeach
        </select>
      </div>
      <div style="display:flex;gap:8px">
        <div style="flex:1"><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Qty</label><input type="number" name="quantity" value="1" min="1" style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px"></div>
        <div style="flex:1"><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Days</label><input type="number" name="days" value="{{ $accDays }}" min="1" style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px"></div>
      </div>
      <div><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Notes</label><input type="text" name="notes" placeholder="Optional" style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px"></div>
    </div>
    <div class="doc-foot"><button type="button" class="tbtn" style="background:#f1f5f9;color:#334155" onclick="closeDocMo('moAddAcc')">Cancel</button><button type="submit" class="doc-accept-btn">Add</button></div>
  </form>
</div></div>

<!-- ASSIGN/EDIT TRANSPORT -->
<div class="doc-mo" id="moTransport"><div class="doc-box" style="width:480px">
  <div class="doc-head"><h3>{{ $transCost > 0 ? 'Edit' : 'Assign' }} Transport</h3><button class="doc-close" onclick="closeDocMo('moTransport')">&times;</button></div>
  <form method="POST" action="{{ $actionUrl }}">
    @csrf
    <input type="hidden" name="action" value="assign_transport">
    <input type="hidden" name="return_to" value="ce_preview"><input type="hidden" name="return_ce_id" value="{{ $ceId }}"><input type="hidden" name="return_view" value="{{ $isClientView ? 'client' : 'internal' }}">
    <div class="doc-body" style="display:flex;flex-direction:column;gap:10px">
      <div><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Zone</label>
        <select name="location_zone" style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px">
          <option value="">— Not set —</option>
          @foreach ($zoneLabels as $zk => $zl)<option value="{{ $zk }}" {{ $transZone === $zk ? 'selected' : '' }}>{{ $zl }}</option>@endforeach
        </select>
      </div>
      <div><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Vehicle</label>
        <select name="vehicle_rate_id" style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px">
          <option value="">— None —</option>
          @foreach ($vehicleRates as $vr)<option value="{{ $vr->vehicle_id }}">{{ $vr->label }} (₱{{ number_format($vr->base_rate, 2) }})</option>@endforeach
        </select>
      </div>
      <div><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Transport Cost (₱) *</label>
        <input type="number" name="transport_cost" value="{{ $transCost }}" step="0.01" min="0" required style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px"></div>
      <div><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Driver</label>
        <select name="driver_crew_id" style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px">
          <option value="0">— No driver —</option>
          @foreach ($availCrew as $cr)<option value="{{ $cr->crew_id }}">{{ $cr->name }}</option>@endforeach
        </select>
      </div>
      <div><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Driver Rate (₱/12hr)</label>
        <input type="number" name="driver_rate" step="0.01" min="0" style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px"></div>
    </div>
    <div class="doc-foot"><button type="button" class="tbtn" style="background:#f1f5f9;color:#334155" onclick="closeDocMo('moTransport')">Cancel</button><button type="submit" class="doc-accept-btn">Save</button></div>
  </form>
</div></div>

<!-- SET DISCOUNT -->
<div class="doc-mo" id="moDiscount"><div class="doc-box" style="width:460px">
  <div class="doc-head"><h3>Set Discount</h3><button class="doc-close" onclick="closeDocMo('moDiscount')">&times;</button></div>
  <form method="POST" action="{{ $actionUrl }}">
    @csrf
    <input type="hidden" name="action" value="set_discount">
    <input type="hidden" name="return_to" value="ce_preview"><input type="hidden" name="return_ce_id" value="{{ $ceId }}"><input type="hidden" name="return_view" value="{{ $isClientView ? 'client' : 'internal' }}">
    <div class="doc-body" style="display:flex;flex-direction:column;gap:10px">
      <div style="font-size:12px;color:var(--sub)">Applies immediately, no separate approval step, recorded under your name.</div>
      <div><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Discount Type</label>
        <select name="discount_type" style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px">
          <option value="flat">Flat amount (₱)</option>
          <option value="percent">Percentage (%)</option>
          <option value="package">Package price (₱)</option>
        </select>
      </div>
      <div><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Amount</label>
        <input type="number" name="discount_value" step="0.01" min="0.01" required style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px"></div>
      <div><label style="font-size:11px;font-weight:700;color:var(--sub);text-transform:uppercase">Reason</label>
        <textarea name="reason" rows="2" placeholder="e.g. Client called, agreed to a loyalty discount" style="width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px"></textarea></div>
    </div>
    <div class="doc-foot"><button type="button" class="tbtn" style="background:#f1f5f9;color:#334155" onclick="closeDocMo('moDiscount')">Cancel</button><button type="submit" class="doc-accept-btn">Set &amp; Apply</button></div>
  </form>
</div></div>
@endif
@endif

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

function ceToggleExportMenu() {
    document.getElementById('ceExportMenu')?.classList.toggle('open');
}
document.addEventListener('click', (e) => {
    if (e.target.closest('.ce-export-wrap')) return;
    document.getElementById('ceExportMenu')?.classList.remove('open');
});

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
    document.querySelectorAll('.doc-mo').forEach(mo => {
        if (e.target === mo) closeDocMo(mo.id);
    });
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') document.querySelectorAll('.doc-mo').forEach(mo => closeDocMo(mo.id));
});

@if ($mode === 'booking' && $canManage)
// ── In-place editing: equipment/crew search-select (Add Equipment / Add Crew modals) ──────
const ceEquipData = {!! $availEquip->values()->toJson() !!};
const ceCrewData  = {!! $availCrew->values()->toJson() !!};

function ceRenderEquipList(items) {
    const box = document.getElementById('ceEquipList');
    box.innerHTML = '';
    if (! items.length) { box.style.display = 'none'; return; }
    items.slice(0, 30).forEach(i => {
        const row = document.createElement('div');
        row.style.cssText = 'padding:8px 12px;cursor:pointer;font-size:12.5px;border-bottom:1px solid #f1f5f9';
        row.addEventListener('mousedown', () => ceSelectEquip(i));
        row.addEventListener('mouseover', () => row.style.background = '#f0f7ff');
        row.addEventListener('mouseout', () => row.style.background = '');
        row.textContent = i.equipment_name + (i.brand ? ' (' + i.brand + ')' : '') + ' — ₱' + Number(i.daily_rate).toLocaleString('en-PH', {minimumFractionDigits:2}) + '/day';
        box.appendChild(row);
    });
    box.style.display = 'block';
}
function ceFilterEquip(q) {
    const lq = q.toLowerCase();
    ceRenderEquipList(ceEquipData.filter(i =>
        i.equipment_name.toLowerCase().includes(lq) ||
        (i.category_name||'').toLowerCase().includes(lq) ||
        (i.brand||'').toLowerCase().includes(lq)
    ));
}
function ceSelectEquip(i) {
    document.getElementById('ceEquipId').value = i.equipment_id;
    document.getElementById('ceEquipRate').value = i.daily_rate;
    document.getElementById('ceEquipSearch').value = i.equipment_name + (i.brand ? ' (' + i.brand + ')' : '');
    document.getElementById('ceEquipList').style.display = 'none';
}
// When this page is embedded (the Booking Detail "Cost Estimate" tab), Add Equipment /
// Add Crew / Assign Transport delegate back to Booking Detail's own modals via postMessage
// instead of opening a second, separately-styled copy of the same form — one real
// implementation of each workflow. Viewed standalone ("Open Full Page"), there's no parent to
// delegate to, so the page's own modals below still work as a fallback.
function ceInIframe() {
    try { return window.self !== window.top; } catch (e) { return true; }
}

function ceOpenAddEquip() {
    if (ceInIframe()) { window.parent.postMessage({source: 'ce-editor', action: 'openAddEquip'}, '*'); return; }
    document.getElementById('ceEquipSearch').value = '';
    document.getElementById('ceEquipId').value = '';
    document.getElementById('ceEquipRate').value = '';
    openDocMo('moAddEquip');
}

function ceRenderCrewList(items) {
    const box = document.getElementById('ceCrewList');
    box.innerHTML = '';
    if (! items.length) { box.style.display = 'none'; return; }
    items.slice(0, 30).forEach(i => {
        const row = document.createElement('div');
        row.style.cssText = 'padding:8px 12px;cursor:pointer;font-size:12.5px;border-bottom:1px solid #f1f5f9' + (i.busy_on_booking ? ';color:#dc2626' : '');
        row.addEventListener('mousedown', () => ceSelectCrew(i));
        row.addEventListener('mouseover', () => row.style.background = '#f0f7ff');
        row.addEventListener('mouseout', () => row.style.background = '');
        row.textContent = i.name + (i.position_name ? ' — ' + i.position_name : '') + (i.busy_on_booking ? ' (busy on ' + i.busy_on_booking + ')' : '');
        box.appendChild(row);
    });
    box.style.display = 'block';
}
function ceFilterCrew(q) {
    const lq = q.toLowerCase();
    ceRenderCrewList(ceCrewData.filter(i => i.name.toLowerCase().includes(lq)));
}
function ceSelectCrew(i) {
    document.getElementById('ceCrewId').value = i.crew_id;
    document.getElementById('ceCrewRate').value = i.base_rate_12hr || '';
    document.getElementById('ceCrewSearch').value = i.name;
    document.getElementById('ceCrewList').style.display = 'none';
}
function ceOpenAddCrew() {
    if (ceInIframe()) { window.parent.postMessage({source: 'ce-editor', action: 'openAddCrew'}, '*'); return; }
    document.getElementById('ceCrewSearch').value = '';
    document.getElementById('ceCrewId').value = '';
    document.getElementById('ceCrewRate').value = '';
    openDocMo('moAddCrew');
}

function ceOpenTransport() {
    if (ceInIframe()) { window.parent.postMessage({source: 'ce-editor', action: 'openAssignTransport'}, '*'); return; }
    openDocMo('moTransport');
}
@endif

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
    // Transportation is never actually charged at booking-submission time — CartController::
    // submitBooking() always stores transportation_cost=0/transport_multiplier=1.00 and staff
    // assign the real transport cost later, matching this page's own "Crew TF and
    // transportation costs will be added after booking confirmation" note. The totals driving
    // every on-screen figure here must match what the booking actually gets created with, so
    // transAdj is shown as its own line (an estimate, for the client's reference) but excluded
    // from grand/sub/vat — it used to be folded in here, silently overstating the total the
    // client saw versus the equipment-only total the booking was actually created with.
    const grand    = eqAdj + _BASE_CREW;
    const vat      = grand * VAT_RATE / (1 + VAT_RATE);
    const sub      = grand - vat;

    const multNote = multiplier !== 1 ? '× ' + multiplier + ' (' + label + ')' : '';
    const transNote = multiplier + '× rate · ' + label + ' (est., billed after confirmation)';

    document.getElementById('live-eq-val').textContent    = _pesoFmt(eqAdj);
    document.getElementById('live-eq-note').textContent   = multNote;
    document.getElementById('live-trans-val').textContent  = _pesoFmt(transAdj);
    document.getElementById('live-trans-note').textContent = transNote;
    document.getElementById('live-trans-val').style.color  = '#003D80';
    document.getElementById('live-sub').textContent        = _pesoFmt(sub);
    document.getElementById('live-vat').textContent        = _pesoFmt(vat);
    document.getElementById('live-grand').textContent      = _pesoFmt(grand);

    const eqN = document.getElementById('sum-eq-note');
    if (eqN) eqN.textContent = multNote;
    const eqV = document.getElementById('sum-eq-val');
    if (eqV) eqV.textContent = _pesoFmt(eqAdj);
    const tN = document.getElementById('sum-trans-note');
    if (tN) tN.textContent = transNote;
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
