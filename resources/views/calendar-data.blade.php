@extends('layouts.app')

@section('pageTitle', 'Calendar Data')

@section('breadcrumb')
<span>Calendar Data</span>
@endsection

@section('content')

<div style="margin-bottom:14px">
  <h1 style="font-size:1.4rem;margin:0 0 4px">Calendar data</h1>
  <p style="font-size:.85rem;color:var(--text-muted);max-width:640px;margin:0">
    How busy the shoot calendar is — density, gaps and utilization, read from every booking
    that isn't cancelled. For creating or editing a booking, use the Dashboard calendar or the
    Bookings page instead; this page is for reading the schedule, not writing to it.
  </p>
</div>

@include('partials.report-period-bar', ['periodRoute' => 'calendar-data'])

<div style="font-size:1.1rem;font-weight:700;color:var(--blue-700);margin-bottom:14px">{{ $period['label'] }}</div>

<!-- KPIs -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:22px">
  <div class="stat-card">
    @include('partials.stat-comparison', ['delta' => $calDelta])
    <div class="stat-icon"><i data-feather="calendar"></i></div>
    <div class="stat-value">{{ $kpis['shoot_days'] }} / {{ $kpis['total_days'] }}</div>
    <div class="stat-label">Shoot Days · {{ $kpis['utilization'] }}% utilization</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon"><i data-feather="trending-up"></i></div>
    <div class="stat-value">
      {{ $kpis['busiest_day_date'] ? date('M j', strtotime($kpis['busiest_day_date'])) : '—' }}
    </div>
    <div class="stat-label">Busiest Day · {{ $kpis['busiest_day_count'] }} booking{{ $kpis['busiest_day_count'] === 1 ? '' : 's' }}</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="repeat"></i></div>
    <div class="stat-value">{{ $kpis['busiest_weekday'] }}</div>
    <div class="stat-label">Busiest Day of Week</div>
  </div>
  <div class="stat-card" style="--sb:#dc2626">
    <div class="stat-icon" style="background:#fee2e2;color:#dc2626"><i data-feather="alert-circle"></i></div>
    <div class="stat-value">{{ $kpis['longest_gap'] }}</div>
    <div class="stat-label">Longest Gap · consecutive days with no shoot</div>
  </div>
</div>

@if ($grid)
<!-- Month grid -->
<div class="card" style="margin-bottom:22px">
  <div class="card-header">
    <h2 class="card-title">{{ $period['label'] }}</h2>
    <div style="display:flex;gap:6px">
      <a href="{{ route('calendar-data', ['period' => 'month', 'm' => $period['prev']]) }}" class="btn btn-outline btn-sm">&lsaquo; Prev</a>
      <a href="{{ route('calendar-data', ['period' => 'month', 'm' => now()->format('Y-m')]) }}" class="btn btn-outline btn-sm">Today</a>
      <a href="{{ route('calendar-data', ['period' => 'month', 'm' => $period['next']]) }}" class="btn btn-outline btn-sm">Next &rsaquo;</a>
    </div>
  </div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:1px;background:var(--border);border:1px solid var(--border);border-radius:6px;overflow:hidden">
      @foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $wd)
      <div style="background:var(--s2);padding:6px;text-align:center;font-size:.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase">{{ $wd }}</div>
      @endforeach
      @foreach ($grid as $week)
        @foreach ($week as $day)
        @php
          $intensity = min(3, $day['count']);
          $bg = $day['in_month'] ? ['var(--card-bg,#fff)', '#e0f2fe', '#bae6fd', '#7dd3fc'][$intensity] : 'var(--s2)';
        @endphp
        <div style="background:{{ $bg }};min-height:74px;padding:5px;{{ $day['in_month'] ? '' : 'opacity:.45' }}{{ $day['is_today'] ? ';box-shadow:inset 0 0 0 2px var(--blue-700)' : '' }}">
          <div style="font-size:.72rem;font-weight:{{ $day['is_today'] ? '800' : '600' }};color:{{ $day['is_today'] ? 'var(--blue-700)' : 'inherit' }}">{{ $day['day'] }}</div>
          @if ($day['count'] > 0)
          <div style="font-size:.68rem;color:var(--blue-900,#1e3a8a);font-weight:700;margin-top:2px">{{ $day['count'] }} shoot{{ $day['count'] === 1 ? '' : 's' }}</div>
          @foreach ($day['items']->take(2) as $it)
          <div style="font-size:.64rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="{{ $it->project_title ?: $it->booking_reference }}">
            {{ $it->project_title ?: $it->booking_reference }}
          </div>
          @endforeach
          @if ($day['items']->count() > 2)
          <div style="font-size:.62rem;color:var(--text-muted)">+{{ $day['items']->count() - 2 }} more</div>
          @endif
          @endif
        </div>
        @endforeach
      @endforeach
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
      <thead><tr><th>Month</th><th style="text-align:right">Shoot Days</th><th style="text-align:right">Bookings on Busiest Day</th><th>Busiest Day</th></tr></thead>
      <tbody>
      @foreach ($monthlySummary as $m)
      <tr>
        <td style="font-weight:600">{{ $m->label }}</td>
        <td style="text-align:right">{{ $m->shoot_days }}</td>
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
  </div>
  <div class="table-wrap">
    @if ($upcoming->isEmpty())
    <div class="empty-state"><i data-feather="calendar"></i><h3>No upcoming shoots in this period</h3></div>
    @else
    <table>
      <thead><tr><th>Dates</th><th>Project</th><th>Client</th><th>Status</th></tr></thead>
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
        <td><span class="badge {{ $statusBadge[$b->booking_status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_', ' ', $b->booking_status)) }}</span></td>
      </tr>
      @endforeach
      </tbody>
    </table>
    @endif
  </div>
</div>

@endsection
