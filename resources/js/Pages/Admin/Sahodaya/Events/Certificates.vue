<template>
    <SahodayaEventsLayout :title="`${event.title} — Certificates`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Certificates`" eyebrow="Operations"
                    description="Generate and manage participant certificates." />
        <div class="mb-4 flex flex-wrap gap-2">
            <button @click="generate" class="btn-primary">Generate for top 3</button>
            <a v-if="certificates.length"
               :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/certificates/download-zip`"
               class="btn-secondary">Download all (ZIP)</a>
        </div>
        <ul class="card-list">
            <li v-for="c in certificates" :key="c.id" class="p-4 flex justify-between items-center text-sm">
                <div>
                    <p class="font-medium">{{ c.student?.name ?? 'Participant' }}</p>
                    <p class="text-gray-500 text-xs">{{ c.item?.title }} · Position {{ c.mark?.position ?? '—' }}</p>
                </div>
                <a :href="`/certificates/verify/${c.uuid}`" target="_blank" class="text-indigo-600 text-xs font-medium mr-3">Verify ↗</a>
                <a :href="`/certificates/print/${c.uuid}`" target="_blank" class="text-gray-600 text-xs font-medium">Print ↗</a>
            </li>
            <li v-if="!certificates.length" class="p-4 text-gray-400 text-sm">No certificates yet. Publish results or click Generate.</li>
        </ul>
            <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, certificates: Array,
    activityLogs: { type: Array, default: () => [] },
});

function generate() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/certificates/generate`, {}, { preserveScroll: true });
}
</script>
