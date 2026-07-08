<template>
    <SahodayaEventsLayout :title="`${event.title} — Pending approvals`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Pending approvals`" eyebrow="Reports"
                    description="Submitted registrations awaiting Sahodaya approval — blocks chest assignment and mark entry.">
            <template #actions>
                <a :href="xlsUrl" class="btn-secondary text-sm">Export Excel ↓</a>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="pending-approvals" />

        <form @submit.prevent="applyFilter" class="card !p-4 mb-4 flex flex-wrap gap-3 items-end">
            <FormField label="School" class-extra="mb-0">
                <select v-model="schoolFilter" class="field text-sm w-56">
                    <option value="">All schools</option>
                    <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </FormField>
            <button type="submit" class="btn-primary text-sm">Apply</button>
        </form>

        <div class="card overflow-hidden p-0">
            <table class="data-table">
                <thead>
                    <tr><th>School</th><th>Head</th><th>Item</th><th>Participants</th><th>Names</th></tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.registration_id">
                        <td class="font-medium">{{ row.school }}</td>
                        <td class="text-xs text-slate-500">{{ row.head_name ?? '—' }}</td>
                        <td>{{ row.item }}</td>
                        <td>{{ row.participant_count }}</td>
                        <td class="text-sm">{{ (row.participants ?? []).join(', ') || '—' }}</td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="5" class="p-6 text-center text-slate-400">No pending registrations.</td></tr>
                </tbody>
            </table>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number, event: Object,
    rows: Array, schools: Array, filterSchoolId: String, xlsUrl: String,
    activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/pending-approvals`;
const schoolFilter = ref(props.filterSchoolId ?? '');

function applyFilter() {
    router.get(base, { school_id: schoolFilter.value || undefined }, { preserveState: true });
}
</script>
