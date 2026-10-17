import { reactive, shallowRef } from 'vue';
import { AnswerLedger, compactMap, reconcile } from './lib/answers';
import { api, ApiError, clock, seg } from './lib/api';
import { answersChecksum } from './lib/checksum';
import { BundleError } from './lib/bundle';
import { loadKatex } from './lib/richText';
import { openPaper, type DisplayQuestion, type DownloadProgress } from './lib/paper';
import { currentRoute, goTo, normaliseCode, stripLaunchParam } from './lib/router';
import { ExamSession } from './lib/session';
import {
    clearLastSession,
    loadAttempt,
    loadBundle,
    loadLastSession,
    saveAttempt,
    saveLastSession,
    type LocalAttempt,
} from './lib/storage';
import type { ExamState, LoginRequest, SahodayaInfo } from './lib/types';

export type Screen = 'boot' | 'code' | 'login' | 'prepare' | 'opening' | 'exam' | 'done' | 'ended' | 'signed_out';

export const app = reactive({
    screen: 'boot' as Screen,
    code: null as string | null,
    sahodaya: null as SahodayaInfo | null,
    state: null as ExamState | null,
    /** Shown on the login page (e.g. after a failed launch link). */
    notice: null as string | null,
    openingStep: '' as string,
    openingError: null as string | null,
    download: null as DownloadProgress | null,
});

export const paper = shallowRef<DisplayQuestion[] | null>(null);
export const session = shallowRef<ExamSession | null>(null);

// ------------------------------------------------------------------ boot

export async function boot(): Promise<void> {
    const route = currentRoute();
    app.code = route.code;
    if (route.launch) {
        stripLaunchParam();
        try {
            const res = await api<{ state: ExamState }>('POST', '/api/login', { method: 'launch', token: route.launch } as LoginRequest, { timeoutMs: 30000 });
            await enterState(res.state);
            return;
        } catch (e) {
            app.notice = launchErrorText(e);
        }
    }
    try {
        const state = await api<ExamState>('GET', '/api/state', undefined, { timeoutMs: 15000 });
        await enterState(state);
        return;
    } catch (e) {
        if (e instanceof ApiError && e.isNetwork && (await resumeOffline())) return;
        if (e instanceof ApiError && e.isNetwork && !app.notice) app.notice = 'Can’t reach the exam server. Check your internet connection, then try again.';
    }
    showLogin();
}

function launchErrorText(e: unknown): string {
    if (e instanceof ApiError) {
        if (e.code === 'device_conflict') return 'Your exam is already open on another device. Ask your school coordinator to allow a new device, then try again.';
        if (e.code === 'closed') return 'This exam has closed.';
        if (e.code === 'busy' || e.code === 'rate_limited') return 'Lots of students are signing in right now. Please sign in below.';
        if (e.isNetwork) return 'Can’t reach the exam server. Check your internet connection.';
    }
    return 'That exam link has expired or was already used. Open the exam again from the student portal, or sign in below.';
}

export function showLogin(): void {
    app.screen = app.code ? 'login' : 'code';
}

export function chooseCode(raw: string): void {
    const code = normaliseCode(raw);
    if (!code) return;
    app.code = code;
    app.sahodaya = null;
    goTo(code);
    app.screen = 'login';
}

export function changeCode(): void {
    app.code = null;
    app.sahodaya = null;
    goTo(null);
    app.screen = 'code';
}

export async function fetchSahodaya(code: string): Promise<SahodayaInfo> {
    const info = await api<SahodayaInfo>('GET', `/api/sahodaya/${seg(code)}`, undefined, { timeoutMs: 15000 });
    app.sahodaya = info;
    return info;
}

// ------------------------------------------------------------------ state routing

/** Route by the attempt state (GET /api/state is the single source of truth). */
export async function enterState(state: ExamState): Promise<void> {
    app.state = state;
    app.notice = null;
    saveLastSession(state.run, state.reg_id).catch(() => undefined);
    switch (state.state) {
        case 'checked_in':
        case 'waiting':
            app.screen = 'prepare';
            return;
        case 'writing':
        case 'locked':
            await openExam(state, true);
            return;
        case 'submitted':
            await rememberSubmitted(state);
            app.screen = 'done';
            return;
        default:
            app.screen = 'ended';
    }
}

async function rememberSubmitted(state: ExamState): Promise<void> {
    const rec = await loadAttempt(state.run, state.reg_id).catch(() => undefined);
    if (rec && !rec.submitted) {
        rec.submitted = { receipt: state.receipt, submitted_at: state.submitted_at };
        await saveAttempt(rec).catch(() => undefined);
    }
}

/** Refresh state (used by the pre-exam screen's poll and after errors). */
export async function refreshState(): Promise<ExamState> {
    const state = await api<ExamState>('GET', '/api/state', undefined, { timeoutMs: 15000 });
    const before = app.state?.state;
    app.state = state;
    if (state.state !== before && !(before === 'checked_in' && state.state === 'waiting')) await enterState(state);
    return state;
}

