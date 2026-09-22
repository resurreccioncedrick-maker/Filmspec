@extends('layouts.app')

@section('pageTitle', 'Crew Management')

@section('breadcrumb')
<span>Crew</span>
@endsection

@section('topbarActions')
@include('partials.export-dropdown', ['id' => 'Crew'])
@if ($canManage)
<a href="{{ route('attendance') }}" class="btn btn-outline btn-sm">
  <i data-feather="clock"></i> Attendance
</a>
<button onclick="openModal('modalAddPos')" class="btn btn-outline btn-sm">
  <i data-feather="briefcase"></i> Add Position
</button>
<button onclick="openModal('modalAddCrew')" class="btn btn-primary btn-sm">
  <i data-feather="user-plus"></i> Add Crew Member
</button>
@endif
@endsection

@section('content')
@php
  $crewBase = route('crew');
@endphp

@if ($msg)
<div class="alert alert-{{ $msg['type'] }}" @if (empty($msg['persist'])) data-autohide @endif>
  <i data-feather="{{ $msg['type'] === 'success' ? 'check-circle' : 'alert-circle' }}"></i>
  {!! $msg['text'] !!}
</div>
@endif

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:22px">
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="users"></i></div>
    <div class="stat-value">{{ $stats['total'] }}</div>
    <div class="stat-label">Active Crew</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon" style="background:#f0fdf4;color:#16a34a"><i data-feather="briefcase"></i></div>
    <div class="stat-value">{{ $stats['staff'] }}</div>
    <div class="stat-label">Staff</div>
  </div>
  <div class="stat-card" style="--sb:#7c3aed">
    <div class="stat-icon" style="background:#f3e8ff;color:#7c3aed"><i data-feather="user-check"></i></div>
    <div class="stat-value">{{ $stats['freelance'] }}</div>
    <div class="stat-label">Freelance</div>
  </div>
  <div class="stat-card orange">
    <div class="stat-icon"><i data-feather="phone-call"></i></div>
    <div class="stat-value">{{ $stats['on_call'] }}</div>
    <div class="stat-label">On Call</div>
  </div>
</div>

<!-- Tabs -->
<div class="tabs" style="margin-bottom:18px">
  <a href="{{ $crewBase }}?tab=members" class="tab-btn {{ $tab === 'members' ? 'active' : '' }}"><i data-feather="users" style="width:13px;height:13px;margin-right:5px;vertical-align:middle"></i>Crew Members</a>
  <a href="{{ $crewBase }}?tab=positions" class="tab-btn {{ $tab === 'positions' ? 'active' : '' }}"><i data-feather="briefcase" style="width:13px;height:13px;margin-right:5px;vertical-align:middle"></i>Positions <span class="badge badge-gray" style="margin-left:4px">{{ $positionsWithCounts->count() }}</span></a>
  <a href="{{ $crewBase }}?tab=schedule" class="tab-btn {{ $tab === 'schedule' ? 'active' : '' }}"><i data-feather="calendar" style="width:13px;height:13px;margin-right:5px;vertical-align:middle"></i>Schedule</a>
  <a href="{{ $crewBase }}?tab=attendance" class="tab-btn {{ $tab === 'attendance' ? 'active' : '' }}"><i data-feather="clock" style="width:13px;height:13px;margin-right:5px;vertical-align:middle"></i>Attendance</a>
</div>

