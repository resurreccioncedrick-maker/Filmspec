<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('pageTitle', 'Dashboard') — FilmSpec</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <script src="{{ asset('assets/js/feather.min.js') }}"></script>
  <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ filemtime(public_path('assets/css/app.css')) }}">
  @stack('head')
</head>
<body>
<div class="app-shell">

@php
  $navUser = auth()->user();
  $navRole = $navUser->role->role_name ?? '';
  $navRoleColors = [
    'super_admin'        => '#3b82f6',
    'admin'              => '#60a5fa',
    'operations_manager' => '#4ade80',
    'traffic'            => '#fb923c',
    'accounting'         => '#c084fc',
    'crew'               => '#2dd4bf',
  ];
  $navRoleLabels = [
    'super_admin'        => 'Super Admin',
    'admin'              => 'Admin',
    'operations_manager' => 'Ops Manager',
    'traffic'            => 'Traffic',
    'accounting'         => 'Accounting',
    'crew'               => 'Crew Member',
  ];
  $navRoleColor = $navRoleColors[$navRole] ?? '#5a7299';
  $navRoleLabel = $navRoleLabels[$navRole] ?? ucfirst(str_replace('_', ' ', $navRole));

  $nav = [
    'Overview' => [
      'dashboard' => ['icon' => 'grid', 'label' => 'Dashboard', 'mod' => 'dashboard'],
    ],
    'Operations' => [
      'bookings'  => ['icon' => 'calendar', 'label' => 'Bookings', 'mod' => 'bookings'],
      'clients'   => ['icon' => 'briefcase', 'label' => 'Clients', 'mod' => 'clients'],
      'billing'   => ['icon' => 'file-text', 'label' => 'Billing & POS', 'mod' => 'billing'],
      'field-requests' => ['icon' => 'truck', 'label' => 'Field Resource Requests', 'mod' => 'field_requests'],
      'support-chat' => ['icon' => 'message-circle', 'label' => 'Support Chat', 'mod' => 'support_chat'],
    ],
    'Inventory' => [
      'equipment'   => ['icon' => 'camera', 'label' => 'Equipment', 'mod' => 'equipment'],
      'accessories' => ['icon' => 'package', 'label' => 'Accessories', 'mod' => 'accessories'],
      'transport'   => ['icon' => 'truck', 'label' => 'Transport', 'mod' => 'transport'],
    ],
    'Crew' => [
      'crew'       => ['icon' => 'users', 'label' => 'Crew Management', 'mod' => 'crew'],
      'attendance' => ['icon' => 'clock', 'label' => 'Attendance', 'mod' => 'attendance'],
    ],
    // A single 'Analytics' row with a flyout of the 6 real pages, rather than 6 flat rows —
    // the flat list was the tallest group in the sidebar by a wide margin. Each child still
    // carries its own 'mod', checked individually, so a future role with only partial
    // Analytics access degrades correctly instead of all-or-nothing.
    'Analytics' => [
      'analytics' => ['icon' => 'pie-chart', 'label' => 'Analytics', 'flyout' => [
        'reports' => ['icon' => 'bar-chart-2', 'label' => 'Reports', 'mod' => 'reports'],
        'cost-estimates' => ['icon' => 'file-text', 'label' => 'Cost Estimates', 'mod' => 'cost_estimates'],
        'crew-data' => ['icon' => 'user-check', 'label' => 'Crew Analytics', 'mod' => 'crew_data'],
        'equipment-data' => ['icon' => 'trending-up', 'label' => 'Equipment Analytics', 'mod' => 'equipment_data'],
        'profit-loss' => ['icon' => 'dollar-sign', 'label' => 'Profit and Loss', 'mod' => 'profit_loss'],
        'calendar-data' => ['icon' => 'calendar', 'label' => 'Calendar Analytics', 'mod' => 'calendar_data'],
      ]],
    ],
    'Team' => [
      'reminders' => ['icon' => 'bell', 'label' => 'Reminders', 'mod' => 'reminders'],
      'faqs' => ['icon' => 'help-circle', 'label' => 'FAQ / Help Center', 'mod' => 'faqs'],
      'repair-purchase' => ['icon' => 'tool', 'label' => 'Repair / Purchase', 'mod' => 'repair_purchase'],
      'incidents' => ['icon' => 'alert-triangle', 'label' => 'Incidents', 'mod' => 'incidents'],
    ],
    'Admin' => [
      'superadmin' => ['icon' => 'shield', 'label' => 'Super Admin', 'mod' => 'superadmin', 'sa' => true],
      'data_retention' => ['icon' => 'trash-2', 'label' => 'Data Retention', 'mod' => 'data_retention', 'sa' => true],
    ],
  ];

  $canAccess = fn (string $mod) => in_array($mod, config("filmspec.role_permissions.$navRole", []), true);

  // Live "needs your attention" count for the Billing nav item — same filters
  // BillingController::index() already uses for pendingDiscounts/pendingPayBkgs, just as a
  // cheap count rather than the full row data. Only computed for roles that can see Billing
  // at all, so this doesn't add a query to every page load for every role.
  $billingBadgeCount = 0;
  if ($canAccess('billing')) {
    $billingBadgeCount = \Illuminate\Support\Facades\DB::table('booking_discounts')->where('status', 'pending')->count()
      + \Illuminate\Support\Facades\DB::table('bookings')
          ->whereIn('booking_status', ['confirmed', 'ongoing', 'completed'])
          ->where('payment_status', '!=', 'paid')
          ->where(function ($w) {
              $w->whereNull('approval_status')->orWhere('approval_status', 'approved');
          })
          ->count();
  }

  // Global "new messages waiting" counts for the Support Chat and Bookings nav items — the
  // per-thread unread dot on the Support Chat inbox and BookingDetailController's own
  // $unreadComments only ever surface once staff is already on that specific page; this reuses
  // the exact same MessageReadTracker::unreadFlags() logic, just across every thread at once,
  // so staff notice new client messages from anywhere in the sidebar.
  $supportChatBadgeCount = 0;
  if ($canAccess('support_chat')) {
    $lastPerClient = \Illuminate\Support\Facades\DB::table('client_support_messages')
      ->where('is_internal', false)
      ->select('client_id as thread_id', \Illuminate\Support\Facades\DB::raw('MAX(created_at) as created_at'))
      ->groupBy('client_id')->get();
    $supportChatBadgeCount = count(array_filter(
      \App\Support\MessageReadTracker::unreadFlags($navUser->user_id, 'support', $lastPerClient)
    ));
  }
  $bookingsBadgeCount = 0;
  if ($canAccess('bookings')) {
    $lastPerBooking = \Illuminate\Support\Facades\DB::table('booking_comments')
      ->select('booking_id as thread_id', \Illuminate\Support\Facades\DB::raw('MAX(created_at) as created_at'))
      ->groupBy('booking_id')->get();
    $bookingsBadgeCount = count(array_filter(
      \App\Support\MessageReadTracker::unreadFlags($navUser->user_id, 'booking', $lastPerBooking)
    ));
  }

  $pageUrl = fn (string $page) => route(config("filmspec.ported_pages.$page"));

  $curPage = array_search(Route::currentRouteName(), config('filmspec.ported_pages'), true) ?: '';
