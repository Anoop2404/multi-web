<template>
    <PortalLayout
        role-label="Event Operations"
        :title="sahodaya.name"
        subtitle="Your assigned events"
        accent="emerald"
        :nav-items="navItems"
    >
        <PortalAssignmentsHub
            title="My event operations"
            eyebrow="Fest ops portal"
            description="Events where you are assigned coordinator or operational duties."
            empty-message="No event assignments yet. Contact your Sahodaya administrator."
            :stats="stats"
            :assignments="assignmentCards"
        />
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import PortalAssignmentsHub from '@/Components/portal/PortalAssignmentsHub.vue';
import { festOpsDashboardNav } from '@/support/festOpsPortalNav.js';
import { computed } from 'vue';

const props = defineProps({ sahodaya: Object, events: Array, dutiesByEvent: Object });

const navItems = computed(() => festOpsDashboardNav(props.sahodaya.id));

const dutyCount = computed(() =>
    props.events.reduce((sum, event) => sum + (props.dutiesByEvent[event.id]?.length ?? 0), 0),
);

const stats = computed(() => [
    { label: 'Events', value: props.events.length },
    { label: 'Duty roles', value: dutyCount.value },
]);

const assignmentCards = computed(() =>
    props.events.map((event) => ({
        key: event.id,
        title: event.title,
        subtitle: event.status?.replace(/_/g, ' '),
        meta: `Duties: ${(props.dutiesByEvent[event.id] || []).join(', ')}`,
        actions: [{
            href: `/portal/fest-ops/${props.sahodaya.id}/events/${event.id}`,
            label: 'Open event',
        }],
    })),
);
</script>
