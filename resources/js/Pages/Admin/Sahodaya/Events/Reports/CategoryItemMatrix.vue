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
                            <th rowspan="2" class="sticky left-0 z-20 bg-slate-900 text-white p-2.5 text-left border-r border-slate-700 min-w-[11rem]">School</th>
                            <th v-for="cat in categories" :key="cat.key" :colspan="cat.items.length + 1"
                                class="bg-slate-800 text-white p-2 text-center border-l border-slate-700 font-bold">
                                {{ cat.label }}
                            </th>
                            <th rowspan="2" class="sticky right-0 z-20 bg-indigo-900 text-white p-2.5 text-center border-l border-slate-700 min-w-[5rem]">OVERALL</th>
                        </tr>
                        <tr>
                            <!-- Deliberately no per-item category label here — the parent <th> above
                                 already groups every item column under its category (cat.label), so
                                 repeating it on each item cell would just add noise. -->
                            <template v-for="cat in categories" :key="`${cat.key}-items`">
                                <th v-for="item in cat.items" :key="item.id" :title="item.title"
                                    class="bg-slate-700 text-white p-1.5 text-center border-l border-slate-600 font-medium whitespace-nowrap max-w-[6rem] overflow-hidden text-ellipsis">
                                    {{ item.item_code || item.title }}
                                </th>
                                <th class="bg-indigo-800 text-white p-1.5 text-center border-l border-slate-600 font-bold">Sub</th>
                            </template>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(school, idx) in schools" :key="school.school_id" :class="idx % 2 ? 'bg-slate-50/60' : 'bg-white'">
                            <td class="sticky left-0 z-10 p-2.5 font-bold text-slate-800 border-r border-slate-200"
                                :class="idx % 2 ? 'bg-slate-50' : 'bg-white'">
                                {{ school.school_name.toUpperCase() }}
                            </td>
                            <template v-for="cat in categories" :key="`${cat.key}-${school.school_id}`">
                                <td v-for="item in cat.items" :key="item.id" class="p-1.5 text-center tabular-nums border-l border-slate-100 text-slate-600">
                                    {{ school.points_by_item[item.id] ?? 0 }}
                                </td>
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

function switchEvent(value) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${value}/reports/category-item-matrix`);
}
</script>
