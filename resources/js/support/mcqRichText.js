import katex from 'katex';

/**
 * Question and option text is plain text (any Unicode, line breaks kept) with LaTeX
 * formulas between $…$ (inline) or $$…$$ (display). \( … \) and \[ … \] work too, and
 * \$ is a literal dollar sign. Everything outside a formula is HTML-escaped, and KaTeX
 * runs with trust off, so the result is safe for v-html.
 */
const MATH = /\$\$([\s\S]+?)\$\$|\\\[([\s\S]+?)\\\]|\\\(([\s\S]+?)\\\)|(?<!\\)\$([^$\n]+?)(?<!\\)\$/g;

function escapeHtml(text) {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function renderFormula(tex, displayMode) {
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

export function hasFormula(text) {
    MATH.lastIndex = 0;
    return typeof text === 'string' && MATH.test(text);
}

export function renderMcqText(text) {
    if (text === null || text === undefined || text === '') return '';
    const source = String(text);
    let html = '';
    let last = 0;
    MATH.lastIndex = 0;
    for (const match of source.matchAll(MATH)) {
        html += escapeHtml(source.slice(last, match.index).replace(/\\\$/g, '$'));
        const [, display, bracket, paren, inline] = match;
        html += display !== undefined || bracket !== undefined
            ? renderFormula(display ?? bracket, true)
            : renderFormula(paren ?? inline, false);
        last = match.index + match[0].length;
    }
    html += escapeHtml(source.slice(last).replace(/\\\$/g, '$'));

    return html;
}

/** Symbols for the editor toolbar, inserted as plain characters. */
export const MCQ_SYMBOLS = [
    '×', '÷', '±', '−', '≤', '≥', '≠', '≈', '∞', '√', 'π', '°', '²', '³', '½', '¼', '¾',
    'α', 'β', 'γ', 'δ', 'θ', 'λ', 'μ', 'σ', 'φ', 'ω', 'Δ', 'Σ', 'Ω',
    '→', '←', '↔', '⇒', '∴', '∵', '∠', '⊥', '∥', '△', '∈', '∉', '⊂', '∪', '∩', '∅', '∑', '∫', '₹',
];

/** Formula snippets for the toolbar; `$` wraps the LaTeX so it renders inline. */
export const MCQ_FORMULAS = [
    { label: 'a/b', title: 'Fraction', tex: '$\\frac{a}{b}$' },
    { label: 'xⁿ', title: 'Power', tex: '$x^{2}$' },
    { label: 'xₙ', title: 'Subscript', tex: '$x_{1}$' },
    { label: '√x', title: 'Square root', tex: '$\\sqrt{x}$' },
    { label: 'ⁿ√x', title: 'nth root', tex: '$\\sqrt[3]{x}$' },
    { label: 'Σ', title: 'Sum', tex: '$\\sum_{i=1}^{n} i$' },
    { label: '∫', title: 'Integral', tex: '$\\int_{a}^{b} f(x)\\,dx$' },
    { label: 'lim', title: 'Limit', tex: '$\\lim_{x \\to 0} f(x)$' },
    { label: 'v⃗', title: 'Vector', tex: '$\\vec{v}$' },
    { label: 'θ°', title: 'Angle in degrees', tex: '$90^{\\circ}$' },
    { label: 'H₂O', title: 'Chemical formula', tex: '$\\mathrm{H_2O}$' },
    { label: '→', title: 'Reaction arrow', tex: '$\\rightarrow$' },
    { label: '[ ]', title: 'Matrix', tex: '$\\begin{bmatrix} a & b \\\\ c & d \\end{bmatrix}$' },
];
