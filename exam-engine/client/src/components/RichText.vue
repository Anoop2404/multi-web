<template>
    <component :is="tag" class="rich" v-html="html" />
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { katexReady, renderRichText } from '../lib/richText';

const props = withDefaults(defineProps<{ text: string | null | undefined; tag?: string }>(), { tag: 'div' });

// Re-renders once KaTeX finishes loading (katexReady flips to true).
const html = computed(() => (katexReady.value, renderRichText(props.text)));
</script>
