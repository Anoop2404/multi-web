<template>
    <SahodayaEventsLayout :title="`${event.title} — Unique Participant Counts`" :sahodaya="sahodaya" :event="event"
                          :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Unique Participant Counts`" eyebrow="Reports"
                    description="Deduplicated unique student participation broken down category-wise (by class range) and school-wise, with bulk category totals.">
            <template #actions>
                <ReportDownloadButtons :pdf-url="pdfUrl" :xls-url="xlsUrl" />
            </template>
        </PageHeader>

        <!-- KPI Metric Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 mb-6">
            <div class="card card--muted !py-3.5 text-center">
                <p class="text-xl font-bold text-slate-800">{{ totals.total_schools ?? 0 }}</p>
                <p class="text-xs text-slate-500 mt-0.5">Participating Schools</p>
            </div>
            <div class="card card--muted !py-3.5 text-center bg-indigo-50/50 border-indigo-100">
                <p class="text-xl font-bold text-indigo-700">{{ totals.total_unique_participants ?? 0 }}</p>
                <p class="text-xs text-indigo-900/70 font-medium mt-0.5">Total Unique Students</p>
            </div>
            <div class="card card--muted !py-3.5 text-center">
                <p class="text-xl font-bold text-slate-700">{{ totals.total_registrations ?? 0 }}</p>
                <p class="text-xs text-slate-500 mt-0.5">Total Registrations</p>
            </div>
            <div v-for="cat in categories" :key="cat.key" class="card card--muted !py-3.5 text-center bg-emerald-50/40 border-emerald-100">
                <p class="text-xl font-bold text-emerald-700">{{ totals.category_counts?.[cat.key] ?? 0 }}</p>
                <p class="text-xs text-emerald-800/80 truncate px-1 mt-0.5" :title="cat.label">{{ cat.label }}</p>
            </div>
        </div>

        <!-- School Search / Filter -->
        <div class="card mb-4 !py-3.5 flex flex-wrap items-center justify-between gap-3">
            <div class="w-full sm:w-80">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search school by name or code…"
                    class="field text-sm w-full"
                />
            </div>
            <div class="text-xs text-slate-500">
                Showing {{ filteredRows.length }} of {{ rows.length }} schools
            </div>
        </div>

        <!-- Data Table -->
        <div class="card overflow-hidden p-0 shadow-sm border border-slate-200">
            <div class="overflow-x-auto">
                <table class="data-table min-w-full">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-xs">
                            <th class="w-12 text-center">#</th>
                            <th class="text-left py-3 px-4 font-semibold text-slate-700">School</th>
                            <th v-for="cat in categories" :key="cat.key"
                                class="text-center py-3 px-3 font-semibold text-slate-700 whitespace-nowrap">
                                {{ cat.label }}
                            </th>
                            <th class="text-center py-3 px-3 font-semibold text-indigo-900 bg-indigo-50/50 whitespace-nowrap">
                                Unique Students
                            </th>
                            <th class="text-center py-3 px-3 font-semibold text-slate-700 whitespace-nowrap">
                                Registrations
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <tr v-for="(row, idx) in filteredRows" :key="row.school_id" class="hover:bg-slate-50/80 transition">
                            <td class="text-center text-xs text-slate-400">{{ idx + 1 }}</td>
                            <td class="py-2.5 px-4">
                                <div class="font-medium text-slate-900">{{ row.school_name }}</div>
                                <div v-if="row.school_code" class="text-xs text-slate-400 font-mono">{{ row.school_code }}</div>
                            </td>
                            <td v-for="cat in categories" :key="cat.key" class="text-center py-2.5 px-3">
                                <span v-if="(row.category_counts?.[cat.key] ?? 0) > 0"
                                      class="inline-block px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    {{ row.category_counts[cat.key] }}
                                </span>
                                <span v-else class="text-slate-300 text-xs">—</span>
                            </td>
                            <td class="text-center py-2.5 px-3 font-bold text-indigo-700 bg-indigo-50/30">
                                {{ row.total_unique_participants }}
                            </td>
                            <td class="text-center py-2.5 px-3 text-slate-600 font-medium">
                                {{ row.total_registrations }}
                            </td>
                        </tr>
                        <tr v-if="!filteredRows.length">
                            <td :colspan="categories.length + 3" class="p-8 text-center text-slate-400">
                                {{ search ? 'No schools match your search query.' : 'No participant registrations found for this event yet.' }}
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="rows.length">
                        <tr class="bg-slate-100/90 font-bold border-t-2 border-slate-300 text-sm">
                            <td class="text-center text-slate-500">Σ</td>
                            <td class="py-3 px-4 text-slate-900">
                                TOTALS ({{ filteredRows.length }} Schools)
                            </td>
                            <td v-for="cat in categories" :key="cat.key" class="text-center py-3 px-3 text-emerald-800">
                                {{ filteredTotals.category_counts[cat.key] ?? 0 }}
                            </td>
                            <td class="text-center py-3 px-3 text-indigo-900 bg-indigo-100/50">
                                {{ filteredTotals.total_unique_participants }}
                            </td>
                            <td class="text-center py-3 px-3 text-slate-800">
                                {{ filteredTotals.total_registrations }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import ReportDownloadButtons from '@/Components/reports/ReportDownloadButtons.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    categories: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({}) },
    pdfUrl: String,
    xlsUrl: String,
    activityLogs: { type: Array, default: () => [] },
});

const search = ref('');

const filteredRows = computed(() => {
    if (!search.value.trim()) {
        return props.rows;
    }
    const q = search.value.toLowerCase();
    return props.rows.filter(r =>
        (r.school_name && r.school_name.toLowerCase().includes(q)) ||
        (r.school_code && r.school_code.toLowerCase().includes(q))
    );
});

const filteredTotals = computed(() => {
    if (!search.value.trim()) {
        return props.totals;
    }
    const catCounts = {};
    props.categories.forEach(c => {
        catCounts[c.key] = filteredRows.value.reduce((acc, r) => acc + (r.category_counts?.[c.key] ?? 0), 0);
    });
    return {
        category_counts: catCounts,
        total_unique_participants: filteredRows.value.reduce((acc, r) => acc + (r.total_unique_participants ?? 0), 0),
        total_registrations: filteredRows.value.reduce((acc, r) => acc + (r.total_registrations ?? 0), 0),
    };
});
</script>
