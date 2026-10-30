export const WINDOW_DATE_FIELDS = ['reg_start', 'reg_end', 'competition_start', 'competition_end'];

// A row save refreshes all server props. Keep unrelated edits while accepting new
// server values for fields the user has not edited.
export function mergeWindowRows(current, previousServer, incoming) {
    const currentById = new Map(current.map(row => [row.id, row]));
    const previousById = new Map(previousServer.map(row => [row.id, row]));
    return incoming.map(row => {
        const edited = currentById.get(row.id);
        const previous = previousById.get(row.id);
        if (!edited || !previous) return row;
        const merged = { ...row };
        for (const field of WINDOW_DATE_FIELDS) {
            if (edited[field] !== previous[field]) merged[field] = edited[field];
        }
        return merged;
    });
}
