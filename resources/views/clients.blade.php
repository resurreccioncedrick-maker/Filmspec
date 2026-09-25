@extends('layouts.app')

@section('pageTitle', 'Client Registry')

@section('breadcrumb')
<span>Clients</span>
@endsection

@section('topbarActions')
@include('partials.export-dropdown', ['id' => 'Clients'])
@if ($canManage)
<button onclick="openModal('modalAddClient')" class="btn btn-primary btn-sm"><i data-feather="user-plus" style="width:14px;height:14px;margin-right:4px;vertical-align:middle"></i> Add Client</button>
@endif
@endsection

@section('content')
@php
  $clientsBase = route('clients');
@endphp

@if ($msg)
<div class="alert alert-{{ $msg['type'] }}" data-autohide>
  <i data-feather="{{ $msg['type'] === 'success' ? 'check-circle' : 'alert-circle' }}"></i>
  {!! $msg['text'] !!}
</div>
@endif

<!-- Stats row -->
<div class="stats-grid" style="margin-bottom:20px">
  <div class="stat-card">
    <div class="stat-value">{{ $stats['total'] }}</div>
    <div class="stat-label">Total Clients</div>
  </div>
  <div class="stat-card">
    <div class="stat-value" style="color:var(--green)">{{ $stats['regular'] }}</div>
    <div class="stat-label">Regular Clients</div>
  </div>
  <div class="stat-card">
    <div class="stat-value" style="color:var(--blue)">{{ $stats['first_time'] }}</div>
    <div class="stat-label">New Customers</div>
  </div>
  <div class="stat-card">
    <div class="stat-value">{{ $stats['new_month'] }}</div>
    <div class="stat-label">New This Month</div>
  </div>
</div>

<!-- Type / status filter tabs -->
<div class="tabs" style="margin-bottom:18px">
  <a href="{{ $clientsBase }}" class="tab-btn {{ ! $typeFilter && ! $statusFilter ? 'active' : '' }}">All <span class="badge badge-gray" style="margin-left:4px">{{ $total }}</span></a>
  <a href="{{ $clientsBase }}?type=regular" class="tab-btn {{ $typeFilter === 'regular' ? 'active' : '' }}">Regular <span class="badge badge-green" style="margin-left:4px">{{ $stats['regular'] }}</span></a>
  <a href="{{ $clientsBase }}?type=first_time" class="tab-btn {{ $typeFilter === 'first_time' ? 'active' : '' }}">New Customers <span class="badge badge-blue" style="margin-left:4px">{{ $stats['first_time'] }}</span></a>
  <a href="{{ $clientsBase }}?status=pending" class="tab-btn {{ $statusFilter === 'pending' ? 'active' : '' }}">Pending Approval <span class="badge badge-yellow" style="margin-left:4px">{{ $stats['pending'] }}</span></a>
</div>

