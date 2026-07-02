<template>
    <SahodayaAdminLayout title="MCQ dashboard" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="MCQ dashboard" eyebrow="MCQ exams"
                    description="Overview of exams, registrations, and multi-level series.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams`" class="btn-primary text-sm">Manage all exams</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-series`" class="btn-secondary text-sm">Exam series</Link>
            </template>
        </PageHeader>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold">{{ stats.exams }}</p>
                <p class="text-xs text-slate-500 mt-1">Total exams</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold text-emerald-700">{{ stats.active }}</p>
                <p class="text-xs text-slate-500 mt-1">Open / scheduled</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold">{{ stats.registrations }}</p>
                <p class="text-xs text-slate-500 mt-1">Registrations</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold text-indigo-700">{{ stats.published }}</p>
                <p class="text-xs text-slate-500 mt-1">Results published</p>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-4 mb-6">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams`" class="card hover:border-indigo-200 transition group">
                <p class="text-sm font-semibold text-slate-900 group-hover:text-indigo-700">All exams</p>
                <p class="text-xs text-slate-500 mt-1">Create standalone Level 1 exams, set fees, publish, and open each exam workspace.</p>
            </Link>
            <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-series`" class="card hover:border-indigo-200 transition group">
                <p class="text-sm font-semibold text-slate-900 group-hover:text-indigo-700">Exam series</p>
                <p class="text-xs text-slate-500 mt-1">{{ seriesCount }} series · Level 1 open + Level 2 cutoff promotion.</p>
            </Link>
            <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq/payments`" class="card hover:border-indigo-200 transition group">
                <p class="text-sm font-semibold text-slate-900 group-hover:text-indigo-700">Payments queue</p>
                <p class="text-xs text-slate-500 mt-1">Approve school batch MCQ fees from one inbox.</p>
            </Link>
            <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq/question-banks`" class="card hover:border-indigo-200 transition group">
                <p class="text-sm font-semibold text-slate-900 group-hover:text-indigo-700">Question banks</p>
                <p class="text-xs text-slate-500 mt-1">Browse teacher-created banks across member schools.</p>
            </Link>
        </div>

        <section class="card overflow-hidden !p-0">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between gap-2">
                <h2 class="section-title !mb-0">Recent exams</h2>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams`" class="link-brand text-xs font-semibold">View all →</Link>
            </div>
            <EmptyState v-if="!recentExams.length" title="No exams yet" description="Create your first exam from All exams." icon="📝" class="py-8" />
            <table v-else class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Level</th>
                        <th>Schedule</th>
                        <th>Fee</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="exam in recentExams" :key="exam.id">
                        <td class="font-medium">{{ exam.title }}</td>
                        <td class="text-xs text-indigo-700 font-semibold">{{ exam.level_label }}</td>
                        <td class="text-xs whitespace-nowrap">{{ exam.scheduled_at_label || '—' }}</td>
                        <td class="text-xs font-semibold" :class="exam.has_fee ? 'text-emerald-700' : 'text-amber-700'">{{ exam.fee_label }}</td>
                        <td><span class="status-pill capitalize text-[10px]">{{ exam.status_label || exam.status }}</span></td>
                        <td class="text-right">
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}`" class="link-brand text-xs font-semibold">Open →</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    stats: Object,
    recentExams: { type: Array, default: () => [] },
    seriesCount: { type: Number, default: 0 },
});
</script>
