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
.dash-status-pills { display:flex;align-items:center;gap:8px;flex-wrap:wrap; }
.dash-pill {
  display:inline-flex;align-items:center;gap:6px;
  padding:6px 13px;border-radius:20px;font-size:12px;font-weight:600;
  border:1.5px solid var(--border);background:var(--surface);color:var(--sub);
  white-space:nowrap;
}
.dash-pill i { width:13px;height:13px; }
.dash-pill.blue   { background:var(--acclight);border-color:rgba(0,96,199,.2);color:var(--accent); }
.dash-pill.green  { background:var(--greenl);border-color:rgba(22,163,74,.2);color:var(--green); }
.dash-pill.red    { background:var(--redl);border-color:rgba(220,38,38,.2);color:var(--red); }
.dash-pill.orange { background:var(--orangel);border-color:rgba(234,88,12,.2);color:var(--orange); }
.dash-grid-main { display:grid;grid-template-columns:1fr 320px;gap:18px;margin-bottom:20px; }
.dash-grid-bottom { display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:20px; }

/* Calendar */
.cal-wrap { background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-md);overflow:hidden;box-shadow:var(--shadow-sm);margin-bottom:20px; }
.cal-head { display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid var(--border); }
.cal-nav-btn { width:32px;height:32px;border:1px solid var(--border);border-radius:8px;background:var(--s2);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s;color:var(--sub); }
.cal-nav-btn:hover { background:var(--accent);border-color:var(--accent);color:#fff; }
.cal-month-title { font-family:var(--font-display);font-size:22px;letter-spacing:1px;color:var(--text); }
.cal-dow { display:grid;grid-template-columns:repeat(7,1fr);background:var(--s2);border-bottom:1px solid var(--border); }
.cal-dow-cell { padding:8px 4px;text-align:center;font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--muted); }
.cal-dow-cell:first-child,.cal-dow-cell:last-child { color:var(--red); }
.cal-grid { display:grid;grid-template-columns:repeat(7,1fr);gap:1px;background:var(--border);padding:1px; }
.cal-cell { background:var(--surface);min-height:90px;padding:6px;position:relative;cursor:pointer;transition:background .1s; }
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

/* Today sidebar */
.today-shoot { display:flex;gap:10px;align-items:flex-start;padding:12px 16px;border-bottom:1px solid var(--border); }
.today-shoot:last-child { border-bottom:none; }
.today-dot { width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-top:5px; }
</style>

<!-- ══ PAGE HEADER ══ -->
<div class="dash-header">
  <div class="dash-header-left">
    <div class="dash-icon-wrap"><i data-feather="grid" style="width:20px;height:20px"></i></div>
    <div>
      <div class="dash-title">Dashboard</div>
      <div class="dash-date">
        <i data-feather="calendar" style="width:11px;height:11px"></i>
        {{ now()->format('l, F j, Y') }}
      </div>
    </div>
  </div>
  <div class="dash-status-pills">
    <div class="dash-pill {{ $activeBookings ? 'blue' : '' }}">
      <i data-feather="layers"></i>
      {{ $activeBookings }} active booking{{ $activeBookings != 1 ? 's' : '' }}
    </div>
    @if (count($todayShoots))
    <div class="dash-pill green">
      <i data-feather="video"></i>
      {{ count($todayShoots) }} shoot{{ count($todayShoots) != 1 ? 's' : '' }} today
    </div>
    @endif
    @if ($pendingApprovalCount)
    <div class="dash-pill orange">
      <i data-feather="clock"></i>
      {{ $pendingApprovalCount }} pending approval
    </div>
    @endif
    @if ($openIncidents)
    <div class="dash-pill red">
      <i data-feather="alert-triangle"></i>
      {{ $openIncidents }} open incident{{ $openIncidents != 1 ? 's' : '' }}
    </div>
    @endif
  </div>
</div>

