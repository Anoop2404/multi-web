<template>
    <AdminLayout :title="defaultType === 'sahodaya' ? 'Add Sahodaya' : 'Add School'">
        <div class="max-w-2xl">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
                <div class="flex items-center gap-2 mb-1">
                    <span :class="defaultType === 'sahodaya' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'"
                          class="px-2 py-0.5 rounded-full text-xs font-semibold capitalize">
                        {{ defaultType }}
                    </span>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-6">
                    {{ defaultType === 'sahodaya' ? 'New Sahodaya Cluster' : 'New Member School' }}
                </h3>

                <form @submit.prevent="submit" class="space-y-5">
                    <input type="hidden" v-model="form.type">

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Name *</label>
                        <input v-model="form.name" type="text" required
                               class="w-full border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2"
                               :class="form.errors.name ? 'border-red-300' : 'border-gray-200'">
                        <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                    </div>

                    <div class="rounded-lg border border-indigo-100 bg-indigo-50/50 p-4 space-y-4">
                        <p class="text-xs font-semibold text-indigo-800 uppercase tracking-wide">Domain assignment</p>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Custom Domain</label>
                            <input v-model="form.domain" type="text"
                                   :placeholder="defaultType === 'sahodaya' ? 'malappuramsahodaya.com' : 'school.edu.in'"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 font-mono bg-white">
                            <p class="text-xs text-gray-500 mt-1">
                                {{ defaultType === 'sahodaya'
                                    ? 'Public portal URL (e.g. malappuramsahodaya.com). Point DNS to your server.'
                                    : 'School website URL. Point DNS A/CNAME to your server.' }}
                            </p>
                            <p v-if="form.errors.domain" class="text-xs text-red-500 mt-1">{{ form.errors.domain }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Platform Subdomain</label>
                            <div class="flex items-center gap-1">
                                <input v-model="form.subdomain" type="text" :placeholder="defaultType === 'sahodaya' ? 'malappuram' : 'schoolname'"
                                       class="flex-1 border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 font-mono bg-white">
                                <span class="text-xs text-gray-400 shrink-0">.{{ tenantBaseDomain }}</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                Optional fallback:
                                <span class="font-mono">{{ form.subdomain || (defaultType === 'sahodaya' ? 'malappuram' : 'schoolname') }}.{{ tenantBaseDomain }}</span>
                            </p>
                            <p v-if="form.errors.subdomain" class="text-xs text-red-500 mt-1">{{ form.errors.subdomain }}</p>
                        </div>
                    </div>

                    <div v-if="defaultType === 'school'">
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Parent Sahodaya *</label>
                        <select v-model="form.parent_id" required
                                class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 bg-white">
                            <option value="">— Select Sahodaya —</option>
                            <option v-for="s in sahodayas" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <p v-if="form.errors.parent_id" class="text-xs text-red-500 mt-1">{{ form.errors.parent_id }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Plan</label>
                        <select v-model="form.plan"
                                class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 bg-white">
                            <option value="free">Free</option>
                            <option value="basic">Basic</option>
                            <option value="pro">Pro</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-4 pt-2">
                        <button type="submit" :disabled="form.processing"
                                :class="defaultType === 'sahodaya' ? 'bg-purple-600 hover:bg-purple-700' : 'bg-blue-600 hover:bg-blue-700'"
                                class="text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition disabled:opacity-50">
                            Create {{ defaultType === 'sahodaya' ? 'Sahodaya' : 'School' }}
                        </button>
                        <Link :href="cancelUrl" class="text-sm text-gray-500 hover:text-gray-700">Cancel</Link>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    sahodayas: { type: Array, default: () => [] },
    tenantBaseDomain: { type: String, default: 'sahodaya.test' },
    defaultType: { type: String, default: 'school' },
    cancelUrl: { type: String, default: '/admin/schools' },
});

const form = useForm({
    type: props.defaultType,
    name: '',
    domain: '',
    subdomain: '',
    parent_id: '',
    plan: 'free',
});

function submit() {
    form.post('/admin/tenants');
}
</script>
