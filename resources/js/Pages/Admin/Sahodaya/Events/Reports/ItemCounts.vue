<template>
    <SahodayaEventsLayout :title="`${event.title} — Item counts`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Item registration counts`" eyebrow="Reports"
                    :description="event.event_type === 'sports'
                        ? 'Registrations, participants, and limits per item — grouped by Sport Event.'
                        : 'Registrations, participants, and limits per item — grouped by item head.'">
            <template #actions>
                <a :href="pdfUrl" target="_blank" class="btn-secondary text-sm">Export PDF ↓</a>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="item-counts" />

        <!-- Region Switcher -->
        <div v-if="childEvents.length" class="card mb-4 !py-3">
            <div class="flex flex-wrap gap-3 items-center">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ event.event_type === 'sports' ? 'Select Sport Event / Region:' : 'Select Region:' }}</label>
                <select :value="String(event.id)" @change="switchSportEvent" class="field text-xs !py-1 w-64 font-semibold">
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

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
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
        </div>

        <section v-if="headSummary?.length" class="mb-8">
            <h3 class="section-title mb-3">{{ event.event_type === 'sports' ? 'Summary by Sport Event' : 'Summary by item head' }}</h3>
            <div class="card overflow-hidden p-0">
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
        </section>

        <section>
            <h3 class="section-title mb-3">By competition item</h3>
            <div class="card overflow-hidden p-0">
                <table class="data-table">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Type</th>
                            <th>Schools</th>
                            <th>Total Regs</th>
                            <th>Approved</th>
                            <th>Pending</th>
                            <th>Students / Teams</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(row, idx) in displayRows" :key="row.item_id">
                            <tr v-if="shouldShowHeadDivider(row, displayRows[idx - 1])" class="bg-slate-50/80">
                                <td colspan="8" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    {{ row.head_name ?? 'Other items' }}
                                </td>
                            </tr>
                            <tr>
                                <td class="font-medium">
                                    {{ row.title }}
                                    <span v-if="row.item_code" class="block text-xs font-mono text-slate-400">{{ row.item_code }}</span>
                                </td>
                                <td>{{ row.age_group || row.class_group || '—' }}</td>
                                <td>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide"
                                          :class="row.participant_type === 'individual' ? 'bg-slate-100 text-slate-700' : 'bg-indigo-100 text-indigo-800'">
                                        {{ row.participant_type === 'individual' ? 'Indiv' : 'Team' }}
                                    </span>
                                </td>
                                <td class="font-semibold text-slate-800">{{ row.school_count ?? 0 }}</td>
                                <td class="font-bold text-indigo-900">{{ row.registration_count }}</td>
                                <td class="text-emerald-700 font-semibold">{{ row.approved }}</td>
                                <td class="text-amber-700 font-semibold">{{ row.pending }}</td>
                                <td class="font-bold text-slate-900">
                                    <template v-if="row.participant_type === 'individual'">
                                        {{ row.participant_count }} <span class="text-xs font-normal text-slate-400">students</span>
                                    </template>
                                    <template v-else>
                                        {{ row.registration_count }} <span class="text-xs font-normal text-indigo-500">teams/groups</span>
                                    </template>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!displayRows.length">
                            <td colspan="8" class="p-6 text-center text-slate-400">No items match the selected filters.</td>
                        </tr>
                    </tbody>
                </table>
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
