(() => {
    const root = document.getElementById('distribution-map');
    const status = document.getElementById('distribution-map-status');
    const fitButton = document.getElementById('distribution-fit');
    if (!root) return;
    try {
        const points = JSON.parse(document.getElementById('distribution-points').textContent);
        const map = L.map(root, { preferCanvas: true, scrollWheelZoom: false }).setView([20.8, 96.1], 5);
        const tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a> contributors'
        }).addTo(map);
        let tileFailed = false;
        tiles.on('tileerror', () => {
            if (tileFailed) return;
            tileFailed = true;
            status.textContent = 'Some map tiles could not load. Location markers and the records list are still available.';
        });
        const bounds = [];
        for (const point of points) {
            const latlng = [Number(point.latitude), Number(point.longitude)];
            bounds.push(latlng);
            const text = document.createElement('p');
            text.textContent = `${Number(point.total).toLocaleString()} first reveal(s) here · ${latlng[0].toFixed(6)}, ${latlng[1].toFixed(6)}`;
            L.circleMarker(latlng, { radius: Math.min(18, 6 + Math.log2(Number(point.total))), color:'#9b1722', fillColor:'#ffd130', fillOpacity:0.8, weight:2 }).bindPopup(text).addTo(map);
        }
        const showAll = () => {
            if (bounds.length) map.fitBounds(bounds, { padding:[30,30], maxZoom:13 });
        };
        fitButton.disabled = !bounds.length;
        fitButton.addEventListener('click', showAll);
        showAll();
        if (bounds.length) status.textContent = `${bounds.length.toLocaleString()} distinct locations shown. Select a marker or use Show on map in the records list.`;
        let selected;
        document.querySelectorAll('.distribution-focus').forEach(button => button.addEventListener('click', () => {
            const latlng = [Number(button.dataset.lat), Number(button.dataset.lng)];
            if (selected) map.removeLayer(selected);
            selected = L.circle(latlng, { radius:Number(button.dataset.accuracy), color:'#c1121f', fillOpacity:0.12 }).addTo(map);
            map.setView(latlng, 14);
            root.scrollIntoView({ behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth', block:'center' });
            root.focus({ preventScroll:true });
        }));
    } catch (_) {
        status.textContent = 'The map is unavailable. Use the coordinates in the records list or export CSV.';
        fitButton.disabled = true;
    }
})();
