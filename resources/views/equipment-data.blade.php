@extends('layouts.app')

@section('pageTitle', 'Equipment Data')

@section('breadcrumb')
<span>Equipment Data</span>
@endsection

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
@endpush

@section('content')
@php
  $baseParams = ['period' => $period['mode'], 'm' => $period['month'], 'chart' => $kpis['chart_months']];
  $linkWith = fn (array $o) => route('equipment-data', array_merge($baseParams, ['sort' => $sort, 'all' => $showAll ? 1 : null], $o));
@endphp

<div style="margin-bottom:14px">
  <h1 style="font-size:1.4rem;margin:0 0 4px">Equipment data</h1>
  <p style="font-size:.85rem;color:var(--text-muted);max-width:680px;margin:0">
    Every shoot with a <strong>confirmed</strong> cost estimate, straight from the CE — ours and
    partner-fronted alike, since both rent out our gear. Nothing is loaded by hand.
  </p>
</div>

@include('partials.report-period-bar', [
  'periodRoute' => 'equipment-data',
  'extraParams' => array_filter(['sort' => $sort, 'all' => $showAll ? 1 : null]),
])

<div style="font-size:1.1rem;font-weight:700;color:var(--blue-700);margin-bottom:14px">{{ $period['label'] }}</div>

<!-- KPIs -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:22px">
  <div class="stat-card">
    @include('partials.stat-comparison', ['delta' => $equipDeltas['fs_earned']])
    <div class="stat-icon"><i data-feather="dollar-sign"></i></div>
    <div class="stat-value">₱{{ number_format($kpis['fs_earned'], 2) }}</div>
    <div class="stat-label">Earned on FS Gear · {{ $period['label'] }}</div>
  </div>
  <div class="stat-card">
    @include('partials.stat-comparison', ['delta' => $equipDeltas['shoots']])
    <div class="stat-icon"><i data-feather="film"></i></div>
    <div class="stat-value">{{ $kpis['shoots'] }}</div>
    <div class="stat-label">Confirmed Shoots</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="camera"></i></div>
    <div class="stat-value">{{ $usageTotalCount }}</div>
    <div class="stat-label">Distinct Items Used</div>
  </div>
</div>

<!-- Earnings trend -->
<div class="card" style="margin-bottom:22px">
  <div class="card-header">
    <h2 class="card-title">Earnings Trend
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
    <h2 class="card-title">Top Equipment · {{ $period['label'] }}</h2>
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
<div class="alert alert-warning" style="margin-bottom:16px;font-size:.83rem">
  <i data-feather="alert-circle"></i>
  {{ $missingCe->count() }} booking{{ $missingCe->count() === 1 ? '' : 's' }} in this period
  {{ $missingCe->count() === 1 ? 'has' : 'have' }} no confirmed cost estimate yet —
  {{ $missingCe->take(4)->map(fn ($b) => $b->project_title ?: $b->booking_reference)->implode(', ') }}@if ($missingCe->count() > 4) and {{ $missingCe->count() - 4 }} more @endif.
  They appear here once confirmed.
</div>
@endif

<!-- Sort controls -->
<div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px">
  <span style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;font-weight:700">Sort by</span>
  <div class="tabs" style="margin-bottom:0">
    @foreach (['pesos' => 'Pesos', 'quantity' => 'Quantity', 'days' => 'Days'] as $k => $l)
    <a href="{{ $linkWith(['sort' => $k]) }}" class="tab-btn {{ $sort === $k ? 'active' : '' }}">{{ strtoupper($l) }}</a>
    @endforeach
  </div>
  <a href="{{ $linkWith(['all' => $showAll ? null : 1]) }}" class="btn btn-outline btn-sm">
    {{ $showAll ? 'Show top ' . $defaultLimit : 'Show all items' }}
  </a>
  <span style="font-size:.75rem;color:var(--text-muted)">showing {{ $shownCount }} of {{ $usageTotalCount }}</span>
</div>

<!-- Most used, grouped by category -->
<div class="card">
  <div class="card-header">
    <h2 class="card-title">Most Used</h2>
    <span style="font-size:.72rem;color:var(--text-muted)">your own gear — what it earned, at CE prices</span>
  </div>
  <div class="card-body">
    @if ($byCategory->isEmpty())
    <div class="empty-state"><i data-feather="camera"></i><h3>No equipment used in this period</h3></div>
    @else
    @foreach ($byCategory as $cat => $group)
    <div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;color:var(--blue-700);margin:12px 0 4px;text-transform:uppercase">
      {{ $cat }}
      <span style="float:right;font-weight:400;color:var(--text-muted)">₱{{ number_format($group->earnings, 2) }}</span>
    </div>
    @foreach ($group->rows as $r)
    <div style="display:flex;justify-content:space-between;gap:10px;padding:4px 0;border-bottom:1px solid var(--border)">
      <div style="min-width:0">
        <div style="font-size:.85rem">{{ $r->equipment_name }}</div>
        @if ($r->brand)<div style="font-size:.7rem;color:var(--text-muted)">{{ $r->brand }}</div>@endif
      </div>
      <div style="text-align:right;white-space:nowrap">
        <span style="font-weight:700;color:var(--blue-700)">₱{{ number_format($r->earnings, 2) }}</span>
        <div style="font-size:.72rem;color:var(--text-muted)">×{{ (int) $r->total_qty }} · {{ (int) $r->total_days }}d</div>
      </div>
    </div>
    @endforeach
    @endforeach
    @endif

    @if ($neverUsed->count() > 0)
    <details style="margin-top:14px;border-top:1px solid var(--border);padding-top:10px">
      <summary style="font-size:12px;color:var(--muted);cursor:pointer">
        Least used — {{ $neverUsed->count() }} item{{ $neverUsed->count() === 1 ? '' : 's' }} never requested in this period
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

