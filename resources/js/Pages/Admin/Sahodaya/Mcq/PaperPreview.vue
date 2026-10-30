<template>
    <SahodayaAdminLayout :title="`Paper preview — ${exam.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="Paper preview"
                    :description="`${paper.total} questions · ${paper.max_score} marks${exam.duration_minutes ? ` · ${exam.duration_minutes} minutes` : ''}. Shown in paper order; with shuffle on, each student sees their own order.`">
            <template #actions>
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input v-model="showKey" type="checkbox"> Show answers
                </label>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/question-banks`" class="btn-secondary text-sm">Back to paper</Link>
            </template>
        </PageHeader>

        <div v-for="(warning, i) in paper.warnings" :key="i" class="card mb-3 !border-amber-200 !py-3 px-4 text-sm text-amber-900">{{ warning }}</div>

        <div v-if="paper.difficulty_counts && paper.total > 0" class="flex flex-wrap items-center gap-2 mb-4 p-3 bg-white border border-slate-200/90 rounded-xl text-xs shadow-xs">
            <span class="font-bold text-slate-700">Difficulty Blueprint:</span>
            <span class="rounded-full bg-emerald-100 text-emerald-800 px-2.5 py-0.5 font-semibold">Easy: {{ paper.difficulty_counts.easy ?? 0 }}</span>
            <span class="rounded-full bg-amber-100 text-amber-800 px-2.5 py-0.5 font-semibold">Medium: {{ paper.difficulty_counts.medium ?? 0 }}</span>
            <span class="rounded-full bg-red-100 text-red-800 px-2.5 py-0.5 font-semibold">Hard: {{ paper.difficulty_counts.hard ?? 0 }}</span>
        </div>

        <EmptyState v-if="!questions.length" title="The paper is empty" description="Add sections on the Paper tab." icon="📄" class="card py-10" />

        <div class="space-y-4">
            <article v-for="q in questions" :key="q.id" class="card space-y-3">
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded-md bg-slate-900 px-2 py-0.5 font-bold text-white">Q{{ q.number }}</span>
                    <span class="rounded-full bg-indigo-50 px-2 py-0.5 font-semibold text-indigo-700">{{ q.type_label }}</span>
                    <span class="text-slate-500">{{ q.marks }} mark{{ q.marks == 1 ? '' : 's' }}<span v-if="q.negative_mark > 0"> · −{{ q.negative_mark }} if wrong</span></span>
                    <span v-if="!q.gradable" class="rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-800">No answer key — not marked</span>
                </div>
                <McqQuestionView :question="q" :show-key="showKey" />
            </article>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqQuestionView from '@/Components/mcq/McqQuestionView.vue';

defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    exam: { type: Object, required: true },
    questions: { type: Array, default: () => [] },
    paper: { type: Object, required: true },
});

const showKey = ref(false);
</script>
