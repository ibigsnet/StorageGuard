// Storage Guard — color Free usage-bar fill only.
// Array: "Array of N devices" totals Free bar.
// Pool: Data Partition Free bar (NOT "Pool of N devices", NOT Internal Boot).
// Supports pools-only + internal-boot-on-cache (Unraid 7 boot partition scheme).

(function () {
  'use strict';

  var lastStatus = null;
  var lastStyle = 'outline'; // outline | solid
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
      el.classList.remove('sg-warning', 'sg-critical', 'sg-bar', 'sg-solid');
    });
    scope.querySelectorAll('[data-sg-outline], .sg-outline').forEach(function (el) {
      el.style.removeProperty('outline');
      el.style.removeProperty('outline-offset');
      el.style.removeProperty('box-shadow');
      el.removeAttribute('data-sg-outline');
      el.classList.remove('sg-outline', 'sg-warning', 'sg-critical');
    });
    scope.querySelectorAll('[data-sg-td]').forEach(function (el) {
      el.style.removeProperty('color');
      el.style.removeProperty('font-weight');
      el.style.removeProperty('outline');
      el.style.removeProperty('box-shadow');
      el.removeAttribute('data-sg-td');
    });
  }

  /**
   * style: 'outline' (default) — keep green free fill, pulse yellow/red outline
   *        'solid'             — replace free bar fill with yellow/red
   */
  function paintFreeBar(tr, level, style) {
    if (!tr) return false;
    clearPaint(tr);
    if (level !== 'warning' && level !== 'critical') return false;

    style = style === 'solid' ? 'solid' : 'outline';
    var color = level === 'critical' ? BAR_CRIT : BAR_WARN;
    var disk = freeUsageDisk(tr);
    if (disk) {
      var bar = disk.querySelector('span:first-child');
      if (style === 'outline') {
        // Keep Unraid green fill; outline the free bar container with a slow pulse
        disk.classList.add('sg-outline', level === 'critical' ? 'sg-critical' : 'sg-warning');
        disk.setAttribute('data-sg-outline', level);
        log('outline free bar', level, (tr.textContent || '').slice(0, 60));
        return true;
      }
      // solid: override free fill color
      if (bar) {
        bar.style.setProperty('background-color', color, 'important');
        bar.style.setProperty('background', color, 'important');
        bar.setAttribute('data-sg-bar', level);
        bar.setAttribute('data-sg-style', 'solid');
        bar.classList.add('sg-bar', 'sg-solid', level === 'critical' ? 'sg-critical' : 'sg-warning');
        log('solid free bar', level, (tr.textContent || '').slice(0, 60));
        return true;
      }
    }

    // Plain-text Free column (no usage bar)
    var tds = tr.querySelectorAll('td');
    if (tds.length) {
      var td = tds[tds.length - 1];
      if (/\d/.test(td.textContent || '')) {
        if (style === 'outline') {
          td.style.setProperty('outline', '2px solid ' + color, 'important');
          td.style.setProperty('outline-offset', '2px', 'important');
          td.setAttribute('data-sg-td', level);
        } else {
          td.style.setProperty('color', color, 'important');
          td.style.setProperty('font-weight', '700', 'important');
          td.setAttribute('data-sg-td', level);
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

  /** Internal boot summary — not the cache data free space */
  function isBootSummaryRow(tr) {
    var t = rowText(tr);
    return (
      t.indexOf('internal boot') !== -1 ||
      t.indexOf('boot(flash)') !== -1 ||
      t.indexOf('boot(flash)') !== -1 ||
      (t.indexOf('boot') !== -1 && t.indexOf('data partition') === -1 && /flash|zfs/.test(t) && t.indexOf('btrfs') === -1)
    );
  }

  function isDataPartitionRow(tr) {
    var t = rowText(tr);
    if (isTotalsOnlyRow(tr) || isBootSummaryRow(tr)) return false;
    // Explicit Unraid label
    if (t.indexOf('data partition') !== -1) return true;
    // Has FS type + usage bars (cache data), not bare "Device N"
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

  /**
   * Pool data free row — Data Partition (btrfs/xfs/…) with Used+Free bars.
   * Never Internal Boot, never "Pool of N devices".
   */
  function findPoolDataFreeRow(root) {
    if (!root) return null;
    var rows = root.querySelectorAll('tr');
    var i, tr;

    // 1) Explicit Data Partition
    for (i = 0; i < rows.length; i++) {
      tr = rows[i];
      if (isDataPartitionRow(tr) && freeUsageDisk(tr)) return tr;
    }

    // 2) Row with 2 usage-disks, not boot/totals/device-only
    for (i = 0; i < rows.length; i++) {
      tr = rows[i];
      if (isTotalsOnlyRow(tr) || isBootSummaryRow(tr)) continue;
      var t = rowText(tr);
      if (/^[\s]*device\s+\d+/.test(t) || t.indexOf('device 1') !== -1 || t.indexOf('device 2') !== -1) {
        // member rows usually have no FS bars; skip if only device label
        if (tr.querySelectorAll('td .usage-disk').length < 2) continue;
      }
      if (tr.querySelectorAll('td .usage-disk').length >= 2) return tr;
    }

    // 3) Any non-boot non-totals row with a free bar
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
    // Whole Pool Devices section as fallback (table containers)
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
    // Single-pool systems: if only one pool in status and this root looks like a pool table
    if (keys.length === 1 && (/pool|cache|btrfs|data partition|device/.test(t))) {
      return keys[0];
    }
    return null;
  }

  function applyStatus(status, style) {
    if (!status) return;
    lastStatus = status;
    if (style) lastStyle = style === 'solid' ? 'solid' : 'outline';
    if (!onMainLikePage()) {
      log('not on Main');
      return;
    }
    var mode = lastStyle;

    // --- Array (skipped when array_coloring=no or pools-only) ---
    var arow = findArrayFreeRow();
    if (arow) {
      if (status.array && status.array.enabled && (status.array.level === 'warning' || status.array.level === 'critical')) {
        paintFreeBar(arow, status.array.level, mode);
      } else {
        clearPaint(arow);
      }
    }

    // --- Pools (incl. boot-on-cache layout) ---
    var pools = status.pools || {};
    var roots = allPoolRoots();
    var matched = {};

    for (var r = 0; r < roots.length; r++) {
      var root = roots[r];
      // Clear bad paints on totals / boot summary in this root
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
        names: names,
        level: st.level,
        enabled: st.enabled,
        foundRow: !!(prow),
        rowHint: prow ? rowText(prow).slice(0, 80) : null
      });

      if (!prow) continue;
      matched[key] = true;

      if (st.enabled && (st.level === 'warning' || st.level === 'critical')) {
        paintFreeBar(prow, st.level, mode);
      } else {
        clearPaint(prow);
      }
    }

    // Last resort: one critical pool, paint any unmatched Data Partition free bar on Main
    Object.keys(pools).forEach(function (pk) {
      if (matched[pk]) return;
      var st = pools[pk];
      if (!st || !st.enabled || (st.level !== 'warning' && st.level !== 'critical')) return;
      document.querySelectorAll('tr').forEach(function (tr) {
        if (matched[pk]) return;
        if (!isDataPartitionRow(tr) && rowText(tr).indexOf(pk.toLowerCase()) === -1) return;
        if (isBootSummaryRow(tr) || isTotalsOnlyRow(tr)) return;
        if (!freeUsageDisk(tr)) return;
        if (paintFreeBar(tr, st.level, mode)) {
          matched[pk] = true;
          log('last-resort paint', pk, rowText(tr).slice(0, 60));
        }
      });
    });
  }

  function fetchAndApply() {
    fetch('/plugins/StorageGuard/get-config.php', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (data) {
        if (!data || !data._status) return;
        var style = (data.color_style === 'solid') ? 'solid' : 'outline';
        log('status', data._status, 'style', style);
        applyStatus(data._status, style);
        fetch('/plugins/StorageGuard/check-alerts.php', { credentials: 'same-origin' }).catch(function () {});
      })
      .catch(function (err) {
        console.warn('Storage Guard: config fetch failed', err);
      });
  }

  function boot() {
    fetchAndApply();
    setInterval(function () {
      if (lastStatus) applyStatus(lastStatus, lastStyle);
    }, 1200);
    setInterval(fetchAndApply, 12000);

    if (typeof MutationObserver !== 'undefined') {
      var obs = new MutationObserver(function () {
        if (lastStatus) applyStatus(lastStatus, lastStyle);
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
  console.log('Storage Guard color injector ready (outline default / solid optional)');
})();
