@extends('layouts.app')

@section('pageTitle', 'Super Admin Panel')

@section('breadcrumb')
<span>Super Admin</span>
@endsection

@section('topbarActions')
<span class="badge badge-purple" style="padding:5px 12px;font-size:.75rem">Super Admin Mode</span>
@endsection

@section('content')
@php
  $adminBase = route('superadmin');
  $permJson = json_encode($permissions);
  $modJson = json_encode($moduleLabels);
@endphp

@if ($msg)
<div class="alert alert-{{ $msg['type'] }}" data-autohide>
  <i data-feather="{{ $msg['type'] === 'success' ? 'check-circle' : 'alert-circle' }}"></i>
  {!! $msg['text'] !!}
</div>
@endif

<!-- KPI row -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:22px">
  <div class="stat-card" style="--sb:#7c3aed">
    <div class="stat-icon" style="background:#f3e8ff;color:#7c3aed"><i data-feather="shield"></i></div>
    <div class="stat-value">{{ $stats['total_users'] }}</div>
    <div class="stat-label">Total Accounts</div>
    <span class="stat-delta up"><i data-feather="user-check"></i> {{ $stats['active_users'] }} active</span>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="users"></i></div>
    <div class="stat-value">{{ $stats['admins'] }}</div>
    <div class="stat-label">Admin Accounts</div>
    <span class="stat-delta up"><i data-feather="star"></i> {{ $stats['super_admins'] }} super admin</span>
  </div>
  <div class="stat-card green">
    <div class="stat-icon" style="background:#f0fdf4;color:#16a34a"><i data-feather="activity"></i></div>
    <div class="stat-value">{{ $activeSessions->count() }}</div>
    <div class="stat-label">Active Now (2hr)</div>
  </div>
  <div class="stat-card orange">
    <div class="stat-icon"><i data-feather="clock"></i></div>
    <div class="stat-value">{{ $recentActivity->count() }}</div>
    <div class="stat-label">Recent Actions</div>
  </div>
</div>

<!-- TABS -->
<div class="tabs" style="margin-bottom:22px">
  <button class="tab-btn active" data-tab="tab-users">User Management</button>
  <button class="tab-btn" data-tab="tab-roles">Role Permissions</button>
  <button class="tab-btn" data-tab="tab-activity">System Activity</button>
  <button class="tab-btn" data-tab="tab-settings">Backup and Restore</button>
</div>

<div data-tab-panes>