@endphp

<aside class="sidebar">
  <div class="sidebar-brand">
    <img src="{{ asset('assets/images/logo.png') }}" alt="FilmSpec" style="height:36px;max-width:148px;object-fit:contain;display:block">
    <div class="brand-sub">Operations Platform</div>
  </div>

  <div class="sidebar-user">
    <div class="user-avatar">{{ strtoupper(substr($navUser->first_name ?? 'U', 0, 1)) }}</div>
    <div class="user-info">
      <span class="user-name">{{ trim(($navUser->first_name ?? '') . ' ' . ($navUser->last_name ?? '')) }}</span>
      <span class="role-badge" style="background:{{ $navRoleColor }}30;color:#fff;border-color:{{ $navRoleColor }}60">{{ $navRoleLabel }}</span>
    </div>
  </div>

  <nav class="sidebar-nav">
    @php $firstGroup = true; @endphp
    @foreach ($nav as $groupName => $items)
      @php
        $visible = false;
        foreach ($items as $item) {
          if (isset($item['flyout'])) {
            foreach ($item['flyout'] as $child) { if ($canAccess($child['mod'])) { $visible = true; break 2; } }
          } elseif ($canAccess($item['mod'])) { $visible = true; break; }
        }
      @endphp
      @continue(! $visible)
      @if (! $firstGroup)<hr class="nav-section-divider">@endif
      @php $firstGroup = false; @endphp
      <div class="nav-group-label">{{ $groupName }}</div>
      @foreach ($items as $page => $item)
        @if (isset($item['flyout']))
          @php
            $visibleChildren = collect($item['flyout'])->filter(fn ($child) => $canAccess($child['mod']));
          @endphp
          @continue($visibleChildren->isEmpty())
          @php
            $isActiveGroup = $visibleChildren->contains(fn ($child) => $curPage === ($child['page'] ?? $child['mod']));
          @endphp
          <button type="button" class="nav-item nav-flyout-trigger {{ $isActiveGroup ? 'active' : '' }}" data-flyout-target="flyout-{{ $page }}">
            <i data-feather="{{ $item['icon'] }}"></i>
            <span>{{ $item['label'] }}</span>
            <i data-feather="chevron-right" class="nav-flyout-chevron"></i>
          </button>
        @else
          @continue(! $canAccess($item['mod']))
          {{-- URL resolves off 'page' when present (for an item that shares another module's
               permission but has its own route), else off 'mod' — matches ported_pages' keys,
               not the $page array key, since a hyphenated key like 'cost-estimates' never
               matched the config's 'cost_estimates' entry, which silently fell through to a
               dead legacy URL. --}}
          @php $urlKey = $item['page'] ?? $item['mod']; @endphp
          <a href="{{ $pageUrl($urlKey) }}" class="nav-item {{ $curPage === $urlKey ? 'active' : '' }}">
            <i data-feather="{{ $item['icon'] }}"></i>
            <span>{{ $item['label'] }}</span>
            @if (! empty($item['sa']))<span class="superadmin-badge">SA</span>@endif
            @if ($urlKey === 'billing' && $billingBadgeCount > 0)<span class="superadmin-badge" style="background:var(--orange, #f97316)" title="Pending discount approvals + unbilled confirmed bookings">{{ $billingBadgeCount }}</span>@endif
            @if ($urlKey === 'support_chat' && $supportChatBadgeCount > 0)<span class="superadmin-badge" style="background:var(--orange, #f97316)" title="Conversations with new messages">{{ $supportChatBadgeCount }}</span>@endif
            @if ($urlKey === 'bookings' && $bookingsBadgeCount > 0)<span class="superadmin-badge" style="background:var(--orange, #f97316)" title="Bookings with new comments">{{ $bookingsBadgeCount }}</span>@endif
          </a>
        @endif
      @endforeach
    @endforeach
  </nav>

  <div class="sidebar-footer">
    <a href="{{ $pageUrl('profile') }}" class="nav-item {{ $curPage === 'profile' ? 'active' : '' }}">
      <i data-feather="settings"></i><span>My Profile</span>
    </a>
    <form method="POST" action="{{ route('logout') }}">
      @csrf
      <button type="submit" class="nav-item nav-logout" style="font-family:inherit;font-size:inherit">
        <i data-feather="log-out"></i><span>Logout</span>
      </button>
    </form>
  </div>
