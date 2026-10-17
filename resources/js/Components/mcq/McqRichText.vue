<template>
    <component :is="tag" class="mcq-rich-text" v-html="html" />
</template>

<script setup>
import { computed } from 'vue';
import 'katex/dist/katex.min.css';
import { renderMcqText } from '@/support/mcqRichText.js';

/** Shows question/option text with $…$ formulas rendered (see support/mcqRichText.js). */
const props = defineProps({
    text: { type: [String, Number], default: '' },
    tag: { type: String, default: 'span' },
});

const html = computed(() => renderMcqText(props.text));
</script>

<style scoped>
.mcq-rich-text {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}
.mcq-rich-text :deep(.katex-display) {
    margin: 0.5em 0;
    overflow-x: auto;
    overflow-y: hidden;
}
</style>
