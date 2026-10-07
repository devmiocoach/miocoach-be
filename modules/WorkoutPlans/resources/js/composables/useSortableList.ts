import Sortable from 'sortablejs';
import { onBeforeUnmount, watch, type Ref } from 'vue';

type SortableOptions = {
    /** Lists sharing a group name can exchange items. */
    group: string;
    /** Source lists clone on drag out instead of losing the item. */
    clone?: boolean;
    /** CSS selector for the drag handle. Omit to drag by the whole element. */
    handle?: string;
    /**
     * An item was dropped into this list from another one.
     * `node` is the DOM element SortableJS inserted — read a data attribute
     * off it to identify what was dragged, rather than reaching into the
     * source list's state.
     */
    onAdd?: (index: number, node: HTMLElement) => void;
    /** Called when an item is reordered within this list. */
    onReorder?: (from: number, to: number) => void;
    /** Called when an item is dragged out of this list into another. */
    onRemove?: (index: number) => void;
};

/**
 * Bridge SortableJS to Vue-owned state.
 *
 * SortableJS moves DOM nodes directly. Vue then re-renders from its own state,
 * finds nodes it did not put there, and the card duplicates or vanishes. The
 * fix is to undo every DOM mutation SortableJS makes, then report the gesture
 * to the caller, who mutates the state Vue renders from.
 *
 * This composable never touches an array. One gesture, one mutation, in the
 * caller.
 */
export function useSortableList(
    el: Ref<HTMLElement | null>,
    options: SortableOptions,
): void {
    let sortable: Sortable | null = null;

    // Watch the ref rather than binding onMounted: the day list lives inside a
    // v-if and is destroyed and recreated whenever the coach switches tabs.
    watch(
        el,
        (element) => {
            sortable?.destroy();
            sortable = null;

            if (!element) {
                return;
            }

            sortable = Sortable.create(element, {
                group: options.clone
                    ? { name: options.group, pull: 'clone', put: false }
                    : { name: options.group, pull: true, put: true },
                handle: options.handle,
                animation: 150,
                ghostClass: 'opacity-40',

                onEnd(event) {
                    if (event.from !== event.to) {
                        return; // handled by onAdd / onRemove
                    }

                    const { oldIndex, newIndex } = event;

                    if (
                        oldIndex === undefined ||
                        newIndex === undefined ||
                        oldIndex === newIndex
                    ) {
                        return;
                    }

                    // Undo SortableJS's DOM move; Vue's state is the source of truth.
                    const parent = event.from;
                    const moved = parent.children[newIndex];
                    parent.removeChild(moved);
                    parent.insertBefore(
                        moved,
                        parent.children[oldIndex] ?? null,
                    );

                    options.onReorder?.(oldIndex, newIndex);
                },

                onAdd(event) {
                    const { newIndex, item } = event;

                    if (newIndex === undefined) {
                        return;
                    }

                    // Remove the node SortableJS inserted (a clone, or the original)
                    // BEFORE mutating the array, or Vue renders a duplicate.
                    item.remove();

                    options.onAdd?.(newIndex, item);
                },

                onRemove(event) {
                    if (options.clone) {
                        return; // the source list keeps its item
                    }

                    const { oldIndex } = event;

                    if (oldIndex === undefined) {
                        return;
                    }

                    options.onRemove?.(oldIndex);
                },
            });
        },
        { immediate: true, flush: 'post' },
    );

    onBeforeUnmount(() => {
        sortable?.destroy();
        sortable = null;
    });
}
