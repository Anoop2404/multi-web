<template>
    <PortalLayout
        role-label="Teacher Portal"
        :title="bank.title"
        :subtitle="`${bank.subject} · ${school.name}`"
        accent="navy"
        :nav-items="navItems"
    >
        <form @submit.prevent="addQuestion" class="card mb-5" enctype="multipart/form-data">
            <h2 class="section-title text-base mb-3">Add Talent Search question</h2>
            <div class="space-y-3">
                <FormField label="Short title (optional)">
                    <input v-model="qForm.title" class="field" placeholder="e.g. Capital of India">
                </FormField>
                <FormField label="Question text">
                    <textarea v-model="qForm.body_text" class="field" rows="3" placeholder="Question text"></textarea>
                </FormField>

                <div class="space-y-2">
                    <p class="form-label">Answer options</p>
                    <div v-for="(opt, idx) in qForm.options" :key="idx" class="flex items-center gap-2">
                        <input v-model="opt.key" class="field field--sm w-14 uppercase" :aria-label="`Option ${idx + 1} key`" maxlength="2">
                        <input v-model="opt.label" class="field field--sm flex-1" :aria-label="`Option ${idx + 1} text`" placeholder="Option text">
                        <label class="text-xs flex items-center gap-1 shrink-0 text-slate-600">
                            <input type="radio" name="correct" :value="opt.key" v-model="qForm.correct_option_key">
                            Correct
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 max-w-xs">
                    <FormField label="Marks">
                        <input v-model.number="qForm.marks" type="number" min="0.01" step="0.01" class="field field--sm">
                    </FormField>
                    <FormField label="Negative mark">
                        <input v-model.number="qForm.negative_mark" type="number" min="0" step="0.01" class="field field--sm">
                    </FormField>
                </div>

                <FormField label="Attach document (optional)">
                    <input type="file" @change="onFile" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="field">
                </FormField>
            </div>
            <FormActions>
                <button class="btn-primary" :disabled="qForm.processing">
                    {{ qForm.processing ? 'Adding…' : 'Add question' }}
                </button>
            </FormActions>
        </form>

        <section class="card-list">
            <div v-for="q in bank.questions" :key="q.id" class="card-list-row items-start justify-between">
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-sm text-slate-900">{{ q.title || `Question #${q.display_order}` }}</p>
                    <p v-if="q.body_text" class="text-sm text-slate-600 mt-1 whitespace-pre-wrap">{{ q.body_text }}</p>
                    <ul v-if="q.options_json?.length" class="mt-2 text-sm space-y-1">
                        <li v-for="opt in q.options_json" :key="opt.key"
                            :class="opt.key === q.correct_option_key ? 'text-emerald-700 font-medium' : 'text-slate-600'">
                            {{ opt.key }}. {{ opt.label }}
                            <span v-if="opt.key === q.correct_option_key" class="text-xs">(correct)</span>
                        </li>
                    </ul>
                    <p class="text-xs text-slate-500 mt-1">
                        Marks {{ Number(q.marks ?? 1) }}
                        <span v-if="Number(q.negative_mark) > 0"> · Negative {{ Number(q.negative_mark) }}</span>
                    </p>
                    <p v-if="q.document_path" class="text-xs text-[#0f3d7a] mt-1">Document attached</p>
                </div>
                <button @click="removeQuestion(q)" class="text-xs font-semibold text-red-600 shrink-0">Remove</button>
            </div>
            <EmptyState v-if="!bank.questions?.length" title="No questions in this bank yet" description="Add questions using the form above." icon="📝" class="!border-0 !shadow-none" />
        </section>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import FormField from '@/Components/ui/FormField.vue';
import FormActions from '@/Components/ui/FormActions.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { useForm, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { teacherPortalNavItems } from '@/support/teacherPortalNav.js';

const props = defineProps({ school: Object, teacher: Object, bank: Object });

const qForm = useForm({
    title: '',
    body_text: '',
    document: null,
    options: [
        { key: 'a', label: '' },
        { key: 'b', label: '' },
        { key: 'c', label: '' },
        { key: 'd', label: '' },
    ],
    correct_option_key: 'a',
    marks: 1,
    negative_mark: 0,
});

const bankLabel = computed(() => props.bank.title?.slice(0, 24) || 'Bank');

const navItems = computed(() => teacherPortalNavItems(props.school.id, {
    bankId: props.bank.id,
    bankLabel: bankLabel.value,
}));

function onFile(e) {
    qForm.document = e.target.files[0] ?? null;
}

function addQuestion() {
    qForm.post(`/portal/teacher/${props.school.id}/question-banks/${props.bank.id}/questions`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            qForm.reset();
            qForm.options = [
                { key: 'a', label: '' },
                { key: 'b', label: '' },
                { key: 'c', label: '' },
                { key: 'd', label: '' },
            ];
            qForm.correct_option_key = 'a';
            qForm.marks = 1;
            qForm.negative_mark = 0;
        },
    });
}

function removeQuestion(q) {
    if (!confirm('Remove this question?')) return;
    router.delete(`/portal/teacher/${props.school.id}/question-banks/${props.bank.id}/questions/${q.id}`, { preserveScroll: true });
}
</script>
