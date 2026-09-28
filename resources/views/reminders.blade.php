@extends('layouts.app')

@section('pageTitle', 'Reminders')

@section('breadcrumb')
<span>Reminders</span>
@endsection

@section('topbarActions')
@if ($canPost)
<button onclick="openModal_add()" class="btn btn-primary btn-sm"><i data-feather="plus"></i> Add Reminder</button>
@endif
@endsection

@section('content')

@if ($msg)
<div class="alert alert-{{ $msg['type'] }}" data-autohide>
  <i data-feather="{{ $msg['type'] === 'success' ? 'check-circle' : 'alert-circle' }}"></i>
  {!! $msg['text'] !!}
</div>
@endif

<!-- KPIs -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:22px">
  <div class="stat-card blue">
    <div class="stat-icon"><i data-feather="clipboard"></i></div>
    <div class="stat-value">{{ $kpis['open_tasks'] }}</div>
    <div class="stat-label">Open Tasks</div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon" style="background:#fef2f2;color:#dc2626"><i data-feather="alert-circle"></i></div>
    <div class="stat-value">{{ $kpis['overdue'] }}</div>
    <div class="stat-label">Overdue</div>
  </div>
  <div class="stat-card orange">
    <div class="stat-icon"><i data-feather="calendar"></i></div>
    <div class="stat-value">{{ $kpis['upcoming_meetings'] }}</div>
    <div class="stat-label">Upcoming Meetings</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon" style="background:#f0fdf4;color:#16a34a"><i data-feather="check-circle"></i></div>
    <div class="stat-value">{{ $kpis['completed_this_month'] }}</div>
    <div class="stat-label">Completed (This Month)</div>
  </div>
</div>

<!-- Partners Meeting -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header">
    <h2 class="card-title"><i data-feather="users" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Partners Meeting</h2>
  </div>
  <div class="card-body">
    @if ($meeting)
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:14px">
      <div>
        <div style="font-weight:700;font-size:1rem">{{ $meeting->title }}@if ($meeting->visible_to_crew) <span class="badge badge-blue" style="font-size:.62rem;vertical-align:middle" title="Visible to crew in the Crew Portal">Crew</span>@endif</div>
        <div style="font-size:.85rem;color:var(--muted);margin-top:4px">
          <i data-feather="calendar" style="width:13px;height:13px;vertical-align:middle"></i>
          {{ date('M j, Y', strtotime($meeting->reminder_date)) }}
          @if ($meeting->meeting_time)
          &middot; {{ date('g:i A', strtotime($meeting->meeting_time)) }}
          @endif
          @if ($meeting->location)
          &middot; <i data-feather="map-pin" style="width:13px;height:13px;vertical-align:middle"></i> {{ $meeting->location }}
          @endif
        </div>
        @if ($meeting->related_label)
        <div style="font-size:.8rem;color:var(--muted);margin-top:4px">Related to: <strong style="color:var(--text)">{{ ucfirst($meeting->related_type) }} — {{ $meeting->related_label }}</strong></div>
        @endif
        @if ($meeting->assigned_name || $meeting->person_in_charge)
        <div style="font-size:.8rem;color:var(--muted);margin-top:4px">Person in charge: {{ $meeting->assigned_name ?: $meeting->person_in_charge }}</div>
        @endif
        @if ($meeting->attendees->isNotEmpty())
        <div style="font-size:.8rem;color:var(--muted);margin-top:4px">Attendees: {{ $meeting->attendees->implode(', ') }}</div>
        @endif
        @if ($meeting->note)
        <div style="font-size:.83rem;margin-top:8px">{{ $meeting->note }}</div>
        @endif
        <div style="font-size:.72rem;color:var(--muted);margin-top:8px">Shared with everyone signed in to admin.</div>
      </div>
      @if ($canPost)
      <div style="display:flex;gap:6px;flex-shrink:0">
        <form method="POST">
          @csrf
          <input type="hidden" name="action" value="toggle_reminder">
          <input type="hidden" name="reminder_id" value="{{ $meeting->reminder_id }}">
          <button type="submit" class="btn btn-sm btn-success" title="Mark Done"><i data-feather="check"></i></button>
        </form>
        <button type="button" class="btn btn-sm btn-outline" title="Edit"
                onclick="openEditReminder({{ Illuminate\Support\Js::from($meeting)->toHtml() }})"><i data-feather="edit-2"></i></button>
        <form method="POST" onsubmit="return confirm('Delete this meeting?')">
          @csrf
          <input type="hidden" name="action" value="delete_reminder">
          <input type="hidden" name="reminder_id" value="{{ $meeting->reminder_id }}">
          <button type="submit" class="btn btn-sm btn-outline" title="Delete"><i data-feather="trash-2"></i></button>
        </form>
      </div>
      @endif
    </div>
    @else
    <div class="empty-state">
      <i data-feather="users"></i>
      <h3>No partners meeting scheduled</h3>
      <p>Add one to let the whole team know when and where the next meeting is.</p>
    </div>
    @endif
  </div>
