@extends('layouts.app')

@section('pageTitle', 'Activity Log')

@section('breadcrumb')
<span>Activity</span>
@endsection

@section('content')
@php $activityBase = route('activity'); @endphp

<div class="card">
  <div class="card-header">
    <h2 class="card-title">System Audit Log <span class="badge badge-blue" style="margin-left:8px">{{ number_format($total) }} entries</span></h2>
  </div>
  <div class="card-body" style="padding-bottom:0">
    <form method="GET" action="{{ $activityBase }}">
      <div class="filter-bar">
        <div class="search-input-wrap">
          <i data-feather="search"></i>
          <input type="text" name="q" class="form-control" placeholder="Search action, user, description…"
                 value="{{ $search }}">
        </div>
        <select name="module" class="form-control" style="width:auto">
          <option value="">All Modules</option>
          @foreach ($modules as $m)
          <option value="{{ $m }}" {{ $module === $m ? 'selected' : '' }}>
            {{ ucfirst($m) }}
          </option>
          @endforeach
        </select>
        <button type="submit" class="btn btn-primary btn-sm"><i data-feather="filter"></i> Filter</button>
        <a href="{{ $activityBase }}" class="btn btn-outline btn-sm"><i data-feather="refresh-cw"></i> Reset</a>
      </div>
    </form>
  </div>
  <div class="table-wrap">
    @if ($logs->isEmpty())
    <div class="empty-state"><i data-feather="activity"></i><h3>No log entries found</h3></div>
    @else
    <table>
      <thead>
        <tr><th>Time</th><th>User</th><th>Role</th><th>Action</th><th>Module</th><th>Description</th><th>IP Address</th></tr>
      </thead>
      <tbody>
        @foreach ($logs as $log)
        <tr>
          <td style="font-size:.78rem;white-space:nowrap;color:var(--text-muted)">
            {{ \Illuminate\Support\Carbon::parse($log->created_at)->format('M j, Y') }}<br>
            <strong>{{ \Illuminate\Support\Carbon::parse($log->created_at)->format('g:i:s a') }}</strong>
          </td>
          <td>
            <div style="font-weight:600;font-size:.875rem">{{ $log->user_name ?? 'System' }}</div>
            <div style="font-size:.72rem;color:var(--text-muted)">{{ $log->user_email ?? '' }}</div>
          </td>
          <td>
            @if ($log->role_name)
            <span class="badge badge-blue" style="font-size:.68rem">{{ ucwords(str_replace('_', ' ', $log->role_name)) }}</span>
            @else—@endif
          </td>
          <td><span class="badge {{ $actionBadge[$log->action] ?? 'badge-gray' }}">{{ $log->action }}</span></td>
          <td style="text-transform:capitalize;font-size:.83rem">{{ $log->module ?? '—' }}</td>
          <td style="color:var(--text-secondary);font-size:.83rem;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            {{ $log->description ?? '—' }}
          </td>
          <td style="font-size:.78rem;font-family:monospace;color:var(--text-muted)">{{ $log->ip_address ?? '—' }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @if ($pages > 1)
    <div style="padding:14px 16px">
      <div class="pagination">
        <a href="{{ $activityBase }}?p={{ max(1, $page - 1) }}&q={{ urlencode($search) }}&module={{ $module }}"
           class="page-btn" {{ $page <= 1 ? 'style="opacity:.4;pointer-events:none"' : '' }}>‹</a>
        @php
          $start = max(1, $page - 3);
          $end = min($pages, $page + 3);
        @endphp
        @if ($start > 1)<span class="page-btn">…</span>@endif
        @for ($pi = $start; $pi <= $end; $pi++)
        <a href="{{ $activityBase }}?p={{ $pi }}&q={{ urlencode($search) }}&module={{ $module }}"
           class="page-btn {{ $pi == $page ? 'active' : '' }}">{{ $pi }}</a>
        @endfor
        @if ($end < $pages)<span class="page-btn">…</span>@endif
        <a href="{{ $activityBase }}?p={{ min($pages, $page + 1) }}&q={{ urlencode($search) }}&module={{ $module }}"
           class="page-btn" {{ $page >= $pages ? 'style="opacity:.4;pointer-events:none"' : '' }}>›</a>
      </div>
      <div style="text-align:center;font-size:.78rem;color:var(--text-muted);margin-top:6px">
        Page {{ $page }} of {{ $pages }} ({{ number_format($total) }} entries)
      </div>
    </div>
    @endif
    @endif
  </div>
</div>
@endsection
