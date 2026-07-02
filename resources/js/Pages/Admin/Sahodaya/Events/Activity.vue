<template>
    <SahodayaEventsLayout :title="`${event.title} — Activity`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Activity log`" eyebrow="Audit trail"
                    description="All actions across this event, grouped newest first." />

        <EventSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="activity" />

        <div class="form-section overflow-hidden !p-0">
            <EmptyState v-if="!activityLogs.length" title="No activity yet" description="Actions on this event will appear here." icon="📋" class="p-8" />
            <div v-else class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-36">When</th>
                            <th class="w-40">Page</th>
                            <th>Action</th>
                            <th class="w-32">User</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in activityLogs" :key="log.id">
                            <td class="text-xs text-slate-500">{{ formatTime(log.created_at) }}</td>
                            <td><span class="text-xs font-medium text-slate-600">{{ log.page_label }}</span></td>
                            <td class="text-sm text-slate-800">{{ log.description }}</td>
                            <td class="text-xs text-slate-500">{{ log.user?.name ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';

defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, activityLogs: { type: Array, default: () => [] },
    pageLabels: Object,
});

function formatTime(iso) {
    if (!iso) return '';
    const d = new Date(iso.replace(' ', 'T'));
    return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>
