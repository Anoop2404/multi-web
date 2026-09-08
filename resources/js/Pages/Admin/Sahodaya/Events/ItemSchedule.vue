<template>
    <SahodayaEventsLayout :title="`${event.title} — Venue & schedule`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Venue & schedule`" eyebrow="Participants"
                    description="Assign optional date, time, and venue (stage) for each event item. Leave blank for unscheduled items.">
            <template #actions>
                <a :href="reportUrl" class="btn-secondary text-sm">Schedule report →</a>
                <a :href="settingsUrl" class="btn-secondary text-sm">Venues & stages →</a>
            </template>
        </PageHeader>

        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ summary.total }}</p>
                <p class="text-xs text-slate-500 mt-1">Items</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ summary.scheduled }}</p>
                <p class="text-xs text-slate-500 mt-1">Scheduled</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-amber-700">{{ summary.unscheduled }}</p>
                <p class="text-xs text-slate-500 mt-1">Not scheduled</p>
            </div>
        </div>

        <div v-if="!venues.length && !stages.length" class="notice-banner notice-banner--info mb-4 text-sm">
            Add venues and stages under
            <a :href="settingsUrl" class="link-brand font-semibold">Event settings → Venues</a>
            first, then pick a stage here (venue is shown from the stage).
        </div>

        <div class="flex flex-wrap gap-2 items-end mb-4">
            <input v-model="search" type="search" class="field flex-1 min-w-[12rem] !py-1.5 text-sm"
                   placeholder="Search items…" autocomplete="off">
            <SearchableSelect v-if="headOptions.length" v-model="headFilter" class="max-w-[14rem]"
                              :options="headFilterOptions" :all-option="true"
                              :all-label="event.event_type === 'sports' ? 'All Event Heads' : 'All item heads'" />
            <SearchableSelect v-if="ageGroups.length" v-model="ageFilter" class="max-w-[10rem]"
                              :options="ageGroupOptions" :all-option="true" all-label="All age groups" />
            <SearchableSelect v-model="statusFilter" class="max-w-[10rem]"
                              :options="[{ value: 'scheduled', label: 'Scheduled only' }, { value: 'unscheduled', label: 'Not scheduled' }]"
                              :all-option="true" all-label="All items" />
            <a :href="importTemplateUrl" class="btn-secondary text-xs">CSV template</a>
        </div>

        <form @submit.prevent="submitImport" class="card mb-4 max-w-xl flex flex-wrap gap-2 items-end p-4">
            <div class="flex-1 min-w-[12rem]">
                <label class="text-xs font-semibold text-slate-600">Import schedule CSV</label>
                <input type="file" accept=".csv,text/csv" class="text-xs mt-1 block w-full" @change="onImportFile">
            </div>
            <button type="submit" class="btn-secondary text-sm" :disabled="!importFile || importForm.processing">Import</button>
        </form>
        <ul v-if="$page.props.importErrors?.length" class="mb-4 text-xs text-red-600 list-disc pl-4">
            <li v-for="(err, i) in $page.props.importErrors" :key="i">{{ err }}</li>
        </ul>

        <div class="card !p-4 mb-4 bg-indigo-50/40 border-indigo-100">
            <p class="text-xs font-bold uppercase tracking-wider text-indigo-950 mb-2">⏱ Auto-sequence times</p>
            <p class="text-[11px] text-slate-500 mb-3">
                Set a start time for the items currently shown below (in their listed order) — each item's
                estimated time (from its Timing column) cascades into the next item's start time automatically.
            </p>
            <div class="flex flex-wrap gap-2 items-end">
                <div>
                    <label class="text-xs font-semibold text-slate-600 block mb-1">Stage</label>
                    <SearchableSelect v-if="stages.length" v-model="autoSeq.stage_id" class="w-40"
                                      :options="stageOptions" :all-option="true" all-label="— None —" />
                    <input v-else v-model="autoSeq.stage" type="text" class="field !py-1.5 !text-xs w-40" placeholder="Stage name">
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-600 block mb-1">Start date</label>
                    <input v-model="autoSeq.date" type="date" class="field !py-1.5 !text-xs">
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-600 block mb-1">Start time</label>
                    <input v-model="autoSeq.time" type="time" class="field !py-1.5 !text-xs">
                </div>
                <button type="button" class="btn-primary text-sm" :disabled="!canAutoSequence || autoSeqForm.processing"
                        @click="runAutoSequence">
                    Auto-generate times for {{ filteredRows.length }} item(s)
                </button>
                <span v-if="autoSeqPreviewEnd" class="text-xs text-slate-500">Estimated finish: {{ autoSeqPreviewEnd }}</span>
            </div>
        </div>

        <form @submit.prevent="saveAll" class="card card--flush overflow-hidden">
            <div class="overflow-x-auto max-h-[32rem]">
                <table class="data-table w-full text-sm">
                    <thead class="sticky top-0 bg-slate-50 z-10">
                        <tr>
                            <th class="min-w-[220px]">Item</th>
                            <th class="w-20">Age</th>
                            <th class="w-16 text-center">Reg.</th>
                            <th class="min-w-[190px]">Timing</th>
                            <th class="w-20 text-center">Est.</th>
                            <th class="w-36">Date</th>
                            <th class="w-28">Time</th>
                            <th class="min-w-[140px]">Stage / venue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="group in groupedFilteredRows" :key="group.key">
                            <tr class="bg-indigo-50/60">
                                <td colspan="8" class="px-3 py-2 text-xs font-bold uppercase tracking-wide text-indigo-800">
                                    {{ group.label }} · {{ group.rows.length }} item{{ group.rows.length === 1 ? '' : 's' }}
                                </td>
                            </tr>
                            <tr v-for="row in group.rows" :key="row.item_id" class="hover:bg-slate-50/60">
                                <td class="font-medium text-slate-900">
                                    <div>{{ row.title }}</div>
                                    <div class="mt-0.5 flex flex-wrap gap-1">
                                        <span v-if="row.category_label" class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold border bg-slate-100 text-slate-600 border-slate-200">{{ row.category_label }}</span>
                                        <span v-if="genderLabel(row)" class="px-1.5 py-0.5 rounded-full text-[10px] font-semibold border bg-slate-100 text-slate-600 border-slate-200">{{ genderLabel(row) }}</span>
                                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold capitalize border"
                                              :class="isMultiPerson(row) ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-indigo-50 text-indigo-700 border-indigo-200'">
                                            {{ participantTypeLabel(row) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="text-xs uppercase text-slate-500">{{ row.age_group || '—' }}</td>
                                <td class="text-center text-xs text-slate-600 tabular-nums">{{ row.registrations_count ?? '—' }}</td>
                                <td>
                                    <div class="flex items-center gap-1">
                                        <SearchableSelect v-model="draft[row.item_id].timing_mode" class="w-28"
                                                          :options="timingModeOptions" />
                                        <input v-model.number="draft[row.item_id].duration_minutes" type="number" min="1" max="600"
                                               class="field !py-1 !text-xs w-14" :placeholder="draft[row.item_id].timing_mode === 'fixed' ? 'Total' : 'Per'">
                                        <span class="text-[10px] text-slate-400">min</span>
                                        <input v-model.number="draft[row.item_id].calling_buffer_minutes" type="number" min="0" max="120"
                                               class="field !py-1 !text-xs w-12" placeholder="Buf" title="Calling / setup buffer, minutes">
                                    </div>
                                </td>
                                <td class="text-center text-xs font-semibold text-slate-700 tabular-nums">
                                    {{ formatMinutes(estimatedMinutesFor(row)) }}
                                </td>
                                <td>
                                    <input v-model="draft[row.item_id].scheduled_date" type="date" class="field !py-1 !text-xs">
                                </td>
                                <td>
                                    <input v-model="draft[row.item_id].scheduled_time" type="time" class="field !py-1 !text-xs">
                                </td>
                                <td>
                                    <SearchableSelect v-if="stages.length" v-model="draft[row.item_id].stage_id"
                                                      :options="stageOptions" :all-option="true" all-label="— Optional —" />
                                    <input v-else v-model="draft[row.item_id].stage" type="text" class="field !py-1 !text-xs"
                                           placeholder="Stage name">
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!filteredRows.length">
                            <td colspan="8" class="p-6 text-center text-slate-400">No items match your filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 bg-slate-50/80">
                <p class="text-xs text-slate-500">{{ filteredRows.length }} row(s) shown · date, time & venue are all optional</p>
                <button type="submit" class="btn-primary text-sm" :disabled="bulkForm.processing">Save schedule</button>
            </div>
        </form>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    rows: Array,
    summary: Object,
    stages: Array,
    venues: Array,
    ageGroups: Array,
    activityLogs: { type: Array, default: () => [] },
});