<div class="card">
  <div class="card-header">
    <h2 class="card-title">Client Registry</h2>
  </div>
  <div class="card-body" style="padding-bottom:0">
    <form method="GET" action="{{ $clientsBase }}">
      @if ($typeFilter)<input type="hidden" name="type" value="{{ $typeFilter }}">@endif
      <div class="filter-bar">
        <div class="search-input-wrap">
          <i data-feather="search"></i>
          <input type="text" name="q" class="form-control" placeholder="Search name, company, email, phone…"
                 value="{{ $search }}">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">
          <i data-feather="filter"></i> Filter
        </button>
      </div>
    </form>
  </div>

  <div class="table-wrap">
    @if ($clients->isEmpty())
    <div class="empty-state">
      <i data-feather="users"></i>
      <h3>No clients found</h3>
      <p>Add a client or adjust your search filters.</p>
    </div>
    @else
    <table class="data-table">
      <thead>
        <tr>
          <th>Client</th>
          <th>Type</th>
          <th>Contact</th>
          <th>Bookings</th>
          <th>Completed</th>
          <th>Last Booking</th>
          <th>Payment Terms</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($clients as $c)
        @php
          $displayName = $c->company_name ? $c->company_name : $c->contact_person;
          $completed = (int) ($c->completed_bookings ?? 0);
          $totalB = (int) ($c->total_bookings ?? 0);
          $nearPromo = ($c->client_type === 'first_time' && $completed === 3);
          $et = $c->entity_type ?? 'individual';
        @endphp
        <tr>
          <td>
            <div style="font-weight:500">{{ $displayName }}</div>
            @if ($c->company_name && $c->contact_person)
            <div style="font-size:11px;color:var(--muted)">{{ $c->contact_person }}</div>
            @endif
            <span class="badge {{ $entityTypeBadge[$et] ?? 'badge-gray' }}" style="font-size:10px;margin-top:2px">
              {{ $entityTypeLabel[$et] ?? ucfirst($et) }}
            </span>
            @if ($c->is_vat_registered)
            <span class="badge badge-purple" style="font-size:10px;margin-top:2px">VAT</span>
            @endif
            @if (($c->status ?? 'approved') === 'pending')
            <span class="badge badge-yellow" style="font-size:10px;margin-top:2px">Pending Approval</span>
            @elseif (($c->status ?? 'approved') === 'rejected')
            <span class="badge badge-red" style="font-size:10px;margin-top:2px" title="{{ $c->rejection_reason }}">Rejected</span>
            @endif
          </td>
          <td>
            <span class="badge {{ $typeBadge[$c->client_type] ?? 'badge-gray' }}">
              {{ $typeLabel[$c->client_type] ?? ucfirst($c->client_type) }}
            </span>
            @if ($nearPromo)
            <div style="font-size:10px;color:var(--muted);margin-top:2px">1 more → Regular</div>
            @endif
          </td>
          <td>
            <div>{{ $c->email ?? '—' }}</div>
            <div style="font-size:11px;color:var(--muted)">{{ $c->phone ?? '' }}</div>
          </td>
          <td style="text-align:center">{{ $totalB }}</td>
          <td style="text-align:center">
            <span style="color:var(--green);font-weight:500">{{ $completed }}</span>
            @if ($c->client_type === 'first_time')
            <span style="color:var(--muted);font-size:11px"> / 4</span>
            @endif
          </td>
          <td style="white-space:nowrap">
            {{ $c->last_booking_date ? \Illuminate\Support\Carbon::parse($c->last_booking_date)->format('M j, Y') : '—' }}
          </td>
          <td>
            <span class="badge badge-gray" style="font-size:11px">
              {{ $termLabel[$c->payment_terms] ?? ucfirst($c->payment_terms ?? '—') }}
            </span>
          </td>
          <td>
            <span class="badge {{ ($c->is_active ?? true) ? 'badge-green' : 'badge-red' }}">
              {{ ($c->is_active ?? true) ? 'Active' : 'Inactive' }}
            </span>
          </td>
          <td>
            <div style="display:flex;gap:6px;align-items:center">
              <a href="{{ route('client-detail', $c->client_id) }}" class="btn btn-outline btn-sm">
                <i data-feather="eye" style="width:13px;height:13px"></i> View
              </a>
              <div class="action-menu-wrap">
                <button type="button" class="btn-icon" onclick="toggleActionMenu(this)" title="More actions">
                  <i data-feather="more-vertical"></i>
                </button>
                <div class="action-menu align-right">
                  @if ($canManage && ($c->status ?? 'approved') === 'pending')
                  <form method="POST" action="{{ $clientsBase }}">
                    @csrf
                    <input type="hidden" name="action" value="approve_client">
                    <input type="hidden" name="client_id" value="{{ $c->client_id }}">
                    <button type="submit" class="text-success" onclick="return confirm('Approve {{ addslashes($displayName) }}\'s account?')">
                      <i data-feather="check"></i> Approve Account
                    </button>
                  </form>
                  <button type="button" class="text-danger" onclick="closeActionMenus(); openRejectClient({{ $c->client_id }}, {{ json_encode($displayName) }})">
                    <i data-feather="x"></i> Reject Account
                  </button>
                  <div class="action-menu-divider"></div>
                  @endif
                  <button type="button" onclick="closeActionMenus(); editClient(@json($c))">
                    <i data-feather="edit-2"></i> Edit
                  </button>
                  <a href="{{ route('bookings') }}?client={{ $c->client_id }}">
                    <i data-feather="calendar"></i> View Bookings
                  </a>
                  <a href="{{ route('client-documents', $c->client_id) }}">
                    <i data-feather="file-text"></i> Documents
                  </a>
                </div>
              </div>
            </div>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>

    @if ($pages > 1)
    <div class="pagination" style="padding:16px">
      @for ($i = 1; $i <= $pages; $i++)
      <a href="{{ $clientsBase }}?{{ http_build_query(array_merge(request()->query(), ['p' => $i])) }}"
         class="page-btn {{ $i === $page ? 'active' : '' }}">{{ $i }}</a>
      @endfor
    </div>
    @endif
    @endif
  </div>
