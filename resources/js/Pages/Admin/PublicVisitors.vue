<template>
    <AdminLayout title="Live Public Visitors & Screens">
        <div class="space-y-6 max-w-7xl mx-auto">
            <!-- Header & Status Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white p-4 rounded-xl border border-slate-200/80 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="relative flex h-3.5 w-3.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-emerald-500"></span>
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-bold text-slate-800">Real-Time Traffic Monitor</h2>
                            <span class="px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider bg-emerald-50 text-emerald-700 rounded-full border border-emerald-200">
                                Live
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Unique active browsers and TV displays in the last 5 minutes. Auto-refreshes every 10s.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 self-start sm:self-auto">
                    <div class="text-right hidden sm:block">
                        <p class="text-[10px] text-slate-400 uppercase tracking-wider font-semibold">Last Synced</p>
                        <p class="text-xs font-medium text-slate-700 font-mono">{{ monitor.updated_at || 'Syncing…' }}</p>
                    </div>
                    <button
                        type="button"
                        @click="manualRefresh"
                        :disabled="isRefreshing"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-xs font-semibold text-slate-700 transition shadow-xs disabled:opacity-50"
                        title="Refresh now"
                    >
                        <svg
                            class="w-3.5 h-3.5 text-slate-500"
                            :class="{ 'animate-spin': isRefreshing }"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>{{ isRefreshing ? 'Refreshing…' : 'Refresh' }}</span>
                    </button>
                </div>
            </div>

            <!-- Error Banner -->
            <div v-if="error" class="p-3 bg-rose-50 border border-rose-200 rounded-lg text-xs text-rose-700 flex items-center justify-between">
                <span>{{ error }}</span>
                <button type="button" @click="refresh" class="font-bold underline ml-2">Retry</button>
            </div>

            <!-- Key Metrics Overview Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Public Web Visitors -->
                <div class="relative overflow-hidden bg-white p-5 rounded-xl border border-slate-200/80 shadow-sm hover:border-slate-300 transition">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Public Visitors</span>
                        <div class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-3xl font-extrabold text-slate-900 tracking-tight">{{ formatNumber(monitor.active_visitors) }}</span>
                        <span class="text-xs font-medium text-emerald-600">Active</span>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-400">Website visitors browsing festival pages</p>
                </div>

                <!-- TV Display Screens -->
                <div class="relative overflow-hidden bg-white p-5 rounded-xl border border-slate-200/80 shadow-sm hover:border-slate-300 transition">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Live TV Displays</span>
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-3xl font-extrabold text-slate-900 tracking-tight">{{ formatNumber(monitor.active_tv_screens) }}</span>
                        <span class="text-xs font-medium text-indigo-600">Connected</span>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-400">Stage / Hall live scoreboard screens</p>
                </div>

                <!-- Active Sahodayas -->
                <div class="relative overflow-hidden bg-white p-5 rounded-xl border border-slate-200/80 shadow-sm hover:border-slate-300 transition">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Active Sahodayas</span>
                        <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-3xl font-extrabold text-slate-900 tracking-tight">{{ formatNumber(activeSahodayasCount) }}</span>
                        <span class="text-xs font-medium text-slate-500">Clusters</span>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-400">Sahodayas currently serving live traffic</p>
                </div>

                <!-- Combined Total Traffic -->
                <div class="relative overflow-hidden bg-white p-5 rounded-xl border border-slate-200/80 shadow-sm hover:border-slate-300 transition">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Live Audience</span>
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-3xl font-extrabold text-slate-900 tracking-tight">{{ formatNumber(totalAudience) }}</span>
                        <span class="text-xs font-medium text-slate-500">Combined</span>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-400">Total concurrent viewers & screens</p>
                </div>
            </div>

            <!-- Sahodaya Wise Breakdown Section -->
            <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-slate-50/50">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <span>Sahodaya Wise Breakdown</span>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-200 text-slate-700 font-mono">
                                {{ (monitor.sahodayas || []).length }}
                            </span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Real-time traffic distributed by each Sahodaya cluster (Public visitors vs. TV screens).
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="relative">
                            <input
                                v-model="sahodayaSearch"
                                type="text"
                                placeholder="Filter Sahodaya…"
                                class="text-xs pl-8 pr-3 py-1.5 rounded-lg border border-slate-200 bg-white placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 w-48 sm:w-56"
                            />
                            <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-semibold text-slate-600 uppercase tracking-wider">
                                <th class="py-3 px-4">#</th>
                                <th class="py-3 px-4">Sahodaya Name</th>
                                <th class="py-3 px-4 text-center">Subdomain</th>
                                <th class="py-3 px-4 text-right">Public Visitors</th>
                                <th class="py-3 px-4 text-right">TV Screens</th>
                                <th class="py-3 px-4 text-right">Total Traffic</th>
                                <th class="py-3 px-4 w-40 text-center">Share</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="(row, idx) in filteredSahodayas"
                                :key="row.tenant_id"
                                class="hover:bg-slate-50/80 transition"
                            >
                                <td class="py-3 px-4 text-slate-400 font-mono text-[11px]">
                                    {{ idx + 1 }}
                                </td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-800 text-sm">
                                        {{ row.tenant_name }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-mono">
                                        ID: {{ row.tenant_id }}
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span v-if="row.subdomain" class="inline-block px-2 py-0.5 text-[11px] font-mono rounded bg-slate-100 text-slate-700 border border-slate-200/60">
                                        {{ row.subdomain }}.truecampus.in
                                    </span>
                                    <span v-else class="text-slate-400">—</span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <span class="inline-flex items-center gap-1.5 font-bold font-mono text-sm text-sky-700 bg-sky-50 px-2.5 py-1 rounded-md border border-sky-100">
                                        <svg class="w-3.5 h-3.5 text-sky-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        {{ formatNumber(row.public_visitors) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <span class="inline-flex items-center gap-1.5 font-bold font-mono text-sm text-indigo-700 bg-indigo-50 px-2.5 py-1 rounded-md border border-indigo-100">
                                        <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                        {{ formatNumber(row.tv_screens) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <span class="font-extrabold font-mono text-sm text-slate-900">
                                        {{ formatNumber(row.total) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 bg-slate-100 rounded-full h-2 overflow-hidden">
                                            <div
                                                class="bg-indigo-600 h-2 rounded-full transition-all duration-500"
                                                :style="{ width: `${trafficPercentage(row.total)}%` }"
                                            ></div>
                                        </div>
                                        <span class="text-[10px] font-mono text-slate-500 w-9 text-right font-semibold">
                                            {{ trafficPercentage(row.total) }}%
                                        </span>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!filteredSahodayas.length">
                                <td colspan="7" class="py-8 text-center text-slate-400">
                                    <div class="flex flex-col items-center justify-center gap-1">
                                        <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <p class="font-medium text-xs text-slate-500">
                                            {{ sahodayaSearch ? 'No Sahodaya matches your filter.' : 'No active visitors recorded in the last 5 minutes.' }}
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="filteredSahodayas.length" class="bg-slate-50/90 font-bold border-t border-slate-200 text-slate-800">
                            <tr>
                                <td class="py-3 px-4" colspan="3">Total Active across Sahodayas</td>
                                <td class="py-3 px-4 text-right font-mono text-sky-800 text-sm">
                                    {{ formatNumber(totalSahodayaPublic) }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-indigo-800 text-sm">
                                    {{ formatNumber(totalSahodayaTv) }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-slate-900 text-sm">
                                    {{ formatNumber(totalSahodayaSum) }}
                                </td>
                                <td class="py-3 px-4 text-center text-[11px] font-mono text-slate-500">
                                    100%
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Active Events Concurrency Limiter Status (if any throttled events) -->
            <div v-if="(monitor.events || []).length" class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Per-Event Visitor Caps & Concurrency</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Events with active visitors tracked against the concurrent visitor safety limiter (200 threshold).</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-semibold text-slate-600 uppercase tracking-wider">
                                <th class="py-3 px-4">Event</th>
                                <th class="py-3 px-4">Sahodaya</th>
                                <th class="py-3 px-4 text-right">Active Viewers</th>
                                <th class="py-3 px-4 text-right">Event Safety Cap</th>
                                <th class="py-3 px-4 w-44 text-center">Capacity Load</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="ev in monitor.events" :key="`${ev.tenant_id}-${ev.event_id}`" class="hover:bg-slate-50/80">
                                <td class="py-3 px-4 font-bold text-slate-800">
                                    {{ ev.event_title || `Event #${ev.event_id}` }}
                                </td>
                                <td class="py-3 px-4 text-slate-600">
                                    {{ ev.tenant_name }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono font-bold text-slate-900">
                                    {{ formatNumber(ev.active) }}
                                </td>
                                <td class="py-3 px-4 text-right font-mono text-slate-500">
                                    {{ formatNumber(ev.limit || 200) }}
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 bg-slate-100 rounded-full h-2 overflow-hidden">
                                            <div
                                                class="h-2 rounded-full transition-all"
                                                :class="loadColorClass(ev.active, ev.limit || 200)"
                                                :style="{ width: `${Math.min(100, Math.round((ev.active / (ev.limit || 200)) * 100))}%` }"
                                            ></div>
                                        </div>
                                        <span class="text-[10px] font-mono text-slate-500 w-10 text-right">
                                            {{ Math.round((ev.active / (ev.limit || 200)) * 100) }}%
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    monitor: {
        type: Object,
        default: () => ({ active_visitors: 0, active_tv_screens: 0, sahodayas: [], events: [], updated_at: '' }),
    },
});

const monitor = ref(props.monitor || {});
const error = ref('');
const isRefreshing = ref(false);
const sahodayaSearch = ref('');
let timer;
let controller;

function formatNumber(num) {
    return Number(num || 0).toLocaleString('en-IN');
}

const activeSahodayasCount = computed(() => {
    return (monitor.value?.sahodayas || []).length;
});

const totalAudience = computed(() => {
    return Number(monitor.value?.active_visitors || 0) + Number(monitor.value?.active_tv_screens || 0);
});

const filteredSahodayas = computed(() => {
    const list = monitor.value?.sahodayas || [];
    const query = sahodayaSearch.value.trim().toLowerCase();
    if (!query) return list;
    return list.filter(row =>
        (row.tenant_name || '').toLowerCase().includes(query) ||
        (row.subdomain || '').toLowerCase().includes(query)
    );
});

const totalSahodayaPublic = computed(() => {
    return filteredSahodayas.value.reduce((acc, row) => acc + (Number(row.public_visitors) || 0), 0);
});

const totalSahodayaTv = computed(() => {
    return filteredSahodayas.value.reduce((acc, row) => acc + (Number(row.tv_screens) || 0), 0), 0;
});

const totalSahodayaSum = computed(() => {
    return totalSahodayaPublic.value + totalSahodayaTv.value;
});

function trafficPercentage(count) {
    const total = totalAudience.value;
    if (!total || total === 0) return 0;
    return Math.min(100, Math.round(((Number(count) || 0) / total) * 100));
}

function loadColorClass(active, limit) {
    const pct = (active / limit) * 100;
    if (pct >= 90) return 'bg-rose-500';
    if (pct >= 70) return 'bg-amber-500';
    return 'bg-emerald-500';
}

async function refresh() {
    if (document.visibilityState !== 'visible') return;
    controller = new AbortController();
    try {
        isRefreshing.value = true;
        const response = await fetch('/admin/public-visitors/data', {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
            signal: controller.signal,
        });
        if (!response.ok) throw new Error('Could not refresh visitor counts.');
        monitor.value = await response.json();
        error.value = '';
    } catch (e) {
        if (e.name !== 'AbortError') {
            error.value = 'Could not refresh live visitor counts; showing last known status.';
        }
    } finally {
        isRefreshing.value = false;
    }
}

function manualRefresh() {
    refresh();
}

onMounted(() => {
    timer = setInterval(refresh, 10000);
});

onBeforeUnmount(() => {
    clearInterval(timer);
    controller?.abort();
});
</script>