// ------------------------------------------------------------------ start + open paper

export async function startExam(pin: string | null): Promise<ExamState> {
    const body = pin ? { pin } : {};
    const state = await api<ExamState>('POST', '/api/start', body, { timeoutMs: 30000 });
    await enterState(state);
    return state;
}

/**
 * Decrypts the paper, restores answers (IndexedDB vs server, task §6) and starts the session.
 * `fresh` = `state` came from the server just now.
 */
export async function openExam(state: ExamState, fresh: boolean, cached?: LocalAttempt): Promise<void> {
    app.screen = 'opening';
    app.openingError = null;
    app.openingStep = 'Opening your question paper…';
    session.value?.stop();
    session.value = null;
    try {
        const katex = loadKatex();
        const questions = await openPaper(state, (p) => {
            app.download = p;
            app.openingStep = 'Downloading the question paper…';
        });
        app.openingStep = 'Preparing questions…';
        await Promise.race([katex, new Promise((r) => setTimeout(r, 8000))]);

        const record = cached ?? (await loadAttempt(state.run, state.reg_id).catch(() => undefined));
        let ledger: AnswerLedger;
        let needResync = false;
        let discarded: Record<string, unknown> | null = null;
        if (fresh) {
            const local = record?.ledger ? AnswerLedger.fromJSON(record.ledger) : null;
            const serverAnswers = compactMap(state.answers ?? {});
            const [serverSum, ackedSum] = await Promise.all([
                answersChecksum(serverAnswers),
                local ? answersChecksum(local.acked) : Promise.resolve(null),
            ]);
            const result = reconcile(local, { answers: serverAnswers, flagged: state.flagged ?? [], rev: state.rev ?? 0 }, serverSum, ackedSum);
            ledger = result.ledger;
            needResync = result.needResync;
            discarded = result.discarded;
        } else {
            ledger = record?.ledger ? AnswerLedger.fromJSON(record.ledger) : AnswerLedger.fromServer({}, [], 0);
        }
        const s = new ExamSession(state, ledger, record ? { ...record, discarded: discarded ?? record.discarded ?? null } : { discarded }, needResync, fresh);
        paper.value = questions;
        session.value = s;
        s.start();
        app.screen = 'exam';
    } catch (e) {
        app.openingError = openingErrorText(e);
    }
}

function openingErrorText(e: unknown): string {
    if (e instanceof BundleError) {
        return e.reason === 'decrypt' || e.reason === 'key'
            ? 'The question paper couldn’t be unlocked on this device. Tap “Try again”. If it keeps failing, tell your invigilator.'
            : 'The question paper looks damaged. Tap “Try again” to download it again.';
    }
    if (e instanceof ApiError && e.isNetwork) return 'Couldn’t download the question paper. Check your internet connection and tap “Try again”.';
    return 'Something went wrong opening the paper. Tap “Try again”.';
}

export async function retryOpen(): Promise<void> {
    try {
        const state = await api<ExamState>('GET', '/api/state', undefined, { timeoutMs: 15000 });
        await enterState(state);
    } catch (e) {
        if (e instanceof ApiError && e.isNetwork && app.state?.paper_key) {
            await openExam(app.state, false);
            return;
        }
        app.openingError = openingErrorText(e);
    }
}

/**
 * /api/state unreachable on load: if this device was in the middle of writing, carry on
 * offline from the device copy (answers keep saving; the sync loop retries in the background).
 */
async function resumeOffline(): Promise<boolean> {
    try {
        const last = await loadLastSession();
        if (!last) return false;
        const rec = await loadAttempt(last.run, last.regId);
        if (!rec || rec.submitted || !rec.state || !rec.paperKey) return false;
        if (rec.state.state !== 'writing' && rec.state.state !== 'locked') return false;
        const bundle = await loadBundle(last.run, rec.bundleVersion);
        if (!bundle) return false;
        if (typeof rec.clockOffsetMs === 'number') clock.seed(rec.clockOffsetMs);
        const state: ExamState = { ...rec.state, paper_key: rec.paperKey, messages: rec.messages ?? [] };
        app.state = state;
        await openExam(state, false, rec);
        return app.screen === 'exam';
    } catch {
        return false;
    }
}

// ------------------------------------------------------------------ after the exam

export function finishToResult(): void {
    const s = session.value;
    if (s?.status.submitted && app.state) {
        app.state = { ...app.state, state: 'submitted', receipt: s.status.submitted.receipt, submitted_at: s.status.submitted.submitted_at };
    }
    app.screen = 'done';
}

export async function logout(): Promise<void> {
    try {
        await api('POST', '/api/logout', {}, { timeoutMs: 10000 });
    } catch {
        /* the cookie may already be gone */
    }
    session.value?.stop();
    session.value = null;
    paper.value = null;
    app.state = null;
    await clearLastSession().catch(() => undefined);
    showLogin();
}

export function signedOutHere(): void {
    session.value?.stop();
    app.screen = 'signed_out';
}
