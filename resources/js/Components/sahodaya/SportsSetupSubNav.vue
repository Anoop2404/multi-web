<template>
    <nav class="flex flex-wrap gap-1.5 border-b border-slate-200 pb-3 mb-4 overflow-x-auto">
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
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { eventHasFestFees } from '@/support/sahodayaEventCapabilities.js';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    eventId: { type: [String, Number], required: true },
    active: { type: String, required: true },
    event: { type: Object, default: null },
});

const base = computed(() => `/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}`);

const tabs = computed(() => {
    const list = [
        { key: 'setup', label: 'Setup hub', href: `${base.value}/setup` },
        { key: 'competition', label: 'Item heads', href: `${base.value}/competition` },
        { key: 'items', label: 'Items under heads', href: `${base.value}/items` },
        { key: 'items-list', label: 'Item listing', href: `${base.value}/items/list` },
    ];

    if (eventHasFestFees(props.event ?? {})) {
        list.push({ key: 'fees', label: 'Fee settings', href: `${base.value}/settings/fees` });
    }

    list.push(
        // Labels below match EventSettingsSubNav's tab names exactly (see
        // sahodayaEventCapabilities.js::settingsTabsForEvent) so following a link
        // here lands on a Settings tab with the same name you clicked, not a
        // differently-worded one.
        { key: 'rank-points', label: 'Rank points', href: `${base.value}/settings/points` },
        { key: 'registration', label: 'Registration windows', href: `${base.value}/settings/registration` },
        { key: 'numbering', label: 'Chest numbering', href: `${base.value}/settings/numbering` },
        { key: 'settings', label: 'All settings', href: `${base.value}/settings` },
    );

    return list;
});
</script>
