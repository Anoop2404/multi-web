import { describe, expect, it } from 'vitest';
import { formatPasscode, hasLookalikes, isCompletePasscode, normalisePasscode } from '../src/lib/passcode';
import { parseRoute } from '../src/lib/router';

describe('passcode (CONTRACT §5.2)', () => {
    it('normalises: uppercase, keep only A–Z / 0–9', () => {
        expect(normalisePasscode('k7m2p-q9rtx')).toBe('K7M2PQ9RTX');
        expect(normalisePasscode(' K7M2P – Q9RTX ')).toBe('K7M2PQ9RTX');
        expect(normalisePasscode('k7.m2p_q9 rtx')).toBe('K7M2PQ9RTX');
        expect(normalisePasscode('')).toBe('');
    });

    it('formats as XXXXX-XXXXX while typing', () => {
        expect(formatPasscode('k7m2')).toBe('K7M2');
        expect(formatPasscode('k7m2p')).toBe('K7M2P');
        expect(formatPasscode('k7m2pq')).toBe('K7M2P-Q');
        expect(formatPasscode('K7M2PQ9RTX')).toBe('K7M2P-Q9RTX');
        expect(formatPasscode('K7M2P-Q9RTX-EXTRA')).toBe('K7M2P-Q9RTX');
        expect(formatPasscode('K7M2P--')).toBe('K7M2P');
    });

    it('formatting is idempotent and normalising a formatted code gives the HMAC input', () => {
        const typed = 'k7m2p q9rtx';
        const shown = formatPasscode(typed);
        expect(formatPasscode(shown)).toBe(shown);
        expect(normalisePasscode(shown)).toBe('K7M2PQ9RTX');
        expect(isCompletePasscode(shown)).toBe(true);
        expect(isCompletePasscode('K7M2P-Q9RT')).toBe(false);
    });

    it('flags characters outside the passcode alphabet', () => {
        expect(hasLookalikes('K7M2P-Q9RTX')).toBe(false);
        expect(hasLookalikes('K7M2P-Q9RT0')).toBe(true);
        expect(hasLookalikes('IL01O')).toBe(true);
    });
});

describe('router', () => {
    it('reads the Sahodaya code and launch token', () => {
        expect(parseRoute('/', '')).toEqual({ code: null, launch: null });
        expect(parseRoute('/mlp', '')).toEqual({ code: 'MLP', launch: null });
        expect(parseRoute('/MLP/', '?launch=abc.def')).toEqual({ code: 'MLP', launch: 'abc.def' });
        expect(parseRoute('/', '?x=1&launch=eyJ%2Bx.sig')).toEqual({ code: null, launch: 'eyJ+x.sig' });
    });
});
