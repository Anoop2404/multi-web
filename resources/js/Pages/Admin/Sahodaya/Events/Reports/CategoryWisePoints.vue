<template>
    <SahodayaEventsLayout :title="`${event.title} — Category-wise Points`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Category-wise Points`" eyebrow="Reports"
                    description="Every category on its own, unmerged — a school x item points table per category, plus each item's participant breakdown." />

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="category-wise-points" />

        <div v-if="childEventOptions.length" class="flex flex-wrap items-center gap-2 mb-4" role="tablist" aria-label="Region">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 mr-1">Region:</span>
            <button v-for="opt in childEventOptions" :key="opt.value" type="button" role="tab"
                    class="px-3.5 py-1.5 rounded-xl text-sm font-semibold border transition"
                    :class="String(event.id) === opt.value
                        ? 'bg-slate-900 text-white border-slate-900'
                        : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                    @click="switchEvent(opt.value)">
                {{ opt.label }}
            </button>
        </div>

        <div class="card mb-4 px-5 py-3 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="section-title text-sm !mb-0">Final result summary</h3>
                <p class="text-xs text-slate-500">Overall top 3 schools, then the top 3 schools of every category on its own page / sheet.</p>
            </div>
            <div class="flex items-center gap-2">
                <a :href="`${base.replace('category-wise-points', 'final-result-summary')}/pdf?preview=1`" target="_blank" rel="noopener" class="btn-secondary text-xs">👁️ Preview PDF</a>
                <a :href="`${base.replace('category-wise-points', 'final-result-summary')}/pdf`" class="btn-secondary text-xs">⬇️ PDF</a>
                <a :href="`${base.replace('category-wise-points', 'final-result-summary')}/xls`" class="btn-secondary text-xs">⬇️ Excel</a>
            </div>
        </div>

        <div v-if="!categories.length" class="card p-8 text-center text-slate-400 text-sm">
            No categorized items found for this event.
        </div>

        <template v-else>
            <div class="flex flex-wrap gap-2 mb-5" role="tablist">
                <button v-for="cat in categories" :key="cat.key" type="button" role="tab"
                        class="px-4 py-2 rounded-xl text-sm font-semibold border transition"
                        :class="activeKey === cat.key
                            ? 'bg-slate-900 text-white border-slate-900'
                            : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                        @click="activeKey = cat.key">
                    {{ cat.label }}
                    <span class="ml-1 text-xs opacity-70">({{ cat.items.length }})</span>
                    <span v-if="cat.excluded_from_overall"
                          class="ml-1.5 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide"
                          :class="activeKey === cat.key ? 'bg-amber-400 text-amber-950' : 'bg-amber-100 text-amber-700'">
                        Excl.
                    </span>
                </button>
            </div>

            <div v-if="activeCategory && activeCategory.excluded_from_overall" class="mb-4 px-4 py-2.5 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-800">
                This category is excluded from the event's OVERALL/Championship total (Settings → Aggregation) — its own points table below is still accurate, it just isn't counted toward the combined ranking.
            </div>

            <div v-if="activeCategory" class="card card--flush overflow-hidden mb-6">
                <div class="px-5 py-3 border-b bg-slate-50/80 flex items-center justify-between gap-3 flex-wrap">
                    <h3 class="section-title text-sm !mb-0">{{ activeCategory.label }} — Points Table</h3>
                    <div class="flex items-center gap-2">
                        <a :href="categoryPdfUrl(activeCategory, true)" target="_blank" rel="noopener" class="btn-secondary text-xs">👁️ Preview PDF</a>
                        <a :href="categoryPdfUrl(activeCategory, false)" class="btn-secondary text-xs">⬇️ PDF</a>
                        <a :href="categoryXlsUrl(activeCategory)" class="btn-secondary text-xs">⬇️ Excel</a>
                        <span class="w-px h-4 bg-slate-200 mx-1"></span>
                        <a :href="`${categorySummaryPdfUrl(activeCategory)}?preview=1`" target="_blank" rel="noopener" class="btn-secondary text-xs">👁️ Totals Only</a>
                        <a :href="categorySummaryPdfUrl(activeCategory)" class="btn-secondary text-xs">⬇️ Totals Only (PDF)</a>
                        <a :href="categorySummaryXlsUrl(activeCategory)" class="btn-secondary text-xs">⬇️ Totals Only (Excel)</a>
                    </div>
                </div>

                <div v-if="pointsTableLoading" class="p-8 text-center text-slate-400 text-sm">Loading points table…</div>
                <div v-else-if="!pointsTable || !pointsTable.schools.length" class="p-8 text-center text-slate-400 text-sm">No results recorded yet for this category.</div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-xs border-collapse">
                        <thead>
                            <tr>
                                <th class="sticky left-0 z-20 bg-slate-900 text-white p-2 text-center border-r border-slate-700 w-10">Rank</th>
                                <th class="sticky left-10 z-20 bg-slate-900 text-white p-2.5 text-left border-r border-slate-700 min-w-[11rem]">School</th>
                                <th v-for="item in pointsTable.items" :key="item.id" :title="itemFullLabel(item)"
                                    class="bg-slate-700 text-white p-1.5 text-center border-l border-slate-600 font-medium align-bottom">
                                    <span class="[writing-mode:vertical-rl] rotate-180 whitespace-nowrap inline-block">{{ itemHeaderLabel(item) }}</span>
                                </th>
                                <th class="sticky right-0 z-20 bg-indigo-900 text-white p-2.5 text-center border-l border-slate-700 min-w-[4rem]">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(school, idx) in pointsTable.schools" :key="school.school_id" :class="idx % 2 ? 'bg-slate-50/60' : 'bg-white'">
                                <td class="sticky left-0 z-10 p-2 text-center font-semibold text-slate-500 border-r border-slate-200" :class="idx % 2 ? 'bg-slate-50' : 'bg-white'">{{ school.rank }}</td>
                                <td class="sticky left-10 z-10 p-2.5 font-bold text-slate-800 border-r border-slate-200" :class="idx % 2 ? 'bg-slate-50' : 'bg-white'">{{ school.school_name.toUpperCase() }}</td>
                                <td v-for="item in pointsTable.items" :key="item.id" class="p-1.5 text-center tabular-nums border-l border-slate-100 text-slate-600" :title="cellBreakdownLabel(school, item.id)">
                                    {{ cellDisplay(school, item.id) }}
                                </td>
                                <td class="sticky right-0 z-10 p-2.5 text-center tabular-nums border-l border-slate-200 bg-indigo-100 font-black text-indigo-900">{{ school.subtotal }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="activeCategory" class="card card--flush overflow-hidden">
                <div class="px-5 py-3 border-b bg-slate-50/80">
                    <h3 class="section-title text-sm !mb-0">{{ activeCategory.label }} — Items</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table w-full text-sm">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Item Code</th>
                                <th>Gender</th>
                                <th>Type</th>
                                <th class="text-right">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in activeCategory.items" :key="item.id">
                                <td class="font-medium text-slate-900">{{ item.title }}</td>
                                <td class="font-mono text-xs text-slate-500">{{ item.item_code ?? '—' }}</td>
                                <td class="text-xs text-slate-600">{{ genderLabel(item.gender) }}</td>
                                <td class="text-xs text-slate-600">{{ typeLabel(item.participant_type) }}</td>
                                <td class="text-right">
                                    <button type="button"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-200 text-slate-500 hover:text-slate-900 hover:border-slate-300 transition"
                                            title="View points breakdown"
                                            @click="openBreakdown(item)">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!activeCategory.items.length">
                                <td colspan="5" class="p-8 text-center text-slate-400">No items in this category.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <CategoryPointsBreakdownModal :open="modalOpen" :fetch-url="modalFetchUrl" :item-title="modalItemTitle" @close="closeBreakdown" />

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import CategoryPointsBreakdownModal from '@/Components/reports/CategoryPointsBreakdownModal.vue';
import { genderLabel as sharedGenderLabel } from '@/support/festItemEligibility.js';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    categories: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
    childEvents: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/category-wise-points`;

const activeKey = ref(props.categories[0]?.key ?? null);
const activeCategory = computed(() => props.categories.find((c) => c.key === activeKey.value) ?? null);

const childEventOptions = computed(() => props.childEvents.map((ev) => ({ value: String(ev.id), label: ev.short_title || ev.title })));

function switchEvent(value) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${value}/reports/category-wise-points`);
}

