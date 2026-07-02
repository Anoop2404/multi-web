/**
 * Filter sidebar nav groups by menu label or section name.
 *
 * @param {Array<{ section: string, items: Array<{ label: string }> }>} groups
 * @param {string} query
 */
export function filterNavGroups(groups, query) {
    const q = query.trim().toLowerCase();
    if (!q) {
        return groups;
    }

    return groups
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => {
                const label = item.label?.toLowerCase() ?? '';
                const section = group.section?.toLowerCase() ?? '';
                return label.includes(q) || section.includes(q);
            }),
        }))
        .filter((group) => group.items.length > 0);
}
