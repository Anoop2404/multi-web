<template>
    <AdminLayout title="Add Tenant">
        <div class="max-w-2xl">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
                <h3 class="text-lg font-bold text-gray-900 mb-6">New Tenant</h3>

                <form @submit.prevent="submit" class="space-y-5">
                    <!-- Type -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-2">Tenant Type *</label>
                        <div class="flex gap-3">
                            <label v-for="type in ['school', 'sahodaya']" :key="type"
                                   class="flex items-center gap-2 px-4 py-2.5 rounded-lg border-2 cursor-pointer transition"
                                   :class="form.type === type
                                       ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                       : 'border-gray-200 text-gray-600 hover:border-gray-300'">
                                <input type="radio" v-model="form.type" :value="type" class="sr-only">
                                <span class="font-medium text-sm capitalize">{{ type }}</span>
                            </label>
                        </div>
                        <p v-if="form.errors.type" class="text-xs text-red-500 mt-1">{{ form.errors.type }}</p>
                    </div>

                    <!-- Name -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Name *</label>
                        <input v-model="form.name" type="text" required
                               class="w-full border rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2"
                               :class="form.errors.name ? 'border-red-300' : 'border-gray-200'">
                        <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-5">
                        <!-- Domain -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Custom Domain</label>
                            <input v-model="form.domain" type="text" placeholder="school.edu.in"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 font-mono">
                            <p v-if="form.errors.domain" class="text-xs text-red-500 mt-1">{{ form.errors.domain }}</p>
                        </div>
                        <!-- Subdomain -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Subdomain</label>
                            <input v-model="form.subdomain" type="text" placeholder="schoolname"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 font-mono">
                            <p v-if="form.errors.subdomain" class="text-xs text-red-500 mt-1">{{ form.errors.subdomain }}</p>
                        </div>
                    </div>

                    <!-- Parent sahodaya (only for schools) -->
                    <div v-if="form.type === 'school'">
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Parent Sahodaya</label>
                        <select v-model="form.parent_id"
                                class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 bg-white">
                            <option value="">— No parent —</option>
                            <option v-for="s in sahodayas" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>

                    <!-- Plan -->
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
                                class="bg-indigo-600 text-white px-6 py-2.5 rounded-lg font-semibold text-sm hover:bg-indigo-700 transition disabled:opacity-50">
                            Create Tenant
                        </button>
                        <Link href="/admin/tenants" class="text-sm text-gray-500 hover:text-gray-700">Cancel</Link>
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
});

const form = useForm({
    type: 'school',
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
