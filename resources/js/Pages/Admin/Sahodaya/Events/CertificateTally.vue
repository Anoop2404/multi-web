<template>
    <SahodayaEventsLayout :title="`${event.title} — Certificate tally`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Certificate tally`" eyebrow="Operations"
                    description="How many winner and participation certificates to print, by item. Team items are counted by member, not by team — this is what you'd hand a print shop." />

        <div class="mb-4 flex flex-wrap gap-2">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates`" class="btn-secondary">
                &larr; Certificates
            </Link>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ totals.items }}</p>
                <p class="text-xs text-slate-500 mt-1">Items with entries</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-amber-700">{{ totals.winner_certs }}</p>
                <p class="text-xs text-slate-500 mt-1">Winner certificates</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-sky-700">{{ totals.participation_certs }}</p>
                <p class="text-xs text-slate-500 mt-1">Participation certificates</p>
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
                    </template>
                    <template v-else>{{ row.entry_count }}</template>
                </td>
                <td class="px-4 py-3 text-right font-semibold text-amber-700">{{ row.winner_certs }}</td>
                <td class="px-4 py-3 text-right font-semibold text-sky-700">{{ row.participation_certs }}</td>
            </tr>
        </SahodayaDataTable>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SahodayaDataTable from '@/Components/SahodayaDataTable.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object,
    rows: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({ items: 0, winner_certs: 0, participation_certs: 0, grand_total: 0 }) },
    activityLogs: { type: Array, default: () => [] },
});

const columns = [
    { key: 'title', label: 'Item' },
    { key: 'category', label: 'Category' },
    { key: 'type', label: 'Type' },
    { key: 'entries', label: 'Entries' },
    { key: 'winner_certs', label: 'Winner certs', align: 'right' },
    { key: 'participation_certs', label: 'Participation certs', align: 'right' },
];
</script>
