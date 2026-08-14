<template>
    <SahodayaEventsLayout :title="`${event.title} — Bulk Event Item Limits & Caps`" :sahodaya="sahodaya" :event="event"
                          :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        
        <PageHeader :title="`${event.title} — Item Limits & Caps`" eyebrow="Bulk Limits Management"
                    description="Configure school entry caps and team squad sizes across all event items.">
            <template #actions>
                <div class="flex items-center gap-2">
                    <Link :href="`${base}/items`" class="btn-secondary text-xs flex items-center gap-1.5">
                        <span>📋 Standard Items List</span>
                    </Link>
                    <button type="button" class="btn-primary text-xs flex items-center gap-1.5 shadow-sm"
                            :disabled="saving || !hasChanges" @click="saveAll">
                        <span v-if="saving">Saving...</span>
                        <span v-else>💾 Save All Caps ({{ changedCount }})</span>
                    </button>
                </div>
            </template>
        </PageHeader>

        <SportsSetupSubNav v-if="isSports" :sahodaya-id="sahodaya.id" :event-id="event.id"
                           :event="event" active="items" class="mb-4" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="items" />

        <!-- Sub Tab Bar to switch between Items List & Limit Caps -->
        <div class="flex items-center gap-2 mb-5 border-b border-slate-200 pb-3">
            <Link :href="`${base}/items`"
                  class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 bg-slate-100 text-slate-700 hover:bg-slate-200">
                <span>📋 Item Catalog & Metadata</span>
            </Link>
            <Link :href="`${base}/items/caps`"
                  class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 bg-indigo-600 text-white shadow-sm">
                <span>⚡ Bulk Limit Caps & Squad Rules</span>
            </Link>
        </div>

        <!-- EXPLANATION BANNER: School Limits & Squad Caps -->
        <div class="rounded-2xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-5 text-white shadow-md border border-indigo-900/50 mb-5 space-y-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 text-indigo-200 font-bold text-sm">
                    <span>💡 Event Limits & Team Squad Caps</span>
                </div>
            </div>
            <div class="grid md:grid-cols-2 gap-3 text-xs text-slate-300 pt-1">
                <div class="bg-white/5 p-3 rounded-xl border border-white/10 space-y-1">
                    <p class="font-bold text-white flex items-center gap-1.5 text-xs">
                        <span>🏫</span> School Limit (Max/School)
                    </p>
                    <p class="text-[11px] leading-relaxed text-slate-300">
                        Maximum participant entries a single member school is permitted to register for this specific item (e.g. max 2 students per school).
                    </p>
                </div>
                <div class="bg-white/5 p-3 rounded-xl border border-white/10 space-y-1">
                    <p class="font-bold text-white flex items-center gap-1.5 text-xs">
                        <span>👥</span> Team Squad & Sub Count
                    </p>
                    <p class="text-[11px] leading-relaxed text-slate-300">
                        Minimum players required to field, maximum squad size cap, and substitute (sub) count for team, group, pair, and relay items.
                    </p>
                </div>
            </div>
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
                <!-- Preset: School Limit -->
                <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm space-y-2">
                    <label class="font-bold text-slate-800 block">🏫 Batch Set School Limit</label>
                    <div class="flex items-center gap-2">
                        <input v-model.number="batchForm.max_per_school" type="number" min="1" class="field text-xs flex-1" placeholder="e.g. 2">
                        <button type="button" class="btn-secondary text-xs shrink-0 !bg-indigo-50 !text-indigo-700 hover:!bg-indigo-100 font-bold"
                                @click="applyBatch('max_per_school')">
                            Apply School Limit
                        </button>
                    </div>
                </div>

                <!-- Preset: Team Squad Rules -->
                <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm space-y-2">
                    <label class="font-bold text-slate-800 block">👥 Batch Set Team Squad & Sub Count</label>
                    <div class="grid grid-cols-3 gap-1.5">
                        <input v-model.number="batchForm.min_group_size" type="number" min="1" class="field text-xs !px-2" placeholder="Min">
                        <input v-model.number="batchForm.max_group_size" type="number" min="1" class="field text-xs !px-2" placeholder="Max">
                        <input v-model.number="batchForm.max_subs" type="number" min="0" class="field text-xs !px-2" placeholder="Subs">
                    </div>
                    <button type="button" class="btn-secondary text-xs w-full !bg-emerald-50 !text-emerald-800 hover:!bg-emerald-100 font-bold mt-1"
                            @click="applyBatch('team_squad')">
                        Apply to Team/Group Items
                    </button>
                </div>
            </div>
        </div>

        <!-- SEARCH & FILTERS BAR -->
        <div class="card !p-4 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <div class="flex flex-wrap items-center gap-2 flex-1 min-w-[14rem]">
                    <input v-model="searchQuery" type="search" class="field flex-1 min-w-[10rem] max-w-sm"
                           placeholder="Search by item name, code, discipline..." autocomplete="off">
                    <select v-model="filterType" class="field text-xs w-auto">
                        <option value="">All Participant Types</option>
                        <option value="individual">Individual Only</option>
                        <option value="multi">Team / Group / Pair / Trio</option>
                    </select>
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

            <!-- TABLE GRID -->
            <EmptyState v-if="!filteredItems.length" title="No items match filter" description="Try clearing filters or search term." icon="🔍" class="py-8" />

            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse divide-y divide-slate-100">
                    <thead>
                        <tr class="bg-slate-50/90 text-slate-600 font-bold uppercase text-[10px] tracking-wider border-b border-slate-200">
                            <th class="py-3 px-3 w-10 text-center">
                                <input type="checkbox" :checked="isAllSelected" class="rounded border-slate-300 text-indigo-600" @change="toggleSelectAll">
                            </th>
                            <th class="py-3 px-3">Item Details</th>
                            <th class="py-3 px-3">Type</th>
                            <th class="py-3 px-3 w-36 text-center bg-indigo-50/50 text-indigo-900 border-x border-indigo-100">
                                School Limit<br><span class="normal-case text-[9px] font-normal text-slate-500">(Max / School)</span>
                            </th>
                            <th class="py-3 px-3 text-center bg-emerald-50/50 text-emerald-950" colspan="3">
                                Team Squad Rules<br><span class="normal-case text-[9px] font-normal text-slate-500">(Group / Team Items Only)</span>
                            </th>
                        </tr>
                        <tr class="bg-emerald-50/30 text-slate-500 font-bold text-[10px] uppercase border-b border-slate-200 text-center">
                            <th colspan="4"></th>
                            <th class="py-1.5 px-2 w-28">Min Members</th>
                            <th class="py-1.5 px-2 w-28">Max Members</th>
                            <th class="py-1.5 px-2 w-28">Sub Count</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="item in filteredItems" :key="item.id"
                            class="hover:bg-slate-50/80 transition"
                            :class="[
                                selectedIds.includes(item.id) ? 'bg-indigo-50/20' : '',
                                isDirty(item.id) ? 'bg-amber-50/30' : ''
                            ]">
                            <!-- Checkbox -->
                            <td class="py-3 px-3 text-center">
                                <input type="checkbox" :value="item.id" v-model="selectedIds" class="rounded border-slate-300 text-indigo-600">
                            </td>

                            <!-- Item Info -->
                            <td class="py-3 px-3">
                                <div class="font-bold text-slate-900 flex items-center gap-1.5">
                                    <span>{{ item.title }}</span>
                                    <span v-if="isDirty(item.id)" class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-amber-100 text-amber-800">
                                        Edited
                                    </span>
                                </div>
                                <div class="text-[11px] text-slate-500 font-mono mt-0.5 flex flex-wrap gap-2">
                                    <span v-if="item.item_code">Code: {{ item.item_code }}</span>
                                    <span>{{ itemCategoryLabel(item) }}</span>
                                </div>
                            </td>

                            <!-- Participant Type -->
                            <td class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold capitalize border inline-block"
                                      :class="isMultiPerson(item) ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-100 text-slate-700 border-slate-200'">
                                    {{ item.participant_type }}
                                </span>
                            </td>

                            <!-- School Limit Input -->
                            <td class="py-2 px-3 text-center bg-indigo-50/30 border-x border-indigo-100/60">
                                <input type="number" min="1" class="field text-center font-bold text-xs !py-1 !px-2 w-20 mx-auto"
                                       v-model.number="capsState[item.id].max_per_school" placeholder="1">
                            </td>

                            <!-- Team Squad Rule Inputs -->
                            <template v-if="isMultiPerson(item)">
                                <td class="py-2 px-2 text-center bg-emerald-50/20">
                                    <input type="number" min="1" class="field text-center font-semibold text-xs !py-1 !px-1.5 w-18 mx-auto"
                                           v-model.number="capsState[item.id].min_group_size" placeholder="Min">
                                </td>
                                <td class="py-2 px-2 text-center bg-emerald-50/20">
                                    <input type="number" min="1" class="field text-center font-semibold text-xs !py-1 !px-1.5 w-18 mx-auto"
                                           v-model.number="capsState[item.id].max_group_size" placeholder="Max">
                                </td>
                                <td class="py-2 px-2 text-center bg-emerald-50/20">
                                    <input type="number" min="0" class="field text-center font-semibold text-xs !py-1 !px-1.5 w-18 mx-auto"
                                           v-model.number="capsState[item.id].max_subs" placeholder="Subs">
                                </td>
                            </template>
                            <template v-else>
                                <td class="py-2 px-2 text-center text-slate-300 text-[11px] bg-slate-50/50" colspan="3">
                                    N/A (Individual)
                                </td>
                            </template>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- STICKY FLOATING SAVE BAR -->
        <div v-if="hasChanges" class="fixed bottom-4 right-4 z-40 bg-slate-900 text-white px-5 py-3 rounded-2xl shadow-2xl flex items-center gap-4 border border-slate-700">
            <div>
                <p class="font-bold text-sm">Unsaved Limit Cap Changes</p>
                <p class="text-xs text-slate-300">{{ changedCount }} item(s) edited</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="btn-secondary text-xs !bg-slate-800 !text-slate-300 hover:!bg-slate-700" @click="resetCapsState">
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
import { computed, ref, reactive, watch } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, taxonomy: Object, itemsByLevel: Object, groupedItems: Object,
    activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const isSports = computed(() => props.event.event_type === 'sports');

