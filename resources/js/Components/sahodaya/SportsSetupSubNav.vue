<script setup>
/**
 * Horizontal tab strip for sports setup & competition workflow pages.
 * Combines Items & Item Listing into a single unified "Items" tab.
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

// Map active keys to current tab ('competition' or 'items-list' maps to 'items')
const currentActiveKey = computed(() => {
    if (['competition', 'items-list'].includes(props.active)) return 'items';
    return props.active;
});

const tabs = computed(() => {
    if (isSeason.value) {
        return [
            { key: 'setup', label: 'Season Setup', icon: '⚙️', href: `${base.value}/setup` },
            { key: 'sports', label: 'Sport Events', icon: '🏆', href: `/sahodaya-admin/${props.sahodayaId}/sports` },
        ];
    }

    return [
        { key: 'setup', label: 'Setup Hub', icon: '⚙️', href: `${base.value}/setup` },
        { key: 'settings', label: 'Settings', icon: '🛠️', href: `${base.value}/settings/fees` },
        { key: 'items', label: 'Items', icon: '🏆', href: `${base.value}/items` },
        { key: 'levels', label: 'Rounds & Levels', icon: '🔀', href: `${base.value}/levels` },
        { key: 'registrations', label: 'Registrations', icon: '📝', href: `${base.value}/registrations` },
        { key: 'fees', label: 'Event Fees', icon: '💳', href: `${base.value}/fees` },
        { key: 'chest-numbers', label: 'Chest Numbers', icon: '🔢', href: `${base.value}/chest-numbers` },
        { key: 'attendance', label: 'Attendance', icon: '📋', href: `${base.value}/attendance` },
        { key: 'marks', label: 'Marks', icon: '✍️', href: `${base.value}/marks` },
        { key: 'results', label: 'Results', icon: '🥇', href: `${base.value}/results` },
        { key: 'schedule', label: 'Schedule', icon: '📅', href: `${base.value}/schedule` },
        { key: 'activity', label: 'Activity log', icon: '🕒', href: `${base.value}/activity` },
    ];
});
</script>

<template>
    <nav class="flex flex-wrap gap-1 bg-slate-100/80 p-1.5 rounded-xl border border-slate-200/80 mb-6 overflow-x-auto shadow-inner"
         aria-label="Sports event setup navigation">
        <Link v-for="tab in tabs" :key="tab.key"
              :href="tab.href"
              :class="currentActiveKey === tab.key
                  ? 'inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-xs font-bold bg-slate-900 text-white shadow-sm transition whitespace-nowrap'
                  : 'inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-white/70 transition whitespace-nowrap'">
            <span class="text-xs opacity-90" aria-hidden="true">{{ tab.icon }}</span>
            <span>{{ tab.label }}</span>
        </Link>
    </nav>
</template>
