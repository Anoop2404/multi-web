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
            <select v-if="headOptions.length" v-model="headFilter" class="field text-sm max-w-[14rem]">
                <option value="">All item heads</option>
                <option v-for="h in headOptions" :key="h.id" :value="h.id">{{ h.name }}</option>
                <option value="other">Unassigned</option>
            </select>
            <select v-if="ageGroups.length" v-model="ageFilter" class="field text-sm max-w-[10rem]">
                <option value="">All age groups</option>
                <option v-for="g in ageGroups" :key="g" :value="g">{{ String(g).toUpperCase() }}</option>
            </select>
            <select v-model="statusFilter" class="field text-sm max-w-[10rem]">
                <option value="">All items</option>
                <option value="scheduled">Scheduled only</option>
                <option value="unscheduled">Not scheduled</option>
            </select>
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

        <form @submit.prevent="saveAll" class="card card--flush overflow-hidden">
            <div class="overflow-x-auto max-h-[32rem]">
                <table class="data-table w-full text-sm">
                    <thead class="sticky top-0 bg-slate-50 z-10">
                        <tr>
                            <th class="min-w-[160px]">Item</th>
                            <th class="w-20">Age</th>
                            <th class="w-36">Date</th>
                            <th class="w-28">Time</th>
                            <th class="min-w-[140px]">Stage / venue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="group in groupedFilteredRows" :key="group.key">
                            <tr class="bg-indigo-50/60">
                                <td colspan="5" class="px-3 py-2 text-xs font-bold uppercase tracking-wide text-indigo-800">
                                    {{ group.label }} · {{ group.rows.length }} item{{ group.rows.length === 1 ? '' : 's' }}
                                </td>
                            </tr>
                            <tr v-for="row in group.rows" :key="row.item_id" class="hover:bg-slate-50/60">
                                <td class="font-medium text-slate-900">{{ row.title }}</td>
                                <td class="text-xs uppercase text-slate-500">{{ row.age_group || '—' }}</td>
                                <td>
                                    <input v-model="draft[row.item_id].scheduled_date" type="date" class="field !py-1 !text-xs">
                                </td>
                                <td>
                                    <input v-model="draft[row.item_id].scheduled_time" type="time" class="field !py-1 !text-xs">
                                </td>
                                <td>
                                    <select v-if="stages.length" v-model="draft[row.item_id].stage_id" class="field !py-1 !text-xs">
                                        <option value="">— Optional —</option>
                                        <option v-for="s in stages" :key="s.id" :value="String(s.id)">{{ stageLabel(s) }}</option>
                                    </select>
                                    <input v-else v-model="draft[row.item_id].stage" type="text" class="field !py-1 !text-xs"
                                           placeholder="Stage name">
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!filteredRows.length">
                            <td colspan="5" class="p-6 text-center text-slate-400">No items match your filters.</td>
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

function initDraft() {
    for (const key of Object.keys(draft)) delete draft[key];
    for (const row of props.rows ?? []) {
        draft[row.item_id] = {
            item_id: row.item_id,
            scheduled_date: row.scheduled_date ?? '',
            scheduled_time: row.scheduled_time ?? '',
            stage_id: row.stage_id ? String(row.stage_id) : '',
            stage: row.stage ?? '',
        };
    }
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
        };
    });
    bulkForm.post(`${base.value}/schedule/items/bulk`, { preserveScroll: true });
}
</script>
