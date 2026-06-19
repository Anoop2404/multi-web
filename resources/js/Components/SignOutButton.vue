<script setup>
import { router } from '@inertiajs/vue3';

defineProps({
    class: { type: String, default: '' },
    label: { type: String, default: 'Sign out' },
});

function signOut() {
    if (! window.confirm('Are you sure you want to sign out?')) {
        return;
    }

    router.post('/logout');
}
</script>

<template>
    <button type="button" :class="class" @click="signOut">
        <slot>{{ label }}</slot>
    </button>
</template>
