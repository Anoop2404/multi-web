<template>
    <SahodayaEventsLayout :title="`${event.title} — Judging Setup`" :sahodaya="sahodaya" :event="event"
                          :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">

        <PageHeader :title="`${event.title} — Judging Setup`" eyebrow="Judging Setup"
                    description="One row per item — judging sheet and total marks are common across every phase/region; judge count is the one thing that can differ, set per region right in the row.">
        </PageHeader>

        <SportsSetupSubNav v-if="isSports" :sahodaya-id="sahodaya.id" :event-id="event.id"
                           :event="event" active="mark-settings" class="mb-4" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="mark-settings" />

        <!-- Sub Tab Bar -->
        <div class="flex items-center gap-2 mb-5 border-b border-slate-200 pb-3">
            <Link :href="`${base}/mark-settings`"
                  class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 bg-slate-100 text-slate-700 hover:bg-slate-200">
                <span>⚙️ Per-Item Settings & Criteria</span>
            </Link>
            <Link :href="`${base}/mark-settings/bulk`"
                  class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 bg-slate-100 text-slate-700 hover:bg-slate-200">
                <span>⚡ Bulk Total Marks & Judges</span>
            </Link>
            <Link :href="`${base}/judging-setup`"
                  class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 bg-indigo-600 text-white shadow-sm">
                <span>🧩 Judging Setup</span>
            </Link>
        </div>

        <!-- BULK RUBRIC + BATCH PANEL -->
        <div class="card !p-4 mb-5 bg-indigo-50/40 border-indigo-100 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-indigo-100/80 pb-2">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-950 flex items-center gap-1">
                        <span>⚡ Quick Batch Actions</span>
                    </span>
                    <span class="text-[11px] text-slate-500 font-medium">
                        ({{ selectedKeys.length ? `${selectedKeys.length} items selected` : 'Applies to all filtered items' }})
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="text-xs font-bold text-indigo-700 hover:text-indigo-900" @click="selectAll">
                        Select All ({{ filteredItems.length }})
                    </button>
                    <span class="text-slate-300">|</span>
                    <button type="button" class="text-xs font-semibold text-slate-500 hover:text-slate-700" @click="clearSelection">
                        Deselect All
                    </button>
                </div>
            </div>

            <p class="text-[11px] text-slate-500">
                Total Marks and the judging sheet are common across every region/phase — saving either propagates everywhere the item
                exists. Judge Count is the one field that stays per-region; batch-setting it below stages every region slot on the
                selected items, saved individually per region.
            </p>

            <div class="grid sm:grid-cols-3 gap-3 text-xs">
                <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm space-y-2">
                    <label class="font-bold text-slate-800 block">🎯 Batch Set Total Marks</label>
                    <div class="flex items-center gap-2">
                        <input v-model.number="batchForm.total_marks" type="number" min="0" step="0.5" class="field text-xs flex-1" placeholder="e.g. 100">
                        <button type="button" class="btn-secondary text-xs shrink-0 !bg-indigo-50 !text-indigo-700 hover:!bg-indigo-100 font-bold"
                                @click="applyBatchTotalMarks">
                            Apply
                        </button>
                    </div>
                </div>

                <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm space-y-2">
                    <label class="font-bold text-slate-800 block">👥 Batch Set Judge Count</label>
                    <div class="flex items-center gap-2">
                        <input v-model.number="batchForm.judge_count" type="number" min="1" max="20" class="field text-xs flex-1" placeholder="e.g. 2">
                        <button type="button" class="btn-secondary text-xs shrink-0 !bg-amber-50 !text-amber-800 hover:!bg-amber-100 font-bold"
                                @click="applyBatchJudgeCount">
                            Apply
                        </button>
                    </div>
                    <p class="text-[10px] text-slate-500">Sets every region's judge count on the selected items (each region stays independently editable after).</p>
                </div>

                <div v-if="rubricTemplates.length" class="bg-white p-3 rounded-xl border border-emerald-200 shadow-sm space-y-2">
                    <label class="font-bold text-slate-800 block">📋 Apply Judging Sheet Now</label>
                    <SearchableSelect v-model="templateForm.template_id" :options="templateOptions" :all-option="false"
                                      placeholder="Select a judging sheet..." />
                    <button type="button" class="btn-primary text-xs w-full !bg-emerald-600 hover:!bg-emerald-700 font-bold"
                            :disabled="applyingTemplate || !templateForm.template_id" @click="applyTemplateToSelection">
                        {{ applyingTemplate ? 'Applying...' : 'Apply to Selection (saves immediately)' }}
                    </button>
                    <p class="text-[10px] text-slate-500">Applies and saves right away, propagating to every region/phase the item has.</p>
                </div>
            </div>
        </div>

        <!-- SEARCH & FILTERS -->
        <div class="card !p-4 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <div class="flex flex-wrap items-center gap-2 flex-1 min-w-[14rem]">
                    <input v-model="searchQuery" type="search" class="field flex-1 min-w-[10rem] max-w-sm"
                           placeholder="Search by item name or code..." autocomplete="off">
                    <span class="text-xs text-slate-500 font-medium tabular-nums">
                        Showing {{ filteredItems.length ? pageStartIndex + 1 : 0 }}–{{ pageEndIndex }} of {{ filteredItems.length }} items
                        <span v-if="filteredItems.length !== itemsList.length">({{ itemsList.length }} total)</span>
                    </span>
                    <div class="flex items-center gap-1.5 text-xs text-slate-600">
                        <span>Show:</span>
                        <SearchableSelect v-model="perPage" :options="perPageOptions" :all-option="false" class="w-24" />
                    </div>
                </div>

                <button type="button" class="btn-primary text-xs" :disabled="saving || !hasTotalMarksChanges" @click="saveAllTotalMarks">
                    <span v-if="saving">Saving...</span>
                    <span v-else>💾 Save Total Marks ({{ changedTotalMarksCount }})</span>
                </button>
            </div>

            <EmptyState v-if="!filteredItems.length" title="No items match filter" description="Try clearing the search term." icon="🔍" class="py-8" />

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse divide-y divide-slate-100">
                    <thead>
                        <tr class="bg-slate-50/90 text-slate-600 font-bold uppercase text-[10px] tracking-wider border-b border-slate-200">
                            <th class="py-3 px-3 w-10 text-center">
                                <input type="checkbox" :checked="isAllSelected" class="rounded border-slate-300 text-indigo-600" @change="toggleSelectAll">
                            </th>
                            <th class="py-3 px-3">Item Details</th>
                            <th class="py-3 px-3 w-64 bg-emerald-50/50 text-emerald-950 border-x border-emerald-100">Judging Sheet (common)</th>
                            <th class="py-3 px-3 w-32 text-center bg-indigo-50/50 text-indigo-900 border-x border-indigo-100">Total Marks (common)</th>
                            <th class="py-3 px-3 w-48 bg-amber-50/50 text-amber-950">Judges (per region)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="item in pagedItems" :key="item.key"
                            class="hover:bg-slate-50/80 transition"
                            :class="selectedKeys.includes(item.key) ? 'bg-indigo-50/20' : ''">
                            <td class="py-3 px-3 text-center align-top">
                                <input type="checkbox" :value="item.key" v-model="selectedKeys" class="rounded border-slate-300 text-indigo-600">
                            </td>

                            <td class="py-3 px-3 align-top">
                                <div class="font-bold text-slate-900 flex items-center gap-1.5">
                                    <span>{{ item.title }}</span>
                                    <span v-if="item.category_label" class="text-[10px] font-semibold text-slate-500">({{ item.category_label }})</span>
                                </div>
                                <div v-if="item.item_code" class="text-[11px] text-slate-500 font-mono mt-0.5">
                                    Code: {{ item.item_code }}
                                </div>
                            </td>

                            <td class="py-2 px-3 bg-emerald-50/30 border-x border-emerald-100/60 align-top">
                                <div class="flex items-center gap-1.5">
                                    <SearchableSelect v-model="rowTemplateChoice[item.key]" :options="templateOptions" :all-option="false"
                                                      placeholder="Assign a sheet..." class="flex-1 min-w-[8rem]" />
                                    <button type="button" class="btn-secondary text-[10px] !py-1 !px-2 shrink-0"
                                            :disabled="rowApplying[item.key] || !rowTemplateChoice[item.key]"
                                            @click="applyTemplateToRow(item)">
                                        {{ rowApplying[item.key] ? '…' : 'Apply' }}
                                    </button>
                                </div>

                                <div class="mt-1.5">
                                    <span v-if="item.rubric_status && item.rubric_status !== 'custom'"
                                          class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800">
                                        ✓ {{ item.rubric_status }}
                                    </span>
                                    <span v-else-if="item.rubric_status === 'custom'"
                                          class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-200 text-slate-700">
                                        Custom sheet
                                    </span>
                                    <span v-else class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-red-50 text-red-600">
                                        Not set
                                    </span>
                                </div>

                                <ul v-if="item.criteria?.length" class="mt-1.5 space-y-0.5">
                                    <li v-for="(c, i) in item.criteria" :key="i" class="flex items-center justify-between gap-2 text-[10px] text-slate-600">
                                        <span class="truncate">{{ c.label }}</span>
                                        <span class="font-mono font-bold text-slate-800 shrink-0">{{ c.max_score }}</span>
                                    </li>
                                </ul>
                                <p v-if="item.criteria?.length" class="mt-1 pt-1 border-t border-emerald-200/60 text-[10px] font-bold text-emerald-900 flex items-center justify-between">
                                    <span>Per-judge total</span>
                                    <span class="font-mono">{{ item.criteria_sum }}</span>
                                </p>
                            </td>

                            <td class="py-2 px-3 text-center bg-indigo-50/30 border-x border-indigo-100/60 align-top">
                                <input type="number" min="0" step="0.5" class="field text-center font-bold text-xs !py-1 !px-2 w-24 mx-auto"
                                       v-model.number="totalMarksState[item.key]" placeholder="e.g. 100">
                                <button v-if="isTotalMarksDirty(item.key)" type="button"
                                        class="mt-1 btn-secondary text-[10px] !py-1 !px-2 !bg-indigo-100 !text-indigo-900 hover:!bg-indigo-200 font-bold w-full"
                                        :disabled="totalMarksSaving[item.key]" @click="saveTotalMarks(item)">
                                    {{ totalMarksSaving[item.key] ? '…' : '💾 Save' }}
                                </button>
                            </td>

                            <td class="py-2 px-3 bg-amber-50/30 align-top">
                                <div v-for="region in item.regions" :key="region.item_id" class="flex items-center gap-1.5 mb-1 last:mb-0">
                                    <span v-if="region.region_label" class="text-[10px] font-semibold text-amber-900 w-20 truncate shrink-0">
                                        {{ region.region_label }}
                                    </span>
                                    <input type="number" min="1" max="20" class="field text-center font-bold text-xs !py-1 !px-2 w-16 shrink-0"
                                           v-model.number="judgeState[region.item_id]" placeholder="1">
                                    <button v-if="isJudgeCountDirty(region.item_id)" type="button"
                                            class="btn-secondary text-[10px] !py-1 !px-1.5 !bg-amber-100 !text-amber-900 hover:!bg-amber-200 font-bold shrink-0"
                                            :disabled="judgeSaving[region.item_id]" @click="saveJudgeCount(region)">
                                        {{ judgeSaving[region.item_id] ? '…' : '💾' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Footer -->
            <div v-if="totalPages > 1" class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-600">
                <span>Page {{ currentPage }} of {{ totalPages }}</span>
                <div class="flex items-center gap-1">
                    <button type="button" @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1"
                            class="px-2.5 py-1 rounded border border-slate-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50">
                        Previous
                    </button>
                    <button type="button" @click="currentPage = Math.min(totalPages, currentPage + 1)" :disabled="currentPage === totalPages"
                            class="px-2.5 py-1 rounded border border-slate-300 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50">
                        Next
                    </button>
                </div>
            </div>
        </div>

        <!-- STICKY FLOATING SAVE BAR -->
        <div v-if="hasTotalMarksChanges" class="fixed bottom-4 right-4 z-40 bg-slate-900 text-white px-5 py-3 rounded-2xl shadow-2xl flex items-center gap-4 border border-slate-700">
            <div>
                <p class="font-bold text-sm">Unsaved Total Marks Changes</p>
                <p class="text-xs text-slate-300">{{ changedTotalMarksCount }} item(s) edited</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="btn-secondary text-xs !bg-slate-800 !text-slate-300 hover:!bg-slate-700" @click="resetTotalMarksState">
                    Reset
                </button>
                <button type="button" class="btn-primary text-xs !bg-indigo-500 hover:!bg-indigo-400 font-bold" :disabled="saving" @click="saveAllTotalMarks">
                    {{ saving ? 'Saving...' : 'Save All Changes' }}
                </button>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, items: { type: Array, default: () => [] },
    rubricTemplates: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const isSports = computed(() => props.event.event_type === 'sports');

function eventUrl(eventId) {
    return `/sahodaya-admin/${props.sahodaya.id}/events/${eventId}`;
}

const searchQuery = ref('');
const selectedKeys = ref([]);
const saving = ref(false);

// One row per item (grouped across the whole event family server-side) — item.key
// identifies the row, item.representative_id/representative_event_id is the underlying
// FestEventItem to write Total Marks/the judging sheet to (the server-side sync then
// propagates to every region/phase), and item.regions lists each region's own judge count.
const itemsList = computed(() => props.items ?? []);

// Local edit state seeded ONCE per key/item id and never overwritten by a later props
// refresh (e.g. from saving a different row, which reloads this whole listing) — otherwise
// an in-progress, unsaved edit elsewhere on the page would be silently wiped out. Values only
// change here through this page's own save actions, which re-baseline immediately on success.
const totalMarksState = reactive({});
const totalMarksOriginal = reactive({});
const totalMarksSaving = reactive({});
const judgeState = reactive({});
const judgeOriginal = reactive({});
const judgeSaving = reactive({});
const rowTemplateChoice = reactive({});
const rowApplying = reactive({});

function initState() {
    for (const item of itemsList.value) {
        if (totalMarksState[item.key] === undefined) {
            totalMarksState[item.key] = item.total_marks ?? null;
            totalMarksOriginal[item.key] = item.total_marks ?? null;
            totalMarksSaving[item.key] = false;
        }
        if (rowTemplateChoice[item.key] === undefined) {
            rowTemplateChoice[item.key] = item.matched_template_id ? String(item.matched_template_id) : '';
            rowApplying[item.key] = false;
        }
        for (const region of item.regions ?? []) {
            if (judgeState[region.item_id] === undefined) {
                judgeState[region.item_id] = region.judge_count ?? 1;
                judgeOriginal[region.item_id] = region.judge_count ?? 1;
                judgeSaving[region.item_id] = false;
            }
        }
    }
}

watch(itemsList, initState, { immediate: true });

function resetTotalMarksState() {
    for (const item of itemsList.value) {
        totalMarksState[item.key] = totalMarksOriginal[item.key];
    }
}

function isTotalMarksDirty(key) {
    return (totalMarksState[key] ?? null) !== (totalMarksOriginal[key] ?? null);
}

function isJudgeCountDirty(itemId) {
    return (judgeState[itemId] ?? null) !== (judgeOriginal[itemId] ?? null);
}

const changedTotalMarksCount = computed(() => itemsList.value.filter(item => isTotalMarksDirty(item.key)).length);
const hasTotalMarksChanges = computed(() => changedTotalMarksCount.value > 0);

const filteredItems = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return itemsList.value;
    return itemsList.value.filter(item => {
        const haystack = `${item.title} ${item.item_code ?? ''}`.toLowerCase();
        return haystack.includes(q);
    });
});

