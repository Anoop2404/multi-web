<template>
    <nav class="mb-6 space-y-3 border-b border-slate-200 pb-4">
        <div class="flex flex-wrap items-center gap-2">
            <Link :href="`${base}/reports`"
                  :class="active === 'hub' ? 'subnav-link subnav-link--active' : 'subnav-link'">
                Overview
            </Link>
            <Link v-for="phase in phases" :key="phase.key"
                  :href="`${base}/reports/downloads/${phase.key}`"
                  :class="active === phase.key ? 'subnav-link subnav-link--active' : 'subnav-link'">
                {{ phase.label }}
            </Link>
        </div>
        <div v-if="navItems.length" class="flex flex-wrap gap-2">
            <Link v-for="report in navItems" :key="report.id"
                  :href="report.href"
                  :class="active === report.id ? 'subnav-link subnav-link--active' : 'subnav-link'">
                {{ report.label }}
            </Link>
        </div>
    </nav>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    eventId: { type: [String, Number], required: true },
    active: { type: String, default: 'hub' },
    interactiveNav: { type: Array, default: null },
});

const page = usePage();

const base = computed(() => `/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}`);

const navItems = computed(() => props.interactiveNav ?? page.props.interactiveNav ?? []);

const phases = [
    { key: 'before', label: 'Before event' },
    { key: 'during', label: 'During event' },
    { key: 'after', label: 'After event' },
];
</script>
