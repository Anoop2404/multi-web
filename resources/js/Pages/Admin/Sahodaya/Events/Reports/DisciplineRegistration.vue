<template>
    <SahodayaEventsLayout :title="`${event.title} — Discipline registration`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Discipline-wise registration`" eyebrow="Reports"
                    description="Approved and pending registrations grouped by sport discipline.">
            <template #actions>
                <a :href="xlsUrl" class="btn-secondary text-sm">Export spreadsheet ↓</a>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="discipline-registration" />

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ totals.disciplines }}</p>
                <p class="text-xs text-slate-500 mt-1">Disciplines</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ totals.items }}</p>
                <p class="text-xs text-slate-500 mt-1">Items</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ totals.approved }}</p>
                <p class="text-xs text-slate-500 mt-1">Approved</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-amber-700">{{ totals.pending }}</p>
                <p class="text-xs text-slate-500 mt-1">Pending</p>
            </div>
        </div>

        <div class="card overflow-hidden p-0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Discipline</th>
                        <th>Items</th>
                        <th>Approved</th>
                        <th>Pending</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.discipline">
                        <td class="font-medium">{{ row.discipline_label }}</td>
                        <td>{{ row.item_count }}</td>
                        <td>{{ row.approved }}</td>
                        <td>{{ row.pending }}</td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="4" class="p-6 text-center text-slate-400">No discipline data for this event.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    rows: Array,
    xlsUrl: String,
    activityLogs: { type: Array, default: () => [] },
});

const totals = computed(() => ({
    disciplines: props.rows?.length ?? 0,
    items: (props.rows ?? []).reduce((n, r) => n + (r.item_count ?? 0), 0),
    approved: (props.rows ?? []).reduce((n, r) => n + (r.approved ?? 0), 0),
    pending: (props.rows ?? []).reduce((n, r) => n + (r.pending ?? 0), 0),
}));
</script>
