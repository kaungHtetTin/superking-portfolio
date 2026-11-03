(() => {
  const form = document.getElementById('qr-settings');
  const status = document.getElementById('settings-status');
  const controls = [...form.elements].filter(control => control.name);
  const single = form.dataset.single === 'true';
  const syncCustom = () => form.querySelectorAll('.custom-paper').forEach(label => {
    label.hidden = form.elements.paper.value !== 'custom';
    label.querySelector('input').disabled = label.hidden;
  });
  const restore = settings => {
    controls.forEach(control => {
      if (control.type === 'checkbox') control.checked = settings[control.name];
      else control.value = settings[control.name];
    });
    syncCustom();
  };
  restore(QrPrintSettings.load(single));
  form.addEventListener('change', syncCustom);
  document.getElementById('reset-settings').addEventListener('click', () => {
    restore(QrPrintSettings.defaults(single));
    status.textContent = 'Defaults restored. Save settings to apply them.';
  });
  form.addEventListener('submit', event => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    const settings = Object.fromEntries(controls.map(control => [control.name, control.type === 'checkbox' ? control.checked : control.value]));
    try { QrPrintSettings.save(settings); }
    catch { status.textContent = 'Unable to save settings. Check the values and allow browser storage, then try again.'; return; }
    if (form.dataset.preview === 'true') window.location.assign(form.dataset.returnUrl);
    else status.textContent = 'QR print settings saved in this browser.';
  });
})();
