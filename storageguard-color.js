// Storage Guard — color the Free *usage bar* only (same style as Unraid's green bars).
// Array: totals row "Array of N devices" → Free column bar.
// Pool: Data Partition / FS summary row (btrfs/zfs + Size/Used/Free) — NOT "Pool of N devices".

(function () {
  'use strict';

  var lastStatus = null;
  var BAR_WARN = '#ffc107';
  var BAR_CRIT = '#e53935';

  function log() {
    if (window.StorageGuardDebug) {
      console.log.apply(console, ['[StorageGuard]'].concat([].slice.call(arguments)));
    }
  }

  function onMainLikePage() {
    return !!(
      document.getElementById('array_devices') ||
      document.querySelector('[id^="pool_device"]') ||
      document.getElementById('array_list')
    );
  }

  /** Free column = last .usage-disk in the row (Used is before Free). */
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
      el.classList.remove('sg-warning', 'sg-critical', 'sg-bar');
    });
    scope.querySelectorAll('[data-sg-td]').forEach(function (el) {
      el.style.removeProperty('background-color');
      el.style.removeProperty('color');
      el.style.removeProperty('font-weight');
      el.removeAttribute('data-sg-td');
    });
  }

  /**
   * Paint only the Free bar fill (first span inside Free .usage-disk).
   * Does not outline the cell or recolor the whole row.
   */
  function paintFreeBar(tr, level) {
    if (!tr) return false;
    clearPaint(tr);

    if (level !== 'warning' && level !== 'critical') return false;

    var color = level === 'critical' ? BAR_CRIT : BAR_WARN;
    var disk = freeUsageDisk(tr);

    if (disk) {
      var bar = disk.querySelector('span:first-child');
      if (bar) {
        // Unraid already sets width:% — we only change the fill color
        bar.style.setProperty('background-color', color, 'important');
        bar.style.setProperty('background', color, 'important');
        bar.setAttribute('data-sg-bar', level);
        bar.classList.add('sg-bar', level === 'critical' ? 'sg-critical' : 'sg-warning');
        log('bar', level, tr);
        return true;
      }
    }

    // Plain-text Free column (no usage bar in Display settings)
    var tds = tr.querySelectorAll('td');
    if (tds.length) {
      var td = tds[tds.length - 1];
      if (/\d/.test(td.textContent || '')) {
        td.style.setProperty('color', color, 'important');
        td.style.setProperty('font-weight', '700', 'important');
        td.setAttribute('data-sg-td', level);
        log('text free', level, tr);
        return true;
      }
    }
    return false;
  }

  function isPoolOfDevicesRow(tr) {
    var t = (tr.textContent || '').toLowerCase();
    return /pool of\s/.test(t) || /array of\s/.test(t);
  }

  function isArrayOfDevicesRow(tr) {
    return /array of\s/i.test(tr.textContent || '');
  }

  /** Array totals row with Free bar */
  function findArrayFreeRow() {
    var tbody = document.getElementById('array_devices');
    if (!tbody) return null;
    var rows = tbody.querySelectorAll('tr');
    for (var i = 0; i < rows.length; i++) {
      if (isArrayOfDevicesRow(rows[i]) && freeUsageDisk(rows[i])) return rows[i];
    }
    // fallback: last row with free usage-disk
    for (var j = rows.length - 1; j >= 0; j--) {
      if (freeUsageDisk(rows[j])) return rows[j];
    }
    return null;
  }

  /**
   * Pool FS row = Data Partition (or similar) with Size + Used/Free bars.
   * Skip "Pool of N devices" (no real free bar — empty colspan).
   * Skip bare Device 1/2 member rows without usage-disk.
   */
  function findPoolFreeRow(tbody) {
    if (!tbody) return null;
    var rows = tbody.querySelectorAll('tr');
    var i, tr, t, nDisks;

    // 1) Prefer "Data Partition" / ONLINE / filesystem name rows
    for (i = 0; i < rows.length; i++) {
      tr = rows[i];
      if (isPoolOfDevicesRow(tr)) continue;
      t = (tr.textContent || '').toLowerCase();
      nDisks = tr.querySelectorAll('td .usage-disk').length;
      if (nDisks < 1) continue;
      if (
        t.indexOf('data partition') !== -1 ||
        t.indexOf('online') !== -1 ||
        t.indexOf('btrfs') !== -1 ||
        t.indexOf('zfs') !== -1 ||
        t.indexOf('xfs') !== -1 ||
        t.indexOf('btrfs') !== -1
      ) {
        return tr;
      }
    }

    // 2) Any row with two usage-disks (Used + Free) that isn't "Pool of"
    for (i = 0; i < rows.length; i++) {
      tr = rows[i];
      if (isPoolOfDevicesRow(tr)) continue;
      if (tr.querySelectorAll('td .usage-disk').length >= 2) return tr;
    }

    // 3) First non-totals row with any usage-disk
    for (i = 0; i < rows.length; i++) {
      tr = rows[i];
      if (isPoolOfDevicesRow(tr)) continue;
      if (freeUsageDisk(tr)) return tr;
    }
    return null;
  }

  function poolNamesInTbody(tbody) {
    var found = [];
    var links = tbody.querySelectorAll('a[href*="Device?name="]');
    for (var i = 0; i < links.length; i++) {
      var href = links[i].getAttribute('href') || '';
      var m = href.match(/name=([^&]+)/i);
      if (!m) continue;
      var n = decodeURIComponent(m[1]);
      if (found.indexOf(n) === -1) found.push(n);
    }
    // Also "Cache" label as pool name
    var t = (tbody.textContent || '');
    return found;
  }

  function resolvePoolKey(names, poolsStatus) {
    if (!poolsStatus) return null;
    var keys = Object.keys(poolsStatus);
    var i, j, pref;
    for (i = 0; i < names.length; i++) {
      if (poolsStatus[names[i]]) return names[i];
    }
    for (i = 0; i < names.length; i++) {
      pref = names[i].replace(/\d+$/, '');
      if (pref && poolsStatus[pref]) return pref;
    }
    for (i = 0; i < names.length; i++) {
      for (j = 0; j < keys.length; j++) {
        if (keys[j].toLowerCase() === String(names[i]).toLowerCase()) return keys[j];
      }
    }
    return null;
  }

  function applyStatus(status) {
    if (!status) return;
    lastStatus = status;
    if (!onMainLikePage()) return;

    // Clear any previous wrong paints on pool "Pool of N" rows
    document.querySelectorAll('[id^="pool_device"] tr, #array_devices tr').forEach(function (tr) {
      if (isPoolOfDevicesRow(tr) && !isArrayOfDevicesRow(tr)) clearPaint(tr);
    });

    // --- Array: "Array of N devices" Free bar ---
    var arow = findArrayFreeRow();
    if (arow) {
      if (status.array && status.array.enabled && (status.array.level === 'warning' || status.array.level === 'critical')) {
        paintFreeBar(arow, status.array.level);
      } else {
        clearPaint(arow);
      }
    }

    // --- Pools: Data Partition Free bar ---
    var pools = status.pools || {};
    var bodies = document.querySelectorAll('[id^="pool_device"]');
    var matched = {};

    for (var b = 0; b < bodies.length; b++) {
      var tbody = bodies[b];
      // Always clear mistaken totals-row paint in this tbody
      tbody.querySelectorAll('tr').forEach(function (tr) {
        if (isPoolOfDevicesRow(tr)) clearPaint(tr);
      });

      var names = poolNamesInTbody(tbody);
      var key = resolvePoolKey(names, pools);
      // Fallback: body text contains pool key
      if (!key) {
        var bt = (tbody.textContent || '').toLowerCase();
        Object.keys(pools).forEach(function (pk) {
          if (bt.indexOf(pk.toLowerCase()) !== -1) key = key || pk;
        });
      }
      if (!key || matched[key]) continue;
      matched[key] = true;

      var st = pools[key];
      var prow = findPoolFreeRow(tbody);
      log('pool', key, 'row', prow, st);
      if (!prow) continue;

      if (st && st.enabled && (st.level === 'warning' || st.level === 'critical')) {
        paintFreeBar(prow, st.level);
      } else {
        clearPaint(prow);
      }
    }
  }

  function fetchAndApply() {
    fetch('/plugins/StorageGuard/get-config.php', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (data) {
        if (!data || !data._status) return;
        log('status', data._status);
        applyStatus(data._status);
        fetch('/plugins/StorageGuard/check-alerts.php', { credentials: 'same-origin' }).catch(function () {});
      })
      .catch(function (err) {
        console.warn('Storage Guard: config fetch failed', err);
      });
  }

  function boot() {
    fetchAndApply();
    setInterval(function () {
      if (lastStatus) applyStatus(lastStatus);
    }, 1200);
    setInterval(fetchAndApply, 12000);

    if (typeof MutationObserver !== 'undefined') {
      var obs = new MutationObserver(function () {
        if (lastStatus) applyStatus(lastStatus);
      });
      var main = document.querySelector('.TableContainer') || document.getElementById('content') || document.body;
      if (main) obs.observe(main, { childList: true, subtree: true });
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
  console.log('Storage Guard color injector ready (free-bar only)');
})();
