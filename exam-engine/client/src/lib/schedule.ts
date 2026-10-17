/**
 * When to send the next sync (pure, so it can be tested).
 *
 * - Never sooner than `next_sync_in` × (1 + jitter) after the previous attempt started
 *   (jitter is drawn once per cycle from 0–0.2, CONTRACT §7 "Pacing").
 * - With unsent changes: debounce ~3 s after the latest change, but never hold a change back
 *   more than 10 s past the first unsent one (so constant clicking can't starve the sync).
 * - Without changes: a heartbeat every max(heartbeat, next_sync_in) × (1 + jitter).
 * - After failures: exponential backoff 2, 4, 8, 16, 30 s (× jitter), and at least the
 *   server's Retry-After. Network failures may retry before `next_sync_in` (they never
 *   reached the server); server refusals (busy / rate limited / 5xx) may not.
 */
export interface ScheduleInput {
    now: number;
    /** Local ms when the previous sync attempt started; 0 = never. */
    lastAttemptAt: number;
    nextSyncInS: number;
    jitter: number;
    heartbeatS: number;
    dirty: boolean;
    lastChangeAt: number;
    firstDirtyAt: number;
    /** Consecutive failed attempts. */
    failures: number;
    /** The last failure was answered by the server (busy, rate limited, 5xx). */
    serverRefused: boolean;
    retryAfterMs: number | null;
    debounceMs?: number;
    maxHoldMs?: number;
}

export const BACKOFF_STEPS_S = [2, 4, 8, 16, 30];

export function backoffMs(failures: number, jitter: number): number {
    const step = BACKOFF_STEPS_S[Math.min(Math.max(failures, 1), BACKOFF_STEPS_S.length) - 1];
    return Math.round(step * 1000 * (1 + jitter));
}

export function nextSyncAt(input: ScheduleInput): number {
    const debounce = input.debounceMs ?? 3000;
    const maxHold = input.maxHoldMs ?? 10000;
    const pace = Math.max(1, input.nextSyncInS || 10);
    const minGap = pace * 1000 * (1 + input.jitter);
    const last = input.lastAttemptAt;

    if (input.failures > 0) {
        let wait = backoffMs(input.failures, input.jitter);
        if (input.retryAfterMs !== null) wait = Math.max(wait, input.retryAfterMs);
        if (input.serverRefused) wait = Math.max(wait, minGap);
        return last + wait;
    }
    if (last === 0) return input.now;
    if (input.dirty) {
        const wanted = Math.min(input.lastChangeAt + debounce, (input.firstDirtyAt || input.lastChangeAt) + maxHold);
        return Math.max(last + minGap, wanted);
    }
    const heartbeat = Math.max(input.heartbeatS || 30, pace) * 1000 * (1 + input.jitter);
    return last + heartbeat;
}
