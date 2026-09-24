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
    <div class="stat-label">Under Maintenance</div>
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
          <option value="booked" {{ $statusFilter === 'booked' ? 'selected' : '' }}>Allocated</option>
          <option value="rented" {{ $statusFilter === 'rented' ? 'selected' : '' }}>In Field</option>
          <option value="under_repair" {{ $statusFilter === 'under_repair' ? 'selected' : '' }}>Under Maintenance</option>
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
            <div style="display:flex;gap:6px;align-items:center">
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
              <div class="action-menu-wrap">
                <button type="button" class="btn-icon" onclick="toggleActionMenu(this)" title="More actions">
                  <i data-feather="more-vertical"></i>
                </button>
                <div class="action-menu align-right">
                  <button type="button" onclick="closeActionMenus(); openAccessories({{ $eq->equipment_id }}, '{{ addslashes($eq->equipment_name) }}')">
                    <i data-feather="package"></i> Accessories
                  </button>
                  <button type="button" onclick="closeActionMenus(); openUnits({{ $eq->equipment_id }}, {{ json_encode($eq->equipment_name) }})">
                    <i data-feather="hash"></i> Physical Units <span class="badge badge-gray" style="margin-left:2px">{{ $eq->unit_count }}</span>
                  </button>
                  <div class="action-menu-divider"></div>
                  <form method="POST" action="{{ $equipBase }}" onsubmit="return confirm('Deactivate / retire this equipment model? It will no longer appear as available for new bookings.')">
                    @csrf
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="equipment_id" value="{{ $eq->equipment_id }}">
                    <button type="submit" class="text-danger"><i data-feather="archive"></i> Deactivate / Retire</button>
                  </form>
                </div>
              </div>
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
          <td><span class="badge {{ $condBadge[$eq->condition_status] ?? 'badge-gray' }}">{{ $condLabel[$eq->condition_status] ?? ucfirst(str_replace('_', ' ', $eq->condition_status)) }}</span></td>
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
            <div style="display:flex;gap:6px;justify-content:flex-end;align-items:center">
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
              <div class="action-menu-wrap">
                <button type="button" class="btn-icon" onclick="toggleActionMenu(this)" title="More actions">
                  <i data-feather="more-vertical"></i>
                </button>
                <div class="action-menu align-right">
                  <button type="button" onclick="closeActionMenus(); openAccessories({{ $eq->equipment_id }}, '{{ addslashes($eq->equipment_name) }}')">
                    <i data-feather="package"></i> Accessories
                  </button>
                  <button type="button" onclick="closeActionMenus(); openUnits({{ $eq->equipment_id }}, {{ json_encode($eq->equipment_name) }})">
                    <i data-feather="hash"></i> Physical Units <span class="badge badge-gray" style="margin-left:2px">{{ $eq->unit_count }}</span>
                  </button>
                  <div class="action-menu-divider"></div>
                  <form method="POST" action="{{ $equipBase }}" onsubmit="return confirm('Deactivate / retire this equipment model? It will no longer appear as available for new bookings.')">
                    @csrf
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="equipment_id" value="{{ $eq->equipment_id }}">
                    <button type="submit" class="text-danger"><i data-feather="archive"></i> Deactivate / Retire</button>
                  </form>
                </div>
              </div>
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
        <div class="form-group"><label>Daily Rate (₱) *</label><input type="number" name="daily_rate" class="form-control" step="0.01" min="0" placeholder="0.00" required></div>
        <div class="form-group" style="font-size:.75rem;color:var(--muted);background:var(--s2);border:1px solid var(--border);border-radius:6px;padding:8px 10px;line-height:1.5">
          <i data-feather="info" style="width:12px;height:12px;margin-right:4px;vertical-align:middle"></i>
          Serial Number, Condition, Stock Quantity, and Date Acquired are tracked per <strong>Physical Unit</strong> — add units (and accessories) from the equipment's Physical Units panel after saving.
        </div>
        <div class="form-group"><label>Description / Specs</label><textarea name="description" class="form-control" rows="2" placeholder="Specs, included items…"></textarea></div>
        <div class="form-group"><label>Internal Notes</label><textarea name="notes" class="form-control" rows="2" placeholder="Storage location, reminders…"></textarea></div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="hidden" name="requires_operator" value="0">
            <input type="checkbox" name="requires_operator" value="1" id="add_requires_op" checked onchange="toggleOperatorSection('add')" style="width:auto">
            Requires a qualified operator
          </label>
        </div>
        <div id="add_operator_fields">
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
        <div class="form-group"><label>Daily Rate (₱) *</label><input type="number" name="daily_rate" id="edit_erate" class="form-control" step="0.01" min="0" required></div>
        <div id="edit_derived_note" style="display:none;font-size:.75rem;color:var(--muted);background:var(--s2);border:1px solid var(--border);border-radius:6px;padding:8px 10px;line-height:1.5;margin-bottom:14px">
          <i data-feather="hash" style="width:12px;height:12px;margin-right:4px;vertical-align:middle"></i>
          <span id="edit_derived_text"></span> — Stock Quantity and Condition are derived from its Physical Units. Manage them from the <strong>Physical Units</strong> button in the table.
        </div>
        <div class="form-row" id="edit_manual_stock_cond">
          <div class="form-group">
            <label>Stock Quantity *</label>
            <input type="number" name="stock_quantity" id="edit_estock" class="form-control" min="1">
          </div>
          <div class="form-group">
            <label>Condition</label>
            <select name="condition_status" id="edit_econd" class="form-control">
              <option value="excellent">Excellent</option>
              <option value="good">Good</option>
              <option value="fair">Serviceable</option>
              <option value="under_repair">Damaged</option>
            </select>
          </div>
        </div>
        <div class="form-row" id="edit_manual_avail">
          <div class="form-group">
            <label>Availability</label>
            <select name="availability_status" id="edit_eavail" class="form-control">
              <option value="available">Available</option>
              <option value="booked">Allocated</option>
              <option value="rented">In Field</option>
              <option value="under_repair">Under Maintenance</option>
              <option value="retired">Retired</option>
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
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="hidden" name="requires_operator" value="0">
            <input type="checkbox" name="requires_operator" value="1" id="edit_requires_op" onchange="toggleOperatorSection('edit')" style="width:auto">
            Requires a qualified operator
          </label>
        </div>
        <div id="edit_operator_fields">
          <div class="form-group"><label>Operator Note *</label><input type="text" name="operator_note" id="edit_eopnote" class="form-control" placeholder="e.g. Requires licensed camera operator"></div>
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

