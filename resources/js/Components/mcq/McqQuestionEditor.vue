<template>
    <form class="space-y-5" @submit.prevent="submit">
        <!-- Type -->
        <div>
            <p class="form-label mb-2">Question type</p>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <button
                    v-for="(label, value) in typeLabels"
                    :key="value"
                    type="button"
                    class="rounded-lg border px-3 py-2 text-left text-sm transition"
                    :class="form.type === value ? 'border-indigo-500 bg-indigo-50 font-semibold text-indigo-900' : 'border-slate-200 hover:border-slate-300'"
                    @click="setType(value)"
                >
                    {{ label }}
                    <span class="block text-[11px] font-normal text-slate-500">{{ typeHints[value] }}</span>
                </button>
            </div>
        </div>

        <!-- Question -->
        <div class="space-y-2">
            <label class="form-label">{{ form.type === 'true_false' ? 'Statement' : 'Question' }}</label>
            <McqTextField v-model="form.body_text" :rows="4" :invalid="!!form.errors.body_text"
                          placeholder="Type the question. Formulas go between $ signs, e.g. $\sqrt{16}$" />
            <InputError :message="form.errors.body_text" />
            <McqImagePicker v-model="form.image" v-model:removed="form.remove_image"
                            :existing-url="question?.image_url ?? null" label="Add an image to the question" />
            <InputError :message="form.errors.image" />
        </div>

        <!-- Options: single / multiple -->
        <div v-if="hasOptions" class="space-y-2">
            <div class="flex items-center justify-between">
                <p class="form-label">Options</p>
                <p class="text-xs text-slate-500">
                    {{ form.type === 'multiple' ? 'Tick every correct option.' : 'Select the one correct option.' }}
                </p>
            </div>
            <div
                v-for="(option, index) in form.options"
                :key="option.uid"
                class="rounded-xl border p-3 space-y-2"
                :class="isCorrect(option.key) ? 'border-emerald-300 bg-emerald-50/40' : 'border-slate-200'"
            >
                <div class="flex items-start gap-3">
                    <label class="mt-2 flex shrink-0 cursor-pointer items-center gap-1.5 text-xs font-semibold"
                           :class="isCorrect(option.key) ? 'text-emerald-700' : 'text-slate-500'">
                        <input
                            :type="form.type === 'multiple' ? 'checkbox' : 'radio'"
                            :name="`correct-${uid}`"
                            :aria-label="`Option ${letter(index)} is correct`"
                            :checked="isCorrect(option.key)"
                            @change="toggleCorrect(option.key)"
                        >
                        {{ letter(index) }}
                    </label>
                    <div class="min-w-0 flex-1">
                        <McqTextField v-model="option.label" :rows="1" compact :placeholder="`Option ${letter(index)} — text, formula, or leave empty for an image-only option`" />
                    </div>
                    <button v-if="form.options.length > 2" type="button" class="mt-2 text-xs text-slate-400 hover:text-red-600"
                            :title="`Remove option ${letter(index)}`" @click="removeOption(index)">✕</button>
                </div>
                <div class="pl-9">
                    <McqImagePicker v-model="option.file" v-model:removed="option.removed" :existing-url="option.existing_url" label="Add image" />
                </div>
            </div>
            <button v-if="form.options.length < 6" type="button" class="text-sm font-semibold text-indigo-700 hover:underline" @click="addOption">
                + Add option
            </button>
            <InputError :message="form.errors.options || form.errors.correct_keys" />
            <label v-if="form.type === 'multiple'" class="flex items-center gap-2 text-sm text-slate-700">
                <input v-model="form.show_count" type="checkbox">
                Tell students how many to choose (e.g. “Choose 2 answers”)
            </label>
        </div>

        <!-- True / False -->
        <div v-if="form.type === 'true_false'" class="space-y-2">
            <p class="form-label">Correct answer</p>
            <div class="flex gap-3">
                <label v-for="choice in trueFalse" :key="choice.key"
                       class="flex cursor-pointer items-center gap-2 rounded-lg border px-4 py-2 text-sm font-semibold"
                       :class="form.correct_keys[0] === choice.key ? 'border-emerald-400 bg-emerald-50 text-emerald-800' : 'border-slate-200 text-slate-600'">
                    <input type="radio" :name="`tf-${uid}`" :checked="form.correct_keys[0] === choice.key" @change="form.correct_keys = [choice.key]">
                    {{ choice.label }}
                </label>
            </div>
            <InputError :message="form.errors.correct_keys" />
        </div>

        <!-- Numeric -->
        <div v-if="form.type === 'numeric'" class="grid gap-3 sm:grid-cols-2">
            <div>
                <label class="form-label">Correct answer</label>
                <input v-model="form.answer_value" type="number" step="any" class="field" :class="form.errors.answer_value ? 'field--error' : ''" placeholder="e.g. 12.5">
                <InputError :message="form.errors.answer_value" />
            </div>
            <div>
                <label class="form-label">Accept within ±</label>
                <input v-model="form.answer_tolerance" type="number" step="any" min="0" class="field" placeholder="0 = exact">
                <InputError :message="form.errors.answer_tolerance" />
            </div>
        </div>

        <!-- Marking -->
        <div class="grid gap-3 sm:grid-cols-3">
            <div>
                <label class="form-label">Marks</label>
                <input v-model="form.marks" type="number" step="0.01" min="0.01" class="field">
                <InputError :message="form.errors.marks" />
            </div>
            <div>
                <label class="form-label">Minus for a wrong answer</label>
                <input v-model="form.negative_mark" type="number" step="0.01" min="0" class="field">
                <InputError :message="form.errors.negative_mark" />
            </div>
            <div>
                <label class="form-label">Difficulty</label>
                <select v-model="form.difficulty" class="field">
                    <option :value="null">—</option>
                    <option v-for="d in difficulties" :key="d" :value="d">{{ d.charAt(0).toUpperCase() + d.slice(1) }}</option>
                </select>
            </div>
        </div>

        <div class="space-y-2">
            <label class="form-label">Explanation <span class="font-normal text-slate-400">(optional, shown to staff)</span></label>
            <McqTextField v-model="form.explanation" :rows="2" compact placeholder="Why this answer is correct" />
        </div>

        <!-- Preview -->
        <div class="rounded-xl border border-dashed border-slate-300 p-4">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Preview — as students see it (correct answer highlighted)</p>
            <McqQuestionView :question="previewQuestion" show-key />
        </div>

        <div class="flex justify-end gap-2">
            <button type="button" class="btn-secondary" @click="$emit('close')">Cancel</button>
            <button type="submit" class="btn-primary" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : (question ? 'Save question' : 'Add question') }}
            </button>
        </div>
    </form>
