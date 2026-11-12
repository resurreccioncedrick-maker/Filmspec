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
      if (ticks >= 14) stopPolling();
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
