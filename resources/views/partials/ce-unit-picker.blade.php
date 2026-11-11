{{--
  Shared per-line "Release Unit" picker for the Cost Estimate editor's Equipment Rental and
  Accessories & Add-ons tables. One instance per booking_equipment/booking_accessories row.

  Expected variables:
    confirmed     bool    whether this line's unit_confirmed flag is already set
    tags          ?string comma-joined asset tags currently allocated to this line, or null
    opts          Collection  selectable units for this line (status=available or already on it)
    allocatedHere array   unit_id(s) currently allocated to this line, in no particular order
    quantity      int     how many unit slots this line needs
    rowKey        string  unique per-row DOM id prefix (e.g. "eq123" / "ac45")
    action        string  the POST action name (assign_equipment_unit / assign_accessory_unit)
    idField       string  the hidden field name identifying the line (bk_equip_id / ba_id)
    idValue       int     the line's own id value
--}}
@if ($confirmed)
<div id="{{ $rowKey }}_view">
  <span class="badge badge-green" style="font-size:10px">Confirmed</span>
  @if ($tags)
  <div style="font-size:11px;font-family:monospace;margin-top:3px">{{ $tags }}</div>
  @endif
  @if ($canManage)
  <button type="button" onclick="document.getElementById('{{ $rowKey }}_view').style.display='none';document.getElementById('{{ $rowKey }}_form').style.display='block'"
          style="background:none;border:none;padding:0;color:var(--accent);font-size:10.5px;text-decoration:underline;cursor:pointer;margin-top:3px">Edit</button>
  @endif
</div>
@elseif (! $canManage)
<span style="color:var(--muted);font-size:11px">Not yet confirmed</span>
@endif

@if ($canManage)
<form method="POST" action="{{ $actionUrl }}" id="{{ $rowKey }}_form" style="{{ $confirmed ? 'display:none' : '' }}">
  @csrf
  <input type="hidden" name="action" value="{{ $action }}">
  <input type="hidden" name="{{ $idField }}" value="{{ $idValue }}">
  <input type="hidden" name="return_to" value="ce_preview">
  <input type="hidden" name="return_ce_id" value="{{ $ceId }}">
  <input type="hidden" name="return_view" value="{{ $isClientView ? 'client' : 'internal' }}">
  @if ($opts->isEmpty())
  <div style="font-size:10.5px;color:var(--muted);margin-bottom:3px">No individually-tracked units for this item.</div>
  @else
  @for ($slot = 0; $slot < $quantity; $slot++)
  @php $preselect = $allocatedHere[$slot] ?? null; @endphp
  <select name="unit_ids[]" style="width:100%;margin-bottom:3px;font-size:11.5px;padding:3px 5px" required>
    <option value="">— pick unit —</option>
    @foreach ($opts as $u)
    <option value="{{ $u->unit_id }}" {{ (string) $preselect === (string) $u->unit_id ? 'selected' : '' }}>{{ $u->asset_tag }}</option>
    @endforeach
  </select>
  @endfor
  @endif
  <button type="submit" class="btn btn-sm btn-primary" style="width:100%;margin-top:2px">Confirm Unit{{ $quantity > 1 ? 's' : '' }}</button>
</form>
@endif
