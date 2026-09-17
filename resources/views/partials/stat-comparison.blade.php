{{--
  Renders a "vs last period" pill for a stat-card, or nothing at all when there's
  nothing sensible to show (ReportPeriod::delta() already returns null for that:
  both current and previous are zero, or the mode is 'all' and there's no previous
  period to compare against in the first place).

  $delta: the ['dir' => 'up'|'down', 'pct' => int|null] array from ReportPeriod::delta(),
  or null. Optional 'suffix' overrides the unit shown after the number — default '%';
  pass 'pt' for a rate/percentage metric (e.g. utilization), where a point difference
  reads more honestly than running a percentage through a relative-percent-change formula.
--}}
@if ($delta)
<span class="stat-cmp {{ $delta['dir'] }}">
  @if ($delta['dir'] === 'up')
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="18 15 12 9 6 15"></polyline></svg>
  @else
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
  @endif
  {{ $delta['pct'] !== null ? $delta['pct'] . ($delta['suffix'] ?? '%') : 'New' }}
</span>
@endif