<!-- PHYSICAL UNITS MODAL -->
<div class="modal-overlay" id="modalUnits">
  <div class="modal" style="max-width:780px">
    <div class="modal-header">
      <div class="modal-title"><i data-feather="hash"></i> Physical Units — <span id="unitsEquipName" style="color:var(--accent)"></span></div>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <div class="table-wrap" style="margin-bottom:14px">
        <table>
          <thead><tr><th>Asset Tag</th><th>Serial No.</th><th>Condition</th><th>Status</th><th>Location</th><th>Acquired</th><th>Actions</th></tr></thead>
          <tbody id="unitsTbody"></tbody>
        </table>
      </div>
      <div id="unitsEmpty" style="display:none;text-align:center;color:var(--muted);padding:16px;font-size:.85rem">No physical units recorded yet.</div>

      <div style="border-top:1px solid var(--border);padding-top:14px">
        <div style="font-size:.78rem;font-weight:700;color:var(--text);margin-bottom:8px">Add Unit</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
          <div class="form-group" style="margin-bottom:0;flex:1;min-width:120px">
            <label style="font-size:.72rem">Asset Tag *</label>
            <input type="text" id="unitAssetTag" class="form-control" placeholder="e.g. CAM-003">
          </div>
          <div class="form-group" style="margin-bottom:0;flex:1;min-width:120px">
            <label style="font-size:.72rem">Serial No.</label>
            <input type="text" id="unitSerialNo" class="form-control">
          </div>
          <div class="form-group" style="margin-bottom:0;min-width:130px">
            <label style="font-size:.72rem">Condition</label>
            <select id="unitCondition" class="form-control">
              @foreach ($unitCondLabel as $ck => $cl)
              <option value="{{ $ck }}" {{ $ck === 'good' ? 'selected' : '' }}>{{ $cl }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0;min-width:150px">
            <label style="font-size:.72rem">Status</label>
            <select id="unitStatus" class="form-control">
              @foreach ($unitStatusLabel as $sk => $sl)
              <option value="{{ $sk }}" {{ $sk === 'available' ? 'selected' : '' }}>{{ $sl }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0;flex:1;min-width:120px">
            <label style="font-size:.72rem">Location</label>
            <input type="text" id="unitLocation" class="form-control" placeholder="e.g. Camera Room A">
          </div>
          <div class="form-group" style="margin-bottom:0;min-width:140px">
            <label style="font-size:.72rem">Date Acquired</label>
            <input type="date" id="unitDateAcquired" class="form-control">
          </div>
          <button type="button" class="btn btn-primary btn-sm" onclick="addUnit()"><i data-feather="plus" style="width:13px;height:13px"></i> Add Unit</button>
        </div>
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

function closeActionMenus() {
  document.querySelectorAll('.action-menu.show').forEach(function (m) {
    m.classList.remove('show', 'drop-up');
  });
}
function toggleActionMenu(trigger) {
  var menu = trigger.nextElementSibling;
  var wasOpen = menu.classList.contains('show');
  closeActionMenus();
  if (wasOpen) return;
  menu.classList.add('show');
  var rect = menu.getBoundingClientRect();
  if (rect.bottom > window.innerHeight) menu.classList.add('drop-up');
}
document.addEventListener('click', function (e) {
  if (!e.target.closest('.action-menu-wrap')) closeActionMenus();
});
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') closeActionMenus();
});

