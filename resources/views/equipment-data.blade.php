@extends('layouts.app')

@section('pageTitle', 'Equipment Analytics')

@section('breadcrumb')
<span>Equipment Analytics</span>
@endsection

@section('topbarActions')
@include('partials.export-dropdown', ['id' => 'EquipmentData'])
@endsection

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
@endpush

@section('content')
@php
  $baseParams = ['period' => $period['mode'], 'm' => $period['month'], 'chart' => $kpis['chart_months'], 'category' => $category ?: null, 'ownership' => $ownership ?: null];
  $linkWith = fn (array $o) => route('equipment-data', array_merge($baseParams, ['sort' => $sort, 'all' => $showAll ? 1 : null], $o));
@endphp

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:14px;margin-bottom:14px">
  <div>
    <h1 style="font-size:1.4rem;margin:0 0 4px">Equipment Analytics</h1>
    <p style="font-size:.85rem;color:var(--text-muted);max-width:680px;margin:0">
      Equipment from current confirmed Cost Estimates — all FilmSpec-owned catalog gear. Nothing is loaded by hand.
    </p>
  </div>
  <form method="GET" action="{{ route('equipment-data') }}" style="display:flex;gap:8px;flex-wrap:wrap">
    <input type="hidden" name="period" value="{{ $period['mode'] }}">
    <input type="hidden" name="m" value="{{ $period['month'] }}">
    <input type="hidden" name="chart" value="{{ $kpis['chart_months'] }}">
    <input type="hidden" name="sort" value="{{ $sort }}">
    <div>
      <label style="display:block;font-size:.68rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px">Ownership</label>
      <select name="ownership" class="form-control" style="min-width:140px" onchange="this.form.submit()">
        <option value="" {{ $ownership === '' ? 'selected' : '' }}>All</option>
        <option value="filmspec" {{ $ownership === 'filmspec' ? 'selected' : '' }}>FilmSpec-Owned</option>
      </select>
    </div>
    <div>
      <label style="display:block;font-size:.68rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px">Equipment Category</label>
      <select name="category" class="form-control" style="min-width:160px" onchange="this.form.submit()">
        <option value="">All</option>
        @foreach ($categoryOptions as $opt)
        <option value="{{ $opt }}" {{ $category === $opt ? 'selected' : '' }}>{{ $opt }}</option>
        @endforeach
      </select>
    </div>
  </form>
</div>

@include('partials.report-period-bar', [
  'periodRoute' => 'equipment-data',
  'extraParams' => array_filter(['sort' => $sort, 'all' => $showAll ? 1 : null, 'category' => $category ?: null, 'ownership' => $ownership ?: null]),
])

<div style="font-size:1.1rem;font-weight:700;color:var(--blue-700);margin-bottom:14px">{{ $period['label'] }}</div>

<!-- KPIs -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:22px">
  <div class="stat-card">
    @include('partials.stat-comparison', ['delta' => $equipDeltas['fs_earned']])
    <div class="stat-icon"><i data-feather="dollar-sign"></i></div>
    <div class="stat-value">₱{{ number_format($kpis['fs_earned'], 2) }}</div>
    <div class="stat-label">Confirmed Equipment Value · {{ $period['label'] }}</div>
  </div>
  <div class="stat-card">
    @include('partials.stat-comparison', ['delta' => $equipDeltas['shoots']])
    <div class="stat-icon"><i data-feather="film"></i></div>
    <div class="stat-value">{{ $kpis['shoots'] }}</div>
    <div class="stat-label">Confirmed CE Shoots</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="camera"></i></div>
    <div class="stat-value">{{ $usageTotalCount }}</div>
    <div class="stat-label">Equipment Models Quoted</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="calendar"></i></div>
    <div class="stat-value">{{ $kpis['quoted_days'] }}</div>
    <div class="stat-label">Quoted Rental Days</div>
  </div>