</template>

<script setup>
import { computed, onBeforeUnmount, reactive, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import McqTextField from '@/Components/mcq/McqTextField.vue';
import McqImagePicker from '@/Components/mcq/McqImagePicker.vue';
import McqQuestionView from '@/Components/mcq/McqQuestionView.vue';

const props = defineProps({
    baseUrl: { type: String, required: true },
    bankId: { type: Number, required: true },
    /** null for a new question; otherwise a question row from the bank page (has `form`, `image_url`, `options`). */
    question: { type: Object, default: null },
    typeLabels: { type: Object, required: true },
    difficulties: { type: Array, default: () => [] },
});
const emit = defineEmits(['close']);

const KEYS = ['a', 'b', 'c', 'd', 'e', 'f'];
const uid = Math.random().toString(36).slice(2);
let nextUid = 0;
const trueFalse = [{ key: 'a', label: 'True' }, { key: 'b', label: 'False' }];
const typeHints = {
    single: 'One right option',
    multiple: 'Two or more right options',
    true_false: 'True or false statement',
    numeric: 'Student types a number',
};

function optionRow(key, label = '', existingUrl = null) {
    return { uid: nextUid++, key, label, existing_url: existingUrl, removed: false, file: null };
}

const source = props.question?.form ?? null;
const initialOptions = source && source.type !== 'numeric' && source.type !== 'true_false' && source.options?.length
    ? source.options.map((o) => optionRow(o.key, o.label, props.question.options?.find((p) => p.key === o.key)?.image_url ?? null))
    : KEYS.slice(0, 4).map((key) => optionRow(key));

const form = useForm({
    type: source?.type ?? 'single',
    title: source?.title ?? '',
    body_text: source?.body_text ?? '',
    options: initialOptions,
    correct_keys: [...(source?.correct_keys ?? [])],
    show_count: source?.show_count ?? true,
    answer_value: source?.answer_value ?? '',
    answer_tolerance: source?.answer_tolerance ?? '',
    marks: source?.marks ?? 1,
    negative_mark: source?.negative_mark ?? 0,
    explanation: source?.explanation ?? '',
    difficulty: source?.difficulty ?? null,
    image: null,
    remove_image: false,
});

const hasOptions = computed(() => form.type === 'single' || form.type === 'multiple');

function letter(index) {
    return KEYS[index].toUpperCase();
}

function isCorrect(key) {
    return form.correct_keys.includes(key);
}

function toggleCorrect(key) {
    if (form.type === 'multiple') {
        form.correct_keys = isCorrect(key) ? form.correct_keys.filter((k) => k !== key) : [...form.correct_keys, key];
    } else {
        form.correct_keys = [key];
    }
}

function setType(type) {
    const previous = form.type;
    form.type = type;
    if (type === 'true_false') {
        form.correct_keys = previous === 'true_false' ? form.correct_keys : [];
    } else if (type === 'single') {
        form.correct_keys = previous === 'true_false' ? [] : form.correct_keys.slice(0, 1);
    } else if (type === 'multiple' && previous === 'true_false') {
        form.correct_keys = [];
    }
    if ((type === 'single' || type === 'multiple') && form.options.length < 2) {
        form.options = KEYS.slice(0, 4).map((key) => optionRow(key));
    }
}

function addOption() {
    const used = form.options.map((o) => o.key);
    const key = KEYS.find((k) => !used.includes(k));
    if (key) form.options.push(optionRow(key));
}

function removeOption(index) {
    const [removed] = form.options.splice(index, 1);
    form.correct_keys = form.correct_keys.filter((k) => k !== removed.key);
}

// Object URLs for the live preview of newly picked images.
const previewUrls = reactive(new Map());
function urlFor(file) {
    if (!file) return null;
    if (!previewUrls.has(file)) previewUrls.set(file, URL.createObjectURL(file));
    return previewUrls.get(file);
}
onBeforeUnmount(() => previewUrls.forEach((url) => URL.revokeObjectURL(url)));

const previewQuestion = computed(() => {
    const options = form.type === 'true_false'
        ? trueFalse.map((o) => ({ key: o.key, label: o.label, image_url: null }))
        : form.options
            .map((o) => ({ key: o.key, label: o.label, image_url: urlFor(o.file) || (o.removed ? null : o.existing_url) }))
            .filter((o) => (o.label ?? '').trim() !== '' || o.image_url);
    // Show options under their final letters (blank options are dropped on save).
    const lettered = options.map((o, i) => ({ ...o, key: KEYS[i], sourceKey: o.key }));
    const correct = lettered.filter((o) => form.correct_keys.includes(o.sourceKey)).map((o) => o.key);
    const tolerance = Number(form.answer_tolerance || 0);

    return {
        type: form.type,
        body_text: form.body_text,
        image_url: urlFor(form.image) || (form.remove_image ? null : props.question?.image_url ?? null),
        options: lettered,
        correct_keys: correct,
        show_count: form.show_count,
        answer_label: form.answer_value === '' || form.answer_value === null
            ? '—'
            : `${form.answer_value}${tolerance > 0 ? ` (±${tolerance})` : ''}`,
        explanation: form.explanation,
    };
});

watch(() => form.type, () => form.clearErrors());

function submit() {
    const url = props.question
        ? `${props.baseUrl}/${props.bankId}/questions/${props.question.id}`
        : `${props.baseUrl}/${props.bankId}/questions`;

    form
        .transform((data) => {
            const payload = {
                ...data,
                options: data.options.map((o) => ({
                    key: o.key,
                    label: o.label ?? '',
                    keep_image: !!o.existing_url && !o.removed && !o.file,
                })),
                option_images: Object.fromEntries(data.options.filter((o) => o.file).map((o) => [o.key, o.file])),
            };
            if (props.question) payload._method = 'put';
            return payload;
        })
        .post(url, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
}
</script>
