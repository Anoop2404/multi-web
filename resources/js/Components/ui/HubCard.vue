<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        class="hub-card"
        :class="{ 'hub-card--static': !href }"
    >
        <span v-if="icon" class="hub-card-icon" aria-hidden="true">{{ icon }}</span>
        <span class="hub-card-label">{{ label }}</span>
        <span v-if="hint" class="hub-card-hint">{{ hint }}</span>
    </component>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: { type: String, default: '' },
    label: { type: String, required: true },
    hint: { type: String, default: '' },
    icon: { type: String, default: '' },
});
</script>
