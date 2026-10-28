<template>
    <main class="page">
        <BrandHeader :name="info?.name ?? (loadingInfo ? 'Loading…' : app.code)" :sub="'Online exam · ' + (app.code ?? '')" :logo="info?.logo_url" :code="app.code" />

        <div v-if="app.notice" class="notice warn" role="alert" style="margin-bottom: 14px">{{ app.notice }}</div>

        <div v-if="infoError" class="card stack" role="alert">
            <h1>{{ infoError.title }}</h1>
            <p class="muted">{{ infoError.text }}</p>
            <div class="row wrap">
                <button v-if="infoError.retry" class="btn primary" type="button" @click="loadInfo">Try again</button>
                <button class="btn" type="button" @click="changeCode">Change code</button>
            </div>
        </div>

        <template v-else>
            <section v-if="info && info.runs.length" class="card" aria-label="Exams">
                <h2 style="font-size: 1rem; margin-bottom: 8px">Exams open for sign-in</h2>
                <ul class="checks">
                    <li v-for="r in info.runs" :key="r.run">
                        <div>
                            <div style="font-weight: 600">{{ r.title }}</div>
                            <div class="faint">Starts {{ formatLocalTime(r.starts_at) }}</div>
                        </div>
                        <span class="spacer" />
                        <span class="pill" :class="r.state === 'live' ? 'ok' : r.state === 'ended' ? 'bad' : ''">{{ runStateLabel(r.state) }}</span>
                    </li>
                </ul>
            </section>

            <form class="card" novalidate @submit.prevent="submit">
                <h1 style="margin-bottom: 14px">Sign in to your exam</h1>
                <div v-if="showTabs" class="tabs" role="tablist" aria-label="Sign-in method">
                    <button id="tab-password" type="button" role="tab" :aria-selected="method === 'password'" aria-controls="panel-login" @click="setMethod('password')">
                        Student ID / username<br />+ password
                    </button>
                    <button id="tab-passcode" type="button" role="tab" :aria-selected="method === 'passcode'" aria-controls="panel-login" @click="setMethod('passcode')">
                        Hall ticket no.<br />+ exam passcode
                    </button>
                </div>

                <div id="panel-login" role="tabpanel" :aria-labelledby="method === 'password' ? 'tab-password' : 'tab-passcode'">
                    <template v-if="method === 'password'">
                        <label class="field">
                            <span>Student ID, username or email</span>
                            <input
                                v-model.trim="username"
                                class="input"
                                name="username"
                                autocomplete="username"
                                autocapitalize="none"
                                spellcheck="false"
                                required
                            />
                        </label>
                        <label class="field">
                            <span>Password</span>
                            <div class="row" style="gap: 8px">
                                <input
                                    v-model="password"
                                    class="input"
                                    name="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    autocomplete="current-password"
                                    required
                                    aria-describedby="password-help"
                                />
                                <button class="icon-btn" type="button" :aria-pressed="showPassword" @click="showPassword = !showPassword">
                                    {{ showPassword ? 'Hide' : 'Show' }}
                                </button>
                            </div>
                        </label>
                        <small id="password-help" class="help">Same as your student portal password.</small>
                    </template>
                    <template v-else>
                        <label class="field">
                            <span>Hall ticket number</span>
                            <input
                                v-model.trim="hallTicket"
                                class="input"
                                name="hall_ticket"
                                autocomplete="off"
                                autocapitalize="characters"
                                spellcheck="false"
                                inputmode="text"
                                required
                            />
                        </label>
                        <label class="field">
                            <span>Exam passcode</span>
                            <input
                                :value="passcode"
                                class="input code"
                                name="passcode"
                                autocomplete="one-time-code"
                                autocapitalize="characters"
                                spellcheck="false"
                                placeholder="XXXXX-XXXXX"
                                maxlength="16"
                                required
                                aria-describedby="passcode-help"
                                @input="onPasscode"
                            />
                        </label>
                        <small id="passcode-help" class="help">
                            10 letters and numbers printed on your hall ticket (exam passcode for sign-in).
                            This is not the Start PIN an invigilator may give later.
                            <template v-if="lookalike"> Passcodes never use I, L, O, 0 or 1 — check those characters.</template>
                        </small>
                    </template>
                </div>

                <div v-if="error" class="notice" :class="error.kind" role="alert" aria-live="assertive" style="margin-top: 16px">
                    {{ error.text }}
                    <span v-if="countdown > 0"> ({{ countdown }} s)</span>
                </div>

                <div style="margin-top: 18px" class="stack">
                    <button class="btn primary block big" type="submit" :disabled="busy || !canSubmit">
                        <span v-if="busy" class="spinner" aria-hidden="true" />
                        {{ busy ? (retrying ? 'Waiting for a free slot…' : 'Signing in…') : 'Sign in' }}
                    </button>
                    <button v-if="retrying" class="btn block" type="button" @click="cancelRetry">Cancel</button>
                </div>
            </form>

            <section class="card" style="margin-top: 14px" aria-label="Before you sign in">
                <h2 style="font-size: 0.95rem; margin-bottom: 8px">Quick checklist</h2>
                <ul class="checks">
                    <li>
                        <span class="icon ok" aria-hidden="true">✓</span>
                        <div>
                            <div style="font-weight: 600">Chrome or Edge</div>
                            <div class="faint">Use a current desktop browser — avoid outdated or unsupported apps.</div>
                        </div>
                    </li>
                    <li>
                        <span class="icon ok" aria-hidden="true">✓</span>
                        <div>
                            <div style="font-weight: 600">Stable power</div>
                            <div class="faint">Plug in the device; don’t rely on a low battery mid-exam.</div>
                        </div>
                    </li>
                    <li>
                        <span class="icon ok" aria-hidden="true">✓</span>
                        <div>
                            <div style="font-weight: 600">One device only</div>
                            <div class="faint">Sign in and stay on this computer for the whole exam.</div>
                        </div>
                    </li>
                </ul>
            </section>

            <p class="center faint" style="margin-top: 16px">
                Not your Sahodaya? <a href="/" @click.prevent="changeCode">Change code</a>
            </p>
        </template>
    </main>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import BrandHeader from '../components/BrandHeader.vue';
