<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between gap-2 pb-1 border-b border-slate-100">
            <h2 class="font-extrabold text-xs uppercase tracking-wider text-slate-800">Question Palette</h2>
            <span class="text-xs font-bold text-slate-600 tabular-nums bg-slate-100 px-2 py-0.5 rounded-full">
                {{ answeredCount }}/{{ questions.length }} done
            </span>
        </div>

        <!-- Section Filter (when multiple sections/subjects exist) -->
        <div v-if="distinctSections.length > 1" class="space-y-1">
            <div class="flex items-center justify-between text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                <span>Subject / Section</span>
                <button
                    v-if="selectedSection !== 'all'"
                    type="button"
                    class="text-[10px] text-blue-600 hover:underline cursor-pointer lowercase"
                    @click="selectedSection = 'all'"
                >
                    reset
                </button>
            </div>
            <div class="flex items-center gap-1 overflow-x-auto pb-1 scrollbar-none" role="tablist" aria-label="Section selector">
                <button
                    type="button"
                    role="tab"
                    :aria-selected="selectedSection === 'all'"
                    class="px-2 py-0.5 rounded-md text-[11px] font-bold whitespace-nowrap transition cursor-pointer"
                    :class="selectedSection === 'all' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                    @click="selectedSection = 'all'"
                >
                    All
                </button>
                <button
                    v-for="sec in distinctSections"
                    :key="sec.name"
                    type="button"
                    role="tab"
                    :aria-selected="selectedSection === sec.name"
                    class="px-2 py-0.5 rounded-md text-[11px] font-bold whitespace-nowrap transition cursor-pointer"
                    :class="selectedSection === sec.name ? 'bg-[#0f3d7a] text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                    @click="selectedSection = sec.name"
                >
                    {{ sec.name }} ({{ sec.count }})
                </button>
            </div>
        </div>

        <!-- Filter Chips -->
        <div v-if="navStatus" class="space-y-1">
            <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                <span>Filter Status</span>
            </div>
            <div class="flex flex-wrap gap-1 text-[11px] font-semibold" role="tablist" aria-label="Question status filters">
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeFilter === 'all'"
                    class="px-2 py-0.5 rounded-md transition cursor-pointer"
                    :class="activeFilter === 'all' ? 'bg-slate-800 text-white font-bold shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                    @click="activeFilter = 'all'"
                >
                    All Questions
                </button>
                <button
                    v-if="reviewCount > 0"
                    type="button"
                    role="tab"
                    :aria-selected="activeFilter === 'review'"
                    class="px-2 py-0.5 rounded-md transition cursor-pointer flex items-center gap-1"
                    :class="activeFilter === 'review' ? 'bg-amber-400 text-amber-950 font-bold shadow-xs' : 'bg-amber-50 text-amber-800 hover:bg-amber-100'"
                    @click="activeFilter = 'review'"
                >
                    <span>⭐</span>
                    <span>Review ({{ reviewCount }})</span>
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="activeFilter === 'unanswered'"
                    class="px-2 py-0.5 rounded-md transition cursor-pointer"
                    :class="activeFilter === 'unanswered' ? 'bg-slate-700 text-white font-bold shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                    @click="activeFilter = 'unanswered'"
                >
                    Unanswered ({{ questions.length - answeredCount }})
                </button>
            </div>
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
const selectedSection = ref('all');

const distinctSections = computed(() => {
    const map = new Map();
    for (const q of props.questions) {
        const sec = q.section || 'General';
        map.set(sec, (map.get(sec) || 0) + 1);
    }
    return Array.from(map.entries()).map(([name, count]) => ({ name, count }));
});

const displayQuestions = computed(() => {
    let list = props.questions;
    if (selectedSection.value !== 'all') {
        list = list.filter((q) => (q.section || 'General') === selectedSection.value);
    }
    if (!props.navStatus || activeFilter.value === 'all') {
        return list;
    }
    return list.filter((q) => props.navStatus(q) === activeFilter.value);
});

function isCurrent(q) {
    return props.currentId != null && String(q.id) === String(props.currentId);
}

function labelFor(q) {
    const base = typeof props.navLabel === 'function' ? props.navLabel(q) : `Question ${q.number}`;
    return isCurrent(q) ? `${base}, current question` : base;
}
</script>
