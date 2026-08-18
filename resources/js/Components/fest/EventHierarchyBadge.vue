<template>
    <div v-if="hierarchy && !hierarchy.is_hub && (hierarchy.parent_event || hierarchy.region || hierarchy.cluster_label || hierarchy.phase)"
         class="flex flex-wrap items-center gap-2 mb-4 text-xs">
        <Link v-if="hubHref" :href="hubHref"
              class="inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1 font-semibold text-indigo-700 hover:bg-indigo-100 transition">
            <span aria-hidden="true">←</span>
            <span>{{ hierarchy.parent_event?.title || 'Hub event' }}</span>
        </Link>
        <span v-else-if="hierarchy.parent_event"
              class="inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1 font-semibold text-indigo-700">
            {{ hierarchy.parent_event.title }}
        </span>

        <span v-if="hierarchy.region || hierarchy.cluster_label"
              class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 font-semibold text-slate-600">
            <span aria-hidden="true">📍</span>
            <span>{{ hierarchy.cluster_label || hierarchy.region?.name }}</span>
        </span>

        <span v-if="hierarchy.phase"
              class="inline-flex items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1 font-semibold text-amber-700">
            <span aria-hidden="true">⚡</span>
            <span>{{ hierarchy.phase.name }}</span>
        </span>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    /** Shape of FestEvent::hierarchyContext(): { is_hub, has_children, parent_event, region, phase, cluster_label, partition_role } */
    hierarchy: { type: Object, default: null },
    /** Only Sahodaya admins can navigate to the hub — omit on School pages, which renders the parent-event chip as plain text instead of a link. */
    hubHref: { type: String, default: null },
});
</script>
