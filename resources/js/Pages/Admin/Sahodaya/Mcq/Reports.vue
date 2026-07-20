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
                <a :href="exportBase + '/malpractice/export'" class="btn-secondary text-sm mt-2 inline-block">Malpractice register ↓</a>
            </div>
            <div class="card">
                <h3 class="section-title">Marks & results</h3>
                <p class="section-desc">Present students without marks, toppers, and grade bands.</p>
                <a :href="exportBase + '/marks-pending/export'" class="btn-secondary text-sm mt-3 inline-block">Marks pending ↓</a>
                <a v-if="exam.results_published" :href="exportBase + '/toppers/export'" class="btn-secondary text-sm mt-2 inline-block">Toppers ↓</a>
                <a v-if="exam.results_published" :href="exportBase + '/result-analysis/export'" class="btn-secondary text-sm mt-2 inline-block">Result analysis ↓</a>
                <a v-if="exam.results_published" :href="exportBase + '/school-performance/export'" class="btn-secondary text-sm mt-2 inline-block">School performance ↓</a>
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

        <section v-if="resultAnalysis" class="card mb-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <div>
                    <h3 class="section-title !mb-0">Result analysis</h3>
                    <p class="section-desc">Pass/fail, mean score, percentiles, and grade histogram.</p>
                </div>
                <a :href="exportBase + '/result-analysis/export'" class="btn-secondary text-sm">Export ↓</a>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 text-center">
                <div class="rounded-lg bg-slate-50 p-3"><p class="text-lg font-bold">{{ resultAnalysis.examined }}</p><p class="text-[10px] uppercase text-slate-500">Examined</p></div>
                <div class="rounded-lg bg-slate-50 p-3"><p class="text-lg font-bold text-emerald-700">{{ resultAnalysis.pass_rate ?? '—' }}%</p><p class="text-[10px] uppercase text-slate-500">Pass rate</p></div>
                <div class="rounded-lg bg-slate-50 p-3"><p class="text-lg font-bold">{{ resultAnalysis.mean_score ?? '—' }}</p><p class="text-[10px] uppercase text-slate-500">Mean</p></div>
                <div class="rounded-lg bg-slate-50 p-3"><p class="text-lg font-bold">{{ resultAnalysis.median_score ?? '—' }}</p><p class="text-[10px] uppercase text-slate-500">Median</p></div>
                <div class="rounded-lg bg-slate-50 p-3"><p class="text-lg font-bold text-red-700">{{ resultAnalysis.fail_rate ?? '—' }}%</p><p class="text-[10px] uppercase text-slate-500">Fail rate</p></div>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Grade</th><th>Count</th></tr></thead>
                    <tbody>
                        <tr v-for="(count, grade) in resultAnalysis.grade_histogram" :key="grade">
                            <td>{{ grade }}</td><td>{{ count }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="schoolPerformance?.length" class="card card--flush overflow-hidden mb-6">
            <div class="p-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                <h3 class="section-title !mb-0">School-wise performance</h3>
                <a :href="exportBase + '/school-performance/export'" class="btn-secondary text-sm">Export ↓</a>
            </div>
            <table class="data-table">
                <thead><tr><th>Sl No</th><th>School</th><th>Registered</th><th>Examined</th><th>Avg</th><th>Pass %</th><th>Top 10</th></tr></thead>
                <tbody>
                    <tr v-for="(row, i) in schoolPerformance" :key="i">
                        <td>{{ i + 1 }}</td>
                        <td>{{ (row.school_name || '').toUpperCase() }}</td>
                        <td>{{ row.registered }}</td>
                        <td>{{ row.examined }}</td>
                        <td>{{ row.avg_score ?? '—' }}</td>
                        <td>{{ row.pass_rate ?? '—' }}</td>
                        <td>{{ row.top_10 }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="card card--flush overflow-hidden mb-6">
            <div class="p-4 border-b border-slate-100">
                <h3 class="section-title !mb-0">Fee summary preview</h3>
            </div>
            <table class="data-table">
                <thead><tr><th>Sl No</th><th>School</th><th>Students</th><th>Due</th><th>Status</th></tr></thead>
                <tbody>
                    <tr v-for="(row, i) in feeSummary" :key="i">
                        <td>{{ i + 1 }}</td>
                        <td>{{ (row.school_name || '').toUpperCase() }}</td>
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
                    <thead><tr><th>Sl No</th><th>Hall ticket</th><th>Student</th><th>School</th><th>Approval</th><th>Attendance</th></tr></thead>
                    <tbody>
                        <tr v-for="(row, i) in registrations.slice(0, 50)" :key="i">
                            <td>{{ i + 1 }}</td>
                            <td>{{ row.hall_ticket_no || '—' }}</td>
                            <td>{{ row.student_name }}</td>
                            <td>{{ (row.school_name || '').toUpperCase() }}</td>
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
    resultAnalysis: { type: Object, default: null },
    schoolPerformance: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});

const exportBase = computed(() => `/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/reports`);
</script>
