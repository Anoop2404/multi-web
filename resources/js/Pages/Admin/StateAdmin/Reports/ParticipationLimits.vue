<template>
    <AdminLayout title="Participation & Limit Compliance">
        <div class="max-w-6xl mx-auto space-y-6">
            <PageHeader eyebrow="Reports" title="Participation & Limit Compliance" :description="stateProgram?.title">
                <template #actions>
                    <ReportDownloadButtons :csv-url="exportUrl" />
                </template>
            </PageHeader>

            <!-- Summary tiles -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="card text-center">
                    <p class="text-2xl font-bold text-slate-900">{{ sahodayaList.length }}</p>
                    <p class="text-xs text-gray-500">Sahodayas</p>
                </div>
                <div class="card text-center">
                    <p class="text-2xl font-bold text-slate-900">{{ rosterRows.length }}</p>
                    <p class="text-xs text-gray-500">Approved Entries</p>
                </div>
                <div class="card text-center">
                    <p class="text-2xl font-bold text-slate-900">{{ itemRows.length }}</p>
                    <p class="text-xs text-gray-500">Items Used</p>
                </div>
                <div class="card text-center" :class="overLimitCount ? 'border-rose-300 bg-rose-50/60' : ''">
                    <p class="text-2xl font-bold" :class="overLimitCount ? 'text-rose-600' : 'text-slate-900'">{{ overLimitCount }}</p>
                    <p class="text-xs" :class="overLimitCount ? 'text-rose-600' : 'text-gray-500'">Over Limit</p>
                </div>
            </div>

            <!-- Sahodayas list -->
            <div class="card !p-0 overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <h2 class="text-sm font-bold text-slate-900">Participating Sahodayas</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table w-full text-xs">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-100 text-slate-500 uppercase text-[10px] tracking-wider">
                                <th class="py-2.5 px-4 text-left">Sahodaya</th>
                                <th class="py-2.5 px-4 text-center">Approved Entries</th>
                                <th class="py-2.5 px-4 text-center">Schools</th>
                                <th class="py-2.5 px-4 text-center">Items</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="row in sahodayaList" :key="row.sahodaya_id" class="hover:bg-slate-50/50">
                                <td class="py-2.5 px-4 font-semibold text-slate-800">{{ row.sahodaya_name }}</td>
                                <td class="py-2.5 px-4 text-center font-mono">{{ row.approved_count }}</td>
                                <td class="py-2.5 px-4 text-center font-mono">{{ row.school_count }}</td>
                                <td class="py-2.5 px-4 text-center font-mono">{{ row.item_count }}</td>
                            </tr>
                            <tr v-if="!sahodayaList.length">
                                <td colspan="4" class="py-8 text-center text-slate-400">No approved qualifier entries yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sahodaya x Item compliance -->
            <div class="card !p-0 overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <h2 class="text-sm font-bold text-slate-900">Per Sahodaya × Item</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table w-full text-xs">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-100 text-slate-500 uppercase text-[10px] tracking-wider">
                                <th class="py-2.5 px-4 text-left">Sahodaya</th>
                                <th class="py-2.5 px-4 text-left">Item</th>
                                <th class="py-2.5 px-4 text-center">Approved</th>
                                <th class="py-2.5 px-4 text-center">Max / Sahodaya</th>
                                <th class="py-2.5 px-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(row, i) in sahodayaRows" :key="i" class="hover:bg-slate-50/50">
                                <td class="py-2.5 px-4 font-semibold text-slate-800">{{ row.sahodaya_name }}</td>
                                <td class="py-2.5 px-4">
                                    {{ row.item_title }}
                                    <span v-if="row.item_code" class="block text-[10px] font-mono text-slate-400">Code: {{ row.item_code }}</span>
                                </td>
                                <td class="py-2.5 px-4 text-center font-mono font-bold">{{ row.approved_count }}</td>
                                <td class="py-2.5 px-4 text-center font-mono">{{ row.max_per_school ?? 'Unlimited' }}</td>
                                <td class="py-2.5 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide border"
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
            <div class="card !p-0 overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <h2 class="text-sm font-bold text-slate-900">Item Utilization (State-wide)</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table w-full text-xs">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-100 text-slate-500 uppercase text-[10px] tracking-wider">
                                <th class="py-2.5 px-4 text-left">Item</th>
                                <th class="py-2.5 px-4 text-center">Approved</th>
                                <th class="py-2.5 px-4 text-center">Qualify Count</th>
                                <th class="py-2.5 px-4 text-center">Utilization</th>
                                <th class="py-2.5 px-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(row, i) in itemRows" :key="i" class="hover:bg-slate-50/50">
                                <td class="py-2.5 px-4 font-semibold text-slate-800">
                                    {{ row.item_title }}
                                    <span v-if="row.item_code" class="block text-[10px] font-mono text-slate-400">Code: {{ row.item_code }}</span>
                                </td>
                                <td class="py-2.5 px-4 text-center font-mono font-bold">{{ row.approved_count }}</td>
                                <td class="py-2.5 px-4 text-center font-mono">{{ row.qualify_count ?? 'Unlimited' }}</td>
                                <td class="py-2.5 px-4 text-center font-mono">{{ row.utilization_pct !== null ? row.utilization_pct + '%' : '—' }}</td>
                                <td class="py-2.5 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide border"
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

            <!-- Approved roster: Sahodaya -> School -> Student -->
            <div class="card !p-0 overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-slate-900">Approved Roster (Sahodaya → School → Student)</h2>
                    <span class="text-[10px] font-bold text-slate-500">{{ rosterRows.length }} entr{{ rosterRows.length === 1 ? 'y' : 'ies' }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table w-full text-xs">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-100 text-slate-500 uppercase text-[10px] tracking-wider">
                                <th class="py-2.5 px-4 text-left">Sahodaya</th>
                                <th class="py-2.5 px-4 text-left">School</th>
                                <th class="py-2.5 px-4 text-left">Item</th>
                                <th class="py-2.5 px-4 text-left">Student</th>
                                <th class="py-2.5 px-4 text-left">Roll No</th>
                                <th class="py-2.5 px-4 text-center">Position</th>
                                <th class="py-2.5 px-4 text-center">Grade</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(row, i) in rosterRows" :key="i" class="hover:bg-slate-50/50">
                                <td class="py-2.5 px-4 text-slate-600">{{ row.sahodaya_name }}</td>
                                <td class="py-2.5 px-4 font-semibold text-slate-800">{{ row.school_name }}</td>
                                <td class="py-2.5 px-4">
                                    {{ row.item_title }}
                                    <span v-if="row.item_code" class="block text-[10px] font-mono text-slate-400">Code: {{ row.item_code }}</span>
                                </td>
                                <td class="py-2.5 px-4 font-semibold text-slate-800">{{ row.student_name }}</td>
                                <td class="py-2.5 px-4 font-mono text-slate-600">{{ row.roll_number || '—' }}</td>
                                <td class="py-2.5 px-4 text-center font-mono">{{ row.position ?? '—' }}</td>
                                <td class="py-2.5 px-4 text-center font-mono">{{ row.grade || '—' }}</td>
                            </tr>
                            <tr v-if="!rosterRows.length">
                                <td colspan="7" class="py-8 text-center text-slate-400">No approved qualifier entries yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import ReportDownloadButtons from '@/Components/reports/ReportDownloadButtons.vue';

const props = defineProps({
    stateProgram: Object,
    sahodayaRows: { type: Array, default: () => [] },
    itemRows: { type: Array, default: () => [] },
    sahodayaList: { type: Array, default: () => [] },
    rosterRows: { type: Array, default: () => [] },
    exportUrl: String,
});

const overLimitCount = computed(() => props.sahodayaRows.filter((r) => r.exceeds).length + props.itemRows.filter((r) => r.exceeds).length);
</script>
