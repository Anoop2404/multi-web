<template>
    <div class="palette-panel">
        <div class="row">
            <h2 style="font-size: 1rem">Questions</h2>
            <span class="spacer" />
            <button v-if="closable" class="icon-btn" type="button" aria-label="Close question list" @click="emit('close')">✕</button>
        </div>
        <p class="faint" style="margin-top: 4px">{{ answered }} of {{ questions.length }} answered · {{ flaggedCount }} marked</p>
        <div class="legend" style="margin-top: 8px" aria-hidden="true">
            <span><i class="answered" />Answered</span>
            <span><i />Not answered</span>
            <span><i class="flagged" />Marked for review</span>
        </div>

        <template v-for="group in groups" :key="group.key">
            <h3>{{ group.title }}</h3>
            <div class="palette-grid">
                <button
                    v-for="q in group.items"
                    :key="q.qid"
                    type="button"
                    class="pbtn"
                    :class="{ answered: isAnswered(q.qid), flagged: isFlagged(q.qid), current: q.number - 1 === current }"
                    :aria-label="label(q)"
                    :aria-current="q.number - 1 === current ? 'step' : undefined"
                    @click="emit('go', q.number - 1)"
                >
                    {{ q.number }}
                </button>
            </div>
        </template>

        <div style="margin-top: 18px" class="stack">
            <button class="btn primary block" type="button" :disabled="disabled" @click="emit('submit')">Review &amp; submit</button>
            <div class="row" role="group" aria-label="Text size">
                <span class="faint">Text size</span>
                <span class="spacer" />
                <button class="icon-btn" type="button" aria-label="Smaller text" @click="emit('font', -1)">A−</button>
                <button class="icon-btn" type="button" aria-label="Larger text" @click="emit('font', 1)">A+</button>
            </div>
            <button v-if="canFullscreen" class="btn block" type="button" @click="emit('fullscreen')">Full screen</button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { DisplayQuestion } from '../lib/paper';

const props = defineProps<{
    questions: DisplayQuestion[];
    current: number;
    isAnswered: (qid: string) => boolean;
    isFlagged: (qid: string) => boolean;
    answered: number;
    flaggedCount: number;
    closable?: boolean;
    disabled?: boolean;
    canFullscreen?: boolean;
}>();
const emit = defineEmits<{ go: [index: number]; close: []; submit: []; font: [delta: number]; fullscreen: [] }>();

const groups = computed(() => {
    const out: { key: string; title: string; items: DisplayQuestion[] }[] = [];
    for (const q of props.questions) {
        const title = q.section ?? 'All questions';
        const last = out[out.length - 1];
        if (last && last.title === title) last.items.push(q);
        else out.push({ key: `${out.length}-${title}`, title, items: [q] });
    }
    return out;
});

function label(q: DisplayQuestion) {
    const parts = [`Question ${q.number}`, props.isAnswered(q.qid) ? 'answered' : 'not answered'];
    if (props.isFlagged(q.qid)) parts.push('marked for review');
    return parts.join(', ');
}
</script>
