export function getPanelPath(panelId) {
    return document.getElementById(panelId)?.dataset.path || '/';
}