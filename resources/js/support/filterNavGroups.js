/**
 * Filter sidebar nav groups by menu label or section name.
 *
 * @param {Array<{ section: string, items: Array<{ label: string }> }>} groups
 * @param {string} query
 */
export function filterNavGroups(groups, query = '') {
    const q = (query ?? '').trim().toLowerCase();

    return groups
        .map((group) => ({
            ...group,
            // Without search: show only non-hidden items
            // With search: show any item whose label matches, including hidden
            items: group.items.filter((item) =>
                q
                    ? item.label.toLowerCase().includes(q)
                    : !item.hidden,
            ),
        }))
        .filter((group) => group.items.length > 0);
}
