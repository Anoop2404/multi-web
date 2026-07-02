<template>
    <SahodayaEventsLayout :title="`${event.title} — Mark entry status`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Mark entry status`" eyebrow="Reports"
                    description="Track mark entry progress by item — judges assigned, marked, and pending.">
            <template #actions>
                <a :href="csvUrl" class="btn-secondary text-sm">Export CSV ↓</a>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="mark-entry-status" />

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ summary.items }}</p>
                <p class="text-xs text-slate-500 mt-1">Items</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ summary.participants }}</p>
                <p class="text-xs text-slate-500 mt-1">Participants</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ summary.marked }}</p>
                <p class="text-xs text-slate-500 mt-1">Marked</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-amber-700">{{ summary.pending }}</p>
                <p class="text-xs text-slate-500 mt-1">Pending</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ summary.complete }}/{{ summary.items }}</p>
                <p class="text-xs text-slate-500 mt-1">Items complete</p>
            </div>
        </div>

        <div class="card overflow-hidden p-0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Class</th>
                        <th>Judges</th>
                        <th>Participants</th>
                        <th>Marked</th>
                        <th>Pending</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.item_id">
                        <td class="font-medium">{{ row.title }}</td>
                        <td>{{ row.class_group || '—' }}</td>
                        <td>{{ row.judges }}</td>
                        <td>{{ row.participants }}</td>
                        <td>{{ row.marked }}</td>
                        <td>{{ row.pending }}</td>
                        <td>
                            <span class="status-pill" :class="row.complete ? 'status-pill--completed' : 'status-pill--open'">
                                {{ row.complete ? 'Complete' : 'Pending' }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="7" class="p-6 text-center text-slate-400">No items in this event.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    summary: Object,
    rows: Array,
    csvUrl: String,
    activityLogs: { type: Array, default: () => [] },
});
</script>
