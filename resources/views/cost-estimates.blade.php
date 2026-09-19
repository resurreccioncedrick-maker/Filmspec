@extends('layouts.app')

@section('pageTitle', 'Cost Estimates')

@section('breadcrumb')
<span>Cost Estimates</span>
@endsection

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
@endpush

@section('content')
@php $ceBase = route('cost-estimates'); @endphp

@include('partials.report-period-bar', [
  'periodRoute' => 'cost-estimates',
  'extraParams' => array_filter(['tab' => $tab, 'q' => $search]),
])

<!-- Financial dashboard (moved from Reports in Part 11) -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:16px">
  <div class="stat-card">
    @include('partials.stat-comparison', ['delta' => $ceDeltas['packaged_cost']])
    <div class="stat-icon"><i data-feather="package"></i></div>
    <div class="stat-value">₱{{ number_format($ceFinancials['packaged_cost'], 2) }}</div>
    <div class="stat-label">Packaged Cost · {{ $ceFinancials['count'] }} confirmed CE{{ $ceFinancials['count'] === 1 ? '' : 's' }}</div>
  </div>
  <div class="stat-card green">
    @include('partials.stat-comparison', ['delta' => $ceDeltas['crew_total']])
    <div class="stat-icon"><i data-feather="users"></i></div>
    <div class="stat-value">₱{{ number_format($ceFinancials['crew_total'], 2) }}</div>
    <div class="stat-label" title="The crew cost frozen on each estimate at the moment it was confirmed — will differ from Crew Data's live figure if crew were reassigned or a no-show happened afterward.">Crew (Quoted) · {{ $ceFinancials['crew_pct'] }}% of packaged cost</div>
  </div>
  <div class="stat-card">
    @include('partials.stat-comparison', ['delta' => $ceDeltas['net_total']])
    <div class="stat-icon"><i data-feather="trending-up"></i></div>
    <div class="stat-value">₱{{ number_format($ceFinancials['net_total'], 2) }}</div>
    <div class="stat-label">Net for FilmSpec (ex-VAT) · {{ $ceFinancials['net_pct'] }}%</div>
  </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(2,1fr);margin-bottom:22px">
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="camera"></i></div>
    <div class="stat-value">₱{{ number_format($ceFinancials['fs_equipment_listed'], 2) }}</div>
    <div class="stat-label">FS Equipment Listed · at CE rates, before package discount</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="divide"></i></div>
    <div class="stat-value">₱{{ number_format($ceFinancials['avg_per_ce'], 2) }}</div>
    <div class="stat-label">Average Per CE · net billed</div>
  </div>
</div>

<!-- Recap chart -->
<div class="card" style="margin-bottom:16px">
  <div class="card-header">
    <h2 class="card-title">Recap
      <span style="font-weight:400;color:var(--text-muted);font-size:.8rem">
        {{ $ceMonthly->first()->label ?? '' }} – {{ $ceMonthly->last()->label ?? '' }}
      </span>
    </h2>
    <div class="tabs" style="margin-bottom:0">
      @foreach ([6, 12] as $cm)
      <a href="{{ route('cost-estimates', array_filter(['period' => $period['mode'], 'm' => $period['month'], 'chart' => $cm, 'tab' => $tab, 'q' => $search])) }}"
         class="tab-btn {{ $period['chart_months'] === $cm ? 'active' : '' }}">{{ $cm }} MONTHS</a>
      @endforeach
    </div>
  </div>
  <div class="card-body">
    <div style="height:240px"><canvas id="ceRecapChart"></canvas></div>
  </div>
</div>

