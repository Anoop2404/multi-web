<template>
    <AdminLayout :title="`Edit: ${tenant.name}`">
        <div class="max-w-2xl">
            <div class="card">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-bold text-gray-900">Edit {{ tenant.type === 'sahodaya' ? 'Sahodaya' : 'School' }}</h3>
                    <Link :href="`/admin/tenants/${tenant.id}`" class="text-sm text-gray-400 hover:text-gray-600">← Back</Link>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label class="form-label mb-1.5">Name *</label>
                        <input v-model="form.name" type="text" required
                               class="field">
                        <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                    </div>

                    <div class="rounded-lg border border-indigo-100 bg-indigo-50/50 p-4 space-y-4">
                        <p class="text-xs font-semibold text-indigo-800 uppercase tracking-wide">Domain assignment</p>

                        <div>
                            <label class="form-label mb-1.5">Custom Domain</label>
                            <input v-model="form.domain" type="text"
                                   :placeholder="tenant.type === 'sahodaya' ? 'malappuramsahodaya.com' : 'school.edu.in'"
                                   class="field font-mono">
                            <p class="text-xs text-gray-500 mt-1">
                                {{ tenant.type === 'sahodaya'
                                    ? 'Public portal host. DNS must point to your server.'
                                    : 'School website host. DNS A/CNAME → your server.' }}
                            </p>
                            <p v-if="form.errors.domain" class="text-xs text-red-500 mt-1">{{ form.errors.domain }}</p>
                        </div>

                        <div>
                            <label class="form-label mb-1.5">Platform Subdomain</label>
                            <div class="flex items-center gap-1">
                                <input v-model="form.subdomain" type="text"
                                       class="flex-1 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 font-mono bg-white">
                                <span class="text-xs text-gray-400 shrink-0">.{{ tenantBaseDomain }}</span>
                            </div>
                            <p v-if="form.subdomain" class="text-xs text-gray-500 mt-1 font-mono">
                                → {{ form.subdomain }}.{{ tenantBaseDomain }}
                            </p>
                            <p v-if="form.errors.subdomain" class="text-xs text-red-500 mt-1">{{ form.errors.subdomain }}</p>
                        </div>
                    </div>

                    <div v-if="tenant.type === 'school'">
                        <label class="form-label mb-1.5">Parent Sahodaya</label>
                        <select v-model="form.parent_id"
                                class="field">
                            <option value="">— No parent —</option>
                            <option v-for="s in sahodayas" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label mb-1.5">Plan</label>
                        <select v-model="form.plan"
                                class="field">
                            <option value="free">Free</option>
                            <option value="basic">Basic</option>
                            <option value="pro">Pro</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-3">
                        <input type="checkbox" id="is_active" v-model="form.is_active" class="rounded">
                        <label for="is_active" class="text-sm text-gray-700">Active (site is live)</label>
                    </div>

                    <div class="flex items-center gap-4 pt-2 border-t border-gray-100">
                        <button type="submit" :disabled="form.processing"
                                class="btn-primary disabled:opacity-50">
                            Save Changes
                        </button>
                        <button type="button" @click="deleteTenant"
                                class="text-sm text-red-500 hover:text-red-700">
                            Delete tenant
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    tenant: Object,
    sahodayas: { type: Array, default: () => [] },
    tenantBaseDomain: { type: String, default: 'sahodaya.test' },
    cancelUrl: { type: String, default: '/admin/schools' },
});

const form = useForm({
    name:      props.tenant.name,
    domain:    props.tenant.domain ?? '',
    subdomain: props.tenant.subdomain ?? '',
    parent_id: props.tenant.parent_id ?? '',
    plan:      props.tenant.plan ?? 'free',
    is_active: props.tenant.is_active,
});

function submit() {
    form.put(`/admin/tenants/${props.tenant.id}`);
}

function deleteTenant() {
    if (!confirm(`Delete "${props.tenant.name}"? This cannot be undone.`)) return;
    router.delete(`/admin/tenants/${props.tenant.id}`);
}
</script>
