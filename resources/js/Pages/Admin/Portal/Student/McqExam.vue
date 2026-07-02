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

        <form v-else @submit.prevent="submit" class="space-y-4">
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
                <aside class="card lg:sticky lg:top-28 space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="font-semibold text-xs uppercase tracking-wide text-gray-500">Questions</h2>
                        <span class="text-xs text-gray-500">{{ answeredCount }}/{{ questions.length }}</span>
                    </div>
                    <div class="grid grid-cols-5 lg:grid-cols-4 gap-1.5">
                        <button
                            v-for="q in questions"
                            :key="`nav-${q.id}`"
                            type="button"
                            class="h-9 rounded-md text-xs font-semibold border transition"
                            :class="questionNavClass(q)"
                            @click="scrollToQuestion(q.id)"
                        >
                            {{ q.number }}
                        </button>
                    </div>
                    <ul class="text-[11px] text-gray-500 space-y-1 pt-1 border-t">
                        <li class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded border border-indigo-400 bg-indigo-100" /> Answered</li>
                        <li class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded border border-gray-300 bg-white" /> Not answered</li>
                    </ul>
                </aside>

                <div class="space-y-4 min-w-0">
                    <article
                        v-for="q in questions"
                        :key="q.id"
                        :ref="el => setQuestionRef(q.id, el)"
                        class="card space-y-3 scroll-mt-36"
                    >
                        <h2 class="font-semibold text-sm">Q{{ q.number }}. {{ q.title || 'Question' }}</h2>
                        <p v-if="q.body_text" class="text-sm text-gray-700 whitespace-pre-wrap">{{ q.body_text }}</p>
                        <div v-if="q.options?.length" class="space-y-2">
                            <label
                                v-for="opt in q.options"
                                :key="opt.key"
                                class="flex items-start gap-2 text-sm border rounded-lg px-3 py-2 cursor-pointer hover:bg-indigo-50"
                                :class="answers[q.id] === opt.key ? 'border-indigo-400 bg-indigo-50/70' : ''"
                            >
                                <input type="radio" :name="`q-${q.id}`" :value="opt.key" v-model="answers[q.id]" class="mt-1">
                                <span><strong class="uppercase">{{ opt.key }}.</strong> {{ opt.label }}</span>
                            </label>
                        </div>
                        <p v-else class="text-xs text-gray-400">Reference material — no online answer required.</p>
                    </article>

                    <div class="flex flex-wrap justify-between items-center gap-3 pb-4">
                        <p class="text-xs text-gray-500">{{ answeredCount }} of {{ questions.length }} answered</p>
                        <button type="submit" class="btn-primary" :disabled="submitting">
                            {{ submitting ? 'Submitting…' : 'Submit exam' }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    school: Object,
    student: Object,
    registration: Object,
    exam: Object,
    questions: Array,
    expiresAt: String,
    started: Boolean,
});

const answers = reactive({});
const submitting = ref(false);
const starting = ref(false);
const remainingSeconds = ref(0);
const questionRefs = ref({});
let timerId = null;

const gradableCount = computed(() => props.questions.filter(q => q.options?.length >= 2).length);

const navItems = computed(() => [
    { href: `/portal/student/${props.school.id}`, label: 'Dashboard' },
    { href: `/portal/student/${props.school.id}/mcq/${props.registration.id}/exam`, label: 'Exam' },
]);

const timerLabel = computed(() => {
    const m = Math.floor(remainingSeconds.value / 60);
    const s = remainingSeconds.value % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
});

const timerUrgent = computed(() => props.started && remainingSeconds.value > 0 && remainingSeconds.value <= 300);

const answeredCount = computed(() =>
    props.questions.filter(q => q.options?.length >= 2 && answers[q.id]).length,
);

function isAnswered(q) {
    return !q.options?.length || q.options.length < 2 || Boolean(answers[q.id]);
}

function questionNavClass(q) {
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

function submit() {
    if (submitting.value) return;
    submitting.value = true;
    router.post(`/portal/student/${props.school.id}/mcq/${props.registration.id}/submit`, {
        answers,
    }, {
        preserveScroll: true,
        onFinish: () => { submitting.value = false; },
    });
}

onMounted(() => {
    tickTimer();
    timerId = window.setInterval(tickTimer, 1000);
});

onUnmounted(() => {
    if (timerId) window.clearInterval(timerId);
});
</script>
