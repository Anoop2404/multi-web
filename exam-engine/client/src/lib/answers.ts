import { canonicalAnswerString, canonicalValue, compareCodePoints } from './checksum';
import type { AnswerChanges, AnswerMap, AnswerValue, QuestionType } from './types';

/**
 * Turns UI input into the contract's answer shape (CONTRACT §1), or null for "unanswered".
 * - single / true_false: the option key
 * - multiple: keys de-duplicated and sorted ascending; [] → null
 * - numeric: the typed string trimmed; must look like a number ("-", "." alone → null)
 */
export function normaliseAnswer(type: QuestionType, value: unknown): AnswerValue | null {
    if (value === null || value === undefined) return null;
    if (type === 'multiple') {
        const list = Array.isArray(value) ? value : [value];
        const keys = Array.from(new Set(list.map(String).filter((k) => k !== ''))).sort(compareCodePoints);
        return keys.length ? keys : null;
    }
    if (type === 'numeric') {
        const text = String(value).trim();
        return NUMERIC.test(text) ? text : null;
    }
    const key = String(value);
    return key === '' ? null : key;
}

export const NUMERIC = /^-?(\d+\.?\d*|\.\d+)$/;

/** Keep only what a number field may hold while typing: digits, one ".", a leading "-". */
export function sanitiseNumericInput(raw: string): string {
    let text = String(raw ?? '').replace(/,/g, '.').replace(/[−–]/g, '-');
    const negative = /^\s*-/.test(text);
    text = text.replace(/[^0-9.]/g, '');
    const dot = text.indexOf('.');
    if (dot !== -1) text = text.slice(0, dot + 1) + text.slice(dot + 1).replace(/\./g, '');
    return (negative ? '-' : '') + text.slice(0, 24);
}

export function valuesEqual(a: AnswerValue | null | undefined, b: AnswerValue | null | undefined): boolean {
    return canonicalValue(a) === canonicalValue(b);
}

export function cloneMap(map: AnswerMap): AnswerMap {
    const out: AnswerMap = {};
    for (const k of Object.keys(map)) {
        const v = map[k];
        out[k] = Array.isArray(v) ? v.slice() : v;
    }
    return out;
}

/** Drops unanswered entries so maps compare and serialise canonically. */
export function compactMap(map: AnswerMap | null | undefined): AnswerMap {
    const out: AnswerMap = {};
    if (!map) return out;
    for (const k of Object.keys(map)) {
        const v = map[k];
        if (canonicalValue(v) === null) continue;
        out[k] = Array.isArray(v) ? v.map(String).sort(compareCodePoints) : String(v).trim();
    }
    return out;
}

/** What turns `from` into `to`: changed/new keys with their value, removed keys with null. */
export function diffAnswers(from: AnswerMap, to: AnswerMap): AnswerChanges {
    const changes: AnswerChanges = {};
    for (const k of Object.keys(to)) {
        if (!valuesEqual(from[k], to[k])) changes[k] = canonicalValue(to[k]) === null ? null : to[k];
    }
    for (const k of Object.keys(from)) {
        if (canonicalValue(from[k]) !== null && canonicalValue(to[k]) === null) changes[k] = null;
    }
    return changes;
}

export function applyChanges(map: AnswerMap, changes: AnswerChanges): AnswerMap {
    const out = cloneMap(map);
    for (const k of Object.keys(changes)) {
        const v = changes[k];
        if (v === null || canonicalValue(v) === null) delete out[k];
        else out[k] = Array.isArray(v) ? v.slice() : v;
    }
    return out;
}

export function mapsEqual(a: AnswerMap, b: AnswerMap): boolean {
    return canonicalAnswerString(a) === canonicalAnswerString(b);
}

export function sameFlags(a: readonly string[], b: readonly string[]): boolean {
    if (a.length !== b.length) return false;
    const sa = a.slice().sort();
    const sb = b.slice().sort();
    return sa.every((v, i) => v === sb[i]);
}

