/** Shared Event Head / item-head navigation link builders (Sahodaya + school fest events). */

export function headQueryParam(headId, itemId = null) {
    const params = new URLSearchParams();
    if (headId !== null && headId !== undefined && headId !== '') {
        params.set('head_id', headId === 'other' || headId === 0 ? 'other' : String(headId));
    }
    if (itemId) {
        params.set('item_id', String(itemId));
    }
    const q = params.toString();

    return q ? `?${q}` : '';
}

export function sahodayaEventBase(sahodayaId, eventId) {
    return `/sahodaya-admin/${sahodayaId}/events/${eventId}`;
}

export function sahodayaCompetitionBase(sahodayaId, eventId) {
    return `${sahodayaEventBase(sahodayaId, eventId)}/competition`;
}

/** Quick links for a single Event Head / item head inside a Sahodaya event. */
export function sahodayaHeadActionLinks(sahodayaId, eventId, headId, options = {}) {
    const { isSports = false, itemId = null } = options;
    const base = sahodayaEventBase(sahodayaId, eventId);
    const q = headQueryParam(headId, itemId);

    const links = [
        { key: 'competition', label: 'Item listing', href: `${sahodayaCompetitionBase(sahodayaId, eventId)}${headQueryParam(headId)}`, icon: 'layers' },
        { key: 'registrations', label: 'Registrations', href: `${base}/registrations${q}`, icon: 'inbox' },
        { key: 'marks', label: 'Mark entry', href: `${base}/marks${q}`, icon: 'edit' },
    ];

    if (isSports) {
        links.push({ key: 'chest', label: 'Chest numbers', href: `${base}/chest-numbers${q}`, icon: 'hash' });
    }

    links.push(
        { key: 'results', label: 'Results', href: `${base}/results${q}`, icon: 'award' },
        {
            key: 'reports',
            label: 'Reports',
            href: isSports ? `${base}/reports/by-head${headQueryParam(headId)}` : `${base}/reports${headQueryParam(headId)}`,
            icon: 'file-text',
        },
    );

    return links;
}

export function schoolEventBase(schoolId, programPrefix, eventId) {
    return `/school-admin/${schoolId}/${programPrefix}/events/${eventId}`;
}

export function schoolCompetitionBase(schoolId, programPrefix, eventId) {
    return `${schoolEventBase(schoolId, programPrefix, eventId)}/competition`;
}

export function schoolProgramBase(schoolId, programPrefix) {
    return `/school-admin/${schoolId}/${programPrefix}`;
}

/** Quick links for a single Event Head / item head inside a school fest event. */
export function schoolHeadActionLinks(schoolId, programPrefix, eventId, headId, options = {}) {
    const { isSports = false, itemId = null } = options;
    const eventBase = schoolEventBase(schoolId, programPrefix, eventId);
    const reportsBase = `/school-admin/${schoolId}/${programPrefix}/reports/${eventId}`;
    const q = headQueryParam(headId, itemId);

    const links = [
        { key: 'registration', label: 'Register students', href: `${eventBase}/registration${q}`, icon: 'clipboard' },
    ];

    if (isSports) {
        links.push({ key: 'items', label: 'Register by Event Head', href: `${eventBase}/items${q}`, icon: 'layers' });
    }

    links.push(
        { key: 'reports', label: 'Reports', href: `${reportsBase}${q}`, icon: 'file-text' },
    );

    return links;
}

export function parseHeadFromUrl(url) {
    try {
        const params = new URL(url, 'http://local').searchParams;
        const raw = params.get('head_id') ?? params.get('head');
        if (raw === null || raw === '') {
            return null;
        }
        if (raw === 'other') {
            return 'other';
        }

        return Number(raw);
    } catch {
        return null;
    }
}

export function parseItemFromUrl(url) {
    try {
        const raw = new URL(url, 'http://local').searchParams.get('item_id');
        if (! raw) {
            return null;
        }

        return Number(raw);
    } catch {
        return null;
    }
}
