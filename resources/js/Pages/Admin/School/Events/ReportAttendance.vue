<template>
    <SchoolAdminLayout :title="`Attendance — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader :title="`Attendance register — ${event.title}`" :eyebrow="programLabel"
                    description="Students registered for this event — filter by head or item, then print the attendance sheet.">
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">← All reports</Link>
                <ReportDownloadButtons v-if="pdfUrl" :pdf-url="pdfUrl" pdf-label="Print sheet" />
            </template>
        </PageHeader>

        <FestEventMetaBar v-if="eventMeta" :meta="eventMeta" :show-edit-hint="false" />

        <ReportHeadSubNav v-if="hasItemHeads"
                          :head-item-groups="headItemGroups"
                          :base-url="base"
                          :selected-head-id="filterHeadId ?? headFilter"
                          :selected-item-id="filterItemId ?? itemFilter"
                          :hub-url="`${programBase}/reports/${event.id}`" />

        <div class="card overflow-hidden p-0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="pl-5">Student</th>
                        <th>Fest ID</th>
                        <th>Chest</th>
                        <th>Items</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.student_id">
                        <td class="pl-5">
                            <ReportStudentCell :name="row.student"
                                               :reg-no="row.reg_no"
                                               :class-label="row.class"
                                               :photo-url="row.photo_url" />
                        </td>
                        <td class="font-mono text-xs">{{ row.fest_id ?? '—' }}</td>
                        <td class="font-mono text-xs">{{ row.chest_no ?? '—' }}</td>
                        <td class="text-xs">
                            <p v-for="(item, idx) in row.items" :key="idx" class="py-0.5">
                                <span class="font-medium text-slate-800">{{ item.item }}</span>
                                <span v-if="item.head_name" class="text-slate-500"> · {{ item.head_name }}</span>
                                <span v-if="item.item_reg" class="text-slate-400 font-mono"> · {{ item.item_reg }}</span>
                            </p>
                        </td>
                    </tr>
                    <tr v-if="!rows?.length">
                        <td colspan="4" class="p-6 text-center text-slate-400">
                            No students match the selected filters.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import FestEventMetaBar from '@/Components/reports/FestEventMetaBar.vue';
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
    eventMeta: Object,
    rows: Array,
    filterHeadId: [String, Number],
    filterItemId: [String, Number],
    pdfUrl: String,
});

const { programLabel, programBase } = useSchoolProgramContext(props);
const base = `${programBase.value}/reports/${props.event.id}/attendance`;

const {
    headFilter,
    itemFilter,
    headItemGroups,
    hasItemHeads,
} = useReportHeadFilters(base, () => props.rows);

if (props.filterHeadId) headFilter.value = String(props.filterHeadId);
if (props.filterItemId) itemFilter.value = String(props.filterItemId);
</script>
