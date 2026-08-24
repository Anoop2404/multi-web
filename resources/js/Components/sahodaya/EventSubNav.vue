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
import {
    FEST_FINANCE,
    FEST_MANAGE,
    FEST_MARKS,
    FEST_REGISTRATIONS,
    FEST_RESULTS,
    FEST_SETTINGS,
    FEST_VIEW,
    staffCanSeeNavItem,
} from '@/support/sahodayaEventNavPermissions.js';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    eventId: { type: [String, Number], required: true },
    active: { type: String, required: true },
    eventType: { type: String, default: null },
});

const page = usePage();
const resolvedEventType = computed(() => props.eventType ?? page.props.event?.event_type ?? null);
const base = computed(() => `/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}`);
const isStaffUser = computed(() => page.props.isStaff ?? false);
const staffPermissions = computed(() => page.props.staffPermissions ?? []);

// Map legacy active keys to current tab ('items-list', 'competition' -> 'items')
const currentActiveKey = computed(() => {
    if (['items-list', 'competition'].includes(props.active)) return 'items';
    return props.active;
});

const tabs = computed(() => {
    const list = [
        { key: 'overview', label: 'Overview', icon: '📊', href: base.value, permissions: FEST_VIEW },
        { key: 'settings', label: 'Settings', icon: '⚙️', href: `${base.value}/settings/fees`, permissions: FEST_SETTINGS },
        { key: 'items', label: 'Items', icon: '🏆', href: `${base.value}/items`, permissions: FEST_SETTINGS },
        { key: 'levels', label: 'Rounds & Levels', icon: '🔀', href: `${base.value}/levels`, permissions: FEST_SETTINGS },
        { key: 'phases', label: 'Phases', icon: '🧭', href: `${base.value}/phases`, permissions: FEST_SETTINGS },
        { key: 'registrations', label: 'Registrations', icon: '📝', href: `${base.value}/registrations`, permissions: FEST_REGISTRATIONS },
        { key: 'fees', label: 'Event Fees', icon: '💳', href: `${base.value}/fees`, permissions: FEST_FINANCE },
        { key: 'chest-numbers', label: 'Chest Numbers', icon: '🔢', href: `${base.value}/chest-numbers`, permissions: FEST_MANAGE },
        { key: 'attendance', label: 'Attendance', icon: '📋', href: `${base.value}/attendance`, permissions: FEST_REGISTRATIONS },
        { key: 'marks', label: 'Marks', icon: '✍️', href: `${base.value}/marks`, permissions: FEST_MARKS },
        { key: 'mark-settings', label: 'Mark Settings', icon: '🎚️', href: `${base.value}/mark-settings`, permissions: FEST_MARKS },
        { key: 'grade-master', label: 'Grade Master', icon: '🎓', href: `${base.value}/grade-master`, permissions: FEST_SETTINGS },
        { key: 'rank-points', label: resolvedEventType.value === 'sports' ? 'Rank Points' : 'Grade Points Master', icon: '🏅', href: `${base.value}/rank-points`, permissions: FEST_SETTINGS },
        { key: 'results', label: 'Results', icon: '🥇', href: `${base.value}/results`, permissions: FEST_RESULTS },
        { key: 'activity', label: 'Log', icon: '🕒', href: `${base.value}/activity`, permissions: FEST_VIEW },
    ];

    if (resolvedEventType.value === 'sports') {
        // Swap settings for setup hub for sports
        const idx = list.findIndex(t => t.key === 'settings');
        if (idx !== -1) {
            list[idx] = { key: 'setup', label: 'Setup Hub', icon: '⚙️', href: `${base.value}/setup`, permissions: FEST_SETTINGS };
        }
    }

    if (!isStaffUser.value) {
        return list;
    }

    const perms = staffPermissions.value;
    return list.filter((tab) => staffCanSeeNavItem(tab, perms));
});
</script>