<!-- Where the packaged cost goes + by client -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:22px">
  <div class="card">
    <div class="card-header"><h2 class="card-title">Where the Packaged Cost Goes</h2></div>
    <div class="card-body">
      @php
        $pcTotal = $ceFinancials['packaged_cost'] ?: 1;
        $segs = [
          ['Net for FilmSpec', $ceFinancials['net_total'], '#0060C7'],
          ['Crew', $ceFinancials['crew_total'], '#2e9e7a'],
        ];
      @endphp
      <div style="display:flex;height:16px;border-radius:4px;overflow:hidden;margin-bottom:12px">
        @foreach ($segs as [$label, $val, $color])
        <div style="width:{{ max(0, $val / $pcTotal * 100) }}%;background:{{ $color }}"></div>
        @endforeach
      </div>
      @foreach ($segs as [$label, $val, $color])
      <div style="display:flex;justify-content:space-between;align-items:center;padding:4px 0;font-size:.85rem">
        <span><span style="display:inline-block;width:9px;height:9px;background:{{ $color }};border-radius:2px;margin-right:6px"></span>{{ $label }}</span>
        <span style="font-family:monospace">₱{{ number_format($val, 2) }}
          <span style="color:var(--text-muted)">{{ round($val / $pcTotal * 100, 1) }}%</span>
        </span>
      </div>
      @endforeach
      <div style="display:flex;justify-content:space-between;padding-top:8px;margin-top:6px;border-top:2px solid var(--border);font-weight:700">
        <span>Packaged cost</span>
        <span style="font-family:monospace">₱{{ number_format($ceFinancials['packaged_cost'], 2) }}</span>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2 class="card-title">By Client <span class="badge badge-gray" style="margin-left:4px">{{ $ceByClient->count() }}</span></h2>
    </div>
    <div class="table-wrap" style="max-height:280px;overflow-y:auto">
      @if ($ceByClient->isEmpty())
      <div class="empty-state"><i data-feather="briefcase"></i><h3>No confirmed CEs in this period</h3></div>
      @else
      <table>
        <thead><tr><th>Production House</th><th style="text-align:right">CEs</th><th style="text-align:right">Package Cost</th><th style="text-align:right">Net</th></tr></thead>
        <tbody>
        @foreach ($ceByClient as $c)
        <tr>
          <td>{{ $c->client_name }}</td>
          <td style="text-align:right">{{ $c->ce_count }}</td>
          <td style="text-align:right">₱{{ number_format($c->packaged_cost, 2) }}</td>
          <td style="text-align:right;font-weight:700;color:var(--blue-700)">₱{{ number_format($c->net_total, 2) }}</td>
        </tr>
        @endforeach
        </tbody>
      </table>
      @endif
    </div>
  </div>
</div>

<!-- KPI Row — deliberately NOT tied to the period filter above: this is a live "right now,
     this real calendar month" operational snapshot (how much work is moving today), same
     idea as Dashboard's always-current numbers, not a historical report. Labeled explicitly
     so switching the period filter above (e.g. to "3 Months") doesn't look like it silently
     failed to update this row — it's a different question on purpose. -->
<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
  <span style="font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-muted)">Right Now — {{ now()->format('F Y') }}</span>
  <span style="font-size:.72rem;color:var(--text-muted)" title="Always the current calendar month, independent of the period filter above">
    <i data-feather="info" style="width:11px;height:11px;vertical-align:middle"></i> not affected by the filter above
  </span>
</div>
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:22px">
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="file-text"></i></div>
    <div class="stat-value">{{ $kpis['this_month'] }}</div>
    <div class="stat-label">Cost Estimates This Month</div>
  </div>
  <div class="stat-card orange">
    <div class="stat-icon"><i data-feather="edit-3"></i></div>
    <div class="stat-value">{{ $kpis['drafts'] }}</div>
    <div class="stat-label">Drafts Outstanding</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon"><i data-feather="check-circle"></i></div>
    <div class="stat-value">{{ $kpis['confirmed_month'] }}</div>
    <div class="stat-label">Confirmed This Month</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="dollar-sign"></i></div>
    <div class="stat-value">₱{{ number_format($kpis['confirmed_value'] / 1000, 1) }}k</div>
    <div class="stat-label">Confirmed Value This Month</div>
  </div>
</div>

<!-- Status tabs -->
<div class="tabs" style="margin-bottom:18px">
  @foreach (['all' => 'All', 'confirmed' => 'Confirmed', 'draft' => 'Draft', 'cancelled' => 'Cancelled'] as $k => $l)
  <a href="{{ $ceBase }}?tab={{ $k }}{{ $search ? '&q=' . urlencode($search) : '' }}" class="tab-btn {{ $tab === $k ? 'active' : '' }}">
    {{ $l }}
    <span class="badge {{ $k === 'confirmed' ? 'badge-green' : ($k === 'draft' ? 'badge-orange' : ($k === 'cancelled' ? 'badge-red' : 'badge-gray')) }}"
          style="margin-left:4px">{{ $tabCounts[$k] }}</span>
  </a>
  @endforeach
</div>

