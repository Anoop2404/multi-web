import { computed } from 'vue';

/** Filter portal registrations client-side when head/item tabs are used. */
export function usePortalHeadFilter(registrations, selectedHeadId, selectedItemId) {
    const filteredRegistrations = computed(() => {
        let list = registrations?.value ?? registrations ?? [];

        if (selectedItemId?.value ?? selectedItemId) {
            const itemId = selectedItemId?.value ?? selectedItemId;
            list = list.filter((r) => String(r.item_id ?? r.item?.id) === String(itemId));
        } else if (selectedHeadId?.value ?? selectedHeadId) {
            const headId = selectedHeadId?.value ?? selectedHeadId;
            if (headId === 'other') {
                list = list.filter((r) => !r.item?.head_id);
            } else {
                list = list.filter((r) => String(r.item?.head_id ?? '') === String(headId));
            }
        }

        return list;
    });

    const groupedByHead = computed(() => {
        const map = new Map();
        for (const reg of filteredRegistrations.value) {
            const key = reg.item?.head?.name ?? reg.item?.head_name ?? 'Other items';
            if (!map.has(key)) {
                map.set(key, []);
            }
            map.get(key).push(reg);
        }

        return [...map.entries()].map(([headName, regs]) => ({ headName, registrations: regs }));
    });

    return { filteredRegistrations, groupedByHead };
}
