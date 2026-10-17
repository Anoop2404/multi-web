import type { AnswerMap, AnswerValue } from './types';

/**
 * The value part of one canonical line (CONTRACT §1): the option key, the multiple keys sorted
 * ascending and joined by ",", or the numeric string trimmed. Returns null for "unanswered"
 * (null, "" after trimming, or an empty array) so the line is left out.
 */
export function canonicalValue(value: AnswerValue | null | undefined): string | null {
    if (value === null || value === undefined) return null;
    if (Array.isArray(value)) {
        if (value.length === 0) return null;
        return value.map(String).slice().sort(compareCodePoints).join(',');
    }
    const text = String(value).trim();
    return text === '' ? null : text;
}

/** Plain code-point order (same as Go's sort.Strings for ASCII keys). */
export function compareCodePoints(a: string, b: string): number {
    return a < b ? -1 : a > b ? 1 : 0;
}

/** Question ids are numeric strings; order them as numbers (then as text for safety). */
export function compareQuestionIds(a: string, b: string): number {
    const na = Number(a);
    const nb = Number(b);
    if (Number.isFinite(na) && Number.isFinite(nb) && na !== nb) return na - nb;
    return compareCodePoints(a, b);
}

/** `"{qid}={value}\n"` for every answered question, by numeric question id ascending. */
export function canonicalAnswerString(answers: AnswerMap): string {
    let out = '';
    for (const qid of Object.keys(answers).sort(compareQuestionIds)) {
        const value = canonicalValue(answers[qid]);
        if (value !== null) out += `${qid}=${value}\n`;
    }
    return out;
}

const HEX: string[] = [];
for (let i = 0; i < 256; i++) HEX.push((i < 16 ? '0' : '') + i.toString(16));

export function toHex(buffer: ArrayBuffer | Uint8Array): string {
    const bytes = buffer instanceof Uint8Array ? buffer : new Uint8Array(buffer);
    let out = '';
    for (let i = 0; i < bytes.length; i++) out += HEX[bytes[i]];
    return out;
}

export async function sha256Hex(text: string): Promise<string> {
    const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(text));
    return toHex(digest);
}

/** Lowercase hex SHA-256 of the canonical answer string (CONTRACT §1). */
export function answersChecksum(answers: AnswerMap): Promise<string> {
    return sha256Hex(canonicalAnswerString(answers));
}