<!-- USERS TAB -->
<div id="tab-users" class="tab-pane active">
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">All System Accounts <span class="badge badge-blue" style="margin-left:8px" id="userCountBadge">{{ $users->count() }}</span></h2>
      <button onclick="openModal('modalAddUser')" class="btn btn-primary btn-sm">
        <i data-feather="user-plus"></i> Add Account
      </button>
    </div>

    <div style="display:flex;gap:10px;padding:12px 16px;border-bottom:1px solid var(--border);flex-wrap:wrap;align-items:center;background:var(--s2)">
      <div style="position:relative;flex:1;min-width:180px">
        <i data-feather="search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:14px;height:14px;color:var(--muted);pointer-events:none"></i>
        <input type="text" id="userSearch" placeholder="Search by name or email…"
               oninput="filterUsers()"
               style="width:100%;padding:7px 10px 7px 32px;border:1px solid var(--border);border-radius:7px;font-size:13px;background:var(--surface);color:var(--text);outline:none;font-family:inherit">
      </div>
      <select id="userRoleFilter" onchange="filterUsers()"
              style="padding:7px 12px;border:1px solid var(--border);border-radius:7px;font-size:13px;background:var(--surface);color:var(--text);outline:none;font-family:inherit;min-width:150px">
        <option value="">All Roles</option>
        @foreach ($rolesDef as $rn => $rd)
        @continue($rn === 'client')
        <option value="{{ $rn }}">{{ $rd['label'] }}</option>
        @endforeach
      </select>
      <select id="userStatusFilter" onchange="filterUsers()"
              style="padding:7px 12px;border:1px solid var(--border);border-radius:7px;font-size:13px;background:var(--surface);color:var(--text);outline:none;font-family:inherit;min-width:130px">
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
      <button onclick="clearUserFilters()" id="clearUserFilters"
              style="display:none;padding:7px 12px;border:1px solid var(--border);border-radius:7px;font-size:12px;background:var(--surface);color:var(--muted);cursor:pointer;font-family:inherit">
        Clear
      </button>
    </div>

    <div id="userNoResults" style="display:none;padding:32px;text-align:center;color:var(--muted);font-size:.875rem">
      <i data-feather="search" style="width:28px;height:28px;display:block;margin:0 auto 10px;opacity:.35"></i>
      No accounts match your search.
    </div>

    <div class="table-wrap">
      <table id="usersTable">
        <thead>
          <tr>
            <th>User</th><th>Email</th><th>Role</th><th>Last Login</th>
            <th>Actions</th><th>Status</th><th style="text-align:right">Manage</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($users as $u)
          @php
            $rd = $rolesDef[$u->role_name] ?? ['color' => '6b7280', 'label' => ucfirst($u->role_name)];
            $isSelf = (int) $u->user_id === (int) auth()->id();
          @endphp
          <tr style="{{ ! $u->is_active ? 'opacity:.5' : '' }}"
              data-name="{{ strtolower($u->first_name . ' ' . $u->last_name) }}"
              data-email="{{ strtolower($u->email) }}"
              data-role="{{ $u->role_name }}"
              data-status="{{ $u->is_active ? 'active' : 'inactive' }}">
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#{{ $rd['color'] }},#{{ $rd['color'] }}aa);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;color:white;flex-shrink:0">
                  {{ strtoupper(substr($u->first_name, 0, 1)) }}
                </div>
                <div>
                  <div style="font-weight:600;font-size:.875rem">
                    {{ $u->first_name . ' ' . $u->last_name }}
                    @if ($isSelf)<span class="badge badge-purple" style="font-size:.6rem;margin-left:4px">YOU</span>@endif
                  </div>
                  <div style="font-size:.72rem;color:var(--muted)">{{ $u->phone ?? '—' }}</div>
                </div>
              </div>
            </td>
            <td style="font-size:.83rem;color:var(--muted)">{{ $u->email }}</td>
            <td>
              <span class="badge" style="background:#{{ $rd['color'] }}22;color:#{{ $rd['color'] }};border:1px solid #{{ $rd['color'] }}44">
                {{ $rd['label'] }}
              </span>
            </td>
            <td style="font-size:.78rem;color:var(--muted)">
              {{ $u->last_login ? \Illuminate\Support\Carbon::parse($u->last_login)->format('M j, Y g:i a') : '—' }}
            </td>
            <td>
              <span class="badge badge-blue">{{ number_format($u->action_count) }}</span>
            </td>
            <td>
              <form method="POST" action="{{ $adminBase }}" style="display:inline">
                @csrf
                <input type="hidden" name="action" value="toggle_user">
                <input type="hidden" name="user_id" value="{{ $u->user_id }}">
                <input type="hidden" name="is_active" value="{{ $u->is_active ? 0 : 1 }}">
                <button type="submit" class="badge {{ $u->is_active ? 'badge-green' : 'badge-red' }}"
                        style="border:none;cursor:pointer;font-size:.72rem"
                        {{ $isSelf ? 'disabled title="Cannot deactivate yourself"' : '' }}>
                  {{ $u->is_active ? 'Active' : 'Inactive' }}
                </button>
              </form>
            </td>
            <td style="text-align:right">
              <div style="display:flex;gap:5px;justify-content:flex-end">
                <button class="btn btn-outline btn-sm" onclick='editUser(@json($u))'>
                  <i data-feather="edit-2"></i> Edit
                </button>
                <button class="btn btn-outline btn-sm" onclick="resetPw({{ $u->user_id }}, '{{ addslashes($u->first_name . ' ' . $u->last_name) }}')">
                  <i data-feather="key"></i>
                </button>
                @if (! $isSelf && $u->role_name !== 'super_admin')
                <form method="POST" action="{{ $adminBase }}" style="display:inline" onsubmit="return confirm('Deactivate this user?')">
                  @csrf
                  <input type="hidden" name="action" value="delete_user">
                  <input type="hidden" name="user_id" value="{{ $u->user_id }}">
                  <button type="submit" class="btn btn-danger btn-sm"><i data-feather="trash-2"></i></button>
                </form>
                @endif
              </div>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ROLE PERMISSIONS TAB -->
