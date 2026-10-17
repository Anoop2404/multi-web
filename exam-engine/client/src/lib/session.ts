import { reactive } from 'vue';
import { AnswerLedger, type Outgoing } from './answers';
import { api, ApiError, clock } from './api';
import { answersChecksum } from './checksum';
import { finalSubmitDelayMs, parseTime } from './clock';
import { backoffMs, nextSyncAt } from './schedule';
import { saveAttempt, type LocalAttempt } from './storage';
import type {
    AnswerValue,
    BroadcastMessage,
    ExamState,
    ProctorEvent,
    ProctorEventType,
    SubmitResponse,
    SyncResponse,
} from './types';

export type SyncStatus = 'saved' | 'saving' | 'pending' | 'offline' | 'retrying';

export interface SessionStatus {
    sync: SyncStatus;
    online: boolean;
    lastSavedAt: number | null;
    locked: boolean;
    timeUp: boolean;
    submitting: boolean;
    submitAttempts: number;
    submitNote: string | null;
    submitted: { receipt: string | null; submitted_at: string | null } | null;
    signedOut: boolean;
    deadline: string | null;
    messages: BroadcastMessage[];
    seenMessageIds: number[];
    fullscreen: boolean;
    storageWarning: boolean;
}

const MAX_EVENTS_PER_SYNC = 50; // CONTRACT §4
const MAX_QUEUED_EVENTS = 500;

/**
 * One writing attempt on this device: the answer ledger (IndexedDB first), the sync loop,
 * proctor signals, the deadline, and the final submit. Everything the UI shows lives in
 * `status` and `ledger` (both reactive).
 */
export class ExamSession {
    readonly run: string;
    readonly regId: number;
    readonly ledger: AnswerLedger;
    readonly status: SessionStatus;
    state: ExamState;
    events: ProctorEvent[];
    offlineSince: string | null;
    needResync: boolean;
    discarded: Record<string, unknown> | null;

    private nextSyncInS: number;
    private heartbeatS: number;
    private graceS: number;
    private jitter = Math.random() * 0.2;
    private lastAttemptAt = 0;
    private failures = 0;
    private serverRefused = false;
    private retryAfterMs: number | null = null;
    private timer: ReturnType<typeof setTimeout> | null = null;
    private ticker: ReturnType<typeof setInterval> | null = null;
    private submitTimer: ReturnType<typeof setTimeout> | null = null;
    private inFlight: Promise<void> | null = null;
    private stopped = false;
    private started = false;
    private wasFullscreen = false;
    private lastEventAt: Record<string, number> = {};
    private listeners: Array<() => void> = [];
    private submitPromise: Promise<void> | null = null;

    /**
     * @param fresh `state` was just read from the engine (the next sync can wait for the pace);
     *              false when resuming offline from the device copy (try the server at once).
     */
    constructor(state: ExamState, ledger: AnswerLedger, record: Partial<LocalAttempt> | null, needResync: boolean, fresh = true) {
        this.run = state.run;
        this.regId = state.reg_id;
        this.state = state;
        this.ledger = reactive(ledger) as AnswerLedger;
        this.events = (record?.events ?? []).slice(-MAX_QUEUED_EVENTS);
        this.offlineSince = record?.offlineSince ?? null;
        this.needResync = needResync || !!record?.needResync;
        this.discarded = record?.discarded ?? null;
        this.nextSyncInS = state.next_sync_in || 10;
        this.heartbeatS = state.heartbeat_seconds || 30;
        this.graceS = state.grace_seconds ?? 90;
        this.lastAttemptAt = fresh ? Date.now() : 0;
        if (!fresh) this.failures = 1;
        const messages = mergeMessages(record?.messages ?? [], state.messages ?? []);
        this.status = reactive<SessionStatus>({
            sync: this.ledger.isDirty() || this.needResync ? 'pending' : 'saved',
            online: navigator.onLine !== false,
            lastSavedAt: null,
            locked: state.state === 'locked',
            timeUp: false,
            submitting: false,
            submitAttempts: 0,
            submitNote: null,
            submitted: null,
            signedOut: false,
            deadline: state.deadline,
            messages,
            seenMessageIds: (record?.seenMessageIds ?? []).slice(),
            fullscreen: !!document.fullscreenElement,
            storageWarning: false,
        });
    }

    // ---------------------------------------------------------------- lifecycle

