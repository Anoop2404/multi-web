<template>
    <PortalLayout role-label="Exam Portal" :title="sahodaya.name" :subtitle="bank.title" accent="emerald" :nav-items="navItems">
        <McqQuestionBankWorkspace :bank="bank" :questions="questions" :stats="stats" :type-labels="typeLabels"
                                  :difficulties="difficulties" :subjects="subjects" :class-groups="classGroups"
                                  :class-categories="classCategories" :master-classes="masterClasses" :base-url="baseUrl" />
    </PortalLayout>
</template>

<script setup>
import { computed } from 'vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import McqQuestionBankWorkspace from '@/Components/mcq/McqQuestionBankWorkspace.vue';
import { examPortalNavItems } from '@/support/examPortalNav.js';

const props = defineProps({
    sahodaya: { type: Object, required: true },
    bank: { type: Object, required: true },
    questions: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    typeLabels: { type: Object, required: true },
    difficulties: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
    classGroups: { type: Array, default: () => [] },
    classCategories: { type: Array, default: () => [] },
    masterClasses: { type: Array, default: () => [] },
    baseUrl: { type: String, required: true },
});

const navItems = computed(() => examPortalNavItems(props.sahodaya.id, null, { canManageQuestions: true }));
</script>
