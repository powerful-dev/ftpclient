import { getItems, clearSelection, selectRange } from './helpers';

export function handleSelection({ panel, item, ctrlKey, shiftKey, state }) {

    const items = getItems(panel);
    const index = items.indexOf(item);
    if (index === -1) return;

    if (shiftKey && state.lastClicked !== null) {
        const lastIndex = items.findIndex(
            i => i.dataset.name === state.lastClicked
        );

        if (lastIndex !== -1) {
            selectRange(
                items,
                Math.min(lastIndex, index),
                Math.max(lastIndex, index)
            );
        }

    } else if (ctrlKey) {
        // toggle selection
        item.classList.toggle('selected');

    } else {
        clearSelection(panel);
        item.classList.add('selected');
    }

    state.lastClicked = item.dataset.name;
}