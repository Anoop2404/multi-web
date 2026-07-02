<template>
    <PortalLayout role-label="Group admin" title="Schedule clashes" :subtitle="school.name" accent="indigo" :nav-items="navItems">
        <p class="text-sm text-gray-500 mb-4">Students in your classes with overlapping performance slots.</p>
        <div v-if="clashes.length" class="space-y-2">
            <div v-for="(c, i) in clashes" :key="i" class="card text-sm">
                <p class="font-medium">{{ c.student_name }} <span class="text-xs text-gray-400">{{ c.school_name }}</span></p>
                <p class="text-xs text-gray-600 mt-1">{{ c.event_title }}: {{ c.event1 }} ↔ {{ c.event2 }}</p>
                <p class="text-xs text-amber-700">{{ c.time }}</p>
            </div>
        </div>
        <EmptyState v-else title="No clashes" description="No schedule conflicts for students in your classes." icon="✓" />
    </PortalLayout>
</template>

<script setup>
import { computed } from 'vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const props = defineProps({ school: Object, clashes: Array });
const navItems = computed(() => [
    { href: `/portal/group/${props.school.id}`, label: 'Dashboard' },
    { href: `/portal/group/${props.school.id}/fest/clashes`, label: 'Clashes' },
]);
</script>
