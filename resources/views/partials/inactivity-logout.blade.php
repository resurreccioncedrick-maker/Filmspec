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
  var TIMEOUT_MS = 20 * 60 * 1000;
  var WARN_MS = 60 * 1000;
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
    if (t - lastMark < 2000) return;
    lastMark = t;
    localStorage.setItem(STORAGE_KEY, String(t));
    schedule();
  }

  ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'wheel'].forEach(function (evt) {
    window.addEventListener(evt, markActivity, { passive: true });
  });

  window.addEventListener('storage', function (e) {
    if (e.key === STORAGE_KEY) schedule();
  });

  document.addEventListener('visibilitychange', function () {
    if (! document.hidden) schedule();
  });

  var stayBtn = document.getElementById('inactivityStayBtn');
  if (stayBtn) stayBtn.addEventListener('click', function () {
    lastMark = 0;
    markActivity();
  });

  markActivity();
})();
</script>
@endauth
