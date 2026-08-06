<template>
    <div class="flex flex-wrap items-center justify-between gap-3 bg-white border border-slate-200 rounded-2xl p-2 mb-6 print:hidden shadow-2xs">
        <div class="flex flex-wrap items-center gap-1">
            <Link
                :href="`/sahodaya-admin/${sahodayaId}/board-results/verification?class=${currentClass || 10}`"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                :class="active === 'overall' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
            >
                <span>📋</span> Overall Submissions Verification
                <span v-if="counts?.schools_submitted !== undefined" class="ml-1 px-1.5 py-0.2 rounded-full text-[10px] font-extrabold" :class="active === 'overall' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800'">
                    {{ counts.schools_submitted }}
                </span>
            </Link>
            <Link
                :href="`/sahodaya-admin/${sahodayaId}/board-results/reports/full-a1-achievers${currentClass ? '?class=' + currentClass : ''}`"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                :class="active === 'full-a1' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
            >
                <span>🌟</span> Full A1 Achievers Verification
                <span v-if="counts?.full_a1 !== undefined" class="ml-1 px-1.5 py-0.2 rounded-full text-[10px] font-extrabold" :class="active === 'full-a1' ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-800'">
                    {{ counts.full_a1 }}
                </span>
            </Link>
            <Link
                :href="`/sahodaya-admin/${sahodayaId}/board-results/toppers${currentClass ? '?class=' + currentClass : ''}`"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                :class="active === 'toppers' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
            >
                <span>🏆</span> Toppers & Subject Verification
                <span v-if="counts?.total_toppers !== undefined" class="ml-1 px-1.5 py-0.2 rounded-full text-[10px] font-extrabold" :class="active === 'toppers' ? 'bg-white/20 text-white' : 'bg-violet-100 text-violet-800'">
                    {{ counts.total_toppers }}
                </span>
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
