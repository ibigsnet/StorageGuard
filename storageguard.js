// Storage Guard UI helpers
console.log("Storage Guard loaded - pools + settings active");

function initStorageGuardUI() {
  // Array custom toggle
  const useCustom = document.getElementById('array_use_custom');
  const arrWarnSel = document.getElementById('array_warning');
  const arrCritSel = document.getElementById('array_critical');
  const arrWarnCust = document.getElementById('array_warning_custom');
  const arrCritCust = document.getElementById('array_critical_custom');

  function updateArrayCustom() {
    const isCustom = useCustom && useCustom.value === 'yes';
    if (arrWarnSel) arrWarnSel.disabled = isCustom;
    if (arrCritSel) arrCritSel.disabled = isCustom;
    if (arrWarnCust) arrWarnCust.disabled = !isCustom;
    if (arrCritCust) arrCritCust.disabled = !isCustom;
  }
  if (useCustom) {
    useCustom.addEventListener('change', updateArrayCustom);
    // initial
    setTimeout(updateArrayCustom, 0);
  }

  // Pool "All" checkbox sync
  const poolAll = document.getElementById('pool_all');
  const poolCbs = document.querySelectorAll('.pool-cb');

  function syncPoolAll() {
    if (!poolAll) return;
    const allChecked = Array.from(poolCbs).every(cb => cb.checked);
    const noneChecked = Array.from(poolCbs).every(cb => !cb.checked);
    poolAll.checked = allChecked;
    poolAll.indeterminate = !allChecked && !noneChecked;
  }

  if (poolAll) {
    poolAll.addEventListener('change', function() {
      const on = this.checked;
      poolCbs.forEach(cb => cb.checked = on);
      updatePoolsHidden();
    });
  }
  poolCbs.forEach(cb => {
    cb.addEventListener('change', function() {
      syncPoolAll();
      updatePoolsHidden();
    });
  });
  // initial sync
  setTimeout(syncPoolAll, 10);

  // Alerts "All" sync
  const alertAll = document.getElementById('alert_all');
  const alertCbs = document.querySelectorAll('.alert-cb');

  function syncAlertAll() {
    if (!alertAll) return;
    const allChecked = Array.from(alertCbs).every(cb => cb.checked);
    const noneChecked = Array.from(alertCbs).every(cb => !cb.checked);
    alertAll.checked = allChecked;
    alertAll.indeterminate = !allChecked && !noneChecked;
  }

  if (alertAll) {
    alertAll.addEventListener('change', function() {
      const on = this.checked;
      alertCbs.forEach(cb => cb.checked = on);
      updateAlertsHidden();
    });
  }
  alertCbs.forEach(cb => {
    cb.addEventListener('change', function() {
      syncAlertAll();
      updateAlertsHidden();
    });
  });
  setTimeout(syncAlertAll, 10);

  // Compile hidden fields before submit
  const form = document.getElementById('storageguard-form');
  if (form) {
    form.addEventListener('submit', function() {
      updatePoolsHidden();
      updateAlertsHidden();
    });
  }

  function updatePoolsHidden() {
    const hidden = document.getElementById('pools_to_color');
    if (!hidden) return;
    const checked = [];
    poolCbs.forEach(cb => {
      if (cb.checked) checked.push(cb.value);
    });
    if (checked.length === 0) {
      hidden.value = '';
    } else if (checked.length === poolCbs.length && poolCbs.length > 0) {
      hidden.value = 'all';
    } else {
      hidden.value = checked.join(',');
    }
  }

  function updateAlertsHidden() {
    const hidden = document.getElementById('alerts_for');
    if (!hidden) return;
    const checked = [];
    alertCbs.forEach(cb => {
      if (cb.checked) checked.push(cb.value);
    });
    if (checked.length === 0) {
      hidden.value = '';
    } else if (checked.length === alertCbs.length && alertCbs.length > 0) {
      hidden.value = 'all';
    } else {
      hidden.value = checked.join(',');
    }
  }

  // Optional: grey out pool capacity sections when their checkbox is off (visual only)
  poolCbs.forEach(cb => {
    cb.addEventListener('change', function() {
      const safe = this.name.replace('pool_color_', '');
      const section = document.getElementById('pool-section-' + safe);
      if (section) {
        section.style.opacity = this.checked ? '1' : '0.55';
      }
    });
  });

  // initial opacity
  setTimeout(function() {
    poolCbs.forEach(cb => {
      const safe = cb.name.replace('pool_color_', '');
      const section = document.getElementById('pool-section-' + safe);
      if (section) section.style.opacity = cb.checked ? '1' : '0.55';
    });
  }, 50);

  // Make sure selects are properly enabled/disabled on load for custom
  if (useCustom) updateArrayCustom();
}

// Expose if needed
window.initStorageGuardUI = initStorageGuardUI;