</div>
<div style="font-size:11px;color:var(--text-muted);margin:-14px 0 22px">
  Data shown is based on current confirmed Cost Estimates. Only the current active confirmed version of each CE is counted — draft, cancelled, and superseded revisions are excluded.
</div>

<!-- Earnings trend -->
<div class="card" style="margin-bottom:22px">
  <div class="card-header">
    <h2 class="card-title">Confirmed Equipment Value Trend
      <span style="font-weight:400;color:var(--text-muted);font-size:.8rem">
        {{ $monthly->first()->label ?? '' }} – {{ $monthly->last()->label ?? '' }}
      </span>
    </h2>
    <div class="tabs" style="margin-bottom:0">
      @foreach ([6, 12] as $cm)
      <a href="{{ route('equipment-data', array_filter(['period' => $period['mode'], 'm' => $period['month'], 'chart' => $cm, 'sort' => $sort, 'all' => $showAll ? 1 : null])) }}"
         class="tab-btn {{ $kpis['chart_months'] === $cm ? 'active' : '' }}">{{ $cm }} MONTHS</a>
      @endforeach
    </div>
  </div>
  <div class="card-body">
    <div style="height:220px"><canvas id="equipEarningsChart"></canvas></div>
  </div>
</div>

<!-- Top equipment -->
<div class="card" style="margin-bottom:22px">
  <div class="card-header">
    <h2 class="card-title">Top Equipment by Confirmed Value <span style="font-weight:400;color:var(--text-muted);font-size:.8rem">{{ $period['label'] }}</span></h2>
    <div class="tabs" style="margin-bottom:0">
      @foreach (['pesos' => 'By Value', 'quantity' => 'By Quantity', 'days' => 'By Rental Days'] as $k => $l)
      <a href="{{ $linkWith(['sort' => $k]) }}" class="tab-btn {{ $sort === $k ? 'active' : '' }}">{{ strtoupper($l) }}</a>
      @endforeach
    </div>
  </div>
  <div class="card-body">
    @if ($topEquipment->isEmpty())
    <div class="empty-state"><i data-feather="bar-chart-2"></i><h3>No equipment used in this period</h3></div>
    @else
    <div style="height:{{ max(160, $topEquipment->count() * 34) }}px"><canvas id="topEquipChart"></canvas></div>
    @endif
  </div>
</div>

@if ($missingCe->count() > 0)
<div class="alert alert-warning" style="margin-bottom:16px;font-size:.83rem;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
  <div>
    <i data-feather="alert-circle"></i>
    {{ $missingCe->count() }} booking{{ $missingCe->count() === 1 ? '' : 's' }} in this period do not yet have a confirmed Cost Estimate and
    {{ $missingCe->count() === 1 ? 'is' : 'are' }} excluded from this analytics page —
    {{ $missingCe->take(4)->map(fn ($b) => $b->project_title ?: $b->booking_reference)->implode(', ') }}@if ($missingCe->count() > 4) and {{ $missingCe->count() - 4 }} more @endif.
    They appear here once confirmed.
  </div>
  <a href="{{ route('bookings', ['status' => 'pending']) }}" class="btn btn-outline btn-sm" style="flex-shrink:0">View Bookings</a>
</div>
@endif

<!-- Sort controls -->
<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px">
  <span style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;font-weight:700">Sort by</span>
  <div class="tabs" style="margin-bottom:0">
    @foreach (['pesos' => 'Value', 'quantity' => 'Quantity', 'days' => 'Quoted Days'] as $k => $l)
    <a href="{{ $linkWith(['sort' => $k]) }}" class="tab-btn {{ $sort === $k ? 'active' : '' }}">{{ strtoupper($l) }}</a>
    @endforeach
  </div>
  <a href="{{ $linkWith(['all' => $showAll ? null : 1]) }}" class="btn btn-outline btn-sm">
    {{ $showAll ? 'Show top ' . $defaultLimit : 'Show all items' }}
  </a>
  <span style="font-size:.75rem;color:var(--text-muted)">showing {{ $shownCount }} of {{ $usageTotalCount }}</span>