</aside>

{{-- Flyout panels for nav groups with a 'flyout' sub-list (e.g. Analytics) — rendered as
     siblings of <aside>, not nested inside it, because .sidebar has overflow:hidden and
     .sidebar-nav has overflow-y:auto; a panel positioned absolute/relative to either of
     those would get silently clipped at the sidebar's edge instead of floating past it.
     app.js positions each one with position:fixed against its trigger's real screen
     position on hover/focus, so it tracks correctly even if the sidebar itself scrolls. --}}
@foreach ($nav as $groupName => $items)
  @foreach ($items as $page => $item)
    @continue(! isset($item['flyout']))
    @php $visibleChildren = collect($item['flyout'])->filter(fn ($child) => $canAccess($child['mod'])); @endphp
    @continue($visibleChildren->isEmpty())
    <div class="nav-flyout" id="flyout-{{ $page }}">
      @foreach ($visibleChildren as $childKey => $child)
        @php $childUrlKey = $child['page'] ?? $child['mod']; @endphp
        <a href="{{ $pageUrl($childUrlKey) }}" class="nav-flyout-item {{ $curPage === $childUrlKey ? 'active' : '' }}">
          <i data-feather="{{ $child['icon'] }}"></i>
          <span>{{ $child['label'] }}</span>
        </a>
      @endforeach
    </div>
  @endforeach
@endforeach

<main class="main-content">
  <header class="topbar">
    <div class="topbar-left">
      <h1 class="page-title">@yield('pageTitle', 'Dashboard')</h1>
      @hasSection('breadcrumb')
      <nav class="breadcrumb">
        @yield('breadcrumb')
      </nav>
      @endif
    </div>
    <div class="topbar-right">
      <span class="topbar-date">{{ date('D, M j, Y') }}</span>
      @yield('topbarActions')
    </div>
  </header>
  <div class="page-body">
    @yield('content')
  </div>
  <footer style="border-top:1px solid var(--border);padding:12px 24px;text-align:center;font-size:11.5px;color:var(--muted);background:var(--surface)">
    <span style="font-family:var(--font-display);color:var(--accent);font-size:13px;letter-spacing:2px">FilmSpec</span>
    <span style="margin:0 8px;opacity:.4">|</span>
    &copy; {{ date('Y') }} FilmSpec. All rights reserved.
    <span style="margin:0 8px;opacity:.4">|</span>
    Integrated Film Operations Platform
  </footer>
</main>
</div>

<div class="toast-container"></div>
<script src="{{ asset('assets/js/keyboard-aware.js') }}"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
