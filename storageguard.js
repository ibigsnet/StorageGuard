// Storage Guard settings page — show/hide sections, custom thresholds, pool multi-select,
// and a notice when Critical free space is set higher than Warning (unusual order).
function initStorageGuardUI() {
  function setVisible(el, on) {
    if (!el) return;
    el.style.display = on ? '' : 'none';
  }

  // First open / unseeded: ensure Array Warning select is largest disk (not None)
  // when the page was rendered with a non-empty default selection.
  (function ensureArrayWarningDefault() {
    var sel = document.getElementById('array_warning');
    if (!sel || sel.disabled) return;
    var useCustom = document.getElementById('array_use_custom');
    if (useCustom && useCustom.value === 'yes') return;
    // data-sg-default-warn set by PHP when product default is largest disk
    var def = sel.getAttribute('data-sg-default-warn') || '';
    if (!def) return;
    if (sel.value === '' || sel.value === null) {
      for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === def) {
          sel.selectedIndex = i;
          break;
        }
      }
    }
  })();

  /** Parse free-space strings (8T, 500G, 1.5T) to decimal TB; null if empty/invalid. */
  function parseToTB(str) {
    if (str === null || str === undefined) return null;
    str = String(str).trim();
    if (!str) return null;
    var m = str.match(/([0-9]*\.?[0-9]+)\s*([TGMKtgmk]?)/);
    if (!m) return null;
    var n = parseFloat(m[1]);
    if (isNaN(n)) return null;
    var u = (m[2] || 'T').toUpperCase();
    if (u === 'T') return n;
    if (u === 'G') return n / 1000.0;
    if (u === 'M') return n / 1e6;
    if (u === 'K') return n / 1e9;
    return n;
  }

  function activeThreshValue(pair, level) {
    // Prefer visible custom inputs when that target is in custom mode
    var nodes = document.querySelectorAll('.sg-thresh[data-sg-pair="' + pair + '"][data-sg-level="' + level + '"]');
    var i, el, val = null;
    for (i = 0; i < nodes.length; i++) {
      el = nodes[i];
      // skip hidden ancestors (display:none sections)
      if (el.offsetParent === null && el.type !== 'hidden') {
        // still allow if only the sibling block is hidden — check closest custom/disk block
        var block = el.closest('[id$="-custom-fields"], [id$="-disk-selects"], #array-custom-fields, #array-disk-selects');
        if (block && block.style.display === 'none') continue;
      }
      if (el.disabled) continue;
      val = el.value;
      // For selects/inputs that are in a hidden custom/disk block, skip
      var parent = el.parentElement;
      while (parent) {
        if (parent.style && parent.style.display === 'none') { val = null; break; }
        if (parent.id === 'array-custom-fields' || parent.id === 'array-disk-selects' ||
            /pool-.*-custom-fields$/.test(parent.id || '') || /pool-.*-disk-selects$/.test(parent.id || '')) {
          if (parent.style.display === 'none') { val = null; break; }
        }
        parent = parent.parentElement;
      }
      if (val !== null) break;
    }
    return val;
  }

  /** Better: resolve warn/crit for a pair using custom toggle when present. */
  function pairValues(pair) {
    var warnEl, critEl;
    if (pair === 'array') {
      var useCustom = document.getElementById('array_use_custom');
      var isCustom = useCustom && useCustom.value === 'yes';
      warnEl = document.getElementById(isCustom ? 'array_warning_custom' : 'array_warning');
      critEl = document.getElementById(isCustom ? 'array_critical_custom' : 'array_critical');
      return {
        label: 'Array',
        warn: warnEl ? warnEl.value : '',
        crit: critEl ? critEl.value : ''
      };
    }
    // pool-<safe>
    var safe = pair.replace(/^pool-/, '');
    var use = document.getElementById('pool_' + safe + '_use_custom');
    var isC = use && use.value === 'yes';
    warnEl = document.getElementById(isC ? ('pool_' + safe + '_warning_custom') : ('pool_' + safe + '_warning'));
    critEl = document.getElementById(isC ? ('pool_' + safe + '_critical_custom') : ('pool_' + safe + '_critical'));
    var labelNode = warnEl || critEl;
    var label = (labelNode && labelNode.getAttribute('data-sg-label')) || safe;
    return {
      label: label,
      warn: warnEl ? warnEl.value : '',
      crit: critEl ? critEl.value : ''
    };
  }

  function updateOrderNote() {
    var note = document.getElementById('sg-order-note');
    if (!note) return;

    var pairs = {};
    document.querySelectorAll('.sg-thresh[data-sg-pair]').forEach(function (el) {
      pairs[el.getAttribute('data-sg-pair')] = true;
    });

    var inverted = [];
    Object.keys(pairs).forEach(function (pair) {
      var v = pairValues(pair);
      var w = parseToTB(v.warn);
      var c = parseToTB(v.crit);
      if (w === null || c === null) return;
      // Unusual: Critical free amount larger than Warning free amount
      if (c > w) {
        inverted.push({
          label: v.label,
          warn: String(v.warn).trim(),
          crit: String(v.crit).trim()
        });
      }
    });

    if (!inverted.length) {
      note.style.display = 'none';
      note.innerHTML = '';
      return;
    }

    var lines = inverted.map(function (x) {
      return '<li><strong>' + x.label + '</strong>: Critical free space (<code>' + x.crit +
        '</code>) is <em>higher</em> than Warning (<code>' + x.warn + '</code>)</li>';
    }).join('');

    note.style.display = 'block';
    note.innerHTML =
      '<strong>Unusual threshold order</strong>' +
      '<ul style="margin:0.4em 0 0.4em 1.2em">' + lines + '</ul>' +
      '<p style="margin:0.4em 0 0">' +
      'Storage Guard always ranks by <strong>how low free space is</strong>, not by which dropdown you used: ' +
      'the <strong>lower</strong> free-space amount is treated as <strong>critical (red)</strong>, ' +
      'and the <strong>higher</strong> as <strong>warning (yellow)</strong>. ' +
      'Main coloring and alerts both use that ranking. ' +
      'Recommended: Warning = earlier heads-up (more free left), Critical = more severe (less free left)—e.g. Warning <code>8T</code>, Critical <code>2T</code>.' +
      '</p>';
  }

  // Array: custom vs disk sizes
  const useCustom = document.getElementById('array_use_custom');
  const diskSelects = document.getElementById('array-disk-selects');
  const customFields = document.getElementById('array-custom-fields');
  const arrWarnSel = document.getElementById('array_warning');
  const arrCritSel = document.getElementById('array_critical');

  function updateArrayCustom() {
    const isCustom = useCustom && useCustom.value === 'yes';
    setVisible(diskSelects, !isCustom);
    setVisible(customFields, isCustom);
    if (arrWarnSel) arrWarnSel.disabled = !!isCustom;
    if (arrCritSel) arrCritSel.disabled = !!isCustom;
    updateOrderNote();
  }
  if (useCustom) {
    useCustom.addEventListener('change', updateArrayCustom);
    updateArrayCustom();
  }

  // Per-pool: custom vs disk sizes
  function updatePoolCustom(safe) {
    const sel = document.getElementById('pool_' + safe + '_use_custom');
    if (!sel) return;
    const isCustom = sel.value === 'yes';
    setVisible(document.getElementById('pool-' + safe + '-disk-selects'), !isCustom);
    setVisible(document.getElementById('pool-' + safe + '-custom-fields'), isCustom);
    const w = document.getElementById('pool_' + safe + '_warning');
    const c = document.getElementById('pool_' + safe + '_critical');
    if (w) w.disabled = !!isCustom;
    if (c) c.disabled = !!isCustom;
    updateOrderNote();
  }

  document.querySelectorAll('.pool-use-custom').forEach(function (sel) {
    const safe = sel.getAttribute('data-pool-safe');
    if (!safe) return;
    sel.addEventListener('change', function () { updatePoolCustom(safe); });
    updatePoolCustom(safe);
  });

  // Coloring Yes/No → hide details for that block
  function wireSectionToggle(selectId) {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    const sectionId = sel.getAttribute('data-sg-section');
    const section = sectionId ? document.getElementById(sectionId) : null;
    function apply() {
      const on = sel.value === 'yes';
      setVisible(section, on);
      if (on && selectId === 'array_coloring') updateArrayCustom();
      if (on && selectId === 'pool_coloring') {
        document.querySelectorAll('.pool-use-custom').forEach(function (s) {
          const safe = s.getAttribute('data-pool-safe');
          if (safe) updatePoolCustom(safe);
        });
      }
      updateOrderNote();
    }
    sel.addEventListener('change', apply);
    apply();
  }
  wireSectionToggle('array_coloring');
  wireSectionToggle('pool_coloring');

  // Pool "All" checkbox sync
  const poolAll = document.getElementById('pool_all');
  const poolCbs = document.querySelectorAll('.pool-cb');

  function syncPoolAll() {
    if (!poolAll || !poolCbs.length) return;
    const list = Array.from(poolCbs);
    const allChecked = list.every(cb => cb.checked);
    const noneChecked = list.every(cb => !cb.checked);
    poolAll.checked = allChecked;
    poolAll.indeterminate = !allChecked && !noneChecked;
  }

  function updatePoolsHidden() {
    const hidden = document.getElementById('pools_to_color');
    if (!hidden) return;
    const checked = [];
    poolCbs.forEach(cb => { if (cb.checked) checked.push(cb.value); });
    if (checked.length === 0) hidden.value = '';
    else if (checked.length === poolCbs.length) hidden.value = 'all';
    else hidden.value = checked.join(',');
  }

  if (poolAll) {
    poolAll.addEventListener('change', function () {
      poolCbs.forEach(cb => { cb.checked = poolAll.checked; });
      updatePoolsHidden();
    });
  }
  poolCbs.forEach(cb => {
    cb.addEventListener('change', function () {
      syncPoolAll();
      updatePoolsHidden();
    });
  });
  syncPoolAll();
  updatePoolsHidden();

  // Threshold order notice on any change
  document.querySelectorAll('.sg-thresh, .pool-use-custom, #array_use_custom').forEach(function (el) {
    el.addEventListener('change', updateOrderNote);
    el.addEventListener('input', updateOrderNote);
  });
  updateOrderNote();

  // Advanced: pool settings (WIP) hidden by default — array is primary
  (function wirePoolsWipToggle() {
    var btn = document.getElementById('sg-toggle-pools-wip');
    var panel = document.getElementById('sg-pools-wip');
    var hint = document.getElementById('sg-pools-wip-hint');
    if (!btn || !panel) return;
    var key = 'sg_show_pools_wip';
    function setOpen(open) {
      panel.style.display = open ? '' : 'none';
      document.querySelectorAll('.sg-pool-alert-row').forEach(function (tr) {
        tr.style.display = open ? '' : 'none';
      });
      btn.textContent = open
        ? 'Hide advanced pool settings (WIP)'
        : 'Show advanced pool settings (WIP)…';
      if (hint) hint.style.display = open ? 'none' : '';
      try { localStorage.setItem(key, open ? '1' : '0'); } catch (e) { /* ignore */ }
      if (open) {
        var pc = document.getElementById('pool_coloring');
        if (pc && pc.value === 'yes') {
          document.querySelectorAll('.pool-use-custom').forEach(function (s) {
            var safe = s.getAttribute('data-pool-safe');
            if (safe) updatePoolCustom(safe);
          });
        }
        updateOrderNote();
      }
    }
    var saved = false;
    try { saved = localStorage.getItem(key) === '1'; } catch (e) { /* ignore */ }
    setOpen(saved);
    btn.addEventListener('click', function () {
      setOpen(panel.style.display === 'none');
    });
  })();

  /**
   * Unraid posts Default into a progress iframe — the page does not reload.
   * Also, update.php only restores keys present in default.cfg; pool_* keys
   * are dynamic and would keep the form's current values without sg-update.php.
   * Reset the form UI to product defaults so what you see matches the cfg write.
   */
  function resetFormToProductDefaults() {
    function setSelect(id, value) {
      var el = document.getElementById(id);
      if (!el) return;
      el.value = value;
      // If value not found, leave as-is for selects; try matching option
      if (el.tagName === 'SELECT' && el.value !== value) {
        for (var i = 0; i < el.options.length; i++) {
          if (el.options[i].value === value) {
            el.selectedIndex = i;
            break;
          }
        }
      }
    }
    function setInput(id, value) {
      var el = document.getElementById(id);
      if (el) el.value = value;
    }
    function setCheckbox(name, on) {
      var boxes = form.querySelectorAll('input[type="checkbox"][name="' + name + '"]');
      boxes.forEach(function (cb) { cb.checked = !!on; });
    }

    setSelect('outline_pulse', 'no');
    setSelect('outline_show_ok', 'no');
    setSelect('array_coloring', 'yes');
    setSelect('array_color_style', 'outline');
    setSelect('array_use_custom', 'no');
    setInput('array_warning_custom', '');
    setInput('array_critical_custom', '');

    var arrWarn = document.getElementById('array_warning');
    var defWarn = arrWarn ? (arrWarn.getAttribute('data-sg-default-warn') || '') : '';
    setSelect('array_warning', defWarn);
    setSelect('array_critical', '');

    // Array alerts: Warning on (if available), Critical off
    var arrWarnCb = form.querySelector('input[type="checkbox"][name="alerts_array_warning"]');
    if (arrWarnCb && !arrWarnCb.disabled) setCheckbox('alerts_array_warning', true);
    else setCheckbox('alerts_array_warning', false);
    setCheckbox('alerts_array_critical', false);

    // Pools: coloring on, all pools, thresholds None, style outline, alerts off
    setSelect('pool_coloring', 'yes');
    if (poolAll) {
      poolAll.checked = true;
      poolAll.indeterminate = false;
    }
    poolCbs.forEach(function (cb) { cb.checked = true; });
    updatePoolsHidden();

    form.querySelectorAll('.pool-use-custom').forEach(function (sel) {
      var safe = sel.getAttribute('data-pool-safe');
      if (!safe) return;
      sel.value = 'no';
      setSelect('pool_' + safe + '_color_style', 'outline');
      setSelect('pool_' + safe + '_warning', '');
      setSelect('pool_' + safe + '_critical', '');
      setInput('pool_' + safe + '_warning_custom', '');
      setInput('pool_' + safe + '_critical_custom', '');
      setCheckbox('alerts_pool_' + safe + '_warning', false);
      setCheckbox('alerts_pool_' + safe + '_critical', false);
    });

    // Re-apply show/hide (do not re-bind wireSectionToggle — would stack listeners)
    ['array_coloring', 'pool_coloring'].forEach(function (selectId) {
      var sel = document.getElementById(selectId);
      if (!sel) return;
      var sectionId = sel.getAttribute('data-sg-section');
      var section = sectionId ? document.getElementById(sectionId) : null;
      setVisible(section, sel.value === 'yes');
    });
    updateArrayCustom();
    document.querySelectorAll('.pool-use-custom').forEach(function (s) {
      var safe = s.getAttribute('data-pool-safe');
      if (safe) updatePoolCustom(safe);
    });
    updateOrderNote();
  }

  const form = document.getElementById('storageguard-form');
  if (form) {
    form.addEventListener('submit', function (e) {
      var submitter = e.submitter || document.activeElement;
      var isDefault = submitter && submitter.name === '#default';
      if (isDefault) {
        resetFormToProductDefaults();
      }
      updatePoolsHidden();
      form.querySelectorAll('select:disabled, input:disabled').forEach(el => {
        if (el.closest('.sg-details') || el.classList.contains('pool-size-select') ||
            el.id === 'array_warning' || el.id === 'array_critical' ||
            (el.id && /^pool_.*_(warning|critical)$/.test(el.id))) {
          el.disabled = false;
        }
      });
    });
  }
}

window.initStorageGuardUI = initStorageGuardUI;