/** Snapshot of what one outgoing sync carried; used to acknowledge exactly that. */
export interface Outgoing {
    baseRev: number;
    changes: AnswerChanges;
    snapshot: AnswerMap;
    flagged: string[];
    localRev: number;
    eventCount: number;
}

export interface LedgerData {
    answers: AnswerMap;
    acked: AnswerMap;
    flagged: string[];
    ackedFlagged: string[];
    serverRev: number;
    localRev: number;
    ackedLocalRev: number;
    /** Canonical strings' checksums this device has sent recently (for reload reconciliation). */
    sentChecksums: string[];
    lastChangeAt: number;
    firstDirtyAt: number;
}

/**
 * The offline-first answer ledger. `answers` is what the student sees; `acked` is what the
 * server is known to hold at `serverRev`. Every sync sends diff(acked → answers). A response
 * that accepts the sync acknowledges exactly the snapshot that was sent, so edits made while a
 * request was in flight stay pending for the next one.
 */
export class AnswerLedger {
    answers: AnswerMap = {};
    acked: AnswerMap = {};
    flagged: string[] = [];
    ackedFlagged: string[] = [];
    serverRev = 0;
    localRev = 0;
    ackedLocalRev = 0;
    sentChecksums: string[] = [];
    lastChangeAt = 0;
    firstDirtyAt = 0;

    static fromServer(answers: AnswerMap, flagged: string[], rev: number): AnswerLedger {
        const l = new AnswerLedger();
        l.answers = compactMap(answers);
        l.acked = cloneMap(l.answers);
        l.flagged = flagged.map(String);
        l.ackedFlagged = l.flagged.slice();
        l.serverRev = rev;
        return l;
    }

    static fromJSON(data: LedgerData): AnswerLedger {
        const l = new AnswerLedger();
        l.answers = compactMap(data.answers);
        l.acked = compactMap(data.acked);
        l.flagged = (data.flagged ?? []).map(String);
        l.ackedFlagged = (data.ackedFlagged ?? []).map(String);
        l.serverRev = Number(data.serverRev) || 0;
        l.localRev = Number(data.localRev) || 0;
        l.ackedLocalRev = Number(data.ackedLocalRev) || 0;
        l.sentChecksums = (data.sentChecksums ?? []).slice(-8);
        l.lastChangeAt = Number(data.lastChangeAt) || 0;
        l.firstDirtyAt = Number(data.firstDirtyAt) || 0;
        return l;
    }

    toJSON(): LedgerData {
        return {
            answers: cloneMap(this.answers),
            acked: cloneMap(this.acked),
            flagged: this.flagged.slice(),
            ackedFlagged: this.ackedFlagged.slice(),
            serverRev: this.serverRev,
            localRev: this.localRev,
            ackedLocalRev: this.ackedLocalRev,
            sentChecksums: this.sentChecksums.slice(-8),
            lastChangeAt: this.lastChangeAt,
            firstDirtyAt: this.firstDirtyAt,
        };
    }

    get(qid: string): AnswerValue | undefined {
        return this.answers[qid];
    }

    /** Sets (or clears with null) one answer. Returns true when something changed. */
    set(qid: string, value: AnswerValue | null, now = Date.now()): boolean {
        const key = String(qid);
        if (valuesEqual(this.answers[key], value)) return false;
        if (value === null || canonicalValue(value) === null) delete this.answers[key];
        else this.answers[key] = Array.isArray(value) ? value.slice().sort(compareCodePoints) : value;
        this.touch(now);
        return true;
    }

    isFlagged(qid: string): boolean {
        return this.flagged.includes(String(qid));
    }

    setFlag(qid: string, on: boolean, now = Date.now()): boolean {
        const key = String(qid);
        const has = this.flagged.includes(key);
        if (has === on) return false;
        this.flagged = on ? this.flagged.concat(key) : this.flagged.filter((f) => f !== key);
        this.touch(now);
        return true;
    }

    private touch(now: number): void {
        if (!this.isDirty()) this.firstDirtyAt = now;
        this.localRev++;
        this.lastChangeAt = now;
    }

    /** Unacknowledged answer or flag changes exist. */
    isDirty(): boolean {
        return this.localRev > this.ackedLocalRev;
    }

