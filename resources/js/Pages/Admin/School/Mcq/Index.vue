<template>
    <SchoolAdminLayout title="MCQ Exams" :school="school" :show-header-title="false">
        <PageHeader title="MCQ exams" eyebrow="Exams & training"
                    description="Level 1 is open registration. Level 2+ only shows students who qualified from the previous level.">
        </PageHeader>

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
                        title="No MCQ exams open"
                        description="When Sahodaya publishes an exam, it will appear here."
                        icon="📝"
                        class="py-12" />
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import ExamCard from '@/Components/school/McqExamCard.vue';

const props = defineProps({
    school: Object,
    seriesGroups: { type: Array, default: () => [] },
    standaloneExams: { type: Array, default: () => [] },
    registrations: Object,
    hubStats: { type: Object, default: () => ({}) },
});

function examRegistrations(examId) {
    return props.registrations?.[examId] ?? [];
}
</script>
