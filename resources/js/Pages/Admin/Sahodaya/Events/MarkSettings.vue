<template>
    <SahodayaEventsLayout :title="`${event.title} — Mark Settings`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                          :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Mark Settings`" eyebrow="Mark settings"
                    description="Configure how each item gets marked — number of judges, scoring criteria columns, and total marks.">
            <template #actions>
                <div class="flex items-center gap-2">
                    <Link :href="bulkSettingsUrl" class="btn-secondary text-xs flex items-center gap-1.5">
                        <span>⚡ Bulk Total Marks & Judges</span>
                    </Link>
                    <Link :href="marksUrl" class="btn-secondary text-xs">← Back to Mark Entry</Link>
                </div>
            </template>
        </PageHeader>

        <SportsSetupSubNav v-if="isSports" :sahodaya-id="sahodaya.id" :event-id="event.id"
                           :event="event" active="mark-settings" class="mb-4" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="mark-settings" class="mb-4" />

        <!-- Sub Tab Bar to switch between per-item settings & the Bulk editor -->
        <div class="flex items-center gap-2 mb-5 border-b border-slate-200 pb-3">
            <Link :href="settingsBaseUrl"
                  class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 bg-indigo-600 text-white shadow-sm">
                <span>⚙️ Per-Item Settings & Criteria</span>
            </Link>
            <Link :href="bulkSettingsUrl"
                  class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 bg-slate-100 text-slate-700 hover:bg-slate-200">
                <span>⚡ Bulk Total Marks & Judges</span>
            </Link>
        </div>

        <div class="card !p-4 space-y-3 mb-5">
            <div v-if="childEvents.length" class="flex flex-wrap items-center gap-2 pb-2 border-b border-slate-100">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ isSports ? 'Sport Event / Region:' : 'Phase / Region:' }}</label>
                <SearchableSelect :model-value="String(event.id)" @update:model-value="switchSportEvent"
                                  :options="childEventOptions" :all-option="false" placeholder="Select event"
                                  class="w-64" />
            </div>

            <ReportItemSearchSelect :items="flatItems" :model-value="props.selectedItemId"
                                    label="Competition Item"
                                    :all-items-label="`All ${flatItems.length} items`"
                                    search-placeholder="Search by item name or code…"
                                    :status-for="itemConfiguredMark"
                                    @select="onItemSelect" />
            <p class="text-right text-[11px] text-slate-400">
                ✓ {{ configuredCount }}/{{ flatItems.length }} items configured
            </p>
        </div>

        <EmptyState v-if="!props.selectedItemId" title="Pick an item"
                    description="Select a competition item above to configure its marking settings." icon="⚙️" />

        <div v-else class="card !p-5 space-y-4">
            <h3 class="section-title !mb-0">{{ selectedItem?.title }}</h3>

            <div class="flex items-center gap-3">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">
                    Total Marks
                </label>
                <input v-model.number="totalMarksDraft" type="number" min="0" step="0.5" placeholder="e.g. 100"
                       class="field text-xs !py-1 w-28">
                <p class="text-[11px] text-slate-400">
                    Optional. Set this to switch Grade Master's bands for this item to a percentage range instead of a raw score range.
                </p>
            </div>

            <div class="flex items-center gap-3 pt-3 border-t border-slate-100">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">
                    No. of Judges
                </label>
                <input v-model.number="judgeCountDraft" type="number" min="1" max="20"
                       class="field text-xs !py-1 w-20">
                <p class="text-[11px] text-slate-400">
                    1 = single evaluator, entered directly online. 2+ = each judge gets their own printed sheet,
                    plus a Sum Sheet, and you type each judge's paper subtotal into the table below.
                </p>
            </div>

            <div class="flex items-center gap-3 pb-3">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">
                    Copy from item
                </label>
                <div class="flex-1 max-w-sm">
                    <ReportItemSearchSelect :items="copySourceOptions" :model-value="copyFromItemId"
                                            all-items-label="Select an item to copy from"
                                            search-placeholder="Search by item name or code…"
                                            @select="(id) => { copyFromItemId = id; }" />
                </div>
                <button type="button" class="btn-secondary text-xs !py-1.5 !px-3 self-end"
                        :disabled="!copyFromItemId || copyingCriteria" @click="copyCriteriaFromItem">
                    {{ copyingCriteria ? 'Copying…' : 'Copy' }}
                </button>
            </div>

            <div v-if="rubricTemplates.length" class="flex items-center gap-3 pb-4 border-b border-slate-100">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">
                    Apply template
                </label>
                <SearchableSelect v-model="applyTemplateId" :options="rubricTemplates"
                                  :all-option="true" all-label="Select a rubric template…"
                                  class="flex-1 max-w-sm" />
                <button type="button" class="btn-secondary text-xs !py-1.5 !px-3"
                        :disabled="!applyTemplateId || applyingTemplate" @click="applyRubricTemplate">
                    {{ applyingTemplate ? 'Applying…' : 'Apply' }}
                </button>
                <a :href="`/sahodaya-admin/${sahodaya.id}/scoring-rubric-templates`" target="_blank"
                   class="text-[11px] text-indigo-600 hover:underline whitespace-nowrap">Manage templates ↗</a>
            </div>

            <div class="space-y-2">
                <p class="text-xs text-slate-500">
                    Scoring columns printed on each judge's paper sheet (e.g. "Content", "Presentation"). SL NO,
                    CHEST NO. and TOTAL are always included automatically — name the criteria columns in between.
                </p>
                <div v-if="columnDraft.length" class="grid grid-cols-12 gap-2 text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1 px-1 items-center">
                    <div class="col-span-1 text-center">#</div>
                    <div class="col-span-7">Column / Criterion Name</div>
                    <div class="col-span-3">Max Marks</div>
                    <div class="col-span-1 text-right"></div>
                </div>
                <div v-for="(row, idx) in columnDraft" :key="row._key" class="grid grid-cols-12 gap-2 items-center px-1">
                    <div class="col-span-1 text-center text-xs font-bold text-slate-400">{{ idx + 1 }}.</div>
                    <div class="col-span-7">
                        <input v-model="row.label" type="text" :placeholder="`e.g. Content / Presentation / Criterion ${idx + 1}`"
                               class="field text-xs w-full">
                    </div>
                    <div class="col-span-3">
                        <input v-model.number="row.max_score" type="number" min="0.5" step="0.5" placeholder="10"
                               class="field text-xs w-full">
                    </div>
                    <div class="col-span-1 text-right">
                        <button type="button" class="text-rose-500 hover:underline text-xs font-semibold"
                                @click="removeColumnRow(idx)">
                            Remove
                        </button>
                    </div>
                </div>
                <button type="button" class="btn-secondary text-xs !py-1 !px-3 mt-1" @click="addColumnRow">
                    + Add Column
                </button>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                <button type="button" class="btn-primary text-xs !py-1.5 !px-4"
                        :disabled="savingColumns" @click="saveColumnConfig">
                    {{ savingColumns ? 'Saving…' : 'Save Settings' }}
                </button>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import ReportItemSearchSelect from '@/Components/reports/ReportItemSearchSelect.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    headItemGroups: { type: Array, default: () => [] },
    hasItemHeads: { type: Boolean, default: false },
    selectedHeadId: { type: [String, Number], default: null },
    selectedItemId: { type: [Number, String], default: null },
    selectedItem: { type: Object, default: null },
    configuredItemIds: { type: Array, default: () => [] },
    rubricTemplates: { type: Array, default: () => [] },
    criteria: { type: Array, default: () => [] },
    judgeCount: { type: Number, default: 1 },
    childEvents: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
});

