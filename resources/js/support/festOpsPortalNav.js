/** Fest operations portal navigation (coordinator desk, gate check, mark entry, …). */

export function festOpsDashboardNav(sahodayaId) {
    const base = `/portal/fest-ops/${sahodayaId}`;

    return [
        { href: base, label: 'Dashboard', exact: true },
        { href: `${base}/gate-check`, label: 'Gate check' },
    ];
}

const dutyNav = {
    coordinator: { path: 'coordinator', label: 'Event desk' },
    registration: { path: 'registrations', label: 'Registrations' },
    stage: { path: 'stage', label: 'Stage' },
    attendance: { path: 'attendance', label: 'Attendance' },
    food: { path: 'kitchen', label: 'Kitchen' },
    appeals: { path: 'appeals', label: 'Appeals' },
    certificates: { path: 'certificates', label: 'Certificates' },
    marks: { path: 'marks', label: 'Mark entry' },
    discipline: { path: 'participants/search', label: 'Discipline desk' },
    admit_cards: { path: 'participants/search', label: 'Admit cards' },
};

/** Event-scoped fest-ops nav — pass assigned `duties` to limit links. */
export function festOpsEventNav(sahodayaId, eventId, duties = null) {
    const opsBase = `/portal/fest-ops/${sahodayaId}`;
    const eventBase = `${opsBase}/events/${eventId}`;

    const items = [
        ...festOpsDashboardNav(sahodayaId),
        { href: eventBase, label: 'Event' },
    ];

    const dutyKeys = duties?.length ? duties : Object.keys(dutyNav);
    const seenHrefs = new Set(items.map((item) => item.href));
    for (const key of dutyKeys) {
        const meta = dutyNav[key];
        if (meta) {
            const href = `${eventBase}/${meta.path}`;
            if (!seenHrefs.has(href)) {
                items.push({ href, label: meta.label });
                seenHrefs.add(href);
            }
        }
    }

    const searchHref = `${eventBase}/participants/search`;
    if (!seenHrefs.has(searchHref)) {
        items.push({ href: searchHref, label: 'Search & admit cards' });
    }

    return items;
}

/** Mark-entry portal base path (fest-coordinator vs fest-ops). */
export function festMarkPortalPaths(sahodayaId, eventId, festOpsBase = null) {
    if (festOpsBase) {
        const eventBase = festOpsBase.replace(/\/$/, '');

        return {
            dashboardHref: `/portal/fest-ops/${sahodayaId}`,
            marksHref: `${eventBase}/marks`,
            marksPostUrl: `${eventBase}/marks`,
            attendancePostUrl: `${eventBase}/attendance`,
            autoRankUrl: (itemId) => `${eventBase}/items/${itemId}/auto-rank`,
        };
    }

    const coordinatorBase = `/portal/fest-coordinator/${sahodayaId}`;
    const eventBase = `${coordinatorBase}/events/${eventId}`;

    return {
        dashboardHref: coordinatorBase,
        marksHref: `${eventBase}/marks`,
        marksPostUrl: `${eventBase}/marks`,
        attendancePostUrl: `${eventBase}/attendance`,
        autoRankUrl: (itemId) => `${eventBase}/items/${itemId}/auto-rank`,
    };
}