@if ($tab === 'members')
<div class="card">
  <div class="card-header">
    <h2 class="card-title">
      Crew Registry
      <span class="badge badge-blue" style="margin-left:8px">{{ $total }}</span>
    </h2>
    <div style="display:flex;gap:8px;align-items:center">
      @if ($canManage)
      <a href="{{ route('attendance') }}" class="btn btn-outline btn-sm">
        <i data-feather="clock"></i> Attendance
      </a>
      <button onclick="openModal('modalAddPos')" class="btn btn-outline btn-sm">
        <i data-feather="briefcase"></i> Add Position
      </button>
      <button onclick="openModal('modalAddCrew')" class="btn btn-primary btn-sm">
        <i data-feather="user-plus"></i> Add Crew Member
      </button>
      @endif
      <a href="{{ $crewBase }}" class="btn btn-outline btn-sm"><i data-feather="refresh-cw"></i></a>
    </div>
  </div>

  <div class="card-body" style="padding-bottom:0">
    <form method="GET" action="{{ $crewBase }}">
      <div class="filter-bar">
        <div class="search-input-wrap">
          <i data-feather="search"></i>
          <input type="text" name="q" class="form-control"
                 placeholder="Search name, phone, position…"
                 value="{{ $search }}">
        </div>
        <select name="pos" class="form-control" style="width:auto">
          <option value="">All Positions</option>
          @foreach ($positions as $p)
          <option value="{{ $p->position_id }}" {{ $posFilter == $p->position_id ? 'selected' : '' }}>
            {{ $p->position_name }}
          </option>
          @endforeach
        </select>
        <select name="et" class="form-control" style="width:auto">
          <option value="">All Types</option>
          <option value="staff" {{ $etFilter === 'staff' ? 'selected' : '' }}>Staff</option>
          <option value="freelance" {{ $etFilter === 'freelance' ? 'selected' : '' }}>Freelance</option>
          <option value="on_call" {{ $etFilter === 'on_call' ? 'selected' : '' }}>On Call</option>
        </select>
        <select name="status" class="form-control" style="width:auto">
          <option value="">All Status</option>
          <option value="active" {{ $statusFilter === 'active' ? 'selected' : '' }}>Active</option>
          <option value="inactive" {{ $statusFilter === 'inactive' ? 'selected' : '' }}>Inactive</option>
          <option value="blacklisted" {{ $statusFilter === 'blacklisted' ? 'selected' : '' }}>Blacklisted</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">
          <i data-feather="filter"></i> Filter
        </button>
      </div>
    </form>
  </div>

  <div class="table-wrap">
    @if ($crew->isEmpty())
    <div class="empty-state">
      <i data-feather="users"></i>
      <h3>No crew members found</h3>
      @if ($canManage)
      <p style="margin-bottom:16px">Add your first crew member to get started.</p>
      <button onclick="openModal('modalAddCrew')" class="btn btn-primary">
        <i data-feather="user-plus"></i> Add Crew Member
      </button>
      @else
      <p>No crew matches your search.</p>
      @endif
    </div>
    @else
    <table>
      <thead>
        <tr>
          <th>Crew Member</th>
          <th>Position / Dept</th>
          <th>Type</th>
          <th>Rate</th>
          <th>Phone</th>
          <th>Shoots</th>
          <th>Status</th>
          <th>Schedule</th>
          @if ($canManage)<th style="text-align:right">Actions</th>@endif
        </tr>
      </thead>
      <tbody>
        @foreach ($crew as $cm)
        <tr style="{{ $cm->status !== 'active' ? 'opacity:.6' : '' }}">
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="crew-avatar" style="width:36px;height:36px;font-size:.9rem">
                @if (! empty($cm->photo_path))
                  <img src="{{ asset('storage/' . $cm->photo_path) }}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%">
                @else
                  {{ strtoupper(substr($cm->first_name, 0, 1)) }}
                @endif
              </div>
              <div>
                <div style="font-weight:600">{{ $cm->first_name . ' ' . $cm->last_name }}</div>
                <div style="font-size:.72rem;color:var(--muted)">{{ $cm->email ?? '' }}</div>
              </div>
            </div>
          </td>
          <td>
            <div style="font-size:.875rem">{{ $cm->position_name ?? '—' }}</div>
            <div style="font-size:.72rem;color:var(--muted)">{{ $cm->department ?? '' }}</div>
          </td>
          <td>
            <span class="badge {{ $typeBadge[$cm->employment_type] ?? 'badge-gray' }}">
              {{ $typeLabel[$cm->employment_type] ?? '—' }}
            </span>
          </td>
          <td style="font-size:.83rem;font-weight:600">
            {{ $cm->employment_type === 'staff'
                ? '₱' . number_format($cm->monthly_salary, 2) . '/mo'
                : '₱' . number_format($cm->base_rate_12hr, 2) . '/12hr' }}
          </td>
          <td style="font-size:.83rem;color:var(--muted)">{{ $cm->phone ?? '—' }}</td>
          <td style="text-align:center">
            <span class="badge badge-blue">{{ $cm->total_shoots }}</span>
          </td>
          <td>
            <span class="badge {{ $statusBadge[$cm->status] ?? 'badge-gray' }}">
              {{ ucfirst($cm->status) }}
            </span>
          </td>
          <td>
            @php $asg = $currentAssignments[$cm->crew_id] ?? null; @endphp
            @if ($asg)
              @php
                $today = \Illuminate\Support\Carbon::today();
                $onSetNow = $today->between(
                  \Illuminate\Support\Carbon::parse($asg->shoot_date_start),
                  \Illuminate\Support\Carbon::parse($asg->shoot_date_end)
                );
              @endphp
              <span class="badge {{ $onSetNow ? 'badge-green' : 'badge-blue' }}" style="white-space:nowrap">
                {{ $onSetNow ? 'On Set' : 'Upcoming' }}
              </span>
              <div style="font-size:.72rem;color:var(--muted);margin-top:3px;white-space:nowrap">
                {{ $asg->booking_reference }}
                &middot; {{ \Illuminate\Support\Carbon::parse($asg->shoot_date_start)->format('M j') }}–{{ \Illuminate\Support\Carbon::parse($asg->shoot_date_end)->format('M j') }}
              </div>
            @else
              <span style="font-size:.8rem;color:var(--muted)">Available</span>
            @endif
          </td>
          @if ($canManage)
          <td style="text-align:right">
            <div style="display:flex;gap:4px;justify-content:flex-end">
              <button class="btn btn-outline btn-sm"
                      onclick='editCrew(@json($cm))'>
                <i data-feather="edit-2"></i> Edit
              </button>
              <button class="btn btn-outline btn-sm" title="Manage Unavailability"
                      onclick="openAvailModal({{ $cm->crew_id }}, '{{ addslashes($cm->first_name . ' ' . $cm->last_name) }}')">
                <i data-feather="calendar-x" style="width:13px;height:13px"></i>
                @php $nb = count($upcomingBlocks[$cm->crew_id] ?? []); @endphp
                @if ($nb)<span class="badge badge-red" style="font-size:.65rem;padding:1px 5px;margin-left:2px">{{ $nb }}</span>@endif
              </button>
              <a href="{{ route('attendance') }}?crew_id={{ $cm->crew_id }}"
                 class="btn btn-outline btn-sm" title="View Attendance">
                <i data-feather="clock"></i>
              </a>
              @if ($cm->user_id)
              <button class="btn btn-outline btn-sm" style="border-color:var(--green);color:var(--green)" title="Login linked — click to unlink"
                      onclick="openUnlinkModal({{ $cm->crew_id }}, '{{ addslashes($cm->first_name . ' ' . $cm->last_name) }}', '{{ addslashes($cm->linked_email ?? '') }}')">
                <i data-feather="user-check" style="width:13px;height:13px"></i>
              </button>
              @else
              <button class="btn btn-outline btn-sm" title="No login linked — click to create one"
                      onclick="openLinkModal({{ $cm->crew_id }}, '{{ addslashes($cm->first_name . ' ' . $cm->last_name) }}')">
                <i data-feather="user-plus" style="width:13px;height:13px"></i>
              </button>
              @endif
              <form method="POST" action="{{ $crewBase }}" style="display:inline"
                    onsubmit="return confirm('Delete {{ addslashes($cm->first_name . ' ' . $cm->last_name) }}? This cannot be undone.')">
                @csrf
                <input type="hidden" name="action" value="delete_crew">
                <input type="hidden" name="crew_id" value="{{ $cm->crew_id }}">
                <button type="submit" class="btn btn-sm" style="border:1px solid var(--red);color:var(--red);background:none;padding:5px 8px;border-radius:6px;cursor:pointer" title="Delete crew member">
                  <i data-feather="trash-2" style="width:13px;height:13px"></i>
                </button>
              </form>
            </div>
          </td>
          @endif
        </tr>
        @endforeach
      </tbody>
    </table>

    @if ($pages > 1)
    <div style="padding:14px 16px">
      <div class="pagination">
        @for ($pi = 1; $pi <= $pages; $pi++)
        <a href="{{ $crewBase }}?p={{ $pi }}&status={{ $statusFilter }}&pos={{ $posFilter }}&et={{ $etFilter }}&q={{ urlencode($search) }}"
           class="page-btn {{ $pi === $page ? 'active' : '' }}">{{ $pi }}</a>
        @endfor
      </div>
    </div>
    @endif
    @endif
  </div>
