<template>
    <SchoolAdminLayout :title="`Item-wise — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader
            :title="`Item-wise — ${event.title}`"
            :eyebrow="programLabel"
            description="Pick an item head, then an item, to view participants and marks."
        >
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">← All reports</Link>
                <ReportDownloadButtons v-if="itemId" :pdf-url="pdfUrl" :csv-url="csvUrl" />
                <a v-if="itemId && resultsPdfUrl"
                   :href="resultsPdfUrl"
                   target="_blank"
                   rel="noopener"
                   class="btn-secondary text-sm">
                    Results PDF ↓
                </a>
            </template>
        </PageHeader>

        <ReportHeadSubNav v-if="hasItemHeads"
                          :head-item-groups="headItemGroups"
                          :base-url="base"
                          :selected-head-id="headFilter"
                          :selected-item-id="itemFilter"
                          :hub-url="`${programBase}/reports/${event.id}`" />

        <div v-if="!itemId" class="notice-banner notice-banner--info mb-4">
            Select a head and item above to view participants.
        </div>

        <div v-else class="card card--flush overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Participant</th>
                        <th>Reg No</th>
                        <th>Fest ID</th>
                        <th>Item reg</th>
                        <th>Chest</th>
                        <th>Grade</th>
                        <th>Position</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in participants" :key="p.id">
                        <td>{{ p.student?.name ?? p.teacher?.name }}</td>
                        <td class="font-mono text-xs">{{ p.student?.reg_no ?? p.teacher?.reg_no }}</td>
                        <td class="font-mono text-xs">{{ p.level_registration_number ?? '—' }}</td>
                        <td class="font-mono text-xs">{{ p.item_registration_number ?? '—' }}</td>
                        <td class="font-mono text-xs">{{ p.chest_no ?? '—' }}</td>
                        <td>{{ p.mark?.grade ?? '—' }}</td>
                        <td>{{ p.mark?.position ?? '—' }}</td>
                        <td>{{ p.mark?.score ?? '—' }}</td>
                    </tr>
                    <tr v-if="!participants.length"><td colspan="8" class="p-6 text-center text-slate-400">No participants</td></tr>
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
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';
import { useReportHeadFilters } from '@/composables/useReportHeadFilters.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    itemId: Number,
    participants: Array,
    pdfUrl: String,
    csvUrl: String,
    resultsPdfUrl: { type: String, default: null },
});

const { programLabel, programBase } = useSchoolProgramContext(props);
const base = `${programBase.value}/reports/${props.event.id}/item-wise`;

const {
    headFilter,
    itemFilter,
    headItemGroups,
    headsForFilter,
    hasItemHeads,
    applyFilter,
} = useReportHeadFilters(base, () => []);

if (props.itemId) itemFilter.value = String(props.itemId);
</script>
