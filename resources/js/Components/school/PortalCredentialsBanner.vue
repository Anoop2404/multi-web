<template>
    <div v-if="credentials" class="notice-banner notice-banner--success mb-4 text-sm">
        <p class="font-semibold mb-1">
            Portal login<span v-if="personName"> — {{ personName }}</span>
        </p>
        <p>
            Username: <strong class="font-mono">{{ credentials.username }}</strong>
            · Temp password: <strong class="font-mono">{{ credentials.password }}</strong>
        </p>
        <p class="text-xs mt-1 opacity-90">
            Shown once — share with the user. They must change the password on first login.
        </p>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    credentials: { type: Object, default: null },
});

const page = usePage();

const credentials = computed(() => props.credentials ?? page.props.flash?.newCredentials ?? null);

const personName = computed(() =>
    credentials.value?.student_name
    ?? credentials.value?.teacher_name
    ?? credentials.value?.name
    ?? null,
);
</script>
