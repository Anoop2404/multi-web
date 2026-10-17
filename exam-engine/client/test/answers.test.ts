import { describe, expect, it } from 'vitest';
import {
    AnswerLedger,
    applyChanges,
    diffAnswers,
    normaliseAnswer,
    reconcile,
    sanitiseNumericInput,
} from '../src/lib/answers';
import { answersChecksum } from '../src/lib/checksum';

describe('normaliseAnswer', () => {
    it('shapes values per question type', () => {
        expect(normaliseAnswer('single', 'b')).toBe('b');
        expect(normaliseAnswer('true_false', 'a')).toBe('a');
        expect(normaliseAnswer('multiple', ['c', 'a', 'c'])).toEqual(['a', 'c']);
        expect(normaliseAnswer('multiple', [])).toBeNull();
        expect(normaliseAnswer('numeric', ' 12.50 ')).toBe('12.50');
        expect(normaliseAnswer('numeric', '-')).toBeNull();
        expect(normaliseAnswer('numeric', '.')).toBeNull();
        expect(normaliseAnswer('numeric', '-.5')).toBe('-.5');
        expect(normaliseAnswer('single', '')).toBeNull();
    });

    it('sanitises typed numbers', () => {
        expect(sanitiseNumericInput('12,5')).toBe('12.5');
        expect(sanitiseNumericInput('−3')).toBe('-3');
        expect(sanitiseNumericInput('1.2.3')).toBe('1.23');
        expect(sanitiseNumericInput('ab-4x')).toBe('4');
        expect(sanitiseNumericInput('-0.75')).toBe('-0.75');
    });
});

describe('diff / apply', () => {
    it('sends changed and new keys, and null for cleared ones', () => {
        const from = { '1': 'a', '2': ['a', 'b'], '3': '7' };
        const to = { '1': 'a', '2': ['b', 'a'], '3': '8', '4': 'c' };
        expect(diffAnswers(from, to)).toEqual({ '3': '8', '4': 'c' });
        expect(diffAnswers(to, { '1': 'a' })).toEqual({ '2': null, '3': null, '4': null });
        expect(applyChanges(from, { '1': null, '5': 'd' })).toEqual({ '2': ['a', 'b'], '3': '7', '5': 'd' });
    });
});

describe('AnswerLedger (offline-first diff queue)', () => {
    it('sends only changes since the last acknowledged rev', () => {
        const l = AnswerLedger.fromServer({ '1': 'a' }, [], 4);
        expect(l.isDirty()).toBe(false);
        l.set('2', 'b');
        l.set('3', ['c', 'a']);
        const out = l.buildOutgoing();
        expect(out.baseRev).toBe(4);
        expect(out.changes).toEqual({ '2': 'b', '3': ['a', 'c'] });
        l.ack(out, 5);
        expect(l.isDirty()).toBe(false);
        expect(l.serverRev).toBe(5);
        expect(l.pendingChanges()).toEqual({});
    });

    it('clearing an acknowledged answer sends null; clearing an unsent one sends nothing', () => {
        const l = AnswerLedger.fromServer({ '1': 'a' }, [], 1);
        l.set('1', null);
        expect(l.pendingChanges()).toEqual({ '1': null });
        l.set('9', 'b');
        l.set('9', null);
        expect(l.pendingChanges()).toEqual({ '1': null });
        l.ack(l.buildOutgoing(), 2);
        expect(l.answers).toEqual({});
        expect(l.pendingChanges()).toEqual({});
    });

    it('keeps edits made while a sync is in flight for the next one', () => {
        const l = AnswerLedger.fromServer({}, [], 0);
        l.set('1', 'a');
        const inFlight = l.buildOutgoing();
        l.set('2', 'b'); // typed while the request was travelling
        l.set('1', 'c'); // changed the in-flight answer again
        l.ack(inFlight, 1);
        expect(l.isDirty()).toBe(true);
        expect(l.pendingChanges()).toEqual({ '1': 'c', '2': 'b' });
        const next = l.buildOutgoing();
        expect(next.baseRev).toBe(1);
        l.ack(next, 2);
        expect(l.isDirty()).toBe(false);
    });

    it('an unacknowledged sync (failure / resync) keeps everything pending', () => {
        const l = AnswerLedger.fromServer({}, [], 3);
        l.set('1', 'a');
        l.buildOutgoing(); // sent, but no ack
        expect(l.pendingChanges()).toEqual({ '1': 'a' });
        expect(l.buildOutgoing().baseRev).toBe(3);
    });

    it('setting the same value is a no-op; flags are tracked as changes', () => {
        const l = AnswerLedger.fromServer({ '1': ['a', 'b'] }, [], 0);
        expect(l.set('1', ['b', 'a'])).toBe(false);
        expect(l.isDirty()).toBe(false);
        expect(l.setFlag('1', true)).toBe(true);
        expect(l.isDirty()).toBe(true);
        expect(l.buildOutgoing().flagged).toEqual(['1']);
        expect(l.pendingChanges()).toEqual({});
    });

    it('round-trips through JSON (IndexedDB copy)', () => {
        const l = AnswerLedger.fromServer({ '1': 'a' }, ['1'], 2);
        l.set('2', '3.5');
        const back = AnswerLedger.fromJSON(JSON.parse(JSON.stringify(l.toJSON())));
        expect(back.pendingChanges()).toEqual({ '2': '3.5' });
        expect(back.serverRev).toBe(2);
        expect(back.isDirty()).toBe(true);
    });
});

