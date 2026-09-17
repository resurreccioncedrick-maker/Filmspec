@extends('layouts.app')

@section('pageTitle', 'Repair / Purchase Tickets')

@section('breadcrumb')
<span>Repair / Purchase</span>
@endsection

@section('topbarActions')
@if ($canPost)
<button onclick="resetNewTicketForm(); openModal('modalNewTicket')" class="btn btn-primary btn-sm"><i data-feather="plus"></i> New Ticket</button>
@endif
@endsection

@section('content')
@php $rpBase = route('repair-purchase'); @endphp

@if ($msg)
<div class="alert alert-{{ $msg['type'] }}" data-autohide>
  <i data-feather="{{ $msg['type'] === 'success' ? 'check-circle' : 'alert-circle' }}"></i>
  {!! $msg['text'] !!}
</div>
@endif

<!-- KPI Row -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:22px">
  <div class="stat-card" style="--sb:#dc2626">
    <div class="stat-icon" style="background:#fee2e2;color:#dc2626"><i data-feather="alert-circle"></i></div>
    <div class="stat-value">{{ $kpis['open'] }}</div>
    <div class="stat-label">Open Tickets</div>
  </div>
  <div class="stat-card orange">
    <div class="stat-icon"><i data-feather="clock"></i></div>
    <div class="stat-value">{{ $kpis['overdue'] }}</div>
    <div class="stat-label">Overdue</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="dollar-sign"></i></div>
    <div class="stat-value">₱{{ number_format($kpis['est_outstanding']/1000,1) }}k</div>
    <div class="stat-label">Est. Cost Outstanding</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon"><i data-feather="check-circle"></i></div>
    <div class="stat-value">{{ $kpis['completed_this_month'] }}</div>
    <div class="stat-label">Completed This Month</div>
  </div>
</div>

