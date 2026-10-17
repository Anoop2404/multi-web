/**
 * Server-owned clock (plan §4.1 rule 3). Every response with `server_time` gives a sample:
 * offset = server_time − midpoint(local send, local receive). The sample with the smallest
 * round trip among the last few is used, so one slow response doesn't skew the countdown.
 * The device clock is only used to measure time elapsed since the latest sample.
 */
export interface ClockSample {
    offsetMs: number;
    rttMs: number;
    at: number; // local ms when taken
}

export class ServerClock {
    private samples: ClockSample[] = [];
    private readonly keep: number;
    private readonly now: () => number;

    constructor(keep = 5, now: () => number = () => Date.now()) {
        this.keep = keep;
        this.now = now;
    }

    /** Record a response's server time; `sentAt`/`receivedAt` are local Date.now() values. */
    update(serverTimeIso: string, sentAt: number, receivedAt: number): void {
        const server = parseTime(serverTimeIso);
        if (server === null) return;
        const rtt = Math.max(0, receivedAt - sentAt);
        const sample: ClockSample = { offsetMs: server - (sentAt + receivedAt) / 2, rttMs: rtt, at: receivedAt };
        this.samples = this.samples.filter((s) => s.rttMs !== Number.MAX_SAFE_INTEGER);
        this.samples.push(sample);
        if (this.samples.length > this.keep) this.samples.shift();
    }

    /** Start from a remembered offset (offline reload); any real sample replaces it. */
    seed(offsetMs: number): void {
        if (this.samples.length === 0 && Number.isFinite(offsetMs)) {
            this.samples.push({ offsetMs, rttMs: Number.MAX_SAFE_INTEGER, at: this.now() });
        }
    }

    get hasSample(): boolean {
        return this.samples.length > 0;
    }

    /** Offset (server − device) in ms from the best recent sample; 0 before any response. */
    get offsetMs(): number {
        if (this.samples.length === 0) return 0;
        let best = this.samples[this.samples.length - 1];
        for (const s of this.samples) if (s.rttMs < best.rttMs) best = s;
        return best.offsetMs;
    }

    /** Server "now" in epoch ms. */
    nowMs(): number {
        return this.now() + this.offsetMs;
    }

    nowIso(): string {
        return new Date(Math.round(this.nowMs())).toISOString();
    }

    /** Milliseconds from server-now until `iso` (negative when past; Infinity for null). */
    msUntil(iso: string | null | undefined): number {
        const t = parseTime(iso);
        return t === null ? Infinity : t - this.nowMs();
    }

    reset(): void {
        this.samples = [];
    }
}

export function parseTime(iso: string | null | undefined): number | null {
    if (!iso) return null;
    const t = Date.parse(iso);
    return Number.isFinite(t) ? t : null;
}

/** "1:02:03" / "12:03" / "0:09"; never negative. */
export function formatCountdown(ms: number): string {
    if (!Number.isFinite(ms)) return '--:--';
    const total = Math.max(0, Math.ceil(ms / 1000));
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;
    const ss = s < 10 ? `0${s}` : String(s);
    if (h > 0) return `${h}:${m < 10 ? '0' : ''}${m}:${ss}`;
    return `${m}:${ss}`;
}

/** Long, friendly form for waiting screens: "2 days 3 h", "1 h 05 min", "4 min 09 s". */
export function formatDuration(ms: number): string {
    if (!Number.isFinite(ms)) return '';
    const total = Math.max(0, Math.ceil(ms / 1000));
    const d = Math.floor(total / 86400);
    const h = Math.floor((total % 86400) / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = total % 60;
    const pad = (n: number) => (n < 10 ? `0${n}` : String(n));
    if (d > 0) return `${d} day${d === 1 ? '' : 's'} ${h} h`;
    if (h > 0) return `${h} h ${pad(m)} min`;
    if (m > 0) return `${m} min ${pad(s)} s`;
    return `${s} s`;
}

/** Local date/time for display ("Fri, 20 Nov, 10:00 am"), in the device's time zone. */
export function formatLocalTime(iso: string | null | undefined, withDate = true): string {
    const t = parseTime(iso);
    if (t === null) return '';
    const opts: Intl.DateTimeFormatOptions = withDate
        ? { weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }
        : { hour: 'numeric', minute: '2-digit' };
    try {
        return new Intl.DateTimeFormat('en-IN', opts).format(new Date(t));
    } catch {
        return new Date(t).toLocaleString();
    }
}

/**
 * Delay before the automatic final submit (CONTRACT §7): random 0–30 s, but always leaving at
 * least `safetyS` seconds of the grace window for retries.
 */
export function finalSubmitDelayMs(graceSeconds: number, random: () => number = Math.random, safetyS = 30): number {
    const windowS = Math.max(0, Math.min(30, graceSeconds - safetyS));
    return Math.floor(random() * windowS * 1000);
}

/** Jittered interval: `seconds` × (1 + 0…`spread`). */
export function jitteredMs(seconds: number, random: () => number = Math.random, spread = 0.2): number {
    return Math.round(seconds * 1000 * (1 + random() * spread));
}