</div>

<div style="display:grid;grid-template-columns:1.6fr 1fr;gap:16px">
  <!-- Equipment Breakdown table -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Equipment Breakdown</h2>
      <span style="font-size:.72rem;color:var(--text-muted)">quoted value on confirmed CEs, at CE rates</span>
    </div>
    <div class="table-wrap">
      @if ($usageShown->isEmpty())
      <div class="empty-state"><i data-feather="camera"></i><h3>No equipment quoted in this period</h3></div>
      @else
      <table>
        <thead><tr><th>#</th><th>Equipment</th><th>Brand</th><th style="text-align:right">Qty Quoted</th><th style="text-align:right">Quoted Days</th><th style="text-align:right">Confirmed Value</th></tr></thead>
        <tbody>
        @foreach ($usageShown as $i => $r)
        <tr>
          <td style="color:var(--text-muted)">{{ $i + 1 }}</td>
          <td style="font-weight:600">{{ $r->equipment_name }}</td>
          <td style="color:var(--text-muted)">{{ $r->brand ?: '—' }}</td>
          <td style="text-align:right">{{ (int) $r->total_qty }}</td>
          <td style="text-align:right">{{ (int) $r->total_days }}</td>
          <td style="text-align:right;font-weight:700;color:var(--blue-700)">₱{{ number_format($r->earnings, 2) }}</td>
        </tr>
        @endforeach
        </tbody>
      </table>
      @endif

      @if ($neverUsed->count() > 0)
      <details style="margin:10px 16px 14px;border-top:1px solid var(--border);padding-top:10px">
        <summary style="font-size:12px;color:var(--muted);cursor:pointer">
          Never Quoted — {{ $neverUsed->count() }} item{{ $neverUsed->count() === 1 ? '' : 's' }} not on any confirmed CE in this period
        </summary>
        <div style="margin-top:8px">
          @foreach ($neverUsed as $n)
          <div style="font-size:.8rem;padding:3px 0;color:var(--text-muted)">
            {{ $n->equipment_name }}@if ($n->brand) <span style="font-size:.72rem">({{ $n->brand }})</span>@endif
          </div>
          @endforeach
        </div>
      </details>
      @endif
    </div>
  </div>

  <!-- Equipment by Category donut -->
  <div class="card">
    <div class="card-header"><h2 class="card-title">Equipment by Category <span style="font-weight:400;color:var(--text-muted);font-size:.8rem">{{ $period['label'] }}</span></h2></div>
    <div class="card-body">
      @if ($categoryBreakdown->isEmpty())
      <div class="empty-state"><i data-feather="pie-chart"></i><h3>No equipment quoted in this period</h3></div>
      @else
      <div style="position:relative;height:180px;display:flex;align-items:center;justify-content:center">
        <canvas id="categoryDonutChart"></canvas>
      </div>
      <div style="margin-top:10px">
        @php $catTotal = $categoryBreakdown->sum('earnings') ?: 1; @endphp
        @foreach ($categoryBreakdown as $c)
        <div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;font-size:.8rem">
          <span>{{ $c->category }}</span>
          <span style="color:var(--text-muted)">₱{{ number_format($c->earnings, 0) }} · {{ round($c->earnings / $catTotal * 100) }}%</span>
        </div>
        @endforeach
      </div>
      @endif
    </div>
  </div>
</div>

