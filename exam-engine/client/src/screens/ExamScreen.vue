<template>
    <div class="exam" :style="{ '--q-size': fontSize + 'rem' }">
        <header class="topbar">
            <div class="title">
                <b>{{ st.title }}</b>
                <span>{{ st.name }} · {{ st.hall_ticket }}</span>
            </div>
            <div class="timer" :class="timerClass" role="timer" :aria-label="`Time left ${formatCountdown(msLeft)}`">{{ formatCountdown(msLeft) }}</div>
            <button class="icon-btn only-mobile" type="button" aria-haspopup="dialog" :aria-expanded="drawer" @click="drawer = true">
                <span aria-hidden="true">☰</span><span class="sr-only">Question list</span>
            </button>
        </header>

        <div class="statusbar" role="status" aria-live="polite">
            <span class="pill" :class="syncPill.cls"><span class="dot" />{{ syncPill.text }}</span>
            <span class="faint">{{ answered }}/{{ questions.length }} answered</span>
            <span class="spacer" />
            <button v-if="canFullscreen && !status.fullscreen" class="btn ghost hide-narrow" style="min-height: 36px; padding: 4px 10px" type="button" @click="goFullscreen">
                Full screen
            </button>
        </div>

        <div v-if="!status.online || status.sync === 'offline'" class="banner offline" role="status">
            <span aria-hidden="true">⚠</span>
            <span><b>Offline — answers saved on this device.</b> Keep writing; they’ll be sent when the connection is back.</span>
        </div>
        <div v-if="message" class="banner" role="alert">
            <span aria-hidden="true">📢</span>
            <span style="flex: 1"><b>Message from the exam team:</b> {{ message.text }}</span>
            <button class="btn" style="min-height: 36px; padding: 4px 12px" type="button" @click="s.dismissMessage(message.id)">OK</button>
        </div>
        <div v-if="status.storageWarning" class="banner" role="alert">
            <span>This browser couldn’t save answers on the device. Stay online and don’t close the page.</span>
        </div>

        <div class="exam-body">
            <main class="qpane">
                <template v-if="q">
                    <p v-if="q.section" class="section-title">{{ q.section }}</p>
                    <div class="qhead">
                        <h2 :id="headingId" ref="heading" tabindex="-1">Question {{ q.number }} <span class="faint">of {{ questions.length }}</span></h2>
                        <span class="marks">{{ marksText(q) }}</span>
                        <span v-if="flagged" class="pill" style="background: var(--flag-soft); color: var(--flag)">Marked for review</span>
                    </div>
                    <QuestionView
                        :key="q.qid"
                        :question="q"
                        :value="ledger.answers[q.qid]"
                        :disabled="!canAnswer"
                        :heading-id="headingId"
                        @answer="(v) => s.setAnswer(q!.qid, v)"
                        @toggle="(key) => s.toggleOption(q!.qid, key)"
                        @zoom="(src) => (zoomSrc = src)"
                    />
                    <div class="qtools">
                        <button class="btn" type="button" :class="{ flagged }" :aria-pressed="flagged" :disabled="!!status.submitted" @click="s.setFlag(q.qid, !flagged)">
                            {{ flagged ? '★ Marked for review' : '☆ Mark for review' }}
                        </button>
                        <button v-if="ledger.isAnswered(q.qid)" class="btn" type="button" :disabled="!canAnswer" @click="s.setAnswer(q.qid, null)">Clear answer</button>
                    </div>
                </template>
            </main>

            <aside class="palette" aria-label="Question list">
                <PalettePanel
                    :questions="questions"
                    :current="index"
                    :is-answered="isAnswered"
                    :is-flagged="isFlagged"
                    :answered="answered"
                    :flagged-count="flaggedCount"
                    :disabled="!canSubmit"
                    @go="go"
                    @submit="openReview"
                    @font="changeFont"
                />
            </aside>
        </div>

        <nav class="navbar" aria-label="Question navigation">
            <div class="inner">
                <button class="btn" type="button" :disabled="index === 0" @click="go(index - 1)">← Previous</button>
                <button class="btn only-mobile" type="button" @click="drawer = true">{{ index + 1 }}/{{ questions.length }}</button>
                <button v-if="index < questions.length - 1" class="btn primary" type="button" style="grid-column: 3" @click="go(index + 1)">Next →</button>
                <button v-else class="btn primary" type="button" style="grid-column: 3" :disabled="!canSubmit" @click="openReview">Review &amp; submit</button>
            </div>
        </nav>

        <div v-if="drawer" class="drawer" @click.self="drawer = false" @keydown.esc="drawer = false">
            <div class="panel" role="dialog" aria-modal="true" aria-label="Question list">
                <PalettePanel
                    :questions="questions"
                    :current="index"
                    :is-answered="isAnswered"
                    :is-flagged="isFlagged"
                    :answered="answered"
                    :flagged-count="flaggedCount"
                    :disabled="!canSubmit"
                    :can-fullscreen="canFullscreen && !status.fullscreen"
                    closable
                    @go="(i) => { go(i); drawer = false; }"
                    @close="drawer = false"
                    @submit="drawer = false; openReview()"
                    @font="changeFont"
                    @fullscreen="drawer = false; goFullscreen()"
                />
            </div>
        </div>

        <Modal v-if="review" title="Submit your exam?" @close="review = false">
            <p>You have answered <b>{{ answered }}</b> of <b>{{ questions.length }}</b> questions.</p>
            <div v-if="unanswered.length" style="margin-top: 12px">
                <p class="muted" style="font-weight: 600">Not answered ({{ unanswered.length }})</p>
                <div class="palette-grid" style="margin-top: 6px">
                    <button v-for="n in unanswered" :key="'u' + n" class="pbtn" type="button" :aria-label="`Go to question ${n}`" @click="jumpFromReview(n)">{{ n }}</button>
                </div>
            </div>
            <div v-if="marked.length" style="margin-top: 12px">
                <p class="muted" style="font-weight: 600">Marked for review ({{ marked.length }})</p>
                <div class="palette-grid" style="margin-top: 6px">
                    <button v-for="n in marked" :key="'m' + n" class="pbtn flagged" :class="{ answered: isAnswered(questions[n - 1].qid) }" type="button" :aria-label="`Go to question ${n}`" @click="jumpFromReview(n)">{{ n }}</button>
                </div>
            </div>
            <p class="notice warn" style="margin-top: 14px">After you submit, you can’t change your answers.</p>
            <template #actions>
                <button class="btn" type="button" @click="review = false">Keep writing</button>
                <button class="btn danger" type="button" :disabled="!canSubmit" @click="confirmSubmit">Submit now</button>
            </template>
        </Modal>

        <div v-if="zoomSrc" class="zoom" role="dialog" aria-modal="true" aria-label="Enlarged picture" @click="zoomSrc = null" @keydown.esc="zoomSrc = null">
            <img :src="zoomSrc" alt="Enlarged picture" />
            <button class="btn" type="button" @click.stop="zoomSrc = null">Close</button>
        </div>

        <!-- Blocking states -->
        <div v-if="status.locked && !status.submitted" class="blocker" role="alertdialog" aria-modal="true" aria-labelledby="lock-title">
            <div class="card stack">
                <div class="row" style="justify-content: center"><span class="spinner" aria-hidden="true" /></div>
                <h2 id="lock-title">Paused by the invigilator</h2>
                <p class="muted">Your answers are safe. Please wait — the exam will continue here automatically.</p>
            </div>
        </div>
        <div v-else-if="status.timeUp || status.submitting" class="blocker" role="alertdialog" aria-modal="true" aria-labelledby="sub-title">
            <div class="card stack">
                <div class="row" style="justify-content: center"><span class="spinner" aria-hidden="true" /></div>
                <h2 id="sub-title">{{ status.timeUp && !manualSubmit ? 'Time is up' : 'Submitting your exam' }}</h2>
                <p class="muted">{{ status.submitNote || 'Submitting your answers…' }}</p>
                <p class="faint">Please keep this page open until you see your receipt.</p>
            </div>
        </div>

        <Watermark :name="st.name" :hall-ticket="st.hall_ticket" />
        <p class="sr-only" aria-live="assertive">{{ announcement }}</p>
    </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Modal from '../components/Modal.vue';
