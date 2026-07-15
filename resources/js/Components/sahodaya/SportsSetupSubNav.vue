<template>
    <nav class="flex flex-wrap gap-1.5 border-b border-slate-200 pb-3 mb-4 overflow-x-auto"
         aria-label="Sports event setup">
        <Link v-for="tab in tabs" :key="tab.key"
              :href="tab.href"
              :class="active === tab.key
                  ? 'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold bg-[#0f3d7a] text-white whitespace-nowrap'
                  : 'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100 whitespace-nowrap'">
            {{ tab.label }}
        </Link>
    </nav>
</template>

<script setup>
/**
 * Horizontal strip for sports setup / competition pages.
 * After Head = Event: Setup → Items (no Event Heads competition hub).
 */
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    eventId: { type: [String, Number], required: true },
    active: { type: String, required: true },
    event: { type: Object, default: null },
});

const base = computed(() => `/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}`);

const tabs = computed(() => [
    { key: 'setup', label: 'Setup hub', href: `${base.value}/setup` },
    { key: 'items', label: 'Items', href: `${base.value}/items` },
    { key: 'items-list', label: 'Item listing', href: `${base.value}/items/list` },
    { key: 'competition', label: 'Competition', href: `${base.value}/competition` },
]);
</script>
