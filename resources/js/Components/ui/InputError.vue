<template>
    <p v-if="message" class="form-error" role="alert">{{ message }}</p>
</template>

<script setup>
defineProps({
    message: { type: String, default: '' },
});
</script>