describe('reconcile on reload', () => {
    const server = (answers: Record<string, string | string[]>, rev: number, flagged: string[] = []) => ({ answers, rev, flagged });

    it('no local copy → take the server', async () => {
        const s = server({ '1': 'a' }, 3);
        const r = reconcile(null, s, await answersChecksum(s.answers), null);
        expect(r.mode).toBe('server');
        expect(r.ledger.answers).toEqual({ '1': 'a' });
        expect(r.ledger.serverRev).toBe(3);
    });

    it('same content → in sync, adopts the server rev', async () => {
        const local = AnswerLedger.fromServer({ '1': 'a' }, [], 2);
        local.set('2', 'b');
        const s = server({ '1': 'a', '2': 'b' }, 3); // our in-flight sync landed, reply was lost
        const r = reconcile(local, s, await answersChecksum(s.answers), await answersChecksum(local.acked));
        expect(r.mode).toBe('in_sync');
        expect(r.ledger.serverRev).toBe(3);
        expect(r.ledger.isDirty()).toBe(false);
    });

    it('local unsent edits on top of content we sent → keep local and resync', async () => {
        const local = AnswerLedger.fromServer({ '1': 'a' }, [], 2);
        local.set('2', 'b');
        const s = server({ '1': 'a' }, 2);
        const r = reconcile(local, s, await answersChecksum(s.answers), await answersChecksum(local.acked));
        expect(r.mode).toBe('local');
        expect(r.needResync).toBe(true);
        expect(r.ledger.answers).toEqual({ '1': 'a', '2': 'b' });
    });

    it('server rev went backwards (store failover) → local wins', async () => {
        const local = AnswerLedger.fromServer({ '1': 'a', '2': 'b' }, [], 9);
        const s = server({ '1': 'a' }, 7);
        const r = reconcile(local, s, await answersChecksum(s.answers), await answersChecksum(local.acked));
        expect(r.mode).toBe('local');
        expect(r.needResync).toBe(true);
    });

    it('server has content this device never sent (another device) → server wins, local kept aside', async () => {
        const local = AnswerLedger.fromServer({ '1': 'a' }, [], 2);
        local.set('3', 'd');
        const s = server({ '1': 'b', '2': 'c' }, 6);
        const r = reconcile(local, s, await answersChecksum(s.answers), await answersChecksum(local.acked));
        expect(r.mode).toBe('server');
        expect(r.ledger.answers).toEqual({ '1': 'b', '2': 'c' });
        expect(r.discarded).toEqual({ '1': 'a', '3': 'd' });
    });
});
