<template>
    <SahodayaAdminLayout :title="`${exam.title} — Live monitor`" :sahodaya="sahodaya" :show-header-title="false">
        <PageHeader
            :title="`${exam.title} — Live monitor`"
            eyebrow="Talent Search exam"
            description="Live view from the online exam engine: who has checked in, who is writing, and who needs help."
        >
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}`" class="btn-secondary text-sm">← Exam overview</Link>
            </template>
        </PageHeader>

        <McqExamSubNav
            :sahodaya-id="sahodaya.id"
            :exam-id="exam.id"
            :delivery-mode="exam.delivery_mode || 'offline'"
            :results-published="!!exam.results_published"
            :uses-engine="!!engine.uses_engine"
            active="live"
        />

        <LiveMonitor
            :exam="exam"
            :engine="engine"
            :permissions="permissions"
            :endpoints="endpoints"
            :initial-filters="filters"
            :states="states"
            :pace-options="paceOptions"
            :registered-count="registeredCount"
            :poll-seconds="pollSeconds"
            :initial="initial"
        />
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';
import LiveMonitor from '@/Components/examEngine/LiveMonitor.vue';

defineProps({
    sahodaya: { type: Object, required: true },
    exam: { type: Object, required: true },
    engine: { type: Object, required: true },
    permissions: { type: Object, required: true },
    endpoints: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    states: { type: Array, default: () => [] },
    paceOptions: { type: Array, default: () => [] },
    registeredCount: { type: Number, default: 0 },
    pollSeconds: { type: Number, default: 5 },
    initial: { type: Object, default: null },
});
</script>