const isSports = computed(() => props.event?.event_type === 'sports');
const selectedItem = computed(() => props.selectedItem);

const marksUrl = computed(() => {
    let url = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/marks`;
    if (props.selectedItemId) url += `?item_id=${props.selectedItemId}`;
    return url;
});
const settingsBaseUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/mark-settings`);
const bulkSettingsUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/mark-settings/bulk`);

const flatItems = computed(() => (props.headItemGroups ?? []).flatMap((h) => h.items ?? []));

const childEventOptions = computed(() => (props.childEvents ?? []).map((ev) => ({
    value: String(ev.id),
    label: ev.short_title || ev.title,
})));

function onItemSelect(itemId) {
    router.get(settingsBaseUrl.value, itemId ? { item_id: itemId } : {}, { preserveScroll: true, preserveState: false });
}

function switchSportEvent(value) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${value}/mark-settings`);
}

function itemConfiguredMark(item) {
    return (props.configuredItemIds ?? []).includes(item.id) ? '✓' : null;
}
const configuredCount = computed(() => (props.configuredItemIds ?? []).length);

let draftKeySeq = 0;
const totalMarksDraft = ref(props.selectedItem?.total_marks ?? null);
const judgeCountDraft = ref(props.judgeCount ?? 1);
const columnDraft = reactive(
    (props.criteria ?? []).map((c) => ({
        _key: draftKeySeq++,
        id: c.id,
        label: c.label,
        max_score: c.max_score ?? 10,
    }))
);

function addColumnRow() {
    columnDraft.push({ _key: draftKeySeq++, id: null, label: '', max_score: 10 });
}

function removeColumnRow(idx) {
    columnDraft.splice(idx, 1);
}

const savingColumns = ref(false);

function saveColumnConfig() {
    if (!props.selectedItemId) return;
    savingColumns.value = true;
    const rows = columnDraft.map((r, idx) => ({
        id: r.id,
        label: (r.label ?? '').trim() || `Criterion ${idx + 1}`,
        max_score: r.max_score || 10,
    }));

    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/items/${props.selectedItemId}/mark-criteria`,
        { judge_count: judgeCountDraft.value || 1, criteria: rows, total_marks: totalMarksDraft.value || null },
        {
            preserveScroll: true,
            onFinish: () => {
                savingColumns.value = false;
            },
        }
    );
}

const copySourceOptions = computed(() => (props.event?.items ?? [])
    .filter((it) => it.is_enabled !== false && String(it.id) !== String(props.selectedItemId)));
const copyFromItemId = ref(null);
const copyingCriteria = ref(false);

function copyCriteriaFromItem() {
    if (!copyFromItemId.value || !props.selectedItemId) return;
    copyingCriteria.value = true;
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/items/${props.selectedItemId}/mark-criteria/copy`,
        { source_item_id: copyFromItemId.value },
        {
            preserveScroll: true,
            onFinish: () => {
                copyingCriteria.value = false;
            },
        }
    );
}

const applyTemplateId = ref('');
const applyingTemplate = ref(false);

function applyRubricTemplate() {
    if (!applyTemplateId.value || !props.selectedItemId) return;
    applyingTemplate.value = true;
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/items/${props.selectedItemId}/mark-criteria/apply-template`,
        { template_id: applyTemplateId.value },
        {
            preserveScroll: true,
            onFinish: () => {
                applyingTemplate.value = false;
            },
        }
    );
}
</script>
