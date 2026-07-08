/** Fest mark coordinator portal navigation. */
export function festCoordinatorPortalNavItems(sahodayaId, eventId = null) {
    const base = `/portal/fest-coordinator/${sahodayaId}`;
    const items = [{ href: base, label: 'Dashboard', exact: !eventId }];

    if (eventId) {
        items.push({ href: `${base}/events/${eventId}/marks`, label: 'Mark entry' });
    }

    return items;
}
