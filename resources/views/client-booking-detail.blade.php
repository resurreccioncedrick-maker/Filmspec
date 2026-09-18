<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $booking->booking_reference }} — FilmSpec</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#ECF2FA;--surface:#fff;--s2:#F0F6FC;
  --border:#CFDFEE;--border2:#B5CFEA;
  --blue:#0060C7;--blue2:#004EA3;--bluelt:#E5F0FF;
  --text:#0B1A33;--sub:#385270;--muted:#7695B0;
  --green:#16a34a;--red:#dc2626;--orange:#c2410c;
  --font-d:'Bebas Neue',sans-serif;--font-b:'DM Sans',sans-serif;--font-m:'JetBrains Mono',monospace;
}
.star-btn{background:none;border:none;cursor:pointer;font-size:1.8rem;color:#d1d5db;padding:0 3px;transition:color .1s;line-height:1}
.star-btn.on,.star-btn:hover,.star-btn:hover ~ .star-btn{color:#f59e0b}
#starRow:hover .star-btn{color:#f59e0b}
#starRow .star-btn:hover ~ .star-btn{color:#d1d5db}
.fb-card{background:linear-gradient(135deg,var(--bluelt) 0%,var(--surface) 60%);border:2px solid var(--blue);}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font-b);background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column;-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}

/* Nav */
.nav{background:var(--surface);border-bottom:1px solid var(--border);padding:0 28px;height:58px;display:flex;align-items:center;gap:14px;box-shadow:0 1px 6px rgba(0,30,80,.06)}
.nav-logo{font-family:var(--font-d);font-size:22px;letter-spacing:2px;color:var(--blue)}
.nav-back{display:flex;align-items:center;gap:6px;padding:6px 14px;border-radius:6px;font-size:13px;font-weight:500;color:var(--sub);border:1.5px solid var(--border2);background:var(--surface);cursor:pointer;transition:all .15s;margin-left:auto}
.nav-back:hover{border-color:var(--blue);color:var(--blue)}

/* Layout */
.container{max-width:1180px;margin:0 auto;padding:28px 20px;flex:1;width:100%}
.layout-grid{display:grid;grid-template-columns:1fr 320px;gap:22px;align-items:start}
@media (max-width:860px){ .layout-grid{grid-template-columns:1fr} .sidebar-col{position:static !important} }

@media (max-width:640px){
  .nav{padding:0 16px}
  .side-btn{min-height:44px;padding:10px 14px;font-size:13.5px}

  /* Progress tracker — 4 nowrap labels + fixed dots had no responsive
     handling and risked clipping at phone width. Same $ptCurrent-driven
     done/now/warn/active class logic, just smaller and allowed to wrap. */
  .pt-dot{width:9px;height:9px}
  .pt-dot.now{box-shadow:0 0 0 3px var(--bluelt)}
  .pt-dot.now.warn{box-shadow:0 0 0 3px #fef3c7}
  .pt-label{font-size:9px;white-space:normal;text-align:center;line-height:1.25;max-width:56px}
  .pt-line{margin:0 4px 15px}

  .tbl th{font-size:.68rem;padding:9px 12px}
  .tbl td{font-size:.85rem;padding:10px 12px}

  /* Modals — 44px tap targets, 16px inputs (avoids Safari auto-zoom) */
  .modal-box{max-width:94vw!important}
  .modal-close{width:36px;height:36px;font-size:20px}
  .modal-overlay input,.modal-overlay select,.modal-overlay textarea{
    font-size:16px!important;min-height:44px;
  }
  .modal-overlay textarea{min-height:80px}
  .modal-overlay button[type=submit],.modal-overlay button[type=button]{min-height:44px}
  .freq-tab,.sig-tab{min-height:40px}

  /* Inline discount-request box (not a modal — its own id-scoped fields) */
  #requestDiscountBox select,#requestDiscountBox input,#requestDiscountBox textarea{
    font-size:16px!important;min-height:44px;
  }
  #requestDiscountBox textarea{min-height:70px}

  /* Star rating — glyph-sized tap target was well under 44px */
  .star-btn{font-size:2rem;padding:8px 6px;min-height:44px;min-width:36px}
}

