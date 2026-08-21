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

        <section v-if="hasItemHeads" class="mb-6">
            <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-800">Filter by item</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Search or pick an item — the register below updates for that item.</p>
                </div>
                <Link :href="`${programBase}/reports/${event.id}`" class="text-xs font-semibold text-indigo-600 hover:underline">← All sections</Link>
            </div>
            <ReportItemSearchSelect :items="flatItems"
                                    :model-value="filterItemId ?? itemFilter"
                                    :all-items-label="`All ${flatItems.length} items`"
                                    search-placeholder="Search by item name or code…"
                                    @select="onItemSelect" />
        </section>

        <div class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="pl-5">Student</th>
                            <th>Fest ID</th>
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
                            <td class="text-xs">
                                <p v-for="(item, idx) in row.items" :key="idx" class="py-0.5">
                                    <span class="font-medium text-slate-800">{{ item.item }}</span>
                                    <span v-if="item.head_name" class="text-slate-500"> · {{ item.head_name }}</span>
                                    <span v-if="item.item_reg" class="text-slate-400 font-mono"> · {{ item.item_reg }}</span>
                                </p>
                            </td>
                        </tr>
                        <tr v-if="!rows?.length">
                            <td colspan="3" class="p-6 text-center text-slate-400">
                                No students match the selected filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import FestEventMetaBar from '@/Components/reports/FestEventMetaBar.vue';
import ReportItemSearchSelect from '@/Components/reports/ReportItemSearchSelect.vue';
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
    itemFilter,
    headItemGroups,
    hasItemHeads,
} = useReportHeadFilters(base, () => props.rows);

if (props.filterItemId) itemFilter.value = String(props.filterItemId);

const flatItems = computed(() => headItemGroups.value.flatMap((h) => h.items ?? []));

function onItemSelect(itemId) {
    router.get(base, itemId ? { item_id: itemId } : {}, { preserveScroll: true, preserveState: true });
}
</script>
