import { describe, expect, it } from 'vitest';
import { hasFormula, renderRichText, tokenize } from '../src/lib/richText';

describe('rich text tokenizer (same $…$ / $$…$$ convention as Laravel)', () => {
    it('splits inline and display formulas', () => {
        expect(tokenize('Area $\\pi r^2$ and $$E=mc^2$$.')).toEqual([
            { kind: 'text', value: 'Area ' },
            { kind: 'math', value: '\\pi r^2', display: false },
            { kind: 'text', value: ' and ' },
            { kind: 'math', value: 'E=mc^2', display: true },
            { kind: 'text', value: '.' },
        ]);
    });

    it('supports \\( \\) and \\[ \\], and \\$ as a literal dollar', () => {
        expect(tokenize('\\(x\\) \\[y\\]').filter((t) => t.kind === 'math')).toEqual([
            { kind: 'math', value: 'x', display: false },
            { kind: 'math', value: 'y', display: true },
        ]);
        expect(tokenize('Costs \\$5 or \\$6')).toEqual([{ kind: 'text', value: 'Costs $5 or $6' }]);
    });

    it('leaves unmatched dollars and line-broken pairs as text', () => {
        expect(hasFormula('Only $5 here')).toBe(false);
        expect(hasFormula('a $b\nc$ d')).toBe(false);
        expect(hasFormula('$x$')).toBe(true);
    });

    it('escapes HTML outside formulas (KaTeX not loaded → raw TeX, escaped)', () => {
        const html = renderRichText('<img src=x onerror=alert(1)> $a<b$');
        expect(html).not.toContain('<img');
        expect(html).toContain('&lt;img');
        expect(html).toContain('<span class="tex-raw">a&lt;b</span>');
    });

    it('keeps Malayalam text intact', () => {
        expect(renderRichText('ശരിയായ ഉത്തരം')).toBe('ശരിയായ ഉത്തരം');
    });
});