import PalettePanel from '../components/PalettePanel.vue';
import QuestionView from '../components/QuestionView.vue';
import Watermark from '../components/Watermark.vue';
import { app, finishToResult, paper, session, signedOutHere } from '../app';
import { formatCountdown, parseTime } from '../lib/clock';
import { useServerNow } from '../lib/useNow';
import type { DisplayQuestion } from '../lib/paper';
import type { ExamSession } from '../lib/session';
import type { ExamState } from '../lib/types';

const s = session.value as ExamSession;
const status = s.status;
const ledger = s.ledger;
const st = computed(() => app.state as ExamState);
const questions = computed(() => paper.value ?? []);

const posKey = `sxe:pos:${s.run}:${s.regId}`;
const index = ref(readPos());
const q = computed<DisplayQuestion | undefined>(() => questions.value[index.value]);
const headingId = 'qheading';
const heading = ref<HTMLElement | null>(null);
const drawer = ref(false);
const review = ref(false);
const zoomSrc = ref<string | null>(null);
const manualSubmit = ref(false);
const fontSize = ref(readFont());

const now = useServerNow();
const msLeft = computed(() => (parseTime(status.deadline) ?? Infinity) - now.value);
const timerClass = computed(() => (msLeft.value <= 60000 ? 'bad' : msLeft.value <= 300000 ? 'warn' : ''));

