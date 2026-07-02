<template>
    <SahodayaEventsLayout :title="`${event.title} — Reports`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Reports`" eyebrow="Analytics"
                    description="Interactive reports and downloadable exports by event phase.">
            <template #actions>
                <span v-if="currentPhase" class="status-pill status-pill--published capitalize">{{ currentPhase }} phase</span>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="hub" />

        <p v-if="allowedPhases?.length" class="mb-6 text-sm text-slate-600">
            Download packs available for:
            <span v-for="(phase, i) in allowedPhases" :key="phase" class="font-medium capitalize">
                {{ phase }}<span v-if="i < allowedPhases.length - 1"> · </span>
            </span>
            event phases.
        </p>

        <section class="mb-8">
            <h3 class="section-title mb-3">Interactive reports</h3>
            <div v-if="interactive.length" class="hub-grid">
                <HubCard v-for="p in interactive" :key="p.id" :href="p.href" :label="p.label" icon="📊" />
            </div>
            <EmptyState v-else title="No interactive reports" description="Publish results or add registrations to unlock live report views." icon="📈" />
        </section>

        <EventPageActivityLog :logs="activityLogs" />
    </SahodayaEventsLayout>
</template>

<script setup>
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, interactive: Array, currentPhase: String, allowedPhases: Array,
    activityLogs: { type: Array, default: () => [] },
});
</script>