// Kalotsav events run to ~140 items at once, each row mounting a SearchableSelect (its own
// document click-listener + computed options) — only the current page's rows are ever
// mounted; "Select All (N)" still targets every filtered item, not just the visible page.
const perPage = ref(25);
const currentPage = ref(1);
const perPageOptions = [
    { value: 25, label: '25' },
    { value: 50, label: '50' },
    { value: 100, label: '100' },
    { value: 'all', label: 'All' },
];

const perPageNum = computed(() => perPage.value === 'all' ? (filteredItems.value.length || 1) : Number(perPage.value));
const totalPages = computed(() => Math.max(1, Math.ceil(filteredItems.value.length / perPageNum.value)));
const pageStartIndex = computed(() => (currentPage.value - 1) * perPageNum.value);
const pageEndIndex = computed(() => Math.min(currentPage.value * perPageNum.value, filteredItems.value.length));
const pagedItems = computed(() => {
    if (perPage.value === 'all') return filteredItems.value;
    return filteredItems.value.slice(pageStartIndex.value, pageStartIndex.value + perPageNum.value);
});

watch([searchQuery, perPage], () => {
    currentPage.value = 1;
});
watch(totalPages, (max) => {
    if (currentPage.value > max) currentPage.value = max;
});

const isAllSelected = computed(() => {
    if (!pagedItems.value.length) return false;
    return pagedItems.value.every(item => selectedKeys.value.includes(item.key));
});

