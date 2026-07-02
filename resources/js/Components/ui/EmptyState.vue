<template>
    <div class="empty-state">
        <div v-if="icon" class="empty-state-icon" aria-hidden="true">{{ icon }}</div>
        <h3 class="empty-state-title">{{ title }}</h3>
        <p v-if="description" class="empty-state-desc">{{ description }}</p>
        <div v-if="$slots.action" class="empty-state-action">
            <slot name="action" />
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    icon: { type: String, default: '📋' },
});
</script>