const searchQuery = ref('');
const filterType = ref('');
const selectedIds = ref([]);
const saving = ref(false);

const itemsList = computed(() => {
    if (props.itemsByLevel) {
        return Object.values(props.itemsByLevel).flat();
    }
    if (props.groupedItems) {
        return Object.values(props.groupedItems).flat();
    }
    return props.event.items ?? [];
});

const MULTI_TYPES = ['team', 'group', 'pair', 'trio'];
function isMultiPerson(item) {
    return MULTI_TYPES.includes(item.participant_type);
}

function itemCategoryLabel(item) {
    if (isSports.value && item.age_group) {
        return props.taxonomy?.age_group?.[item.age_group] ?? item.age_group;
    }
    if (item.class_group) {
        return props.taxonomy?.class_group?.[item.class_group] ?? item.class_group;
    }
    if (item.category) {
        return props.taxonomy?.arts_category?.[item.category] ?? item.category;
    }
    return 'General';
}

const capsState = reactive({});
const originalState = reactive({});

function initCapsState() {
    for (const item of itemsList.value) {
        const c = item.criteria_json ?? {};
        const minGroup = c.min_squad ?? (item.min_group_size ?? c.min_playing ?? null);
        const maxGroup = c.max_squad ?? (item.max_group_size ?? null);
        const maxSubs = c.max_subs ?? (item.standbys ?? c.standbys ?? null);

        const row = {
            max_per_school: item.max_per_school ?? null,
            min_group_size: minGroup,
            max_group_size: maxGroup,
            max_subs: maxSubs,
        };
        capsState[item.id] = { ...row };
        originalState[item.id] = { ...row };
    }
}