<!-- ══ KPI CARDS ══ -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:22px">
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="layers"></i></div>
    <div class="stat-value">{{ $totalBookings }}</div>
    <div class="stat-label">Total Bookings</div>
    <span class="stat-delta up"><i data-feather="activity"></i> {{ $activeBookings }} active now</span>
  </div>
  <div class="stat-card {{ $pendingApprovalCount > 0 ? 'orange' : '' }}">
    <div class="stat-icon" style="{{ $pendingApprovalCount > 0 ? 'background:var(--orangel);color:var(--orange)' : '' }}"><i data-feather="clock"></i></div>
    <div class="stat-value">{{ $pendingApprovalCount }}</div>
    <div class="stat-label">Pending Approval</div>
    <span class="stat-delta {{ $pendingApprovalCount > 0 ? 'down' : 'up' }}">
      <i data-feather="{{ $pendingApprovalCount > 0 ? 'alert-triangle' : 'check' }}"></i>
      {{ $pendingApprovalCount > 0 ? 'Needs review' : 'All clear' }}
    </span>
  </div>
  @if ($canSeeFinancials)
  <div class="stat-card green">
    <div class="stat-icon" style="background:var(--greenl);color:var(--green)"><i data-feather="dollar-sign"></i></div>
    <div class="stat-value">₱{{ number_format($totalRevenue / 1000, 1) }}k</div>
    <div class="stat-label">Sales This Month</div>
    <span class="stat-delta up"><i data-feather="trending-up"></i> Payments received</span>
  </div>
  @else
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="users"></i></div>
    <div class="stat-value">{{ $activeCrewCount }}</div>
    <div class="stat-label">Active Crew</div>
    <span class="stat-delta up"><i data-feather="check"></i> On roster</span>
  </div>
  @endif
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="camera"></i></div>
    <div class="stat-value">{{ $availableEquipment }}<span style="font-size:16px;color:var(--muted)"> / {{ $totalEquipment }}</span></div>
    <div class="stat-label">Equipment Available</div>
    <span class="stat-delta {{ $rentedEquipment > 0 ? 'down' : 'up' }}">
      <i data-feather="box"></i> {{ $rentedEquipment }} in field
    </span>
  </div>
</div>

