<template>
    <SchoolAdminLayout :title="`Item counts — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader :title="`Item registration counts — ${event.title}`" :eyebrow="programLabel"
                    description="Search items, view registered students, and download filtered reports.">
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">← All reports</Link>
                <ReportDownloadButtons :pdf-url="exportPdfUrl" :xls-url="exportXlsUrl" />
            </template>
        </PageHeader>

        <FestEventMetaBar v-if="eventMeta" :meta="eventMeta" :show-edit-hint="false" />

        <ReportHeadSubNav v-if="hasItemHeads"
                          :head-item-groups="headItemGroups"
                          :base-url="base"
                          :selected-head-id="headFilter"
                          :selected-item-id="itemFilter"
                          :hub-url="`${programBase}/reports/${event.id}`"
                          :participant-counts-by-item="participantCountsByItem"
                          :is-sports="event.event_type === 'sports'"
                          @view-participants="openParticipantsModal" />

        <div v-if="!headFilter" class="mb-4">
            <label class="block text-xs font-semibold text-slate-600 mb-1.5">Filter table</label>
            <input v-model="searchQuery"
                   type="search"
                   placeholder="Search by item name, head, age group…"
                   class="w-full max-w-md rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100">
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ filteredTotals.items }}</p>
                <p class="text-xs text-slate-500 mt-1">Items</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ filteredTotals.registrations }}</p>
                <p class="text-xs text-slate-500 mt-1">Total registrations</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ filteredTotals.approved }}</p>
                <p class="text-xs text-slate-500 mt-1">Approved</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-amber-700">{{ filteredTotals.pending }}</p>
                <p class="text-xs text-slate-500 mt-1">Awaiting review</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-indigo-700">
                    <template v-if="filteredTotals.estimated_fee">₹{{ filteredTotals.estimated_fee }}</template>
                    <template v-else>—</template>
                </p>
                <p class="text-xs text-slate-500 mt-1">Est. fee</p>
            </div>
        </div>

        <section v-if="headSummary?.length && !headFilter" class="mb-8">
            <h3 class="section-title mb-3">{{ event.event_type === 'sports' ? 'Summary by Sport Event' : 'Summary by item head' }}</h3>
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
                            <th>Max item regs</th>
                            <th>Est. fee</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in headSummary" :key="row.head_id">
                            <td class="font-medium">{{ row.head_name }}</td>
                            <td>{{ row.item_count }}</td>
                            <td>{{ row.registration_count ?? 0 }}</td>
                            <td>{{ row.approved_count ?? 0 }}</td>
                            <td>{{ row.pending_count ?? 0 }}</td>
                            <td>{{ row.participant_count }}</td>
                            <td>
                                <span v-if="row.max_item_title" class="text-xs text-slate-500 block">{{ row.max_item_title }}</span>
                                {{ row.busiest_item_regs ?? row.max_item_reg_count ?? 0 }}
                            </td>
                            <td>
                                <template v-if="row.estimated_fee">₹{{ row.estimated_fee }}</template>
                                <template v-else>—</template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section>
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <h3 class="section-title !mb-0">By competition item</h3>
                <p class="text-xs text-slate-500">{{ searchedRows.length }} item(s) shown</p>
            </div>
            <div class="card overflow-hidden p-0">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Head</th>
                            <th>Item</th>
                            <th>Age / class</th>
                            <th>Approved</th>
                            <th>Pending</th>
                            <th>Participants</th>
                            <th>Item IDs</th>
                            <th>Max / school</th>
                            <th>Fee / item</th>
                            <th>Line fee</th>
                            <th class="w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(row, idx) in searchedRows" :key="row.item_id">
                            <tr v-if="shouldShowHeadDivider(row, searchedRows[idx - 1])" class="bg-slate-50/80">
                                <td colspan="11" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    {{ row.head_name ?? 'Other items' }}
                                </td>
                            </tr>
                            <tr>
                                <td class="text-xs text-slate-400">{{ row.head_name ?? '—' }}</td>
                                <td class="font-medium">{{ row.title }}</td>
                                <td>{{ row.age_group || row.class_group || '—' }}</td>
                                <td>{{ row.approved }}</td>
                                <td>{{ row.pending }}</td>
                                <td>{{ row.participant_count }}</td>
                                <td>{{ row.item_reg_assigned }}</td>
                                <td>{{ row.max_per_school ?? '—' }}</td>
                                <td>
                                    <template v-if="row.fee_per_item !== null">₹{{ row.fee_per_item }}</template>
                                    <template v-else>—</template>
                                </td>
                                <td>
                                    <template v-if="row.line_fee !== null">₹{{ row.line_fee }}</template>
                                    <template v-else>—</template>
                                </td>
                                <td class="text-center">
                                    <button v-if="row.participant_count > 0 || row.approved > 0 || row.pending > 0"
                                            type="button"
                                            class="inline-flex items-center justify-center h-8 w-8 rounded-lg text-slate-500 hover:text-indigo-700 hover:bg-indigo-50 transition"
                                            title="View participants"
                                            @click="openParticipantsModal(row)">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!searchedRows.length">
                            <td colspan="11" class="p-6 text-center text-slate-400">No items match your search or filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <ReportItemParticipantsModal :open="modalOpen"
                                     :fetch-url="modalFetchUrl"
                                     :item-title="modalItemTitle"
                                     :head-name="modalHeadName"
                                     @close="closeParticipantsModal" />
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import FestEventMetaBar from '@/Components/reports/FestEventMetaBar.vue';
import ReportHeadSubNav from '@/Components/reports/ReportHeadSubNav.vue';
import ReportDownloadButtons from '@/Components/reports/ReportDownloadButtons.vue';
import ReportItemParticipantsModal from '@/Components/reports/ReportItemParticipantsModal.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';
import { useReportHeadFilters } from '@/composables/useReportHeadFilters.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    eventMeta: Object,
    rows: Array,
    headSummary: Array,
    totals: Object,
    pdfUrl: String,
    xlsUrl: String,
});

