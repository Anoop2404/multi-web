<template>
    <PortalLayout
        role-label="Teacher Portal"
        :title="bank.title"
        :subtitle="`${bank.subject} · ${school.name}`"
        accent="indigo"
        :nav-items="navItems"
    >
        <form @submit.prevent="addQuestion" class="bg-white border rounded-xl p-4 mb-4 space-y-3" enctype="multipart/form-data">
            <h2 class="font-semibold text-sm">Add MCQ question</h2>
            <input v-model="qForm.title" class="field" placeholder="Short title (optional)" aria-label="Question title">
            <textarea v-model="qForm.body_text" class="field" rows="3" placeholder="Question text" aria-label="Question text"></textarea>

            <div class="space-y-2">
                <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Answer options</p>
                <div v-for="(opt, idx) in qForm.options" :key="idx" class="flex items-center gap-2">
                    <input v-model="opt.key" class="field w-14 uppercase" :aria-label="`Option ${idx + 1} key`" maxlength="2">
                    <input v-model="opt.label" class="field flex-1" :aria-label="`Option ${idx + 1} text`" placeholder="Option text">
                    <label class="text-xs flex items-center gap-1 shrink-0">
                        <input type="radio" name="correct" :value="opt.key" v-model="qForm.correct_option_key">
                        Correct
                    </label>
                </div>
            </div>

            <input type="file" @change="onFile" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="text-sm" aria-label="Attach document">
            <button class="btn-primary" :disabled="qForm.processing">Add question</button>
        </form>

        <ul class="card-list">
            <li v-for="q in bank.questions" :key="q.id" class="p-4">
                <div class="flex justify-between gap-4">
                    <div class="flex-1">
                        <p class="font-medium text-sm">{{ q.title || `Question #${q.display_order}` }}</p>
                        <p v-if="q.body_text" class="text-sm text-gray-600 mt-1 whitespace-pre-wrap">{{ q.body_text }}</p>
                        <ul v-if="q.options_json?.length" class="mt-2 text-sm space-y-1">
                            <li v-for="opt in q.options_json" :key="opt.key"
                                :class="opt.key === q.correct_option_key ? 'text-green-700 font-medium' : 'text-gray-600'">
                                {{ opt.key }}. {{ opt.label }}
                                <span v-if="opt.key === q.correct_option_key" class="text-xs">(correct)</span>
                            </li>
                        </ul>
                        <p v-if="q.document_path" class="text-xs text-indigo-600 mt-1">Document attached</p>
                    </div>
                    <button @click="removeQuestion(q)" class="text-xs text-red-600 shrink-0">Remove</button>
                </div>
            </li>
            <li v-if="!bank.questions?.length" class="p-6 text-center text-gray-400 text-sm">No questions in this bank yet.</li>
        </ul>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { useForm, router } from '@inertiajs/vue3';
import { computed } from 'vue';

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
});

const bankLabel = computed(() => props.bank.title?.slice(0, 24) || 'Bank');

const navItems = computed(() => [
    { href: `/portal/teacher/${props.school.id}`, label: 'Dashboard' },
    { href: `/portal/teacher/${props.school.id}/question-banks`, label: 'Question Banks' },
    { href: `/portal/teacher/${props.school.id}/question-banks/${props.bank.id}`, label: bankLabel.value },
]);

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
        },
    });
}

function removeQuestion(q) {
    if (!confirm('Remove this question?')) return;
    router.delete(`/portal/teacher/${props.school.id}/question-banks/${props.bank.id}/questions/${q.id}`, { preserveScroll: true });
}
</script>
