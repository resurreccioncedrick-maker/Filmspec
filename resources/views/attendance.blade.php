@extends('layouts.app')

@section('pageTitle', 'Crew Attendance')

@section('breadcrumb')
<a href="{{ route('crew') }}">Crew</a>
<span>Attendance</span>
@endsection

@section('topbarActions')
@include('partials.export-dropdown', ['id' => 'Attendance'])
@if ($filterBooking)
<a href="{{ route('attendance.print', ['booking_id' => $filterBooking]) }}" target="_blank" class="btn btn-outline btn-sm"><i data-feather="printer"></i> Print Record</a>
@endif
@if ($canManage)
<a href="{{ route('crew') }}" class="btn btn-outline btn-sm"><i data-feather="users"></i> Back to Crew</a>
@endif
@endsection

@section('content')
@php
  $attBase = route('attendance');
@endphp

@if ($msg)
<div class="alert alert-{{ $msg['type'] }}" data-autohide>
  <i data-feather="{{ $msg['type'] === 'success' ? 'check-circle' : 'alert-circle' }}"></i>
  {!! $msg['text'] !!}
</div>
@endif

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:22px">
  <div class="stat-card green">
    <div class="stat-icon" style="background:#f0fdf4;color:#16a34a"><i data-feather="user-check"></i></div>
    <div class="stat-value">{{ $totalDeployed }}</div>
    <div class="stat-label">Deployed Today</div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon" style="background:#fef2f2;color:#dc2626"><i data-feather="user-x"></i></div>
    <div class="stat-value">{{ $totalNoShows }}</div>
    <div class="stat-label">No Shows / Back-Outs (30d)</div>
  </div>
  <div class="stat-card orange">
    <div class="stat-icon"><i data-feather="clock"></i></div>
    <div class="stat-value">{{ $totalOT }}</div>
    <div class="stat-label">OT Entries (30d)</div>
  </div>
</div>

<!-- TABS -->
<div class="tabs" style="margin-bottom:22px">
  <button class="tab-btn active" data-tab="tab-log">Log Attendance</button>
  <button class="tab-btn" data-tab="tab-history">Attendance History</button>
  <button class="tab-btn" data-tab="tab-timesheet">Timesheets</button>
</div>

<div data-tab-panes>

