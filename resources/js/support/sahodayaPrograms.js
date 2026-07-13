/**
 * Sahodaya fest program definitions (Kalotsav, Sports Meet, Kids Fest, Teacher Fest).
 * System programs are listed here; custom Competition Types are merged from Inertia
 * `competitionPrograms` (FRD-08 Phase 1).
 */

import { FEST_FINANCE, FEST_MANAGE, FEST_VIEW } from './sahodayaEventNavPermissions.js';
import { isNavProgramVisible } from './sahodayaAdminNav.js';
import { usePage } from '@inertiajs/vue3';

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
    'english-fest': {
        slug: 'english-fest',
        prefix: 'english-fest',
        eventType: 'english_fest',
        label: 'English Fest',
        icon: 'file-text',
        description: 'English fest items, registrations, and results.',
    },
    'science-fest': {
        slug: 'science-fest',
        prefix: 'science-fest',
        eventType: 'science_fest',
        label: 'Science Fest',
        icon: 'layers',
        description: 'Science fest items, registrations, and results.',
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

/** Merge DB-backed competition programs from Inertia shared props. */
export function resolvedPrograms() {
    let dynamic = {};
    try {
        dynamic = usePage().props.competitionPrograms || {};
    } catch {
        dynamic = {};
    }

    const merged = { ...SAHODAYA_PROGRAMS };
    Object.values(dynamic).forEach((p) => {
        if (!p?.slug) return;
        merged[p.slug] = {
            slug: p.slug,
            prefix: p.prefix || `programs/${p.slug}`,
            eventType: p.eventType || p.event_type,
            label: p.label,
            icon: p.icon || 'calendar',
            description: p.description || '',
            is_system: p.is_system,
            is_singleton: p.is_singleton,
        };
    });

    return merged;
}

export const PROGRAM_SLUGS = Object.keys(SAHODAYA_PROGRAMS);

export function programBySlug(slug) {
    if (!slug) {
        return null;
    }

    const programs = resolvedPrograms();
    if (programs[slug]) {
        return programs[slug];
    }

    return Object.values(programs).find((p) => p.prefix === slug) ?? null;
}

/** Resolve canonical program slug from slug, prefix, or program object. */
export function resolveCatalogProgramSlug(programOrSlug) {
    if (typeof programOrSlug === 'string') {
        return programBySlug(programOrSlug)?.slug ?? programOrSlug;
    }

    if (programOrSlug?.slug) {
        return programBySlug(programOrSlug.slug)?.slug ?? programOrSlug.slug;
    }

    if (programOrSlug?.prefix) {
        return programBySlug(programOrSlug.prefix)?.slug ?? null;
    }

    if (programOrSlug?.eventType) {
        return programForEventType(programOrSlug.eventType)?.slug ?? null;
    }

    return null;
}

export function programForEventType(eventType) {
    const programs = resolvedPrograms();
    return Object.values(programs).find((p) => p.eventType === eventType) ?? null;
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

/** Catalog hub and mutation URLs (`/sports/catalog/…`, not `/programs/sports-meet/catalog/…`). */
export function sahodayaCatalogHref(sahodayaId, programSlug, ...segments) {
    const slug = resolveCatalogProgramSlug(programSlug);

    return sahodayaProgramHref(sahodayaId, slug, 'catalog', ...segments);
}

/** Section browse URLs for master/list tabs (`…/catalog/master/track`, etc.). */
export function sahodayaCatalogSectionHref(sahodayaId, programSlug, mode, sectionSlug = null) {
    const slug = resolveCatalogProgramSlug(programSlug);
    const base = sahodayaCatalogHref(sahodayaId, slug, mode);
    if (!sectionSlug || sectionSlug === 'all') {
        return base;
    }

    return `${base}/${sectionSlug}`;
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
    const visible = (events ?? []).filter((ev) => !ev.nav_hidden).slice(0, maxEvents);

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
export function programScopedNav(sahodayaId, programSlug, events = [], options = {}) {
    const { navVisibility = null } = options;

    if (!isNavProgramVisible(navVisibility, programSlug)) {
        return eventsModuleNav(sahodayaId, options);
    }

    const program = programBySlug(programSlug);
    const base = `/sahodaya-admin/${sahodayaId}`;

    if (!program) {
        return [];
    }

    const programBase = sahodayaProgramHref(sahodayaId, programSlug);

    const setupItems = [
        { label: 'Overview', href: programBase, icon: 'grid', exact: true, permissions: FEST_VIEW },
        { label: 'Item catalog', href: sahodayaProgramHref(sahodayaId, programSlug, 'catalog'), icon: 'file-text', permissions: FEST_MANAGE },
        { label: 'Competition types', href: `${base}/competition-types`, icon: 'layers', permissions: FEST_MANAGE },
        { label: 'Category masters', href: `${base}/taxonomy-masters?program=${programSlug}`, icon: 'settings', permissions: FEST_MANAGE },
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
                { label: 'Cluster results', href: sahodayaProgramHref(sahodayaId, programSlug, 'results'), icon: 'bar-chart', permissions: FEST_VIEW },
                { label: 'School rankings', href: sahodayaProgramHref(sahodayaId, programSlug, 'rankings'), icon: 'star', permissions: FEST_VIEW },
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
