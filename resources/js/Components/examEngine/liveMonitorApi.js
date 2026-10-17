/**
 * Small helpers for the online exam live monitor (exam-engine/CONTRACT.md §3, served through
 * Laravel's /live/* endpoints). Times are shown in IST like the rest of the admin panel.
 */
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

/** POST JSON to a monitor endpoint. Resolves with the JSON body; rejects with a readable Error. */
export async function postJson(url, body = {}) {
    let res;
    try {
        res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(body),
        });
    } catch {
        throw new Error('Could not reach the server. Check your connection and try again.');
    }

    let json = null;
    try {
        json = await res.json();
    } catch {
        json = null;
    }

    if (res.status === 401 || res.status === 419) {
        throw new Error('Your session has expired. Reload the page and sign in again.');
    }
    if (!res.ok || json?.ok === false) {
        const firstError = json?.errors ? Object.values(json.errors).flat()[0] : null;
        throw new Error(firstError || json?.message || `The request failed (${res.status}).`);
    }

    return json ?? {};
}

export function endpointFor(template, regId) {
    return template ? template.replace('__REG__', encodeURIComponent(regId)) : null;
}

const timeFormat = new Intl.DateTimeFormat('en-IN', {
    hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true, timeZone: 'Asia/Kolkata',
});
const shortTimeFormat = new Intl.DateTimeFormat('en-IN', {
    hour: 'numeric', minute: '2-digit', hour12: true, timeZone: 'Asia/Kolkata',
});
const dateTimeFormat = new Intl.DateTimeFormat('en-IN', {
    day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit', hour12: true, timeZone: 'Asia/Kolkata',
});

function toDate(value) {
    if (!value) return null;
    const d = value instanceof Date ? value : new Date(value);
    return Number.isNaN(d.getTime()) ? null : d;
}

export function formatClock(value) {
    const d = toDate(value);
    return d ? timeFormat.format(d) : '—';
}

export function formatTime(value) {
    const d = toDate(value);
    return d ? shortTimeFormat.format(d) : '—';
}

export function formatDateTimeShort(value) {
    const d = toDate(value);
    return d ? dateTimeFormat.format(d) : '—';
}

/** "45 s ago", "3 min ago" relative to the engine's clock (not the browser's). */
export function ago(value, now) {
    const d = toDate(value);
    const ref = toDate(now) ?? new Date();
    if (!d) return '—';
    const seconds = Math.max(0, Math.round((ref.getTime() - d.getTime()) / 1000));
    if (seconds < 60) return `${seconds} s ago`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)} min ago`;
    return `${Math.floor(seconds / 3600)} h ago`;
}

/** "12 min left" / "overdue" for a deadline, relative to the engine's clock. */
export function timeLeft(deadline, now) {
    const d = toDate(deadline);
    const ref = toDate(now) ?? new Date();
    if (!d) return null;
    const seconds = Math.round((d.getTime() - ref.getTime()) / 1000);
    if (seconds <= 0) return 'time up';
    if (seconds < 60) return `${seconds} s left`;
    if (seconds < 3600) return `${Math.ceil(seconds / 60)} min left`;
    const h = Math.floor(seconds / 3600);
    return `${h} h ${Math.ceil((seconds - h * 3600) / 60)} min left`;
}

export const RUN_STATE_LABELS = {
    armed: 'Armed — check-in not open yet',
    checkin: 'Check-in open',
    waiting: 'Waiting room open',
    live: 'Exam is live',
    ended: 'Time over — waiting to close',
    closed: 'Closed',
};

export const ATTEMPT_STATE_LABELS = {
    checked_in: 'Checked in',
    waiting: 'In waiting room',
    writing: 'Writing',
    submitted: 'Submitted',
    locked: 'Locked',
    ended: 'Ended',
};

export const SUBMIT_MODE_LABELS = { manual: 'by student', auto: 'at time-up', forced: 'forced' };

/**
 * Polls the monitor's data endpoint (every `pollSeconds`, paused while the tab is hidden) and
 * keeps the filters. Filter changes reset to page 1 and refresh at once (search is debounced).
 */
export function useLiveMonitor({ dataUrl, initial = null, filters: initialFilters = {}, pollSeconds = 5, enabled = true }) {
    const snapshot = ref(initial);
    const filters = reactive({
        school_id: initialFilters.school_id ?? '',
        state: initialFilters.state ?? '',
        q: initialFilters.q ?? '',
        flagged: !!initialFilters.flagged,
        page: initialFilters.page ?? 1,
    });
    const loading = ref(false);
    const requestError = ref('');
    const lastUpdated = ref(initial ? new Date() : null);
    let timer = null;
    let searchTimer = null;
    let seq = 0;
    let stopped = !enabled;

    function queryString() {
        const params = new URLSearchParams();
        if (filters.school_id) params.set('school_id', filters.school_id);
        if (filters.state) params.set('state', filters.state);
        if (filters.q?.trim()) params.set('q', filters.q.trim());
        if (filters.flagged) params.set('flagged', '1');
        if (filters.page > 1) params.set('page', String(filters.page));
        const qs = params.toString();
        return qs ? `?${qs}` : '';
    }

    async function refresh() {
        if (!dataUrl) return;
        const mine = ++seq;
        loading.value = true;
        try {
            const res = await fetch(`${dataUrl}${queryString()}`, {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (mine !== seq) return;
            if (res.status === 401 || res.status === 419) {
                requestError.value = 'Your session has expired. Reload the page and sign in again.';
                stop();
                return;
            }
            const json = await res.json().catch(() => null);
            if (mine !== seq) return;
            if (!res.ok || !json) {
                requestError.value = json?.message || `Could not refresh the monitor (${res.status}). Retrying…`;
                return;
            }
            snapshot.value = json;
            requestError.value = '';
            lastUpdated.value = new Date();
        } catch {
            if (mine === seq) requestError.value = 'Could not reach the server. Retrying…';
        } finally {
            if (mine === seq) loading.value = false;
        }
    }

    function interval() {
        // Not armed yet: nothing changes quickly, so look less often.
        return (snapshot.value?.reason === 'not_armed' ? 30 : pollSeconds) * 1000;
    }

    function schedule() {
        clearTimeout(timer);
        if (stopped) return;
        timer = setTimeout(async () => {
            if (!document.hidden) await refresh();
            schedule();
        }, interval());
    }

    function stop() {
        stopped = true;
        clearTimeout(timer);
    }

    function onVisibility() {
        if (!document.hidden && !stopped) {
            refresh();
            schedule();
        }
    }

    function setPage(page) {
        filters.page = page;
        refresh();
    }

    watch(() => [filters.school_id, filters.state, filters.flagged], () => {
        filters.page = 1;
        refresh();
    });
    watch(() => filters.q, () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            filters.page = 1;
            refresh();
        }, 350);
    });

    onMounted(() => {
        if (stopped) return;
        schedule();
        document.addEventListener('visibilitychange', onVisibility);
    });
    onBeforeUnmount(() => {
        stop();
        clearTimeout(searchTimer);
        document.removeEventListener('visibilitychange', onVisibility);
    });

    return { snapshot, filters, loading, requestError, lastUpdated, refresh, setPage };
}
