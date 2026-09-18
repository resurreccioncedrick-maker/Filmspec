@extends('layouts.app')

@section('pageTitle', 'Support Chat')

@section('breadcrumb')
<span>Support Chat</span>
@if ($client)
<span class="bc-sep">/</span>
<span>{{ $client->company_name ?: $client->contact_person ?: 'Client #' . $activeClientId }}</span>
@endif
@endsection

@section('content')

@php
  $initials = function (?string $company, ?string $contact) {
      $src = trim((string) ($company ?: $contact));
      if ($src === '') return '?';
      $words = preg_split('/\s+/', $src);
      $chars = count($words) >= 2 ? $words[0][0] . $words[1][0] : mb_substr($src, 0, 2);
      return mb_strtoupper($chars);
  };
  $avatarPalette = ['#3b82f6,#7c3aed', '#16a34a,#0891b2', '#d97706,#dc2626', '#7c3aed,#0060C7', '#0891b2,#16a34a'];
@endphp

@if (session('sc_flash'))
<div class="alert alert-{{ session('sc_flash')['type'] }}" data-autohide>
  <i data-feather="{{ session('sc_flash')['type'] === 'success' ? 'check-circle' : 'alert-circle' }}"></i>
  {{ session('sc_flash')['text'] }}
</div>
@endif

<div class="sc-inbox">

  <div class="sc-list">
    <div class="sc-list-header">
      <span class="sc-list-title"><i data-feather="message-circle" style="width:15px;height:15px"></i>Conversations</span>
      <span class="sc-list-count">{{ $threads->count() }}</span>
    </div>
    <div class="sc-list-scroll">
      @if ($threads->isEmpty())
      <div class="empty-state" style="padding:32px 20px"><i data-feather="message-circle"></i><h3>No conversations yet</h3><p>Messages clients send from the homepage chat widget will show up here.</p></div>
      @else
      @foreach ($threads as $t)
      <a href="{{ route('support-chat.show', $t->client_id) }}" class="sc-conv {{ $activeClientId === $t->client_id ? 'active' : '' }} {{ $t->unread ? 'unread' : '' }}">
        <div class="sc-conv-avatar" style="background:linear-gradient(135deg,{{ $avatarPalette[$t->client_id % count($avatarPalette)] }})">{{ $initials($t->company_name, $t->contact_person) }}</div>
        <div class="sc-conv-main">
          <div class="sc-conv-top">
            <span class="sc-conv-name">{{ $t->company_name ?: $t->contact_person ?: 'Client #' . $t->client_id }}</span>
            <span class="sc-conv-time">{{ $t->last_message_at ? \Illuminate\Support\Carbon::parse($t->last_message_at)->diffForHumans(null, true) : '' }}</span>
          </div>
          <div class="sc-conv-preview">
            @if ($t->last_is_internal)<span class="sc-conv-internal-tag">Internal</span>@endif
            {{ $t->last_body !== '' ? $t->last_body : '(attachment)' }}
          </div>
        </div>
        @if ($t->unread)<span class="sc-conv-dot"></span>@endif
      </a>
      @endforeach
      @endif
    </div>
  </div>

  <div class="sc-thread">
    @if (! $client)
    <div class="empty-state" style="margin:auto"><i data-feather="message-circle"></i><h3>Select a conversation</h3><p>Pick a client on the left to view their messages.</p></div>
    @else
    <div class="sc-thread-header">
      <div class="sc-conv-avatar" style="width:36px;height:36px;font-size:13px;background:linear-gradient(135deg,{{ $avatarPalette[$activeClientId % count($avatarPalette)] }})">{{ $initials($client->company_name, $client->contact_person) }}</div>
      <div>
        <div class="sc-thread-name">{{ $client->company_name ?: $client->contact_person ?: 'Client #' . $activeClientId }}</div>
        @if ($client->company_name && $client->contact_person)
        <div class="sc-thread-sub">{{ $client->contact_person }}</div>
        @endif
      </div>
    </div>

    <div id="scMessages" class="sc-thread-scroll" data-last-id="{{ $messages->last()->message_id ?? 0 }}">
      @if ($messages->isEmpty())
      <div class="empty-state" id="scEmpty" style="margin:auto"><i data-feather="message-circle"></i><h3>No messages yet</h3><p>Nothing sent in this conversation so far.</p></div>
      @endif
      @foreach ($messages as $m)
      @php $out = $m->author_role !== 'client'; @endphp
      <div class="sc-bubble-row {{ $out ? 'out' : 'in' }}">
        <div>
          <div class="sc-bubble {{ $m->is_internal ? 'internal' : '' }}">
            @if ($m->is_internal)<span class="sc-bubble-tag">Internal Only</span>@endif
            @if ($m->body !== '')<div class="sc-bubble-body">{{ $m->body }}</div>@endif
            @if ($m->attachment_path)
              @if (str_starts_with($m->attachment_mime, 'image/'))
              <img src="{{ route('support-chat.attachment', [$activeClientId, $m->message_id]) }}" alt="{{ $m->attachment_name }}" class="sc-bubble-img" onclick="window.open(this.src,'_blank')">
              @else
              <a href="{{ route('support-chat.attachment', [$activeClientId, $m->message_id]) }}" target="_blank" class="sc-bubble-file">
                <i data-feather="paperclip" style="width:13px;height:13px"></i> {{ $m->attachment_name }}
              </a>
              @endif
            @endif
          </div>
          <div class="sc-bubble-meta">{{ trim($m->first_name . ' ' . $m->last_name) }} &middot; {{ \Illuminate\Support\Carbon::parse($m->created_at)->format('M j, g:i a') }}</div>
        </div>
      </div>
      @endforeach
    </div>

    <form method="POST" action="{{ route('support-chat.show', $activeClientId) }}" enctype="multipart/form-data" class="sc-composer">
      @csrf
      <input type="hidden" name="action" value="post_message">
      <label class="sc-composer-attach" title="Attach a file">
        <i data-feather="paperclip" style="width:15px;height:15px"></i>
        <input type="file" name="attachment" style="display:none" onchange="this.closest('form').querySelector('.sc-attach-name').textContent = this.files[0] ? this.files[0].name : ''">
      </label>
      <textarea name="body" class="sc-composer-input" rows="1" placeholder="Write a reply…" onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault();this.form.submit();}"></textarea>
      <label class="sc-composer-internal">
        <input type="checkbox" name="is_internal" value="1"> Internal
      </label>
      <button type="submit" class="sc-composer-send" title="Send">
        <i data-feather="send" style="width:15px;height:15px"></i>
      </button>
      <span class="sc-attach-name"></span>
    </form>
    @endif
  </div>

