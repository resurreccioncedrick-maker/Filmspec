{{-- One row of the Saved Cost Estimates list (Part 9), matching the reference tool's format:
     reference · project · client · date · total, with Edit / Erase. Erase is drafts-only —
     confirmed CEs are immutable history (Part 2a). --}}
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:8px 0;border-bottom:1px solid var(--border)">
  <div style="min-width:0">
    <div style="font-size:.88rem">
      <span style="font-family:monospace;font-weight:700;color:var(--blue-700)">{{ $row->ce_reference }}</span>
      <span style="font-weight:600;margin-left:6px">{{ $booking->project_title ?: $booking->booking_reference }}</span>
      <span style="color:var(--text-muted);margin-left:6px">{{ $booking->company_name ?: $booking->contact_person }}</span>
    </div>
    <div style="font-size:.75rem;color:var(--text-muted);margin-top:2px">
      {{ $row->generated_at ? \Carbon\Carbon::parse($row->generated_at)->format('M j, Y') : '—' }}
      @if ($row->status === 'confirmed')
        · <span style="color:var(--green-700, #15803d);font-weight:600">confirmed</span>
        @if ($row->confirmed_by_name) by {{ $row->confirmed_by_name }} @endif
      @else
        · <span style="font-weight:600">draft</span>
      @endif
      @if ($row->ce_id === $ceLatestId) · <em>editing now</em> @endif
    </div>
  </div>
  <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
    <span style="font-family:monospace;font-weight:700;white-space:nowrap">₱{{ number_format($row->grand_total, 2) }}</span>
    <a href="{{ route('ce-preview', ['booking_id' => $booking->booking_id, 'ce_id' => $row->ce_id]) }}"
       class="btn btn-outline btn-sm" title="Open this cost estimate">Edit</a>
    @if ($ceCanErase && $row->status !== 'confirmed')
    <form method="POST" action="{{ $actionUrl }}" onsubmit="return confirm('Erase draft {{ $row->ce_reference }}?')" style="display:inline">
      @csrf
      <input type="hidden" name="action" value="erase_ce">
      <input type="hidden" name="ce_id" value="{{ $row->ce_id }}">
      <button type="submit" class="btn btn-outline btn-sm" style="border-color:var(--red);color:var(--red)">Erase</button>
    </form>
    @endif
  </div>
</div>