<div id="tab-roles" class="tab-pane">
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Role Access Matrix</h2>
      <span style="font-size:.8rem;color:var(--muted)">Select a role to see its full permission checklist</span>
    </div>

    <div class="role-pills">
      @foreach ($rolesDef as $rn => $rd)
      <button type="button" class="role-pill {{ $loop->first ? 'active' : '' }}"
              data-role-target="role-panel-{{ $rn }}" data-color="{{ $rd['color'] }}"
              style="{{ $loop->first ? 'background:#'.$rd['color'].';border-color:transparent;color:#fff' : '' }}"
              onclick="switchRolePanel(this)">
        <span class="role-pill-dot" style="background:{{ $loop->first ? '#fff' : '#'.$rd['color'] }}"></span>{{ $rd['label'] }}
      </button>
      @endforeach
    </div>

    @foreach ($rolesDef as $rn => $rd)
    @php
      $roleUserCount = $users->where('role_name', $rn)->count();
      $rolePerms = $permissions[$rn] ?? [];
      $isPortalOnly = in_array($rn, ['client', 'crew'], true);
    @endphp
    <div class="role-panel {{ $loop->first ? 'active' : '' }}" id="role-panel-{{ $rn }}">
      <div class="role-banner" style="background:#{{ $rd['color'] }}14">
        <div class="role-banner-who">
          <div class="role-avatar" style="background:#{{ $rd['color'] }}">{{ mb_substr($rd['label'], 0, 1) }}</div>
          <div>
            <div class="role-banner-name">{{ $rd['label'] }}</div>
            <div class="role-banner-desc">{{ $rd['desc'] }}</div>
          </div>
        </div>
        <span class="role-banner-stat">{{ $roleUserCount }} user{{ $roleUserCount === 1 ? '' : 's' }}</span>
      </div>

      @if ($isPortalOnly)
      <div class="portal-single">
        <strong>{{ $moduleLabels[$rolePerms[0] ?? ''] ?? 'Portal' }}</strong>
        This role only ever reaches one route — there's no module checklist to show.
      </div>
      @else
      <div class="checklist-groups">
        @foreach ($moduleGroups as $groupName => $mods)
        <div class="cl-group">
          <h4>{{ $groupName }}</h4>
          <div class="cl-items">
            @foreach ($mods as $mod)
            @php $has = in_array($mod, $rolePerms, true); @endphp
            <div class="cl-item {{ $has ? '' : 'off' }}">
              <span class="cl-ico" style="{{ $has ? 'background:#'.$rd['color'].'22;color:#'.$rd['color'] : '' }}">
                @if ($has)<i data-feather="check"></i>@else &ndash; @endif
              </span>
              {{ $moduleLabels[$mod] ?? $mod }}
            </div>
            @endforeach
          </div>
        </div>
        @endforeach
      </div>
      @endif
    </div>
    @endforeach
  </div>
</div>

