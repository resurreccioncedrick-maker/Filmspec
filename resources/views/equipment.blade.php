@extends('layouts.app')

@section('pageTitle', 'Equipment Inventory')

@section('breadcrumb')
<span>Equipment</span>
@endsection

@section('topbarActions')
@include('partials.export-dropdown', ['id' => 'Equipment'])
@if ($canManage)
<button onclick="openModal('modalAddCat')" class="btn btn-outline btn-sm"><i data-feather="tag"></i> Category</button>
<button onclick="openModal('modalAddEquip')" class="btn btn-primary btn-sm"><i data-feather="plus"></i> Add Equipment</button>
@endif
@endsection

@section('content')
@php
  $equipBase = route('equipment');
  $qs = request()->query();
@endphp

@if ($msg)
<div class="alert alert-{{ $msg['type'] }}" data-autohide>
  <i data-feather="{{ $msg['type'] === 'success' ? 'check-circle' : 'alert-circle' }}"></i>
  {!! $msg['text'] !!}
</div>
@endif

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr)">
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="package"></i></div>
    <div class="stat-value">{{ $stats['total'] }}</div>
    <div class="stat-label">Total Items</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon" style="background:rgba(74,222,128,.12);color:var(--green)"><i data-feather="check-circle"></i></div>
    <div class="stat-value">{{ $stats['available'] }}</div>
    <div class="stat-label">Available</div>
  </div>
  <div class="stat-card" style="--sb:#f59e0b">
    <div class="stat-icon" style="background:rgba(245,158,11,.12);color:#f59e0b"><i data-feather="clock"></i></div>
    <div class="stat-value">{{ $stats['allocated'] }}</div>
    <div class="stat-label">Allocated</div>
  </div>
  <div class="stat-card" style="--sb:var(--accent)">
    <div class="stat-icon" style="background:rgba(232,197,71,.12);color:var(--accent)"><i data-feather="truck"></i></div>
    <div class="stat-value">{{ $stats['rented'] }}</div>
    <div class="stat-label">In Field</div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon" style="background:rgba(248,113,113,.12);color:var(--red)"><i data-feather="tool"></i></div>
    <div class="stat-value">{{ $stats['repair'] }}</div>
    <div class="stat-label">Under Repair</div>
  </div>
</div>

