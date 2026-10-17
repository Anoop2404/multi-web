<template>
    <div class="space-y-4">
        <form class="card space-y-3" @submit.prevent="sendBroadcast">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Message all candidates</h3>
                <p class="text-xs text-slate-500">Shown to every candidate on their next sync (within {{ paceSeconds || 10 }}–{{ (paceSeconds || 10) * 2 }} seconds).</p>
            </div>
            <textarea v-model="message" class="field" rows="2" maxlength="500" placeholder="e.g. Question 14 has a typo: read 'x = 3' as 'x = 5'." :disabled="!armed" />
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs text-slate-400">{{ message.length }}/500</span>
                <button type="submit" class="btn-primary text-sm" :disabled="!armed || !message.trim() || busy === 'broadcast'">
                    {{ busy === 'broadcast' ? 'Sending…' : 'Send message' }}
                </button>
            </div>
        </form>

        <div class="card space-y-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Answer sync pacing</h3>
                <p class="text-xs text-slate-500">
                    How often each candidate's answers are saved to the engine. Slow it down only if the engine is under strain.
                    <span v-if="paceSeconds">Now: every {{ paceSeconds }} s.</span>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="option in paceChoices"
                    :key="option.label"
                    type="button"
                    class="rounded-md border px-3 py-1.5 text-sm font-semibold transition"
                    :class="selectedPace === option.value ? 'border-[color:var(--brand-navy)] bg-[color:var(--brand-navy)] text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
                    :disabled="!armed || busy === 'pace'"
                    @click="setPace(option.value)"
                >
                    {{ option.label }}
                </button>
            </div>
        </div>

        <div v-if="endpoints.extend_all || endpoints.hold" class="card space-y-3 border-amber-100">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Incident controls</h3>
                <p class="text-xs text-slate-500">
                    For a power or network cut that hit many students. Each action is logged with your reason.
                    <span v-if="summary?.extended_minutes">Already added for everyone: {{ summary.extended_minutes }} min.</span>
                </p>
            </div>
            <form v-if="endpoints.extend_all" class="grid gap-2 sm:grid-cols-[6rem_minmax(0,1fr)_auto]" @submit.prevent="extendAll">
                <input v-model.number="extendMinutes" type="number" min="1" max="120" class="field field--sm" aria-label="Minutes to add" :disabled="!armed">
                <input v-model="extendReason" class="field field--sm" maxlength="300" placeholder="Reason (required)" :disabled="!armed">
                <button type="submit" class="btn-secondary text-sm" :disabled="!armed || !extendReason.trim() || !(extendMinutes > 0) || busy === 'extend_all'">
                    Add time for everyone
                </button>
            </form>
            <div v-if="endpoints.hold" class="flex flex-wrap items-center gap-2">
                <span class="text-sm" :class="summary?.hold ? 'font-semibold text-amber-800' : 'text-slate-600'">
                    {{ summary?.hold ? 'Auto-submit is ON HOLD — candidates past the deadline are paused.' : 'Auto-submit at the deadline is running.' }}
                </span>
                <button type="button" class="btn-secondary text-sm" :disabled="!armed || busy === 'hold'" @click="toggleHold">
                    {{ summary?.hold ? 'Resume auto-submit' : 'Hold auto-submit…' }}
                </button>
            </div>
        </div>

        <div class="card space-y-3 border-red-100">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Close exam</h3>
                <p class="text-xs text-slate-500">
                    After time is over: submits anything still open with its last saved answers and stops all logins.
                    <span v-if="unfinished !== null">{{ unfinished }} {{ unfinished === 1 ? 'attempt is' : 'attempts are' }} unfinished.</span>
                </p>
            </div>
            <button type="button" class="btn-secondary text-sm !border-red-200 !text-red-700 hover:!bg-red-50" :disabled="!armed" @click="openClose">
                Review and close…
            </button>
        </div>

        <Modal :show="closeOpen" title="Close the exam" subtitle="Check the reconciliation first" size="lg" @close="closeOpen = false">
            <div class="space-y-4">
                <p v-if="runState && runState !== 'ended'" class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    The engine says the exam is <strong>{{ runState }}</strong>, not over. Closing now ends it for everyone still writing.
                </p>
                <Reconciliation :summary="summary" :registered="registered" show-schools />
                <p v-if="unfinished" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">
                    {{ unfinished }} unfinished {{ unfinished === 1 ? 'attempt' : 'attempts' }} will be submitted with the answers saved so far.
                </p>
                <label class="flex items-start gap-2 text-sm text-slate-700">
                    <input v-model="checked" type="checkbox" class="mt-1">
                    <span>I have checked these numbers. Close the exam: no more logins or answers will be accepted.</span>
                </label>
                <p v-if="closeError" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{{ closeError }}</p>
            </div>
            <template #footer>
                <button type="button" class="btn-secondary text-sm" :disabled="busy === 'close'" @click="closeOpen = false">Cancel</button>
                <button type="button" class="btn-primary text-sm !bg-red-600 hover:!bg-red-700" :disabled="!checked || busy === 'close'" @click="closeExam">
                    {{ busy === 'close' ? 'Closing…' : 'Close exam' }}
                </button>
            </template>
        </Modal>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useConfirm } from '@/composables/useConfirm.js';
