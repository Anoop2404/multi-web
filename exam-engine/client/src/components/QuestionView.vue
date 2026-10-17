<template>
    <article class="qbody" :aria-labelledby="headingId">
        <RichText v-if="question.body" :text="question.body" />
        <img v-if="question.image" class="qimage" :src="question.image" :alt="`Picture for question ${question.number}`" @click="emit('zoom', question.image)" />

        <p v-if="hint" class="hint" :id="hintId">{{ hint }}</p>

        <!-- True / false: two big buttons -->
        <div v-if="question.type === 'true_false'" class="tf" role="radiogroup" :aria-labelledby="headingId" :aria-describedby="hintId">
            <label
                v-for="opt in tfOptions"
                :key="opt.key"
                class="option"
                :class="{ selected: value === opt.key, disabled }"
            >
                <input type="radio" :name="groupName" :value="opt.key" :checked="value === opt.key" :disabled="disabled" @change="choose(opt.key)" />
                <span>{{ opt.text }}</span>
            </label>
        </div>

        <!-- Single / multiple -->
        <ul
            v-else-if="question.type !== 'numeric'"
            class="options"
            :role="question.type === 'multiple' ? 'group' : 'radiogroup'"
            :aria-labelledby="headingId"
            :aria-describedby="hint ? hintId : undefined"
        >
            <li v-for="opt in question.options" :key="opt.key">
                <label class="option" :class="{ selected: isSelected(opt.key), multi: question.type === 'multiple', disabled }">
                    <input
                        v-if="question.type === 'multiple'"
                        type="checkbox"
                        :value="opt.key"
                        :checked="isSelected(opt.key)"
                        :disabled="disabled"
                        @change="toggle(opt.key)"
                    />
                    <input v-else type="radio" :name="groupName" :value="opt.key" :checked="isSelected(opt.key)" :disabled="disabled" @change="choose(opt.key)" />
                    <span class="key" aria-hidden="true">{{ opt.key }}</span>
                    <span class="content">
                        <span class="sr-only">Option {{ opt.key.toUpperCase() }}: </span>
                        <RichText v-if="opt.label" :text="opt.label" tag="span" />
                        <img v-if="opt.image" :src="opt.image" :alt="`Option ${opt.key.toUpperCase()} picture`" @click.prevent="emit('zoom', opt.image)" />
                    </span>
                </label>
            </li>
        </ul>

        <!-- Numeric -->
        <div v-else>
            <label :for="inputId" class="hint" style="display: block">Type your answer (a number)</label>
            <div class="numeric">
                <button class="icon-btn" type="button" :disabled="disabled" aria-label="Switch between positive and negative" @click="flipSign">±</button>
                <input
                    :id="inputId"
                    ref="numInput"
                    class="input"
                    type="text"
                    inputmode="decimal"
                    autocomplete="off"
                    spellcheck="false"
                    enterkeyhint="done"
                    :value="numText"
                    :disabled="disabled"
                    placeholder="e.g. 12.5"
                    @input="onNumeric"
                    @blur="commitNumeric"
                />
            </div>
            <p v-if="numText && !numericValid" class="faint" role="status" style="margin-top: 6px">Enter a number such as 42, -3 or 0.75.</p>
        </div>
    </article>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import RichText from './RichText.vue';
import { NUMERIC, sanitiseNumericInput } from '../lib/answers';
import type { DisplayQuestion } from '../lib/paper';
import type { AnswerValue } from '../lib/types';

const props = defineProps<{ question: DisplayQuestion; value: AnswerValue | undefined; disabled: boolean; headingId: string }>();
const emit = defineEmits<{ answer: [value: AnswerValue | null]; toggle: [key: string]; zoom: [src: string] }>();

const groupName = computed(() => `q-${props.question.qid}`);
const hintId = computed(() => `hint-${props.question.qid}`);
const inputId = computed(() => `num-${props.question.qid}`);

const tfOptions = computed(() => {
    const opts = props.question.options;
    const a = opts.find((o) => o.key === 'a');
    const b = opts.find((o) => o.key === 'b');
    return [
        { key: a?.key ?? 'a', text: a?.label?.trim() || 'True' },
        { key: b?.key ?? 'b', text: b?.label?.trim() || 'False' },
    ];
});

const hint = computed(() => {
    const q = props.question;
    if (q.type === 'multiple') {
        return q.show_count ? `Choose ${q.show_count} answer${q.show_count === 1 ? '' : 's'}.` : 'Choose all the correct answers.';
    }
    if (q.type === 'true_false') return 'True or false?';
    if (q.type === 'single') return 'Choose one answer.';
    return '';
});

const selected = computed<string[]>(() => {
    const v = props.value;
    if (v === undefined || v === null) return [];
    return Array.isArray(v) ? v : [v];
});

function isSelected(key: string) {
    return selected.value.includes(key);
}

function choose(key: string) {
    if (props.disabled) return;
    emit('answer', key);
}

// The parent applies the toggle to the live answer (not this render's props), so two quick
// clicks in the same tick can't overwrite each other.
function toggle(key: string) {
    if (props.disabled) return;
    emit('toggle', key);
}

// Numeric: keep what the student typed on screen; store it only when it is a number.
const numText = ref(typeof props.value === 'string' ? props.value : '');
const numInput = ref<HTMLInputElement | null>(null);
const numericValid = computed(() => NUMERIC.test(numText.value.trim()));

watch(
    () => [props.question.qid, props.value] as const,
    ([qid, v], old) => {
        const changedQuestion = !old || old[0] !== qid;
        const incoming = typeof v === 'string' ? v : '';
        // Don't fight the field while typing ("12." stays "12."); do follow external changes.
        if (changedQuestion || (incoming !== numText.value.trim() && !(incoming === '' && !numericValid.value))) numText.value = incoming;
    },
);

function onNumeric(e: Event) {
    const el = e.target as HTMLInputElement;
    const clean = sanitiseNumericInput(el.value);
    if (clean !== el.value) el.value = clean;
    numText.value = clean;
    emit('answer', NUMERIC.test(clean.trim()) ? clean.trim() : null);
}

function commitNumeric() {
    const t = numText.value.trim();
    emit('answer', NUMERIC.test(t) ? t : null);
}

function flipSign() {
    const t = numText.value;
    numText.value = t.startsWith('-') ? t.slice(1) : `-${t}`;
    emit('answer', NUMERIC.test(numText.value) ? numText.value : null);
    numInput.value?.focus();
}
</script>
