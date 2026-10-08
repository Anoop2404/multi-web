<template>
    <PortalLayout
        role-label="Event Operations"
        :title="event.title"
        :subtitle="sahodaya.name"
        accent="emerald"
        :nav-items="navItems"
    >
        <EmptyState
            v-if="!dutyLinks.length"
            title="No duties assigned"
            description="Ask your Sahodaya admin to assign event ops duties for this fest."
            icon="🔐"
        />
        <div v-else class="hub-grid">
            <HubCard
                v-for="link in dutyLinks"
                :key="link.duty"
                :href="link.href"
                :label="link.label"
                :hint="link.hint"
                :icon="link.icon ?? dutyIcons[link.duty] ?? '📋'"
            />
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { festOpsEventNav } from '@/support/festOpsPortalNav.js';
import { computed } from 'vue';

const props = defineProps({ sahodaya: Object, event: Object, duties: Array, canManageEvent: { type: Boolean, default: false } });

const dutyMeta = {
    coordinator:  { label: 'Event coordinator', hint: 'Overview & stats', path: 'coordinator' },
    registration: { label: 'Registration desk', hint: 'Approve or reject entries', path: 'registrations' },
    stage:        { label: 'Stage manager', hint: 'Live schedule queue', path: 'stage' },
    attendance:   { label: 'Attendance', hint: 'Mark item attendance', path: 'attendance' },
    food:         { label: 'Kitchen board', hint: 'Meal orders & status', path: 'kitchen' },
    appeals:      { label: 'Appeals officer', hint: 'Review participant appeals', path: 'appeals' },
    certificates: { label: 'Certificates', hint: 'View & print certificates', path: 'certificates' },
    marks:        { label: 'Mark entry', hint: 'Enter scores & grades', path: 'marks' },
};

const dutyIcons = {
    coordinator: '🎯',
    registration: '📝',
    stage: '🎭',
    attendance: '✓',
    food: '🍽',
    appeals: '⚖️',
    certificates: '🏅',
    marks: '📊',
};

const dutyLinks = computed(() => {
    const base = `/portal/fest-ops/${props.sahodaya.id}/events/${props.event.id}`;
    const links = (props.duties || [])
        .filter(d => dutyMeta[d])
        .map(d => ({
            duty: d,
            label: dutyMeta[d].label,
            hint: dutyMeta[d].hint,
            href: `${base}/${dutyMeta[d].path}`,
        }));
    if (props.canManageEvent) {
        const adminBase = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
        links.unshift(
            { duty: 'manage', label: 'Manage event', hint: 'Settings, registrations, results & certificates', href: adminBase, icon: '⚙️' },
            { duty: 'batches', label: 'Reporting batches', hint: 'Manage batches & reporting lists', href: `${adminBase}/reporting-batches`, icon: '📋' },
            { duty: 'venues', label: 'Venues & stages', hint: 'Add and manage venues and stages', href: `${adminBase}/settings/venues`, icon: '🏟️' },
            { duty: 'areas', label: 'Competition areas', hint: 'Manage competition spaces', href: `${adminBase}/areas`, icon: '📍' },
            { duty: 'schedule', label: 'Scheduling', hint: 'Plan competition times & stages', href: `${adminBase}/schedule`, icon: '📅' },
            { duty: 'items', label: 'Items & catalog', hint: 'Edit assigned event items', href: `${adminBase}/items`, icon: '📝' },
        );
    }
    links.push({ duty: 'search', label: 'Participant search', hint: 'Lookup & admit cards', href: `${base}/participants/search`, icon: '🔍' });
    return links;
});

const navItems = computed(() => festOpsEventNav(props.sahodaya.id, props.event.id, props.duties, props.canManageEvent));
</script>
