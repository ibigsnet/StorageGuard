// Storage Guard settings UI — section show/hide + pool checkboxes
function initStorageGuardUI() {
  function setVisible(el, on) {
    if (!el) return;
    el.style.display = on ? '' : 'none';
  }

  // Array: custom vs disk sizes
  const useCustom = document.getElementById('array_use_custom');
  const diskSelects = document.getElementById('array-disk-selects');
  const customFields = document.getElementById('array-custom-fields');
  const arrWarnSel = document.getElementById('array_warning');
  const arrCritSel = document.getElementById('array_critical');

  function updateArrayCustom() {
    const isCustom = useCustom && useCustom.value === 'yes';
    setVisible(diskSelects, !isCustom);
    setVisible(customFields, isCustom);
    if (arrWarnSel) arrWarnSel.disabled = !!isCustom;
    if (arrCritSel) arrCritSel.disabled = !!isCustom;
  }
  if (useCustom) {
    useCustom.addEventListener('change', updateArrayCustom);
    updateArrayCustom();
  }

  // Coloring Yes/No → hide details for that block
  function wireSectionToggle(selectId) {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    const sectionId = sel.getAttribute('data-sg-section');
    const section = sectionId ? document.getElementById(sectionId) : null;
    function apply() {
      const on = sel.value === 'yes';
      setVisible(section, on);
      if (on && selectId === 'array_coloring') updateArrayCustom();
    }
    sel.addEventListener('change', apply);
    apply();
  }
  wireSectionToggle('array_coloring');
  wireSectionToggle('pool_coloring');

  // Pool "All" checkbox sync
  const poolAll = document.getElementById('pool_all');
  const poolCbs = document.querySelectorAll('.pool-cb');

  function syncPoolAll() {
    if (!poolAll || !poolCbs.length) return;
    const list = Array.from(poolCbs);
    const allChecked = list.every(cb => cb.checked);
    const noneChecked = list.every(cb => !cb.checked);
    poolAll.checked = allChecked;
    poolAll.indeterminate = !allChecked && !noneChecked;
  }

  function updatePoolsHidden() {
    const hidden = document.getElementById('pools_to_color');
    if (!hidden) return;
    const checked = [];
    poolCbs.forEach(cb => { if (cb.checked) checked.push(cb.value); });
    if (checked.length === 0) hidden.value = '';
    else if (checked.length === poolCbs.length) hidden.value = 'all';
    else hidden.value = checked.join(',');
  }

  if (poolAll) {
    poolAll.addEventListener('change', function () {
      poolCbs.forEach(cb => { cb.checked = poolAll.checked; });
      updatePoolsHidden();
    });
  }
  poolCbs.forEach(cb => {
    cb.addEventListener('change', function () {
      syncPoolAll();
      updatePoolsHidden();
    });
  });
  syncPoolAll();
  updatePoolsHidden();

  const form = document.getElementById('storageguard-form');
  if (form) {
    form.addEventListener('submit', function () {
      updatePoolsHidden();
      // Re-enable fields so hidden section values still POST
      form.querySelectorAll('select:disabled, input:disabled').forEach(el => {
        if (el.closest('.sg-details') || el.id === 'array_warning' || el.id === 'array_critical') {
          el.disabled = false;
        }
      });
    });
  }
}

window.initStorageGuardUI = initStorageGuardUI;
