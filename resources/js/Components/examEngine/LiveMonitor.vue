<template>
    <div class="space-y-5">
        <EmptyState
            v-if="staticallyUnavailable"
            :title="unavailableTitle"
            :description="engine.message || ''"
            icon="🛰️"
        >
            <template v-if="endpoints.session && engine.reason === 'not_engine'" #action>
                <Link :href="endpoints.session" class="btn-secondary text-sm">Open live session</Link>
            </template>
        </EmptyState>

        <template v-else>
            <div class="card flex flex-wrap items-center justify-between gap-3 !py-3">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold" :class="runStateClass">
                        <span class="h-2 w-2 rounded-full" :class="runState === 'live' ? 'animate-pulse bg-current' : 'bg-current opacity-60'" />
                        {{ runStateLabel }}
                    </span>
                    <span class="text-sm text-slate-600">Engine time <strong class="tabular-nums text-slate-900">{{ formatClock(serverTime) }}</strong></span>
                    <span v-if="exam.scheduled_at" class="text-sm text-slate-500">Starts {{ formatDateTimeShort(exam.scheduled_at) }} · {{ exam.duration_minutes }} min</span>
                    <span v-if="engine.supervision === 'supervised'" class="text-xs font-semibold text-indigo-700">Supervised (start PIN)</span>
                </div>
                <div class="flex items-center gap-3 text-xs text-slate-500">
                    <span>{{ loading ? 'Updating…' : updatedLabel }}</span>
                    <button type="button" class="btn-secondary !min-h-0 !px-3 !py-1.5 text-xs" :disabled="loading" @click="refresh">Refresh</button>
                </div>
            </div>

            <div v-if="notice" class="rounded-lg px-4 py-3 text-sm" :class="notice.type === 'error' ? 'bg-red-50 text-red-800' : 'bg-emerald-50 text-emerald-800'">
                {{ notice.text }}
            </div>
            <div v-if="requestError" class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ requestError }}</div>
            <div v-if="!permissions.can_act" class="rounded-lg bg-slate-50 px-4 py-2 text-xs text-slate-600">
                You can watch this exam. Actions on candidates are for the exam team.
            </div>
            <div v-else-if="permissions.school_only" class="rounded-lg bg-slate-50 px-4 py-2 text-xs text-slate-600">
                Your school's candidates only. You can reissue passcodes, allow a device switch, unlock, and give up to
                {{ permissions.extend_cap }} extra minutes per candidate. For anything more, contact the Sahodaya exam team.
            </div>

            <div v-if="snapshot && !snapshot.ok" class="card border-amber-200 bg-amber-50/70">
                <h3 class="text-sm font-semibold text-amber-900">{{ snapshot.reason === 'not_armed' ? 'Not armed yet' : 'Live data unavailable' }}</h3>
                <p class="mt-1 text-sm text-amber-900">{{ snapshot.error }}</p>
                <p v-if="snapshot.reason === 'engine_error'" class="mt-2 text-xs text-amber-800">
                    Candidates who are already writing keep their answers on their device and sync when the engine is back. This page keeps retrying.
                </p>
                <Link v-if="snapshot.reason === 'not_armed' && endpoints.paper" :href="endpoints.paper" class="btn-secondary mt-3 text-sm">Go to the Paper tab</Link>
            </div>

            <template v-if="summary">
                <div v-if="closed" class="card space-y-3 border-emerald-200">
                    <h3 class="text-sm font-semibold text-slate-900">Exam closed — final reconciliation</h3>
                    <Reconciliation :summary="summary" :registered="registeredCount" :show-schools="!permissions.school_only" />
                </div>

                <LiveCounters
                    :counts="summary.counts"
                    :active-state="filters.state"
                    :flagged-active="filters.flagged"
                    @filter-state="filters.state = $event"
                    @filter-flagged="filters.flagged = $event"
                />

                <div v-if="!permissions.school_only || showRunControls" class="grid gap-5" :class="showRunControls ? 'xl:grid-cols-3' : ''">
                    <div v-if="!permissions.school_only" :class="showRunControls ? 'xl:col-span-2' : ''">
                        <LiveSchoolTable :rows="summary.by_school" :active-school-id="filters.school_id" @select="filters.school_id = $event" />
                    </div>
                    <RunControls
                        v-if="showRunControls"
                        :endpoints="endpoints"
                        :summary="summary"
                        :registered="registeredCount"
                        :armed="armed"
                        :pace-options="paceOptions"
                        @notice="showNotice"
                        @changed="refresh"
                    />
                </div>

                <div class="card card--flush overflow-hidden">
                    <div class="flex flex-wrap items-end gap-3 border-b border-slate-100 px-4 py-3">
                        <div class="min-w-[12rem] flex-1">
                            <label class="form-label" for="live-search">Search</label>
                            <input id="live-search" v-model="filters.q" type="search" class="field field--sm" placeholder="Name or hall ticket number">
                        </div>
                        <div v-if="!permissions.school_only" class="min-w-[12rem]">
                            <label class="form-label" for="live-school">School</label>
                            <select id="live-school" v-model="filters.school_id" class="field field--sm">
                                <option value="">All schools</option>
                                <option v-for="school in summary.by_school" :key="school.school_id" :value="school.school_id">{{ school.school_name }}</option>
                            </select>
                        </div>
                        <div class="min-w-[10rem]">
                            <label class="form-label" for="live-state">Status</label>
                            <select id="live-state" v-model="filters.state" class="field field--sm">
                                <option value="">Any status</option>
                                <option v-for="state in states" :key="state" :value="state">{{ ATTEMPT_STATE_LABELS[state] || state }}</option>
                            </select>
                        </div>
                        <label class="flex items-center gap-2 pb-2 text-sm text-slate-700">
                            <input v-model="filters.flagged" type="checkbox"> Flagged only
                        </label>
                        <button v-if="hasFilters" type="button" class="pb-2 text-xs font-semibold text-slate-500 hover:text-slate-800" @click="clearFilters">Clear filters</button>
                    </div>

                    <LiveAttemptsTable
                        :rows="attempts.data"
                        :server-time="serverTime"
                        :show-school="!permissions.school_only"
                        :allowed-actions="permissions.attempt_actions"
                        :armed="armed"
                        :extend-cap="permissions.extend_cap"
                        @act="onAct"
                    />

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-sm text-slate-600">
                        <span>{{ rangeLabel }}</span>
                        <div class="flex items-center gap-2">
                            <button type="button" class="btn-secondary !min-h-0 !px-3 !py-1.5 text-xs" :disabled="attempts.page <= 1 || loading" @click="setPage(attempts.page - 1)">← Previous</button>
                            <span class="text-xs">Page {{ attempts.page }} of {{ attempts.last_page }}</span>
                            <button type="button" class="btn-secondary !min-h-0 !px-3 !py-1.5 text-xs" :disabled="attempts.page >= attempts.last_page || loading" @click="setPage(attempts.page + 1)">Next →</button>
                        </div>
                    </div>
                </div>
            </template>
        </template>

        <AttemptActionDialog
            :show="dialog.open"
            :action="dialog.action"
            :row="dialog.row"
            :url="dialog.url"
            :extend-cap="permissions.extend_cap"
            @close="dialog.open = false"
            @done="onActionDone"
        />
        <PasscodeDialog
            :show="passcode.open"
            :passcode="passcode.code"
            :row="passcode.row"
            :warning="passcode.warning"
            @close="passcode.open = false"
        />
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm.js';
import {
    ATTEMPT_STATE_LABELS,
    RUN_STATE_LABELS,
    endpointFor,
    formatClock,
    formatDateTimeShort,
    postJson,
    useLiveMonitor,
} from './liveMonitorApi.js';
import LiveCounters from './LiveCounters.vue';
import LiveSchoolTable from './LiveSchoolTable.vue';
import LiveAttemptsTable from './LiveAttemptsTable.vue';
import AttemptActionDialog from './AttemptActionDialog.vue';
import PasscodeDialog from './PasscodeDialog.vue';
import Reconciliation from './Reconciliation.vue';
import RunControls from './RunControls.vue';

