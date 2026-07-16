<template>
    <SahodayaAdminLayout :title="exam.title" :sahodaya="sahodaya" :show-header-title="false">
        <PageHeader :title="`${exam.title} — Live session`" eyebrow="Talent Search exam"
                    description="Monitor who has started, submitted, and remaining progress.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}`" class="btn-secondary text-sm">← Exam overview</Link>
            </template>
        </PageHeader>

        <McqExamSubNav :sahodaya-id="sahodaya.id" :exam-id="exam.id"
                       :delivery-mode="exam.delivery_mode || 'online'"
                       :results-published="!!exam.results_published"
                       active="session" />

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
            <div class="card card--muted text-center !py-4">
                <p class="text-2xl font-bold">{{ counts.total }}</p><p class="text-xs text-slate-500">Registered</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-2xl font-bold text-blue-700">{{ counts.started }}</p><p class="text-xs text-slate-500">Started</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-2xl font-bold text-green-700">{{ counts.submitted }}</p><p class="text-xs text-slate-500">Submitted</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-2xl font-bold text-amber-700">{{ counts.pending }}</p><p class="text-xs text-slate-500">Not started</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-2xl font-bold text-red-700">{{ counts.absent }}</p><p class="text-xs text-slate-500">Absent</p>
            </div>
        </div>

        <div class="card card--flush overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr><th>Reg. no.</th><th>Student</th><th>School</th><th>Attendance</th><th>Session</th><th>Started</th><th>Submitted</th><th>Score</th></tr>
                </thead>
                <tbody>
                    <tr v-for="r in registrations" :key="r.id">
                        <td class="font-mono text-xs">{{ r.hall_ticket_no || '—' }}</td>
                        <td>{{ r.student }}</td>
                        <td class="text-xs">{{ r.school }}</td>
                        <td class="text-xs capitalize">{{ r.attendance_status || '—' }}</td>
                        <td class="text-xs">
                            <span class="font-semibold" :class="sessionTone(r.session_status?.tone)">{{ r.session_status?.label || r.status }}</span>
                        </td>
                        <td class="text-xs">{{ formatDateTime(r.started_at) }}</td>
                        <td class="text-xs">{{ formatDateTime(r.submitted_at) }}</td>
                        <td>{{ r.score ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';
import { formatDateTime } from '@/support/calendarDates.js';

const props = defineProps({ sahodaya: Object, exam: Object, registrations: { type: Array, default: () => [] } });
const counts = computed(() => ({
    total: props.registrations.length,
    started: props.registrations.filter(r => r.session_status?.key === 'started' || (r.started_at && r.status !== 'submitted')).length,
    submitted: props.registrations.filter(r => r.status === 'submitted' || r.session_status?.key === 'submitted').length,
    pending: props.registrations.filter(r => r.session_status?.key === 'not_started').length,
    absent: props.registrations.filter(r => r.session_status?.key === 'absent' || r.attendance_status === 'absent').length,
}));

function sessionTone(tone) {
    return ({
        success: 'text-emerald-700',
        warning: 'text-amber-700',
        danger: 'text-red-700',
        info: 'text-blue-700',
    })[tone] || 'text-slate-700';
}
</script>
