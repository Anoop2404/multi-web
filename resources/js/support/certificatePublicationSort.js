/** Sort without changing server props; missing publication times always come last. */
export function sortByPublication(rows, order, getDate = row => row.results_published_at, getCode = row => row.item_code) {
    if (order === 'default') return rows;
    return [...rows].sort((a, b) => {
        if (order === 'code_asc' || order === 'code_desc') {
            const aCode = String(getCode(a) ?? '').trim();
            const bCode = String(getCode(b) ?? '').trim();
            if (!aCode || !bCode) return aCode === bCode ? 0 : aCode ? -1 : 1;
            const comparison = aCode.localeCompare(bCode, undefined, { numeric: true, sensitivity: 'base' });
            return order === 'code_asc' ? comparison : -comparison;
        }
        const aTime = Date.parse(getDate(a));
        const bTime = Date.parse(getDate(b));
        const aMissing = !Number.isFinite(aTime);
        const bMissing = !Number.isFinite(bTime);
        if (aMissing !== bMissing) return aMissing ? 1 : -1;
        if (aMissing) return 0;
        return order === 'published_asc' ? aTime - bTime : bTime - aTime;
    });
}
