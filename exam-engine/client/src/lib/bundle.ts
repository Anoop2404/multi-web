import type { BundleEnvelope, Paper, PaperQuestion } from './types';

/** Standard or URL-safe base64 (padding optional) → bytes. */
export function base64ToBytes(b64: string): Uint8Array {
    let clean = String(b64).replace(/\s+/g, '').replace(/-/g, '+').replace(/_/g, '/');
    while (clean.length % 4 !== 0) clean += '=';
    const binary = atob(clean);
    const out = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) out[i] = binary.charCodeAt(i);
    return out;
}

export class BundleError extends Error {
    constructor(
        message: string,
        readonly reason: 'key' | 'decrypt' | 'format',
    ) {
        super(message);
    }
}

/**
 * Decrypts the paper bundle (CONTRACT §2.3): AES-256-GCM, 12-byte IV, ciphertext with the
 * 16-byte tag appended (exactly what WebCrypto expects), 32-byte key, UTF-8 JSON plaintext.
 */
export async function decryptBundle(envelope: Pick<BundleEnvelope, 'iv' | 'ciphertext'>, keyB64: string): Promise<Paper> {
    const keyBytes = base64ToBytes(keyB64);
    if (keyBytes.length !== 32) throw new BundleError(`paper key must be 32 bytes, got ${keyBytes.length}`, 'key');
    const iv = base64ToBytes(envelope.iv);
    const data = base64ToBytes(envelope.ciphertext);
    let plain: ArrayBuffer;
    try {
        const key = await crypto.subtle.importKey('raw', keyBytes as BufferSource, { name: 'AES-GCM' }, false, ['decrypt']);
        plain = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: iv as BufferSource, tagLength: 128 }, key, data as BufferSource);
    } catch (e) {
        throw new BundleError(`could not decrypt the paper: ${(e as Error)?.message ?? e}`, 'decrypt');
    }
    let parsed: unknown;
    try {
        parsed = JSON.parse(new TextDecoder('utf-8').decode(plain));
    } catch {
        throw new BundleError('paper is not valid JSON', 'format');
    }
    return validatePaper(parsed);
}

const TYPES = ['single', 'multiple', 'true_false', 'numeric'];

/** Light shape check + defaults, so a slightly odd bundle still renders. */
export function validatePaper(raw: unknown): Paper {
    const obj = raw as { version?: unknown; questions?: unknown };
    if (!obj || typeof obj !== 'object' || !Array.isArray(obj.questions)) {
        throw new BundleError('paper has no questions', 'format');
    }
    const questions: PaperQuestion[] = [];
    for (const q of obj.questions as Record<string, unknown>[]) {
        if (!q || typeof q !== 'object' || q.id === undefined || q.id === null) continue;
        const type = (TYPES.includes(String(q.type)) ? String(q.type) : 'single') as PaperQuestion['type'];
        const options = Array.isArray(q.options)
            ? (q.options as Record<string, unknown>[])
                  .filter((o) => o && typeof o === 'object' && o.key !== undefined && o.key !== null)
                  .map((o) => ({
                      key: String(o.key),
                      label: o.label === null || o.label === undefined ? '' : String(o.label),
                      image: typeof o.image === 'string' && o.image ? o.image : null,
                  }))
            : [];
        questions.push({
            id: Number(q.id),
            type,
            section: typeof q.section === 'string' && q.section.trim() ? q.section : null,
            body: q.body === null || q.body === undefined ? '' : String(q.body),
            image: typeof q.image === 'string' && q.image ? q.image : null,
            options: type === 'numeric' ? [] : options,
            show_count: typeof q.show_count === 'number' && q.show_count > 0 ? q.show_count : null,
            marks: Number(q.marks ?? 0) || 0,
            negative_mark: Number(q.negative_mark ?? 0) || 0,
        });
    }
    if (questions.length === 0) throw new BundleError('paper has no questions', 'format');
    return { version: Number(obj.version ?? 0) || 0, questions };
}
