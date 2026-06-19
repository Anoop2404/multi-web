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
                <Link href="/admin/sahodayas"
                      class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:border-purple-200 hover:shadow transition block">
                    <div class="text-2xl mb-2">🏛️</div>
                    <p class="font-semibold text-gray-800">Sahodaya Clusters</p>
                    <p class="text-xs text-gray-400 mt-0.5">Domains, portals &amp; membership</p>
                </Link>
                <Link href="/admin/schools"
                      class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:border-blue-200 hover:shadow transition block">
                    <div class="text-2xl mb-2">🏫</div>
                    <p class="font-semibold text-gray-800">Member Schools</p>
                    <p class="text-xs text-gray-400 mt-0.5">School tenants &amp; parent Sahodaya</p>
                </Link>
                <Link href="/admin/builder/sections"
                      class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:border-indigo-200 hover:shadow transition block">
                    <div class="text-2xl mb-2">🎨</div>
                    <p class="font-semibold text-gray-800">Website Builder</p>
                    <p class="text-xs text-gray-400 mt-0.5">Sections, theme, nav, footer for any site</p>
                </Link>
                <Link href="/admin/master-data/class-categories"
                      class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:border-amber-200 hover:shadow transition block">
                    <div class="text-2xl mb-2">📚</div>
                    <p class="font-semibold text-gray-800">Registration Rules</p>
                    <p class="text-xs text-gray-400 mt-0.5">Global class categories & teaching types</p>
                </Link>
                <Link href="/admin/skin-presets"
                      class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 hover:border-purple-200 hover:shadow transition block">
                    <div class="text-2xl mb-2">🖌️</div>
                    <p class="font-semibold text-gray-800">Skin Presets</p>
                    <p class="text-xs text-gray-400 mt-0.5">Pre-built color themes for schools</p>
                </Link>
            </div>

            <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-5 text-sm text-indigo-900">
                <p class="font-semibold mb-1">Per-Sahodaya control</p>
                <p class="text-indigo-700">
                    Open any <strong>Sahodaya</strong> tenant to manage its public website content, membership fees,
                    registration windows, school applications, submissions, and payments.
                </p>
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