    start(): void {
        if (this.started) return;
        this.started = true;
        this.attachListeners();
        this.ticker = setInterval(() => this.tick(), 1000);
        this.persist();
        this.tick();
        if (this.needResync || this.offlineSince) this.syncSoon(0);
        else this.schedule();
    }

    stop(): void {
        this.stopped = true;
        if (this.timer) clearTimeout(this.timer);
        if (this.ticker) clearInterval(this.ticker);
        if (this.submitTimer) clearTimeout(this.submitTimer);
        this.timer = this.ticker = this.submitTimer = null;
        for (const off of this.listeners) off();
        this.listeners = [];
    }

    get canAnswer(): boolean {
        return !this.status.locked && !this.status.timeUp && !this.status.submitting && !this.status.submitted && !this.status.signedOut;
    }

    msLeft(): number {
        return clock.msUntil(this.status.deadline);
    }

    // ---------------------------------------------------------------- answers

    setAnswer(qid: string, value: AnswerValue | null): void {
        if (!this.canAnswer) return;
        if (this.ledger.set(qid, value)) this.changed();
    }

    /** Multiple-correct: add/remove one key against the current answer. */
    toggleOption(qid: string, key: string): void {
        const current = this.ledger.get(qid);
        const keys = Array.isArray(current) ? current.slice() : current ? [current] : [];
        const next = keys.includes(key) ? keys.filter((k) => k !== key) : keys.concat(key);
        this.setAnswer(qid, next.length ? next : null);
    }

    setFlag(qid: string, on: boolean): void {
        if (this.status.submitted || this.status.submitting) return;
        if (this.ledger.setFlag(qid, on)) this.changed();
    }

    private changed(): void {
        this.status.sync = 'pending';
        this.persist();
        this.schedule();
    }

    // ---------------------------------------------------------------- persistence

    record(): LocalAttempt {
        return {
            v: 1,
            run: this.run,
            regId: this.regId,
            ledger: this.ledger.toJSON(),
            events: this.events.slice(),
            offlineSince: this.offlineSince,
            needResync: this.needResync,
            seenMessageIds: this.status.seenMessageIds.slice(),
            paperKey: this.state.paper_key,
            bundleVersion: this.state.bundle_version,
            state: { ...this.state, deadline: this.status.deadline, answers: {}, flagged: [] },
            messages: this.status.messages.slice(),
            submitted: this.status.submitted,
            discarded: this.discarded,
            clockOffsetMs: clock.offsetMs,
            savedAt: Date.now(),
        };
    }

    persist(): void {
        saveAttempt(this.record()).catch(() => {
            this.status.storageWarning = true;
        });
    }

    // ---------------------------------------------------------------- proctor events

    recordEvent(type: ProctorEventType, extra: Partial<ProctorEvent> = {}): void {
        if (this.status.submitted || this.stopped) return;
        const now = Date.now();
        if (type !== 'offline' && now - (this.lastEventAt[type] ?? 0) < 2000) return; // flapping
        this.lastEventAt[type] = now;
        this.events.push({ type, at: clock.nowIso(), ...extra });
        if (this.events.length > MAX_QUEUED_EVENTS) this.events.splice(0, this.events.length - MAX_QUEUED_EVENTS);
        this.persist();
    }

    private attachListeners(): void {
        const on = (target: EventTarget, name: string, fn: EventListener) => {
            target.addEventListener(name, fn);
            this.listeners.push(() => target.removeEventListener(name, fn));
        };
        on(document, 'visibilitychange', () => {
            if (document.hidden) {
                this.recordEvent('tab_hidden');
                if (this.ledger.isDirty()) this.syncSoon(0);
            } else {
                this.tick();
                if (this.ledger.isDirty() || this.failures > 0) this.syncSoon(0);
            }
        });
        on(window, 'blur', () => {
            if (!document.hidden) this.recordEvent('window_blur');
        });
        on(document, 'fullscreenchange', () => {
            const isFull = !!document.fullscreenElement;
            if (this.wasFullscreen && !isFull) this.recordEvent('fullscreen_exit');
            this.wasFullscreen = isFull;
            this.status.fullscreen = isFull;
        });
        on(window, 'online', () => {
            this.status.online = true;
            if (this.failures > 0) {
                // Retry at once (the failed attempts never reached the server, so no pace applies).
                this.retryAfterMs = null;
                this.syncSoon(0);
            }
        });
        on(window, 'offline', () => {
            this.status.online = false;
            this.status.sync = 'offline';
            if (!this.offlineSince) {
                this.offlineSince = clock.nowIso();
                this.persist();
            }
        });
        on(window, 'pagehide', () => this.persist());
    }

