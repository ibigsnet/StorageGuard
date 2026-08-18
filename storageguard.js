function initStorageGuardUI() {
  function setVisible(el, on) {
    if (!el) return;
    el.style.display = on ? '' : 'none';
  }



  (function ensureArrayThresholdDefaults() {
    var form = document.getElementById('storageguard-form');
    if (!form || form.getAttribute('data-sg-has-array') !== '1') return;
    var useCustom = document.getElementById('array_use_custom');
    if (useCustom && useCustom.value === 'yes') return;

    function pickDefault(sel, attr) {
      if (!sel || sel.disabled) return;
      var def = sel.getAttribute(attr) || '';
      if (!def) return;
      if (sel.value !== '' && sel.value !== null) return;
      for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === def) {
          sel.selectedIndex = i;
          break;
        }
      }
    }
    var warn = document.getElementById('array_warning');
    var crit = document.getElementById('array_critical');
    pickDefault(warn, 'data-sg-default-warn');
    pickDefault(crit, 'data-sg-default-crit');
    // Critical may only be stamped on the warning select
    if (crit && (crit.value === '' || crit.value === null)) {
      pickDefault(crit, 'data-sg-default-crit');
      var fallback = warn ? (warn.getAttribute('data-sg-default-crit') || '') : '';
      if (fallback && (crit.value === '' || crit.value === null)) {
        for (var j = 0; j < crit.options.length; j++) {
          if (crit.options[j].value === fallback) {
            crit.selectedIndex = j;
            break;
          }
        }
      }
    }
  })();


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

    var nodes = document.querySelectorAll('.sg-thresh[data-sg-pair="' + pair + '"][data-sg-level="' + level + '"]');
    var i, el, val = null;
    for (i = 0; i < nodes.length; i++) {
      el = nodes[i];

      if (el.offsetParent === null && el.type !== 'hidden') {

        var block = el.closest('[id$="-custom-fields"], [id$="-disk-selects"], #array-custom-fields, #array-disk-selects');
        if (block && block.style.display === 'none') continue;
      }
      if (el.disabled) continue;
      val = el.value;

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


  // Only hide coloring options (style / which pools). Thresholds stay visible for alerts.
  function wireSectionToggle(selectId) {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    const sectionId = sel.getAttribute('data-sg-section');
    const section = sectionId ? document.getElementById(sectionId) : null;
    function apply() {
      const on = sel.value === 'yes';
      setVisible(section, on);
      updateOrderNote();
      updateShowOkVisibility();
    }
    sel.addEventListener('change', apply);
    apply();
  }
  wireSectionToggle('array_coloring');
  wireSectionToggle('pool_coloring');
  // Threshold disk/custom toggles always active (not under coloring)
  updateArrayCustom();
  document.querySelectorAll('.pool-use-custom').forEach(function (s) {
    const safe = s.getAttribute('data-pool-safe');
    if (safe) updatePoolCustom(safe);
  });

  // Green outline when OK is Outline-only — hide when no enabled target uses Outline
  function poolNameToSafe(name) {
    return String(name || '').replace(/[^a-zA-Z0-9_]/g, '_');
  }
  function anyOutlinePaintEnabled() {
    var ac = document.getElementById('array_coloring');
    var as = document.getElementById('array_color_style');
    if (ac && ac.value === 'yes' && as && as.value === 'outline') return true;

    var pc = document.getElementById('pool_coloring');
    if (!pc || pc.value !== 'yes') return false;

    var cbs = document.querySelectorAll('.pool-cb');
    var i, checked = 0;
    for (i = 0; i < cbs.length; i++) {
      if (!cbs[i].checked) continue;
      checked++;
      var st = document.getElementById('pool_' + poolNameToSafe(cbs[i].value) + '_color_style');
      // Missing style select → product default is outline
      if (!st || st.value === 'outline') return true;
    }
    return false;
  }
  function updateShowOkVisibility() {
    var row = document.getElementById('sg-show-ok-row');
    if (!row) return;
    setVisible(row, anyOutlinePaintEnabled());
  }
  (function wireShowOkVisibility() {
    var ids = ['array_coloring', 'array_color_style', 'pool_coloring'];
    ids.forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('change', updateShowOkVisibility);
    });
    document.querySelectorAll('select[id$="_color_style"]').forEach(function (sel) {
      sel.addEventListener('change', updateShowOkVisibility);
    });
    document.querySelectorAll('.pool-cb').forEach(function (cb) {
      cb.addEventListener('change', updateShowOkVisibility);
    });
    var poolAllEl = document.getElementById('pool_all');
    if (poolAllEl) poolAllEl.addEventListener('change', updateShowOkVisibility);
    updateShowOkVisibility();
  })();


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


  document.querySelectorAll('.sg-thresh, .pool-use-custom, #array_use_custom').forEach(function (el) {
    el.addEventListener('change', updateOrderNote);
    el.addEventListener('input', updateOrderNote);
  });
  updateOrderNote();


  // Array: visible when data disks exist; pools-only starts hidden (not sticky).
  var setArrayHiddenOpen = null;
  (function wireArrayToggle() {
    var form = document.getElementById('storageguard-form');
    var hasArray = form && form.getAttribute('data-sg-has-array') === '1';
    var btn = document.getElementById('sg-toggle-array');
    var block = document.getElementById('sg-array-block');
    var appear = document.getElementById('sg-array-appearance');
    // Drop legacy sticky key so old "Show Array" sessions do not reopen by default.
    try { localStorage.removeItem('sg_show_array'); } catch (e0) { /* ignore */ }

    function setOpen(open) {
      if (hasArray) {
        open = true;
        if (btn) btn.style.display = 'none';
      }
      if (block) block.style.display = open ? '' : 'none';
      if (appear) appear.style.display = open ? '' : 'none';
      document.querySelectorAll('.sg-array-alert-row').forEach(function (tr) {
        tr.style.display = open ? '' : 'none';
      });
      if (btn) btn.textContent = open ? 'Hide Array' : 'Show Array';
      if (open) {
        updateArrayCustom();
        var ac = document.getElementById('array_coloring');
        if (ac) {
          var sec = document.getElementById(ac.getAttribute('data-sg-section') || 'array-color-options');
          setVisible(sec, ac.value === 'yes');
        }
        updateOrderNote();
      }
    }
    setArrayHiddenOpen = setOpen;
    if (hasArray) {
      setOpen(true);
      return;
    }
    if (!btn) return;
    setOpen(false);
    btn.addEventListener('click', function () {
      var open = !block || block.style.display === 'none';
      setOpen(open);
    });
  })();

  // Cache thresholds: hidden by default; remembers open state.
  // Pool coloring + pool alert rows stay visible whenever pools exist (not tied to this toggle).
  (function wireCacheToggle() {
    var btn = document.getElementById('sg-toggle-cache');
    var panel = document.getElementById('sg-cache-panel');
    if (!btn || !panel) return;
    var key = 'sg_show_cache';
    function setOpen(open) {
      panel.style.display = open ? '' : 'none';
      btn.textContent = open ? 'Hide Cache' : 'Show Cache';
      try { localStorage.setItem(key, open ? '1' : '0'); } catch (e2) { /* ignore */ }
      if (open) {
        document.querySelectorAll('.pool-use-custom').forEach(function (s) {
          var safe = s.getAttribute('data-pool-safe');
          if (safe) updatePoolCustom(safe);
        });
        updateOrderNote();
      }
    }
    var saved = false;
    try { saved = localStorage.getItem(key) === '1'; } catch (e3) { /* ignore */ }
    setOpen(saved);
    btn.addEventListener('click', function () {
      var open = panel.style.display === 'none';
      setOpen(open);
    });
  })();

  function resetFormToProductDefaults() {
    function setSelect(id, value) {
      var el = document.getElementById(id);
      if (!el) return;
      el.value = value;

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
    var formEl = document.getElementById('storageguard-form');
    function setCheckbox(name, on) {
      if (!formEl) return;
      var boxes = formEl.querySelectorAll('input[type="checkbox"][name="' + name + '"]');
      boxes.forEach(function (cb) { cb.checked = !!on; });
    }

    // data-sg-has-array from Settings page (0 = pools-only / no array data disks)
    var hasArray = formEl && formEl.getAttribute('data-sg-has-array') === '1';

    // Product defaults: Yes toggles; outline + green OK; pulse off; autofill disk sizes
    setSelect('outline_pulse', 'no');
    setSelect('outline_show_ok', 'yes');
    setSelect('array_coloring', hasArray ? 'yes' : 'no');
    setSelect('array_color_style', 'outline');
    setSelect('array_use_custom', 'no');
    setInput('array_warning_custom', '');
    setInput('array_critical_custom', '');

    var arrWarn = document.getElementById('array_warning');
    var arrCrit = document.getElementById('array_critical');
    var defWarn = arrWarn ? (arrWarn.getAttribute('data-sg-default-warn') || '') : '';
    var defCrit = (arrCrit && arrCrit.getAttribute('data-sg-default-crit'))
      || (arrWarn && arrWarn.getAttribute('data-sg-default-crit'))
      || '';
    setSelect('array_warning', hasArray ? defWarn : '');
    setSelect('array_critical', hasArray ? defCrit : '');

    setCheckbox('alerts_array_warning', hasArray);
    setCheckbox('alerts_array_critical', hasArray);

    setSelect('pool_coloring', 'yes');
    if (poolAll) {
      poolAll.checked = true;
      poolAll.indeterminate = false;
    }
    poolCbs.forEach(function (cb) { cb.checked = true; });
    updatePoolsHidden();

    if (formEl) formEl.querySelectorAll('.pool-use-custom').forEach(function (sel) {
      var safe = sel.getAttribute('data-pool-safe');
      if (!safe) return;
      sel.value = 'no';
      setSelect('pool_' + safe + '_color_style', 'outline');
      var wSel = document.getElementById('pool_' + safe + '_warning');
      var cSel = document.getElementById('pool_' + safe + '_critical');
      // Autofill: first real size option (skip None) = largest; last = smallest
      var wDef = '';
      var cDef = '';
      if (wSel && wSel.options) {
        for (var i = 0; i < wSel.options.length; i++) {
          if (wSel.options[i].value) { wDef = wSel.options[i].value; break; }
        }
        for (var j = wSel.options.length - 1; j >= 0; j--) {
          if (wSel.options[j].value) { cDef = wSel.options[j].value; break; }
        }
      }
      setSelect('pool_' + safe + '_warning', wDef);
      setSelect('pool_' + safe + '_critical', cDef || wDef);
      setInput('pool_' + safe + '_warning_custom', '');
      setInput('pool_' + safe + '_critical_custom', '');
      setCheckbox('alerts_pool_' + safe + '_warning', true);
      setCheckbox('alerts_pool_' + safe + '_critical', true);
    });


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
    // Product Default: re-hide array settings when no array (config values stay; UI collapses)
    if (typeof setArrayHiddenOpen === 'function') {
      setArrayHiddenOpen(false);
    }
    updateShowOkVisibility();
    updateOrderNote();
  }

  const form = document.getElementById('storageguard-form');

  function markFormDirty() {
    if (!form) return;
    var apply = form.querySelector('input[type="submit"][name="#apply"]');
    if (apply) apply.disabled = false;
  }

  // Pool math: fill Custom free thresholds from capacity-Δ suggestions (does not save until Apply)
  document.querySelectorAll('.sg-suggest-pool').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var safe = btn.getAttribute('data-pool-safe');
      var warn = btn.getAttribute('data-warn') || '';
      var crit = btn.getAttribute('data-crit') || '';
      if (!safe) return;
      var use = document.getElementById('pool_' + safe + '_use_custom');
      if (use) {
        use.value = 'yes';
        updatePoolCustom(safe);
      }
      var wIn = document.getElementById('pool_' + safe + '_warning_custom');
      var cIn = document.getElementById('pool_' + safe + '_critical_custom');
      if (wIn) wIn.value = warn;
      if (cIn) cIn.value = crit;
      markFormDirty();
      updateOrderNote();
      try {
        btn.classList.add('sg-suggest-applied');
        var prev = btn.textContent;
        btn.textContent = 'Suggested — click Apply to save';
        setTimeout(function () {
          btn.textContent = prev;
          btn.classList.remove('sg-suggest-applied');
        }, 2500);
      } catch (err) { /* ignore */ }
    });
  });

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
