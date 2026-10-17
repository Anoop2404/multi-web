/**
 * Seeded question order (documented in NOTES-client.md, "Question order").
 *
 * PRNG: mulberry32 over a uint32 state initialised with `seed >>> 0`.
 * Shuffle: Fisher–Yates from the last index down: for i = n-1 … 1, j = floor(next() * (i + 1)),
 * swap(i, j). Questions are grouped by `section` (groups keep the order in which they first
 * appear in the bundle), and each group is shuffled in that order with ONE shared PRNG stream.
 */
export function mulberry32(seed: number): () => number {
    let a = seed >>> 0;
    return () => {
        a = (a + 0x6d2b79f5) >>> 0;
        let t = a;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

/** Fisher–Yates in place, driven by `next()` in [0, 1). Returns the same array. */
export function fisherYates<T>(items: T[], next: () => number): T[] {
    for (let i = items.length - 1; i > 0; i--) {
        const j = Math.floor(next() * (i + 1));
        const tmp = items[i];
        items[i] = items[j];
        items[j] = tmp;
    }
    return items;
}

/** A shuffled copy of `items` (whole list, no grouping). */
export function seededShuffle<T>(items: readonly T[], seed: number): T[] {
    return fisherYates(items.slice(), mulberry32(seed));
}

/**
 * Shuffle within groups: group order = first appearance, items shuffled inside each group, one
 * PRNG stream shared across groups (group 1 consumes its draws first, then group 2, …).
 */
export function seededShuffleGrouped<T>(items: readonly T[], seed: number, groupOf: (item: T) => string): T[] {
    const order: string[] = [];
    const groups = new Map<string, T[]>();
    for (const item of items) {
        const key = groupOf(item);
        let list = groups.get(key);
        if (!list) {
            list = [];
            groups.set(key, list);
            order.push(key);
        }
        list.push(item);
    }
    const next = mulberry32(seed);
    const out: T[] = [];
    for (const key of order) out.push(...fisherYates(groups.get(key) as T[], next));
    return out;
}
