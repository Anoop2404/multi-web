<template>
    <AdminLayout title="Participation & Limit Compliance">
        <div class="max-w-6xl mx-auto space-y-6">
            <!-- Header Banner -->
            <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 rounded-3xl p-6 sm:p-8 text-white shadow-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <Link href="/admin/state-workspace/qualifiers" class="text-xs font-bold text-indigo-300 hover:text-white transition">← Qualifiers</Link>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight">Participation & Limit Compliance</h1>
                    <p class="text-slate-300 text-xs sm:text-sm">{{ stateProgram?.title }}</p>
                </div>
                <a :href="exportUrl" class="px-4 py-2.5 rounded-2xl bg-white/10 hover:bg-white/20 text-white font-bold text-xs border border-white/20 transition">
                    ⬇ Export CSV
                </a>
            </div>

            <!-- Sahodaya x Item compliance -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <h2 class="text-base font-bold text-slate-900">Per Sahodaya × Item</h2>
                    <span class="text-xs font-bold text-slate-500">{{ sahodayaRows.length }} row(s)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider font-semibold border-b border-slate-100">
                                <th class="py-3 px-4">Sahodaya</th>
                                <th class="py-3 px-4">Item</th>
                                <th class="py-3 px-4 text-center">Approved</th>
                                <th class="py-3 px-4 text-center">Max / Sahodaya</th>
                                <th class="py-3 px-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <tr v-for="(row, i) in sahodayaRows" :key="i" class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-3.5 px-4 font-semibold text-slate-800">{{ row.sahodaya_name }}</td>
                                <td class="py-3.5 px-4">
                                    {{ row.item_title }}
                                    <span v-if="row.item_code" class="block text-xs font-mono font-normal text-slate-400">Code: {{ row.item_code }}</span>
                                </td>
                                <td class="py-3.5 px-4 text-center font-mono font-bold">{{ row.approved_count }}</td>
                                <td class="py-3.5 px-4 text-center font-mono">{{ row.max_per_school ?? 'Unlimited' }}</td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold border"
                                          :class="row.exceeds ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'">
                                        {{ row.exceeds ? 'Over limit' : 'OK' }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!sahodayaRows.length">
                                <td colspan="5" class="py-8 text-center text-slate-400">No approved qualifier entries yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Item utilization -->
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <h2 class="text-base font-bold text-slate-900">Item Utilization (State-wide)</h2>
                    <span class="text-xs font-bold text-slate-500">{{ itemRows.length }} item(s)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider font-semibold border-b border-slate-100">
                                <th class="py-3 px-4">Item</th>
                                <th class="py-3 px-4 text-center">Approved</th>
                                <th class="py-3 px-4 text-center">Qualify Count</th>
                                <th class="py-3 px-4 text-center">Utilization</th>
                                <th class="py-3 px-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <tr v-for="(row, i) in itemRows" :key="i" class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-3.5 px-4 font-semibold text-slate-800">
                                    {{ row.item_title }}
                                    <span v-if="row.item_code" class="block text-xs font-mono font-normal text-slate-400">Code: {{ row.item_code }}</span>
                                </td>
                                <td class="py-3.5 px-4 text-center font-mono font-bold">{{ row.approved_count }}</td>
                                <td class="py-3.5 px-4 text-center font-mono">{{ row.qualify_count ?? 'Unlimited' }}</td>
                                <td class="py-3.5 px-4 text-center font-mono">{{ row.utilization_pct !== null ? row.utilization_pct + '%' : '—' }}</td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold border"
                                          :class="row.exceeds ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'">
                                        {{ row.exceeds ? 'Over limit' : 'OK' }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!itemRows.length">
                                <td colspan="5" class="py-8 text-center text-slate-400">No approved qualifier entries yet.</td>
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
    stateProgram: Object,
    sahodayaRows: { type: Array, default: () => [] },
    itemRows: { type: Array, default: () => [] },
    exportUrl: String,
});
</script>