    // ---------------------------------------------------------------- clock / deadline

    private tick(): void {
        if (this.stopped || this.status.submitted || this.status.timeUp) return;
        if (!this.status.deadline) return;
        if (this.msLeft() <= 0) this.onTimeUp();
    }

    /** CONTRACT §7: lock at the deadline, submit after a random 0–30 s inside the grace window. */
    private onTimeUp(): void {
        if (this.status.timeUp) return;
        this.status.timeUp = true;
        this.status.submitNote = 'Time is up. Your answers are locked and will be submitted in a moment.';
        if (this.timer) clearTimeout(this.timer);
        const delay = finalSubmitDelayMs(this.graceS);
        this.submitTimer = setTimeout(() => {
            this.submit('auto').catch(() => undefined);
        }, delay);
    }

    // ---------------------------------------------------------------- sync loop

    private schedule(): void {
        if (this.stopped || this.status.submitting || this.status.submitted || this.status.timeUp) return;
        if (this.timer) clearTimeout(this.timer);
        if (this.inFlight) return; // rescheduled when the request settles
        const due = nextSyncAt({
            now: Date.now(),
            lastAttemptAt: this.lastAttemptAt,
            nextSyncInS: this.nextSyncInS,
            jitter: this.jitter,
            heartbeatS: this.heartbeatS,
            dirty: this.ledger.isDirty() || this.needResync,
            lastChangeAt: this.ledger.lastChangeAt,
            firstDirtyAt: this.ledger.firstDirtyAt,
            failures: this.failures,
            serverRefused: this.serverRefused,
            retryAfterMs: this.retryAfterMs,
        });
        this.timer = setTimeout(() => this.syncNow(), Math.max(0, due - Date.now()));
    }

    /** Sync as soon as allowed: now, or once the server's pace allows if it was reached last time. */
    private syncSoon(delayMs: number): void {
        if (this.stopped || this.status.submitting || this.status.submitted || this.status.timeUp) return;
        if (this.inFlight) return;
        if (this.timer) clearTimeout(this.timer);
        let at = Date.now() + delayMs;
        if (this.failures === 0 && this.lastAttemptAt) at = Math.max(at, this.lastAttemptAt + this.nextSyncInS * 1000 * (1 + this.jitter));
        this.timer = setTimeout(() => this.syncNow(), Math.max(0, at - Date.now()));
    }

    /** One sync (or resync) round. Never runs two requests at once. */
    syncNow(): Promise<void> {
        if (this.inFlight) return this.inFlight;
        if (this.stopped || this.status.submitted) return Promise.resolve();
        this.inFlight = (this.needResync ? this.resyncRound() : this.syncRound())
            .catch(() => undefined)
            .then(() => {
                this.inFlight = null;
                this.persist();
                this.schedule();
            });
        return this.inFlight;
    }

    private async syncRound(): Promise<void> {
        const events = this.events.slice(0, MAX_EVENTS_PER_SYNC);
        const out = this.ledger.buildOutgoing(events.length);
        const checksum = await answersChecksum(out.snapshot);
        this.ledger.rememberSent(checksum);
        this.lastAttemptAt = Date.now();
        if (this.ledger.isDirty()) this.status.sync = 'saving';
        let resp: SyncResponse;
        try {
            resp = await api<SyncResponse>('POST', '/api/sync', {
                base_rev: out.baseRev,
                changes: out.changes,
                flagged: out.flagged,
                checksum,
                events,
            }, { timeoutMs: 20000 });
        } catch (e) {
            this.onFailure(e);
            return;
        }
        this.onReachable();
        this.applyResponse(resp, out, events.length);
    }

    private async resyncRound(): Promise<void> {
        const out = this.ledger.buildOutgoing(0);
        const checksum = await answersChecksum(out.snapshot);
        this.ledger.rememberSent(checksum);
        this.lastAttemptAt = Date.now();
        this.status.sync = 'saving';
        let resp: SyncResponse;
        try {
            resp = await api<SyncResponse>('POST', '/api/resync', { answers: out.snapshot, flagged: out.flagged }, { timeoutMs: 20000 });
        } catch (e) {
            this.onFailure(e);
            return;
        }
        this.onReachable();
        if (resp.action === 'resync') {
            // The server still disagrees right after a full replace: back off instead of looping.
            this.failures++;
            this.serverRefused = true;
            this.status.sync = 'retrying';
            return;
        }
        this.applyResponse(resp, out, 0, true);
    }

