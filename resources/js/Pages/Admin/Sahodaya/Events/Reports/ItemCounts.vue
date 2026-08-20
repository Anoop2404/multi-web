<template>
    <SahodayaEventsLayout :title="`${event.title} — Item counts`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Item registration counts`" eyebrow="Reports"
                    :description="event.event_type === 'sports'
                        ? 'Registrations, participants, and limits per item — grouped by Sport Event.'
                        : 'Registrations, participants, and limits per item — grouped by item head.'">
            <template #actions>
                <ReportDownloadButtons :pdf-url="pdfUrl" :xls-url="xlsUrl" />
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="item-counts" />

        <!-- Region Switcher -->
        <div v-if="childEvents.length" class="card mb-6 !py-3.5 border-l-4 border-l-indigo-600 bg-gradient-to-r from-slate-50 to-white shadow-sm">
            <div class="flex flex-wrap gap-3 items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="p-1.5 rounded-md bg-indigo-50 text-indigo-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 002 2h1.5a2.5 2.5 0 002.5-2.5V7.865M19 12a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-600">
                        {{ event.event_type === 'sports' ? 'Select Sport Event / Region:' : 'Select Region:' }}
                    </label>
                </div>
                <select :value="String(event.id)" @change="switchSportEvent" class="field text-xs !py-1.5 w-72 font-semibold shadow-sm border-slate-300">
                    <option v-for="ev in childEvents" :key="ev.id" :value="String(ev.id)">
                        {{ ev.short_title || ev.title }}
                    </option>
                </select>
            </div>
        </div>

        <ReportHeadFilter v-if="hasItemHeads"
                          v-model="headFilter"
                          v-model:item-id="itemFilter"
                          :heads="headsForFilter"
                          :head-item-groups="headItemGroups"
                          :is-sports="event.event_type === 'sports'"
                          @apply="applyFilter" />

        <!-- Stat Cards Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card !py-4 px-5 border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Items</p>
                    <p class="text-2xl font-extrabold text-slate-900 mt-1">{{ filteredTotals.items }}</p>
                </div>
                <span class="p-2.5 rounded-lg bg-slate-100 text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </span>
            </div>
            <div class="card !py-4 px-5 border border-indigo-100 bg-indigo-50/20 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-indigo-600">Total Registrations</p>
                    <p class="text-2xl font-extrabold text-indigo-950 mt-1">{{ filteredTotals.registrations }}</p>
                </div>
                <span class="p-2.5 rounded-lg bg-indigo-100 text-indigo-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </span>
            </div>
            <div class="card !py-4 px-5 border border-emerald-100 bg-emerald-50/20 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-600">Approved</p>
                    <p class="text-2xl font-extrabold text-emerald-800 mt-1">{{ filteredTotals.approved }}</p>
                </div>
                <span class="p-2.5 rounded-lg bg-emerald-100 text-emerald-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="card !py-4 px-5 border border-amber-100 bg-amber-50/20 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-amber-600">Awaiting Review</p>
                    <p class="text-2xl font-extrabold text-amber-800 mt-1">{{ filteredTotals.pending }}</p>
                </div>
                <span class="p-2.5 rounded-lg bg-amber-100 text-amber-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
        </div>

        <section v-if="headSummary?.length" class="mb-8">
            <h3 class="section-title mb-3">{{ event.event_type === 'sports' ? 'Summary by Sport Event' : 'Summary by item head' }}</h3>
            <div class="card overflow-hidden p-0">
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ event.event_type === 'sports' ? 'Sport Event' : 'Head' }}</th>
                                <th>Items</th>
                                <th>Regs</th>
                                <th>Approved</th>
                                <th>Pending</th>
                                <th>Participants</th>
                                <th>Max item regs</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in filteredHeadSummary" :key="row.head_id">
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
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- By Competition Item Section -->
        <section>
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <h3 class="section-title mb-0">By competition item</h3>
                <ReportDownloadButtons :pdf-url="pdfUrl" :xls-url="xlsUrl" />
            </div>
            <div class="card overflow-hidden p-0 shadow-sm border border-slate-200/80">
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead class="bg-slate-50/90 border-b border-slate-200">
                            <tr>
                                <th class="w-10 text-center">#</th>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Type</th>
                                <th class="text-center">Total Entries</th>
                                <th class="text-center">Approved</th>
                                <th class="text-center">Pending</th>
                                <th class="text-center">Total Participants</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template v-for="(row, idx) in displayRows" :key="row.item_id">
                                <tr v-if="shouldShowHeadDivider(row, displayRows[idx - 1])" class="bg-slate-100/70 border-y border-slate-200">
                                    <td colspan="8" class="px-4 py-2.5 text-xs font-bold uppercase tracking-wider text-slate-700">
                                        {{ row.head_name ?? 'Other items' }}
                                    </td>
                                </tr>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="text-center text-xs text-slate-400 font-mono">{{ idx + 1 }}</td>
                                    <td class="font-semibold text-slate-900">
                                        {{ row.title }}
                                        <span v-if="row.item_code" class="block text-xs font-mono text-slate-400 font-normal">{{ row.item_code }}</span>
                                    </td>
                                    <td class="text-slate-600 font-medium">{{ row.age_group || row.class_group || '—' }}</td>
                                    <td>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide inline-block"
                                              :class="row.participant_type === 'individual' ? 'bg-slate-100 text-slate-700 border border-slate-200' : 'bg-indigo-50 text-indigo-700 border border-indigo-200'">
                                            {{ row.participant_type === 'individual' ? 'Indiv' : 'Team' }}
                                        </span>
                                    </td>
                                    <td class="font-bold text-indigo-950 text-center">
                                        {{ row.registration_count }}
                                        <span class="block text-[10px] font-normal text-slate-400">{{ row.participant_type === 'individual' ? 'entries' : 'groups' }}</span>
                                    </td>
                                    <td class="text-emerald-700 font-bold text-center">{{ row.approved }}</td>
                                    <td class="text-amber-700 font-bold text-center">{{ row.pending }}</td>
                                    <td class="font-bold text-slate-900 text-center">
                                        {{ row.participant_count }}
                                        <span class="block text-[10px] font-normal text-slate-400">students</span>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="!displayRows.length">
                                <td colspan="8" class="p-8 text-center text-slate-400">No items match the selected filters.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import ReportHeadFilter from '@/Components/reports/ReportHeadFilter.vue';
