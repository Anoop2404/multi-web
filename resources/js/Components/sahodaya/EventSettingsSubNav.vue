<template>
    <nav class="mb-6 flex flex-wrap gap-1.5 bg-slate-100/80 p-1.5 rounded-xl border border-slate-200/80 shadow-inner overflow-x-auto" aria-label="Event settings navigation">
        <Link v-for="tab in tabs" :key="tab.id"
              :href="`${base}/settings/${tab.id}`"
              :class="currentTab === tab.id
                  ? 'inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-xs font-bold bg-slate-900 text-white shadow-sm transition whitespace-nowrap'
                  : 'inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-white/70 transition whitespace-nowrap'">
            <span>{{ tab.label }}</span>
        </Link>
    </nav>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { settingsTabsForEvent } from '@/support/sahodayaEventCapabilities.js';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    eventId: { type: [String, Number], required: true },
    event: { type: Object, required: true },
    activeTab: { type: String, default: 'fees' },
});

const base = computed(() => `/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}`);
const tabs = computed(() => settingsTabsForEvent(props.event));

// Map legacy sub-tab keys to one of the 4 primary group tabs
const currentTab = computed(() => {
    const a = props.activeTab;
    if (['fees', 'registration', 'participation'].includes(a)) return 'fees';
    if (['points', 'eligibility', 'grades', 'combo', 'records'].includes(a)) return 'points';
    if (['venues', 'numbering', 'volunteers'].includes(a)) return 'venues';
    if (['lifecycle', 'locks', 'clone'].includes(a)) return 'lifecycle';
    return a;
});
</script>
