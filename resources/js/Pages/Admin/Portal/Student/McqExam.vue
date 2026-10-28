<template>
    <PortalLayout
        role-label="Student Portal"
        :title="exam.title"
        :subtitle="[school.name, student.reg_no].filter(Boolean).join(' · ')"
        accent="indigo"
        :nav-items="navItems"
    >
        <div v-if="!started" class="card py-8 px-5 space-y-4 max-w-2xl mx-auto">
            <div class="text-center space-y-2">
                <h2 class="font-semibold text-base text-slate-900">Before you start</h2>
                <p class="text-sm text-gray-600">Read these instructions, then start when you are ready.</p>
            </div>
            <ul class="text-sm text-slate-700 space-y-2 list-disc pl-5">
                <li><strong>{{ questions.length }}</strong> question(s) · <strong>{{ gradableCount }}</strong> graded · <strong>{{ totalMarks }}</strong> marks</li>
                <li v-if="exam.duration_minutes">Duration: <strong>{{ exam.duration_minutes }} minutes</strong> — timer starts when you click Start</li>
                <li v-if="exam.has_negative_marking">Some questions have <strong>negative marking</strong> for wrong answers</li>
                <li>Answers <strong>autosave</strong> while you work — keep this tab open</li>
                <li>Refresh or closing the tab may interrupt you; use <strong>Submit</strong> when finished</li>
                <li>Use a stable connection; wait for “Saved” before submitting if you see an offline warning</li>
                <li>Tab switches and leaving this window are <strong>recorded</strong> for review (not auto-disqualification)</li>
                <li v-if="exam.no_pin_required">No hall ticket or Start PIN — you start from this page</li>
                <li v-else>If your venue uses a Start PIN, an invigilator will give it on the exam site — not your portal password</li>
            </ul>
            <p v-if="pageErrors" class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2" role="alert">{{ pageErrors }}</p>
            <div class="text-center pt-2">
                <button type="button" class="btn-primary" :disabled="starting" @click="startExam">Start exam</button>
            </div>
        </div>

        <form v-else @submit.prevent="requestSubmit" class="space-y-4 pb-20 lg:pb-4">
            <div v-if="resumeBanner" class="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900">
                Continuing your attempt — {{ timerLabel }} left · answers restored
            </div>
            <div
                v-if="offline || saveStatus === 'failed'"
                class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900"
                role="status"
            >
                <span v-if="offline">You appear offline. Answers will retry when the connection returns.</span>
                <span v-else>Answers could not be saved. Check your connection — Submit stays disabled until they save.</span>
                <span v-if="lastSavedAt" class="block text-xs mt-1 opacity-80">Last saved: {{ lastSavedLabel }}</span>
            </div>
            <div
                v-else-if="lastSavedAt && saveStatus === 'saved'"
                class="text-xs text-emerald-700"
            >
                Last saved: {{ lastSavedLabel }}
            </div>

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
                <div v-if="proctorWarningVisible" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 flex justify-between gap-2 items-start">
                    <span>{{ proctorMessage }}</span>
                    <button type="button" class="text-xs font-semibold underline shrink-0" @click="proctorWarningVisible = false">Dismiss</button>
                </div>
                <div v-if="!fullscreenActive && !fullscreenDismissed" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 flex flex-wrap justify-between gap-2 items-center">
                    <span>Optional: use fullscreen for fewer distractions. Leaving fullscreen is recorded as a signal — not an automatic fail.</span>
                    <div class="flex gap-2 shrink-0">
                        <button type="button" class="btn-secondary text-xs" @click="enterFullscreen">Enter fullscreen</button>
                        <button type="button" class="text-xs font-semibold text-slate-500 underline" @click="fullscreenDismissed = true">Not now</button>
                    </div>
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
                                v-if="isAnswerable(q)"
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
                        <p class="text-xs font-medium text-slate-500">
                            {{ q.marks }} mark{{ q.marks === 1 ? '' : 's' }}
                            <span v-if="q.negative_mark > 0"> · −{{ q.negative_mark }} for a wrong answer</span>
                            <span v-if="q.type === 'multiple' && q.choose_count"> · Choose {{ q.choose_count }}</span>
                            <span v-else-if="q.type === 'multiple'"> · Select all that apply</span>
                            <span v-else-if="q.type === 'numeric'"> · Enter a number</span>
                            <span v-else-if="q.type === 'true_false'"> · True or false</span>
                        </p>
                        <McqRichText v-if="q.body_text" :text="q.body_text" tag="p" class="text-sm text-gray-700" />
                        <img v-if="q.image_url" :src="q.image_url" :alt="`Question ${q.number} image`" class="max-h-80 max-w-full rounded-lg border bg-white object-contain">

                        <!-- Numeric -->
                        <div v-if="q.type === 'numeric'" class="max-w-xs">
                            <label :for="`num-${q.id}`" class="sr-only">Numeric answer for question {{ q.number }}</label>
                            <input
                                :id="`num-${q.id}`"
                                type="text"
                                inputmode="decimal"
                                autocomplete="off"
                                class="field"
                                placeholder="e.g. 12.5"
                                :value="answers[q.id] ?? ''"
                                @input="onNumericInput(q.id, $event.target.value)"
                            >
                        </div>

                        <!-- Multiple correct -->
                        <div v-else-if="q.type === 'multiple' && q.options?.length" class="grid gap-2" :class="q.options.some(o => o.image_url) ? 'sm:grid-cols-2' : ''" role="group">
                            <label
                                v-for="opt in q.options"
                                :key="opt.key"
                                class="flex items-start gap-2 text-sm border rounded-lg px-3 py-2 cursor-pointer hover:bg-indigo-50"
                                :class="isMultiSelected(q.id, opt.key) ? 'border-indigo-400 bg-indigo-50/70' : ''"
                            >
                                <input
                                    type="checkbox"
                                    :aria-label="`Question ${q.number}, option ${(opt.display_key || opt.key).toUpperCase()}`"
                                    :checked="isMultiSelected(q.id, opt.key)"
                                    class="mt-1"
                                    @change="toggleMulti(q.id, opt.key)"
                                >
                                <span class="min-w-0 space-y-1.5">
                                    <span class="flex gap-1"><strong class="uppercase">{{ opt.display_key || opt.key }}.</strong> <McqRichText :text="opt.label" /></span>
                                    <img v-if="opt.image_url" :src="opt.image_url" :alt="`Option ${(opt.display_key || opt.key).toUpperCase()}`" class="block max-h-40 max-w-full rounded border bg-white object-contain">
                                </span>
                            </label>
                        </div>

                        <!-- Single / true-false -->
                        <div v-else-if="q.options?.length" class="grid gap-2" :class="q.options.some(o => o.image_url) ? 'sm:grid-cols-2' : ''" role="radiogroup">
                            <label
                                v-for="opt in q.options"
                                :key="opt.key"
                                class="flex items-start gap-2 text-sm border rounded-lg px-3 py-2 cursor-pointer hover:bg-indigo-50"
                                :class="answers[q.id] === opt.key ? 'border-indigo-400 bg-indigo-50/70' : ''"
                            >
                                <input
                                    type="radio"
                                    :name="`q-${q.id}`"
                                    :aria-label="`Question ${q.number}, option ${(opt.display_key || opt.key).toUpperCase()}: ${opt.label || 'Image option'}`"
                                    :value="opt.key"
                                    v-model="answers[q.id]"
                                    class="mt-1"
                                    @change="scheduleSave"
                                >
                                <span class="min-w-0 space-y-1.5">
                                    <span class="flex gap-1"><strong class="uppercase">{{ opt.display_key || opt.key }}.</strong> <McqRichText :text="opt.label" /></span>
                                    <img v-if="opt.image_url" :src="opt.image_url" :alt="`Option ${(opt.display_key || opt.key).toUpperCase()}`" class="block max-h-40 max-w-full rounded border bg-white object-contain">
                                </span>
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
                        <button type="submit" class="btn-primary" :disabled="submitting || submitBlocked">
                            {{ submitting ? 'Submitting…' : (submitBlocked ? 'Wait until answers are saved' : 'Submit exam') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <div v-if="started" class="lg:hidden fixed bottom-0 inset-x-0 z-30 border-t border-gray-200 bg-white/95 backdrop-blur px-4 py-3">
            <button type="button" class="btn-primary w-full text-sm" @click="mobileDrawerOpen = true">
                Questions ({{ answeredCount }}/{{ questions.length }})
            </button>
        </div>

        <div v-if="mobileDrawerOpen" class="lg:hidden fixed inset-0 z-40" @click.self="mobileDrawerOpen = false">
            <div class="absolute inset-0 bg-black/40" @click="mobileDrawerOpen = false" />
            <div class="absolute inset-x-0 bottom-0 max-h-[70vh] rounded-t-2xl bg-white shadow-xl flex flex-col">
                <div class="flex items-center justify-between gap-2 px-4 py-3 border-b">
                    <h2 class="font-semibold text-sm">Question navigator</h2>
                    <button type="button" class="text-xs font-semibold text-gray-500 px-2 py-1" @click="mobileDrawerOpen = false">Close</button>
                </div>
                <div class="overflow-y-auto p-4">
                    <QuestionNavGrid :questions="questions" :answered-count="answeredCount" :nav-class="questionNavClass" @select="selectFromDrawer" />
                </div>
            </div>
        </div>

        <div v-if="confirmOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" @click.self="confirmOpen = false">
            <div
                ref="confirmPanelRef"
                class="card max-w-md w-full space-y-4 shadow-xl"
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="submit-exam-title"
                aria-describedby="submit-exam-desc"
                tabindex="-1"
                @keydown="onConfirmKeydown"
            >
                <h2 id="submit-exam-title" class="font-semibold text-base">Submit exam?</h2>
                <p id="submit-exam-desc" class="text-sm text-gray-600">
                    You have answered {{ answeredCount }} of {{ gradableCount }} gradable questions.
                    <span v-if="markedForReview.size"> {{ markedForReview.size }} question(s) are marked for review.</span>
                    Once submitted, you cannot change your answers.
                </p>
                <div class="flex flex-wrap justify-end gap-2">
                    <button ref="confirmCancelRef" type="button" class="btn-secondary text-sm" @click="confirmOpen = false">Cancel</button>
                    <button type="button" class="btn-primary text-sm" :disabled="submitting || submitBlocked" @click="submit">
                        {{ submitting ? 'Submitting…' : 'Submit now' }}
                    </button>
                </div>
            </div>
        </div>
    </PortalLayout>
</template>

<script setup>
import McqRichText from '@/Components/mcq/McqRichText.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import QuestionNavGrid from '@/Components/portal/QuestionNavGrid.vue';
import { studentPortalNavItems } from '@/support/studentPortalNav.js';
import { computed, nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

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

const page = usePage();
const answers = reactive({});
const markedForReview = ref(new Set());
const submitting = ref(false);
const starting = ref(false);
const confirmOpen = ref(false);
const mobileDrawerOpen = ref(false);
const saveStatus = ref('');
const lastSavedAt = ref(null);
const offline = ref(typeof navigator !== 'undefined' ? !navigator.onLine : false);
const remainingSeconds = ref(props.expiresAt ? Math.max(0, Math.ceil((new Date(props.expiresAt).getTime() - Date.now()) / 1000)) : 0);
const questionRefs = ref({});
const proctorWarningVisible = ref(false);
const proctorEventCount = ref(0);
const resumeBanner = ref(false);
const fullscreenActive = ref(false);
const fullscreenDismissed = ref(false);
const confirmPanelRef = ref(null);
const confirmCancelRef = ref(null);
let timerId = null;
let autoSubmitRequested = false;
let saveTimerId = null;
let lastProctorEventAt = 0;
let dirtySinceSave = false;
const PROCTOR_EVENT_THROTTLE_MS = 2000;

const totalMarks = computed(() => props.questions.reduce((total, q) => total + Number(q.marks || 0), 0));
const gradableCount = computed(() => props.questions.filter(isAnswerable).length);
const navItems = computed(() => studentPortalNavItems(props.school.id));
const pageErrors = computed(() => {
    const errs = page.props.errors || {};
    const first = Object.values(errs).flat()[0];
    return first ? String(first) : null;
});

const timerLabel = computed(() => {
    const m = Math.floor(remainingSeconds.value / 60);
    const s = remainingSeconds.value % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
});
const timerUrgent = computed(() => props.started && remainingSeconds.value > 0 && remainingSeconds.value <= 300);
const lastSavedLabel = computed(() => {
    if (!lastSavedAt.value) return '';
    try {
        return new Date(lastSavedAt.value).toLocaleTimeString();
    } catch {
        return '';
    }
});
const submitBlocked = computed(() => dirtySinceSave && (saveStatus.value === 'failed' || saveStatus.value === 'saving' || offline.value));
const proctorMessage = computed(() => {
    if (proctorEventCount.value <= 1) {
        return 'Leaving this tab during the exam is being recorded for review — not an automatic fail.';
    }
    return `${proctorEventCount.value} leave/blur signals recorded. Stay on this tab until you submit.`;
});

const answeredCount = computed(() => props.questions.filter((q) => isAnswerable(q) && hasAnswer(q.id)).length);

function isAnswerable(q) {
    return q.type === 'numeric' || (q.options?.length >= 2);
}

function hasAnswer(id) {
    const v = answers[id];
    if (Array.isArray(v)) return v.length > 0;
    return v != null && String(v).trim() !== '';
}

function isMultiSelected(qid, key) {
    const v = answers[qid];
    return Array.isArray(v) && v.includes(key);
}

function toggleMulti(qid, key) {
    const current = Array.isArray(answers[qid]) ? [...answers[qid]] : [];
    const idx = current.indexOf(key);
    if (idx >= 0) current.splice(idx, 1);
    else current.push(key);
    answers[qid] = current.sort();
    scheduleSave();
}

function onNumericInput(qid, raw) {
    answers[qid] = String(raw ?? '');
    scheduleSave();
}

function csrfToken() {
    return document.querySelector('meta[name=csrf-token]')?.content ?? '';
}

function restoreSavedAnswers() {
    Object.keys(answers).forEach((k) => delete answers[k]);
    for (const [questionId, value] of Object.entries(props.savedAnswers ?? {})) {
        if (Array.isArray(value)) {
            answers[questionId] = [...value];
        } else if (typeof value === 'string' && value.includes(',')) {
            answers[questionId] = value.split(',').map((s) => s.trim()).filter(Boolean);
        } else if (value != null && value !== '') {
            answers[questionId] = value;
        }
    }
}

function questionNavClass(q) {
    if (markedForReview.value.has(q.id)) return 'border-amber-400 bg-amber-100 text-amber-900';
    if (hasAnswer(q.id)) return 'border-indigo-400 bg-indigo-100 text-indigo-900';
    return 'border-gray-200 bg-white text-gray-700 hover:border-indigo-300';
}

function setQuestionRef(id, el) {
    if (el) questionRefs.value[id] = el;
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
    if (next.has(questionId)) next.delete(questionId);
    else next.add(questionId);
    markedForReview.value = next;
    try { sessionStorage.setItem(`mcq-review-${props.school.id}-${props.registration.id}`, JSON.stringify([...next])); } catch { /* optional */ }
}

function scheduleSave() {
    if (!props.started || submitting.value) return;
    dirtySinceSave = true;
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
        if (res.ok) {
            saveStatus.value = 'saved';
            dirtySinceSave = false;
            const body = await res.json().catch(() => ({}));
            lastSavedAt.value = body.saved_at || new Date().toISOString();
        } else {
            saveStatus.value = 'failed';
        }
    } catch {
        saveStatus.value = 'failed';
    }
}

async function reportProctorEvent(type) {
    if (!props.started || submitting.value) return;
    const now = Date.now();
    if (now - lastProctorEventAt < PROCTOR_EVENT_THROTTLE_MS) return;
    lastProctorEventAt = now;
    proctorEventCount.value += 1;
    proctorWarningVisible.value = true;
    try {
        await fetch(
            `/portal/student/${props.school.id}/mcq/${props.registration.id}/proctor-event`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({ type }),
            },
        );
    } catch { /* best-effort */ }
}

function handleVisibilityChange() {
    if (document.visibilityState === 'hidden') reportProctorEvent('tab_hidden');
}
function handleWindowBlur() {
    reportProctorEvent('window_blur');
}
function handleOnline() {
    offline.value = false;
    if (dirtySinceSave) scheduleSave();
}
function handleOffline() {
    offline.value = true;
}
function handleBeforeUnload(e) {
    if (!props.started || submitting.value) return;
    e.preventDefault();
    e.returnValue = 'Your exam is in progress. Answers already saved will be kept, but leaving may interrupt you.';
}

function tickTimer() {
    if (!props.expiresAt) return;
    const diff = Math.max(0, Math.ceil((new Date(props.expiresAt).getTime() - Date.now()) / 1000));
    remainingSeconds.value = diff;
    if (diff === 0 && props.started && !submitting.value && !autoSubmitRequested) {
        autoSubmitRequested = true;
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
    if (submitBlocked.value) return;
    confirmOpen.value = true;
    nextTick(() => confirmCancelRef.value?.focus?.());
}

function onConfirmKeydown(e) {
    if (e.key === 'Escape') {
        e.preventDefault();
        confirmOpen.value = false;
        return;
    }
    if (e.key !== 'Tab' || ! confirmPanelRef.value) return;
    const nodes = [...confirmPanelRef.value.querySelectorAll('button:not([disabled])')];
    if (! nodes.length) return;
    const first = nodes[0];
    const last = nodes[nodes.length - 1];
    if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
    } else if (! e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
    }
}

async function enterFullscreen() {
    try {
        await document.documentElement.requestFullscreen?.();
        fullscreenActive.value = true;
        fullscreenDismissed.value = true;
    } catch {
        fullscreenDismissed.value = true;
    }
}

function handleFullscreenChange() {
    const nowFs = !!document.fullscreenElement;
    if (fullscreenActive.value && ! nowFs && props.started && ! submitting.value) {
        reportProctorEvent('fullscreen_exit');
    }
    fullscreenActive.value = nowFs;
}

function submit() {
    if (submitting.value || submitBlocked.value) return;
    confirmOpen.value = false;
    submitting.value = true;
    router.post(`/portal/student/${props.school.id}/mcq/${props.registration.id}/submit`, {
        answers,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            try { sessionStorage.removeItem(`mcq-review-${props.school.id}-${props.registration.id}`); } catch { /* */ }
        },
        onFinish: () => { submitting.value = false; },
    });
}