    private applyResponse(resp: SyncResponse, out: Outgoing, sentEvents: number, wasResync = false): void {
        if (typeof resp.next_sync_in === 'number' && resp.next_sync_in > 0) this.nextSyncInS = resp.next_sync_in;
        this.jitter = Math.random() * 0.2;
        if (resp.deadline !== undefined && resp.deadline !== null) this.status.deadline = resp.deadline;
        if (Array.isArray(resp.messages) && resp.messages.length) this.status.messages = mergeMessages(this.status.messages, resp.messages);
        // Proctor events count as delivered on any 200 reply (the engine records them before it
        // looks at revs or locks), so they are never sent twice.
        if (sentEvents) this.events.splice(0, sentEvents);

        const action = resp.action || 'none';
        if (action === 'resync' || (!wasResync && resp.checksum_ok === false && action === 'none')) {
            this.needResync = true;
            this.status.sync = 'pending';
            // Resync right away (still inside the 6-per-10 s budget).
            this.lastAttemptAt = 0;
            return;
        }
        if (action === 'lock') {
            this.status.locked = true;
            this.status.sync = this.ledger.isDirty() ? 'pending' : 'saved';
            return;
        }
        // none: the server accepted what we sent. submit: it may have refused late changes
        // (past deadline + grace), so only count them as stored if the rev moved.
        const accepted =
            resp.checksum_ok !== false &&
            (action !== 'submit' || wasResync || Object.keys(out.changes).length === 0 || (typeof resp.rev === 'number' && resp.rev > out.baseRev));
        if (accepted) {
            this.ledger.ack(out, typeof resp.rev === 'number' ? resp.rev : out.baseRev);
            if (wasResync) this.needResync = false;
            this.status.lastSavedAt = Date.now();
        }
        this.status.locked = false;
        this.status.sync = this.ledger.isDirty() || this.needResync ? 'pending' : 'saved';
        if (action === 'submit') this.submit('server').catch(() => undefined);
        else this.tick();
    }

    private onReachable(): void {
        this.failures = 0;
        this.serverRefused = false;
        this.retryAfterMs = null;
        this.status.online = true;
        if (this.offlineSince) {
            const since = parseTime(this.offlineSince);
            const seconds = since === null ? undefined : Math.max(0, Math.round((clock.nowMs() - since) / 1000));
            const at = this.offlineSince;
            this.offlineSince = null;
            this.events.push({ type: 'offline', at, ...(seconds !== undefined ? { seconds } : {}) });
        }
    }

    private onFailure(e: unknown): void {
        const err = e instanceof ApiError ? e : new ApiError(0, 'network', String(e));
        if (err.isNetwork) {
            this.failures++;
            this.serverRefused = false;
            this.retryAfterMs = null;
            this.status.online = false;
            this.status.sync = 'offline';
            if (!this.offlineSince) this.offlineSince = clock.nowIso();
            return;
        }
        this.status.online = true;
        switch (err.code) {
            case 'unauthorized':
                this.status.signedOut = true;
                this.stop();
                return;
            case 'locked':
                this.status.locked = true;
                this.failures = 0;
                this.lastAttemptAt = Date.now();
                return;
            case 'closed':
            case 'already_submitted':
                this.submit('server').catch(() => undefined);
                return;
            case 'invalid':
                // Our diff didn't validate; replacing the whole map is the safe fallback.
                this.needResync = true;
                break;
            default:
                break;
        }
        this.failures++;
        this.serverRefused = true;
        this.retryAfterMs = err.retryAfter !== null ? err.retryAfter * 1000 : null;
        this.status.sync = 'retrying';
    }

    // ---------------------------------------------------------------- submit

    /**
     * Final submit; retried until the engine confirms (it is idempotent). `mode` only changes
     * wording: the engine decides manual vs auto from the clock.
     */
    submit(mode: 'manual' | 'auto' | 'server'): Promise<void> {
        if (this.status.submitted) return Promise.resolve();
        if (this.submitPromise) return this.submitPromise;
        this.status.submitting = true;
        if (mode === 'manual') this.status.submitNote = 'Submitting your answers…';
        else if (mode === 'server') this.status.submitNote = 'Your exam is being submitted…';
        if (this.timer) clearTimeout(this.timer);
        if (this.submitTimer) clearTimeout(this.submitTimer);
        this.submitPromise = this.submitLoop().finally(() => {
            this.submitPromise = null;
        });
        return this.submitPromise;
    }

