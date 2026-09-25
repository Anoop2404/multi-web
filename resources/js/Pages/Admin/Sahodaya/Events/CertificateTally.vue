<template>
    <SahodayaEventsLayout :title="`${event.title} — Certificate tally`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Certificate tally`" eyebrow="Operations"
                    description="Winner certificates to print, by item — team items are counted by member, not by team. Participation certificates are issued once per person for the whole event, not per item; the per-item column below shows entries, not certificate counts. 'Projected' assumes 1st/2nd/3rd are awarded for every item, using each item's own registered entries (and, for team items, its 3 largest teams' real rosters) — a planning estimate for before marks are entered, not an actual count." />

        <div class="mb-4 flex flex-wrap gap-2">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates`" class="btn-secondary">
                &larr; Certificates
            </Link>
            <!-- Merit only: rank 1/2/3 and winner certificates per item + by category, no participation columns. -->
            <a v-if="rows.length" :href="rankReportUrl('pdf')" class="btn-secondary">⬇ Rank tally report (PDF)</a>
            <a v-if="rows.length" :href="rankReportUrl('xls')" class="btn-secondary">⬇ Rank tally report (Excel)</a>
        </div>

        <div v-if="childEvents.length" class="card !p-4 mb-5 flex flex-wrap items-center gap-2">
            <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Phase / Region:</label>
            <SearchableSelect :model-value="String(event.id)" @update:model-value="switchSportEvent"
                               :options="regionOptions" :all-option="false" placeholder="Select region"
                               class="w-64" />
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ totals.items }}</p>
                <p class="text-xs text-slate-500 mt-1">Items with entries</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-amber-700">{{ totals.winner_certs }}</p>
                <p class="text-xs text-slate-500 mt-1">Winner certificates</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-orange-600">{{ totals.projected_winner_certs }}</p>
                <p class="text-xs text-slate-500 mt-1">Projected (top 3)</p>
                <p class="text-[10px] text-slate-400">{{ totals.projected_winner_unique_students }} unique students · before marks</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-sky-700">{{ totals.participation_certs }}</p>
                <p class="text-xs text-slate-500 mt-1">Participation certificates</p>
                <p class="text-[10px] text-slate-400">one per person, whole event</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ totals.grand_total }}</p>
                <p class="text-xs text-slate-500 mt-1">Total to print</p>
            </div>
        </div>

        <SahodayaDataTable :columns="columns" :has-rows="rows.length > 0" empty="No approved entries yet.">
            <tr v-for="row in rows" :key="row.item_id">
                <td class="px-4 py-3">
                    <p class="font-medium">{{ row.title }}</p>
                    <p v-if="row.head_name" class="text-xs text-slate-400">{{ row.head_name }}</p>
                </td>
                <td class="px-4 py-3 text-slate-600">{{ row.category || '—' }}</td>
                <td class="px-4 py-3">
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                          :class="row.is_team ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-slate-100 text-slate-700 border border-slate-200'">
                        {{ row.is_team ? 'Team' : 'Individual' }}
                    </span>
                </td>
                <td class="px-4 py-3 text-slate-600">
                    <template v-if="row.is_team">
                        {{ row.entry_count }} team{{ row.entry_count === 1 ? '' : 's' }}
                        <span class="block text-xs text-slate-400">{{ row.member_count }} members</span>
                        <span v-if="row.standby_count" class="block text-xs text-amber-600">{{ row.standby_count }} standby</span>
                    </template>
                    <template v-else>{{ row.entry_count }}</template>
                </td>
                <td class="px-4 py-3 text-right font-semibold text-amber-700">{{ row.winner_certs }}</td>
                <td class="px-4 py-3 text-right font-semibold">{{ row.rank_1 || '—' }}</td>
                <td class="px-4 py-3 text-right font-semibold">{{ row.rank_2 || '—' }}</td>
                <td class="px-4 py-3 text-right font-semibold">{{ row.rank_3 || '—' }}</td>
                <td class="px-4 py-3 text-right font-semibold text-orange-600">{{ row.projected_winner_certs }}</td>
                <td class="px-4 py-3 text-right font-semibold text-sky-700">{{ row.participation_certs }}
                    <span class="block text-[10px] font-normal text-slate-400">entries</span>
                </td>
            </tr>
        </SahodayaDataTable>

        <!-- Summary list: podium places rolled up by category, ending in the event-wide total. -->
        <div v-if="rows.length" class="card mt-6 overflow-hidden !p-0">
            <div class="px-4 py-3 border-b border-slate-200">
                <h3 class="font-bold text-sm text-slate-800">Summary — rank 1 / 2 / 3 by category</h3>
                <p class="text-xs text-slate-400 mt-0.5">Individual items count people, team items count teams. Zero until marks are entered.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table min-w-full">
                    <thead>
                        <tr class="bg-slate-50 text-xs">
                            <th class="text-left px-4 py-2">Category</th>
                            <th class="text-right px-4 py-2">Items</th>
                            <th class="text-right px-4 py-2">Rank 1</th>
                            <th class="text-right px-4 py-2">Rank 2</th>
                            <th class="text-right px-4 py-2">Rank 3</th>
                            <th class="text-right px-4 py-2">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <tr v-for="s in summary" :key="s.category">
                            <td class="px-4 py-2 font-medium">{{ s.category }}</td>
                            <td class="px-4 py-2 text-right">{{ s.items }}</td>
                            <td class="px-4 py-2 text-right">{{ s.rank_1 }}</td>
                            <td class="px-4 py-2 text-right">{{ s.rank_2 }}</td>
                            <td class="px-4 py-2 text-right">{{ s.rank_3 }}</td>
                            <td class="px-4 py-2 text-right font-semibold">{{ s.total }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-100 font-bold text-sm border-t-2 border-slate-300">
                            <td class="px-4 py-2">All categories</td>
                            <td class="px-4 py-2 text-right">{{ totals.items }}</td>
                            <td class="px-4 py-2 text-right">{{ totals.rank_1 }}</td>
                            <td class="px-4 py-2 text-right">{{ totals.rank_2 }}</td>
                            <td class="px-4 py-2 text-right">{{ totals.rank_3 }}</td>
                            <td class="px-4 py-2 text-right">{{ (totals.rank_1 || 0) + (totals.rank_2 || 0) + (totals.rank_3 || 0) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SahodayaDataTable from '@/Components/SahodayaDataTable.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object,
    rows: { type: Array, default: () => [] },
    summary: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({ items: 0, winner_certs: 0, projected_winner_certs: 0, projected_winner_unique_students: 0, participation_certs: 0, grand_total: 0, rank_1: 0, rank_2: 0, rank_3: 0 }) },
    activityLogs: { type: Array, default: () => [] },
    childEvents: { type: Array, default: () => [] },
});

function rankReportUrl(format) {
    return `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/certificates/tally/rank-report?format=${format}`;
}

function switchSportEvent(value) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${value}/certificates/tally`);
}

const regionOptions = computed(() => props.childEvents.map(ev => ({
    value: String(ev.id),
    label: ev.short_title || ev.title,
})));

const columns = [
    { key: 'title', label: 'Item' },
    { key: 'category', label: 'Category' },
    { key: 'type', label: 'Type' },
    { key: 'entries', label: 'Entries' },
    { key: 'winner_certs', label: 'Winner certs', align: 'right' },
    { key: 'rank_1', label: 'Rank 1', align: 'right' },
    { key: 'rank_2', label: 'Rank 2', align: 'right' },
    { key: 'rank_3', label: 'Rank 3', align: 'right' },
    { key: 'projected_winner_certs', label: 'Projected (top 3)', align: 'right' },
    { key: 'participation_certs', label: 'Entries', align: 'right' },
];
</script>
