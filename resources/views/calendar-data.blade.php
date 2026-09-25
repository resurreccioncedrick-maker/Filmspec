@extends('layouts.app')

@section('pageTitle', 'Calendar Analytics')

@section('breadcrumb')
<span>Calendar Analytics</span>
@endsection

@section('content')
@php
  $calLinkWith = fn (array $o) => route('calendar-data', array_merge([
    'period' => $period['mode'], 'm' => $period['month'],
    'status' => $status ?: null, 'project_type' => $projectType ?: null, 'client_id' => $clientId ?: null,
  ], $o));
@endphp

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:14px;margin-bottom:14px">
  <div>
    <h1 style="font-size:1.4rem;margin:0 0 4px">Calendar Analytics</h1>
    <p style="font-size:.85rem;color:var(--text-muted);max-width:640px;margin:0">
      How busy the shoot calendar is — density, gaps and utilization, from confirmed production
      bookings. Pending bookings are shown separately as tentative and never count toward
      utilization. For creating or editing a booking, use the Dashboard calendar or the Bookings
      page instead; this page is for reading the schedule, not writing to it.
    </p>
  </div>
  <form method="GET" action="{{ route('calendar-data') }}" style="display:flex;gap:8px;flex-wrap:wrap">
    <input type="hidden" name="period" value="{{ $period['mode'] }}">
    <input type="hidden" name="m" value="{{ $period['month'] }}">
    <div>
      <label style="display:block;font-size:.68rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px">Status</label>
      <select name="status" class="form-control" style="min-width:150px" onchange="this.form.submit()">
        <option value="" {{ $status === '' ? 'selected' : '' }}>All Bookings</option>
        <option value="confirmed" {{ $status === 'confirmed' ? 'selected' : '' }}>Confirmed Only</option>
        <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending (Tentative) Only</option>
      </select>
    </div>
    <div>
      <label style="display:block;font-size:.68rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px">Project Type</label>
      <select name="project_type" class="form-control" style="min-width:150px" onchange="this.form.submit()">
        <option value="">All Types</option>
        @foreach ($projectTypeOptions as $val => $label)
        <option value="{{ $val }}" {{ $projectType === $val ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label style="display:block;font-size:.68rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px">Client</label>
      <select name="client_id" class="form-control" style="min-width:150px" onchange="this.form.submit()">
        <option value="0">All Clients</option>
        @foreach ($clientOptions as $c)
        <option value="{{ $c->client_id }}" {{ $clientId === $c->client_id ? 'selected' : '' }}>{{ $c->company_name ?: $c->contact_person }}</option>
        @endforeach
      </select>
    </div>
  </form>
</div>

@include('partials.report-period-bar', [
  'periodRoute' => 'calendar-data',
  'extraParams' => array_filter(['status' => $status ?: null, 'project_type' => $projectType ?: null, 'client_id' => $clientId ?: null]),
])

<div style="font-size:1.1rem;font-weight:700;color:var(--blue-700);margin-bottom:14px">{{ $period['label'] }}</div>

<!-- KPIs -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:22px">
  <div class="stat-card">
    @include('partials.stat-comparison', ['delta' => $calDelta])
    <div class="stat-icon"><i data-feather="calendar"></i></div>
    <div class="stat-value">{{ $kpis['shoot_days'] }} / {{ $kpis['total_days'] }}</div>
    <div class="stat-label">Shoot-Day Utilization · {{ $kpis['utilization'] }}%</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon"><i data-feather="trending-up"></i></div>
    <div class="stat-value">
      {{ $kpis['busiest_day_date'] ? date('M j', strtotime($kpis['busiest_day_date'])) : '—' }}
    </div>
    <div class="stat-label">Busiest Shoot Day · {{ $kpis['busiest_day_count'] }} confirmed shoot{{ $kpis['busiest_day_count'] === 1 ? '' : 's' }}</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="repeat"></i></div>
    <div class="stat-value">{{ $kpis['busiest_weekday'] }}</div>
    <div class="stat-label">Busiest Weekday · {{ $kpis['busiest_weekday_count'] }} confirmed shoot{{ $kpis['busiest_weekday_count'] === 1 ? '' : 's' }} across {{ $kpis['busiest_weekday_occurrences'] }} {{ $kpis['busiest_weekday'] }}{{ $kpis['busiest_weekday_occurrences'] === 1 ? '' : 's' }}</div>
  </div>
  <div class="stat-card" style="--sb:#dc2626">
    <div class="stat-icon" style="background:#fee2e2;color:#dc2626"><i data-feather="alert-circle"></i></div>
    <div class="stat-value">{{ $kpis['longest_gap'] }}</div>
    <div class="stat-label">Longest No-Shoot Gap · consecutive days</div>
  </div>
  <div class="stat-card orange">
    <div class="stat-icon" style="background:var(--orangel);color:var(--orange)"><i data-feather="clock"></i></div>
    <div class="stat-value">{{ $kpis['tentative_days'] }}</div>
    <div class="stat-label">Tentative Shoot Days · pending, not in utilization</div>
  </div>
</div>

@if ($grid)
<!-- Month grid + day-detail side panel -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:22px" class="cal-grid-layout">
  <div class="card" style="margin-bottom:0">
    <div class="card-header">
      <h2 class="card-title">{{ $period['label'] }}</h2>
      <div style="display:flex;gap:6px">
        <a href="{{ $calLinkWith(['m' => $period['prev'], 'day' => null]) }}" class="btn btn-outline btn-sm">&lsaquo; Prev</a>
        <a href="{{ $calLinkWith(['m' => now()->format('Y-m'), 'day' => now()->toDateString()]) }}" class="btn btn-outline btn-sm">Today</a>
        <a href="{{ $calLinkWith(['m' => $period['next'], 'day' => null]) }}" class="btn btn-outline btn-sm">Next &rsaquo;</a>
      </div>
    </div>
    <div class="card-body">
      <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:12px;font-size:.72rem;color:var(--text-muted)">
        <span style="display:flex;align-items:center;gap:5px"><span style="width:11px;height:11px;border-radius:3px;background:#e0f2fe;display:inline-block"></span>1 confirmed shoot</span>
        <span style="display:flex;align-items:center;gap:5px"><span style="width:11px;height:11px;border-radius:3px;background:#7dd3fc;display:inline-block"></span>Multiple confirmed</span>
        <span style="display:flex;align-items:center;gap:5px"><span style="width:11px;height:11px;border-radius:3px;background:#fde68a;display:inline-block"></span>Pending (tentative)</span>
        <span style="display:flex;align-items:center;gap:5px"><span style="width:11px;height:11px;border-radius:3px;background:var(--card-bg,#fff);border:1px solid var(--border);display:inline-block"></span>No shoot</span>
      </div>
      <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:1px;background:var(--border);border:1px solid var(--border);border-radius:6px;overflow:hidden">
        @foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $wd)
        <div style="background:var(--s2);padding:6px;text-align:center;font-size:.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase">{{ $wd }}</div>
        @endforeach
        @foreach ($grid as $week)
          @foreach ($week as $day)
          @php
            $intensity = min(3, $day['count']);
            if ($day['count'] === 0 && $day['pending_count'] > 0) {
              $bg = $day['in_month'] ? '#fde68a' : 'var(--s2)';
            } else {
              $bg = $day['in_month'] ? ['var(--card-bg,#fff)', '#e0f2fe', '#bae6fd', '#7dd3fc'][$intensity] : 'var(--s2)';
            }
            $isSelected = $day['date'] === $selectedDay;
            $hasShoots = $day['count'] > 0 || $day['pending_count'] > 0;
          @endphp
          <div @if ($day['in_month']) onclick="openModal('dayModal-{{ $day['date'] }}')" @endif
             style="min-width:0;overflow:hidden;background:{{ $bg }};min-height:74px;padding:5px;{{ $day['in_month'] ? 'cursor:pointer' : 'opacity:.45' }}{{ $day['is_today'] ? ';box-shadow:inset 0 0 0 2px var(--blue-700)' : '' }}{{ $isSelected ? ';box-shadow:inset 0 0 0 2px #1d4ed8,0 0 0 1px #1d4ed8' : '' }}">
            <div style="font-size:.72rem;font-weight:{{ $day['is_today'] ? '800' : '600' }};color:{{ $day['is_today'] ? 'var(--blue-700)' : 'inherit' }}">{{ $day['day'] }}</div>
            @if ($hasShoots)
            <div style="font-size:.68rem;color:var(--blue-900,#1e3a8a);font-weight:700;margin-top:2px">
              @if ($day['count'] > 0 && $day['pending_count'] > 0)
                {{ $day['count'] }} confirmed · {{ $day['pending_count'] }} pending
              @elseif ($day['count'] > 0)
                {{ $day['count'] }} shoot{{ $day['count'] === 1 ? '' : 's' }}
              @else
                {{ $day['pending_count'] }} pending
              @endif
            </div>
            @endif
          </div>
          @endforeach
        @endforeach
      </div>
    </div>
  </div>

  <!-- Day-detail side panel -->
  <div class="card" style="margin-bottom:0">
    <div class="card-header">
      <h2 class="card-title">{{ date('M j, Y', strtotime($selectedDay)) }}</h2>
      <span class="badge badge-blue">{{ $selectedDayData['count'] + $selectedDayData['pending_count'] }} shoot{{ ($selectedDayData['count'] + $selectedDayData['pending_count']) === 1 ? '' : 's' }}</span>
    </div>
    <div class="card-body" style="max-height:520px;overflow-y:auto">
      @if ($selectedDayData['count'] === 0 && $selectedDayData['pending_count'] === 0)
      <div class="empty-state" style="padding:24px 0"><i data-feather="calendar"></i><h3>No shoots this day</h3></div>
      @else
        @if ($selectedDayData['confirmed']->isNotEmpty())
        <div style="font-size:.7rem;font-weight:700;letter-spacing:.05em;color:var(--blue-700);text-transform:uppercase;margin-bottom:6px">
          Confirmed Shoots ({{ $selectedDayData['confirmed']->count() }})
        </div>
        @foreach ($selectedDayData['confirmed'] as $b)
        <a href="{{ route('booking-detail', $b->booking_id) }}" style="display:block;padding:8px 0;border-bottom:1px solid var(--border);text-decoration:none;color:inherit">
          <div style="font-weight:600;font-size:.85rem">{{ $b->project_title ?: $b->booking_reference }}</div>
          <div style="font-size:.75rem;color:var(--text-muted)">{{ $b->client_name }}</div>
        </a>
        @endforeach
        @endif
        @if ($selectedDayData['pending']->isNotEmpty())
        <div style="font-size:.7rem;font-weight:700;letter-spacing:.05em;color:var(--orange);text-transform:uppercase;margin:14px 0 6px">
          Pending / Tentative ({{ $selectedDayData['pending']->count() }})
        </div>
        @foreach ($selectedDayData['pending'] as $b)
        <a href="{{ route('booking-detail', $b->booking_id) }}" style="display:block;padding:8px 0;border-bottom:1px solid var(--border);text-decoration:none;color:inherit">
          <div style="font-weight:600;font-size:.85rem">{{ $b->project_title ?: $b->booking_reference }}</div>
          <div style="font-size:.75rem;color:var(--text-muted)">{{ $b->client_name }}</div>
        </a>
        @endforeach
        @endif
      @endif
    </div>
    @if ($selectedDayData['count'] + $selectedDayData['pending_count'] > 0)
    <div class="card-body" style="padding-top:0">
      <a href="{{ route('bookings') }}" class="btn btn-outline btn-sm" style="width:100%;text-align:center">View All Bookings for {{ date('M j', strtotime($selectedDay)) }}</a>
    </div>
    @endif
  </div>
</div>

<!-- Day-click modals — one per in-month day, so clicking a cell shows that day's bookings
     instantly without a page reload. -->
@foreach ($grid as $week)
  @foreach ($week as $day)
  @continue(! $day['in_month'])
  @php
    $mConfirmed = $day['items']->filter(fn ($b) => $b->booking_status !== 'pending')->values();
    $mPending = $day['items']->filter(fn ($b) => $b->booking_status === 'pending')->values();
  @endphp
  <div class="modal-overlay" id="dayModal-{{ $day['date'] }}">
    <div class="modal" style="max-width:480px">
      <div class="modal-header">
        <h3><i data-feather="calendar" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>{{ date('F j, Y', strtotime($day['date'])) }}</h3>
        <button class="modal-close" onclick="closeModal('dayModal-{{ $day['date'] }}')">&times;</button>
      </div>
      <div class="modal-body" style="max-height:60vh;overflow-y:auto">
        @if ($mConfirmed->isEmpty() && $mPending->isEmpty())
        <div class="empty-state" style="padding:20px 0"><i data-feather="calendar"></i><h3>No shoots this day</h3></div>
        @else
          @if ($mConfirmed->isNotEmpty())
          <div style="font-size:.7rem;font-weight:700;letter-spacing:.05em;color:var(--blue-700);text-transform:uppercase;margin-bottom:6px">
            Confirmed Shoots ({{ $mConfirmed->count() }})
          </div>
          @foreach ($mConfirmed as $b)
          <a href="{{ route('booking-detail', $b->booking_id) }}" style="display:block;padding:9px 0;border-bottom:1px solid var(--border);text-decoration:none;color:inherit">
            <div style="font-weight:600;font-size:.88rem">{{ $b->project_title ?: $b->booking_reference }}</div>
            <div style="font-size:.76rem;color:var(--text-muted)">{{ $b->client_name }} · {{ $b->booking_reference }}</div>
          </a>
          @endforeach
          @endif
          @if ($mPending->isNotEmpty())
          <div style="font-size:.7rem;font-weight:700;letter-spacing:.05em;color:var(--orange);text-transform:uppercase;margin:14px 0 6px">
            Pending / Tentative ({{ $mPending->count() }})
          </div>
          @foreach ($mPending as $b)
          <a href="{{ route('booking-detail', $b->booking_id) }}" style="display:block;padding:9px 0;border-bottom:1px solid var(--border);text-decoration:none;color:inherit">
            <div style="font-weight:600;font-size:.88rem">{{ $b->project_title ?: $b->booking_reference }}</div>
            <div style="font-size:.76rem;color:var(--text-muted)">{{ $b->client_name }} · {{ $b->booking_reference }}</div>
          </a>
          @endforeach
          @endif
        @endif
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline btn-sm" onclick="closeModal('dayModal-{{ $day['date'] }}')">Close</button>
      </div>
    </div>
  </div>
  @endforeach
@endforeach

<!-- This Month at a Glance -->
<div class="card" style="margin-bottom:22px">
  <div class="card-header"><h2 class="card-title">This Month at a Glance</h2></div>
  <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);padding:16px 20px">
    <div class="stat-card" style="box-shadow:none;border:1px solid var(--border)">
      <div class="stat-icon"><i data-feather="calendar"></i></div>
      <div class="stat-value">{{ $monthGlance['total_bookings'] }}</div>
      <div class="stat-label">Total Bookings · {{ $monthGlance['confirmed_bookings'] }} confirmed · {{ $monthGlance['pending_bookings'] }} pending</div>
    </div>
    <div class="stat-card" style="box-shadow:none;border:1px solid var(--border)">
      <div class="stat-icon"><i data-feather="film"></i></div>
      <div class="stat-value">{{ $monthGlance['shoot_days'] }}</div>
      <div class="stat-label">Total Shoot Days · {{ $monthGlance['pending_only_days'] }} pending · {{ $monthGlance['free_days'] }} free</div>
    </div>
    <div class="stat-card" style="box-shadow:none;border:1px solid var(--border)">
      <div class="stat-icon"><i data-feather="bar-chart-2"></i></div>
      <div class="stat-value">{{ $monthGlance['avg_per_shoot_day'] }}</div>
      <div class="stat-label">Average Bookings per Shoot Day · confirmed only</div>
    </div>
    <div class="stat-card" style="box-shadow:none;border:1px solid var(--border)">
      <div class="stat-icon"><i data-feather="trending-up"></i></div>
      <div class="stat-value" style="font-size:1.1rem">
        @if ($monthGlance['busiest_week'])
          {{ date('M j', strtotime($monthGlance['busiest_week']['from'])) }} – {{ date('j', strtotime($monthGlance['busiest_week']['to'])) }}
        @else
          —
        @endif
      </div>
      <div class="stat-label">Busiest Week{{ $monthGlance['busiest_week'] ? ' · ' . $monthGlance['busiest_week']['count'] . ' confirmed shoots' : '' }}</div>
    </div>
  </div>
