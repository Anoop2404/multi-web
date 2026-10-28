<template>
    <SchoolAdminLayout :title="`Paper — ${exam.title}`" :school="school" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="Model exam paper"
                    description="Build the paper from Ready school question banks. Shuffle and partial-credit defaults suit model exams.">
            <template #actions>
                <Link :href="`/school-admin/${school.id}/model-exams/${exam.id}`" class="btn-secondary text-sm">← Exam</Link>
                <Link :href="banksUrl" class="btn-secondary text-sm">Question banks</Link>
            </template>
        </PageHeader>

        <div v-if="paper.locked" class="card mb-4 !border-amber-200 !bg-amber-50 !py-3 px-4 text-sm text-amber-900">
            Students have started this exam, so the paper is locked.
        </div>
        <div v-for="(warning, i) in paper.warnings" :key="i" class="card mb-3 !border-amber-200 !py-3 px-4 text-sm text-amber-900">
            {{ warning }}
        </div>

        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="card card--muted !py-3 text-center">
                <p class="text-2xl font-bold">{{ paper.total }}</p>
                <p class="text-xs text-slate-500">Questions</p>
            </div>
            <div class="card card--muted !py-3 text-center">
                <p class="text-2xl font-bold">{{ paper.max_score }}</p>
                <p class="text-xs text-slate-500">Total marks</p>
            </div>
        </div>

        <section class="card mb-6">
            <h3 class="section-title">Paper sections</h3>
            <EmptyState v-if="!paper.sections?.length" title="No sections yet" description="Add a Ready question bank below." icon="📚" class="py-6" />
            <div v-else class="divide-y divide-slate-100">
                <div v-for="(section, index) in paper.sections" :key="section.bank_id" class="flex flex-wrap items-start gap-4 py-4">
                    <div class="flex flex-col gap-1 pt-1">
                        <button type="button" class="btn-ghost !px-2 !py-0.5 text-xs" :disabled="paper.locked || index === 0" @click="moveSection(section, 'up')">↑</button>
                        <button type="button" class="btn-ghost !px-2 !py-0.5 text-xs" :disabled="paper.locked || index === paper.sections.length - 1" @click="moveSection(section, 'down')">↓</button>
                    </div>
                    <div class="min-w-[14rem] flex-1 space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-bold text-slate-400">Section {{ index + 1 }}</span>
                            <Link :href="`${banksUrl}/${section.bank_id}`" class="font-semibold link-brand">{{ section.bank_title }}</Link>
                            <span class="text-xs text-slate-500">{{ section.subject }} · {{ section.bank_questions }} in bank</span>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_10rem_6rem]">
                            <input v-model="drafts[section.bank_id].section_title" class="field field--sm" placeholder="Section heading (optional)" :disabled="paper.locked">
                            <select v-model="drafts[section.bank_id].pick_mode" class="field field--sm" :disabled="paper.locked">
                                <option value="all">All questions</option>
                                <option value="first">First N</option>
                                <option value="random">Random N</option>
                            </select>
                            <input v-if="drafts[section.bank_id].pick_mode !== 'all'" v-model.number="drafts[section.bank_id].pick_count"
                                   type="number" min="1" :max="section.bank_questions" class="field field--sm" placeholder="N" :disabled="paper.locked">
                        </div>
                        <p class="text-xs text-slate-500">Takes <strong>{{ section.picked }}</strong> · {{ section.marks }} marks</p>
                    </div>
                    <div v-if="!paper.locked" class="flex gap-2 pt-1">
                        <button type="button" class="btn-secondary text-xs" @click="saveSection(section)">Save</button>
                        <button type="button" class="btn-ghost text-xs !text-red-600" @click="removeSection(section)">Remove</button>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="!paper.locked" class="card mb-6">
            <h3 class="section-title">Add Ready bank</h3>
            <p v-if="exam.eligibility_filter?.assignment_type && exam.eligibility_filter.assignment_type !== 'all'"
               class="section-desc mb-2">
                Showing banks matching this exam’s {{ exam.eligibility_filter.assignment_type }} eligibility, plus unscoped banks.
            </p>
            <EmptyState v-if="!available.length" title="No unused Ready banks"
                        description="Create a bank scoped to this exam’s class/category (or leave unscoped), add questions, mark Ready, then return here." icon="📚" class="py-6" />
            <ul v-else class="divide-y divide-slate-100">
                <li v-for="bank in available" :key="bank.id" class="flex flex-wrap items-center justify-between gap-3 py-3">
                    <div>
                        <p class="font-medium">{{ bank.title }}</p>
                        <p class="text-xs text-slate-500">{{ bank.subject }} · {{ bank.questions_count }} questions · {{ bank.scope_label || 'Any class' }}</p>
                    </div>
                    <button type="button" class="btn-secondary text-sm" @click="attach(bank)">Add to paper</button>
                </li>
            </ul>
        </section>

        <form @submit.prevent="saveSettings" class="card space-y-4">
            <h3 class="section-title">Delivery settings</h3>
            <FormGrid>
                <FormField label="Shuffle questions">
                    <SearchableSelect v-model="settingsForm.shuffle_questions"
                                       :options="[{ value: true, label: 'Yes' }, { value: false, label: 'No' }]"
                                       :all-option="false" />
                </FormField>
                <FormField label="Shuffle options">
                    <SearchableSelect v-model="settingsForm.shuffle_options"
                                       :options="[{ value: true, label: 'Yes' }, { value: false, label: 'No' }]"
                                       :all-option="false" />
                </FormField>
                <FormField label="Multi-correct marking">
                    <SearchableSelect v-model="settingsForm.multi_marking"
                                       :options="[
                                           { value: 'partial', label: 'Split / partial marks' },
                                           { value: 'all_or_nothing', label: 'All or nothing' },
                                       ]"
                                       :all-option="false" />
                </FormField>
            </FormGrid>
            <FormActions>
                <button type="submit" class="btn-primary text-sm" :disabled="paper.locked || settingsForm.processing">Save settings</button>
            </FormActions>
        </form>
    </SchoolAdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';

const props = defineProps({
    school: Object,
    exam: { type: Object, required: true },
    paper: { type: Object, default: () => ({ sections: [], total: 0, max_score: 0, warnings: [], locked: false }) },
    available: { type: Array, default: () => [] },
    settings: { type: Object, default: () => ({}) },
    banksUrl: { type: String, required: true },
});

const drafts = reactive({});
for (const section of props.paper.sections ?? []) {
    drafts[section.bank_id] = {
        section_title: section.section_title ?? '',
        pick_mode: section.pick_mode ?? 'all',
        pick_count: section.pick_count ?? null,
    };
}

const base = `/school-admin/${props.school.id}/model-exams/${props.exam.id}/paper`;

const settingsForm = useForm({
    shuffle_questions: props.settings.shuffle_questions ?? true,
    shuffle_options: props.settings.shuffle_options ?? true,
    multi_marking: props.settings.multi_marking ?? 'partial',
});

function attach(bank) {
    router.post(`${base}/banks`, { bank_id: bank.id }, { preserveScroll: true });
}

function saveSection(section) {
    router.put(`${base}/banks/${section.bank_id}`, drafts[section.bank_id], { preserveScroll: true });
}

function removeSection(section) {
    router.delete(`${base}/banks/${section.bank_id}`, { preserveScroll: true });
}

function moveSection(section, direction) {
    router.post(`${base}/banks/${section.bank_id}/move`, { direction }, { preserveScroll: true });
}

function saveSettings() {
    settingsForm.put(`${base}/settings`, { preserveScroll: true });
}
</script>
