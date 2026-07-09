// Storage Guard — color array/pool free space on Main (and Dashboard when present)
// Free space + level are computed server-side in get-config.php (_status).
// DOM is re-colored on a timer because Unraid nchan replaces tbody HTML.

(function () {
  'use strict';

  var path = window.location.pathname || '';
  if (path.indexOf('/Main') === -1 && path.indexOf('/Dashboard') === -1) return;

  var lastStatus = null;
  var CLASSES = ['sg-warning', 'sg-critical', 'array-warning', 'array-critical', 'pool-warning', 'pool-critical'];

  function log() {
    if (window.StorageGuardDebug) console.log.apply(console, ['[StorageGuard]'].concat([].slice.call(arguments)));
  }

  function clearClasses(el) {
    if (!el) return;
    CLASSES.forEach(function (c) { el.classList.remove(c); });
  }

  function paint(el, level, kind) {
    if (!el) return;
    clearClasses(el);
    if (level !== 'warning' && level !== 'critical') return;
    el.classList.add(level === 'critical' ? 'sg-critical' : 'sg-warning');
    el.classList.add(kind === 'array'
      ? (level === 'critical' ? 'array-critical' : 'array-warning')
      : (level === 'critical' ? 'pool-critical' : 'pool-warning'));
  }

  /** Free column is typically the last <td> on the totals row */
  function freeCells(tr) {
    if (!tr) return [];
    var tds = tr.querySelectorAll('td');
    if (!tds.length) return [tr];
    var last = tds[tds.length - 1];
    var cells = [last, tr];
    // Also paint usage-disk free span if present
    var spans = last.querySelectorAll('.usage-disk span, span');
    for (var i = 0; i < spans.length; i++) cells.push(spans[i]);
    return cells;
  }

  function findArrayTotalsRow() {
    var tbody = document.getElementById('array_devices');
    if (!tbody) return null;
    var rows = tbody.querySelectorAll('tr.tr_last');
    var i, tr, t;
    for (i = 0; i < rows.length; i++) {
      tr = rows[i];
      t = (tr.textContent || '').toLowerCase();
      // "Array of N devices" totals row (localized may vary — also match free-looking sizes)
      if (t.indexOf('array') !== -1 || /\d+(\.\d+)?\s*[tgm]b/i.test(tr.textContent || '')) {
        // Prefer row that mentions Array
        if (t.indexOf('array') !== -1) return tr;
      }
    }
    // Fallback: last tr_last with numeric size in last cell
    for (i = rows.length - 1; i >= 0; i--) {
      if (/\d/.test(rows[i].textContent || '')) return rows[i];
    }
    return rows.length ? rows[rows.length - 1] : null;
  }

  function findPoolTotalsRow(poolName) {
    var name = (poolName || '').toLowerCase();
    var bodies = document.querySelectorAll('[id^="pool_device"]');
    var b, i, j, rows, tr, t, link;
    for (b = 0; b < bodies.length; b++) {
      // Match pool by Device link or text
      link = bodies[b].querySelector('a[href*="Device?name="]');
      var href = link ? (link.getAttribute('href') || '') : '';
      var bodyText = (bodies[b].textContent || '').toLowerCase();
      var match =
        href.toLowerCase().indexOf('name=' + name) !== -1 ||
        bodyText.indexOf(name) !== -1;
      if (!match) continue;

      rows = bodies[b].querySelectorAll('tr.tr_last');
      for (j = 0; j < rows.length; j++) {
        t = (rows[j].textContent || '').toLowerCase();
        if (t.indexOf('pool') !== -1) return rows[j];
      }
      if (rows.length) return rows[rows.length - 1];
    }

    // Dashboard pool list
    var dash = document.querySelectorAll('[id^="pool_list"]');
    for (i = 0; i < dash.length; i++) {
      if ((dash[i].getAttribute('title') || '').toLowerCase().indexOf(name) !== -1 ||
          (dash[i].textContent || '').toLowerCase().indexOf(name) !== -1) {
        return dash[i];
      }
    }
    return null;
  }

  function applyStatus(status) {
    if (!status) return;
    lastStatus = status;

    // Array
    if (status.array && status.array.enabled && status.array.level && status.array.level !== 'ok') {
      var atr = findArrayTotalsRow();
      freeCells(atr).forEach(function (el) { paint(el, status.array.level, 'array'); });
      log('array', status.array);
    } else if (status.array) {
      var atr2 = findArrayTotalsRow();
      freeCells(atr2).forEach(clearClasses);
    }

    // Pools
    var pools = status.pools || {};
    Object.keys(pools).forEach(function (pname) {
      var st = pools[pname];
      var row = findPoolTotalsRow(pname);
      if (!row) return;
      if (st.enabled && st.level && st.level !== 'ok') {
        freeCells(row).forEach(function (el) { paint(el, st.level, 'pool'); });
        log('pool', pname, st);
      } else {
        freeCells(row).forEach(clearClasses);
      }
    });

    // Dashboard array list
    if (status.array && status.array.enabled && status.array.level !== 'ok') {
      var al = document.getElementById('array_list');
      if (al) paint(al, status.array.level, 'array');
    }
  }

  function fetchAndApply() {
    fetch('/plugins/StorageGuard/get-config.php', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (data) {
        if (data && data._status) {
          applyStatus(data._status);
        } else {
          log('no _status in config response', data);
        }
        // Alerts (rate-limited server-side)
        fetch('/plugins/StorageGuard/check-alerts.php', { credentials: 'same-origin' }).catch(function () {});
      })
      .catch(function (err) {
        console.warn('Storage Guard: config fetch failed', err);
      });
  }

  // Initial + periodic re-apply (nchan rebuilds table rows)
  fetchAndApply();
  setInterval(function () {
    if (lastStatus) applyStatus(lastStatus);
  }, 1500);
  setInterval(fetchAndApply, 15000);

  // Re-apply when Main device tables mutate
  function observe(id) {
    var el = document.getElementById(id);
    if (!el || typeof MutationObserver === 'undefined') return;
    new MutationObserver(function () {
      if (lastStatus) applyStatus(lastStatus);
    }).observe(el, { childList: true, subtree: true });
  }
  observe('array_devices');
  // pool_device0..n appear after paint — observe table containers
  document.querySelectorAll('[id^="pool_device"]').forEach(function (el) {
    if (typeof MutationObserver === 'undefined') return;
    new MutationObserver(function () {
      if (lastStatus) applyStatus(lastStatus);
    }).observe(el, { childList: true, subtree: true });
  });

  window.StorageGuardColor = {
    refresh: fetchAndApply,
    apply: applyStatus,
    debug: function (on) { window.StorageGuardDebug = !!on; }
  };
  console.log('Storage Guard color injector ready');
})();