const search = ref('');
const headFilter = ref('');
const ageFilter = ref('');
const statusFilter = ref('');
const importFile = ref(null);
const importForm = useForm({ file: null });
const bulkForm = useForm({ rows: [] });
const draft = reactive({});

const timingModeOptions = [
    { value: 'per_participant', label: 'Per participant' },
    { value: 'fixed', label: 'Fixed total' },
];

const MULTI_TYPES = ['team', 'group', 'pair', 'trio'];
function isMultiPerson(row) {
    return MULTI_TYPES.includes(row.participant_type);
}

function participantTypeLabel(row) {
    return row.participant_type || 'individual';
}

function genderLabel(row) {
    const g = String(row.gender || '').toLowerCase();
    if (!g || g === 'open') return null;
    return { male: 'Boys', m: 'Boys', boy: 'Boys', boys: 'Boys', female: 'Girls', f: 'Girls', girl: 'Girls', girls: 'Girls', mixed: 'Mixed', common: 'Mixed' }[g] ?? null;
}

function initDraft() {
    for (const key of Object.keys(draft)) delete draft[key];
    for (const row of props.rows ?? []) {
        draft[row.item_id] = {
            item_id: row.item_id,
            scheduled_date: row.scheduled_date ?? '',
            scheduled_time: row.scheduled_time ?? '',
            stage_id: row.stage_id ? String(row.stage_id) : '',
            stage: row.stage ?? '',
            timing_mode: row.timing_mode ?? 'per_participant',
            duration_minutes: row.duration_minutes ?? null,
            calling_buffer_minutes: row.calling_buffer_minutes ?? null,
        };
    }
}

