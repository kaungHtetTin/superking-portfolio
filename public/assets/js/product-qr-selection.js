(() => {
  const form = document.getElementById('product-qr-selection');
  if (!form) return;
  const key = 'sk-product-qr-selection-v1';
  const boxes = [...document.querySelectorAll('.qr-product-select')];
  const selectPage = document.getElementById('select-page-qr');
  const count = document.getElementById('qr-selection-count');
  const print = document.getElementById('print-selected-qr');
  const clear = document.getElementById('clear-qr-selection');
  let selected = new Set();
  const load = () => {
    try {
      const saved = JSON.parse(sessionStorage.getItem(key));
      if (Array.isArray(saved)) selected = new Set(saved.filter(id => typeof id === 'string' && /^[1-9][0-9]{0,9}$/.test(id)).slice(0, 200));
    } catch {}
  };
  const sync = (limit = false) => {
    boxes.forEach(box => box.checked = selected.has(box.value));
    const checked = boxes.filter(box => box.checked).length;
    selectPage.checked = boxes.length > 0 && checked === boxes.length;
    selectPage.indeterminate = checked > 0 && checked < boxes.length;
    count.textContent = `${selected.size} product${selected.size === 1 ? '' : 's'} selected${limit ? ' (200 product limit reached)' : ''}`;
    document.getElementById('qr-selected-ids').value = [...selected].join(',');
    print.disabled = clear.disabled = selected.size === 0;
    try { sessionStorage.setItem(key, JSON.stringify([...selected])); } catch {}
  };
  boxes.forEach(box => box.addEventListener('change', () => {
    const limit = box.checked && !selected.has(box.value) && selected.size >= 200;
    if (box.checked && !limit) selected.add(box.value);
    else if (!box.checked) selected.delete(box.value);
    sync(limit);
  }));
  selectPage.addEventListener('change', () => {
    let limit = false;
    boxes.forEach(box => {
      if (!selectPage.checked) selected.delete(box.value);
      else if (selected.size < 200 || selected.has(box.value)) selected.add(box.value);
      else limit = true;
    });
    sync(limit);
  });
  clear.addEventListener('click', () => { selected.clear(); sync(); });
  form.addEventListener('submit', event => { if (!selected.size) event.preventDefault(); });
  window.addEventListener('pageshow', () => { load(); sync(); });
  load();
  sync();
})();