function toggleSelectAll() {
    if (isAllSelected.value) {
        const pageKeys = new Set(pagedItems.value.map(item => item.key));
        selectedKeys.value = selectedKeys.value.filter(key => !pageKeys.has(key));
    } else {
        const merged = new Set(selectedKeys.value);
        pagedItems.value.forEach(item => merged.add(item.key));
        selectedKeys.value = Array.from(merged);
    }
}

function selectAll() {
    selectedKeys.value = filteredItems.value.map(item => item.key);
}

function clearSelection() {
    selectedKeys.value = [];
}

const batchForm = reactive({
    total_marks: null,
    judge_count: null,
});

function targetSelection() {
    return selectedKeys.value.length
        ? filteredItems.value.filter(i => selectedKeys.value.includes(i.key))
        : filteredItems.value;
}

function applyBatchTotalMarks() {
    if (batchForm.total_marks === null) return;
    for (const item of targetSelection()) {
        totalMarksState[item.key] = batchForm.total_marks;
    }
}

function applyBatchJudgeCount() {
    if (batchForm.judge_count === null) return;
    for (const item of targetSelection()) {
        for (const region of item.regions ?? []) {
            judgeState[region.item_id] = batchForm.judge_count;
        }
    }
}

/** Wraps an Inertia POST as a Promise so multiple region groups can be saved one after another. */
function postInertia(url, data) {
    return new Promise((resolve) => {
        router.post(url, data, { preserveScroll: true, preserveState: true, onFinish: resolve });
    });
}

