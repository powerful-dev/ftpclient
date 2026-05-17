import { clearSelection, clearOtherPanelSelection } from './helpers';
import { getPanelPath } from './navigation';

export function closeAllContextMenus() {
    document.querySelectorAll('.context-menu').forEach(menu => {
        menu.style.display = 'none';
        menu.classList.remove('open-up');
    });
}

function calculateMenuY(e, menuHeight = 220) {
    if (e.clientY + menuHeight > window.innerHeight) {
        return e.pageY - menuHeight;
    }
    return e.pageY;
}

export function initContextMenu({ panel, panelName }) {
    
    panel.addEventListener('contextmenu', (e) => {
        const content = e.target.closest('.file-list-content');
        if (!content) return;

        window.__activePanelId = panel.id; 

        e.preventDefault();

        const item = e.target.closest('.file-item');

        let files = [];

        if (!item) {
            clearSelection(panel);
            clearOtherPanelSelection(panel.id);
        } else {
            const isSelected = item.classList.contains('selected');

            if (!isSelected) {
                clearSelection(panel);
                clearOtherPanelSelection(panel.id);
                item.classList.add('selected');
            }

            files = Array.from(
                panel.querySelectorAll('.file-item.selected')
            ).map(el => ({
                path: el.dataset.path,
                name: el.dataset.name,
                type: el.dataset.type
            }));
        }

        const y = calculateMenuY(e);



        Livewire.dispatchTo('file-explorer', 'openContextMenu', {
            sourcePath: getPanelPath(panel.id),
            panel: panelName,
            files,
            x: e.pageX,
            y
        });
    });
}
