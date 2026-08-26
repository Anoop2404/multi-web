<template>
    <SahodayaEventsLayout :title="`${event.title} — Bulk Mark Settings`" :sahodaya="sahodaya" :event="event"
                          :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">

        <PageHeader :title="`${event.title} — Mark Settings`" eyebrow="Bulk Mark Settings"
                    description="Set Total Marks and Judge Count across every item in this event in one pass, instead of opening each item one at a time.">
            <template #actions>
                <div class="flex items-center gap-2">
                    <Link :href="`${base}/mark-settings`" class="btn-secondary text-xs flex items-center gap-1.5">
                        <span>⚙️ Per-Item Settings</span>
                    </Link>
                    <button type="button" class="btn-primary text-xs flex items-center gap-1.5 shadow-sm"
                            :disabled="saving || !hasChanges" @click="saveAll">
                        <span v-if="saving">Saving...</span>
                        <span v-else>💾 Save All ({{ changedCount }})</span>
                    </button>
                </div>
            </template>
        </PageHeader>

        <SportsSetupSubNav v-if="isSports" :sahodaya-id="sahodaya.id" :event-id="event.id"
                           :event="event" active="mark-settings" class="mb-4" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="mark-settings" />

        <!-- Sub Tab Bar to switch between per-item Mark Settings & this Bulk editor -->
        <div class="flex items-center gap-2 mb-5 border-b border-slate-200 pb-3">
            <Link :href="`${base}/mark-settings`"
                  class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 bg-slate-100 text-slate-700 hover:bg-slate-200">
                <span>⚙️ Per-Item Settings & Criteria</span>
            </Link>
            <Link :href="`${base}/mark-settings/bulk`"
                  class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 bg-indigo-600 text-white shadow-sm">
                <span>⚡ Bulk Total Marks & Judges</span>
            </Link>
        </div>

        <div v-if="childEvents.length" class="card !p-4 space-y-3 mb-5">
            <div class="flex flex-wrap items-center gap-2">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ isSports ? 'Sport Event / Region:' : 'Region:' }}</label>
                <SearchableSelect :model-value="String(event.id)" @update:model-value="switchEvent"
                                  :options="childEventOptions" :all-option="false"
                                  placeholder="Select event/region" class="w-64" />
            </div>
            <p class="text-[11px] text-slate-500">
                This applies only to items in the region/event selected above — switch above to configure another region separately.
            </p>
        </div>

        <!-- BULK BATCH SETTER ACTION BAR -->
        <div class="card !p-4 mb-5 bg-indigo-50/40 border-indigo-100 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-indigo-100/80 pb-2">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-950 flex items-center gap-1">
                        <span>⚡ Quick Batch Preset</span>
                    </span>
                    <span class="text-[11px] text-slate-500 font-medium">
                        ({{ selectedIds.length ? `${selectedIds.length} items selected` : 'Applies to all filtered items' }})
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

            <div class="grid sm:grid-cols-2 gap-3 text-xs">
                <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm space-y-2">
                    <label class="font-bold text-slate-800 block">🎯 Batch Set Total Marks</label>
                    <div class="flex items-center gap-2">
                        <input v-model.number="batchForm.total_marks" type="number" min="0" step="0.5" class="field text-xs flex-1" placeholder="e.g. 100">
                        <button type="button" class="btn-secondary text-xs shrink-0 !bg-indigo-50 !text-indigo-700 hover:!bg-indigo-100 font-bold"
                                @click="applyBatch('total_marks')">
                            Apply
                        </button>
                    </div>
                </div>

                <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm space-y-2">
                    <label class="font-bold text-slate-800 block">👥 Batch Set Judge Count</label>
                    <div class="flex items-center gap-2">
                        <input v-model.number="batchForm.judge_count" type="number" min="1" max="20" class="field text-xs flex-1" placeholder="e.g. 2">
                        <button type="button" class="btn-secondary text-xs shrink-0 !bg-amber-50 !text-amber-800 hover:!bg-amber-100 font-bold"
                                @click="applyBatch('judge_count')">
                            Apply
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- SEARCH & FILTERS BAR -->
        <div class="card !p-4 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <div class="flex flex-wrap items-center gap-2 flex-1 min-w-[14rem]">
                    <input v-model="searchQuery" type="search" class="field flex-1 min-w-[10rem] max-w-sm"
                           placeholder="Search by item name or code..." autocomplete="off">
                    <span class="text-xs text-slate-500 font-medium tabular-nums">
                        Showing {{ filteredItems.length }} of {{ itemsList.length }} items
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" class="btn-primary text-xs" :disabled="saving || !hasChanges" @click="saveAll">
                        <span v-if="saving">Saving...</span>
                        <span v-else>💾 Save Changes ({{ changedCount }})</span>
                    </button>
                </div>
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
                            <th class="py-3 px-3 w-36 text-center bg-indigo-50/50 text-indigo-900 border-x border-indigo-100">Total Marks</th>
                            <th class="py-3 px-3 w-36 text-center bg-amber-50/50 text-amber-950">Judge Count</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="item in filteredItems" :key="item.id"
                            class="hover:bg-slate-50/80 transition"
                            :class="[
                                selectedIds.includes(item.id) ? 'bg-indigo-50/20' : '',
                                isDirty(item.id) ? 'bg-amber-50/30' : ''
                            ]">
                            <td class="py-3 px-3 text-center">
                                <input type="checkbox" :value="item.id" v-model="selectedIds" class="rounded border-slate-300 text-indigo-600">
                            </td>

                            <td class="py-3 px-3">
                                <div class="font-bold text-slate-900 flex items-center gap-1.5">
                                    <span>{{ item.title }}</span>
                                    <span v-if="item.category_label" class="text-[10px] font-semibold text-slate-500">({{ item.category_label }})</span>
                                    <span v-if="isDirty(item.id)" class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-amber-100 text-amber-800">
                                        Edited
                                    </span>
                                </div>
                                <div v-if="item.item_code" class="text-[11px] text-slate-500 font-mono mt-0.5">
                                    Code: {{ item.item_code }}
                                </div>
                            </td>

                            <td class="py-2 px-3 text-center bg-indigo-50/30 border-x border-indigo-100/60">
                                <input type="number" min="0" step="0.5" class="field text-center font-bold text-xs !py-1 !px-2 w-24 mx-auto"
                                       v-model.number="settingsState[item.id].total_marks" placeholder="e.g. 100">
                            </td>

                            <td class="py-2 px-3 text-center bg-amber-50/30">
                                <input type="number" min="1" max="20" class="field text-center font-bold text-xs !py-1 !px-2 w-20 mx-auto"
                                       v-model.number="settingsState[item.id].judge_count" placeholder="1">
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- STICKY FLOATING SAVE BAR -->
        <div v-if="hasChanges" class="fixed bottom-4 right-4 z-40 bg-slate-900 text-white px-5 py-3 rounded-2xl shadow-2xl flex items-center gap-4 border border-slate-700">
            <div>
                <p class="font-bold text-sm">Unsaved Mark Settings Changes</p>
                <p class="text-xs text-slate-300">{{ changedCount }} item(s) edited</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="btn-secondary text-xs !bg-slate-800 !text-slate-300 hover:!bg-slate-700" @click="resetSettingsState">
                    Reset
                </button>
                <button type="button" class="btn-primary text-xs !bg-indigo-500 hover:!bg-indigo-400 font-bold" :disabled="saving" @click="saveAll">
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
    childEvents: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const isSports = computed(() => props.event.event_type === 'sports');

