{{-- "Access log — last 7 days" collapsible (Part 16). Reads the login/logout entries
     activity_logs already records under module 'auth'. Expects $accessLog. --}}
<details style="margin-top:10px;border-top:1px solid var(--border);padding-top:10px">
  <summary style="font-size:12px;color:var(--muted);cursor:pointer;display:flex;justify-content:space-between">
    <span>Access log ({{ $accessLog->count() }})</span>
    <span style="color:var(--text-muted)">last 7 days</span>
  </summary>
  <div style="margin-top:8px">
    @forelse ($accessLog as $a)
    <div style="display:flex;justify-content:space-between;gap:12px;padding:4px 0;border-bottom:1px solid var(--border);font-size:.8rem">
      <div style="min-width:0">
        <span class="badge {{ $a->action === 'login' ? 'badge-green' : 'badge-gray' }}" style="margin-right:6px">
          {{ $a->action === 'login' ? 'Signed in' : 'Signed out' }}
        </span>
        {{ trim($a->user_name ?? '') ?: 'Unknown user' }}
        @if ($a->role_name)
        <span style="color:var(--text-muted);font-size:.72rem">({{ str_replace('_', ' ', $a->role_name) }})</span>
        @endif
      </div>
      <div style="white-space:nowrap;color:var(--text-muted);font-size:.72rem">
        <span style="font-family:monospace">{{ $a->ip_address ?: '—' }}</span> ·
        {{ \Carbon\Carbon::parse($a->created_at)->format('M j, g:ia') }}
      </div>
    </div>
    @empty
    <div style="font-size:.8rem;color:var(--text-muted)">No sign-ins recorded in the last 7 days.</div>
    @endforelse

    @if (in_array('activity', config("filmspec.role_permissions." . (auth()->user()->role->role_name ?? ''), []), true))
    <a href="{{ route('activity') }}?module=auth"
       style="display:inline-block;margin-top:8px;font-size:.75rem;color:var(--accent)">Full access history &rarr;</a>
    @endif
  </div>
</details>
