<template>
    <main class="page wide">
        <BrandHeader :name="st.sahodaya_name" :sub="st.title" :logo="st.logo_url" :code="app.code" />

        <section class="card" aria-labelledby="who">
            <div class="row wrap" style="align-items: flex-start">
                <div style="min-width: 0; flex: 1">
                    <p class="faint">Signed in as</p>
                    <h1 id="who">{{ st.name }}</h1>
                </div>
                <span class="pill ok"><span class="dot" />Checked in</span>
            </div>
            <dl class="kv" style="margin-top: 12px">
                <dt>Hall ticket</dt>
                <dd>{{ st.hall_ticket }}</dd>
                <dt>School</dt>
                <dd>{{ st.school_name }}</dd>
                <dt>Class</dt>
                <dd>{{ st.class_name }}</dd>
                <dt>Exam</dt>
                <dd>{{ st.title }}</dd>
                <dt>Starts</dt>
                <dd>{{ formatLocalTime(st.starts_at) }}</dd>
                <dt>Duration</dt>
                <dd>{{ st.duration_minutes }} minutes<template v-if="st.extra_minutes"> + {{ st.extra_minutes }} extra</template></dd>
            </dl>
        </section>

        <div v-for="m in st.messages" :key="m.id" class="notice info card" role="status" style="margin-top: 14px">
            <b>Message from the exam team:</b> {{ m.text }}
        </div>

        <!-- Phase: countdown / start -->
        <section class="card center stack" aria-live="polite" aria-labelledby="phase">
            <template v-if="phase === 'checkin'">
                <h2 id="phase">Check-in complete</h2>
                <p class="muted">The waiting room opens {{ formatLocalTime(st.waiting_opens_at) }}.</p>
                <div class="big-count">{{ formatDuration(msToWaiting) }}</div>
                <p class="faint">You can close this page now. On exam day, open the exam again on <b>this device</b> — you may not need to sign in again.</p>
            </template>
            <template v-else-if="phase === 'waiting'">
                <h2 id="phase">Waiting room</h2>
                <p class="muted">Your exam starts {{ formatLocalTime(st.starts_at, false) }}. Stay on this page.</p>
                <div class="big-count" :aria-label="`Starts in ${formatDuration(msToStart)}`">{{ formatCountdown(msToStart) }}</div>
            </template>
            <template v-else-if="phase === 'open'">
                <h2 id="phase">The exam has started</h2>
                <p class="muted">Late entry closes {{ formatLocalTime(st.late_entry_until, false) }}.</p>
            </template>
            <template v-else>
                <h2 id="phase">Entry has closed</h2>
                <p class="muted">Late entry ended {{ formatLocalTime(st.late_entry_until, false) }}. Contact your school coordinator if you couldn’t start.</p>
            </template>

            <form v-if="phase === 'waiting' || phase === 'open'" class="stack" style="text-align: left" @submit.prevent="start">
                <label v-if="st.start_pin_required" class="field">
                    <span>Start PIN — from your invigilator</span>
                    <input
                        v-model.trim="pin"
                        class="input code"
                        inputmode="numeric"
                        autocomplete="off"
                        maxlength="12"
                        :disabled="starting"
                        aria-describedby="pin-help"
                        :aria-invalid="startError?.kind === 'bad' ? 'true' : undefined"
                    />
                    <small id="pin-help" class="help">
                        Told by the invigilator at the venue when the exam starts — not your hall-ticket passcode or portal password.
                    </small>
                </label>
                <p v-else class="help" style="margin: 0">
                    No Start PIN for this exam — tap Start when the waiting room opens.
                </p>
                <div v-if="startError" class="notice" :class="startError.kind" role="alert">
                    {{ startError.text }}<span v-if="retryIn > 0"> ({{ retryIn }} s)</span>
                </div>
                <button class="btn primary block big" type="submit" :disabled="!canStart">
                    <span v-if="starting" class="spinner" aria-hidden="true" />
                    {{ startLabel }}
                </button>
            </form>
        </section>

        <!-- System check -->
        <section class="card" aria-labelledby="syscheck">
            <div class="row">
                <h2 id="syscheck">System check</h2>
                <span class="spacer" />
                <span v-if="check" class="pill" :class="check.ok ? 'ok' : 'bad'">{{ check.ok ? 'Ready' : 'Needs attention' }}</span>
            </div>
            <p v-if="checking" class="row muted" style="margin-top: 10px"><span class="spinner" aria-hidden="true" /> Checking this device…</p>
            <ul v-else-if="check" class="checks" style="margin-top: 8px">
                <li v-for="item in check.items" :key="item.id">
                    <span class="icon" :class="item.status" aria-hidden="true">{{ item.status === 'ok' ? '✓' : item.status === 'warn' ? '!' : '✕' }}</span>
                    <div>
                        <div style="font-weight: 600">{{ item.label }} <span class="sr-only">— {{ item.status === 'ok' ? 'OK' : item.status === 'warn' ? 'warning' : 'problem' }}</span></div>
                        <div class="faint">{{ item.detail }}</div>
                    </div>
                </li>
            </ul>
            <button class="btn" type="button" style="margin-top: 10px" :disabled="checking" @click="runCheck(true)">Run the check again</button>
        </section>

        <!-- Waiting room: instructions + paper download -->
        <section v-if="phase !== 'checkin'" class="card stack" aria-labelledby="instr">
            <h2 id="instr">Before you start</h2>
            <ul class="instructions">
                <li>The exam lasts <b>{{ st.duration_minutes + (st.extra_minutes || 0) }} minutes</b>. The timer at the top of the screen is the official time.</li>
                <li>One question per screen. Use <b>Next</b> and <b>Previous</b>, or the <b>Questions</b> list to jump to any question.</li>
                <li>Some questions have more than one correct answer — read the line that says <b>Choose …</b>.</li>
                <li>Mark a question <b>for review</b> to come back to it. Marked questions are still counted if answered.</li>
                <li>Each answer is saved on this device at once and sent automatically. If the internet drops, keep writing.</li>
                <li>Don’t open the exam on another device. Leaving the exam screen is recorded.</li>
                <li v-if="st.require_fullscreen">This exam requires <b>fullscreen</b> while writing. Leaving fullscreen is recorded for review — not an automatic fail. Extra monitors are not detected.</li>
                <li>When the time is up, your answers are submitted automatically.</li>
            </ul>
            <div class="row" style="margin-top: 6px">
                <span class="icon-btn" style="pointer-events: none" aria-hidden="true">📄</span>
                <div style="flex: 1; min-width: 0">
                    <div style="font-weight: 600">Question paper (locked until the start)</div>
                    <div class="faint" aria-live="polite">{{ downloadText }}</div>
                    <div v-if="download.state === 'downloading'" class="progress" style="margin-top: 6px"><i :style="{ width: progressPct + '%' }" /></div>
                </div>
                <span v-if="download.state === 'ready'" class="pill ok">Ready</span>
            </div>
        </section>

        <p class="center" style="margin-top: 18px">
            <button class="btn ghost" type="button" @click="logout">Sign out</button>
        </p>
    </main>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import BrandHeader from '../components/BrandHeader.vue';
