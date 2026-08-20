import { computed, ref } from 'vue';

/**
 * Page-scoped bulk-select: an explicit array of selected ids, a select-all-visible
 * toggle, and a clear action. Extracted from the checkbox-array pattern hand-rolled
 * independently across Results.vue, Registrations.vue, Phases.vue and others — same
 * shape, one implementation.
 *
 * `items` may be a plain array/ref of rows, or a getter function returning the current
 * visible rows (use a function when the row list changes, e.g. after a filter or an
 * Inertia partial reload, so "select all" always reflects what's on screen right now).
 *
 * @param {Array|import('vue').Ref<Array>|() => Array} items
 * @param {string} idKey
 */
export function useBulkSelection(items, idKey = 'id') {
    const selectedIds = ref([]);

    function visibleItems() {
        const source = typeof items === 'function' ? items() : (items?.value ?? items ?? []);
        return Array.isArray(source) ? source : [];
    }

    function visibleIds() {
        return visibleItems().map((item) => item[idKey]);
    }

    const isSelected = (id) => selectedIds.value.includes(id);

    function toggle(id) {
        const idx = selectedIds.value.indexOf(id);
        if (idx === -1) selectedIds.value.push(id);
        else selectedIds.value.splice(idx, 1);
    }

    const allSelected = computed(() => {
        const ids = visibleIds();
        return ids.length > 0 && ids.every((id) => selectedIds.value.includes(id));
    });

    function toggleSelectAll() {
        selectedIds.value = allSelected.value ? [] : visibleIds();
    }

    function clear() {
        selectedIds.value = [];
    }

    return { selectedIds, isSelected, toggle, allSelected, toggleSelectAll, clear };
}