/** Total marks is common across the family — save writes to the representative item and the server propagates it everywhere. */
async function saveTotalMarks(item) {
    if (!isTotalMarksDirty(item.key)) return;

    totalMarksSaving[item.key] = true;
    await postInertia(`${eventUrl(item.representative_event_id)}/mark-settings/sync-total-marks`, {
        items: [{ id: item.representative_id, total_marks: totalMarksState[item.key] }],
    });
    totalMarksOriginal[item.key] = totalMarksState[item.key];
    totalMarksSaving[item.key] = false;
}

async function saveAllTotalMarks() {
    if (!hasTotalMarksChanges.value || saving.value) return;

    const dirtyItems = itemsList.value.filter(item => isTotalMarksDirty(item.key));
    saving.value = true;

    // Each save already propagates to the whole family server-side, so these are independent,
    // single-item calls rather than needing to be grouped by event first.
    for (const item of dirtyItems) {
        // eslint-disable-next-line no-await-in-loop
        await postInertia(`${eventUrl(item.representative_event_id)}/mark-settings/sync-total-marks`, {
            items: [{ id: item.representative_id, total_marks: totalMarksState[item.key] }],
        });
        totalMarksOriginal[item.key] = totalMarksState[item.key];
    }

    saving.value = false;
}

/** Judge count is per-region — saves only that one region's item, no propagation. */
async function saveJudgeCount(region) {
    if (!isJudgeCountDirty(region.item_id)) return;

    judgeSaving[region.item_id] = true;
    await postInertia(`${eventUrl(region.event_id)}/mark-settings/judge-count`, {
        items: [{ id: region.item_id, judge_count: judgeState[region.item_id] }],
    });
    judgeOriginal[region.item_id] = judgeState[region.item_id];
    judgeSaving[region.item_id] = false;
}

