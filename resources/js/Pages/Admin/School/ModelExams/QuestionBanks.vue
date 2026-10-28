<template>
    <SchoolAdminLayout title="Question banks" :school="school" :show-header-title="false">
        <PageHeader title="Question banks" eyebrow="Model exams"
                    description="Scope banks to a class or class category. Copy Sahodaya Ready banks into your school library, or contribute yours back.">
            <template #actions>
                <Link :href="`/school-admin/${school.id}/model-exams`" class="btn-secondary text-sm">← Model exams</Link>
            </template>
        </PageHeader>

        <McqQuestionBankList :banks="banks" :stats="stats" :filters="filters" :subjects="subjects"
                             :class-groups="classGroups" :class-categories="classCategories"
                             :master-classes="masterClasses" :base-url="baseUrl" />

        <section v-if="associationBanks?.length" class="card mt-6">
            <h3 class="section-title">Association Ready banks</h3>
            <p class="section-desc mb-3">Copy a Sahodaya bank into your private school banks (draft). Edit freely, then mark Ready for your model exam papers.</p>
            <ul class="divide-y divide-slate-100">
                <li v-for="bank in associationBanks" :key="bank.id" class="flex flex-wrap items-center justify-between gap-3 py-3">
                    <div>
                        <p class="font-medium">{{ bank.title }}</p>
                        <p class="text-xs text-slate-500">
                            {{ bank.subject }} · {{ bank.questions_count }} questions · {{ bank.scope_label }}
                            <span v-if="bank.from_school"> · contributed by {{ bank.from_school }}</span>
                        </p>
                    </div>
                    <button type="button" class="btn-secondary text-sm" @click="copyBank(bank)">Copy to my school</button>
                </li>
            </ul>
        </section>
    </SchoolAdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import McqQuestionBankList from '@/Components/mcq/McqQuestionBankList.vue';

const props = defineProps({
    school: Object,
    banks: { type: Array, default: () => [] },
    associationBanks: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    subjects: { type: Array, default: () => [] },
    classGroups: { type: Array, default: () => [] },
    classCategories: { type: Array, default: () => [] },
    masterClasses: { type: Array, default: () => [] },
    baseUrl: { type: String, required: true },
});

function copyBank(bank) {
    router.post(`${props.baseUrl}/${bank.id}/copy-to-school`);
}
</script>
