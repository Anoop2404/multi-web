import { ref } from 'vue';

/**
 * Question and option text (same convention as Laravel's resources/js/support/mcqRichText.js):
 * plain text (any Unicode, line breaks kept) with LaTeX between $…$ (inline) or $$…$$
 * (display); \( … \) and \[ … \] work too, and \$ is a literal dollar sign. Everything outside
 * a formula is HTML-escaped and KaTeX runs with trust off, so the output is safe for v-html.
 *
 * Differences from the Laravel helper: a hand-written scanner instead of a regex with
 * lookbehind (lookbehind is a syntax error on iOS < 16.4 and would stop the whole app from
 * loading), and KaTeX is loaded lazily (in the waiting room) to keep the login page small.
 */
export interface Token {
    kind: 'text' | 'math';
    value: string;
    display?: boolean;
}

export function tokenize(source: string): Token[] {
    const out: Token[] = [];
    let text = '';
    let i = 0;
    const n = source.length;
    const pushText = () => {
        if (text) out.push({ kind: 'text', value: text });
        text = '';
    };
    const pushMath = (value: string, display: boolean) => {
        pushText();
        out.push({ kind: 'math', value, display });
    };
    while (i < n) {
        const c = source[i];
        const next = source[i + 1];
        if (c === '\\' && next === '$') {
            text += '$';
            i += 2;
            continue;
        }
        if (c === '$' && next === '$') {
            const end = source.indexOf('$$', i + 2);
            if (end > i + 2) {
                pushMath(source.slice(i + 2, end), true);
                i = end + 2;
                continue;
            }
        }
        if (c === '\\' && (next === '[' || next === '(')) {
            const close = next === '[' ? '\\]' : '\\)';
            const end = source.indexOf(close, i + 2);
            if (end > i + 2) {
                pushMath(source.slice(i + 2, end), next === '[');
                i = end + 2;
                continue;
            }
        }
        if (c === '$' && next !== '$') {
            let k = i + 1;
            while (k < n && source[k] !== '$' && source[k] !== '\n') k++;
            if (k < n && source[k] === '$' && k > i + 1 && source[k - 1] !== '\\') {
                pushMath(source.slice(i + 1, k), false);
                i = k + 1;
                continue;
            }
        }
        text += c;
        i++;
    }
    pushText();
    return out;
}

export function escapeHtml(text: string): string {
    return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

export function hasFormula(text: string | null | undefined): boolean {
    return typeof text === 'string' && tokenize(text).some((t) => t.kind === 'math');
}

type Katex = { renderToString: (tex: string, options: Record<string, unknown>) => string };
let katex: Katex | null = null;
let loading: Promise<boolean> | null = null;

/** Becomes true once KaTeX (JS + CSS) is loaded; RichText re-renders on it. */
export const katexReady = ref(false);

export function loadKatex(): Promise<boolean> {
    if (loading) return loading;
    loading = Promise.all([import('katex'), import('katex/dist/katex.min.css')])
        .then(([mod]) => {
            katex = ((mod as { default?: Katex }).default ?? mod) as Katex;
            katexReady.value = true;
            warmKatexFonts();
            return true;
        })
        .catch(() => {
            loading = null;
            return false;
        });
    return loading;
}

/** Ask the browser to fetch the common KaTeX fonts now, while we are surely online. */
function warmKatexFonts(): void {
    const fonts = (document as Document & { fonts?: FontFaceSet }).fonts;
    if (!fonts || typeof fonts.load !== 'function') return;
    const faces = ['16px KaTeX_Main', 'bold 16px KaTeX_Main', 'italic 16px KaTeX_Math', '16px KaTeX_AMS', '16px KaTeX_Size1', '16px KaTeX_Size2', '16px KaTeX_Size3', '16px KaTeX_Size4'];
    for (const face of faces) fonts.load(face).catch(() => undefined);
}

function renderFormula(tex: string, displayMode: boolean): string {
    if (!katex) return `<span class="tex-raw">${escapeHtml(tex)}</span>`;
    try {
        return katex.renderToString(tex, {
            displayMode,
            throwOnError: false,
            trust: false,
            strict: 'ignore',
            maxSize: 20,
            maxExpand: 500,
            output: 'html',
        });
    } catch {
        return `<code>${escapeHtml(tex)}</code>`;
    }
}

/** Safe HTML for v-html. */
export function renderRichText(text: string | null | undefined): string {
    if (text === null || text === undefined || text === '') return '';
    let html = '';
    for (const token of tokenize(String(text))) {
        html += token.kind === 'text' ? escapeHtml(token.value) : renderFormula(token.value, !!token.display);
    }
    return html;
}