<!-- Bookings with Confirmed Equipment CEs (Part 12) -->
<div style="margin-top:26px">
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Bookings with Confirmed Equipment CEs</h2>
      <div style="font-size:.8rem;color:var(--text-muted)">
        {{ $shootTotals['shoots'] }} Confirmed CE Shoot{{ $shootTotals['shoots'] === 1 ? '' : 's' }} ·
        {{ $shootTotals['matched'] }} Equipment Line{{ $shootTotals['matched'] === 1 ? '' : 's' }} Matched
      </div>
    </div>
    <div class="card-body" style="padding-bottom:0">
      <p style="font-size:.8rem;color:var(--text-muted);margin:0 0 14px;max-width:680px">
        FilmSpec equipment lines from each confirmed cost estimate, matched to the catalog automatically. "Equipment Lines Matched" is a sum across all shoots below, not a count of shoots.
      </p>
      <form method="GET">
        <input type="hidden" name="period" value="{{ $period['mode'] }}">
        <input type="hidden" name="m" value="{{ $period['month'] }}">
        <input type="hidden" name="chart" value="{{ $kpis['chart_months'] }}">
        <input type="hidden" name="sort" value="{{ $sort }}">
        <input type="hidden" name="category" value="{{ $category }}">
        <input type="hidden" name="ownership" value="{{ $ownership }}">
        <div class="filter-bar">
          <div class="search-input-wrap">
            <i data-feather="search"></i>
            <input type="text" name="q" class="form-control" placeholder="Search project, client or CE #…" value="{{ $search }}">
          </div>
          <button type="submit" class="btn btn-primary btn-sm"><i data-feather="filter"></i> Filter</button>
        </div>
      </form>
    </div>

    <div class="table-wrap">
      @if ($pastShoots->isEmpty())
      <div class="empty-state">
        <i data-feather="calendar"></i><h3>No confirmed shoots in this period</h3>
        <p>Shoots appear here once their cost estimate is confirmed.</p>
      </div>
      @else
      <table>
        <thead>
          <tr>
            <th>Project / Booking</th><th>Shoot Dates</th><th>Client</th><th>CE #</th>
            <th>Equipment</th><th style="text-align:right">Confirmed Value</th><th>Status</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
        @foreach ($pastShoots as $s)
        <tr>
          <td>
            <div style="font-weight:600">{{ $s->project_title }}</div>
            <div style="font-size:.72rem;color:var(--muted);font-family:monospace">{{ $s->ce_type_label }}</div>
          </td>
          <td style="white-space:nowrap;font-size:.83rem">
            {{ date('M j, Y', strtotime($s->shoot_date_start)) }}
            @if ($s->shoot_date_end !== $s->shoot_date_start)–{{ date('j, Y', strtotime($s->shoot_date_end)) }}@endif
          </td>
          <td style="font-size:.85rem">{{ $s->client_name ?: '—' }}</td>
          <td>
            <a href="{{ route('ce-preview', ['booking_id' => $s->booking_id, 'ce_id' => $s->ce_id]) }}"
               style="font-family:monospace;font-size:.82rem;color:var(--accent)">{{ $s->ce_reference }}</a>
          </td>
          <td>
            @if ($s->matched_count > 0)
            <details>
              <summary style="cursor:pointer;font-size:.83rem">{{ $s->matched_count }} item{{ $s->matched_count === 1 ? '' : 's' }}</summary>
              <div style="margin-top:6px;min-width:220px">
                @foreach ($s->matched as $m)
                <div style="font-size:.76rem;padding:2px 0;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:5px">
                  <i data-feather="check-circle" style="width:10px;height:10px;color:var(--green)" title="Matched to catalog"></i>
                  <span style="font-family:monospace;color:var(--text-muted)">{{ $m->quantity }}×</span>
                  {{ $m->equipment_name }}
                  <span style="color:var(--text-muted)">· {{ $m->days }}d</span>
                </div>
                @endforeach
              </div>
            </details>
            @else
            <span style="color:var(--text-muted);font-size:.83rem">No FS equipment</span>
            @endif
          </td>
          <td style="text-align:right;font-weight:700;color:var(--blue-700);white-space:nowrap">₱{{ number_format($s->matched_value, 2) }}</td>
          <td><span class="badge badge-green">&check; Confirmed</span></td>
          <td>
            <div style="display:flex;gap:6px">
              <a href="{{ route('booking-detail', $s->booking_id) }}" class="btn btn-outline btn-sm" title="View booking"><i data-feather="calendar"></i></a>
              <a href="{{ route('ce-preview', ['booking_id' => $s->booking_id, 'ce_id' => $s->ce_id]) }}" class="btn btn-outline btn-sm" title="Create Revision — adding/editing lines on the CE page creates a new revision once a CE is confirmed"><i data-feather="edit-2"></i></a>
            </div>
          </td>
        </tr>
        @endforeach
        </tbody>
      </table>

      <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 18px;flex-wrap:wrap;gap:10px">
        <span style="font-size:.8rem;color:var(--text-muted)">Showing {{ $pastShoots->count() }} of {{ $pastShootsTotal }} booking{{ $pastShootsTotal === 1 ? '' : 's' }}</span>
        @if ($pastShootsPages > 1)
        <div style="display:flex;gap:6px">
          @for ($p = 1; $p <= $pastShootsPages; $p++)
          <a href="{{ $linkWith(['p' => $p, 'q' => $search ?: null]) }}" class="btn btn-sm {{ $p === $page ? 'btn-primary' : 'btn-outline' }}">{{ $p }}</a>
          @endfor
        </div>
        @endif
      </div>
      @endif
    </div>
  </div>
