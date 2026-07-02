<template>
    <div class="min-h-screen flex items-center justify-center bg-slate-100 px-4">
        <form class="card w-full max-w-md space-y-4" @submit.prevent="submit">
            <h1 class="text-xl font-semibold text-slate-900">Change your password</h1>
            <p class="text-sm text-slate-600">You must set a new password before continuing.</p>
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

const form = useForm({
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/change-password');
}
</script>
