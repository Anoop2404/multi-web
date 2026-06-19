<template>
    <AdminLayout :title="pageTitle">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-700">{{ pageTitle }}</h3>
                <p class="text-sm text-gray-400 mt-0.5">
                    {{ tenantType === 'sahodaya'
                        ? 'Manage Sahodaya clusters, custom domains, and portal access.'
                        : 'Manage member schools, domains, and parent Sahodaya assignment.' }}
                </p>
            </div>
            <Link :href="createUrl"
                  :class="tenantType === 'sahodaya' ? 'bg-purple-600 hover:bg-purple-700' : 'bg-blue-600 hover:bg-blue-700'"
                  class="text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                + {{ tenantType === 'sahodaya' ? 'Add Sahodaya' : 'Add School' }}
            </Link>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th v-if="tenantType === 'school'" class="px-4 py-3 text-left">Sahodaya</th>
                        <th v-else class="px-4 py-3 text-left">Schools</th>
                        <th class="px-4 py-3 text-left">Custom Domain</th>
                        <th class="px-4 py-3 text-left">Subdomain</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-if="!tenants.data.length">
                        <td :colspan="tenantType === 'school' ? 6 : 6" class="px-4 py-8 text-center text-gray-400">
                            No {{ tenantType === 'sahodaya' ? 'Sahodaya clusters' : 'schools' }} yet.
                        </td>
                    </tr>
                    <tr v-for="tenant in tenants.data" :key="tenant.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ tenant.name }}</td>
                        <td v-if="tenantType === 'school'" class="px-4 py-3 text-gray-500 text-xs">
                            {{ tenant.parent?.name || '—' }}
                        </td>
                        <td v-else class="px-4 py-3 text-gray-500 text-xs">
                            {{ tenant.children_count ?? 0 }}
                        </td>
                        <td class="px-4 py-3">
                            <a v-if="tenant.domain" :href="`https://${tenant.domain}`" target="_blank" rel="noopener"
                               class="font-mono text-xs text-indigo-600 hover:underline">
                                {{ tenant.domain }}
                            </a>
                            <span v-else class="text-xs text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3">
                            <span v-if="tenant.subdomain" class="font-mono text-xs text-gray-500">
                                {{ tenant.subdomain }}.{{ tenantBaseDomain }}
                            </span>
                            <span v-else class="text-xs text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3">
                            <span :class="tenant.is_active ? 'text-green-600' : 'text-gray-400'" class="text-xs font-medium">
                                {{ tenant.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <Link :href="`/admin/tenants/${tenant.id}/edit`" class="text-gray-500 hover:text-gray-700 text-xs mr-3">Edit</Link>
                            <Link :href="`/admin/tenants/${tenant.id}`" class="text-indigo-600 hover:underline text-xs">View</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    tenants: Object,
    tenantType: { type: String, required: true },
    pageTitle: { type: String, required: true },
    createUrl: { type: String, required: true },
    tenantBaseDomain: { type: String, default: 'sahodaya.test' },
});
</script>