const canAnswer = computed(() => !status.locked && !status.timeUp && !status.submitting && !status.submitted && !status.signedOut);
const canSubmit = computed(() => canAnswer.value);
const flagged = computed(() => (q.value ? ledger.flagged.includes(q.value.qid) : false));
const answered = computed(() => questions.value.filter((x) => isAnswered(x.qid)).length);
const flaggedCount = computed(() => questions.value.filter((x) => ledger.flagged.includes(x.qid)).length);
const unanswered = computed(() => questions.value.filter((x) => !isAnswered(x.qid)).map((x) => x.number));
const marked = computed(() => questions.value.filter((x) => ledger.flagged.includes(x.qid)).map((x) => x.number));
const message = computed(() => {
    const seen = status.seenMessageIds;
    const unseen = status.messages.filter((m) => !seen.includes(m.id));
    return unseen.length ? unseen[unseen.length - 1] : null;
});

const syncPill = computed(() => {
    switch (status.sync) {
        case 'saved':
            return { cls: 'ok', text: status.lastSavedAt ? 'All answers saved' : 'Saved on this device' };
        case 'saving':
            return { cls: '', text: 'Saving…' };
        case 'pending':
            return { cls: '', text: 'Saved on this device' };
        case 'offline':
            return { cls: 'warn', text: 'Offline — saved on this device' };
        default:
            return { cls: 'warn', text: 'Server busy — retrying' };
    }
});

const canFullscreen = typeof document.documentElement.requestFullscreen === 'function' && document.fullscreenEnabled !== false;

function isAnswered(qid: string) {
    return ledger.isAnswered(qid);
}
function isFlagged(qid: string) {
    return ledger.flagged.includes(qid);
}

function marksText(x: DisplayQuestion) {
    const plus = `+${x.marks} mark${x.marks === 1 ? '' : 's'}`;
    return x.negative_mark ? `${plus}, −${x.negative_mark} if wrong` : plus;
}

function go(i: number) {
    if (i < 0 || i >= questions.value.length) return;
    index.value = i;
}

watch(index, async (i) => {
    try {
        window.localStorage.setItem(posKey, String(i));
    } catch {
        /* ignore */
    }
    window.scrollTo(0, 0);
    await nextTick();
    heading.value?.focus({ preventScroll: true } as FocusOptions);
});

function readPos(): number {
    try {
        const n = Number(window.localStorage.getItem(posKey));
        return Number.isInteger(n) && n >= 0 && n < (paper.value?.length ?? 0) ? n : 0;
    } catch {
        return 0;
    }
}

function readFont(): number {
    try {
        const n = Number(window.localStorage.getItem('sxe:font'));
        return n >= 0.875 && n <= 1.5 ? n : 1.0625;
    } catch {
        return 1.0625;
    }
}

function changeFont(delta: number) {
    fontSize.value = Math.min(1.5, Math.max(0.875, Math.round((fontSize.value + delta * 0.125) * 1000) / 1000));
    try {
        window.localStorage.setItem('sxe:font', String(fontSize.value));
    } catch {
        /* ignore */
    }
}

function openReview() {
    if (!canSubmit.value) return;
    review.value = true;
}

function jumpFromReview(n: number) {
    review.value = false;
    go(n - 1);
}

function confirmSubmit() {
    review.value = false;
    manualSubmit.value = true;
    s.submit('manual');
}

function goFullscreen() {
    document.documentElement.requestFullscreen?.().catch(() => undefined);
}

// Announce time milestones to screen readers (not every second).
const announcement = ref('');
let announced = new Set<number>();
watch(msLeft, (ms) => {
    for (const mins of [10, 5, 1]) {
        if (ms <= mins * 60000 && ms > (mins * 60000 - 5000) && !announced.has(mins)) {
            announced.add(mins);
            announcement.value = `${mins} minute${mins === 1 ? '' : 's'} left.`;
        }
    }
});

watch(
    () => status.submitted,
    (done) => {
        if (done) finishToResult();
    },
);
watch(
    () => status.signedOut,
    (out) => {
        if (out) signedOutHere();
    },
);

function onKey(e: KeyboardEvent) {
    if (review.value || drawer.value || zoomSrc.value || e.altKey || e.ctrlKey || e.metaKey) return;
    const t = e.target as HTMLElement | null;
    const tag = t?.tagName;
    if (tag === 'TEXTAREA' || (tag === 'INPUT' && (t as HTMLInputElement).type !== 'radio' && (t as HTMLInputElement).type !== 'checkbox')) return;
    if (tag === 'INPUT' && (e.key === 'ArrowLeft' || e.key === 'ArrowRight')) return; // radios use arrows natively
    if (e.key === 'ArrowRight' || e.key === 'PageDown') {
        go(index.value + 1);
        e.preventDefault();
    } else if (e.key === 'ArrowLeft' || e.key === 'PageUp') {
        go(index.value - 1);
        e.preventDefault();
    }
}

onMounted(() => {
    window.addEventListener('keydown', onKey);
    if (status.submitted) finishToResult();
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    announced = new Set();
});
</script>
