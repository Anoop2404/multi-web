<template>
    <PortalLayout
        role-label="Student Portal"
        :title="exam.title"
        :subtitle="`${school.name} · ${student.reg_no}`"
        accent="indigo"
        :nav-items="navItems"
    >
        <div v-if="!started" class="card text-center py-10 space-y-4">
            <p class="text-sm text-gray-600">You are about to start the online exam.</p>
            <p v-if="exam.duration_minutes" class="text-sm font-medium">Duration: {{ exam.duration_minutes }} minutes</p>
            <p class="text-sm text-gray-500">{{ questions.length }} question(s) · {{ gradableCount }} auto-graded</p>
            <p class="text-xs text-amber-700 max-w-md mx-auto">
                The timer starts when you click Start. Your answers auto-submit when time runs out.
            </p>
            <button type="button" class="btn-primary" :disabled="starting" @click="startExam">Start exam</button>
        </div>

        <form v-else @submit.prevent="requestSubmit" class="space-y-4 pb-20 lg:pb-4">
            <div
                v-if="expiresAt"
                class="sticky top-0 z-20 -mx-1 px-1 pt-1 space-y-2"
            >
                <div
                    class="card flex flex-wrap justify-between items-center gap-2 text-sm shadow-sm"
                    :class="timerUrgent ? 'border-red-300 bg-red-50' : ''"
                >
                    <span class="font-medium" :class="timerUrgent ? 'text-red-800' : ''">Time remaining</span>
                    <span class="font-semibold tabular-nums text-lg" :class="timerUrgent ? 'text-red-600' : 'text-indigo-700'">
                        {{ timerLabel }}
                    </span>
                </div>
                <div v-if="timerUrgent" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                    Less than 5 minutes left. Your exam will submit automatically when the timer reaches zero.
                </div>
            </div>

            <div class="lg:grid lg:grid-cols-[12rem_minmax(0,1fr)] lg:gap-4 lg:items-start">
                <aside class="hidden lg:block card lg:sticky lg:top-28 space-y-3">
                    <QuestionNavGrid
                        :questions="questions"
                        :answered-count="answeredCount"
                        :nav-class="questionNavClass"
                        @select="scrollToQuestion"
                    />
                </aside>

                <div class="space-y-4 min-w-0">
                    <article
                        v-for="q in questions"
                        :key="q.id"
                        :ref="el => setQuestionRef(q.id, el)"
                        class="card space-y-3 scroll-mt-36"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <h2 class="font-semibold text-sm">Q{{ q.number }}. {{ q.title || 'Question' }}</h2>
                            <button
                                v-if="q.options?.length >= 2"
                                type="button"
                                class="text-xs font-semibold px-2.5 py-1 rounded-md border transition shrink-0"
                                :class="markedForReview.has(q.id)
                                    ? 'border-amber-400 bg-amber-50 text-amber-900'
                                    : 'border-gray-200 text-gray-600 hover:border-amber-300'"
                                @click="toggleReview(q.id)"
                            >
                                {{ markedForReview.has(q.id) ? 'Marked for review' : 'Mark for review' }}
                            </button>
                        </div>
                        <p v-if="q.body_text" class="text-sm text-gray-700 whitespace-pre-wrap">{{ q.body_text }}</p>
                        <div v-if="q.options?.length" class="space-y-2">
                            <label
                                v-for="opt in q.options"
                                :key="opt.key"
                                class="flex items-start gap-2 text-sm border rounded-lg px-3 py-2 cursor-pointer hover:bg-indigo-50"
                                :class="answers[q.id] === opt.key ? 'border-indigo-400 bg-indigo-50/70' : ''"
                            >
                                <input
                                    type="radio"
                                    :name="`q-${q.id}`"
                                    :value="opt.key"
                                    v-model="answers[q.id]"
                                    class="mt-1"
                                    @change="scheduleSave"
                                >
                                <span><strong class="uppercase">{{ opt.key }}.</strong> {{ opt.label }}</span>
                            </label>
                        </div>
                        <p v-else class="text-xs text-gray-400">Reference material — no online answer required.</p>
                    </article>

                    <div class="flex flex-wrap justify-between items-center gap-3 pb-4">
                        <p class="text-xs text-gray-500">
                            {{ answeredCount }} of {{ questions.length }} answered
                            <span v-if="markedForReview.size"> · {{ markedForReview.size }} marked for review</span>
                            <span v-if="saveStatus === 'saving'" class="text-gray-400"> · Saving…</span>
                            <span v-else-if="saveStatus === 'saved'" class="text-emerald-600"> · Saved</span>
                        </p>
                        <button type="submit" class="btn-primary" :disabled="submitting">
                            {{ submitting ? 'Submitting…' : 'Submit exam' }}
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <div v-if="started" class="lg:hidden fixed bottom-0 inset-x-0 z-30 border-t border-gray-200 bg-white/95 backdrop-blur px-4 py-3">
            <button
                type="button"
                class="btn-primary w-full text-sm"
                @click="mobileDrawerOpen = true"
            >
                Questions ({{ answeredCount }}/{{ questions.length }})
            </button>
        </div>

        <div
            v-if="mobileDrawerOpen"
            class="lg:hidden fixed inset-0 z-40"
            @click.self="mobileDrawerOpen = false"
        >
            <div class="absolute inset-0 bg-black/40" @click="mobileDrawerOpen = false" />
            <div class="absolute inset-x-0 bottom-0 max-h-[70vh] rounded-t-2xl bg-white shadow-xl flex flex-col">
                <div class="flex items-center justify-between gap-2 px-4 py-3 border-b">
                    <h2 class="font-semibold text-sm">Question navigator</h2>
                    <button type="button" class="text-xs font-semibold text-gray-500 px-2 py-1" @click="mobileDrawerOpen = false">
                        Close
                    </button>
                </div>
                <div class="overflow-y-auto p-4">
                    <QuestionNavGrid
                        :questions="questions"
                        :answered-count="answeredCount"
                        :nav-class="questionNavClass"
                        @select="selectFromDrawer"
                    />
                </div>
            </div>
        </div>

        <div
            v-if="confirmOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40"
            @click.self="confirmOpen = false"
        >
            <div class="card max-w-md w-full space-y-4 shadow-xl">
                <h2 class="font-semibold text-base">Submit exam?</h2>
                <p class="text-sm text-gray-600">
                    You have answered {{ answeredCount }} of {{ gradableCount }} gradable questions.
                    <span v-if="markedForReview.size"> {{ markedForReview.size }} question(s) are marked for review.</span>
                    Once submitted, you cannot change your answers.
                </p>
                <div class="flex flex-wrap justify-end gap-2">
                    <button type="button" class="btn-secondary text-sm" @click="confirmOpen = false">Cancel</button>
                    <button type="button" class="btn-primary text-sm" :disabled="submitting" @click="submit">
                        {{ submitting ? 'Submitting…' : 'Submit now' }}
                    </button>
                </div>
            </div>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import QuestionNavGrid from '@/Components/portal/QuestionNavGrid.vue';
