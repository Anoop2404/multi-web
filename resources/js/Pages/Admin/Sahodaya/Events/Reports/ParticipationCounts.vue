<template>
    <SahodayaEventsLayout :title="`${event.title} — Participation`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Participation`" eyebrow="Reports"
                    description="Participation usage against policy limits.">
            <template #actions>
                <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/reports/export/student-participation`" class="btn-primary text-sm">Export CSV ↓</a>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="participation-counts" />

        <div class="mt-4 grid sm:grid-cols-2 gap-4">
            <div class="card">
                <h3 class="font-semibold text-sm mb-2">Used</h3>
                <ul class="text-sm space-y-1">
                    <li>Total entries: <strong>{{ used.total }}</strong></li>
                    <li>On-stage: {{ used.on_stage }}</li>
                    <li>Off-stage: {{ used.off_stage }}</li>
                    <li>Individual: {{ used.individual }}</li>
                    <li>Group/team: {{ used.group }}</li>
                </ul>
            </div>
            <div class="card">
                <h3 class="font-semibold text-sm mb-2">Limits (if set)</h3>
                <ul v-if="hasLimits" class="text-sm space-y-1 text-gray-600">
                    <li v-if="limits.max_onstage_per_student != null">Max on-stage / student: {{ limits.max_onstage_per_student }}</li>
                    <li v-if="limits.max_offstage_per_student != null">Max off-stage / student: {{ limits.max_offstage_per_student }}</li>
                    <li v-if="limits.max_group_per_student != null">Max group / student: {{ limits.max_group_per_student }}</li>
                </ul>
                <p v-else class="text-gray-400 text-sm">No participation limits set — configure under Event settings → Participation.</p>
            </div>
        </div>
            <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import { computed } from 'vue';

const props = defineProps({ sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number, event: Object, used: Object, limits: Object,
    activityLogs: { type: Array, default: () => [] },
});

const hasLimits = computed(() => props.limits && (
    props.limits.max_onstage_per_student != null
    || props.limits.max_offstage_per_student != null
    || props.limits.max_group_per_student != null
));
</script>
