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
        @if ($meeting->person_in_charge)
        <div style="font-size:.8rem;color:var(--muted);margin-top:4px">Person in charge: {{ $meeting->person_in_charge }}</div>
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
          <th>Due</th>
          <th>Target Date</th>
          <th>Person In Charge</th>
          <th>Note</th>
          @if ($canPost)<th>Actions</th>@endif
        </tr>
      </thead>
      <tbody>
      @foreach ($todos as $t)
      <tr>
        <td style="font-weight:600">{{ $t->title }}@if ($t->visible_to_crew) <span class="badge badge-blue" style="font-size:.62rem;vertical-align:middle" title="Visible to crew in the Crew Portal">Crew</span>@endif</td>
        <td style="white-space:nowrap;font-size:.83rem">{{ date('M j, Y', strtotime($t->reminder_date)) }}</td>
        <td style="white-space:nowrap;font-size:.83rem">{{ $t->target_date ? date('M j, Y', strtotime($t->target_date)) : '—' }}</td>
        <td style="font-size:.83rem">{{ $t->person_in_charge ?: '—' }}</td>
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
            <label id="reminderDateLabel">Due Date <span style="color:var(--red)">*</span></label>
            <input type="date" name="reminder_date" id="reminderDate" class="form-control" required>
          </div>
          <div class="form-group" id="meetingTimeWrap" style="display:none">
            <label>Meeting Time</label>
            <input type="time" name="meeting_time" id="reminderTime" class="form-control">
          </div>
        </div>
        <div class="form-group" id="meetingLocationWrap" style="display:none">
          <label>Location</label>
          <input type="text" name="location" id="reminderLocation" class="form-control">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Target Date (optional)</label>
            <input type="date" name="target_date" id="reminderTargetDate" class="form-control">
          </div>
          <div class="form-group">
            <label>Person In Charge (optional)</label>
            <input type="text" name="person_in_charge" id="reminderPic" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label>Note (optional)</label>
          <textarea name="note" id="reminderNote" class="form-control" rows="2"></textarea>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label style="display:flex;align-items:center;gap:8px;font-weight:400;cursor:pointer">
            <input type="checkbox" name="visible_to_crew" id="reminderVisibleToCrew" value="1" style="width:16px;height:16px;accent-color:var(--accent)">
            Show as an announcement in the Crew Portal
          </label>
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
// The topbar button adds; the pencil on a row edits. Same form either way (Part 15).
function openModal_add() {
  document.getElementById('reminderModalTitle').innerHTML =
    '<i data-feather="bell" style="width:16px;height:16px;margin-right:6px;vertical-align:middle"></i>Add Reminder';
  document.getElementById('reminderAction').value = 'add_reminder';
  document.getElementById('reminderId').value = '';
  document.getElementById('reminderType').value = 'todo';
  ['reminderTitle', 'reminderDate', 'reminderTime', 'reminderLocation', 'reminderTargetDate', 'reminderPic', 'reminderNote']
    .forEach(id => { document.getElementById(id).value = ''; });
  document.getElementById('reminderVisibleToCrew').checked = false;
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
  document.getElementById('reminderTime').value = (r.meeting_time || '').slice(0, 5);
  document.getElementById('reminderLocation').value = r.location || '';
  document.getElementById('reminderTargetDate').value = r.target_date || '';
  document.getElementById('reminderPic').value = r.person_in_charge || '';
  document.getElementById('reminderNote').value = r.note || '';
  document.getElementById('reminderVisibleToCrew').checked = !!(r.visible_to_crew && r.visible_to_crew != 0);
  document.getElementById('reminderSubmit').innerHTML = '<i data-feather="check"></i> Save Changes';
  toggleReminderType();
  openModal('modalAddReminder');
  if (window.feather) feather.replace();
}

function toggleReminderType() {
  const sel = document.getElementById('reminderType');
  const isMeeting = sel.value === 'partners_meeting';
  document.getElementById('meetingTimeWrap').style.display = isMeeting ? '' : 'none';
  document.getElementById('meetingLocationWrap').style.display = isMeeting ? '' : 'none';
  document.getElementById('reminderDateLabel').innerHTML = (isMeeting ? 'Meeting Date' : 'Due Date') + ' <span style="color:var(--red)">*</span>';
}
</script>
@endpush
