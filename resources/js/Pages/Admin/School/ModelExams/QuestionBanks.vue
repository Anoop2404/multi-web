<template>
    <SchoolAdminLayout title="Question banks" :school="school" :show-header-title="false">
        <PageHeader title="Question banks" eyebrow="Model exams"
                    description="School-scoped banks for model exams. Mark a bank Ready before attaching it to a paper.">
            <template #actions>
                <Link :href="`/school-admin/${school.id}/model-exams`" class="btn-secondary text-sm">← Model exams</Link>
            </template>
        </PageHeader>

        <McqQuestionBankList :banks="banks" :stats="stats" :filters="filters" :subjects="subjects"
                             :class-groups="classGroups" :base-url="baseUrl" />
    </SchoolAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import McqQuestionBankList from '@/Components/mcq/McqQuestionBankList.vue';

defineProps({
    school: Object,
    banks: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    subjects: { type: Array, default: () => [] },
    classGroups: { type: Array, default: () => [] },
    baseUrl: { type: String, required: true },
});
</script>
