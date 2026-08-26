<template>
    <div class="page-header">
        <div class="min-w-0 flex-1 sm:min-w-[280px]">
            <BreadcrumbTrail :items="resolvedBreadcrumbs" />
            <p v-if="eyebrow" class="page-header-eyebrow">{{ eyebrow }}</p>
            <h2 v-if="title" class="page-header-title">{{ title }}</h2>
            <p v-if="description" class="page-header-desc">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="page-header-actions w-full sm:w-auto">
            <slot name="actions" />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import BreadcrumbTrail from '@/Components/ui/BreadcrumbTrail.vue';
import { autoBreadcrumbs } from '@/support/breadcrumbs.js';

const props = defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    eyebrow: { type: String, default: '' },
    breadcrumbs: { type: Array, default: () => [] },
});

const page = usePage();

const resolvedBreadcrumbs = computed(() => {
    if (props.breadcrumbs.length) {
        return props.breadcrumbs;
    }

    const ancestors = autoBreadcrumbs(page.url, page.props);
    const last = ancestors[ancestors.length - 1];
    const trail = (props.title && last?.label.toLowerCase() !== props.title.toLowerCase())
        ? [...ancestors, { label: props.title }]
        : ancestors;

    // A single crumb ("Dashboard" alone) has nowhere to navigate — not worth showing.
    return trail.length > 1 ? trail : [];
});
</script>
