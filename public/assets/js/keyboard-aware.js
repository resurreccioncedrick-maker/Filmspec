/* Keeps the focused form field visible above the on-screen mobile keyboard.
   Two problems this solves together:
   1. Fixed/centered containers (modals, auth cards) are sized against the full
      layout viewport, which does NOT shrink when the keyboard opens — so a field
      near the bottom of one can end up hidden behind the keyboard even though the
      container "fits" on screen. We track the real visible height via the
      VisualViewport API into a --vvh custom property that CSS can size against.
   2. Even in normal page flow, the browser's own "scroll focused input into view"
      doesn't always fire correctly inside fixed-position containers — so we also
      explicitly scroll the focused field into view once the keyboard has settled. */
(function () {
  function syncVvh() {
    var h = (window.visualViewport && window.visualViewport.height) || window.innerHeight;
    document.documentElement.style.setProperty('--vvh', h + 'px');
  }
  syncVvh();

  function isField(el) {
    if (!el || !el.tagName) return false;
    if (!/^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName)) return false;
    var t = (el.type || '').toLowerCase();
    return t !== 'checkbox' && t !== 'radio' && t !== 'button' && t !== 'submit';
  }

  function revealActiveField(delay) {
    var el = document.activeElement;
    if (!isField(el)) return;
    setTimeout(function () {
      if (document.activeElement === el) {
        el.scrollIntoView({ block: 'center', behavior: 'smooth' });
      }
    }, delay);
  }

  document.addEventListener('focusin', function (e) {
    if (isField(e.target)) revealActiveField(250);
  });

  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', function () {
      syncVvh();
      revealActiveField(80);
    });
    window.visualViewport.addEventListener('scroll', syncVvh);
  } else {
    window.addEventListener('resize', syncVvh);
  }
})();
