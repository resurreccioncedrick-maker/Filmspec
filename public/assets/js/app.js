// FilmSpec — App JS

document.addEventListener('DOMContentLoaded', init);

function init() {
  if (typeof feather !== 'undefined') {
    feather.replace({ 'stroke-width': 1.8, width: 14, height: 14 });
  }

  // ── Tabs ──────────────────────────────────────────────────
  document.querySelectorAll('[data-tab]').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const target = this.dataset.tab;
      const tabBar = this.closest('.tabs');
      if (tabBar) {
        tabBar.querySelectorAll('[data-tab]').forEach(b => b.classList.remove('active'));
      }
      this.classList.add('active');
      document.querySelectorAll('.tab-pane').forEach(p => {
        p.classList.toggle('active', p.id === target);
      });
      feather.replace({ 'stroke-width': 1.8, width: 14, height: 14 });
    });
  });

  // ── Nav flyouts (e.g. the Analytics group) ────────────────
  // Each trigger is a <button data-flyout-target="flyout-xyz">; the panel itself lives
  // outside <aside> in the DOM (see layouts/app.blade.php) so it isn't clipped by the
  // sidebar's own overflow. Positioned with getBoundingClientRect() on open, since the
  // panel uses position:fixed and has no reliable CSS-only anchor to the trigger.
  let navFlyoutCloseTimer = null;
  function openNavFlyout(trigger) {
    clearTimeout(navFlyoutCloseTimer);
    document.querySelectorAll('.nav-flyout.open').forEach(f => { if (f.id !== trigger.dataset.flyoutTarget) f.classList.remove('open'); });
    const panel = document.getElementById(trigger.dataset.flyoutTarget);
    if (!panel) return;
    const r = trigger.getBoundingClientRect();
    panel.style.top = Math.max(8, r.top) + 'px';
    panel.style.left = (r.right + 8) + 'px';
    panel.classList.add('open');
  }
  function scheduleNavFlyoutClose() {
    clearTimeout(navFlyoutCloseTimer);
    navFlyoutCloseTimer = setTimeout(() => {
      document.querySelectorAll('.nav-flyout.open').forEach(f => f.classList.remove('open'));
    }, 200);
  }
  document.querySelectorAll('.nav-flyout-trigger').forEach(trigger => {
    trigger.addEventListener('mouseenter', () => openNavFlyout(trigger));
    trigger.addEventListener('mouseleave', scheduleNavFlyoutClose);
    trigger.addEventListener('focus', () => openNavFlyout(trigger));
    trigger.addEventListener('click', (e) => { e.preventDefault(); openNavFlyout(trigger); });
  });
  document.querySelectorAll('.nav-flyout').forEach(panel => {
    panel.addEventListener('mouseenter', () => clearTimeout(navFlyoutCloseTimer));
    panel.addEventListener('mouseleave', scheduleNavFlyoutClose);
    panel.addEventListener('focusout', (e) => {
      if (!panel.contains(e.relatedTarget)) scheduleNavFlyoutClose();
    });
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.querySelectorAll('.nav-flyout.open').forEach(f => f.classList.remove('open'));
  });
  document.addEventListener('click', e => {
    if (!e.target.closest('.nav-flyout') && !e.target.closest('.nav-flyout-trigger')) {
      document.querySelectorAll('.nav-flyout.open').forEach(f => f.classList.remove('open'));
    }
  });
  window.addEventListener('scroll', () => {
    document.querySelectorAll('.nav-flyout.open').forEach(f => f.classList.remove('open'));
  }, true);

  // ── Modals ─────────────────────────────────────────────────
  // data-modal trigger (open)
  document.querySelectorAll('[data-modal]').forEach(el => {
    el.addEventListener('click', function(e) {
      e.preventDefault();
      openModal(this.dataset.modal);
    });
  });

  // Click outside to close
  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
      if (e.target === this) closeModalEl(this);
    });
  });

  // Close buttons
  document.querySelectorAll('.modal-close, [data-modal-close]').forEach(btn => {
    btn.addEventListener('click', function() {
      const ov = this.closest('.modal-overlay');
      if (ov) closeModalEl(ov);
    });
  });

  // ESC key
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-overlay.active').forEach(closeModalEl);
    }
  });

  // ── Auto-hide alerts ──────────────────────────────────────
  document.querySelectorAll('[data-autohide]').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity .4s, transform .4s';
      el.style.opacity = '0';
      el.style.transform = 'translateY(-8px)';
      setTimeout(() => el.remove(), 400);
    }, 4500);
  });

  // ── Global input profiling ────────────────────────────────
  // Auto-uppercase name fields
  const NAME_FIELDS = [
    'first_name','last_name','contact_person','company_name',
    'dop_name','head_crew_name','custodian_name','new_client_name',
  ];
  document.querySelectorAll('input[type="text"]').forEach(inp => {
    const nm = (inp.name || '').replace(/\[.*?\]/g,'').trim();
    if (NAME_FIELDS.includes(nm) || nm.endsWith('_name') || nm.endsWith('_person')) {
      inp.addEventListener('input', () => {
        const pos = inp.selectionStart;
        inp.value = inp.value.toUpperCase();
        try { inp.setSelectionRange(pos, pos); } catch(e) {}
      });
    }
  });

  // Phone inputs: enforce 09XXXXXXXXX (11 digits, starts with 09)
  document.querySelectorAll('input[name="phone"], input[name="new_client_phone"], input[type="tel"]').forEach(inp => {
    inp.setAttribute('maxlength', '11');
    inp.setAttribute('placeholder', inp.placeholder || '09XXXXXXXXX');
    inp.addEventListener('input', () => {
      inp.value = inp.value.replace(/\D/g, '').slice(0, 11);
    });
    inp.addEventListener('blur', () => {
      if (inp.value && !/^09\d{9}$/.test(inp.value)) {
        inp.setCustomValidity('Phone must be 11 digits starting with 09.');
        inp.reportValidity();
      } else {
        inp.setCustomValidity('');
      }
    });
    inp.addEventListener('input', () => { inp.setCustomValidity(''); });
  });

  // ── Sidebar nav scroll position ───────────────────────────
  // Every nav click is a full page load in this app, which used to snap the
  // sidebar back to the top even when a lower group (e.g. Analytics/Team/Admin)
  // was scrolled into view — restore it so the sidebar stays where you left it.
  (function persistSidebarScroll() {
    const nav = document.querySelector('.sidebar-nav');
    if (!nav) return;
    try {
      const saved = sessionStorage.getItem('sidebarNavScroll');
      if (saved !== null) nav.scrollTop = parseInt(saved, 10) || 0;
      nav.addEventListener('scroll', () => {
        try { sessionStorage.setItem('sidebarNavScroll', nav.scrollTop); } catch (e) {}
      });
    } catch (e) {}
  })();

  // ── Image upload previews ─────────────────────────────────
  document.querySelectorAll('.img-upload-zone input[type="file"]').forEach(input => {
    input.addEventListener('change', function() {
      const zone = this.closest('.img-upload-zone');
      if (!zone || !this.files[0]) return;
      const reader = new FileReader();
      reader.onload = e => {
        zone.classList.add('has-image');
        // Remove existing content except the input
        Array.from(zone.children).forEach(c => { if (c !== this) c.remove(); });
        const img = document.createElement('img');
        img.className = 'preview-img';
        img.src = e.target.result;
        zone.insertBefore(img, this);
      };
      reader.readAsDataURL(this.files[0]);
    });
  });
}

