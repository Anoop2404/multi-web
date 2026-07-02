<template>
    <div class="grid gap-4 sm:grid-cols-2" :class="classExtra">
        <slot />
    </div>
</template>

<script setup>
defineProps({
    classExtra: { type: String, default: '' },
});
</script>