<!-- SYSTEM ACTIVITY TAB -->
<div id="tab-activity" class="tab-pane">
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">All System Activity <span style="font-size:.78rem;color:var(--muted);font-weight:400;margin-left:8px">Last 20 actions across all users</span></h2>
      <a href="{{ route('activity') }}" class="btn btn-outline btn-sm"><i data-feather="list"></i> Full Log</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Time</th><th>User</th><th>Role</th><th>Action</th><th>Module</th><th>Description</th></tr>
        </thead>
        <tbody>
          @php $actBadges = ['login' => 'badge-green', 'logout' => 'badge-gray', 'create' => 'badge-blue', 'update' => 'badge-yellow', 'delete' => 'badge-red', 'payment' => 'badge-purple']; @endphp
          @foreach ($recentActivity as $act)
          @php $actColor = $actBadges[$act->action] ?? 'badge-gray'; @endphp
          <tr>
            <td style="font-size:.75rem;color:var(--muted);white-space:nowrap">
              {{ \Illuminate\Support\Carbon::parse($act->created_at)->format('M j, g:i a') }}
            </td>
            <td style="font-weight:600;font-size:.875rem">{{ $act->user_name ?? 'System' }}</td>
            <td>
              @if ($act->role_name)
              @php $r = $rolesDef[$act->role_name] ?? ['color' => '6b7280', 'label' => $act->role_name]; @endphp
              <span class="badge" style="background:#{{ $r['color'] }}22;color:#{{ $r['color'] }};font-size:.68rem">{{ $r['label'] }}</span>
              @endif
            </td>
            <td><span class="badge {{ $actColor }}">{{ $act->action }}</span></td>
            <td style="font-size:.8rem;text-transform:capitalize">{{ $act->module ?? '—' }}</td>
            <td style="font-size:.8rem;color:var(--muted);max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              {{ $act->description ?? '—' }}
            </td>
          </tr>
          @endforeach
          @if ($recentActivity->isEmpty())
          <tr><td colspan="6"><div class="empty-state" style="padding:30px 0"><i data-feather="activity"></i><h3>No activity yet</h3></div></td></tr>
          @endif
        </tbody>
      </table>
    </div>
  </div>

  <!-- Active sessions -->
  <div class="card" style="margin-top:22px">
    <div class="card-header">
      <h2 class="card-title">Recently Active Users (last 2 hours)</h2>
    </div>
    <div class="card-body">
      @if ($activeSessions->isEmpty())
      <p style="color:var(--muted);font-size:.875rem">No recent sessions.</p>
      @else
      <div style="display:flex;flex-wrap:wrap;gap:10px">
        @foreach ($activeSessions as $sess)
        @php $r = $rolesDef[$sess->role_name] ?? ['color' => '6b7280', 'label' => $sess->role_name]; @endphp
        <div style="background:var(--s2);border:1px solid var(--border);border-radius:var(--radius-md);padding:10px 14px;display:flex;align-items:center;gap:10px">
          <div style="width:8px;height:8px;border-radius:50%;background:#16a34a;animation:pulse 2s infinite"></div>
          <div>
            <div style="font-weight:600;font-size:.875rem">{{ $sess->user_name }}</div>
            <div style="font-size:.72rem;color:var(--muted)">{{ $r['label'] }} · last active {{ \Illuminate\Support\Carbon::parse($sess->last_active)->format('g:i a') }}</div>
          </div>
        </div>
        @endforeach
      </div>
      @endif
    </div>
  </div>
</div>

<!-- BACKUP AND RESTORE TAB -->
<div id="tab-settings" class="tab-pane">

  <div class="card" style="margin-bottom:20px">
    <div class="card-header"><h3 class="card-title">Database Backup</h3></div>
    <div class="card-body">
      <p style="color:var(--muted);font-size:13px;margin-bottom:14px">Downloads a full .sql dump of the live database. Read-only — safe to run any time.</p>
      <form method="POST" action="{{ route('superadmin.backup') }}">
        @csrf
        <button type="submit" class="btn btn-primary btn-sm"><i data-feather="download"></i> Download Backup</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h3 class="card-title" style="color:var(--red)">Database Restore</h3></div>
    <div class="card-body">
      <p style="color:var(--muted);font-size:13px;margin-bottom:14px">
        <strong style="color:var(--red)">Destructive.</strong> Replaces all current data with the contents of the uploaded .sql file. This cannot be undone — download a fresh backup above first.
      </p>
      <form method="POST" action="{{ $adminBase }}" enctype="multipart/form-data" onsubmit="return confirm('This will permanently overwrite the live database with the uploaded file. Are you absolutely sure?')">
        @csrf
        <input type="hidden" name="action" value="restore_db">
        <div class="form-group"><label>SQL File *</label><input type="file" name="sql_file" accept=".sql" class="form-control" required></div>
        <div class="form-group">
          <label>Type <strong>RESTORE</strong> (all caps) to confirm *</label>
          <input type="text" name="confirm_phrase" id="supaRestorePhrase" class="form-control" placeholder="RESTORE" required autocomplete="off" oninput="document.getElementById('supaRestoreBtn').disabled = (this.value !== 'RESTORE')">
        </div>
        <button type="submit" class="btn btn-danger btn-sm" id="supaRestoreBtn" disabled><i data-feather="upload"></i> Restore Database</button>
      </form>
    </div>
  </div>

</div>

</div><!-- /tab-panes -->