</div>

<!-- To Do -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header">
    <h2 class="card-title"><i data-feather="check-square" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>To Do</h2>
  </div>
  <div class="table-wrap">
    @if ($todos->isEmpty())
    <div class="empty-state">
      <i data-feather="check-square"></i>
      <h3>Nothing on the list</h3>
      <p>Add a to-do to keep track of things that need attention.</p>
    </div>
    @else
    <table>
      <thead>
        <tr>
          <th>Title</th>
          <th>Related To</th>
          <th>Priority</th>
          <th>Due</th>
          <th>Target Date</th>
          <th>Assigned To</th>
          <th>Note</th>
          @if ($canPost)<th>Actions</th>@endif
        </tr>
      </thead>
      <tbody>
      @foreach ($todos as $t)
      <tr>
        <td style="font-weight:600">{{ $t->title }}@if ($t->visible_to_crew) <span class="badge badge-blue" style="font-size:.62rem;vertical-align:middle" title="Visible to crew in the Crew Portal">Crew</span>@endif</td>
        <td style="font-size:.8rem;color:var(--muted)">{{ $t->related_label ? ucfirst($t->related_type).' — '.$t->related_label : '—' }}</td>
        <td>
          @if ($t->priority === 'high')
          <span class="badge badge-red">High</span>
          @elseif ($t->priority === 'low')
          <span class="badge badge-gray">Low</span>
          @else
          <span class="badge badge-blue">Normal</span>
          @endif
        </td>
        <td style="white-space:nowrap;font-size:.83rem{{ $t->reminder_date < now()->toDateString() ? ';color:var(--red);font-weight:600' : '' }}">
          {{ date('M j, Y', strtotime($t->reminder_date)) }}{{ $t->due_time ? ' · '.date('g:i A', strtotime($t->due_time)) : '' }}
        </td>
        <td style="white-space:nowrap;font-size:.83rem">{{ $t->target_date ? date('M j, Y', strtotime($t->target_date)) : '—' }}</td>
        <td style="font-size:.83rem">{{ $t->assigned_name ?: ($t->person_in_charge ?: '—') }}</td>
        <td style="font-size:.83rem;color:var(--muted)">{{ $t->note ?: '—' }}</td>
        @if ($canPost)
        <td>
          <div style="display:flex;gap:6px">
            <form method="POST">
              @csrf
              <input type="hidden" name="action" value="toggle_reminder">
              <input type="hidden" name="reminder_id" value="{{ $t->reminder_id }}">
              <button type="submit" class="btn btn-sm btn-success" title="Mark Done"><i data-feather="check"></i></button>
            </form>
            <button type="button" class="btn btn-sm btn-outline" title="Edit"
                    onclick="openEditReminder({{ Illuminate\Support\Js::from($t)->toHtml() }})"><i data-feather="edit-2"></i></button>
            <form method="POST" onsubmit="return confirm('Delete this to-do?')">
              @csrf
              <input type="hidden" name="action" value="delete_reminder">
              <input type="hidden" name="reminder_id" value="{{ $t->reminder_id }}">
              <button type="submit" class="btn btn-sm btn-outline" title="Delete"><i data-feather="trash-2"></i></button>
            </form>
          </div>
        </td>
        @endif
      </tr>
      @endforeach
      </tbody>
    </table>
    @endif
  </div>
</div>

