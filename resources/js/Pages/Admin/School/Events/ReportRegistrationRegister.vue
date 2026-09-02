<template>
    <SchoolAdminLayout :title="`Registration Register — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader
            :title="`Registration & Fees — ${event.title}`"
            :eyebrow="programLabel"
            description="Fest ID per student, item registrations, and your school's fee status."
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
                Event fees are one total per school — upload proof under Payments once all items are registered.
            </p>
        </div>

        <div v-if="hasItemHeads" class="mb-6">
            <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
                <h3 class="text-sm font-semibold text-slate-800">Filter by item</h3>
                <Link :href="`${programBase}/reports/${event.id}`" class="text-xs font-semibold text-indigo-600 hover:underline">← All sections</Link>
            </div>
            <ReportItemSearchSelect :items="flatItems"
                                    :model-value="itemFilter"
                                    :all-items-label="`All ${flatItems.length} items`"
                                    search-placeholder="Search by item name or code…"
                                    @select="onItemSelect" />
        </div>

        <div v-if="filterOptions.phases.length || filterOptions.batches.length" class="mb-6 flex flex-wrap gap-3">
            <div v-if="filterOptions.phases.length" class="w-56">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Phase</label>
                <SearchableSelect :model-value="filterPhaseId ?? ''" :options="filterOptions.phases.map((p) => ({ value: p.id, label: p.name }))"
                                   all-label="All phases" @update:model-value="onPhaseSelect" />
            </div>
            <div v-if="filterOptions.batches.length" class="w-56">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Level</label>
                <SearchableSelect :model-value="filterBatchId ?? ''" :options="filterOptions.batches.map((b) => ({ value: b.id, label: b.name }))"
                                   all-label="All levels" @update:model-value="onBatchSelect" />
            </div>
        </div>

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
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">Head</th>
                        <th class="p-3">Participant</th>
                        <th class="p-3">Fest ID</th>
                        <th class="p-3">Item</th>
                        <th v-if="filterOptions.phases.length" class="p-3">Phase</th>
                        <th v-if="filterOptions.batches.length" class="p-3">Level</th>
                        <th class="p-3">Item reg</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Item fee</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(row, idx) in rows.data" :key="row.participant_id">
                        <tr v-if="shouldShowHeadDivider(row, rows.data[idx - 1])" class="bg-slate-50">
                            <td :colspan="columnCount" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
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
                            <td v-if="filterOptions.phases.length" class="p-3 text-xs">{{ row.phase_name ?? '—' }}</td>
                            <td v-if="filterOptions.batches.length" class="p-3 text-xs">{{ row.batch_name ?? '—' }}</td>
                            <td class="p-3 font-mono text-xs">{{ row.item_reg }}</td>
                            <td class="p-3 text-xs capitalize">
                                {{ row.registration_status }}
                                <span v-if="row.participant_role === 'standby'" class="text-slate-500"> · standby</span>
                            </td>
                            <td class="p-3 text-xs">{{ row.item_fee != null ? `₹${row.item_fee}` : '—' }}</td>
                        </tr>
                    </template>
                    <tr v-if="!rows.data.length">
                        <td :colspan="columnCount" class="p-8 text-center text-gray-400">No registrations match the selected filters.</td>
                    </tr>
                </tbody>
            </table>
            </div>
            <div v-if="rows.last_page > 1" class="px-4 py-3 border-t border-gray-100 flex flex-wrap justify-center gap-1">
                <Link v-for="link in rows.links" :key="link.label"
                      :href="link.url || '#'"
                      class="px-3 py-1 rounded text-xs font-medium"
                      :class="link.active ? 'bg-[#0f3d7a] text-white' : (link.url ? 'text-[#0f3d7a] hover:bg-gray-100' : 'text-gray-300 pointer-events-none')"
                      v-html="link.label" />
            </div>
            <div v-else-if="rows.total" class="px-4 py-2 border-t border-gray-100 text-center text-xs text-slate-400">
                Showing all {{ rows.total }} row{{ rows.total === 1 ? '' : 's' }}
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import ReportItemSearchSelect from '@/Components/reports/ReportItemSearchSelect.vue';
import ReportDownloadButtons from '@/Components/reports/ReportDownloadButtons.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';
import { useReportHeadFilters } from '@/composables/useReportHeadFilters.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    rows: Object,
    schoolSummary: Object,
    totals: Object,
    filterOptions: { type: Object, default: () => ({ phases: [], batches: [] }) },
    filterPhaseId: { type: [String, Number], default: null },
    filterBatchId: { type: [String, Number], default: null },
    paymentsUrl: String,
    pdfUrl: String,
    csvUrl: String,
});

const { programLabel, programBase } = useSchoolProgramContext(props);
const base = `${programBase.value}/reports/${props.event.id}/registration-register`;

const columnCount = computed(() => 6 + (props.filterOptions.phases.length ? 1 : 0) + (props.filterOptions.batches.length ? 1 : 0));

function onPhaseSelect(phaseId) {
    router.get(base, phaseId ? { phase_id: phaseId } : {}, { preserveScroll: true, preserveState: true });
}

function onBatchSelect(batchId) {
    router.get(base, batchId ? { registration_batch_id: batchId } : {}, { preserveScroll: true, preserveState: true });
}

// Filtering now happens server-side (see FestRegistrationRegisterService::build()) since
// 'rows' is a paginated slice — client-side post-filtering would only ever see whatever
// happened to land on the current page. onItemSelect() below does a full page navigation
// with item_id in the query string; the server does the actual filtering + paging.
const {
    itemFilter,
    headItemGroups,
    hasItemHeads,
    shouldShowHeadDivider,
} = useReportHeadFilters(base, () => props.rows.data);

// Flat item list across every head — headItemGroups already carries complete, unstripped
// item arrays per head (FestHeadItemNavigationService::navigationForEvent()), so this is
// lossless versus the old head-then-item picker.
const flatItems = computed(() => headItemGroups.value.flatMap((h) => h.items ?? []));

function onItemSelect(itemId) {
    router.get(base, itemId ? { item_id: itemId } : {}, { preserveScroll: true, preserveState: true });
}
</script>
