import { createStore, del, get, keys, set, type UseStore } from 'idb-keyval';
import type { BundleEnvelope, BroadcastMessage, ExamState, ProctorEvent } from './types';
import type { LedgerData } from './answers';

/**
 * Device storage. IndexedDB (idb-keyval) first; if it is unavailable (private mode, old
 * WebView, quota) we fall back to localStorage, then to memory, so the exam keeps running.
 * Writes are serialised so an older snapshot can never overwrite a newer one.
 */
let store: UseStore | null = null;
let mode: 'idb' | 'local' | 'memory' = 'idb';
const memory = new Map<string, unknown>();
const LS_PREFIX = 'sxe:';

function idbStore(): UseStore {
    if (!store) store = createStore('sahodaya-exam', 'kv');
    return store;
}

export function storageMode(): string {
    return mode;
}

async function rawGet<T>(key: string): Promise<T | undefined> {
    if (mode === 'idb') {
        try {
            const v = (await get(key, idbStore())) as T | undefined;
            return v === undefined ? (memory.get(key) as T | undefined) : v;
        } catch {
            mode = 'local';
        }
    }
    if (mode === 'local') {
        try {
            const v = window.localStorage.getItem(LS_PREFIX + key);
            if (v !== null) return JSON.parse(v) as T;
        } catch {
            mode = 'memory';
        }
    }
    return memory.get(key) as T | undefined;
}

async function rawSet(key: string, value: unknown, allowLocalStorage = true): Promise<void> {
    memory.set(key, value);
    if (mode === 'idb') {
        try {
            await set(key, value, idbStore());
            return;
        } catch {
            mode = 'local';
        }
    }
    if (mode === 'local' && allowLocalStorage) {
        try {
            window.localStorage.setItem(LS_PREFIX + key, JSON.stringify(value));
            return;
        } catch {
            mode = 'memory';
        }
    }
}

async function rawDel(key: string): Promise<void> {
    memory.delete(key);
    try {
        if (mode === 'idb') await del(key, idbStore());
        else if (mode === 'local') window.localStorage.removeItem(LS_PREFIX + key);
    } catch {
        /* ignore */
    }
}

let chain: Promise<unknown> = Promise.resolve();
function serial<T>(fn: () => Promise<T>): Promise<T> {
    const next = chain.then(fn, fn);
    chain = next.catch(() => undefined);
    return next;
}

/** Round-trip test for the system check. */
export async function storageSelfTest(): Promise<boolean> {
    const key = `selftest:${Math.random().toString(36).slice(2)}`;
    try {
        await rawSet(key, { ok: 1 });
        const back = await rawGet<{ ok: number }>(key);
        await rawDel(key);
        return mode === 'idb' && back?.ok === 1;
    } catch {
        return false;
    }
}

// ---- Attempt record (keyed by run + reg_id) ----

export interface LocalAttempt {
    v: 1;
    run: string;
    regId: number;
    ledger: LedgerData;
    events: ProctorEvent[];
    offlineSince: string | null;
    needResync: boolean;
    seenMessageIds: number[];
    paperKey: string | null;
    bundleVersion: number;
    /** Last /api/state we saw, for an offline reload while writing. */
    state: ExamState | null;
    messages: BroadcastMessage[];
    submitted: { receipt: string | null; submitted_at: string | null } | null;
    discarded?: Record<string, unknown> | null;
    clockOffsetMs?: number;
    savedAt: number;
}

export const attemptKey = (run: string, regId: number) => `att:${run}:${regId}`;

export function loadAttempt(run: string, regId: number): Promise<LocalAttempt | undefined> {
    return serial(() => rawGet<LocalAttempt>(attemptKey(run, regId)));
}

export function saveAttempt(rec: LocalAttempt): Promise<void> {
    const copy = JSON.parse(JSON.stringify({ ...rec, savedAt: Date.now() })) as LocalAttempt;
    return serial(() => rawSet(attemptKey(rec.run, rec.regId), copy));
}

export function deleteAttempt(run: string, regId: number): Promise<void> {
    return serial(() => rawDel(attemptKey(run, regId)));
}

/** Which attempt this device was last using (to resume offline when /api/state is unreachable). */
export function saveLastSession(run: string, regId: number): Promise<void> {
    return serial(() => rawSet('last', { run, regId }));
}

export function loadLastSession(): Promise<{ run: string; regId: number } | undefined> {
    return serial(() => rawGet<{ run: string; regId: number }>('last'));
}

export function clearLastSession(): Promise<void> {
    return serial(() => rawDel('last'));
}

// ---- Encrypted paper bundle cache ----

export const bundleKey = (run: string, version: number) => `bundle:${run}:${version}`;

export function loadBundle(run: string, version: number): Promise<BundleEnvelope | undefined> {
    return serial(() => rawGet<BundleEnvelope>(bundleKey(run, version)));
}

/** Stores a bundle and drops older versions of the same run. */
export function saveBundle(run: string, version: number, envelope: BundleEnvelope): Promise<void> {
    return serial(async () => {
        // Bundles can be megabytes: never push them into localStorage (quota), memory is fine.
        await rawSet(bundleKey(run, version), envelope, false);
        if (mode !== 'idb') return;
        try {
            const prefix = `bundle:${run}:`;
            for (const k of await keys(idbStore())) {
                const name = String(k);
                if (name.startsWith(prefix) && name !== bundleKey(run, version)) await del(name, idbStore());
            }
        } catch {
            /* ignore */
        }
    });
}

export function deleteBundle(run: string, version: number): Promise<void> {
    return serial(() => rawDel(bundleKey(run, version)));
}

// ---- Small per-device flags ----

export function loadFlag<T>(key: string): Promise<T | undefined> {
    return serial(() => rawGet<T>(`flag:${key}`));
}

export function saveFlag(key: string, value: unknown): Promise<void> {
    return serial(() => rawSet(`flag:${key}`, value));
}
