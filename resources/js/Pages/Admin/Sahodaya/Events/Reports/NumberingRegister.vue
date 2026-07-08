<template>
    <SahodayaEventsLayout :title="`${event.title} — Numbering register`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Numbering register`" eyebrow="Reports"
                    description="Fest ID, item registration number and chest number for all active registrations.">
            <template #actions>
                <a :href="xlsUrl" class="btn-secondary text-sm">Export Excel ↓</a>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="numbering-register" />

        <div class="card overflow-hidden p-0">
            <table class="data-table text-sm">
                <thead>
                    <tr>
                        <th>Head</th><th>Item</th><th>School</th><th>Participant</th><th>Reg no</th>
                        <th>Status</th><th>Fest ID</th><th>Item reg</th><th>Chest</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.participant_id">
                        <td class="text-xs text-slate-500">{{ row.head_name ?? '—' }}</td>
                        <td>{{ row.item }}</td>
                        <td class="text-xs">{{ row.school }}</td>
                        <td class="font-medium">{{ row.name }}</td>
                        <td>{{ row.reg_no ?? '—' }}</td>
                        <td><span :class="row.reg_status === 'approved' ? 'text-emerald-700' : 'text-amber-700'">{{ row.reg_status }}</span></td>
                        <td class="font-mono text-xs">{{ row.fest_id ?? '—' }}</td>
                        <td class="font-mono text-xs">{{ row.item_reg ?? '—' }}</td>
                        <td class="font-mono font-bold">{{ row.chest_no ?? '—' }}</td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="9" class="p-6 text-center text-slate-400">No registrations yet.</td></tr>
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
    rows: Array, xlsUrl: String,
    activityLogs: { type: Array, default: () => [] },
});
</script>