<!-- LOG ATTENDANCE -->
<div id="tab-log" class="tab-pane active">
  @if (! $canManage)
  <div class="alert alert-info"><i data-feather="info"></i> You have view-only access to attendance records.</div>
  @else
  <div class="card" style="margin-bottom:22px">
    <div class="card-header"><h2 class="card-title">Log Shoot Attendance</h2></div>
    <div class="card-body">
      <form method="POST" action="{{ $attBase }}" id="attendanceForm">
        @csrf
        <input type="hidden" name="action" value="log_attendance">
        <div class="form-row">
          <div class="form-group">
            <label>Booking / Project *</label>
            <select name="booking_id" class="form-control" required id="bookingSelect"
                    onchange="loadBookingCrew(this.value)">
              <option value="">— Select active booking —</option>
              @foreach ($activeBookings as $bk)
              <option value="{{ $bk->booking_id }}" {{ $filterBooking == $bk->booking_id ? 'selected' : '' }}>
                {{ $bk->booking_reference }} —
                {{ $bk->project_title ?: ($bk->company_name ?: $bk->contact_person) }}
                ({{ \Illuminate\Support\Carbon::parse($bk->shoot_date_start)->format('M j') }})
              </option>
              @endforeach
            </select>
          </div>
          <div class="form-group">
            <label>Shoot Date *</label>
            <input type="date" name="attendance_date" class="form-control"
                   value="{{ request('date', now()->toDateString()) }}" required>
          </div>
        </div>

        @if ($filterBooking && $bookingCrew->isNotEmpty())
        <div id="crewAttendanceTable">
          <div style="background:var(--blue-50);border:1px solid var(--blue-200);border-radius:var(--radius-md);padding:14px;margin-bottom:16px">
            <div style="font-size:.8rem;font-weight:700;color:var(--blue-700);text-transform:uppercase;letter-spacing:.06em;margin-bottom:12px">
              Assigned Crew — Mark Attendance
            </div>
            <table style="width:100%;border-collapse:collapse;font-size:.875rem">
              <thead>
                <tr style="border-bottom:2px solid var(--blue-200)">
                  <th style="padding:8px;text-align:left;color:var(--blue-800);font-size:.75rem;text-transform:uppercase;letter-spacing:.06em">Crew Member</th>
                  <th style="padding:8px;text-align:left;color:var(--blue-800);font-size:.75rem;text-transform:uppercase;letter-spacing:.06em">Position</th>
                  <th style="padding:8px;text-align:center;color:var(--blue-800);font-size:.75rem;text-transform:uppercase;letter-spacing:.06em">Status</th>
                  <th style="padding:8px;text-align:left;color:var(--blue-800);font-size:.75rem;text-transform:uppercase;letter-spacing:.06em">Reason (if absent)</th>
                  <th style="padding:8px;text-align:left;color:var(--blue-800);font-size:.75rem;text-transform:uppercase;letter-spacing:.06em">Category</th>
                  <th style="padding:8px;text-align:left;color:var(--blue-800);font-size:.75rem;text-transform:uppercase;letter-spacing:.06em">Replacement</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($bookingCrew as $bc)
                @php $existAtt = $bc->existing_attendance; @endphp
                <tr style="border-bottom:1px solid var(--blue-100)">
                  <td style="padding:10px 8px">
                    <div style="font-weight:600">{{ $bc->crew_name }}</div>
                    <div style="font-size:.72rem;color:var(--text-muted)">{{ $bc->phone ?? '' }}</div>
                  </td>
                  <td style="padding:10px 8px;font-size:.83rem;color:var(--text-muted)">{{ $bc->position_name ?? '—' }}</td>
                  <td style="padding:10px 8px;text-align:center">
                    <select name="crew_status[{{ $bc->crew_id }}]" class="form-control"
                            style="width:auto;padding:5px 10px;font-size:.83rem"
                            onchange="toggleReason(this,{{ $bc->crew_id }})">
                      <option value="present" {{ ($existAtt->status ?? 'present') === 'present' ? 'selected' : '' }}>Present</option>
                      <option value="late" {{ ($existAtt->status ?? '') === 'late' ? 'selected' : '' }}>Late</option>
                      <option value="absent" {{ ($existAtt->status ?? '') === 'absent' ? 'selected' : '' }}>Absent</option>
                      <option value="no_show" {{ ($existAtt->status ?? '') === 'no_show' ? 'selected' : '' }}>No Show</option>
                      <option value="back_out" {{ ($existAtt->status ?? '') === 'back_out' ? 'selected' : '' }}>Back Out</option>
                    </select>
                  </td>
                  <td style="padding:10px 8px">
                    <input type="text" name="crew_reason[{{ $bc->crew_id }}]"
                           class="form-control reason-field-{{ $bc->crew_id }}"
                           style="font-size:.83rem{{ in_array($existAtt->status ?? 'present', ['present', 'late']) ? ';display:none' : '' }}"
                           value="{{ $existAtt->reason ?? '' }}"
                           placeholder="Reason…">
                  </td>
                  <td style="padding:10px 8px">
                    <select name="crew_reason_category[{{ $bc->crew_id }}]"
                            class="form-control reason-category-field-{{ $bc->crew_id }}"
                            style="font-size:.83rem{{ in_array($existAtt->status ?? 'present', ['present', 'late']) ? ';display:none' : '' }}">
                      <option value="">—</option>
                      @foreach (['Personal' => 'Personal', 'Emergency' => 'Emergency', 'Prior Notice' => 'Prior Notice', 'Other' => 'Other'] as $catVal => $catLabel)
                      <option value="{{ $catVal }}" {{ ($existAtt->reason_category ?? '') === $catVal ? 'selected' : '' }}>{{ $catLabel }}</option>
                      @endforeach
                    </select>
                  </td>
                  <td style="padding:10px 8px">
                    <select name="crew_replacement[{{ $bc->crew_id }}]"
                            class="form-control replacement-field-{{ $bc->crew_id }}"
                            style="font-size:.83rem{{ in_array($existAtt->status ?? 'present', ['present', 'late', 'absent']) ? ';display:none' : '' }}">
                      <option value="">— No replacement —</option>
                      @foreach ($allActiveCrew as $ac)
                      @continue($ac->crew_id == $bc->crew_id)
                      <option value="{{ $ac->crew_id }}" {{ ($existAtt->replacement_crew_id ?? 0) == $ac->crew_id ? 'selected' : '' }}>
                        {{ $ac->name }}
                      </option>
                      @endforeach
                    </select>
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          <button type="submit" class="btn btn-primary">
            <i data-feather="save"></i> Save Attendance
          </button>
        </div>
        @elseif ($filterBooking && $bookingCrew->isEmpty())
        <div class="alert alert-info" style="margin-top:14px">
          <i data-feather="info"></i> No crew assigned to this booking yet. Assign crew from the <a href="{{ route('booking-detail', $filterBooking) }}">booking detail page</a>.
        </div>
        @else
        <div style="background:var(--surface-hover);border:1px solid var(--border);border-radius:var(--radius-md);padding:24px;text-align:center;color:var(--text-muted);margin-top:8px">
          <i data-feather="users" style="width:32px;height:32px;margin-bottom:10px;opacity:.4"></i>
          <p>Select a booking above to load the assigned crew.</p>
        </div>
        @endif
      </form>
    </div>
  </div>
  @endif

  <!-- Quick booking selector buttons -->
  @if ($activeBookings->isNotEmpty())
  <div class="card">
    <div class="card-header"><h2 class="card-title">Active Bookings</h2></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Reference</th><th>Project</th><th>Shoot Date</th><th>Crew Count</th><th>Action</th></tr></thead>
        <tbody>
          @foreach ($activeBookings as $bk)
          @php $crewCount = (int) \Illuminate\Support\Facades\DB::table('booking_crew')->where('booking_id', $bk->booking_id)->where('assignment_status', 'confirmed')->count(); @endphp
          <tr>
            <td style="font-weight:600;color:var(--blue-600)">{{ $bk->booking_reference }}</td>
            <td>{{ $bk->project_title ?: ($bk->company_name ?: $bk->contact_person) }}</td>
            <td>{{ \Illuminate\Support\Carbon::parse($bk->shoot_date_start)->format('M j, Y') }}</td>
            <td><span class="badge badge-blue">{{ $crewCount }} confirmed</span></td>
            <td>
              <a href="{{ $attBase }}?booking_id={{ $bk->booking_id }}&date={{ now()->toDateString() }}"
                 class="btn btn-primary btn-sm">
                <i data-feather="clipboard"></i> Log Attendance
              </a>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  @endif
