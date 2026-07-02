<template>
    <PortalLayout role-label="Group admin" title="Admit cards" :subtitle="school.name" accent="indigo" :nav-items="navItems">
        <div class="space-y-2">
            <div v-for="e in events" :key="e.id" class="card flex justify-between items-center gap-2">
                <div>
                    <p class="font-medium text-sm">{{ e.title }}</p>
                    <p class="text-xs text-gray-500 capitalize">{{ e.program }}</p>
                </div>
                <a :href="e.download_url" target="_blank" class="btn-primary text-xs">Download PDF</a>
            </div>
            <EmptyState v-if="!events.length" title="No events" description="No approved fest entries with admit cards yet." icon="🎫" />
        </div>
    </PortalLayout>
</template>

<script setup>
import { computed } from 'vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const props = defineProps({ school: Object, events: Array });
const navItems = computed(() => [
    { href: `/portal/group/${props.school.id}`, label: 'Dashboard' },
    { href: `/portal/group/${props.school.id}/fest/admit-cards`, label: 'Admit cards' },
]);
</script>