import { app, logout, refreshState, showLogin, startExam } from '../app';
import { api, ApiError } from '../lib/api';
import { formatCountdown, formatDuration, formatLocalTime, parseTime } from '../lib/clock';
import { ensureBundle } from '../lib/paper';
import { loadKatex } from '../lib/richText';
import { loadFlag, saveFlag } from '../lib/storage';
import { runSystemCheck, type SystemCheckResult } from '../lib/systemCheck';
import { useServerNow } from '../lib/useNow';
import type { ExamState } from '../lib/types';

const now = useServerNow();
const st = computed(() => app.state as ExamState);

const msToWaiting = computed(() => (parseTime(st.value.waiting_opens_at) ?? 0) - now.value);
const msToStart = computed(() => (parseTime(st.value.starts_at) ?? 0) - now.value);
const msToLateEnd = computed(() => (parseTime(st.value.late_entry_until) ?? Infinity) - now.value);

const phase = computed<'checkin' | 'waiting' | 'open' | 'late'>(() => {
    if (msToWaiting.value > 0) return 'checkin';
    if (msToStart.value > 0) return 'waiting';
    if (msToLateEnd.value > 0) return 'open';
    return 'late';
});

const startUnlocked = computed(() => msToStart.value <= 0 && phase.value === 'open');