import { app, changeCode, enterState, fetchSahodaya } from '../app';
import { api, ApiError } from '../lib/api';
import { formatLocalTime } from '../lib/clock';
import { formatPasscode, hasLookalikes, isCompletePasscode, normalisePasscode } from '../lib/passcode';
import type { ExamState, LoginRequest, RunState, SahodayaInfo } from '../lib/types';

type Method = 'password' | 'passcode';

const info = ref<SahodayaInfo | null>(app.sahodaya);
const loadingInfo = ref(false);
const infoError = ref<{ title: string; text: string; retry: boolean } | null>(null);

const method = ref<Method>(readPref());
const username = ref('');
const password = ref('');
const showPassword = ref(false);
const hallTicket = ref('');
const passcode = ref('');

const busy = ref(false);
const retrying = ref(false);
const countdown = ref(0);
const error = ref<{ kind: 'bad' | 'warn' | 'info'; text: string } | null>(null);
let retryTimer: ReturnType<typeof setInterval> | null = null;

const lookalike = computed(() => hasLookalikes(passcode.value));

/** Hide a tab only when the engine tells us every open run uses one entry mode. */
const allowed = computed<Method[]>(() => {
    const modes = (info.value?.runs ?? []).map((r) => r.entry_mode).filter(Boolean);
    if (modes.length && modes.length === (info.value?.runs ?? []).length) {
        if (modes.every((m) => m === 'portal_password')) return ['password'];
        if (modes.every((m) => m === 'exam_passcode')) return ['passcode'];
    }
    return ['password', 'passcode'];
});
const showTabs = computed(() => allowed.value.length > 1);

const canSubmit = computed(() =>
    method.value === 'password' ? username.value !== '' && password.value !== '' : hallTicket.value !== '' && isCompletePasscode(passcode.value),
);

function readPref(): Method {
    try {
        return window.localStorage.getItem('sxe:method') === 'passcode' ? 'passcode' : 'password';
    } catch {
        return 'password';
    }
}

function setMethod(m: Method) {
    method.value = m;
    error.value = null;
    try {
        window.localStorage.setItem('sxe:method', m);
    } catch {
        /* ignore */
    }
}

function onPasscode(e: Event) {
    const el = e.target as HTMLInputElement;
    const formatted = formatPasscode(el.value);
    passcode.value = formatted;
    if (el.value !== formatted) el.value = formatted;
}

function runStateLabel(s: RunState): string {
    return { armed: 'Soon', checkin: 'Check-in open', waiting: 'Waiting room open', live: 'In progress', ended: 'Ended', closed: 'Closed' }[s] ?? s;
}