/* Sidebar */
.sidebar-col{position:sticky;top:20px}
.side-ref{font-family:var(--font-d);font-size:1.5rem;letter-spacing:-.01em;color:var(--text);margin-bottom:6px}
.side-client{font-size:.83rem;color:var(--sub);margin-bottom:12px}
.side-meta{display:flex;flex-direction:column;gap:10px;margin:16px 0;padding:14px 0;border-top:1px solid var(--border);border-bottom:1px solid var(--border)}
.side-meta-item .meta-label{font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:2px}
.side-meta-item .meta-val{font-weight:600;color:var(--text);font-size:.85rem}
.side-amt{font-family:var(--font-d);font-size:1.7rem;color:var(--blue);margin-bottom:2px}
.side-amt-l{font-size:.72rem;color:var(--muted);margin-bottom:14px}
.side-btn{display:flex;align-items:center;justify-content:center;gap:7px;width:100%;padding:9px 14px;border-radius:7px;font-size:12.5px;font-weight:700;border:1.5px solid var(--border2);background:var(--surface);color:var(--sub);cursor:pointer;margin-bottom:8px;text-decoration:none;font-family:var(--font-b);box-sizing:border-box}
.side-btn svg{width:13px;height:13px;flex-shrink:0}
.side-btn.primary{background:var(--bluelt);color:var(--blue);border-color:var(--bluelt)}
.side-btn.green{background:#f0fdf4;color:#15803d;border-color:#86efac}
.side-btn.danger{background:#fee2e2;color:#b91c1c;border-color:#fca5a5}
.side-btn.pending{background:var(--s2);color:var(--muted);border-color:var(--border);cursor:default}

/* Card */
.card{background:var(--surface);border:1px solid var(--border);border-radius:10px;margin-bottom:18px}
.card-header{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.card-title{font-weight:700;font-size:.95rem;color:var(--text)}
.card-body{padding:16px 18px}

/* Badges */
.badge{display:inline-flex;align-items:center;padding:2px 9px;border-radius:20px;font-size:.72rem;font-weight:600;letter-spacing:.02em}
.badge-yellow{background:#fef9c3;color:#a16207}
.badge-blue{background:var(--bluelt);color:var(--blue)}
.badge-green{background:#dcfce7;color:#15803d}
.badge-gray{background:#f1f5f9;color:#475569}
.badge-red{background:#fee2e2;color:#b91c1c}
.badge-orange{background:#ffedd5;color:#c2410c}
.badge-purple{background:#f3e8ff;color:#7e22ce}

/* Table */
.table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
.tbl{width:100%;border-collapse:collapse}
.tbl th{padding:10px 14px;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);border-bottom:1px solid var(--border);text-align:left;font-weight:600}
.tbl td{padding:11px 14px;font-size:.85rem;border-bottom:1px solid var(--border);vertical-align:top}
.tbl tr:last-child td{border-bottom:none}
.tbl tfoot td{background:var(--s2);font-weight:700;padding:10px 14px}

.empty-msg{padding:32px;text-align:center;color:var(--muted);font-size:.875rem}

/* Booking progress tracker */
.progress-track{display:flex;align-items:center;margin:18px 0 4px}
.pt-step{display:flex;flex-direction:column;align-items:center;gap:6px;flex-shrink:0}
.pt-dot{width:11px;height:11px;border-radius:50%;background:var(--border)}
.pt-dot.done{background:var(--green)}
.pt-dot.now{background:var(--blue);box-shadow:0 0 0 4px var(--bluelt)}
.pt-dot.now.warn{background:#f59e0b;box-shadow:0 0 0 4px #fef3c7}
.pt-label{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;font-weight:600;white-space:nowrap}
.pt-label.active{color:var(--text);font-weight:700}
.pt-line{flex:1;height:2px;background:var(--border);margin:0 8px 19px}
.pt-line.done{background:var(--green)}

/* Cancel modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(7,14,26,.55);z-index:1000;align-items:center;justify-content:center;padding:20px}
.modal-box{background:#fff;border-radius:12px;width:100%;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,.25)}
.modal-head{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.modal-head h3{font-size:.95rem;font-weight:700;color:var(--text)}
.modal-body{padding:20px}
.modal-close{background:none;border:none;font-size:18px;cursor:pointer;color:var(--muted);line-height:1;padding:2px 6px;border-radius:4px}
.modal-close:hover{background:var(--s2);color:var(--text)}
.form-label{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:600;display:block;margin-bottom:6px}
.form-textarea{width:100%;background:#f8fafc;border:1.5px solid var(--border);color:var(--text);padding:9px 12px;border-radius:7px;font-size:13px;font-family:var(--font-b);resize:vertical;outline:none;min-height:100px}
.form-textarea:focus{border-color:var(--blue)}

/* Footer */
footer{background:#070e1a;border-top:1px solid #1e2d4a;padding:18px 28px;text-align:center;color:#5a7299;font-size:12px}

/* Support chat — same component/classes as home.blade.php, for a consistent look
   everywhere. Here it's scoped to this booking's own thread instead of the general one. */
.sup-fab{position:fixed;bottom:22px;right:22px;width:44px;height:44px;border-radius:12px;background:var(--surface);border:1.5px solid var(--border2);box-shadow:0 2px 8px rgba(0,30,80,.08);display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:900}
.sup-fab svg{width:19px;height:19px;color:var(--blue)}
.sup-fab-badge{position:absolute;top:-3px;right:-3px;width:17px;height:17px;border-radius:50%;background:var(--red);color:#fff;font-size:9.5px;font-weight:700;display:flex;align-items:center;justify-content:center;border:2px solid var(--bg)}
.sup-overlay{display:none;position:fixed;inset:0;backdrop-filter:blur(6px) saturate(1.05);-webkit-backdrop-filter:blur(6px) saturate(1.05);background:rgba(11,26,51,.14);z-index:950}
.sup-overlay.open{display:block}
.sup-panel{position:fixed;bottom:22px;right:22px;width:320px;max-height:min(70vh,520px);background:var(--surface);border-radius:8px;box-shadow:0 4px 20px rgba(0,30,80,.10);border:1px solid var(--border);display:none;flex-direction:column;overflow:hidden;z-index:960}
.sup-panel.open{display:flex}
.sup-h{padding:11px 16px;display:flex;align-items:center;gap:10px;flex-shrink:0;border-bottom:1px solid var(--border)}
.sup-h .t{font-weight:700;font-size:12.5px;color:var(--text)}
.sup-h .s{font-size:10.5px;color:var(--muted)}
.sup-close{margin-left:auto;width:22px;height:22px;border:none;background:none;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--muted)}
.sup-close svg{width:13px;height:13px}
.sup-body{flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;background:var(--surface)}
.sup-cluster{display:flex;flex-direction:column;gap:3px;margin-bottom:3px}
.sup-cluster.me{align-items:flex-end}
.sup-cluster.them{align-items:flex-start}
.sup-bubble{max-width:74%;padding:8px 12px;border-radius:14px;font-size:12.5px;line-height:1.45;white-space:pre-wrap}
.sup-cluster.me .sup-bubble{background:var(--blue);color:#fff;border-bottom-right-radius:4px}
.sup-cluster.them .sup-bubble{background:var(--surface);color:var(--text);border:1px solid var(--border);border-bottom-left-radius:4px}
.sup-cluster+.sup-meta{margin-bottom:10px}
.sup-meta{font-family:var(--font-m);font-size:9.5px;color:var(--muted);margin:2px 3px 10px}
.sup-empty{text-align:center;color:var(--muted);font-size:12.5px;padding:28px 10px}
.sup-input{display:flex;flex-direction:column;gap:6px;padding:10px 12px;background:var(--surface);border-top:1px solid var(--border);flex-shrink:0}
.sup-input-row{display:flex;gap:8px;align-items:flex-end}
.sup-input textarea{flex:1;border:none;border-bottom:1.5px solid var(--border2);border-radius:0;padding:5px 2px;font-size:12.5px;font-family:var(--font-b);outline:none;background:none;resize:none;max-height:80px}
.sup-input textarea:focus{border-color:var(--blue)}
.sup-send{width:28px;height:28px;border:none;background:none;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;color:var(--blue)}
.sup-send svg{width:16px;height:16px}
.sup-attach-btn{width:28px;height:28px;background:none;border:none;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;color:var(--muted)}
.sup-attach-btn svg{width:16px;height:16px}
.sup-attach-btn:hover{color:var(--blue)}
.sup-file-chip{display:none;align-items:center;gap:6px;background:var(--bluelt);color:var(--blue);border-radius:8px;padding:5px 9px;font-size:11.5px;font-weight:600;max-width:100%}
.sup-file-chip.show{display:flex}
.sup-file-chip svg{width:12px;height:12px;flex-shrink:0}
.sup-file-chip .name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sup-file-chip .rm{margin-left:auto;cursor:pointer;width:16px;height:16px;display:flex;align-items:center;justify-content:center;flex-shrink:0;border-radius:50%;color:var(--blue)}
.sup-file-chip .rm:hover{background:rgba(0,0,0,.08)}
.sup-attach-img{max-width:100%;border-radius:10px;display:block;margin-top:6px;cursor:pointer}
.sup-attach-file{display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:10px;margin-top:6px;text-decoration:none}
.sup-cluster.me .sup-attach-file{background:rgba(255,255,255,.15);color:#fff}
.sup-cluster.them .sup-attach-file{background:var(--s2);color:var(--text);border:1px solid var(--border)}
.sup-attach-file svg{width:16px;height:16px;flex-shrink:0}
.sup-attach-file .meta{overflow:hidden}
.sup-attach-file .fname{font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sup-attach-file .fsize{font-size:10px;opacity:.75}
@media(max-width:420px){
  .sup-panel{right:10px;left:10px;width:auto;bottom:86px}
  .sup-fab{right:16px;bottom:16px}
}
</style>
</head>
<body>

<nav class="nav">
  <img src="{{ asset('assets/images/logo.png') }}" alt="FilmSpec" style="height:32px;object-fit:contain">
  <a href="{{ route('home') }}" class="nav-back">&larr; Back to My Bookings</a>
</nav>

<div class="container">

@php $costApproval = $booking->cost_approval_status ?? null; @endphp

@if ($costApprovalMsg)
<div style="padding:12px 16px;border-radius:8px;margin-bottom:14px;font-size:13px;font-weight:600;
            background:{{ $costApprovalMsg['type'] === 'success' ? '#dcfce7' : ($costApprovalMsg['type'] === 'warning' ? '#fefce8' : '#fee2e2') }};
            color:{{ $costApprovalMsg['type'] === 'success' ? '#15803d' : ($costApprovalMsg['type'] === 'warning' ? '#92400e' : '#b91c1c') }}">
  {{ $costApprovalMsg['text'] }}
</div>
@endif

@if ($costApproval === 'pending_client')
<div class="card" style="margin-bottom:16px;border:2px solid #f59e0b">
  <div class="card-body" style="padding:18px 20px">
    <div style="font-weight:800;font-size:15px;color:#92400e;margin-bottom:8px">&#9888; Cost Estimate Ready — Your Approval Needed</div>
    <p style="font-size:13px;color:#78350f;margin:0 0 16px">FilmSpec has assigned crew and transport for your booking. Please review the updated cost below and approve or request adjustments.</p>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <form method="POST" action="{{ route('client-booking-detail', $id) }}">
        @csrf
        <input type="hidden" name="action" value="client_approve_cost">
        <button type="submit" style="background:#16a34a;color:#fff;border:none;padding:9px 20px;border-radius:7px;font-weight:700;font-size:13px;cursor:pointer">&#10003; Approve Cost</button>
      </form>
      <button onclick="document.getElementById('rejectCostBox').style.display='block';this.style.display='none'"
              style="background:#fee2e2;color:#b91c1c;border:1.5px solid #fca5a5;padding:9px 20px;border-radius:7px;font-weight:700;font-size:13px;cursor:pointer">Request Adjustment</button>
    </div>
    <div id="rejectCostBox" style="display:none;margin-top:14px">
      <form method="POST" action="{{ route('client-booking-detail', $id) }}">
        @csrf
        <input type="hidden" name="action" value="client_reject_cost">
        <textarea name="reason" rows="3" placeholder="Describe what you'd like adjusted..." style="width:100%;padding:8px 10px;border:1.5px solid #fca5a5;border-radius:6px;font-size:13px;resize:vertical;box-sizing:border-box;margin-bottom:8px"></textarea>
        <button type="submit" style="background:#b91c1c;color:#fff;border:none;padding:8px 18px;border-radius:6px;font-weight:700;font-size:13px;cursor:pointer">Submit Adjustment Request</button>
      </form>
    </div>
  </div>
</div>
@elseif ($costApproval === 'client_rejected')
<div class="card" style="margin-bottom:16px;border-left:4px solid #ef4444">
  <div class="card-body" style="padding:14px 18px;font-size:13px;color:#7f1d1d">
    <strong>You requested adjustments to the cost estimate.</strong> Our team has been notified and will update and resend.
  </div>
</div>
@elseif ($costApproval === 'client_approved')
<div class="card" style="margin-bottom:16px;border-left:4px solid #22c55e">
  <div class="card-body" style="padding:14px 18px;font-size:13px;color:#15803d">
    <strong>&#10003; You have approved the cost estimate.</strong> The team is proceeding with equipment preparation.
  </div>
</div>
@endif

@if ($requestMsg)
<div style="padding:12px 16px;border-radius:8px;margin-bottom:14px;font-size:13px;font-weight:600;
            background:{{ $requestMsg['type'] === 'success' ? '#dcfce7' : '#fee2e2' }};
            color:{{ $requestMsg['type'] === 'success' ? '#15803d' : '#b91c1c' }}">
  {{ $requestMsg['text'] }}
</div>
@endif

@if (session('doc_msg'))
<div style="padding:12px 16px;border-radius:8px;margin-bottom:14px;font-size:13px;font-weight:600;
            background:{{ session('doc_msg')['type'] === 'success' ? '#dcfce7' : '#fee2e2' }};
            color:{{ session('doc_msg')['type'] === 'success' ? '#15803d' : '#b91c1c' }}">
  {{ session('doc_msg')['text'] }}
</div>
@endif

<div class="layout-grid">
<div class="main-col">

@if ($extensionRequests->isNotEmpty() || $equipRequests->isNotEmpty())
<!-- Requests History -->
<div class="card" style="margin-bottom:18px">
  <div class="card-header">
    <div class="card-title">Your Requests</div>
    @php $pendingReqCount = ($pendingExtension ? 1 : 0) + $equipRequests->where('status', 'pending')->count(); @endphp
    @if ($pendingReqCount > 0)
    <span class="badge badge-yellow">{{ $pendingReqCount }} Pending</span>
    @endif
  </div>
  <div class="card-body" style="padding:0">
    @if ($extensionRequests->isNotEmpty())
    <div style="padding:12px 18px 4px;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:700">Rental Extension Requests</div>
    <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>Current End Date</th><th>Requested End Date</th><th>Status</th><th>Admin Notes</th></tr></thead>
      <tbody>
        @foreach ($extensionRequests as $er)
        <tr>
          <td>{{ \Illuminate\Support\Carbon::parse($er->current_end_date)->format('M j, Y') }}</td>
          <td style="font-weight:700;color:var(--blue)">{{ \Illuminate\Support\Carbon::parse($er->requested_end_date)->format('M j, Y') }}</td>
          <td>
            @if ($er->status === 'pending')
            <span class="badge badge-yellow">Pending Review</span>
            @elseif ($er->status === 'approved')
            <span class="badge badge-green">Approved</span>
            @else
            <span class="badge badge-red">Rejected</span>
            @endif
          </td>
          <td style="font-size:.82rem;color:var(--sub)">{{ $er->admin_notes ?: '—' }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    </div>
    @endif
    @if ($equipRequests->isNotEmpty())
    @if ($extensionRequests->isNotEmpty())<div style="border-top:1px solid var(--border)"></div>@endif
    <div style="padding:12px 18px 4px;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:700">Field Requests (Equipment / Accessory / Crew)</div>
    <div class="table-wrap">
    <table class="tbl">
      <thead><tr><th>Item</th><th>Qty</th><th>Status</th><th>Delivery</th><th>Admin Notes</th></tr></thead>
      <tbody>
        @foreach ($equipRequests as $r)
        @php
          $itemLabel = match ($r->item_type) {
              'accessory' => $r->accessory_name,
              'crew' => $r->position_name . ($r->crew_name && trim($r->crew_name) !== '' ? ' — ' . $r->crew_name : ''),
              default => $r->equipment_name,
          };
        @endphp
        <tr>
          <td>
            <div style="font-weight:600">{{ $itemLabel }}</div>
            @if ($r->item_type === 'equipment')
            <div style="font-size:.75rem;color:var(--muted)">{{ trim($r->brand . ' ' . $r->model) }}</div>
            @else
            <div style="font-size:.75rem;color:var(--muted);text-transform:capitalize">{{ $r->item_type }}</div>
            @endif
          </td>
          <td>{{ $r->quantity }}</td>
          <td>
            @if ($r->status === 'pending')
            <span class="badge badge-yellow">Pending Review</span>
            @elseif ($r->status === 'approved')
            <span class="badge badge-green">Approved</span>
            @elseif ($r->status === 'dispatched')
            <span class="badge badge-blue">Dispatched</span>
            @elseif ($r->status === 'delivered')
            <span class="badge badge-gray">Delivered</span>
            @else
            <span class="badge badge-red">Rejected</span>
            @endif
          </td>
          <td style="font-size:.8rem;color:var(--sub)">
            @if (in_array($r->status, ['dispatched', 'delivered'], true))
              @if ($r->driver_name){{ trim($r->driver_name) }}@endif
              @if ($r->eta)<div style="font-size:.72rem;color:var(--muted)">ETA {{ \Illuminate\Support\Carbon::parse($r->eta)->format('M j, g:ia') }}</div>@endif
              @if ($r->status === 'delivered')<div style="font-size:.72rem;color:var(--green,#16a34a)">Delivered {{ $r->delivered_at ? \Illuminate\Support\Carbon::parse($r->delivered_at)->format('M j, g:ia') : '' }}</div>@endif
            @else
              —
            @endif
          </td>
          <td style="font-size:.82rem;color:var(--sub)">{{ $r->admin_notes ?: '—' }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    </div>
    @endif
  </div>
</div>
@endif

@php
  $discStatusBadge = ['pending' => 'badge-yellow', 'approved' => 'badge-green', 'rejected' => 'badge-red'];
@endphp
<!-- Discounts -->
<div class="card" style="margin-bottom:18px">
  <div class="card-header">
    <div class="card-title">Discounts</div>
    @if ($discounts->isNotEmpty())
    <span class="badge badge-gray">{{ $discounts->count() }}</span>
    @endif
  </div>
  <div class="card-body">
    @if ($discountMsg)
    <div style="padding:10px 14px;border-radius:8px;margin-bottom:12px;font-size:13px;font-weight:600;
                background:{{ $discountMsg['type'] === 'success' ? '#dcfce7' : '#fee2e2' }};
                color:{{ $discountMsg['type'] === 'success' ? '#15803d' : '#b91c1c' }}">
      {{ $discountMsg['text'] }}
    </div>
    @endif

    @if ($discounts->isNotEmpty())
    <div style="margin-bottom:14px">
      @foreach ($discounts as $d)
      <div style="padding:8px 0;{{ ! $loop->last ? 'border-bottom:1px solid var(--border)' : '' }}">
        <span class="badge {{ $discStatusBadge[$d->status] ?? 'badge-gray' }}">{{ ucfirst($d->status) }}</span>
        <strong style="margin-left:6px">
          @if ($d->discount_type === 'percent') {{ number_format($d->discount_value, 2) }}% off
          @elseif ($d->discount_type === 'package') Package price: ₱{{ number_format($d->discount_value, 2) }}
          @else ₱{{ number_format($d->discount_value, 2) }} off
          @endif
        </strong>
        <span style="color:var(--muted);font-size:12px">requested {{ \Illuminate\Support\Carbon::parse($d->created_at)->format('M j, Y') }}</span>
        @if ($d->reason)<div style="font-size:12.5px;color:var(--sub);margin-top:2px">{{ $d->reason }}</div>@endif
        @if ($d->status === 'rejected' && $d->review_notes)<div style="font-size:12.5px;color:#b91c1c;margin-top:2px">{{ $d->review_notes }}</div>@endif
      </div>
      @endforeach
    </div>
    @endif

    @if ($canRequestDiscount)
      @if (! $discounts->where('status', 'pending')->count())
      <button type="button" onclick="document.getElementById('requestDiscountBox').style.display='block';this.style.display='none'"
              class="side-btn primary" style="width:auto;padding:8px 16px;font-size:12.5px">Request Discount</button>
      <div id="requestDiscountBox" style="display:none;margin-top:12px;padding:12px;background:var(--s2);border-radius:8px">
        <form method="POST" action="{{ route('client-booking-detail', $id) }}">
          @csrf
          <input type="hidden" name="action" value="request_discount">
          <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:8px">
            <select name="discount_type" id="discReqType" onchange="toggleDiscReqInput()" style="padding:7px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px">
              <option value="flat">Flat amount (₱)</option>
              <option value="percent">Percentage (%)</option>
              <option value="package">Package price (₱)</option>
            </select>
            <input type="number" name="discount_value" id="discReqValue" step="0.01" min="0.01" placeholder="Amount" required
                   style="padding:7px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px;width:160px">
          </div>
          <div id="discReqPackageNote" style="display:none;font-size:11.5px;color:var(--muted);margin:-4px 0 8px">Enter the total you'd like to pay — the discount is calculated automatically from the current itemized total.</div>
          <textarea name="reason" rows="2" placeholder="Why are you requesting this discount? (optional)"
                    style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px;resize:vertical;box-sizing:border-box;margin-bottom:8px"></textarea>
          <button type="submit" class="side-btn primary" style="width:auto;padding:8px 16px;font-size:12.5px">Submit Request</button>
        </form>
      </div>
      @else
      <div style="font-size:12.5px;color:var(--muted)">You have a discount request pending review.</div>
      @endif
    @else
    <div style="font-size:12.5px;color:var(--muted)">Discount requests aren't available for this booking right now.</div>
    @endif
  </div>
</div>

<!-- Equipment -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Equipment</div>
    <span class="badge badge-blue">{{ $equipmentLines->count() }}</span>
  </div>
  @if ($equipmentLines->isEmpty())
  <div class="empty-msg">No equipment added yet.</div>
  @else
  <div class="table-wrap">
  <table class="tbl">
    <thead>
      <tr><th>Item</th><th>Category</th><th>Qty</th><th>Days</th><th>Rate/Day</th><th>Subtotal</th></tr>
    </thead>
    <tbody>
      @foreach ($equipmentLines as $line)
      <tr>
        <td><div style="font-weight:600">{{ $line->equipment_name }}</div>
            <div style="font-size:.75rem;color:var(--muted)">{{ trim($line->brand . ' ' . $line->model) }}</div></td>
        <td><span class="badge badge-blue">{{ $line->category_name }}</span></td>
        <td>{{ $line->quantity }}</td>
        <td>{{ $line->days }}</td>
        <td>₱{{ number_format($line->daily_rate, 2) }}</td>
        <td style="font-weight:700;color:var(--blue)">₱{{ number_format($line->subtotal, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr>
        <td colspan="5" style="text-align:right">Equipment Subtotal</td>
        <td style="color:var(--blue)">₱{{ number_format($equipmentLines->sum('subtotal'), 2) }}</td>
      </tr>
    </tfoot>
  </table>
  </div>
  @endif
</div>

<!-- Crew -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Crew</div>
    <span class="badge badge-blue">{{ $crewLines->count() }}</span>
  </div>
  @if ($crewLines->isEmpty())
  <div class="empty-msg">No crew assigned yet.</div>
  @else
  <div class="table-wrap">
  <table class="tbl">
    <thead>
      <tr><th>Crew Member</th><th>Position</th><th>Rate</th><th>Subtotal</th></tr>
    </thead>
    <tbody>
      @foreach ($crewLines as $cl)
      <tr>
        <td style="font-weight:600">{{ $cl->crew_name }}</td>
        <td>{{ $cl->position_name ?? '—' }}</td>
        <td>₱{{ number_format($cl->rate_used, 2) }}/12hr{!! $cl->is_overtime ? ' <span class="badge badge-orange" style="font-size:.6rem">OT</span>' : '' !!}</td>
        <td style="font-weight:700;color:var(--blue)">₱{{ number_format($cl->subtotal, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>
  </div>
  @endif
</div>

<!-- Payments -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Payment History</div>
  </div>
  @if ($payments->isEmpty())
  <div class="empty-msg">No payments recorded yet.</div>
  @else
  <div class="table-wrap">
  <table class="tbl">
    <thead>
      <tr><th>Date</th><th>Type</th><th>Method</th><th>Amount</th><th>Receipt</th></tr>
    </thead>
    <tbody>
      @foreach ($payments as $pay)
      <tr>
        <td>{{ \Illuminate\Support\Carbon::parse($pay->payment_date)->format('M j, Y') }}</td>
        <td><span class="badge badge-blue">{{ ucfirst($pay->payment_type) }}</span></td>
        <td>{{ ucwords(str_replace('_', ' ', $pay->payment_method)) }}</td>
        <td style="font-weight:700;color:var(--blue)">₱{{ number_format($pay->amount, 2) }}</td>
        <td>
          <a href="{{ route('payment-receipt', $pay->payment_id) }}" target="_blank" style="font-size:.8rem;color:var(--blue);font-weight:600;text-decoration:none">
            {{ $pay->receipt_number ?: 'View' }} &rarr;
          </a>
        </td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr>
        <td colspan="3" style="text-align:right">Total Paid</td>
        <td style="color:var(--green)">₱{{ number_format($payments->sum('amount'), 2) }}</td>
        <td></td>
      </tr>
    </tfoot>
  </table>
  </div>
  @endif
</div>

@if ($booking->booking_status === 'completed')
<!-- Client Feedback -->
<div class="card fb-card">
  <div class="card-header">
    <div class="card-title" style="color:var(--blue)">Rate Your Experience</div>
    @if ($feedbackDeadline)
    <span class="badge {{ time() < $feedbackDeadline ? 'badge-blue' : 'badge-gray' }}" style="font-size:11px">
      {{ time() < $feedbackDeadline ? 'Open until ' . \Illuminate\Support\Carbon::createFromTimestamp($feedbackDeadline)->format('M j, Y') : 'Review period closed' }}
    </span>
    @endif
  </div>
  <div class="card-body">
    @if ($feedbackMsg)
    <div style="padding:10px 14px;border-radius:7px;margin-bottom:14px;font-size:13px;font-weight:600;
                background:{{ $feedbackMsg['type'] === 'success' ? '#dcfce7' : '#fee2e2' }};
                color:{{ $feedbackMsg['type'] === 'success' ? '#15803d' : '#b91c1c' }}">
      {{ $feedbackMsg['text'] }}
    </div>
    @endif

    @if ($feedback)
    <!-- Existing feedback display -->
    <div style="display:flex;gap:18px;align-items:flex-start;margin-bottom:{{ ($feedbackDeadline && time() < $feedbackDeadline) ? '18px' : '0' }}">
      <div style="text-align:center;min-width:70px">
        <div style="font-size:2.4rem;font-weight:800;color:var(--blue);line-height:1">{{ $feedback->rating }}</div>
        <div style="color:#f59e0b;font-size:1.3rem;margin:3px 0">{{ str_repeat('★', (int) $feedback->rating) . str_repeat('☆', 5 - (int) $feedback->rating) }}</div>
        <div style="font-size:10px;color:var(--muted)">out of 5</div>
      </div>
      <div style="flex:1">
        @if ($feedback->comment)
        <p style="font-style:italic;color:var(--text);margin-bottom:8px;font-size:.9rem">"{{ $feedback->comment }}"</p>
        @endif
        <div style="font-size:11px;color:var(--muted)">Submitted {{ \Illuminate\Support\Carbon::parse($feedback->submitted_at)->format('M j, Y') }}</div>
      </div>
    </div>
    @endif

    @if ($feedbackDeadline && time() < $feedbackDeadline)
    <form method="POST" action="{{ route('client-booking-detail', $id) }}">
      @csrf
      <input type="hidden" name="action" value="submit_feedback">
      <div style="margin-bottom:14px">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:600;margin-bottom:8px">Your Rating *</div>
        <div id="starRow" style="display:flex;flex-direction:row-reverse;justify-content:flex-end">
          @for ($r = 5; $r >= 1; $r--)
          <button type="button" class="star-btn {{ (($feedback->rating ?? 0) >= $r) ? 'on' : '' }}" data-val="{{ $r }}" onclick="setRating({{ $r }})">&#9733;</button>
          @endfor
          <input type="hidden" name="rating" id="ratingInput" value="{{ $feedback->rating ?? 5 }}">
        </div>
      </div>
      <div style="margin-bottom:14px">
        <label style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:600;display:block;margin-bottom:6px">Comment (optional)</label>
        <textarea name="comment" rows="3" placeholder="Tell us about your experience with FilmSpec…"
                  style="width:100%;background:#f8fafc;border:1.5px solid var(--border);color:var(--text);padding:9px 12px;border-radius:7px;font-size:13px;font-family:var(--font-b);resize:vertical;outline:none">{{ $feedback->comment ?? '' }}</textarea>
      </div>
      <button type="submit"
              style="background:var(--blue);color:#fff;border:none;padding:10px 22px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;font-family:var(--font-b)">
        {{ $feedback ? 'Update Review' : 'Submit Review' }}
      </button>
    </form>
    <script>
    function setRating(val) {
      document.getElementById('ratingInput').value = val;
      document.querySelectorAll('.star-btn').forEach(b => {
        b.classList.toggle('on', parseInt(b.dataset.val) <= val);
      });
    }
    </script>
    @elseif (! $feedback)
    <p style="font-size:13px;color:var(--muted)">The 30-day review window for this booking has closed.</p>
    @endif
  </div>
</div>
@endif

</div><!-- /main-col -->

<aside class="sidebar-col">
  <div class="card">
    <div class="card-body">
      <div class="side-ref">{{ $booking->booking_reference }}</div>
      <div class="side-client">
        {{ $booking->company_name ?: $booking->contact_person }}
        @if ($booking->company_name)
        &nbsp;·&nbsp; {{ $booking->contact_person }}
        @endif
      </div>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <span class="badge {{ $statusBadge[$booking->booking_status] ?? 'badge-gray' }}">{{ ucfirst($booking->booking_status) }}</span>
        <span class="badge {{ $payBadge[$booking->payment_status] ?? 'badge-gray' }}">{{ $payLabel[$booking->payment_status] ?? ucfirst($booking->payment_status) }}</span>
        @if ($costApproval === 'pending_client')
        <span class="badge" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a">Action Required</span>
        @endif
      </div>

      @php
        $ptOrder = ['pending' => 1, 'confirmed' => 2, 'ongoing' => 3, 'completed' => 4];
        $ptCurrent = $ptOrder[$booking->booking_status] ?? null;
        $ptLabels = [1 => 'Submitted', 2 => 'Confirmed', 3 => 'In Field', 4 => 'Completed'];
        $ptWarn = $costApproval === 'pending_client';
      @endphp
      @if ($booking->booking_status === 'cancelled')
      <div style="margin:16px 0 2px;display:flex;align-items:center;gap:7px;font-size:12.5px;font-weight:700;color:var(--red)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>
        This booking was cancelled
      </div>
      @elseif ($ptCurrent)
      <div class="progress-track">
        @foreach ($ptLabels as $n => $label)
          @if ($n > 1)
          <div class="pt-line {{ ($n - 1) < $ptCurrent ? 'done' : '' }}"></div>
          @endif
          <div class="pt-step">
            <div class="pt-dot {{ $n < $ptCurrent ? 'done' : ($n === $ptCurrent ? ($booking->booking_status === 'completed' ? 'done' : ('now' . ($ptWarn ? ' warn' : ''))) : '') }}"></div>
            <div class="pt-label {{ $n === $ptCurrent ? 'active' : '' }}">{{ $label }}</div>
          </div>
        @endforeach
      </div>
      @endif

      <div class="side-meta">
        <div class="side-meta-item">
          <div class="meta-label">Project</div>
          <div class="meta-val">{{ $booking->project_title ?: '—' }}</div>
        </div>
        <div class="side-meta-item">
          <div class="meta-label">Shoot Dates</div>
          <div class="meta-val">{{ \Illuminate\Support\Carbon::parse($booking->shoot_date_start)->format('M j') }} – {{ \Illuminate\Support\Carbon::parse($booking->shoot_date_end)->format('M j, Y') }}</div>
        </div>
        @if ($booking->shoot_location)
        <div class="side-meta-item">
          <div class="meta-label">Location</div>
          <div class="meta-val">{{ $booking->shoot_location }}</div>
        </div>
        @endif
        @if ($booking->transportation_cost > 0)
        <div class="side-meta-item">
          <div class="meta-label">Transportation</div>
          <div class="meta-val" style="color:var(--blue)">₱{{ number_format($booking->transportation_cost, 2) }}</div>
        </div>
        @endif
      </div>

      @php
        $activeFieldReq = $equipRequests->whereIn('status', ['pending', 'approved', 'dispatched'])->sortByDesc('created_at')->first();
      @endphp
      @if ($activeFieldReq)
      @php
        $afLabel = match ($activeFieldReq->item_type) {
            'accessory' => $activeFieldReq->accessory_name,
            'crew' => $activeFieldReq->position_name,
            default => $activeFieldReq->equipment_name,
        };
      @endphp
      <div style="background:var(--bluelt,#eff6ff);border:1px solid var(--bluemid,#bfdbfe);border-radius:7px;padding:10px 12px;font-size:12px;margin-bottom:14px">
        <div style="font-weight:700;color:var(--blue);margin-bottom:2px">Field Request: {{ $afLabel }}</div>
        @if ($activeFieldReq->status === 'pending')
        <div style="color:var(--sub)">Awaiting admin review.</div>
        @elseif ($activeFieldReq->status === 'approved')
        <div style="color:var(--sub)">Approved — awaiting dispatch to your location.</div>
        @elseif ($activeFieldReq->status === 'dispatched')
        <div style="color:var(--sub)">
          On the way @if ($activeFieldReq->driver_name)with driver <strong>{{ trim($activeFieldReq->driver_name) }}</strong>@endif.
          @if ($activeFieldReq->eta)<br>ETA <strong>{{ \Illuminate\Support\Carbon::parse($activeFieldReq->eta)->format('M j, g:ia') }}</strong>@endif
        </div>
        @endif
      </div>
      @endif

      @if ($booking->client_type === 'first_time' && in_array($booking->booking_status, ['pending', 'confirmed']))
      <div style="background:#fefce8;border:1px solid #fde047;color:#a16207;padding:10px 12px;border-radius:6px;font-size:11.5px;margin-bottom:14px;display:flex;align-items:flex-start;gap:7px">
        <span style="font-weight:700">!</span>
        <div><strong>New Customer — 50% Downpayment Required</strong> before equipment can be released.
        @if ($booking->final_amount > 0)
        &nbsp;Amount due: <strong>₱{{ number_format($booking->final_amount * 0.5, 2) }}</strong>
        @endif
        </div>
      </div>
      @endif
      @if ($cancelMsg)
      <div style="background:{{ $cancelMsg['type'] === 'success' ? '#dcfce7' : '#fee2e2' }};border:1px solid {{ $cancelMsg['type'] === 'success' ? '#86efac' : '#fca5a5' }};color:{{ $cancelMsg['type'] === 'success' ? '#15803d' : '#b91c1c' }};padding:10px 12px;border-radius:6px;font-size:12.5px;font-weight:600;margin-bottom:14px">
        {{ $cancelMsg['text'] }}
      </div>
      @endif

      @if ($booking->final_amount > 0)
      <div class="side-amt">₱{{ number_format($booking->final_amount, 2) }}</div>
      <div class="side-amt-l">Total Amount (inc. VAT)</div>
      @endif

      <a href="{{ route('ce-preview', ['booking_id' => $booking->booking_id]) }}" target="_blank" class="side-btn primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        View Cost Estimate
      </a>
      @if (in_array($booking->booking_status, ['confirmed', 'ongoing']))
        @if ($pendingExtension)
        <span class="side-btn pending">Extension Pending</span>
        @else
        <button onclick="document.getElementById('modalExtendRequest').style.display='flex'" class="side-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
          Extend Rental
        </button>
        @endif
        <button onclick="document.getElementById('modalEquipRequest').style.display='flex'" class="side-btn green">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg>
          Request Equipment / Crew
        </button>
      @endif
      @if (in_array($booking->booking_status, ['pending', 'confirmed']) && ! $booking->is_archived)
        @if ($pendingCancel)
        <span class="side-btn pending">Cancellation Request Pending</span>
        @else
        <button onclick="openCancelStep1()" class="side-btn danger">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6"/></svg>
          Request Cancellation
        </button>
        @endif
      @endif
    </div>
  </div>
</aside>

</div><!-- /layout-grid -->

</div><!-- /container -->

<!-- Support chat — this booking's own thread, same component as the general one on Home -->
<button class="sup-fab" onclick="toggleSupportChat()" aria-label="Message FilmSpec about this booking">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z"/></svg>
  @if ($comments->count() > 0)
  <span class="sup-fab-badge">{{ $comments->count() }}</span>
  @endif
</button>

<div class="sup-overlay" id="supOverlay" onclick="closeSupportChat()"></div>
<div class="sup-panel" id="supPanel">
  <div class="sup-h">
    <div>
      <div class="t">Booking {{ $booking->booking_reference }}</div>
      <div class="s">Message FilmSpec about this booking</div>
    </div>
    <button class="sup-close" onclick="closeSupportChat()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="sup-body" id="supBody">
    @if ($chatMsg)
    <div style="padding:9px 12px;border-radius:7px;font-size:12px;font-weight:600;
                background:{{ $chatMsg['type'] === 'success' ? '#dcfce7' : '#fee2e2' }};
                color:{{ $chatMsg['type'] === 'success' ? '#15803d' : '#b91c1c' }}">
      {{ $chatMsg['text'] }}
    </div>
    @endif

    @if ($comments->isEmpty())
    <div class="sup-empty">No messages yet about this booking. Send one below — your staff contact will reply here. For general questions not about this booking, use the chat bubble on the Home page instead.</div>
    @else
    @php
      $clusters = [];
      foreach ($comments as $c) {
          $role = $c->author_role === 'client' ? 'me' : 'them';
          if ($clusters && end($clusters)['role'] === $role) {
              $clusters[array_key_last($clusters)]['items'][] = $c;
          } else {
              $clusters[] = ['role' => $role, 'items' => [$c]];
          }
      }
    @endphp
    @foreach ($clusters as $cluster)
      <div class="sup-cluster {{ $cluster['role'] }}">
        @foreach ($cluster['items'] as $c)
        <div class="sup-bubble">
          @if ($c->body){{ $c->body }}@endif
          @if ($c->attachment_path)
            @if (str_starts_with($c->attachment_mime, 'image/'))
            <img src="{{ route('client-booking-attachment', ['id' => $id, 'commentId' => $c->comment_id]) }}" class="sup-attach-img" onclick="window.open(this.src,'_blank')" alt="{{ $c->attachment_name }}">
            @else
            <a href="{{ route('client-booking-attachment', ['id' => $id, 'commentId' => $c->comment_id]) }}" target="_blank" class="sup-attach-file">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              <div class="meta">
                <div class="fname">{{ $c->attachment_name }}</div>
                <div class="fsize">{{ number_format($c->attachment_size / 1024, 0) }} KB</div>
              </div>
            </a>
            @endif
          @endif
        </div>
        @endforeach
      </div>
      @php $lastItem = end($cluster['items']); @endphp
      <div class="sup-meta">{{ $cluster['role'] === 'them' ? 'FilmSpec Support · ' : '' }}{{ \Illuminate\Support\Carbon::parse($lastItem->created_at)->format('M j, g:i A') }}</div>
    @endforeach
    @endif
  </div>
  <form method="POST" action="{{ route('client-booking-detail', $id) }}" class="sup-input" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="action" value="post_comment">
    <div class="sup-file-chip" id="supFileChip">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M21.44 11.05 12.25 20.24a5 5 0 0 1-7.07-7.07l9.19-9.19a3.33 3.33 0 0 1 4.71 4.71l-9.2 9.19a1.67 1.67 0 0 1-2.36-2.36l8.49-8.48"/></svg>
      <span class="name"></span>
      <span class="rm" onclick="clearSupFile()" title="Remove">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="10" height="10"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </span>
    </div>
    <div class="sup-input-row">
      <button type="button" class="sup-attach-btn" onclick="document.getElementById('supFile').click()" title="Attach file">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21.44 11.05 12.25 20.24a5 5 0 0 1-7.07-7.07l9.19-9.19a3.33 3.33 0 0 1 4.71 4.71l-9.2 9.19a1.67 1.67 0 0 1-2.36-2.36l8.49-8.48"/></svg>
      </button>
      <input type="file" id="supFile" name="attachment" accept="image/*,.pdf,.doc,.docx" style="display:none" onchange="showSupFilePreview(this)">
      <textarea name="body" rows="1" placeholder="Message the FilmSpec team…" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();this.form.submit()}"></textarea>
      <button type="submit" class="sup-send" title="Send">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7Z"/></svg>
      </button>
    </div>
  </form>
</div>
<script>
function showSupFilePreview(input){
  var chip = document.getElementById('supFileChip');
  if (input.files && input.files[0]) {
    chip.querySelector('.name').textContent = input.files[0].name;
    chip.classList.add('show');
  } else {
    chip.classList.remove('show');
  }
}
function clearSupFile(){
  document.getElementById('supFile').value = '';
  document.getElementById('supFileChip').classList.remove('show');
}
function toggleSupportChat(){
  var p = document.getElementById('supPanel'), o = document.getElementById('supOverlay');
  var open = p.classList.toggle('open');
  o.classList.toggle('open', open);
  if (open) { var b = document.getElementById('supBody'); b.scrollTop = b.scrollHeight; }
}
function closeSupportChat(){
  document.getElementById('supPanel').classList.remove('open');
  document.getElementById('supOverlay').classList.remove('open');
}
@if (request('action') === 'post_comment')
document.addEventListener('DOMContentLoaded', toggleSupportChat);
@endif
</script>

<footer>
  <span style="font-family:var(--font-d);color:var(--blue);font-size:14px;letter-spacing:2px">FilmSpec</span>
  &nbsp;|&nbsp; &copy; {{ date('Y') }} FilmSpec. All rights reserved. &nbsp;|&nbsp; Integrated Film Operations Platform
</footer>
<!-- Cancellation Request Modal — Step 1: Policy -->
<div id="modalCancelPolicy" class="modal-overlay" onclick="if(event.target===this)closeCancelModals()" style="align-items:flex-start;padding:30px 20px;overflow-y:auto">
  <div class="modal-box" style="max-width:560px;margin:auto">
    <div class="modal-head" style="background:#fef2f2;border-radius:12px 12px 0 0">
      <h3 style="color:#b91c1c;display:flex;align-items:center;gap:8px">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Cancellation &amp; Refund Policy
      </h3>
      <button class="modal-close" onclick="closeCancelModals()">&times;</button>
    </div>
    <div class="modal-body" style="padding:22px 22px 18px">
      <p style="font-size:13px;color:var(--sub);margin-bottom:18px;line-height:1.6">Before proceeding, please read our cancellation and refund policy. Penalties are based on <strong>how far in advance</strong> the cancellation is made.</p>

      <!-- Client-Requested -->
      <div style="background:var(--s2);border:1px solid var(--border);border-radius:8px;padding:14px 16px;margin-bottom:12px">
        <div style="font-weight:700;font-size:13px;color:var(--text);margin-bottom:10px;display:flex;align-items:center;gap:7px">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          Client-Requested Cancellation
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:12px">
          <div style="background:var(--bluelt);border-radius:5px;padding:8px 10px">
            <div style="color:var(--muted);margin-bottom:2px">7+ days before shoot</div>
            <div style="font-weight:700;color:#15803d">No penalty — Full refund</div>
          </div>
          <div style="background:#fef9c3;border-radius:5px;padding:8px 10px">
            <div style="color:var(--muted);margin-bottom:2px">3–6 days before</div>
            <div style="font-weight:700;color:#a16207">25% of total charged</div>
          </div>
          <div style="background:#ffedd5;border-radius:5px;padding:8px 10px">
            <div style="color:var(--muted);margin-bottom:2px">1–2 days before</div>
            <div style="font-weight:700;color:#c2410c">50% of total charged</div>
          </div>
          <div style="background:#fee2e2;border-radius:5px;padding:8px 10px">
            <div style="color:var(--muted);margin-bottom:2px">Day of shoot</div>
            <div style="font-weight:700;color:#b91c1c">75% of total charged</div>
          </div>
        </div>
      </div>

      <!-- On-Field -->
      <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:14px 16px;margin-bottom:12px">
        <div style="font-weight:700;font-size:13px;color:#c2410c;margin-bottom:6px;display:flex;align-items:center;gap:7px">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#c2410c" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
          On-Field Cancellation
        </div>
        <p style="font-size:12px;color:#7c2d12;line-height:1.65">If cancelled <strong>after crew and equipment have been deployed</strong>, a flat <strong>50% penalty</strong> applies on the total booking amount. This covers mobilization, crew compensation, and equipment preparation.</p>
      </div>

      <!-- Refund Process -->
      <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:14px 16px;margin-bottom:16px">
        <div style="font-weight:700;font-size:13px;color:#15803d;margin-bottom:8px">Refund Process</div>
        <ul style="margin:0 0 0 16px;font-size:12px;color:#166534;line-height:1.9">
          <li>All refunds are processed as <strong>walk-in payments</strong> at our office</li>
          <li>Issued within <strong>7–14 business days</strong> after approval</li>
          <li>Original proof of payment is required</li>
        </ul>
      </div>

      <div style="font-size:12px;color:var(--muted);line-height:1.6;padding:10px 14px;background:var(--bluelt);border-radius:6px;border:1px solid var(--border2)">
        <strong style="color:var(--blue)">Admin cancellations:</strong> If FilmSpec cancels due to equipment unavailability, <strong>no penalty applies</strong> and any deposits are fully refunded.
      </div>
    </div>
    <div style="padding:0 22px 20px;display:flex;gap:10px;justify-content:flex-end">
      <button onclick="closeCancelModals()"
              style="background:var(--s2);color:var(--sub);border:1.5px solid var(--border);padding:9px 18px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;font-family:var(--font-b)">
        Go Back
      </button>
      <button onclick="openCancelStep2()"
              style="background:#dc2626;color:#fff;border:none;padding:9px 20px;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer;font-family:var(--font-b);display:flex;align-items:center;gap:6px">
        I Understand — Continue
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
      </button>
    </div>
  </div>
</div>

<!-- Cancellation Request Modal — Step 2: Reason Form -->
<div id="modalCancelRequest" class="modal-overlay" onclick="if(event.target===this)closeCancelModals()">
  <div class="modal-box" style="max-width:460px">
    <div class="modal-head">
      <h3 style="display:flex;align-items:center;gap:8px">
        <button onclick="openCancelStep1()" style="background:none;border:none;cursor:pointer;color:var(--muted);padding:0;display:flex;align-items:center" title="Back to policy">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        Submit Cancellation Request
      </h3>
      <button class="modal-close" onclick="closeCancelModals()">&times;</button>
    </div>
    <div class="modal-body">
      <div style="background:#fef9c3;border:1px solid #fde047;border-radius:7px;padding:10px 13px;margin-bottom:16px;font-size:12px;color:#a16207;line-height:1.5">
        Your request will be reviewed by our team. Penalties apply based on the policy you just read.
      </div>
      <form method="POST" action="{{ route('client-booking-detail', $id) }}">
        @csrf
        <input type="hidden" name="action" value="client_cancel_request">
        <div style="margin-bottom:16px">
          <label class="form-label">Reason for Cancellation *</label>
          <textarea name="reason" class="form-textarea" required placeholder="Please explain why you need to cancel this booking…"></textarea>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end">
          <button type="button" onclick="closeCancelModals()"
                  style="background:var(--s2);color:var(--sub);border:1.5px solid var(--border);padding:9px 18px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;font-family:var(--font-b)">
            Cancel
          </button>
          <button type="submit"
                  style="background:#dc2626;color:#fff;border:none;padding:9px 20px;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer;font-family:var(--font-b)">
            Submit Request
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function openCancelStep1(){ closeCancelModals(); document.getElementById('modalCancelPolicy').style.display='flex'; }
function openCancelStep2(){ closeCancelModals(); document.getElementById('modalCancelRequest').style.display='flex'; }
function closeCancelModals(){ document.getElementById('modalCancelPolicy').style.display='none'; document.getElementById('modalCancelRequest').style.display='none'; }
document.addEventListener('keydown', e => { if(e.key==='Escape') { closeCancelModals(); closeRequestModals(); closeSupportChat(); } });
</script>

<!-- Extension Request Modal -->
<div id="modalExtendRequest" class="modal-overlay" onclick="if(event.target===this)closeRequestModals()">
  <div class="modal-box" style="max-width:460px">
    <div class="modal-head">
      <h3>Request Rental Extension</h3>
      <button class="modal-close" onclick="closeRequestModals()">&times;</button>
    </div>
    <div class="modal-body">
      <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:7px;padding:10px 13px;margin-bottom:16px;font-size:12.5px;color:#1e40af;line-height:1.5">
        Current end date: <strong>{{ \Illuminate\Support\Carbon::parse($booking->shoot_date_end)->format('M j, Y') }}</strong>. Select a new end date below.
      </div>
      <form method="POST" action="{{ route('client-booking-detail', $id) }}">
        @csrf
        <input type="hidden" name="action" value="request_extension">
        <div style="margin-bottom:14px">
          <label class="form-label">New End Date *</label>
          <input type="date" name="new_end_date" required
                 min="{{ \Illuminate\Support\Carbon::parse($booking->shoot_date_end)->addDay()->format('Y-m-d') }}"
                 style="width:100%;background:#f8fafc;border:1.5px solid var(--border);color:var(--text);padding:9px 12px;border-radius:7px;font-size:13px;font-family:var(--font-b);outline:none;box-sizing:border-box">
        </div>
        <div style="margin-bottom:16px">
          <label class="form-label">Reason</label>
          <textarea name="reason" class="form-textarea" placeholder="Why do you need to extend the rental?"></textarea>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end">
          <button type="button" onclick="closeRequestModals()"
                  style="background:var(--s2);color:var(--sub);border:1.5px solid var(--border);padding:9px 18px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;font-family:var(--font-b)">
            Cancel
          </button>
          <button type="submit"
                  style="background:var(--blue);color:#fff;border:none;padding:9px 20px;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer;font-family:var(--font-b)">
            Submit Request
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Field Request Modal (Equipment / Accessory / Crew) -->
<div id="modalEquipRequest" class="modal-overlay" onclick="if(event.target===this)closeRequestModals()">
  <div class="modal-box" style="max-width:520px">
    <div class="modal-head">
      <h3>Request Equipment, Accessory, or Crew</h3>
      <button class="modal-close" onclick="closeRequestModals()">&times;</button>
    </div>
    <div class="modal-body">
      <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:7px;padding:10px 13px;margin-bottom:14px;font-size:12.5px;color:#15803d;line-height:1.5">
        Need something extra delivered to your shoot? Our team will review, then dispatch it with a driver and ETA.
      </div>
      <div style="display:flex;gap:6px;margin-bottom:16px" id="freqTabs">
        <button type="button" class="freq-tab on" data-type="equipment" onclick="freqSwitch('equipment')">Equipment</button>
        <button type="button" class="freq-tab" data-type="accessory" onclick="freqSwitch('accessory')">Accessory</button>
        <button type="button" class="freq-tab" data-type="crew" onclick="freqSwitch('crew')">Crew</button>
      </div>
      <form method="POST" action="{{ route('client-booking-detail', $id) }}">
        @csrf
        <input type="hidden" name="action" value="request_field_item">
        <input type="hidden" name="item_type" id="freqItemType" value="equipment">

        <div id="freqPanel-equipment" class="freq-panel">
          <div style="margin-bottom:14px">
            <label class="form-label">Equipment *</label>
            <select name="equipment_id" id="freqEquipmentSelect" required
                    style="width:100%;background:#f8fafc;border:1.5px solid var(--border);color:var(--text);padding:9px 12px;border-radius:7px;font-size:13px;font-family:var(--font-b);outline:none;box-sizing:border-box">
              <option value="">— Select equipment —</option>
              @foreach ($availEquipForRequest as $eq)
              <option value="{{ $eq->equipment_id }}">
                {{ $eq->equipment_name }}{{ $eq->brand ? ' — ' . $eq->brand . ' ' . $eq->model : '' }} (₱{{ number_format($eq->daily_rate, 2) }}/day)
              </option>
              @endforeach
            </select>
            @if ($availEquipForRequest->isEmpty())
            <div style="font-size:11px;color:var(--muted);margin-top:5px">No additional equipment currently available.</div>
            @endif
          </div>
          <div style="margin-bottom:14px">
            <label class="form-label">Quantity</label>
            <input type="number" name="quantity" id="freqEquipmentQty" value="1" min="1" max="10"
                   style="width:100%;background:#f8fafc;border:1.5px solid var(--border);color:var(--text);padding:9px 12px;border-radius:7px;font-size:13px;font-family:var(--font-b);outline:none;box-sizing:border-box">
          </div>
        </div>

        <div id="freqPanel-accessory" class="freq-panel" style="display:none">
          <div style="margin-bottom:14px">
            <label class="form-label">Accessory *</label>
            <select name="accessory_id" id="freqAccessorySelect" required disabled
                    style="width:100%;background:#f8fafc;border:1.5px solid var(--border);color:var(--text);padding:9px 12px;border-radius:7px;font-size:13px;font-family:var(--font-b);outline:none;box-sizing:border-box">
              <option value="">— Select accessory —</option>
              @foreach ($availAccessoriesForRequest as $acc)
              <option value="{{ $acc->accessory_id }}">{{ $acc->accessory_name }} (₱{{ number_format($acc->daily_rate, 2) }}/day)</option>
              @endforeach
            </select>
            @if ($availAccessoriesForRequest->isEmpty())
            <div style="font-size:11px;color:var(--muted);margin-top:5px">No additional accessories currently available.</div>
            @endif
          </div>
          <div style="margin-bottom:14px">
            <label class="form-label">Quantity</label>
            <input type="number" name="quantity" id="freqAccessoryQty" value="1" min="1" max="10" disabled
                   style="width:100%;background:#f8fafc;border:1.5px solid var(--border);color:var(--text);padding:9px 12px;border-radius:7px;font-size:13px;font-family:var(--font-b);outline:none;box-sizing:border-box">
          </div>
        </div>

        <div id="freqPanel-crew" class="freq-panel" style="display:none">
          <div style="margin-bottom:14px">
            <label class="form-label">Role Needed *</label>
            <select name="position_id" id="freqPositionSelect" required disabled
                    style="width:100%;background:#f8fafc;border:1.5px solid var(--border);color:var(--text);padding:9px 12px;border-radius:7px;font-size:13px;font-family:var(--font-b);outline:none;box-sizing:border-box">
              <option value="">— Select a role —</option>
              @foreach ($crewPositions as $pos)
              <option value="{{ $pos->position_id }}">{{ $pos->position_name }}</option>
              @endforeach
            </select>
            <div style="font-size:11px;color:var(--muted);margin-top:5px">Our team assigns a specific, available crew member for this role.</div>
          </div>
        </div>

        <div style="margin-bottom:16px">
          <label class="form-label">Reason / Notes</label>
          <textarea name="reason" class="form-textarea" placeholder="Describe what you need and why…"></textarea>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end">
          <button type="button" onclick="closeRequestModals()"
                  style="background:var(--s2);color:var(--sub);border:1.5px solid var(--border);padding:9px 18px;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;font-family:var(--font-b)">
            Cancel
          </button>
          <button type="submit"
                  style="background:#16a34a;color:#fff;border:none;padding:9px 20px;border-radius:7px;font-size:13px;font-weight:700;cursor:pointer;font-family:var(--font-b)">
            Submit Request
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
.freq-tab{flex:1;background:var(--s2,#f1f5f9);border:1.5px solid var(--border);color:var(--sub);padding:8px 10px;border-radius:7px;font-size:12.5px;font-weight:700;cursor:pointer;font-family:var(--font-b)}
.freq-tab.on{background:#16a34a;border-color:#16a34a;color:#fff}
</style>
<script>
function freqSwitch(type) {
  document.getElementById('freqItemType').value = type;
  document.querySelectorAll('.freq-tab').forEach(t => t.classList.toggle('on', t.dataset.type === type));
  ['equipment', 'accessory', 'crew'].forEach(t => {
    const panel = document.getElementById('freqPanel-' + t);
    const active = t === type;
    panel.style.display = active ? '' : 'none';
    panel.querySelectorAll('select, input').forEach(el => { el.disabled = !active; });
  });
}
function closeRequestModals() {
  document.getElementById('modalExtendRequest').style.display = 'none';
  document.getElementById('modalEquipRequest').style.display  = 'none';
}

// ── Request Discount: only the field for the selected type is ever shown ───
function toggleDiscReqInput() {
  const type = document.getElementById('discReqType').value;
  const input = document.getElementById('discReqValue');
  const note = document.getElementById('discReqPackageNote');
  const labels = { flat: 'Amount (₱)', percent: 'Percent off (%)', package: 'Target package price (₱)' };
  input.placeholder = labels[type] || 'Amount';
  input.max = type === 'percent' ? '100' : '';
  note.style.display = type === 'package' ? '' : 'none';
}

</script>

</body>
</html>
