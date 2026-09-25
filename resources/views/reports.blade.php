@extends('layouts.app')

@section('pageTitle', 'Reports')

@section('breadcrumb')
<span>Reports</span>
@endsection

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
@endpush

@section('content')
@php
  $reportsBase = route('reports');
  $presetActive = request('preset', (! request()->has('date_from') && ! request()->has('preset')) ? '6m' : '');
  $compareActiveQ = request('compare', '');
  $filterBase = collect(request()->query())->except(['export'])->all();

  // format defaults to 'csv' server-side (DataExporter::respond()'s default) — the client-side
  // format-pill selector below rewrites these links' ?format= to xlsx/pdf without a page reload.
  $exportLink = function (string $type) use ($filterBase, $reportsBase) {
      return $reportsBase . '?' . http_build_query(array_merge($filterBase, ['export' => $type]));
  };
@endphp

<style>
.rpt-filter {
  display:flex;align-items:center;gap:10px;flex-wrap:wrap;
  background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-md);
  padding:12px 18px;margin-bottom:22px;box-shadow:var(--shadow-sm);
}
.rpt-filter .sep { width:1px;height:24px;background:var(--border);margin:0 4px; }
.preset-btn {
  padding:5px 13px;border-radius:20px;font-size:12px;font-weight:600;border:1.5px solid var(--border);
  background:var(--s2);color:var(--sub);cursor:pointer;text-decoration:none;transition:all .15s;
  white-space:nowrap;
}
.preset-btn:hover,.preset-btn.active { background:var(--accent);border-color:var(--accent);color:#fff; }
.compare-btn { background:var(--s2);color:var(--sub);border:1.5px solid var(--border);padding:5px 13px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none;transition:all .15s;white-space:nowrap; }
.compare-btn:hover,.compare-btn.active { background:#7c3aed;border-color:#7c3aed;color:#fff; }
.export-fmt-row { display:flex;gap:4px;padding:4px 8px 8px;border-bottom:1px solid var(--border);margin-bottom:4px; }
.export-fmt-pill { flex:1;padding:4px 0;border-radius:6px;font-size:11px;font-weight:700;border:1.5px solid var(--border);background:var(--s2);color:var(--sub);cursor:pointer; }
.export-fmt-pill.active { background:var(--accent);border-color:var(--accent);color:#fff; }
.rpt-section {
  display:flex;align-items:center;gap:10px;margin:28px 0 14px;
}
.rpt-section-label {
  font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;
  color:var(--muted);white-space:nowrap;
}
.rpt-section-line { flex:1;height:1px;background:var(--border); }
.hero-chart-card {
  background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-md);
  overflow:hidden;box-shadow:var(--shadow-sm);margin-bottom:0;
}
.hero-chart-head {
  display:flex;align-items:flex-start;justify-content:space-between;
  padding:20px 24px 0;
}
.hero-chart-title { font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--muted);margin-bottom:4px; }
.hero-chart-value { font-family:var(--font-display);font-size:42px;color:var(--text);line-height:1; }
.hero-chart-sub   { font-size:12px;color:var(--muted);margin-top:3px; }
.hero-chart-body  { padding:16px 20px 20px; }
.kpi-mini { text-align:center;padding:0 16px; }
.kpi-mini-val { font-family:var(--font-display);font-size:24px;color:var(--text);line-height:1; }
.kpi-mini-lbl { font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-top:3px; }
.kpi-mini-sep { width:1px;height:40px;background:var(--border);align-self:center; }
.rpt-grid-2 { display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:0; }
.donut-wrap { padding:16px 20px 8px;display:flex;justify-content:center; }
.rank-num { display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;font-size:10px;font-weight:700;background:var(--s3);color:var(--muted); }
.rank-num.top { background:var(--accent);color:#fff; }
.insight-row { display:flex;gap:0;border-top:1px solid var(--border);padding:0; }
.insight-box { flex:1;padding:16px;text-align:center;border-right:1px solid var(--border); }
.insight-box:last-child { border-right:none; }
.insight-val { font-family:var(--font-display);font-size:28px;color:var(--text);line-height:1; }
.insight-lbl { font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.8px;margin-top:3px; }
.fb-item { padding:14px 18px;border-bottom:1px solid var(--border);display:flex;gap:12px;align-items:flex-start; }
.fb-item:last-child { border-bottom:none; }
.fb-stars { color:#f59e0b;font-size:13px;white-space:nowrap; }
.compare-range { font-size:11px;color:#7c3aed;background:rgba(124,58,237,.07);border:1px solid rgba(124,58,237,.2);border-radius:5px;padding:3px 9px;white-space:nowrap; }
</style>

<!-- FILTER BAR -->
<form method="GET" action="{{ $reportsBase }}" class="rpt-filter" id="rptFilterForm">
  <i data-feather="calendar" style="width:15px;height:15px;color:var(--muted);flex-shrink:0"></i>
  <input type="date" name="date_from" class="form-control" style="width:140px;font-size:12.5px"
         value="{{ $dateFrom }}" max="{{ $dateTo }}">
  <span style="font-size:12px;color:var(--muted)">→</span>
  <input type="date" name="date_to" class="form-control" style="width:140px;font-size:12.5px"
         value="{{ $dateTo }}" max="{{ now()->toDateString() }}">
  <button type="submit" class="btn btn-primary btn-sm" style="font-size:12px">Apply</button>

  <div class="sep"></div>
  @foreach (['30d' => '30D', '3m' => '3M', '6m' => '6M', 'year' => 'YTD'] as $k => $l)
  <a href="{{ $reportsBase }}?preset={{ $k }}{{ $compareActiveQ ? '&compare=' . urlencode($compareActiveQ) : '' }}"
     class="preset-btn {{ $presetActive === $k ? 'active' : '' }}">{{ $l }}</a>
  @endforeach

  <div class="sep"></div>
  <a href="{{ $reportsBase }}?{{ http_build_query(array_merge($filterBase, ['compare' => 'prev'])) }}"
     class="compare-btn {{ $compareActiveQ === 'prev' ? 'active' : '' }}" title="Compare to previous period">
    <i data-feather="bar-chart-2" style="width:12px;height:12px;display:inline;vertical-align:middle;margin-right:3px"></i>vs Prev
  </a>
  <a href="{{ $reportsBase }}?{{ http_build_query(array_merge($filterBase, ['compare' => 'year'])) }}"
     class="compare-btn {{ $compareActiveQ === 'year' ? 'active' : '' }}" title="Compare to same period last year">
    <i data-feather="calendar" style="width:12px;height:12px;display:inline;vertical-align:middle;margin-right:3px"></i>vs Last Year
  </a>
  @if ($compareActiveQ)
  <a href="{{ $reportsBase }}?{{ http_build_query(collect($filterBase)->except(['compare'])->all()) }}"
     class="preset-btn" style="color:var(--red);border-color:var(--red)" title="Remove comparison">✕ Compare</a>
  @endif

  <span style="margin-left:auto;font-size:11px;color:var(--muted)">
    {{ \Illuminate\Support\Carbon::parse($dateFrom)->format('M j, Y') }} &mdash; {{ \Illuminate\Support\Carbon::parse($dateTo)->format('M j, Y') }}
  </span>

  <div class="export-btn" id="exportBtnWrap">
    <button type="button" class="btn btn-outline btn-sm export-toggle" data-target="reportsExportMenu" style="font-size:12px">
      <i data-feather="download" style="width:13px;height:13px"></i> Export
    </button>
    <div class="export-menu" id="reportsExportMenu">
      <div class="export-fmt-row">
        <button type="button" class="export-fmt-pill active" data-fmt="csv" onclick="rptSetExportFormat('csv')">CSV</button>
        <button type="button" class="export-fmt-pill" data-fmt="xlsx" onclick="rptSetExportFormat('xlsx')">Excel</button>
        <button type="button" class="export-fmt-pill" data-fmt="pdf" onclick="rptSetExportFormat('pdf')">PDF</button>
      </div>
      @foreach ([
          'sales' => 'Daily Payments Trend', 'collection' => 'Payment Collection',
          'bookings' => 'Bookings by Type', 'equipment' => 'Top Equipment',
          'availability' => 'Equipment Availability', 'crew' => 'Crew Performance',
          'clients' => 'Top Clients', 'incidents' => 'Incidents Report',
      ] as $type => $label)
      <a href="{{ $exportLink($type) }}" class="export-opt" data-type="{{ $type }}">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>{{ $label }}
      </a>
      @endforeach
    </div>
  </div>
</form>

@if ($compareActiveQ && $compFrom)
<div style="margin-bottom:14px;display:flex;align-items:center;gap:8px;font-size:12px;color:var(--muted)">
  <i data-feather="git-compare" style="width:13px;height:13px;color:#7c3aed"></i>
  Comparing <strong>{{ \Illuminate\Support\Carbon::parse($dateFrom)->format('M j, Y') }} – {{ \Illuminate\Support\Carbon::parse($dateTo)->format('M j, Y') }}</strong>
  vs
  <span class="compare-range">{{ \Illuminate\Support\Carbon::parse($compFrom)->format('M j, Y') }} – {{ \Illuminate\Support\Carbon::parse($compTo)->format('M j, Y') }}</span>
</div>
@endif

@php
  // ↑12% vs previous period, same treatment on every applicable card — $t is one
  // $kpiTrends[...] entry (or null when there's nothing to compare against).
  $trendBadge = function (?array $t) {
      if (! $t) return '';
      if (! empty($t['isNew'])) {
          return '<div style="font-size:10.5px;font-weight:700;margin-top:4px;color:var(--green)">'
              . 'New <span style="font-weight:400;color:var(--muted)">vs previous period</span></div>';
      }
      $color = $t['dir'] === 'up' ? 'var(--green)' : 'var(--red)';
      $arrow = $t['dir'] === 'up' ? '&uarr;' : '&darr;';

      return '<div style="font-size:10.5px;font-weight:700;margin-top:4px;color:' . $color . '">'
          . $arrow . ' ' . $t['pct'] . '% <span style="font-weight:400;color:var(--muted)">vs previous period</span></div>';
  };
@endphp
<!-- KPI STRIP -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);margin-bottom:22px">
  <div class="stat-card green">
    <div class="stat-icon" style="background:var(--greenl);color:var(--green)"><i data-feather="trending-up"></i></div>
    <div class="stat-value">₱{{ number_format($kpis['period_sales'] / 1000, 1) }}k</div>
    <div class="stat-label">Period Sales</div>
    <div style="font-size:10px;color:var(--muted);margin-top:2px">Booking value created in period, excl. cancelled</div>
    {!! $trendBadge($kpiTrends['period_sales']) !!}
  </div>
  <div class="stat-card" style="--sb:var(--blue-600, #2563eb)">
    <div class="stat-icon" style="background:var(--bluel,#eff6ff);color:var(--blue-600,#2563eb)"><i data-feather="credit-card"></i></div>
    <div class="stat-value">₱{{ number_format($kpis['payments_collected'] / 1000, 1) }}k</div>
    <div class="stat-label">Payments Collected</div>
    <div style="font-size:10px;color:var(--muted);margin-top:2px">Cash received in period, any booking</div>
    {!! $trendBadge($kpiTrends['payments_collected']) !!}
  </div>
  <div class="stat-card orange">
    <div class="stat-icon" style="background:var(--orangel);color:var(--orange)"><i data-feather="alert-circle"></i></div>
    <div class="stat-value">₱{{ number_format($kpis['outstanding_bal'] / 1000, 1) }}k</div>
    <div class="stat-label">Outstanding</div>
    <div style="font-size:10px;color:var(--muted);margin-top:2px">Unpaid balance as of today, all time</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="calendar"></i></div>
    <div class="stat-value">{{ $kpis['total_bookings'] }}</div>
    <div class="stat-label">Total Bookings</div>
    <div style="font-size:10px;color:var(--muted);margin-top:2px">Created in period, any status</div>
    {!! $trendBadge($kpiTrends['total_bookings']) !!}
  </div>
  <div class="stat-card" style="--sb:var(--green)">
    <div class="stat-icon" style="background:var(--greenl);color:var(--green)"><i data-feather="check-circle"></i></div>
    <div class="stat-value">{{ $kpis['completed'] }}</div>
    <div class="stat-label">Completed Bookings</div>
    <div style="font-size:10px;color:var(--muted);margin-top:2px">Created in period, status = completed</div>
    {!! $trendBadge($kpiTrends['completed']) !!}
  </div>
</div>

<!-- SECTION: SALES -->
<div class="rpt-section">
  <span class="rpt-section-label"><i data-feather="trending-up" style="width:11px;height:11px;vertical-align:middle;margin-right:4px"></i>Sales Overview</span>
  <div class="rpt-section-line"></div>
</div>

@php
  $ftSalesTotal = $salesMonthly->sum('sales_total');
  $ftCollTotal = $salesMonthly->sum('collected_total');
  $ftBookingsTotal = $salesMonthly->sum('bookings_count');
  $ftTxTotal = $salesMonthly->sum('transactions');
@endphp
<div class="hero-chart-card" style="margin-bottom:16px">
  <div class="hero-chart-head">
    <div>
      <div class="hero-chart-title">Financial Trend — <span id="ftModeLabel">Sales</span></div>
      <div class="hero-chart-value" id="ftValue">₱{{ number_format($ftSalesTotal, 2) }}</div>
      <div class="hero-chart-sub" id="ftSub">Booking value created {{ \Illuminate\Support\Carbon::parse($dateFrom)->format('M j, Y') }} — {{ \Illuminate\Support\Carbon::parse($dateTo)->format('M j, Y') }} (contracted, not necessarily collected)</div>
    </div>
    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:10px">
      <div style="display:flex;border:1.5px solid var(--border);border-radius:20px;overflow:hidden">
        <button type="button" class="preset-btn active" id="ftBtnSales" onclick="setFinTrendMode('sales')" style="border-radius:0;border:none">Sales</button>
        <button type="button" class="preset-btn" id="ftBtnCollections" onclick="setFinTrendMode('collections')" style="border-radius:0;border:none;border-left:1.5px solid var(--border)">Collections</button>
      </div>
      <div style="display:flex;gap:0;align-items:center">
        <div class="kpi-mini"><div class="kpi-mini-val" id="ftCountVal">{{ $ftBookingsTotal }}</div><div class="kpi-mini-lbl" id="ftCountLbl">Bookings</div></div>
        <div class="kpi-mini-sep"></div>
        <div class="kpi-mini"><div class="kpi-mini-val">{{ $salesMonthly->count() }}</div><div class="kpi-mini-lbl">Months</div></div>
      </div>
    </div>
  </div>
  <div class="hero-chart-body">
    @if ($salesMonthly->isEmpty())
    <div class="empty-state" style="padding:32px 0"><i data-feather="bar-chart-2"></i><h3>No financial data for this period</h3></div>
    @else
    <canvas id="finTrendChart" height="80"></canvas>
    <div class="table-wrap" style="border-top:1px solid var(--border)">
      <table>
        <thead><tr><th>Month</th><th style="text-align:right" id="ftColHead">Bookings</th><th style="text-align:right" id="ftAmtHead">Sales</th></tr></thead>
        <tbody>
          @foreach ($salesMonthly as $r)
          <tr>
            <td style="font-weight:600">{{ $r->label }}</td>
            <td style="text-align:right" class="ft-count-cell" data-sales="{{ $r->bookings_count }}" data-coll="{{ $r->transactions }}">{{ $r->bookings_count }}</td>
            <td style="text-align:right;font-family:var(--font-m)" class="ft-amt-cell" data-sales="{{ $r->sales_total }}" data-coll="{{ $r->collected_total }}">₱{{ number_format($r->sales_total, 2) }}</td>
          </tr>
          @endforeach
        </tbody>
        <tfoot>
          <tr style="background:var(--s2)">
            <td style="font-weight:700">Total</td>
            <td style="text-align:right;font-weight:700" id="ftCountFoot">{{ $ftBookingsTotal }}</td>
            <td style="text-align:right;font-weight:700;font-family:var(--font-m)" id="ftAmtFoot">₱{{ number_format($ftSalesTotal, 2) }}</td>
          </tr>
        </tfoot>
      </table>
    </div>
    @endif
  </div>
</div>

<!-- SECTION: BOOKINGS -->
<div class="rpt-section" style="margin-top:32px">
  <span class="rpt-section-label"><i data-feather="layers" style="width:11px;height:11px;vertical-align:middle;margin-right:4px"></i>Bookings &amp; Equipment</span>
  <div class="rpt-section-line"></div>
</div>

<div class="rpt-grid-2" style="margin-bottom:16px">

  <div class="card" style="margin-bottom:0">
    <div class="card-header">
      <h2 class="card-title"><i data-feather="pie-chart" style="width:14px;height:14px"></i> Bookings by Project Type</h2>
      <span class="badge badge-blue">{{ $bookingsByType->sum('total') }} bookings</span>
    </div>
    <div style="padding:6px 20px 0;font-size:10.5px;color:var(--muted)">
      Created {{ \Illuminate\Support\Carbon::parse($dateFrom)->format('M j') }} – {{ \Illuminate\Support\Carbon::parse($dateTo)->format('M j, Y') }}, excludes cancelled
      ({{ $kpis['total_bookings'] }} total bookings created in period, including cancelled)
    </div>
    @if ($bookingsByType->isEmpty())
    <div class="empty-state" style="padding:32px 0"><i data-feather="layers"></i><h3>No bookings in this period</h3></div>
    @else
    {{-- Horizontal bar instead of a donut — with this few categories a donut is usually one
         giant slice and a sliver, which reads worse than a simple ranked bar list. --}}
    <div style="padding:16px 20px 4px">
      <canvas id="typeChart" height="{{ max(90, $bookingsByType->count() * 42) }}"></canvas>
    </div>
    <div class="table-wrap" style="border-top:1px solid var(--border)">
      <table>
        <thead><tr><th>Type</th><th>Count</th><th style="text-align:right">Share</th></tr></thead>
        <tbody>
          @foreach ($bookingsByType as $bt)
          @php $pct = round($bt->total / $totalBookingsByType * 100, 1); @endphp
          <tr>
            <td style="font-weight:500">{{ ucfirst(str_replace('_', ' ', $bt->project_type)) }}</td>
            <td><span class="badge badge-blue">{{ $bt->total }}</span></td>
            <td style="text-align:right">
              <div style="display:flex;align-items:center;gap:6px;justify-content:flex-end">
                <div style="background:var(--border);border-radius:3px;height:5px;width:50px;overflow:hidden">
                  <div style="background:var(--accent);width:{{ $pct }}%;height:100%"></div>
                </div>
                <span style="font-size:11px;font-weight:700;min-width:32px;text-align:right">{{ $pct }}%</span>
              </div>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>

  <div class="card" style="margin-bottom:0">
    <div class="card-header">
      <h2 class="card-title"><i data-feather="camera" style="width:14px;height:14px"></i> Top Rented Equipment</h2>
      <span class="badge badge-gray">by rentals</span>
    </div>
    @if ($topEquipment->isEmpty())
    <div class="empty-state" style="padding:32px 0"><i data-feather="camera"></i><h3>No rental data</h3></div>
    @else
    <div class="donut-wrap"><canvas id="topEquipPieChart" style="max-width:220px;max-height:220px"></canvas></div>
    <div class="table-wrap" style="border-top:1px solid var(--border)">
      <table>
        <thead><tr><th>#</th><th>Equipment</th><th style="text-align:right">Rentals</th></tr></thead>
        <tbody>
          @foreach ($topEquipment->take(8) as $i => $te)
          <tr>
            <td style="width:28px"><span class="rank-num {{ $i < 3 ? 'top' : '' }}">{{ $i + 1 }}</span></td>
            <td>
              <div style="font-weight:600;font-size:12.5px">{{ $te->equipment_name }}</div>
              @if ($te->brand)<div style="font-size:11px;color:var(--muted)">{{ $te->brand }}</div>@endif
            </td>
            <td style="text-align:right"><span class="badge badge-blue">{{ $te->rental_count }}×</span></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>

</div>

<!-- SECTION: EQUIPMENT & PEOPLE -->
<div class="rpt-section" style="margin-top:32px">
  <span class="rpt-section-label"><i data-feather="users" style="width:11px;height:11px;vertical-align:middle;margin-right:4px"></i>Equipment &amp; People</span>
  <div class="rpt-section-line"></div>
</div>

<div class="rpt-grid-2" style="margin-bottom:16px">

  <div class="card" style="margin-bottom:0">
    <div class="card-header">
      <h2 class="card-title"><i data-feather="box" style="width:14px;height:14px"></i> Equipment Availability</h2>
      <span class="badge badge-gray">{{ array_sum($equipAvail) }} units</span>
    </div>
    <div style="padding:20px 20px 4px">
      <canvas id="availChart" height="140"></canvas>
    </div>
    <div style="border-top:1px solid var(--border)">
      @php
        $availRows = [
            ['label' => 'Available', 'count' => $equipAvail['available']],
            ['label' => 'Booked', 'count' => $equipAvail['booked']],
            ['label' => 'In Use', 'count' => $equipAvail['rented']],
            ['label' => 'Under Repair', 'count' => $equipAvail['under_repair']],
            ['label' => 'Retired', 'count' => $equipAvail['retired']],
        ];
        foreach ($availRows as &$av) { $av['pct'] = $availTotal > 0 ? round($av['count'] / $availTotal * 100, 1) : 0; }
        unset($av);
      @endphp
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0">
        @foreach ($availRows as $i => $av)
        <div style="padding:10px 16px;{{ $i % 2 === 0 ? 'border-right:1px solid var(--border)' : '' }};border-bottom:1px solid var(--border)">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
            <span style="font-size:11px;color:var(--sub);font-weight:600">{{ $av['label'] }}</span>
            <span style="font-size:11px;font-weight:700;color:var(--text)">{{ $av['count'] }} <span style="font-weight:400;color:var(--muted)">({{ $av['pct'] }}%)</span></span>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  <div class="card" style="margin-bottom:0">
    <div class="card-header">
      <h2 class="card-title"><i data-feather="users" style="width:14px;height:14px"></i> Crew Performance</h2>
      <span class="badge badge-gray">by shoots</span>
    </div>
    @if ($crewPerf->isEmpty())
    <div class="empty-state"><i data-feather="users"></i><h3>No crew data</h3></div>
    @else
    @php $maxShoots = $crewPerf->max('total_shoots') ?: 1; @endphp
    <div class="table-wrap card-scroll">
      <table>
        <thead><tr><th>#</th><th>Crew Member</th><th>Shoots</th><th>No-Show</th></tr></thead>
        <tbody>
          @foreach ($crewPerf as $i => $cp)
          <tr>
            <td style="width:32px"><span class="rank-num {{ $i < 3 ? 'top' : '' }}">{{ $i + 1 }}</span></td>
            <td>
              <div style="font-weight:600;font-size:12.5px">{{ $cp->first_name . ' ' . $cp->last_name }}</div>
              <div style="font-size:11px;color:var(--muted)">{{ $cp->position_name ?? '—' }}</div>
            </td>
            <td>
              <div style="display:flex;align-items:center;gap:7px">
                <div style="background:var(--border);border-radius:3px;height:5px;width:40px;overflow:hidden">
                  <div style="background:var(--green);width:{{ round($cp->total_shoots / $maxShoots * 100) }}%;height:100%"></div>
                </div>
                <span class="badge badge-green">{{ $cp->total_shoots }}</span>
              </div>
            </td>
            <td>
              @if ($cp->no_shows > 0)
              <span class="badge badge-red">{{ $cp->no_shows }}</span>
              @else
              <span style="color:var(--muted);font-size:11px">—</span>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>

</div>

<!-- SECTION: CLIENTS -->
<div class="rpt-section" style="margin-top:32px">
  <span class="rpt-section-label"><i data-feather="briefcase" style="width:11px;height:11px;vertical-align:middle;margin-right:4px"></i>Clients</span>
  <div class="rpt-section-line"></div>
</div>

<div class="card" style="margin-bottom:16px">
  <div class="card-header">
    <h2 class="card-title"><i data-feather="briefcase" style="width:14px;height:14px"></i> Top Clients</h2>
    <span class="badge badge-gray">by period spend</span>
  </div>
  @if ($topClients->isEmpty())
  <div class="empty-state"><i data-feather="briefcase"></i><h3>No client data yet</h3></div>
  @else
  @php $maxSpend = $topClients->max('total_spend') ?: 1; @endphp
  <div class="table-wrap card-scroll">
    <table>
      <thead><tr><th>#</th><th>Client</th><th>Type</th><th>Completed</th><th>All Bookings</th><th>Spend</th><th style="text-align:right">Share</th></tr></thead>
      <tbody>
        @foreach ($topClients as $i => $tc)
        <tr>
          <td style="width:32px"><span class="rank-num {{ $i < 3 ? 'top' : '' }}">{{ $i + 1 }}</span></td>
          <td style="font-weight:600">{{ $tc->company_name ?: $tc->contact_person }}</td>
          <td><span class="badge {{ $tc->client_type === 'regular' ? 'badge-green' : 'badge-blue' }}">{{ $tc->client_type === 'regular' ? 'Regular' : 'New' }}</span></td>
          <td><span class="badge badge-green">{{ $tc->completed_count }}</span></td>
          <td style="color:var(--muted)">{{ $tc->total_bookings }}</td>
          <td style="font-weight:700;color:var(--accent)">₱{{ number_format($tc->total_spend, 2) }}</td>
          <td style="text-align:right;min-width:100px">
            @php $spendPct = $maxSpend > 0 ? round($tc->total_spend / $maxSpend * 100) : 0; @endphp
            <div style="display:flex;align-items:center;gap:6px;justify-content:flex-end">
              <div style="background:var(--border);border-radius:3px;height:5px;width:60px;overflow:hidden">
                <div style="background:var(--accent);width:{{ $spendPct }}%;height:100%"></div>
              </div>
            </div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif
</div>

<!-- SECTION: INCIDENTS & FEEDBACK -->
<div class="rpt-section" style="margin-top:32px">
  <span class="rpt-section-label"><i data-feather="alert-triangle" style="width:11px;height:11px;vertical-align:middle;margin-right:4px"></i>Incidents &amp; Feedback</span>
  <div class="rpt-section-line"></div>
</div>

<div class="rpt-grid-2">

  <div class="card" style="margin-bottom:0">
    <div class="card-header">
      <h2 class="card-title"><i data-feather="alert-triangle" style="width:14px;height:14px"></i> Damaged &amp; Missing</h2>
      <span class="badge {{ $damagedReport->count() > 0 ? 'badge-red' : 'badge-gray' }}">{{ $damagedReport->count() }}</span>
    </div>
    @if ($damagedReport->isEmpty())
    <div class="empty-state" style="padding:32px 0"><i data-feather="shield"></i><h3>No incidents in this period</h3></div>
    @else
    <div class="table-wrap card-scroll">
      <table>
        <thead><tr><th>Date</th><th>Equipment</th><th>Type</th><th>Charge</th><th>Status</th></tr></thead>
        <tbody>
          @foreach ($damagedReport as $dr)
          <tr>
            <td style="font-size:11px;white-space:nowrap;color:var(--muted)">{{ \Illuminate\Support\Carbon::parse($dr->incident_date)->format('M j, Y') }}</td>
            <td>
              <div style="font-weight:600;font-size:12.5px">{{ $dr->equipment_name }}</div>
              <div style="font-size:11px;color:var(--muted);font-family:monospace">{{ $dr->booking_reference }}</div>
            </td>
            <td><span class="badge {{ $dr->incident_type === 'damaged' ? 'badge-red' : 'badge-orange' }}">{{ ucfirst($dr->incident_type) }}</span></td>
            <td style="font-weight:700;color:var(--red)">{{ $dr->charge_amount > 0 ? '₱' . number_format($dr->charge_amount, 2) : '—' }}</td>
            <td><span class="badge {{ $dr->status === 'resolved' ? 'badge-green' : ($dr->status === 'open' ? 'badge-yellow' : 'badge-gray') }}">{{ ucfirst($dr->status) }}</span></td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>

  <div class="card" style="margin-bottom:0">
    <div class="card-header">
      <h2 class="card-title"><i data-feather="message-square" style="width:14px;height:14px"></i> Client Feedback</h2>
      @php $fbTotal = (int) ($feedbackStats->total ?? 0); $fbAvg = (float) ($feedbackStats->avg_rating ?? 0); @endphp
      @if ($fbTotal > 0)
      <div style="display:flex;align-items:center;gap:6px">
        <span style="color:#f59e0b;letter-spacing:-1px">{{ str_repeat('★', (int) round($fbAvg)) }}</span>
        <span style="font-family:var(--font-display);font-size:18px;color:var(--text)">{{ number_format($fbAvg, 1) }}</span>
        <span style="font-size:11px;color:var(--muted)">({{ $fbTotal }})</span>
      </div>
      @endif
    </div>
    @if ($fbTotal > 0)
    <div style="display:flex;border-bottom:1px solid var(--border)">
      @php $starRows = [5 => '★★★★★', 4 => '★★★★', 3 => '★★★', 2 => '★★', 1 => '★']; @endphp
      @foreach ($starRows as $star => $lbl)
      @php
        $cnt = $star === 5 ? ($feedbackStats->five_star ?? 0) : ($star === 4 ? ($feedbackStats->four_star ?? 0) : ($star === 3 ? ($feedbackStats->three_star ?? 0) : ($feedbackStats->low_star ?? 0)));
      @endphp
      <div style="flex:1;padding:10px 12px;text-align:center;border-right:1px solid var(--border);font-size:10px">
        <div style="color:#f59e0b;font-size:9px;letter-spacing:-1px">{{ $lbl }}</div>
        <div style="font-family:var(--font-display);font-size:18px;color:var(--text)">{{ $cnt }}</div>
      </div>
      @endforeach
    </div>
    @endif
    @if ($recentFeedback->isEmpty())
    <div class="empty-state" style="padding:32px 0"><i data-feather="message-square"></i><h3>No feedback yet</h3></div>
    @else
    <div class="card-scroll">
      @foreach ($recentFeedback as $fb)
      <div class="fb-item">
        <div style="flex:1;min-width:0">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:4px">
            <span style="font-weight:600;font-size:12.5px">{{ $fb->company_name ?: $fb->contact_person }}</span>
            <span class="fb-stars">{{ str_repeat('★', (int) $fb->rating) . str_repeat('☆', 5 - (int) $fb->rating) }}</span>
          </div>
          @if ($fb->comment)
          <p style="font-size:12px;color:var(--sub);margin:0;font-style:italic;line-height:1.5">"{{ $fb->comment }}"</p>
          @endif
          <div style="font-size:10px;color:var(--muted);margin-top:5px">{{ \Illuminate\Support\Carbon::parse($fb->submitted_at)->format('M j, Y') }} · {{ $fb->booking_reference }}</div>
        </div>
      </div>
      @endforeach
    </div>
    @endif
  </div>

</div>

<div style="height:24px"></div>

@php
  $jsTypePcts = $bookingsByType->map(fn ($r) => round($r->total / $totalBookingsByType * 100, 1))->values();
  $jsTypeLabels = $bookingsByType->map(fn ($r) => ucfirst(str_replace('_', ' ', $r->project_type)));
  $jsTypeValues = $bookingsByType->pluck('total')->map(fn ($v) => (int) $v);
  $jsTopEquipLabels = $topEquipment->take(8)->pluck('equipment_name');
  $jsTopEquipValues = $topEquipment->take(8)->pluck('rental_count')->map(fn ($v) => (int) $v);
  $availAvailable = $equipAvail['available'];
  $availBooked = $equipAvail['booked'];
  $availRented = $equipAvail['rented'];
  $availRepair = $equipAvail['under_repair'];
  $availRetired = $equipAvail['retired'];
  $jsAvailLabels = ['Available', 'Booked', 'In Use', 'Under Repair', 'Retired'];
  $jsAvailValues = [$availAvailable, $availBooked, $availRented, $availRepair, $availRetired];

  $jsFtLabels = $salesMonthly->pluck('label');
  $jsFtSalesValues = $salesMonthly->pluck('sales_total')->map(fn ($v) => (float) $v);
  $jsFtCollValues = $salesMonthly->pluck('collected_total')->map(fn ($v) => (float) $v);
  $showCompareLine = $compareActive && $compareMonthly->isNotEmpty();
  $jsFtCompareSalesValues = $compareMonthly->pluck('sales_total')->map(fn ($v) => (float) $v);
  $jsFtCompareCollValues = $compareMonthly->pluck('collected_total')->map(fn ($v) => (float) $v);
@endphp

@push('scripts')
<script>
const BLUE_SHADES = [
  '#003d99','#0052cc','#0060C7','#1a73e8',
  '#4299e1','#60a5fa','#7ec8e3','#93c5fd',
  '#bfdbfe','#dbeafe'
];

const _typePcts = @json($jsTypePcts);

@if ($salesMonthly->isNotEmpty())
const ftLabels = @json($jsFtLabels);
const ftSalesValues = @json($jsFtSalesValues);
const ftCollValues = @json($jsFtCollValues);
const ftCompareSalesValues = @json($jsFtCompareSalesValues);
const ftCompareCollValues = @json($jsFtCompareCollValues);
const ftShowCompare = {{ $showCompareLine ? 'true' : 'false' }};

const ftDatasets = [
  {
    label: 'Sales', data: ftSalesValues, hidden: false,
    borderColor: '#0060C7', backgroundColor: 'rgba(0,96,199,0.07)',
    fill: true, tension: 0.45, pointRadius: 5, pointHoverRadius: 8,
    pointBackgroundColor: '#fff', pointBorderColor: '#0060C7', pointBorderWidth: 2.5, borderWidth: 2.5,
  },
  {
    label: 'Collections', data: ftCollValues, hidden: true,
    borderColor: '#16a34a', backgroundColor: 'rgba(22,163,74,0.07)',
    fill: true, tension: 0.45, pointRadius: 5, pointHoverRadius: 8,
    pointBackgroundColor: '#fff', pointBorderColor: '#16a34a', pointBorderWidth: 2.5, borderWidth: 2.5,
  },
];
@if ($showCompareLine)
ftDatasets.push({
  label: 'Comparison — Sales', data: ftCompareSalesValues, hidden: false, isCompare: true, compareFor: 'sales',
  borderColor: '#7c3aed', backgroundColor: 'rgba(124,58,237,0.05)',
  fill: true, tension: 0.45, pointRadius: 4, pointHoverRadius: 7,
  pointBackgroundColor: '#fff', pointBorderColor: '#7c3aed', pointBorderWidth: 2, borderWidth: 2, borderDash: [5, 3],
});
ftDatasets.push({
  label: 'Comparison — Collections', data: ftCompareCollValues, hidden: true, isCompare: true, compareFor: 'collections',
  borderColor: '#7c3aed', backgroundColor: 'rgba(124,58,237,0.05)',
  fill: true, tension: 0.45, pointRadius: 4, pointHoverRadius: 7,
  pointBackgroundColor: '#fff', pointBorderColor: '#7c3aed', pointBorderWidth: 2, borderWidth: 2, borderDash: [5, 3],
});
@endif

const finTrendChart = new Chart(document.getElementById('finTrendChart'), {
  type: 'line',
  data: { labels: ftLabels, datasets: ftDatasets },
  options: {
    responsive: true,
    plugins: {
      legend: { display: ftShowCompare, labels: { font: { size: 11 }, boxWidth: 10 } },
      tooltip: { backgroundColor: '#1e293b', padding: 12, cornerRadius: 8,
        callbacks: { label: v => '  ' + v.dataset.label + ': ₱' + v.raw.toLocaleString('en-PH', { minimumFractionDigits: 2 }) } }
    },
    scales: {
      x: { grid: { display: false }, ticks: { font: { size: 11 } } },
      y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.05)' },
        ticks: { callback: v => '₱' + Number(v).toLocaleString('en-PH', { maximumFractionDigits: 0 }), font: { size: 11 } } }
    }
  }
});

function setFinTrendMode(mode) {
  const isSales = mode === 'sales';
  document.getElementById('ftBtnSales').classList.toggle('active', isSales);
  document.getElementById('ftBtnCollections').classList.toggle('active', !isSales);
  document.getElementById('ftModeLabel').textContent = isSales ? 'Sales' : 'Collections';
  document.getElementById('ftCountLbl').textContent = isSales ? 'Bookings' : 'Payments';
  document.getElementById('ftColHead').textContent = isSales ? 'Bookings' : 'Payments';
  document.getElementById('ftAmtHead').textContent = isSales ? 'Sales' : 'Collected';

  const sumKey = isSales ? 'sales' : 'coll';
  let amtTotal = 0, countTotal = 0;
  document.querySelectorAll('.ft-count-cell').forEach(td => {
    const v = parseInt(td.dataset[sumKey], 10) || 0;
    td.textContent = v;
    countTotal += v;
  });
  document.querySelectorAll('.ft-amt-cell').forEach(td => {
    const v = parseFloat(td.dataset[sumKey]) || 0;
    td.textContent = '₱' + v.toLocaleString('en-PH', { minimumFractionDigits: 2 });
    amtTotal += v;
  });
  document.getElementById('ftCountVal').textContent = countTotal;
  document.getElementById('ftCountFoot').textContent = countTotal;
  document.getElementById('ftAmtFoot').textContent = '₱' + amtTotal.toLocaleString('en-PH', { minimumFractionDigits: 2 });
  document.getElementById('ftValue').textContent = '₱' + amtTotal.toLocaleString('en-PH', { minimumFractionDigits: 2 });
  document.getElementById('ftSub').textContent = isSales
    ? 'Booking value created {{ $dateFrom }} — {{ $dateTo }} (contracted, not necessarily collected)'
    : 'Cash received {{ $dateFrom }} — {{ $dateTo }} (not booking value)';

  finTrendChart.data.datasets.forEach(ds => {
    if (ds.isCompare) { ds.hidden = ds.compareFor !== mode; return; }
    ds.hidden = (ds.label === 'Sales') !== isSales;
  });
  finTrendChart.update();
}
@endif

@if ($bookingsByType->isNotEmpty())
new Chart(document.getElementById('typeChart'), {
  type: 'bar',
  data: {
    labels: @json($jsTypeLabels),
    datasets: [{
      data: @json($jsTypeValues),
      backgroundColor: BLUE_SHADES.slice(0, {{ $bookingsByType->count() }}),
      borderRadius: 4, borderSkipped: false,
    }]
  },
  options: {
    indexAxis: 'y',
    plugins: {
      legend: { display: false },
      tooltip: { callbacks: { label: ctx => ' ' + ctx.raw + ' booking' + (ctx.raw === 1 ? '' : 's') + ' (' + (_typePcts[ctx.dataIndex] ?? 0) + '%)' } }
    },
    scales: {
      x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.05)' }, ticks: { font: { size: 11 }, precision: 0 } },
      y: { grid: { display: false }, ticks: { font: { size: 11 } } }
    }
  }
});
@endif