</div>

<!-- ATTENDANCE HISTORY -->
<div id="tab-history" class="tab-pane">
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Attendance Records <span class="badge badge-blue" style="margin-left:8px">{{ $attTotal }}</span></h2>
    </div>
    <div class="card-body" style="padding-bottom:0">
      <form method="GET" action="{{ $attBase }}">
        <div class="filter-bar">
          <div class="search-input-wrap" style="min-width:unset;width:auto">
            <label style="font-size:.78rem;color:var(--text-muted);margin-bottom:0;white-space:nowrap">Booking:</label>
          </div>
          <select name="booking_id" class="form-control" style="width:auto">
            <option value="">All Bookings</option>
            @foreach ($activeBookings as $bk)
            <option value="{{ $bk->booking_id }}" {{ $filterBooking == $bk->booking_id ? 'selected' : '' }}>
              {{ $bk->booking_reference }}
            </option>
            @endforeach
          </select>
          <input type="date" name="date" class="form-control" style="width:auto" value="{{ $filterDate }}">
          <select name="status" class="form-control" style="width:auto">
            <option value="">All Status</option>
            <option value="present" {{ $filterStatus === 'present' ? 'selected' : '' }}>Present</option>
            <option value="absent" {{ $filterStatus === 'absent' ? 'selected' : '' }}>Absent</option>
            <option value="late" {{ $filterStatus === 'late' ? 'selected' : '' }}>Late</option>
            <option value="no_show" {{ $filterStatus === 'no_show' ? 'selected' : '' }}>No Show</option>
            <option value="back_out" {{ $filterStatus === 'back_out' ? 'selected' : '' }}>Back Out</option>
          </select>
          <button type="submit" name="p" value="1" class="btn btn-primary btn-sm"><i data-feather="filter"></i> Filter</button>
          <a href="{{ $attBase }}" class="btn btn-outline btn-sm"><i data-feather="refresh-cw"></i></a>
        </div>
      </form>
    </div>
    <div class="table-wrap">
      @if ($attendance->isEmpty())
      <div class="empty-state"><i data-feather="clipboard"></i><h3>No attendance records yet</h3>
        <p>Use the Log Attendance tab to record crew attendance for a shoot.</p>
      </div>
      @else
      <table>
        <thead>
          <tr>
            <th>Date</th><th>Crew Member</th><th>Position</th><th>Booking</th>
            <th>Status</th><th>Reason</th><th>Category</th><th>Replacement</th><th>Logged By</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($attendance as $att)
          <tr>
            <td style="white-space:nowrap;font-size:.83rem">{{ \Illuminate\Support\Carbon::parse($att->attendance_date)->format('M j, Y') }}</td>
            <td>
              <div style="font-weight:600">{{ $att->crew_name }}</div>
              <div style="font-size:.72rem;color:var(--text-muted)">{{ $att->crew_phone ?? '' }}</div>
            </td>
            <td style="font-size:.83rem;color:var(--text-muted)">{{ $att->position_name ?? '—' }}</td>
            <td style="font-size:.83rem">
              <span style="font-weight:600;color:var(--blue-600)">{{ $att->booking_reference }}</span>
              <div style="font-size:.72rem;color:var(--text-muted)">{{ substr($att->project_title ?? '', 0, 30) }}</div>
            </td>
            <td><span class="badge {{ $statusBadge[$att->status] ?? 'badge-gray' }}">{{ $statusLabel[$att->status] ?? ucfirst($att->status) }}</span></td>
            <td style="font-size:.83rem;color:var(--text-muted)">{{ $att->reason ?? '—' }}</td>
            <td style="font-size:.83rem;color:var(--text-muted)">{{ $att->reason_category ?? '—' }}</td>
            <td style="font-size:.83rem">
              @if ($att->replacement_name)<span class="badge badge-blue">{{ $att->replacement_name }}</span>@else—@endif
            </td>
            <td style="font-size:.78rem;color:var(--text-muted)">{{ $att->logged_by_name ?? '—' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @if ($attPages > 1)
      <div style="padding:14px 16px"><div class="pagination">
        @for ($pi = 1; $pi <= $attPages; $pi++)
        <a href="{{ $attBase }}?p={{ $pi }}&booking_id={{ $filterBooking }}&date={{ $filterDate }}&status={{ $filterStatus }}"
           class="page-btn {{ $pi == $page ? 'active' : '' }}">{{ $pi }}</a>
        @endfor
      </div></div>
      @endif
      @endif
    </div>
  </div>
</div>

<!-- TIMESHEETS -->
<div id="tab-timesheet" class="tab-pane">
  @if ($canManage)
  <div class="card" style="margin-bottom:22px">
    <div class="card-header"><h2 class="card-title">Log Timesheet Entry</h2></div>
    <div class="card-body">
      <form method="POST" action="{{ $attBase }}">
        @csrf
        <input type="hidden" name="action" value="log_timesheet">
        <div class="form-row">
          <div class="form-group">
            <label>Booking *</label>
            <select name="booking_id" class="form-control" required>
              <option value="">— Select booking —</option>
              @foreach ($activeBookings as $bk)
              <option value="{{ $bk->booking_id }}">{{ $bk->booking_reference }} — {{ $bk->project_title ?: $bk->contact_person }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group">
            <label>Crew Member *</label>
            <select name="crew_id" class="form-control" required>
              <option value="">— Select crew —</option>
              @foreach ($allActiveCrew as $ac)
              <option value="{{ $ac->crew_id }}">{{ $ac->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="form-group">
            <label>Shoot Date *</label>
            <input type="date" name="timesheet_date" class="form-control" value="{{ now()->toDateString() }}" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Time In</label><input type="time" name="time_in" class="form-control"></div>
          <div class="form-group"><label>Time Out</label><input type="time" name="time_out" class="form-control"></div>
          <div class="form-group"><label>Total Hours</label><input type="number" name="total_hours" class="form-control" step="0.5" min="0" placeholder="12"></div>
          <div class="form-group"><label>Overtime Hours</label><input type="number" name="overtime_hours" class="form-control" step="0.5" min="0" placeholder="0"></div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Pay Type</label>
            <select name="is_double_pay" class="form-control">
              <option value="0">Standard / Overtime</option>
              <option value="1">Double Pay (22–24h)</option>
            </select>
          </div>
          <div class="form-group"><label>Remarks</label><input type="text" name="remarks" class="form-control" placeholder="Notes about this entry…"></div>
        </div>
        <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Save Timesheet</button>
      </form>
    </div>
  </div>
  @endif

  <div class="card">
    <div class="card-header"><h2 class="card-title">Timesheet Records</h2></div>
    <div class="table-wrap">
      @if ($timesheets->isEmpty())
      <div class="empty-state"><i data-feather="clock"></i><h3>No timesheet entries yet</h3>
        <p>Select a booking or crew member to view timesheets, or log a new entry above.</p>
      </div>
      @else
      <table>
        <thead>
          <tr><th>Date</th><th>Crew</th><th>Position</th><th>Booking</th><th>In</th><th>Out</th><th>Hours</th><th>OT</th><th>Pay Type</th><th>Remarks</th></tr>
        </thead>
        <tbody>
          @foreach ($timesheets as $ts)
          <tr>
            <td style="white-space:nowrap;font-size:.83rem">{{ \Illuminate\Support\Carbon::parse($ts->timesheet_date)->format('M j, Y') }}</td>
            <td style="font-weight:600">{{ $ts->crew_name }}</td>
            <td style="font-size:.8rem;color:var(--text-muted)">{{ $ts->position_name ?? '—' }}</td>
            <td style="font-size:.83rem;color:var(--blue-600);font-weight:600">{{ $ts->booking_reference }}</td>
            <td style="font-family:monospace;font-size:.83rem">{{ $ts->time_in ? \Illuminate\Support\Carbon::parse($ts->time_in)->format('g:i a') : '—' }}</td>
            <td style="font-family:monospace;font-size:.83rem">{{ $ts->time_out ? \Illuminate\Support\Carbon::parse($ts->time_out)->format('g:i a') : '—' }}</td>
            <td style="font-weight:600;text-align:center">{{ $ts->total_hours }}<span style="font-size:.7rem;color:var(--text-muted)">h</span></td>
            <td style="text-align:center">
              @if ($ts->overtime_hours > 0)
              <span class="badge badge-orange">{{ $ts->overtime_hours }}h OT</span>
              @else
              <span style="color:var(--text-muted)">—</span>
              @endif
            </td>
            <td>
              @if ($ts->is_double_pay)
              <span class="badge badge-red">Double Pay</span>
              @elseif ($ts->overtime_hours > 0)
              <span class="badge badge-yellow">Overtime</span>
              @else
              <span class="badge badge-green">Standard</span>
              @endif
            </td>
            <td style="font-size:.8rem;color:var(--text-muted)">{{ $ts->remarks ?? '—' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @endif
    </div>
  </div>
</div>

</div><!-- /tab-panes -->

@push('scripts')
<script>
function toggleReason(sel, crewId) {
  var val      = sel.value;
  var reason   = document.querySelector('.reason-field-' + crewId);
  var category = document.querySelector('.reason-category-field-' + crewId);
  var replace  = document.querySelector('.replacement-field-' + crewId);
  if (reason) {
    reason.style.display  = (val === 'present' || val === 'late') ? 'none' : 'block';
  }
  if (category) {
    category.style.display = (val === 'present' || val === 'late') ? 'none' : 'block';
  }
  if (replace) {
    replace.style.display = (val === 'no_show' || val === 'back_out') ? 'block' : 'none';
  }
}
function loadBookingCrew(bookingId) {
  if (bookingId) {
    window.location.href = "{{ $attBase }}?booking_id=" + bookingId + "&date=" + document.querySelector('[name="attendance_date"]').value;
  }
}
</script>
@endpush
@endsection
