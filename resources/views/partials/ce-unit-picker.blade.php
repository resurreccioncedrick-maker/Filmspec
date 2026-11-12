@php
    $statusLabel = ['available' => 'Available', 'allocated' => 'Allocated elsewhere', 'in_field' => 'In the Field',
        'inspection_pending' => 'Pending Inspection', 'under_maintenance' => 'Under Maintenance', 'booked_overlap' => 'Booked on overlapping dates'];
    $selectStyle = 'width:100%;padding:7px 9px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:12.5px;font-family:"DM Sans",sans-serif;background:#fff;color:#0B1A33;margin-bottom:4px';
@endphp
@if ($confirmed)
<div id="{{ $rowKey }}_view">
  <span class="badge badge-green" style="font-size:10px">Confirmed</span>
  @if ($tags)
  <div style="font-size:11.5px;font-family:monospace;margin-top:4px;color:#334155">{{ $tags }}</div>
  @endif
  @if ($canManage)
  <button type="button" class="edit-mini-btn" style="margin-top:6px;padding:3px 9px;font-size:10.5px"
          onclick="document.getElementById('{{ $rowKey }}_view').style.display='none';document.getElementById('{{ $rowKey }}_form').style.display='block'">
    <i data-feather="rotate-ccw" style="width:11px;height:11px"></i> Reopen
  </button>
  @endif
</div>
@elseif (! $canManage)
<span style="color:var(--muted);font-size:11px">Not yet confirmed</span>
@endif

@if ($canManage)
<div id="{{ $rowKey }}_form" style="{{ $confirmed ? 'display:none' : '' }}">
  <input type="hidden" name="lines[{{ $idValue }}][{{ $lineField }}]" value="{{ $idValue }}">
  @if ($opts->isEmpty())
  <div style="font-size:10.5px;color:var(--red)">No units registered for this item at all — contact Super Admin.</div>
  @else
  @for ($slot = 0; $slot < $quantity; $slot++)
  @php $preselect = $allocatedHere[$slot] ?? null; @endphp
  <select name="lines[{{ $idValue }}][unit_ids][]" style="{{ $selectStyle }}">
    <option value="">— pick unit —</option>
    @foreach ($opts as $u)
    <option value="{{ $u->unit_id }}" {{ (string) $preselect === (string) $u->unit_id ? 'selected' : '' }} {{ ! $u->selectable ? 'disabled' : '' }}>
      {{ $u->asset_tag }}{{ $u->selectable ? '' : ' — ' . ($statusLabel[$u->status] ?? ucfirst($u->status)) }}
    </option>
    @endforeach
  </select>
  @endfor
  @if ($opts->where('selectable', true)->isEmpty())
  <div style="font-size:10.5px;color:var(--orange);line-height:1.4">All {{ $opts->count() }} unit(s) of this item are currently committed elsewhere.</div>
  @endif
  @endif
</div>
@endif
