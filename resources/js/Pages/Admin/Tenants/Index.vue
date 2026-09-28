<template>
    <AdminLayout :title="pageTitle">
        <div class="space-y-6 max-w-7xl mx-auto">
            <!-- Header section -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white rounded-2xl p-6 border border-slate-200/80 shadow-[0_2px_12px_rgba(15,23,42,0.03)]">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">
                        <span>Platform Administration</span>
                        <span>•</span>
                        <span class="text-indigo-600 font-bold">{{ tenantType === 'school' ? 'Schools' : 'Clusters' }}</span>
                    </div>
                    <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight flex items-center gap-3">
                        <span>{{ pageTitle }}</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                            {{ stats?.total ?? tenants.total ?? 0 }} total
                        </span>
                    </h1>
                    <p class="text-sm text-slate-500 mt-1 max-w-2xl">
                        {{ tenantType === 'sahodaya'
                            ? (readOnly
                                ? 'View Sahodaya clusters in your state (read-only mode).'
                                : 'Provision and oversee Sahodaya clusters, database assignments, custom domains, and platform-wide configurations.')
                            : 'Centrally oversee all member schools, assign parent Sahodaya clusters, inspect portal credentials, and enforce platform security policies.' }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <Link v-if="databasesUrl" :href="databasesUrl"
                          class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition shadow-xs">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7M4 7c0-2 1.5-3 3.5-3h9c2 0 3.5 1 3.5 3M4 7c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3m-16 5c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3" />
                        </svg>
                        <span>Cluster Databases</span>
                    </Link>

                    <button v-if="!readOnly" type="button" @click="exportAdminCredentials"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition shadow-xs">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        <span>Export Credentials</span>
                    </button>

                    <Link v-if="createUrl && !readOnly" :href="createUrl"
                          class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700 active:bg-indigo-800 transition shadow-xs hover:shadow">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>{{ tenantType === 'sahodaya' ? 'Add Sahodaya' : 'Add School Tenant' }}</span>
                    </Link>
                </div>
            </div>

            <!-- Stats Overview Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-[0_2px_8px_rgba(15,23,42,0.03)] flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 font-extrabold text-xl shrink-0">
                        {{ tenantType === 'school' ? '🏫' : '🏛️' }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Registered</p>
                        <p class="text-2xl font-black text-slate-900 leading-tight mt-0.5">{{ stats?.total ?? tenants.total ?? 0 }}</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-[0_2px_8px_rgba(15,23,42,0.03)] flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 font-extrabold text-xl shrink-0">
                        ✓
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Active Status</p>
                        <p class="text-2xl font-black text-emerald-600 leading-tight mt-0.5">{{ stats?.active ?? 0 }}</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-[0_2px_8px_rgba(15,23,42,0.03)] flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-600 font-extrabold text-xl shrink-0">
                        🌐
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Custom Domains</p>
                        <p class="text-2xl font-black text-slate-900 leading-tight mt-0.5">{{ stats?.with_domain ?? 0 }}</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-[0_2px_8px_rgba(15,23,42,0.03)] flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-purple-50 border border-purple-100 flex items-center justify-center text-purple-600 font-extrabold text-xl shrink-0">
                        {{ tenantType === 'school' ? '🧩' : '👥' }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">
                            {{ tenantType === 'school' ? 'Sahodaya Clusters' : 'Member Schools' }}
                        </p>
                        <p class="text-2xl font-black text-slate-900 leading-tight mt-0.5">{{ stats?.clusters_count ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <!-- Filter and search bar -->
            <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-[0_2px_8px_rgba(15,23,42,0.03)] space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <!-- Status quick filter buttons -->
                    <div class="flex items-center gap-1.5 p-1 bg-slate-100 rounded-xl">
                        <button type="button" @click="setStatusFilter('all')"
                                :class="['px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer',
                                         (filterForm.status || 'all') === 'all' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900']">
                            All ({{ stats?.total ?? tenants.total ?? 0 }})
                        </button>
                        <button type="button" @click="setStatusFilter('active')"
                                :class="['px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer flex items-center gap-1.5',
                                         filterForm.status === 'active' ? 'bg-white text-emerald-700 shadow-xs' : 'text-slate-600 hover:text-slate-900']">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            Active ({{ stats?.active ?? 0 }})
                        </button>
                        <button type="button" @click="setStatusFilter('inactive')"
                                :class="['px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer flex items-center gap-1.5',
                                         filterForm.status === 'inactive' ? 'bg-white text-slate-700 shadow-xs' : 'text-slate-600 hover:text-slate-900']">
                            <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                            Inactive ({{ stats?.inactive ?? 0 }})
                        </button>
                    </div>

                    <button v-if="hasFilters" type="button" @click="clearFilters"
                            class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                        <span>Reset all filters</span>
                        <span>✕</span>
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 pt-1">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input v-model="filterForm.search" type="search"
                               placeholder="Search by school name, code, domain, or admin login..."
                               class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400 transition outline-none">
                    </div>

                    <div v-if="tenantType === 'school' && clusters?.length">
                        <select v-model="filterForm.cluster"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400 transition outline-none">
                            <option value="all">All Sahodaya Clusters</option>
                            <option v-for="c in clusters" :key="c.id" :value="c.id">
                                {{ c.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <select v-model="filterForm.status"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:bg-white focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400 transition outline-none">
                            <option value="all">All Statuses</option>
                            <option value="active">Active Only</option>
                            <option value="inactive">Inactive Only</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Table of Tenants -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-[0_2px_12px_rgba(15,23,42,0.03)] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs min-w-[950px]">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-200/80 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                <th class="px-5 py-3.5">{{ tenantType === 'school' ? 'School Organization' : 'Sahodaya Cluster' }}</th>
                                <th v-if="tenantType === 'school'" class="px-4 py-3.5">Parent Cluster</th>
                                <th v-else class="px-4 py-3.5">Affiliated Schools</th>
                                <th class="px-4 py-3.5">Portal Admin Account</th>
                                <th class="px-4 py-3.5">Web Addresses</th>
                                <th class="px-4 py-3.5">Status</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="!tenants.data.length">
                                <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                    <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-2">
                                        🔍
                                    </div>
                                    <p class="font-semibold text-slate-600">No {{ tenantType === 'school' ? 'schools' : 'Sahodaya clusters' }} found</p>
                                    <p class="text-xs text-slate-400 mt-1">Try adjusting your search criteria or filter options.</p>
                                </td>
                            </tr>
                            <tr v-for="tenant in tenants.data" :key="tenant.id"
                                class="hover:bg-indigo-50/30 transition-colors group">
                                <!-- School / Tenant Details -->
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xs font-bold text-white shrink-0 shadow-xs"
                                             :style="{ backgroundColor: hashColor(tenant.name) }">
                                            {{ getInitials(tenant.name) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <Link :href="`/admin/tenants/${tenant.id}`"
                                                      class="font-bold text-slate-900 hover:text-indigo-600 transition text-sm">
                                                    {{ tenant.name }}
                                                </Link>
                                                <span v-if="tenant.school_prefix"
                                                      class="px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-100 text-slate-700">
                                                    {{ tenant.school_prefix }}
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-2 mt-1 text-[11px] text-slate-400 font-mono">
                                                <span>ID: {{ tenant.id.substring(0, 8) }}…</span>
                                                <span v-if="tenant.setup_incomplete"
                                                      class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                                    ⚠️ Setup incomplete
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Parent or Children -->
                                <td v-if="tenantType === 'school'" class="px-4 py-4">
                                    <span v-if="tenant.parent?.name" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-100 text-slate-700">
                                        🏛️ {{ tenant.parent.name }}
                                    </span>
                                    <span v-else class="text-slate-400 italic text-xs">Unassigned</span>
                                </td>
                                <td v-else class="px-4 py-4">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-purple-50 text-purple-700 border border-purple-100">
                                        {{ tenant.children_count ?? 0 }} schools
                                    </span>
                                </td>

                                <!-- Login Username -->
                                <td class="px-4 py-4">
                                    <div v-if="tenant.login_username" class="flex items-center gap-2">
                                        <code class="px-2 py-1 rounded bg-slate-100 font-mono text-[11px] text-slate-800 select-all font-semibold">
                                            {{ tenant.login_username }}
                                        </code>
                                        <button type="button" @click="copyText(tenant.login_username)"
                                                title="Copy username"
                                                class="text-slate-400 hover:text-slate-600 transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                            </svg>
                                        </button>
                                    </div>
                                    <span v-else class="text-amber-600 font-semibold text-xs flex items-center gap-1">
                                        <span>⚠️</span> No admin account
                                    </span>
                                </td>

                                <!-- Domains -->
                                <td class="px-4 py-4">
                                    <div class="space-y-1">
                                        <div v-if="tenant.domain" class="flex items-center gap-1">
                                            <a :href="tenantPublicUrl(tenant)" target="_blank" rel="noopener"
                                               class="font-mono text-xs font-semibold text-indigo-600 hover:text-indigo-800 hover:underline flex items-center gap-1">
                                                <span>{{ tenant.domain }}</span>
                                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                </svg>
                                            </a>
                                        </div>
                                        <div v-if="tenant.subdomain" class="flex items-center gap-1">
                                            <a :href="tenantSubdomainUrl(tenant)" target="_blank" rel="noopener"
                                               class="font-mono text-[11px] text-slate-500 hover:text-indigo-600 flex items-center gap-1">
                                                <span>{{ tenant.subdomain }}.{{ tenantBaseDomain }}</span>
                                                <svg class="w-2.5 h-2.5 shrink-0 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                </svg>
                                            </a>
                                        </div>
                                        <span v-if="!tenant.domain && !tenant.subdomain" class="text-slate-300 text-xs">—</span>
                                    </div>
                                </td>

                                <!-- Status -->
                                <td class="px-4 py-4">
                                    <span v-if="tenant.is_active"
                                          class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        Active
                                    </span>
                                    <span v-else
                                          class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 text-slate-600">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        Inactive
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td class="px-5 py-4 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        <Link :href="`/admin/tenants/${tenant.id}`"
                                              class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition">
                                            <span>Manage</span>
                                            <span>→</span>
                                        </Link>

                                        <Link v-if="!readOnly" :href="`/admin/tenants/${tenant.id}/edit`"
                                              class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition"
                                              title="Edit organization">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                            </svg>
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="tenants.links?.length > 3" class="px-6 py-4 bg-slate-50/50 border-t border-slate-100 flex items-center justify-between">
                    <p class="text-xs text-slate-500 font-medium">
                        Showing page {{ tenants.current_page }} of {{ tenants.last_page }} ({{ tenants.total }} total items)
                    </p>
                    <div class="flex flex-wrap gap-1">
                        <Link v-for="link in tenants.links" :key="link.label"
                              :href="link.url || '#'"
                              :class="['px-3 py-1 rounded-lg text-xs font-semibold transition',
                                       link.active ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-200/60 bg-white border border-slate-200',
                                       !link.url && 'opacity-40 pointer-events-none']"
                              v-html="link.label" />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import { useDebouncedInertiaFilters } from '@/composables/useDebouncedInertiaFilters.js';
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();

const props = defineProps({
    tenants: Object,
    tenantType: { type: String, required: true },
    pageTitle: { type: String, required: true },
    createUrl: { type: String, default: null },
    databasesUrl: { type: String, default: null },
    readOnly: { type: Boolean, default: false },
    tenantBaseDomain: { type: String, default: 'sahodaya.test' },
    filters: { type: Object, default: () => ({ search: '', status: 'all', cluster: 'all' }) },
    stats: { type: Object, default: () => ({}) },
    clusters: { type: Array, default: () => [] },
});

const filterForm = reactive({
    search: props.filters?.search ?? '',
    status: props.filters?.status ?? 'all',
    cluster: props.filters?.cluster ?? 'all',
});

watch(() => props.filters, (f) => {
    filterForm.search = f?.search ?? '';
    filterForm.status = f?.status ?? 'all';
    filterForm.cluster = f?.cluster ?? 'all';
}, { deep: true });

const hasFilters = computed(() => !!filterForm.search || filterForm.status !== 'all' || filterForm.cluster !== 'all');

const listPath = computed(() =>
    props.tenantType === 'school' ? '/admin/schools' : '/admin/sahodayas',
);

function setStatusFilter(status) {
    filterForm.status = status;
    applyFilters();
}

async function exportAdminCredentials() {
    if (!(await confirm({
        message: 'This file contains every ' + (props.tenantType === 'school' ? 'school' : 'Sahodaya') + ' admin\'s temporary password in plain text. Handle it carefully and delete it once shared.',
        destructive: false,
    }))) return;
    window.location.href = `${listPath.value}/export-admin-credentials`;
}

function tenantPublicUrl(tenant) {
    if (!tenant.domain) return null;
    const proto = typeof window !== 'undefined' && window.location.protocol === 'https:' ? 'https:' : 'http:';
    return `${proto}//${tenant.domain}`;
}

function tenantSubdomainUrl(tenant) {
    if (!tenant.subdomain) return null;
    const proto = typeof window !== 'undefined' && window.location.protocol === 'https:' ? 'https:' : 'http:';
    return `${proto}//${tenant.subdomain}.${props.tenantBaseDomain}`;
}

function applyFilters() {
    router.get(listPath.value, {
        search: filterForm.search || undefined,
        status: filterForm.status !== 'all' ? filterForm.status : undefined,
        cluster: filterForm.cluster !== 'all' ? filterForm.cluster : undefined,
    }, { preserveState: true, preserveScroll: true });
}

useDebouncedInertiaFilters(filterForm, applyFilters, () => props.filters);

function clearFilters() {
    filterForm.search = '';
    filterForm.status = 'all';
    filterForm.cluster = 'all';
    router.get(listPath.value, {}, { preserveState: true, preserveScroll: true });
}

function getInitials(name) {
    if (!name) return '??';
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map(w => w[0].toUpperCase())
        .join('');
}

function hashColor(str) {
    if (!str) return '#4f46e5';
    const palette = ['#0284c7', '#0d9488', '#16a34a', '#d97706', '#dc2626', '#7c3aed', '#4f46e5', '#db2777', '#475569'];
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        hash = str.charCodeAt(i) + ((hash << 5) - hash);
    }
    return palette[Math.abs(hash) % palette.length];
}

function copyText(txt) {
    if (!txt) return;
    navigator.clipboard?.writeText(txt);
}
</script>