function categoryPdfUrl(category, preview) {
    return `${base}/${encodeURIComponent(category.key)}/pdf${preview ? '?preview=1' : ''}`;
}

function categoryXlsUrl(category) {
    return `${base}/${encodeURIComponent(category.key)}/xls`;
}

function categorySummaryPdfUrl(category) {
    return `${base}/${encodeURIComponent(category.key)}/summary-pdf`;
}

function categorySummaryXlsUrl(category) {
    return `${base}/${encodeURIComponent(category.key)}/summary-xls`;
}

function genderLabel(gender) {
    return sharedGenderLabel(gender) ?? 'Mixed';
}

function typeLabel(participantType) {
    return ['team', 'group', 'pair', 'trio'].includes(participantType) ? 'Group' : 'Individual';
}

function typeAbbr(participantType) {
    return ['team', 'group', 'pair', 'trio'].includes(participantType) ? 'Grp' : 'Ind';
}

function itemHeaderLabel(item) {
    const base = item.item_code ? `${item.item_code} — ${item.title}` : item.title;
    return `${base} · ${genderLabel(item.gender)} · ${typeAbbr(item.participant_type)}`;
}

function itemFullLabel(item) {
    return `${item.title} — ${genderLabel(item.gender)}, ${typeLabel(item.participant_type)}`;
}

// Same "5+3" formatting as the Consolidated Report — a school winning an item's 1st
// AND 3rd shows how the total was actually earned, not just the bare sum.
function cellDisplay(school, itemId) {
    const breakdown = school.breakdown_by_item?.[itemId] ?? [];
    if (breakdown.length > 1) {
        return breakdown.join('+');
    }
    const points = school.points_by_item?.[itemId] ?? 0;

    return points > 0 ? points : '';
}

function cellBreakdownLabel(school, itemId) {
    const breakdown = school.breakdown_by_item?.[itemId] ?? [];

    return breakdown.length > 1 ? `${breakdown.length} results: ${breakdown.join(' + ')}` : '';
}

const pointsTable = ref(null);
const pointsTableLoading = ref(false);

async function fetchPointsTable(categoryKey) {
    if (!categoryKey) {
        pointsTable.value = null;
        return;
    }
    pointsTableLoading.value = true;
    try {
        const response = await fetch(`${base}/${encodeURIComponent(categoryKey)}/table`, { headers: { Accept: 'application/json' } });
        pointsTable.value = response.ok ? await response.json() : null;
    } catch {
        pointsTable.value = null;
    } finally {
        pointsTableLoading.value = false;
    }
}

watch(activeKey, (key) => fetchPointsTable(key), { immediate: true });

const modalOpen = ref(false);
const modalFetchUrl = ref(null);
const modalItemTitle = ref('');

function openBreakdown(item) {
    modalFetchUrl.value = `${base}/${item.id}/participants`;
    modalItemTitle.value = item.title;
    modalOpen.value = true;
}

function closeBreakdown() {
    modalOpen.value = false;
    modalFetchUrl.value = null;
}
</script>
