@extends('layouts.app')

@section('pageTitle', 'Accessories')

@section('breadcrumb')
<span>Accessories</span>
@endsection

@section('topbarActions')
@include('partials.export-dropdown', ['id' => 'Accessories'])
@if ($canManage)
<button onclick="openModal('modalAddAcc')" class="btn btn-primary btn-sm">
  <i data-feather="plus"></i> Add Accessory
</button>
@endif
@endsection

@section('content')
@php
  $accBase = route('accessories');
@endphp

@if ($msg)
<div class="alert alert-{{ $msg['type'] }}" data-autohide>
  <i data-feather="{{ $msg['type'] === 'success' ? 'check-circle' : 'alert-circle' }}"></i>
  {!! $msg['text'] !!}
</div>
@endif

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px">
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="package"></i></div>
    <div class="stat-value">{{ $stats['total'] }}</div>
    <div class="stat-label">Total Accessories</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon" style="background:rgba(74,222,128,.12);color:var(--green)"><i data-feather="check-circle"></i></div>
    <div class="stat-value">{{ $stats['included'] }}</div>
    <div class="stat-label">Package Included</div>
  </div>
  <div class="stat-card" style="--sb:var(--accent)">
    <div class="stat-icon" style="background:rgba(0,96,199,.12);color:var(--accent)"><i data-feather="plus-circle"></i></div>
    <div class="stat-value">{{ $stats['addon'] }}</div>
    <div class="stat-label">Optional Add-ons</div>
  </div>
</div>

<!-- Filter bar -->
<div class="card" style="margin-bottom:18px">
  <div class="card-body" style="padding:12px 16px">
    <form method="GET" action="{{ $accBase }}" class="filter-bar">
      <div class="search-input-wrap">
        <i data-feather="search"></i>
        <input type="text" name="q" placeholder="Search accessories…" value="{{ $search }}">
      </div>
      <select name="incl" class="form-control" style="width:auto">
        <option value="">All Types</option>
        <option value="1" {{ $inclFilter === '1' ? 'selected' : '' }}>Package Included</option>
        <option value="0" {{ $inclFilter === '0' ? 'selected' : '' }}>Add-on Only</option>
      </select>
      <button type="submit" class="btn btn-outline btn-sm"><i data-feather="filter"></i> Filter</button>
    </form>
  </div>
</div>

