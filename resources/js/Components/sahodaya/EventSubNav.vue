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

const tabs = computed(() => {
    const list = [
        { key: 'overview', label: 'Overview', href: base.value },
        { key: 'items', label: 'Catalog & rules', href: `${base.value}/items` },
        { key: 'items-list', label: 'All items', href: `${base.value}/items/list` },
        { key: 'levels', label: 'Rounds & promotion', href: `${base.value}/levels` },
    ];
    if (resolvedEventType.value === 'sports') {
        list.splice(1, 0,
            { key: 'setup', label: 'Setup hub', href: `${base.value}/setup` },
            { key: 'competition', label: 'Event Heads', href: `${base.value}/competition` },
        );
    }
    list.push({ key: 'activity', label: 'Log', href: `${base.value}/activity` });
    return list;
});
</script>