<!-- Category filter -->
<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:18px;align-items:center">
  <span style="font-size:9.5px;color:var(--muted);letter-spacing:2px;text-transform:uppercase;margin-right:4px">Category</span>
  <a href="{{ $equipBase }}?view={{ $viewMode }}{{ $search ? '&q=' . urlencode($search) : '' }}"
     class="badge {{ ! $catFilter ? 'badge-yellow' : 'badge-gray' }}"
     style="padding:4px 12px;cursor:pointer">All</a>
  @foreach ($categories as $cat)
  <a href="{{ $equipBase }}?cat={{ $cat->category_id }}&view={{ $viewMode }}{{ $search ? '&q=' . urlencode($search) : '' }}"
     class="badge {{ $catFilter == $cat->category_id ? 'badge-yellow' : 'badge-gray' }}"
     style="padding:4px 12px;cursor:pointer">
    {{ $cat->category_name }}
  </a>
  @endforeach
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">
      Equipment Catalog
      <span class="badge badge-yellow" style="font-family:var(--font-mono);font-size:.72rem">{{ $total }}</span>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
      <!-- View toggle -->
      <a href="{{ $equipBase }}?view=table&cat={{ $catFilter }}&status={{ $statusFilter }}&q={{ urlencode($search) }}"
         class="btn {{ $viewMode === 'table' ? 'btn-primary' : 'btn-outline' }} btn-sm" title="Table view">
        <i data-feather="list"></i>
      </a>
      <a href="{{ $equipBase }}?view=grid&cat={{ $catFilter }}&status={{ $statusFilter }}&q={{ urlencode($search) }}"
         class="btn {{ $viewMode === 'grid' ? 'btn-primary' : 'btn-outline' }} btn-sm" title="Card view">
        <i data-feather="grid"></i>
      </a>
    </div>
  </div>

  <div class="card-body" style="padding-bottom:0">
    <form method="GET" action="{{ $equipBase }}">
      <input type="hidden" name="view" value="{{ $viewMode }}">
      @if ($catFilter)<input type="hidden" name="cat" value="{{ $catFilter }}">@endif
      <div class="filter-bar">
        <div class="search-input-wrap">
          <i data-feather="search"></i>
          <input type="text" name="q" placeholder="Search name, brand, model, serial…" value="{{ $search }}">
        </div>
        <select name="status" class="form-control" style="width:auto">
          <option value="">All Status</option>
          <option value="available" {{ $statusFilter === 'available' ? 'selected' : '' }}>Available</option>
          <option value="booked" {{ $statusFilter === 'booked' ? 'selected' : '' }}>Booked</option>
          <option value="rented" {{ $statusFilter === 'rented' ? 'selected' : '' }}>In Use</option>
          <option value="under_repair" {{ $statusFilter === 'under_repair' ? 'selected' : '' }}>Under Repair</option>
        </select>
        <button type="submit" class="btn btn-outline btn-sm"><i data-feather="filter"></i> Filter</button>
      </div>
    </form>
  </div>

  @if ($equipment->isEmpty())
  <div class="empty-state">
    <i data-feather="camera"></i>
    <h3>No equipment found</h3>
    @if ($canManage)
    <p>Add your first equipment item to get started.</p>
    <button onclick="openModal('modalAddEquip')" class="btn btn-primary"><i data-feather="plus"></i> Add Equipment</button>
    @else
    <p>No equipment matches your search.</p>
    @endif
  </div>

  @elseif ($viewMode === 'grid')
  <!-- GRID VIEW -->
  <div style="padding:18px">
    <div class="equip-grid">
      @foreach ($equipment as $eq)
      <div class="equip-card">
        <div class="equip-card-img">
          @if ($eq->image_path)
          <img src="{{ asset('storage/' . $eq->image_path) }}" alt="{{ $eq->equipment_name }}">
          @else
          <span class="eq-cat-icon">{{ strtoupper(substr($eq->category_name ?? '', 0, 2)) }}</span>
          @endif
        </div>
        <div class="equip-card-body">
          <div class="equip-card-name">{{ $eq->equipment_name }}</div>
          <div class="equip-card-cat">{{ $eq->category_name }} {{ $eq->brand ? '· ' . $eq->brand : '' }}</div>
          <div class="equip-card-foot">
            <span class="equip-card-rate">₱{{ number_format($eq->daily_rate, 2) }}<span style="font-size:.7rem;color:var(--muted);font-family:var(--font-body)">/day</span></span>
            @if (! empty($eq->allocated_to) && $eq->availability_status === 'available')
            <span class="badge badge-orange" title="Allocated to {{ $eq->allocated_to }}">Allocated</span>
            @else
            <span class="badge {{ $availBadge[$eq->availability_status] ?? 'badge-gray' }}">{{ $availLabel[$eq->availability_status] ?? '—' }}</span>
            @endif
          </div>
          @if ($canManage)
          <div class="equip-card-acts" style="margin-top:10px">
            <div style="display:flex;gap:5px;flex-wrap:wrap;">
              <button class="btn btn-outline btn-sm" onclick='editEquip(@json($eq))'>
                <i data-feather="edit-2"></i> Edit
              </button>
              @if ($eq->availability_status === 'booked')
              <button class="btn btn-success btn-sm" onclick="checkoutEquip({{ $eq->equipment_id }}, '{{ addslashes($eq->equipment_name) }}')">
                <i data-feather="log-out"></i> Out
              </button>
              @endif
              @if ($eq->availability_status === 'rented')
              <button class="btn btn-warning btn-sm" onclick="checkinEquip({{ $eq->equipment_id }}, '{{ addslashes($eq->equipment_name) }}')">
                <i data-feather="log-in"></i> In
              </button>
              @endif
              <form method="POST" action="{{ $equipBase }}" style="display:inline" onsubmit="return confirm('Retire this item?')">
                @csrf
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="equipment_id" value="{{ $eq->equipment_id }}">
                <button type="submit" class="btn btn-danger btn-sm"><i data-feather="archive"></i></button>
              </form>
            </div>
          </div>
          @endif
        </div>
      </div>
      @endforeach
    </div>
  </div>

  @else
  <!-- TABLE VIEW -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th><th>Equipment</th><th>Category</th><th>Brand / Model</th>
          <th>Serial</th><th>Rate/Day</th><th>Qty</th><th>Condition</th><th>Status</th>
          @if ($canManage)<th style="text-align:right">Actions</th>@endif
        </tr>
      </thead>
      <tbody>
        @foreach ($equipment as $i => $eq)
        <tr>
          <td style="color:var(--muted);font-size:.75rem;font-family:var(--font-mono)">{{ $offset + $i + 1 }}</td>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              @if ($eq->image_path)
              <img src="{{ asset('storage/' . $eq->image_path) }}"
                   style="width:36px;height:36px;border-radius:6px;object-fit:cover;flex-shrink:0;border:1px solid var(--border)"
                   alt="">
              @else
              <div style="width:36px;height:36px;border-radius:6px;background:var(--s2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--accent);letter-spacing:.5px;flex-shrink:0">
                {{ strtoupper(substr($eq->category_name ?? '', 0, 2)) }}
              </div>
              @endif
              <div>
                <div style="font-weight:600;font-size:.875rem">{{ $eq->equipment_name }}</div>
                @if ($eq->description)
                <div style="font-size:.72rem;color:var(--muted)">{{ substr($eq->description, 0, 50) }}…</div>
                @endif
              </div>
            </div>
          </td>
          <td><span class="badge badge-blue" style="background:rgba(96,165,250,.08)">{{ $eq->category_name }}</span></td>
          <td style="color:var(--muted);font-size:.83rem">{{ trim($eq->brand . ' ' . $eq->model) ?: '—' }}</td>
          <td style="font-family:var(--font-mono);font-size:.75rem;color:var(--muted)">{{ $eq->serial_number ?? '—' }}</td>
          <td style="font-family:var(--font-mono);font-size:.85rem;color:var(--accent);font-weight:600">₱{{ number_format($eq->daily_rate, 2) }}</td>
          <td style="font-family:var(--font-mono);font-size:.83rem;text-align:center">{{ (int) ($eq->stock_quantity ?? 1) }}</td>
          <td><span class="badge {{ $condBadge[$eq->condition_status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_', ' ', $eq->condition_status)) }}</span></td>
          <td>
            @if (! empty($eq->allocated_to) && $eq->availability_status === 'available')
            <span class="status-dot dot-yellow"></span>
            <span class="badge badge-orange" title="Allocated to {{ $eq->allocated_to }}">Allocated · {{ $eq->allocated_to }}</span>
            @else
            <span class="status-dot dot-{{ ['available' => 'green', 'booked' => 'red', 'rented' => 'yellow', 'under_repair' => 'red'][$eq->availability_status] ?? 'gray' }}"></span>
            <span class="badge {{ $availBadge[$eq->availability_status] ?? 'badge-gray' }}">{{ $availLabel[$eq->availability_status] ?? '—' }}</span>
            @endif
          </td>
          @if ($canManage)
          <td style="text-align:right">
            <div style="display:flex;gap:5px;justify-content:flex-end;flex-wrap:wrap;">
              <button class="btn btn-outline btn-sm" onclick='editEquip(@json($eq))'>
                <i data-feather="edit-2"></i>
              </button>
              <button class="btn btn-outline btn-sm" title="Accessories"
                      onclick="openAccessories({{ $eq->equipment_id }}, '{{ addslashes($eq->equipment_name) }}')">
                <i data-feather="package"></i>
              </button>
              @if ($eq->availability_status === 'booked')
              <button class="btn btn-success btn-sm" onclick="checkoutEquip({{ $eq->equipment_id }}, '{{ addslashes($eq->equipment_name) }}')">
                <i data-feather="log-out"></i> Out
              </button>
              @endif
              @if ($eq->availability_status === 'rented')
              <button class="btn btn-warning btn-sm" onclick="checkinEquip({{ $eq->equipment_id }}, '{{ addslashes($eq->equipment_name) }}')">
                <i data-feather="log-in"></i> In
              </button>
              @endif
              <form method="POST" action="{{ $equipBase }}" style="display:inline" onsubmit="return confirm('Retire this item?')">
                @csrf
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="equipment_id" value="{{ $eq->equipment_id }}">
                <button type="submit" class="btn btn-danger btn-sm"><i data-feather="archive"></i></button>
              </form>
            </div>
          </td>
          @endif
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif

  @if ($pages > 1)
  <div style="padding:14px 18px;border-top:1px solid var(--border)">
    <div class="pagination">
      @for ($pi = 1; $pi <= $pages; $pi++)
      <a href="{{ $equipBase }}?p={{ $pi }}&view={{ $viewMode }}&cat={{ $catFilter }}&status={{ $statusFilter }}&q={{ urlencode($search) }}"
         class="page-btn {{ $pi == $page ? 'active' : '' }}">{{ $pi }}</a>
      @endfor
    </div>
  </div>
  @endif
