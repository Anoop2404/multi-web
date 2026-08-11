<template>
    <PortalLayout role-label="State Judge Portal" title="State Kalolsavam" accent="amber" :nav-items="[]">
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div class="card text-center">
                    <p class="text-2xl font-bold">{{ events.length }}</p>
                    <p class="text-xs text-slate-500">Events</p>
                </div>
                <div class="card text-center">
                    <p class="text-2xl font-bold">{{ itemCount }}</p>
                    <p class="text-xs text-slate-500">Items assigned</p>
                </div>
            </div>

            <div v-if="events.length === 0" class="card text-sm text-slate-400">
                No judging assignments yet. Contact the State Kalolsavam office if you're expecting one.
            </div>

            <div v-for="event in events" :key="event.id" class="card space-y-2">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold">{{ event.name }}</h3>
                    <Link :href="`/portal/state-judge/events/${event.id}/marks`" class="text-sm text-indigo-600">Enter marks →</Link>
                </div>
                <p class="text-xs text-slate-500">{{ (assignments[event.id] || []).length }} item(s) assigned to you</p>
            </div>

            <section v-if="itemProgress.length" class="card">
                <h2 class="font-semibold text-sm mb-3">Mark entry progress</h2>
                <ul class="text-sm space-y-3">
                    <li v-for="p in itemProgress" :key="`${p.event_id}-${p.item_id}`" class="space-y-1.5">
                        <div class="flex justify-between gap-2">
                            <span class="font-medium truncate font-mono text-xs">{{ p.item_title }}</span>
                            <span class="text-xs font-semibold shrink-0" :class="p.marked >= p.total && p.total ? 'text-emerald-700' : 'text-amber-700'">
                                {{ p.marked }} / {{ p.total }} marked
                            </span>
                        </div>
                        <div class="h-2 rounded-full bg-amber-100 overflow-hidden">
                            <div class="h-full rounded-full transition-all"
                                 :class="p.marked >= p.total && p.total ? 'bg-emerald-500' : 'bg-amber-400'"
                                 :style="{ width: `${p.total ? Math.min(100, Math.round((p.marked / p.total) * 100)) : 0}%` }" />
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </PortalLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed } from 'vue';

const props = defineProps({
    events: { type: Array, default: () => [] },
    assignments: { type: Object, default: () => ({}) },
    itemProgress: { type: Array, default: () => [] },
});

const itemCount = computed(() =>
    props.events.reduce((sum, event) => sum + (props.assignments[event.id]?.length ?? 0), 0),
);
</script>