function editEquip(eq) {
  document.getElementById('edit_eid').value     = eq.equipment_id;
  document.getElementById('edit_cat').value     = eq.category_id;
  document.getElementById('edit_ename').value   = eq.equipment_name;
  document.getElementById('edit_ebrand').value  = eq.brand  || '';
  document.getElementById('edit_emodel').value  = eq.model  || '';
  document.getElementById('edit_erate').value   = eq.daily_rate;
  document.getElementById('edit_estock').value  = eq.stock_quantity || 1;
  document.getElementById('edit_econd').value   = eq.condition_status;
  document.getElementById('edit_eavail').value  = eq.availability_status;
  document.getElementById('edit_edesc').value   = eq.description || '';
  document.getElementById('edit_enotes').value  = eq.notes || '';
  document.getElementById('edit_eopnote').value = eq.operator_note || '';
  document.getElementById('edit_requires_op').checked = !!parseInt(eq.requires_operator);
  toggleOperatorSection('edit');

  // Once this model has real Physical Units, Stock Quantity/Condition/Availability are
  // derived — hide the manual fields and point staff at the Physical Units panel instead.
  var hasUnits = parseInt(eq.unit_count || 0) > 0;
  document.getElementById('edit_manual_stock_cond').style.display = hasUnits ? 'none' : '';
  document.getElementById('edit_manual_avail').style.display = hasUnits ? 'none' : '';
  document.getElementById('edit_derived_note').style.display = hasUnits ? '' : 'none';
  if (hasUnits) {
    document.getElementById('edit_derived_text').textContent =
      eq.unit_count + ' active physical unit' + (eq.unit_count == 1 ? '' : 's');
  }

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

function toggleOperatorSection(prefix) {
  var checked = document.getElementById(prefix + '_requires_op').checked;
  var fields = document.getElementById(prefix + '_operator_fields');
  fields.style.display = checked ? '' : 'none';
  var noteInput = document.getElementById(prefix + '_eopnote') || fields.querySelector('input[name="operator_note"]');
  if (noteInput) {
    if (checked) noteInput.setAttribute('required', 'required');
    else { noteInput.removeAttribute('required'); noteInput.value = ''; }
  }
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

const UNIT_COND_LABEL = @json($unitCondLabel);
const UNIT_STATUS_LABEL = @json($unitStatusLabel);
let currentUnitsEquipId = null;

function openUnits(eid, ename) {
  currentUnitsEquipId = eid;
  document.getElementById('unitsEquipName').textContent = ename;
  document.getElementById('unitAssetTag').value = '';
  document.getElementById('unitSerialNo').value = '';
  document.getElementById('unitLocation').value = '';
  document.getElementById('unitDateAcquired').value = '';
  loadUnits();
  openModal('modalUnits');
}

function loadUnits() {
  const tbody = document.getElementById('unitsTbody');
  const empty = document.getElementById('unitsEmpty');
  tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--muted);padding:12px">Loading…</td></tr>';
  fetch(EQUIP_BASE_URL + '?get_units=' + currentUnitsEquipId)
    .then(r => r.json())
    .then(data => {
      if (!data.length) {
        tbody.innerHTML = '';
        empty.style.display = 'block';
        return;
      }
      empty.style.display = 'none';
      tbody.innerHTML = data.map(u => {
        const condOpts = Object.keys(UNIT_COND_LABEL).map(k =>
          `<option value="${k}" ${k === u.condition ? 'selected' : ''}>${escHtml(UNIT_COND_LABEL[k])}</option>`).join('');
        const statusOpts = Object.keys(UNIT_STATUS_LABEL).map(k =>
          `<option value="${k}" ${k === u.status ? 'selected' : ''}>${escHtml(UNIT_STATUS_LABEL[k])}</option>`).join('');
        return `<tr data-unit-id="${u.unit_id}">
          <td style="font-family:monospace;font-weight:700">${escHtml(u.asset_tag)}</td>
          <td style="font-family:monospace;font-size:.8rem">${escHtml(u.serial_no || '—')}</td>
          <td><select class="form-control unit-cond-sel" style="font-size:.78rem;padding:4px 6px">${condOpts}</select></td>
          <td><select class="form-control unit-status-sel" style="font-size:.78rem;padding:4px 6px">${statusOpts}</select></td>
          <td><input type="text" class="form-control unit-loc-input" value="${escHtml(u.location || '')}" style="font-size:.78rem;padding:4px 6px" placeholder="Location"></td>
          <td style="font-size:.78rem;color:var(--muted);white-space:nowrap">${u.date_acquired ? escHtml(u.date_acquired) : '—'}</td>
          <td style="white-space:nowrap">
            <button class="btn btn-outline btn-sm" onclick="saveUnit(${u.unit_id}, this)" title="Save"><i data-feather="save" style="width:12px;height:12px"></i></button>
            <button class="btn btn-danger btn-sm" onclick="retireUnit(${u.unit_id})" title="Retire Unit"><i data-feather="archive" style="width:12px;height:12px"></i></button>
          </td>
        </tr>`;
      }).join('');
      if (window.feather) feather.replace();
    });
}

function addUnit() {
  const tag = document.getElementById('unitAssetTag').value.trim();
  if (!tag) { alert('Asset tag is required.'); return; }
  const fd = new FormData();
  fd.append('ajax_action', 'add_unit');
  fd.append('equipment_id', currentUnitsEquipId);
  fd.append('asset_tag', tag);
  fd.append('serial_no', document.getElementById('unitSerialNo').value.trim());
  fd.append('condition', document.getElementById('unitCondition').value);
  fd.append('status', document.getElementById('unitStatus').value);
  fd.append('location', document.getElementById('unitLocation').value.trim());
  fd.append('date_acquired', document.getElementById('unitDateAcquired').value);
  fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);

  fetch(EQUIP_BASE_URL, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        document.getElementById('unitAssetTag').value = '';
        document.getElementById('unitSerialNo').value = '';
        document.getElementById('unitLocation').value = '';
        document.getElementById('unitDateAcquired').value = '';
        loadUnits();
      } else {
        alert(data.error || 'Could not add unit.');
      }
    });
}

function saveUnit(unitId, btn) {
  const row = btn.closest('tr');
  const fd = new FormData();
  fd.append('ajax_action', 'update_unit');
  fd.append('unit_id', unitId);
  fd.append('condition', row.querySelector('.unit-cond-sel').value);
  fd.append('status', row.querySelector('.unit-status-sel').value);
  fd.append('location', row.querySelector('.unit-loc-input').value.trim());
  fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);

  fetch(EQUIP_BASE_URL, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => { if (!data.success) alert(data.error || 'Could not save unit.'); });
}

function retireUnit(unitId) {
  if (!confirm('Retire this physical unit? It will be marked Retired and hidden from the active unit count.')) return;
  const fd = new FormData();
  fd.append('ajax_action', 'retire_unit');
  fd.append('unit_id', unitId);
  fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);

  fetch(EQUIP_BASE_URL, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => { if (data.success) loadUnits(); });
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
      var reqOp = form.querySelector('input[type="checkbox"][name="requires_operator"]');
      if (reqOp && !reqOp.checked) return;
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
