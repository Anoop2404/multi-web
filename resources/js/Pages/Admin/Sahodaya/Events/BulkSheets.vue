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
                    <span>
                        {{ item.title }}
                        <span v-if="item.item_code" class="text-slate-400">· #{{ item.item_code }}</span>
                        <span v-if="item.category_label" class="text-slate-400">· {{ item.category_label }}</span>
                    </span>
                </label>
                <p v-if="!items.length" class="px-3 py-4 text-xs text-slate-400">No items in this event yet.</p>
            </div>

            <div v-if="bulkSelectionActive" class="flex flex-wrap gap-2 pt-1">
                <span v-for="type in reportTypeOptions" :key="type.key" class="inline-flex rounded-lg overflow-hidden border border-slate-200">
                    <a :href="type.url()" target="_blank" class="btn-secondary text-xs !rounded-none !border-0">{{ type.label }}</a>
                    <a :href="type.url({ preview: true })" target="_blank" title="Preview in a new tab" class="btn-secondary text-xs !rounded-none !border-0 !border-l !border-slate-200 !px-2">👁️</a>
                </span>
            </div>
            <p v-else class="text-xs text-slate-400">Pick a phase, an area, or one or more items above to enable the download buttons.</p>

            <!-- Report combo: check which of the report types above to include, then get
                 them all as ONE merged PDF (each report's own pages appended in turn) --
                 either for the phase/area/item selection above, or (ignoring that
                 selection entirely) for literally every enabled item in the whole event --
                 and optionally remember this combo as the Sahodaya's default so it's
                 pre-checked next time (any admin, any event). -->
            <div class="mt-3 pt-3 border-t border-slate-100 space-y-2">
                <label class="block text-[10px] font-bold uppercase tracking-wide text-slate-500">Report combo — merged into one PDF</label>
                <div class="flex flex-wrap gap-x-4 gap-y-1.5">
                    <label v-for="type in reportTypeOptions" :key="type.key" class="flex items-center gap-1.5 text-xs text-slate-600 cursor-pointer select-none">
                        <input type="checkbox" :value="type.key" v-model="comboSelectedTypes" class="rounded border-slate-300">
                        {{ type.label }}
                    </label>
                </div>
                <div class="flex flex-wrap items-center gap-2 pt-1">
                    <button type="button" class="btn-primary text-xs !py-1.5 !px-4" :disabled="!comboSelectedTypes.length || !bulkSelectionActive"
                            :title="!bulkSelectionActive ? 'Pick a phase, an area, or one or more items above first' : ''" @click="downloadCombo(false)">
                        ⬇️ Download merged PDF
                    </button>
                    <button type="button" class="btn-secondary text-xs !py-1.5 !px-4" :disabled="!comboSelectedTypes.length || !bulkSelectionActive"
                            :title="!bulkSelectionActive ? 'Pick a phase, an area, or one or more items above first' : ''" @click="previewCombo(false)">
                        👁️ Preview merged PDF
                    </button>
                    <button type="button" class="btn-secondary text-xs !py-1.5 !px-3" :disabled="savingCombo" @click="saveComboAsDefault">
                        {{ savingCombo ? 'Saving…' : '💾 Save as default for this Sahodaya' }}
                    </button>
                </div>
                <div class="flex flex-wrap items-center gap-2 pt-2 mt-2 border-t border-slate-100">
                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-500 mr-1">Whole event (ignores the filters above):</span>
                    <button type="button" class="btn-primary text-xs !py-1.5 !px-4" :disabled="!comboSelectedTypes.length" @click="downloadCombo(true)">
                        ⬇️ Download merged PDF for ALL items
                    </button>
                    <button type="button" class="btn-secondary text-xs !py-1.5 !px-4" :disabled="!comboSelectedTypes.length" @click="previewCombo(true)">
                        👁️ Preview merged PDF for ALL items
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
// `global: true` skips item_ids/phase_id/area_id entirely -- every bulk-capable
// controller already treats "no item filter at all" as "every enabled item in the
// event" (that's the original single-item_id/whole-event behavior these reports had
// before bulk selection existed), so this is a real function (not a computed) since it
// needs to branch per call, not just per reactive state.
function bulkSheetUrl(path, extraParams = {}, { global = false } = {}) {
    const params = new URLSearchParams();
    if (!global) {
        if (bulkSelectedItemIds.value.length) params.set('item_ids', bulkSelectedItemIds.value.join(','));
        if (bulkPhaseId.value) params.set('phase_id', bulkPhaseId.value);
        if (bulkAreaId.value) params.set('area_id', bulkAreaId.value);
    }
    Object.entries(extraParams).forEach(([key, value]) => params.set(key, value));
    const qs = params.toString();
    return `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/${path}${qs ? `?${qs}` : ''}`;
}

const bulkSelectionActive = computed(() => bulkSelectedItemIds.value.length > 0 || !!bulkPhaseId.value || !!bulkAreaId.value);

// Every report now supports ?preview=1 (view inline in a new tab) alongside its default
// download. attendance-sheet/timesheet actually default to preview already (see the
// comment below) -- explicit download:1 only when NOT previewing keeps that reversed.
const reportTypeOptions = [
    { key: 'judge_sheet', label: '🖨️ Judge Sheets',
        url: (o = {}) => bulkSheetUrl('reports/mark-entry-sheet', o.preview ? { preview: 1 } : {}, o) },
    { key: 'judge_sheet_no_chest', label: '🖨️ Judge Sheets — No Chest No',
        url: (o = {}) => bulkSheetUrl('reports/mark-entry-sheet', { blank_chest: 1, ...(o.preview ? { preview: 1 } : {}) }, o) },
    // A genuinely blank paper Sum Sheet (Sl No/Chest No/one column per judge/Grand
    // Total, nothing filled in) -- NOT the same as the "Digital Sum Sheet" / Online
    // Tabulation report elsewhere on the Mark Entry page, which shows the real marks
    // already entered. Only covers multi-judge items (a sum sheet has nothing to add
    // up for a single-judge item).
    { key: 'sum_sheet', label: '📊 Sum Sheet',
        url: (o = {}) => bulkSheetUrl('reports/sum-sheet', o.preview ? { preview: 1 } : {}, o) },
    { key: 'sum_sheet_no_chest', label: '📊 Sum Sheet — No Chest No',
        url: (o = {}) => bulkSheetUrl('reports/sum-sheet', { blank_chest: 1, ...(o.preview ? { preview: 1 } : {}) }, o) },
    { key: 'result_declaration', label: '📝 Result Declaration Sheet',
        url: (o = {}) => bulkSheetUrl('reports/result-declaration-sheet', o.preview ? { preview: 1 } : {}, o) },
    { key: 'chest_number_list', label: '🔢 Chest Number List',
        url: (o = {}) => bulkSheetUrl('chest-numbers/print', o.preview ? { preview: 1 } : {}, o) },
    // Chest Numbers' print() defaults to a real download (no extra param needed) unlike
    // this generic reports/export/{type} dispatcher, which defaults to preview and needs
    // download=1 for an actual attachment -- see FestReportService::export()'s $this->preview.
    { key: 'attendance_sheet', label: '📋 Attendance Sheet',
        url: (o = {}) => bulkSheetUrl('reports/export/attendance-sheet', o.preview ? {} : { download: 1 }, o) },
    { key: 'timesheet', label: '⏱️ Timesheet',
        url: (o = {}) => bulkSheetUrl('reports/export/timesheet', o.preview ? {} : { download: 1 }, o) },
];
const comboSelectedTypes = ref([...props.bulkReportCombo]);
const savingCombo = ref(false);

// One merged PDF for every checked report type (each type's own pages appended in
// turn via FPDI server-side -- see FestMarkEntryController::bulkComboPdf()), instead of
// firing a separate download/tab per type. That used to only ever deliver the first
// checked report -- browsers cap "popup/tab opens triggered by one click" at one, and
// silently drop the rest, whether via window.open() or a synthetic <a target=_blank>
// click. A single merged file sidesteps that limit entirely: there's only ever one URL
// to open, downloaded or previewed exactly like any other single report on this page.
function comboMergeUrl({ preview = false, global = false } = {}) {
    const params = new URLSearchParams();
    comboSelectedTypes.value.forEach((type) => params.append('report_types[]', type));
    if (!global) {
        if (bulkSelectedItemIds.value.length) params.set('item_ids', bulkSelectedItemIds.value.join(','));
        if (bulkPhaseId.value) params.set('phase_id', bulkPhaseId.value);
        if (bulkAreaId.value) params.set('area_id', bulkAreaId.value);
    }
    if (preview) params.set('preview', 1);
    return `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/bulk-combo-pdf?${params.toString()}`;
}

function downloadCombo(global = false) {
    if (!comboSelectedTypes.value.length) return;
    window.location.href = comboMergeUrl({ global });
}

function previewCombo(global = false) {
    if (!comboSelectedTypes.value.length) return;
    window.open(comboMergeUrl({ preview: true, global }), '_blank');
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
