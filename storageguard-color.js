// Storage Guard — color free-space *bars* on Main (not whole rows)
// Levels come from get-config.php (_status). Re-applies after nchan DOM refresh.

(function () {
  'use strict';

  var lastStatus = null;
  var BAR_WARN = '#ffc107';
  var BAR_CRIT = '#f44336';

  function log() {
    if (window.StorageGuardDebug) {
      console.log.apply(console, ['[StorageGuard]'].concat([].slice.call(arguments)));
    }
  }

  function onMainLikePage() {
    // Prefer element detection over pathname (more reliable across Unraid versions)
    return !!(
      document.getElementById('array_devices') ||
      document.querySelector('[id^="pool_device"]') ||
      document.getElementById('array_list') ||
      document.querySelector('[id^="pool_list"]')
    );
  }

  function clearBarPaint(root) {
    if (!root) return;
    root.querySelectorAll('.usage-disk[data-sg], .usage-disk span[data-sg], td[data-sg]').forEach(function (el) {
      el.style.removeProperty('background-color');
      el.style.removeProperty('background');
      el.style.removeProperty('outline');
      el.style.removeProperty('box-shadow');
      el.style.removeProperty('color');
      el.removeAttribute('data-sg');
      el.classList.remove('sg-warning', 'sg-critical', 'sg-bar');
    });
    // also clear legacy row classes if present
    root.classList.remove('sg-warning', 'sg-critical', 'array-warning', 'array-critical', 'pool-warning', 'pool-critical');
  }

  /**
   * Paint the Free column usage bar (first span inside .usage-disk).
   * Falls back to the Free <td> when bars are disabled in Display settings.
   */
  function paintFreeCell(tr, level) {
    if (!tr) return;
    var td = tr.querySelector('td:last-child') || tr;
    var disk = td.querySelector('.usage-disk');
    var bar = disk ? disk.querySelector('span:first-child') : null;
    var label = disk ? disk.querySelector('span:last-child') : null;
    var color = level === 'critical' ? BAR_CRIT : BAR_WARN;

    // Clear previous on this cell first
    clearBarPaint(td);

    if (level !== 'warning' && level !== 'critical') return;

    if (bar) {
      // The filled portion of Unraid's free-space bar
      bar.style.setProperty('background-color', color, 'important');
      bar.style.setProperty('background', color, 'important');
      bar.setAttribute('data-sg', level);
      bar.classList.add('sg-bar', level === 'critical' ? 'sg-critical' : 'sg-warning');
      if (disk) {
        disk.style.setProperty('outline', '2px solid ' + color, 'important');
        disk.setAttribute('data-sg', level);
      }
      if (label) {
        label.style.setProperty('font-weight', '700', 'important');
        if (level === 'critical') label.style.setProperty('color', '#ffcdd2', 'important');
        label.setAttribute('data-sg', level);
      }
      log('painted bar', level, tr);
      return;
    }

    // Plain free text mode (no usage bar)
    td.style.setProperty('background-color', color, 'important');
    td.style.setProperty('color', level === 'critical' ? '#fff' : '#000', 'important');
    td.style.setProperty('font-weight', '700', 'important');
    td.setAttribute('data-sg', level);
    log('painted td (no bar)', level, tr);
  }

  function totalsRows(tbody) {
    if (!tbody) return [];
    return Array.prototype.slice.call(tbody.querySelectorAll('tr.tr_last'));
  }

  function pickTotalsRow(tbody, preferText) {
    var rows = totalsRows(tbody);
    var i, t;
    if (preferText) {
      for (i = 0; i < rows.length; i++) {
        t = (rows[i].textContent || '').toLowerCase();
        if (t.indexOf(preferText) !== -1) return rows[i];
      }
    }
    // Prefer a row that has a free size / usage bar
    for (i = rows.length - 1; i >= 0; i--) {
      if (rows[i].querySelector('.usage-disk') || /\d+(\.\d+)?\s*[tgm]b/i.test(rows[i].textContent || '')) {
        return rows[i];
      }
    }
    return rows.length ? rows[rows.length - 1] : null;
  }

  function poolNamesInTbody(tbody) {
    var found = [];
    var links = tbody.querySelectorAll('a[href*="Device?name="], a[href*="Device?name%3D"]');
    for (var i = 0; i < links.length; i++) {
      var href = links[i].getAttribute('href') || '';
      var m = href.match(/name=([^&]+)/i);
      if (!m) continue;
      var n = decodeURIComponent(m[1]).replace(/\/$/, '');
      // strip trailing device index: cache2 -> try as-is and as cache
      if (found.indexOf(n) === -1) found.push(n);
    }
    return found;
  }

  function resolvePoolKey(names, poolsStatus) {
    if (!poolsStatus) return null;
    var keys = Object.keys(poolsStatus);
    var i, j, n, pref;
    // Exact match first
    for (i = 0; i < names.length; i++) {
      if (poolsStatus[names[i]]) return names[i];
    }
    // prefix(cache2) => cache
    for (i = 0; i < names.length; i++) {
      pref = names[i].replace(/\d+$/, '');
      if (pref && poolsStatus[pref]) return pref;
    }
    // case-insensitive
    for (i = 0; i < names.length; i++) {
      for (j = 0; j < keys.length; j++) {
        if (keys[j].toLowerCase() === names[i].toLowerCase()) return keys[j];
      }
    }
    return null;
  }

  function applyStatus(status) {
    if (!status) return;
    lastStatus = status;
    if (!onMainLikePage()) {
      log('skip apply — not on Main-like page');
      return;
    }

    // --- Array ---
    var arrayBody = document.getElementById('array_devices');
    if (arrayBody) {
      var arow = pickTotalsRow(arrayBody, 'array');
      if (status.array && status.array.enabled && (status.array.level === 'warning' || status.array.level === 'critical')) {
        paintFreeCell(arow, status.array.level);
      } else if (arow) {
        clearBarPaint(arow.querySelector('td:last-child') || arow);
      }
    }

    // Dashboard array block
    var alist = document.getElementById('array_list');
    if (alist && status.array && status.array.enabled && status.array.level !== 'ok') {
      // tint free-looking numbers inside dashboard card
      var bars = alist.querySelectorAll('.usage-disk');
      if (bars.length) {
        bars.forEach(function (d) {
          var fakeTr = { querySelector: function (sel) {
            if (sel === 'td:last-child') return d.parentElement;
            return d.parentElement ? d.parentElement.querySelector(sel) : null;
          }};
          // simpler: paint disk directly
          var bar = d.querySelector('span:first-child');
          var color = status.array.level === 'critical' ? BAR_CRIT : BAR_WARN;
          if (bar) {
            bar.style.setProperty('background-color', color, 'important');
            bar.setAttribute('data-sg', status.array.level);
          }
        });
      }
    }

    // --- Pools ---
    var pools = status.pools || {};
    var bodies = document.querySelectorAll('[id^="pool_device"]');
    var matched = {};
    for (var b = 0; b < bodies.length; b++) {
      var tbody = bodies[b];
      var names = poolNamesInTbody(tbody);
      var key = resolvePoolKey(names, pools);
      log('pool tbody', tbody.id, 'names', names, 'key', key);
      if (!key || matched[key]) continue;
      var st = pools[key];
      if (!st) continue;
      matched[key] = true;
      var prow = pickTotalsRow(tbody, 'pool');
      if (!prow) {
        // last resort: any row with usage-disk
        prow = tbody.querySelector('tr:has(.usage-disk)') || tbody.querySelector('tr.tr_last');
      }
      if (st.enabled && (st.level === 'warning' || st.level === 'critical')) {
        paintFreeCell(prow, st.level);
      } else if (prow) {
        clearBarPaint(prow.querySelector('td:last-child') || prow);
      }
    }

    // If a critical/warning pool was never matched, try loose scan (single-pool systems)
    Object.keys(pools).forEach(function (pname) {
      if (matched[pname]) return;
      var st = pools[pname];
      if (!st || !st.enabled || (st.level !== 'warning' && st.level !== 'critical')) return;
      // find any pool_device whose text mentions the pool name
      for (var i = 0; i < bodies.length; i++) {
        if ((bodies[i].textContent || '').toLowerCase().indexOf(pname.toLowerCase()) === -1) continue;
        var row = pickTotalsRow(bodies[i], 'pool') || pickTotalsRow(bodies[i], null);
        if (row) {
          paintFreeCell(row, st.level);
          log('loose match pool', pname, bodies[i].id);
          break;
        }
      }
    });
  }

  function fetchAndApply() {
    fetch('/plugins/StorageGuard/get-config.php', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (data) {
        if (!data || !data._status) {
          console.warn('Storage Guard: get-config missing _status', data);
          return;
        }
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
    // nchan rewrites table bodies often
    setInterval(function () {
      if (lastStatus) applyStatus(lastStatus);
    }, 1200);
    setInterval(fetchAndApply, 12000);

    if (typeof MutationObserver !== 'undefined') {
      var obs = new MutationObserver(function () {
        if (lastStatus) applyStatus(lastStatus);
      });
      var roots = [
        document.getElementById('array_devices'),
        document.getElementById('array_list')
      ];
      document.querySelectorAll('[id^="pool_device"], [id^="pool_list"]').forEach(function (el) {
        roots.push(el);
      });
      // Also observe a parent that wraps Main tables
      var main = document.querySelector('.TableContainer') || document.getElementById('content') || document.body;
      roots.push(main);
      roots.forEach(function (el) {
        if (el) obs.observe(el, { childList: true, subtree: true });
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  // Retry once after load — nchan may fill tables late
  setTimeout(fetchAndApply, 2500);
  setTimeout(fetchAndApply, 6000);

  window.StorageGuardColor = {
    refresh: fetchAndApply,
    apply: applyStatus,
    debug: function (on) {
      window.StorageGuardDebug = on !== false;
      console.log('StorageGuardDebug', window.StorageGuardDebug);
      fetchAndApply();
    }
  };
  console.log('Storage Guard color injector ready (bar mode)');
})();