function formatMinutes(mins) {
    if (!mins && mins !== 0) return '—';
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    if (h && m) return `${h}h ${m}m`;
    if (h) return `${h}h`;
    return `${m}m`;
}

function estimatedMinutesFor(row) {
    const d = draft[row.item_id];
    if (!d) return row.estimated_minutes ?? null;

    const buffer = Number(d.calling_buffer_minutes) || 0;
    if (d.duration_minutes === null || d.duration_minutes === '' || d.duration_minutes === undefined) {
        return 60 + buffer;
    }
    const unit = Number(d.duration_minutes) || 0;
    if (d.timing_mode === 'fixed') {
        return unit + buffer;
    }
    const count = Math.max(row.registrations_count ?? 1, 1);
    return unit * count + buffer;
}

watch(() => props.rows, initDraft, { immediate: true });

const base = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`);
const settingsUrl = computed(() => `${base.value}/settings/venues`);
const reportUrl = computed(() => `${base.value}/reports/item-schedule`);
const importTemplateUrl = computed(() => `${base.value}/schedule/items/import-template`);

const filteredRows = computed(() => {
    const q = search.value.trim().toLowerCase();
    return (props.rows ?? []).filter((row) => {
        if (headFilter.value === 'other' && row.head_id) return false;
        if (headFilter.value && headFilter.value !== 'other' && String(row.head_id ?? '') !== String(headFilter.value)) return false;
        if (ageFilter.value && row.age_group !== ageFilter.value) return false;
        const hasSchedule = Boolean(row.scheduled_date || row.scheduled_time || row.stage_id || row.stage);
        if (statusFilter.value === 'scheduled' && !hasSchedule) return false;
        if (statusFilter.value === 'unscheduled' && hasSchedule) return false;
        if (q && !String(row.title ?? '').toLowerCase().includes(q)) return false;
        return true;
    });
});

const headOptions = computed(() => {
    const map = new Map();
    for (const row of props.rows ?? []) {
        if (row.head_id && row.head_name) {
            map.set(String(row.head_id), row.head_name);
        }
    }
    return [...map.entries()].map(([id, name]) => ({ id, name }));
});

const headFilterOptions = computed(() => [
    ...headOptions.value,
    { id: 'other', name: 'Unassigned' },
]);

const ageGroupOptions = computed(() => (props.ageGroups ?? []).map((g) => ({ value: g, label: String(g).toUpperCase() })));

const stageOptions = computed(() => (props.stages ?? []).map((s) => ({ value: String(s.id), label: stageLabel(s) })));

const groupedFilteredRows = computed(() => {
    const groups = [];
    const byKey = new Map();
    for (const row of filteredRows.value) {
        const key = row.head_id ? String(row.head_id) : 'other';
        if (!byKey.has(key)) {
            const group = {
                key,
                label: row.head_name || 'Unassigned items',
                rows: [],
            };
            byKey.set(key, group);
            groups.push(group);
        }
        byKey.get(key).rows.push(row);
    }
    return groups;
});

function stageLabel(stage) {
    return stage.venue?.name ? `${stage.name} · ${stage.venue.name}` : stage.name;
}

function onImportFile(e) {
    importFile.value = e.target.files[0] ?? null;
}

function submitImport() {
    importForm.file = importFile.value;
    importForm.post(`${base.value}/schedule/items/import`, {
        forceFormData: true,
        preserveScroll: true,
    });
}

function saveAll() {
    bulkForm.rows = (props.rows ?? []).map((row) => {
        const d = draft[row.item_id] ?? {};
        return {
            item_id: row.item_id,
            scheduled_date: d.scheduled_date || null,
            scheduled_time: d.scheduled_time || null,
            stage_id: d.stage_id ? Number(d.stage_id) : null,
            stage: d.stage || null,
            timing_mode: d.timing_mode || null,
            duration_minutes: d.duration_minutes || null,
            calling_buffer_minutes: d.calling_buffer_minutes ?? null,
        };
    });
    bulkForm.post(`${base.value}/schedule/items/bulk`, { preserveScroll: true });
}

const autoSeq = reactive({ stage_id: '', stage: '', date: '', time: '' });
const autoSeqForm = useForm({ item_ids: [], start_at: '', stage_id: null, stage: null });

const canAutoSequence = computed(() => Boolean(autoSeq.date && autoSeq.time && filteredRows.value.length));

const autoSeqPreviewEnd = computed(() => {
    if (!canAutoSequence.value) return null;
    let cursor = new Date(`${autoSeq.date}T${autoSeq.time}`);
    for (const row of filteredRows.value) {
        cursor = new Date(cursor.getTime() + estimatedMinutesFor(row) * 60000);
    }
    return cursor.toLocaleString(undefined, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
});

function runAutoSequence() {
    if (!canAutoSequence.value) return;
    autoSeqForm.item_ids = filteredRows.value.map((row) => row.item_id);
    autoSeqForm.start_at = `${autoSeq.date} ${autoSeq.time}:00`;
    autoSeqForm.stage_id = autoSeq.stage_id ? Number(autoSeq.stage_id) : null;
    autoSeqForm.stage = autoSeq.stage || null;
    autoSeqForm.post(`${base.value}/schedule/items/auto-sequence`, { preserveScroll: true });
}
</script>
