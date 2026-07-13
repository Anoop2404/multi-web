<template>
    <SchoolAdminLayout :title="`Mark entry status — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader :title="`Mark entry status — ${event.title}`" :eyebrow="programLabel"
                    :description="event.event_type === 'sports'
                        ? 'Mark entry progress for your school\'s participants by Event Head and item.'
                        : 'Mark entry progress for your school\'s participants by item head and item.'">
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">← Reports</Link>
                <ReportDownloadButtons :pdf-url="pdfUrl" :csv-url="csvUrl" />
            </template>
        </PageHeader>

        <ReportHeadSubNav v-if="hasItemHeads"
                          :head-item-groups="headItemGroups"
                          :base-url="base"
                          :selected-head-id="headFilter"
                          :selected-item-id="itemFilter"
                          :hub-url="`${programBase}/reports/${event.id}`"
                          :is-sports="event.event_type === 'sports'" />

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ filteredSummary.items }}</p>
                <p class="text-xs text-slate-500 mt-1">Items</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ filteredSummary.participants }}</p>
                <p class="text-xs text-slate-500 mt-1">Participants</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ filteredSummary.marked }}</p>
                <p class="text-xs text-slate-500 mt-1">Marked</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-amber-700">{{ filteredSummary.pending }}</p>
                <p class="text-xs text-slate-500 mt-1">Pending</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ filteredSummary.complete }}/{{ filteredSummary.items }}</p>
                <p class="text-xs text-slate-500 mt-1">Items complete</p>
            </div>
        </div>

        <div class="card overflow-hidden p-0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Head</th>
                        <th>Item</th>
                        <th>Participants</th>
                        <th>Marked</th>
                        <th>Pending</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(row, idx) in displayRows" :key="row.item_id">
                        <tr v-if="shouldShowHeadDivider(row, displayRows[idx - 1])" class="bg-slate-50/80">
                            <td colspan="6" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {{ row.head_name ?? 'Other items' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="text-xs text-slate-400">{{ row.head_name ?? '—' }}</td>
                            <td class="font-medium">{{ row.title }}</td>
                            <td>{{ row.participants }}</td>
                            <td>{{ row.marked }}</td>
                            <td>{{ row.pending }}</td>
                            <td>
                                <span class="status-pill text-xs" :class="row.complete ? 'status-pill--completed' : 'status-pill--open'">
                                    {{ row.complete ? 'Complete' : 'Pending' }}
                                </span>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="!displayRows.length">
                        <td colspan="6" class="p-6 text-center text-slate-400">No items match the selected filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import ReportHeadSubNav from '@/Components/reports/ReportHeadSubNav.vue';
import ReportDownloadButtons from '@/Components/reports/ReportDownloadButtons.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';
import { useReportHeadFilters } from '@/composables/useReportHeadFilters.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    summary: Object,
    rows: Array,
    pdfUrl: String,
    csvUrl: String,
});

const { programLabel, programBase } = useSchoolProgramContext(props);
const base = `${programBase.value}/reports/${props.event.id}/mark-entry-status`;

const {
    headFilter,
    itemFilter,
    headItemGroups,
    headsForFilter,
    hasItemHeads,
    displayRows,
    applyFilter,
    shouldShowHeadDivider,
} = useReportHeadFilters(base, () => props.rows);

const filteredSummary = computed(() => ({
    items: displayRows.value.length,
    participants: displayRows.value.reduce((n, r) => n + r.participants, 0),
    marked: displayRows.value.reduce((n, r) => n + r.marked, 0),
    pending: displayRows.value.reduce((n, r) => n + r.pending, 0),
    complete: displayRows.value.filter((r) => r.complete).length,
}));
</script>
