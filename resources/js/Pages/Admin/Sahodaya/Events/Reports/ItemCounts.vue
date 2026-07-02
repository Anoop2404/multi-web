<template>
    <SahodayaEventsLayout :title="`${event.title} — Item counts`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Item registration counts`" eyebrow="Reports"
                    description="Approved and pending registrations per event item.">
            <template #actions>
                <a :href="pdfUrl" target="_blank" class="btn-secondary text-sm">Export PDF ↓</a>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="item-counts" />

        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ totals.items }}</p>
                <p class="text-xs text-slate-500 mt-1">Items</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ totals.approved }}</p>
                <p class="text-xs text-slate-500 mt-1">Approved registrations</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-amber-700">{{ totals.pending }}</p>
                <p class="text-xs text-slate-500 mt-1">Awaiting review</p>
            </div>
        </div>

        <div class="card overflow-hidden p-0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Class</th>
                        <th>Stage</th>
                        <th>Approved</th>
                        <th>Pending</th>
                        <th>Max / school</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.item_id">
                        <td class="font-medium">{{ row.title }}</td>
                        <td>{{ row.class_group || '—' }}</td>
                        <td>{{ row.stage_type || '—' }}</td>
                        <td>{{ row.approved }}</td>
                        <td>{{ row.pending }}</td>
                        <td>{{ row.max_per_school ?? '—' }}</td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="6" class="p-6 text-center text-slate-400">No items in this event.</td>
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
    rows: Array,
    totals: Object,
    pdfUrl: String,
    activityLogs: { type: Array, default: () => [] },
});
</script>
