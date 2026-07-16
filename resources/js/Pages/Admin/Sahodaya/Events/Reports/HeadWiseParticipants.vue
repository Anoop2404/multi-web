<template>
    <SahodayaEventsLayout :title="event.event_type === 'sports' ? `${event.title} — Sport-wise participants` : `${event.title} — Head-wise participants`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="event.event_type === 'sports' ? `${event.title} — Sport-wise participants` : `${event.title} — Head-wise participants`" eyebrow="Reports"
                    :description="event.event_type === 'sports'
                        ? 'Participants grouped by Sport Event (Athletics, Chess, etc.).'
                        : 'Participants grouped by item head.'">
            <template #actions>
                <Link v-if="event.event_type === 'sports'"
                      :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/reports`"
                      class="btn-secondary text-sm">
                    ← All report types
                </Link>
                <Link v-else :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/reports`"
                      class="btn-secondary text-sm">
                    All report types
                </Link>
                <a :href="xlsUrl" class="btn-secondary text-sm">Export spreadsheet ↓</a>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="head-wise-participants" />

        <ReportHeadFilter v-if="hasItemHeads"
                          v-model="headFilter"
                          v-model:item-id="itemFilter"
                          :heads="headsForFilter"
                          :head-item-groups="headItemGroups"
                          :is-sports="event.event_type === 'sports'"
                          @apply="applyFilter">
            <template #extra>
                <FormField label="School" class-extra="mb-0 min-w-[14rem]">
                    <select v-model="schoolFilter" class="field text-sm">
                        <option value="">All schools</option>
                        <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </FormField>
            </template>
        </ReportHeadFilter>

        <form v-else @submit.prevent="applyFilter" class="card !p-4 mb-4 flex flex-wrap gap-3 items-end">
            <FormField label="School" class-extra="mb-0">
                <select v-model="schoolFilter" class="field text-sm w-56">
                    <option value="">All schools</option>
                    <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </FormField>
            <button type="submit" class="btn-primary text-sm">Apply</button>
        </form>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ totals.heads }}</p>
                <p class="text-xs text-slate-500 mt-1">{{ event.event_type === 'sports' ? 'Sport Events' : 'Item heads' }}</p>
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

        <section class="mb-8">
            <h3 class="section-title mb-3">{{ event.event_type === 'sports' ? 'Summary by Sport Event' : 'Summary by head' }}</h3>
            <div class="card overflow-hidden p-0">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ event.event_type === 'sports' ? 'Sport Event' : 'Head' }}</th>
                            <th>Items</th>
                            <th>Regs</th>
                            <th>Approved</th>
                            <th>Pending</th>
                            <th>Waitlist</th>
                            <th>Participants</th>
                            <th>Verified</th>
                            <th>Due ₹</th>
                            <th>Collected ₹</th>
                            <th>Pending ₹</th>
                            <th>Max item regs</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in summary" :key="row.head_id">
                            <td class="font-medium">{{ row.head_name }}</td>
                            <td>{{ row.item_count }}</td>
                            <td>{{ row.registration_count ?? 0 }}</td>
                            <td>{{ row.approved_count ?? 0 }}</td>
                            <td>{{ row.pending_count ?? 0 }}</td>
                            <td>{{ row.waitlisted_count ?? 0 }}</td>
                            <td>{{ row.participant_count }}</td>
                            <td>
                                {{ row.verified_count ?? 0 }}
                                <span v-if="(row.unverified_count ?? 0) > 0" class="text-xs text-amber-700">
                                    / {{ row.unverified_count }} unverified
                                </span>
                            </td>
                            <td>{{ Number(row.due_total ?? 0).toLocaleString('en-IN') }}</td>
                            <td class="text-emerald-700">{{ Number(row.collected_total ?? 0).toLocaleString('en-IN') }}</td>
                            <td class="text-amber-700">{{ Number(row.pending_fee_total ?? 0).toLocaleString('en-IN') }}</td>
                            <td>
                                <span v-if="row.max_item_title" class="text-xs text-slate-500 block">{{ row.max_item_title }}</span>
                                {{ row.busiest_item_regs ?? row.max_item_reg_count ?? 0 }}
                            </td>
                        </tr>
                        <tr v-if="!summary.length">
                            <td colspan="12" class="p-6 text-center text-slate-400">
                                {{ event.event_type === 'sports'
                                    ? 'No Event Heads on this event. Sync heads from Event settings → Event Heads.'
                                    : 'No item heads on this event. Sync heads from Event settings → Item heads.' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section>
            <h3 class="section-title mb-3">Participant list</h3>
            <div class="card overflow-hidden p-0">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Head</th>
                            <th>School</th>
                            <th>Participant</th>
                            <th>Reg no</th>
                            <th>Item</th>
                            <th>Fest ID</th>
                            <th>Item reg</th>
                            <th>Chest</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(row, idx) in displayRows" :key="`${row.head_id}-${row.item_id}-${idx}`">
                            <tr v-if="shouldShowHeadDivider(row, displayRows[idx - 1])" class="bg-slate-50/80">
                                <td colspan="8" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    {{ row.head_name ?? 'Other items' }}
                                </td>
                            </tr>
                            <tr>
                                <td>{{ row.head_name }}</td>
                                <td>{{ row.school }}</td>
                                <td class="font-medium">
                                    {{ row.student }}
                                    <div class="flex flex-wrap gap-1 mt-0.5 text-[10px]">
                                        <span v-if="row.role" class="px-1.5 py-0.5 rounded font-bold uppercase tracking-wide"
                                              :class="row.role === 'standby' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'">
                                            {{ row.role }}
                                        </span>
                                        <span v-if="row.team_name" class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 font-semibold">
                                            👥 {{ row.team_name }}
                                        </span>
                                    </div>
                                </td>
                                <td>{{ row.reg_no }}</td>
                                <td>
                                    {{ row.item }}
                                    <div v-if="row.competition_start && event.event_type === 'sports'" class="mt-0.5">
                                        <span class="inline-flex items-center gap-1 rounded bg-sky-50 px-1 py-0.5 text-[9px] font-bold text-sky-800 border border-sky-100 uppercase tracking-wide">
                                            📅 {{ formatDate(row.competition_start) }}<span v-if="row.competition_time"> @ {{ row.competition_time.slice(0, 5) }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td>{{ row.fest_id ?? '—' }}</td>
                                <td class="font-mono text-xs">{{ row.item_reg ?? '—' }}</td>
                                <td>{{ row.chest_no ?? '—' }}</td>
                            </tr>
                        </template>
                        <tr v-if="!displayRows.length && summary.length">
                            <td colspan="8" class="p-6 text-center text-slate-400">No participants for the selected filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
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
    summary: Array,
    rows: Array,
    schools: Array,
    filterHeadId: [String, Number],
    filterItemId: [String, Number],
    filterSchoolId: [String, Number],
    xlsUrl: String,
    activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/head-wise-participants`;
const schoolFilter = ref(props.filterSchoolId ?? '');

const {
    headFilter,
    itemFilter,
    headItemGroups,
    headsForFilter,
    hasItemHeads,
    displayRows,
    shouldShowHeadDivider,
    applyFilter: applyHeadFilter,
} = useReportHeadFilters(base, () => props.rows);

if (props.filterHeadId) headFilter.value = String(props.filterHeadId);
if (props.filterItemId) itemFilter.value = String(props.filterItemId);

const totals = computed(() => ({
    heads: props.summary?.length ?? 0,
    items: (props.summary ?? []).reduce((n, r) => n + (r.item_count ?? 0), 0),
    registrations: (props.summary ?? []).reduce((n, r) => n + (r.registration_count ?? 0), 0),
    participants: displayRows.value.length,
    pending: (props.summary ?? []).reduce((n, r) => n + (r.pending_count ?? 0), 0),
}));

function applyFilter() {
    applyHeadFilter({ school_id: schoolFilter.value || undefined });
}

function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(`${iso}T12:00:00`);
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
}
</script>
