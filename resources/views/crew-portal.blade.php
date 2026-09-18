<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Crew Portal — FilmSpec</title>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,600;9..40,700;9..40,800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#ECF2FA;--surface:#fff;--s2:#F0F6FC;--s3:#E5F0FF;
  --border:#CFDFEE;--border2:#B5CFEA;
  --blue:#0060C7;--blue2:#004EA3;--bluelt:#E5F0FF;
  --text:#0B1A33;--sub:#385270;--muted:#7695B0;
  --green:#16a34a;--greenlt:#dcfce7;--red:#dc2626;--redlt:#fee2e2;
  --orange:#c2410c;--orangelt:#ffedd5;--yellow:#b45309;--yellowlt:#fef9c3;--purple:#7c3aed;--purplelt:#ede9fe;
  --font-d:'Bebas Neue',sans-serif;--font-b:'DM Sans',sans-serif;--font-m:'JetBrains Mono',monospace;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font-b);background:var(--bg);color:var(--text);min-height:100vh;-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit}
button{font-family:var(--font-b);cursor:pointer}

/* ── SHELL (mobile-first, widens on larger phones instead of centering with dead space) ── */
.shell{max-width:480px;margin:0 auto;min-height:100vh;background:var(--bg);position:relative;padding-bottom:84px}
@media(min-width:480px) and (max-width:599px){.shell{max-width:560px}}

