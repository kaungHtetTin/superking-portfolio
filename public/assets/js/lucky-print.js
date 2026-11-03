(() => {
    const status = document.getElementById('status');
    const button = document.getElementById('print');
    try {
        const labels = [...document.querySelectorAll('.label')];
        for (const label of labels) {
            const qr = qrcodegen.QrCode.encodeText(label.dataset.url, qrcodegen.QrCode.Ecc.MEDIUM);
            const border = 4, size = qr.size + border * 2;
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('viewBox', `0 0 ${size} ${size}`);
            svg.setAttribute('role', 'img');
            svg.setAttribute('aria-label', 'Promotional QR code');
            svg.setAttribute('shape-rendering', 'crispEdges');
            const bg = document.createElementNS(svg.namespaceURI, 'rect');
            bg.setAttribute('width', '100%'); bg.setAttribute('height', '100%'); bg.setAttribute('fill', 'white');
            svg.append(bg);
            let d = '';
            for (let y = 0; y < qr.size; y++) for (let x = 0; x < qr.size; x++) {
                if (qr.getModule(x, y)) d += `M${x + border},${y + border}h1v1h-1z`;
            }
            const path = document.createElementNS(svg.namespaceURI, 'path');
            path.setAttribute('d', d); path.setAttribute('fill', 'black'); svg.append(path);
            label.querySelector('.qr').append(svg);
            const host = new URL(label.dataset.url).hostname;
            if (host === 'localhost' || host === '127.0.0.1' || host === '[::1]' || host.endsWith('.local')) document.getElementById('local-warning').hidden = false;
        }
        status.textContent = labels.length ? `${labels.length} labels ready.` : 'Generate codes before printing.';
        button.disabled = !labels.length;
        button.addEventListener('click', () => window.print());
        if (labels.length && document.body.dataset.autoprint === 'true') {
            // Wait for fonts and the completed QR layout before opening print preview.
            document.fonts.ready.then(() => {
                requestAnimationFrame(() => requestAnimationFrame(() => window.print()));
            }).catch(() => {
                status.textContent = 'Labels are ready. Use Print / Save PDF to continue.';
            });
        }
    } catch (error) {
        status.textContent = 'Could not generate QR labels. Reload this page before printing.';
    }
})();
