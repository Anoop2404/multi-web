<template>
    <div class="min-h-screen flex items-center justify-center px-4 py-10 bg-gradient-to-br from-[#0a2a52] via-[#0f3d7a] to-[#1a5a9e]">
        <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl p-8 sm:p-10 text-center">
            <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-[#0f3d7a]/10 text-[#0f3d7a]">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                </svg>
            </div>
            <p v-if="tenantName" class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">{{ tenantName }}</p>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Attend exam</h1>
            <p class="mt-2 text-sm text-slate-600">Enter your venue keycode to open your exam. No portal password needed.</p>

            <form class="mt-8 space-y-4 text-left" @submit.prevent="submit">
                <div>
                    <label for="keycode" class="block text-sm font-semibold text-slate-800 mb-1.5">Venue keycode</label>
                    <input
                        id="keycode"
                        v-model="form.keycode"
                        type="text"
                        required
                        maxlength="16"
                        autocomplete="off"
                        autocapitalize="characters"
                        spellcheck="false"
                        class="field w-full text-center text-lg font-bold tracking-[0.2em] uppercase"
                        :class="{ 'border-red-400': form.errors.keycode }"
                        placeholder="ABCD-1234"
                    >
                    <p v-if="form.errors.keycode" class="mt-1.5 text-sm text-red-700">{{ form.errors.keycode }}</p>
                </div>
                <button type="submit" class="btn-primary w-full justify-center" :disabled="form.processing">
                    {{ form.processing ? 'Opening…' : 'Start exam' }}
                </button>
            </form>

            <p class="mt-6 text-xs text-slate-500">
                Prefer the portal?
                <a href="/portal/login" class="font-semibold text-[#0f3d7a] hover:underline">Sign in</a>
            </p>
        </div>
    </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    keycode: { type: String, default: '' },
    tenantName: { type: String, default: null },
});

const form = useForm({
    keycode: (props.keycode || '').replace(/[^A-Za-z0-9]/g, '').toUpperCase(),
});

function submit() {
    form.keycode = String(form.keycode || '').replace(/[^A-Za-z0-9]/g, '').toUpperCase();
    form.post('/attend-exam');
}
</script>
