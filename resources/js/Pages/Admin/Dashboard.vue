<template>
    <AdminLayout title="Superadmin Dashboard">
        <div class="max-w-7xl mx-auto space-y-8">
            <!-- Executive Cockpit Header -->
            <div class="bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 rounded-3xl p-6 sm:p-8 text-white shadow-xl relative overflow-hidden">
                <!-- Background ambient decoration -->
                <div class="absolute -right-12 -bottom-12 w-64 h-64 rounded-full bg-indigo-500/10 blur-3xl pointer-events-none"></div>
                <div class="absolute right-1/4 -top-12 w-48 h-48 rounded-full bg-amber-500/10 blur-2xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-400/20 text-amber-300 border border-amber-400/30">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                                Platform Super Admin
                            </span>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-white/10 text-slate-300 backdrop-blur-xs">
                                Central Multi-Tenant Hub
                            </span>
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">
                            Platform Command Center
                        </h1>
                        <p class="text-sm text-slate-300 max-w-2xl leading-relaxed">
                            Oversee Sahodaya clusters, member school tenants, multi-database infrastructure, global platform rules, and billing across India.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                        <Link
                            href="/admin/sahodayas/create"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-xs font-bold text-white transition shadow-lg shadow-indigo-950/40"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Add Sahodaya</span>
                        </Link>
                        <Link
                            href="/admin/schools/create"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 active:bg-white/30 text-xs font-bold text-white backdrop-blur-xs transition border border-white/10"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Add School</span>
                        </Link>
                        <Link
                            href="/admin/sahodayas/databases"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-800/80 hover:bg-slate-700 text-xs font-bold text-slate-200 transition border border-slate-700"
                        >
                            <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7M4 7c0-2 1.5-3 3.5-3h9c2 0 3.5 1 3.5 3M4 7c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3m-16 5c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3" />
                            </svg>
                            <span>Cluster DBs</span>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Primary Platform Health Metrics -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                <Link
                    href="/admin/sahodayas"
                    class="group bg-white rounded-2xl p-5 border border-slate-200/80 shadow-[0_2px_12px_rgba(15,23,42,0.03)] hover:shadow-md hover:border-indigo-300 transition-all"
                >
                    <div class="flex items-center justify-between mb-3">
                        <span class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 font-extrabold text-xl group-hover:scale-105 transition-transform">
                            🏛️
                        </span>
                        <span class="text-[11px] font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full border border-indigo-100">
                            Clusters
                        </span>
                    </div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Sahodaya Clusters</p>
                    <p class="text-3xl font-black text-slate-900 leading-tight mt-1">{{ stats.sahodaya_tenants ?? 0 }}</p>
                    <p class="text-xs text-slate-500 mt-1.5 flex items-center gap-1">
                        <span>Regional governing complexes</span>
                    </p>
                </Link>

                <Link
                    href="/admin/schools"
                    class="group bg-white rounded-2xl p-5 border border-slate-200/80 shadow-[0_2px_12px_rgba(15,23,42,0.03)] hover:shadow-md hover:border-indigo-300 transition-all"
                >
                    <div class="flex items-center justify-between mb-3">
                        <span class="w-10 h-10 rounded-xl bg-sky-50 border border-sky-100 flex items-center justify-center text-sky-600 font-extrabold text-xl group-hover:scale-105 transition-transform">
                            🏫
                        </span>
                        <span class="text-[11px] font-bold text-sky-600 bg-sky-50 px-2 py-0.5 rounded-full border border-sky-100">
                            Members
                        </span>
                    </div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Member Schools</p>
                    <p class="text-3xl font-black text-slate-900 leading-tight mt-1">{{ stats.school_tenants ?? 0 }}</p>
                    <p class="text-xs text-slate-500 mt-1.5 flex items-center gap-1">
                        <span>Affiliated institutions</span>
                    </p>
                </Link>

                <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-[0_2px_12px_rgba(15,23,42,0.03)]">
                    <div class="flex items-center justify-between mb-3">
                        <span class="w-10 h-10 rounded-xl bg-purple-50 border border-purple-100 flex items-center justify-center text-purple-600 font-extrabold text-xl">
                            🏢
                        </span>
                        <span class="text-[11px] font-bold text-purple-600 bg-purple-50 px-2 py-0.5 rounded-full border border-purple-100">
                            Tenancy
                        </span>
                    </div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Tenants</p>
                    <p class="text-3xl font-black text-slate-900 leading-tight mt-1">{{ stats.total_tenants ?? 0 }}</p>
                    <p class="text-xs text-slate-500 mt-1.5">
                        Clusters &amp; Schools Combined
                    </p>
                </div>

                <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-[0_2px_12px_rgba(15,23,42,0.03)]">
                    <div class="flex items-center justify-between mb-3">
                        <span class="w-10 h-10 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 font-extrabold text-xl">
                            ✓
                        </span>
                        <span class="text-[11px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-100">
                            {{ activeRate }}% Active
                        </span>
                    </div>
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Operational Status</p>
                    <p class="text-3xl font-black text-emerald-600 leading-tight mt-1">{{ stats.active_tenants ?? 0 }}</p>
                    <p class="text-xs text-slate-500 mt-1.5">
                        Live active tenants on platform
                    </p>
                </div>
            </div>

            <!-- Aggregated Cross-Cluster Telemetry -->
            <div class="bg-white rounded-3xl p-6 sm:p-7 border border-slate-200/80 shadow-[0_2px_12px_rgba(15,23,42,0.03)] space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-slate-100 pb-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-indigo-600"></span>
                            <h2 class="text-base font-extrabold text-slate-900 tracking-tight">Cross-Cluster Platform Telemetry</h2>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">Aggregated student enrollments, faculty registries, and payment volume computed across isolated tenant databases.</p>
                    </div>
                    <div v-if="snapshot" class="text-xs text-slate-400 font-medium">
                        Updated {{ formatDateTime(snapshot.computed_at) }}
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="p-4 rounded-2xl bg-slate-50/80 border border-slate-200/60">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Students Enrolled</p>
                        <p class="text-2xl sm:text-3xl font-black text-slate-900 mt-1">
                            {{ snapshot ? formatAmount(snapshot.total_students) : '—' }}
                        </p>
                        <p class="text-xs text-slate-500 mt-1">Participating across academic and fest registers</p>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-50/80 border border-slate-200/60">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Faculty &amp; Teachers</p>
                        <p class="text-2xl sm:text-3xl font-black text-slate-900 mt-1">
                            {{ snapshot ? formatAmount(snapshot.total_teachers) : '—' }}
                        </p>
                        <p class="text-xs text-slate-500 mt-1">Registered educators and evaluators</p>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-50/80 border border-slate-200/60">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Monthly Revenue (INR)</p>
                        <p class="text-2xl sm:text-3xl font-black text-emerald-700 mt-1">
                            ₹{{ snapshot ? formatAmount(snapshot.revenue_this_month_inr) : '0' }}
                        </p>
                        <p class="text-xs text-slate-500 mt-1">Platform subscription and service dues</p>
                    </div>
                </div>

                <!-- Snapshot Telemetry Health Footer -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-2 text-xs text-slate-500">
                    <div class="flex items-center gap-2">
                        <template v-if="snapshot">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold"
                                  :class="snapshot.sahodayas_included >= snapshot.sahodayas_total
                                      ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                      : 'bg-amber-50 text-amber-700 border border-amber-200'">
                                <span class="w-1.5 h-1.5 rounded-full"
                                      :class="snapshot.sahodayas_included >= snapshot.sahodayas_total ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                                <span>{{ snapshot.sahodayas_included }}/{{ snapshot.sahodayas_total }} Cluster DBs In Sync</span>
                            </span>
                            <span v-if="snapshot.sahodayas_included < snapshot.sahodayas_total" class="text-amber-600 font-semibold">
                                Note: Some databases were unreachable during the last nightly snapshot run.
                            </span>
                        </template>
                        <template v-else>
                            <span class="text-slate-400">
                                No snapshot yet — scheduled to run nightly via <code class="font-mono bg-slate-100 px-1 py-0.5 rounded">platform:snapshot-dashboard</code>.
                            </span>
                        </template>
                    </div>

                    <Link href="/admin/reports" class="text-indigo-600 hover:text-indigo-800 font-bold inline-flex items-center gap-1">
                        <span>View Analytics &amp; Reports</span>
                        <span>&rarr;</span>
                    </Link>
                </div>
            </div>

            <!-- Categorized Executive Launchpad -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-black text-slate-900 tracking-tight">Superadmin Launchpad</h2>
                        <p class="text-xs text-slate-500">Direct access to multi-tenant controls, website builders, platform rules, and security.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Section: Clusters & Schools -->
                    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-[0_2px_8px_rgba(15,23,42,0.03)] flex flex-col justify-between">
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-lg">🏛️</span>
                                <h3 class="font-bold text-sm text-slate-900">Clusters &amp; Schools</h3>
                            </div>
                            <ul class="space-y-2 text-xs">
                                <li>
                                    <Link href="/admin/sahodayas" class="flex items-center justify-between text-slate-600 hover:text-indigo-600 font-semibold py-1">
                                        <span>All Sahodaya Clusters</span>
                                        <span class="text-slate-400">&rarr;</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/admin/schools" class="flex items-center justify-between text-slate-600 hover:text-indigo-600 font-semibold py-1">
                                        <span>All Member Schools</span>
                                        <span class="text-slate-400">&rarr;</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/admin/sahodayas/databases" class="flex items-center justify-between text-slate-600 hover:text-indigo-600 font-semibold py-1">
                                        <span>Postgres DB Provisioning</span>
                                        <span class="text-slate-400">&rarr;</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/admin/state-workspace/qualifiers" class="flex items-center justify-between text-slate-600 hover:text-indigo-600 font-semibold py-1">
                                        <span>State Qualifier Intakes</span>
                                        <span class="text-slate-400">&rarr;</span>
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Section: CMS & Website Builder (Superadmin-only) -->
                    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-[0_2px_8px_rgba(15,23,42,0.03)] flex flex-col justify-between">
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-lg">🎨</span>
                                <h3 class="font-bold text-sm text-slate-900">Website Builder</h3>
                            </div>
                            <ul class="space-y-2 text-xs">
                                <li>
                                    <Link href="/admin/builder/sections" class="flex items-center justify-between text-slate-600 hover:text-indigo-600 font-semibold py-1">
                                        <span>Page Sections Suite</span>
                                        <span class="text-slate-400">&rarr;</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/admin/builder/theme" class="flex items-center justify-between text-slate-600 hover:text-indigo-600 font-semibold py-1">
                                        <span>Theme &amp; Skin Customizer</span>
                                        <span class="text-slate-400">&rarr;</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/admin/builder/nav" class="flex items-center justify-between text-slate-600 hover:text-indigo-600 font-semibold py-1">
                                        <span>Global Navigation &amp; Menus</span>
                                        <span class="text-slate-400">&rarr;</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/admin/skin-presets" class="flex items-center justify-between text-slate-600 hover:text-indigo-600 font-semibold py-1">
                                        <span>Color Skin Presets</span>
                                        <span class="text-slate-400">&rarr;</span>
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Section: Global Master Data & Platform Rules -->
                    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-[0_2px_8px_rgba(15,23,42,0.03)] flex flex-col justify-between">
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-lg">📚</span>
                                <h3 class="font-bold text-sm text-slate-900">Global Master Data</h3>
                            </div>
                            <ul class="space-y-2 text-xs">
                                <li>
                                    <Link href="/admin/master-data/class-categories" class="flex items-center justify-between text-slate-600 hover:text-indigo-600 font-semibold py-1">
                                        <span>CBSE Class Categories</span>
                                        <span class="text-slate-400">&rarr;</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/admin/master-data/teaching-types" class="flex items-center justify-between text-slate-600 hover:text-indigo-600 font-semibold py-1">
                                        <span>Teaching Types &amp; Cadres</span>
                                        <span class="text-slate-400">&rarr;</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/admin/master-data/subjects" class="flex items-center justify-between text-slate-600 hover:text-indigo-600 font-semibold py-1">
                                        <span>Standard Academic Subjects</span>
                                        <span class="text-slate-400">&rarr;</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/admin/master-data/designations" class="flex items-center justify-between text-slate-600 hover:text-indigo-600 font-semibold py-1">
                                        <span>Staff Designations</span>
                                        <span class="text-slate-400">&rarr;</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/admin/master-data/age-categories" class="flex items-center justify-between text-slate-600 hover:text-indigo-600 font-semibold py-1">
                                        <span>Sports Age Categories</span>
                                        <span class="text-slate-400">&rarr;</span>
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Section: Security, Billing & Governance -->
                    <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-[0_2px_8px_rgba(15,23,42,0.03)] flex flex-col justify-between">
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-lg">🛡️</span>
                                <h3 class="font-bold text-sm text-slate-900">Security &amp; Billing</h3>
                            </div>
                            <ul class="space-y-2 text-xs">
                                <li>
                                    <Link href="/admin/billing" class="flex items-center justify-between text-slate-600 hover:text-indigo-600 font-semibold py-1">
                                        <span>Subscriptions &amp; Plans</span>
                                        <span class="text-slate-400">&rarr;</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/admin/audit-logs" class="flex items-center justify-between text-slate-600 hover:text-indigo-600 font-semibold py-1">
                                        <span>Platform Audit Logs</span>
                                        <span class="text-slate-400">&rarr;</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/admin/state-users" class="flex items-center justify-between text-slate-600 hover:text-indigo-600 font-semibold py-1">
                                        <span>State Office Users</span>
                                        <span class="text-slate-400">&rarr;</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/admin/storage-migration" class="flex items-center justify-between text-slate-600 hover:text-indigo-600 font-semibold py-1">
                                        <span>S3 Storage Migration</span>
                                        <span class="text-slate-400">&rarr;</span>
                                    </Link>
                                </li>
                                <li>
                                    <Link href="/admin/dev-pass-token" class="flex items-center justify-between text-slate-600 hover:text-indigo-600 font-semibold py-1">
                                        <span>Developer Pass Token</span>
                                        <span class="text-slate-400">&rarr;</span>
                                    </Link>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Guidance Callout -->
            <div class="bg-slate-900 text-white rounded-3xl p-6 sm:p-7 shadow-lg relative overflow-hidden flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="space-y-1">
                    <p class="text-xs font-bold uppercase tracking-wider text-amber-400">Multi-Cluster Governance Model</p>
                    <p class="text-sm font-semibold text-slate-200">
                        Tenancy isolation is strictly enforced at database and filesystem levels.
                    </p>
                    <p class="text-xs text-slate-400 max-w-2xl">
                        Sahodaya cluster officials manage their regional events, local circulars, fee collections, and school memberships. As Superadmin, your changes in Master Data and Website Builder apply across the entire multi-cluster ecosystem.
                    </p>
                </div>
                <div class="shrink-0">
                    <Link
                        href="/admin/sahodayas"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-xs font-bold text-white transition border border-white/10"
                    >
                        <span>Manage Clusters</span>
                        <span>&rarr;</span>
                    </Link>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatDateTime } from '@/support/calendarDates.js';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    snapshot: { type: Object, default: null },
});

const activeRate = computed(() => {
    const total = props.stats.total_tenants ?? 0;
    const active = props.stats.active_tenants ?? 0;
    if (!total) return 100;
    return Math.round((active / total) * 100);
});

function formatAmount(v) {
    return Number(v ?? 0).toLocaleString('en-IN');
}
</script>
