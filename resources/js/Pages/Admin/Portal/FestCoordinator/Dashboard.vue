<template>
    <PortalLayout
        role-label="Fest Mark Entry"
        :title="sahodaya.name"
        accent="indigo"
        :nav-items="navItems"
    >
        <PortalAssignmentsHub
            title="My mark entry events"
            eyebrow="Mark coordinator"
            description="Events where you are assigned mark entry coordinator duties."
            empty-message="No events assigned yet. Ask your Sahodaya admin to assign the Mark entry coordinator duty on Event Staff."
            :stats="stats"
            :assignments="assignmentCards"
        />
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import PortalAssignmentsHub from '@/Components/portal/PortalAssignmentsHub.vue';
import { computed } from 'vue';

const props = defineProps({ sahodaya: Object, events: Array });

const navItems = computed(() => [
    { href: `/portal/fest-coordinator/${props.sahodaya.id}`, label: 'Dashboard' },
]);

const pendingTotal = computed(() =>
    props.events.reduce((sum, event) => sum + (event.pending ?? 0), 0),
);

const stats = computed(() => [
    { label: 'Events', value: props.events.length },
    { label: 'Pending marks', value: pendingTotal.value },
]);

const assignmentCards = computed(() =>
    props.events.map((event) => ({
        key: event.id,
        title: event.title,
        subtitle: [event.level_round, event.status?.replace(/_/g, ' ')].filter(Boolean).join(' · '),
        badge: `${event.marks_entered ?? 0} / ${event.participants ?? 0}`,
        badgeHint: event.pending ? `${event.pending} pending` : '',
        actions: [{
            href: `/portal/fest-coordinator/${props.sahodaya.id}/events/${event.id}/marks`,
            label: 'Enter marks',
        }],
    })),
);
</script>
