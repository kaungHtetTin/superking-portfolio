(() => {
  const key = 'sk-qr-print-settings-v1';
  const defaults = single => ({qrSize: '35', perPage: single ? '1' : '6', copies: '1', paper: 'a4', orientation: 'portrait', margin: '10', pageWidth: '100', pageHeight: '150', fontSize: '10', caption: 'Scan for product details', showBrand: true, showName: true, showCode: true, showUrl: true, showBorder: false});
  const valid = settings => {
    const ranges = {qrSize: [15, 100, 1], perPage: [1, 100, 1], copies: [1, 100, 1], margin: [5, 30, 1], pageWidth: [50, 500, 10], pageHeight: [50, 500, 10], fontSize: [8, 16, 1]};
    return Object.entries(ranges).every(([key, [min, max, step]]) => {
      if (settings.paper !== 'custom' && ['pageWidth', 'pageHeight'].includes(key)) return true;
      const value = Number(settings[key]);
      return settings[key] !== '' && Number.isFinite(value) && value >= min && value <= max && Math.abs(value * step - Math.round(value * step)) < .00001;
    }) && ['a4', 'a5', 'letter', 'custom'].includes(settings.paper)
      && ['portrait', 'landscape'].includes(settings.orientation)
      && typeof settings.caption === 'string' && settings.caption.length <= 100
      && ['showBrand', 'showName', 'showCode', 'showUrl', 'showBorder'].every(key => typeof settings[key] === 'boolean');
  };
  const load = single => {
    const fallback = defaults(single);
    try {
      const saved = JSON.parse(localStorage.getItem(key));
      if (saved && typeof saved === 'object' && !Array.isArray(saved)) {
        const settings = Object.fromEntries(Object.entries(fallback).map(([name, value]) => [name, Object.hasOwn(saved, name) ? saved[name] : value]));
        if (valid(settings)) return settings;
      }
    } catch {}
    return fallback;
  };
  window.QrPrintSettings = {key, defaults, load, save(settings) {
    if (!valid(settings)) throw new Error('Invalid QR print settings');
    localStorage.setItem(key, JSON.stringify(settings));
  }};
})();