</div>
@endif

@if ($monthlySummary)
<!-- Monthly summary (non-month periods) -->
<div class="card" style="margin-bottom:22px">
  <div class="card-header"><h2 class="card-title">Monthly Summary</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Month</th><th style="text-align:right">Shoot Days</th><th style="text-align:right">Tentative Days</th><th style="text-align:right">Confirmed Bookings on Busiest Day</th><th>Busiest Day</th></tr></thead>
      <tbody>
      @foreach ($monthlySummary as $m)
      <tr>
        <td style="font-weight:600">{{ $m->label }}</td>
        <td style="text-align:right">{{ $m->shoot_days }}</td>
        <td style="text-align:right;color:var(--orange)">{{ $m->tentative_days }}</td>
        <td style="text-align:right">{{ $m->busiest_count }}</td>
        <td style="font-size:.83rem;color:var(--text-muted)">{{ $m->busiest_date ? date('M j, Y', strtotime($m->busiest_date)) : '—' }}</td>
      </tr>
      @endforeach
      </tbody>
    </table>
  </div>
</div>
@endif

<!-- Upcoming shoots -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title">Upcoming Shoots <span class="badge badge-gray" style="margin-left:4px">{{ $upcoming->count() }}</span></h2>
    <a href="{{ $calLinkWith(['period' => 'month', 'm' => now()->format('Y-m'), 'day' => now()->toDateString()]) }}" class="btn btn-outline btn-sm"><i data-feather="calendar"></i> View Calendar</a>
  </div>
  <div class="table-wrap">
    @if ($upcoming->isEmpty())
    <div class="empty-state"><i data-feather="calendar"></i><h3>No upcoming shoots in this period</h3></div>
    @else
    <table>
      <thead><tr><th>Shoot Dates</th><th>Project</th><th>Client</th><th style="text-align:right">Crew</th><th style="text-align:right">Equipment</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      @foreach ($upcoming as $b)
      <tr>
        <td style="white-space:nowrap;font-size:.83rem">
          {{ date('M j', strtotime($b->shoot_date_start)) }}
          @if ($b->shoot_date_start !== $b->shoot_date_end)–{{ date('M j, Y', strtotime($b->shoot_date_end)) }}@else, {{ date('Y', strtotime($b->shoot_date_start)) }}@endif
        </td>
        <td>
          <div style="font-weight:600">{{ $b->project_title ?: '—' }}</div>
          <div style="font-size:.72rem;color:var(--text-muted);font-family:monospace">{{ $b->booking_reference }}</div>
        </td>
        <td style="font-size:.85rem">{{ $b->client_name ?: '—' }}</td>
        <td style="text-align:right">{{ $b->crew_count }}</td>
        <td style="text-align:right">{{ $b->equipment_count }}</td>
        <td><span class="badge {{ $statusBadge[$b->booking_status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_', ' ', $b->booking_status)) }}</span></td>
        <td>
          <a href="{{ route('booking-detail', $b->booking_id) }}" class="btn btn-outline btn-sm" title="View booking"><i data-feather="eye"></i></a>
        </td>
      </tr>
      @endforeach
      </tbody>
    </table>
    @endif
  </div>
</div>

@endsection