function openModal(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.classList.add('active');
  const count = (parseInt(document.body.dataset.modalOpenCount || '0', 10)) + 1;
  document.body.dataset.modalOpenCount = String(count);
  document.body.style.overflow = 'hidden';
  if (typeof feather !== 'undefined') {
    feather.replace({ 'stroke-width': 1.8, width: 14, height: 14 });
  }
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) closeModalEl(el);
}

function closeModalEl(el) {
  el.classList.remove('active');
  const count = Math.max(0, (parseInt(document.body.dataset.modalOpenCount || '0', 10)) - 1);
  document.body.dataset.modalOpenCount = String(count);
  if (count === 0) document.body.style.overflow = '';
}

// Position a .crew-slot-drop (or similar) dropdown as position:fixed, anchored
// to its input's current on-screen location - this lets it escape a scrollable
// ancestor (e.g. a modal-body with overflow-y:auto), which would otherwise clip
// an absolutely-positioned dropdown that overflows the ancestor's clip box.
function positionSearchDrop(inputEl, dropEl) {
  if (!inputEl || !dropEl) return;
  // .modal has `transform:scale(...)` (for its open/close animation), and per spec any
  // non-none transform on an ancestor makes IT the containing block for position:fixed
  // descendants instead of the viewport - so a fixed dropdown left inside .modal renders
  // at the wrong spot even with viewport-relative coordinates. Move it to <body> once (a
  // "portal") so position:fixed always resolves against the real viewport.
  if (dropEl.parentElement !== document.body) {
    document.body.appendChild(dropEl);
  }
  const rect = inputEl.getBoundingClientRect();
  dropEl.style.position = 'fixed';
  dropEl.style.left = rect.left + 'px';
  dropEl.style.right = 'auto'; // the base .crew-slot-drop class sets right:0 for its
                                // original absolute-positioning use - left+right+width
                                // together is over-constrained, and right was winning.
  dropEl.style.top = (rect.bottom + 3) + 'px';
  dropEl.style.width = Math.max(rect.width, 210) + 'px';
}

