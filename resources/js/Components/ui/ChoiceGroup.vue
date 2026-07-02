<template>
    <div class="choice-group">
        <p v-if="label" class="form-label">{{ label }}</p>
        <p v-if="hint" class="form-hint mb-2">{{ hint }}</p>
        <div class="flex flex-wrap gap-2">
            <slot />
        </div>
        <p v-if="error" class="form-error mt-2" role="alert">{{ error }}</p>
    </div>
</template>

<script setup>
defineProps({
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
});
</script>
