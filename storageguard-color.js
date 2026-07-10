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


  function freeUsageDisk(tr) {
    if (!tr) return null;
    var disks = tr.querySelectorAll('td .usage-disk');
    if (disks.length) return disks[disks.length - 1];
    return null;
  }


  function freeUsageCell(disk) {
    if (!disk) return null;
    if (disk.closest) return disk.closest('td');
    return disk.parentElement && disk.parentElement.nodeName === 'TD' ? disk.parentElement : null;
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


  function ensureSolidBar(disk, level, pulse, sig) {
    if (!disk || (level !== 'warning' && level !== 'critical')) return;
    var color = level === 'critical' ? BAR_CRIT : BAR_WARN;
    var wantPulse = !!(pulse && (level === 'warning' || level === 'critical'));
    var levelClass = level === 'critical' ? 'sg-critical' : 'sg-warning';
    var bar = disk.querySelector('span:first-child');
    var ok =
      disk.getAttribute('data-sg-solid') === level &&
      disk.getAttribute('data-sg-sig') === sig &&
      !disk.classList.contains('sg-outline') &&
      (wantPulse === disk.classList.contains('sg-pulse')) &&
      bar && bar.classList.contains('sg-solid');

    // Pulse: leave native free fill (greenbar) alone; CSS flashes warn color over it.
    // No pulse: lock solid warn/crit over the native bar.
    function applySolidFill(el) {
      if (!el) return;
      if (wantPulse) {
        el.style.removeProperty('background-color');
        el.style.removeProperty('background');
        el.style.removeProperty('opacity');
      } else {
        el.style.setProperty('background-color', color, 'important');
        el.style.setProperty('background', color, 'important');
        el.style.removeProperty('opacity');
      }
    }

    if (ok) {
      applySolidFill(bar);
      return;
    }

    disk.classList.remove('sg-outline', 'sg-ok', 'sg-warning', 'sg-critical', 'sg-pulse');
    disk.removeAttribute('data-sg-outline');
    disk.classList.add(levelClass);
    if (wantPulse) disk.classList.add('sg-pulse');
    else disk.classList.remove('sg-pulse');
    disk.setAttribute('data-sg-solid', level);
    if (sig) disk.setAttribute('data-sg-sig', sig);
    if (bar) {
      applySolidFill(bar);
      bar.setAttribute('data-sg-bar', level);
      bar.setAttribute('data-sg-style', 'solid');
      bar.classList.add('sg-bar', 'sg-solid', levelClass);
    }
  }

  function outlineCellMarked(cell, level, pulseActive, sig) {
    return !!(
      cell &&
      cell.getAttribute('data-sg-outline') === level &&
      cell.getAttribute('data-sg-sig') === sig &&
      pulseActive === cell.classList.contains('sg-pulse')
    );
  }

  function markOutlineCell(cell, level, pulseActive, sig) {
    if (!cell) return;
    if (outlineCellMarked(cell, level, pulseActive, sig)) return;
    cell.setAttribute('data-sg-outline', level);
    cell.setAttribute('data-sg-sig', sig);
    if (pulseActive) cell.classList.add('sg-pulse');
    else cell.classList.remove('sg-pulse');
  }

  function makeSig(level, style, pulse, showOk) {
    return [level || 'none', style || 'outline', pulse ? '1' : '0', showOk ? '1' : '0'].join('|');
  }

  function paintFreeBar(tr, level, style, opts) {
    if (!tr) return false;
    opts = opts || {};
    var pulse = !!opts.pulse;
    var showOk = !!opts.showOk;
    style = style === 'solid' ? 'solid' : 'outline';
    var pulseActive = !!(pulse && (level === 'warning' || level === 'critical'));

    if (level === 'ok') {
      if (style !== 'outline' || !showOk) {
        clearPaint(tr);
        return false;
      }
    } else if (level !== 'warning' && level !== 'critical') {
      clearPaint(tr);
      return false;
    }

    var sig = makeSig(level, style, pulseActive, showOk);
    var disk = freeUsageDisk(tr);
    var cell = freeUsageCell(disk);

    if (style === 'outline') {
      if (outlineCellMarked(cell, level, pulseActive, sig)) {
        if (tr.getAttribute('data-sg-sig') !== sig) tr.setAttribute('data-sg-sig', sig);
        return true;
      }
      if (disk && disk.hasAttribute('data-sg-solid')) clearPaint(tr);
      tr.setAttribute('data-sg-sig', sig);
      cell = freeUsageCell(disk) || cell;
      if (cell) {
        markOutlineCell(cell, level, pulseActive, sig);
        return true;
      }
      var tdsO = tr.querySelectorAll('td');
      if (tdsO.length) {
        var tdO = tdsO[tdsO.length - 1];
        if (/\d/.test(tdO.textContent || '')) {
          markOutlineCell(tdO, level, pulseActive, sig);
          return true;
        }
      }
      return false;
    }

    var existing =
      (disk && disk.getAttribute('data-sg-sig') === sig && disk) ||
      (tr.getAttribute('data-sg-sig') === sig && tr);
    if (existing) {
      if (disk) ensureSolidBar(disk, level, pulse, sig);
      return true;
    }

    clearPaint(tr);
    tr.setAttribute('data-sg-sig', sig);
    if (disk && level !== 'ok') {
      ensureSolidBar(disk, level, pulse, sig);
      return true;
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

    if (/device\s+\d+/.test(t)) return true;
    if (isPoolOfDevicesRow(tr) || isArrayOfDevicesRow(tr) || isBootSummaryRow(tr)) return false;

    var link = tr.querySelector('a[href*="Device?name="]');
    if (link && freeUsageDisk(tr) && !isDataPartitionRow(tr)) {


      if (/\b(cache\d+|device)\b/.test(t) && t.indexOf('pool of') === -1) {

      }
    }
    return false;
  }

  function findPoolDataFreeRow(root) {
    if (!root) return null;
    var rows = root.querySelectorAll('tr');
    var i, tr;


    for (i = 0; i < rows.length; i++) {
      tr = rows[i];
      if (isPoolOfDevicesRow(tr) && freeUsageDisk(tr)) return tr;
    }

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

      if (el.id === 'array_devices' || el.id === 'array_list') return;
      if (el.querySelector && el.querySelector('#array_devices')) return;
      seen[el.id || el] = true;
      list.push(el);
    }
    document.querySelectorAll('[id^="pool_device"]').forEach(add);
    add(document.getElementById('boot_device'));

    document.querySelectorAll('.TableContainer table.disk_status').forEach(function (el) {
      if (el.id === 'array_devices' || (el.querySelector && el.querySelector('#array_devices'))) return;

      var wrap = el.closest ? el.closest('.TableContainer, .tabs, #tab1, #array') : null;
      var label = wrap ? (wrap.textContent || '').slice(0, 80).toLowerCase() : '';
      if (/^[\s]*array devices/.test(label) || (el.previousElementSibling && /array devices/i.test(el.previousElementSibling.textContent || ''))) {

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

  function paintTarget(tr, st, opts) {
    if (!tr || !st) return;
    if (!st.enabled) {
      clearPaint(tr);
      return;
    }
    var style = resolveStyle(st, 'outline');
    var level = st.level || 'ok';
    paintFreeBar(tr, level, style, opts);
  }

  function stripAllPulse() {
    document.querySelectorAll('.sg-pulse').forEach(function (el) {
      el.classList.remove('sg-pulse');
    });
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

    if (!opts.pulse) stripAllPulse();

    var arow = findArrayFreeRow();
    if (arow) paintTarget(arow, status.array, opts);

    var pools = status.pools || {};
    var roots = allPoolRoots();
    var matched = {};

    for (var r = 0; r < roots.length; r++) {
      var root = roots[r];

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

      if (isArrayOfDevicesRow(prow)) continue;

      if (isPoolMemberDeviceRow(prow) || /^[\s]*device\s+\d+/.test(rowText(prow))) continue;
      matched[key] = true;
      paintTarget(prow, st, opts);
    }

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
          candidates.unshift(tr);
          return;
        }
        if (isDataPartitionRow(tr) && t.indexOf(pk.toLowerCase()) !== -1) {
          candidates.push(tr);
        }
      });
      if (candidates.length && paintFreeBar(candidates[0], st.level || 'ok', resolveStyle(st, 'outline'), opts)) {
        matched[pk] = true;
      }
    });

    if (!opts.pulse) stripAllPulse();
  }

  function scheduleApply() {
    if (applyTimer) return;
    applyTimer = requestAnimationFrame(function () {
      applyTimer = null;
      if (lastStatus) applyStatus(lastStatus, lastOpts);
    });
  }

  function isMainTableId(id) {
    return id === 'array_devices' || id === 'boot_device' || (id && id.indexOf('pool_device') === 0);
  }

  function hookJQueryHtml() {
    var $ = window.jQuery || window.$;
    if (!$ || !$.fn || $.fn._sgHtmlHooked) return;
    var orig = $.fn.html;
    $.fn.html = function (value) {
      if (arguments.length === 0) return orig.apply(this, arguments);
      var ret = orig.apply(this, arguments);
      if (!lastStatus) return ret;
      var need = false;
      this.each(function () {
        var id = this.id || '';
        if (isMainTableId(id)) need = true;
        else if (this.querySelector && (
          this.querySelector('#array_devices') ||
          this.querySelector('#boot_device') ||
          this.querySelector('[id^="pool_device"]')
        )) need = true;
      });
      if (need) applyStatus(lastStatus, lastOpts);
      return ret;
    };
    $.fn._sgHtmlHooked = true;
    log('jquery html hook installed');
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
    hookJQueryHtml();
    fetchAndApply();
    setInterval(function () {
      if (lastStatus) applyStatus(lastStatus, lastOpts);
    }, 5000);
    setInterval(fetchAndApply, 10000);
    setTimeout(hookJQueryHtml, 0);
    setTimeout(hookJQueryHtml, 1000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
  setTimeout(fetchAndApply, 1500);

  window.StorageGuardColor = {
    refresh: fetchAndApply,
    apply: applyStatus,
    debug: function (on) {
      window.StorageGuardDebug = on !== false;
      fetchAndApply();
    }
  };
})();