<style>
.acc-pg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(195px,1fr));gap:14px}
.acc-pg-card{background:var(--surface);border:1px solid var(--border);border-radius:11px;overflow:hidden;transition:box-shadow .15s,transform .1s;display:flex;flex-direction:column}
.acc-pg-card:hover{box-shadow:var(--shadow-md);transform:translateY(-1px)}
.acc-pg-thumb{width:100%;aspect-ratio:16/9;background:var(--s2);display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative;flex-shrink:0}
.acc-pg-thumb img{width:100%;height:100%;object-fit:cover}
.acc-pg-icon{font-family:var(--font-display);font-size:30px;color:var(--accent);opacity:.3;letter-spacing:1px}
.acc-pg-pill{position:absolute;top:8px;left:8px;font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:3px 9px;border-radius:10px;backdrop-filter:blur(4px)}
.acc-pg-pill.included{background:rgba(21,128,61,.85);color:#fff}
.acc-pg-pill.addon{background:rgba(0,96,199,.85);color:#fff}
.acc-pg-body{padding:10px 12px;flex:1;display:flex;flex-direction:column}
.acc-pg-name{font-weight:700;font-size:.84rem;color:var(--text);margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.acc-pg-desc{font-size:.7rem;color:var(--muted);margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1}
.acc-pg-rate{font-size:.78rem;font-weight:700;color:var(--accent);font-family:var(--font-mono);margin-bottom:5px}
.acc-pg-equip{font-size:.68rem;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px}
.acc-pg-actions{display:flex;gap:4px;padding:8px 10px 10px;border-top:1px solid var(--border);margin-top:auto}
.acc-link-chip{display:inline-flex;align-items:center;gap:3px;background:rgba(0,96,199,.1);color:var(--accent);border:1px solid rgba(0,96,199,.2);border-radius:10px;font-size:.62rem;font-weight:700;padding:2px 8px;cursor:pointer;transition:background .12s}
.acc-link-chip:hover{background:rgba(0,96,199,.2)}
.acc-link-chip.none{background:rgba(220,38,38,.08);color:var(--red);border-color:rgba(220,38,38,.2)}
.acc-img-zone{border:1.5px dashed var(--border2);border-radius:7px;padding:10px 14px;cursor:pointer;text-align:center;background:var(--s2);transition:border-color .15s;position:relative;min-height:68px;display:flex;align-items:center;justify-content:center}
.acc-img-zone:hover{border-color:var(--accent)}
.acc-img-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.acc-img-preview-modal{width:100%;height:90px;object-fit:cover;border-radius:5px;display:none}
.acc-equip-list{max-height:220px;overflow-y:auto;border:1px solid var(--border);border-radius:7px}
.acc-equip-item{display:flex;align-items:center;gap:10px;padding:8px 12px;border-bottom:1px solid var(--border);cursor:pointer;transition:background .1s}
.acc-equip-item:last-child{border-bottom:none}
.acc-equip-item:hover{background:var(--s2)}
.acc-equip-item label{cursor:pointer;flex:1;font-size:.82rem}
.acc-equip-cat{font-size:.65rem;color:var(--muted);margin-left:auto}
</style>

@if ($accessories->isEmpty())
<div class="card">
  <div class="empty-state">
    <i data-feather="package"></i>
    <h3>No accessories found</h3>
    @if ($canManage)
    <p>Add your first accessory to get started. Accessories can be linked to multiple equipment items.</p>
    <button onclick="openModal('modalAddAcc')" class="btn btn-primary"><i data-feather="plus"></i> Add Accessory</button>
    @else
    <p>No accessories match your search.</p>
    @endif
  </div>
</div>
@else
<div class="acc-pg-grid">
  @foreach ($accessories as $acc)
  @php
    $rate = (float) $acc->daily_rate;
    $rateStr = $rate > 0 ? '₱' . number_format($rate, 2) . '/day' : 'Included in package';
    $inclCls = $acc->is_included ? 'included' : 'addon';
    $inclLbl = $acc->is_included ? 'Package' : 'Add-on';
    $eqCount = (int) $acc->equipment_count;
    $qtyColor = $acc->available < 1 ? 'var(--red)' : ($acc->available <= 1 ? '#d97706' : 'var(--green)');
  @endphp
  <div class="acc-pg-card">
    <div class="acc-pg-thumb">
      @if ($acc->image_path)
      <img src="{{ asset('storage/' . $acc->image_path) }}" alt="">
      @else
      <span class="acc-pg-icon">{{ strtoupper(substr($acc->accessory_name, 0, 2)) }}</span>
      @endif
      <span class="acc-pg-pill {{ $inclCls }}">{{ $inclLbl }}</span>
    </div>
    <div class="acc-pg-body">
      <div class="acc-pg-name" title="{{ $acc->accessory_name }}">{{ $acc->accessory_name }}</div>
      <div class="acc-pg-desc">{{ $acc->description ?: '—' }}</div>
      <div class="acc-pg-rate">{{ $rateStr }}</div>
      @if (($acc->tracking_method ?? 'quantity') === 'individual')
      <div style="font-size:.68rem;color:var(--accent);font-weight:700;margin-bottom:4px">
        {{ $acc->unit_count }} unit{{ $acc->unit_count === 1 ? '' : 's' }} tracked
      </div>
      @else
      <div style="font-size:.68rem;color:{{ $qtyColor }};font-weight:700;margin-bottom:4px">
        {{ $acc->available }}/{{ (int) ($acc->quantity ?? 1) }} available{{ $acc->in_use > 0 ? ' · ' . $acc->in_use . ' out' : '' }}
      </div>
      @endif
      <div>
        @if ($eqCount > 0)
        <span class="acc-link-chip" title="{{ $acc->linked_equipment ?? '' }}"
              onclick="openLinks({{ $acc->accessory_id }}, '{{ addslashes($acc->accessory_name) }}')">
          <i data-feather="link" style="width:10px;height:10px"></i> {{ $eqCount }} equipment
        </span>
        @else
        <span class="acc-link-chip none"
              onclick="openLinks({{ $acc->accessory_id }}, '{{ addslashes($acc->accessory_name) }}')">
          <i data-feather="link-2" style="width:10px;height:10px"></i> Not linked
        </span>
        @endif
      </div>
    </div>
    @if ($canManage)
    <div class="acc-pg-actions">
      <button class="btn btn-outline btn-sm" style="flex:1;font-size:.72rem"
              onclick="openLinks({{ $acc->accessory_id }}, '{{ addslashes($acc->accessory_name) }}')">
        <i data-feather="link" style="width:11px;height:11px"></i> Manage
      </button>
      <button class="btn btn-outline btn-sm" style="font-size:.72rem"
              onclick='openEditAcc(@json($acc))' title="Edit">
        <i data-feather="edit-2" style="width:11px;height:11px"></i>
      </button>
      <button class="btn btn-outline btn-sm" style="font-size:.72rem"
              onclick="openAccUnits({{ $acc->accessory_id }}, {{ json_encode($acc->accessory_name) }})" title="Physical Units ({{ $acc->unit_count }})">
        <i data-feather="hash" style="width:11px;height:11px"></i>
      </button>
      <button class="btn btn-danger btn-sm" style="font-size:.72rem"
              onclick="deleteAcc({{ $acc->accessory_id }}, '{{ addslashes($acc->accessory_name) }}')" title="Delete">
        <i data-feather="trash-2" style="width:11px;height:11px"></i>
      </button>
    </div>
    @endif
  </div>
  @endforeach
</div>
@endif

@if ($canManage)
<!-- ADD ACCESSORY MODAL -->
<div class="modal-overlay" id="modalAddAcc">
  <div class="modal" style="max-width:580px">
    <div class="modal-header">
      <h3 class="modal-title"><i data-feather="package" style="width:16px;height:16px;margin-right:8px;vertical-align:middle"></i>New Accessory</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div style="grid-column:1/-1">
          <label style="font-size:.75rem;display:block;margin-bottom:5px">Photo <span style="color:var(--muted);font-weight:400">(optional)</span></label>
          <div class="acc-img-zone" id="addImgZone">
            <input type="file" id="addAccImg" accept="image/*" onchange="previewAccImg('add',this)">
            <img id="addAccImgPrev" class="acc-img-preview-modal" alt="">
            <div id="addAccImgPh" style="font-size:.75rem;color:var(--muted);pointer-events:none">
              <i data-feather="image" style="width:16px;height:16px;vertical-align:middle;margin-right:4px"></i>Click to upload photo
            </div>
          </div>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label>Accessory Name *</label>
          <input type="text" id="addAccName" class="form-control" placeholder="e.g. 50mm Lens, Tripod">
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label>Description</label>
          <input type="text" id="addAccDesc" class="form-control" placeholder="Optional notes">
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label>Accessory Type</label>
          <select id="addAccType" class="form-control" onchange="onAddAccTypeChange()">
            @foreach ($typeLabel as $tk => $tl)
            <option value="{{ $tk }}" {{ $tk === 'optional_addon' ? 'selected' : '' }}>{{ $tl }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label>Tracking Method</label>
          <select id="addAccTracking" class="form-control" onchange="onAddAccTrackingChange()">
            <option value="quantity" selected>Quantity Tracked</option>
            <option value="individual">Individually Tracked</option>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0" id="addAccRateWrap">
          <label>Daily Rate (₱)</label>
          <input type="number" id="addAccRate" class="form-control" step="0.01" min="0" value="0">
        </div>
        <div class="form-group" style="margin-bottom:0;display:none" id="addAccInclNote">
          <label>Pricing</label>
          <div style="font-size:.8rem;color:var(--muted);background:var(--s2);border:1px solid var(--border);border-radius:6px;padding:8px 10px">
            No separate charge. Included with linked equipment/package.
          </div>
        </div>
        <div class="form-group" style="margin-bottom:0" id="addAccQtyWrap">
          <label>Stock Qty <span style="color:var(--muted);font-weight:400">— units available</span></label>
          <input type="number" id="addAccQty" class="form-control" min="1" value="1" placeholder="1">
        </div>
        <div class="form-group" style="margin-bottom:0;display:none" id="addAccIndivNote">
          <div style="font-size:.8rem;color:var(--muted);background:var(--s2);border:1px solid var(--border);border-radius:6px;padding:8px 10px">
            Individual units are added after saving, from the accessory's <strong>Units</strong> button.
          </div>
        </div>
        <div style="grid-column:1/-1">
          <label style="font-size:.75rem;font-weight:700;display:block;margin-bottom:8px">
            Compatible Equipment
            <span style="font-weight:400;color:var(--muted)">— select which equipment this belongs to</span>
          </label>
          <div style="position:relative;margin-bottom:6px">
            <i data-feather="search" style="position:absolute;left:9px;top:50%;transform:translateY(-50%);width:13px;height:13px;color:var(--muted);pointer-events:none"></i>
            <input type="text" class="form-control" style="padding-left:28px;font-size:.8rem" placeholder="Search equipment…" oninput="filterEquipList('add',this.value)">
          </div>
          <div class="acc-equip-list" id="addEquipList">
            @foreach ($allEquipment as $eq)
            <div class="acc-equip-item" data-search="{{ strtolower($eq->equipment_name) }}">
              <input type="checkbox" id="addEq{{ $eq->equipment_id }}" class="add-equip-chk" value="{{ $eq->equipment_id }}" onchange="document.getElementById('addEqQty{{ $eq->equipment_id }}').style.display = this.checked ? 'inline-block' : 'none'">
              <label for="addEq{{ $eq->equipment_id }}">{{ $eq->equipment_name }}</label>
              <input type="number" id="addEqQty{{ $eq->equipment_id }}" class="add-equip-incqty form-control" data-eid="{{ $eq->equipment_id }}" min="1" placeholder="Qty" title="Included quantity for this equipment" style="display:none;width:56px;padding:3px 5px;font-size:.72rem;margin-left:6px">
              <span class="acc-equip-cat">{{ $eq->category_name }}</span>
            </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
      <button type="button" class="btn btn-primary" onclick="submitAddAcc()"><i data-feather="save"></i> Add Accessory</button>
    </div>
  </div>
</div>

<!-- EDIT ACCESSORY MODAL -->
<div class="modal-overlay" id="modalEditAcc">
  <div class="modal" style="max-width:580px">
    <div class="modal-header">
      <h3 class="modal-title"><i data-feather="edit-2" style="width:16px;height:16px;margin-right:8px;vertical-align:middle"></i>Edit Accessory</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="editAccId">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div style="grid-column:1/-1">
          <label style="font-size:.75rem;display:block;margin-bottom:5px">Photo</label>
          <div class="acc-img-zone" id="editImgZone">
            <input type="file" id="editAccImg" accept="image/*" onchange="previewAccImg('edit',this)">
            <img id="editAccImgPrev" class="acc-img-preview-modal" alt="">
            <div id="editAccImgPh" style="font-size:.75rem;color:var(--muted);pointer-events:none">
              <i data-feather="image" style="width:16px;height:16px;vertical-align:middle;margin-right:4px"></i>Click to upload / replace
            </div>
          </div>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label>Accessory Name *</label>
          <input type="text" id="editAccName" class="form-control">
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label>Description</label>
          <input type="text" id="editAccDesc" class="form-control">
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label>Accessory Type</label>
          <select id="editAccType" class="form-control" onchange="onEditAccTypeChange()">
            @foreach ($typeLabel as $tk => $tl)
            <option value="{{ $tk }}">{{ $tl }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label>Tracking Method</label>
          <select id="editAccTracking" class="form-control" onchange="onEditAccTrackingChange()">
            <option value="quantity">Quantity Tracked</option>
            <option value="individual">Individually Tracked</option>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0" id="editAccRateWrap">
          <label>Daily Rate (₱)</label>
          <input type="number" id="editAccRate" class="form-control" step="0.01" min="0">
        </div>
        <div class="form-group" style="margin-bottom:0;display:none" id="editAccInclNote">
          <label>Pricing</label>
          <div style="font-size:.8rem;color:var(--muted);background:var(--s2);border:1px solid var(--border);border-radius:6px;padding:8px 10px">
            No separate charge. Included with linked equipment/package.
          </div>
        </div>
        <div class="form-group" style="margin-bottom:0" id="editAccQtyWrap">
          <label>Stock Qty</label>
          <input type="number" id="editAccQty" class="form-control" min="1" value="1">
        </div>
        <div class="form-group" style="margin-bottom:0;display:none" id="editAccIndivNote">
          <div style="font-size:.8rem;color:var(--muted);background:var(--s2);border:1px solid var(--border);border-radius:6px;padding:8px 10px">
            Manage individual units from the accessory's <strong>Units</strong> button.
          </div>
        </div>
        <div style="grid-column:1/-1">
          <label style="font-size:.75rem;font-weight:700;display:block;margin-bottom:8px">Compatible Equipment</label>
          <div style="position:relative;margin-bottom:6px">
            <i data-feather="search" style="position:absolute;left:9px;top:50%;transform:translateY(-50%);width:13px;height:13px;color:var(--muted);pointer-events:none"></i>
            <input type="text" class="form-control" style="padding-left:28px;font-size:.8rem" placeholder="Search equipment…" oninput="filterEquipList('edit',this.value)">
          </div>
          <div class="acc-equip-list" id="editEquipList">
            @foreach ($allEquipment as $eq)
            <div class="acc-equip-item" data-search="{{ strtolower($eq->equipment_name) }}">
              <input type="checkbox" id="editEq{{ $eq->equipment_id }}" class="edit-equip-chk" value="{{ $eq->equipment_id }}" onchange="document.getElementById('editEqQty{{ $eq->equipment_id }}').style.display = this.checked ? 'inline-block' : 'none'">
              <label for="editEq{{ $eq->equipment_id }}">{{ $eq->equipment_name }}</label>
              <input type="number" id="editEqQty{{ $eq->equipment_id }}" class="edit-equip-incqty form-control" data-eid="{{ $eq->equipment_id }}" min="1" placeholder="Qty" title="Included quantity for this equipment" style="display:none;width:56px;padding:3px 5px;font-size:.72rem;margin-left:6px">
              <span class="acc-equip-cat">{{ $eq->category_name }}</span>
            </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
      <button type="button" class="btn btn-primary" onclick="submitEditAcc()"><i data-feather="save"></i> Save Changes</button>
    </div>
  </div>
</div>

<!-- PHYSICAL UNITS MODAL (ACCESSORY) -->
<div class="modal-overlay" id="modalAccUnits">
  <div class="modal" style="max-width:780px">
    <div class="modal-header">
      <div class="modal-title"><i data-feather="hash"></i> Physical Units — <span id="accUnitsName" style="color:var(--accent)"></span></div>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <div class="table-wrap" style="margin-bottom:14px">
        <table>
          <thead><tr><th>Asset Tag</th><th>Serial No.</th><th>Condition</th><th>Status</th><th>Location</th><th>Actions</th></tr></thead>
          <tbody id="accUnitsTbody"></tbody>
        </table>
      </div>
      <div id="accUnitsEmpty" style="display:none;text-align:center;color:var(--muted);padding:16px;font-size:.85rem">No physical units recorded yet.</div>

      <div style="border-top:1px solid var(--border);padding-top:14px">
        <div style="font-size:.78rem;font-weight:700;color:var(--text);margin-bottom:8px">Add Unit</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
          <div class="form-group" style="margin-bottom:0;flex:1;min-width:120px">
            <label style="font-size:.72rem">Asset Tag *</label>
            <input type="text" id="accUnitAssetTag" class="form-control" placeholder="e.g. ACC-001">
          </div>
          <div class="form-group" style="margin-bottom:0;flex:1;min-width:120px">
            <label style="font-size:.72rem">Serial No.</label>
            <input type="text" id="accUnitSerialNo" class="form-control">
          </div>
          <div class="form-group" style="margin-bottom:0;min-width:130px">
            <label style="font-size:.72rem">Condition</label>
            <select id="accUnitCondition" class="form-control">
              @foreach ($unitCondLabel as $ck => $cl)
              <option value="{{ $ck }}" {{ $ck === 'good' ? 'selected' : '' }}>{{ $cl }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0;min-width:150px">
            <label style="font-size:.72rem">Status</label>
            <select id="accUnitStatus" class="form-control">
              @foreach ($unitStatusLabel as $sk => $sl)
              <option value="{{ $sk }}" {{ $sk === 'available' ? 'selected' : '' }}>{{ $sl }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0;flex:1;min-width:120px">
            <label style="font-size:.72rem">Location</label>
            <input type="text" id="accUnitLocation" class="form-control">
          </div>
          <button type="button" class="btn btn-primary btn-sm" onclick="addAccUnit()"><i data-feather="plus" style="width:13px;height:13px"></i> Add Unit</button>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" data-modal-close>Close</button>
    </div>
  </div>
</div>

<!-- MANAGE LINKS MODAL -->
<style>
.lnk-cat-header{padding:5px 14px;font-size:.62rem;font-weight:800;text-transform:uppercase;letter-spacing:.09em;color:var(--muted);background:var(--s2);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:1}
.lnk-row{border-bottom:1px solid var(--border)}
.lnk-row:last-child{border-bottom:none}
.lnk-row label{display:flex;align-items:center;gap:10px;padding:9px 14px;cursor:pointer;transition:background .1s;width:100%}
.lnk-row label:hover{background:var(--s2)}
.lnk-row label input[type=checkbox]{flex-shrink:0}
.lnk-row-name{font-size:.82rem;flex:1}
.lnk-cat-pill{padding:3px 11px;border-radius:20px;font-size:.72rem;font-weight:600;cursor:pointer;border:1px solid var(--border);background:var(--s2);color:var(--text);transition:all .12s}
.lnk-cat-pill.active,.lnk-cat-pill:hover{background:var(--accent);color:#fff;border-color:var(--accent)}
.lnk-no-results{text-align:center;padding:20px;color:var(--muted);font-size:.82rem;display:none}
</style>
<div class="modal-overlay" id="modalLinks">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <div>
        <h3 class="modal-title">
          <i data-feather="link" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>
          Equipment Links — <span id="linksAccName" style="color:var(--accent)"></span>
        </h3>
        <div style="font-size:.73rem;color:var(--muted);margin-top:2px">
          <span id="linksSelectedCount" style="color:var(--accent);font-weight:700">0</span> equipment linked
        </div>
      </div>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body" style="padding-bottom:8px">
      <input type="hidden" id="linksAccId">

      <div style="position:relative;margin-bottom:10px">
        <i data-feather="search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:14px;height:14px;color:var(--muted);pointer-events:none"></i>
        <input type="text" id="linksSearch" class="form-control" style="padding-left:32px"
               placeholder="Search equipment by name…" oninput="filterLinks()">
      </div>

      <div id="linksCatPills" style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:12px"></div>

      <div style="display:flex;gap:8px;margin-bottom:10px">
        <button type="button" class="btn btn-outline btn-sm" onclick="setAllLinks(true)">
          <i data-feather="check-square" style="width:12px;height:12px"></i> Select visible
        </button>
        <button type="button" class="btn btn-outline btn-sm" onclick="setAllLinks(false)">
          <i data-feather="square" style="width:12px;height:12px"></i> Clear visible
        </button>
      </div>

      <div id="linksEquipScroll" style="max-height:280px;overflow-y:auto;border:1px solid var(--border);border-radius:8px">
        @php $eqByCategory = $allEquipment->groupBy('category_name'); @endphp
        @foreach ($eqByCategory as $catName => $catItems)
        <div class="lnk-cat-group" data-category="{{ strtolower($catName) }}">
          <div class="lnk-cat-header">
            {{ $catName }}
            <span style="font-weight:400;opacity:.7">({{ $catItems->count() }})</span>
          </div>
          @foreach ($catItems as $eq)
          <div class="lnk-row"
               data-name="{{ strtolower($eq->equipment_name) }}"
               data-cat="{{ strtolower($catName) }}">
            <label>
              <input type="checkbox" class="link-equip-chk" value="{{ $eq->equipment_id }}" onchange="updateLinksCount()">
              <span class="lnk-row-name">{{ $eq->equipment_name }}</span>
            </label>
          </div>
          @endforeach
        </div>
        @endforeach
        <div class="lnk-no-results" id="linksNoResults">No equipment matches your search.</div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
      <button type="button" class="btn btn-primary" onclick="saveLinks()"><i data-feather="save"></i> Save Links</button>
    </div>
  </div>
</div>
@endif

@push('scripts')
<script>
const ACC_ASSET_BASE = "{{ asset('storage') }}";
const ACC_BASE_URL = "{{ $accBase }}";
const ACC_CSRF = "{{ csrf_token() }}";

function escHtml(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function previewAccImg(prefix, input) {
  const prev = document.getElementById(prefix + 'AccImgPrev');
  const ph   = document.getElementById(prefix + 'AccImgPh');
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => { prev.src = e.target.result; prev.style.display = 'block'; ph.style.display = 'none'; };
    reader.readAsDataURL(input.files[0]);
  } else {
    prev.style.display = 'none'; ph.style.display = '';
  }
}

@if ($canManage)
function onAddAccTypeChange() {
  const isIncl = document.getElementById('addAccType').value === 'package_inclusion';
  document.getElementById('addAccRateWrap').style.display = isIncl ? 'none' : '';
  document.getElementById('addAccInclNote').style.display = isIncl ? '' : 'none';
}
function onAddAccTrackingChange() {
  const isIndiv = document.getElementById('addAccTracking').value === 'individual';
  document.getElementById('addAccQtyWrap').style.display = isIndiv ? 'none' : '';
  document.getElementById('addAccIndivNote').style.display = isIndiv ? '' : 'none';
}
function onEditAccTypeChange() {
  const isIncl = document.getElementById('editAccType').value === 'package_inclusion';
  document.getElementById('editAccRateWrap').style.display = isIncl ? 'none' : '';
  document.getElementById('editAccInclNote').style.display = isIncl ? '' : 'none';
}
function onEditAccTrackingChange() {
  const isIndiv = document.getElementById('editAccTracking').value === 'individual';
  document.getElementById('editAccQtyWrap').style.display = isIndiv ? 'none' : '';
  document.getElementById('editAccIndivNote').style.display = isIndiv ? '' : 'none';
}

function submitAddAcc() {
  const name = document.getElementById('addAccName').value.trim();
  if (!name) { alert('Accessory name is required.'); return; }

  const fd = new FormData();
  fd.append('_token',         ACC_CSRF);
  fd.append('ajax_action',    'add_accessory');
  fd.append('accessory_name', name);
  fd.append('daily_rate',     document.getElementById('addAccRate').value || '0');
  fd.append('description',    document.getElementById('addAccDesc').value.trim());
  fd.append('accessory_type', document.getElementById('addAccType').value);
  fd.append('tracking_method', document.getElementById('addAccTracking').value);
  fd.append('quantity',       document.getElementById('addAccQty').value || '1');
  const imgFile = document.getElementById('addAccImg').files[0];
  if (imgFile) fd.append('accessory_image', imgFile);
  document.querySelectorAll('.add-equip-chk:checked').forEach(cb => {
    fd.append('equipment_ids[]', cb.value);
    const qtyInput = document.getElementById('addEqQty' + cb.value);
    if (qtyInput && qtyInput.value) fd.append('included_qty[' + cb.value + ']', qtyInput.value);
  });

  fetch(ACC_BASE_URL, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        location.reload();
      } else {
        alert(data.error || 'Failed to add accessory.');
      }
    });
}

function openEditAcc(acc) {
  document.getElementById('editAccId').value   = acc.accessory_id;
  document.getElementById('editAccName').value = acc.accessory_name;
  document.getElementById('editAccRate').value = acc.daily_rate;
  document.getElementById('editAccDesc').value = acc.description || '';
  document.getElementById('editAccType').value = acc.accessory_type || (acc.is_included ? 'package_inclusion' : 'optional_addon');
  document.getElementById('editAccTracking').value = acc.tracking_method || 'quantity';
  document.getElementById('editAccQty').value  = acc.quantity || 1;
  onEditAccTypeChange();
  onEditAccTrackingChange();
  const prev = document.getElementById('editAccImgPrev');
  const ph   = document.getElementById('editAccImgPh');
  document.getElementById('editAccImg').value = '';
  if (acc.image_path) {
    prev.src           = ACC_ASSET_BASE + '/' + acc.image_path;
    prev.style.display = 'block';
    ph.style.display   = 'none';
  } else {
    prev.style.display = 'none';
    ph.style.display   = '';
  }
  fetch(ACC_BASE_URL + '?get_links_detail=' + acc.accessory_id)
    .then(r => r.json())
    .then(links => {
      const byEid = {};
      links.forEach(l => { byEid[l.equipment_id] = l.included_qty; });
      document.querySelectorAll('.edit-equip-chk').forEach(cb => {
        const eid = parseInt(cb.value);
        const linked = Object.prototype.hasOwnProperty.call(byEid, eid);
        cb.checked = linked;
        const qtyInput = document.getElementById('editEqQty' + eid);
        if (qtyInput) {
          qtyInput.style.display = linked ? 'inline-block' : 'none';
          qtyInput.value = linked && byEid[eid] ? byEid[eid] : '';
        }
      });
    });
  openModal('modalEditAcc');
}

function submitEditAcc() {
  const aid  = document.getElementById('editAccId').value;
  const name = document.getElementById('editAccName').value.trim();
  if (!name) { alert('Accessory name is required.'); return; }

  const fd = new FormData();
  fd.append('_token',         ACC_CSRF);
  fd.append('ajax_action',    'edit_accessory');
  fd.append('accessory_id',   aid);
  fd.append('accessory_name', name);
  fd.append('daily_rate',     document.getElementById('editAccRate').value || '0');
  fd.append('description',    document.getElementById('editAccDesc').value.trim());
  fd.append('accessory_type', document.getElementById('editAccType').value);
  fd.append('tracking_method', document.getElementById('editAccTracking').value);
  fd.append('quantity',       document.getElementById('editAccQty').value || '1');
  const imgFile = document.getElementById('editAccImg').files[0];
  if (imgFile) fd.append('accessory_image', imgFile);
  document.querySelectorAll('.edit-equip-chk:checked').forEach(cb => {
    fd.append('equipment_ids[]', cb.value);
    const qtyInput = document.getElementById('editEqQty' + cb.value);
    if (qtyInput && qtyInput.value) fd.append('included_qty[' + cb.value + ']', qtyInput.value);
  });

  fetch(ACC_BASE_URL, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        location.reload();
      } else {
        alert(data.error || 'Update failed.');
      }
    });
}

const ACC_UNIT_COND_LABEL = @json($unitCondLabel);
const ACC_UNIT_STATUS_LABEL = @json($unitStatusLabel);
let currentAccUnitsId = null;

function openAccUnits(aid, aname) {
  currentAccUnitsId = aid;
  document.getElementById('accUnitsName').textContent = aname;
  document.getElementById('accUnitAssetTag').value = '';
  document.getElementById('accUnitSerialNo').value = '';
  document.getElementById('accUnitLocation').value = '';
  loadAccUnits();
  openModal('modalAccUnits');
}

function loadAccUnits() {
  const tbody = document.getElementById('accUnitsTbody');
  const empty = document.getElementById('accUnitsEmpty');
  tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--muted);padding:12px">Loading…</td></tr>';
  fetch(ACC_BASE_URL + '?get_units=' + currentAccUnitsId)
    .then(r => r.json())
    .then(data => {
      if (!data.length) {
        tbody.innerHTML = '';
        empty.style.display = 'block';
        return;
      }
      empty.style.display = 'none';
      tbody.innerHTML = data.map(u => {
        const condOpts = Object.keys(ACC_UNIT_COND_LABEL).map(k =>
          `<option value="${k}" ${k === u.condition ? 'selected' : ''}>${escHtml(ACC_UNIT_COND_LABEL[k])}</option>`).join('');
        const statusOpts = Object.keys(ACC_UNIT_STATUS_LABEL).map(k =>
          `<option value="${k}" ${k === u.status ? 'selected' : ''}>${escHtml(ACC_UNIT_STATUS_LABEL[k])}</option>`).join('');
        return `<tr>
          <td style="font-family:monospace;font-weight:700">${escHtml(u.asset_tag)}</td>
          <td style="font-family:monospace;font-size:.8rem">${escHtml(u.serial_no || '—')}</td>
          <td><select class="form-control acc-unit-cond-sel" style="font-size:.78rem;padding:4px 6px">${condOpts}</select></td>
          <td><select class="form-control acc-unit-status-sel" style="font-size:.78rem;padding:4px 6px">${statusOpts}</select></td>
          <td><input type="text" class="form-control acc-unit-loc-input" value="${escHtml(u.location || '')}" style="font-size:.78rem;padding:4px 6px" placeholder="Location"></td>
          <td style="white-space:nowrap">
            <button class="btn btn-outline btn-sm" onclick="saveAccUnit(${u.unit_id}, this)" title="Save"><i data-feather="save" style="width:12px;height:12px"></i></button>
            <button class="btn btn-danger btn-sm" onclick="retireAccUnit(${u.unit_id})" title="Retire Unit"><i data-feather="archive" style="width:12px;height:12px"></i></button>
          </td>
        </tr>`;
      }).join('');
      if (window.feather) feather.replace();
    });
}

function addAccUnit() {
  const tag = document.getElementById('accUnitAssetTag').value.trim();
  if (!tag) { alert('Asset tag is required.'); return; }
  const fd = new FormData();
  fd.append('ajax_action', 'add_unit');
  fd.append('accessory_id', currentAccUnitsId);
  fd.append('asset_tag', tag);
  fd.append('serial_no', document.getElementById('accUnitSerialNo').value.trim());
  fd.append('condition', document.getElementById('accUnitCondition').value);
  fd.append('status', document.getElementById('accUnitStatus').value);
  fd.append('location', document.getElementById('accUnitLocation').value.trim());
  fd.append('_token', ACC_CSRF);

  fetch(ACC_BASE_URL, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        document.getElementById('accUnitAssetTag').value = '';
        document.getElementById('accUnitSerialNo').value = '';
        document.getElementById('accUnitLocation').value = '';
        loadAccUnits();
      } else {
        alert(data.error || 'Could not add unit.');
      }
    });
}

function saveAccUnit(unitId, btn) {
  const row = btn.closest('tr');
  const fd = new FormData();
  fd.append('ajax_action', 'update_unit');
  fd.append('unit_id', unitId);
  fd.append('condition', row.querySelector('.acc-unit-cond-sel').value);
  fd.append('status', row.querySelector('.acc-unit-status-sel').value);
  fd.append('location', row.querySelector('.acc-unit-loc-input').value.trim());
  fd.append('_token', ACC_CSRF);

  fetch(ACC_BASE_URL, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => { if (!data.success) alert(data.error || 'Could not save unit.'); });
}

function retireAccUnit(unitId) {
  if (!confirm('Retire this physical unit?')) return;
  const fd = new FormData();
  fd.append('ajax_action', 'retire_unit');
  fd.append('unit_id', unitId);
  fd.append('_token', ACC_CSRF);

  fetch(ACC_BASE_URL, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => { if (data.success) loadAccUnits(); });
}

function deleteAcc(aid, name) {
  if (!confirm('Delete "' + name + '"?\n\nThis will permanently remove it from all equipment.')) return;
  const fd = new FormData();
  fd.append('_token',       ACC_CSRF);
  fd.append('ajax_action',  'delete_accessory');
  fd.append('accessory_id', aid);
  fetch(ACC_BASE_URL, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => { if (data.success) location.reload(); });
}

(function() {
  const params = new URLSearchParams(window.location.search);
  const eid = params.get('equipment_id');
  if (!eid) return;
  openModal('modalAddAcc');
  const cb = document.getElementById('addEq' + eid);
  if (cb) {
    cb.checked = true;
    cb.closest('.acc-equip-item').scrollIntoView({ block: 'center' });
  }
})();

function filterEquipList(prefix, q) {
  q = q.toLowerCase().trim();
  document.querySelectorAll('#' + prefix + 'EquipList .acc-equip-item').forEach(item => {
    item.style.display = (!q || item.dataset.search.includes(q)) ? '' : 'none';
  });
}

let _linksActiveCat = '';

function openLinks(aid, aname) {
  document.getElementById('linksAccId').value         = aid;
  document.getElementById('linksAccName').textContent  = aname;
  document.getElementById('linksSearch').value         = '';
  _linksActiveCat = '';

  document.querySelectorAll('.lnk-row,.lnk-cat-group').forEach(el => el.style.display = '');
  document.querySelectorAll('.link-equip-chk').forEach(cb => cb.checked = false);
  document.getElementById('linksNoResults').style.display = 'none';

  const cats = [...document.querySelectorAll('.lnk-cat-group')].map(g => g.dataset.category);
  const unique = [...new Set(cats)];
  const pillEl = document.getElementById('linksCatPills');
  pillEl.innerHTML =
    '<button class="lnk-cat-pill active" data-cat="" onclick="setLinkCat(this,\'\')">All</button>'
    + unique.map(c => {
        const label = document.querySelector('.lnk-cat-group[data-category="' + c + '"] .lnk-cat-header');
        const display = label ? label.textContent.trim().replace(/\s*\(\d+\)\s*$/, '') : c;
        return `<button class="lnk-cat-pill" data-cat="${escHtml(c)}" onclick="setLinkCat(this,'${escHtml(c)}')">${escHtml(display)}</button>`;
      }).join('');

  fetch(ACC_BASE_URL + '?get_linked_ids=' + aid)
    .then(r => r.json())
    .then(ids => {
      ids.map(Number).forEach(eid => {
        const cb = document.querySelector('.link-equip-chk[value="' + eid + '"]');
        if (cb) cb.checked = true;
      });
      updateLinksCount();
    });
  openModal('modalLinks');
  if (typeof feather !== 'undefined') feather.replace();
}

function setLinkCat(btn, cat) {
  _linksActiveCat = cat;
  document.querySelectorAll('.lnk-cat-pill').forEach(p => p.classList.toggle('active', p === btn));
  filterLinks();
}

function filterLinks() {
  const q   = (document.getElementById('linksSearch').value || '').toLowerCase().trim();
  const cat = _linksActiveCat;
  let anyVisible = {};

  document.querySelectorAll('.lnk-row').forEach(row => {
    const matchQ = !q   || row.dataset.name.includes(q);
    const matchC = !cat || row.dataset.cat === cat;
    const show   = matchQ && matchC;
    row.style.display = show ? '' : 'none';
    if (show) anyVisible[row.dataset.cat] = true;
  });

  let totalVisible = 0;
  document.querySelectorAll('.lnk-cat-group').forEach(grp => {
    const show = !!anyVisible[grp.dataset.category];
    grp.style.display = show ? '' : 'none';
    if (show) totalVisible++;
  });

  document.getElementById('linksNoResults').style.display = totalVisible === 0 ? 'block' : 'none';
}

function updateLinksCount() {
  const n = document.querySelectorAll('.link-equip-chk:checked').length;
  document.getElementById('linksSelectedCount').textContent = n;
}

function setAllLinks(checked) {
  document.querySelectorAll('.lnk-row').forEach(row => {
    if (row.style.display !== 'none') {
      const cb = row.querySelector('.link-equip-chk');
      if (cb) cb.checked = checked;
    }
  });
  updateLinksCount();
}

function saveLinks() {
  const aid = document.getElementById('linksAccId').value;
  const fd  = new FormData();
  fd.append('_token',       ACC_CSRF);
  fd.append('ajax_action',  'save_links');
  fd.append('accessory_id', aid);
  document.querySelectorAll('.link-equip-chk:checked').forEach(cb => fd.append('equipment_ids[]', cb.value));
  fetch(ACC_BASE_URL, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => { if (data.success) { closeModal('modalLinks'); location.reload(); } });
}
@endif
</script>
@endpush
@endsection
