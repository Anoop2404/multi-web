import { describe, expect, it } from 'vitest';
import { mulberry32, seededShuffle, seededShuffleGrouped } from '../src/lib/shuffle';
import { orderQuestions } from '../src/lib/paper';
import type { Paper } from '../src/lib/types';

/** Reference mulberry32 exactly as commonly published (bryc), to pin our port to it. */
function referenceMulberry32(a: number) {
    return function () {
        let t = (a += 0x6d2b79f5);
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

describe('mulberry32', () => {
    it('matches the reference implementation', () => {
        for (const seed of [0, 1, 101, 123456789, 4294967295]) {
            const ours = mulberry32(seed);
            const ref = referenceMulberry32(seed);
            for (let i = 0; i < 1000; i++) expect(ours()).toBe(ref());
        }
    });

    it('fixed vector: seed 101 → first uint32 outputs', () => {
        const next = mulberry32(101);
        const u32 = Array.from({ length: 5 }, () => Math.round(next() * 4294967296));
        // Documented in NOTES-client.md for cross-language checks.
        expect(u32).toEqual(SEED_101_U32);
    });

    it('stays in [0, 1)', () => {
        const next = mulberry32(42);
        for (let i = 0; i < 10000; i++) {
            const v = next();
            expect(v).toBeGreaterThanOrEqual(0);
            expect(v).toBeLessThan(1);
        }
    });
});

const SEED_101_U32 = [582603121, 3284799363, 2321895009, 2761734473, 1649615189];

describe('seeded shuffle', () => {
    const ids = Array.from({ length: 10 }, (_, i) => i + 1);

    it('is deterministic for a seed and does not mutate the input', () => {
        const copy = ids.slice();
        expect(seededShuffle(ids, 101)).toEqual(seededShuffle(ids, 101));
        expect(ids).toEqual(copy);
    });

    it('fixed vector: [1..10] with seed 101', () => {
        expect(seededShuffle(ids, 101)).toEqual(SEED_101_ORDER);
    });

    it('is a permutation and differs between seeds', () => {
        const a = seededShuffle(ids, 7);
        const b = seededShuffle(ids, 8);
        expect(a.slice().sort((x, y) => x - y)).toEqual(ids);
        expect(a).not.toEqual(b);
    });

    it('treats the seed as uint32 (negative / > 2^32 wrap)', () => {
        expect(seededShuffle(ids, -1)).toEqual(seededShuffle(ids, 4294967295));
    });

    it('groups by section in first-appearance order with one PRNG stream', () => {
        const items = [
            { id: 1, s: 'A' },
            { id: 2, s: 'B' },
            { id: 3, s: 'A' },
            { id: 4, s: 'B' },
            { id: 5, s: 'A' },
        ];
        const out = seededShuffleGrouped(items, 101, (x) => x.s);
        expect(out.slice(0, 3).map((x) => x.s)).toEqual(['A', 'A', 'A']);
        expect(out.slice(3).map((x) => x.s)).toEqual(['B', 'B']);
        // Manual: shuffle A=[1,3,5] then B=[2,4] with the same generator.
        const next = mulberry32(101);
        const a = [1, 3, 5];
        for (let i = a.length - 1; i > 0; i--) {
            const j = Math.floor(next() * (i + 1));
            [a[i], a[j]] = [a[j], a[i]];
        }
        const b = [2, 4];
        for (let i = b.length - 1; i > 0; i--) {
            const j = Math.floor(next() * (i + 1));
            [b[i], b[j]] = [b[j], b[i]];
        }
        expect(out.map((x) => x.id)).toEqual(a.concat(b));
    });

    it('orderQuestions numbers questions and marks section starts; no shuffle keeps order', () => {
        const paper: Paper = {
            version: 1,
            questions: [1, 2, 3, 4].map((id) => ({
                id,
                type: 'single' as const,
                section: id <= 2 ? 'Section A' : 'Section B',
                body: `Q${id}`,
                image: null,
                options: [],
                show_count: null,
                marks: 1,
                negative_mark: 0,
            })),
        };
        const plain = orderQuestions(paper, false, 101);
        expect(plain.map((q) => q.id)).toEqual([1, 2, 3, 4]);
        expect(plain.map((q) => q.number)).toEqual([1, 2, 3, 4]);
        expect(plain.map((q) => q.sectionStart)).toEqual([true, false, true, false]);
        const shuffled = orderQuestions(paper, true, 101);
        expect(shuffled.slice(0, 2).every((q) => q.section === 'Section A')).toBe(true);
        expect(orderQuestions(paper, true, 101).map((q) => q.id)).toEqual(shuffled.map((q) => q.id));
    });
});

const SEED_101_ORDER = [10, 1, 9, 6, 4, 3, 8, 5, 7, 2];
