<template>
    <AdminLayout title="State Kalotsav Portal">
        <div class="space-y-6">
            <!-- Top Header & Quick Actions -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-6 rounded-2xl text-white shadow-xl">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/20 text-amber-300 text-xs font-semibold uppercase tracking-wider mb-2">
                        <span>🏛️ State Kalotsavam Authority</span>
                    </div>
                    <h1 class="text-2xl font-bold tracking-tight">State Kalotsavam Workspace</h1>
                    <p class="text-slate-300 text-sm mt-1">Manage state programs, review qualifier intakes, monitor Sahodaya clusters, and certify results.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <Link href="/admin/state-workspace/qualifiers" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-sm transition shadow-lg shadow-amber-500/20">
                        <span>📥 Review Intakes</span>
                    </Link>
                    <Link href="/admin/state-programs" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white font-semibold text-sm transition border border-white/10 backdrop-blur">
                        <span>📋 Configure Programs</span>
                    </Link>
                </div>
            </div>

            <!-- Metric Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl font-bold">
                        🎭
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Total Programs</p>
                        <p class="text-2xl font-black text-slate-900 mt-0.5">{{ programs.length }}</p>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl font-bold">
                        ✨
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Published</p>
                        <p class="text-2xl font-black text-slate-900 mt-0.5">
                            {{ programs.filter(p => p.status === 'published').length }}
                        </p>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl font-bold">
                        🏛️
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Active Clusters</p>
                        <p class="text-2xl font-black text-slate-900 mt-0.5">
                            {{ programs.reduce((acc, p) => acc + (p.propagations_count || 0), 0) }}
                        </p>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl font-bold">
                        🏆
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wider">Catalog Items</p>
                        <p class="text-2xl font-black text-slate-900 mt-0.5">
                            {{ programs.reduce((acc, p) => acc + (p.items_count || 0), 0) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Programs Data Table -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h2 class="font-bold text-slate-900 text-base">State Kalotsavam Programs</h2>
                    <span class="text-xs font-medium text-slate-500">{{ programs.length }} record(s)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 text-slate-500 text-xs uppercase tracking-wider font-semibold border-b border-slate-100">
                                <th class="py-3.5 px-6">Program Title</th>
                                <th class="py-3.5 px-4">Academic Year</th>
                                <th class="py-3.5 px-4">Status</th>
                                <th class="py-3.5 px-4 text-center">Clusters</th>
                                <th class="py-3.5 px-4 text-center">Items</th>
                                <th class="py-3.5 px-6 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <tr v-for="p in programs" :key="p.id" class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-4 px-6 font-bold text-slate-900">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold text-xs shrink-0">
                                            KAL
                                        </div>
                                        <div>
                                            <p class="font-bold text-slate-900 leading-snug">{{ p.title }}</p>
                                            <p class="text-xs text-slate-400">ID: {{ p.id.substring(0, 8) }}...</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-4 font-mono text-xs text-slate-600">
                                    {{ p.academic_year || '2026-2027' }}
                                </td>
                                <td class="py-4 px-4">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold capitalize"
                                          :class="{
                                              'bg-emerald-50 text-emerald-700 border border-emerald-200/60': p.status === 'published',
                                              'bg-amber-50 text-amber-700 border border-amber-200/60': p.status === 'draft',
                                              'bg-slate-100 text-slate-600 border border-slate-200': !['published', 'draft'].includes(p.status)
                                          }">
                                        <span class="w-1.5 h-1.5 rounded-full"
                                              :class="{
                                                  'bg-emerald-500': p.status === 'published',
                                                  'bg-amber-500': p.status === 'draft',
                                                  'bg-slate-400': !['published', 'draft'].includes(p.status)
                                              }"></span>
                                        {{ p.status }}
                                    </span>
                                </td>
                                <td class="py-4 px-4 text-center font-bold text-slate-900">
                                    {{ p.propagations_count ?? 0 }}
                                </td>
                                <td class="py-4 px-4 text-center font-bold text-slate-900">
                                    {{ p.items_count ?? 0 }}
                                </td>
                                <td class="py-4 px-6 text-right">
                                    <div class="inline-flex items-center justify-end gap-2">
                                        <Link :href="`/admin/kalotsav/${p.id}`" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                                            Overview
                                        </Link>
                                        <Link :href="`/admin/kalotsav/${p.id}/results`" class="px-3 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold transition">
                                            Results
                                        </Link>
                                        <Link :href="`/admin/kalotsav/${p.id}/winners`" class="px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold transition">
                                            Winners
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!programs.length">
                                <td colspan="6" class="py-12 text-center text-slate-400">
                                    <p class="text-2xl mb-2">🎭</p>
                                    <p class="font-medium text-slate-600">No State Kalotsavam programs created yet.</p>
                                    <p class="text-xs text-slate-400 mt-1">Configure your first program under State Programs Configuration.</p>
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
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    programs: { type: Array, default: () => [] }
});
</script>
