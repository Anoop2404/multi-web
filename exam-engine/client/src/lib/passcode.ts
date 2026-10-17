/**
 * Exam passcodes (CONTRACT §5.2): printed as XXXXX-XXXXX from the alphabet
 * ABCDEFGHJKMNPQRSTUVWXYZ23456789. The engine normalises with "uppercase, drop everything
 * except A–Z/0–9", so the client sends exactly that (no look-alike substitution: a typed "O"
 * stays "O" and simply won't match, which keeps both sides identical).
 */
export const PASSCODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
export const PASSCODE_LENGTH = 10;

export function normalisePasscode(input: string): string {
    return String(input ?? '')
        .toUpperCase()
        .replace(/[^A-Z0-9]/g, '');
}

/** What the field shows while typing: groups of five joined by "-", capped at 10 characters. */
export function formatPasscode(input: string): string {
    const clean = normalisePasscode(input).slice(0, PASSCODE_LENGTH);
    return clean.length > 5 ? `${clean.slice(0, 5)}-${clean.slice(5)}` : clean;
}

export function isCompletePasscode(input: string): boolean {
    return normalisePasscode(input).length === PASSCODE_LENGTH;
}

/** Characters that can't appear in a real passcode (for a gentle hint, never to block). */
export function hasLookalikes(input: string): boolean {
    return /[ILO01]/.test(normalisePasscode(input));
}