</div>

@push('scripts')
<script>
(function () {
  const trendEl = document.getElementById('equipEarningsChart');
  if (trendEl && typeof Chart !== 'undefined') {
    new Chart(trendEl, {
      type: 'bar',
      data: {
        labels: @json($monthly->pluck('label')),
        datasets: [{ label: 'Confirmed Value', data: @json($monthly->pluck('earnings')), backgroundColor: '#0060C7', borderRadius: 4 }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { callback: v => '₱' + Number(v).toLocaleString('en-PH') } } }
      }
    });
  }

  const topEl = document.getElementById('topEquipChart');
  if (topEl && typeof Chart !== 'undefined') {
    const sortMode = @json($sort);
    const metricKey = { pesos: 'earnings', quantity: 'total_qty', days: 'total_days' }[sortMode];
    const metricLabel = { pesos: 'Confirmed Value', quantity: 'Qty Quoted', days: 'Quoted Days' }[sortMode];
    @php
      $topEquipJs = $topEquipment->map(fn ($r) => [
          'name' => $r->equipment_name, 'earnings' => (float) $r->earnings,
          'total_qty' => (int) $r->total_qty, 'total_days' => (int) $r->total_days,
      ]);
    @endphp
    const topData = @json($topEquipJs);
    new Chart(topEl, {
      type: 'bar',
      data: {
        labels: topData.map(r => r.name),
        datasets: [{ label: metricLabel, data: topData.map(r => r[metricKey]), backgroundColor: '#0060C7', borderRadius: 4 }]
      },
      options: {
        indexAxis: 'y',
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true, ticks: { callback: v => sortMode === 'pesos' ? ('₱' + Number(v).toLocaleString('en-PH')) : Number(v).toLocaleString('en-PH') } } }
      }
    });
  }

  const catEl = document.getElementById('categoryDonutChart');
  if (catEl && typeof Chart !== 'undefined') {
    const catData = @json($categoryBreakdown ?? collect());
    const palette = ['#0060C7', '#2e9e7a', '#d97706', '#dc2626', '#7c3aed', '#0891b2', '#64748b'];
    new Chart(catEl, {
      type: 'doughnut',
      data: {
        labels: catData.map(c => c.category),
        datasets: [{ data: catData.map(c => c.earnings), backgroundColor: catData.map((_, i) => palette[i % palette.length]) }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ctx.label + ': ₱' + Number(ctx.parsed).toLocaleString('en-PH') } } }
      }
    });
  }
})();
</script>
@endpush

@endsection
