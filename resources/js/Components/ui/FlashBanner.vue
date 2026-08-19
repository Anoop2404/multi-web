<template>
    <div v-if="success || error || warning || info" class="flash-stack" role="status" aria-live="polite">
        <div v-if="success" class="flash-banner flash-banner--success">
            <span class="flash-banner-icon" aria-hidden="true">✓</span>
            <span>{{ success }}</span>
        </div>
        <div v-if="error" class="flash-banner flash-banner--error">
            <span class="flash-banner-icon" aria-hidden="true">✕</span>
            <span>{{ error }}</span>
        </div>
        <div v-if="warning" class="flash-banner flash-banner--warning">
            <span class="flash-banner-icon" aria-hidden="true">!</span>
            <span>{{ warning }}</span>
        </div>
        <div v-if="info" class="flash-banner flash-banner--info">
            <span class="flash-banner-icon" aria-hidden="true">i</span>
            <span>{{ info }}</span>
        </div>
    </div>
</template>

<script setup>
import { computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const success = computed(() => page.props.flash?.success ?? '');
const error = computed(() => page.props.flash?.error ?? '');
const warning = computed(() => page.props.flash?.warning ?? '');
const info = computed(() => page.props.flash?.info ?? '');

// Only error/warning force a scroll to top — those need attention the user might
// not be expecting. A routine success/info flash (e.g. "Item updated.") shouldn't
// yank someone back to the top of a long list they were working through — see the
// Sahodaya admin Items & catalog page, where every edit/delete used to do exactly that.
watch([error, warning], () => {
    if (error.value || warning.value) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
});
</script>
