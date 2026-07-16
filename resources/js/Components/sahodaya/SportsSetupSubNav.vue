<script setup>
/**
 * Horizontal strip for sports setup / competition pages.
 * Season hub: Setup + sport events list. Sport event: Setup → Items → Competition.
 */
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { isSportsSeasonEvent } from '@/support/sportsEventNav.js';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    eventId: { type: [String, Number], required: true },
    active: { type: String, required: true },
    event: { type: Object, default: null },
});

const base = computed(() => `/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}`);
const isSeason = computed(() => isSportsSeasonEvent(props.event));

const tabs = computed(() => {
    if (isSeason.value) {
        return [
            { key: 'setup', label: 'Season setup', href: `${base.value}/setup` },
            { key: 'sports', label: 'Sport events', href: `/sahodaya-admin/${props.sahodayaId}/sports` },
        ];
    }

    return [
        { key: 'setup', label: 'Setup hub', href: `${base.value}/setup` },
        { key: 'items', label: 'Items', href: `${base.value}/items` },
        { key: 'items-list', label: 'Item listing', href: `${base.value}/items/list` },
    ];
});
</script>

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
