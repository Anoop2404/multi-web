<template>
    <SahodayaEventsLayout :title="`${event.title} — Houses`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Houses`" eyebrow="Reports"
                    description="House-wise points and standings.">
            <template #actions>
                <a :href="pdfUrl" target="_blank" class="btn-primary text-sm">Download PDF ↓</a>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="house-detailed" />

        <ol class="mt-4 bg-white border rounded-xl divide-y">
            <li v-for="row in board" :key="row.house_id" class="p-4 flex justify-between text-sm">
                <span>#{{ row.rank }} {{ row.house_name }}</span>
                <span class="font-mono">{{ row.total_points }} pts</span>
            </li>
            <li v-if="!board.length" class="p-4 text-gray-400">No houses configured.</li>
        </ol>
            <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

defineProps({ sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number, event: Object, board: Array, pdfUrl: String,
    activityLogs: { type: Array, default: () => [] },
});
</script>