</div>
@endif

@if ($tab === 'positions')
<div class="card">
  <div class="card-header">
    <h2 class="card-title">Positions <span class="badge badge-blue" style="margin-left:8px">{{ $positionsWithCounts->count() }}</span></h2>
    @if ($canManage)
    <button onclick="openModal('modalAddPos')" class="btn btn-primary btn-sm"><i data-feather="plus"></i> Add Position</button>
    @endif
  </div>
  <div class="table-wrap">
    @if ($positionsWithCounts->isEmpty())
    <div class="empty-state"><i data-feather="briefcase"></i><h3>No positions defined yet</h3></div>
    @else
    <table>
      <thead><tr><th>Position</th><th>Department</th><th>Active Crew</th></tr></thead>
      <tbody>
      @foreach ($positionsWithCounts as $p)
      <tr>
        <td style="font-weight:600">{{ $p->position_name }}</td>
        <td><span class="badge badge-gray">{{ $p->department ?: '—' }}</span></td>
        <td>{{ $p->crew_count }}</td>
      </tr>
      @endforeach
      </tbody>
    </table>
    @endif
  </div>
</div>
@endif

@if ($tab === 'schedule')
<div class="card">
  <div class="card-header">
    <h2 class="card-title"><i data-feather="calendar" style="width:15px;height:15px;margin-right:6px;vertical-align:middle"></i>Crew Schedule</h2>
    <span style="font-size:11px;color:var(--muted)">Confirmed / ongoing bookings only</span>
  </div>
  <div class="card-body">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
      <button class="btn btn-outline btn-sm" onclick="crewSchedPrev()"><i data-feather="chevron-left" style="width:14px;height:14px"></i></button>
      <div id="crewSchedTitle" style="font-family:var(--font-display,inherit);font-size:18px;min-width:160px;text-align:center"></div>
      <button class="btn btn-outline btn-sm" onclick="crewSchedNext()"><i data-feather="chevron-right" style="width:14px;height:14px"></i></button>
      <button class="btn btn-outline btn-sm" onclick="crewSchedToday()">Today</button>
    </div>
    <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:1px;background:var(--border);border:1px solid var(--border);border-radius:6px;overflow:hidden">
      @foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $wd)
      <div style="background:var(--s2);padding:6px;text-align:center;font-size:.68rem;font-weight:700;color:var(--muted);text-transform:uppercase">{{ $wd }}</div>
      @endforeach
    </div>
    <div id="crewSchedGrid" style="display:grid;grid-template-columns:repeat(7,1fr);gap:1px;background:var(--border);border:1px solid var(--border);border-top:none"></div>
  </div>