</div>

@if ($canManage)
<!-- Add Client Modal -->
<div id="modalAddClient" class="modal-overlay">
  <div class="modal" style="max-width:600px">
    <div class="modal-header">
      <h3><i data-feather="user-plus" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Add Client</h3>
      <button class="modal-close" onclick="closeModal('modalAddClient')">&times;</button>
    </div>
    <form method="POST" action="{{ $clientsBase }}">
      @csrf
      <input type="hidden" name="action" value="add_client">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label>Company Name</label>
            <input type="text" name="company_name" class="form-control" placeholder="Production company / studio">
          </div>
          <div class="form-group">
            <label>Contact Person <span class="req">*</span></label>
            <input type="text" name="contact_person" class="form-control" placeholder="Full name" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control" placeholder="client@example.com">
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control" placeholder="+63 9xx xxx xxxx">
          </div>
        </div>
        <div class="form-group">
          <label>Address</label>
          <input type="text" name="address" class="form-control" placeholder="Full address">
        </div>
        <div class="form-group">
          <label>Entity Type</label>
          <select name="entity_type" class="form-control" onchange="onAddEntityTypeChange(this.value)">
            <option value="individual">Individual</option>
            <option value="student">Student</option>
            <option value="company">Company</option>
            <option value="ngo">NGO / Non-Profit</option>
            <option value="government">Government</option>
          </select>
        </div>
        <div id="add_vat_notice" style="display:none;background:rgba(168,85,247,.08);border:1px solid rgba(168,85,247,.3);border-radius:6px;padding:8px 12px;font-size:12px;color:#c084fc;margin-bottom:10px">
          Companies are subject to 12% VAT on all fees.
        </div>
        <div class="form-group">
          <label>Notes</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Internal notes…"></textarea>
        </div>
        <div style="font-size:.75rem;color:var(--text-muted);background:var(--s2);border-radius:6px;padding:8px 10px">
          New clients start as <strong>New Customer</strong> and are promoted to Regular from Client Detail once eligible.
          Payment Terms and Loyalty Discount are set from the client's <strong>Billing</strong> tab after they're added.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalAddClient')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="save" style="width:14px;height:14px;margin-right:4px;vertical-align:middle"></i>Save Client</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Client Modal -->
<div id="modalEditClient" class="modal-overlay">
  <div class="modal" style="max-width:600px">
    <div class="modal-header">
      <h3><i data-feather="edit-2" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Edit Client</h3>
      <button class="modal-close" onclick="closeModal('modalEditClient')">&times;</button>
    </div>
    <form method="POST" action="{{ $clientsBase }}">
      @csrf
      <input type="hidden" name="action" value="edit_client">
      <input type="hidden" name="client_id" id="edit_cid">
      <div class="modal-body">
        <div id="edit_client_badge" style="margin-bottom:12px"></div>
        <div class="form-row">
          <div class="form-group">
            <label>Company Name</label>
            <input type="text" name="company_name" id="edit_ccompany" class="form-control">
          </div>
          <div class="form-group">
            <label>Contact Person <span class="req">*</span></label>
            <input type="text" name="contact_person" id="edit_ccontact" class="form-control" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" id="edit_cemail" class="form-control">
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone" id="edit_cphone" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label>Address</label>
          <input type="text" name="address" id="edit_caddress" class="form-control">
        </div>
        <div class="form-group">
          <label>Entity Type</label>
          <select name="entity_type" id="edit_centity" class="form-control" onchange="onEditEntityTypeChange(this.value)">
            <option value="individual">Individual</option>
            <option value="student">Student</option>
            <option value="company">Company</option>
            <option value="ngo">NGO / Non-Profit</option>
            <option value="government">Government</option>
          </select>
        </div>
        <div id="edit_vat_notice" style="display:none;background:rgba(168,85,247,.08);border:1px solid rgba(168,85,247,.3);border-radius:6px;padding:8px 12px;font-size:12px;color:#c084fc;margin-bottom:10px">
          Companies are subject to 12% VAT on all fees.
        </div>
        <div class="form-group">
          <label>Notes</label>
          <textarea name="notes" id="edit_cnotes" class="form-control" rows="2"></textarea>
        </div>
        <div style="font-size:.75rem;color:var(--text-muted);background:var(--s2);border-radius:6px;padding:8px 10px">
          Regular Client status is promoted from Client Detail once eligible, not editable here.
          Payment Terms and Loyalty Discount are managed on the client's <a href="#" id="edit_cbilling_link">Billing tab</a>.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalEditClient')">Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="save" style="width:14px;height:14px;margin-right:4px;vertical-align:middle"></i>Update Client</button>
      </div>
    </form>
  </div>
