<template>
    <AdminLayout title="Tenants">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-semibold text-gray-700">All Tenants</h3>
            <Link href="/admin/tenants/create" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700">
                + New Tenant
            </Link>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-4 py-3 text-left">Name</th>
                        <th class="px-4 py-3 text-left">Type</th>
                        <th class="px-4 py-3 text-left">Domain</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="tenant in tenants.data" :key="tenant.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ tenant.name }}</td>
                        <td class="px-4 py-3">
                            <span :class="tenant.type === 'sahodaya' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'"
                                  class="px-2 py-0.5 rounded-full text-xs font-medium capitalize">
                                {{ tenant.type }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ tenant.domain || tenant.subdomain || '—' }}</td>
                        <td class="px-4 py-3">
                            <span :class="tenant.is_active ? 'text-green-600' : 'text-gray-400'" class="text-xs font-medium">
                                {{ tenant.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
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
});
</script>
