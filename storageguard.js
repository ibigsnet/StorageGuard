// Storage Guard settings UI
function initStorageGuardUI() {
  const useCustom = document.getElementById('array_use_custom');
  const diskSelects = document.getElementById('array-disk-selects');
  const customFields = document.getElementById('array-custom-fields');
  const arrWarnSel = document.getElementById('array_warning');
  const arrCritSel = document.getElementById('array_critical');

  function updateArrayCustom() {
    const isCustom = useCustom && useCustom.value === 'yes';
    if (diskSelects) diskSelects.style.display = isCustom ? 'none' : '';
    if (customFields) customFields.style.display = isCustom ? '' : 'none';
    // Keep selects enabled for form post when not custom; when custom they are hidden
    if (arrWarnSel) arrWarnSel.disabled = isCustom;
    if (arrCritSel) arrCritSel.disabled = isCustom;
  }
  if (useCustom) {
    useCustom.addEventListener('change', updateArrayCustom);
    updateArrayCustom();
  }

  // Pool "All" checkbox sync
  const poolAll = document.getElementById('pool_all');
  const poolCbs = document.querySelectorAll('.pool-cb');

  function syncPoolAll() {
    if (!poolAll) return;
    const list = Array.from(poolCbs);
    if (!list.length) return;
    const allChecked = list.every(cb => cb.checked);
    const noneChecked = list.every(cb => !cb.checked);
    poolAll.checked = allChecked;
    poolAll.indeterminate = !allChecked && !noneChecked;
  }

  if (poolAll) {
    poolAll.addEventListener('change', function() {
      const on = this.checked;
      poolCbs.forEach(cb => { cb.checked = on; });
      updatePoolsHidden();
      updatePoolSections();
    });
  }
  poolCbs.forEach(cb => {
    cb.addEventListener('change', function() {
      syncPoolAll();
      updatePoolsHidden();
      updatePoolSections();
    });
  });
  syncPoolAll();

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

  function updatePoolSections() {
    poolCbs.forEach(cb => {
      const safe = cb.name.replace('pool_color_', '');
      const section = document.getElementById('pool-section-' + safe);
      if (section) section.style.opacity = cb.checked ? '1' : '0.55';
    });
  }
  updatePoolSections();

  // Checkbox + hidden "no": ensure only one value is intended on submit
  // (browser sends both; PHP update.php typically keeps last — checkbox after hidden = yes when checked)
  const form = document.getElementById('storageguard-form');
  if (form) {
    form.addEventListener('submit', function() {
      updatePoolsHidden();
      // Disable unchecked severity checkboxes so only hidden "no" is posted
      form.querySelectorAll('input.alert-sev[type="checkbox"]').forEach(cb => {
        if (!cb.checked) cb.disabled = true;
      });
    });
  }
}

window.initStorageGuardUI = initStorageGuardUI;
