<template>
    <div class="flex flex-wrap items-center justify-between gap-3 bg-white border border-slate-200 rounded-2xl p-2 mb-6 print:hidden shadow-2xs">
        <div class="flex flex-wrap items-center gap-1">
            <Link
                :href="`/sahodaya-admin/${sahodayaId}/board-results/reports/school-wise-toppers`"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                :class="active === 'school-wise-toppers' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
            >
                <span>🏫</span> School-Wise Toppers
                <span v-if="counts?.school_toppers !== undefined" class="ml-1 px-1.5 py-0.2 rounded-full text-[10px] font-extrabold" :class="active === 'school-wise-toppers' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'">
                    {{ counts.school_toppers }}
                </span>
            </Link>
            <Link
                :href="`/sahodaya-admin/${sahodayaId}/board-results/reports/subject-merit`"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                :class="active === 'subject-merit' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
            >
                <span>⭐</span> Subject-Wise Toppers
                <span v-if="counts?.subject_merit !== undefined" class="ml-1 px-1.5 py-0.2 rounded-full text-[10px] font-extrabold" :class="active === 'subject-merit' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'">
                    {{ counts.subject_merit }}
                </span>
            </Link>
            <Link
                :href="`/sahodaya-admin/${sahodayaId}/board-results/reports/full-a1-achievers`"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                :class="active === 'full-a1' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
            >
                <span>🌟</span> Full A1 Achievers
                <span v-if="counts?.full_a1 !== undefined" class="ml-1 px-1.5 py-0.2 rounded-full text-[10px] font-extrabold" :class="active === 'full-a1' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'">
                    {{ counts.full_a1 }}
                </span>
            </Link>
            <Link
                :href="`/sahodaya-admin/${sahodayaId}/board-results/toppers`"
                class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5"
                :class="active === 'toppers' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
            >
                <span>🏆</span> Stream Toppers
                <span v-if="counts?.total_toppers !== undefined" class="ml-1 px-1.5 py-0.2 rounded-full text-[10px] font-extrabold" :class="active === 'toppers' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-700'">
                    {{ counts.total_toppers }}
                </span>
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
    counts: { type: Object, default: () => ({}) },
});
</script>
