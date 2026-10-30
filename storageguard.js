/* Storage Guard Settings tabs. Every tab is its own form (form.sg-form); all handlers are
 * scoped to that form, so tabs never touch each other's fields. */
(function () {
  'use strict';
  if (window.StorageGuardUI) return;

  function show(el, on) {
    if (el) el.style.display = on ? '' : 'none';
  }

  function parseToTB(str) {
    str = String(str == null ? '' : str).trim();
    var m = str.match(/([0-9]*\.?[0-9]+)\s*([TGMKtgmk]?)/);
    if (!m) return null;
    var n = parseFloat(m[1]);
    if (isNaN(n)) return null;
    switch ((m[2] || 'T').toUpperCase()) {
      case 'G': return n / 1e3;
      case 'M': return n / 1e6;
      case 'K': return n / 1e9;
      default: return n;
    }
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function setValue(el, value) {
    if (!el) return;
    if (el.tagName === 'SELECT') {
      for (var i = 0; i < el.options.length; i++) {
        if (el.options[i].value === value) { el.selectedIndex = i; return; }
      }
      return;
    }
    el.value = value;
  }

  function initForm(form) {
    if (form.getAttribute('data-sg-init')) return;
    form.setAttribute('data-sg-init', '1');
    var wrap = form.parentNode;
    var note = wrap ? wrap.querySelector('.sg-order-note') : null;
    var useCustom = form.querySelector('.sg-use-custom');
    var diskBlock = form.querySelector('.sg-disk-selects');
    var customBlock = form.querySelector('.sg-custom-fields');

    function isCustom() {
      return !useCustom || useCustom.value === 'yes';
    }

    function markDirty() {
      var apply = form.querySelector('input[name="#apply"]');
      if (apply) apply.disabled = false;
    }

    function current(level) {
      var block = isCustom() ? customBlock : diskBlock;
      var el = block ? block.querySelector('.sg-thresh[data-sg-level="' + level + '"]') : null;
      return el ? el.value : '';
    }

    function updateOrderNote() {
      if (!note) return;
      var w = parseToTB(current('warn'));
      var c = parseToTB(current('crit'));
      if (w === null || c === null || c <= w) {
        note.style.display = 'none';
        note.innerHTML = '';
        return;
      }
      note.style.display = 'block';
      note.innerHTML =
        '<strong>Unusual threshold order</strong>: ' + esc(form.getAttribute('data-sg-label') || '') +
        ' Critical (<code>' + esc(current('crit')) + '</code>) is higher than Warning (<code>' +
        esc(current('warn')) + '</code>). The lower free amount is always treated as Critical (red).';
    }

    function syncCustom() {
      if (useCustom && useCustom.tagName === 'SELECT') {
        var on = isCustom();
        show(diskBlock, !on);
        show(customBlock, on);
        if (diskBlock) {
          diskBlock.querySelectorAll('select').forEach(function (s) { s.disabled = on; });
        }
      }
      updateOrderNote();
    }

    if (useCustom && useCustom.tagName === 'SELECT') {
      useCustom.addEventListener('change', syncCustom);
    }
    form.querySelectorAll('.sg-thresh').forEach(function (el) {
      el.addEventListener('change', updateOrderNote);
      el.addEventListener('input', updateOrderNote);
    });
    syncCustom();

    // Suggest: fill this form's free amounts (saved on Apply).
    form.querySelectorAll('.sg-suggest-pool').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        if (useCustom) {
          setValue(useCustom, 'yes');
          syncCustom();
        }
        var w = customBlock && customBlock.querySelector('[data-sg-level="warn"]');
        var c = customBlock && customBlock.querySelector('[data-sg-level="crit"]');
        if (w) w.value = btn.getAttribute('data-warn') || '';
        if (c) c.value = btn.getAttribute('data-crit') || '';
        markDirty();
        updateOrderNote();
        var prev = btn.value;
        btn.value = 'Suggested — click Apply to save';
        btn.classList.add('sg-suggest-applied');
        setTimeout(function () {
          btn.value = prev;
          btn.classList.remove('sg-suggest-applied');
        }, 2500);
      });
    });

    // Default: show the product defaults in this form before it posts
    // (the saved values come from sg-update.php either way).
    function resetToDefaults() {
      form.querySelectorAll('[data-sg-default]').forEach(function (el) {
        setValue(el, el.getAttribute('data-sg-default'));
      });
      if (useCustom && useCustom.tagName === 'SELECT') setValue(useCustom, 'no');
      if (customBlock) {
        customBlock.querySelectorAll('input[type="text"]').forEach(function (el) { el.value = ''; });
      }
      syncCustom();
    }

    form.addEventListener('submit', function (e) {
      var submitter = e.submitter || document.activeElement;
      if (submitter && submitter.name === '#default') resetToDefaults();
      // Disabled fields are not posted; send the disk-size values as they are.
      form.querySelectorAll('select:disabled').forEach(function (el) { el.disabled = false; });
    });
  }

  function initAll() {
    document.querySelectorAll('form.sg-form').forEach(initForm);
  }

  window.StorageGuardUI = { init: initAll };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
