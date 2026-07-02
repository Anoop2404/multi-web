<template>
    <div v-if="success || error" class="flash-stack" role="status" aria-live="polite">
        <div v-if="success" class="flash-banner flash-banner--success">
            <span class="flash-banner-icon" aria-hidden="true">✓</span>
            <span>{{ success }}</span>
        </div>
        <div v-if="error" class="flash-banner flash-banner--error">
            <span class="flash-banner-icon" aria-hidden="true">✕</span>
            <span>{{ error }}</span>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const success = computed(() => page.props.flash?.success ?? '');
const error = computed(() => page.props.flash?.error ?? '');
</script>
