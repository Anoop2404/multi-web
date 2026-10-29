<template>
    <PortalLayout
        role-label="Student Portal"
        title="Online Examination Center"
        :subtitle="`${school.name} · Student ID ${student.reg_no}`"
        accent="navy"
        :nav-items="navItems"
        :avatar-url="student.photo_url || ''"
    >
        <!-- Exam Hub Header & Device Readiness Banner -->
        <section class="rounded-3xl bg-gradient-to-br from-[#041525] via-[#0f3d7a] to-[#1e5aa8] text-white p-5 sm:p-7 shadow-lg mb-6 relative overflow-hidden">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_rgba(255,255,255,0.4),_transparent_60%)] pointer-events-none" />
            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-5">
                <div class="space-y-2 max-w-xl">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-white/10 text-amber-300 ring-1 ring-white/15">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse" />
                        Online Assessment Hub
                    </span>
                    <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight">
                        Your Online Exams &amp; Talent Searches
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-200/90 leading-relaxed">
                        Access scheduled tests, practice device checks, download official hall tickets, and review completed performance reports.
                    </p>
                </div>

                <!-- Pre-exam readiness card -->
                <div class="shrink-0 rounded-2xl bg-black/25 backdrop-blur p-4 border border-white/15 space-y-3 min-w-[16rem]">
                    <div class="flex items-center gap-2.5">
                        <span class="text-lg">💻</span>
                        <div>
                            <p class="text-xs font-bold text-white">Device &amp; Browser Check</p>
                            <p class="text-[11px] text-slate-300">Run test before starting</p>
                        </div>
                    </div>
                    <a
                        :href="`/portal/student/${school.id}/mcq/system-check`"
                        class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-[#041525] text-xs font-extrabold py-2 px-3 transition shadow-sm"
                    >
                        <span>Run practice check</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>
        </section>

        <!-- Exam Filter Tabs & Stats -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
            <!-- Filter Pills -->
            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    class="rounded-xl px-4 py-2 text-xs font-bold transition flex items-center gap-2"
                    :class="filter === tab.id
                        ? 'bg-[#0f3d7a] text-white shadow-sm ring-1 ring-[#0f3d7a]'
                        : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 hover:text-slate-900'"
                    @click="filter = tab.id"
                >
                    <span>{{ tab.label }}</span>
                    <span
                        class="text-[10px] px-1.5 py-0.2 rounded-full"
                        :class="filter === tab.id ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'"
                    >
                        {{ tab.count }}
                    </span>
                </button>
            </div>

            <div class="text-xs text-slate-500 font-medium self-end sm:self-center">
                Showing {{ filteredExams.length }} of {{ (mcqExams ?? []).length }} assessment{{ (mcqExams ?? []).length === 1 ? '' : 's' }}
            </div>
        </div>

        <!-- Exams List -->
        <section class="space-y-3.5 mb-8">
            <StudentMcqExamCard
                v-for="exam in filteredExams"
                :key="exam.registration_route_id ?? exam.id"
                :school-id="school.id"
                :exam="exam"
            />

            <!-- Empty State -->
            <div v-if="!filteredExams.length" class="rounded-3xl border border-dashed border-slate-200 bg-white p-8 text-center space-y-3">
                <div class="h-12 w-12 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-center mx-auto text-xl">
                    📝
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-900">No exams in this category</h3>
                    <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                        {{ emptyStateMessage }}
                    </p>
                </div>
                <div class="pt-1">
                    <button
                        v-if="filter !== 'all'"
                        type="button"
                        class="text-xs font-bold text-[#0f3d7a] hover:underline"
                        @click="filter = 'all'"
                    >
                        Show all exams →
                    </button>
                </div>
            </div>
        </section>

        <!-- Exam Day Tips & Help Box -->
        <section class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm space-y-3">
            <div class="flex items-center gap-2">
                <span class="text-base">💡</span>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">Exam Hall Guidelines &amp; Best Practices</h3>
            </div>
            <div class="grid gap-3 sm:grid-cols-3 text-xs text-slate-600">
                <div class="rounded-xl bg-slate-50 p-3 border border-slate-100 space-y-1">
                    <p class="font-bold text-slate-900">1. Automatic Saving</p>
                    <p class="text-slate-500">Every answer is instantly saved to the server. You can freely navigate between questions.</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 border border-slate-100 space-y-1">
                    <p class="font-bold text-slate-900">2. Keep Tab Active</p>
                    <p class="text-slate-500">Avoid switching browser tabs or minimizing the window during strict proctored exams.</p>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 border border-slate-100 space-y-1">
                    <p class="font-bold text-slate-900">3. Connection Issues?</p>
                    <p class="text-slate-500">If your internet drops, answers are saved locally in the browser and will sync upon reconnecting.</p>
                </div>
            </div>
        </section>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import StudentMcqExamCard from '@/Components/portal/StudentMcqExamCard.vue';
import { computed, ref } from 'vue';
import { studentPortalNavItems } from '@/support/studentPortalNav.js';

const props = defineProps({
    school: Object,
    student: Object,
    mcqExams: { type: Array, default: () => [] },
});

const navItems = computed(() => studentPortalNavItems(props.school.id));
const filter = ref('all');

const actionable = computed(() => (props.mcqExams ?? []).filter((e) => e.can_take_online));
const upcoming = computed(() => (props.mcqExams ?? []).filter((e) => !e.can_take_online && e.status !== 'submitted' && !e.show_results));
const submitted = computed(() => (props.mcqExams ?? []).filter((e) => e.status === 'submitted' || e.show_results));

const tabs = computed(() => [
    { id: 'all', label: 'All Exams', count: (props.mcqExams ?? []).length },
    { id: 'actionable', label: 'Ready to Start', count: actionable.value.length },
    { id: 'upcoming', label: 'Upcoming & Offline', count: upcoming.value.length },
    { id: 'submitted', label: 'Completed & Results', count: submitted.value.length },
]);

const filteredExams = computed(() => {
    if (filter.value === 'actionable') return actionable.value;
    if (filter.value === 'upcoming') return upcoming.value;
    if (filter.value === 'submitted') return submitted.value;
    return props.mcqExams ?? [];
});

const emptyStateMessage = computed(() => {
    if (filter.value === 'actionable') return 'No online exams are ready to start right now. Check back at your scheduled exam time.';
    if (filter.value === 'upcoming') return 'No upcoming or offline exams scheduled on your profile.';
    if (filter.value === 'submitted') return 'You have not submitted any exam attempts yet.';
    return 'No exams or talent searches have been registered for your student ID yet.';
});
</script>
