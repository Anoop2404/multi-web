<template>
    <SchoolAdminLayout :title="`Head-wise — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader :title="`Participant list — ${event.title}`" :eyebrow="programLabel"
                    description="Your school's participants — pick a head and item above to filter.">
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">← All reports</Link>
                <ReportDownloadButtons :pdf-url="pdfUrl" :xls-url="xlsUrl" />
            </template>
        </PageHeader>

        <FestEventMetaBar v-if="eventMeta" :meta="eventMeta" :show-edit-hint="false" />

        <ReportHeadSubNav v-if="hasItemHeads"
                          :head-item-groups="headItemGroups"
                          :base-url="base"
                          :selected-head-id="filterHeadId ?? headFilter"
                          :selected-item-id="filterItemId ?? itemFilter"
                          :hub-url="`${programBase}/reports/${event.id}`"
                          :is-sports="true" />

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ totals.heads }}</p>
                <p class="text-xs text-slate-500 mt-1">Heads with entries</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ totals.items }}</p>
                <p class="text-xs text-slate-500 mt-1">Items</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ totals.registrations }}</p>
                <p class="text-xs text-slate-500 mt-1">Registrations</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ totals.participants }}</p>
                <p class="text-xs text-slate-500 mt-1">Participants</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-amber-700">{{ totals.pending }}</p>
                <p class="text-xs text-slate-500 mt-1">Pending review</p>
            </div>
        </div>

        <section v-if="!filterHeadId && !headFilter">
            <h3 class="section-title mb-3">Summary by head</h3>
            <p class="text-sm text-slate-500 mb-3">Select a head above to see the full participant list with photos and IDs.</p>
            <div class="card overflow-hidden p-0">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Head</th>
                            <th>Items</th>
                            <th>Regs</th>
                            <th>Approved</th>
                            <th>Pending</th>
                            <th>Participants</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in summary" :key="row.head_id">
                            <td class="font-medium">{{ row.head_name }}</td>
                            <td>{{ row.item_count }}</td>
                            <td>{{ row.registration_count ?? 0 }}</td>
                            <td>{{ row.approved_count ?? 0 }}</td>
                            <td>{{ row.pending_count ?? 0 }}</td>
                            <td>{{ row.participant_count }}</td>
                            <td>
                                <Link v-if="row.head_id"
                                      :href="`${base}?head_id=${row.head_id}`"
                                      class="text-xs font-semibold text-indigo-700 hover:underline">
                                    View list →
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!summary.length">
                            <td colspan="7" class="p-6 text-center text-slate-400">
                                No sport events configured for this event yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="filterHeadId || headFilter">
            <h3 class="section-title mb-3">Participant list</h3>
            <div class="card overflow-hidden p-0">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Head</th>
                            <th>Participant</th>
                            <th>Item</th>
                            <th>Fest ID</th>
                            <th>Item reg</th>
                            <th>Chest</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(row, idx) in displayRows" :key="`${row.head_id}-${row.item_id}-${idx}`">
                            <tr v-if="shouldShowHeadDivider(row, displayRows[idx - 1])" class="bg-slate-50/80">
                                <td colspan="7" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    {{ row.head_name ?? 'Other items' }}
                                </td>
                            </tr>
                            <tr>
                                <td>{{ row.head_name }}</td>
                                <td>
                                    <ReportStudentCell :name="row.student"
                                                       :reg-no="row.reg_no"
                                                       :class-label="row.class"
                                                       :photo-url="row.photo_url" />
                                </td>
                                <td>{{ row.item }}</td>
                                <td class="font-mono text-xs">{{ row.fest_id ?? '—' }}</td>
                                <td class="font-mono text-xs">{{ row.item_reg ?? '—' }}</td>
                                <td class="font-mono text-xs">{{ row.chest_no ?? '—' }}</td>
                                <td class="capitalize text-xs">{{ row.status ?? '—' }}</td>
                            </tr>
                        </template>
                        <tr v-if="!displayRows.length && summary.length">
                            <td colspan="7" class="p-6 text-center text-slate-400">No participants for the selected filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
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
    summary: Array,
    rows: Array,
    filterHeadId: [String, Number],
    filterItemId: [String, Number],
    pdfUrl: String,
    xlsUrl: String,
});

const { programLabel, programBase } = useSchoolProgramContext(props);
const base = `${programBase.value}/reports/${props.event.id}/head-wise`;

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

if (props.filterHeadId) headFilter.value = String(props.filterHeadId);
if (props.filterItemId) itemFilter.value = String(props.filterItemId);

const totals = computed(() => ({
    heads: (props.summary ?? []).filter((r) => (r.participant_count ?? 0) > 0 || (r.registration_count ?? 0) > 0).length,
    items: (props.summary ?? []).reduce((n, r) => n + (r.item_count ?? 0), 0),
    registrations: (props.summary ?? []).reduce((n, r) => n + (r.registration_count ?? 0), 0),
    participants: displayRows.value.length,
    pending: (props.summary ?? []).reduce((n, r) => n + (r.pending_count ?? 0), 0),
}));
</script>