    private async submitLoop(): Promise<void> {
        if (this.inFlight) await this.inFlight.catch(() => undefined);
        let attempt = 0;
        while (!this.status.submitted && !this.status.signedOut) {
            attempt++;
            this.status.submitAttempts = attempt;
            try {
                if (this.needResync) {
                    await this.resyncRound();
                    if (this.needResync && this.failures > 0) throw new ApiError(0, 'network', 'resync failed');
                }
                const out = this.ledger.buildOutgoing(0);
                const checksum = await answersChecksum(out.snapshot);
                const resp = await api<SubmitResponse>('POST', '/api/submit', { base_rev: out.baseRev, changes: out.changes, checksum }, { timeoutMs: 30000 });
                if (resp && resp.submitted) {
                    this.ledger.ack(out, out.baseRev + (Object.keys(out.changes).length ? 1 : 0));
                    this.finish(resp.receipt ?? null, resp.submitted_at ?? clock.nowIso());
                    return;
                }
                throw new ApiError(500, 'server', 'not submitted');
            } catch (e) {
                const err = e instanceof ApiError ? e : new ApiError(0, 'network', String(e));
                if (err.code === 'unauthorized') {
                    this.status.signedOut = true;
                    this.status.submitting = false;
                    this.stop();
                    return;
                }
                if (err.code === 'already_submitted' || err.code === 'closed') {
                    if (await this.adoptServerFinish()) return;
                }
                if (err.code === 'invalid') this.needResync = true;
                if (err.code === 'locked') {
                    this.status.locked = true;
                    this.status.submitNote = 'Paused by the invigilator. Your answers will be submitted when the exam resumes.';
                    await sleep(Math.max(5000, this.nextSyncInS * 1000));
                    continue;
                }
                this.status.locked = false;
                this.status.online = !err.isNetwork;
                this.status.sync = err.isNetwork ? 'offline' : 'retrying';
                this.status.submitNote = err.isNetwork
                    ? 'You’re offline. Your answers are saved on this device and will be submitted as soon as the connection is back.'
                    : 'The server is busy. Retrying the submit automatically…';
                const wait = Math.max(backoffMs(Math.min(attempt, 4), Math.random() * 0.2), (err.retryAfter ?? 0) * 1000);
                await sleep(err.isNetwork && !navigator.onLine ? Math.min(wait, 5000) : wait);
            }
        }
    }

    /** After `already_submitted` / `closed`: read the receipt from /api/state. */
    private async adoptServerFinish(): Promise<boolean> {
        try {
            const state = await api<ExamState>('GET', '/api/state');
            this.state = state;
            if (state.state === 'submitted' || state.state === 'ended') {
                this.finish(state.receipt, state.submitted_at);
                return true;
            }
        } catch {
            /* retry in the loop */
        }
        return false;
    }

    private finish(receipt: string | null, submittedAt: string | null): void {
        this.status.submitted = { receipt, submitted_at: submittedAt };
        this.status.submitting = false;
        this.status.timeUp = true;
        this.status.sync = 'saved';
        this.status.submitNote = null;
        this.persist();
        this.stop();
        if (document.fullscreenElement && document.exitFullscreen) document.exitFullscreen().catch(() => undefined);
    }

    // ---------------------------------------------------------------- messages

    get unseenMessage(): BroadcastMessage | null {
        const unseen = this.status.messages.filter((m) => !this.status.seenMessageIds.includes(m.id));
        return unseen.length ? unseen[unseen.length - 1] : null;
    }

    dismissMessage(id: number): void {
        if (!this.status.seenMessageIds.includes(id)) this.status.seenMessageIds.push(id);
        this.persist();
    }

    /** For tests / debugging. */
    debugInfo(): Record<string, unknown> {
        return { failures: this.failures, nextSyncInS: this.nextSyncInS, needResync: this.needResync, events: this.events.length, serverRev: this.ledger.serverRev };
    }
}

export function mergeMessages(a: BroadcastMessage[], b: BroadcastMessage[]): BroadcastMessage[] {
    const byId = new Map<number, BroadcastMessage>();
    for (const m of a.concat(b)) if (m && typeof m.id === 'number') byId.set(m.id, m);
    return Array.from(byId.values()).sort((x, y) => x.id - y.id);
}

function sleep(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
}
