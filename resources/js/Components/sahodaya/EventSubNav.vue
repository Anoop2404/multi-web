<template>
    <nav class="flex flex-wrap gap-1.5 border-b border-slate-200 pb-3 mb-4">
        <Link v-for="tab in tabs" :key="tab.key"
              :href="tab.href"
              :class="active === tab.key
                  ? 'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold bg-[#0f3d7a] text-white'
                  : 'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100'">
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
    { key: 'items', label: 'Items', href: `${base.value}/items` },
    { key: 'items-list', label: 'Item list', href: `${base.value}/items/list` },
    { key: 'levels', label: 'Rounds & promotion', href: `${base.value}/levels` },
    { key: 'activity', label: 'Log', href: `${base.value}/activity` },
]);
</script>
