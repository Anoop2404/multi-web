<template>
    <div class="min-h-screen flex items-center justify-center bg-slate-100 px-4 py-10">
        <div class="card w-full max-w-lg space-y-6">
            <div class="text-center space-y-2">
                <p class="text-3xl">👋</p>
                <h1 class="text-xl font-bold text-slate-900">Welcome{{ organizationName ? ` to ${organizationName}` : '' }}</h1>
                <p v-if="roleLabel" class="text-xs font-semibold uppercase tracking-wide text-indigo-600">{{ roleLabel }}</p>
            </div>

            <p class="text-sm text-slate-600 leading-relaxed text-center">{{ welcomeText }}</p>

            <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Common actions</p>
                <Link v-for="action in actions" :key="action.href"
                      :href="action.href"
                      class="flex items-center justify-between rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-800 hover:border-indigo-300 hover:bg-indigo-50/50 transition">
                    {{ action.label }}
                    <span class="text-slate-400">→</span>
                </Link>
            </div>

            <form @submit.prevent="submit">
                <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                    Got it — take me to my dashboard →
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';

defineProps({
    organizationName: { type: String, default: null },
    roleLabel: { type: String, default: null },
    welcomeText: { type: String, required: true },
    actions: { type: Array, default: () => [] },
    dashboardUrl: { type: String, default: '/' },
});

const form = useForm({});

function submit() {
    form.post('/portal/welcome');
}
</script>
