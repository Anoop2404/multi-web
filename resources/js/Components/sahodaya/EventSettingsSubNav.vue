<template>
    <nav class="mb-6 flex flex-wrap gap-2 border-b border-slate-200 pb-2">
        <Link v-for="tab in tabs" :key="tab.id"
              :href="`${base}/settings/${tab.id}`"
              :class="['tab-btn', activeTab === tab.id ? 'tab-btn--active' : '']">
            {{ tab.label }}
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
    activeTab: { type: String, default: 'lifecycle' },
});

const base = computed(() => `/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}`);
const tabs = computed(() => settingsTabsForEvent(props.event));
</script>
