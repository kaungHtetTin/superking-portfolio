(() => {
    const rows = document.getElementById('lucky-prizes');
    if (!rows) return;
    const update = () => {
        const items = rows.querySelectorAll('.lucky-prize-row');
        items.forEach(row => { row.querySelector('button').disabled = items.length === 1; });
        document.getElementById('lucky-add').disabled = items.length >= 30;
    };
    document.getElementById('lucky-add').addEventListener('click', () => {
        if (rows.children.length >= 30) return;
        rows.append(document.getElementById('lucky-prize-template').content.cloneNode(true));
        rows.lastElementChild.querySelector('input').focus();
        update();
    });
    rows.addEventListener('click', event => {
        if (event.target.matches('.lucky-remove') && rows.children.length > 1) {
            event.target.closest('.lucky-prize-row').remove();
            update();
        }
    });
    update();
})();