<!-- Status tabs -->
<div class="tabs" style="margin-bottom:12px">
  @foreach (['all' => 'All', 'requested' => 'Requested', 'approved' => 'Approved', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $k => $l)
  <a href="{{ $rpBase }}?tab={{ $k }}&type={{ $typeFilter }}" class="tab-btn {{ $tab === $k ? 'active' : '' }}">
    {{ $l }} <span class="badge {{ $statusBadge[$k] ?? 'badge-gray' }}" style="margin-left:4px">{{ $tabCounts[$k] }}</span>
  </a>
  @endforeach
</div>

<!-- Type filter -->
<div class="tabs" style="margin-bottom:18px">
  @foreach (['' => 'All Types', 'repair' => 'Repair', 'purchase' => 'Purchase'] as $k => $l)
  <a href="{{ $rpBase }}?tab={{ $tab }}&type={{ $k }}" class="tab-btn {{ $typeFilter === $k ? 'active' : '' }}">{{ $l }}</a>
  @endforeach
</div>

<div class="card">
  <div class="card-header">
    <h2 class="card-title">Tickets</h2>
  </div>
  <div class="card-body" style="padding-bottom:0">
    <form method="GET">
      <input type="hidden" name="tab" value="{{ $tab }}">
      <input type="hidden" name="type" value="{{ $typeFilter }}">
      <div class="filter-bar">
        <div class="search-input-wrap">
          <i data-feather="search"></i>
          <input type="text" name="q" class="form-control" placeholder="Search ticket #, title, vendor…" value="{{ $search }}">
        </div>
        <button type="submit" class="btn btn-primary btn-sm"><i data-feather="filter"></i> Filter</button>
      </div>
    </form>
  </div>

  <div class="table-wrap">
    @if ($tickets->isEmpty())
    <div class="empty-state">
      <i data-feather="tool"></i>
      <h3>No tickets found</h3>
      <p>Create a ticket to track an equipment repair or a new purchase request.</p>
    </div>
    @else
    <table>
      <thead>
        <tr>
          <th>Ticket #</th>
          <th>Type</th>
          <th>Title</th>
          <th>Equipment</th>
          <th>Status</th>
          <th>Priority</th>
          <th>Est. / Actual Cost</th>
          <th>Target Date</th>
          @if ($canPost)<th>Actions</th>@endif
        </tr>
      </thead>
      <tbody>
      @foreach ($tickets as $t)
      @php
        $isOpen = ! in_array($t->status, ['completed', 'cancelled'], true);
        $isOverdue = $isOpen && $t->target_date && strtotime($t->target_date) < strtotime(date('Y-m-d'));
      @endphp
      <tr>
        <td><span style="font-family:monospace;font-size:.85rem;font-weight:700">{{ $t->ticket_number }}</span></td>
        <td><span class="badge {{ $typeBadge[$t->type] ?? 'badge-gray' }}">{{ $typeLabel[$t->type] ?? ucfirst($t->type) }}</span></td>
        <td>
          <div style="font-weight:600">{{ $t->title }}</div>
          @php $tMsgs = $messages[$t->ticket_id] ?? collect(); @endphp
          <div style="font-size:.72rem;color:var(--text-muted)">
            @if ($t->serial_no) SN: {{ $t->serial_no }} · @endif
            @if ($t->reported_by) reported by {{ $t->reported_by }} · @endif
            {{ $tMsgs->count() }} message{{ $tMsgs->count() === 1 ? '' : 's' }}
          </div>
        </td>
        <td>
          @if ($t->equipment_name)
          <div>{{ $t->equipment_name }}</div>
          <div style="font-size:.75rem;color:var(--muted)">{{ $t->brand }}</div>
          @else
          <span style="color:var(--muted)">—</span>
          @endif
        </td>
        <td>
          {{-- Same stored status, worded for the kind of job (Part 15). --}}
          <span class="badge {{ $statusBadge[$t->status] ?? 'badge-gray' }}">
            {{ $statusLabelByType[$t->type][$t->status] ?? ($statusLabel[$t->status] ?? ucfirst($t->status)) }}
          </span>
        </td>
        <td><span class="badge {{ $priorityBadge[$t->priority] ?? 'badge-gray' }}">{{ ucfirst($t->priority) }}</span></td>
        <td style="font-size:.83rem">
          {{ $t->estimated_cost !== null ? '₱'.number_format($t->estimated_cost,2) : '—' }}
          @if ($t->actual_cost !== null)
          <div style="color:var(--muted)">Actual: ₱{{ number_format($t->actual_cost,2) }}</div>
          @endif
        </td>
        <td style="white-space:nowrap;font-size:.83rem;{{ $isOverdue ? 'color:var(--red);font-weight:700' : '' }}">
          {{ $t->target_date ? date('M j, Y', strtotime($t->target_date)) : '—' }}
        </td>
        @if ($canPost)
        <td>
          <div style="display:flex;gap:6px;flex-wrap:wrap">
            <button onclick="openStatusModal({{ Illuminate\Support\Js::from($t)->toHtml() }})" class="btn btn-sm btn-outline" title="Update status">
              <i data-feather="check-circle"></i>
            </button>
            <button onclick="openEditTicket({{ Illuminate\Support\Js::from($t)->toHtml() }})" class="btn btn-sm btn-outline" title="Edit ticket">
              <i data-feather="edit-2"></i>
            </button>
            <button onclick="openTicketDetails({{ Illuminate\Support\Js::from($t)->toHtml() }}, {{ Illuminate\Support\Js::from($tMsgs->values())->toHtml() }})"
                    class="btn btn-sm btn-outline" title="Details &amp; messages">
              <i data-feather="message-square"></i>
            </button>
            @if ($canManage)
            <form method="POST" onsubmit="return confirm('Delete ticket {{ $t->ticket_number }}?')">
              @csrf
              <input type="hidden" name="action" value="delete_ticket">
              <input type="hidden" name="ticket_id" value="{{ $t->ticket_id }}">
              <button type="submit" class="btn btn-sm btn-outline" title="Delete"><i data-feather="trash-2"></i></button>
            </form>
            @endif
          </div>
        </td>
        @endif
      </tr>
      @endforeach
      </tbody>
    </table>

    @if ($pages > 1)
    <div style="display:flex;gap:6px;padding:14px 18px;align-items:center;flex-wrap:wrap">
      @for ($p = 1; $p <= $pages; $p++)
      <a href="?tab={{ $tab }}&type={{ $typeFilter }}&q={{ urlencode($search) }}&p={{ $p }}"
         class="btn btn-sm {{ $p === $page ? 'btn-primary' : 'btn-outline' }}">{{ $p }}</a>
      @endfor
    </div>
    @endif
    @endif
  </div>
</div>

<div style="font-size:.75rem;color:var(--text-muted);margin-top:14px">
  Tickets are shared with everyone signed in to admin. Completed tickets are kept for 2 years.
</div>

@include('partials.page-activity')
@include('partials.access-log')

@if ($canPost)
<!-- NEW TICKET MODAL -->
<div class="modal-overlay" id="modalNewTicket">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <h3><i data-feather="tool" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>New Ticket</h3>
      <button class="modal-close" onclick="closeModal('modalNewTicket')">&times;</button>
    </div>
    <form method="POST">
      @csrf
      <input type="hidden" name="action" value="add_ticket">
      <div class="modal-body">
        <div class="form-group">
          <label>Type</label>
          <select name="type" id="ticketType" class="form-control" onchange="toggleTicketType()">
            <option value="repair">Repair (existing equipment)</option>
            <option value="purchase">Purchase (new item)</option>
          </select>
        </div>
        <div class="form-group">
          <label>Title <span style="color:var(--red)">*</span></label>
          <input type="text" name="title" class="form-control" placeholder="e.g. Sony A7III — lens mount repair" required>
        </div>
        <div class="form-group" id="ticketEquipWrap">
          <label>Equipment</label>
          <div style="position:relative">
            <input type="text" id="ticketEquipSearch" class="form-control" placeholder="Search equipment…" autocomplete="off"
                   oninput="openTicketEquipSearch('ticket_equipment','ticketEquipSearch','ticketEquipDrop',this.value)"
                   onfocus="openTicketEquipSearch('ticket_equipment','ticketEquipSearch','ticketEquipDrop',this.value)"
                   onblur="closeTicketEquipSearch('ticketEquipDrop',200)">
            <input type="hidden" name="equipment_id" id="ticket_equipment">
            <div class="crew-slot-drop" id="ticketEquipDrop"></div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Priority</label>
            <select name="priority" class="form-control">
              <option value="low">Low</option>
              <option value="normal" selected>Normal</option>
              <option value="high">High</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
          <div class="form-group">
            <label>Vendor / Supplier</label>
            <input type="text" name="vendor_supplier" class="form-control">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Requested Date <span style="color:var(--red)">*</span></label>
            <input type="date" name="requested_date" class="form-control" value="{{ date('Y-m-d') }}" required>
          </div>
          <div class="form-group">
            <label>Target Date (optional)</label>
            <input type="date" name="target_date" class="form-control">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Estimated Cost (₱)</label>
            <input type="number" name="estimated_cost" class="form-control" step="0.01" min="0">
          </div>
          <div class="form-group">
            <label>Person In Charge</label>
            <input type="text" name="person_in_charge" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label>Note</label>
          <textarea name="note" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalNewTicket')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="plus"></i> Create Ticket</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT TICKET MODAL (Part 15) -->
<div class="modal-overlay" id="modalEditTicket">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <h3><i data-feather="edit-2" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Edit Ticket</h3>
      <button class="modal-close" onclick="closeModal('modalEditTicket')">&times;</button>
    </div>
    <form method="POST">
      @csrf
      <input type="hidden" name="action" value="update_ticket">
      <input type="hidden" name="ticket_id" id="et_ticket_id">
      <div class="modal-body">
        <div id="et_context" style="font-size:.85rem;color:var(--text-muted);margin-bottom:12px"></div>
        <div class="form-row">
          <div class="form-group">
            <label>Type</label>
            <select name="type" id="et_type" class="form-control" onchange="toggleEditTicketType()">
              <option value="repair">Repair (existing equipment)</option>
              <option value="purchase">Purchase (new item)</option>
            </select>
          </div>
          <div class="form-group">
            <label>Priority</label>
            <select name="priority" id="et_priority" class="form-control">
              <option value="low">Low</option><option value="normal">Normal</option>
              <option value="high">High</option><option value="urgent">Urgent</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Title <span style="color:var(--red)">*</span></label>
          <input type="text" name="title" id="et_title" class="form-control" required>
        </div>
        <div class="form-group" id="et_equipWrap">
          <label>Equipment</label>
          <div style="position:relative">
            <input type="text" id="et_equipSearch" class="form-control" placeholder="Search equipment…" autocomplete="off"
                   oninput="openTicketEquipSearch('et_equipment','et_equipSearch','et_equipDrop',this.value)"
                   onfocus="openTicketEquipSearch('et_equipment','et_equipSearch','et_equipDrop',this.value)"
                   onblur="closeTicketEquipSearch('et_equipDrop',200)">
            <input type="hidden" name="equipment_id" id="et_equipment">
            <div class="crew-slot-drop" id="et_equipDrop"></div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Serial / asset no.</label>
            <input type="text" name="serial_no" id="et_serial" class="form-control" placeholder="Which physical unit">
          </div>
          <div class="form-group">
            <label>Vendor / Supplier</label>
            <input type="text" name="vendor_supplier" id="et_vendor" class="form-control">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Requested Date</label>
            <input type="date" name="requested_date" id="et_requested" class="form-control">
          </div>
          <div class="form-group">
            <label>Target Date</label>
            <input type="date" name="target_date" id="et_target" class="form-control">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Estimated Cost (₱)</label>
            <input type="number" name="estimated_cost" id="et_estimate" class="form-control" step="0.01" min="0">
          </div>
          <div class="form-group">
            <label>Person In Charge</label>
            <input type="text" name="person_in_charge" id="et_pic" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label>Reported By</label>
          <input type="text" name="reported_by" id="et_reported" class="form-control" placeholder="Who flagged it">
        </div>
        <div class="form-group">
          <label>Note</label>
          <textarea name="note" id="et_note" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalEditTicket')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="check"></i> Save</button>
      </div>
    </form>
  </div>
</div>

<!-- TICKET DETAILS & MESSAGES (Part 15) -->
<div class="modal-overlay" id="modalTicketDetails">
  <div class="modal" style="max-width:600px">
    <div class="modal-header">
      <h3><i data-feather="message-square" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i><span id="td_title">Ticket</span></h3>
      <button class="modal-close" onclick="closeModal('modalTicketDetails')">&times;</button>
    </div>
    <div class="modal-body" style="max-height:70vh;overflow-y:auto">
      <div id="td_details" style="font-size:.85rem;margin-bottom:14px"></div>
      <div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;color:var(--blue-700);text-transform:uppercase;margin-bottom:6px">
        Messages (<span id="td_count">0</span>)
      </div>
      <div id="td_messages" style="margin-bottom:12px"></div>
      <form method="POST">
        @csrf
        <input type="hidden" name="action" value="post_ticket_message">
        <input type="hidden" name="ticket_id" id="td_ticket_id">
        <div class="form-group">
          <label>Add a message</label>
          <textarea name="body" class="form-control" rows="2" placeholder="Quote received, chased the supplier, parts ordered…" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary btn-sm"><i data-feather="send"></i> Post message</button>
      </form>
    </div>
  </div>
</div>

<!-- UPDATE STATUS MODAL -->
<div class="modal-overlay" id="modalUpdateStatus">
  <div class="modal" style="max-width:460px">
    <div class="modal-header">
      <h3><i data-feather="edit-2" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Update Ticket</h3>
      <button class="modal-close" onclick="closeModal('modalUpdateStatus')">&times;</button>
    </div>
    <form method="POST">
      @csrf
      <input type="hidden" name="action" value="update_ticket_status">
      <input type="hidden" name="ticket_id" id="us_ticket_id">
      <div class="modal-body">
        <div id="us_context" style="font-size:.85rem;color:var(--muted);margin-bottom:12px"></div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" id="us_status" class="form-control" onchange="toggleStatusCost()">
            @foreach ($statusLabel as $k => $l)
            <option value="{{ $k }}">{{ $l }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group" id="us_costWrap" style="display:none">
          <label>Actual Cost (₱)</label>
          <input type="number" name="actual_cost" id="us_actual_cost" class="form-control" step="0.01" min="0">
        </div>
        <div class="form-group">
          <label>Note</label>
          <textarea name="note" id="us_note" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalUpdateStatus')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="check"></i> Save</button>
      </div>
    </form>
  </div>
</div>
@endif

@endsection

@push('scripts')
<script>
function toggleTicketType() {
  const isRepair = document.getElementById('ticketType').value === 'repair';
  document.getElementById('ticketEquipWrap').style.display = isRepair ? '' : 'none';
}

// ── Equipment type-to-search (New/Edit Ticket) ──────────────────────────────
const REPAIR_ALL_EQUIPMENT = {!! $allEquipment->values()->toJson() !!};

function _ticketEquipLabel(eq) {
  return eq.equipment_name + ' (' + (eq.brand || '') + (eq.serial_number ? ' · ' + eq.serial_number : '') + ')';
}
function openTicketEquipSearch(hiddenId, searchId, dropId, q) {
  const lq = (q || '').toLowerCase().trim();
  const pool = REPAIR_ALL_EQUIPMENT.filter((eq) => {
    if (!lq) return true;
    return (eq.equipment_name || '').toLowerCase().includes(lq)
      || (eq.brand || '').toLowerCase().includes(lq)
      || (eq.serial_number || '').toLowerCase().includes(lq);
  }).slice(0, 30);
  const drop = document.getElementById(dropId);
  if (!drop) return;
  drop.innerHTML = pool.length
    ? pool.map((eq) => `<div class="csd-item" data-id="${eq.equipment_id}" data-label="${esc(_ticketEquipLabel(eq))}">
        <div class="csd-ref">${esc(eq.equipment_name)}</div>
        <div class="csd-sub">${esc(eq.brand || '')}${eq.serial_number ? ' &middot; ' + esc(eq.serial_number) : ''}</div>
      </div>`).join('')
    : '<div class="csd-empty">No matching equipment</div>';
  positionSearchDrop(document.getElementById(searchId), drop);
  drop.style.display = 'block';
  drop.querySelectorAll('.csd-item').forEach((el) => {
    el.addEventListener('mousedown', (e) => {
      e.preventDefault();
      document.getElementById(hiddenId).value = el.dataset.id;
      document.getElementById(searchId).value = el.dataset.label;
      drop.style.display = 'none';
    });
  });
}
function closeTicketEquipSearch(dropId, ms) {
  setTimeout(() => { const d = document.getElementById(dropId); if (d) d.style.display = 'none'; }, ms);
}
function esc(s) {
  return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
function resetNewTicketForm() {
  const search = document.getElementById('ticketEquipSearch');
  const hidden = document.getElementById('ticket_equipment');
  if (search) search.value = '';
  if (hidden) hidden.value = '';
}

function toggleStatusCost() {
  const sel = document.getElementById('us_status');
  document.getElementById('us_costWrap').style.display = sel.value === 'completed' ? '' : 'none';
}

// ── Edit ticket / details & messages (Part 15) ─────────────────────────────
const TICKET_STATUS_LABELS = @json($statusLabelByType);

function toggleEditTicketType() {
  const isRepair = document.getElementById('et_type').value === 'repair';
  document.getElementById('et_equipWrap').style.display = isRepair ? '' : 'none';
}

function openEditTicket(t) {
  document.getElementById('et_ticket_id').value = t.ticket_id;
  document.getElementById('et_context').textContent = t.ticket_number + ' — created ' + (t.requested_date || '');
  document.getElementById('et_type').value = t.type;
  document.getElementById('et_priority').value = t.priority || 'normal';
  document.getElementById('et_title').value = t.title || '';
  document.getElementById('et_equipment').value = t.equipment_id || '';
  {
    const eq = REPAIR_ALL_EQUIPMENT.find((x) => String(x.equipment_id) === String(t.equipment_id));
    document.getElementById('et_equipSearch').value = eq ? _ticketEquipLabel(eq) : '';
  }
  document.getElementById('et_serial').value = t.serial_no || '';
  document.getElementById('et_vendor').value = t.vendor_supplier || '';
  document.getElementById('et_requested').value = t.requested_date || '';
  document.getElementById('et_target').value = t.target_date || '';
  document.getElementById('et_estimate').value = t.estimated_cost || '';
  document.getElementById('et_pic').value = t.person_in_charge || '';
  document.getElementById('et_reported').value = t.reported_by || '';
  document.getElementById('et_note').value = t.note || '';
  toggleEditTicketType();
  openModal('modalEditTicket');
  if (window.feather) feather.replace();
}

function openTicketDetails(t, msgs) {
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const label = (TICKET_STATUS_LABELS[t.type] || {})[t.status] || t.status;
  const peso = v => (v == null || v === '') ? '—' : '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2 });

  document.getElementById('td_title').textContent = t.ticket_number + ' — ' + t.title;
  document.getElementById('td_ticket_id').value = t.ticket_id;

  const rows = [
    ['Status', esc(label)],
    ['Type', t.type === 'repair' ? 'Repair' : 'Purchase'],
    ['Priority', esc(t.priority || '')],
    ['Serial / asset no.', esc(t.serial_no || '—')],
    ['Vendor / supplier', esc(t.vendor_supplier || '—')],
    ['Requested', esc(t.requested_date || '—')],
    ['Target', esc(t.target_date || '—')],
    ['Completed', esc(t.completed_date || '—')],
    ['Estimated cost', peso(t.estimated_cost)],
    ['Actual cost', peso(t.actual_cost)],
    ['Person in charge', esc(t.person_in_charge || '—')],
    ['Reported by', esc(t.reported_by || '—')],
  ];
  document.getElementById('td_details').innerHTML =
    rows.map(([k, v]) => '<div style="display:flex;justify-content:space-between;gap:12px;padding:3px 0;border-bottom:1px solid var(--border)">'
      + '<span style="color:var(--text-muted)">' + k + '</span><span>' + v + '</span></div>').join('')
    + (t.note ? '<div style="margin-top:8px;padding:8px;background:var(--s2);border-radius:6px">' + esc(t.note) + '</div>' : '');

  document.getElementById('td_count').textContent = msgs.length;
  document.getElementById('td_messages').innerHTML = msgs.length
    ? msgs.map(m => '<div style="padding:6px 0;border-bottom:1px solid var(--border)">'
        + '<div style="font-size:.72rem;color:var(--text-muted)">' + esc(m.author || 'Unknown')
        + ' · ' + esc(m.created_at) + '</div>'
        + '<div style="font-size:.85rem;white-space:pre-wrap">' + esc(m.body) + '</div></div>').join('')
    : '<div style="font-size:.83rem;color:var(--text-muted)">No messages yet.</div>';

  openModal('modalTicketDetails');
  if (window.feather) feather.replace();
}

function openStatusModal(ticket) {
  document.getElementById('us_ticket_id').value = ticket.ticket_id;
  document.getElementById('us_status').value = ticket.status;
  document.getElementById('us_actual_cost').value = ticket.actual_cost || '';
  document.getElementById('us_note').value = ticket.note || '';
  document.getElementById('us_context').textContent = ticket.ticket_number + ' — ' + ticket.title;
  toggleStatusCost();
  openModal('modalUpdateStatus');
}
</script>
@endpush
