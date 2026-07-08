<template>
    <SahodayaEventsLayout :title="`${event.title} — Assignment completeness`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Assignment completeness`" eyebrow="Reports"
                    description="Per item: registrations, chest & item reg assignment, scheduling, judges and mark entry progress.">
            <template #actions>
                <a :href="xlsUrl" class="btn-secondary text-sm">Export Excel ↓</a>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="assignment-completeness" />

        <div class="grid grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center"><p class="text-xl font-bold">{{ totals.items }}</p><p class="text-xs text-slate-500 mt-1">Items</p></div>
            <div class="card card--muted !py-4 text-center"><p class="text-xl font-bold">{{ totals.performers }}</p><p class="text-xs text-slate-500 mt-1">Performers</p></div>
            <div class="card card--muted !py-4 text-center"><p class="text-xl font-bold text-amber-700">{{ totals.pending_regs }}</p><p class="text-xs text-slate-500 mt-1">Pending regs</p></div>
            <div class="card card--muted !py-4 text-center"><p class="text-xl font-bold text-red-700">{{ totals.chest_missing }}</p><p class="text-xs text-slate-500 mt-1">Chest missing</p></div>
            <div class="card card--muted !py-4 text-center"><p class="text-xl font-bold text-red-700">{{ totals.item_reg_missing }}</p><p class="text-xs text-slate-500 mt-1">Item reg missing</p></div>
            <div class="card card--muted !py-4 text-center"><p class="text-xl font-bold text-indigo-700">{{ totals.marks_pending }}</p><p class="text-xs text-slate-500 mt-1">Marks pending</p></div>
        </div>

        <div class="card overflow-hidden p-0">
            <table class="data-table text-sm">
                <thead>
                    <tr>
                        <th>Head</th><th>Item</th><th>Approved</th><th>Pending</th><th>Performers</th>
                        <th>Chest</th><th>Item reg</th><th>Scheduled</th><th>Marks</th><th>Judges</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.item_id" :class="row.chest_missing || row.item_reg_missing ? 'bg-amber-50/50' : ''">
                        <td class="text-xs text-slate-500">{{ row.head_name ?? '—' }}</td>
                        <td class="font-medium">{{ row.title }}</td>
                        <td>{{ row.approved }}</td>
                        <td>{{ row.pending }}</td>
                        <td>{{ row.performers }}</td>
                        <td>{{ row.chest_assigned }}<span v-if="row.chest_missing" class="text-red-600 text-xs"> (−{{ row.chest_missing }})</span></td>
                        <td>{{ row.item_reg_assigned }}<span v-if="row.item_reg_missing" class="text-red-600 text-xs"> (−{{ row.item_reg_missing }})</span></td>
                        <td>{{ row.item_scheduled ? '✓ item' : '—' }} · {{ row.participants_scheduled }} ppl</td>
                        <td>{{ row.marks_entered }}<span v-if="row.marks_pending" class="text-amber-700 text-xs"> / {{ row.marks_pending }} left</span></td>
                        <td>{{ row.judges_assigned }}</td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="10" class="p-6 text-center text-slate-400">No items on this event.</td></tr>
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
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number, event: Object,
    rows: Array, totals: Object, xlsUrl: String,
    activityLogs: { type: Array, default: () => [] },
});
</script>
