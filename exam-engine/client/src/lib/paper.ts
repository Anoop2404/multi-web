import { ApiError } from './api';
import { BundleError, decryptBundle } from './bundle';
import { seededShuffleGrouped } from './shuffle';
import { deleteBundle, loadBundle, saveBundle } from './storage';
import type { BundleEnvelope, ExamState, Paper, PaperQuestion } from './types';

export interface DisplayQuestion extends PaperQuestion {
    qid: string;
    /** 1-based position on this student's screen. */
    number: number;
    /** First question of its section (show the heading). */
    sectionStart: boolean;
}

/** Question order for this attempt (seeded, stable across reloads and devices). */
export function orderQuestions(paper: Paper, shuffle: boolean, seed: number): DisplayQuestion[] {
    const list = shuffle ? seededShuffleGrouped(paper.questions, seed, (q) => q.section ?? '') : paper.questions.slice();
    let prevSection: string | null | undefined;
    return list.map((q, index) => {
        const sectionStart = index === 0 ? !!q.section : q.section !== prevSection && !!q.section;
        prevSection = q.section;
        return { ...q, qid: String(q.id), number: index + 1, sectionStart };
    });
}

export interface DownloadProgress {
    loaded: number;
    total: number | null;
}

/** GET the encrypted bundle (public, immutable), reporting progress when the body streams. */
export async function downloadBundle(
    url: string,
    onProgress?: (p: DownloadProgress) => void,
    bypassCache = false,
    timeoutMs = 120000,
): Promise<BundleEnvelope> {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    let res: Response;
    try {
        // `reload` skips the HTTP cache (the bundle is served "immutable"), used after a bad decrypt.
        res = await fetch(url, { credentials: 'same-origin', signal: controller.signal, cache: bypassCache ? 'reload' : 'default' });
    } catch {
        clearTimeout(timer);
        throw new ApiError(0, 'network', 'Couldn’t download the question paper.');
    }
    try {
        if (!res.ok) throw new ApiError(res.status, res.status === 404 ? 'not_found' : 'server', `Paper download failed (${res.status})`);
        const lengthHeader = res.headers.get('Content-Length');
        const total = lengthHeader && /^\d+$/.test(lengthHeader) ? Number(lengthHeader) : null;
        let text: string;
        if (res.body && typeof res.body.getReader === 'function' && onProgress) {
            const reader = res.body.getReader();
            const chunks: Uint8Array[] = [];
            let loaded = 0;
            for (;;) {
                const { done, value } = await reader.read();
                if (done) break;
                if (value) {
                    chunks.push(value);
                    loaded += value.length;
                    onProgress({ loaded, total });
                }
            }
            const all = new Uint8Array(loaded);
            let offset = 0;
            for (const c of chunks) {
                all.set(c, offset);
                offset += c.length;
            }
            text = new TextDecoder('utf-8').decode(all);
        } else {
            text = await res.text();
            onProgress?.({ loaded: text.length, total });
        }
        const data = JSON.parse(text) as BundleEnvelope;
        if (!data || typeof data.iv !== 'string' || typeof data.ciphertext !== 'string') throw new BundleError('bundle is malformed', 'format');
        return data;
    } catch (e) {
        if (e instanceof ApiError || e instanceof BundleError) throw e;
        throw new ApiError(0, 'network', 'The question paper download was interrupted.');
    } finally {
        clearTimeout(timer);
    }
}

/** The cached bundle for this state's version, or download + cache it. */
export async function ensureBundle(state: Pick<ExamState, 'run' | 'bundle_url' | 'bundle_version'>, onProgress?: (p: DownloadProgress) => void): Promise<BundleEnvelope> {
    const cached = await loadBundle(state.run, state.bundle_version).catch(() => undefined);
    if (cached && cached.iv && cached.ciphertext) return cached;
    const fresh = await downloadBundle(state.bundle_url, onProgress);
    await saveBundle(state.run, state.bundle_version, fresh).catch(() => undefined);
    return fresh;
}

/**
 * Decrypts the paper for a writing attempt. A cached bundle that fails to decrypt (corrupt, or
 * from an older arm) is thrown away and downloaded once more before giving up.
 */
export async function openPaper(state: ExamState, onProgress?: (p: DownloadProgress) => void): Promise<DisplayQuestion[]> {
    if (!state.paper_key) throw new BundleError('no paper key yet', 'key');
    let envelope = await ensureBundle(state, onProgress);
    let paper: Paper;
    try {
        paper = await decryptBundle(envelope, state.paper_key);
    } catch (e) {
        if (!(e instanceof BundleError) || e.reason === 'key') throw e;
        await deleteBundle(state.run, state.bundle_version).catch(() => undefined);
        envelope = await downloadBundle(state.bundle_url, onProgress, true);
        paper = await decryptBundle(envelope, state.paper_key);
        await saveBundle(state.run, state.bundle_version, envelope).catch(() => undefined);
    }
    return orderQuestions(paper, state.shuffle_questions, state.seed);
}
