<template>
    <div v-if="message" class="flash-banner mb-4" :class="`flash-banner--${type}`" role="alert" aria-live="assertive">
        <span class="flash-banner-icon" aria-hidden="true">{{ icon }}</span>
        <span class="flex-1">{{ message }}</span>
        <button
            v-if="dismissible"
            type="button"
            class="shrink-0 leading-none opacity-60 hover:opacity-100"
            aria-label="Dismiss"
            @click="$emit('dismiss')"
        >
            ✕
        </button>
    </div>
</template>

<script setup>
/**
 * Drop-in replacement for native alert() popups. Renders using the same
 * flash-banner classes as FlashBanner.vue (resources/css/app.css) so
 * client-side validation/error messages look identical to server-flashed
 * ones, just scoped to a single component instead of the whole page.
 *
 * Usage:
 *   const notice = ref('');
 *   const noticeType = ref('error');
 *   ...
 *   notice.value = 'A reason/note is required.'; // was: alert('...')
 *   ...
 *   <InlineAlert :message="notice" :type="noticeType" @dismiss="notice = ''" />
 */
import { computed } from 'vue';

const props = defineProps({
    message: { type: String, default: '' },
    type: { type: String, default: 'error' }, // success | error | warning | info
    dismissible: { type: Boolean, default: true },
});
defineEmits(['dismiss']);

const icon = computed(() => ({ success: '✓', error: '✕', warning: '!', info: 'i' }[props.type] ?? 'i'));
</script>
