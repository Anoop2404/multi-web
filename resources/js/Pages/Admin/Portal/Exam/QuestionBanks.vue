<template>
    <PortalLayout role-label="Exam Portal" :title="sahodaya.name" subtitle="Question banks" accent="emerald" :nav-items="navItems">
        <PageHeader title="Question banks" eyebrow="Talent Search exams"
                    description="Write and import questions — text, images and formulas; single correct, multiple correct, true/false and numeric." />
        <McqQuestionBankList :banks="banks" :stats="stats" :filters="filters" :subjects="subjects"
                             :class-groups="classGroups" :class-categories="classCategories"
                             :master-classes="masterClasses" :base-url="baseUrl" />
    </PortalLayout>
</template>

<script setup>
import { computed } from 'vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import McqQuestionBankList from '@/Components/mcq/McqQuestionBankList.vue';
import { examPortalNavItems } from '@/support/examPortalNav.js';

const props = defineProps({
    sahodaya: { type: Object, required: true },
    banks: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    subjects: { type: Array, default: () => [] },
    classGroups: { type: Array, default: () => [] },
    classCategories: { type: Array, default: () => [] },
    masterClasses: { type: Array, default: () => [] },
    baseUrl: { type: String, required: true },
});

const navItems = computed(() => examPortalNavItems(props.sahodaya.id, null, { canManageQuestions: true }));
</script>
