/** Sort without changing server props; missing publication times always come last. */
export function sortByPublication(rows, order, getDate = row => row.results_published_at) {
    if (order === 'default') return rows;
    return [...rows].sort((a, b) => {
        const aTime = Date.parse(getDate(a));
        const bTime = Date.parse(getDate(b));
        const aMissing = !Number.isFinite(aTime);
        const bMissing = !Number.isFinite(bTime);
        if (aMissing !== bMissing) return aMissing ? 1 : -1;
        if (aMissing) return 0;
        return order === 'published_asc' ? aTime - bTime : bTime - aTime;
    });
}
