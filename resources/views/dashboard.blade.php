@extends('layouts.app')

@section('pageTitle', 'Dashboard')

@section('content')
@php
  $userRole = auth()->user()->role->role_name ?? '';
@endphp
<style>
/* ── Dashboard-specific ── */
.dash-header {
  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;
  margin-bottom:20px;padding-bottom:18px;border-bottom:1px solid var(--border);
}
.dash-header-left { display:flex;align-items:center;gap:14px; }
.dash-icon-wrap {
  width:44px;height:44px;border-radius:12px;
  background:var(--acclight);color:var(--accent);
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.dash-title { font-family:var(--font-display);font-size:24px;letter-spacing:.5px;color:var(--text);line-height:1; }
.dash-date  { font-size:12px;color:var(--muted);margin-top:3px;display:flex;align-items:center;gap:5px; }

/* Needs Attention */
.attn-wrap { background:#fef2f2;border:1px solid #fecaca;border-radius:var(--radius-md);padding:18px 20px;margin-bottom:20px; }
.attn-head { display:flex;align-items:center;gap:8px;margin-bottom:14px;font-weight:700;font-size:14px;color:#991b1b; }
.attn-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:14px; }
.attn-card { background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px 16px; }
.attn-card .num { font-family:var(--font-display);font-size:26px;color:var(--text);line-height:1;display:flex;align-items:center;gap:8px; }
.attn-card .lbl { font-size:12px;color:var(--sub);font-weight:600;margin-top:4px; }
.attn-card a { font-size:11.5px;color:var(--accent);text-decoration:none;font-weight:600;display:inline-flex;align-items:center;gap:3px;margin-top:8px; }
.attn-card a:hover { text-decoration:underline; }
.attn-card.clear { opacity:.55; }

/* Today's Operations */
.today-ops-grid { display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:20px; }
.today-ops-card { background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px 16px;box-shadow:var(--shadow-sm); }
.today-ops-card .icon { width:30px;height:30px;border-radius:8px;background:var(--acclight);color:var(--accent);display:flex;align-items:center;justify-content:center;margin-bottom:8px; }
.today-ops-card .num { font-family:var(--font-display);font-size:24px;color:var(--text);line-height:1; }
.today-ops-card .lbl { font-size:10.5px;color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-top:4px; }

.dash-grid-main { display:grid;grid-template-columns:1fr 320px;gap:18px;margin-bottom:20px; }
.dash-grid-bottom { display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:20px; }

/* Equipment Status — Signal Board: a real ring chart with a center total instead of a bar +
   number strip. Overdue is a booking-level flag, not a fleet-utilization slice, so it's called
   out as its own chip rather than a ring segment. */
.eq-ring-wrap { display:flex;align-items:center;gap:18px;padding:14px 16px; flex-wrap:wrap; }
.eq-ring-svg { flex-shrink:0; }
.eq-ring-center-num { font-family:var(--font-display); }
.eq-ring-center-lbl { font-family:var(--font-body); }
.eq-legend { display:flex;flex-direction:column;gap:9px;font-size:12px;flex:1;min-width:120px; }
.eq-legend span { display:flex;align-items:center;gap:7px;color:var(--sub); }
.eq-legend b { margin-left:auto;padding-left:14px;font-family:var(--font-mono);color:var(--text); }
.eq-legend .dot { width:8px;height:8px;border-radius:50%;flex-shrink:0; }
.eq-overdue-chip { margin:0 16px 16px;display:flex;align-items:center;gap:8px;border-radius:8px;padding:9px 12px;font-size:12.5px;font-weight:700; }
.eq-overdue-chip.alert { background:var(--redl);color:#991b1b; }
.eq-overdue-chip.clear { background:var(--greenl);color:var(--green); }

/* Financial Snapshot — a real area sparkline with a labeled current-month point, not seven
   skinny disconnected bars. */
.fin-snap { padding:16px 18px 4px; }
.fin-snap .val { font-family:var(--font-display);font-size:32px;color:var(--text);line-height:1; }
.fin-snap .lbl { font-size:11px;color:var(--muted);margin-top:4px; }
.fin-chart-wrap { padding:6px 16px 2px;position:relative; }
.fin-callout { position:absolute;right:4px;font-family:var(--font-mono);font-size:9.5px;font-weight:700;background:var(--accent);color:#fff;border-radius:5px;padding:2px 6px;white-space:nowrap;transform:translate(0,-130%);pointer-events:none; }
.fin-axis { display:flex;justify-content:space-between;font-size:9px;font-family:var(--font-mono);color:var(--muted);padding:2px 2px 0; }

/* Command Strip's compact agenda — next few shoot days, no need to open the calendar */
.fin-agenda { padding:12px 18px 16px;border-top:1px solid var(--border);margin-top:14px;display:flex;flex-direction:column;gap:8px; }
.fin-agenda-row { display:flex;gap:10px;align-items:baseline;font-size:12px; }
.fin-agenda-date { font-family:var(--font-mono);font-weight:700;color:var(--accent);width:52px;flex-shrink:0; }
.fin-agenda-txt { color:var(--sub); }

/* Calendar */
.cal-wrap { background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-md);overflow:hidden;box-shadow:var(--shadow-sm);margin-bottom:0;height:100%; }
.cal-head { display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border); }
.cal-nav-btn { width:32px;height:32px;border:1px solid var(--border);border-radius:8px;background:var(--s2);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s;color:var(--sub); }
.cal-nav-btn:hover { background:var(--accent);border-color:var(--accent);color:#fff; }
.cal-month-title { font-family:var(--font-display);font-size:22px;letter-spacing:1px;color:var(--text); }
.cal-dow { display:grid;grid-template-columns:repeat(7,minmax(0,1fr));background:var(--s2);border-bottom:1px solid var(--border); }
.cal-dow-cell { padding:8px 4px;text-align:center;font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--muted); }
.cal-dow-cell:first-child,.cal-dow-cell:last-child { color:var(--red); }
.cal-grid { display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:1px;background:var(--border);padding:1px; }
.cal-cell { background:var(--surface);min-height:74px;min-width:0;overflow:hidden;padding:6px;position:relative;cursor:pointer;transition:background .1s; }
.cal-cell:hover { background:var(--acclight); }
.cal-cell.other-month { background:var(--s2); }
.cal-cell.other-month .cal-day-num { color:var(--border2); }
.cal-cell.is-today { background:var(--acclight); }
.cal-cell.is-today .cal-day-num { background:var(--accent);color:#fff;border-radius:50%;width:22px;height:22px;display:flex;align-items:center;justify-content:center;font-weight:700; }
.cal-cell.is-weekend .cal-day-num { color:var(--red); }
.cal-day-num { font-size:12px;font-weight:600;color:var(--sub);margin-bottom:4px;width:22px;height:22px;display:flex;align-items:center;justify-content:center; }
.cal-event { font-size:10px;font-weight:600;border-radius:3px;padding:2px 5px;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:pointer;line-height:1.4; }
.cal-event.confirmed { background:var(--acclight);color:var(--accent);border-left:2px solid var(--accent); }
.cal-event.ongoing   { background:var(--greenl);color:var(--green);border-left:2px solid var(--green); }
.cal-event.pending   { background:var(--yellowl);color:var(--yellow);border-left:2px solid var(--yellow); }
.cal-more { font-size:9px;color:var(--muted);font-weight:600;margin-top:1px; }

/* Activity feed */
.activity-item { display:flex;gap:12px;padding:12px 18px;border-bottom:1px solid var(--border); }
.activity-item:last-child { border-bottom:none; }
.activity-avatar { width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#fff;flex-shrink:0; }
.activity-body { flex:1;min-width:0; }
.activity-user { font-weight:600;font-size:12.5px;color:var(--text); }
.activity-action { font-size:12px;color:var(--sub);margin-top:1px; }
.activity-time { font-size:10px;color:var(--muted);white-space:nowrap;margin-top:2px; }

@media (max-width:1100px) {
  .today-ops-grid { grid-template-columns:repeat(2,1fr); }
  .attn-grid { grid-template-columns:1fr; }
  .dash-grid-main { grid-template-columns:1fr; }
}
@media (max-width:560px) {
  .eq-ring-wrap { gap:14px; }
  .eq-ring-svg { width:76px;height:76px; }
  .fin-snap .val { font-size:26px; }
  .fin-axis span { font-size:8px; }
  .dash-grid-bottom { grid-template-columns:1fr; }
}
</style>

<!-- ══ PAGE HEADER ══ -->
<div class="dash-header">
  <div class="dash-header-left">
    <div class="dash-icon-wrap"><i data-feather="grid" style="width:20px;height:20px"></i></div>
    <div>
      <div class="dash-title">Operations Dashboard</div>
      <div class="dash-date">
        <i data-feather="calendar" style="width:11px;height:11px"></i>
        {{ now()->format('l, F j, Y') }}
      </div>
    </div>
  </div>
</div>

<!-- ══ NEEDS ATTENTION ══ -->
@php $needsAttentionTotal = $pendingApprovalCount + $overdueReturns + $openIncidents; @endphp
<div class="attn-wrap" style="{{ $needsAttentionTotal === 0 ? 'background:var(--greenl);border-color:rgba(22,163,74,.25)' : '' }}">
  <div class="attn-head" style="{{ $needsAttentionTotal === 0 ? 'color:var(--green)' : '' }}">
    <i data-feather="{{ $needsAttentionTotal === 0 ? 'check-circle' : 'alert-triangle' }}" style="width:16px;height:16px"></i>
    Needs Attention
    <span style="font-weight:400;font-size:11.5px;opacity:.8">— items that require your action</span>
  </div>
  <div class="attn-grid">
    <div class="attn-card {{ $pendingApprovalCount === 0 ? 'clear' : '' }}">
      <div class="num"><i data-feather="file-text" style="width:18px;height:18px;color:var(--orange)"></i>{{ $pendingApprovalCount }}</div>
      <div class="lbl">Requests Awaiting Review</div>
      <a href="{{ route('bookings') }}?approval=pending_approval">View Requests <i data-feather="arrow-right" style="width:11px;height:11px"></i></a>
    </div>
    <div class="attn-card {{ $overdueReturns === 0 ? 'clear' : '' }}">
      <div class="num"><i data-feather="rotate-ccw" style="width:18px;height:18px;color:var(--red)"></i>{{ $overdueReturns }}</div>
      <div class="lbl">Overdue Returns</div>
      <a href="{{ route('bookings') }}?status=ongoing">View Overdue <i data-feather="arrow-right" style="width:11px;height:11px"></i></a>
    </div>
    <div class="attn-card {{ $openIncidents === 0 ? 'clear' : '' }}">
      <div class="num"><i data-feather="alert-triangle" style="width:18px;height:18px;color:var(--red)"></i>{{ $openIncidents }}</div>
      <div class="lbl">Open Incidents</div>
      <a href="{{ route('incidents') }}">View Incidents <i data-feather="arrow-right" style="width:11px;height:11px"></i></a>
    </div>
  </div>
</div>

<!-- ══ TODAY'S OPERATIONS ══ -->
<div style="font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin-bottom:8px">
  Today's Operations — what's happening today
</div>
<div class="today-ops-grid">
  <div class="today-ops-card">
    <div class="icon"><i data-feather="video" style="width:15px;height:15px"></i></div>
    <div class="num">{{ count($todayShoots) }}</div>
    <div class="lbl">Shoots Today</div>
  </div>
  <div class="today-ops-card">
    <div class="icon" style="background:var(--greenl);color:var(--green)"><i data-feather="upload" style="width:15px;height:15px"></i></div>
    <div class="num">{{ $unitsDueOutToday }}</div>
    <div class="lbl">Units Due Out</div>
  </div>
  <div class="today-ops-card">
    <div class="icon" style="background:var(--orangel);color:var(--orange)"><i data-feather="download" style="width:15px;height:15px"></i></div>
    <div class="num">{{ $dueBackToday }}</div>
    <div class="lbl">Due Back Today</div>
  </div>
  <div class="today-ops-card">
    <div class="icon" style="background:#f3e8ff;color:#7c3aed"><i data-feather="box" style="width:15px;height:15px"></i></div>
    <div class="num">{{ $rentedEquipment }}</div>
    <div class="lbl">Units in Field</div>
  </div>
  <div class="today-ops-card">
    <div class="icon" style="background:#e0f2fe;color:#0891b2"><i data-feather="users" style="width:15px;height:15px"></i></div>
    <div class="num">{{ $crewOnScheduleToday }}</div>
    <div class="lbl">Crew on Schedule</div>
  </div>
</div>

<!-- ══ CALENDAR + EQUIPMENT STATUS ══ -->
<div class="dash-grid-main">

  <!-- Upcoming Calendar -->
  <div class="cal-wrap">
    <div class="cal-head">
      <div style="display:flex;align-items:center;gap:12px">
        <button class="cal-nav-btn" onclick="calPrev()"><i data-feather="chevron-left" style="width:14px;height:14px"></i></button>
        <div>
          <div class="cal-month-title" id="calTitle"></div>
          <div style="font-size:11px;color:var(--muted);margin-top:1px" id="calSub"></div>
        </div>
        <button class="cal-nav-btn" onclick="calNext()"><i data-feather="chevron-right" style="width:14px;height:14px"></i></button>
      </div>
      <div style="display:flex;align-items:center;gap:8px">
        <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--sub)">
          <span style="width:10px;height:10px;background:var(--acclight);border-left:2px solid var(--accent);border-radius:1px;display:inline-block"></span> Confirmed &nbsp;
          <span style="width:10px;height:10px;background:var(--greenl);border-left:2px solid var(--green);border-radius:1px;display:inline-block"></span> Ongoing &nbsp;
          <span style="width:10px;height:10px;background:var(--yellowl);border-left:2px solid var(--yellow);border-radius:1px;display:inline-block"></span> Pending
        </div>
        <button class="btn btn-outline btn-sm" onclick="calGoToday()" style="font-size:11px">Today</button>
      </div>
    </div>
    <div class="cal-dow">
      @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d)
      <div class="cal-dow-cell">{{ $d }}</div>
      @endforeach
    </div>
    <div class="cal-grid" id="calGrid"></div>
  </div>

  <!-- Equipment Status + Financial Snapshot — "Signal Board" -->
  <div style="display:flex;flex-direction:column;gap:16px">
    @php
      // Ring segments are the three mutually-exclusive equipment statuses that make up fleet
      // utilization (available/rented/under_repair) — "Overdue Returns" is a booking-level flag,
      // not an equipment status, so it's called out as its own chip rather than a ring segment.
      // Any remainder (e.g. 'booked' units) is left as unfilled track, not force-summed to 100%.
      $eqR = 37; $eqC = 2 * M_PI * $eqR;
      $eqSegments = [
        ['value' => $availableEquipment, 'color' => 'var(--green)'],
        ['value' => $rentedEquipment,    'color' => 'var(--purple)'],
        ['value' => $underMaintenance,   'color' => 'var(--orange)'],
      ];
      $eqCum = 0;
      foreach ($eqSegments as $i => $seg) {
        $frac = $totalEquipment > 0 ? $seg['value'] / $totalEquipment : 0;
        $eqSegments[$i]['len'] = round($frac * $eqC, 2);
        $eqSegments[$i]['offset'] = round(-$eqCum, 2);
        $eqCum += $frac * $eqC;
      }
    @endphp
    <div class="card" style="margin-bottom:0">
      <div class="card-header"><h2 class="card-title"><i data-feather="package" style="width:14px;height:14px"></i> Equipment Status</h2></div>
      <div class="eq-ring-wrap">
        <svg class="eq-ring-svg" width="92" height="92" viewBox="0 0 92 92" role="img" aria-label="{{ $availableEquipment }} of {{ $totalEquipment }} units available">
          <circle cx="46" cy="46" r="{{ $eqR }}" fill="none" stroke="var(--s3)" stroke-width="11"/>
          @foreach ($eqSegments as $seg)
          @if ($seg['value'] > 0)
          <circle cx="46" cy="46" r="{{ $eqR }}" fill="none" stroke="{{ $seg['color'] }}" stroke-width="11"
            stroke-dasharray="{{ $seg['len'] }} {{ round($eqC, 2) }}"
            stroke-dashoffset="{{ $seg['offset'] }}"
            transform="rotate(-90 46 46)"/>
          @endif
          @endforeach
          <text x="46" y="43" text-anchor="middle" class="eq-ring-center-num" font-size="24" fill="var(--text)">{{ $totalEquipment }}</text>
          <text x="46" y="57" text-anchor="middle" class="eq-ring-center-lbl" font-size="8" fill="var(--muted)">TOTAL UNITS</text>
        </svg>
        <div class="eq-legend">
          <span><span class="dot" style="background:var(--green)"></span>Available<b>{{ $availableEquipment }}</b></span>
          <span><span class="dot" style="background:var(--purple)"></span>In Field<b>{{ $rentedEquipment }}</b></span>
          <span><span class="dot" style="background:var(--orange)"></span>Maintenance<b>{{ $underMaintenance }}</b></span>
        </div>
      </div>
      <div class="eq-overdue-chip {{ $overdueReturns > 0 ? 'alert' : 'clear' }}">
        <i data-feather="{{ $overdueReturns > 0 ? 'alert-triangle' : 'check-circle' }}" style="width:14px;height:14px"></i>
        {{ $overdueReturns > 0 ? $overdueReturns . ' unit' . ($overdueReturns === 1 ? '' : 's') . ' overdue for return' : 'No overdue returns' }}
      </div>
    </div>

    @if ($canSeeFinancials)
    @php
      $finPoints = [];
      if ($financialTrend->isNotEmpty()) {
        $finMax = max(1, $financialTrend->max('total'));
        $finVals = $financialTrend->values();
        $finCount = $finVals->count();
        $finW = 280; $finTop = 4; $finBase = 60;
        foreach ($finVals as $i => $t) {
          $x = $finCount > 1 ? round(($i / ($finCount - 1)) * $finW, 1) : $finW / 2;
          $y = round($finBase - (($t->total / $finMax) * ($finBase - $finTop)), 1);
          $finPoints[] = ['x' => $x, 'y' => $y, 'label' => $t->label, 'total' => $t->total];
        }
      }
      $finLinePath = ''; $finAreaPath = ''; $finLast = null;
      if (count($finPoints) >= 2) {
        $finLinePath = 'M' . $finPoints[0]['x'] . ',' . $finPoints[0]['y'];
        foreach (array_slice($finPoints, 1) as $p) { $finLinePath .= ' L' . $p['x'] . ',' . $p['y']; }
        $finLast = end($finPoints);
        $finAreaPath = $finLinePath . ' L' . $finLast['x'] . ',64 L' . $finPoints[0]['x'] . ',64 Z';
      }
    @endphp
    <!-- Financial Snapshot -->
    <div class="card" style="margin-bottom:0">
      <div class="card-header">
        <h2 class="card-title"><i data-feather="bar-chart-2" style="width:14px;height:14px"></i> Financial Snapshot</h2>
        <a href="{{ route('reports') }}" class="btn btn-outline btn-sm" style="font-size:11px">View Reports</a>
      </div>
      <div class="fin-snap">
        <div class="val">₱{{ number_format($totalRevenue, 0) }}</div>
        <div class="lbl">Payments Received This Month</div>
        @if ($revenueDeltaPct !== null)
        <span class="stat-delta {{ $revenueDeltaPct >= 0 ? 'up' : 'down' }}" style="margin-top:6px">
          <i data-feather="{{ $revenueDeltaPct >= 0 ? 'trending-up' : 'trending-down' }}"></i> {{ $revenueDeltaPct >= 0 ? '+' : '' }}{{ $revenueDeltaPct }}% vs last month
        </span>
        @endif
      </div>
      @if ($finLast)
      <div class="fin-chart-wrap">
        <svg width="100%" height="72" viewBox="0 0 280 72" preserveAspectRatio="none" style="display:block;overflow:visible">
          <line x1="0" y1="60" x2="280" y2="60" stroke="var(--border)" stroke-width="1" vector-effect="non-scaling-stroke"/>
          <path d="{{ $finLinePath }}" fill="none" stroke="var(--accent)" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
          <path d="{{ $finAreaPath }}" fill="var(--acclight)" opacity="0.6"/>
          <circle cx="{{ $finLast['x'] }}" cy="{{ $finLast['y'] }}" r="4" fill="var(--accent)" stroke="var(--surface)" stroke-width="2" vector-effect="non-scaling-stroke"/>
        </svg>
        <div class="fin-callout" style="top:{{ $finLast['y'] }}px" title="{{ $finLast['label'] }}: ₱{{ number_format($finLast['total'], 2) }}">
          ₱{{ $finLast['total'] >= 1000 ? number_format($finLast['total'] / 1000, 1) . 'K' : number_format($finLast['total'], 0) }}
        </div>
        <div class="fin-axis">
          @foreach ($finPoints as $p)
          <span>{{ strtoupper(substr($p['label'], 0, 3)) }}</span>
          @endforeach
        </div>
      </div>
      @endif

      @if ($upcomingAgenda->isNotEmpty())
      <div class="fin-agenda">
        @foreach ($upcomingAgenda as $day)
        <div class="fin-agenda-row">
          <span class="fin-agenda-date">{{ \Illuminate\Support\Carbon::parse($day['date'])->format('M j') }}</span>
          <span class="fin-agenda-txt">{{ $day['count'] }} shoot{{ $day['count'] === 1 ? '' : 's' }}</span>
        </div>
        @endforeach
      </div>
      @endif
    </div>
    @endif
  </div>
</div>

<!-- ══ REQUESTS AWAITING REVIEW + UPCOMING CONFIRMED BOOKINGS ══ -->
<div class="dash-grid-bottom">

  <div class="card" style="margin-bottom:0">
    <div class="card-header">
      <h2 class="card-title"><i data-feather="file-text" style="width:14px;height:14px"></i> Requests Awaiting Review</h2>
      <a href="{{ route('bookings') }}" class="btn btn-outline btn-sm">View All</a>
    </div>
    @if ($requestsAwaitingReview->isEmpty())
    <div class="empty-state" style="padding:28px 0"><i data-feather="check-circle"></i><h3>Nothing awaiting review</h3></div>
    @else
    <div class="table-wrap card-scroll">
      <table>
        <thead><tr><th>Ref</th><th>Client</th><th>Project</th><th>Submitted</th><th>Days</th></tr></thead>
        <tbody>
          @foreach ($requestsAwaitingReview as $r)
          <tr style="cursor:pointer" onclick="window.location='{{ route('booking-detail', $r->booking_id) }}'">
            <td style="font-family:var(--font-mono);font-size:11px;font-weight:700;color:var(--accent)">{{ $r->booking_reference }}</td>
            <td style="font-weight:600;max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $r->client_name }}</td>
            <td style="color:var(--sub);max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $r->project_title ?: '—' }}</td>
            <td style="font-size:11.5px;color:var(--muted);white-space:nowrap">{{ \Carbon\Carbon::parse($r->created_at)->format('M j, Y') }}</td>
            <td><span class="badge {{ $r->days_waiting >= 2 ? 'badge-red' : 'badge-orange' }}" style="font-size:10px">{{ $r->days_waiting }}</span></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>

  <div class="card" style="margin-bottom:0">
    <div class="card-header">
      <h2 class="card-title"><i data-feather="calendar" style="width:14px;height:14px"></i> Upcoming Confirmed Bookings</h2>
      <span style="font-size:11px;color:var(--muted)">Next 7 days</span>
    </div>
    @if ($upcomingConfirmed->isEmpty())
    <div class="empty-state" style="padding:28px 0"><i data-feather="calendar"></i><h3>Nothing confirmed in the next 7 days</h3></div>
    @else
    <div class="table-wrap card-scroll">
      <table>
        <thead><tr><th>Ref</th><th>Client</th><th>Project</th><th>Shoot Date</th><th>Status</th></tr></thead>
        <tbody>
          @foreach ($upcomingConfirmed as $b)
          <tr style="cursor:pointer" onclick="window.location='{{ route('booking-detail', $b->booking_id) }}'">
            <td style="font-family:var(--font-mono);font-size:11px;font-weight:700;color:var(--accent)">{{ $b->booking_reference }}</td>
            <td style="font-weight:600;max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $b->client_name }}</td>
            <td style="color:var(--sub);max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $b->project_title ?: '—' }}</td>
            <td style="font-size:11.5px;color:var(--muted);white-space:nowrap">{{ \Carbon\Carbon::parse($b->shoot_date_start)->format('M j, Y') }}</td>
            <td><span class="badge {{ $statusBadge[$b->booking_status] ?? '' }}" style="font-size:10px">{{ ucfirst($b->booking_status) }}</span></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>

</div>

<!-- ══ RECENT ACTIVITY ══ -->
<div class="card" style="margin-bottom:0">
  <div class="card-header">
    <h2 class="card-title"><i data-feather="activity" style="width:14px;height:14px"></i> Recent Activity</h2>
    @if (in_array($userRole, ['super_admin', 'operations_manager'], true))
    <a href="{{ route('activity') }}" class="btn btn-outline btn-sm" style="font-size:11px">Full Log</a>
    @endif
  </div>
  @if (empty($recentActivity) || count($recentActivity) === 0)
  <div class="empty-state" style="padding:28px 0"><i data-feather="activity"></i><h3>No activity yet</h3></div>
  @else
  <div class="card-scroll-sm">
    @foreach ($recentActivity as $al)
    @php
      $initials = strtoupper(substr($al->first_name ?? 'S', 0, 1));
      $avatarColor = $roleColors[$al->role_name ?? ''] ?? '#94a3b8';
      $actionLabel = str_replace(['_'], [' '], $al->action);
      $moduleLabel = ucfirst($al->module ?? '');
    @endphp
    <div class="activity-item">
      <div class="activity-avatar" style="background:{{ $avatarColor }}">{{ $initials }}</div>
      <div class="activity-body">
        <div style="display:flex;align-items:baseline;gap:6px;flex-wrap:wrap">
          <span class="activity-user">{{ $al->user_name ?? 'System' }}</span>
          <span class="badge badge-blue" style="font-size:9px">{{ $actionLabel }}</span>
          @if ($moduleLabel)<span style="font-size:10px;color:var(--muted)">{{ $moduleLabel }}</span>@endif
        </div>
        @if ($al->description)
        <div class="activity-action">{{ $al->description }}</div>
        @endif
        <div class="activity-time">{{ \App\Support\Dates::relTime($al->created_at) }}</div>
      </div>
    </div>
    @endforeach
  </div>
  @endif
</div>

<div style="height:8px"></div>

@push('scripts')
<script>

// ── Calendar ──────────────────────────────────────────────────────────────────
const CAL_BOOKINGS = {!! $calendarBookings->map(fn ($b) => [
  'id' => $b->booking_id,
  'ref' => $b->booking_reference,
  'title' => $b->project_title ?: $b->booking_reference,
  'client' => $b->company_name ?: $b->contact_person,
  'start' => $b->shoot_date_start,
  'end' => $b->shoot_date_end,
  'status' => $b->booking_status,
])->values()->toJson() !!};

const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];

let calYear  = {{ (int) \Carbon\Carbon::parse($currentMonth . '-01')->format('Y') }};
let calMonth = {{ (int) \Carbon\Carbon::parse($currentMonth . '-01')->format('n') }}; // 1-12

function buildBookingMap(year, month) {
  const map = {};
  CAL_BOOKINGS.forEach(b => {
    const start = new Date(b.start + 'T00:00:00');
    const end   = new Date(b.end   + 'T00:00:00');
    for (let d = new Date(start); d <= end; d.setDate(d.getDate()+1)) {
      const key = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
      if (!map[key]) map[key] = [];
      map[key].push(b);
    }
  });
  return map;
}

function renderCalendar() {
  const today     = new Date();
  const todayStr  = today.getFullYear()+'-'+String(today.getMonth()+1).padStart(2,'0')+'-'+String(today.getDate()).padStart(2,'0');
  const firstOfMonth = new Date(calYear, calMonth-1, 1);
  const daysInMonth  = new Date(calYear, calMonth, 0).getDate();
  const startDow     = firstOfMonth.getDay(); // 0=Sun
  const totalCells   = Math.ceil((startDow + daysInMonth) / 7) * 7;
  const bookingMap   = buildBookingMap(calYear, calMonth);

  document.getElementById('calTitle').textContent = MONTHS[calMonth-1] + ' ' + calYear;
  const bCount = Object.values(bookingMap).flat().length;
  document.getElementById('calSub').textContent = bCount
    ? Object.keys(bookingMap).length + ' shoot day' + (Object.keys(bookingMap).length>1?'s':'') + ' this month'
    : 'No shoots scheduled';

  const grid = document.getElementById('calGrid');
  grid.innerHTML = '';

  for (let i = 0; i < totalCells; i++) {
    const dayOffset = i - startDow + 1;
    const isCurrentMonth = dayOffset >= 1 && dayOffset <= daysInMonth;
    let displayDay, dateStr;

    if (!isCurrentMonth) {
      if (dayOffset < 1) {
        const prevDate = new Date(calYear, calMonth-1, dayOffset);
        displayDay = prevDate.getDate();
        dateStr = prevDate.getFullYear()+'-'+String(prevDate.getMonth()+1).padStart(2,'0')+'-'+String(prevDate.getDate()).padStart(2,'0');
      } else {
        const nextDate = new Date(calYear, calMonth-1, dayOffset);
        displayDay = nextDate.getDate();
        dateStr = nextDate.getFullYear()+'-'+String(nextDate.getMonth()+1).padStart(2,'0')+'-'+String(nextDate.getDate()).padStart(2,'0');
      }
    } else {
      displayDay = dayOffset;
      dateStr = calYear+'-'+String(calMonth).padStart(2,'0')+'-'+String(dayOffset).padStart(2,'0');
    }

    const isToday   = dateStr === todayStr;
    const isWeekend = (i % 7 === 0) || (i % 7 === 6);
    const events    = bookingMap[dateStr] || [];
    const cell = document.createElement('div');
    cell.className = 'cal-cell' +
      (!isCurrentMonth ? ' other-month' : '') +
      (isToday         ? ' is-today'    : '') +
      (isWeekend && isCurrentMonth ? ' is-weekend' : '');

    const dayNum = document.createElement('div');
    dayNum.className = 'cal-day-num';
    dayNum.textContent = displayDay;
    cell.appendChild(dayNum);

    const maxShow = 2;
    events.slice(0, maxShow).forEach(ev => {
      const pill = document.createElement('div');
      pill.className = 'cal-event ' + ev.status;
      pill.title = ev.title + ' — ' + ev.client;
      pill.textContent = ev.title;
      pill.onclick = (e) => { e.stopPropagation(); window.open('/bookings/'+ev.id,'_blank'); };
      cell.appendChild(pill);
    });
    if (events.length > maxShow) {
      const more = document.createElement('div');
      more.className = 'cal-more';
      more.textContent = '+' + (events.length - maxShow) + ' more';
      cell.appendChild(more);
    }
    grid.appendChild(cell);
  }

  if (window.feather) feather.replace();
}

function calPrev()    { if (calMonth===1){calMonth=12;calYear--;}else{calMonth--;} renderCalendar(); }
function calNext()    { if (calMonth===12){calMonth=1;calYear++;}else{calMonth++;} renderCalendar(); }
function calGoToday() { const t=new Date(); calYear=t.getFullYear(); calMonth=t.getMonth()+1; renderCalendar(); }

document.addEventListener('DOMContentLoaded', () => {
  renderCalendar();
  if (window.feather) feather.replace();
});
</script>
@endpush
@endsection
