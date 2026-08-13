<template>
    <AdminLayout title="Developer Pass Token">
        <PageHeader
            title="Developer Login Pass Token"
            eyebrow="Superadmin Configuration"
            description="Inspect, set, generate, or test the master developer pass token to access user accounts by username during development and support."
        />

        <div class="max-w-4xl space-y-6">
            <div v-if="success" class="p-4 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-xl text-sm font-medium flex items-center justify-between">
                <span>{{ success }}</span>
                <span class="text-xs bg-emerald-100 text-emerald-900 px-2 py-0.5 rounded font-mono">Updated</span>
            </div>

            <!-- Active Pass Token Card -->
            <div class="card p-6 space-y-5 bg-white border border-slate-200 rounded-xl shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Current Pass Token Status</h3>
                        <p class="text-xs text-slate-500 mt-1">
                            When active, entering any user's <strong>username</strong> or <strong>email</strong> along with this <strong>pass token</strong> authenticates directly as that account.
                        </p>
                    </div>
                    <span
                        class="px-3 py-1 text-xs font-semibold rounded-full shrink-0"
                        :class="activeToken ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-amber-100 text-amber-800 border border-amber-200'"
                    >
                        {{ activeToken ? '● Active & Enabled' : '○ Disabled' }}
                    </span>
                </div>

                <div class="p-4 bg-slate-900 text-white rounded-xl flex flex-wrap items-center justify-between gap-4">
                    <div class="space-y-1">
                        <span class="text-[11px] uppercase tracking-wider text-slate-400 font-semibold block">Active Pass Token</span>
                        <div class="flex items-center gap-3">
                            <code class="text-base font-mono font-bold tracking-wide text-emerald-400">
                                {{ activeToken ? (showToken ? activeToken : '••••••••••••••••') : 'None (Disabled)' }}
                            </code>
                            <button
                                v-if="activeToken"
                                type="button"
                                @click="showToken = !showToken"
                                class="text-slate-400 hover:text-white text-xs underline transition-colors"
                            >
                                {{ showToken ? 'Hide' : 'Reveal' }}
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="activeToken"
                            type="button"
                            @click="copyToClipboard(activeToken, 'token')"
                            class="px-3.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium rounded-lg border border-slate-700 transition-colors flex items-center gap-1.5"
                        >
                            <span>{{ copiedToken ? '✓ Copied!' : '📋 Copy Token' }}</span>
                        </button>

                        <button
                            type="button"
                            @click="regenerateToken"
                            :disabled="regenerateForm.processing"
                            class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg shadow-sm transition-colors disabled:opacity-50 flex items-center gap-1.5"
                        >
                            <span>⚡ {{ regenerateForm.processing ? 'Generating...' : 'Generate New Token' }}</span>
                        </button>
                    </div>
                </div>

                <!-- Update Form -->
                <form @submit.prevent="submit" class="space-y-4 pt-2 border-t border-slate-100">
                    <div>
                        <label for="dev_pass_token" class="block text-sm font-medium text-slate-700">Set Custom Pass Token</label>
                        <div class="mt-1 flex gap-2">
                            <input
                                id="dev_pass_token"
                                v-model="form.dev_pass_token"
                                type="text"
                                placeholder="e.g. sahodaya-dev-pass-2026"
                                class="block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm px-3 py-2 border font-mono"
                            />
                            <button
                                type="button"
                                @click="generateRandomCustomToken"
                                class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg border border-slate-300 transition-colors shrink-0"
                            >
                                🎲 Fill Random
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm disabled:opacity-50 transition-colors shrink-0"
                            >
                                {{ form.processing ? 'Saving...' : 'Save Token' }}
                            </button>
                            <button
                                type="button"
                                @click="disableToken"
                                :disabled="form.processing || !activeToken"
                                class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium rounded-lg disabled:opacity-50 transition-colors shrink-0"
                            >
                                Disable
                            </button>
                        </div>
                        <p v-if="form.errors.dev_pass_token" class="text-xs text-red-600 mt-1">
                            {{ form.errors.dev_pass_token }}
                        </p>
                    </div>
                </form>
            </div>

            <!-- Target User Search & Credential Helper -->
            <div class="card p-6 space-y-4 bg-white border border-slate-200 rounded-xl shadow-sm">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">User Account Verification Helper</h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Search any user account by username or email to verify their login details and generate login test snippets.
                    </p>
                </div>

                <div class="flex gap-2">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search username, email, or name..."
                        @keyup.enter="searchUsers"
                        class="block w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm px-3 py-2 border"
                    />
                    <button
                        type="button"
                        @click="searchUsers"
                        class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium rounded-lg transition-colors shrink-0"
                    >
                        Search
                    </button>
                </div>

                <div v-if="searchResults && searchResults.length > 0" class="overflow-x-auto border border-slate-200 rounded-lg">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50 text-slate-700 font-semibold border-b border-slate-200">
                            <tr>
                                <th class="p-3">User Details</th>
                                <th class="p-3">Username</th>
                                <th class="p-3">Account Type / Tenant</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="user in searchResults" :key="user.id" class="hover:bg-slate-50">
                                <td class="p-3">
                                    <p class="font-medium text-slate-900">{{ user.name }}</p>
                                    <p class="text-slate-500">{{ user.email }}</p>
                                </td>
                                <td class="p-3">
                                    <code class="px-1.5 py-0.5 bg-slate-100 rounded text-slate-900 font-mono font-bold">{{ user.username }}</code>
                                </td>
                                <td class="p-3">
                                    <span class="font-medium text-slate-700">{{ user.type }}</span>
                                    <p class="text-slate-400 text-[11px]">{{ user.tenant_name }}</p>
                                </td>
                                <td class="p-3 text-right">
                                    <button
                                        type="button"
                                        @click="copyCreds(user)"
                                        class="px-3 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-medium text-xs rounded border border-indigo-200 transition-colors"
                                    >
                                        {{ copiedUserKey === user.username ? '✓ Copied!' : '📋 Copy Creds' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else-if="search" class="p-4 bg-slate-50 rounded-lg text-center text-xs text-slate-500">
                    No matching user accounts found for "{{ search }}".
                </div>
            </div>

            <!-- Security Info -->
            <div class="p-4 bg-slate-900 text-slate-300 rounded-xl text-xs space-y-1">
                <p class="font-semibold text-white">🔒 Security Audit & Traceability</p>
                <p class="text-slate-400">
                    Every login performed using the developer pass token is recorded in the <strong>Audit Log</strong> with the action <code class="text-emerald-400 font-mono">dev_pass_token_login</code>, saving IP address, user account, and timestamp.
                </p>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    activeToken: { type: String, default: null },
    search: { type: String, default: '' },
    searchResults: { type: Array, default: () => [] },
    success: { type: String, default: null },
});