<!-- Done -->
<div class="card">
  <details style="padding:12px 20px">
    <summary style="font-size:12px;color:var(--muted);cursor:pointer">Done — {{ $done->count() }} item{{ $done->count() === 1 ? '' : 's' }}</summary>
    @if ($done->isEmpty())
    <p style="font-size:.83rem;color:var(--muted);margin-top:10px">Nothing marked done yet.</p>
    @else
    <table style="margin-top:10px">
      <thead>
        <tr>
          <th>Title</th>
          <th>Done On</th>
          <th>Person In Charge</th>
          <th>Erases In</th>
          @if ($canPost)<th>Actions</th>@endif
        </tr>
      </thead>
      <tbody>
      @foreach ($done as $d)
      <tr>
        <td style="text-decoration:line-through;color:var(--muted)">{{ $d->title }}</td>
        <td style="white-space:nowrap;font-size:.83rem">{{ date('M j, Y', strtotime($d->done_at)) }}</td>
        <td style="font-size:.83rem">{{ $d->person_in_charge ?: '—' }}</td>
        <td style="font-size:.83rem;color:var(--muted)">{{ $d->days_left }}d</td>
        @if ($canPost)
        <td>
          <div style="display:flex;gap:6px">
            <form method="POST">
              @csrf
              <input type="hidden" name="action" value="toggle_reminder">
              <input type="hidden" name="reminder_id" value="{{ $d->reminder_id }}">
              <button type="submit" class="btn btn-sm btn-outline" title="Reopen"><i data-feather="rotate-ccw"></i></button>
            </form>
            <button type="button" class="btn btn-sm btn-outline" title="Edit"
                    onclick="openEditReminder({{ Illuminate\Support\Js::from($d)->toHtml() }})"><i data-feather="edit-2"></i></button>
            <form method="POST" onsubmit="return confirm('Delete this reminder?')">
              @csrf
              <input type="hidden" name="action" value="delete_reminder">
              <input type="hidden" name="reminder_id" value="{{ $d->reminder_id }}">
              <button type="submit" class="btn btn-sm btn-outline" title="Delete"><i data-feather="trash-2"></i></button>
            </form>
          </div>
        </td>
        @endif
      </tr>
      @endforeach
      </tbody>
    </table>
    @endif
  </details>
</div>

<div style="font-size:.75rem;color:var(--text-muted);margin-top:14px">
  Reminders are shared with everyone signed in to admin.
</div>

@include('partials.page-activity')
@include('partials.access-log')

