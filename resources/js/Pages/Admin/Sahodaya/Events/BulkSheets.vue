<template>
    <SahodayaEventsLayout :title="`${event.title} — Bulk Sheets`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                          :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Bulk Sheets`" eyebrow="Bulk Sheets"
                    description="Pick a phase, an area, or several items, then download combined judge/sum/result-declaration sheets in one go." />

        <!-- Sub Navigation Bar -->
        <SportsSetupSubNav v-if="isSports" :sahodaya-id="sahodaya.id" :event-id="event.id"
                           :event="event" active="bulk-sheets" class="mb-4" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="bulk-sheets" class="mb-4" />

        <div class="card !p-4 space-y-3">
            <!-- Child Event / Region Selector -->
            <div v-if="childEvents.length" class="flex flex-wrap items-center gap-2 pb-2 border-b border-slate-100">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ isSports ? 'Sport Event / Region:' : 'Phase / Region:' }}</label>
                <SearchableSelect :model-value="String(event.id)" @update:model-value="switchSportEvent"
                                  :options="childEventOptions" :all-option="false" placeholder="Select region"
                                  class="w-64" />
            </div>

            <div class="flex flex-wrap gap-3">
                <div v-if="phases.length" class="w-48">
                    <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 mb-1">Phase</label>
                    <SearchableSelect v-model="bulkPhaseId" :options="phases.map((p) => ({ value: String(p.id), label: p.name }))"
                                      :all-option="true" all-label="Any phase" />
                </div>
                <div v-if="competitionAreas.length" class="w-48">
                    <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-500 mb-1">Competition area</label>
                    <SearchableSelect v-model="bulkAreaId" :options="competitionAreas.map((a) => ({ value: String(a.id), label: a.name }))"
                                      :all-option="true" all-label="Any area" />
                </div>
            </div>

            <div class="flex items-center justify-between gap-3">
                <label class="flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer select-none">
                    <input type="checkbox" :checked="bulkSelectedItemIds.length === items.length && items.length > 0"
                           class="rounded border-slate-300" @change="toggleSelectAllItems">
                    Select all {{ items.length }} items
                </label>
                <span class="text-xs text-slate-400">{{ bulkSelectedItemIds.length }} item(s) individually selected</span>
            </div>
            <div class="max-h-96 overflow-y-auto rounded-lg border border-slate-200 divide-y divide-slate-100">
                <label v-for="item in items" :key="item.id" class="flex items-center gap-2 px-3 py-1.5 text-xs cursor-pointer hover:bg-slate-50">
                    <input type="checkbox" :value="item.id" v-model="bulkSelectedItemIds" class="rounded border-slate-300">
                    {{ item.title }}
                </label>
                <p v-if="!items.length" class="px-3 py-4 text-xs text-slate-400">No items in this event yet.</p>
            </div>

            <div v-if="bulkSelectionActive" class="flex flex-wrap gap-2 pt-1">
                <a :href="bulkMarkEntrySheetUrl" target="_blank" class="btn-secondary text-xs">🖨️ Judge Sheets (combined PDF)</a>
                <a :href="bulkMarkEntrySheetBlankChestUrl" target="_blank" class="btn-secondary text-xs">🖨️ Judge Sheets — No Chest No</a>
                <a :href="bulkCumulativeSheetUrl" target="_blank" class="btn-secondary text-xs">📊 Digital Sum Sheet</a>
                <a :href="bulkCumulativeSheetBlankChestUrl" target="_blank" class="btn-secondary text-xs">📊 Sum Sheet — No Chest No</a>
                <a :href="bulkResultDeclarationSheetUrl" target="_blank" class="btn-secondary text-xs">📝 Result Declaration Sheet</a>
                <a :href="bulkChestNumberListUrl" target="_blank" class="btn-secondary text-xs">🔢 Chest Number List</a>
                <a :href="bulkAttendanceSheetUrl" target="_blank" class="btn-secondary text-xs">📋 Attendance Sheet</a>
                <a :href="bulkTimesheetUrl" target="_blank" class="btn-secondary text-xs">⏱️ Timesheet</a>
                <a :href="bulkItemsListUrl" target="_blank" class="btn-secondary text-xs">📃 Items List</a>
            </div>
            <p v-else class="text-xs text-slate-400">Pick a phase, an area, or one or more items above to enable the download buttons.</p>

            <!-- Report combo: check which of the report types above to include, download
                 them all in one click, and optionally remember this combo as the
                 Sahodaya's default so it's pre-checked next time (any admin, any event). -->
            <div v-if="bulkSelectionActive" class="mt-3 pt-3 border-t border-slate-100 space-y-2">
                <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-500">Report combo</label>
                <div class="flex flex-wrap gap-x-4 gap-y-1.5">
                    <label v-for="type in reportTypeOptions" :key="type.key" class="flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer select-none">
                        <input type="checkbox" :value="type.key" v-model="comboSelectedTypes" class="rounded border-slate-300">
                        {{ type.label }}
                    </label>
                </div>
                <div class="flex flex-wrap items-center gap-2 pt-1">
                    <button type="button" class="btn-primary text-xs !py-1.5 !px-4" :disabled="!comboSelectedTypes.length" @click="downloadCombo">
                        ⬇️ Download checked reports
                    </button>
                    <button type="button" class="btn-secondary text-xs !py-1.5 !px-3" :disabled="savingCombo" @click="saveComboAsDefault">
                        {{ savingCombo ? 'Saving…' : '💾 Save as default for this Sahodaya' }}
                    </button>
                </div>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    items: { type: Array, default: () => [] },
    childEvents: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
    phases: { type: Array, default: () => [] },
    competitionAreas: { type: Array, default: () => [] },
    bulkReportCombo: { type: Array, default: () => [] },
});

