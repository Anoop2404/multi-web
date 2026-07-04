<template>
    <SchoolAdminLayout :title="`Mark entry status — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader :title="`Mark entry status — ${event.title}`" :eyebrow="programLabel"
                    description="Mark entry progress for your school's participants by item.">
            <template #actions>
                <Link :href="`${programBase}/reports`" class="btn-secondary text-sm">← Reports</Link>
            </template>
        </PageHeader>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ summary.items }}</p>
                <p class="text-xs text-slate-500 mt-1">Items</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ summary.participants }}</p>
                <p class="text-xs text-slate-500 mt-1">Participants</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ summary.marked }}</p>
                <p class="text-xs text-slate-500 mt-1">Marked</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-amber-700">{{ summary.pending }}</p>
                <p class="text-xs text-slate-500 mt-1">Pending</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ summary.complete }}/{{ summary.items }}</p>
                <p class="text-xs text-slate-500 mt-1">Items complete</p>
            </div>
        </div>

        <div class="card overflow-hidden p-0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Participants</th>
                        <th>Marked</th>
                        <th>Pending</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.item_id">
                        <td class="font-medium">{{ row.title }}</td>
                        <td>{{ row.participants }}</td>
                        <td>{{ row.marked }}</td>
                        <td>{{ row.pending }}</td>
                        <td>
                            <span class="status-pill text-xs" :class="row.complete ? 'status-pill--completed' : 'status-pill--open'">
                                {{ row.complete ? 'Complete' : 'Pending' }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="5" class="p-6 text-center text-slate-400">No items with registrations yet.</td>
                    </tr>
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
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    summary: Object,
    rows: Array,
});
const { programLabel, programBase } = useSchoolProgramContext(props);
</script>