const pin = ref('');
const starting = ref(false);
const retryIn = ref(0);
const startError = ref<{ kind: 'bad' | 'warn' | 'info'; text: string } | null>(null);
let retryTimer: ReturnType<typeof setInterval> | null = null;
let retriedStart = false;

const canStart = computed(
    () => startUnlocked.value && !starting.value && retryIn.value <= 0 && (!st.value.start_pin_required || pin.value.length > 0),
);
const startLabel = computed(() => {
    if (starting.value) return 'Opening the exam…';
    if (phase.value === 'waiting' || !startUnlocked.value) return `Start exam (in ${formatCountdown(Math.max(0, msToStart.value))})`;
    return 'Start exam';
});

async function start() {
    if (!canStart.value) return;
    starting.value = true;
    startError.value = null;
    try {
        if (st.value.require_fullscreen) {
            try {
                await document.documentElement.requestFullscreen?.();
            } catch {
                startError.value = {
                    kind: 'warn',
                    text: 'Allow fullscreen to continue — this exam requires it while writing.',
                };
                starting.value = false;
                return;
            }
        }
        // Plan §3: spread the T0 key requests — a click in the first 10 s waits 0–5 s first.
        if (msToStart.value > -10000 && !retriedStart) await new Promise((r) => setTimeout(r, Math.random() * 5000));
        retriedStart = true;
        await startExam(st.value.start_pin_required ? pin.value : null);
    } catch (e) {
        const err = e instanceof ApiError ? e : new ApiError(0, 'network', String(e));
        switch (err.code) {
            case 'wrong_pin':
                startError.value = { kind: 'bad', text: 'That PIN isn’t right. Ask your invigilator for the start PIN.' };
                break;
            case 'not_open_yet':
                startError.value = { kind: 'info', text: 'The exam hasn’t opened yet. Please wait a moment.' };
                refreshState().catch(() => undefined);
                autoRetry(5);
                break;
            case 'closed':
                startError.value = { kind: 'bad', text: 'Entry to this exam has closed. Contact your school coordinator.' };
                break;
            case 'locked':
                startError.value = { kind: 'warn', text: 'Your exam is on hold. Please wait for your invigilator.' };
                break;
            case 'forbidden':
                startError.value = { kind: 'bad', text: 'You can’t start this exam. Please contact your school coordinator.' };
                break;
            case 'unauthorized':
                showLogin();
                break;
            default:
                if (err.isTransient) {
                    startError.value = { kind: 'info', text: err.isNetwork ? 'No connection to the exam server. Retrying automatically' : 'Many students are starting right now. Retrying automatically' };
                    autoRetry(err.retryAfter ?? 3 + Math.random() * 4);
                } else {
                    startError.value = { kind: 'bad', text: err.message || 'Couldn’t start the exam. Try again.' };
                }
        }
    } finally {
        starting.value = false;
    }
}

function autoRetry(seconds: number) {
    if (retryTimer) clearInterval(retryTimer);
    retryIn.value = Math.max(1, Math.round(seconds));
    retryTimer = setInterval(() => {
        retryIn.value--;
        if (retryIn.value <= 0) {
            if (retryTimer) clearInterval(retryTimer);
            retryTimer = null;
            start();
        }
    }, 1000);
}

// ---------------------------------------------------------------- system check

const check = ref<SystemCheckResult | null>(null);
const checking = ref(false);
const checkKey = computed(() => `syscheck:${st.value.run}:${st.value.reg_id}`);

async function runCheck(force: boolean) {
    if (checking.value) return;
    if (!force && st.value.system_check?.ok) {
        const done = await loadFlag<boolean>(checkKey.value).catch(() => undefined);
        // Already reported from this device: run the quick local checks again only.
        if (done) {
            checking.value = true;
            check.value = await runSystemCheck();
            checking.value = false;
            return;
        }
    }
    checking.value = true;
    try {
        check.value = await runSystemCheck();
        await api('POST', '/api/system-check', check.value.payload, { timeoutMs: 15000 });
        await saveFlag(checkKey.value, true).catch(() => undefined);
    } catch {
        /* shown via the latency/online rows */
    } finally {
        checking.value = false;
    }
}

