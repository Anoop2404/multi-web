<template>
    <PortalLayout
        role-label="Student Portal"
        :title="registration.exam?.title ?? 'Exam result'"
        :subtitle="`${school.name} · Student ID ${student.reg_no}`"
        accent="navy"
        :nav-items="navItems"
        :avatar-url="student.photo_url || ''"
    >
        <div class="max-w-3xl mx-auto space-y-6 py-2">
            <!-- Scorecard Hero -->
            <section
                class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#041525] via-[#0f3d7a] to-[#1e5aa8] text-white p-6 sm:p-8 shadow-xl"
            >
                <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_rgba(255,255,255,0.4),_transparent_60%)] pointer-events-none" />
                <div class="relative space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-white/10 text-amber-300 ring-1 ring-white/15">
                            Assessment Report
                        </span>
                        <span class="text-xs text-slate-300">
                            ID: {{ student.reg_no }}
                        </span>
                    </div>

                    <div>
                        <h1 class="text-xl sm:text-2xl font-extrabold leading-tight">
                            {{ registration.exam?.title ?? 'Online Examination' }}
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-200 mt-1">
                            Attempt completed and registered with {{ school.name }}.
                        </p>
                    </div>

                    <!-- Published Score Display -->
                    <div v-if="showResults && mark" class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
                        <div class="rounded-2xl bg-white/10 backdrop-blur p-3.5 border border-white/15 text-center">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-300">Score</span>
                            <span class="block text-2xl sm:text-3xl font-black text-amber-300 mt-0.5">{{ mark.score }}</span>
                            <span v-if="mark.percentage != null" class="text-[11px] font-semibold text-slate-300">({{ mark.percentage }}%)</span>
                        </div>

                        <div v-if="mark.grade" class="rounded-2xl bg-white/10 backdrop-blur p-3.5 border border-white/15 text-center">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-300">Grade</span>
                            <span class="block text-2xl sm:text-3xl font-black text-white mt-0.5">{{ mark.grade }}</span>
                            <span class="text-[11px] font-semibold text-emerald-300">Achieved</span>
                        </div>

                        <div v-if="mark.rank" class="rounded-2xl bg-white/10 backdrop-blur p-3.5 border border-white/15 text-center">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-300">Rank</span>
                            <span class="block text-2xl sm:text-3xl font-black text-amber-300 mt-0.5">#{{ mark.rank }}</span>
                            <span class="text-[11px] font-semibold text-slate-300">Overall</span>
                        </div>

                        <div class="rounded-2xl bg-white/10 backdrop-blur p-3.5 border border-white/15 text-center">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-300">Status</span>
                            <span class="block text-lg sm:text-xl font-black text-emerald-300 mt-1">Published</span>
                            <span class="text-[10px] font-semibold text-slate-300">Official</span>
                        </div>
                    </div>

                    <!-- Results Held / Pending -->
                    <div v-else-if="!showResults" class="rounded-2xl bg-amber-400/15 border border-amber-300/30 p-4 text-xs sm:text-sm text-amber-100 space-y-1">
                        <p class="font-bold text-amber-200">⏳ Results pending publication</p>
                        <p class="text-slate-200">Your answers have been safely submitted. Scores and ranks will be published here once authorized by the examination coordinator.</p>
                    </div>

                    <div v-else class="rounded-2xl bg-white/10 p-4 text-xs text-slate-200">
                        Exam submitted — marks not published for this assessment.
                    </div>

                    <!-- Certificate Button -->
                    <div v-if="certificateUrl" class="pt-2">
                        <a
                            :href="certificateUrl"
                            target="_blank"
                            rel="noopener"
                            class="inline-flex items-center gap-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-[#041525] px-5 py-2.5 text-xs font-extrabold shadow-md transition"
                        >
                            <span>Download Official Certificate</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </a>
                    </div>
                    <p v-else-if="certificatesEnabled && certificateUnavailableReason" class="text-xs text-slate-300">
                        Certificate: {{ certificateUnavailableReason }}
                    </p>
                </div>
            </section>

            <!-- Reattempt Opportunity Card -->
            <section v-if="canReattempt && reattemptUrl" class="rounded-3xl border border-sky-200 bg-sky-50/70 p-5 space-y-3">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-extrabold text-sky-950">Another Attempt is Available</h2>
                        <p class="text-xs text-sky-800 mt-0.5">
                            You are permitted to reattempt this exam.
                            <span v-if="attemptsRemaining" class="font-bold">({{ attemptsRemaining }} attempt{{ attemptsRemaining === 1 ? '' : 's' }} remaining)</span>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="btn-primary text-xs font-bold !min-h-10 px-4 shrink-0"
                        :disabled="reattempting"
                        @click="startReattempt"
                    >
                        {{ reattempting ? 'Starting attempt…' : 'Start reattempt now' }}
                    </button>
                </div>
            </section>

            <!-- Answer Evaluation Breakdown -->
            <section v-if="showResults && mark?.answers_json?.length" class="rounded-3xl border border-slate-200/90 bg-white p-5 sm:p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-base font-extrabold text-slate-900">Question-by-Question Review</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Summary of your submitted responses vs correct answers</p>
                    </div>
                </div>

                <ul class="divide-y divide-slate-100 text-xs sm:text-sm">
                    <li
                        v-for="(a, i) in mark.answers_json"
                        :key="i"
                        class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2"
                    >
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded-md text-xs">
                                Q{{ a.index ?? (i + 1) }}
                            </span>
                            <span v-if="a.correct != null" class="text-xs text-slate-500">
                                (Key: <strong class="text-slate-800 uppercase">{{ formatKey(a.correct) }}</strong>)
                            </span>
                        </div>

                        <div class="flex items-center gap-2 text-xs">
                            <span
                                class="font-extrabold uppercase tracking-wide px-2 py-0.5 rounded-full"
                                :class="a.is_correct ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : (a.chosen ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-slate-100 text-slate-600')"
                            >
                                {{ a.is_correct ? '✓ Correct' : (a.chosen ? '✕ Incorrect' : '⚪ Unanswered') }}
                            </span>
                            <span v-if="a.chosen" class="text-slate-600 font-medium">
                                Your answer: <strong class="uppercase text-slate-900">{{ formatKey(a.chosen) }}</strong>
                            </span>
                        </div>
                    </li>
                </ul>
            </section>

            <!-- Footer actions -->
            <div class="flex justify-between items-center pt-2">
                <a :href="`/portal/student/${school.id}/mcq`" class="btn-secondary text-xs font-bold min-h-11 px-4">
                    ← Return to exam center
                </a>
                <a :href="`/portal/student/${school.id}/documents`" class="text-xs font-bold text-[#0f3d7a] hover:underline">
                    View in documents vault →
                </a>
            </div>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { studentPortalNavItems } from '@/support/studentPortalNav.js';
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    school: Object,
    student: Object,
    registration: Object,
    mark: Object,
    showResults: Boolean,
    resultVisibility: { type: String, default: 'score_grade' },
    certificateUrl: { type: String, default: null },
    certificateUnavailableReason: { type: String, default: null },
    certificatesEnabled: { type: Boolean, default: false },
    canReattempt: { type: Boolean, default: false },
    reattemptUrl: { type: String, default: null },
    attemptsRemaining: { type: Number, default: 0 },
});

const reattempting = ref(false);
const navItems = computed(() => studentPortalNavItems(props.school.id));

function startReattempt() {
    if (!props.reattemptUrl || reattempting.value) return;
    reattempting.value = true;
    router.post(props.reattemptUrl, {}, {
        onFinish: () => { reattempting.value = false; },
    });
}

function formatKey(value) {
    if (Array.isArray(value)) return value.join(', ');
    return value == null ? '—' : String(value);
}
</script>
