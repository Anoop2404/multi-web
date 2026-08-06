<template>
    <div class="flex flex-wrap items-center justify-between gap-3 bg-white border border-slate-200 rounded-2xl p-2 mb-6 print:hidden shadow-2xs">
        <div class="flex flex-wrap items-center gap-1">
            <Link
                :href="`/sahodaya-admin/${sahodayaId}/board-results/verification?class=${currentClass || 10}`"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                :class="active === 'overall' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
            >
                <span>📋</span> Overall School Submissions Verification
            </Link>
            <Link
                :href="`/sahodaya-admin/${sahodayaId}/board-results/reports/full-a1-achievers${currentClass ? '?class=' + currentClass : ''}`"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                :class="active === 'full-a1' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
            >
                <span>🌟</span> Full A1 Achievers Verification
            </Link>
            <Link
                :href="`/sahodaya-admin/${sahodayaId}/board-results/toppers${currentClass ? '?class=' + currentClass : ''}`"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                :class="active === 'toppers' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
            >
                <span>🏆</span> Toppers & Subject Verification
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
});
</script>
