<template>
    <SahodayaEventsLayout :title="`${event.title} — Category & Item-wise Report`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Category & Item-wise Consolidated Report`" eyebrow="Reports"
                    description="Every school's points per item, grouped by category, with subtotal and overall columns — matches the printed result-sheet format.">
            <template #actions>
                <a :href="`${exportBase}/category-item-matrix-pdf`" class="btn-secondary text-sm">⬇️ PDF</a>
                <a :href="`${exportBase}/category-item-matrix-xls`" class="btn-secondary text-sm">⬇️ Excel</a>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="category-item-matrix" />

        <div v-if="childEvents.length" class="card mb-4 !py-3 flex flex-wrap items-center gap-2">
            <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Region:</label>
            <SearchableSelect :model-value="String(event.id)" @update:model-value="switchEvent" :options="regionOptions"
                :all-option="false" placeholder="Select region" class="text-xs w-64 font-semibold" />
        </div>

        <div v-if="!schools.length" class="card p-8 text-center text-slate-400 text-sm">
            No results recorded yet for this event.
        </div>

        <div v-else class="card card--flush overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs border-collapse">
                    <thead>
                        <tr>
                            <th class="sticky left-0 z-20 bg-slate-900 text-white p-2.5 text-left border-r border-slate-700 min-w-[13rem]">Item</th>
                            <th v-for="school in schools" :key="school.school_id"
                                class="bg-slate-800 text-white p-2 text-center border-l border-slate-700 font-bold whitespace-nowrap">
                                {{ school.school_name.toUpperCase() }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template v-for="cat in categories" :key="cat.key">
                            <tr>
                                <td :colspan="schools.length + 1" class="sticky left-0 z-10 bg-slate-800 text-white p-2 text-left font-bold">
                                    {{ cat.label }}
                                </td>
                            </tr>
                            <template v-for="head in cat.heads" :key="`${cat.key}-${head.head_label}`">
                                <tr>
                                    <td :colspan="schools.length + 1" class="sticky left-0 z-10 bg-slate-600 text-white p-1.5 pl-4 text-left font-semibold">
                                        {{ head.head_label }}
                                    </td>
                                </tr>
                                <tr v-for="(item, idx) in head.items" :key="item.id" :class="idx % 2 ? 'bg-slate-50/60' : 'bg-white'">
                                    <td class="sticky left-0 z-10 p-2 text-slate-700 border-r border-slate-200 whitespace-nowrap overflow-hidden text-ellipsis max-w-[13rem]"
                                        :class="idx % 2 ? 'bg-slate-50' : 'bg-white'" :title="item.title">
                                        {{ item.item_code ? `${item.item_code} — ${item.title}` : item.title }}
                                    </td>
                                    <td v-for="school in schools" :key="school.school_id" class="p-1.5 text-center tabular-nums border-l border-slate-100 text-slate-600"
                                        :title="cellBreakdownLabel(school, item.id)">
                                        {{ cellDisplay(school, item.id) }}
                                    </td>
                                </tr>
                            </template>
                            <tr>
                                <td class="sticky left-0 z-10 p-2 text-left font-bold bg-indigo-50 text-indigo-900 border-r border-slate-200">
                                    {{ cat.label }} — Subtotal
                                </td>
                                <td v-for="school in schools" :key="school.school_id" class="p-1.5 text-center tabular-nums border-l border-slate-100 bg-indigo-50 font-bold text-indigo-900">
                                    {{ school.category_totals[cat.key] ?? 0 }}
                                </td>
                            </tr>
                        </template>
                        <tr>
                            <td class="sticky left-0 z-10 p-2.5 text-left font-black bg-indigo-900 text-white border-r border-slate-700">OVERALL</td>
                            <td v-for="school in schools" :key="school.school_id" class="p-2.5 text-center tabular-nums border-l border-slate-700 bg-indigo-900 font-black text-white">
                                {{ school.overall }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    categories: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
    childEvents: { type: Array, default: () => [] },
});

const exportBase = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/export`;

const regionOptions = computed(() => props.childEvents.map(ev => ({
    value: String(ev.id),
    label: ev.short_title || ev.title,
})));

// A school winning an item's 1st AND 3rd (two separate participants/groups both
// placing) shows as "5+3" rather than a bare "8", so the sheet shows how the total
// was actually earned — same formatting FestEventReportAnalyticsService::
// formatMatrixCell() applies to the PDF/Excel exports.
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

function switchEvent(value) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${value}/reports/category-item-matrix`);
}
</script>
