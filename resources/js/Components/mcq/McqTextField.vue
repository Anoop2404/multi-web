<template>
    <div class="space-y-1.5">
        <div class="relative">
            <textarea
                ref="textarea"
                :value="modelValue"
                :rows="rows"
                :placeholder="placeholder"
                class="field"
                :class="[compact ? 'field--sm !pr-10' : '', invalid ? 'field--error' : '']"
                :style="compact ? { minHeight: '2.5rem' } : null"
                @input="$emit('update:modelValue', $event.target.value)"
            />
            <button
                v-if="compact"
                type="button"
                class="absolute right-2 top-2 rounded-md px-1.5 py-0.5 text-xs font-semibold text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                :class="toolsOpen ? 'bg-slate-100 text-slate-800' : ''"
                title="Symbols and formulas"
                @click="toolsOpen = !toolsOpen"
            >Ω</button>
        </div>

        <div v-if="!compact || toolsOpen" class="rounded-lg border border-slate-200 bg-slate-50/70 p-2 space-y-1.5">
            <div class="flex flex-wrap gap-1">
                <button
                    v-for="symbol in MCQ_SYMBOLS"
                    :key="symbol"
                    type="button"
                    class="min-w-[1.75rem] rounded border border-transparent px-1 py-0.5 text-sm text-slate-700 hover:border-slate-300 hover:bg-white"
                    :title="`Insert ${symbol}`"
                    @click="insert(symbol)"
                >{{ symbol }}</button>
            </div>
            <div class="flex flex-wrap items-center gap-1">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 mr-1">Formula</span>
                <button
                    v-for="formula in MCQ_FORMULAS"
                    :key="formula.title"
                    type="button"
                    class="rounded border border-slate-200 bg-white px-2 py-0.5 text-xs text-slate-700 hover:border-slate-400"
                    :title="formula.title"
                    @click="insert(formula.tex)"
                >{{ formula.label }}</button>
            </div>
            <p class="text-[11px] text-slate-500">
                Write formulas between dollar signs, e.g. <code>$\frac{1}{2}$</code> or <code>$x^2 + y^2$</code>.
                Use <code>$$…$$</code> for a formula on its own line.
            </p>
        </div>

        <div v-if="showPreview" class="rounded-lg border border-indigo-100 bg-indigo-50/40 px-3 py-2 text-sm text-slate-800">
            <span class="block text-[11px] font-semibold uppercase tracking-wide text-indigo-500 mb-0.5">Preview</span>
            <McqRichText :text="modelValue" />
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue';
import McqRichText from '@/Components/mcq/McqRichText.vue';
import { hasFormula, MCQ_FORMULAS, MCQ_SYMBOLS } from '@/support/mcqRichText.js';

/** Textarea for question/option text with a symbol + formula toolbar and a live formula preview. */
const props = defineProps({
    modelValue: { type: String, default: '' },
    rows: { type: Number, default: 3 },
    placeholder: { type: String, default: '' },
    compact: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);

const textarea = ref(null);
const toolsOpen = ref(false);
const showPreview = computed(() => hasFormula(props.modelValue));

async function insert(snippet) {
    const el = textarea.value;
    const value = props.modelValue ?? '';
    const start = el ? el.selectionStart : value.length;
    const end = el ? el.selectionEnd : value.length;
    emit('update:modelValue', value.slice(0, start) + snippet + value.slice(end));
    await nextTick();
    if (el) {
        el.focus();
        el.setSelectionRange(start + snippet.length, start + snippet.length);
    }
}
</script>
