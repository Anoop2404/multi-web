<template>
    <AdminLayout title="Dashboard">
        <PageHeader
            title="Platform dashboard"
            eyebrow="Superadmin"
            description="Manage Sahodaya clusters, member schools, billing, and global configuration."
        />

        <div class="space-y-6">
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="stat-tile">
                    <p class="stat-tile-label">Total Tenants</p>
                    <p class="stat-tile-value">{{ stats.total_tenants ?? '—' }}</p>
                </div>
                <div class="stat-tile">
                    <p class="stat-tile-label">Schools</p>
                    <p class="stat-tile-value text-[color:var(--brand-blue)]">{{ stats.school_tenants ?? '—' }}</p>
                </div>
                <div class="stat-tile">
                    <p class="stat-tile-label">Sahodaya Clusters</p>
                    <p class="stat-tile-value">{{ stats.sahodaya_tenants ?? '—' }}</p>
                </div>
                <div class="stat-tile">
                    <p class="stat-tile-label">Active</p>
                    <p class="stat-tile-value text-emerald-700">{{ stats.active_tenants ?? '—' }}</p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link href="/admin/sahodayas" class="track-card block">
                    <div class="text-2xl mb-2">🏛️</div>
                    <p class="font-semibold text-slate-900">Sahodaya Clusters</p>
                    <p class="text-xs text-slate-500 mt-0.5">Domains, portals &amp; membership</p>
                </Link>
                <Link href="/admin/schools" class="track-card block">
                    <div class="text-2xl mb-2">🏫</div>
                    <p class="font-semibold text-slate-900">Member Schools</p>
                    <p class="text-xs text-slate-500 mt-0.5">School tenants &amp; parent Sahodaya</p>
                </Link>
                <Link href="/admin/builder/sections" class="track-card block">
                    <div class="text-2xl mb-2">🎨</div>
                    <p class="font-semibold text-slate-900">Website Builder</p>
                    <p class="text-xs text-slate-500 mt-0.5">Sections, theme, nav, footer for any site</p>
                </Link>
                <Link href="/admin/master-data/class-categories" class="track-card block">
                    <div class="text-2xl mb-2">📚</div>
                    <p class="font-semibold text-slate-900">Registration Rules</p>
                    <p class="text-xs text-slate-500 mt-0.5">Global class categories & teaching types</p>
                </Link>
                <Link href="/admin/skin-presets" class="track-card block">
                    <div class="text-2xl mb-2">🖌️</div>
                    <p class="font-semibold text-slate-900">Skin Presets</p>
                    <p class="text-xs text-slate-500 mt-0.5">Pre-built color themes for schools</p>
                </Link>
                <Link href="/admin/audit" class="track-card block">
                    <div class="text-2xl mb-2">📋</div>
                    <p class="font-semibold text-slate-900">Audit Logs</p>
                    <p class="text-xs text-slate-500 mt-0.5">Platform activity and security events</p>
                </Link>
                <Link href="/admin/billing" class="track-card block">
                    <div class="text-2xl mb-2">💳</div>
                    <p class="font-semibold text-slate-900">Billing</p>
                    <p class="text-xs text-slate-500 mt-0.5">Subscriptions, plans, and receipts</p>
                </Link>
            </div>

            <div class="notice-banner notice-banner--info">
                <p class="font-semibold mb-1">Per-Sahodaya control</p>
                <p class="opacity-90">
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