import { studentPortalNavItems } from '@/support/studentPortalNav.js';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    school: Object,
    student: Object,
    registration: Object,
    exam: Object,
    questions: Array,
    savedAnswers: { type: Object, default: () => ({}) },
    expiresAt: String,
    started: Boolean,
});

const answers = reactive({});
const markedForReview = ref(new Set());
const submitting = ref(false);
const starting = ref(false);
const confirmOpen = ref(false);
const mobileDrawerOpen = ref(false);
const saveStatus = ref('');
const remainingSeconds = ref(0);
const questionRefs = ref({});
let timerId = null;
let saveTimerId = null;

const gradableCount = computed(() => props.questions.filter(q => q.options?.length >= 2).length);

const navItems = computed(() => studentPortalNavItems(props.school.id));

const timerLabel = computed(() => {
    const m = Math.floor(remainingSeconds.value / 60);
    const s = remainingSeconds.value % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
});

const timerUrgent = computed(() => props.started && remainingSeconds.value > 0 && remainingSeconds.value <= 300);

const answeredCount = computed(() =>
    props.questions.filter(q => q.options?.length >= 2 && answers[q.id]).length,
);

function csrfToken() {
    return document.querySelector('meta[name=csrf-token]')?.content ?? '';
}

function restoreSavedAnswers() {
    Object.keys(answers).forEach(k => delete answers[k]);
    for (const [questionId, optionKey] of Object.entries(props.savedAnswers ?? {})) {
        answers[questionId] = optionKey;
    }
}

function questionNavClass(q) {
    if (markedForReview.value.has(q.id)) {
        return 'border-amber-400 bg-amber-100 text-amber-900';
    }
    if (answers[q.id]) {
        return 'border-indigo-400 bg-indigo-100 text-indigo-900';
    }
    return 'border-gray-200 bg-white text-gray-700 hover:border-indigo-300';
}

function setQuestionRef(id, el) {
    if (el) {
        questionRefs.value[id] = el;
    }
}

function scrollToQuestion(id) {
    questionRefs.value[id]?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function selectFromDrawer(id) {
    mobileDrawerOpen.value = false;
    scrollToQuestion(id);
}

function toggleReview(questionId) {
    const next = new Set(markedForReview.value);
    if (next.has(questionId)) {
        next.delete(questionId);
    } else {
        next.add(questionId);
    }
    markedForReview.value = next;
}

function scheduleSave() {
    if (!props.started || submitting.value) return;
    saveStatus.value = 'saving';
    if (saveTimerId) window.clearTimeout(saveTimerId);
    saveTimerId = window.setTimeout(saveDraft, 600);
}

async function saveDraft() {
    if (!props.started || submitting.value) return;
    try {
        const res = await fetch(
            `/portal/student/${props.school.id}/mcq/${props.registration.id}/save-answers`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ answers }),
            },
        );
        saveStatus.value = res.ok ? 'saved' : '';
    } catch {
        saveStatus.value = '';
    }
}

function tickTimer() {
    if (!props.expiresAt) return;
    const diff = Math.max(0, Math.floor((new Date(props.expiresAt).getTime() - Date.now()) / 1000));
    remainingSeconds.value = diff;
    if (diff === 0 && props.started && !submitting.value) {
        submit();
    }
}

function startExam() {
    starting.value = true;
    router.post(`/portal/student/${props.school.id}/mcq/${props.registration.id}/start`, {}, {
        onFinish: () => { starting.value = false; },
    });
}

function requestSubmit() {
    confirmOpen.value = true;
}

function submit() {
    if (submitting.value) return;
    confirmOpen.value = false;
    submitting.value = true;
    router.post(`/portal/student/${props.school.id}/mcq/${props.registration.id}/submit`, {
        answers,
    }, {
        preserveScroll: true,
        onFinish: () => { submitting.value = false; },
    });
}

watch(() => props.savedAnswers, restoreSavedAnswers, { deep: true });

onMounted(() => {
    restoreSavedAnswers();
    tickTimer();
    timerId = window.setInterval(tickTimer, 1000);
});

onUnmounted(() => {
    if (timerId) window.clearInterval(timerId);
    if (saveTimerId) window.clearTimeout(saveTimerId);
});
</script>
