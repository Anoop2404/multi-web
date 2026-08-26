<template>
    <SahodayaEventsLayout :title="`${event.title} — Certificate tally`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Certificate tally`" eyebrow="Operations"
                    description="Winner certificates to print, by item — team items are counted by member, not by team. Participation certificates are issued once per person for the whole event, not per item; the per-item column below shows entries, not certificate counts." />

        <div class="mb-4 flex flex-wrap gap-2">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates`" class="btn-secondary">
                &larr; Certificates
            </Link>
        </div>

        <div v-if="childEvents.length" class="card !p-4 mb-5 flex flex-wrap items-center gap-2">
            <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Region:</label>
            <SearchableSelect :model-value="String(event.id)" @update:model-value="switchSportEvent"
                               :options="regionOptions" :all-option="false" placeholder="Select region"
                               class="w-64" />
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
                <td class="px-4 py-3 text-right font-semibold text-sky-700">{{ row.participation_certs }}
                    <span class="block text-[10px] font-normal text-slate-400">entries</span>
                </td>
            </tr>
        </SahodayaDataTable>

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
    totals: { type: Object, default: () => ({ items: 0, winner_certs: 0, participation_certs: 0, grand_total: 0 }) },
    activityLogs: { type: Array, default: () => [] },
    childEvents: { type: Array, default: () => [] },
});

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
    { key: 'participation_certs', label: 'Entries', align: 'right' },
];
</script>
