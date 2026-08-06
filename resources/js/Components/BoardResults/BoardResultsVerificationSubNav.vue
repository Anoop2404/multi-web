<template>
    <div class="flex flex-wrap items-center justify-between gap-3 bg-white border border-slate-200 rounded-2xl p-2 mb-6 print:hidden shadow-2xs">
        <div class="flex flex-wrap items-center gap-1">
            <Link
                :href="`/sahodaya-admin/${sahodayaId}/board-results/verification?class=${currentClass || 10}`"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                :class="active === 'overall' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
            >
                <span>🏫</span> School Proofs
            </Link>
            <Link
                :href="`/sahodaya-admin/${sahodayaId}/board-results/verification/overall?class=${currentClass || 10}`"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                :class="active === 'toppers-overall' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
            >
                <span>🏆</span> {{ (currentClass == 12) ? 'Stream' : 'Overall' }} Toppers
            </Link>
            <Link
                :href="`/sahodaya-admin/${sahodayaId}/board-results/verification/subjects?class=${currentClass || 10}`"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                :class="active === 'toppers-subjects' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
            >
                <span>🎯</span> Subject Toppers
            </Link>
            <Link
                :href="`/sahodaya-admin/${sahodayaId}/board-results/verification/a1?class=${currentClass || 10}`"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                :class="active === 'full-a1' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
            >
                <span>🌟</span> Full A1 Achievers
            </Link>
        </div>

        <div class="flex items-center gap-2">
            <Link
                :href="`/sahodaya-admin/${sahodayaId}/board-results/reports`"
                class="px-3.5 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 hover:text-slate-900 flex items-center gap-1.5 transition"
            >
                <span>📊</span> Reports Hub
            </Link>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    sahodayaId: { type: [String, Number], required: true },
    active: { type: String, default: 'overall' },
    currentClass: { type: [Number, String], default: null },
    counts: { type: Object, default: () => ({}) },
});
</script>