<!-- Past Shoots (Part 12) -->
<div style="margin-top:26px">
  <div style="display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:10px;margin-bottom:6px">
    <h2 style="font-size:1.1rem;margin:0">Past Shoots</h2>
    <div style="font-size:.8rem;color:var(--text-muted)">
      {{ $shootTotals['shoots'] }} shoot{{ $shootTotals['shoots'] === 1 ? '' : 's' }} ·
      {{ $shootTotals['matched'] }} matched
    </div>
  </div>
  <p style="font-size:.8rem;color:var(--text-muted);margin:0 0 14px;max-width:680px">
    FilmSpec equipment lines from each confirmed cost estimate, matched to the catalog automatically.
  </p>

  @if ($pastShoots->isEmpty())
  <div class="card"><div class="empty-state">
    <i data-feather="calendar"></i><h3>No confirmed shoots in this period</h3>
    <p>Shoots appear here once their cost estimate is confirmed.</p>
  </div></div>
  @else
  @foreach ($pastShoots as $s)
  <div class="card" style="margin-bottom:12px">
    <div class="card-body">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
        <div style="min-width:0">
          <div style="font-size:1rem;font-weight:700;color:var(--blue-700)">{{ $s->project_title }}</div>
          <div style="font-size:.78rem;color:var(--text-muted);margin-top:2px">
            {{ strtoupper(date('F j, Y', strtotime($s->shoot_date_start))) }}
            @if ($s->shoot_date_end !== $s->shoot_date_start)–{{ strtoupper(date('j, Y', strtotime($s->shoot_date_end))) }}@endif
            · PH: {{ strtoupper($s->client_name ?: '—') }}
            @if ($s->director) · DOP: {{ strtoupper($s->director) }} @endif
            · <span style="font-weight:700">{{ $s->ce_type_label }}</span>
          </div>
        </div>
        <span class="badge badge-green" style="flex-shrink:0">&check; Confirmed CE</span>
      </div>

      <div style="margin-top:10px;padding-top:8px;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <div style="font-size:.85rem">
          <span style="font-family:monospace;font-weight:700">CE# {{ $s->ce_reference }}</span>
          — {{ $s->project_title }} —
          <strong>{{ $s->matched_count }} matched</strong>
        </div>
        <div style="display:flex;gap:8px;flex-shrink:0">
          <a href="{{ route('ce-preview', ['booking_id' => $s->booking_id, 'ce_id' => $s->ce_id]) }}"
             style="font-size:.8rem;color:var(--accent);text-decoration:underline">View CE</a>
          <a href="{{ route('booking-detail', $s->booking_id) }}"
             style="font-size:.8rem;color:var(--accent);text-decoration:underline">Edit on CE page</a>
        </div>
      </div>

      <details style="margin-top:8px">
        <summary style="font-size:12px;color:var(--muted);cursor:pointer">View list</summary>
        <div style="margin-top:8px">
          <div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;color:var(--blue-700);text-transform:uppercase;margin-bottom:4px">
            Matched ({{ $s->matched_count }})
          </div>
          @forelse ($s->matched as $m)
          <div style="font-size:.8rem;padding:2px 0;border-bottom:1px solid var(--border)">
            <span style="font-family:monospace;color:var(--text-muted)">{{ $m->quantity }}×</span>
            {{ $m->equipment_name }}@if ($m->brand) <span style="color:var(--text-muted);font-size:.72rem">({{ $m->brand }})</span>@endif
            <span style="color:var(--text-muted);font-size:.72rem">· {{ $m->days }}d</span>
          </div>
          @empty
          <div style="font-size:.8rem;color:var(--text-muted)">No FilmSpec equipment on this shoot.</div>
          @endforelse
        </div>
      </details>
    </div>
  </div>
  @endforeach
  @endif
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
        datasets: [{ label: 'Earnings', data: @json($monthly->pluck('earnings')), backgroundColor: '#0060C7', borderRadius: 4 }]
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
    new Chart(topEl, {
      type: 'bar',
      data: {
        labels: @json($topEquipment->map(fn ($r) => $r->equipment_name)),
        datasets: [{ label: 'Earnings', data: @json($topEquipment->pluck('earnings')), backgroundColor: '#0060C7', borderRadius: 4 }]
      },
      options: {
        indexAxis: 'y',
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true, ticks: { callback: v => '₱' + Number(v).toLocaleString('en-PH') } } }
      }
    });
  }
})();
</script>
@endpush

@endsection