const isSports = computed(() => props.event?.event_type === 'sports');

const childEventOptions = computed(() => (props.childEvents ?? []).map((ev) => ({
    value: String(ev.id),
    label: ev.short_title || ev.title,
})));

function switchSportEvent(eventId) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${eventId}/bulk-sheets`);
}

const bulkSelectedItemIds = ref([]);
const bulkPhaseId = ref('');
const bulkAreaId = ref('');

// `path` is relative to the event base (e.g. 'reports/mark-entry-sheet',
// 'chest-numbers/print') -- not every bulk-capable report lives under /reports/.
function bulkSheetUrl(path, extraParams = {}) {
    const params = new URLSearchParams();
    if (bulkSelectedItemIds.value.length) params.set('item_ids', bulkSelectedItemIds.value.join(','));
    if (bulkPhaseId.value) params.set('phase_id', bulkPhaseId.value);
    if (bulkAreaId.value) params.set('area_id', bulkAreaId.value);
    Object.entries(extraParams).forEach(([key, value]) => params.set(key, value));
    const qs = params.toString();
    return `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/${path}${qs ? `?${qs}` : ''}`;
}

const bulkMarkEntrySheetUrl = computed(() => bulkSheetUrl('reports/mark-entry-sheet'));
const bulkMarkEntrySheetBlankChestUrl = computed(() => `${bulkMarkEntrySheetUrl.value}${bulkMarkEntrySheetUrl.value.includes('?') ? '&' : '?'}blank_chest=1`);
const bulkCumulativeSheetUrl = computed(() => bulkSheetUrl('reports/mark-criteria-sheet'));
const bulkCumulativeSheetBlankChestUrl = computed(() => `${bulkCumulativeSheetUrl.value}${bulkCumulativeSheetUrl.value.includes('?') ? '&' : '?'}blank_chest=1`);
const bulkResultDeclarationSheetUrl = computed(() => bulkSheetUrl('reports/result-declaration-sheet'));
// Chest Numbers' print() defaults to a real download (no extra param needed), unlike the
// generic reports/export/{type} dispatcher below, which defaults to preview and needs
// download=1 for an actual attachment -- see FestReportService::export()'s $this->preview.
const bulkChestNumberListUrl = computed(() => bulkSheetUrl('chest-numbers/print'));
const bulkAttendanceSheetUrl = computed(() => bulkSheetUrl('reports/export/attendance-sheet', { download: 1 }));
const bulkTimesheetUrl = computed(() => bulkSheetUrl('reports/export/timesheet', { download: 1 }));
const bulkItemsListUrl = computed(() => bulkSheetUrl('reports/items-list'));
const bulkSelectionActive = computed(() => bulkSelectedItemIds.value.length > 0 || !!bulkPhaseId.value || !!bulkAreaId.value);

const reportTypeOptions = [
    { key: 'judge_sheet', label: '🖨️ Judge Sheets', url: () => bulkMarkEntrySheetUrl.value },
    { key: 'judge_sheet_no_chest', label: '🖨️ Judge Sheets — No Chest No', url: () => bulkMarkEntrySheetBlankChestUrl.value },
    { key: 'sum_sheet', label: '📊 Digital Sum Sheet', url: () => bulkCumulativeSheetUrl.value },
    { key: 'sum_sheet_no_chest', label: '📊 Sum Sheet — No Chest No', url: () => bulkCumulativeSheetBlankChestUrl.value },
    { key: 'result_declaration', label: '📝 Result Declaration Sheet', url: () => bulkResultDeclarationSheetUrl.value },
    { key: 'chest_number_list', label: '🔢 Chest Number List', url: () => bulkChestNumberListUrl.value },
    { key: 'attendance_sheet', label: '📋 Attendance Sheet', url: () => bulkAttendanceSheetUrl.value },
    { key: 'timesheet', label: '⏱️ Timesheet', url: () => bulkTimesheetUrl.value },
    { key: 'items_list', label: '📃 Items List', url: () => bulkItemsListUrl.value },
];
const comboSelectedTypes = ref([...props.bulkReportCombo]);
const savingCombo = ref(false);

function downloadCombo() {
    reportTypeOptions
        .filter((type) => comboSelectedTypes.value.includes(type.key))
        .forEach((type) => window.open(type.url(), '_blank'));
}

function saveComboAsDefault() {
    savingCombo.value = true;
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/bulk-report-combo`,
        { report_types: comboSelectedTypes.value },
        { preserveScroll: true, preserveState: true, onFinish: () => { savingCombo.value = false; } },
    );
}

function toggleSelectAllItems() {
    bulkSelectedItemIds.value = bulkSelectedItemIds.value.length === props.items.length
        ? []
        : props.items.map((item) => item.id);
}
</script>
