<template>
    <nav class="flex flex-wrap gap-2 border-b border-slate-200 pb-4 mb-2">
        <Link v-for="tab in tabs" :key="tab.key"
              :href="tab.href"
              :class="active === tab.key ? 'subnav-link subnav-link--active' : 'subnav-link'">
            {{ tab.label }}
        </Link>
    </nav>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    eventId: { type: [String, Number], required: true },
    active: { type: String, required: true },
});

const base = computed(() => `/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}`);

const tabs = computed(() => [
    { key: 'overview', label: 'Overview', href: base.value },
    { key: 'items', label: 'Items setup', href: `${base.value}/items` },
    { key: 'items-list', label: 'Item listing', href: `${base.value}/items/list` },
    { key: 'levels', label: 'Levels & cascade', href: `${base.value}/levels` },
    { key: 'activity', label: 'Activity log', href: `${base.value}/activity` },
]);
</script>
