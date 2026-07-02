<template>
    <div class="form-section">
        <div v-if="title || hint" class="form-section-head">
            <h3 v-if="title" class="form-section-title">{{ title }}</h3>
            <p v-if="hint" class="form-section-hint">{{ hint }}</p>
        </div>
        <div class="form-section-body">
            <slot />
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: { type: String, default: '' },
    hint: { type: String, default: '' },
});
</script>
