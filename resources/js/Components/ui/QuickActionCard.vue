<template>
    <Link :href="href" class="quick-action">
        <span class="quick-action-icon" aria-hidden="true">{{ icon }}</span>
        <span class="min-w-0">
            <span class="quick-action-label">{{ label }}</span>
            <span v-if="description" class="quick-action-desc">{{ description }}</span>
        </span>
    </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: { type: String, required: true },
    label: { type: String, required: true },
    description: { type: String, default: '' },
    icon: { type: String, default: '→' },
});
</script>