watch(() => props.savedAnswers, restoreSavedAnswers, { deep: true });
watch(() => props.expiresAt, tickTimer);

onMounted(() => {
    restoreSavedAnswers();
    if (props.started && props.registration?.started_at) {
        resumeBanner.value = true;
        window.setTimeout(() => { resumeBanner.value = false; }, 8000);
    }
    try {
        const ids = JSON.parse(sessionStorage.getItem(`mcq-review-${props.school.id}-${props.registration.id}`) || '[]');
        markedForReview.value = new Set(Array.isArray(ids) ? ids.filter((id) => props.questions.some((q) => q.id === id)) : []);
    } catch { /* */ }
    tickTimer();
    timerId = window.setInterval(tickTimer, 1000);
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('blur', handleWindowBlur);
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
    window.addEventListener('beforeunload', handleBeforeUnload);
    document.addEventListener('fullscreenchange', handleFullscreenChange);
    fullscreenActive.value = !!document.fullscreenElement;
});

onUnmounted(() => {
    if (timerId) window.clearInterval(timerId);
    if (saveTimerId) window.clearTimeout(saveTimerId);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    window.removeEventListener('blur', handleWindowBlur);
    window.removeEventListener('online', handleOnline);
    window.removeEventListener('offline', handleOffline);
    window.removeEventListener('beforeunload', handleBeforeUnload);
    document.removeEventListener('fullscreenchange', handleFullscreenChange);
});
</script>
