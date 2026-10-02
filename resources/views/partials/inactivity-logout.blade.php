{{-- Auto-logout after inactivity. Self-contained (its own CSS/JS, no dependency on the host
     page's modal system) so the same partial works identically whether it's included in the
     staff layout, the Crew Portal, or the client-facing home page. Activity is tracked via a
     shared localStorage timestamp so multiple tabs of the same site don't fight each other —
     typing in one tab keeps every tab's timer alive, and a tab that's been backgrounded
     recalculates its remaining time (rather than trusting a possibly-throttled setTimeout) the
     moment it's focused again. --}}
@auth
<div id="inactivityOverlay" style="display:none;position:fixed;inset:0;background:rgba(11,26,51,.55);z-index:99999;align-items:center;justify-content:center;padding:16px">
  <div style="background:#fff;border-radius:14px;max-width:360px;width:100%;padding:26px 24px;box-shadow:0 20px 50px rgba(0,0,0,.3);font-family:'DM Sans',system-ui,sans-serif;text-align:center">
    <div style="width:48px;height:48px;border-radius:50%;background:#fef3c7;color:#d97706;display:flex;align-items:center;justify-content:center;margin:0 auto 14px">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
    </div>
    <div style="font-weight:700;font-size:16px;color:#0B1A33;margin-bottom:8px">Still there?</div>
    <div style="font-size:13.5px;color:#445e7a;line-height:1.6;margin-bottom:18px">
      You've been inactive for a while. For your security, you'll be signed out in
      <strong id="inactivityCountdown">60</strong> seconds.
    </div>
    <button type="button" id="inactivityStayBtn" style="width:100%;background:#0060C7;color:#fff;border:none;border-radius:9px;padding:12px;font-weight:700;font-size:13.5px;cursor:pointer;font-family:inherit">
      Stay Signed In
    </button>
  </div>
</div>
<form id="inactivityLogoutForm" method="POST" action="{{ route('logout') }}" style="display:none">
  @csrf
</form>
<script>
(function(){
  var TIMEOUT_MS = 20 * 60 * 1000; // 20 minutes of inactivity
  var WARN_MS = 60 * 1000;         // show the warning for the last 60 seconds
  var STORAGE_KEY = 'fs_last_activity';

  var overlay = document.getElementById('inactivityOverlay');
  var countdownEl = document.getElementById('inactivityCountdown');
  var logoutTimer, warnTimer, countdownTimer;

  function now() { return Date.now(); }

  function getLastActivity() {
    var v = parseInt(localStorage.getItem(STORAGE_KEY) || '0', 10);
    return v || now();
  }

  function schedule() {
    clearTimeout(logoutTimer);
    clearTimeout(warnTimer);
    clearInterval(countdownTimer);
    hideWarning();

    var elapsed = now() - getLastActivity();
    var remaining = Math.max(0, TIMEOUT_MS - elapsed);
    var toWarn = Math.max(0, remaining - WARN_MS);

    warnTimer = setTimeout(showWarning, toWarn);
    logoutTimer = setTimeout(doLogout, remaining);
  }

  function showWarning() {
    var secs = Math.ceil(WARN_MS / 1000);
    updateCountdown(secs);
    overlay.style.display = 'flex';
    countdownTimer = setInterval(function () {
      secs--;
      updateCountdown(secs);
      if (secs <= 0) clearInterval(countdownTimer);
    }, 1000);
  }

  function updateCountdown(s) {
    if (countdownEl) countdownEl.textContent = Math.max(0, s);
  }

  function hideWarning() {
    overlay.style.display = 'none';
  }

  function doLogout() {
    var f = document.getElementById('inactivityLogoutForm');
    if (f) f.submit();
  }

  var lastMark = 0;
  function markActivity() {
    var t = now();
    // Throttled — we don't need (or want) a localStorage write on every single mousemove.
    if (t - lastMark < 2000) return;
    lastMark = t;
    localStorage.setItem(STORAGE_KEY, String(t));
    schedule();
  }

  ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'wheel'].forEach(function (evt) {
    window.addEventListener(evt, markActivity, { passive: true });
  });

  // Another tab just recorded activity — keep this tab's clock in sync instead of logging out
  // a tab the person isn't currently looking at while they're actively using a different one.
  window.addEventListener('storage', function (e) {
    if (e.key === STORAGE_KEY) schedule();
  });

  // A backgrounded tab's own setTimeout can fire late (browsers throttle inactive-tab timers),
  // so recompute from the real last-activity timestamp the moment this tab is looked at again,
  // rather than trusting whatever the throttled timer already queued.
  document.addEventListener('visibilitychange', function () {
    if (! document.hidden) schedule();
  });

  var stayBtn = document.getElementById('inactivityStayBtn');
  if (stayBtn) stayBtn.addEventListener('click', function () {
    lastMark = 0; // force the next markActivity() through the throttle
    markActivity();
  });

  markActivity();
})();
</script>
@endauth
