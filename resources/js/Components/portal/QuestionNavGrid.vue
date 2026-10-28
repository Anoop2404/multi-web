<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between gap-2">
            <h2 class="font-semibold text-xs uppercase tracking-wide text-slate-500">Questions</h2>
            <span class="text-xs text-slate-500 tabular-nums">{{ answeredCount }}/{{ questions.length }}</span>
        </div>
        <div class="grid grid-cols-5 lg:grid-cols-4 gap-1.5" role="list">
            <button
                v-for="q in questions"
                :key="`nav-${q.id}`"
                type="button"
                role="listitem"
                class="h-10 min-w-[2.25rem] rounded-md text-xs font-semibold border transition"
                :class="[
                    navClass(q),
                    isCurrent(q) ? 'ring-2 ring-offset-1 ring-slate-800' : '',
                ]"
                :aria-current="isCurrent(q) ? 'true' : undefined"
                :aria-label="labelFor(q)"
                @click="$emit('select', q.id)"
            >
                {{ q.number }}
            </button>
        </div>
        <ul class="text-[11px] text-slate-500 space-y-1 pt-1 border-t border-slate-100">
            <li class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded border-2 border-slate-800 ring-1 ring-slate-800/30" aria-hidden="true" /> Current</li>
            <li class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded border border-indigo-400 bg-indigo-100" aria-hidden="true" /> Answered</li>
            <li class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded border border-slate-300 bg-white" aria-hidden="true" /> Not answered</li>
            <li class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded border border-amber-400 bg-amber-100" aria-hidden="true" /> Marked for review</li>
        </ul>
    </div>
</template>

<script setup>
const props = defineProps({
    questions: { type: Array, required: true },
    answeredCount: { type: Number, required: true },
    navClass: { type: Function, required: true },
    navLabel: { type: Function, default: null },
    currentId: { type: [Number, String], default: null },
});

defineEmits(['select']);

function isCurrent(q) {
    return props.currentId != null && String(q.id) === String(props.currentId);
}

function labelFor(q) {
    const base = typeof props.navLabel === 'function' ? props.navLabel(q) : `Question ${q.number}`;
    return isCurrent(q) ? `${base}, current` : base;
}
</script>
