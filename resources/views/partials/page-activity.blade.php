{{-- "Activity — recent changes" collapsible (Part 16). Expects $pageActivity and, optionally,
     $activityModule so the footer can link through to the filtered full history. --}}
<details style="margin-top:14px;border-top:1px solid var(--border);padding-top:10px">
  <summary style="font-size:12px;color:var(--muted);cursor:pointer;display:flex;justify-content:space-between">
    <span>Activity ({{ $pageActivity->count() }})</span>
    <span style="color:var(--text-muted)">recent changes</span>
  </summary>
  <div style="margin-top:8px">
    @forelse ($pageActivity as $a)
    <div style="display:flex;justify-content:space-between;gap:12px;padding:4px 0;border-bottom:1px solid var(--border);font-size:.8rem">
      <div style="min-width:0">
        {!! $a->description !!}
        <div style="font-size:.72rem;color:var(--text-muted)">
          {{ trim($a->user_name ?? '') ?: 'Unknown user' }} · {{ ucfirst($a->action) }}
        </div>
      </div>
      <div style="white-space:nowrap;color:var(--text-muted);font-size:.72rem">
        {{ \Carbon\Carbon::parse($a->created_at)->diffForHumans() }}
      </div>
    </div>
    @empty
    <div style="font-size:.8rem;color:var(--text-muted)">Nothing recorded yet.</div>
    @endforelse

    @if (! empty($activityModule) && in_array('activity', config("filmspec.role_permissions." . (auth()->user()->role->role_name ?? ''), []), true))
    <a href="{{ route('activity') }}?module={{ $activityModule }}"
       style="display:inline-block;margin-top:8px;font-size:.75rem;color:var(--accent)">See full history &rarr;</a>
    @endif
  </div>
</details>
