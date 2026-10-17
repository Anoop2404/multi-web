import { api } from './api';
import { storageSelfTest } from './storage';
import type { SystemCheckRequest } from './types';

export type CheckStatus = 'ok' | 'warn' | 'fail';

export interface CheckItem {
    id: 'browser' | 'crypto' | 'storage' | 'screen' | 'online' | 'latency';
    label: string;
    status: CheckStatus;
    detail: string;
}

export interface SystemCheckResult {
    items: CheckItem[];
    ok: boolean;
    payload: SystemCheckRequest;
}

function browserSupported(): boolean {
    try {
        return (
            typeof fetch === 'function' &&
            typeof Promise !== 'undefined' &&
            typeof Proxy !== 'undefined' &&
            typeof TextEncoder !== 'undefined' &&
            typeof AbortController !== 'undefined' &&
            typeof Intl !== 'undefined'
        );
    } catch {
        return false;
    }
}

/** Real AES-GCM + SHA-256 round trip, not just "crypto.subtle exists". */
export async function cryptoSelfTest(): Promise<boolean> {
    try {
        if (!window.isSecureContext || !crypto?.subtle) return false;
        const raw = crypto.getRandomValues(new Uint8Array(32));
        const iv = crypto.getRandomValues(new Uint8Array(12));
        const key = await crypto.subtle.importKey('raw', raw, { name: 'AES-GCM' }, false, ['encrypt', 'decrypt']);
        const sealed = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, new TextEncoder().encode('ok'));
        const opened = await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, key, sealed);
        await crypto.subtle.digest('SHA-256', new Uint8Array([1]));
        return new TextDecoder().decode(opened) === 'ok';
    } catch {
        return false;
    }
}

/** Median of three timed GET /api/state calls (they also refresh the server clock). */
export async function measureLatency(): Promise<number | null> {
    const samples: number[] = [];
    for (let i = 0; i < 3; i++) {
        const t0 = performance.now();
        try {
            await api('GET', '/api/state', undefined, { timeoutMs: 10000 });
            samples.push(performance.now() - t0);
        } catch {
            /* counted as missing */
        }
    }
    if (!samples.length) return null;
    samples.sort((a, b) => a - b);
    return Math.round(samples[Math.floor(samples.length / 2)]);
}

export async function runSystemCheck(): Promise<SystemCheckResult> {
    const items: CheckItem[] = [];
    const browserOk = browserSupported();
    items.push({
        id: 'browser',
        label: 'Browser',
        status: browserOk ? 'ok' : 'fail',
        detail: browserOk ? 'Supported' : 'This browser is too old. Update Chrome, Safari, Edge or Firefox.',
    });

    const cryptoOk = await cryptoSelfTest();
    items.push({
        id: 'crypto',
        label: 'Secure paper',
        status: cryptoOk ? 'ok' : 'fail',
        detail: cryptoOk
            ? 'This browser can open the encrypted question paper'
            : window.isSecureContext
              ? 'This browser can’t open the encrypted question paper. Update it or use another browser.'
              : 'Open the exam using its https:// address.',
    });

    const storageOk = await storageSelfTest();
    items.push({
        id: 'storage',
        label: 'Saving answers on this device',
        status: storageOk ? 'ok' : 'warn',
        detail: storageOk ? 'Works' : 'Limited (private/incognito window?). Use a normal window so answers survive a reload.',
    });

    const w = Math.round(window.innerWidth || screen.width);
    const h = Math.round(window.innerHeight || screen.height);
    const small = Math.min(w, h) < 320 || Math.max(w, h) < 480;
    items.push({
        id: 'screen',
        label: 'Screen',
        status: small ? 'warn' : 'ok',
        detail: small ? `${w}×${h} is very small. Rotate the phone or use a bigger screen.` : `${w}×${h}`,
    });

    const online = navigator.onLine !== false;
    items.push({ id: 'online', label: 'Internet', status: online ? 'ok' : 'fail', detail: online ? 'Connected' : 'You seem to be offline.' });

    const latency = online ? await measureLatency() : null;
    items.push({
        id: 'latency',
        label: 'Connection to the exam server',
        status: latency === null ? 'fail' : latency > 2000 ? 'warn' : 'ok',
        detail: latency === null ? 'Couldn’t reach the server.' : latency > 2000 ? `Slow (${latency} ms). Move closer to Wi-Fi or switch networks.` : `${latency} ms`,
    });

    const payload: SystemCheckRequest = {
        user_agent: navigator.userAgent.slice(0, 400),
        screen: `${Math.round(screen.width)}x${Math.round(screen.height)}`,
        online,
        latency_ms: latency ?? 0,
        storage_ok: storageOk,
        crypto_ok: cryptoOk,
    };
    return { items, ok: items.every((i) => i.status !== 'fail'), payload };
}
