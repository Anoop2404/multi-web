/**
 * Sahodaya fest program definitions (Kalotsav, Sports Meet, Kids Fest, Teacher Fest).
 * Each program has its own URL prefix, page, and sidebar.
 */

import { FEST_FINANCE, FEST_MANAGE, FEST_VIEW } from './sahodayaEventNavPermissions.js';

export const SAHODAYA_PROGRAMS = {
    kalotsav: {
        slug: 'kalotsav',
        prefix: 'kalotsav',
        eventType: 'kalolsavam',
        label: 'Kalotsav',
        icon: 'star',
        description: 'Manage Kalotsav rounds, items, registrations, marks, and results for your cluster.',
    },
    'sports-meet': {
        slug: 'sports-meet',
        prefix: 'sports',
        eventType: 'sports',
        label: 'Sports Meet',
        icon: 'award',
        description: 'School and Sahodaya sports meets — track events, marks, athletic records, and championships.',
    },
    'kids-fest': {
        slug: 'kids-fest',
        prefix: 'kids-fest',
        eventType: 'kids_fest',
        label: 'Kids Fest',
        icon: 'users',
        description: 'Kids Fest programs by class band — registrations, scheduling, and results.',
    },
    'teacher-fest': {
        slug: 'teacher-fest',
        prefix: 'teacher-fest',
        eventType: 'teacher_fest',
        label: 'Teacher Fest',
        icon: 'users',
        description: 'Teacher fest programs — registrations, scheduling, marks, and results.',
    },
    custom: {
        slug: 'custom',
        prefix: 'programs/custom',
        eventType: 'custom',
        label: 'Custom Events',
        icon: 'layers',
        description: 'One-off and custom fest programs.',
    },
};

export const PROGRAM_SLUGS = Object.keys(SAHODAYA_PROGRAMS);

const EVENT_TYPE_TO_SLUG = Object.fromEntries(
    Object.values(SAHODAYA_PROGRAMS).map((p) => [p.eventType, p.slug]),
);

export function programBySlug(slug) {
    return SAHODAYA_PROGRAMS[slug] ?? null;
}

export function programForEventType(eventType) {
    const slug = EVENT_TYPE_TO_SLUG[eventType];
    return slug ? SAHODAYA_PROGRAMS[slug] : null;
}

/** Build program hub URL using dedicated prefix routes. */
export function sahodayaProgramHref(sahodayaId, programSlug, ...segments) {
    const program = programBySlug(programSlug);
    const base = `/sahodaya-admin/${sahodayaId}`;
    if (!program) {
        return base;
    }

    const tail = segments
        .flat()
        .filter((s) => s != null && String(s).trim() !== '')
        .map((s) => String(s).replace(/^\/+|\/+$/g, ''))
        .join('/');

    return program.prefix.startsWith('programs/')
        ? `${base}/${program.prefix}${tail ? `/${tail}` : ''}`
        : `${base}/${program.prefix}${tail ? `/${tail}` : ''}`;
}

/** Links to leave fest/event pages (dashboard, program hub, event directory). */
export function festMainMenuNavItems(sahodayaId, program = null) {
    const base = `/sahodaya-admin/${sahodayaId}`;
    const items = [
        { label: 'Sahodaya home', href: base, icon: 'grid', exact: true, permissions: FEST_VIEW },
    ];

    if (program?.slug) {
        items.push({
            label: `${program.label} hub`,
            href: sahodayaProgramHref(sahodayaId, program.slug),
            icon: program.icon ?? 'layers',
            permissions: FEST_VIEW,
        });
    }

    items.push({
        label: 'All events',
        href: `${base}/events`,
        icon: 'calendar',
        exact: true,
        permissions: FEST_VIEW,
    });

    return items;
}

/** Per-event sidebar groups on program hub (Manage · Fees · Reports). */
export function programEventSidebarGroups(sahodayaId, events, options = {}) {
    const { maxEvents = 8, overflowHref = null } = options;
    const base = `/sahodaya-admin/${sahodayaId}`;
    const visible = (events ?? []).slice(0, maxEvents);

    const groups = visible.map((ev) => ({
        section: ev.title,
        items: [
            { label: 'Manage', href: `${base}/events/${ev.id}`, icon: 'star', exact: true, permissions: FEST_VIEW },
            { label: 'Venue & schedule', href: `${base}/events/${ev.id}/schedule/items`, icon: 'calendar', permissions: FEST_VIEW },
            { label: 'Fees', href: `${base}/events/${ev.id}/fees`, icon: 'credit-card', permissions: FEST_FINANCE },
            { label: 'Reports', href: `${base}/events/${ev.id}/reports`, icon: 'bar-chart', permissions: FEST_VIEW },
        ],
    }));

    if ((events?.length ?? 0) > maxEvents && overflowHref) {
        groups.push({
            section: 'More events',
            items: [
                {
                    label: `All ${events.length} events on overview`,
                    href: overflowHref,
                    icon: 'layers',
                    exact: true,
                    permissions: FEST_VIEW,
                },
            ],
        });
    }

    return groups;
}

/** Sidebar when viewing a program hub (Kalotsav, Sports, Kids Fest). */
export function programScopedNav(sahodayaId, programSlug, events = []) {
    const program = programBySlug(programSlug);
    const base = `/sahodaya-admin/${sahodayaId}`;

    if (!program) {
        return [];
    }

    const programBase = sahodayaProgramHref(sahodayaId, programSlug);

    const setupItems = [
        { label: 'Overview', href: programBase, icon: 'grid', exact: true, permissions: FEST_VIEW },
        { label: 'Item catalog', href: sahodayaProgramHref(sahodayaId, programSlug, 'catalog'), icon: 'file-text', permissions: FEST_MANAGE },
    ];

    if (programSlug === 'sports-meet') {
        setupItems.push(
            { label: 'Age groups', href: sahodayaProgramHref(sahodayaId, programSlug, 'age-groups'), icon: 'users', permissions: FEST_MANAGE },
        );
    }

    if (programSlug === 'kalotsav') {
        setupItems.push(
            { label: 'School rounds', href: sahodayaProgramHref(sahodayaId, programSlug, 'school-rounds'), icon: 'layers', permissions: FEST_VIEW },
        );
    }

    const groups = [
        {
            section: 'Main menu',
            items: festMainMenuNavItems(sahodayaId, program),
        },
        {
            section: `${program.label} — setup`,
            items: setupItems,
        },
    ];

    if (programSlug === 'sports-meet') {
        groups.push({
            section: 'Program records',
            items: [
                { label: 'Athletic records', href: sahodayaProgramHref(sahodayaId, programSlug, 'records'), icon: 'award', permissions: FEST_VIEW },
                { label: 'House championship', href: sahodayaProgramHref(sahodayaId, programSlug, 'championship'), icon: 'bar-chart', permissions: FEST_VIEW },
            ],
        });
    }

    if (events.length) {
        groups.push(...programEventSidebarGroups(sahodayaId, events, {
            maxEvents: 8,
            overflowHref: programBase,
        }));
    }

    return groups;
}