</div>
@endif

@if ($tab === 'attendance')
<div class="card">
  <div class="card-header">
    <h2 class="card-title"><i data-feather="clock" style="width:15px;height:15px;margin-right:6px;vertical-align:middle"></i>Attendance</h2>
    <a href="{{ route('attendance') }}" class="btn btn-outline btn-sm" target="_blank">Open Full Page <i data-feather="external-link" style="width:12px;height:12px"></i></a>
  </div>
  <div class="card-body" style="padding:0">
    <iframe src="{{ route('attendance') }}" style="width:100%;height:900px;border:none;display:block"></iframe>
  </div>
</div>
@endif

@if ($canManage)

<!-- ADD CREW MODAL -->
<div class="modal-overlay" id="modalAddCrew">
  <div class="modal" style="max-width:640px">
    <div class="modal-header">
      <h3 class="modal-title">
        <i data-feather="user-plus" style="width:17px;height:17px;margin-right:8px;vertical-align:middle"></i>
        Add Crew Member
      </h3>
      <button class="modal-close" data-modal-close><i data-feather="x"></i></button>
    </div>
    <form method="POST" action="{{ $crewBase }}" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="action" value="add_crew">
      <div class="modal-body">
        <div class="form-group">
          <label>Profile Photo</label>
          <div class="img-upload-zone">
            <input type="file" name="photo" accept="image/*">
            <div class="img-upload-icon"><i data-feather="user"></i></div>
            <div style="font-size:13px;color:var(--muted)">Upload profile photo (optional)</div>
            <div class="img-upload-label">JPG or PNG · Max 5MB</div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>First Name *</label>
            <input type="text" name="first_name" class="form-control" required placeholder="First name">
          </div>
          <div class="form-group">
            <label>Last Name *</label>
            <input type="text" name="last_name" class="form-control" required placeholder="Last name">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control" placeholder="crew@email.com">
          </div>
          <div class="form-group">
            <label>Phone / GCash</label>
            <input type="text" name="phone" class="form-control" placeholder="+63 9XX XXX XXXX">
          </div>
        </div>
        <div class="form-group">
          <label>Address</label>
          <input type="text" name="address" class="form-control" placeholder="Home address">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Primary Position</label>
            <select name="primary_position_id" class="form-control">
              <option value="">— Select position —</option>
              @foreach ($positions as $p)
              <option value="{{ $p->position_id }}">
                {{ $p->position_name }}{{ $p->department ? ' (' . $p->department . ')' : '' }}
              </option>
              @endforeach
            </select>
          </div>
          <div class="form-group">
            <label>Employment Type *</label>
            <select name="employment_type" class="form-control" id="addEmpType"
                    onchange="toggleRates(this.value,'add')" required>
              <option value="freelance">Freelance (Day Rate)</option>
              <option value="staff">Staff (Monthly Salary)</option>
              <option value="on_call">On Call</option>
            </select>
          </div>
        </div>

        <div id="add_freelance_rates">
          <div style="background:var(--s2);border-radius:var(--radius-md);padding:14px;margin-bottom:4px">
            <div style="font-size:.75rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">
              Compensation Rates
            </div>
            <div class="form-row cols-3">
              <div class="form-group">
                <label>Base Rate (₱/12hr)</label>
                <input type="number" name="base_rate_12hr" class="form-control" value="0" step="0.01" min="0">
              </div>
              <div class="form-group">
                <label>Overtime Rate (₱/hr)</label>
                <input type="number" name="overtime_rate" class="form-control" value="0" step="0.01" min="0">
              </div>
              <div class="form-group">
                <label>Double Pay Rate (₱)</label>
                <input type="number" name="double_pay_rate" class="form-control" value="0" step="0.01" min="0">
              </div>
            </div>
          </div>
        </div>

        <div id="add_staff_rate" style="display:none">
          <div style="background:var(--s2);border-radius:var(--radius-md);padding:14px;margin-bottom:4px">
            <div class="form-group" style="margin-bottom:0">
              <label>Monthly Salary (₱)</label>
              <input type="number" name="monthly_salary" class="form-control" value="0" step="0.01" min="0">
            </div>
          </div>
        </div>

        <div class="form-row" style="margin-top:4px">
          <div class="form-group">
            <label>Date Joined</label>
            <input type="date" name="date_joined" class="form-control" value="{{ date('Y-m-d') }}">
          </div>
        </div>
        <div class="form-group">
          <label>Skills / Notes</label>
          <textarea name="profile_notes" class="form-control" rows="2"
                    placeholder="Skills, specializations, previous productions, GCash number…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">
          <i data-feather="save"></i> Add Crew Member
        </button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT CREW MODAL -->
