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
