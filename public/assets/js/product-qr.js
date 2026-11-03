(() => {
  const preview = document.getElementById('qr-preview');
  const printButton = document.getElementById('print-qr');
  const status = document.getElementById('qr-status');
  const sources = document.getElementById('qr-products');
  if (sources.dataset.batch === 'true') {
    try { sessionStorage.setItem('sk-product-qr-selection-v1', JSON.stringify([...sources.children].map(node => node.dataset.id))); } catch {}
  }
  let products = [], generationError = false;
  const element = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  };
  const fail = message => {
    printButton.disabled = true;
    document.body.dataset.printReady = 'false';
    status.dataset.error = 'true';
    status.textContent = message;
  };
  const makeSvg = destination => {
    const qr = qrcodegen.QrCode.encodeText(destination, qrcodegen.QrCode.Ecc.MEDIUM);
    const border = 4, size = qr.size + border * 2;
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', `0 0 ${size} ${size}`);
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', 'QR code linking to the product detail page');
    svg.setAttribute('shape-rendering', 'crispEdges');
    const background = document.createElementNS(ns, 'rect');
    background.setAttribute('width', '100%');
    background.setAttribute('height', '100%');
    background.setAttribute('fill', '#fff');
    svg.append(background);
    const path = document.createElementNS(ns, 'path'), modules = [];
    for (let y = 0; y < qr.size; y++) {
      for (let x = 0; x < qr.size; x++) {
        if (qr.getModule(x, y)) modules.push(`M${x + border},${y + border}h1v1h-1z`);
      }
    }
    path.setAttribute('d', modules.join(''));
    path.setAttribute('fill', '#000');
    svg.append(path);
    return svg;
  };
  const makeLabel = (product, settings, first) => {
    const label = element('article', 'product-label');
    label.dataset.productId = product.id;
    label.setAttribute('aria-label', `QR label for ${product.name}`);
    const content = element('div', 'label-content');
    if (settings.showBrand) content.append(element('p', 'brand', sources.dataset.brand));
    if (settings.showName) content.append(element('h2', '', product.name));
    if (settings.showCode && product.code) content.append(element('p', 'product-code', product.code));
    const qr = element('div', 'qr-code');
    if (first) qr.id = 'product-qr';
    qr.append(product.svg.cloneNode(true));
    content.append(qr);
    if (settings.caption) content.append(element('p', 'scan-caption', settings.caption));
    if (settings.showUrl) {
      const link = element('a', 'product-url', product.url);
      if (first) link.id = 'product-url';
      link.href = product.url;
      content.append(link);
    }
    label.append(content);
    return label;
  };
  const render = () => {
    printButton.disabled = true;
    document.body.dataset.printReady = 'false';
    if (generationError) return fail('Unable to generate the QR code. Check the website address and reload this page.');
    const settings = QrPrintSettings.load(sources.dataset.batch !== 'true' && sources.children.length === 1);
    const qrSize = Number(settings.qrSize), perPage = Number(settings.perPage), copies = Number(settings.copies);
    const total = products.length * copies;
    if (total > 1000) {
      preview.replaceChildren();
      return fail('A print batch can contain up to 1,000 labels. Reduce the copies per product.');
    }
    let [width, height] = {a4: [210, 297], a5: [148, 210], letter: [215.9, 279.4], custom: [Number(settings.pageWidth), Number(settings.pageHeight)]}[settings.paper];
    if (settings.orientation === 'landscape') [width, height] = [height, width];
    const margin = Number(settings.margin), availableWidth = width - margin * 2, availableHeight = height - margin * 2;
    preview.style.setProperty('--page-width', `${width}mm`);
    preview.style.setProperty('--page-height', `${height}mm`);
    preview.style.setProperty('--page-margin', `${margin}mm`);
    preview.style.setProperty('--qr-size', `${qrSize}mm`);
    preview.style.setProperty('--label-font', `${settings.fontSize}pt`);
    document.getElementById('qr-page-style').textContent = `@page{size:${width}mm ${height}mm;margin:0}`;
    preview.classList.toggle('with-border', settings.showBorder);
    const candidates = [];
    for (let columns = 1; columns <= perPage; columns++) {
      const rows = Math.ceil(perPage / columns);
      const cellWidth = (availableWidth - (columns - 1) * 4) / columns;
      const cellHeight = (availableHeight - (rows - 1) * 4) / rows;
      if (cellWidth >= qrSize + 6.4 && cellHeight >= qrSize + 8.4) candidates.push({columns, rows, score: Math.abs(Math.log(cellWidth / cellHeight))});
    }
    candidates.sort((a, b) => a.score - b.score);
    if (!candidates.length) {
      preview.replaceChildren();
      return fail('This layout does not fit. Reduce QR size or QR codes per page, or choose a larger page.');
    }
    const fragment = document.createDocumentFragment(), scroll = element('div', 'sheet-scroll');
    let grid, labelIndex = 0;
    products.forEach(product => {
      for (let copy = 0; copy < copies; copy++) {
        if (labelIndex % perPage === 0) {
          const sheet = element('section', 'print-sheet');
          sheet.setAttribute('aria-label', `Print page ${Math.floor(labelIndex / perPage) + 1}`);
          grid = element('div', 'sheet-grid');
          sheet.append(grid);
          scroll.append(sheet);
        }
        grid.append(makeLabel(product, settings, labelIndex === 0));
        labelIndex++;
      }
    });
    const pageCount = Math.ceil(total / perPage);
    fragment.append(element('p', 'preview-caption', `${total} labels on ${pageCount} page(s). Preview uses the selected physical paper size; scroll sideways on smaller screens.`), scroll);
    preview.replaceChildren(fragment);
    const labels = [...preview.querySelectorAll('.product-label')];
    let chosen;
    for (const candidate of candidates) {
      preview.style.setProperty('--columns', candidate.columns);
      preview.style.setProperty('--rows', candidate.rows);
      // Check real text wrapping as well as the QR dimensions. Never shrink the requested QR size.
      const fits = labels.every(label => {
        const bounds = label.getBoundingClientRect(), content = label.firstElementChild.getBoundingClientRect();
        const qr = label.querySelector('.qr-code').getBoundingClientRect();
        const padding = 6.4 * 96 / 25.4;
        return content.height <= bounds.height - padding - 1 && qr.width <= bounds.width - padding + .5 && qr.height <= bounds.height - padding - 1;
      });
      if (fits) { chosen = candidate; break; }
    }
    if (!chosen) return fail('Label text does not fit this layout. Reduce the QR size, font size or codes per page, shorten the caption, or hide some label fields.');
    status.dataset.error = 'false';
    status.textContent = `${products.length} product(s), ${total} labels, ${pageCount} page(s). ${chosen.columns} column(s) by ${chosen.rows} row(s); QR size ${qrSize} mm. Ready to print.`;
    printButton.disabled = false;
    document.body.dataset.printReady = 'true';
  };
  try {
    products = [...sources.children].map(node => {
      const destination = new URL(node.dataset.url, window.location.origin);
      if (!['http:', 'https:'].includes(destination.protocol)) throw new Error('Invalid website URL');
      return {...node.dataset, url: destination.href, svg: makeSvg(destination.href)};
    });
    document.getElementById('local-url-notice').hidden = !products.some(product => ['localhost', '127.0.0.1', '[::1]'].includes(new URL(product.url).hostname));
  } catch { generationError = true; }
  window.addEventListener('pageshow', event => { if (event.persisted) render(); });
  window.addEventListener('storage', event => { if (event.key === QrPrintSettings.key || event.key === null) render(); });
  printButton.addEventListener('click', () => { render(); if (!printButton.disabled) window.print(); });
  render();
})();
