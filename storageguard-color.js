// Storage Guard — paint Free bars on Main.
// Array: "Array of N devices" free bar.
// Pool: prefer "Pool of N devices" free bar (pool total free = our threshold);
//       never paint every member disk row (that looked like "flashing" on multi-device cache).
//
// Style matrix (per target: array / each pool):
//   Outline (default) — border only around .usage-disk; Unraid free fill unchanged.
//   Solid             — recolor free fill yellow/red; no border.
//
// Global opts:
//   Pulse (outline_pulse) — flasher for warning/critical on BOTH styles
//     Outline: pulses the border; Solid: pulses the free fill.
//   Green-when-OK (outline_show_ok) — Outline only (static green border when healthy).
//
// Flash / thrash guards:
//   - data-sg-sig skips clearPaint when level/style/pulse/showOk unchanged
//   - Solid: data-sg-solid on parent + CSS so Unraid span rewrites keep color
//   - Outline: classes on parent .usage-disk (survives fill-span rewrites)
//   - Pulse: only .sg-pulse for warning/critical; never re-clear same sig
//     (clearPaint would restart CSS animation → look like thrash)
//   - Pulse=No: hard-strip every .sg-pulse each apply (stale class → false "pulse")
//   - OK/green: never gets .sg-pulse; CSS also forces animation:none on .sg-ok
(function () {
  'use strict';

  var lastStatus = null;
  var lastOpts = { pulse: false, showOk: false };
  var BAR_WARN = '#ffc107';
  var BAR_CRIT = '#e53935';
  var BAR_OK = '#4caf50';
  var applyTimer = null;
  /** Last painted free-bar row per target key (array / pool name) — clear if target moves. */
  var paintedRows = {};

  function resolveStyle(obj, fallback) {
    if (obj && obj.style === 'outline') return 'outline';
    if (obj && obj.style === 'solid') return 'solid';
    // Product default is outline when status omits style
    return fallback === 'solid' ? 'solid' : 'outline';
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
    if (scope.nodeName === 'TR') scope.removeAttribute('data-sg-sig');
    scope.querySelectorAll('[data-sg-bar]').forEach(function (el) {
      el.style.removeProperty('background-color');
      el.style.removeProperty('background');
      el.removeAttribute('data-sg-bar');
      el.removeAttribute('data-sg-style');
      el.classList.remove('sg-warning', 'sg-critical', 'sg-ok', 'sg-bar', 'sg-solid');
    });
    scope.querySelectorAll('[data-sg-solid], [data-sg-outline], .sg-outline').forEach(function (el) {
      el.style.removeProperty('outline');
      el.style.removeProperty('outline-offset');
      el.style.removeProperty('box-shadow');
      el.removeAttribute('data-sg-outline');
      el.removeAttribute('data-sg-solid');
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

  /** Pulse is only for warning/critical — never OK/green. */
  function wantPulseFor(level, pulse) {
    return !!(pulse && (level === 'warning' || level === 'critical'));
  }

  function stripPulse(el) {
    if (!el || !el.classList) return;
    if (el.classList.contains('sg-pulse')) el.classList.remove('sg-pulse');
  }

  /** When Pulse=No (or level OK), strip every leftover flasher class site-wide. */
  function stripAllPulse() {
    document.querySelectorAll('.sg-pulse').forEach(function (el) {
      el.classList.remove('sg-pulse');
    });
  }

  /** Soft re-tint solid fill if Unraid replaced the bar span (no clearPaint flash). */
  function ensureSolidBar(disk, level, pulse, sig) {
    if (!disk || (level !== 'warning' && level !== 'critical')) return;
    var color = level === 'critical' ? BAR_CRIT : BAR_WARN;
    var wantPulse = wantPulseFor(level, pulse);
    var levelClass = level === 'critical' ? 'sg-critical' : 'sg-warning';
    var bar = disk.querySelector('span:first-child');
    var ok =
      disk.getAttribute('data-sg-solid') === level &&
      disk.getAttribute('data-sg-sig') === sig &&
      !disk.classList.contains('sg-outline') &&
      (wantPulse === disk.classList.contains('sg-pulse')) &&
      bar && bar.classList.contains('sg-solid');
    if (ok) {
      // Pulse=No must never leave a flasher class (stale → green/yellow “pulsing”)
      if (!wantPulse) stripPulse(disk);
      // Only re-tint if Unraid wiped our color (avoids thrash/flicker on every tick)
      if (bar.style.backgroundColor !== color && bar.style.background !== color) {
        bar.style.setProperty('background-color', color, 'important');
        bar.style.setProperty('background', color, 'important');
      }
      return;
    }

    disk.classList.remove('sg-outline', 'sg-ok', 'sg-warning', 'sg-critical', 'sg-pulse');
    disk.removeAttribute('data-sg-outline');
    disk.classList.add(levelClass);
    if (wantPulse) disk.classList.add('sg-pulse');
    else stripPulse(disk);
    disk.setAttribute('data-sg-solid', level);
    if (sig) disk.setAttribute('data-sg-sig', sig);
    if (bar) {
      bar.style.setProperty('background-color', color, 'important');
      bar.style.setProperty('background', color, 'important');
      bar.setAttribute('data-sg-bar', level);
      bar.setAttribute('data-sg-style', 'solid');
      bar.classList.add('sg-bar', 'sg-solid', levelClass);
    }
  }

  /**
   * Outline classes on parent .usage-disk. Free fill span is intentionally untouched.
   * When already correct, no-op (re-toggling .sg-pulse would restart the CSS animation).
   * OK/green never receives .sg-pulse.
   */
  function ensureOutline(disk, level, pulse, sig) {
    if (!disk) return;
    var wantPulse = wantPulseFor(level, pulse);
    var levelClass = level === 'ok' ? 'sg-ok' : (level === 'critical' ? 'sg-critical' : 'sg-warning');
    var ok =
      disk.classList.contains('sg-outline') &&
      disk.getAttribute('data-sg-outline') === level &&
      disk.getAttribute('data-sg-sig') === sig &&
      disk.classList.contains(levelClass) &&
      (wantPulse === disk.classList.contains('sg-pulse')) &&
      !disk.hasAttribute('data-sg-solid');
    if (ok) {
      // Hard stop: Pulse=No or OK must not keep .sg-pulse (stale class → false pulse)
      if (!wantPulse) stripPulse(disk);
      return;
    }

    disk.classList.remove('sg-ok', 'sg-warning', 'sg-critical', 'sg-pulse', 'sg-solid', 'sg-bar');
    disk.removeAttribute('data-sg-solid');
    disk.classList.add('sg-outline', levelClass);
    if (wantPulse) disk.classList.add('sg-pulse');
    else stripPulse(disk);
    disk.setAttribute('data-sg-outline', level);
    disk.setAttribute('data-sg-sig', sig);
    // Ensure free fill is not left with solid tints from a prior style switch
    var bar = disk.querySelector('span:first-child');
    if (bar && (bar.hasAttribute('data-sg-bar') || bar.classList.contains('sg-solid'))) {
      bar.style.removeProperty('background-color');
      bar.style.removeProperty('background');
      bar.removeAttribute('data-sg-bar');
      bar.removeAttribute('data-sg-style');
      bar.classList.remove('sg-bar', 'sg-solid', 'sg-warning', 'sg-critical', 'sg-ok');
    }
  }

  /** Remember which TR we painted for a target; clear previous if Main re-targets. */
  function bindPaintedRow(key, tr) {
    if (!key || !tr) return;
    var prev = paintedRows[key];
    if (prev && prev !== tr && prev.isConnected) {
      clearPaint(prev);
    }
    paintedRows[key] = tr;
  }

  function makeSig(level, style, pulse, showOk) {
    // Product default style is outline (not solid)
    return [level || 'none', style || 'outline', pulse ? '1' : '0', showOk ? '1' : '0'].join('|');
  }

  /**
   * Paint free bar for level: ok | warning | critical
   * style: outline (default) | solid
   * opts: { pulse, showOk }
   *   pulse   — global flasher for warn/crit (Outline border or Solid fill)
   *   showOk  — Outline-only green border when healthy
   */
  function paintFreeBar(tr, level, style, opts, targetKey) {
    if (!tr) return false;
    opts = opts || {};
    var pulse = !!opts.pulse;
    var showOk = !!opts.showOk;
    style = style === 'solid' ? 'solid' : 'outline';
    // Pulse is the flasher for warning/critical on both styles (never for OK/green)
    var pulseActive = wantPulseFor(level, pulse);

    // OK: only Outline + green-when-OK paints; Solid OK = clear (normal Unraid fill)
    if (level === 'ok') {
      if (style !== 'outline' || !showOk) {
        clearPaint(tr);
        if (targetKey && paintedRows[targetKey] === tr) delete paintedRows[targetKey];
        return false;
      }
    } else if (level !== 'warning' && level !== 'critical') {
      clearPaint(tr);
      if (targetKey && paintedRows[targetKey] === tr) delete paintedRows[targetKey];
      return false;
    }

    var sig = makeSig(level, style, pulseActive, showOk);
    var disk = freeUsageDisk(tr);
    // Prefer stable host (.usage-disk / tr) for signature — Unraid often replaces the fill span
    var existing =
      (disk && disk.getAttribute('data-sg-sig') === sig && disk) ||
      (tr.getAttribute('data-sg-sig') === sig && tr) ||
      tr.querySelector('[data-sg-sig="' + sig + '"]');
    if (existing) {
      // Same look: soft-repair only (no clearPaint → no flash / no pulse restart)
      if (style === 'solid' && disk) ensureSolidBar(disk, level, pulse, sig);
      else if (style === 'outline' && disk) ensureOutline(disk, level, pulse, sig);
      // Pulse=No: never leave flasher on soft path (incl. text-fallback td)
      if (!pulseActive) {
        stripPulse(disk);
        tr.querySelectorAll('.sg-pulse').forEach(stripPulse);
      }
      if (targetKey) bindPaintedRow(targetKey, tr);
      return true;
    }

    clearPaint(tr);
    tr.setAttribute('data-sg-sig', sig);

    var color = level === 'critical' ? BAR_CRIT : (level === 'warning' ? BAR_WARN : BAR_OK);

    if (disk) {
      if (style === 'outline') {
        ensureOutline(disk, level, pulse, sig);
        if (targetKey) bindPaintedRow(targetKey, tr);
        log('outline free bar', level, pulseActive ? 'pulse' : 'static', (tr.textContent || '').slice(0, 50));
        return true;
      }
      // solid — mark parent + tint fill (parent attr survives Unraid span rewrites)
      if (level !== 'ok') {
        ensureSolidBar(disk, level, pulse, sig);
        if (targetKey) bindPaintedRow(targetKey, tr);
        log('solid free bar', level, pulseActive ? 'pulse' : 'static', (tr.textContent || '').slice(0, 50));
        return true;
      }
    }

    // Plain-text Free column fallback (no .usage-disk)
    var tds = tr.querySelectorAll('td');
    if (tds.length) {
      var td = tds[tds.length - 1];
      if (/\d/.test(td.textContent || '')) {
        if (style === 'outline') {
          td.style.setProperty('outline', '2px solid ' + color, 'important');
          td.style.setProperty('outline-offset', '2px', 'important');
          td.setAttribute('data-sg-td', level);
          td.setAttribute('data-sg-sig', sig);
          if (pulseActive) td.classList.add('sg-pulse');
          else stripPulse(td);
        } else if (level !== 'ok') {
          td.style.setProperty('color', color, 'important');
          td.style.setProperty('font-weight', '700', 'important');
          td.setAttribute('data-sg-td', level);
          td.setAttribute('data-sg-sig', sig);
          if (pulseActive) td.classList.add('sg-pulse');
          else stripPulse(td);
        }
        if (targetKey) bindPaintedRow(targetKey, tr);
        return true;
      }
    }
    return false;
  }

  function rowText(tr) {
    return (tr && tr.textContent ? tr.textContent : '').toLowerCase();
  }

  function isPoolOfDevicesRow(tr) {
    return /pool of\s/.test(rowText(tr));
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

  function isPoolMemberDeviceRow(tr) {
    var t = rowText(tr);
    // Individual pool slots: "Device 1", device links, not the pool total line
    if (/device\s+\d+/.test(t)) return true;
    if (isPoolOfDevicesRow(tr) || isArrayOfDevicesRow(tr) || isBootSummaryRow(tr)) return false;
    // Named slots like cache2 / nvme under a multi-device pool (have a Device?name= link + not "pool of")
    var link = tr.querySelector('a[href*="Device?name="]');
    if (link && freeUsageDisk(tr) && !isDataPartitionRow(tr)) {
      // single-device free bars on member rows still have usage disks; exclude pure members
      // Prefer only excluding when text looks like a secondary device row
      if (/\b(cache\d+|device)\b/.test(t) && t.indexOf('pool of') === -1) {
        // keep data partition for single-disk pools; multi-device members often say Device N
      }
    }
    return false;
  }

  function findPoolDataFreeRow(root) {
    if (!root) return null;
    var rows = root.querySelectorAll('tr');
    var i, tr;

    // Prefer pool total free ("Pool of N devices") — matches get-config pool free_tb
    for (i = 0; i < rows.length; i++) {
      tr = rows[i];
      if (isPoolOfDevicesRow(tr) && freeUsageDisk(tr)) return tr;
    }
    // Single-disk pool / layout without pool-of row: data partition free
    for (i = 0; i < rows.length; i++) {
      tr = rows[i];
      if (isPoolMemberDeviceRow(tr)) continue;
      if (isDataPartitionRow(tr) && freeUsageDisk(tr)) return tr;
    }
    for (i = 0; i < rows.length; i++) {
      tr = rows[i];
      if (isBootSummaryRow(tr) || isPoolMemberDeviceRow(tr)) continue;
      if (isPoolOfDevicesRow(tr)) continue;
      var t = rowText(tr);
      if (/^[\s]*device\s+\d+/.test(t)) continue;
      if (tr.querySelectorAll('td .usage-disk').length >= 2) return tr;
    }
    for (i = 0; i < rows.length; i++) {
      tr = rows[i];
      if (isBootSummaryRow(tr) || isPoolMemberDeviceRow(tr) || /^[\s]*device\s+\d+/.test(rowText(tr))) continue;
      if (freeUsageDisk(tr)) return tr;
    }
    return null;
  }

  function allPoolRoots() {
    var list = [];
    var seen = {};
    function add(el) {
      if (!el || seen[el.id || el]) return;
      // Never treat the array table/tbody as a pool root (would wipe array free-bar paint)
      if (el.id === 'array_devices' || el.id === 'array_list') return;
      if (el.querySelector && el.querySelector('#array_devices')) return;
      seen[el.id || el] = true;
      list.push(el);
    }
    document.querySelectorAll('[id^="pool_device"]').forEach(add);
    add(document.getElementById('boot_device'));
    // Fallback tables — skip any that are the array section
    document.querySelectorAll('.TableContainer table.disk_status').forEach(function (el) {
      if (el.id === 'array_devices' || (el.querySelector && el.querySelector('#array_devices'))) return;
      // Parent section often labeled Array Devices
      var wrap = el.closest ? el.closest('.TableContainer, .tabs, #tab1, #array') : null;
      var label = wrap ? (wrap.textContent || '').slice(0, 80).toLowerCase() : '';
      if (/^[\s]*array devices/.test(label) || (el.previousElementSibling && /array devices/i.test(el.previousElementSibling.textContent || ''))) {
        // still allow if it also has pool devices — rare; prefer id filters above
      }
      add(el);
    });
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

  function paintTarget(tr, st, opts, targetKey) {
    if (!tr || !st) return;
    if (!st.enabled) {
      clearPaint(tr);
      if (targetKey && paintedRows[targetKey] === tr) delete paintedRows[targetKey];
      return;
    }
    var style = resolveStyle(st, 'outline');
    var level = st.level || 'ok';
    paintFreeBar(tr, level, style, opts, targetKey || null);
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

    // Pulse=No (or mis-set): strip every flasher first so green/OK cannot animate
    if (!opts.pulse) stripAllPulse();

    var arow = findArrayFreeRow();
    if (arow) paintTarget(arow, status.array, opts, 'array');

    var pools = status.pools || {};
    var roots = allPoolRoots();
    var matched = {};

    for (var r = 0; r < roots.length; r++) {
      var root = roots[r];
      // Clear boot summary + individual member rows only — keep pool-total free bar for painting
      root.querySelectorAll('tr').forEach(function (tr) {
        if (isArrayOfDevicesRow(tr)) return;
        if (isBootSummaryRow(tr) || isPoolMemberDeviceRow(tr) || /^[\s]*device\s+\d+/.test(rowText(tr))) {
          clearPaint(tr);
        }
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
        style: resolveStyle(st, 'outline'),
        foundRow: !!prow
      });

      if (!prow) continue;
      // Safety: never paint pool status onto the array totals row
      if (isArrayOfDevicesRow(prow)) continue;
      // Never paint every member disk — only one free-bar target per pool
      if (isPoolMemberDeviceRow(prow) || /^[\s]*device\s+\d+/.test(rowText(prow))) continue;
      matched[key] = true;
      paintTarget(prow, st, opts, 'pool:' + key);
    }

    // Fallback: one row only (pool-of or single data partition) — do not paint all "cache*" members
    Object.keys(pools).forEach(function (pk) {
      if (matched[pk]) return;
      var st = pools[pk];
      if (!st || !st.enabled) return;
      if (st.level !== 'warning' && st.level !== 'critical' &&
          !(st.level === 'ok' && opts.showOk && resolveStyle(st, 'outline') === 'outline')) {
        return;
      }
      var candidates = [];
      document.querySelectorAll('tr').forEach(function (tr) {
        if (isBootSummaryRow(tr) || isArrayOfDevicesRow(tr)) return;
        if (isPoolMemberDeviceRow(tr) || /^[\s]*device\s+\d+/.test(rowText(tr))) return;
        if (!freeUsageDisk(tr)) return;
        var t = rowText(tr);
        if (isPoolOfDevicesRow(tr) && t.indexOf(pk.toLowerCase()) !== -1) {
          candidates.unshift(tr); // prefer pool total
          return;
        }
        if (isDataPartitionRow(tr) && t.indexOf(pk.toLowerCase()) !== -1) {
          candidates.push(tr);
        }
      });
      if (candidates.length && paintFreeBar(candidates[0], st.level || 'ok', resolveStyle(st, 'outline'), opts, 'pool:' + pk)) {
        matched[pk] = true;
      }
    });

    // Final guard: Pulse=No must not leave any flasher (incl. after soft-repair)
    if (!opts.pulse) stripAllPulse();
    // OK/green rows must never keep .sg-pulse even if Pulse=Yes
    document.querySelectorAll('.usage-disk.sg-ok.sg-pulse, .usage-disk[data-sg-outline="ok"].sg-pulse').forEach(stripPulse);
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
