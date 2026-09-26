import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';

/**
 * Admin pages are Inertia snapshots: what was on screen when the page loaded stays there
 * until the user navigates. With several admins working the same event (one ticks a
 * school, another enters marks) a tab left open in the background keeps showing old data
 * even though the server already has the new rows.
 *
 * When the tab becomes visible again (or the page is restored from the back/forward
 * cache) after being away for a while, re-fetch the current page's props in place.
 * Skipped while the user has unsaved typing on the page or a form is mid-submit, so a
 * refresh never clobbers work in progress.
 */
const STALE_AFTER_MS = 45_000;

export function useStaleRefresh() {
    let loadedAt = Date.now();
    let dirty = false;
    let busy = false;

    const markDirty = (e) => {
        const t = e.target;
        if (t && /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName) && t.type !== 'search') dirty = true;
    };
    const markClean = () => { dirty = false; loadedAt = Date.now(); busy = false; };
    const markBusy = () => { busy = true; };

    function refreshIfStale(force = false) {
        if (document.visibilityState !== 'visible' || busy) return;
        if (!force && Date.now() - loadedAt < STALE_AFTER_MS) return;
        if (dirty) return;
        loadedAt = Date.now();
        router.reload({ preserveScroll: true, preserveState: true });
    }

    const onVisible = () => refreshIfStale();
    const onPageShow = (e) => { if (e.persisted) refreshIfStale(true); };

    let offStart; let offFinish;
    onMounted(() => {
        loadedAt = Date.now();
        document.addEventListener('visibilitychange', onVisible);
        window.addEventListener('pageshow', onPageShow);
        document.addEventListener('input', markDirty, true);
        offStart = router.on('start', markBusy);
        offFinish = router.on('finish', markClean);
    });
    onBeforeUnmount(() => {
        document.removeEventListener('visibilitychange', onVisible);
        window.removeEventListener('pageshow', onPageShow);
        document.removeEventListener('input', markDirty, true);
        offStart?.();
        offFinish?.();
    });
}