    pendingChanges(): AnswerChanges {
        return diffAnswers(this.acked, this.answers);
    }

    /** Captures what the next sync sends. */
    buildOutgoing(eventCount = 0): Outgoing {
        return {
            baseRev: this.serverRev,
            changes: this.pendingChanges(),
            snapshot: cloneMap(this.answers),
            flagged: this.flagged.slice(),
            localRev: this.localRev,
            eventCount,
        };
    }

    /** The server accepted `out` and is now at `rev`. */
    ack(out: Outgoing, rev: number): void {
        this.acked = cloneMap(out.snapshot);
        this.ackedFlagged = out.flagged.slice();
        this.serverRev = rev;
        if (out.localRev > this.ackedLocalRev) this.ackedLocalRev = out.localRev;
        if (!this.isDirty()) this.firstDirtyAt = 0;
    }

    /** Remember a checksum this device sent (so a reload can tell "our own content"). */
    rememberSent(checksum: string): void {
        if (this.sentChecksums[this.sentChecksums.length - 1] === checksum) return;
        this.sentChecksums.push(checksum);
        if (this.sentChecksums.length > 8) this.sentChecksums.shift();
    }

    /** Answered = has a non-empty value. */
    isAnswered(qid: string): boolean {
        return canonicalValue(this.answers[String(qid)]) !== null;
    }

    answeredCount(): number {
        return Object.keys(this.answers).filter((k) => canonicalValue(this.answers[k]) !== null).length;
    }
}

export type ReconcileMode = 'in_sync' | 'local' | 'server';

export interface ReconcileResult {
    ledger: AnswerLedger;
    mode: ReconcileMode;
    /** True when the server must be overwritten with the local map (POST /api/resync). */
    needResync: boolean;
    /** Local answers that were dropped because another device wrote newer content. */
    discarded: AnswerMap | null;
}

/**
 * Reload rule (task §6): restore from IndexedDB + /api/state, prefer whichever has the newer
 * content, resync if they differ.
 * - equal content → in sync (adopt the server rev).
 * - the server's rev went backwards (store failover lost writes) → local is newer: resync.
 * - the server holds content this device sent (its checksum is one we remember, or what we last
 *   had acknowledged) → anything different here is newer: keep local, resync.
 * - otherwise the server is newer (e.g. written on another device after a switch) → take it;
 *   unsynced local answers are returned in `discarded` so the caller can keep a copy.
 */
export function reconcile(
    local: AnswerLedger | null,
    server: { answers: AnswerMap; flagged: string[]; rev: number },
    serverChecksum: string,
    localAckedChecksum: string | null,
): ReconcileResult {
    const serverMap = compactMap(server.answers);
    if (!local) {
        return { ledger: AnswerLedger.fromServer(serverMap, server.flagged ?? [], server.rev), mode: 'server', needResync: false, discarded: null };
    }
    if (mapsEqual(local.answers, serverMap)) {
        local.acked = cloneMap(serverMap);
        local.serverRev = server.rev;
        if (sameFlags(local.flagged, server.flagged ?? [])) {
            local.ackedFlagged = local.flagged.slice();
            local.ackedLocalRev = local.localRev;
            local.firstDirtyAt = 0;
            return { ledger: local, mode: 'in_sync', needResync: false, discarded: null };
        }
        // Only the review flags differ: keep the local ones; the next sync carries them.
        local.ackedFlagged = (server.flagged ?? []).slice();
        return { ledger: local, mode: 'local', needResync: false, discarded: null };
    }
    const serverIsOurs = serverChecksum === localAckedChecksum || local.sentChecksums.includes(serverChecksum);
    if (server.rev < local.serverRev || serverIsOurs) {
        return { ledger: local, mode: 'local', needResync: true, discarded: null };
    }
    const discarded = local.isDirty() ? cloneMap(local.answers) : null;
    const ledger = AnswerLedger.fromServer(serverMap, server.flagged ?? [], server.rev);
    ledger.sentChecksums = local.sentChecksums.slice();
    return { ledger, mode: 'server', needResync: false, discarded };
}