watch(itemsList, () => {
    initCapsState();
}, { immediate: true });

function resetCapsState() {
    initCapsState();
}

function isDirty(itemId) {
    const curr = capsState[itemId];
    const orig = originalState[itemId];
    if (!curr || !orig) return false;

    return (
        (curr.max_per_school ?? null) !== (orig.max_per_school ?? null) ||
        (curr.min_group_size ?? null) !== (orig.min_group_size ?? null) ||
        (curr.max_group_size ?? null) !== (orig.max_group_size ?? null) ||
        (curr.max_subs ?? null) !== (orig.max_subs ?? null)
    );
}

const changedCount = computed(() => {
    return itemsList.value.filter(item => isDirty(item.id)).length;
});

const hasChanges = computed(() => changedCount.value > 0);

const filteredItems = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return itemsList.value.filter(item => {
        if (q) {
            const haystack = `${item.title} ${item.item_code ?? ''} ${item.category ?? ''} ${item.age_group ?? ''} ${item.class_group ?? ''}`.toLowerCase();
            if (!haystack.includes(q)) return false;
        }
        if (filterType.value === 'individual' && isMultiPerson(item)) return false;
        if (filterType.value === 'multi' && !isMultiPerson(item)) return false;
        return true;
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
    max_per_school: null,
    min_group_size: null,
    max_group_size: null,
    max_subs: null,
});

function applyBatch(field) {
    const targetItems = selectedIds.value.length
        ? filteredItems.value.filter(i => selectedIds.value.includes(i.id))
        : filteredItems.value;

    for (const item of targetItems) {
        if (!capsState[item.id]) continue;

        if (field === 'max_per_school' && batchForm.max_per_school !== null) {
            capsState[item.id].max_per_school = batchForm.max_per_school;
        }
        if (field === 'team_squad' && isMultiPerson(item)) {
            if (batchForm.min_group_size !== null) capsState[item.id].min_group_size = batchForm.min_group_size;
            if (batchForm.max_group_size !== null) capsState[item.id].max_group_size = batchForm.max_group_size;
            if (batchForm.max_subs !== null) capsState[item.id].max_subs = batchForm.max_subs;
        }
    }
}

function saveAll() {
    if (!hasChanges.value) return;

    const dirtyRows = itemsList.value
        .filter(item => isDirty(item.id))
        .map(item => ({
            id: item.id,
            max_per_school: capsState[item.id].max_per_school,
            min_group_size: capsState[item.id].min_group_size,
            max_group_size: capsState[item.id].max_group_size,
            max_subs: capsState[item.id].max_subs,
            standbys: capsState[item.id].max_subs,
        }));

    saving.value = true;

    router.post(`${base}/items/bulk-caps`, {
        items: dirtyRows,
    }, {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false;
        },
    });
}
</script>
