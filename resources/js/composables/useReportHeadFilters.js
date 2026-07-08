import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

export function rowMatchesHead(row, headId) {
    if (!headId) return true;
    if (headId === 'other') return row.head_id == null;
    return String(row.head_id) === String(headId);
}

export function rowMatchesItem(row, itemId) {
    if (!itemId) return true;
    const id = row.item_id ?? row.item1_id ?? row.item2_id;
    if (itemId && (row.item1_id || row.item2_id)) {
        return String(row.item1_id) === String(itemId) || String(row.item2_id) === String(itemId);
    }
    return String(id) === String(itemId);
}

export function filterReportRows(rows, { headId = '', itemId = '' } = {}) {
    return (rows ?? []).filter((r) => rowMatchesHead(r, headId) && rowMatchesItem(r, itemId));
}

export function clashMatchesHead(clash, headId) {
    if (!headId) return true;
    if (headId === 'other') {
        return clash.head1_id == null || clash.head2_id == null;
    }
    return String(clash.head1_id) === String(headId) || String(clash.head2_id) === String(headId);
}

export function clashMatchesItem(clash, itemId) {
    if (!itemId) return true;
    return String(clash.item1_id) === String(itemId) || String(clash.item2_id) === String(itemId);
}

export function filterClashRows(rows, { headId = '', itemId = '' } = {}) {
    return (rows ?? []).filter((r) => clashMatchesHead(r, headId) && clashMatchesItem(r, itemId));
}

export function shouldShowHeadDivider(row, prevRow) {
    const name = row.head_name ?? 'Other items';
    const prevName = prevRow?.head_name ?? 'Other items';
    return name !== prevName;
}

/**
 * Shared head → item filter state for report pages.
 */
export function useReportHeadFilters(baseUrl, rowsSource, options = {}) {
    const page = usePage();
    const params = new URLSearchParams(window.location.search);

    const headFilter = ref(params.get('head_id') ?? '');
    const itemFilter = ref(params.get('item_id') ?? '');

    const headItemGroups = computed(() => page.props.headItemGroups ?? []);
    const headsForFilter = computed(() => page.props.headsForFilter ?? []);
    const hasItemHeads = computed(() => page.props.hasItemHeads ?? false);

    const displayRows = computed(() => {
        const rows = typeof rowsSource === 'function'
            ? rowsSource()
            : (rowsSource?.value ?? rowsSource ?? []);
        return filterReportRows(rows, { headId: headFilter.value, itemId: itemFilter.value });
    });

    function applyFilter(extra = {}) {
        router.get(
            baseUrl,
            {
                head_id: headFilter.value || undefined,
                item_id: itemFilter.value || undefined,
                ...extra,
            },
            { preserveScroll: true, preserveState: true, ...options.routerOptions },
        );
    }

    return {
        headFilter,
        itemFilter,
        headItemGroups,
        headsForFilter,
        hasItemHeads,
        displayRows,
        applyFilter,
        shouldShowHeadDivider,
    };
}
