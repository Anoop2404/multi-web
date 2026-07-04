<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between gap-2">
            <h2 class="font-semibold text-xs uppercase tracking-wide text-gray-500">Questions</h2>
            <span class="text-xs text-gray-500">{{ answeredCount }}/{{ questions.length }}</span>
        </div>
        <div class="grid grid-cols-5 lg:grid-cols-4 gap-1.5">
            <button
                v-for="q in questions"
                :key="`nav-${q.id}`"
                type="button"
                class="h-9 rounded-md text-xs font-semibold border transition"
                :class="navClass(q)"
                @click="$emit('select', q.id)"
            >
                {{ q.number }}
            </button>
        </div>
        <ul class="text-[11px] text-gray-500 space-y-1 pt-1 border-t">
            <li class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded border border-indigo-400 bg-indigo-100" /> Answered</li>
            <li class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded border border-gray-300 bg-white" /> Not answered</li>
            <li class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded border border-amber-400 bg-amber-100" /> Marked for review</li>
        </ul>
    </div>
</template>

<script setup>
defineProps({
    questions: { type: Array, required: true },
    answeredCount: { type: Number, required: true },
    navClass: { type: Function, required: true },
});

defineEmits(['select']);
</script>
