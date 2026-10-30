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
                            <span class="block text-lg sm:text-xl font-black text-emerald-300 mt-1">
                                {{ registration.exam?.results_published ? 'Published' : 'Completed' }}
                            </span>
                            <span class="text-[10px] font-semibold text-slate-300">
                                {{ registration.exam?.results_published ? 'Official' : 'Instant Score' }}
                            </span>
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
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
                    <div>
                        <h2 class="text-base font-extrabold text-slate-900">Question-by-Question Review</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Detailed examination breakdown with correct answers and explanations</p>
                    </div>

                    <!-- Filter Chips: All, Correct, Incorrect, Unanswered -->
                    <div class="flex flex-wrap items-center gap-1.5 text-xs">
                        <button
                            type="button"
                            @click="reviewFilter = 'all'"
                            class="px-2.5 py-1 rounded-lg font-bold transition"
                            :class="reviewFilter === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        >
                            All ({{ mark.answers_json.length }})
                        </button>
                        <button
                            type="button"
                            @click="reviewFilter = 'correct'"
                            class="px-2.5 py-1 rounded-lg font-bold transition"
                            :class="reviewFilter === 'correct' ? 'bg-emerald-700 text-white' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100'"
                        >
                            Correct ({{ mark.correct_count ?? 0 }})
                        </button>
                        <button
                            type="button"
                            @click="reviewFilter = 'wrong'"
                            class="px-2.5 py-1 rounded-lg font-bold transition"
                            :class="reviewFilter === 'wrong' ? 'bg-red-700 text-white' : 'bg-red-50 text-red-800 hover:bg-red-100'"
                        >
                            Incorrect ({{ mark.wrong_count ?? 0 }})
                        </button>
                        <button
                            v-if="mark.unanswered_count"
                            type="button"
                            @click="reviewFilter = 'unanswered'"
                            class="px-2.5 py-1 rounded-lg font-bold transition"
                            :class="reviewFilter === 'unanswered' ? 'bg-amber-700 text-white' : 'bg-amber-50 text-amber-800 hover:bg-amber-100'"
                        >
                            Unanswered ({{ mark.unanswered_count }})
                        </button>
                    </div>
                </div>

                <div class="space-y-4">
                    <div
                        v-for="(a, i) in filteredAnswers"
                        :key="a.question_id ?? i"
                        class="rounded-2xl border p-4 sm:p-5 transition"
                        :class="a.is_correct ? 'border-emerald-200 bg-emerald-50/20' : (a.chosen ? 'border-red-200 bg-red-50/20' : 'border-slate-200 bg-slate-50/30')"
                    >
                        <!-- Question Header -->
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-black text-xs px-2.5 py-1 rounded-lg"
                                      :class="a.is_correct ? 'bg-emerald-600 text-white' : (a.chosen ? 'bg-red-600 text-white' : 'bg-slate-200 text-slate-700')">
                                    Q{{ a.index }}
                                </span>
                                <span class="text-xs font-extrabold uppercase tracking-wide px-2.5 py-0.5 rounded-full"
                                      :class="a.is_correct ? 'bg-emerald-100 text-emerald-900' : (a.chosen ? 'bg-red-100 text-red-900' : 'bg-slate-200 text-slate-700')">
                                    {{ a.is_correct ? '✓ Correct' : (a.chosen ? '✕ Incorrect' : '⚪ Unanswered') }}
                                </span>
                            </div>

                            <div class="text-right text-xs">
                                <span class="font-bold text-slate-700">
                                    Marks: <strong :class="a.awarded > 0 ? 'text-emerald-700' : (a.awarded < 0 ? 'text-red-700' : 'text-slate-500')">{{ a.awarded > 0 ? `+${a.awarded}` : (a.awarded ?? 0) }}</strong> / {{ a.marks ?? 1 }}
                                </span>
                                <span v-if="a.negative_mark > 0" class="block text-[10px] text-slate-400">
                                    Negative: -{{ a.negative_mark }}
                                </span>
                            </div>
                        </div>

                        <!-- Question Body (if provided in review mode) -->
                        <div v-if="a.question_body" class="mb-3">
                            <McqRichText :text="a.question_body" class="text-slate-900 font-medium text-sm leading-relaxed" />
                            <div v-if="a.question_image_url" class="mt-2 max-w-sm rounded-lg overflow-hidden border border-slate-200">
                                <img
                                    :src="a.question_image_url"
                                    alt="Question illustration"
                                    class="max-h-48 object-contain cursor-zoom-in hover:opacity-95 transition"
                                    @click="zoomSrc = a.question_image_url"
                                />
                            </div>
                        </div>

                        <!-- Options List (if options exist) -->
                        <div v-if="a.options?.length" class="grid grid-cols-1 gap-2 my-3">
                            <div
                                v-for="opt in a.options"
                                :key="opt.key"
                                class="flex items-center gap-2.5 p-2.5 rounded-xl border text-xs"
                                :class="getOptionClass(a, opt.key)"
                            >
                                <span class="h-6 w-6 rounded-lg font-bold flex items-center justify-center shrink-0 uppercase text-xs"
                                      :class="getOptionBadgeClass(a, opt.key)">
                                    {{ opt.key }}
                                </span>
                                <div class="flex-1 min-w-0 space-y-1">
                                    <McqRichText :text="opt.label" class="font-medium leading-snug" />
                                    <div v-if="opt.image_url" class="rounded-lg overflow-hidden border border-slate-200 max-w-xs bg-white p-1">
                                        <img
                                            :src="opt.image_url"
                                            alt="Option illustration"
                                            class="max-h-28 object-contain cursor-zoom-in hover:opacity-95 transition"
                                            @click.stop="zoomSrc = opt.image_url"
                                        />
                                    </div>
                                </div>
                                <span v-if="isCorrectOption(a, opt.key)" class="text-[11px] font-extrabold text-emerald-700 shrink-0">
                                    ✓ Correct
                                </span>
                                <span v-else-if="isChosenOption(a, opt.key) && !a.is_correct" class="text-[11px] font-extrabold text-red-700 shrink-0">
                                    ✕ Your choice
                                </span>
                            </div>
                        </div>

                        <!-- Simple Answer Row when options not hydrated -->
                        <div v-else class="flex flex-wrap items-center gap-4 text-xs pt-1 border-t border-slate-100">
                            <div v-if="a.chosen">
                                <span class="text-slate-500">Your answer: </span>
                                <strong class="uppercase text-slate-900">{{ formatKey(a.chosen) }}</strong>
                            </div>
                            <div v-if="a.correct != null">
                                <span class="text-slate-500">Correct key: </span>
                                <strong class="uppercase text-emerald-700">{{ formatKey(a.correct) }}</strong>
                            </div>
                        </div>

                        <!-- Explanation Box -->
                        <div v-if="a.explanation" class="mt-3 p-3 rounded-xl bg-amber-500/10 border border-amber-300/40 text-xs text-amber-950">
                            <span class="font-bold text-amber-900 block mb-0.5">💡 Explanation:</span>
                            <McqRichText :text="a.explanation" class="leading-relaxed" />
                        </div>
                    </div>

                    <div v-if="!filteredAnswers.length" class="text-center py-6 text-xs text-slate-400">
                        No questions in this filter view.
                    </div>
                </div>
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

        <!-- Image Zoom Lightbox Modal -->
        <div
            v-if="zoomSrc"
            class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 cursor-zoom-out"
            @click="zoomSrc = null"
            @keydown.escape.window="zoomSrc = null"
        >
            <div class="relative max-w-4xl max-h-[90vh] bg-white rounded-2xl overflow-hidden shadow-2xl p-2" @click.stop>
                <button
                    type="button"
                    class="absolute top-3 right-3 h-8 w-8 rounded-full bg-slate-900/70 text-white flex items-center justify-center text-xs font-bold hover:bg-slate-900 cursor-pointer"
                    @click="zoomSrc = null"
                    aria-label="Close zoom"
                >
                    ✕
                </button>
                <img :src="zoomSrc" alt="Enlarged view" class="max-h-[85vh] w-auto max-w-full object-contain mx-auto rounded-lg">
            </div>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import McqRichText from '@/Components/mcq/McqRichText.vue';
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
const reviewFilter = ref('all');
const zoomSrc = ref(null);
const navItems = computed(() => studentPortalNavItems(props.school.id));