function switchEvent(value) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${value}/mark-settings/bulk`);
}

const childEventOptions = computed(() => props.childEvents.map(ev => ({
    value: String(ev.id),
    label: ev.short_title || ev.title,
})));

const searchQuery = ref('');
const selectedIds = ref([]);
const saving = ref(false);

const itemsList = computed(() => props.items ?? []);

const settingsState = reactive({});
const originalState = reactive({});

function initSettingsState() {
    for (const item of itemsList.value) {
        const row = {
            total_marks: item.total_marks ?? null,
            judge_count: item.judge_count ?? 1,
        };
        settingsState[item.id] = { ...row };
        originalState[item.id] = { ...row };
    }
}

watch(itemsList, () => {
    initSettingsState();
}, { immediate: true });

function resetSettingsState() {
    initSettingsState();
}

function isDirty(itemId) {
    const curr = settingsState[itemId];
    const orig = originalState[itemId];
    if (!curr || !orig) return false;

    return (
        (curr.total_marks ?? null) !== (orig.total_marks ?? null) ||
        (curr.judge_count ?? null) !== (orig.judge_count ?? null)
    );
}

const changedCount = computed(() => itemsList.value.filter(item => isDirty(item.id)).length);
const hasChanges = computed(() => changedCount.value > 0);

const filteredItems = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return itemsList.value;
    return itemsList.value.filter(item => {
        const haystack = `${item.title} ${item.item_code ?? ''}`.toLowerCase();
        return haystack.includes(q);
    });
});

const isAllSelected = computed(() => {
    if (!filteredItems.value.length) return false;
    return filteredItems.value.every(item => selectedIds.value.includes(item.id));
});

function toggleSelectAll() {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = filteredItems.value.map(item => item.id);
    }
}

function selectAll() {
    selectedIds.value = filteredItems.value.map(item => item.id);
}

function clearSelection() {
    selectedIds.value = [];
}

const batchForm = reactive({
    total_marks: null,
    judge_count: null,
});

function applyBatch(field) {
    const targetItems = selectedIds.value.length
        ? filteredItems.value.filter(i => selectedIds.value.includes(i.id))
        : filteredItems.value;

    for (const item of targetItems) {
        if (!settingsState[item.id]) continue;

        if (field === 'total_marks' && batchForm.total_marks !== null) {
            settingsState[item.id].total_marks = batchForm.total_marks;
        }
        if (field === 'judge_count' && batchForm.judge_count !== null) {
            settingsState[item.id].judge_count = batchForm.judge_count;
        }
    }
}

function saveAll() {
    if (!hasChanges.value) return;

    const dirtyRows = itemsList.value
        .filter(item => isDirty(item.id))
        .map(item => ({
            id: item.id,
            total_marks: settingsState[item.id].total_marks,
            judge_count: settingsState[item.id].judge_count,
        }));

    saving.value = true;

    router.post(`${base}/mark-settings/bulk`, {
        items: dirtyRows,
    }, {
        preserveScroll: true,
        // Inertia's partial-reload props update doesn't reliably re-trigger the
        // itemsList watcher here, so re-sync explicitly once the fresh `items`
        // prop has actually landed — otherwise the just-saved values vanish
        // from the inputs even though they're correctly persisted server-side.
        onSuccess: () => {
            initSettingsState();
        },
        onFinish: () => {
            saving.value = false;
        },
    });
}
</script>