const props = defineProps({
    exam: { type: Object, required: true },
    engine: { type: Object, required: true },
    permissions: { type: Object, required: true },
    endpoints: { type: Object, required: true },
    initialFilters: { type: Object, default: () => ({}) },
    states: { type: Array, default: () => [] },
    paceOptions: { type: Array, default: () => [20, 30, 60] },
    registeredCount: { type: Number, default: 0 },
    pollSeconds: { type: Number, default: 5 },
    initial: { type: Object, default: null },
});

const { confirm } = useConfirm();

const staticallyUnavailable = computed(() => ['not_online', 'not_engine', 'not_configured'].includes(props.engine.reason));
const unavailableTitle = computed(() => ({
    not_online: 'Not an online exam',
    not_engine: 'Not on the online exam engine',
    not_configured: 'Exam engine not connected',
}[props.engine.reason] ?? 'Live monitor unavailable'));

const { snapshot, filters, loading, requestError, lastUpdated, refresh, setPage } = useLiveMonitor({
    dataUrl: props.endpoints.data,
    initial: props.initial,
    filters: props.initialFilters,
    pollSeconds: props.pollSeconds,
    enabled: !staticallyUnavailable.value,
});

const summary = computed(() => (snapshot.value?.ok ? snapshot.value.summary : null));
const attempts = computed(() => snapshot.value?.attempts ?? { data: [], total: 0, page: 1, per_page: 50, last_page: 1 });
const serverTime = computed(() => summary.value?.server_time ?? null);
const engineState = computed(() => snapshot.value?.engine_state ?? props.engine.state);
const armed = computed(() => engineState.value === 'armed');
const closed = computed(() => engineState.value === 'closed');
const runState = computed(() => (closed.value ? 'closed' : (summary.value?.state ?? engineState.value)));
const showRunControls = computed(() => props.permissions.run_actions && !closed.value);

