<template>
    <main class="page">
        <BrandHeader :name="st?.sahodaya_name" :sub="st?.title" :logo="st?.logo_url" :code="app.code" />
        <section class="card stack center" aria-labelledby="done-title">
            <div class="checks" aria-hidden="true" style="display: flex; justify-content: center">
                <span class="icon ok" style="width: 56px; height: 56px; font-size: 1.75rem; border-radius: 50%; display: grid; place-items: center">✓</span>
            </div>
            <h1 id="done-title">Your answers are submitted</h1>
            <p class="muted">{{ st?.name }} · Hall ticket {{ st?.hall_ticket }}</p>
            <p v-if="submittedAt" class="faint">Submitted {{ formatLocalTime(submittedAt) }}</p>
            <div v-if="receipt">
                <p class="faint" style="margin-bottom: 6px">Your receipt number</p>
                <div class="receipt" aria-label="Receipt number">{{ receipt }}</div>
                <p class="faint" style="margin-top: 6px">Note it down or take a screenshot — it proves your exam was received.</p>
            </div>
        </section>

        <section class="card stack center" aria-live="polite" aria-labelledby="result-title">
            <h2 id="result-title">Result</h2>
            <template v-if="result.status === 'ready'">
                <div class="score">
                    {{ fmt(result.score) }}<span class="muted" style="font-size: 1.25rem"> / {{ fmt(result.max_score) }}</span>
                </div>
                <p v-if="result.percentage !== undefined && result.percentage !== null" class="muted">{{ fmt(result.percentage) }}%<template v-if="result.grade"> · Grade {{ result.grade }}</template></p>
                <p class="faint">Final results, ranks and certificates are published by your Sahodaya.</p>
            </template>
            <template v-else-if="result.status === 'pending' && !gaveUp">
                <p class="row muted" style="justify-content: center"><span class="spinner" aria-hidden="true" /> Your score is being calculated…</p>
            </template>
            <template v-else-if="result.status === 'pending'">
                <p class="muted">Your score isn’t ready yet. Check back a little later.</p>
                <button class="btn" type="button" @click="restart">Check again</button>
            </template>
            <template v-else-if="result.status === 'error'">
                <p class="muted">Couldn’t load the result right now.</p>
                <button class="btn" type="button" @click="restart">Try again</button>
            </template>
            <template v-else-if="result.status === 'hidden'">
                <p class="muted">Your answers are submitted. Results will be published by your Sahodaya.</p>
            </template>
            <template v-else>
                <p class="row muted" style="justify-content: center"><span class="spinner" aria-hidden="true" /> Loading…</p>
            </template>
        </section>

        <p class="center" style="margin-top: 18px">
            <button class="btn" type="button" @click="logout">Sign out</button>
        </p>
    </main>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import BrandHeader from '../components/BrandHeader.vue';
import { app, logout } from '../app';
import { api, ApiError } from '../lib/api';
import { formatLocalTime } from '../lib/clock';
import type { ResultResponse } from '../lib/types';

const st = computed(() => app.state);
const receipt = computed(() => st.value?.receipt ?? null);
const submittedAt = computed(() => st.value?.submitted_at ?? null);

const result = reactive<{ status: ResultResponse['status'] | 'loading' | 'error' } & Omit<ResultResponse, 'status'>>({ status: 'loading' });
const gaveUp = ref(false);
let timer: ReturnType<typeof setTimeout> | null = null;
let startedAt = 0;
const POLL_MS = 10000;
const MAX_MS = 10 * 60000;

function fmt(n: number | undefined | null) {
    if (n === undefined || n === null || !Number.isFinite(n)) return '–';
    return Math.round(n * 100) / 100;
}

async function poll() {
    try {
        const r = await api<ResultResponse>('GET', '/api/result', undefined, { timeoutMs: 15000 });
        Object.assign(result, r);
    } catch (e) {
        if (e instanceof ApiError && e.code === 'unauthorized') {
            result.status = 'hidden';
            return;
        }
        if (result.status === 'loading') result.status = 'error';
    }
    if (result.status === 'pending' || result.status === 'error') {
        if (Date.now() - startedAt < MAX_MS) timer = setTimeout(poll, POLL_MS);
        else gaveUp.value = true;
    }
}

function restart() {
    if (timer) clearTimeout(timer);
    gaveUp.value = false;
    startedAt = Date.now();
    poll();
}

onMounted(() => {
    startedAt = Date.now();
    poll();
});
onBeforeUnmount(() => {
    if (timer) clearTimeout(timer);
});
</script>