import { postJson } from './liveMonitorApi.js';
import Reconciliation from './Reconciliation.vue';

const props = defineProps({
    endpoints: { type: Object, required: true },
    summary: { type: Object, default: null },
    registered: { type: Number, default: 0 },
    armed: { type: Boolean, default: false },
    paceOptions: { type: Array, default: () => [20, 30, 60] },
});

const emit = defineEmits(['notice', 'changed']);
const { confirm, prompt } = useConfirm();

const message = ref('');
const busy = ref('');
const selectedPace = ref(null);
const closeOpen = ref(false);
const checked = ref(false);
const closeError = ref('');
const extendMinutes = ref(10);
const extendReason = ref('');

const paceSeconds = computed(() => props.summary?.pace_seconds ?? null);
const unfinished = computed(() => props.summary?.unfinished ?? null);
const runState = computed(() => props.summary?.state ?? null);
const roster = computed(() => props.summary?.counts?.roster ?? 0);
const paceChoices = computed(() => [{ label: 'Automatic', value: null }, ...props.paceOptions.map((s) => ({ label: `${s} s`, value: s }))]);

async function sendBroadcast() {
    const text = message.value.trim();
    if (!text) return;
    const ok = await confirm({
        title: 'Send to every candidate?',
        message: `This message goes to all ${roster.value} candidates on the roster:\n\n"${text}"`,
        confirmLabel: 'Send',
        destructive: false,
    });
    if (!ok) return;

    busy.value = 'broadcast';
    try {
        const json = await postJson(props.endpoints.broadcast, { message: text });
        message.value = '';
        emit('notice', { type: 'success', text: json.message });
    } catch (e) {
        emit('notice', { type: 'error', text: e.message });
    } finally {
        busy.value = '';
    }
}

async function extendAll() {
    const ok = await confirm({
        title: `Add ${extendMinutes.value} minutes for everyone?`,
        message: `Every candidate's deadline moves by ${extendMinutes.value} minutes.\n\nReason: ${extendReason.value.trim()}`,
        confirmLabel: 'Add time',
    });
    if (!ok) return;
    busy.value = 'extend_all';
    try {
        const json = await postJson(props.endpoints.extend_all, { minutes: extendMinutes.value, reason: extendReason.value.trim() });
        extendReason.value = '';
        emit('notice', { type: 'success', text: json.message });
        emit('changed');
    } catch (e) {
        emit('notice', { type: 'error', text: e.message });
    } finally {
        busy.value = '';
    }
}

async function toggleHold() {
    const turningOn = !props.summary?.hold;
    let reason = null;
    if (turningOn) {
        reason = await prompt({
            title: 'Hold automatic submission?',
            message: 'Candidates who reach their deadline are paused instead of submitted, until you resume. Use this while you decide on extra time.',
            inputLabel: 'Reason',
            inputRequired: true,
            confirmLabel: 'Hold',
        });
        if (!reason) return;
    } else if (!(await confirm({ title: 'Resume automatic submission?', message: 'Candidates already past their deadline (plus grace) are submitted within a few seconds.', confirmLabel: 'Resume' }))) {
        return;
    }
    busy.value = 'hold';
    try {
        const json = await postJson(props.endpoints.hold, { on: turningOn, reason });
        emit('notice', { type: 'success', text: json.message });
        emit('changed');
    } catch (e) {
        emit('notice', { type: 'error', text: e.message });
    } finally {
        busy.value = '';
    }
}

async function setPace(seconds) {
    busy.value = 'pace';
    try {
        const json = await postJson(props.endpoints.pace, { seconds });
        selectedPace.value = seconds;
        emit('notice', { type: 'success', text: json.message });
        emit('changed');
    } catch (e) {
        emit('notice', { type: 'error', text: e.message });
    } finally {
        busy.value = '';
    }
}

function openClose() {
    checked.value = false;
    closeError.value = '';
    closeOpen.value = true;
}

async function closeExam() {
    const n = unfinished.value ?? 0;
    const ok = await confirm({
        title: 'Close the exam now?',
        message: n
            ? `${n} unfinished ${n === 1 ? 'attempt' : 'attempts'} will be submitted with their last saved answers. Candidates can no longer log in. This cannot be undone.`
            : 'Candidates can no longer log in. This cannot be undone.',
        confirmLabel: 'Yes, close the exam',
        destructive: true,
    });
    if (!ok) return;

    busy.value = 'close';
    closeError.value = '';
    try {
        const json = await postJson(props.endpoints.close, { confirm: true });
        closeOpen.value = false;
        emit('notice', { type: 'success', text: json.message });
        emit('changed', { closed: true });
    } catch (e) {
        closeError.value = e.message;
    } finally {
        busy.value = '';
    }
}
</script>