async function loadInfo() {
    if (!app.code) return;
    loadingInfo.value = true;
    infoError.value = null;
    try {
        info.value = await fetchSahodaya(app.code);
        if (!allowed.value.includes(method.value)) method.value = allowed.value[0];
    } catch (e) {
        if (e instanceof ApiError && e.status === 404) {
            infoError.value = {
                title: 'No open exam for this code',
                text: `We couldn’t find an exam that is open for “${app.code}”. Check the code on your hall ticket. Sign-in opens when check-in starts for your exam.`,
                retry: false,
            };
        } else {
            infoError.value = { title: 'Can’t reach the exam server', text: 'Check your internet connection and try again.', retry: true };
        }
    } finally {
        loadingInfo.value = false;
    }
}

function body(): LoginRequest {
    const sahodaya = app.code ?? '';
    return method.value === 'password'
        ? { sahodaya, method: 'password', username: username.value.trim(), password: password.value }
        : { sahodaya, method: 'passcode', hall_ticket: hallTicket.value.trim(), passcode: normalisePasscode(passcode.value) };
}

function minutesText(seconds: number | null): string {
    if (!seconds) return 'about 10 minutes';
    const m = Math.ceil(seconds / 60);
    return m <= 1 ? 'a minute' : `${m} minutes`;
}

async function submit() {
    if (!canSubmit.value || (busy.value && !retrying.value)) return;
    stopTimer();
    busy.value = true;
    retrying.value = false;
    error.value = null;
    try {
        const res = await api<{ state: ExamState }>('POST', '/api/login', body(), { timeoutMs: 35000 });
        password.value = '';
        await enterState(res.state);
    } catch (e) {
        handleError(e);
    } finally {
        if (!retrying.value) busy.value = false;
    }
}

function handleError(e: unknown) {
    const err = e instanceof ApiError ? e : new ApiError(0, 'network', String(e));
    switch (err.code) {
        case 'unauthorized':
            error.value = { kind: 'bad', text: 'Those details don’t match. Check them and try again.' };
            return;
        case 'forbidden':
            error.value = {
                kind: 'bad',
                text:
                    'You can’t sign in this way right now. Your exam may use the other sign-in method, or you may not be registered for an exam that is open. Ask your school coordinator if this continues.',
            };
            return;
        case 'locked':
            error.value = { kind: 'bad', text: `Too many wrong attempts. Please wait ${minutesText(err.retryAfter)} before trying again.` };
            return;
        case 'device_conflict':
            error.value = {
                kind: 'warn',
                text: 'Your exam is already open on another device. Ask your school coordinator to allow a new device, then sign in again here.',
            };
            return;
        case 'closed':
            error.value = { kind: 'bad', text: 'This exam has closed. Sign-in is no longer possible.' };
            return;
        case 'invalid':
            error.value = { kind: 'bad', text: 'Please fill in every field correctly.' };
            return;
        case 'not_found':
            error.value = { kind: 'bad', text: 'No open exam was found for this code.' };
            return;
        case 'busy':
        case 'rate_limited':
            startRetry(err.retryAfter ?? 5, err.code === 'busy' ? 'Lots of students are signing in right now. We’ll try again automatically' : 'Too many tries in a short time. We’ll try again automatically');
            return;
        default:
            if (err.isNetwork) {
                error.value = { kind: 'bad', text: err.code === 'timeout' ? 'The exam server is slow to answer. Please try again.' : 'Can’t reach the exam server. Check your internet connection.' };
            } else if (err.status >= 500) {
                startRetry(err.retryAfter ?? 8, 'The exam server had a problem. We’ll try again automatically');
            } else {
                error.value = { kind: 'bad', text: err.message || 'Sign-in failed. Please try again.' };
            }
    }
}

function startRetry(seconds: number, text: string) {
    retrying.value = true;
    busy.value = true;
    countdown.value = Math.max(1, Math.min(120, Math.round(seconds + Math.random() * 2)));
    error.value = { kind: 'info', text };
    retryTimer = setInterval(() => {
        countdown.value--;
        if (countdown.value <= 0) {
            stopTimer();
            retrying.value = false;
            busy.value = false;
            submit();
        }
    }, 1000);
}

function stopTimer() {
    if (retryTimer) clearInterval(retryTimer);
    retryTimer = null;
    countdown.value = 0;
}

function cancelRetry() {
    stopTimer();
    retrying.value = false;
    busy.value = false;
    error.value = null;
}

onMounted(() => {
    if (!info.value || info.value.code?.toUpperCase() !== app.code) loadInfo();
});
onBeforeUnmount(stopTimer);
</script>
