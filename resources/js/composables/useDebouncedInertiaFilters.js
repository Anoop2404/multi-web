import { onBeforeUnmount, watch } from 'vue';

/**
 * Debounce Inertia filter applies when filter form fields change.
 * Skips the initial sync from props and re-applies after server round-trips.
 */
export function useDebouncedInertiaFilters(form, applyFn, propsFilters, options = {}) {
    const delay = options.delay ?? 350;
    let timer = null;
    let syncingFromServer = false;

    function snapshot() {
        return JSON.stringify(form);
    }

    function serverSnapshot() {
        const f = typeof propsFilters === 'function' ? propsFilters() : propsFilters?.value ?? propsFilters;
        if (!f) return '';
        return JSON.stringify(f);
    }

    watch(form, () => {
        if (syncingFromServer) return;

        clearTimeout(timer);
        timer = setTimeout(() => {
            if (snapshot() === serverSnapshot()) return;
            applyFn();
        }, delay);
    }, { deep: true });

    watch(
        () => (typeof propsFilters === 'function' ? propsFilters() : propsFilters?.value ?? propsFilters),
        (f) => {
            if (!f) return;
            syncingFromServer = true;
            clearTimeout(timer);
            queueMicrotask(() => {
                syncingFromServer = false;
            });
        },
        { deep: true },
    );

    onBeforeUnmount(() => clearTimeout(timer));
}