@if ($canPost)
<!-- ADD REMINDER MODAL -->
<div class="modal-overlay" id="modalAddReminder">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <h3 id="reminderModalTitle"><i data-feather="bell" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Add Reminder</h3>
      <button class="modal-close" onclick="closeModal('modalAddReminder')">&times;</button>
    </div>
    <form method="POST">
      @csrf
      {{-- One form serves add and edit; the action and id are swapped in by JS (Part 15). --}}
      <input type="hidden" name="action" id="reminderAction" value="add_reminder">
      <input type="hidden" name="reminder_id" id="reminderId">
      <div class="modal-body">
        <div class="form-group">
          <label>Type</label>
          <select name="type" id="reminderType" class="form-control" onchange="toggleReminderType()">
            <option value="todo">To Do</option>
            <option value="partners_meeting">Set Partners Meeting</option>
          </select>
        </div>
        <div class="form-group">
          <label>Title <span style="color:var(--red)">*</span></label>
          <input type="text" name="title" id="reminderTitle" class="form-control" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Related To</label>
            <select name="related_type" id="reminderRelatedType" class="form-control" onchange="onRelatedTypeChange()">
              <option value="">— None —</option>
              <option value="booking">Booking</option>
              <option value="client">Client</option>
              <option value="equipment">Equipment</option>
              <option value="crew">Crew</option>
            </select>
          </div>
          <div class="form-group" id="reminderRelatedItemWrap" style="display:none">
            <label>&nbsp;</label>
            <select name="related_id" id="reminderRelatedId" class="form-control"></select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label id="reminderDateLabel">Due Date <span style="color:var(--red)">*</span></label>
            <input type="date" name="reminder_date" id="reminderDate" class="form-control" required>
          </div>
          <div class="form-group" id="dueTimeWrap">
            <label>Due Time (optional)</label>
            <input type="time" name="due_time" id="reminderDueTime" class="form-control">
          </div>
          <div class="form-group" id="meetingTimeWrap" style="display:none">
            <label>Meeting Time</label>
            <input type="time" name="meeting_time" id="reminderTime" class="form-control">
          </div>
          <div class="form-group">
            <label>Priority</label>
            <select name="priority" id="reminderPriority" class="form-control">
              <option value="low">Low</option>
              <option value="normal">Normal</option>
              <option value="high">High</option>
            </select>
          </div>
        </div>
        <div class="form-group" id="meetingLocationWrap" style="display:none">
          <label>Location</label>
          <input type="text" name="location" id="reminderLocation" class="form-control">
        </div>

        <div id="attendeesWrap" style="display:none;margin-bottom:14px">
          <label style="display:block;margin-bottom:6px">Attendees</label>
          <div id="attendeesList" style="display:flex;flex-direction:column;gap:6px;margin-bottom:6px"></div>
          <button type="button" class="btn btn-sm btn-outline" onclick="addAttendeeRow()"><i data-feather="user-plus"></i> Add Attendee</button>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Target Date (optional)</label>
            <input type="date" name="target_date" id="reminderTargetDate" class="form-control">
          </div>
          <div class="form-group">
            <label>Assigned To (staff)</label>
            <select name="assigned_to" id="reminderAssignedTo" class="form-control">
              <option value="">— Unassigned —</option>
              @foreach ($pickerStaff as $s)
              <option value="{{ $s->user_id }}">{{ $s->name }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="form-group">
          <label>Person In Charge — if not a staff user (optional)</label>
          <input type="text" name="person_in_charge" id="reminderPic" class="form-control" placeholder="e.g. an external partner">
        </div>
        <div class="form-group">
          <label>Note (optional)</label>
          <textarea name="note" id="reminderNote" class="form-control" rows="2"></textarea>
        </div>

        <div class="form-group" style="margin-bottom:0">
          <label>Visibility</label>
          <select name="visibility" id="reminderVisibility" class="form-control" onchange="onVisibilityChange()">
            <option value="internal">Internal — this admin panel only</option>
            <option value="roles">Specific Roles</option>
            <option value="crew_portal">Crew Portal announcement</option>
          </select>
          <div id="visibilityRolesWrap" style="display:none;margin-top:8px">
            <div style="display:flex;flex-wrap:wrap;gap:10px">
              @foreach ($roleOptions as $r)
              <label style="display:flex;align-items:center;gap:6px;font-weight:400;font-size:.83rem;cursor:pointer">
                <input type="checkbox" name="visible_roles[]" value="{{ $r }}" class="reminderRoleCb" style="width:14px;height:14px;accent-color:var(--accent)">
                {{ ucfirst(str_replace('_', ' ', $r)) }}
              </label>
              @endforeach
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modalAddReminder')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="reminderSubmit"><i data-feather="plus"></i> Add Reminder</button>
      </div>
    </form>
  </div>
</div>
@endif

@endsection

@push('scripts')
<script>
const REM_PICKERS = {
  booking: {!! Illuminate\Support\Js::from($pickerBookings->map(fn($b) => ['id' => $b->booking_id, 'label' => $b->booking_reference . ($b->project_title ? ' — ' . $b->project_title : '')])) !!},
  client: {!! Illuminate\Support\Js::from($pickerClients->map(fn($c) => ['id' => $c->client_id, 'label' => $c->company_name ?: $c->contact_person])) !!},
  equipment: {!! Illuminate\Support\Js::from($pickerEquipment->map(fn($e) => ['id' => $e->equipment_id, 'label' => $e->equipment_name])) !!},
  crew: {!! Illuminate\Support\Js::from($pickerCrew->map(fn($c) => ['id' => $c->crew_id, 'label' => $c->name])) !!},
};

// The topbar button adds; the pencil on a row edits. Same form either way (Part 15).
function openModal_add() {
  document.getElementById('reminderModalTitle').innerHTML =
    '<i data-feather="bell" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Add Reminder';
  document.getElementById('reminderAction').value = 'add_reminder';
  document.getElementById('reminderId').value = '';
  document.getElementById('reminderType').value = 'todo';
  ['reminderTitle', 'reminderDate', 'reminderDueTime', 'reminderTime', 'reminderLocation', 'reminderTargetDate', 'reminderPic', 'reminderNote']
    .forEach(id => { document.getElementById(id).value = ''; });
  document.getElementById('reminderPriority').value = 'normal';
  document.getElementById('reminderAssignedTo').value = '';
  document.getElementById('reminderRelatedType').value = '';
  onRelatedTypeChange();
  document.getElementById('reminderVisibility').value = 'internal';
  document.querySelectorAll('.reminderRoleCb').forEach(cb => { cb.checked = false; });
  onVisibilityChange();
  setAttendees([]);
  document.getElementById('reminderSubmit').innerHTML =
    '<i data-feather="plus"></i> Add Reminder';
  toggleReminderType();
  openModal('modalAddReminder');
  if (window.feather) feather.replace();
}

function openEditReminder(r) {
  document.getElementById('reminderModalTitle').innerHTML =
    '<i data-feather="edit-2" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Edit Reminder';
  document.getElementById('reminderAction').value = 'update_reminder';
  document.getElementById('reminderId').value = r.reminder_id;
  document.getElementById('reminderType').value = r.type;
  document.getElementById('reminderTitle').value = r.title || '';
  document.getElementById('reminderDate').value = r.reminder_date || '';
  document.getElementById('reminderDueTime').value = (r.due_time || '').slice(0, 5);
  document.getElementById('reminderTime').value = (r.meeting_time || '').slice(0, 5);
  document.getElementById('reminderLocation').value = r.location || '';
  document.getElementById('reminderTargetDate').value = r.target_date || '';
  document.getElementById('reminderPic').value = r.person_in_charge || '';
  document.getElementById('reminderAssignedTo').value = r.assigned_to || '';
  document.getElementById('reminderNote').value = r.note || '';
  document.getElementById('reminderPriority').value = r.priority || 'normal';

  document.getElementById('reminderRelatedType').value = r.related_type || '';
  onRelatedTypeChange();
  if (r.related_type && r.related_id) {
    document.getElementById('reminderRelatedId').value = r.related_id;
  }

  document.getElementById('reminderVisibility').value = r.visibility || 'internal';
  const selectedRoles = (r.visible_roles || '').split(',').filter(Boolean);
  document.querySelectorAll('.reminderRoleCb').forEach(cb => { cb.checked = selectedRoles.includes(cb.value); });
  onVisibilityChange();

  setAttendees(r.attendees || []);

  document.getElementById('reminderSubmit').innerHTML = '<i data-feather="check"></i> Save Changes';
  toggleReminderType();
  openModal('modalAddReminder');
  if (window.feather) feather.replace();
}

function toggleReminderType() {
  const sel = document.getElementById('reminderType');
  const isMeeting = sel.value === 'partners_meeting';
  document.getElementById('meetingTimeWrap').style.display = isMeeting ? '' : 'none';
  document.getElementById('dueTimeWrap').style.display = isMeeting ? 'none' : '';
  document.getElementById('meetingLocationWrap').style.display = isMeeting ? '' : 'none';
  document.getElementById('attendeesWrap').style.display = isMeeting ? '' : 'none';
  document.getElementById('reminderDateLabel').innerHTML = (isMeeting ? 'Meeting Date' : 'Due Date') + ' <span style="color:var(--red)">*</span>';
}

function onRelatedTypeChange() {
  const type = document.getElementById('reminderRelatedType').value;
  const wrap = document.getElementById('reminderRelatedItemWrap');
  const sel = document.getElementById('reminderRelatedId');
  if (!type) {
    wrap.style.display = 'none';
    sel.innerHTML = '';
    return;
  }
  const items = REM_PICKERS[type] || [];
  sel.innerHTML = items.map(i => `<option value="${i.id}">${i.label.replace(/</g, '&lt;')}</option>`).join('');
  wrap.style.display = '';
}

function onVisibilityChange() {
  const isRoles = document.getElementById('reminderVisibility').value === 'roles';
  document.getElementById('visibilityRolesWrap').style.display = isRoles ? '' : 'none';
}

function setAttendees(names) {
  const list = document.getElementById('attendeesList');
  list.innerHTML = '';
  (names.length ? names : ['']).forEach(n => addAttendeeRow(n));
}

function addAttendeeRow(name) {
  const list = document.getElementById('attendeesList');
  const row = document.createElement('div');
  row.style.cssText = 'display:flex;gap:6px';
  row.innerHTML = `<input type="text" name="attendees[]" class="form-control" placeholder="Attendee name" value="${(name || '').replace(/"/g, '&quot;')}">
    <button type="button" class="btn btn-sm btn-outline" onclick="this.closest('div').remove()"><i data-feather="x"></i></button>`;
  list.appendChild(row);
  if (window.feather) feather.replace();
}
</script>
@endpush