<!-- ADD USER MODAL -->
<div class="modal-overlay" id="modalAddUser">
  <div class="modal" style="max-width:560px">
    <div class="modal-header">
      <h3 class="modal-title"><i data-feather="user-plus" style="width:17px;height:17px;margin-right:8px;vertical-align:middle"></i>Create New Account</h3>
      <button class="modal-close" data-modal-close><i data-feather="x"></i></button>
    </div>
    <form method="POST" action="{{ $adminBase }}">
      @csrf
      <input type="hidden" name="action" value="add_user">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label>First Name *</label><input type="text" name="first_name" class="form-control" required></div>
          <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" class="form-control" required></div>
        </div>
        <div class="form-group"><label>Email Address *</label><input type="email" name="email" class="form-control" placeholder="user@filmspec.ph" required></div>
        <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control" placeholder="+63 9XX XXX XXXX"></div>
        <div class="form-group">
          <label>Role *</label>
          <select name="role_name" class="form-control" required id="addRoleSelect" onchange="updateRoleDesc(this,'addRoleDesc');document.getElementById('crewMemberLinkWrap').style.display=(this.value==='crew')?'':'none'">
            @foreach ($rolesDef as $rn => $rd)
            @continue($rn === 'client')
            <option value="{{ $rn }}" data-desc="{{ $rd['desc'] }}">{{ $rd['label'] }}</option>
            @endforeach
          </select>
          <div id="addRoleDesc" style="font-size:.78rem;color:var(--muted);margin-top:5px;padding:6px 10px;background:var(--s2);border-radius:5px"></div>
        </div>
        <div class="form-group">
          <label>Password * (min 6 characters)</label>
          <input type="password" name="password" class="form-control" minlength="6" required>
        </div>

        <div class="form-group" id="crewMemberLinkWrap" style="display:none">
          <label>Link to Crew Member <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
          <select name="crew_member_id" class="form-control">
            <option value="">— Don't link yet —</option>
            @foreach ($unlinkedCrewMembers as $cm)
            <option value="{{ $cm->crew_id }}">{{ $cm->last_name . ', ' . $cm->first_name }}{{ $cm->position_name ? ' — ' . $cm->position_name : '' }}</option>
            @endforeach
          </select>
          <div style="font-size:.75rem;color:var(--muted);margin-top:4px">Links this login account to a crew member record so they can file incident reports.</div>
        </div>

        <div style="background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2);border-radius:var(--radius-md);padding:12px;margin-top:4px">
          <div style="font-size:.75rem;font-weight:700;color:var(--accent);margin-bottom:8px;text-transform:uppercase;letter-spacing:.06em">Access Preview</div>
          <div style="display:flex;flex-wrap:wrap;gap:4px" id="addAccessPreview"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Create Account</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT USER MODAL -->
<div class="modal-overlay" id="modalEditUser">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <h3 class="modal-title"><i data-feather="edit-2" style="width:17px;height:17px;margin-right:8px;vertical-align:middle"></i>Edit Account</h3>
      <button class="modal-close" data-modal-close><i data-feather="x"></i></button>
    </div>
    <form method="POST" action="{{ $adminBase }}">
      @csrf
      <input type="hidden" name="action" value="edit_user">
      <input type="hidden" name="user_id" id="edit_uid">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label>First Name *</label><input type="text" name="first_name" id="edit_fn" class="form-control" required></div>
          <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" id="edit_ln" class="form-control" required></div>
        </div>
        <div class="form-group">
          <label>Email <span style="color:var(--muted);font-weight:400">(read-only)</span></label>
          <input type="text" id="edit_em" class="form-control" disabled style="background:var(--bg)">
        </div>
        <div class="form-group"><label>Phone</label><input type="text" name="phone" id="edit_ph" class="form-control"></div>
        <div class="form-group">
          <label>Role *</label>
          <select name="role_name" id="edit_role" class="form-control" required onchange="updateRoleDesc(this,'editRoleDesc')">
            @foreach ($rolesDef as $rn => $rd)
            <option value="{{ $rn }}" data-desc="{{ $rd['desc'] }}">{{ $rd['label'] }}</option>
            @endforeach
          </select>
          <div id="editRoleDesc" style="font-size:.78rem;color:var(--muted);margin-top:5px;padding:6px 10px;background:var(--s2);border-radius:5px"></div>
        </div>
        <div class="form-group">
          <label>Account Status</label>
          <select name="is_active" id="edit_active" class="form-control">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- RESET PASSWORD MODAL -->
<div class="modal-overlay" id="modalResetPw">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <h3 class="modal-title"><i data-feather="key" style="width:17px;height:17px;margin-right:8px;vertical-align:middle"></i>Reset Password</h3>
      <button class="modal-close" data-modal-close><i data-feather="x"></i></button>
    </div>
    <form method="POST" action="{{ $adminBase }}">
      @csrf
      <input type="hidden" name="action" value="reset_pw">
      <input type="hidden" name="user_id" id="resetUid">
      <div class="modal-body">
        <p style="font-size:.875rem;color:var(--sub);margin-bottom:16px">
          Resetting password for: <strong id="resetName"></strong>
        </p>
        <div class="form-group">
          <label>New Password *</label>
          <input type="password" name="new_password" class="form-control" minlength="6" required placeholder="Min 6 characters">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-danger"><i data-feather="key"></i> Reset Password</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
