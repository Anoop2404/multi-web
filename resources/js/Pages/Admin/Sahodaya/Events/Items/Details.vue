<template>
    <SahodayaEventsLayout :title="`${event.title} — Bulk Item Code`" :sahodaya="sahodaya" :event="event"
                          :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">

        <PageHeader :title="`${event.title} — Item Code`" eyebrow="Bulk Item Code"
                    description="Fill in each item's code across the whole event at once. Category, gender, and participant type are shown for reference only — edit those from the item's own form.">
            <template #actions>
                <div class="flex items-center gap-2">
                    <Link :href="`${base}/items`" class="btn-secondary text-xs flex items-center gap-1.5">
                        <span>📋 Standard Items List</span>
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
                           :event="event" active="items" class="mb-4" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="items" />

        <!-- Sub Tab Bar -->
        <div class="flex items-center gap-2 mb-5 border-b border-slate-200 pb-3">
            <Link :href="`${base}/items`"
                  class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 bg-slate-100 text-slate-700 hover:bg-slate-200">
                <span>📋 Item Catalog & Metadata</span>
            </Link>
            <Link :href="`${base}/items/caps`"
                  class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 bg-slate-100 text-slate-700 hover:bg-slate-200">
                <span>⚡ Bulk Limit Caps & Squad Rules</span>
            </Link>
            <Link :href="`${base}/items/details`"
                  class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 bg-indigo-600 text-white shadow-sm">
                <span>🏷️ Bulk Item Code</span>
            </Link>
        </div>

        <!-- SEARCH & FILTERS -->
        <div class="card !p-4 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <div class="flex flex-wrap items-center gap-2 flex-1 min-w-[14rem]">
                    <input v-model="searchQuery" type="search" class="field flex-1 min-w-[10rem] max-w-sm"
                           placeholder="Search by item name or code..." autocomplete="off">
                    <label class="flex items-center gap-1.5 text-xs text-slate-600 font-semibold">
                        <input type="checkbox" v-model="onlyMissing" class="rounded border-slate-300 text-indigo-600">
                        <span>Only items missing a code</span>
                    </label>
                    <span class="text-xs text-slate-500 font-medium tabular-nums">
                        Showing {{ filteredItems.length ? pageStartIndex + 1 : 0 }}–{{ pageEndIndex }} of {{ filteredItems.length }} items
                        <span v-if="filteredItems.length !== itemsList.length">({{ itemsList.length }} total)</span>
                    </span>
                    <div class="flex items-center gap-1.5 text-xs text-slate-600">
                        <span>Show:</span>
                        <SearchableSelect v-model="perPage" :options="perPageOptions" :all-option="false" class="w-24" />
                    </div>
                </div>

                <button type="button" class="btn-primary text-xs" :disabled="saving || !hasChanges" @click="saveAll">
                    <span v-if="saving">Saving...</span>
                    <span v-else>💾 Save Changes ({{ changedCount }})</span>
                </button>
            </div>

            <EmptyState v-if="!filteredItems.length" title="No items match filter" description="Try clearing filters or search term." icon="🔍" class="py-8" />

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse divide-y divide-slate-100">
                    <thead>
                        <tr class="bg-slate-50/90 text-slate-600 font-bold uppercase text-[10px] tracking-wider border-b border-slate-200">
                            <th class="py-3 px-3">Item Title</th>
                            <th class="py-3 px-3 w-36 text-center">Category</th>
                            <th class="py-3 px-3 w-28 text-center">Gender</th>
                            <th class="py-3 px-3 w-32 text-center">Type</th>
                            <th class="py-3 px-3 w-36 bg-indigo-50/50 text-indigo-900 border-l border-indigo-100">Item Code</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="item in pagedItems" :key="item.id"
                            class="hover:bg-slate-50/80 transition"
                            :class="isDirty(item.id) ? 'bg-amber-50/30' : ''">
                            <td class="py-3 px-3">
                                <div class="font-bold text-slate-900 flex items-center gap-1.5">
                                    <span>{{ item.title }}</span>
                                    <span v-if="isDirty(item.id)" class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-amber-100 text-amber-800">
                                        Edited
                                    </span>
                                </div>
                            </td>

                            <!-- Indication only — edit category from the item's own form on the Item Catalog page. -->
                            <td class="py-3 px-3 text-center">
                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">
                                    {{ categoryLabel(item) }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">
                                    {{ genderLabel(item) }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded capitalize"
                                      :class="isMultiPerson(item) ? 'bg-emerald-50 text-emerald-800' : 'bg-sky-50 text-sky-800'">
                                    {{ item.participant_type }}
                                </span>
                            </td>

                            <td class="py-2 px-3 bg-indigo-50/30 border-l border-indigo-100/60">
                                <input type="text" class="field font-mono text-xs !py-1 !px-2 w-full" maxlength="20"
                                       v-model="codeState[item.id]" placeholder="e.g. ART-01">
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
        <div v-if="hasChanges" class="fixed bottom-4 right-4 z-40 bg-slate-900 text-white px-5 py-3 rounded-2xl shadow-2xl flex items-center gap-4 border border-slate-700">
            <div>
                <p class="font-bold text-sm">Unsaved Item Code Changes</p>
                <p class="text-xs text-slate-300">{{ changedCount }} item(s) edited</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="btn-secondary text-xs !bg-slate-800 !text-slate-300 hover:!bg-slate-700" @click="resetCodeState">
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
    event: Object, taxonomy: Object, groupedItems: Object,
    activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const isSports = computed(() => props.event.event_type === 'sports');

const MULTI_TYPES = ['team', 'group', 'pair', 'trio'];
function isMultiPerson(item) {
    return MULTI_TYPES.includes(item.participant_type);
}

function categoryLabel(item) {
    if (isSports.value && item.age_group) {
        return props.taxonomy?.age_group?.[item.age_group] ?? item.age_group;
    }
    if (item.class_group && item.class_group !== 'open') {
        return props.taxonomy?.class_group?.[item.class_group] ?? item.class_group;
    }
    if (item.category) {
        return props.taxonomy?.arts_category?.[item.category] ?? item.category;
    }
    return 'General';
}

function genderLabel(item) {
    if (!item.gender || item.gender === 'open') return 'Open';
    return props.taxonomy?.gender?.[item.gender] ?? item.gender;
}

const searchQuery = ref('');
const onlyMissing = ref(false);
const saving = ref(false);

const itemsList = computed(() => {
    if (props.groupedItems) {
        return Object.values(props.groupedItems).flat();
    }
    return props.event.items ?? [];
});

// Item code is the only editable field here — category/gender/participant type are shown
// read-only for context (edit those from the item's own form on the Item Catalog page).
const codeState = reactive({});
const originalState = reactive({});

function initCodeState() {
    for (const item of itemsList.value) {
        codeState[item.id] = item.item_code ?? '';
        originalState[item.id] = item.item_code ?? '';
    }
}

watch(itemsList, initCodeState, { immediate: true });

function resetCodeState() {
    initCodeState();
}

function isDirty(itemId) {
    return (codeState[itemId] || '') !== (originalState[itemId] || '');
}

const changedCount = computed(() => itemsList.value.filter(item => isDirty(item.id)).length);
const hasChanges = computed(() => changedCount.value > 0);

const filteredItems = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return itemsList.value.filter(item => {
        if (onlyMissing.value && item.item_code) return false;
        if (!q) return true;
        const haystack = `${item.title} ${item.item_code ?? ''}`.toLowerCase();
        return haystack.includes(q);
    });
});

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

watch([searchQuery, onlyMissing, perPage], () => {
    currentPage.value = 1;
});
watch(totalPages, (max) => {
    if (currentPage.value > max) currentPage.value = max;
});

function saveAll() {
    if (!hasChanges.value) return;

    const dirtyRows = itemsList.value
        .filter(item => isDirty(item.id))
        .map(item => ({
            id: item.id,
            item_code: codeState[item.id] || null,
        }));

    saving.value = true;

    router.post(`${base}/items/bulk-details`, {
        items: dirtyRows,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            initCodeState();
        },
        onFinish: () => {
            saving.value = false;
        },
    });
}
</script>
