<template>
    <SahodayaAdminLayout :title="bank.title" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <McqQuestionBankWorkspace :bank="bank" :questions="questions" :stats="stats" :type-labels="typeLabels"
                                  :difficulties="difficulties" :subjects="subjects" :class-groups="classGroups" :base-url="baseUrl" />
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqQuestionBankWorkspace from '@/Components/mcq/McqQuestionBankWorkspace.vue';

defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
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
