<template>
    <div v-if="impersonating" class="impersonation-banner flex items-center justify-between gap-3 px-4 py-2.5 text-sm font-medium">
        <div class="flex items-center gap-2">
            <SvgIcon name="alert-circle" class="w-4 h-4 shrink-0" />
            <span>You're viewing this account as another user. Every action here is logged.</span>
        </div>
        <button type="button" class="impersonation-exit-btn shrink-0 px-3 py-1 rounded-lg text-xs font-semibold" @click="exit">
            Exit impersonation
        </button>
    </div>
</template>

<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import SvgIcon from '@/Components/icons/SvgIcon.vue';

const page = usePage();
const impersonating = computed(() => page.props.impersonating);

function exit() {
    router.post('/impersonate/end');
}
</script>

<style scoped>
.impersonation-banner {
    background: #fef9ec;
    border-bottom: 2px solid #d4a017;
    color: #041525;
}

.impersonation-exit-btn {
    background: #041525;
    color: #fef9ec;
    transition: opacity 0.15s;
}

.impersonation-exit-btn:hover {
    opacity: 0.85;
}
</style>
