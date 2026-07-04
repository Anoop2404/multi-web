<template>
    <div class="page-header">
        <div class="min-w-0 flex-1">
            <BreadcrumbTrail v-if="breadcrumbs?.length" :items="breadcrumbs" />
            <p v-if="eyebrow" class="page-header-eyebrow">{{ eyebrow }}</p>
            <h2 v-if="title" class="page-header-title">{{ title }}</h2>
            <p v-if="description" class="page-header-desc">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="page-header-actions shrink-0">
            <slot name="actions" />
        </div>
    </div>
</template>

<script setup>
import BreadcrumbTrail from '@/Components/ui/BreadcrumbTrail.vue';

defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    eyebrow: { type: String, default: '' },
    breadcrumbs: { type: Array, default: () => [] },
});
</script>
