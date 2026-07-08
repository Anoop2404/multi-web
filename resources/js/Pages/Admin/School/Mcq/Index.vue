<template>
    <SchoolAdminLayout :title="TALENT_SEARCH_EXAMS_LABEL" :school="school" :show-header-title="false">
        <PageHeader :title="TALENT_SEARCH_EXAMS_LABEL" eyebrow="Exams & training"
                    description="Level 1 is open registration. Level 2+ only shows students who qualified from the previous level.">
        </PageHeader>

        <div v-if="registrationGate?.blocked" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 mb-6">
            <p class="font-semibold">Registration blocked</p>
            <p class="text-xs mt-1">{{ registrationGate.reason }}</p>
            <p v-if="registrationGate.links?.membership" class="text-xs mt-2">
                <Link :href="registrationGate.links.membership" class="link-brand font-semibold">Complete annual registration →</Link>
            </p>
        </div>

        <div v-if="hubStats && Object.keys(hubStats).length" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <div class="card card--muted text-center !py-4">
                <p class="text-2xl font-bold text-emerald-700">{{ hubStats.available ?? 0 }}</p>
                <p class="text-xs text-slate-500 mt-1">Open exams</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-2xl font-bold">{{ hubStats.registered ?? 0 }}</p>
                <p class="text-xs text-slate-500 mt-1">Exams joined</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-2xl font-bold text-indigo-700">{{ hubStats.completed ?? 0 }}</p>
                <p class="text-xs text-slate-500 mt-1">Results published</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-2xl font-bold">{{ hubStats.total_regs ?? 0 }}</p>
                <p class="text-xs text-slate-500 mt-1">Total registrations</p>
            </div>
        </div>

        <div class="space-y-8">
            <section v-for="group in seriesGroups" :key="'series-'+group.id" class="space-y-3">
                <h2 class="text-lg font-semibold text-slate-900">{{ group.title }}</h2>
                <ExamCard v-for="exam in group.exams" :key="exam.id"
                          :exam="exam" :school="school"
                          :registrations="examRegistrations(exam.id)" />
            </section>

            <section v-if="standaloneExams?.length" class="space-y-3">
                <h2 v-if="seriesGroups?.length" class="text-lg font-semibold text-slate-900">Other exams</h2>
                <ExamCard v-for="exam in standaloneExams" :key="exam.id"
                          :exam="exam" :school="school"
                          :registrations="examRegistrations(exam.id)" />
            </section>

            <EmptyState v-if="!seriesGroups?.length && !standaloneExams?.length"
                        title="No Talent Search exams open"
                        description="When Sahodaya publishes an exam, it will appear here."
                        icon="📝"
                        class="py-12" />
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import ExamCard from '@/Components/school/McqExamCard.vue';
import { TALENT_SEARCH_EXAMS_LABEL } from '@/support/mcqSchoolLabels.js';

const props = defineProps({
    school: Object,
    seriesGroups: { type: Array, default: () => [] },
    standaloneExams: { type: Array, default: () => [] },
    registrations: Object,
    hubStats: { type: Object, default: () => ({}) },
    registrationGate: { type: Object, default: () => ({ blocked: false }) },
});

function examRegistrations(examId) {
    return props.registrations?.[examId] ?? [];
}
</script>
