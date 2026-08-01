<template>
    <div class="flex flex-wrap items-center justify-between gap-3 bg-white border border-slate-200 rounded-2xl p-2 mb-6 print:hidden shadow-2xs">
        <div class="flex flex-wrap items-center gap-1">
            <Link
                :href="`/sahodaya-admin/${sahodayaId}/board-results/reports/subject-merit`"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                :class="active === 'subject-merit' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
            >
                <span>⭐</span> Subject-Wise Toppers
            </Link>
            <Link
                :href="`/sahodaya-admin/${sahodayaId}/board-results/reports/full-a1-achievers`"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                :class="active === 'full-a1' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
            >
                <span>🌟</span> Full A1 Achievers
            </Link>
            <Link
                :href="`/sahodaya-admin/${sahodayaId}/board-results/toppers`"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                :class="active === 'toppers' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
            >
                <span>🏆</span> Class 10 & 12 Stream Toppers
            </Link>
            <Link
                :href="`/sahodaya-admin/${sahodayaId}/board-results/reports/excellence`"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                :class="active === 'excellence' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
            >
                <span>🏅</span> Excellence Awards
            </Link>
        </div>
        <Link
            :href="`/sahodaya-admin/${sahodayaId}/board-results/reports`"
            class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
            :class="active === 'reports-hub' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
        >
            <span>📂</span> All Reports Hub →
        </Link>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    sahodayaId: { type: [String, Number], required: true },
    active: { type: String, default: '' },
});
</script>