const filteredAnswers = computed(() => {
    const list = props.mark?.answers_json ?? [];
    if (reviewFilter.value === 'correct') {
        return list.filter((a) => a.is_correct);
    }
    if (reviewFilter.value === 'wrong') {
        return list.filter((a) => !a.is_correct && a.chosen != null);
    }
    if (reviewFilter.value === 'unanswered') {
        return list.filter((a) => a.chosen == null || a.chosen === '');
    }
    return list;
});

function isCorrectOption(answer, optKey) {
    if (Array.isArray(answer.correct)) {
        return answer.correct.map(String).includes(String(optKey));
    }
    return String(answer.correct ?? '').toLowerCase() === String(optKey).toLowerCase();
}

function isChosenOption(answer, optKey) {
    if (Array.isArray(answer.chosen)) {
        return answer.chosen.map(String).includes(String(optKey));
    }
    return String(answer.chosen ?? '').toLowerCase() === String(optKey).toLowerCase();
}

function getOptionClass(answer, optKey) {
    const isCorrect = isCorrectOption(answer, optKey);
    const isChosen = isChosenOption(answer, optKey);

    if (isCorrect) {
        return 'border-emerald-300 bg-emerald-50/80 text-emerald-950 font-semibold';
    }
    if (isChosen && !answer.is_correct) {
        return 'border-red-300 bg-red-50/80 text-red-950 font-semibold';
    }
    return 'border-slate-200 bg-white text-slate-700';
}

function getOptionBadgeClass(answer, optKey) {
    const isCorrect = isCorrectOption(answer, optKey);
    const isChosen = isChosenOption(answer, optKey);

    if (isCorrect) {
        return 'bg-emerald-600 text-white';
    }
    if (isChosen && !answer.is_correct) {
        return 'bg-red-600 text-white';
    }
    return 'bg-slate-100 text-slate-700';
}

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
