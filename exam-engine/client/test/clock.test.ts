import { describe, expect, it } from 'vitest';
import { finalSubmitDelayMs, formatCountdown, jitteredMs, ServerClock } from '../src/lib/clock';
import { backoffMs, nextSyncAt } from '../src/lib/schedule';

describe('ServerClock (never trust the device clock)', () => {
    it('corrects a device clock that is 5 minutes slow', () => {
        let local = Date.parse('2026-11-20T04:25:00Z'); // device thinks 04:25
        const c = new ServerClock(5, () => local);
        // Request sent at local t, answered 200 ms later; server says 04:30:00.1
        c.update('2026-11-20T04:30:00.100Z', local, local + 200);
        local += 200;
        // offset = server − midpoint = 04:30:00.100 − 04:25:00.100 = +300 s
        expect(c.offsetMs).toBe(300000);
        expect(new Date(c.nowMs()).toISOString()).toBe('2026-11-20T04:30:00.200Z');
        // 29 min 59.8 s left until a 05:00 deadline
        expect(c.msUntil('2026-11-20T05:00:00Z')).toBe(1799800);
        local += 1000;
        expect(c.msUntil('2026-11-20T05:00:00Z')).toBe(1798800);
    });

    it('prefers the sample with the smallest round trip', () => {
        const local = 1_000_000;
        const c = new ServerClock(5, () => local);
        c.update(new Date(local + 10_000).toISOString(), local - 50, local + 50); // rtt 100, offset +10 s
        c.update(new Date(local + 13_000).toISOString(), local - 3000, local + 3000); // rtt 6 s (skewed)
        expect(c.offsetMs).toBe(10_000);
    });

    it('seeded offset is replaced by the first real sample', () => {
        const c = new ServerClock(5, () => 0);
        c.seed(5000);
        expect(c.offsetMs).toBe(5000);
        c.update(new Date(1000).toISOString(), 0, 0);
        expect(c.offsetMs).toBe(1000);
    });

    it('null deadline is infinitely far; garbage server_time is ignored', () => {
        const c = new ServerClock(5, () => 0);
        c.update('not a date', 0, 10);
        expect(c.hasSample).toBe(false);
        expect(c.msUntil(null)).toBe(Infinity);
    });
});

describe('formatting and delays', () => {
    it('formats countdowns', () => {
        expect(formatCountdown(0)).toBe('0:00');
        expect(formatCountdown(-5000)).toBe('0:00');
        expect(formatCountdown(9_001)).toBe('0:10');
        expect(formatCountdown(61_000)).toBe('1:01');
        expect(formatCountdown(3_723_000)).toBe('1:02:03');
    });

    it('final submit delay is 0–30 s and leaves grace for retries', () => {
        expect(finalSubmitDelayMs(90, () => 0)).toBe(0);
        expect(finalSubmitDelayMs(90, () => 0.999999)).toBeLessThan(30_000);
        expect(finalSubmitDelayMs(90, () => 0.5)).toBe(15_000);
        expect(finalSubmitDelayMs(40, () => 0.999999)).toBeLessThan(10_000); // 40 s grace → ≤ 10 s
        expect(finalSubmitDelayMs(20, () => 0.9)).toBe(0); // tiny grace → submit at once
    });

    it('jitter adds 0–20 %', () => {
        expect(jitteredMs(10, () => 0)).toBe(10_000);
        expect(jitteredMs(10, () => 0.999)).toBeLessThanOrEqual(12_000);
    });
});

describe('sync scheduling', () => {
    const base = {
        now: 100_000,
        lastAttemptAt: 90_000,
        nextSyncInS: 10,
        jitter: 0,
        heartbeatS: 30,
        dirty: false,
        lastChangeAt: 0,
        firstDirtyAt: 0,
        failures: 0,
        serverRefused: false,
        retryAfterMs: null,
    };

    it('heartbeat every max(30, next_sync_in) s when nothing changed', () => {
        expect(nextSyncAt(base)).toBe(120_000);
        expect(nextSyncAt({ ...base, nextSyncInS: 60 })).toBe(150_000);
    });

    it('after a change: debounce 3 s but never sooner than next_sync_in after the last sync', () => {
        // last sync 90 s, change at 95 s → wants 98 s, pace allows 100 s
        expect(nextSyncAt({ ...base, dirty: true, lastChangeAt: 95_000, firstDirtyAt: 95_000 })).toBe(100_000);
        // change at 120 s → 123 s
        expect(nextSyncAt({ ...base, now: 121_000, dirty: true, lastChangeAt: 120_000, firstDirtyAt: 120_000 })).toBe(123_000);
        // constant clicking can't hold changes back more than 10 s after the first one
        expect(nextSyncAt({ ...base, now: 140_000, dirty: true, lastChangeAt: 139_000, firstDirtyAt: 130_000 })).toBe(140_000);
    });

    it('applies jitter to the pace', () => {
        expect(nextSyncAt({ ...base, jitter: 0.2, dirty: true, lastChangeAt: 90_500, firstDirtyAt: 90_500 })).toBe(102_000);
    });

    it('backs off after failures and honours Retry-After / pace for server refusals', () => {
        expect(backoffMs(1, 0)).toBe(2000);
        expect(backoffMs(3, 0)).toBe(8000);
        expect(backoffMs(9, 0)).toBe(30_000);
        expect(nextSyncAt({ ...base, failures: 1 })).toBe(92_000); // offline: quick retry
        expect(nextSyncAt({ ...base, failures: 1, serverRefused: true })).toBe(100_000); // busy: at least the pace
        expect(nextSyncAt({ ...base, failures: 1, serverRefused: true, retryAfterMs: 45_000 })).toBe(135_000);
    });

    it('first sync goes out at once', () => {
        expect(nextSyncAt({ ...base, lastAttemptAt: 0 })).toBe(100_000);
    });
});
