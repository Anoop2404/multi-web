<template>
    <div class="space-y-3">
        <McqRichText v-if="question.body_text" :text="question.body_text" tag="div" class="text-sm text-slate-800" />
        <img v-if="question.image_url" :src="question.image_url" alt="Question image"
             class="max-h-72 max-w-full rounded-lg border border-slate-200 bg-white object-contain">

        <p v-if="hint" class="text-xs font-medium text-slate-500">{{ hint }}</p>

        <ul v-if="question.type !== 'numeric' && question.options?.length" class="grid gap-2" :class="hasOptionImages ? 'sm:grid-cols-2' : ''">
            <li
                v-for="option in question.options"
                :key="option.key"
                class="flex items-start gap-2.5 rounded-lg border px-3 py-2 text-sm"
                :class="showKey && isCorrect(option.key) ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 bg-white'"
            >
                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border text-[11px] font-bold uppercase"
                      :class="showKey && isCorrect(option.key) ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300 text-slate-500'">
                    {{ option.key }}
                </span>
                <div class="min-w-0 space-y-1.5">
                    <McqRichText v-if="option.label" :text="option.label" tag="div" />
                    <img v-if="option.image_url" :src="option.image_url" :alt="`Option ${option.key.toUpperCase()}`"
                         class="max-h-40 max-w-full rounded border border-slate-200 bg-white object-contain">
                </div>
            </li>
        </ul>

        <p v-if="question.type === 'numeric'" class="text-sm text-slate-600">
            <span class="inline-block rounded-md border border-dashed border-slate-300 px-3 py-1.5 text-slate-400">Student types a number</span>
            <span v-if="showKey" class="ml-2 font-semibold text-emerald-700">Answer: {{ question.answer_label }}</span>
        </p>

        <div v-if="showKey && question.explanation" class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
            <span class="font-semibold text-slate-700">Explanation: </span>
            <McqRichText :text="question.explanation" />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import McqRichText from '@/Components/mcq/McqRichText.vue';

/** A question as students see it; `showKey` highlights the correct answer for admins. */
const props = defineProps({
    question: { type: Object, required: true },
    showKey: { type: Boolean, default: false },
});

const hasOptionImages = computed(() => (props.question.options ?? []).some((o) => o.image_url));

const hint = computed(() => {
    if (props.question.type === 'multiple') {
        const count = props.question.correct_keys?.length ?? 0;
        return props.question.show_count && count ? `Choose ${count} answer${count === 1 ? '' : 's'}.` : 'Choose all correct answers.';
    }
    if (props.question.type === 'true_false') return 'True or false?';
    return '';
});

function isCorrect(key) {
    return (props.question.correct_keys ?? []).includes(key);
}
</script>
