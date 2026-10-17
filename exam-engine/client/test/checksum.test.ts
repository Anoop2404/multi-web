import { describe, expect, it } from 'vitest';
import { answersChecksum, canonicalAnswerString } from '../src/lib/checksum';

// Fixed vectors, also listed in NOTES-client.md so the engine's Go tests can match them.
// Expected hashes were computed independently with `printf '<canonical>' | shasum -a 256`.
const VECTORS: { name: string; answers: Record<string, string | string[]>; canonical: string; sha256: string }[] = [
    {
        name: 'empty map',
        answers: {},
        canonical: '',
        sha256: 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
    },
    {
        name: 'task example (numeric id order, multiple sorted, numeric kept as typed)',
        answers: { '12': 'b', '3': ['c', 'a'], '7': '12.50' },
        canonical: '3=a,c\n7=12.50\n12=b\n',
        sha256: 'e566b5b54dbb2a2711b5e521a96eabcc23032c325733469f3692a0f98c675165',
    },
    {
        name: 'trimmed numeric; empty array and empty string are unanswered',
        answers: { '5': ' -0.5 ', '9': [], '10': '' },
        canonical: '5=-0.5\n',
        sha256: '7dfb85e97c639f8fda0c7e1582a4920edeae9efcbb0210b8649bca499b208a96',
    },
    {
        name: 'contract example ids',
        answers: { '5503': '-3', '5501': ['a', 'c'], '5502': 'b' },
        canonical: '5501=a,c\n5502=b\n5503=-3\n',
        sha256: '2189f85b8ae46c4aea448e7b2be4d679bbb1d1c167875fde96945b84def002ed',
    },
    {
        name: 'numeric (not lexical) id order: 2 < 10 < 100',
        answers: { '100': ['d', 'b', 'a'], '10': 'b', '2': 'a' },
        canonical: '2=a\n10=b\n100=a,b,d\n',
        sha256: 'ec420cb2df0f9314107a63eae4e173c7b18c266775f35ebd14a931bb9ccc8574',
    },
];

describe('answer checksum (CONTRACT §1)', () => {
    for (const v of VECTORS) {
        it(v.name, async () => {
            expect(canonicalAnswerString(v.answers)).toBe(v.canonical);
            expect(await answersChecksum(v.answers)).toBe(v.sha256);
        });
    }

    it('does not depend on key insertion order', async () => {
        const a = await answersChecksum({ '1': 'a', '2': ['b', 'c'] });
        const b = await answersChecksum({ '2': ['c', 'b'], '1': 'a' });
        expect(a).toBe(b);
    });

    it('is lowercase hex, 64 chars', async () => {
        expect(await answersChecksum({ '1': 'a' })).toMatch(/^[0-9a-f]{64}$/);
    });
});
