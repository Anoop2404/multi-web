<template>
    <div class="min-h-screen flex items-center justify-center bg-slate-100 px-4">
        <form class="card w-full max-w-md space-y-4" @submit.prevent="submit">
            <h1 class="text-xl font-semibold text-slate-900">Change your password</h1>
            <p v-if="organizationName" class="text-sm text-slate-700">
                Welcome to <strong>{{ organizationName }}</strong>.
                <span v-if="roleLabel"> Your {{ roleLabel }} account</span>
                was set up by an administrator — choose a personal password to continue.
            </p>
            <p v-else class="text-sm text-slate-600">
                Your account was set up with a temporary password. Choose a new one before continuing.
            </p>
            <label class="block text-sm">
                <span class="font-medium">New password</span>
                <input v-model="form.password" type="password" class="field mt-1" required minlength="8">
            </label>
            <label class="block text-sm">
                <span class="font-medium">Confirm password</span>
                <input v-model="form.password_confirmation" type="password" class="field mt-1" required minlength="8">
            </label>
            <button type="submit" class="btn-primary w-full" :disabled="form.processing">Save password</button>
        </form>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';

defineProps({
    organizationName: { type: String, default: null },
    roleLabel: { type: String, default: null },
});

const form = useForm({
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/change-password');
}
</script>
