@extends('layouts.app')

@section('pageTitle', 'Profit & Loss')

@section('breadcrumb')
<span>Profit &amp; Loss</span>
@endsection

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
@endpush

@section('content')
@php
  $peso = fn ($n) => '₱' . number_format((float) $n, 2);
  $signColor = fn ($n) => $n < 0 ? 'var(--red)' : 'var(--blue-700)';
@endphp

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:14px">
  <div>
    <h1 style="font-size:1.4rem;margin:0 0 4px">Profit &amp; loss</h1>
    <p style="font-size:.82rem;color:var(--text-muted);max-width:660px;margin:0">
      Sales is <strong>cash received</strong> — payments banked in this period. Costs are
      attributed to the <strong>shoot</strong> they belong to. A job shot in one month and paid
      the next therefore shows its sales and its costs in different months.
    </p>
  </div>
  @include('partials.export-dropdown', ['id' => 'ProfitLoss', 'exportValue' => 'pl'])
</div>

@include('partials.report-period-bar', ['periodRoute' => 'profit-loss'])

<!-- Headline -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:22px">
  <div class="stat-card green">
    @include('partials.stat-comparison', ['delta' => $plDeltas['revenue_total']])
    <div class="stat-icon"><i data-feather="arrow-down-circle"></i></div>
    <div class="stat-value">{{ $peso($pl['revenue_total']) }}</div>
    <div class="stat-label">Sales · {{ $pl['payment_count'] }} payment{{ $pl['payment_count'] === 1 ? '' : 's' }}</div>
  </div>
  <div class="stat-card" style="--sb:#dc2626">
    <div class="stat-icon" style="background:#fee2e2;color:#dc2626"><i data-feather="arrow-up-circle"></i></div>
    <div class="stat-value">{{ $peso($pl['direct_total'] + $pl['opex_total']) }}</div>
    <div class="stat-label">Total Costs · {{ $pl['shoot_count'] }} shoot{{ $pl['shoot_count'] === 1 ? '' : 's' }}</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="percent"></i></div>
    <div class="stat-value" style="color:{{ $signColor($pl['gross_profit']) }}">{{ $peso($pl['gross_profit']) }}</div>
    <div class="stat-label">Gross Profit · {{ $pl['gross_margin'] }}% margin</div>
  </div>
  <div class="stat-card">
    @include('partials.stat-comparison', ['delta' => $plDeltas['net_profit']])
    <div class="stat-icon"><i data-feather="trending-up"></i></div>
    <div class="stat-value" style="color:{{ $signColor($pl['net_profit']) }}">{{ $peso($pl['net_profit']) }}</div>
    <div class="stat-label">Net Profit · {{ $pl['net_margin'] }}% margin</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1.1fr .9fr;gap:16px">
  <!-- Statement -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Statement</h2>
      <span style="font-size:.75rem;color:var(--text-muted)">{{ $period['label'] }}</span>
    </div>
    <div class="card-body">
      @php
        $line = function ($label, $value, $opts = []) use ($peso) {
          $style = 'display:flex;justify-content:space-between;gap:16px;padding:5px 0;';
          if (! empty($opts['muted'])) $style .= 'color:var(--text-muted);';
          if (! empty($opts['bold'])) $style .= 'font-weight:700;';
          if (! empty($opts['top'])) $style .= 'border-top:1px solid var(--border);padding-top:7px;margin-top:2px;';
          if (! empty($opts['strong'])) $style .= 'border-top:2px solid var(--blue-700);margin-top:6px;padding-top:8px;font-size:15px;font-weight:800;';
          $titleAttr = ! empty($opts['title']) ? ' title="' . e($opts['title']) . '"' : '';
          return '<div style="' . $style . '"><span' . $titleAttr . '>' . e($label) . '</span>'
            . '<span style="font-family:monospace;white-space:nowrap;'
            . (($value < 0) ? 'color:var(--red)' : '') . '">' . $peso($value) . '</span></div>';
        };
      @endphp

      <div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;color:var(--blue-700);text-transform:uppercase;margin-bottom:2px">Sales</div>
      @foreach ($pl['revenue_by_type'] as $r)
        {!! $line($r['label'] . ($r['count'] ? " ({$r['count']})" : ''), $r['amount'], ['muted' => true]) !!}
      @endforeach
      {!! $line('Total sales', $pl['revenue_total'], ['bold' => true, 'top' => true]) !!}

      <div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;color:var(--blue-700);text-transform:uppercase;margin:14px 0 2px">Direct Costs</div>
      {!! $line('Crew talent fees (by shoot date)', $pl['crew_cost'], ['muted' => true, 'title' => 'Every crew assignment for a shoot in this period, at planned rate — not deducted for no-shows, and not limited to bookings with a confirmed estimate. Will differ from Cost Estimates\' and Crew Data\'s crew figures.']) !!}
      {!! $line('Transportation', $pl['transport_cost'], ['muted' => true]) !!}
      {!! $line('Total direct costs', $pl['direct_total'], ['bold' => true, 'top' => true]) !!}

      {!! $line('Gross profit (' . $pl['gross_margin'] . '%)', $pl['gross_profit'], ['bold' => true, 'top' => true]) !!}

      <div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;color:var(--blue-700);text-transform:uppercase;margin:14px 0 2px">Operating Expenses</div>
      {!! $line('Repairs &amp; purchases', $pl['repair_spend'], ['muted' => true]) !!}
      {!! $line('Total operating expenses', $pl['opex_total'], ['bold' => true, 'top' => true]) !!}

      {!! $line('Net profit (' . $pl['net_margin'] . '%)', $pl['net_profit'], ['strong' => true]) !!}

      @if ($pl['repair_spend'] == 0)
      <div style="font-size:.72rem;color:var(--text-muted);margin-top:10px">
        Repairs &amp; purchases reads ₱0.00 until repair/purchase tickets are marked completed with an actual cost.
      </div>
      @endif
    </div>
  </div>

  <!-- Trend -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Trend</h2>
      <div class="tabs" style="margin-bottom:0">
        @foreach ([6, 12] as $cm)
        <a href="{{ route('profit-loss', ['period' => $period['mode'], 'm' => $period['month'], 'chart' => $cm]) }}"
           class="tab-btn {{ $period['chart_months'] === $cm ? 'active' : '' }}">{{ $cm }}M</a>
        @endforeach
      </div>
    </div>
    <div class="card-body">
      <div style="height:230px"><canvas id="plChart"></canvas></div>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Month</th><th style="text-align:right">Sales</th><th style="text-align:right">Costs</th><th style="text-align:right">Net</th></tr></thead>
        <tbody>
        @foreach ($monthly as $m)
        <tr>
          <td style="white-space:nowrap">{{ $m->label }}</td>
          <td style="text-align:right">{{ $peso($m->revenue) }}</td>
          <td style="text-align:right;color:var(--red)">{{ $peso($m->costs) }}</td>
          <td style="text-align:right;font-weight:700;color:{{ $signColor($m->net) }}">{{ $peso($m->net) }}</td>
        </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
  const el = document.getElementById('plChart');
  if (!el || typeof Chart === 'undefined') return;
  const rows = @json($monthly);
  new Chart(el, {
    type: 'bar',
    data: {
      labels: rows.map(r => r.label),
      datasets: [
        { label: 'Sales', data: rows.map(r => r.revenue), backgroundColor: '#2e9e7a' },
        { label: 'Costs', data: rows.map(r => r.costs), backgroundColor: '#dc2626' },
        { label: 'Net', type: 'line', data: rows.map(r => r.net), borderColor: '#0060C7',
          backgroundColor: '#0060C7', tension: .3, borderWidth: 2, pointRadius: 3 }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'top', labels: { boxWidth: 10, font: { size: 11 } } } },
      scales: { y: { beginAtZero: true, ticks: { callback: v => '₱' + Number(v).toLocaleString('en-PH') } } }
    }
  });
})();
</script>
@endpush
