<template>
    <div class="auth-login-alerts">
        <div v-if="sessionExpired && !authError"
             class="auth-login-alert auth-login-alert--warn"
             role="status">
            Your session has expired. Please sign in again.
        </div>
        <div v-if="flashError"
             class="auth-login-alert auth-login-alert--warn"
             role="status">
            {{ flashError }}
        </div>
        <div v-if="authError"
             class="auth-login-alert auth-login-alert--error"
             role="alert">
            {{ authError }}
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

defineProps({
    sessionExpired: { type: Boolean, default: false },
    authError: { type: String, default: '' },
});

const page = usePage();
const flashError = computed(() => page.props.flash?.error ?? '');
</script>

<style scoped>
.auth-login-alerts {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}
.auth-login-alert {
    border-radius: 0.65rem;
    padding: 0.65rem 0.85rem;
    font-size: 0.8125rem;
    line-height: 1.45;
}
.auth-login-alert--warn {
    border: 1px solid #fcd34d;
    background: #fffbeb;
    color: #92400e;
}
.auth-login-alert--error {
    border: 1px solid #fca5a5;
    background: #fef2f2;
    color: #b91c1c;
    font-weight: 600;
}
</style>
