<template>
    <SchoolAdminLayout :title="`${exam.title} — Live monitor`" :school="school" :show-header-title="false">
        <PageHeader
            :title="`${exam.title} — Live monitor`"
            eyebrow="Talent Search exam"
            description="Your school's candidates on the online exam engine: who has logged in, who is writing, and who needs help."
        >
            <template #actions>
                <Link :href="`/school-admin/${school.id}/mcq/${exam.id}`" class="btn-secondary text-sm">← Exam page</Link>
            </template>
        </PageHeader>

        <SchoolMcqSubNav :school-id="school.id" :exam-id="exam.id" active="live" :results-published="!!exam.results_published" uses-engine />

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
    </SchoolAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import SchoolMcqSubNav from '@/Components/school/SchoolMcqSubNav.vue';
import LiveMonitor from '@/Components/examEngine/LiveMonitor.vue';

defineProps({
    school: { type: Object, required: true },
    exam: { type: Object, required: true },
    engine: { type: Object, required: true },
    permissions: { type: Object, required: true },
    endpoints: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    states: { type: Array, default: () => [] },
    paceOptions: { type: Array, default: () => [] },
    registeredCount: { type: Number, default: 0 },
    pollSeconds: { type: Number, default: 10 },
    initial: { type: Object, default: null },
});
</script>
