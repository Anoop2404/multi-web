<template>
    <SchoolAdminLayout :title="`Published results — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader :title="`Published results — ${event.title}`" :eyebrow="programLabel"
                    description="Official results published by Sahodaya — filter by head or item.">
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">← All reports</Link>
                <ReportDownloadButtons v-if="pdfUrl" :pdf-url="pdfUrl" />
            </template>
        </PageHeader>

        <div v-if="!results.published"
             class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            No results are published yet for your items. Sahodaya may publish items individually during the event — check
            <Link :href="`${programBase}/reports/${event.id}/results-publish-status`" class="font-semibold underline">Results publish status</Link>.
        </div>
        <div v-else-if="!results.event_published"
             class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
            Some items have published results. Full event release may still be pending.
        </div>

        <ReportHeadSubNav v-if="hasItemHeads"
                          :head-item-groups="headItemGroups"
                          :base-url="base"
                          :selected-head-id="filterHeadId ?? headFilter"
                          :selected-item-id="filterItemId ?? itemFilter"
                          :hub-url="`${programBase}/reports/${event.id}`" />

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card text-center">
                <p class="text-2xl font-bold text-amber-600">{{ results.gold }}</p>
                <p class="text-xs text-gray-500 mt-1">Gold</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-slate-500">{{ results.silver }}</p>
                <p class="text-xs text-gray-500 mt-1">Silver</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-orange-700">{{ results.bronze }}</p>
                <p class="text-xs text-gray-500 mt-1">Bronze</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold">{{ results.total_score }}</p>
                <p class="text-xs text-gray-500 mt-1">Total score</p>
            </div>
        </div>

        <div class="card overflow-hidden p-0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="pl-5">Participant</th>
                        <th>Head</th>
                        <th>Item</th>
                        <th>Fest ID</th>
                        <th>Chest</th>
                        <th>Position</th>
                        <th>Grade</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in displayRows" :key="i">
                        <td class="pl-5">
                            <ReportStudentCell :name="row.participant"
                                               :reg-no="row.reg_no"
                                               :class-label="row.class"
                                               :photo-url="row.photo_url" />
                        </td>
                        <td class="text-sm">{{ row.head_name ?? '—' }}</td>
                        <td class="text-sm">{{ row.item }}</td>
                        <td class="font-mono text-xs">{{ row.fest_id ?? '—' }}</td>
                        <td class="font-mono text-xs">{{ row.chest_no ?? '—' }}</td>
                        <td>{{ row.position ?? '—' }}</td>
                        <td>{{ row.grade ?? '—' }}</td>
                        <td>{{ row.score ?? '—' }}</td>
                    </tr>
                    <tr v-if="!displayRows.length">
                        <td colspan="8" class="p-6 text-center text-slate-400">No results for the selected filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import ReportHeadSubNav from '@/Components/reports/ReportHeadSubNav.vue';
import ReportDownloadButtons from '@/Components/reports/ReportDownloadButtons.vue';
import ReportStudentCell from '@/Components/reports/ReportStudentCell.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';
import { useReportHeadFilters } from '@/composables/useReportHeadFilters.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    results: Object,
    filterHeadId: [String, Number],
    filterItemId: [String, Number],
    pdfUrl: String,
});

const { programLabel, programBase } = useSchoolProgramContext(props);
const base = `${programBase.value}/reports/${props.event.id}/published-results`;

const {
    headFilter,
    itemFilter,
    headItemGroups,
    hasItemHeads,
    displayRows,
} = useReportHeadFilters(base, () => props.results?.items ?? []);

if (props.filterHeadId) headFilter.value = String(props.filterHeadId);
if (props.filterItemId) itemFilter.value = String(props.filterItemId);
</script>