</div>

<!-- Reject Client Modal -->
<div id="modalRejectClient" class="modal-overlay">
  <div class="modal" style="max-width:460px">
    <div class="modal-header">
      <h3><i data-feather="x-circle" style="width:16px;height:16px;margin-right:6px;vertical-align:middle;color:var(--red)"></i>Reject Client</h3>
      <button class="modal-close" onclick="closeModal('modalRejectClient')">&times;</button>
    </div>
    <form method="POST" action="{{ $clientsBase }}">
      @csrf
      <input type="hidden" name="action" value="reject_client">
      <input type="hidden" name="client_id" id="reject_cid">
      <div class="modal-body">
        <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:12px">
          Rejecting <strong id="reject_cname"></strong>'s account application. This reason will be emailed to them.
        </p>
        <div class="form-group">
          <label>Reason *</label>
          <textarea name="reason" class="form-control" rows="3" required placeholder="Why is this application being rejected?"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modalRejectClient')">Cancel</button>
        <button type="submit" class="btn btn-danger"><i data-feather="x" style="width:14px;height:14px;margin-right:4px;vertical-align:middle"></i>Reject Account</button>
      </div>
    </form>
  </div>
</div>
@endif

@push('scripts')
<script>
function editClient(c) {
  document.getElementById('edit_cid').value      = c.client_id;
  document.getElementById('edit_ccompany').value = c.company_name   || '';
  document.getElementById('edit_ccontact').value = c.contact_person || '';
  document.getElementById('edit_cemail').value   = c.email          || '';
  document.getElementById('edit_cphone').value   = c.phone          || '';
  document.getElementById('edit_caddress').value = c.address        || '';
  document.getElementById('edit_centity').value  = c.entity_type    || 'individual';
  document.getElementById('edit_cnotes').value   = c.notes          || '';
  const billingLink = document.getElementById('edit_cbilling_link');
  if (billingLink) billingLink.href = '/clients/' + c.client_id + '?tab=billing';

  onEditEntityTypeChange(c.entity_type || 'individual');

  const isRegular = c.client_type === 'regular';
  const badge = document.getElementById('edit_client_badge');
  badge.innerHTML = isRegular
    ? '<div style="background:#052e16;border:1px solid #16a34a;color:#4ade80;padding:8px 12px;border-radius:6px;font-size:12px;display:flex;align-items:center;gap:6px"><i data-feather="star" style="width:13px;height:13px"></i> Regular Client — Pay Later eligible</div>'
    : '<div style="background:#172554;border:1px solid #1d4ed8;color:#93c5fd;padding:8px 12px;border-radius:6px;font-size:12px;display:flex;align-items:center;gap:6px"><i data-feather="user" style="width:13px;height:13px"></i> New Customer — 50% downpayment required</div>';

  openModal('modalEditClient');
  if (typeof feather !== 'undefined') feather.replace();
}

function onAddEntityTypeChange(val) {
  document.getElementById('add_vat_notice').style.display = val === 'company' ? 'block' : 'none';
}

function onEditEntityTypeChange(val) {
  document.getElementById('edit_vat_notice').style.display = val === 'company' ? 'block' : 'none';
}

function openRejectClient(cid, name) {
  document.getElementById('reject_cid').value = cid;
  document.getElementById('reject_cname').textContent = name;
  openModal('modalRejectClient');
}

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
  if (rect.bottom > window.innerHeight) {
    menu.classList.add('drop-up');
  }
}

document.addEventListener('click', function (e) {
  if (!e.target.closest('.action-menu-wrap')) closeActionMenus();
});
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') closeActionMenus();
});
</script>
@endpush
@endsection