</div>

@push('head')
<script>
function scEscapeHtml(s) {
  var d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}
function scBubbleHtml(m) {
  var out = m.author_role !== 'client';
  var internalTag = m.is_internal ? '<span class="sc-bubble-tag">Internal Only</span>' : '';
  var body = m.body ? '<div class="sc-bubble-body">' + scEscapeHtml(m.body) + '</div>' : '';
  var attach = '';
  if (m.attachment_path) {
    var url = '{{ url('/support-chat') }}/{{ $activeClientId }}/attachment/' + m.message_id;
    if ((m.attachment_mime || '').indexOf('image/') === 0) {
      attach = '<img src="' + url + '" class="sc-bubble-img" onclick="window.open(this.src,\'_blank\')">';
    } else {
      attach = '<a href="' + url + '" target="_blank" class="sc-bubble-file"><i data-feather="paperclip" style="width:13px;height:13px"></i> ' + scEscapeHtml(m.attachment_name || '') + '</a>';
    }
  }
  var name = scEscapeHtml((m.first_name || '') + ' ' + (m.last_name || ''));
  var when = new Date(m.created_at).toLocaleString();
  return '<div class="sc-bubble-row ' + (out ? 'out' : 'in') + '"><div>'
    + '<div class="sc-bubble ' + (m.is_internal ? 'internal' : '') + '">' + internalTag + body + attach + '</div>'
    + '<div class="sc-bubble-meta">' + name + ' &middot; ' + when + '</div>'
    + '</div></div>';
}
function pollSupportThread() {
  if (document.hidden) return;
  var container = document.getElementById('scMessages');
  if (!container) return;
  var lastId = parseInt(container.dataset.lastId || '0');
  fetch('{{ $activeClientId ? route('support-chat.poll', $activeClientId) : '' }}?after=' + lastId)
    .then(r => r.json())
    .then(d => {
      if (!d.messages || !d.messages.length) return;
      var empty = document.getElementById('scEmpty');
      if (empty) empty.remove();
      var atBottom = container.scrollTop + container.clientHeight >= container.scrollHeight - 30;
      var html = '';
      d.messages.forEach(m => { html += scBubbleHtml(m); });
      container.insertAdjacentHTML('beforeend', html);
      container.dataset.lastId = d.messages[d.messages.length - 1].message_id;
      if (atBottom) container.scrollTop = container.scrollHeight;
      if (window.feather) feather.replace();
    })
    .catch(() => {});
}
@if ($activeClientId)
setInterval(pollSupportThread, 12000);
document.addEventListener('visibilitychange', function () { if (!document.hidden) pollSupportThread(); });
document.addEventListener('DOMContentLoaded', function () {
  var c = document.getElementById('scMessages');
  if (c) c.scrollTop = c.scrollHeight;
});
@endif
</script>
@endpush

@endsection
