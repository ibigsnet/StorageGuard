// Storage Guard — paint Free bars on Main.
// Array: "Array of N devices" free bar.
// Pool: Data Partition free bar (not "Pool of N devices", not Internal Boot).

(function () {
  'use strict';

  var lastStatus = null;
  var lastOpts = { pulse: false, showOk: false };
  var BAR_WARN = '#ffc107';
  var BAR_CRIT = '#e53935';
  var BAR_OK = '#4caf50';
  var applyTimer = null;

  function resolveStyle(obj, fallback) {
    if (obj && obj.style === 'outline') return 'outline';
    if (obj && obj.style === 'solid') return 'solid';
    return fallback === 'outline' ? 'outline' : 'solid';
  }

  function log() {
    if (window.StorageGuardDebug) {
      console.log.apply(console, ['[StorageGuard]'].concat([].slice.call(arguments)));
    }
  }

  function onMainLikePage() {
    return !!(
      document.getElementById('array_devices') ||
      document.querySelector('[id^="pool_device"]') ||
      document.getElementById('boot_device') ||
      document.getElementById('array_list')
    );
  }

  /** Free column = last .usage-disk in the row (Used comes before Free). */
  function freeUsageDisk(tr) {
    if (!tr) return null;
    var disks = tr.querySelectorAll('td .usage-disk');
    if (disks.length) return disks[disks.length - 1];
    return null;
  }

  function clearPaint(scope) {
    if (!scope) return;
    scope.querySelectorAll('[data-sg-bar]').forEach(function (el) {
      el.style.removeProperty('background-color');
      el.style.removeProperty('background');
      el.removeAttribute('data-sg-bar');
      el.removeAttribute('data-sg-style');
      el.classList.remove('sg-warning', 'sg-critical', 'sg-ok', 'sg-bar', 'sg-solid');
    });
    scope.querySelectorAll('[data-sg-outline], .sg-outline').forEach(function (el) {
      el.style.removeProperty('outline');
      el.style.removeProperty('outline-offset');
      el.style.removeProperty('box-shadow');
      el.removeAttribute('data-sg-outline');
      el.removeAttribute('data-sg-sig');
      el.classList.remove('sg-outline', 'sg-warning', 'sg-critical', 'sg-ok', 'sg-pulse');
    });
    scope.querySelectorAll('[data-sg-td]').forEach(function (el) {
      el.style.removeProperty('color');
      el.style.removeProperty('font-weight');
      el.style.removeProperty('outline');
      el.style.removeProperty('outline-offset');
      el.style.removeProperty('box-shadow');
      el.removeAttribute('data-sg-td');
      el.removeAttribute('data-sg-sig');
    });
    scope.querySelectorAll('[data-sg-sig]').forEach(function (el) {
      el.removeAttribute('data-sg-sig');
    });
  }

  function makeSig(level, style, pulse, showOk) {
    return [level || 'none', style || 'solid', pulse ? '1' : '0', showOk ? '1' : '0'].join('|');
  }

  /**
   * Paint free bar for level: ok | warning | critical
   * style: solid (default) | outline
   * opts: { pulse, showOk }
   */
  function paintFreeBar(tr, level, style, opts) {
    if (!tr) return false;
    opts = opts || {};
    var pulse = !!opts.pulse;
    var showOk = !!opts.showOk;
    style = style === 'outline' ? 'outline' : 'solid';

    // Nothing to draw for OK unless outline + show healthy
    if (level === 'ok') {
      if (style !== 'outline' || !showOk) {
        clearPaint(tr);
        return false;
      }
    } else if (level !== 'warning' && level !== 'critical') {
      clearPaint(tr);
      return false;
    }

    var sig = makeSig(level, style, pulse && (level === 'warning' || level === 'critical'), showOk);
    var disk = freeUsageDisk(tr);
    var existing = tr.querySelector('[data-sg-sig]');
    if (existing && existing.getAttribute('data-sg-sig') === sig) {
      return true; // already painted — do not restart CSS animation
    }

    clearPaint(tr);

    var color = level === 'critical' ? BAR_CRIT : (level === 'warning' ? BAR_WARN : BAR_OK);

    if (disk) {
      var bar = disk.querySelector('span:first-child');
      if (style === 'outline') {
        disk.classList.add('sg-outline');
        if (level === 'ok') disk.classList.add('sg-ok');
        else if (level === 'critical') disk.classList.add('sg-critical');
        else disk.classList.add('sg-warning');
        if (pulse && level !== 'ok') disk.classList.add('sg-pulse');
        disk.setAttribute('data-sg-outline', level);
        disk.setAttribute('data-sg-sig', sig);
        log('outline free bar', level, pulse ? 'pulse' : 'static', (tr.textContent || '').slice(0, 50));
        return true;
      }
      // solid — only warning/critical
      if (level !== 'ok' && bar) {
        bar.style.setProperty('background-color', color, 'important');
        bar.style.setProperty('background', color, 'important');
        bar.setAttribute('data-sg-bar', level);
        bar.setAttribute('data-sg-style', 'solid');
        bar.setAttribute('data-sg-sig', sig);
        bar.classList.add('sg-bar', 'sg-solid', level === 'critical' ? 'sg-critical' : 'sg-warning');
        log('solid free bar', level, (tr.textContent || '').slice(0, 50));
        return true;
      }
    }

    // Plain-text Free column fallback
    var tds = tr.querySelectorAll('td');
    if (tds.length) {
      var td = tds[tds.length - 1];
      if (/\d/.test(td.textContent || '')) {
        if (style === 'outline') {
          td.style.setProperty('outline', '2px solid ' + color, 'important');
          td.style.setProperty('outline-offset', '2px', 'important');
          td.setAttribute('data-sg-td', level);
          td.setAttribute('data-sg-sig', sig);
        } else if (level !== 'ok') {
          td.style.setProperty('color', color, 'important');
          td.style.setProperty('font-weight', '700', 'important');
          td.setAttribute('data-sg-td', level);
          td.setAttribute('data-sg-sig', sig);
        }
        return true;
      }
    }
    return false;
  }

  function rowText(tr) {
    return (tr && tr.textContent ? tr.textContent : '').toLowerCase();
  }

  function isTotalsOnlyRow(tr) {
    var t = rowText(tr);
    return /pool of\s/.test(t) || /array of\s/.test(t);
  }

  function isArrayOfDevicesRow(tr) {
    return /array of\s/.test(rowText(tr));
  }

  function isBootSummaryRow(tr) {
    var t = rowText(tr);
    return (
      t.indexOf('internal boot') !== -1 ||
      t.indexOf('boot(flash)') !== -1 ||
      (t.indexOf('boot') !== -1 && t.indexOf('data partition') === -1 && /flash|zfs/.test(t) && t.indexOf('btrfs') === -1)
    );
  }

  function isDataPartitionRow(tr) {
    var t = rowText(tr);
    if (isTotalsOnlyRow(tr) || isBootSummaryRow(tr)) return false;
    if (t.indexOf('data partition') !== -1) return true;
    var n = tr.querySelectorAll('td .usage-disk').length;
    if (n >= 2 && /btrfs|xfs|zfs|online/.test(t) && t.indexOf('device ') === -1) return true;
    if (n >= 2 && /btrfs/.test(t)) return true;
    return false;
  }

  function findArrayFreeRow() {
    var tbody = document.getElementById('array_devices');
    if (!tbody) return null;
    var rows = tbody.querySelectorAll('tr');
    var i;
    for (i = 0; i < rows.length; i++) {
      if (isArrayOfDevicesRow(rows[i]) && freeUsageDisk(rows[i])) return rows[i];
    }
    for (i = rows.length - 1; i >= 0; i--) {
      if (freeUsageDisk(rows[i]) && !isBootSummaryRow(rows[i])) return rows[i];
    }
    return null;
  }

  function findPoolDataFreeRow(root) {
    if (!root) return null;
    var rows = root.querySelectorAll('tr');
    var i, tr;

    for (i = 0; i < rows.length; i++) {
      tr = rows[i];
      if (isDataPartitionRow(tr) && freeUsageDisk(tr)) return tr;
    }
    for (i = 0; i < rows.length; i++) {
      tr = rows[i];
      if (isTotalsOnlyRow(tr) || isBootSummaryRow(tr)) continue;
      var t = rowText(tr);
      if (/^[\s]*device\s+\d+/.test(t) || t.indexOf('device 1') !== -1 || t.indexOf('device 2') !== -1) {
        if (tr.querySelectorAll('td .usage-disk').length < 2) continue;
      }
      if (tr.querySelectorAll('td .usage-disk').length >= 2) return tr;
    }
    for (i = 0; i < rows.length; i++) {
      tr = rows[i];
      if (isTotalsOnlyRow(tr) || isBootSummaryRow(tr)) continue;
      if (freeUsageDisk(tr)) return tr;
    }
    return null;
  }

  function allPoolRoots() {
    var list = [];
    var seen = {};
    function add(el) {
      if (!el || seen[el.id || el]) return;
      seen[el.id || el] = true;
      list.push(el);
    }
    document.querySelectorAll('[id^="pool_device"]').forEach(add);
    add(document.getElementById('boot_device'));
    document.querySelectorAll('.TableContainer table.disk_status').forEach(add);
    return list;
  }

  function namesInRoot(root) {
    var found = [];
    var links = root.querySelectorAll('a[href*="Device?name="], a[href*="Boot?name="]');
    for (var i = 0; i < links.length; i++) {
      var href = links[i].getAttribute('href') || '';
      var m = href.match(/name=([^&]+)/i);
      if (!m) continue;
      var n = decodeURIComponent(m[1]);
      if (found.indexOf(n) === -1) found.push(n);
    }
    return found;
  }

  function resolvePoolKey(names, text, poolsStatus) {
    if (!poolsStatus) return null;
    var keys = Object.keys(poolsStatus);
    var i, j, pref, t;
    for (i = 0; i < names.length; i++) {
      if (poolsStatus[names[i]]) return names[i];
    }
    for (i = 0; i < names.length; i++) {
      pref = String(names[i]).replace(/\d+$/, '');
      if (pref && poolsStatus[pref]) return pref;
    }
    t = (text || '').toLowerCase();
    for (j = 0; j < keys.length; j++) {
      if (t.indexOf(keys[j].toLowerCase()) !== -1) return keys[j];
    }
    if (keys.length === 1 && (/pool|cache|btrfs|data partition|device/.test(t))) {
      return keys[0];
    }
    return null;
  }

  function paintTarget(tr, st, opts) {
    if (!tr || !st) return;
    if (!st.enabled) {
      clearPaint(tr);
      return;
    }
    var style = resolveStyle(st, 'solid');
    var level = st.level || 'ok';
    paintFreeBar(tr, level, style, opts);
  }

  function applyStatus(status, opts) {
    if (!status) return;
    lastStatus = status;
    if (opts) lastOpts = opts;
    opts = lastOpts || { pulse: false, showOk: false };

    if (!onMainLikePage()) {
      log('not on Main');
      return;
    }

    var arow = findArrayFreeRow();
    if (arow) paintTarget(arow, status.array, opts);

    var pools = status.pools || {};
    var roots = allPoolRoots();
    var matched = {};

    for (var r = 0; r < roots.length; r++) {
      var root = roots[r];
      root.querySelectorAll('tr').forEach(function (tr) {
        if (isTotalsOnlyRow(tr) || isBootSummaryRow(tr)) clearPaint(tr);
      });

      var names = namesInRoot(root);
      var key = resolvePoolKey(names, root.textContent || '', pools);
      if (!key || matched[key]) continue;

      var st = pools[key];
      if (!st) continue;

      var prow = findPoolDataFreeRow(root);
      log('pool match', {
        root: root.id || root.className,
        key: key,
        level: st.level,
        enabled: st.enabled,
        style: resolveStyle(st, 'solid'),
        foundRow: !!prow
      });

      if (!prow) continue;
      matched[key] = true;
      paintTarget(prow, st, opts);
    }

    Object.keys(pools).forEach(function (pk) {
      if (matched[pk]) return;
      var st = pools[pk];
      if (!st || !st.enabled) return;
      if (st.level !== 'warning' && st.level !== 'critical' &&
          !(st.level === 'ok' && opts.showOk && resolveStyle(st, 'solid') === 'outline')) {
        return;
      }
      document.querySelectorAll('tr').forEach(function (tr) {
        if (matched[pk]) return;
        if (!isDataPartitionRow(tr) && rowText(tr).indexOf(pk.toLowerCase()) === -1) return;
        if (isBootSummaryRow(tr) || isTotalsOnlyRow(tr)) return;
        if (!freeUsageDisk(tr)) return;
        if (paintFreeBar(tr, st.level || 'ok', resolveStyle(st, 'solid'), opts)) {
          matched[pk] = true;
        }
      });
    });
  }

  function scheduleApply() {
    if (applyTimer) return;
    applyTimer = setTimeout(function () {
      applyTimer = null;
      if (lastStatus) applyStatus(lastStatus, lastOpts);
    }, 250);
  }

  function fetchAndApply() {
    fetch('/plugins/StorageGuard/get-config.php', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (data) {
        if (!data || !data._status) return;
        var opts = {
          pulse: (data.outline_pulse === 'yes'),
          showOk: (data.outline_show_ok === 'yes')
        };
        // Also expose under _status for debugging
        data._status._opts = opts;
        log('status', data._status, opts);
        applyStatus(data._status, opts);
        fetch('/plugins/StorageGuard/check-alerts.php', { credentials: 'same-origin' }).catch(function () {});
      })
      .catch(function (err) {
        console.warn('Storage Guard: config fetch failed', err);
      });
  }

  function boot() {
    fetchAndApply();
    // Re-apply for nchan/table refreshes without hammering animations
    setInterval(function () {
      if (lastStatus) applyStatus(lastStatus, lastOpts);
    }, 3000);
    setInterval(fetchAndApply, 12000);

    if (typeof MutationObserver !== 'undefined') {
      var obs = new MutationObserver(function () {
        scheduleApply();
      });
      var main = document.getElementById('content') || document.body;
      obs.observe(main, { childList: true, subtree: true });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
  setTimeout(fetchAndApply, 2000);
  setTimeout(fetchAndApply, 5000);

  window.StorageGuardColor = {
    refresh: fetchAndApply,
    apply: applyStatus,
    debug: function (on) {
      window.StorageGuardDebug = on !== false;
      fetchAndApply();
    }
  };
  console.log('Storage Guard: Main free-bar coloring ready');
})();
