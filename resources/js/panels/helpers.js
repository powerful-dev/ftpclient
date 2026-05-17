export function getItems(panel) {
    return Array.from(
        panel.querySelectorAll('.file-item:not(.go-up)')
    );
}

export function clearSelection(panel) {
    panel.querySelectorAll('.file-item.selected')
        .forEach(el => el.classList.remove('selected'));
}

export function selectRange(items, start, end) {
    for (let i = start; i <= end; i++) {
        items[i].classList.add('selected');
    }
}

export function getSelectedCount(panel) {
    return panel.querySelectorAll('.file-checkbox:checked').length;
}

/**
 * Clear selection in a single panel and sync with Livewire
 */
export function clearPanel(panel) {
    if (!panel) return;

    clearSelection(panel);
}

/**
 * Clear selection in the opposite panel
 */
export function clearOtherPanelSelection(currentPanelId) {
    const panels = ['left-panel', 'right-panel'];

    panels.forEach(id => {
        if (id === currentPanelId) return;

        const panel = document.getElementById(id);
        if (!panel) return;

        clearPanel(panel);
    });
}
