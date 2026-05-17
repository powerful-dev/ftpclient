export function initColumnsResize({ panel, panelId }) {

    const resizers = panel.querySelectorAll('.resizer');
    let resizing = false;
    let currentResizer, startX, startWidth, columnElement;

    resizers.forEach(resizer => {
        resizer.addEventListener('mousedown', (e) => {
            currentResizer = resizer;
            columnElement = currentResizer.parentElement;
            startX = e.pageX;
            startWidth = columnElement.offsetWidth;
            resizing = true;

            currentResizer.classList.add('active');
            document.body.classList.add('resizing-active');
            e.preventDefault();
        });
    });

    document.addEventListener('mousemove', (e) => {
        if (!resizing) return;

        const delta = e.pageX - startX;
        const newWidth = Math.max(50, startWidth + delta);
        columnElement.style.width = `${newWidth}px`;

        const columnName = currentResizer.dataset.column;
        panel.querySelectorAll(
            `.file-item .file-item-col:nth-child(${getColumnIndex(columnName)})`
        ).forEach(cell => {
            cell.style.width = `${newWidth}px`;
        });
    });

    document.addEventListener('mouseup', () => {
        if (!resizing) return;

        Livewire.dispatch('setColumnWidth', {
            column: currentResizer.dataset.column,
            width: columnElement.offsetWidth,
            panel: panelId.split('-')[0]
        });

        resizing = false;
        currentResizer.classList.remove('active');
        document.body.classList.remove('resizing-active');
    });
}

function getColumnIndex(columnName) {
    const columns = ['name', 'size', 'modified', 'permissions'];
    return columns.indexOf(columnName) + 1;
}