// ---------------------------------------------------------------- paper prefetch

const download = reactive({ state: 'idle' as 'idle' | 'scheduled' | 'downloading' | 'ready' | 'error', loaded: 0, total: null as number | null, retryAt: 0 });
let prefetchTimer: ReturnType<typeof setTimeout> | null = null;
let prefetchFor = '';
const progressPct = computed(() => (download.total ? Math.min(100, Math.round((download.loaded / download.total) * 100)) : download.loaded ? 60 : 5));
const downloadText = computed(() => {
    switch (download.state) {
        case 'scheduled':
            return 'Will download in a moment…';
        case 'downloading':
            return `Downloading… ${Math.round(download.loaded / 1024)} KB${download.total ? ` of ${Math.round(download.total / 1024)} KB` : ''}`;
        case 'ready':
            return 'Downloaded and saved on this device. It unlocks when the exam starts.';
        case 'error':
            return 'Download failed. Retrying automatically…';
        default:
            return 'Downloads automatically in the waiting room.';
    }
});

function schedulePrefetch(delayMs: number) {
    if (prefetchTimer) clearTimeout(prefetchTimer);
    prefetchTimer = setTimeout(prefetch, delayMs);
}

async function prefetch() {
    const state = st.value;
    const id = `${state.run}:${state.bundle_version}`;
    prefetchFor = id;
    download.state = 'downloading';
    download.loaded = 0;
    download.total = null;
    loadKatex();
    try {
        await ensureBundle(state, (p) => {
            download.loaded = p.loaded;
            download.total = p.total;
        });
        if (prefetchFor === id) download.state = 'ready';
    } catch {
        download.state = 'error';
        schedulePrefetch(10000 + Math.random() * 20000);
    }
}

function maybePrefetch() {
    if (phase.value === 'checkin') return;
    const id = `${st.value.run}:${st.value.bundle_version}`;
    if (prefetchFor === id && download.state !== 'idle') return;
    prefetchFor = id;
    download.state = 'scheduled';
    // Spread the downloads: up to 2 min of jitter when the start is far, a few seconds when close.
    const room = Math.max(0, msToStart.value);
    const spread = Math.min(120000, room / 4);
    schedulePrefetch(Math.random() * Math.max(3000, spread));
}

watch(phase, maybePrefetch);
watch(() => st.value.bundle_version, () => {
    download.state = 'idle';
    maybePrefetch();
});

// ---------------------------------------------------------------- state polling

let pollTimer: ReturnType<typeof setTimeout> | null = null;
function schedulePoll() {
    if (pollTimer) clearTimeout(pollTimer);
    const pace = Math.max(st.value.next_sync_in || 10, 10);
    // Sparse: the waiting room only needs broadcasts and late changes (start time, PIN rule).
    const base = phase.value === 'checkin' ? Math.min(300, Math.max(60, msToWaiting.value / 4000)) : Math.max(60, pace * 6);
    // In check-in, also wake up shortly after the waiting room opens (spread over ~30 s).
    const cap = phase.value === 'checkin' ? Math.max(5000, msToWaiting.value + 2000 + Math.random() * 30000) : Infinity;
    const delay = Math.min(base * 1000 * (1 + Math.random() * 0.5), cap);
    pollTimer = setTimeout(async () => {
        try {
            await refreshState();
        } catch (e) {
            if (e instanceof ApiError && e.code === 'unauthorized') {
                showLogin();
                return;
            }
        }
        if (app.screen === 'prepare') schedulePoll();
    }, delay);
}

onMounted(() => {
    runCheck(false);
    maybePrefetch();
    schedulePoll();
});

onBeforeUnmount(() => {
    if (pollTimer) clearTimeout(pollTimer);
    if (prefetchTimer) clearTimeout(prefetchTimer);
    if (retryTimer) clearInterval(retryTimer);
});
</script>
