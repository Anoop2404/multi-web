<template>
    <nav class="flex flex-wrap gap-2 border-b border-slate-200 pb-4 mb-2">
        <Link v-for="tab in tabs" :key="tab.key"
              :href="tab.href"
              :class="active === tab.key ? 'subnav-link subnav-link--active' : 'subnav-link'">
            {{ tab.label }}
        </Link>
    </nav>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    programSlug: { type: String, required: true },
    active: { type: String, required: true },
});

const page = usePage();

const eventQuery = computed(() => {
    const id = page.props.event?.id;
    return id ? `?event_id=${id}` : '';
});

const base = computed(() => `/sahodaya-admin/${props.sahodayaId}/programs/${props.programSlug}/catalog`);

const tabs = computed(() => [
    { key: 'hub', label: 'Overview', href: `${base.value}${eventQuery.value}` },
    { key: 'master', label: 'Items & fees', href: `${base.value}/master${eventQuery.value}` },
    { key: 'list', label: 'Item listing', href: `${base.value}/list${eventQuery.value}` },
    { key: 'assign', label: 'Assign to event', href: `${base.value}/assign${eventQuery.value}` },
]);
</script>