<!-- ══ MAIN CONTENT ROW ══ -->
<div class="dash-grid-main">

  <!-- Recent Bookings -->
  <div class="card" style="margin-bottom:0">
    <div class="card-header">
      <h2 class="card-title"><i data-feather="layers" style="width:14px;height:14px"></i> Recent Bookings</h2>
      <a href="{{ route('bookings') }}" class="btn btn-outline btn-sm">View All <i data-feather="arrow-right" style="width:12px;height:12px"></i></a>
    </div>
    @if (empty($recentBookings) || count($recentBookings) === 0)
    <div class="empty-state"><i data-feather="calendar"></i><h3>No bookings yet</h3></div>
    @else
    <div class="table-wrap card-scroll">
      <table>
        <thead>
          <tr><th>Ref</th><th>Client</th><th>Project</th><th>Date</th><th>Amount</th><th>Status</th></tr>
        </thead>
        <tbody>
          @foreach ($recentBookings as $rb)
          <tr style="cursor:pointer" onclick="window.location='{{ route('booking-detail', $rb->booking_id) }}'">
            <td style="font-family:var(--font-mono);font-size:11px;font-weight:700;color:var(--accent)">{{ $rb->booking_reference }}</td>
            <td style="font-weight:600;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $rb->company_name ?: $rb->contact_person }}</td>
            <td style="color:var(--sub);max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $rb->project_title ?: '—' }}</td>
            <td style="font-size:11.5px;color:var(--muted);white-space:nowrap">{{ \Carbon\Carbon::parse($rb->shoot_date_start)->format('M j, Y') }}</td>
            <td style="font-weight:700;color:var(--accent);font-size:12px">{{ $canSeeFinancials && $rb->final_amount > 0 ? '₱' . number_format($rb->final_amount, 0) : '—' }}</td>
            <td>
              <span class="badge {{ $statusBadge[$rb->booking_status] ?? '' }}" style="font-size:10px">
                {{ ucfirst(str_replace('_', ' ', $rb->booking_status)) }}
              </span>
              @if (($rb->approval_status ?? '') === 'pending_approval')
              <span class="badge badge-orange" style="font-size:9px;margin-left:3px">Pending OM</span>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>

  <!-- Sidebar -->
  <div style="display:flex;flex-direction:column;gap:16px">

    <!-- Today's Shoots -->
    <div class="card" style="margin-bottom:0">
      <div class="card-header">
        <h2 class="card-title"><i data-feather="video" style="width:14px;height:14px"></i> Today's Shoots</h2>
        <span class="badge {{ count($todayShoots) ? 'badge-blue' : 'badge-gray' }}">{{ count($todayShoots) }}</span>
      </div>
      @if (empty($todayShoots) || count($todayShoots) === 0)
      <div class="card-body" style="padding:20px;text-align:center">
        <div style="color:var(--green);margin-bottom:6px"><i data-feather="check-circle" style="width:28px;height:28px"></i></div>
        <p style="font-size:12.5px;color:var(--muted)">No shoots scheduled today.</p>
      </div>
      @else
      <div class="card-scroll-sm">
        @foreach ($todayShoots as $ts)
        <div class="today-shoot">
          <div class="today-dot" style="background:{{ $ts->booking_status === 'ongoing' ? 'var(--green)' : 'var(--accent)' }}"></div>
          <div style="flex:1;min-width:0">
            <div style="font-weight:600;font-size:12.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $ts->project_title ?: $ts->booking_reference }}</div>
            <div style="font-size:11px;color:var(--muted);margin-top:2px">{{ $ts->company_name ?: $ts->contact_person }}</div>
            <span class="badge {{ $statusBadge[$ts->booking_status] ?? '' }}" style="font-size:9px;margin-top:4px">{{ ucfirst($ts->booking_status) }}</span>
          </div>
          <a href="{{ route('booking-detail', $ts->booking_id) }}" class="btn btn-outline btn-sm" style="padding:3px 8px;font-size:10px">View</a>
        </div>
        @endforeach
      </div>
      @endif
    </div>

    <!-- Quick Stats -->
    <div class="card" style="margin-bottom:0">
      <div class="card-header"><h2 class="card-title"><i data-feather="bar-chart-2" style="width:14px;height:14px"></i> Quick Stats</h2></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0">
        @php
          $qs = [
            ['Active Crew', $activeCrewCount, 'badge-green', 'users'],
            ['Rented Units', $rentedEquipment, 'badge-orange', 'box'],
            ['Open Incidents', $openIncidents, $openIncidents > 0 ? 'badge-red' : 'badge-gray', 'alert-triangle'],
            ['This Week', $upcomingCount, 'badge-blue', 'calendar'],
          ];
        @endphp
        @foreach ($qs as $i => [$lbl, $val, $badge, $icon])
        <div style="padding:14px 16px;{{ $i % 2 === 0 ? 'border-right:1px solid var(--border)' : '' }};border-bottom:1px solid var(--border)">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
            <span style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;font-weight:600">{{ $lbl }}</span>
            <i data-feather="{{ $icon }}" style="width:12px;height:12px;color:var(--muted)"></i>
          </div>
          <div style="font-family:var(--font-display);font-size:26px;color:var(--text);line-height:1">{{ $val }}</div>
        </div>
        @endforeach
      </div>
    </div>

  </div>
</div>

<!-- ══ CALENDAR ══ -->
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

<!-- ══ BOTTOM ROW ══ -->
<div class="dash-grid-bottom">

  <!-- Equipment in Field -->
  <div class="card" style="margin-bottom:0">
    <div class="card-header">
      <h2 class="card-title"><i data-feather="box" style="width:14px;height:14px"></i> Equipment in Field</h2>
      <span class="badge {{ $rentedEquipment > 0 ? 'badge-orange' : 'badge-green' }}">{{ $rentedEquipment }} rented</span>
    </div>
    @if (empty($rentedAll) || count($rentedAll) === 0)
    <div class="empty-state" style="padding:28px 0">
      <i data-feather="check-circle"></i>
      <h3>All equipment available</h3>
    </div>
    @else
    <div class="table-wrap card-scroll">
      <table>
        <thead><tr><th>Equipment</th><th>Brand</th><th>Category</th><th>Status</th></tr></thead>
        <tbody>
          @foreach ($rentedAll as $r)
          <tr>
            <td style="font-weight:600">{{ $r->equipment_name }}</td>
            <td style="color:var(--muted);font-size:12px">{{ $r->brand ?? '—' }}</td>
            <td style="color:var(--muted);font-size:12px">{{ $r->category_name }}</td>
            <td><span class="badge badge-orange">In Field</span></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>

  <!-- Activity Feed -->
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