</div>

@if ($canManage)

<!-- ADD EQUIPMENT MODAL -->
<div class="modal-overlay" id="modalAddEquip">
  <div class="modal" style="max-width:640px">
    <div class="modal-header">
      <div class="modal-title"><i data-feather="plus-circle"></i> Add Equipment</div>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="POST" action="{{ $equipBase }}" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group">
          <label>Equipment Photo</label>
          <div class="img-upload-zone" id="addImgZone">
            <input type="file" name="image" accept="image/*">
            <div class="img-upload-icon"><i data-feather="image"></i></div>
            <div style="font-size:13px;color:var(--muted)">Click to upload image</div>
            <div class="img-upload-label">JPG, PNG or WebP · Max 5MB</div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Category *</label>
            <select name="category_id" class="form-control" required>
              <option value="">— Select —</option>
              @foreach ($categories as $cat)
              <option value="{{ $cat->category_id }}">{{ $cat->category_name }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group">
            <label>Equipment Name *</label>
            <input type="text" name="equipment_name" class="form-control" placeholder="e.g. Sony FX9 Full Package" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Brand</label><input type="text" name="brand" class="form-control" placeholder="Sony, ARRI, Aputure…"></div>
          <div class="form-group"><label>Model</label><input type="text" name="model" class="form-control" placeholder="FX9, ALEXA Mini…"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Serial Number</label><input type="text" name="serial_number" class="form-control"></div>
          <div class="form-group"><label>Daily Rate (₱) *</label><input type="number" name="daily_rate" class="form-control" step="0.01" min="0" placeholder="0.00" required></div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Stock Quantity *</label>
            <input type="number" name="stock_quantity" class="form-control" min="1" value="1" required placeholder="How many units owned">
          </div>
          <div class="form-group"><label>Date Acquired</label><input type="date" name="date_acquired" class="form-control"></div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Condition</label>
            <select name="condition_status" class="form-control">
              <option value="excellent">Excellent</option>
              <option value="good" selected>Good</option>
              <option value="fair">Fair</option>
            </select>
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end">
            <div style="font-size:.75rem;color:var(--muted);background:var(--s2);border:1px solid var(--border);border-radius:6px;padding:8px 10px;line-height:1.5;width:100%">
              <i data-feather="info" style="width:12px;height:12px;margin-right:4px;vertical-align:middle"></i>
              Accessories can be added after saving the equipment.
            </div>
          </div>
        </div>
        <div class="form-group"><label>Description / Specs</label><textarea name="description" class="form-control" rows="2" placeholder="Specs, included items…"></textarea></div>
        <div class="form-group"><label>Internal Notes</label><textarea name="notes" class="form-control" rows="2" placeholder="Storage location, reminders…"></textarea></div>
        <input type="hidden" name="requires_operator" value="1">
        <div class="form-group" id="add_op_positions">
          <label>Allowed Operator Positions <span style="color:var(--muted);font-weight:400">(who can operate this) *</span></label>
          <div style="background:var(--s2);border:1px solid var(--border2);border-radius:7px;padding:10px;display:grid;grid-template-columns:1fr 1fr;gap:6px">
            @foreach ($positions as $pos)
            <label style="display:flex;align-items:center;gap:7px;font-size:12px;color:var(--text);cursor:pointer;text-transform:none;letter-spacing:0">
              <input type="checkbox" name="operator_positions[]" value="{{ $pos->position_id }}" style="width:auto">
              {{ $pos->position_name }}
            </label>
            @endforeach
          </div>
        </div>
        <div class="form-group" id="add_op_note_wrap">
          <label>Operator Note *</label>
          <input type="text" name="operator_note" class="form-control" placeholder="e.g. Requires licensed camera operator" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Save Equipment</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT EQUIPMENT MODAL -->
<div class="modal-overlay" id="modalEditEquip">
  <div class="modal" style="max-width:640px">
    <div class="modal-header">
      <div class="modal-title"><i data-feather="edit-2"></i> Edit Equipment</div>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="POST" action="{{ $equipBase }}" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="equipment_id" id="edit_eid">
      <div class="modal-body">
        <div class="form-group">
          <label>Equipment Photo</label>
          <div class="img-upload-zone" id="editImgZone">
            <input type="file" name="image" accept="image/*">
            <div id="editImgPreview" style="display:none">
              <img id="editImgCurrent" src="" style="width:100%;height:160px;object-fit:cover;display:block;border-radius:10px" alt="">
            </div>
            <div id="editImgPlaceholder">
              <div class="img-upload-icon"><i data-feather="image"></i></div>
              <div style="font-size:13px;color:var(--muted)">Click to change image</div>
            </div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Category *</label>
            <select name="category_id" id="edit_cat" class="form-control" required>
              @foreach ($categories as $cat)
              <option value="{{ $cat->category_id }}">{{ $cat->category_name }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group"><label>Equipment Name *</label><input type="text" name="equipment_name" id="edit_ename" class="form-control" required></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Brand</label><input type="text" name="brand" id="edit_ebrand" class="form-control"></div>
          <div class="form-group"><label>Model</label><input type="text" name="model" id="edit_emodel" class="form-control"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Serial Number</label><input type="text" name="serial_number" id="edit_eserial" class="form-control"></div>
          <div class="form-group"><label>Daily Rate (₱) *</label><input type="number" name="daily_rate" id="edit_erate" class="form-control" step="0.01" min="0" required></div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Stock Quantity *</label>
            <input type="number" name="stock_quantity" id="edit_estock" class="form-control" min="1" required>
          </div>
          <div class="form-group">
            <label>Condition</label>
            <select name="condition_status" id="edit_econd" class="form-control">
              <option value="excellent">Excellent</option>
              <option value="good">Good</option>
              <option value="fair">Fair</option>
              <option value="under_repair">Under Repair</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Availability</label>
            <select name="availability_status" id="edit_eavail" class="form-control">
              <option value="available">Available</option>
              <option value="rented">In Use</option>
              <option value="under_repair">Under Repair</option>
            </select>
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end">
            <div style="font-size:.75rem;color:var(--muted);background:var(--s2);border:1px solid var(--border);border-radius:6px;padding:8px 10px;line-height:1.5;width:100%">
              <i data-feather="package" style="width:12px;height:12px;margin-right:4px;vertical-align:middle"></i>
              Use the <strong>Accessories</strong> button in the table to manage accessories for this equipment.
            </div>
          </div>
        </div>
        <div class="form-group"><label>Description</label><textarea name="description" id="edit_edesc" class="form-control" rows="2"></textarea></div>
        <div class="form-group"><label>Internal Notes</label><textarea name="notes" id="edit_enotes" class="form-control" rows="2"></textarea></div>
        <input type="hidden" name="requires_operator" value="1">
        <div class="form-group" id="edit_op_positions">
          <label>Allowed Operator Positions <span style="color:var(--muted);font-weight:400">(who can operate this) *</span></label>
          <div style="background:var(--s2);border:1px solid var(--border2);border-radius:7px;padding:10px;display:grid;grid-template-columns:1fr 1fr;gap:6px" id="edit_op_chk">
            @foreach ($positions as $pos)
            <label style="display:flex;align-items:center;gap:7px;font-size:12px;color:var(--text);cursor:pointer;text-transform:none;letter-spacing:0">
              <input type="checkbox" name="operator_positions[]" value="{{ $pos->position_id }}" class="op-pos-chk" style="width:auto">
              {{ $pos->position_name }}
            </label>
            @endforeach
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Update Equipment</button>
      </div>
    </form>
  </div>
</div>

<!-- ADD CATEGORY -->
<div class="modal-overlay" id="modalAddCat">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <div class="modal-title"><i data-feather="tag"></i> Add Category</div>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="POST" action="{{ $equipBase }}">
      @csrf
      <input type="hidden" name="action" value="add_category">
      <div class="modal-body">
        <div class="form-group"><label>Category Name *</label><input type="text" name="category_name" class="form-control" placeholder="e.g. Drone, Generator" required></div>
        <div class="form-group"><label>Description</label><input type="text" name="category_desc" class="form-control" placeholder="Brief description"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Add</button>
      </div>
    </form>
  </div>
</div>

<!-- ACCESSORIES MODAL -->
<style>
.acc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:10px;margin-bottom:16px}
.acc-card{background:var(--s2);border:1px solid var(--border);border-radius:9px;overflow:hidden;transition:box-shadow .15s}
.acc-card:hover{box-shadow:var(--shadow-md)}
.acc-thumb{width:100%;aspect-ratio:4/3;background:var(--s3);display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative}
.acc-thumb img{width:100%;height:100%;object-fit:cover}
.acc-thumb-icon{font-family:var(--font-display);font-size:22px;color:var(--accent);opacity:.4;letter-spacing:1px}
.acc-pill{position:absolute;top:6px;left:6px;font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;padding:2px 7px;border-radius:10px;backdrop-filter:blur(4px)}
.acc-pill.included{background:rgba(21,128,61,.82);color:#fff}
.acc-pill.addon{background:rgba(0,96,199,.82);color:#fff}
.acc-info{padding:8px 9px}
.acc-iname{font-weight:700;font-size:.8rem;color:var(--text);line-height:1.3;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.acc-idesc{font-size:.68rem;color:var(--muted);margin-bottom:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.acc-ifoot{display:flex;align-items:center;justify-content:space-between}
.acc-irate{font-size:.75rem;font-weight:700;color:var(--accent);font-family:var(--font-mono)}
.acc-del{background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.18);color:var(--red);border-radius:5px;padding:2px 8px;cursor:pointer;font-size:.68rem;font-weight:700;transition:background .12s}
.acc-del:hover{background:rgba(220,38,38,.16)}
.acc-img-zone{border:1.5px dashed var(--border2);border-radius:7px;padding:8px 12px;cursor:pointer;text-align:center;background:var(--s2);transition:border-color .15s;position:relative}
.acc-img-zone:hover{border-color:var(--accent)}
.acc-img-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.acc-img-preview{width:100%;height:80px;object-fit:cover;border-radius:5px;display:none}
</style>
<div class="modal-overlay" id="modalAccessories">
  <div class="modal" style="max-width:620px">
    <div class="modal-header">
      <div class="modal-title"><i data-feather="package"></i> Accessories — <span id="accEquipName" style="color:var(--accent)"></span></div>
      <div style="display:flex;align-items:center;gap:10px">
        <a href="{{ route('accessories') }}" style="font-size:.72rem;color:var(--accent);text-decoration:none;opacity:.8">Manage all →</a>
        <button class="modal-close" data-modal-close>&times;</button>
      </div>
    </div>
    <div class="modal-body">
      <div id="accList" style="margin-bottom:4px;min-height:40px"></div>
      <div style="border-top:1px solid var(--border);padding-top:12px;text-align:center">
        <a id="accAddMoreLink" href="{{ route('accessories') }}" class="btn btn-outline btn-sm">
          <i data-feather="plus"></i> Add / Manage Accessories
        </a>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" data-modal-close>Close</button>
    </div>
  </div>
</div>
@endif

@push('scripts')
<script>
const EQUIP_ASSET_BASE = "{{ asset('storage') }}";
const EQUIP_BASE_URL = "{{ $equipBase }}";

function editEquip(eq) {
  document.getElementById('edit_eid').value     = eq.equipment_id;
  document.getElementById('edit_cat').value     = eq.category_id;
  document.getElementById('edit_ename').value   = eq.equipment_name;
  document.getElementById('edit_ebrand').value  = eq.brand  || '';
  document.getElementById('edit_emodel').value  = eq.model  || '';
  document.getElementById('edit_eserial').value = eq.serial_number || '';
  document.getElementById('edit_erate').value   = eq.daily_rate;
  document.getElementById('edit_estock').value  = eq.stock_quantity || 1;
  document.getElementById('edit_econd').value   = eq.condition_status;
  document.getElementById('edit_eavail').value  = eq.availability_status;
  document.getElementById('edit_edesc').value   = eq.description || '';
  document.getElementById('edit_enotes').value  = eq.notes || '';

  fetch(EQUIP_BASE_URL + '?get_operators=' + eq.equipment_id)
    .then(r => r.json())
    .then(data => {
      document.querySelectorAll('.op-pos-chk').forEach(cb => {
        cb.checked = data.includes(parseInt(cb.value));
      });
    });

  var preview = document.getElementById('editImgPreview');
  var ph      = document.getElementById('editImgPlaceholder');
  var img     = document.getElementById('editImgCurrent');
  if (eq.image_path && preview) {
    img.src = EQUIP_ASSET_BASE + '/' + eq.image_path;
    preview.style.display = 'block';
    if (ph) ph.style.display = 'none';
  } else if (preview) {
    preview.style.display = 'none';
    if (ph) ph.style.display = 'block';
  }
  openModal('modalEditEquip');
}

function checkoutEquip(eid, equipName) {
  if (confirm('Check out ' + equipName + '?\n\nThis will mark the equipment as "In Field" and create a checkout transaction.')) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = EQUIP_BASE_URL;
    form.innerHTML = '@csrf'
                   + '<input type="hidden" name="action" value="checkout">'
                   + '<input type="hidden" name="equipment_id" value="' + eid + '">';
    document.body.appendChild(form);
    form.submit();
  }
}

function checkinEquip(eid, equipName) {
  var condition = prompt(
    'Check in ' + equipName + '?\n\nEnter equipment condition:\n• excellent\n• good\n• fair\n• damaged\n• missing',
    'good'
  );
  if (condition === null) return;
  var valid = ['excellent', 'good', 'fair', 'damaged', 'missing'];
  if (!valid.includes(condition.toLowerCase())) {
    alert('Invalid condition. Please use: excellent, good, fair, damaged, or missing');
    return;
  }
  var form = document.createElement('form');
  form.method = 'POST';
  form.action = EQUIP_BASE_URL;
  form.innerHTML = '@csrf'
                 + '<input type="hidden" name="action" value="checkin">'
                 + '<input type="hidden" name="equipment_id" value="' + eid + '">'
                 + '<input type="hidden" name="condition_in" value="' + condition + '">';
  document.body.appendChild(form);
  form.submit();
}

@if ($canManage)
let currentAccEquipId = null;

function openAccessories(eid, ename) {
  currentAccEquipId = eid;
  document.getElementById('accEquipName').textContent = ename;
  document.getElementById('accAddMoreLink').href = "{{ route('accessories') }}?equipment_id=" + eid;
  loadAccessories();
  openModal('modalAccessories');
}

function loadAccessories() {
  const list = document.getElementById('accList');
  list.innerHTML = '<div style="color:var(--muted);font-size:.83rem;padding:8px 0">Loading…</div>';
  fetch(EQUIP_BASE_URL + '?get_accessories=' + currentAccEquipId)
    .then(r => r.json())
    .then(data => {
      if (!data.length) {
        list.innerHTML = '<div style="color:var(--muted);font-size:.83rem;text-align:center;padding:18px 0;background:var(--s2);border-radius:8px;border:1px dashed var(--border)">No accessories added yet.</div>';
        return;
      }
      list.innerHTML = '<div class="acc-grid">' + data.map(a => {
        const rate     = parseFloat(a.daily_rate);
        const rateStr  = rate > 0 ? '₱' + rate.toLocaleString('en-PH',{minimumFractionDigits:2}) + '/day' : 'Included';
        const inclCls  = a.is_included ? 'included' : 'addon';
        const inclLbl  = a.is_included ? 'Package'  : 'Add-on';
        const imgHtml  = a.image_path
          ? `<img src="${EQUIP_ASSET_BASE}/${escHtml(a.image_path)}" alt="" style="width:100%;height:100%;object-fit:cover">`
          : `<span class="acc-thumb-icon">${escHtml((a.accessory_name||'').substring(0,2).toUpperCase())}</span>`;
        return `<div class="acc-card">
          <div class="acc-thumb">
            ${imgHtml}
            <span class="acc-pill ${inclCls}">${inclLbl}</span>
          </div>
          <div class="acc-info">
            <div class="acc-iname" title="${escHtml(a.accessory_name)}">${escHtml(a.accessory_name)}</div>
            <div class="acc-idesc">${escHtml(a.description||'—')}</div>
            <div class="acc-ifoot">
              <span class="acc-irate">${rateStr}</span>
              <button class="acc-del" onclick="deleteAccessory(${a.accessory_id})" title="Unlink from this equipment">Unlink</button>
            </div>
          </div>
        </div>`;
      }).join('') + '</div>';
      if (window.feather) feather.replace();
    });
}

function deleteAccessory(aid) {
  if (!confirm('Unlink this accessory from this equipment?\n\nThe accessory will remain in the global Accessories catalog.')) return;
  const fd = new FormData();
  fd.append('ajax_action',  'delete_accessory');
  fd.append('accessory_id', aid);
  fd.append('equipment_id', currentAccEquipId);
  fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);

  fetch(EQUIP_BASE_URL, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => { if (data.success) loadAccessories(); });
}
@endif

function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('DOMContentLoaded', function() {
  ['modalAddEquip', 'modalEditEquip'].forEach(function(modalId) {
    var form = document.querySelector('#' + modalId + ' form');
    if (!form) return;
    form.addEventListener('submit', function(e) {
      var boxes = form.querySelectorAll('input[name="operator_positions[]"]');
      if (boxes.length && !Array.from(boxes).some(function(cb) { return cb.checked; })) {
        e.preventDefault();
        alert('Please select at least one Allowed Operator Position.');
      }
    });
  });
});
</script>
@endpush
@endsection
