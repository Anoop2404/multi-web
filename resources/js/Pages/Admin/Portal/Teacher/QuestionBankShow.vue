<template>
    <PortalLayout
        role-label="Teacher Portal"
        :title="bank.title"
        :subtitle="`${bank.subject} · ${school.name}`"
        accent="navy"
        :nav-items="navItems"
        :avatar-url="teacher?.photo_url"
        show-avatar-placeholder
    >
        <!-- Add Question Form -->
        <form @submit.prevent="addQuestion" class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm mb-6 space-y-4" enctype="multipart/form-data">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h2 class="section-title text-base font-bold text-slate-900 flex items-center gap-2">
                    ➕ Add Question to Bank
                </h2>
            </div>
            <div class="space-y-4">
                <FormField label="Short Title (optional)">
                    <input v-model="qForm.title" class="field" placeholder="e.g. Question on Photosynthesis">
                </FormField>
                <FormField label="Question Body / Text">
                    <textarea v-model="qForm.body_text" class="field text-xs" rows="3" placeholder="Enter complete question stem or prompt"></textarea>
                </FormField>

                <div class="space-y-2 bg-slate-50/80 p-4 rounded-2xl border border-slate-100">
                    <p class="form-label font-bold text-slate-800">Multiple Choice Answer Options</p>
                    <div v-for="(opt, idx) in qForm.options" :key="idx" class="flex items-center gap-2">
                        <input v-model="opt.key" class="field field--sm w-16 uppercase font-bold text-center" :aria-label="`Option ${idx + 1} key`" maxlength="2">
                        <input v-model="opt.label" class="field field--sm flex-1" :aria-label="`Option ${idx + 1} text`" placeholder="Option text">
                        <label class="text-xs font-bold flex items-center gap-1.5 shrink-0 text-slate-700 bg-white px-3 py-1.5 rounded-xl border border-slate-200 cursor-pointer">
                            <input type="radio" name="correct" :value="opt.key" v-model="qForm.correct_option_key">
                            Correct
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 max-w-xs">
                    <FormField label="Marks">
                        <input v-model.number="qForm.marks" type="number" min="0.01" step="0.01" class="field field--sm">
                    </FormField>
                    <FormField label="Negative Mark">
                        <input v-model.number="qForm.negative_mark" type="number" min="0" step="0.01" class="field field--sm">
                    </FormField>
                </div>

                <FormField label="Attach Diagram / Document (optional)">
                    <input type="file" @change="onFile" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="field">
                </FormField>
            </div>
            <FormActions>
                <button class="btn-primary text-xs !min-h-0 !py-2.5 px-5 shadow-sm" :disabled="qForm.processing">
                    {{ qForm.processing ? 'Adding…' : 'Add Question to Bank' }}
                </button>
            </FormActions>
        </form>

        <!-- Question List -->
        <section class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h2 class="section-title text-base font-bold text-slate-900 flex items-center gap-2">
                    📋 Questions in Bank
                </h2>
                <span class="text-xs font-semibold text-slate-500">Total: {{ bank.questions?.length ?? 0 }}</span>
            </div>

            <div v-if="bank.questions?.length" class="space-y-3">
                <div v-for="q in bank.questions" :key="q.id" class="p-4 rounded-2xl border border-slate-200/90 bg-white shadow-sm flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="bg-blue-50 text-[#0f3d7a] font-bold text-xs px-2.5 py-0.5 rounded-full border border-blue-200">
                                #{{ q.display_order }}
                            </span>
                            <h3 class="font-bold text-sm text-slate-900">{{ q.title || `Question #${q.display_order}` }}</h3>
                        </div>
                        <p v-if="q.body_text" class="text-xs text-slate-700 mt-2 whitespace-pre-wrap leading-relaxed">{{ q.body_text }}</p>

                        <ul v-if="q.options_json?.length" class="mt-3 text-xs space-y-1.5 bg-slate-50 p-3 rounded-xl border border-slate-100">
                            <li v-for="opt in q.options_json" :key="opt.key"
                                class="flex items-center gap-2"
                                :class="opt.key === q.correct_option_key ? 'text-emerald-800 font-bold bg-emerald-50 px-2 py-1 rounded-lg border border-emerald-200' : 'text-slate-700'">
                                <span class="uppercase font-mono font-bold">{{ opt.key }}.</span>
                                <span>{{ opt.label }}</span>
                                <span v-if="opt.key === q.correct_option_key" class="text-[10px] uppercase tracking-wide bg-emerald-600 text-white px-1.5 py-0.5 rounded ml-auto">Correct</span>
                            </li>
                        </ul>

                        <div class="flex items-center gap-3 text-xs text-slate-500 mt-3 pt-2 border-t border-slate-100">
                            <span class="font-semibold text-slate-700">Marks: {{ Number(q.marks ?? 1) }}</span>
                            <span v-if="Number(q.negative_mark) > 0" class="text-amber-800 font-semibold">· Negative: {{ Number(q.negative_mark) }}</span>
                            <span v-if="q.document_path" class="text-[#0f3d7a] font-bold flex items-center gap-1">📎 Attachment</span>
                        </div>
                    </div>

                    <button @click="removeQuestion(q)" type="button" class="text-xs font-bold text-red-600 hover:text-red-700 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-xl transition shrink-0">
                        Remove
                    </button>
                </div>
            </div>
            <EmptyState v-else title="No questions in this bank yet" description="Add questions using the form above." icon="📝" />
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
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({ school: Object, teacher: Object, bank: Object });

const { confirm } = useConfirm();

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

async function removeQuestion(q) {
    if (!(await confirm({ message: 'Remove this question?' }))) return;
    router.delete(`/portal/teacher/${props.school.id}/question-banks/${props.bank.id}/questions/${q.id}`, { preserveScroll: true });
}
</script>

