@extends('layouts.app')

@section('pageTitle', 'Data Retention')

@section('breadcrumb')
<span>Data Retention</span>
@endsection

@section('topbarActions')
<span class="badge badge-purple" style="padding:5px 12px;font-size:.75rem">Super Admin Mode</span>
@endsection

@section('content')
@php $drBase = route('data-retention'); @endphp

@if ($msg)
<div class="alert alert-{{ $msg['type'] }}" data-autohide>
  <i data-feather="{{ $msg['type'] === 'success' ? 'check-circle' : 'alert-circle' }}"></i>
  {!! $msg['text'] !!}
</div>
@endif

<!-- KPI row -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:22px">
  <div class="stat-card" style="--sb:#0891b2">
    <div class="stat-icon" style="background:#cffafe;color:#0891b2"><i data-feather="calendar"></i></div>
    <div class="stat-value">{{ $stats['retention_days'] }}</div>
    <div class="stat-label">Day Retention Window</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="message-circle"></i></div>
    <div class="stat-value">{{ number_format($stats['total_messages']) }}</div>
    <div class="stat-label">Total Support Messages</div>
  </div>
  <div class="stat-card" style="--sb:#d97706">
    <div class="stat-icon" style="background:#fef3c7;color:#d97706"><i data-feather="clock"></i></div>
    <div class="stat-value">{{ number_format($stats['eligible_count']) }}</div>
    <div class="stat-label">Eligible For Cleanup Now</div>
    @if ($stats['eligible_with_attachment'] > 0)
    <span class="stat-delta" style="color:#d97706"><i data-feather="paperclip"></i> {{ $stats['eligible_with_attachment'] }} with attachments</span>
    @endif
  </div>
  <div class="stat-card">
    <div class="stat-icon"><i data-feather="check-circle"></i></div>
    <div class="stat-value" style="font-size:1rem">
      {{ $stats['last_run_at'] ? \Illuminate\Support\Carbon::parse($stats['last_run_at'])->format('M j, g:ia') : 'Never' }}
    </div>
    <div class="stat-label">Last Cleanup Run</div>
    @if ($stats['last_run_at'])
    <span class="stat-delta up"><i data-feather="trash-2"></i> {{ $stats['last_run_deleted'] }} removed</span>
    @endif
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2 class="card-title">Support Chat Cleanup</h2>
  </div>
  <div class="card-body">
    <p style="color:var(--text-muted);font-size:.9rem;line-height:1.6;max-width:640px">
      Support chat messages (and any attached files) older than <strong>{{ $stats['retention_days'] }} days</strong>
      are removed automatically every day so the conversation history and file storage don't grow without bound.
      Change the window in <code>config/filmspec.php</code> (<code>support_chat_retention_days</code>) if needed.
    </p>

    <form method="POST" action="{{ $drBase }}" style="margin-top:18px"
          onsubmit="return confirm('Permanently delete {{ $stats['eligible_count'] }} support chat message(s) older than {{ $stats['retention_days'] }} days, including their attachments? This cannot be undone.')">
      @csrf
      <input type="hidden" name="action" value="run_now">
      <button type="submit" class="btn btn-primary" {{ $stats['eligible_count'] === 0 ? 'disabled' : '' }}>
        <i data-feather="trash-2"></i> Run Cleanup Now
      </button>
      @if ($stats['eligible_count'] === 0)
      <span style="margin-left:10px;color:var(--text-muted);font-size:.85rem">Nothing is old enough to remove right now.</span>
      @endif
    </form>
  </div>
</div>
@endsection
