<template>
    <AdminLayout title="Developer Pass Token">
        <PageHeader
            title="Developer Login Pass Token"
            eyebrow="Superadmin Configuration"
            description="Set or update the master developer pass token to access user accounts by username during development and support."
        />

        <div class="max-w-2xl space-y-6">
            <div v-if="success" class="p-4 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-xl text-sm font-medium">
                {{ success }}
            </div>

            <div class="card p-6 space-y-4 bg-white border border-slate-200 rounded-xl shadow-sm">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Current Pass Token Status</h3>
                    <p class="text-xs text-slate-500 mt-1">
                        When active, logging in with any user's <strong>username</strong> or <strong>email</strong> and this <strong>pass token</strong> as the password authenticates as that user.
                    </p>
                </div>

                <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg flex items-center justify-between">
                    <div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 block">Active Pass Token</span>
                        <code class="text-sm font-mono font-bold text-slate-900 mt-0.5 inline-block">
                            {{ activeToken || 'None (Disabled)' }}
                        </code>
                    </div>
                    <span
                        class="px-2.5 py-1 text-xs font-semibold rounded-full"
                        :class="activeToken ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                    >
                        {{ activeToken ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>

                <form @submit.prevent="submit" class="space-y-4 pt-2">
                    <div>
                        <label for="dev_pass_token" class="block text-sm font-medium text-slate-700">Set New Pass Token</label>
                        <input
                            id="dev_pass_token"
                            v-model="form.dev_pass_token"
                            type="text"
                            placeholder="e.g. sahodaya-dev-pass-2026"
                            class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm px-3 py-2 border"
                            required
                        />
                        <p v-if="form.errors.dev_pass_token" class="text-xs text-red-600 mt-1">
                            {{ form.errors.dev_pass_token }}
                        </p>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm disabled:opacity-50 transition-colors"
                        >
                            {{ form.processing ? 'Saving...' : 'Save Pass Token' }}
                        </button>

                        <button
                            type="button"
                            @click="disableToken"
                            :disabled="form.processing || !activeToken"
                            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-lg disabled:opacity-50 transition-colors"
                        >
                            Disable Token
                        </button>
                    </div>
                </form>
            </div>

            <div class="p-4 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800 space-y-1">
                <p class="font-semibold text-amber-900">🔒 Security Notice & Audit Trail</p>
                <p>
                    Every authentication attempt using the developer pass token is logged in the <strong>Audit Logs</strong> with full details (IP, timestamp, user account).
                </p>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    activeToken: { type: String, default: null },
    success: { type: String, default: null },
});

const form = useForm({
    dev_pass_token: props.activeToken || '',
});

const submit = () => {
    form.post('/admin/dev-pass-token');
};

const disableToken = () => {
    form.dev_pass_token = '';
    form.post('/admin/dev-pass-token');
};
</script>
