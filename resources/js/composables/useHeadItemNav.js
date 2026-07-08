import { computed } from 'vue';

/**
 * Client-side helpers for head → item navigation and filtering.
 */
export function useHeadItemNav(propsOrGroups, options = {}) {
    const groups = computed(() => {
        const g = propsOrGroups.headItemGroups ?? propsOrGroups.groups ?? propsOrGroups;
        return Array.isArray(g?.value ?? g) ? (g?.value ?? g) : [];
    });

    const selectedHeadId = computed(() => {
        const v = propsOrGroups.selectedHeadId ?? options.selectedHeadId;
        return v?.value ?? v ?? null;
    });

    const selectedItemId = computed(() => {
        const v = propsOrGroups.selectedItemId ?? options.selectedItemId;
        return v?.value ?? v ?? null;
    });

    const selectedHead = computed(() => {
        const id = selectedHeadId.value;
        if (id === 'other') {
            return groups.value.find((g) => g.head_id == null) ?? null;
        }
        if (!id) return null;
        return groups.value.find((g) => String(g.head_id) === String(id)) ?? null;
    });

    const selectedItem = computed(() => {
        const itemId = selectedItemId.value;
        if (!itemId) return null;
        for (const group of groups.value) {
            const item = (group.items ?? []).find((i) => String(i.id) === String(itemId));
            if (item) {
                return { ...item, head_id: group.head_id, head_name: group.head_name };
            }
        }
        return null;
    });

    const headsForFilter = computed(() => propsOrGroups.headsForFilter?.value ?? propsOrGroups.headsForFilter ?? []);

    function buildQuery(baseParams = {}, { headId, itemId } = {}) {
        const q = { ...baseParams };
        const h = headId ?? selectedHeadId.value;
        const i = itemId ?? selectedItemId.value;
        if (h) q.head_id = h;
        if (i) q.item_id = i;
        return q;
    }

    function filterRowsByHead(rows, headId) {
        if (!headId) return rows ?? [];
        return (rows ?? []).filter((r) => String(r.head_id) === String(headId));
    }

    function groupRowsByHead(rows) {
        const map = new Map();
        for (const row of rows ?? []) {
            const key = row.head_id ?? 'other';
            const label = row.head_name ?? 'Other items';
            if (!map.has(key)) {
                map.set(key, { head_id: row.head_id, head_name: label, rows: [] });
            }
            map.get(key).rows.push(row);
        }
        return [...map.values()];
    }

    return {
        groups,
        selectedHeadId,
        selectedItemId,
        selectedHead,
        selectedItem,
        headsForFilter,
        buildQuery,
        filterRowsByHead,
        groupRowsByHead,
    };
}
