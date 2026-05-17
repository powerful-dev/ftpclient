import '../scss/app.scss';

import { createPanelState } from './panels/state';
import { handleSelection } from './panels/selection';
import { initContextMenu } from './panels/contextMenu';
import { initColumnsResize } from './panels/columnsResize';
import { initDragDrop } from './panels/dragDrop';
import { getPanelPath } from './panels/navigation';
import { clearSelection, clearOtherPanelSelection } from './panels/helpers';

window.__activePanelId = null;

document.addEventListener('DOMContentLoaded', () => {

    const panels = ['left-panel', 'right-panel'];

    panels.forEach(panelId => {

        const panel = document.getElementById(panelId);
        if (!panel) return;

        const panelName = panelId.split('-')[0];
        const state = createPanelState();

        /* ==========================
         * INIT MODULES
         * ========================== */

        initColumnsResize({ panel, panelId });

        initContextMenu({
            panel,
            panelName
        });

        initDragDrop({
            panel,
            panelId,
            state
        });

        /* ==========================
         * CLICK SELECTION
         * ========================== */

        panel.addEventListener('click', (e) => {

            window.__activePanelId = panelId; 

            const item = e.target.closest('.file-item');

            if (!item) {
                clearSelection(panel);
                clearOtherPanelSelection(panel.id);
                return;
            }

            handleSelection({
                panel,
                item,
                ctrlKey: e.ctrlKey,
                shiftKey: e.shiftKey,
                state
            });
        });

        /* ==========================
         * DOUBLE CLICK (NAVIGATION)
         * ========================== */

        panel.addEventListener('dblclick', (e) => {
            const item = e.target.closest('.file-item');
            if (!item) return;

            e.preventDefault();  

            if (item.classList.contains('go-up')) {

                Livewire.dispatchTo('file-explorer', 'changeDirectory', {
                    path: item.dataset.path,
                    panel: panelName
                });

                return;
            }

            if (item.classList.contains('file')) {
                item.classList.add('opening');

                Livewire.dispatchTo('file-explorer', 'openFile', {
                    openInExplorer: false
                });

                const off = Livewire.on('file-opened', ({ path }) => {

                    item.classList.remove('opening');
                    off();
                });
                return;
            }

            if (!item.classList.contains('folder')) return;
            if (state.isNavigating) return;

            state.isNavigating = true;
            item.classList.add('navigating');

            Livewire.dispatchTo('file-explorer', 'changeDirectory', {
                path: item.dataset.path,
                panel: panelName
            });

            const off = Livewire.on('directory-changed', () => {
                state.isNavigating = false;
                item.classList.remove('navigating');
                off();
            });
        });

    });

    /* ==========================
     * GLOBAL: CLOSE CONTEXT MENUS
     * ========================== */

    document.addEventListener('click', (e) => {
        document.querySelectorAll('.context-menu').forEach(menu => {
            if (!menu.contains(e.target)) {
                menu.style.display = 'none';
            }
        });
    });
});

document.addEventListener('livewire:init', () => {

    Livewire.on('start-elevated-action', ({ payload }) => {
        setTimeout(() => {

            Livewire.dispatch('executeElevatedAction', {
                payload: payload
            });
        }, 50);
    });
});

window.ActionHelper = {
    fileAction(component, params = {}) {

        const panelId = window.__activePanelId;
        if (!panelId) return;

        const panel = document.getElementById(panelId);
        if (!panel) return;

        const type = params.type;
        if (!type) return;

        const items = Array.from(
            panel.querySelectorAll('.file-item.selected.actions')
        ).map(fileItem => ({
            name: fileItem.dataset.name,
            path: fileItem.dataset.path,
            type: fileItem.classList.contains('folder') ? 'dir' : 'file',
        }));

        const sourcePanelId = panelId;
        const sourcePanel = sourcePanelId.split('-')[0];

        const targetPanel = sourcePanel === 'left' ? 'right' : 'left';
        const targetPanelId = targetPanel + '-panel';

        const payload = {
            type,
            items,
            sourcePanel,
            targetPanel,
            targetPath: null,
            sourcePath: getPanelPath(sourcePanelId),
            targetBasePath: getPanelPath(targetPanelId),
        };

        Livewire.dispatchTo(component, 'handleAction', {payload: payload});
    },

    open(component, params = {}) {

        const openInExplorer = params.openInExplorer ?? false;

        const panelId = window.__activePanelId;
        if (!panelId) return;

        const panel = document.getElementById(panelId);
        if (!panel) return;

        const items = Array.from(
            panel.querySelectorAll('.file-item.selected.actions')
        ).map(fileItem => ({
            name: fileItem.dataset.name,
            path: fileItem.dataset.path,
            type: fileItem.classList.contains('folder') ? 'dir' : 'file',
        }));

        if (items.length !== 1) return;

        const sourcePanel = panelId.split('-')[0];

        const payload = {
            type: 'open',
            items,
            sourcePanel,
            openInExplorer,
        };

        Livewire.dispatchTo(component, 'handleAction', {payload: payload});
    },

    changeDirectory(component, params = {}) {
        Livewire.dispatchTo(component, 'changeDirectory', {
            path: params.path,
            panel: params.panel
        });
    }
};

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
}
setInterval(() => {
    fetch("/ping", {
        method: "POST",
        headers: {
            "X-CSRF-TOKEN": csrfToken(),
            "Accept": "application/json"
        },
    }).then(r => {
        if (!r.ok) {
            console.error("Ping failed:", r.status);
        }
    }).catch(err => console.error("Ping error:", err));
}, 10 * 60 * 100); // each 10 min
// document.addEventListener('showRightPanelLoading', function (event) {
//     const loader = document.querySelector('.con-loading');
//     loader.classList.remove('d-none');
// });
// document.addEventListener('hideRightPanelLoading', function (event) {
//     const loader = document.querySelector('.con-loading');
//     loader.classList.add('d-none');
// });
document.addEventListener('livewire:init', () => {
    Livewire.on('focus-input', () => {
        setTimeout(() => {
            document.getElementById('focus-input')?.focus();
        }, 50);
    });
});