<template>
    <PortalLayout
        role-label="Student Portal"
        title="Fest Competition Results"
        :subtitle="`${school.name} · Student ID ${student.reg_no}`"
        accent="navy"
        :nav-items="navItems"
    >
        <!-- Activity Sub-navigation Tabs -->
        <div class="flex flex-wrap items-center gap-2 mb-6 border-b border-slate-200/80 pb-3">
            <a
                :href="`/portal/student/${school.id}/fest/schedule`"
                class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 hover:text-slate-900 transition"
            >
                📅 Timetable &amp; Stages
            </a>
            <a
                :href="`/portal/student/${school.id}/results`"
                class="px-4 py-2 rounded-xl text-xs font-extrabold bg-[#0f3d7a] text-white shadow-sm"
            >
                🏆 Fest Results
            </a>
            <a
                :href="`/portal/student/${school.id}/sports-results`"
                class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 hover:text-slate-900 transition"
            >
                🏃 Sports Results
            </a>
            <a
                :href="`/portal/student/${school.id}/fest-registrations`"
                class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 hover:text-slate-900 transition"
            >
                📝 Registrations
            </a>
            <a
                :href="`/portal/student/${school.id}/certificates`"
                class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 hover:text-slate-900 transition"
            >
                📜 Certificates
            </a>
        </div>

        <section class="space-y-4">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-extrabold text-slate-900 tracking-tight">Official Published Fest Results</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Approved marks, grades, and positions awarded by the judging panel</p>
                </div>
                <span class="text-xs font-bold text-slate-500 bg-slate-100 px-2.5 py-1 rounded-full">
                    {{ festResults?.length ?? 0 }} Result{{ (festResults?.length ?? 0) === 1 ? '' : 's' }}
                </span>
            </div>

            <div v-if="festResults?.length" class="grid gap-3 sm:grid-cols-2">
                <article
                    v-for="(r, i) in festResults"
                    :key="i"
                    class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm space-y-3 hover:border-[#0f3d7a]/30 transition"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="font-bold text-slate-900 text-sm sm:text-base leading-snug">
                                {{ r.event_title }}
                            </h3>
                            <p class="text-xs text-slate-600 font-medium mt-0.5">{{ r.item_title }}</p>
                        </div>
                        <div v-if="r.position" class="shrink-0">
                            <span
                                class="inline-flex items-center gap-1 px-3 py-1 rounded-xl text-xs font-extrabold shadow-2xs"
                                :class="positionClass(r.position)"
                            >
                                <span>{{ positionIcon(r.position) }}</span>
                                <span>{{ formatPosition(r.position) }}</span>
                            </span>
                        </div>
                    </div>

                    <!-- Scores & Grades Row -->
                    <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100 text-xs">
                        <span v-if="r.grade" class="px-2.5 py-1 rounded-xl bg-emerald-50 text-emerald-800 border border-emerald-200 font-bold">
                            Grade {{ r.grade }}
                        </span>
                        <span v-if="r.points !== null && r.points !== undefined" class="px-2.5 py-1 rounded-xl bg-blue-50 text-[#0f3d7a] border border-blue-200 font-bold">
                            {{ r.points }} Point{{ r.points === 1 ? '' : 's' }}
                        </span>
                        <span v-if="r.chest_no" class="px-2 py-1 rounded-lg bg-slate-100 text-slate-600 font-mono text-[11px]">
                            Chest #{{ r.chest_no }}
                        </span>
                        <span v-if="!r.grade && !r.position && (r.points === null || r.points === undefined)" class="text-xs text-slate-400 italic">
                            Performance evaluated · Score details pending
                        </span>
                    </div>
                </article>
            </div>

            <!-- Empty State -->
            <div v-else class="rounded-3xl border border-dashed border-slate-200 bg-white p-10 text-center space-y-3">
                <span class="text-3xl">🏆</span>
                <div>
                    <h3 class="text-sm font-bold text-slate-900">No published fest results yet</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                        Official competition marks will appear here once the festival tabulation desk approves results.
                    </p>
                </div>
                <div class="pt-2 flex justify-center gap-2">
                    <a :href="`/portal/student/${school.id}/fest/schedule`" class="btn-secondary text-xs">
                        View your timetable →
                    </a>
                </div>
            </div>
        </section>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed } from 'vue';
import { studentPortalNavItems } from '@/support/studentPortalNav.js';

const props = defineProps({
    school: Object,
    student: Object,
    festResults: { type: Array, default: () => [] },
});

const navItems = computed(() => studentPortalNavItems(props.school.id));

function positionIcon(pos) {
    const p = String(pos).toLowerCase();
    if (p === '1' || p.includes('first') || p.includes('1st')) return '🥇';
    if (p === '2' || p.includes('second') || p.includes('2nd')) return '🥈';
    if (p === '3' || p.includes('third') || p.includes('3rd')) return '🥉';
    return '🏅';
}

function positionClass(pos) {
    const p = String(pos).toLowerCase();
    if (p === '1' || p.includes('first') || p.includes('1st')) return 'bg-amber-100 text-amber-900 border border-amber-300';
    if (p === '2' || p.includes('second') || p.includes('2nd')) return 'bg-slate-200 text-slate-800 border border-slate-300';
    if (p === '3' || p.includes('third') || p.includes('3rd')) return 'bg-amber-50 text-amber-950 border border-amber-200';
    return 'bg-blue-50 text-blue-900 border border-blue-200';
}

function formatPosition(pos) {
    const p = String(pos);
    if (p === '1') return '1st Place';
    if (p === '2') return '2nd Place';
    if (p === '3') return '3rd Place';
    return `Position ${p}`;
}
</script>
