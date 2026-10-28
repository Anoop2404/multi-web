<template>
    <SahodayaAdminLayout :title="`Leaderboard — ${exam.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl" :show-header-title="false">
        <PageHeader :title="exam.title"
                    :eyebrow="exam.is_model_exam ? (exam.product_mode_label || 'Sahodaya model exam') : 'Talent Search exam'"
                    description="Top scorers across schools. Publish results first — then ranks show here for staff export and school visibility.">
            <template #actions>
                <a :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/leaderboard/export`" class="btn-secondary text-sm">Export CSV</a>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/results`" class="btn-secondary text-sm">Results &amp; marks</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}`" class="btn-secondary text-sm">← Exam overview</Link>
            </template>
        </PageHeader>

        <McqExamSubNav :sahodaya-id="sahodaya.id" :exam-id="exam.id"
                       :delivery-mode="exam.delivery_mode || 'offline'"
                       :results-published="!!exam.results_published"
                       :is-model-exam="!!exam.is_model_exam"
                       active="leaderboard" />

        <p v-if="exam.results_published" class="text-xs text-slate-500 mb-3">
            Results are published. Review or correct marks on Results if you need to unpublish first.
        </p>

        <div class="card overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr><th>Rank</th><th>Student</th><th>School</th><th>Score</th><th>Grade</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in rows" :key="i">
                        <td>{{ row.rank }}</td>
                        <td>{{ row.student }}</td>
                        <td>{{ row.school }}</td>
                        <td>{{ row.score }}</td>
                        <td>{{ row.grade }}</td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="5" class="p-6 text-center text-slate-400">No ranked results yet.</td></tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>
<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';

defineProps({ sahodaya: Object, publicUrl: String, exam: Object, rows: Array });
</script>