const PERMS = @json($permissions);
const MODS  = @json($moduleLabels);

function editUser(u) {
  document.getElementById('edit_uid').value    = u.user_id;
  document.getElementById('edit_fn').value     = u.first_name;
  document.getElementById('edit_ln').value     = u.last_name;
  document.getElementById('edit_em').value     = u.email;
  document.getElementById('edit_ph').value     = u.phone || '';
  document.getElementById('edit_role').value   = u.role_name;
  document.getElementById('edit_active').value = u.is_active;
  updateRoleDesc(document.getElementById('edit_role'), 'editRoleDesc');
  openModal('modalEditUser');
}

function resetPw(uid, name) {
  document.getElementById('resetUid').value  = uid;
  document.getElementById('resetName').textContent = name;
  openModal('modalResetPw');
}

// Role Access Matrix — switches the visible role-panel without touching the top-level
// .tab-pane system (that one's handled globally in app.js and toggles ALL .tab-pane
// elements on the page, which would fight a naive reuse of the same classes here).
function switchRolePanel(btn) {
  const pills = btn.closest('.role-pills');
  pills.querySelectorAll('.role-pill').forEach(p => {
    p.classList.remove('active');
    p.style.background = '';
    p.style.borderColor = '';
    p.style.color = '';
    const dot = p.querySelector('.role-pill-dot');
    if (dot) dot.style.background = '#' + p.dataset.color;
  });
  btn.classList.add('active');
  btn.style.background = '#' + btn.dataset.color;
  btn.style.borderColor = 'transparent';
  btn.style.color = '#fff';
  const activeDot = btn.querySelector('.role-pill-dot');
  if (activeDot) activeDot.style.background = '#fff';

  const target = btn.dataset.roleTarget;
  document.querySelectorAll('.role-panel').forEach(p => {
    p.classList.toggle('active', p.id === target);
  });
}

function updateRoleDesc(sel, descId) {
  const opt  = sel.options[sel.selectedIndex];
  const desc = opt.dataset.desc || '';
  const role = sel.value;
  document.getElementById(descId).textContent = desc;
  const preview = document.getElementById('addAccessPreview');
  if (preview && descId === 'addRoleDesc') {
    const mods = PERMS[role] || [];
    preview.innerHTML = mods.map(m =>
      '<span class="badge badge-blue" style="font-size:.65rem">' + (MODS[m] || m) + '</span>'
    ).join('');
    const crewWrap = document.getElementById('crewMemberLinkWrap');
    if (crewWrap) crewWrap.style.display = (role === 'crew') ? '' : 'none';
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const addSel = document.getElementById('addRoleSelect');
  if (addSel) updateRoleDesc(addSel, 'addRoleDesc');
});

function filterUsers() {
  const q      = (document.getElementById('userSearch').value || '').toLowerCase().trim();
  const role   = document.getElementById('userRoleFilter').value;
  const status = document.getElementById('userStatusFilter').value;
  const rows   = document.querySelectorAll('#usersTable tbody tr');
  let visible  = 0;

  rows.forEach(row => {
    const name   = row.dataset.name  || '';
    const email  = row.dataset.email || '';
    const rRole  = row.dataset.role  || '';
    const rStat  = row.dataset.status || '';

    const matchQ      = !q      || name.includes(q) || email.includes(q);
    const matchRole   = !role   || rRole === role;
    const matchStatus = !status || rStat === status;

    const show = matchQ && matchRole && matchStatus;
    row.style.display = show ? '' : 'none';
    if (show) visible++;
  });

  document.getElementById('userCountBadge').textContent = visible;
  document.getElementById('userNoResults').style.display = visible === 0 ? 'block' : 'none';
  document.getElementById('usersTable').style.display    = visible === 0 ? 'none'  : '';
  document.getElementById('clearUserFilters').style.display = (q || role || status) ? '' : 'none';
}

function clearUserFilters() {
  document.getElementById('userSearch').value       = '';
  document.getElementById('userRoleFilter').value   = '';
  document.getElementById('userStatusFilter').value = '';
  filterUsers();
}
</script>
@endpush
@endsection
