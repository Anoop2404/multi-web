<template>
    <SchoolAdminLayout :title="`Assignment completeness — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader :title="`Assignment completeness — ${event.title}`" :eyebrow="programLabel"
                    description="Your school's readiness per item: chest, item reg, schedule and marks.">
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">← Reports</Link>
                <a :href="xlsUrl" class="btn-secondary text-sm">Export Excel ↓</a>
            </template>
        </PageHeader>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center"><p class="text-xl font-bold">{{ totals.items }}</p><p class="text-xs text-slate-500 mt-1">Items</p></div>
            <div class="card card--muted !py-4 text-center"><p class="text-xl font-bold">{{ totals.performers }}</p><p class="text-xs text-slate-500 mt-1">Performers</p></div>
            <div class="card card--muted !py-4 text-center"><p class="text-xl font-bold text-amber-700">{{ totals.pending_regs }}</p><p class="text-xs text-slate-500 mt-1">Pending approval</p></div>
            <div class="card card--muted !py-4 text-center"><p class="text-xl font-bold text-red-700">{{ totals.chest_missing }}</p><p class="text-xs text-slate-500 mt-1">Chest missing</p></div>
            <div class="card card--muted !py-4 text-center"><p class="text-xl font-bold text-indigo-700">{{ totals.marks_pending }}</p><p class="text-xs text-slate-500 mt-1">Marks pending</p></div>
        </div>

        <div class="card overflow-hidden p-0">
            <table class="data-table text-sm">
                <thead>
                    <tr><th>Head</th><th>Item</th><th>Approved</th><th>Pending</th><th>Performers</th><th>Chest</th><th>Item reg</th><th>Marks</th></tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.item_id">
                        <td class="text-xs text-slate-500">{{ row.head_name ?? '—' }}</td>
                        <td class="font-medium">{{ row.title }}</td>
                        <td>{{ row.approved }}</td>
                        <td>{{ row.pending }}</td>
                        <td>{{ row.performers }}</td>
                        <td>{{ row.chest_assigned }}<span v-if="row.chest_missing" class="text-red-600 text-xs"> (−{{ row.chest_missing }})</span></td>
                        <td>{{ row.item_reg_assigned }}<span v-if="row.item_reg_missing" class="text-red-600 text-xs"> (−{{ row.item_reg_missing }})</span></td>
                        <td>{{ row.marks_entered }}/{{ row.performers }}</td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="8" class="p-6 text-center text-slate-400">No items yet.</td></tr>
                </tbody>
            </table>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({
    school: Object, program: [String, Object], programMeta: Object, event: Object,
    rows: Array, totals: Object, xlsUrl: String,
});
const { programLabel, programBase } = useSchoolProgramContext(props);
</script>
