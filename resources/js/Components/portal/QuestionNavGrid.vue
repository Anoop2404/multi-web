<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between gap-2 pb-1 border-b border-slate-100">
            <h2 class="font-extrabold text-xs uppercase tracking-wider text-slate-800">Question Palette</h2>
            <span class="text-xs font-bold text-slate-600 tabular-nums bg-slate-100 px-2 py-0.5 rounded-full">
                {{ answeredCount }}/{{ questions.length }} done
            </span>
        </div>

        <!-- Filter Chips -->
        <div v-if="navStatus" class="flex flex-wrap gap-1 text-[11px] font-semibold" role="tablist" aria-label="Question status filters">
            <button
                type="button"
                role="tab"
                :aria-selected="activeFilter === 'all'"
                class="px-2 py-0.5 rounded-md transition cursor-pointer"
                :class="activeFilter === 'all' ? 'bg-slate-900 text-white font-bold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                @click="activeFilter = 'all'"
            >
                All ({{ questions.length }})
            </button>
            <button
                v-if="reviewCount > 0"
                type="button"
                role="tab"
                :aria-selected="activeFilter === 'review'"
                class="px-2 py-0.5 rounded-md transition cursor-pointer"
                :class="activeFilter === 'review' ? 'bg-amber-400 text-amber-950 font-bold' : 'bg-amber-50 text-amber-800 hover:bg-amber-100'"
                @click="activeFilter = 'review'"
            >
                Review ({{ reviewCount }})
            </button>
            <button
                type="button"
                role="tab"
                :aria-selected="activeFilter === 'unanswered'"
                class="px-2 py-0.5 rounded-md transition cursor-pointer"
                :class="activeFilter === 'unanswered' ? 'bg-slate-700 text-white font-bold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                @click="activeFilter = 'unanswered'"
            >
                Unanswered ({{ questions.length - answeredCount }})
            </button>
        </div>

        <div class="grid grid-cols-5 lg:grid-cols-4 gap-1.5" role="list">
            <button
                v-for="q in displayQuestions"
                :key="`nav-${q.id}`"
                type="button"
                role="listitem"
                class="h-10 min-w-[2.25rem] rounded-xl text-xs font-bold border transition flex items-center justify-center relative cursor-pointer"
                :class="[
                    navClass(q),
                    isCurrent(q) ? 'ring-2 ring-offset-2 ring-[#0f3d7a] !border-[#0f3d7a] shadow-sm font-extrabold scale-105 z-10' : '',
                ]"
                :aria-current="isCurrent(q) ? 'true' : undefined"
                :aria-label="labelFor(q)"
                @click="$emit('select', q.id)"
            >
                <span>{{ q.number }}</span>
            </button>
        </div>

        <p v-if="!displayQuestions.length" class="text-xs text-slate-400 text-center py-2">
            No questions in this filter.
        </p>

        <div class="grid grid-cols-2 gap-2 text-[11px] font-semibold pt-2 border-t border-slate-100">
            <div class="flex items-center gap-1.5 text-emerald-800">
                <span class="h-2.5 w-2.5 rounded-md bg-emerald-500 shrink-0" aria-hidden="true" />
                <span>Answered</span>
            </div>
            <div class="flex items-center gap-1.5 text-amber-800">
                <span class="h-2.5 w-2.5 rounded-md bg-amber-400 shrink-0" aria-hidden="true" />
                <span>Review</span>
            </div>
            <div class="flex items-center gap-1.5 text-slate-600">
                <span class="h-2.5 w-2.5 rounded-md border border-slate-300 bg-white shrink-0" aria-hidden="true" />
                <span>Unanswered</span>
            </div>
            <div class="flex items-center gap-1.5 text-[#0f3d7a]">
                <span class="h-2.5 w-2.5 rounded-md border-2 border-[#0f3d7a] bg-blue-50 shrink-0" aria-hidden="true" />
                <span>Current Q</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    questions: { type: Array, required: true },
    answeredCount: { type: Number, required: true },
    reviewCount: { type: Number, default: 0 },
    navClass: { type: Function, required: true },
    navLabel: { type: Function, default: null },
    navStatus: { type: Function, default: null },
    currentId: { type: [Number, String], default: null },
});

defineEmits(['select']);

const activeFilter = ref('all');

const displayQuestions = computed(() => {
    if (!props.navStatus || activeFilter.value === 'all') {
        return props.questions;
    }
    return props.questions.filter(q => props.navStatus(q) === activeFilter.value);
});

function isCurrent(q) {
    return props.currentId != null && String(q.id) === String(props.currentId);
}

function labelFor(q) {
    const base = typeof props.navLabel === 'function' ? props.navLabel(q) : `Question ${q.number}`;
    return isCurrent(q) ? `${base}, current question` : base;
}
</script>
