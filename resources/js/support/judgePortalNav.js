/** Judge portal navigation. */
export function judgePortalNavItems(sahodayaId, eventId = null) {
    const base = `/portal/judge/${sahodayaId}`;
    const items = [{ href: base, label: 'Dashboard', exact: !eventId }];

    if (eventId) {
        items.push({ href: `${base}/events/${eventId}/marks`, label: 'Mark entry' });
    }

    return items;
}
