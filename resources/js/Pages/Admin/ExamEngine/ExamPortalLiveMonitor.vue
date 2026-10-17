<template>
    <PortalLayout
        role-label="Exam Portal"
        :title="exam.title"
        subtitle="Live monitor"
        accent="emerald"
        :nav-items="navItems"
    >
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
    </PortalLayout>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import LiveMonitor from '@/Components/examEngine/LiveMonitor.vue';
import { examPortalNavItems } from '@/support/examPortalNav.js';

const props = defineProps({
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

const currentRole = computed(() => {
    const roles = usePage().props.auth?.user?.roles ?? [];
    return roles.includes('exam_staff') && !roles.includes('exam_controller') && !roles.includes('sahodaya_admin') ? 'exam_staff' : null;
});
const navItems = computed(() => examPortalNavItems(props.sahodaya.id, props.exam.id, {
    role: currentRole.value,
    usesEngine: !!props.engine.uses_engine,
}));
</script>
