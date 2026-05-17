import { clearSelection, getCheckbox } from './helpers';
import { getPanelPath } from './navigation'; // или вынеси отдельно, если хочешь

export function initDragDrop({ panel, panelId, state }) {

    /* ==========================
     * DRAG START
     * ========================== */

    panel.addEventListener('dragstart', (e) => {

        let element = e.target;
        if (element.nodeType !== Node.ELEMENT_NODE) {
            element = element.parentElement;
        }

        const item = element?.closest('.file-item');
        if (!item) return;

        state.startItem = item;
        state.startPanelId = panelId;

        const img = document.createElement('img');
        img.src =
            'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/B8AAusB9YzfoVcAAAAASUVORK5CYII=';
        e.dataTransfer.setDragImage(img, 0, 0);

           if (!item.classList.contains('selected')) {
            clearSelection(panel);

            item.classList.add('selected');
        }

        const selectedItems = panel.querySelectorAll('.file-item.selected.actions');

        const payload = Array.from(selectedItems)
            .map(fileItem => ({
                name: fileItem.dataset.name,
                path: fileItem.dataset.path,
                type: fileItem.classList.contains('folder') ? 'dir' : 'file',
            }));

        if (!payload.length) return;

        e.dataTransfer.setData('application/json', JSON.stringify(payload));
        e.dataTransfer.setData('sourcePanel', panelId);
    }, true);

    /* ==========================
     * DRAG OVER
     * ========================== */

    panel.addEventListener('dragover', (e) => {
        e.preventDefault();

        if (state.startPanelId === panelId) {
            const targetFolder = e.target.closest('.file-item.folder');
            if (targetFolder) {
                targetFolder.classList.add('drag-over-folder');
                e.dataTransfer.dropEffect = 'move';
            } else {
                panel.querySelectorAll('.file-item.folder')
                    .forEach(f => f.classList.remove('drag-over-folder'));
                e.dataTransfer.dropEffect = 'copy';
            }
        } else {
            panel.classList.add('drag-over');
        }
    });

    /* ==========================
     * DRAG LEAVE
     * ========================== */

    panel.addEventListener('dragleave', (e) => {
        const targetFolder = e.target.closest('.file-item.folder');
        if (targetFolder) {
            targetFolder.classList.remove('drag-over-folder');
        }

        if (!panel.contains(e.relatedTarget)) {
            panel.classList.remove('drag-over');
        }
    });

    /* ==========================
     * DROP
     * ========================== */

    panel.addEventListener('drop', (e) => {
        e.preventDefault();

        panel.classList.remove('drag-over');
        panel.querySelectorAll('.file-item.folder, .file-item.go-up')
            .forEach(f => f.classList.remove('drag-over-folder'));

        const raw = e.dataTransfer.getData('application/json');
        const sourcePanelId = e.dataTransfer.getData('sourcePanel');

        if (!raw || !sourcePanelId) return;

        let items;
        try {
            items = JSON.parse(raw);
        } catch {
            return;
        }

        if (!Array.isArray(items) || !items.length) return;

        const targetItem = e.target.closest('.file-item');

        let targetPath = null;

        if (targetItem) {
            if (targetItem.classList.contains('go-up')) {
                targetPath = targetItem.dataset.path;
            } else if (targetItem.classList.contains('folder')) {
                targetPath = targetItem.dataset.path;
            }
        }


        // fallback — текущая директория панели
        if (!targetPath || targetPath === '\\') {
            targetPath = getPanelPath(panelId);
        }

        if (!targetPath) return;

        const sourcePanel = sourcePanelId.split('-')[0];
        const targetPanel = panelId.split('-')[0];
        const sourcePath = getPanelPath(sourcePanelId);

        if (
            sourcePanel === targetPanel &&
            items.every(i => {
                const parent = i.path.split('/').slice(0, -1).join('/') || '/';
                return parent === targetPath;
            })
        ) {
            return;
        }

        let type = 'copy';

        if (sourcePanel === targetPanel) {
            type = 'move';
        }

        const payload = {
            type,
            items,
            sourcePanel,
            targetPanel,
            sourcePath, 
            targetPath,
        };

        // console.log('DROP PAYLOAD:', payload);

        Livewire.dispatchTo('file-explorer', 'handleAction', {payload: payload});
    });
}