@if ($topEquipment->isNotEmpty())
new Chart(document.getElementById('topEquipPieChart'), {
  type:'pie',
  data:{
    labels:@json($jsTopEquipLabels),
    datasets:[{
      data:@json($jsTopEquipValues),
      backgroundColor: BLUE_SHADES.slice(0, {{ $topEquipment->take(8)->count() }}),
      borderWidth:3,borderColor:'var(--surface)',hoverOffset:6
    }]
  },
  options:{
    plugins:{
      legend:{position:'bottom',labels:{font:{size:11},boxWidth:10,padding:8,color:'#64748b'}},
      tooltip:{callbacks:{label:ctx=>' '+ctx.label+': '+ctx.raw+' rental'+(ctx.raw===1?'':'s')}}
    }
  }
});
@endif

new Chart(document.getElementById('availChart'), {
  type:'bar',
  data:{
    labels:@json($jsAvailLabels),
    datasets:[{
      data:@json($jsAvailValues),
      backgroundColor: BLUE_SHADES.slice(0,5),
      borderRadius:4,borderSkipped:false,
    }]
  },
  options:{
    indexAxis:'y',
    plugins:{
      legend:{display:false},
      tooltip:{callbacks:{label:ctx=>{const t={{ $availTotal }};return ' '+ctx.raw+' units ('+Math.round(ctx.raw/t*100)+'%)';}}}
    },
    scales:{
      x:{beginAtZero:true,grid:{color:'rgba(0,0,0,.05)'},ticks:{font:{size:11},callback:v=>v+' u'}},
      y:{grid:{display:false},ticks:{font:{size:11}}}
    }
  }
});

// Export ▾ open/close is handled by the shared .export-toggle delegated listener in
// public/assets/js/app.js — this page only needs its own format-pill behavior, since it's
// the one page offering 8 different datasets from a single dropdown instead of one dataset.
function rptSetExportFormat(fmt) {
  document.querySelectorAll('.export-fmt-pill').forEach(b => b.classList.toggle('active', b.dataset.fmt === fmt));
  document.querySelectorAll('#reportsExportMenu .export-opt[data-type]').forEach(a => {
    const url = new URL(a.href, window.location.origin);
    url.searchParams.set('format', fmt);
    a.href = url.toString();
  });
}
</script>
@endpush
@endsection