const templateOptions = computed(() => (props.rubricTemplates ?? []).map(t => ({
    value: String(t.id),
    label: t.name,
})));

const templateForm = reactive({
    template_id: '',
});

const applyingTemplate = ref(false);

/** Bulk template apply targets each selected item's representative — the server-side sync then reaches every region/phase on its own. */
async function applyTemplateToSelection() {
    if (!templateForm.template_id) return;

    const targets = selectedKeys.value.length
        ? filteredItems.value.filter(i => selectedKeys.value.includes(i.key))
        : filteredItems.value;

    if (!targets.length) return;

    applyingTemplate.value = true;
    const templateId = Number(templateForm.template_id);

    const byEvent = new Map();
    for (const item of targets) {
        if (!byEvent.has(item.representative_event_id)) byEvent.set(item.representative_event_id, []);
        byEvent.get(item.representative_event_id).push(item);
    }

    for (const [eventId, items] of byEvent) {
        // eslint-disable-next-line no-await-in-loop
        await postInertia(`${eventUrl(eventId)}/mark-settings/bulk-apply-template`, {
            template_id: templateId,
            item_ids: items.map(i => i.representative_id),
        });
        for (const item of items) {
            rowTemplateChoice[item.key] = String(templateId);
        }
    }

    applyingTemplate.value = false;
}

async function applyTemplateToRow(item) {
    const templateId = rowTemplateChoice[item.key];
    if (!templateId) return;

    rowApplying[item.key] = true;
    await postInertia(`${eventUrl(item.representative_event_id)}/items/${item.representative_id}/mark-criteria/apply-template`, {
        template_id: Number(templateId),
    });
    rowApplying[item.key] = false;
}
</script>
