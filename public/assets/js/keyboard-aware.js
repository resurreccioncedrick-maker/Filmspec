/* Keeps the focused form field visible above the on-screen mobile keyboard.
   Two problems this solves together:
   1. Fixed/centered containers (modals, auth cards) are sized against the full
      layout viewport, which does NOT shrink when the keyboard opens in most
      browsers — so a field near the bottom of one can end up hidden behind the
      keyboard even though the container "fits" on screen. We track the real
      visible height via the VisualViewport API into a --vvh custom property
      that CSS can size against.
   2. The browser's own "scroll focused input into view" doesn't fire reliably
      everywhere — it's especially unreliable inside in-app WebViews (Messenger,
      Instagram, Line, etc.), which often don't fire visualViewport/resize events
      at a predictable time relative to the keyboard's open animation, if at all.
      Rather than depend on any one event firing, we poll for a couple of
      seconds after a field is focused and keep re-correcting the scroll
      position — this self-heals regardless of what events the host browser
      actually fires, or when. */
(function () {
  function currentVisibleHeight() {
    return (window.visualViewport && window.visualViewport.height) || window.innerHeight;
  }
  function syncVvh() {
    document.documentElement.style.setProperty('--vvh', currentVisibleHeight() + 'px');
  }
  syncVvh();

  function isField(el) {
    if (!el || !el.tagName) return false;
    if (!/^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName)) return false;
    var t = (el.type || '').toLowerCase();
    return t !== 'checkbox' && t !== 'radio' && t !== 'button' && t !== 'submit';
  }

  var pollTimer = null;
  function stopPolling() {
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
  }
  function startPolling(el) {
    stopPolling();
    var ticks = 0;
    pollTimer = setInterval(function () {
      ticks++;
      syncVvh();
      if (document.activeElement !== el) { stopPolling(); return; }
      el.scrollIntoView({ block: 'center', behavior: 'smooth' });
      if (ticks >= 14) stopPolling(); // ~2.1s of correction at 150ms ticks, then stop
    }, 150);
  }

  document.addEventListener('focusin', function (e) {
    if (isField(e.target)) startPolling(e.target);
  });
  document.addEventListener('focusout', function (e) {
    if (isField(e.target)) stopPolling();
  });

  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', syncVvh);
    window.visualViewport.addEventListener('scroll', syncVvh);
  }
  window.addEventListener('resize', syncVvh);
  window.addEventListener('orientationchange', syncVvh);
})();
