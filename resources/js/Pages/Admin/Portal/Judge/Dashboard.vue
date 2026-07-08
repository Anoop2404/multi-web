<template>
    <PortalLayout
        role-label="Judge Portal"
        :title="sahodaya.name"
        accent="amber"
        :nav-items="navItems"
    >
        <PortalAssignmentsHub
            title="My judging assignments"
            eyebrow="Judge portal"
            description="Events and items assigned to you for mark entry."
            empty-message="No active events with assignments for your account."
            :stats="stats"
            :assignments="assignmentCards"
        />

        <section v-if="itemProgress?.length" class="card mt-4">
            <h2 class="font-semibold text-sm mb-3">Mark entry progress</h2>
            <ul class="text-sm space-y-3">
                <li v-for="p in itemProgress" :key="`${p.event_id}-${p.item_id}`" class="space-y-1.5">
                    <div class="flex justify-between gap-2">
                        <span class="font-medium truncate">{{ p.item_title }}</span>
                        <span class="text-xs font-semibold shrink-0" :class="progressTextClass(p)">
                            {{ p.marked }} / {{ p.total }} marked
                        </span>
                    </div>
                    <div class="h-2 rounded-full bg-amber-100 overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all"
                            :class="progressBarClass(p)"
                            :style="{ width: `${progressPercent(p)}%` }"
                        />
                    </div>
                </li>
            </ul>
        </section>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import PortalAssignmentsHub from '@/Components/portal/PortalAssignmentsHub.vue';
import { judgePortalNavItems } from '@/support/judgePortalNav.js';
import { computed } from 'vue';

const props = defineProps({ sahodaya: Object, events: Array, assignments: Object, itemProgress: { type: Array, default: () => [] } });

const navItems = computed(() => {
    const eventId = props.events?.length === 1 ? props.events[0].id : null;

    return judgePortalNavItems(props.sahodaya.id, eventId);
});

const itemCount = computed(() =>
    props.events.reduce((sum, event) => sum + (props.assignments[event.id]?.length ?? 0), 0),
);

const stats = computed(() => [
    { label: 'Events', value: props.events.length },
    { label: 'Items assigned', value: itemCount.value },
]);

const assignmentCards = computed(() =>
    props.events.map((event) => {
        const items = props.assignments[event.id] ?? [];
        return {
            key: event.id,
            title: event.title,
            subtitle: [event.event_type?.replace(/_/g, ' '), event.status?.replace(/_/g, ' ')].filter(Boolean).join(' · '),
            details: items.map((a) => a.item?.title).filter(Boolean),
            actions: [{
                href: `/portal/judge/${props.sahodaya.id}/events/${event.id}/marks`,
                label: 'Enter marks',
            }],
        };
    }),
);

function progressPercent(p) {
    if (!p.total) return 0;
    return Math.min(100, Math.round((p.marked / p.total) * 100));
}

function progressTextClass(p) {
    return p.marked >= p.total && p.total ? 'text-emerald-700' : 'text-amber-700';
}

function progressBarClass(p) {
    if (!p.total) return 'bg-gray-300';
    if (p.marked >= p.total) return 'bg-emerald-500';
    if (p.marked > 0) return 'bg-amber-500';
    return 'bg-amber-300';
}
</script>
