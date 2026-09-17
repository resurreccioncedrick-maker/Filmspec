@extends('layouts.app')

@section('pageTitle', 'My Profile')

@section('breadcrumb')
<span>Profile</span>
@endsection

@section('content')
@php $profileBase = route('profile'); @endphp

@if ($msg)
<div class="alert alert-{{ $msg['type'] }}" data-autohide>
  <i data-feather="{{ $msg['type'] === 'success' ? 'check-circle' : 'alert-circle' }}"></i>
  {{ $msg['text'] }}
</div>
@endif

<div class="dashboard-grid">
  <div style="display:flex;flex-direction:column;gap:22px">

    <!-- Profile card -->
    <div class="card">
      <div class="card-header"><h2 class="card-title">Profile Information</h2></div>
      <div class="card-body">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:22px">
          <div style="width:64px;height:64px;background:linear-gradient(135deg,var(--blue-600),var(--blue-400));border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:1.4rem;font-weight:800;color:white;flex-shrink:0">
            {{ strtoupper(substr($user->first_name ?? 'U', 0, 1)) }}
          </div>
          <div>
            <div style="font-family:var(--font-display);font-size:1.1rem;font-weight:700;color:var(--blue-900)">{{ ($user->first_name ?? '') . ' ' . ($user->last_name ?? '') }}</div>
            <div style="font-size:.83rem;color:var(--muted)">{{ $user->email ?? '' }}</div>
            <span class="badge badge-blue" style="margin-top:4px">{{ ucwords(str_replace('_', ' ', $user->role_name ?? 'user')) }}</span>
          </div>
        </div>
        <form method="POST" action="{{ $profileBase }}">
          @csrf
          <input type="hidden" name="action" value="update_profile">
          <div class="form-row">
            <div class="form-group">
              <label>First Name</label>
              <input type="text" name="first_name" class="form-control" value="{{ $user->first_name ?? '' }}" required>
            </div>
            <div class="form-group">
              <label>Last Name</label>
              <input type="text" name="last_name" class="form-control" value="{{ $user->last_name ?? '' }}" required>
            </div>
          </div>
          <div class="form-group">
            <label>Email <span style="color:var(--muted);font-weight:400">(contact admin to change)</span></label>
            <input type="email" class="form-control" value="{{ $user->email ?? '' }}" disabled style="background:var(--surface-bg)">
          </div>
          <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control" value="{{ $user->phone ?? '' }}" placeholder="+63 9XX XXX XXXX">
          </div>
          <button type="submit" class="btn btn-primary"><i data-feather="save"></i> Save Changes</button>
        </form>
      </div>
    </div>

    <!-- Change password -->
    <div class="card">
      <div class="card-header"><h2 class="card-title">Change Password</h2></div>
      <div class="card-body">
        <form method="POST" action="{{ $profileBase }}">
          @csrf
          <input type="hidden" name="action" value="change_password">
          <div class="form-group">
            <label>Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="form-group">
            <label>New Password</label>
            <input type="password" name="new_password" class="form-control" minlength="8" required>
          </div>
          <div class="form-group">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" minlength="8" required>
          </div>
          <button type="submit" class="btn btn-outline"><i data-feather="lock"></i> Change Password</button>
        </form>
      </div>
    </div>

  </div>
</div>
@endsection
