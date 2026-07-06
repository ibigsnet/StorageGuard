// Storage Guard - Injection for coloring on Main page and Dashboard
// Leverages Unraid's existing UI elements for array and pool free space coloring
// Uses the full configuration from the settings (disk capacity based thresholds, per-pool, etc.)

(function() {
  'use strict';

  const path = window.location.pathname;
  if (!path.includes('/Main') && !path.includes('/Dashboard')) {
    return;
  }

  console.log('Storage Guard color injector loaded');

  fetch('/plugins/StorageGuard/get-config.php')
    .then(r => r.json())
    .then(cfg => {
      applyColoring(cfg);
      // Check and send alerts (server side, rate limited)
      fetch('/plugins/StorageGuard/check-alerts.php').catch(()=>{});
    })
    .catch(err => {
      console.warn('Storage Guard: could not load config, using defaults', err);
      applyColoring({
        array_coloring: 'yes',
        array_warning: '8T',
        array_critical: '2T',
        pool_coloring: 'yes',
        pools_to_color: 'all'
      });
    });

  function parseSizeToTB(sizeStr) {
    if (!sizeStr || typeof sizeStr !== 'string') return 0;
    const num = parseFloat(sizeStr);
    if (isNaN(num)) return 0;
    const upper = sizeStr.toUpperCase();
    if (upper.includes('T')) return num;
    if (upper.includes('G')) return num / 1024;
    if (upper.includes('M')) return num / 1024 / 1024;
    if (upper.includes('K')) return num / 1024 / 1024 / 1024;
    return num;
  }

  function getFreeTB(text) {
    if (!text) return null;
    const match = text.match(/([\d.]+)\s*(T|G|M|K)?B?\s*free/i);
    if (!match) return null;
    return parseSizeToTB(match[0]);
  }

  function findElementForArray() {
    // Try common Unraid Main tab structures for the array summary
    let el = document.getElementById('array');
    if (el) return el;

    // Look for header containing "Array" and nearby free space
    const headers = document.querySelectorAll('h2, h3, .title, [class*="array"]');
    for (let h of headers) {
      if (h.textContent.toLowerCase().includes('array')) {
        // Find the container that has the free text
        let container = h.closest('tr') || h.closest('div') || h.parentElement;
        if (container && container.textContent.toLowerCase().includes('free')) {
          return container;
        }
        // Broaden search
        const siblings = h.parentElement ? Array.from(h.parentElement.children) : [];
        for (let s of siblings) {
          if (s.textContent.toLowerCase().includes('free')) return s;
        }
      }
    }
    return null;
  }

  function findElementForPool(poolName) {
    const lowerName = poolName.toLowerCase();
    const headers = document.querySelectorAll('h2, h3, .title, [class*="pool"], [id*="pool"]');
    for (let h of headers) {
      const txt = h.textContent.toLowerCase();
      if (txt.includes(lowerName) || txt.includes(poolName.toLowerCase().replace(/[^a-z0-9]/g, ''))) {
        let container = h.closest('tr') || h.closest('div') || h.parentElement;
        if (container && container.textContent.toLowerCase().includes('free')) {
          return container;
        }
        const siblings = h.parentElement ? Array.from(h.parentElement.children) : [];
        for (let s of siblings) {
          if (s.textContent.toLowerCase().includes('free')) return s;
        }
        return h; // fallback to header
      }
    }
    return null;
  }

  function applyColor(el, level) {
    if (!el) return;
    el.classList.remove('array-warning', 'array-critical', 'pool-warning', 'pool-critical', 'warning', 'critical');
    const isArray = el.id === 'array' || el.textContent.toLowerCase().includes('array');
    if (level === 'critical') {
      el.classList.add(isArray ? 'array-critical' : 'pool-critical');
    } else if (level === 'warning') {
      el.classList.add(isArray ? 'array-warning' : 'pool-warning');
    }
  }

  function applyColoring(cfg) {
    if (!cfg) return;

    const doArray = (cfg.array_coloring || 'yes') === 'yes';
    const doPools = (cfg.pool_coloring || 'yes') === 'yes';

    const arrayWarnTB = parseSizeToTB(cfg.array_warning || cfg.array_warning_custom || '8T');
    const arrayCritTB = parseSizeToTB(cfg.array_critical || cfg.array_critical_custom || '2T');

    // Array
    if (doArray) {
      const arrayEl = findElementForArray();
      if (arrayEl) {
        // Try to get the free value from the element or nearby
        let freeTB = getFreeTB(arrayEl.textContent);
        if (freeTB === null) {
          // Search siblings or parent
          const parent = arrayEl.parentElement || document;
          freeTB = getFreeTB(parent.textContent);
        }
        if (freeTB !== null) {
          if (freeTB <= arrayCritTB) {
            applyColor(arrayEl, 'critical');
          } else if (freeTB <= arrayWarnTB) {
            applyColor(arrayEl, 'warning');
          }
        }
      }
    }

    // Pools
    if (doPools) {
      let poolList = [];
      if (cfg.pools_to_color === 'all' || !cfg.pools_to_color) {
        // Will try to color all visible pools that have thresholds
        // For simplicity, scan for common pool headers
      } else {
        poolList = cfg.pools_to_color.split(/[\s,]+/).filter(Boolean);
      }

      // Collect configured pools from cfg keys
      const configuredPools = [];
      for (let key in cfg) {
        if (key.startsWith('pool_') && key.endsWith('_warning')) {
          const pname = key.replace(/^pool_/, '').replace(/_warning$/, '');
          configuredPools.push(pname);
        }
      }

      const toColor = (poolList.length > 0) ? poolList : configuredPools;

      toColor.forEach(pname => {
        const el = findElementForPool(pname);
        if (el) {
          const safe = pname.replace(/[^a-zA-Z0-9_]/g, '_');
          const warnTB = parseSizeToTB(cfg[`pool_${safe}_warning`] || '2T');
          const critTB = parseSizeToTB(cfg[`pool_${safe}_critical`] || '1T');

          let freeTB = getFreeTB(el.textContent);
          if (freeTB === null && el.parentElement) {
            freeTB = getFreeTB(el.parentElement.textContent);
          }
          if (freeTB !== null) {
            if (freeTB <= critTB) {
              applyColor(el, 'critical');
            } else if (freeTB <= warnTB) {
              applyColor(el, 'warning');
            }
          }
        }
      });
    }
  }

  // Make available for debugging
  window.StorageGuardColor = applyColoring;
})();