const runStateLabel = computed(() => RUN_STATE_LABELS[runState.value] ?? (engineState.value ? 'Armed' : 'Not armed'));
const runStateClass = computed(() => ({
    armed: 'bg-slate-100 text-slate-700',
    checkin: 'bg-indigo-100 text-indigo-800',
    waiting: 'bg-sky-100 text-sky-800',
    live: 'bg-emerald-100 text-emerald-800',
    ended: 'bg-amber-100 text-amber-800',
    closed: 'bg-slate-200 text-slate-700',
}[runState.value] ?? 'bg-slate-100 text-slate-600'));

const now = ref(Date.now());
let clock = null;
onMounted(() => { clock = setInterval(() => { now.value = Date.now(); }, 1000); });
onBeforeUnmount(() => clearInterval(clock));

const updatedLabel = computed(() => {
    if (!lastUpdated.value) return `Refreshes every ${props.pollSeconds} s`;
    const s = Math.max(0, Math.round((now.value - lastUpdated.value.getTime()) / 1000));
    return `Updated ${s < 2 ? 'just now' : `${s} s ago`} · every ${props.pollSeconds} s`;
});

const hasFilters = computed(() => !!(filters.q || filters.state || filters.flagged || (!props.permissions.school_only && filters.school_id)));
const rangeLabel = computed(() => {
    const a = attempts.value;
    if (!a.total) return 'No candidates';
    const from = (a.page - 1) * a.per_page + 1;
    const to = Math.min(a.total, from + a.data.length - 1);
    return `Showing ${from}–${to} of ${a.total}`;
});

function clearFilters() {
    filters.q = '';
    filters.state = '';
    filters.flagged = false;
    if (!props.permissions.school_only) filters.school_id = '';
}

const notice = ref(null);
let noticeTimer = null;
function showNotice(value) {
    notice.value = value;
    clearTimeout(noticeTimer);
    noticeTimer = setTimeout(() => { notice.value = null; }, value?.type === 'error' ? 10000 : 6000);
}
onBeforeUnmount(() => clearTimeout(noticeTimer));

const dialog = reactive({ open: false, action: '', row: null, url: '' });
const passcode = reactive({ open: false, code: '', row: null, warning: '' });

async function onAct({ action, row }) {
    if (action === 'reissue_passcode') {
        const ok = await confirm({
            title: 'Issue a new passcode?',
            message: `${row.name}'s current exam passcode stops working at once. Use this for a lost hall ticket or a passcode that may have been shared.`,
            confirmLabel: 'Issue new passcode',
            destructive: true,
        });
        if (!ok) return;
        try {
            const json = await postJson(endpointFor(props.endpoints.passcode, row.reg_id), {});
            Object.assign(passcode, { open: true, code: json.passcode, row, warning: json.warning || '' });
        } catch (e) {
            showNotice({ type: 'error', text: e.message });
        }
        return;
    }

    Object.assign(dialog, { open: true, action, row, url: endpointFor(props.endpoints.action, row.reg_id) });
}

function onActionDone(json) {
    dialog.open = false;
    showNotice({ type: 'success', text: json.message || 'Done.' });
    refresh();
}
</script>
