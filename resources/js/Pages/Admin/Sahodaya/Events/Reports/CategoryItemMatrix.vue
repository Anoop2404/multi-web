<template>
    <SahodayaEventsLayout :title="`${event.title} — Category & Item-wise Report`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Category & Item-wise Consolidated Report`" eyebrow="Reports"
                    description="Every school's points per item, grouped by category, with subtotal and overall columns — matches the printed result-sheet format.">
            <template #actions>
                <a :href="`${exportBase}/category-item-matrix-pdf`" class="btn-secondary text-sm">⬇️ PDF</a>
                <a :href="`${exportBase}/category-item-matrix-xls`" class="btn-secondary text-sm">⬇️ Excel</a>
                <a :href="`${exportBase}/category-totals-pdf`" class="btn-secondary text-sm">⬇️ Category Totals (PDF)</a>
                <a :href="`${exportBase}/category-totals-xls`" class="btn-secondary text-sm">⬇️ Category Totals (Excel)</a>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="category-item-matrix" />

        <div v-if="regionOptions.length" class="flex flex-wrap items-center gap-2 mb-4" role="tablist" aria-label="Region">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 mr-1">Region:</span>
            <button v-for="opt in regionOptions" :key="opt.value" type="button" role="tab"
                    class="px-3.5 py-1.5 rounded-xl text-sm font-semibold border transition"
                    :class="String(event.id) === opt.value
                        ? 'bg-slate-900 text-white border-slate-900'
                        : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                    @click="switchEvent(opt.value)">
                {{ opt.label }}
            </button>
        </div>

        <div v-if="phaseOptions.length" class="card p-4 mb-4 flex flex-wrap items-center gap-x-5 gap-y-2">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Category Totals — per Phase:</span>
            <div v-for="phase in phaseOptions" :key="phase.id" class="flex items-center gap-1.5">
                <span class="text-xs font-semibold text-slate-600">{{ phase.short_title || phase.title }}</span>
                <a :href="phaseCategoryTotalsUrl(phase.id, 'pdf')" class="btn-secondary text-xs">PDF</a>
                <a :href="phaseCategoryTotalsUrl(phase.id, 'xls')" class="btn-secondary text-xs">Excel</a>
            </div>
        </div>

        <div v-if="!schools.length" class="card p-8 text-center text-slate-400 text-sm">
            No results recorded yet for this event.
        </div>

        <template v-else>
        <div class="card card--flush overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs border-collapse">
                    <thead>
                        <tr>
                            <th rowspan="3" class="sticky left-0 z-20 bg-slate-900 text-white p-2.5 text-center border-r border-slate-700 w-10">Rank</th>
                            <th rowspan="3" class="sticky left-10 z-20 bg-slate-900 text-white p-2.5 text-left border-r border-slate-700 min-w-[11rem]">School</th>
                            <th v-for="cat in categories" :key="cat.key" :colspan="categoryItemCount(cat) + 1"
                                class="bg-slate-800 text-white p-2 text-center border-l border-slate-700 font-bold">
                                {{ cat.label }}
                            </th>
                            <th rowspan="3" class="sticky right-0 z-20 bg-indigo-900 text-white p-2.5 text-center border-l border-slate-700 min-w-[5rem]">OVERALL</th>
                        </tr>
                        <tr>
                            <template v-for="cat in categories" :key="`${cat.key}-heads`">
                                <th v-for="head in cat.heads" :key="head.head_label" :colspan="head.items.length"
                                    class="text-white p-1.5 text-center border-l border-slate-600 font-semibold" style="background-color:#3d5a80">
                                    {{ head.head_label }}
                                </th>
                                <th rowspan="2" class="bg-indigo-800 text-white p-1.5 text-center border-l border-slate-600 font-bold">Sub</th>
                            </template>
                        </tr>
                        <tr>
                            <template v-for="cat in categories" :key="`${cat.key}-items`">
                                <template v-for="head in cat.heads" :key="`${cat.key}-${head.head_label}`">
                                    <th v-for="item in head.items" :key="item.id" :title="itemFullLabel(item)"
                                        class="bg-slate-700 text-white p-1.5 text-center border-l border-slate-600 font-medium align-bottom">
                                        <span class="[writing-mode:vertical-rl] rotate-180 whitespace-nowrap inline-block">
                                            {{ itemHeaderLabel(item) }}
                                        </span>
                                    </th>
                                </template>
                            </template>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(school, idx) in schools" :key="school.school_id" :class="idx % 2 ? 'bg-slate-50/60' : 'bg-white'">
                            <td class="sticky left-0 z-10 p-2.5 text-center font-semibold text-slate-500 border-r border-slate-200"
                                :class="idx % 2 ? 'bg-slate-50' : 'bg-white'">
                                {{ school.rank }}
                            </td>
                            <td class="sticky left-10 z-10 p-2.5 font-bold text-slate-800 border-r border-slate-200"
                                :class="idx % 2 ? 'bg-slate-50' : 'bg-white'">
                                {{ school.school_name.toUpperCase() }}
                            </td>
                            <template v-for="cat in categories" :key="`${cat.key}-${school.school_id}`">
                                <template v-for="head in cat.heads" :key="`${cat.key}-${head.head_label}-${school.school_id}`">
                                    <td v-for="item in head.items" :key="item.id" class="p-1.5 text-center tabular-nums border-l border-slate-100 text-slate-600"
                                        :title="cellBreakdownLabel(school, item.id)">
                                        {{ cellDisplay(school, item.id) }}
                                    </td>
                                </template>
                                <td class="p-1.5 text-center tabular-nums border-l border-slate-100 bg-indigo-50 font-bold text-indigo-900">
                                    {{ school.category_totals[cat.key] ?? 0 }}
                                </td>
                            </template>
                            <td class="sticky right-0 z-10 p-2.5 text-center tabular-nums border-l border-slate-200 bg-indigo-100 font-black text-indigo-900">
                                {{ school.overall }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        </template>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import { genderLabel as sharedGenderLabel } from '@/support/festItemEligibility.js';

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

// The combined "All Regions" hub is one of childEvents' own rows (is_hub: true) --
// excluded here since a per-phase Category Totals download only makes sense for an
// actual phase/region, not the hub that already has its own Category Totals buttons
// in the page header above.
const phaseOptions = computed(() => props.childEvents.filter(ev => !ev.is_hub));

function phaseCategoryTotalsUrl(eventId, format) {
    return `/sahodaya-admin/${props.sahodaya.id}/events/${eventId}/reports/export/category-totals-${format}`;
}

function categoryItemCount(cat) {
    return cat.heads.reduce((sum, head) => sum + head.items.length, 0);
}

function typeAbbr(participantType) {
    return ['team', 'group', 'pair', 'trio'].includes(participantType) ? 'Grp' : 'Ind';
}

function genderLabel(gender) {
    return sharedGenderLabel(gender) ?? 'Mixed';
}

function itemHeaderLabel(item) {
    const base = item.item_code ? `${item.item_code} — ${item.title}` : item.title;
    return `${base} · ${genderLabel(item.gender)} · ${typeAbbr(item.participant_type)}`;
}

function itemFullLabel(item) {
    const type = typeAbbr(item.participant_type) === 'Grp' ? 'Group' : 'Individual';
    return `${item.title} — ${genderLabel(item.gender)}, ${type}`;
}

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
