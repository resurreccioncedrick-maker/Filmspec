@extends('layouts.app')

@section('pageTitle', 'Crew Data')

@section('breadcrumb')
<span>Crew Data</span>
@endsection

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
@endpush

@section('content')

<div style="margin-bottom:14px">
  <h1 style="font-size:1.4rem;margin:0 0 4px">Crew data</h1>
  <p style="font-size:.85rem;color:var(--text-muted);max-width:640px;margin:0">
    Shoots and talent fees per person, from the crew assigned to every booking with a confirmed
    cost estimate. Reassigning crew updates this page straight away.
  </p>
</div>

@include('partials.report-period-bar', [
  'periodRoute' => 'crew-data',
  'extraParams' => array_filter(['q' => $search]),
])

<div style="display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:10px;margin-bottom:14px">
  <div style="font-size:1.1rem;font-weight:700;color:var(--blue-700)">{{ $period['label'] }}</div>
  <div style="font-size:.83rem;color:var(--text-muted)">
    {{ $kpis['shoots'] }} confirmed shoot{{ $kpis['shoots'] === 1 ? '' : 's' }} ·
    crew spend ₱{{ number_format($kpis['spend'], 2) }}
  </div>
</div>

<!-- KPIs -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:22px">
  <div class="stat-card green">
    @include('partials.stat-comparison', ['delta' => $crewDeltas['spend']])
    <div class="stat-icon"><i data-feather="dollar-sign"></i></div>
    <div class="stat-value">₱{{ number_format($kpis['spend'], 2) }}</div>
    <div class="stat-label" title="Recalculated live from current crew assignments, with no-show/back-out days deducted — will differ from Cost Estimates' frozen quoted figure if anything changed after the estimate was confirmed.">Crew Spend (Live) · {{ $period['label'] }}</div>
  </div>
  <div class="stat-card">
    @include('partials.stat-comparison', ['delta' => $crewDeltas['crew_booked']])
    <div class="stat-icon"><i data-feather="users"></i></div>
    <div class="stat-value">{{ $kpis['crew_booked'] }}</div>
    <div class="stat-label">Crew Booked · people in this window</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="calendar"></i></div>
    <div class="stat-value">₱{{ number_format($kpis['avg_per_month'], 2) }}</div>
    <div class="stat-label">Average Per Month · over {{ $kpis['chart_months'] }} months</div>
  </div>
</div>

<!-- Spend chart -->
<div class="card" style="margin-bottom:22px">
  <div class="card-header">
    <h2 class="card-title">Crew Spend
      <span style="font-weight:400;color:var(--text-muted);font-size:.8rem">
        {{ $monthly->first()->label ?? '' }} – {{ $monthly->last()->label ?? '' }}
      </span>
    </h2>
    <div class="tabs" style="margin-bottom:0">
      @foreach ([6, 12] as $cm)
      <a href="{{ route('crew-data', array_filter(['period' => $period['mode'], 'm' => $period['month'], 'chart' => $cm, 'q' => $search])) }}"
         class="tab-btn {{ $kpis['chart_months'] === $cm ? 'active' : '' }}">{{ $cm }} MONTHS</a>
      @endforeach
    </div>
  </div>
  <div class="card-body">
    <div style="height:220px"><canvas id="crewSpendChart"></canvas></div>
  </div>
</div>

<!-- Search -->
<div class="card" style="margin-bottom:14px">
  <div class="card-body">
    <form method="GET" action="{{ route('crew-data') }}">
      <input type="hidden" name="period" value="{{ $period['mode'] }}">
      <input type="hidden" name="m" value="{{ $period['month'] }}">
      <input type="hidden" name="chart" value="{{ $kpis['chart_months'] }}">
      <div class="filter-bar">
        <div class="search-input-wrap">
          <i data-feather="search"></i>
          <input type="text" name="q" class="form-control" placeholder="Search person or role…" value="{{ $search }}">
        </div>
        <button type="submit" class="btn btn-primary btn-sm"><i data-feather="filter"></i> Filter</button>
      </div>
    </form>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px" class="crew-data-grid">
  <!-- People -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">People <span class="badge badge-gray" style="margin-left:4px">{{ $byPerson->count() }}</span></h2>
    </div>
    <div class="table-wrap">
      @if ($byPerson->isEmpty())
      <div class="empty-state"><i data-feather="users"></i><h3>No crew in this period</h3></div>
      @else
      <table>
        <thead><tr><th>Name</th><th style="text-align:right">Shoots</th><th style="text-align:right">Days</th><th style="text-align:right">No-Shows</th><th style="text-align:right">Paid</th></tr></thead>
        <tbody>
        @foreach ($byPerson as $p)
        <tr>
          <td>
            <div style="font-weight:600">{{ $p->name }}</div>
            <div style="font-size:.72rem;color:var(--text-muted)">
              {{ $p->positions ?: 'Unassigned' }}
              @if ($p->last_worked) · last {{ date('Y-m-d', strtotime($p->last_worked)) }} @endif
            </div>
          </td>
          <td style="text-align:right">{{ $p->shoots }}</td>
          <td style="text-align:right">{{ rtrim(rtrim(number_format($p->days, 1), '0'), '.') }}</td>
          <td style="text-align:right">
            @if ($p->no_shows > 0)
            <span class="badge badge-red" title="Days excluded from pay for logged no-show/back-out attendance">{{ $p->no_shows }}</span>
            @else
            <span style="color:var(--text-muted)">—</span>
            @endif
          </td>
          <td style="text-align:right;font-weight:700">₱{{ number_format($p->paid, 2) }}</td>
        </tr>
        @endforeach
        </tbody>
      </table>
      @endif
    </div>
  </div>

  <!-- Roles -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Roles <span class="badge badge-gray" style="margin-left:4px">{{ $byRole->count() }}</span></h2>
    </div>
    <div class="table-wrap">
      @if ($byRole->isEmpty())
      <div class="empty-state"><i data-feather="briefcase"></i><h3>No roles in this period</h3></div>
      @else
      <table>
        <thead><tr><th>Role</th><th style="text-align:right">Shoots</th><th style="text-align:right">Headcount</th><th style="text-align:right">Paid</th></tr></thead>
        <tbody>
        @foreach ($byRole as $r)
        <tr>
          <td style="font-weight:600">{{ $r->role }}</td>
          <td style="text-align:right">{{ $r->shoots }}</td>
          <td style="text-align:right">{{ $r->headcount }}</td>
          <td style="text-align:right;font-weight:700">₱{{ number_format($r->paid, 2) }}</td>
        </tr>
        @endforeach
        </tbody>
      </table>
      @endif
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
  const el = document.getElementById('crewSpendChart');
  if (!el || typeof Chart === 'undefined') return;
  new Chart(el, {
    type: 'bar',
    data: {
      labels: @json($monthly->pluck('label')),
      datasets: [{ label: 'Crew spend', data: @json($monthly->pluck('spend')), backgroundColor: '#2e9e7a', borderRadius: 4 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { callback: v => '₱' + Number(v).toLocaleString('en-PH') } } }
    }
  });
})();
</script>
@endpush