// ── Reusable table/list search + filter + pagination (booking tab lists) ──
function listFilter(opts) {
  const rows = Array.from(document.querySelectorAll(opts.rowSelector));
  const q = opts.searchId ? (document.getElementById(opts.searchId)?.value || '').toLowerCase().trim() : '';
  const filterVal = opts.filterId ? (document.getElementById(opts.filterId)?.value || '') : '';
  const matches = rows.filter((row) => {
    const searchOk = !q || row.textContent.toLowerCase().includes(q);
    const filterOk = !filterVal || row.dataset.filter === filterVal;
    return searchOk && filterOk;
  });
  const pager = opts.pagerId ? document.getElementById(opts.pagerId) : null;
  if (pager && opts.pageSize) {
    pager.dataset.page = '1';
    listPaginate(matches, rows, opts);
  } else {
    rows.forEach((row) => { row.style.display = matches.includes(row) ? '' : 'none'; });
  }
}

function listPaginate(matches, allRows, opts) {
  const pager = document.getElementById(opts.pagerId);
  if (!pager) return;
  const pageSize = opts.pageSize;
  const total = matches.length;
  const pageCount = Math.max(1, Math.ceil(total / pageSize));
  let page = parseInt(pager.dataset.page || '1', 10);
  if (page > pageCount) page = pageCount;
  if (page < 1) page = 1;
  pager.dataset.page = String(page);

  allRows.forEach((row) => { row.style.display = 'none'; });
  const start = (page - 1) * pageSize;
  matches.slice(start, start + pageSize).forEach((row) => { row.style.display = ''; });

  const info = pager.querySelector('.pager-info');
  if (info) info.textContent = total === 0 ? 'No results' : `Showing ${start + 1}–${Math.min(start + pageSize, total)} of ${total}`;

  const btnsWrap = pager.querySelector('.pager-btns');
  if (btnsWrap) {
    btnsWrap.innerHTML = '';
    const mk = (label, targetPage, disabled, active) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'pg-btn' + (active ? ' on' : '');
      b.textContent = label;
      b.disabled = disabled;
      b.onclick = () => { pager.dataset.page = String(targetPage); listPaginate(matches, allRows, opts); };
      return b;
    };
    btnsWrap.appendChild(mk('‹', page - 1, page <= 1, false));
    for (let p = 1; p <= pageCount; p++) btnsWrap.appendChild(mk(String(p), p, false, p === page));
    btnsWrap.appendChild(mk('›', page + 1, page >= pageCount, false));
  }
  pager.style.display = total > pageSize ? 'flex' : 'none';
}

function showToast(msg, type) {
  type = type || 'success';
  const cols = { success: 'var(--green)', danger: 'var(--red)', warning: 'var(--yellow)', info: 'var(--accent)' };
  let container = document.querySelector('.toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
  }
  const item = document.createElement('div');
  item.className = 'toast-item';
  item.innerHTML = '<div class="toast-dot" style="background:' + (cols[type]||cols.success) + '"></div><span>' + msg + '</span>';
  container.appendChild(item);
  requestAnimationFrame(() => requestAnimationFrame(() => item.classList.add('show')));
  setTimeout(() => { item.classList.remove('show'); setTimeout(() => item.remove(), 300); }, 3500);
}

// ── Export ▾ dropdown (partials/export-dropdown.blade.php) ─────────────────
// Event-delegated (not hardcoded element ids) so any number of independent dropdowns can
// coexist on one page — e.g. Billing's 3 per-tab export buttons.
document.addEventListener('click', e => {
  const toggle = e.target.closest('.export-toggle');
  if (toggle) {
    const menu = document.getElementById(toggle.dataset.target);
    const wasOpen = menu && menu.classList.contains('open');
    document.querySelectorAll('.export-menu.open').forEach(m => m.classList.remove('open'));
    if (menu && !wasOpen) menu.classList.add('open');

    return;
  }
  if (!e.target.closest('.export-menu')) {
    document.querySelectorAll('.export-menu.open').forEach(m => m.classList.remove('open'));
  }
});