/* ── TOP BAR ── */
.topbar{background:var(--surface);border-bottom:1px solid var(--border);padding:14px 18px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.brand{font-family:var(--font-d);font-size:20px;letter-spacing:2px;color:var(--blue)}
.av{width:40px;height:40px;border-radius:10px;background:var(--blue);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px;flex-shrink:0}
.logout-btn{width:44px;height:44px;display:flex;align-items:center;justify-content:center;color:var(--muted);border-radius:10px}
.logout-btn:hover{background:var(--s2);color:var(--red)}

/* ── PAGES ── */
.pg{display:none}
.pg.on{display:block}
.ctr{padding:18px 16px 8px}

/* ── ALERT ── */
.alert{padding:13px 16px;border-radius:10px;margin:14px 16px 0;font-size:14px;font-weight:600}
.alert-success{background:var(--greenlt);color:#15803d;border:1px solid #86efac}
.alert-danger{background:var(--redlt);color:#b91c1c;border:1px solid #fca5a5}

/* ── CALL SHEET ── */
.callsheet{margin-bottom:16px;padding:14px 16px;border-radius:14px;background:linear-gradient(135deg,var(--blue),var(--blue2));color:#fff;position:relative;overflow:hidden}
.callsheet::after{content:'';position:absolute;inset:0;background:radial-gradient(circle at 100% 0%,rgba(255,255,255,.14),transparent 60%)}
.cs-label{font-size:11px;letter-spacing:.14em;text-transform:uppercase;font-weight:700;opacity:.8;margin-bottom:5px;position:relative}
.cs-main{font-family:var(--font-d);font-size:19px;letter-spacing:.3px;line-height:1.15;position:relative}
.cs-sub{font-family:var(--font-m);font-size:12.5px;opacity:.9;margin-top:6px;position:relative}

/* ── KPI ── */
.kpis{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px}
.kpi{background:var(--surface);border:1px solid var(--border);border-radius:13px;padding:14px 15px}
.kpi.wide{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between}
.kpi b{font-family:var(--font-d);font-size:27px;color:var(--blue);display:block;line-height:1}
.kpi span{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;display:block;margin-top:5px;font-weight:700}

/* ── CARD ── */
.card{background:var(--surface);border:1px solid var(--border);border-radius:13px;margin-bottom:14px;overflow:hidden}
.ch{padding:14px 16px;border-bottom:1px solid var(--border);font-weight:700;font-size:15px;display:flex;align-items:center;justify-content:space-between;gap:8px;min-height:48px}
.ch-link{font-size:12.5px;color:var(--blue);font-weight:700}

/* ── PROFILE ROW ── */
.prow{display:flex;flex-wrap:wrap;gap:14px;padding:14px 16px}
.pf{min-width:42%}
.pf div:first-child{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;font-weight:700}
.pf div:last-child{font-weight:700;font-size:14px}
.pf.rate div:last-child{font-family:var(--font-m);color:var(--blue)}

/* ── ITEM ROWS (replace tables — one hand, one thumb) ── */
.item{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:flex-start;gap:10px;min-height:52px}
.item:last-child{border-bottom:none}
.it-ref{font-family:var(--font-m);color:var(--blue);font-size:12px;font-weight:700}
.it-title{font-weight:700;font-size:15.5px;margin-top:3px;line-height:1.35;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.it-meta{font-size:13px;color:var(--sub);margin-top:4px;line-height:1.55}
.it-side{text-align:right;flex-shrink:0}
.it-rate{font-family:var(--font-m);font-size:13px;color:var(--blue);font-weight:700;white-space:nowrap}

/* ── SUB-TABS (within a page, e.g. Check-Out/Check-In, Attendance/Timesheets) ── */
.subtabs{display:flex;gap:8px;margin-bottom:14px;overflow-x:auto}
.subtab-btn{font-family:var(--font-b);font-size:13.5px;font-weight:700;padding:10px 18px;border-radius:20px;border:1.5px solid var(--border2);background:var(--surface);color:var(--sub);white-space:nowrap;flex-shrink:0;min-height:40px}
.subtab-btn.on{background:var(--blue);border-color:var(--blue);color:#fff}
.subpanel{display:none}
.subpanel.on{display:block}
.booking-pick-item{padding:14px;border:1.5px solid var(--border2);border-radius:8px;margin-bottom:6px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:10px;transition:border-color .15s,background .15s;min-height:52px}
.booking-pick-item:hover{border-color:var(--blue)}
.booking-pick-item.active{border-color:var(--blue);background:var(--bluelt)}
.booking-pick-item .bp-ref{font-weight:700;font-size:14px}
.booking-pick-item .bp-title{font-size:13px;color:var(--sub);margin-top:2px}
.booking-pick-item .bp-check{color:var(--blue);display:none;flex-shrink:0}
.booking-pick-item.active .bp-check{display:block}
.bp-empty{padding:16px 14px;text-align:center;color:var(--muted);font-size:13px}

/* ── FILTER PILLS ── */
.filter-pills{display:flex;gap:8px;margin-bottom:14px;overflow-x:auto}
.filter-pill{font-family:var(--font-b);font-size:13px;font-weight:700;padding:9px 16px;border-radius:20px;border:1.5px solid var(--border2);background:var(--surface);color:var(--sub);white-space:nowrap;flex-shrink:0;min-height:40px;display:flex;align-items:center}
.filter-pill.on{background:var(--blue);border-color:var(--blue);color:#fff}

/* ── BADGE ── */
.badge{display:inline-flex;align-items:center;padding:4px 11px;border-radius:20px;font-size:11.5px;font-weight:700;letter-spacing:.02em;white-space:nowrap;margin-top:6px}
.badge-green{background:var(--greenlt);color:#15803d}
.badge-blue{background:var(--bluelt);color:var(--blue)}
.badge-yellow{background:var(--yellowlt);color:var(--yellow)}
.badge-red{background:var(--redlt);color:var(--red)}
.badge-orange{background:var(--orangelt);color:var(--orange)}
.badge-gray{background:var(--s2);color:var(--muted)}
.badge-purple{background:var(--purplelt);color:var(--purple)}

/* ── EMPTY STATE ── */
.empty{padding:34px 20px;text-align:center;color:var(--muted);font-size:14px}

/* ── WARNING BANNER ── */
.no-link-banner{background:var(--orangelt);border:1.5px solid #fdba74;border-radius:13px;padding:20px;text-align:center;margin-bottom:16px}

/* ── SECTION HEADER ── */
.sec-hd{margin-bottom:14px}
.sec-title{font-family:var(--font-d);font-size:24px;letter-spacing:.5px}
.sec-sub{font-size:13.5px;color:var(--sub);margin-top:2px}

/* ── TODAY HERO (dashboard) ── */
.today-hero{margin-bottom:14px;padding:24px 22px;border-radius:20px;background:linear-gradient(135deg,var(--blue),var(--blue2));color:#fff;position:relative;overflow:hidden;box-shadow:0 14px 34px rgba(0,96,199,.32)}
.today-hero::after{content:'';position:absolute;inset:0;background:radial-gradient(circle at 100% 0%,rgba(255,255,255,.18),transparent 60%)}
.th-eyebrow{position:relative;display:flex;align-items:center;gap:7px;margin-bottom:14px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;font-weight:700;opacity:.9}
.th-dot{width:7px;height:7px;border-radius:50%;background:#4ade80;flex-shrink:0}
.th-date-row{position:relative;display:flex;align-items:baseline;gap:14px;margin-bottom:6px}
.th-date{font-family:var(--font-d);font-size:52px;line-height:.85}
.th-year{font-family:var(--font-m);font-size:13.5px;opacity:.85}
.th-title{position:relative;font-family:var(--font-d);font-size:24px;letter-spacing:.3px;line-height:1.15;margin-bottom:10px}
.th-foot{position:relative;display:flex;align-items:center;justify-content:space-between;gap:10px;padding-top:14px;border-top:1px solid rgba(255,255,255,.22)}
.th-ref{font-family:var(--font-m);font-size:13px;opacity:.9}
.th-status{padding:6px 13px;border-radius:20px;font-size:11.5px;font-weight:700;background:rgba(255,255,255,.18);white-space:nowrap}

/* ── QUICK ACTIONS ── */
.qa-row{display:flex;gap:10px;margin-bottom:16px}
.qa-btn{flex:1;padding:13px 8px;border-radius:12px;background:var(--surface);border:1px solid var(--border);display:flex;flex-direction:column;align-items:center;gap:6px;font-family:var(--font-b);min-height:52px}
.qa-btn svg{width:22px;height:22px}
.qa-btn span{font-size:12px;font-weight:700;color:var(--text)}

/* ── STAT STRIP ── */
.stat-strip{display:flex;align-items:center;gap:16px;padding:13px 16px;background:var(--surface);border:1px solid var(--border);border-radius:12px;margin-bottom:16px;flex-wrap:wrap}
.stat-strip .stat{display:flex;align-items:baseline;gap:5px}
.stat-strip .stat b{font-family:var(--font-m);font-size:17px;font-weight:700}
.stat-strip .stat span{font-size:11px;color:var(--muted)}
.stat-strip .stat-sep{width:1px;height:14px;background:var(--border)}

/* ── SCHEDULE ITEM (secondary bookings on dashboard) ── */
.sched-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:9px}
.sched-item{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:13px 14px;display:flex;justify-content:space-between;gap:10px;margin-bottom:10px;min-height:52px}
.sched-item:last-child{margin-bottom:0}

/* ── MORE SHEET ── */
.more-group-label{padding:12px 16px 6px;font-size:11.5px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--muted)}
.more-divider{height:1px;background:var(--border);margin:4px 0}
.more-item{display:flex;align-items:center;gap:14px;padding:12px 16px;border-bottom:1px solid var(--border);min-height:56px}
.more-item:last-child{border-bottom:none}
.more-item-ico{width:44px;height:44px;border-radius:12px;background:var(--s2);color:var(--blue);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.more-item span{font-weight:700;font-size:15px}

/* ── MAINTENANCE ICON ── */
.maint-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.maint-icon svg{width:19px;height:19px}

/* ── FAB ── */
.fab{position:sticky;bottom:92px;margin-left:auto;margin-right:16px;width:fit-content;display:flex;justify-content:flex-end;padding-right:0;z-index:50}
.fab button{background:var(--blue);color:#fff;border:none;border-radius:24px;padding:14px 22px;font-size:13.5px;font-weight:700;box-shadow:0 8px 20px rgba(0,96,199,.35);display:flex;align-items:center;gap:8px;min-height:48px}

/* ── BOTTOM TAB BAR ── */
.tabbar{position:fixed;bottom:0;left:50%;transform:translateX(-50%);width:100%;max-width:480px;background:var(--surface);border-top:1px solid var(--border);display:flex;box-shadow:0 -4px 18px rgba(11,26,51,.06);z-index:200}
@media(min-width:480px) and (max-width:599px){.tabbar{max-width:560px}}
.tabbar button{flex:1;background:none;border:none;padding:8px 2px;text-align:center;color:var(--muted);font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;font-family:var(--font-b);min-height:56px}
.tabbar button.on{color:var(--blue)}
.tabbar .ico{display:flex;align-items:center;justify-content:center;margin-bottom:3px}
.tabbar .ico svg{width:20px;height:20px}

/* ── MODAL ── */
.mo{display:none;position:fixed;inset:0;background:rgba(11,26,51,.55);z-index:1000;align-items:flex-end;justify-content:center}
.mo.on{display:flex}
.mo-box{background:var(--surface);border-radius:18px 18px 0 0;width:100%;max-width:480px;max-height:92vh;overflow-y:auto;box-shadow:0 -10px 40px rgba(0,0,0,.25)}
@media(min-width:480px) and (max-width:599px){.mo-box{max-width:560px}}
@media(min-width:600px){.mo{align-items:center}.mo-box{border-radius:16px;max-height:88vh}}
.mo-head{padding:16px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;background:var(--surface)}
.mo-title{font-size:17px;font-weight:800;color:var(--text)}
.mo-close{background:var(--s2);border:none;font-size:17px;cursor:pointer;color:var(--sub);line-height:1;width:44px;height:44px;border-radius:50%}
.mo-body{padding:18px}
.mo-footer{padding:14px 18px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end}

/* ── FORM ── */
.fg{margin-bottom:14px}
.fg label{display:block;font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:700;margin-bottom:6px}
.fg input,.fg select,.fg textarea{width:100%;background:var(--s2);border:1.5px solid var(--border);color:var(--text);padding:12px;border-radius:8px;font-size:14px;font-family:var(--font-b);outline:none;transition:border-color .15s;min-height:44px}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:var(--blue);background:var(--surface)}
.fg textarea{resize:vertical;min-height:84px}
.btn{padding:12px 20px;border-radius:8px;font-size:14px;font-weight:700;border:none;cursor:pointer;transition:all .15s;font-family:var(--font-b);min-height:48px}
.btn-primary{background:var(--blue);color:#fff}.btn-primary:hover{background:var(--blue2)}
.btn-outline{background:none;border:1.5px solid var(--border);color:var(--sub)}.btn-outline:hover{border-color:var(--blue);color:var(--blue)}
.btn-sm{padding:9px 16px;font-size:12.5px;min-height:40px}

/* ── ATTENDANCE: live status summary + per-member card ── */
.att-summary{display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:12px 15px;background:var(--surface);border:1px solid var(--border);border-radius:10px;margin:0 0 12px}
.att-summary .stat{display:flex;align-items:baseline;gap:5px}
.att-summary .stat b{font-family:var(--font-m);font-size:15px}
.att-summary .stat span{font-size:11.5px;color:var(--muted)}
.att-card{border:1px solid var(--border);border-radius:10px;padding:12px 13px;margin-bottom:10px;border-left:3px solid var(--border2);transition:border-left-color .15s}
.att-card[data-status="present"]{border-left-color:var(--green)}
.att-card[data-status="late"]{border-left-color:var(--yellow)}
.att-card[data-status="absent"],.att-card[data-status="no_show"],.att-card[data-status="back_out"]{border-left-color:var(--red)}
.att-member-row{display:flex;align-items:center;justify-content:space-between;gap:10px}
.att-member-name{font-weight:700;font-size:14.5px}
.att-member-role{font-size:12px;color:var(--muted);margin-top:1px}

/* ── ATTENDANCE HISTORY: card-per-day ── */
.att-day-card{border:1px solid var(--border);border-radius:10px;padding:13px 14px;margin-bottom:10px}
.att-day-head{font-weight:700;font-size:14.5px;color:var(--text);margin-bottom:8px}
.att-chip-row{display:flex;flex-wrap:wrap;gap:8px}
.att-person-chip{display:flex;align-items:center;gap:6px;padding:6px 10px 6px 6px;border-radius:999px;background:var(--s2);min-height:32px}
.att-person-chip .mini-av{width:20px;height:20px;border-radius:50%;background:var(--s3);color:var(--sub);display:flex;align-items:center;justify-content:center;font-size:9.5px;font-weight:700;flex-shrink:0}
.att-person-chip span{font-size:12.5px;font-weight:600;color:var(--text)}
.att-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.dot-green{background:var(--green)}.dot-yellow{background:var(--yellow)}.dot-red{background:var(--red)}
</style>
</head>
<body>

<div class="shell">

<!-- TOP BAR -->
<div class="topbar">
  <div class="brand">FILMSPEC</div>
  <div style="display:flex;align-items:center;gap:10px">
    <div style="text-align:right">
      <div style="font-weight:700;font-size:12.5px;line-height:1.3">{{ $crewMember ? $crewMember->first_name.' '.$crewMember->last_name : trim(($user->first_name ?? '').' '.($user->last_name ?? '')) }}</div>
      <div style="font-size:10px;color:var(--muted)">{{ $crewMember ? ($crewMember->position_name ?? 'Crew') : 'Crew' }}</div>
    </div>
    <div class="av">{{ strtoupper(substr($user->first_name ?? 'C', 0, 1)) }}</div>
    <form method="POST" action="{{ route('logout') }}" style="display:contents">
      @csrf
      <button type="submit" title="Logout" class="logout-btn" style="background:none;border:none;cursor:pointer">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg>
      </button>
    </form>
  </div>
</div>

@if ($msg)
<div class="alert alert-{{ $msg['type'] }}">{!! $msg['text'] !!}</div>
@endif

<!-- ═══════ DASHBOARD (Today-First) ═══════ -->
<div id="pg-dashboard" class="pg {{ $activeTab === 'dashboard' ? 'on' : '' }}">
  <div class="ctr">

    @unless ($crewMember)
    <div class="no-link-banner">
      <div style="font-size:1.4rem;margin-bottom:8px">&#9888;</div>
      <div style="font-weight:700;font-size:.95rem;color:var(--orange);margin-bottom:6px">Account Not Linked to Crew Record</div>
      <div style="font-size:.85rem;color:var(--sub)">Your login is not yet linked to a crew member profile. Please ask the Super Admin to link your account to your crew record in the Super Admin panel.</div>
    </div>
    @else

    @php
      // Soonest-upcoming call, not latest — $myBookings itself stays ordered
      // newest-first for the full My Bookings list, this is a display-only re-sort
      // for "what's next" scoped to the dashboard.
      $todayStr = date('Y-m-d');
      $upcoming = $myBookings->whereIn('booking_status', ['confirmed', 'ongoing', 'pending_inspection'])
        ->filter(fn ($bk) => ($bk->shoot_date_end ?: $bk->shoot_date_start) >= $todayStr)
        ->sortBy('shoot_date_start')->values();
      $nextCall = $upcoming->first();
      $laterCalls = $upcoming->slice(1, 2);

      $countdownLabel = null;
      if ($nextCall) {
        $todayTs = strtotime(date('Y-m-d'));
        $startTs = strtotime($nextCall->shoot_date_start);
        $endTs = strtotime($nextCall->shoot_date_end ?: $nextCall->shoot_date_start);
        $daysUntil = (int) round(($startTs - $todayTs) / 86400);
        if ($nextCall->booking_status === 'ongoing' || ($todayTs >= $startTs && $todayTs <= $endTs)) {
          $countdownLabel = 'Happening Now';
        } elseif ($daysUntil === 0) {
          $countdownLabel = 'Today';
        } elseif ($daysUntil === 1) {
          $countdownLabel = 'Tomorrow';
        } elseif ($daysUntil > 1) {
          $countdownLabel = 'In ' . $daysUntil . ' Days';
        }
      }
    @endphp

    @if ($nextCall)
    <div class="today-hero">
      <div class="th-eyebrow"><span class="th-dot"></span>@if($countdownLabel){{ $countdownLabel }} &middot; Your Next Call @else Your Next Call @endif</div>
      <div class="th-date-row">
        <div class="th-date">{{ strtoupper(date('M j', strtotime($nextCall->shoot_date_start))) }}</div>
        <div class="th-year">{{ date('Y', strtotime($nextCall->shoot_date_start)) }}</div>
      </div>
      <div class="th-title">{{ $nextCall->project_title ?: $nextCall->booking_reference }}</div>
      <div class="th-foot">
        <div class="th-ref">{{ $nextCall->booking_reference }}@if(!empty($nextCall->shoot_date_end) && $nextCall->shoot_date_end !== $nextCall->shoot_date_start) &middot; through {{ date('M j', strtotime($nextCall->shoot_date_end)) }}@endif</div>
        <span class="th-status">{{ ucfirst(str_replace('_', ' ', $nextCall->booking_status)) }}</span>
      </div>
    </div>
    @else
    <div class="card" style="padding:28px 20px;text-align:center;margin-bottom:14px">
      <div style="width:52px;height:52px;border-radius:14px;background:var(--bluelt);color:var(--blue);display:flex;align-items:center;justify-content:center;margin:0 auto 14px">
        <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
      </div>
      <div style="font-weight:700;font-size:14.5px;margin-bottom:4px">No Upcoming Calls</div>
      <div style="font-size:12.5px;color:var(--sub)">You'll see your next call here as soon as you're assigned to a booking.</div>
    </div>
    @endif

    @if ($crewAnnouncements->isNotEmpty())
    <div class="card" style="border-left:3px solid var(--blue)">
      <div class="ch">
        <div style="display:flex;align-items:center;gap:8px">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          Announcements
        </div>
      </div>
      @foreach ($crewAnnouncements as $an)
      <div class="item">
        <div>
          <div class="it-title" style="margin-top:0">{{ $an->title }}</div>
          @if ($an->note)<div class="it-meta">{{ $an->note }}</div>@endif
          <div class="it-meta" style="margin-top:4px">
            {{ date('M j, Y', strtotime($an->reminder_date)) }}
            @if ($an->type === 'partners_meeting' && $an->meeting_time) &middot; {{ date('g:i A', strtotime($an->meeting_time)) }} @endif
            @if ($an->location) &middot; {{ $an->location }} @endif
          </div>
        </div>
        <span class="badge {{ $an->type === 'partners_meeting' ? 'badge-purple' : 'badge-blue' }}">{{ $an->type === 'partners_meeting' ? 'Meeting' : 'Notice' }}</span>
      </div>
      @endforeach
    </div>
    @endif

    <div class="qa-row">
      <button class="qa-btn" onclick="showPg('attendance')">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
        <span>My Time</span>
      </button>
      <button class="qa-btn" onclick="openMo('moFileIncident')">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></svg>
        <span>File Report</span>
      </button>
      <button class="qa-btn" onclick="showPg('bookings')">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/></svg>
        <span>Bookings</span>
      </button>
    </div>

    <div class="stat-strip">
      <div class="stat"><b>{{ $myBookings->count() }}</b><span>active</span></div>
      <div class="stat-sep"></div>
      <div class="stat"><b>{{ $myMaintenances->count() }}</b><span>maintenance</span></div>
      <div class="stat-sep"></div>
      <div class="stat"><b>{{ $myIncidents->count() }}</b><span>reports</span></div>
    </div>

    @if ($laterCalls->isNotEmpty())
    <div class="sched-lbl">Also On Your Schedule</div>
    @foreach ($laterCalls as $bk)
    <div class="sched-item">
      <div>
        <div class="it-ref">{{ $bk->booking_reference }}</div>
        <div class="it-title">{{ $bk->project_title ?: '—' }}</div>
        <div class="it-meta">{{ date('M j', strtotime($bk->shoot_date_start)) }} – {{ date('M j, Y', strtotime($bk->shoot_date_end)) }}</div>
      </div>
      <div class="it-side">
        <div class="it-rate">{{ $bk->rate_used ? '₱'.number_format($bk->rate_used, 2) : '—' }}</div>
      </div>
    </div>
    @endforeach
    @endif

    @if ($myMaintenances->isNotEmpty())
    @php $mt = $myMaintenances->first(); @endphp
    <div class="sched-lbl" style="margin-top:{{ $laterCalls->isNotEmpty() ? '16px' : '0' }}">Needs Your Attention</div>
    <div class="sched-item" onclick="showPg('maintenance')" style="cursor:pointer">
      <div style="display:flex;gap:12px;align-items:flex-start">
        <div class="maint-icon" style="background:{{ $mt->maintenance_type==='preventive'?'var(--yellowlt)':'var(--bluelt)' }};color:{{ $mt->maintenance_type==='preventive'?'var(--yellow)':'var(--blue)' }}">
          @if($mt->maintenance_type==='calibration')
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="0.6" fill="currentColor"/></svg>
          @elseif($mt->maintenance_type==='cleaning')
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9.5 9.5 4 15a2.1 2.1 0 1 0 3 3l5.5-5.5"/><path d="M14.5 6.5a3 3 0 1 1 3 3l-2 2-3-3z"/></svg>
          @else
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76Z"/></svg>
          @endif
        </div>
        <div>
          <div class="it-title">{{ $mt->equipment_name }} needs maintenance</div>
          <div class="it-meta">{{ $maintLabel[$mt->maintenance_type] }} &middot; due {{ date('M j', strtotime($mt->scheduled_date)) }}</div>
        </div>
      </div>
    </div>
    @endif

    @endunless
  </div>
</div>

<!-- ═══════ BOOKINGS ═══════ -->
<div id="pg-bookings" class="pg {{ $activeTab === 'bookings' ? 'on' : '' }}">
  <div class="ctr">
    <div class="sec-hd">
      <div class="sec-title">My Bookings</div>
      <div class="sec-sub">Every booking you're assigned to.</div>
    </div>
    @if ($myBookings->isNotEmpty())
    <div class="filter-pills" id="bookingFilterPills">
      <button type="button" class="filter-pill on" onclick="filterBookings('all',this)">All</button>
      <button type="button" class="filter-pill" onclick="filterBookings('confirmed',this)">Upcoming</button>
      <button type="button" class="filter-pill" onclick="filterBookings('ongoing,pending_inspection',this)">Active</button>
      <button type="button" class="filter-pill" onclick="filterBookings('returned',this)">Completed</button>
    </div>
    @endif
    <div class="card">
      @if ($myBookings->isEmpty())
      <div class="empty">No bookings assigned to you.</div>
      @else
      @foreach ($myBookings as $bk)
      <div class="item" data-status="{{ $bk->booking_status }}">
        <div>
          <div class="it-ref">{{ $bk->booking_reference }}</div>
          <div class="it-title">{{ $bk->project_title ?: '—' }}</div>
          <div class="it-meta">
            {{ $bk->company_name ?: $bk->contact_person }}@if($bk->shoot_location) &middot; {{ $bk->shoot_location }}@endif<br>
            {{ date('M j', strtotime($bk->shoot_date_start)) }} – {{ date('M j, Y', strtotime($bk->shoot_date_end)) }}
            @if($bk->position_name) &middot; {{ $bk->position_name }}@endif
          </div>
        </div>
        <div class="it-side">
          <div class="it-rate">{{ $bk->rate_used ? '₱'.number_format($bk->rate_used, 2) : '—' }}</div>
          @if($bk->hours_worked)<div style="font-size:11px;color:var(--muted);margin-top:2px">{{ $bk->hours_worked }}hr</div>@endif
          <span class="badge {{ $bookingBadge[$bk->booking_status] ?? 'badge-gray' }}">{{ ucfirst($bk->booking_status) }}</span>
        </div>
      </div>
      @endforeach
      @endif
    </div>
  </div>
</div>

<!-- ═══════ ATTENDANCE ═══════ -->
<div id="pg-attendance" class="pg {{ $activeTab === 'attendance' ? 'on' : '' }}">
  <div class="ctr">
    <div class="sec-hd">
      <div class="sec-title">Attendance &amp; Timesheets</div>
      <div class="sec-sub">Your logged attendance and time records, as recorded by staff.</div>
    </div>

    @php $attSubTab = in_array($activeSubTab, ['attendance', 'timesheets'], true) ? $activeSubTab : 'attendance'; @endphp
    <div class="subtabs">
      <button type="button" class="subtab-btn {{ $attSubTab === 'attendance' ? 'on' : '' }}" data-group="attendance" onclick="showSubTab('attendance','attendance',this)">Attendance</button>
      <button type="button" class="subtab-btn {{ $attSubTab === 'timesheets' ? 'on' : '' }}" data-group="attendance" onclick="showSubTab('attendance','timesheets',this)">Timesheets</button>
    </div>

    <div class="subpanel {{ $attSubTab === 'attendance' ? 'on' : '' }}" data-group="attendance" id="sub-attendance-attendance">
    @if ($myTeamBookings->isNotEmpty())
    <div class="card">
      <div class="ch">Mark Today's Attendance</div>
      <div style="padding:12px 16px 0;font-size:12px;color:var(--sub)">As the crew member on record for this booking, you can mark attendance for the whole field team — not just yourself.</div>
      <div class="fg" style="padding:12px 16px 0;margin-bottom:8px">
        <label>Search Bookings</label>
        <input type="text" id="attBookingSearch" placeholder="Search by reference or project title…" oninput="filterBookingList('att', this.value)">
      </div>
      <div style="padding:0 16px 4px">
        @foreach ($myTeamBookings as $tb)
        <div class="booking-pick-item {{ $activeBookingId === $tb->booking->booking_id ? 'active' : '' }}" data-kind="att" data-booking="{{ $tb->booking->booking_id }}" data-search="{{ strtolower($tb->booking->booking_reference.' '.($tb->booking->project_title ?: '')) }}" onclick="selectBooking('att', {{ $tb->booking->booking_id }}, this)">
          <div>
            <div class="bp-ref">{{ $tb->booking->booking_reference }}</div>
            <div class="bp-title">{{ $tb->booking->project_title ?: 'Untitled' }}</div>
          </div>
          <svg class="bp-check" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        @endforeach
        <div class="bp-empty" id="attBookingEmpty" style="display:none">No bookings match your search.</div>
      </div>
      @foreach ($myTeamBookings as $tb)
      <div class="att-booking-panel" data-booking="{{ $tb->booking->booking_id }}" style="display:none">
      <form method="POST" style="padding:12px 16px 16px">
        @csrf
        <input type="hidden" name="action" value="crew_mark_attendance">
        <input type="hidden" name="return_tab" value="attendance">
        <input type="hidden" name="return_subtab" value="attendance">
        <input type="hidden" name="return_booking_id" value="{{ $tb->booking->booking_id }}">
        <input type="hidden" name="booking_id" value="{{ $tb->booking->booking_id }}">
        <input type="hidden" name="attendance_date" value="{{ date('Y-m-d') }}">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2px">
          <div style="font-weight:700;font-size:13px">{{ $tb->booking->booking_reference }}</div>
          <a href="{{ route('crew-portal.attendance-print', ['booking_id' => $tb->booking->booking_id]) }}" target="_blank" style="color:var(--muted);display:flex;align-items:center" title="Print attendance record">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
          </a>
        </div>
        <div class="it-meta" style="margin-bottom:12px">{{ $tb->booking->project_title ?: '—' }} &middot; {{ date('M j, Y') }}</div>
        @if (empty($tb->team))
        <div class="empty" style="padding:16px 0">No crew assigned to this booking yet.</div>
        @else
        @php
          $presentN = collect($tb->team)->filter(fn ($t) => ($t->existing_attendance->status ?? 'present') === 'present')->count();
          $lateN = collect($tb->team)->filter(fn ($t) => ($t->existing_attendance->status ?? 'present') === 'late')->count();
          $flagN = collect($tb->team)->filter(fn ($t) => in_array($t->existing_attendance->status ?? 'present', ['absent', 'no_show', 'back_out'], true))->count();
        @endphp
        <div class="att-summary" id="attSummary{{ $tb->booking->booking_id }}">
          <div class="stat"><b style="color:var(--green)">{{ $presentN }}</b><span>Present</span></div>
          <div class="stat"><b style="color:var(--yellow)">{{ $lateN }}</b><span>Late</span></div>
          <div class="stat"><b style="color:var(--red)">{{ $flagN }}</b><span>Flagged</span></div>
          <div class="stat"><b style="color:var(--muted)">{{ count($tb->team) }}</b><span>Team</span></div>
        </div>
        @foreach ($tb->team as $tm)
        @php $ex = $tm->existing_attendance; @endphp
        <div class="att-card" id="attCard{{ $tm->crew_id }}" data-status="{{ $ex->status ?? 'present' }}">
          <div class="att-member-row">
            <div>
              <div class="att-member-name">{{ $tm->crew_name }}</div>
              <div class="att-member-role">{{ $tm->position_name ?? '—' }}</div>
            </div>
            <div class="fg" style="margin-bottom:0;min-width:150px">
              <select name="crew_status[{{ $tm->crew_id }}]" onchange="toggleAttFields({{ $tm->crew_id }}, this.value);updateAttCard({{ $tm->crew_id }}, this.value);updateAttSummary({{ $tb->booking->booking_id }})">
                @foreach ($attStatusLabel as $val => $lbl)
                <option value="{{ $val }}" {{ ($ex->status ?? 'present') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div id="attReason{{ $tm->crew_id }}" class="fg" style="margin-bottom:0;margin-top:10px;display:{{ in_array($ex->status ?? 'present', ['present','late'], true) ? 'none' : 'block' }}">
            <input type="text" name="crew_reason[{{ $tm->crew_id }}]" value="{{ $ex->reason ?? '' }}" placeholder="Reason…">
          </div>
          <div id="attRepl{{ $tm->crew_id }}" class="fg" style="margin-bottom:0;margin-top:10px;display:{{ in_array($ex->status ?? 'present', ['no_show','back_out'], true) ? 'block' : 'none' }}">
            <select name="crew_replacement[{{ $tm->crew_id }}]">
              <option value="">— Replacement (optional) —</option>
              @foreach ($allActiveCrew as $ac)
              @if ($ac->crew_id != $tm->crew_id)
              <option value="{{ $ac->crew_id }}" {{ ($ex->replacement_crew_id ?? null) == $ac->crew_id ? 'selected' : '' }}>{{ $ac->name }}</option>
              @endif
              @endforeach
            </select>
          </div>
        </div>
        @endforeach
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:4px">Save Attendance</button>
        @endif
      </form>
      </div><!-- /att-booking-panel -->
      @endforeach
    </div>
    @endif

    <div class="card">
      <div class="ch">Attendance History</div>
      @if ($myAttendance->isEmpty())
      <div class="empty">No attendance records yet.</div>
      @else
      @foreach ($myAttendance as $att)
      @php
        $attBadge = ['present' => 'badge-green', 'late' => 'badge-yellow', 'absent' => 'badge-red', 'no_show' => 'badge-red', 'back_out' => 'badge-orange'];
      @endphp
      <div class="item">
        <div>
          <div class="it-ref">{{ date('M j, Y', strtotime($att->attendance_date)) }}</div>
          <div class="it-meta" style="margin-top:4px">{{ $att->booking_reference }} — {{ $att->project_title ?: '—' }}</div>
          @if ($att->reason)<div class="it-meta">{{ $att->reason }}</div>@endif
        </div>
        <span class="badge {{ $attBadge[$att->status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_', ' ', $att->status)) }}</span>
      </div>
      @endforeach
      @endif
    </div>
    </div><!-- /sub-attendance-attendance -->

    <div class="subpanel {{ $attSubTab === 'timesheets' ? 'on' : '' }}" data-group="attendance" id="sub-attendance-timesheets">
    <div class="card">
      <div class="ch">Timesheets</div>
      @if ($myTimesheets->isEmpty())
      <div class="empty">No timesheet records yet.</div>
      @else
      @foreach ($myTimesheets as $ts)
      <div class="item">
        <div>
          <div class="it-ref">{{ date('M j, Y', strtotime($ts->timesheet_date)) }}</div>
          <div class="it-meta" style="margin-top:4px">{{ $ts->booking_reference }} — {{ $ts->project_title ?: '—' }}</div>
          <div class="it-meta">
            {{ $ts->time_in ? date('g:i A', strtotime($ts->time_in)) : '—' }} – {{ $ts->time_out ? date('g:i A', strtotime($ts->time_out)) : '—' }}
            @if ($ts->remarks) &middot; {{ $ts->remarks }}@endif
          </div>
        </div>
        <div class="it-side">
          <div class="it-rate">{{ $ts->total_hours ?? '—' }}hr</div>
          @if ($ts->overtime_hours > 0)<div style="font-size:10.5px;color:var(--orange);margin-top:2px;font-weight:700">+{{ $ts->overtime_hours }}hr OT</div>@endif
          @if ($ts->is_double_pay)<span class="badge badge-purple">2x Pay</span>@endif
        </div>
      </div>
      @endforeach
      @endif
    </div>
    </div><!-- /sub-attendance-timesheets -->
  </div>
</div>

<!-- ═══════ EQUIPMENT CHECKLIST ═══════ -->
<div id="pg-checklist" class="pg {{ $activeTab === 'checklist' ? 'on' : '' }}">
  <div class="ctr">
    <div class="sec-hd">
      <div class="sec-title">Equipment Checklist</div>
      <div class="sec-sub">Confirm equipment released to you (out) and returned to the company (in). Something actually wrong with an item? File an Incident Report instead.</div>
    </div>

    @php
      $outBookings = $myChecklistBookings->where('direction', 'out')->values();
      $inBookings = $myChecklistBookings->where('direction', 'in')->values();
      $checklistSubTab = in_array($activeSubTab, ['out', 'in'], true) ? $activeSubTab : 'out';
    @endphp
    <div class="subtabs">
      <button type="button" class="subtab-btn {{ $checklistSubTab === 'out' ? 'on' : '' }}" data-group="checklist" onclick="showSubTab('checklist','out',this)">Check-Out ({{ $outBookings->count() }})</button>
      <button type="button" class="subtab-btn {{ $checklistSubTab === 'in' ? 'on' : '' }}" data-group="checklist" onclick="showSubTab('checklist','in',this)">Check-In ({{ $inBookings->count() }})</button>
    </div>

    @foreach (['out' => $outBookings, 'in' => $inBookings] as $dir => $bookingsForDir)
    <div class="subpanel {{ $checklistSubTab === $dir ? 'on' : '' }}" data-group="checklist" id="sub-checklist-{{ $dir }}">
      @if ($bookingsForDir->isEmpty())
      <div class="card"><div class="empty">Nothing to check {{ $dir }} right now.</div></div>
      @else
      <div class="card">
        <div class="fg" style="padding:14px 16px 8px;margin-bottom:0">
          <label>Search Bookings</label>
          <input type="text" id="checklistSearch-{{ $dir }}" placeholder="Search by reference or project title…" oninput="filterBookingList('checklist-{{ $dir }}', this.value)">
        </div>
        <div style="padding:0 16px 12px">
          @foreach ($bookingsForDir as $cb)
          <div class="booking-pick-item {{ $activeBookingId === $cb->booking->booking_id ? 'active' : '' }}" data-kind="checklist-{{ $dir }}" data-booking="{{ $cb->booking->booking_id }}" data-search="{{ strtolower($cb->booking->booking_reference.' '.($cb->booking->project_title ?: '')) }}" onclick="selectBooking('checklist-{{ $dir }}', {{ $cb->booking->booking_id }}, this)">
            <div>
              <div class="bp-ref">{{ $cb->booking->booking_reference }}</div>
              <div class="bp-title">{{ $cb->booking->project_title ?: 'Untitled' }}</div>
            </div>
            <svg class="bp-check" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          </div>
          @endforeach
          <div class="bp-empty" id="checklistBookingEmpty-{{ $dir }}" style="display:none">No bookings match your search.</div>
        </div>
      </div>
      @foreach ($bookingsForDir as $cb)
      @php
        $doneCount = collect($cb->items)->filter(fn ($it) => $dir === 'out' ? $it->co_checked : $it->ci_checked)->count();
        $totalCount = count($cb->items);
      @endphp
      <div class="card checklist-booking-panel" data-dir="{{ $dir }}" data-booking="{{ $cb->booking->booking_id }}" style="display:none">
        <div class="ch">
          <div>
            <div>{{ $cb->booking->booking_reference }}</div>
            <div style="font-size:10.5px;color:var(--muted);font-weight:400;margin-top:2px">{{ $cb->booking->project_title ?: '—' }}</div>
          </div>
          <div style="display:flex;align-items:center;gap:8px">
            <a href="{{ route('crew-portal.checklist-print', ['booking_id' => $cb->booking->booking_id]) }}" target="_blank" style="color:var(--muted);display:flex;align-items:center" title="Print checklist">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            </a>
            <span class="badge {{ $dir === 'out' ? 'badge-blue' : 'badge-orange' }}">{{ $dir === 'out' ? 'Check-Out' : 'Check-In' }} {{ $doneCount }}/{{ $totalCount }}</span>
          </div>
        </div>
        @if (empty($cb->items))
        <div class="empty">No equipment on this booking.</div>
        @else
        <form method="POST">
          @csrf
          <input type="hidden" name="action" value="crew_save_checklist">
          <input type="hidden" name="return_tab" value="checklist">
          <input type="hidden" name="return_subtab" value="{{ $dir }}">
          <input type="hidden" name="return_booking_id" value="{{ $cb->booking->booking_id }}">
          <input type="hidden" name="booking_id" value="{{ $cb->booking->booking_id }}">
          <input type="hidden" name="direction" value="{{ $dir }}">
          @foreach ($cb->items as $it)
          @php $isChecked = $dir === 'out' ? $it->co_checked : $it->ci_checked; @endphp
          <label class="item" style="cursor:pointer">
            <input type="checkbox" name="items[]" value="{{ $it->equipment_id }}" {{ $isChecked ? 'checked' : '' }} style="width:22px;height:22px;accent-color:var(--blue);flex-shrink:0">
            <span style="flex:1">
              <span class="it-title" style="margin-top:0">{{ $it->equipment_name }}</span>
              <span class="it-meta">{{ $it->brand }} {{ $it->model }}</span>
            </span>
            <span class="it-side" style="font-family:var(--font-m);font-size:11.5px;color:var(--muted)">Qty {{ $it->quantity }}</span>
          </label>
          @endforeach
          <div style="padding:14px 16px">
            <button type="submit" class="btn btn-primary" style="width:100%">Save {{ $dir === 'out' ? 'Check-Out' : 'Check-In' }}</button>
          </div>
        </form>
        @endif
      </div>
      @endforeach
      @endif
    </div>
    @endforeach
  </div>
</div>

<!-- ═══════ INCIDENTS ═══════ -->
<div id="pg-incidents" class="pg {{ $activeTab === 'incidents' ? 'on' : '' }}">
  <div class="ctr">
    <div class="sec-hd">
      <div class="sec-title">Incident Reports</div>
      <div class="sec-sub">File and track equipment incidents. Charges are determined by the admin.</div>
    </div>

    <div class="card">
      @if ($myIncidents->isEmpty())
      <div class="empty">You have not filed any incident reports.</div>
      @else
      @foreach ($myIncidents as $ir)
      <div class="item">
        <div>
          <div class="it-ref">{{ $ir->incident_number ?? '—' }}</div>
          <div class="it-title">{{ $ir->equipment_name }}</div>
          <div class="it-meta">{{ $ir->booking_reference }} &middot; {{ date('M j, Y', strtotime($ir->created_at)) }}</div>
          <span class="badge {{ $typeBadge[$ir->incident_type] ?? 'badge-gray' }}">{{ $typeLabel[$ir->incident_type] ?? $ir->incident_type }}</span>
        </div>
        <div class="it-side">
          <div class="it-rate" style="color:{{ $ir->charge_amount>0?'var(--orange)':'var(--muted)' }}">{{ $ir->charge_amount > 0 ? '₱'.number_format($ir->charge_amount,2) : '—' }}</div>
          <span class="badge {{ $statusBadge[$ir->status] ?? 'badge-gray' }}">{{ ucfirst($ir->status) }}</span>
        </div>
      </div>
      @endforeach
      @endif
    </div>

    <div style="background:var(--s2);border:1px solid var(--border);border-radius:10px;padding:14px 16px;font-size:12px;color:var(--sub);margin-bottom:14px">
      <strong style="color:var(--yellow)">Note:</strong> Incident charges are determined by the Operations Manager or Admin after reviewing your report. You will be notified if charges are applied.
    </div>
  </div>
  <div class="fab"><button onclick="openMo('moFileIncident')">+ File Incident Report</button></div>
</div>

<!-- ═══════ FIELD REQUESTS ═══════ -->
<div id="pg-fieldrequests" class="pg {{ $activeTab === 'fieldrequests' ? 'on' : '' }}">
  <div class="ctr">
    <div class="sec-hd">
      <div class="sec-title">Field Requests</div>
      <div class="sec-sub">Need something extra sent out to your shoot? Request it here — the admin reviews, then dispatches with a driver and ETA.</div>
    </div>

    @php
      $freqStatusLabel = ['pending' => 'Pending Review', 'approved' => 'Approved', 'dispatched' => 'Dispatched', 'delivered' => 'Delivered', 'rejected' => 'Rejected'];
      $freqStatusBadge = ['pending' => 'badge-yellow', 'approved' => 'badge-green', 'dispatched' => 'badge-blue', 'delivered' => 'badge-gray', 'rejected' => 'badge-red'];
    @endphp
    <div class="card">
      @if ($myFieldRequests->isEmpty())
      <div class="empty">You have not made any field requests.</div>
      @else
      @foreach ($myFieldRequests as $fr)
      @php $frItemName = $fr->item_type === 'equipment' ? $fr->equipment_name : ($fr->item_type === 'accessory' ? $fr->accessory_name : $fr->position_name); @endphp
      <div class="item">
        <div>
          <div class="it-title">{{ $frItemName }}{{ $fr->quantity > 1 ? ' ×' . $fr->quantity : '' }}</div>
          <div class="it-meta">{{ $fr->booking_reference }} &middot; {{ date('M j, Y', strtotime($fr->created_at)) }}</div>
          <span class="badge badge-gray" style="text-transform:capitalize">{{ $fr->item_type }}</span>
        </div>
        <div class="it-side">
          <span class="badge {{ $freqStatusBadge[$fr->status] ?? 'badge-gray' }}">{{ $freqStatusLabel[$fr->status] ?? ucfirst($fr->status) }}</span>
        </div>
      </div>
      @endforeach
      @endif
    </div>
  </div>
  <div class="fab"><button onclick="openMo('moFieldRequest')">+ Request Item</button></div>
</div>

<!-- ═══════ MAINTENANCE ═══════ -->
<div id="pg-maintenance" class="pg {{ $activeTab === 'maintenance' ? 'on' : '' }}">
  <div class="ctr">
    <div class="sec-hd">
      <div class="sec-title">Maintenance</div>
      <div class="sec-sub">Equipment maintenance tasks assigned to you by the admin.</div>
    </div>

    @if ($myMaintenances->isEmpty())
    <div class="card"><div class="empty">No pending maintenance tasks assigned to you.</div></div>
    @else
    @foreach ($myMaintenances as $mt)
    <div class="card">
      <div class="ch">
        <div style="display:flex;align-items:center;gap:10px">
          <div class="maint-icon" style="background:{{ $mt->maintenance_type==='preventive'?'var(--yellowlt)':'var(--bluelt)' }};color:{{ $mt->maintenance_type==='preventive'?'var(--yellow)':'var(--blue)' }}">
            {{ $mt->maintenance_type==='preventive'?'🔧':($mt->maintenance_type==='corrective'?'🛠️':($mt->maintenance_type==='calibration'?'🎯':'🧹')) }}
          </div>
          <div>
            <div style="font-weight:700">{{ $mt->equipment_name }}</div>
            <div style="font-size:.78rem;color:var(--sub)">{{ $mt->brand }} {{ $mt->serial_number ? '· SN: '.$mt->serial_number : '' }}</div>
          </div>
        </div>
        <span class="badge {{ $maintBadge[$mt->status] }}">{{ ucfirst(str_replace('_',' ',$mt->status)) }}</span>
      </div>
      <div style="padding:14px 16px">
        <div style="display:flex;flex-wrap:wrap;gap:20px;margin-bottom:10px">
          <div><div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:2px;font-weight:700">Type</div>
            <span class="badge badge-blue" style="margin-top:0">{{ $maintLabel[$mt->maintenance_type] }}</span></div>
          <div><div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:2px;font-weight:700">Scheduled</div>
            <div style="font-family:var(--font-m);font-size:.85rem;color:var(--blue)">{{ date('M j, Y', strtotime($mt->scheduled_date)) }}</div></div>
        </div>
        @if ($mt->description)
        <div style="font-size:.85rem;color:var(--sub);margin-top:6px">{{ $mt->description }}</div>
        @endif
        @if ($mt->notes)
        <div style="font-size:.8rem;color:var(--muted);margin-top:6px;font-style:italic">{{ $mt->notes }}</div>
        @endif
        <button class="btn btn-outline btn-sm" style="margin-top:12px;width:100%" onclick="openUpdateMaint({{ $mt->schedule_id }},'{{ $mt->status }}')">Update Status</button>
      </div>
    </div>
    @endforeach
    @endif
  </div>
</div>

<!-- BOTTOM TAB BAR (3 tabs — Time/Reports/Repairs live in the More sheet) -->
<div class="tabbar">
  <button class="{{ $activeTab === 'dashboard' ? 'on' : '' }}" data-tab="dashboard" onclick="showPg('dashboard',this)"><span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l9-8 9 8"/><path d="M5 10v10a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V10"/></svg></span>Today</button>
  <button class="{{ $activeTab === 'bookings' ? 'on' : '' }}" data-tab="bookings" onclick="showPg('bookings',this)"><span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg></span>Schedule</button>
  <button data-tab="more" onclick="openMo('moMore')"><span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/></svg></span>More</button>
</div>

</div><!-- /shell -->

<!-- ═══ SHEET: More ═══ -->
<div class="mo" id="moMore" onclick="if(event.target===this)closeMo('moMore')">
  <div class="mo-box" style="max-width:420px">
    <div class="mo-head">
      <div class="mo-title">More</div>
      <button class="mo-close" onclick="closeMo('moMore')">&times;</button>
    </div>
    <div>
      <div class="more-group-label">My Shift</div>
      <div class="more-item" style="cursor:pointer" onclick="closeMo('moMore');showPg('attendance')">
        <div class="more-item-ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg></div>
        <span>Attendance &amp; Timesheets</span>
      </div>
      <div class="more-item" style="cursor:pointer" onclick="closeMo('moMore');showPg('checklist')">
        <div class="more-item-ico"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></div>
        <span>Equipment Checklist</span>
      </div>
      <div class="more-divider"></div>
      <div class="more-group-label">Report Something</div>
      <div class="more-item" style="cursor:pointer" onclick="closeMo('moMore');showPg('incidents')">
        <div class="more-item-ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/></svg></div>
        <span>Incident Reports</span>
      </div>
      <div class="more-item" style="cursor:pointer" onclick="closeMo('moMore');showPg('fieldrequests')">
        <div class="more-item-ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3c-.6.6-.2 1.7.7 1.7H17"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg></div>
        <span>Field Requests</span>
      </div>
      <div class="more-item" style="cursor:pointer" onclick="closeMo('moMore');showPg('maintenance')">
        <div class="more-item-ico"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></div>
        <span>Maintenance</span>
      </div>
      <form method="POST" action="{{ route('logout') }}" style="display:contents">
      @csrf
      <button type="submit" class="more-item" style="color:var(--red);background:none;border:none;cursor:pointer;width:100%;text-align:left;font:inherit">
        <div class="more-item-ico" style="background:var(--redlt);color:var(--red)"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg></div>
        <span>Logout</span>
      </button>
      </form>
    </div>
  </div>
</div>

<!-- ═══ MODAL: File Incident Report ═══ -->
<div class="mo" id="moFileIncident" onclick="if(event.target===this)closeMo('moFileIncident')">
  <div class="mo-box">
    <div class="mo-head">
      <div class="mo-title">File Incident Report</div>
      <button class="mo-close" onclick="closeMo('moFileIncident')">&times;</button>
    </div>
    <form method="POST">
      @csrf
      <input type="hidden" name="action" value="crew_file_incident">
      <input type="hidden" name="return_tab" id="incidentReturnTab" value="{{ $activeTab }}">
      <div class="mo-body">
        <div style="background:var(--yellowlt);border:1px solid #fde68a;border-radius:8px;padding:10px 13px;margin-bottom:16px;font-size:.82rem;color:var(--yellow)">
          <strong>Charge Note:</strong> Do not enter any charge amount — charges are set by the Operations Manager after review.
        </div>
        <div class="fg">
          <label>Booking *</label>
          <select name="booking_id" id="crewIR_booking" required onchange="filterEquipmentByBooking(this.value)">
            <option value="">— Select Booking —</option>
            @foreach ($myActiveBookings as $bk)
            <option value="{{ $bk->booking_id }}">{{ $bk->booking_reference }} — {{ $bk->project_title }}</option>
            @endforeach
          </select>
        </div>
        <div class="fg">
          <label>Equipment *</label>
          <select name="equipment_id" id="crewIR_equipment" required disabled>
            <option value="">— Select a booking first —</option>
          </select>
        </div>
        <div style="display:flex;gap:10px">
          <div class="fg" style="flex:1">
            <label>Incident Type *</label>
            <select name="incident_type" required>
              <option value="damaged">Damaged</option>
              <option value="missing">Missing</option>
              <option value="malfunction">Malfunction</option>
              <option value="late_return">Late Return</option>
            </select>
          </div>
          <div class="fg" style="flex:1">
            <label>Incident Date *</label>
            <input type="date" name="incident_date" value="{{ date('Y-m-d') }}" required>
          </div>
        </div>
        <div class="fg">
          <label>Cause</label>
          <select name="cause">
            <option value="accident">Accident</option>
            <option value="negligence">Negligence</option>
            <option value="lifespan">End of Lifespan</option>
            <option value="unknown">Unknown</option>
          </select>
        </div>
        <div class="fg">
          <label>Damage Description (check all that apply)</label>
          <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:4px">
            @foreach ($damageTypes as $val => $lbl)
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:500;color:var(--sub)">
              <input type="checkbox" name="damage_types[]" value="{{ $val }}" style="width:19px;height:19px;accent-color:var(--blue)">
              {{ $lbl }}
            </label>
            @endforeach
          </div>
          <input type="text" name="damage_others_note" placeholder="If Others — describe here" style="margin-top:8px">
        </div>
        <div class="fg">
          <label>Description / What happened *</label>
          <textarea name="description" placeholder="Describe what happened in detail…" required></textarea>
        </div>
      </div>
      <div class="mo-footer">
        <button type="button" class="btn btn-outline" onclick="closeMo('moFileIncident')">Cancel</button>
        <button type="submit" class="btn btn-primary">Submit Report</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ MODAL: Request Field Item ═══ -->
<div class="mo" id="moFieldRequest" onclick="if(event.target===this)closeMo('moFieldRequest')">
  <div class="mo-box">
    <div class="mo-head">
      <div class="mo-title">Request Equipment, Accessory, or Crew</div>
      <button class="mo-close" onclick="closeMo('moFieldRequest')">&times;</button>
    </div>
    <form method="POST">
      @csrf
      <input type="hidden" name="action" value="crew_request_field_item">
      <input type="hidden" name="return_tab" value="fieldrequests">
      <input type="hidden" name="item_type" id="freqItemType" value="equipment">
      <div class="mo-body">
        <div class="fg">
          <label>Booking *</label>
          <select name="booking_id" id="freqBooking" required onchange="freqFilterByBooking(this.value)">
            <option value="">— Select Booking —</option>
            @foreach ($myTeamBookings as $tb)
            <option value="{{ $tb->booking->booking_id }}">{{ $tb->booking->booking_reference }} — {{ $tb->booking->project_title ?: 'Untitled' }}</option>
            @endforeach
          </select>
        </div>
        <div class="subtabs" style="margin-bottom:14px">
          <button type="button" class="subtab-btn on" data-freq="equipment" onclick="freqSwitch('equipment', this)">Equipment</button>
          <button type="button" class="subtab-btn" data-freq="accessory" onclick="freqSwitch('accessory', this)">Accessory</button>
          <button type="button" class="subtab-btn" data-freq="crew" onclick="freqSwitch('crew', this)">Crew</button>
        </div>

        <div id="freqPanel-equipment">
          <div class="fg">
            <label>Equipment *</label>
            <select name="equipment_id" id="freqEquipment" disabled>
              <option value="">— Select a booking first —</option>
            </select>
          </div>
          <div class="fg">
            <label>Quantity</label>
            <input type="number" name="quantity" id="freqEquipQty" value="1" min="1" max="10">
          </div>
        </div>

        <div id="freqPanel-accessory" style="display:none">
          <div class="fg">
            <label>Accessory *</label>
            <select name="accessory_id" id="freqAccessory" disabled>
              <option value="">— Select a booking first —</option>
            </select>
          </div>
        </div>

        <div id="freqPanel-crew" style="display:none">
          <div class="fg">
            <label>Role Needed *</label>
            <select name="position_id" id="freqPosition">
              <option value="">— Select a role —</option>
              @foreach ($crewPositions as $pos)
              <option value="{{ $pos->position_id }}">{{ $pos->position_name }}</option>
              @endforeach
            </select>
            <div style="font-size:11px;color:var(--muted);margin-top:5px">The admin assigns a specific, available crew member for this role.</div>
          </div>
        </div>

        <div class="fg">
          <label>Reason / Notes</label>
          <textarea name="reason" placeholder="Describe what you need and why…"></textarea>
        </div>
      </div>
      <div class="mo-footer">
        <button type="button" class="btn btn-outline" onclick="closeMo('moFieldRequest')">Cancel</button>
        <button type="submit" class="btn btn-primary">Submit Request</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ MODAL: Update Maintenance ═══ -->
<div class="mo" id="moUpdateMaint" onclick="if(event.target===this)closeMo('moUpdateMaint')">
  <div class="mo-box" style="max-width:420px">
    <div class="mo-head">
      <div class="mo-title">Update Maintenance Status</div>
      <button class="mo-close" onclick="closeMo('moUpdateMaint')">&times;</button>
    </div>
    <form method="POST">
      @csrf
      <input type="hidden" name="action" value="update_maintenance_status">
      <input type="hidden" name="return_tab" value="maintenance">
      <input type="hidden" name="schedule_id" id="maintSchedId">
      <div class="mo-body">
        <div class="fg">
          <label>Status</label>
          <select name="status" id="maintStatus">
            <option value="in_progress">In Progress</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div class="fg">
          <label>Notes / Remarks</label>
          <textarea name="notes" placeholder="Describe what was done or why cancelled…"></textarea>
        </div>
      </div>
      <div class="mo-footer">
        <button type="button" class="btn btn-outline" onclick="closeMo('moUpdateMaint')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
// Equipment scoped per booking (Fix 3) — same pattern as IncidentsController.php's
// $bookingEquipMap / incidents.blade.php's filterEquipmentByBooking().
const BOOKING_EQUIP = {!! json_encode($bookingEquipMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};

// Field-request pickers — the OPPOSITE list from BOOKING_EQUIP above: equipment/accessories
// NOT yet on the booking (available to request), keyed by booking_id, same shape ClientBookingDetailController
// builds server-side for the client's own request form.
const FIELD_EQUIP = {!! json_encode($fieldEquipMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
const FIELD_ACC = {!! json_encode($fieldAccMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};

function freqSwitch(type, btn) {
  document.getElementById('freqItemType').value = type;
  document.querySelectorAll('[id^="freqPanel-"]').forEach(p => p.style.display = 'none');
  document.getElementById('freqPanel-' + type).style.display = 'block';
  btn.parentElement.querySelectorAll('.subtab-btn').forEach(b => b.classList.remove('on'));
  btn.classList.add('on');
  document.getElementById('freqEquipment').required = type === 'equipment';
  document.getElementById('freqAccessory').required = type === 'accessory';
  document.getElementById('freqPosition').required = type === 'crew';
}

function freqFilterByBooking(bookingId) {
  const equipSel = document.getElementById('freqEquipment');
  const accSel = document.getElementById('freqAccessory');
  const equip = FIELD_EQUIP[bookingId] || [];
  const acc = FIELD_ACC[bookingId] || [];

  if (!bookingId || !equip.length) {
    equipSel.innerHTML = '<option value="">— No additional equipment available —</option>';
    equipSel.disabled = true;
  } else {
    equipSel.innerHTML = '<option value="">— Select Equipment —</option>' + equip.map(e =>
      `<option value="${e.equipment_id}">${escCrew(e.equipment_name)}${e.brand ? ' — ' + escCrew(e.brand) : ''} (₱${Number(e.daily_rate).toLocaleString(undefined,{minimumFractionDigits:2})}/day)</option>`
    ).join('');
    equipSel.disabled = false;
  }

  if (!bookingId || !acc.length) {
    accSel.innerHTML = '<option value="">— No additional accessories available —</option>';
    accSel.disabled = true;
  } else {
    accSel.innerHTML = '<option value="">— Select Accessory —</option>' + acc.map(a =>
      `<option value="${a.accessory_id}">${escCrew(a.accessory_name)} (₱${Number(a.daily_rate).toLocaleString(undefined,{minimumFractionDigits:2})}/day)</option>`
    ).join('');
    accSel.disabled = false;
  }
}

function escCrew(s) {
  return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function filterEquipmentByBooking(bookingId) {
  const sel = document.getElementById('crewIR_equipment');
  const equip = BOOKING_EQUIP[bookingId] || [];
  if (!bookingId || !equip.length) {
    sel.innerHTML = '<option value="">— No equipment on this booking —</option>';
    sel.disabled = true;
    return;
  }
  sel.innerHTML = '<option value="">— Select Equipment —</option>' + equip.map(e =>
    `<option value="${e.equipment_id}">${escCrew(e.equipment_name)}${e.brand ? ' — ' + escCrew(e.brand) : ''}${e.serial_number ? ' (' + escCrew(e.serial_number) + ')' : ''}</option>`
  ).join('');
  sel.disabled = false;
}

function showPg(id, btn) {
  document.querySelectorAll('.pg').forEach(p => p.classList.remove('on'));
  document.getElementById('pg-' + id).classList.add('on');
  document.querySelectorAll('.tabbar button').forEach(b => b.classList.remove('on'));
  // Only dashboard/bookings have a dedicated tab — attendance/incidents/maintenance are
  // reached via the More sheet, so no tab lights up for those, which is fine.
  const match = btn || document.querySelector(`.tabbar button[data-tab="${id}"]`);
  if (match) match.classList.add('on');
  // Whichever tab is current when "File Report" gets opened is where the incident form
  // should land back on after submitting, since that modal opens from more than one tab.
  const irReturnTab = document.getElementById('incidentReturnTab');
  if (irReturnTab) irReturnTab.value = id;
  window.scrollTo(0, 0);
}
function showSubTab(group, name, btn) {
  document.querySelectorAll(`.subpanel[data-group="${group}"]`).forEach(p => p.classList.remove('on'));
  const panel = document.getElementById('sub-' + group + '-' + name);
  if (panel) panel.classList.add('on');
  document.querySelectorAll(`.subtab-btn[data-group="${group}"]`).forEach(b => b.classList.remove('on'));
  btn.classList.add('on');
}
function openMo(id) { document.getElementById(id).classList.add('on'); }
function closeMo(id) { document.getElementById(id).classList.remove('on'); }
function showAttBooking(bookingId) {
  document.querySelectorAll('.att-booking-panel').forEach(p => p.style.display = 'none');
  if (!bookingId) return;
  const panel = document.querySelector(`.att-booking-panel[data-booking="${bookingId}"]`);
  if (panel) panel.style.display = 'block';
}
function showChecklistBooking(dir, bookingId) {
  document.querySelectorAll(`.checklist-booking-panel[data-dir="${dir}"]`).forEach(p => p.style.display = 'none');
  if (!bookingId) return;
  const panel = document.querySelector(`.checklist-booking-panel[data-dir="${dir}"][data-booking="${bookingId}"]`);
  if (panel) panel.style.display = 'block';
}
function selectBooking(kind, bookingId, el) {
  document.querySelectorAll(`.booking-pick-item[data-kind="${kind}"]`).forEach(it => it.classList.remove('active'));
  el.classList.add('active');
  if (kind === 'att') {
    showAttBooking(bookingId);
  } else {
    showChecklistBooking(kind.replace('checklist-', ''), bookingId);
  }
}
function filterBookingList(kind, query) {
  const q = query.trim().toLowerCase();
  const items = document.querySelectorAll(`.booking-pick-item[data-kind="${kind}"]`);
  let anyVisible = false;
  let activeHidden = false;
  items.forEach(it => {
    const match = q === '' || it.dataset.search.includes(q);
    it.style.display = match ? '' : 'none';
    if (match) anyVisible = true;
    if (!match && it.classList.contains('active')) activeHidden = true;
  });
  const emptyEl = kind === 'att' ? document.getElementById('attBookingEmpty') : document.getElementById('checklistBookingEmpty-' + kind.replace('checklist-', ''));
  if (emptyEl) emptyEl.style.display = anyVisible ? 'none' : 'block';
  // If the currently active booking just got filtered out, clear its panel so a stale pick can't linger.
  if (activeHidden) {
    document.querySelectorAll(`.booking-pick-item[data-kind="${kind}"]`).forEach(it => it.classList.remove('active'));
    if (kind === 'att') {
      showAttBooking(null);
    } else {
      showChecklistBooking(kind.replace('checklist-', ''), null);
    }
  }
}
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.booking-pick-item.active').forEach(it => {
    const kind = it.dataset.kind;
    const bookingId = it.dataset.booking;
    if (kind === 'att') {
      showAttBooking(bookingId);
    } else {
      showChecklistBooking(kind.replace('checklist-', ''), bookingId);
    }
  });
});
function toggleAttFields(crewId, status) {
  const reasonEl = document.getElementById('attReason' + crewId);
  const replEl = document.getElementById('attRepl' + crewId);
  if (reasonEl) reasonEl.style.display = (status === 'present' || status === 'late') ? 'none' : 'block';
  if (replEl) replEl.style.display = (status === 'no_show' || status === 'back_out') ? 'block' : 'none';
}
function updateAttCard(crewId, status) {
  const card = document.getElementById('attCard' + crewId);
  if (card) card.dataset.status = status;
}
function updateAttSummary(bookingId) {
  const box = document.getElementById('attSummary' + bookingId);
  const panel = document.querySelector('.att-booking-panel[data-booking="' + bookingId + '"]');
  if (!box || !panel) return;
  const selects = panel.querySelectorAll('select[name^="crew_status"]');
  let present = 0, late = 0, flagged = 0;
  selects.forEach(s => {
    if (s.value === 'present') present++;
    else if (s.value === 'late') late++;
    else if (s.value === 'absent' || s.value === 'no_show' || s.value === 'back_out') flagged++;
  });
  const stats = box.querySelectorAll('.stat b');
  if (stats[0]) stats[0].textContent = present;
  if (stats[1]) stats[1].textContent = late;
  if (stats[2]) stats[2].textContent = flagged;
}
function filterBookings(statusCsv, btn) {
  document.querySelectorAll('#bookingFilterPills .filter-pill').forEach(p => p.classList.remove('on'));
  btn.classList.add('on');
  const statuses = statusCsv.split(',');
  document.querySelectorAll('#pg-bookings .item[data-status]').forEach(row => {
    row.style.display = (statusCsv === 'all' || statuses.includes(row.dataset.status)) ? 'flex' : 'none';
  });
}
function openUpdateMaint(schedId, currentStatus) {
  document.getElementById('maintSchedId').value = schedId;
  const sel = document.getElementById('maintStatus');
  if (sel) sel.value = currentStatus === 'pending' ? 'in_progress' : currentStatus;
  openMo('moUpdateMaint');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.mo.on').forEach(m => m.classList.remove('on')); });
</script>
</body>
</html>