import ReportDownloadButtons from '@/Components/reports/ReportDownloadButtons.vue';
import { useReportHeadFilters } from '@/composables/useReportHeadFilters.js';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    rows: Array,
    headSummary: Array,
    totals: Object,
    pdfUrl: String,
    xlsUrl: String,
    activityLogs: { type: Array, default: () => [] },
    childEvents: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/item-counts`;

function switchSportEvent(evt) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${evt.target.value}/reports/item-counts`);
}

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

const filteredTotals = computed(() => ({
    items: displayRows.value.length,
    approved: displayRows.value.reduce((n, r) => n + r.approved, 0),
    pending: displayRows.value.reduce((n, r) => n + r.pending, 0),
    registrations: displayRows.value.reduce((n, r) => n + r.registration_count, 0),
}));

const filteredHeadSummary = computed(() => {
    const headId = headFilter.value;
    if (!headId) return props.headSummary ?? [];
    return (props.headSummary ?? []).filter((h) => String(h.head_id) === headId);
});

function formatDate(iso) {
    if (!iso) return '—';
    const d = new Date(`${iso}T12:00:00`);
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
}

function formatDateRange(start, end) {
    if (!start && !end) return '—';
    if (start && end) {
        if (start === end) return formatDate(start);
        return `${formatDate(start)} – ${formatDate(end)}`;
    }
    return start ? `From ${formatDate(start)}` : `Until ${formatDate(end)}`;
}
</script>
