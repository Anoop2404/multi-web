<template>
    <AdminLayout :title="tenant.name">
        <div class="space-y-6">
            <!-- Header card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <h2 class="text-xl font-bold text-gray-900">{{ tenant.name }}</h2>
                        <span :class="tenant.type === 'sahodaya' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'"
                              class="px-2 py-0.5 rounded-full text-xs font-semibold capitalize">
                            {{ tenant.type }}
                        </span>
                        <span :class="tenant.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                              class="px-2 py-0.5 rounded-full text-xs font-medium">
                            {{ tenant.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <p class="text-sm text-gray-500 font-mono">{{ tenant.domain || tenant.subdomain || 'No domain set' }}</p>
                </div>

                <div class="flex items-center gap-2">
                    <Link :href="`/admin/tenants/${tenant.id}/edit`"
                          class="px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                        Edit
                    </Link>
                    <Link :href="`/admin/builder/sections?tenant=${tenant.id}`"
                          class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 transition">
                        Open Builder →
                    </Link>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-6">
                <!-- Sections overview -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="font-bold text-gray-900 mb-4">Sections ({{ tenant.sections?.length ?? 0 }})</h3>
                    <div v-if="tenant.sections?.length" class="space-y-2">
                        <div v-for="section in tenant.sections" :key="section.id"
                             class="flex items-center justify-between text-sm py-2 border-b border-gray-50 last:border-0">
                            <span class="font-mono text-gray-600 text-xs">{{ section.section_type }}/{{ section.variant }}</span>
                            <span :class="section.is_active ? 'text-green-600' : 'text-gray-300'" class="text-xs font-medium">
                                {{ section.is_active ? '● Active' : '○ Hidden' }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">No sections configured yet.</p>
                </div>

                <!-- Settings overview -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="font-bold text-gray-900 mb-4">Settings ({{ tenant.settings?.length ?? 0 }} keys)</h3>
                    <div v-if="tenant.settings?.length" class="space-y-1.5">
                        <div v-for="setting in tenant.settings" :key="setting.key"
                             class="flex items-center justify-between text-sm">
                            <span class="font-mono text-gray-500 text-xs">{{ setting.key }}</span>
                            <span class="text-xs text-gray-400">configured</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">No settings configured.</p>
                </div>

                <!-- Child schools (for sahodaya) -->
                <div v-if="tenant.type === 'sahodaya' && tenant.children?.length" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 lg:col-span-2">
                    <h3 class="font-bold text-gray-900 mb-4">Member Schools ({{ tenant.children.length }})</h3>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <Link v-for="school in tenant.children" :key="school.id"
                              :href="`/admin/tenants/${school.id}`"
                              class="flex items-center gap-3 p-3 rounded-lg border border-gray-100 hover:border-indigo-200 hover:bg-indigo-50 transition text-sm">
                            <span class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-xs shrink-0">
                                {{ school.name.charAt(0).toUpperCase() }}
                            </span>
                            <span class="font-medium text-gray-800 truncate">{{ school.name }}</span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    tenant: Object,
});
</script>
