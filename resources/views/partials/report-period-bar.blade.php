{{-- Shared period control (Part 11): MONTH / 3 / 6 / 12 / ALL plus ‹ month › navigation.
     Pure query-param links — no JS. $periodRoute is the route name of the hosting page;
     $extraParams carries any page-specific query the bar must preserve (sort, search, tab). --}}
@php
  $extraParams = $extraParams ?? [];
  $barBase = fn (array $overrides) => route($periodRoute, array_merge($extraParams, $overrides));
@endphp

<div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:16px">
  <div class="tabs" style="margin-bottom:0">
    @foreach (\App\Support\ReportPeriod::MODES as $key => $label)
    <a href="{{ $barBase(['period' => $key, 'm' => $period['month'], 'chart' => $period['chart_months']]) }}"
       class="tab-btn {{ $period['mode'] === $key ? 'active' : '' }}">{{ strtoupper($label) }}</a>
    @endforeach
  </div>

  @if ($period['mode'] === 'month')
  <div style="display:flex;align-items:center;gap:6px">
    <a href="{{ $barBase(['period' => 'month', 'm' => $period['prev'], 'chart' => $period['chart_months']]) }}"
       class="btn btn-outline btn-sm" title="Previous month">&lsaquo;</a>
    <span style="font-weight:700;min-width:120px;text-align:center">{{ $period['label'] }}</span>
    <a href="{{ $barBase(['period' => 'month', 'm' => $period['next'], 'chart' => $period['chart_months']]) }}"
       class="btn btn-outline btn-sm" title="Next month">&rsaquo;</a>
  </div>
  @else
  <span style="font-weight:700">{{ $period['label'] }}</span>
  @endif
</div>