<div class="modal-overlay" id="modalEditCrew">
  <div class="modal" style="max-width:640px">
    <div class="modal-header">
      <h3 class="modal-title">
        <i data-feather="edit-2" style="width:17px;height:17px;margin-right:8px;vertical-align:middle"></i>
        Edit Crew Member
      </h3>
      <button class="modal-close" data-modal-close><i data-feather="x"></i></button>
    </div>
    <form method="POST" action="{{ $crewBase }}" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="action" value="edit_crew">
      <input type="hidden" name="crew_id" id="edit_cid">
      <div class="modal-body">
        <div class="form-group">
          <label>Profile Photo</label>
          <div class="img-upload-zone" id="editCrewImgZone">
            <input type="file" name="photo" accept="image/*">
            <div id="editCrewImgPreview" style="display:none">
              <img id="editCrewImgCurrent" src="" style="width:80px;height:80px;object-fit:cover;border-radius:50%;display:block;margin:0 auto" alt="">
            </div>
            <div id="editCrewImgPlaceholder">
              <div class="img-upload-icon"><i data-feather="user"></i></div>
              <div style="font-size:12px;color:var(--muted)">Click to change photo</div>
            </div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>First Name *</label>
            <input type="text" name="first_name" id="edit_cfn" class="form-control" required>
          </div>
          <div class="form-group">
            <label>Last Name *</label>
            <input type="text" name="last_name" id="edit_cln" class="form-control" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" id="edit_cem" class="form-control">
          </div>
          <div class="form-group">
            <label>Phone / GCash</label>
            <input type="text" name="phone" id="edit_cph" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label>Address</label>
          <input type="text" name="address" id="edit_caddr" class="form-control">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Primary Position</label>
            <select name="primary_position_id" id="edit_cpos" class="form-control">
              <option value="">— Select —</option>
              @foreach ($positions as $p)
              <option value="{{ $p->position_id }}">{{ $p->position_name }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group">
            <label>Employment Type</label>
            <select name="employment_type" id="edit_cet" class="form-control"
                    onchange="toggleRates(this.value,'edit')">
              <option value="freelance">Freelance (Day Rate)</option>
              <option value="staff">Staff (Monthly Salary)</option>
              <option value="on_call">On Call</option>
            </select>
          </div>
        </div>
        <div id="edit_freelance_rates">
          <div class="form-row cols-3">
            <div class="form-group">
              <label>Base Rate (₱/12hr)</label>
              <input type="number" name="base_rate_12hr" id="edit_cr12" class="form-control" step="0.01" min="0">
            </div>
            <div class="form-group">
              <label>Overtime Rate</label>
              <input type="number" name="overtime_rate" id="edit_crot" class="form-control" step="0.01" min="0">
            </div>
            <div class="form-group">
              <label>Double Pay Rate</label>
              <input type="number" name="double_pay_rate" id="edit_crdp" class="form-control" step="0.01" min="0">
            </div>
          </div>
        </div>
        <div id="edit_staff_rate" style="display:none">
          <div class="form-group">
            <label>Monthly Salary (₱)</label>
            <input type="number" name="monthly_salary" id="edit_crms" class="form-control" step="0.01" min="0">
          </div>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select name="status" id="edit_cst" class="form-control">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="blacklisted">Blacklisted</option>
          </select>
        </div>
        <div class="form-group">
          <label>Skills / Notes</label>
          <textarea name="profile_notes" id="edit_cnotes" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">
          <i data-feather="save"></i> Update
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ADD POSITION MODAL -->
<div class="modal-overlay" id="modalAddPos">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <h3 class="modal-title">
        <i data-feather="briefcase" style="width:17px;height:17px;margin-right:8px;vertical-align:middle"></i>
        Add Position
      </h3>
      <button class="modal-close" data-modal-close><i data-feather="x"></i></button>
    </div>
    <form method="POST" action="{{ $crewBase }}">
      @csrf
      <input type="hidden" name="action" value="add_position">
      <div class="modal-body">
        <div class="form-group">
          <label>Position Name *</label>
          <input type="text" name="position_name" class="form-control"
                 placeholder="e.g. Drone Operator, DIT" required>
        </div>
        <div class="form-group">
          <label>Department</label>
          <select name="department" class="form-control">
            <option value="Camera">Camera</option>
            <option value="Lighting">Lighting</option>
            <option value="Grip">Grip</option>
            <option value="Audio">Audio</option>
            <option value="Production">Production</option>
            <option value="Logistics / Transport">Logistics / Transport</option>
            <option value="Other">Other</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary">
          <i data-feather="save"></i> Add Position
        </button>
      </div>
    </form>
  </div>
</div>

@endif

<!-- Unavailability Modal -->
<div class="modal-overlay" id="modalAvailability">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <h3><i data-feather="calendar-x" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Unavailability — <span id="avail_crew_name"></span></h3>
      <button class="modal-close" onclick="closeModal('modalAvailability')">&times;</button>
    </div>
    <div class="modal-body">
      <div id="avail_blocks_list" style="margin-bottom:16px"></div>
      <div style="border-top:1px solid var(--border);padding-top:14px">
        <div style="font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:10px">Add Blocked Dates</div>
        <form method="POST" action="{{ $crewBase }}">
          @csrf
          <input type="hidden" name="action" value="add_unavailability">
          <input type="hidden" name="crew_id" id="avail_crew_id">
          <div class="form-row">
            <div class="form-group" style="margin-bottom:10px">
              <label style="font-size:.75rem">From</label>
              <input type="date" name="date_from" class="form-control" required>
            </div>
            <div class="form-group" style="margin-bottom:10px">
              <label style="font-size:.75rem">To</label>
              <input type="date" name="date_to" class="form-control" required>
            </div>
          </div>
          <div class="form-group" style="margin-bottom:10px">
            <label style="font-size:.75rem">Reason Category <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
            <select name="reason_category" class="form-control">
              <option value="">— Not specified —</option>
              <option value="Personal">Personal</option>
              <option value="Existing Commitment">Existing Commitment</option>
              <option value="Leave">Leave</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:10px">
            <label style="font-size:.75rem">Reason <span style="font-weight:400;color:var(--muted)">(optional, shown to scheduling staff)</span></label>
            <input type="text" name="reason" class="form-control" placeholder="e.g. Vacation, Medical leave…">
          </div>
          <div class="form-group" style="margin-bottom:10px">
            <label style="font-size:.75rem">Internal Note <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
            <input type="text" name="internal_note" class="form-control" placeholder="Not shown to the crew member">
          </div>
          <div class="modal-footer" style="padding:0;border:0;margin-top:4px">
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('modalAvailability')">Cancel</button>
            <button type="submit" class="btn btn-primary btn-sm"><i data-feather="plus" style="width:13px;height:13px;margin-right:3px;vertical-align:middle"></i>Add Block</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Link Login modal -->
<div class="modal-overlay" id="modalLinkCrew">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <h3><i data-feather="user-plus" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Link Login — <span id="link_crew_name"></span></h3>
      <button class="modal-close" onclick="closeModal('modalLinkCrew')">&times;</button>
    </div>
    <form method="POST" action="{{ $crewBase }}">
      @csrf
      <input type="hidden" name="action" value="link_crew_account">
      <input type="hidden" name="crew_id" id="link_crew_id">
      <div class="modal-body">
        <p style="font-size:.83rem;color:var(--muted);margin-bottom:14px">Creates a login for this crew member so they can see their own bookings and file incident reports from their phone. A temporary password is generated — you'll see it once after creating the account, to hand over directly.</p>
        <div class="form-group">
          <label>Email Address *</label>
          <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('modalLinkCrew')">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm"><i data-feather="user-plus" style="width:13px;height:13px;margin-right:3px;vertical-align:middle"></i>Create Login</button>
      </div>
    </form>
  </div>
</div>

<!-- Unlink Login modal -->
<div class="modal-overlay" id="modalUnlinkCrew">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <h3><i data-feather="user-check" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Linked Login — <span id="unlink_crew_name"></span></h3>
      <button class="modal-close" onclick="closeModal('modalUnlinkCrew')">&times;</button>
    </div>
    <form method="POST" action="{{ $crewBase }}">
      @csrf
      <input type="hidden" name="action" value="unlink_crew_account">
      <input type="hidden" name="crew_id" id="unlink_crew_id">
      <div class="modal-body">
        <p style="font-size:.83rem;color:var(--sub)">Logged in as <strong id="unlink_crew_email"></strong>.</p>
        <p style="font-size:.83rem;color:var(--muted);margin-top:8px">Unlinking removes their access to the crew portal — the account itself isn't deleted, so you can link them (or someone else) again later.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('modalUnlinkCrew')">Cancel</button>
        <button type="submit" class="btn btn-sm" style="border:1px solid var(--red);color:var(--red);background:none">Unlink</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
const CREW_ASSET_BASE = "{{ asset('storage') }}";

@if ($tab === 'schedule')
// ── Crew Schedule calendar — same day-map/grid approach as the Dashboard calendar ──────────
const CREW_SCHED_ASSIGNMENTS = {!! $scheduleAssignments->map(fn ($a) => [
  'crew_name' => $a->crew_name,
  'ref' => $a->booking_reference,
  'title' => $a->project_title ?: $a->booking_reference,
  'start' => $a->shoot_date_start,
  'end' => $a->shoot_date_end,
  'status' => $a->booking_status,
])->values()->toJson() !!};

const CREW_MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
let crewSchedYear  = {{ (int) \Carbon\Carbon::parse($scheduleMonth . '-01')->format('Y') }};
let crewSchedMonth = {{ (int) \Carbon\Carbon::parse($scheduleMonth . '-01')->format('n') }};

function crewBuildDayMap() {
  const map = {};
  CREW_SCHED_ASSIGNMENTS.forEach(a => {
    const start = new Date(a.start + 'T00:00:00');
    const end   = new Date(a.end   + 'T00:00:00');
    for (let d = new Date(start); d <= end; d.setDate(d.getDate()+1)) {
      const key = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
      if (!map[key]) map[key] = [];
      map[key].push(a);
    }
  });
  return map;
}

function crewRenderSchedule() {
  const today = new Date();
  const todayStr = today.getFullYear()+'-'+String(today.getMonth()+1).padStart(2,'0')+'-'+String(today.getDate()).padStart(2,'0');
  const firstOfMonth = new Date(crewSchedYear, crewSchedMonth-1, 1);
  const daysInMonth  = new Date(crewSchedYear, crewSchedMonth, 0).getDate();
  const startDow     = firstOfMonth.getDay();
  const totalCells   = Math.ceil((startDow + daysInMonth) / 7) * 7;
  const dayMap       = crewBuildDayMap();

  document.getElementById('crewSchedTitle').textContent = CREW_MONTHS[crewSchedMonth-1] + ' ' + crewSchedYear;

  const grid = document.getElementById('crewSchedGrid');
  grid.innerHTML = '';
  for (let i = 0; i < totalCells; i++) {
    const dayOffset = i - startDow + 1;
    const isCurrentMonth = dayOffset >= 1 && dayOffset <= daysInMonth;
    const cellDate = new Date(crewSchedYear, crewSchedMonth-1, dayOffset);
    const dateStr = cellDate.getFullYear()+'-'+String(cellDate.getMonth()+1).padStart(2,'0')+'-'+String(cellDate.getDate()).padStart(2,'0');
    const isToday = dateStr === todayStr;
    const events = dayMap[dateStr] || [];

    const cell = document.createElement('div');
    cell.style.cssText = 'background:#fff;min-height:80px;padding:6px;' + (!isCurrentMonth ? 'background:var(--s2);opacity:.5;' : '') + (isToday ? 'box-shadow:inset 0 0 0 2px var(--accent);' : '');

    const dayNum = document.createElement('div');
    dayNum.style.cssText = 'font-size:11px;font-weight:600;margin-bottom:3px';
    dayNum.textContent = cellDate.getDate();
    cell.appendChild(dayNum);

    const uniqueCrew = [...new Set(events.map(e => e.crew_name))];
    uniqueCrew.slice(0, 3).forEach(name => {
      const pill = document.createElement('div');
      pill.style.cssText = 'font-size:9px;font-weight:600;border-radius:3px;padding:2px 5px;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;background:#e0f2fe;color:#0369a1';
      pill.textContent = name;
      cell.appendChild(pill);
    });
    if (uniqueCrew.length > 3) {
      const more = document.createElement('div');
      more.style.cssText = 'font-size:9px;color:var(--muted)';
      more.textContent = '+' + (uniqueCrew.length - 3) + ' more';
      cell.appendChild(more);
    }
    grid.appendChild(cell);
  }
}

function crewSchedPrev()  { if (crewSchedMonth===1){crewSchedMonth=12;crewSchedYear--;}else{crewSchedMonth--;} crewRenderSchedule(); }
function crewSchedNext()  { if (crewSchedMonth===12){crewSchedMonth=1;crewSchedYear++;}else{crewSchedMonth++;} crewRenderSchedule(); }
function crewSchedToday() { const t=new Date(); crewSchedYear=t.getFullYear(); crewSchedMonth=t.getMonth()+1; crewRenderSchedule(); }

document.addEventListener('DOMContentLoaded', crewRenderSchedule);
@endif

function toggleRates(val, prefix) {
  var showStaff = val === 'staff';
  document.getElementById(prefix + '_freelance_rates').style.display = showStaff ? 'none'  : 'block';
  document.getElementById(prefix + '_staff_rate').style.display      = showStaff ? 'block' : 'none';
}

function editCrew(cm) {
  var preview = document.getElementById('editCrewImgPreview');
  var ph      = document.getElementById('editCrewImgPlaceholder');
  var img     = document.getElementById('editCrewImgCurrent');
  if (cm.photo_path && preview) {
    img.src = CREW_ASSET_BASE + '/' + cm.photo_path;
    preview.style.display = 'block';
    if (ph) ph.style.display = 'none';
  } else if (preview) {
    preview.style.display = 'none';
    if (ph) ph.style.display = 'block';
  }

  document.getElementById('edit_cid').value    = cm.crew_id;
  document.getElementById('edit_cfn').value    = cm.first_name;
  document.getElementById('edit_cln').value    = cm.last_name;
  document.getElementById('edit_cem').value    = cm.email          || '';
  document.getElementById('edit_cph').value    = cm.phone          || '';
  document.getElementById('edit_caddr').value  = cm.address        || '';
  document.getElementById('edit_cpos').value   = cm.primary_position_id || '';
  document.getElementById('edit_cet').value    = cm.employment_type;
  document.getElementById('edit_cr12').value   = cm.base_rate_12hr;
  document.getElementById('edit_crot').value   = cm.overtime_rate;
  document.getElementById('edit_crdp').value   = cm.double_pay_rate;
  document.getElementById('edit_crms').value   = cm.monthly_salary;
  document.getElementById('edit_cst').value    = cm.status;
  document.getElementById('edit_cnotes').value = cm.profile_notes  || '';

  toggleRates(cm.employment_type, 'edit');
  openModal('modalEditCrew');
}

function openLinkModal(crewId, crewName) {
  document.getElementById('link_crew_id').value = crewId;
  document.getElementById('link_crew_name').textContent = crewName;
  openModal('modalLinkCrew');
}

function openUnlinkModal(crewId, crewName, email) {
  document.getElementById('unlink_crew_id').value = crewId;
  document.getElementById('unlink_crew_name').textContent = crewName;
  document.getElementById('unlink_crew_email').textContent = email;
  openModal('modalUnlinkCrew');
}

const _allBlocks = @json($allUpcomingBlocks);

function openAvailModal(crewId, crewName) {
  document.getElementById('avail_crew_id').value = crewId;
  document.getElementById('avail_crew_name').textContent = crewName;
  renderAvailBlocks(crewId);
  openModal('modalAvailability');
  if (typeof feather !== 'undefined') feather.replace();
}

function renderAvailBlocks(crewId) {
  const list   = document.getElementById('avail_blocks_list');
  const blocks = _allBlocks.filter(b => parseInt(b.crew_id) === parseInt(crewId));
  if (!blocks.length) {
    list.innerHTML = '<div style="padding:12px;font-size:.82rem;color:var(--muted);text-align:center">No upcoming blocked dates</div>';
    return;
  }
  list.innerHTML = blocks.map(b =>
    '<div style="display:flex;align-items:center;gap:10px;padding:8px 12px;border:1px solid var(--border);border-radius:6px;margin-bottom:6px">'
    + '<div style="flex:1">'
    + '<div style="font-weight:600;font-size:.83rem">' + _escAvail(b.date_from) + ' – ' + _escAvail(b.date_to)
    + (b.reason_category ? ' <span style="font-size:.65rem;font-weight:700;color:var(--accent);background:var(--s2);border-radius:8px;padding:1px 7px;margin-left:4px">' + _escAvail(b.reason_category) + '</span>' : '')
    + '</div>'
    + (b.reason ? '<div style="font-size:.72rem;color:var(--muted);margin-top:2px">' + _escAvail(b.reason) + '</div>' : '')
    + '</div>'
    + '<form method="POST" action="{{ $crewBase }}" style="display:inline" onsubmit="return confirm(\'Remove this block?\')">'
    + '@csrf'
    + '<input type="hidden" name="action" value="remove_unavailability">'
    + '<input type="hidden" name="unavailability_id" value="' + b.unavailability_id + '">'
    + '<button type="submit" class="btn btn-sm" style="border:1px solid var(--red);color:var(--red);background:none;padding:4px 8px;border-radius:5px;cursor:pointer">'
    + '<i data-feather="x" style="width:12px;height:12px"></i>'
    + '</button>'
    + '</form>'
    + '</div>'
  ).join('');
  if (typeof feather !== 'undefined') feather.replace();
}

function _escAvail(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
@endpush
@endsection
