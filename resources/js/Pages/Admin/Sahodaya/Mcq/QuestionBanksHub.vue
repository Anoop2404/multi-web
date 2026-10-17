<template>
    <SahodayaAdminLayout title="Question banks" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Question banks" eyebrow="Talent Search exams"
                    description="Write and import questions — text, images and formulas; single correct, multiple correct, true/false and numeric. Exams pick their papers from Ready banks.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams`" class="btn-secondary text-sm">All exams</Link>
            </template>
        </PageHeader>

        <McqQuestionBankList :banks="banks" :stats="stats" :filters="filters" :subjects="subjects"
                             :class-groups="classGroups" :base-url="baseUrl" />

        <p class="mt-4 text-xs text-slate-500">
            Exam controllers can also manage these banks from the exam portal.
        </p>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqQuestionBankList from '@/Components/mcq/McqQuestionBankList.vue';

defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    banks: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    subjects: { type: Array, default: () => [] },
    classGroups: { type: Array, default: () => [] },
    baseUrl: { type: String, required: true },
});
</script>
