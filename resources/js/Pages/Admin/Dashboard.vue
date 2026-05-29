<template>
    <AdminLayout title="Dashboard">
        <div class="space-y-6">
            <!-- Stats grid -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Tenants</p>
                    <p class="text-3xl font-bold text-gray-800 mt-1">{{ stats.total_tenants ?? '—' }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Schools</p>
                    <p class="text-3xl font-bold text-blue-600 mt-1">{{ stats.school_tenants ?? '—' }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Sahodaya Clusters</p>
                    <p class="text-3xl font-bold text-purple-600 mt-1">{{ stats.sahodaya_tenants ?? '—' }}</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Active</p>
                    <p class="text-3xl font-bold text-green-600 mt-1">{{ stats.active_tenants ?? '—' }}</p>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <Link href="/admin/tenants"
                      class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:border-blue-200 hover:shadow transition block">
                    <div class="text-2xl mb-2">🏫</div>
                    <p class="font-semibold text-gray-800">Manage Tenants</p>
                    <p class="text-xs text-gray-400 mt-0.5">Create and configure school/sahodaya sites</p>
                </Link>
                <Link href="/admin/builder/sections"
                      class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:border-indigo-200 hover:shadow transition block">
                    <div class="text-2xl mb-2">🎨</div>
                    <p class="font-semibold text-gray-800">Site Builder</p>
                    <p class="text-xs text-gray-400 mt-0.5">Manage sections, themes, and navigation</p>
                </Link>
                <Link href="/admin/skin-presets"
                      class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:border-purple-200 hover:shadow transition block">
                    <div class="text-2xl mb-2">🎨</div>
                    <p class="font-semibold text-gray-800">Skin Presets</p>
                    <p class="text-xs text-gray-400 mt-0.5">Pre-built color themes for schools</p>
                </Link>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    stats: { type: Object, default: () => ({}) },
});
</script>