const { programLabel, programBase } = useSchoolProgramContext(props);
const base = `${programBase.value}/reports/${props.event.id}/item-counts`;
const participantsBase = `${programBase.value}/reports/${props.event.id}/items`;

const searchQuery = ref('');
const modalOpen = ref(false);
const modalFetchUrl = ref(null);
const modalItemTitle = ref('');
const modalHeadName = ref('');

const {
    headFilter,
    itemFilter,
    headItemGroups,
    hasItemHeads,
    displayRows,
    shouldShowHeadDivider,
} = useReportHeadFilters(base, () => props.rows);

const participantCountsByItem = computed(() => {
    const map = {};
    for (const row of props.rows ?? []) {
        map[row.item_id] = row.participant_count ?? 0;
    }
    return map;
});

const searchedRows = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return displayRows.value;
    return displayRows.value.filter((row) => {
        const haystack = [
            row.title,
            row.head_name,
            row.age_group,
            row.class_group,
            row.item_code,
        ].filter(Boolean).join(' ').toLowerCase();
        return haystack.includes(q);
    });
});

const filteredTotals = computed(() => ({
    items: searchedRows.value.length,
    approved: searchedRows.value.reduce((n, r) => n + r.approved, 0),
    pending: searchedRows.value.reduce((n, r) => n + r.pending, 0),
    registrations: searchedRows.value.reduce((n, r) => n + r.registration_count, 0),
    estimated_fee: Math.round(searchedRows.value.reduce((n, r) => n + (r.line_fee ?? 0), 0) * 100) / 100,
}));

function filterQueryString() {
    const q = new URLSearchParams();
    if (headFilter.value) q.set('head_id', headFilter.value);
    if (itemFilter.value) q.set('item_id', itemFilter.value);
    const s = q.toString();
    return s ? `?${s}` : '';
}

const exportPdfUrl = computed(() => `${props.pdfUrl}${filterQueryString()}`);
const exportXlsUrl = computed(() => `${props.xlsUrl}${filterQueryString()}`);

function openParticipantsModal(rowOrItemId) {
    const row = typeof rowOrItemId === 'object'
        ? rowOrItemId
        : (props.rows ?? []).find((r) => String(r.item_id) === String(rowOrItemId));
    if (!row) return;
    modalFetchUrl.value = `${participantsBase}/${row.item_id}/participants`;
    modalItemTitle.value = row.title;
    modalHeadName.value = row.head_name ?? '';
    modalOpen.value = true;
}

function closeParticipantsModal() {
    modalOpen.value = false;
    modalFetchUrl.value = null;
}
</script>
