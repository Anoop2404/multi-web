<template>
    <SahodayaAdminLayout :title="`Reports — ${exam.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="Talent Search exam" description="Registration register, fee summary, and attendance exports." />
        <McqExamSubNav :sahodaya-id="sahodaya.id" :exam-id="exam.id" active="reports" />

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ stats.registrations }}</p>
                <p class="text-xs text-slate-500 mt-1">Registrations</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ stats.present }}</p>
                <p class="text-xs text-slate-500 mt-1">Present</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">₹{{ stats.fee_collected }}</p>
                <p class="text-xs text-slate-500 mt-1">Fees collected</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-amber-700">₹{{ stats.fee_pending }}</p>
                <p class="text-xs text-slate-500 mt-1">Fees pending</p>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-4 mb-6">
            <div class="card">
                <h3 class="section-title">Registration register</h3>
                <p class="section-desc">All registrations with approval, attendance, and marks.</p>
                <a :href="exportBase + '/registration/export'" class="btn-secondary text-sm mt-3 inline-block">Export Excel ↓</a>
            </div>
            <div class="card">
                <h3 class="section-title">Fee summary</h3>
                <p class="section-desc">Per-school batch fees and payment status.</p>
                <a :href="exportBase + '/fees/export'" class="btn-secondary text-sm mt-3 inline-block">Export Excel ↓</a>
                <a :href="exportBase + '/fees-pending/export'" class="btn-secondary text-sm mt-2 inline-block">Pending fees ↓</a>
                <a :href="exportBase + '/fees-rejected/export'" class="btn-secondary text-sm mt-2 inline-block">Rejected fees ↓</a>
            </div>
            <div class="card">
                <h3 class="section-title">Attendance sheet</h3>
                <p class="section-desc">Hall ticket list for exam-day attendance marking.</p>
                <a :href="exportBase + '/attendance/export'" class="btn-secondary text-sm mt-3 inline-block">Export Excel ↓</a>
                <a :href="exportBase + '/absent/export'" class="btn-secondary text-sm mt-2 inline-block">Absent list ↓</a>
            </div>
            <div class="card">
                <h3 class="section-title">Marks & results</h3>
                <p class="section-desc">Present students without marks, toppers, and grade bands.</p>
                <a :href="exportBase + '/marks-pending/export'" class="btn-secondary text-sm mt-3 inline-block">Marks pending ↓</a>
                <a v-if="exam.results_published" :href="exportBase + '/toppers/export'" class="btn-secondary text-sm mt-2 inline-block">Toppers ↓</a>
                <a :href="exportBase + '/grade-bands/export'" class="btn-secondary text-sm mt-2 inline-block">Grade bands ↓</a>
            </div>
            <div v-if="exam.delivery_mode === 'online'" class="card">
                <h3 class="section-title">Online session status</h3>
                <p class="section-desc">Started, submitted, expired, and not-started counts.</p>
                <a :href="exportBase + '/session-status/export'" class="btn-secondary text-sm mt-3 inline-block">Session status ↓</a>
            </div>
            <div v-if="(exam.exam_level ?? 1) > 1" class="card">
                <h3 class="section-title">Level 2 qualifiers</h3>
                <p class="section-desc">Eligible and not-eligible students from Level 1 promotion rules.</p>
                <a :href="exportBase + '/level2-qualifiers/export'" class="btn-secondary text-sm mt-3 inline-block">Qualifier list ↓</a>
            </div>
        </div>

        <section class="card card--flush overflow-hidden mb-6">
            <div class="p-4 border-b border-slate-100">
                <h3 class="section-title !mb-0">Fee summary preview</h3>
            </div>
            <table class="data-table">
                <thead><tr><th>School</th><th>Students</th><th>Due</th><th>Status</th></tr></thead>
                <tbody>
                    <tr v-for="(row, i) in feeSummary" :key="i">
                        <td>{{ row.school_name }}</td>
                        <td>{{ row.student_count }}</td>
                        <td>₹{{ row.total_due }}</td>
                        <td class="text-xs capitalize">{{ row.status?.replace('_', ' ') }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-if="!feeSummary.length" title="No fee records" icon="💰" class="py-6" />
        </section>

        <section class="card card--flush overflow-hidden">
            <div class="p-4 border-b border-slate-100">
                <h3 class="section-title !mb-0">Registration preview (latest 50)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Hall ticket</th><th>Student</th><th>School</th><th>Approval</th><th>Attendance</th></tr></thead>
                    <tbody>
                        <tr v-for="(row, i) in registrations.slice(0, 50)" :key="i">
                            <td>{{ row.hall_ticket_no || '—' }}</td>
                            <td>{{ row.student_name }}</td>
                            <td>{{ row.school_name }}</td>
                            <td class="text-xs">{{ row.approval_status }}</td>
                            <td class="text-xs">{{ row.attendance_status || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    exam: Object,
    registrations: { type: Array, default: () => [] },
    feeSummary: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});

const exportBase = computed(() => `/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/reports`);
</script>
