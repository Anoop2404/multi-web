<template>
    <nav class="flex flex-wrap gap-1 bg-slate-100/80 p-1.5 rounded-xl border border-slate-200/80 mb-6 overflow-x-auto shadow-inner"
         aria-label="Event navigation">
        <Link v-for="tab in tabs" :key="tab.key"
              :href="tab.href"
              :class="currentActiveKey === tab.key
                  ? 'inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-xs font-bold bg-slate-900 text-white shadow-sm transition whitespace-nowrap'
                  : 'inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-white/70 transition whitespace-nowrap'">
            <span v-if="tab.icon" class="text-xs opacity-90" aria-hidden="true">{{ tab.icon }}</span>
            <span>{{ tab.label }}</span>
        </Link>
    </nav>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    eventId: { type: [String, Number], required: true },
    active: { type: String, required: true },
    eventType: { type: String, default: null },
});

const page = usePage();
const resolvedEventType = computed(() => props.eventType ?? page.props.event?.event_type ?? null);
const base = computed(() => `/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}`);

// Map legacy active keys to current tab ('items-list', 'competition' -> 'items')
const currentActiveKey = computed(() => {
    if (['items-list', 'competition'].includes(props.active)) return 'items';
    return props.active;
});

const tabs = computed(() => {
    const list = [
        { key: 'overview', label: 'Overview', icon: '📊', href: base.value },
        { key: 'settings', label: 'Settings', icon: '⚙️', href: `${base.value}/settings/fees` },
        { key: 'items', label: 'Items', icon: '🏆', href: `${base.value}/items` },
        { key: 'levels', label: 'Rounds & Levels', icon: '🔀', href: `${base.value}/levels` },
        { key: 'registrations', label: 'Registrations', icon: '📝', href: `${base.value}/registrations` },
        { key: 'fees', label: 'Event Fees', icon: '💳', href: `${base.value}/fees` },
        { key: 'chest-numbers', label: 'Chest Numbers', icon: '🔢', href: `${base.value}/chest-numbers` },
        { key: 'attendance', label: 'Attendance', icon: '📋', href: `${base.value}/attendance` },
        { key: 'marks', label: 'Marks', icon: '✍️', href: `${base.value}/marks` },
        { key: 'results', label: 'Results', icon: '🥇', href: `${base.value}/results` },
        { key: 'activity', label: 'Log', icon: '🕒', href: `${base.value}/activity` },
    ];

    if (resolvedEventType.value === 'sports') {
        // Swap settings for setup hub for sports
        const idx = list.findIndex(t => t.key === 'settings');
        if (idx !== -1) {
            list[idx] = { key: 'setup', label: 'Setup Hub', icon: '⚙️', href: `${base.value}/setup` };
        }
    }

    return list;
});
</script>
