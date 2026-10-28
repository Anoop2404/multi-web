<template>
    <SchoolAdminLayout :title="bank.title" :school="school" :show-header-title="false">
        <McqQuestionBankWorkspace :bank="bank" :questions="questions" :stats="stats" :type-labels="typeLabels"
                                  :difficulties="difficulties" :subjects="subjects" :class-groups="classGroups" :base-url="baseUrl" />
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import McqQuestionBankWorkspace from '@/Components/mcq/McqQuestionBankWorkspace.vue';

defineProps({
    school: Object,
    bank: { type: Object, required: true },
    questions: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    typeLabels: { type: Object, required: true },
    difficulties: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
    classGroups: { type: Array, default: () => [] },
    baseUrl: { type: String, required: true },
});
</script>
