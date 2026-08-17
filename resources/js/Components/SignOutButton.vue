<script setup>
import { router } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';

defineProps({
    class: { type: String, default: '' },
    label: { type: String, default: 'Sign out' },
});

const { confirm } = useConfirm();

async function signOut() {
    if (! (await confirm({ message: 'Are you sure you want to sign out?', destructive: false }))) {
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
