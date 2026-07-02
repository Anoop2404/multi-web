<template>
    <div class="form-actions" :class="{ 'form-actions--sticky': sticky }">
        <slot />
    </div>
</template>

<script setup>
defineProps({
    sticky: { type: Boolean, default: false },
});
</script>
