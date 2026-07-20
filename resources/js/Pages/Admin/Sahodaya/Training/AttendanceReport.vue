<template>
    <SahodayaAdminLayout :title="`${program.title} — Attendance report`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="program.title" eyebrow="Attendance report"
                    description="Filled present / absent data from online marking — download as PDF or Excel.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/attendance`"
                      class="btn-secondary text-sm">
                    ← Mark attendance
                </Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/attendance/sheet`"
                      class="btn-secondary text-sm">
                    Attendance sheet
                </Link>
                <a :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/attendance/report/pdf`"
                   class="btn-primary text-sm">
                    Download PDF
                </a>
                <a :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/attendance/export`"
                   class="btn-secondary text-sm">
                    Excel
                </a>
            </template>
        </PageHeader>

        <div class="card mb-4 bg-slate-50 border-slate-200 text-sm text-slate-700">
            <p class="font-semibold text-slate-900">What this is</p>
            <p class="mt-1">A report of attendance already marked in the system (P / A). Use this after the event — not the blank venue sheet.</p>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-6">
            <div class="card text-center">
                <p class="text-2xl font-bold">{{ rows.length }}</p>
                <p class="text-xs text-gray-500">Participants</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold">{{ sessions.length }}</p>
                <p class="text-xs text-gray-500">Sessions</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-green-700">{{ markedCount }}</p>
                <p class="text-xs text-gray-500">Marks recorded</p>
            </div>
        </div>

        <div class="card card--flush overflow-hidden">
            <div class="overflow-x-auto">
                <table class="data-table min-w-[720px] text-sm">
                    <thead>
                        <tr>
                            <th>Sl</th>
                            <th>Teacher</th>
                            <th>Category</th>
                            <th>School</th>
                            <th>Present</th>
                            <th v-for="session in sessions" :key="session.id">{{ session.title }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in rows" :key="i">
                            <td>{{ i + 1 }}</td>
                            <td>{{ row.teacher_name || '—' }}</td>
                            <td>{{ row.category || '—' }}</td>
                            <td>{{ (row.school_name || '').toUpperCase() || '—' }}</td>
                            <td>{{ row.days_present }}/{{ row.total_sessions }}</td>
                            <td v-for="session in sessions" :key="session.id">
                                <span :class="markClass(row[`session_${session.id}`])">
                                    {{ markLabel(row[`session_${session.id}`]) }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td :colspan="5 + sessions.length" class="text-center text-gray-400 py-8">
                                No attendance data yet. Mark attendance first.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    program: Object,
    sessions: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
});

const markedCount = computed(() =>
    props.rows.reduce((sum, row) => {
        return sum + props.sessions.filter(s => ['present', 'absent'].includes(row[`session_${s.id}`])).length;
    }, 0),
);

function markLabel(status) {
    if (status === 'present') return 'P';
    if (status === 'absent') return 'A';
    return '—';
}

function markClass(status) {
    if (status === 'present') return 'text-green-700 font-semibold';
    if (status === 'absent') return 'text-red-600 font-semibold';
    return 'text-gray-400';
}
</script>
