{{--
  Shared per-line "Release Unit" cell for the Cost Estimate editor's Equipment Rental and
  Accessories & Add-ons tables. Renders ONLY the cell content — the enclosing <form> and the
  single "Confirm All Units" submit button live once per table (see ce-preview.blade.php), not
  per row, so confirming several lines' picks is one action instead of one click per row.

  Expected variables:
    confirmed bool    whether this line's unit_confirmed flag is already set
    tags      ?string comma-joined asset tags currently allocated to this line, or null
    opts      Collection  every non-retired unit of this model, each {unit_id, asset_tag,
                          status, selectable} — 'selectable' means available or already on
                          this exact line; everything else renders disabled so staff can see
                          the whole pool and why a given unit can't be picked right now.
    allocatedHere array  unit_id(s) currently allocated to this line, in no particular order
    quantity  int     how many unit slots this line needs
    rowKey    string  unique per-row DOM id prefix (e.g. "eq123" / "ac45")
    lineField string  the array key under "lines[...]" identifying this line (bk_equip_id / ba_id)
    idValue   int     the line's own id value
--}}
@php
    $statusLabel = ['available' => 'Available', 'allocated' => 'Allocated elsewhere', 'in_field' => 'In the Field',
        'inspection_pending' => 'Pending Inspection', 'under_maintenance' => 'Under Maintenance'];
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
