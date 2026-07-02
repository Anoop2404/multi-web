<template>
    <SahodayaEventsLayout :title="`${event.title} — Ranking`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Ranking`" eyebrow="Reports"
                    description="Overall school ranking from published marks.">
            <template #actions>
                <a :href="pdfUrl" target="_blank" class="btn-primary text-sm">Download PDF ↓</a>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="overall-ranking" />

        <ol class="mt-4 bg-white border rounded-xl divide-y">
            <li v-for="row in rankings" :key="row.id" class="p-4 flex justify-between text-sm">
                <span><strong>#{{ row.rank }}</strong> {{ row.name }}</span>
                <span class="text-gray-500">🥇{{ row.gold }} 🥈{{ row.silver }} 🥉{{ row.bronze }} · <strong>{{ row.total_points }}</strong> pts</span>
            </li>
        </ol>
            <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

defineProps({ sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number, event: Object, rankings: Array, pdfUrl: String,
    activityLogs: { type: Array, default: () => [] },
});
</script>
