import { ServerClock } from './clock';

/** The one server clock for the app (CONTRACT §4: every response carries server_time). */
export const clock = new ServerClock();

export type ErrorCode =
    | 'unauthorized'
    | 'forbidden'
    | 'not_found'
    | 'invalid'
    | 'not_open_yet'
    | 'closed'
    | 'locked'
    | 'device_conflict'
    | 'busy'
    | 'rate_limited'
    | 'already_submitted'
    | 'wrong_pin'
    | 'network'
    | 'timeout'
    | 'server'
    | string;

export class ApiError extends Error {
    constructor(
        readonly status: number,
        readonly code: ErrorCode,
        message: string,
        /** Seconds from Retry-After (or null). */
        readonly retryAfter: number | null = null,
    ) {
        super(message);
    }

    /** No HTTP response at all (offline, DNS, CORS, aborted). */
    get isNetwork(): boolean {
        return this.status === 0;
    }

    /** Worth retrying later without user action. */
    get isTransient(): boolean {
        return this.status === 0 || this.status >= 500 || this.code === 'busy' || this.code === 'rate_limited' || this.status === 429;
    }
}

export function parseRetryAfter(header: string | null, nowMs = Date.now()): number | null {
    if (!header) return null;
    const trimmed = header.trim();
    if (/^\d+$/.test(trimmed)) return Number(trimmed);
    const t = Date.parse(trimmed);
    return Number.isFinite(t) ? Math.max(0, Math.ceil((t - nowMs) / 1000)) : null;
}

export interface RequestOptions {
    timeoutMs?: number;
    signal?: AbortSignal;
}

/**
 * JSON request to the engine. State-changing requests carry `X-Exam-Client: 1` (CSRF guard,
 * CONTRACT §4). Any `server_time` (top level or inside `state`) feeds the server clock.
 */
export async function api<T>(method: 'GET' | 'POST', path: string, body?: unknown, opts: RequestOptions = {}): Promise<T> {
    const controller = new AbortController();
    const timeoutMs = opts.timeoutMs ?? 20000;
    let timedOut = false;
    const timer = setTimeout(() => {
        timedOut = true;
        controller.abort();
    }, timeoutMs);
    const onAbort = () => controller.abort();
    if (opts.signal) opts.signal.addEventListener('abort', onAbort);

    const headers: Record<string, string> = { Accept: 'application/json' };
    if (method !== 'GET') {
        headers['Content-Type'] = 'application/json';
        headers['X-Exam-Client'] = '1';
    }
    const sentAt = Date.now();
    let res: Response;
    try {
        res = await fetch(path, {
            method,
            headers,
            body: body === undefined ? undefined : JSON.stringify(body),
            credentials: 'same-origin',
            cache: 'no-store',
            signal: controller.signal,
        });
    } catch {
        throw new ApiError(0, timedOut ? 'timeout' : 'network', timedOut ? 'The server took too long to answer.' : 'Can’t reach the exam server.');
    } finally {
        clearTimeout(timer);
        if (opts.signal) opts.signal.removeEventListener('abort', onAbort);
    }
    const receivedAt = Date.now();

    let data: unknown = null;
    const text = await res.text().catch(() => '');
    if (text) {
        try {
            data = JSON.parse(text);
        } catch {
            data = null;
        }
    }

    if (data && typeof data === 'object') {
        const obj = data as { server_time?: unknown; state?: { server_time?: unknown } };
        const serverTime = typeof obj.server_time === 'string' ? obj.server_time : typeof obj.state?.server_time === 'string' ? obj.state.server_time : null;
        if (serverTime) clock.update(serverTime, sentAt, receivedAt);
    }

    if (!res.ok) {
        const err = (data ?? {}) as { error?: string; message?: string };
        const code = err.error || (res.status === 429 ? 'rate_limited' : res.status >= 500 ? 'server' : `http_${res.status}`);
        throw new ApiError(res.status, code, err.message || res.statusText || 'Request failed', parseRetryAfter(res.headers.get('Retry-After')));
    }
    return data as T;
}

/** Encodes one path segment (a run id like "uuid:12" becomes "uuid%3A12"). */
export function seg(value: string | number): string {
    return encodeURIComponent(String(value));
}
