<template>
    <nav class="flex flex-wrap gap-2 border-b border-slate-200 pb-4 mb-4">
        <Link v-for="tab in tabs" :key="tab.key"
              :href="tab.href"
              :class="active === tab.key ? 'subnav-link subnav-link--active' : 'subnav-link'">
            {{ tab.label }}
        </Link>
    </nav>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    programId: { type: [String, Number], required: true },
    active: { type: String, default: 'overview' },
});

const base = computed(() => `/sahodaya-admin/${props.sahodayaId}/training/${props.programId}`);

const tabs = computed(() => [
    { key: 'overview', label: 'Overview', href: base.value },
    { key: 'registrations', label: 'Registrations', href: `${base.value}/registrations` },
    { key: 'feedback', label: 'Feedback', href: `${base.value}/feedback` },
    { key: 'qr-reports', label: 'QR reports', href: `${base.value}/qr-reports` },
]);
</script>