<div class="card">
  <div class="card-header">
    <h2 class="card-title">Saved Cost Estimates</h2>
    <div style="display:flex;align-items:center;gap:10px">
      <span style="font-size:.78rem;color:var(--text-muted)">{{ $total }} record{{ $total === 1 ? '' : 's' }}</span>
      @include('partials.export-dropdown', ['id' => 'CeFinancials', 'label' => 'Export Financials', 'exportValue' => 'ce_financials'])
    </div>
  </div>
  <div class="card-body" style="padding-bottom:0">
    <form method="GET">
      <input type="hidden" name="tab" value="{{ $tab }}">
      <div class="filter-bar">
        <div class="search-input-wrap">
          <i data-feather="search"></i>
          <input type="text" name="q" class="form-control" placeholder="Search CE #, booking, project or client…" value="{{ $search }}">
        </div>
        <button type="submit" class="btn btn-primary btn-sm"><i data-feather="filter"></i> Filter</button>
      </div>
    </form>
  </div>

  <div class="table-wrap">
    @if ($rows->isEmpty())
    <div class="empty-state">
      <i data-feather="file-text"></i>
      <h3>No cost estimates found</h3>
      <p>Cost estimates appear here as they're generated on bookings.</p>
    </div>
    @else
    <table>
      <thead>
        <tr>
          <th>CE #</th>
          <th>Date</th>
          <th>Project</th>
          <th>Client / Director</th>
          <th>Shoot</th>
          <th>Status</th>
          <th style="text-align:right">Grand Total</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      @foreach ($rows as $r)
      <tr>
        <td style="white-space:nowrap">
          <span style="font-family:monospace;font-size:.85rem;font-weight:700">{{ $r->ce_reference }}</span>
          @if ($r->is_revision)<div><span class="badge badge-orange" style="margin-top:2px">Revision</span></div>@endif
        </td>
        <td style="white-space:nowrap;font-size:.83rem">
          {{ $r->generated_at ? date('M j, Y', strtotime($r->generated_at)) : '—' }}
        </td>
        <td>
          <div style="font-weight:600">{{ $r->project_title ?: '—' }}</div>
          <div style="font-size:.75rem;color:var(--muted);font-family:monospace">{{ $r->booking_reference }}</div>
        </td>
        <td>
          <div style="font-size:.85rem">{{ $r->client_name ?: '—' }}</div>
          @if ($r->ce_director_dop)
          <div style="font-size:.75rem;color:var(--muted)">{{ $r->ce_director_dop }}</div>
          @endif
        </td>
        <td style="white-space:nowrap;font-size:.83rem">
          @if ($r->shoot_date_start === $r->shoot_date_end)
            {{ date('M j, Y', strtotime($r->shoot_date_start)) }}
          @else
            {{ date('M j', strtotime($r->shoot_date_start)) }}–{{ date('M j, Y', strtotime($r->shoot_date_end)) }}
          @endif
        </td>
        <td>
          @if ($r->booking_status === 'cancelled')
            <span class="badge badge-red">Cancelled</span>
          @else
            <span class="badge {{ $statusBadge[$r->status] ?? 'badge-gray' }}">{{ ucfirst($r->status) }}</span>
            @if ($r->confirmed_by_name)
            <div style="font-size:.7rem;color:var(--muted);margin-top:2px">by {{ $r->confirmed_by_name }}</div>
            @endif
          @endif
        </td>
        <td style="text-align:right;font-weight:700;color:var(--blue-700);white-space:nowrap">
          ₱{{ number_format($r->grand_total, 2) }}
        </td>
        <td>
          <div style="display:flex;gap:6px">
            <a href="{{ route('ce-preview', ['booking_id' => $r->booking_id, 'ce_id' => $r->ce_id]) }}"
               class="btn btn-outline btn-sm" title="Open cost estimate"><i data-feather="eye"></i></a>
            <a href="{{ route('booking-detail', $r->booking_id) }}"
               class="btn btn-outline btn-sm" title="Open booking"><i data-feather="calendar"></i></a>
          </div>
        </td>
      </tr>
      @endforeach
      </tbody>
      <tfoot>
        <tr style="background:var(--blue-50)">
          <td colspan="6" style="text-align:right;font-weight:700;padding:12px 16px;color:var(--blue-900)">
            Total on this page
          </td>
          <td style="text-align:right;font-weight:800;color:var(--blue-700);padding:12px 16px;white-space:nowrap">
            ₱{{ number_format($pageTotal, 2) }}
          </td>
          <td></td>
        </tr>
      </tfoot>
    </table>

    @if ($pages > 1)
    <div style="display:flex;gap:6px;padding:14px 18px;align-items:center;flex-wrap:wrap">
      @for ($p = 1; $p <= $pages; $p++)
      <a href="?tab={{ $tab }}&q={{ urlencode($search) }}&p={{ $p }}"
         class="btn btn-sm {{ $p === $page ? 'btn-primary' : 'btn-outline' }}">{{ $p }}</a>
      @endfor
    </div>
    @endif
    @endif
  </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
  const el = document.getElementById('ceRecapChart');
  if (!el || typeof Chart === 'undefined') return;
  const rows = @json($ceMonthly);
  new Chart(el, {
    type: 'bar',
    data: {
      labels: rows.map(r => r.label),
      datasets: [
        { label: 'Net (FS)', data: rows.map(r => r.net_total), backgroundColor: '#0060C7' },
        { label: 'Crew', data: rows.map(r => r.crew_total), backgroundColor: '#2e9e7a' }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { position: 'top', labels: { boxWidth: 10, font: { size: 11 } } } },
      scales: {
        x: { stacked: true },
        y: { stacked: true, beginAtZero: true, ticks: { callback: v => '₱' + Number(v).toLocaleString('en-PH') } }
      }
    }
  });
})();
</script>
@endpush
