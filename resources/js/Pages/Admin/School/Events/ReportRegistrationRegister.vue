<template>
    <SchoolAdminLayout :title="`Registration Register — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader
            :title="`Registration & Fees — ${event.title}`"
            :eyebrow="programLabel"
            description="Fest ID per student, item registrations, chest numbers, and your school's fee status."
        >
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">← Reports</Link>
                <ReportDownloadButtons :pdf-url="pdfUrl" :csv-url="csvUrl" />
                <Link :href="paymentsUrl" class="btn-secondary text-sm">Payments →</Link>
            </template>
        </PageHeader>

        <div class="notice-banner notice-banner--info mb-4 text-sm max-w-3xl">
            <p class="font-semibold text-[#0f3d7a] mb-1">Your fest IDs</p>
            <p class="text-slate-700">
                Each student gets one <strong>Fest ID</strong> for this event (shown below). That same ID applies to every item they register for.
                <strong>Chest numbers</strong> are per item and appear after Sahodaya approval.
                Event fees are one total per school — upload proof under Payments once all items are registered.
            </p>
        </div>

        <ReportHeadSubNav v-if="hasItemHeads"
                          :head-item-groups="headItemGroups"
                          :base-url="base"
                          :selected-head-id="headFilter"
                          :selected-item-id="itemFilter"
                          :hub-url="`${programBase}/reports/${event.id}`" />

        <div v-if="schoolSummary && totals.fee_required" class="grid sm:grid-cols-4 gap-3 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ schoolSummary.item_count }}</p>
                <p class="text-xs text-slate-500 mt-1">Items registered</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">₹{{ schoolSummary.total_due }}</p>
                <p class="text-xs text-slate-500 mt-1">Total due</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold capitalize">{{ schoolSummary.fee_status }}</p>
                <p class="text-xs text-slate-500 mt-1">Fee status</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold font-mono text-sm">{{ schoolSummary.receipt_no ?? '—' }}</p>
                <p class="text-xs text-slate-500 mt-1">Receipt #</p>
            </div>
        </div>

        <div class="card card--flush overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">Head</th>
                        <th class="p-3">Participant</th>
                        <th class="p-3">Fest ID</th>
                        <th class="p-3">Item</th>
                        <th class="p-3">Item reg</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Chest</th>
                        <th class="p-3">Item fee</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(row, idx) in displayRows" :key="row.participant_id">
                        <tr v-if="shouldShowHeadDivider(row, displayRows[idx - 1])" class="bg-slate-50">
                            <td colspan="8" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {{ row.head_name ?? 'Other items' }}
                            </td>
                        </tr>
                        <tr class="border-t align-top">
                            <td class="p-3 text-xs text-slate-400">{{ row.head_name ?? '—' }}</td>
                            <td class="p-3">
                                <span class="font-medium">{{ row.participant_name }}</span>
                                <p class="text-xs font-mono text-[#0f3d7a]">{{ row.participant_reg_no }}</p>
                            </td>
                            <td class="p-3 font-mono text-xs font-semibold text-[#0f3d7a]">{{ row.level_reg }}</td>
                            <td class="p-3 text-xs">{{ row.item_title }}</td>
                            <td class="p-3 font-mono text-xs">{{ row.item_reg }}</td>
                            <td class="p-3 text-xs capitalize">
                                {{ row.registration_status }}
                                <span v-if="row.participant_role === 'standby'" class="text-slate-500"> · standby</span>
                            </td>
                            <td class="p-3 font-mono text-xs">{{ row.chest_no }}</td>
                            <td class="p-3 text-xs">{{ row.item_fee != null ? `₹${row.item_fee}` : '—' }}</td>
                        </tr>
                    </template>
                    <tr v-if="!displayRows.length">
                        <td colspan="8" class="p-8 text-center text-gray-400">No registrations match the selected filters.</td>
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
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';
import { useReportHeadFilters } from '@/composables/useReportHeadFilters.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    rows: Array,
    schoolSummary: Object,
    totals: Object,
    paymentsUrl: String,
    pdfUrl: String,
    csvUrl: String,
});

const { programLabel, programBase } = useSchoolProgramContext(props);
const base = `${programBase.value}/reports/${props.event.id}/registration-register`;

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
</script>