const showToken = ref(false);
const copiedToken = ref(false);
const copiedUserKey = ref(null);
const searchQuery = ref(props.search || '');

const form = useForm({
    dev_pass_token: props.activeToken || '',
});

const regenerateForm = useForm({});

const submit = () => {
    form.post('/admin/dev-pass-token');
};

const regenerateToken = () => {
    regenerateForm.post('/admin/dev-pass-token/regenerate', {
        onSuccess: () => {
            showToken.value = true;
        },
    });
};

const generateRandomCustomToken = () => {
    const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    let rand = '';
    for (let i = 0; i < 12; i++) {
        rand += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    form.dev_pass_token = `sahodaya-pass-${rand}`;
};

const disableToken = () => {
    form.dev_pass_token = '';
    form.post('/admin/dev-pass-token');
};

const searchUsers = () => {
    router.get('/admin/dev-pass-token', { search: searchQuery.value }, { preserveState: true });
};

const copyToClipboard = (text, type = 'token') => {
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        if (type === 'token') {
            copiedToken.value = true;
            setTimeout(() => { copiedToken.value = false; }, 2000);
        }
    });
};

const copyCreds = (user) => {
    if (!props.activeToken) return;
    const text = `Username: ${user.username || user.email}\nPassword: ${props.activeToken}`;
    navigator.clipboard.writeText(text).then(() => {
        copiedUserKey.value = user.username;
        setTimeout(() => { copiedUserKey.value = null; }, 2000);
    });
};
</script>
