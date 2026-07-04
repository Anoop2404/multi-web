/**
 * Events module sidebar navigation (Sahodaya admin).
 * Grouped by workflow phase to reduce clutter on event pages.
 */

import { capabilitiesForEvent } from './sahodayaEventCapabilities.js';
import {
    FEST_CATERING,
    FEST_CERTIFICATES,
    FEST_FINANCE,
    FEST_MANAGE,
    FEST_MARKS,
    FEST_REGISTRATIONS,
    FEST_RESULTS,
    FEST_SCHEDULE,
    FEST_SETTINGS,
    FEST_VIEW,
} from './sahodayaEventNavPermissions.js';
import { PROGRAM_SLUGS, SAHODAYA_PROGRAMS, programForEventType, sahodayaProgramHref } from './sahodayaPrograms.js';

function eventQuery(eventId) {
    return eventId ? `?event_id=${eventId}` : '';
}

export function eventsModuleNav(sahodayaId) {
    const base = `/sahodaya-admin/${sahodayaId}`;

    return [
        {
            section: 'Fest programs',
            items: PROGRAM_SLUGS.map((slug) => {
                const p = SAHODAYA_PROGRAMS[slug];
                return { label: p.label, href: sahodayaProgramHref(sahodayaId, slug), icon: p.icon, permissions: FEST_VIEW };
            }),
        },
        {
            section: 'Directory',
            items: [
                { label: 'All events', href: `${base}/events`, icon: 'layers', exact: true, permissions: FEST_VIEW },
            ],
        },
    ];
}

export function eventScopedNav(sahodayaId, eventId, event = null, programEvents = []) {
    const base = `/sahodaya-admin/${sahodayaId}/events/${eventId}`;
    const tenantBase = `/sahodaya-admin/${sahodayaId}`;
    const eq = eventQuery(eventId);
    const caps = capabilitiesForEvent(event);
    const program = event?.event_type ? programForEventType(event.event_type) : null;

    const groups = [];

    // Event setup — overview/items/levels live in EventSubNav tab bar
    groups.push({
        section: 'Event setup',
        items: [
            { label: 'Settings', href: `${base}/settings`, icon: 'settings', permissions: FEST_SETTINGS },
            { label: 'Activity log', href: `${base}/activity`, icon: 'clock', permissions: FEST_VIEW },
        ],
    });

    groups.push({
        section: 'Participants',
        items: [
            { label: 'Registrations', href: `${base}/registrations`, icon: 'inbox', permissions: FEST_REGISTRATIONS },
            { label: 'Attendance', href: `${base}/attendance`, icon: 'check-square', permissions: FEST_REGISTRATIONS },
            { label: 'Stage schedule', href: `${base}/schedule`, icon: 'calendar', permissions: FEST_SCHEDULE },
            { label: 'Item scheduling', href: `${base}/schedule/items`, icon: 'map-pin', permissions: FEST_SCHEDULE },
        ],
    });

    const competitionItems = [
        { label: 'Mark entry', href: `${base}/marks`, icon: 'edit', permissions: FEST_MARKS },
        { label: 'Results & publish', href: `${base}/results`, icon: 'award', permissions: FEST_RESULTS },
        { label: 'Leaderboard', href: `${base}/leaderboard`, icon: 'bar-chart', permissions: FEST_RESULTS },
    ];

    if (caps.championship) {
        competitionItems.push({ label: 'Championship', href: `${base}/championship`, icon: 'star', permissions: FEST_RESULTS });
    }

    if (caps.isSports) {
        competitionItems.push({ label: 'Chest numbers', href: `${base}/chest-numbers`, icon: 'hash', permissions: FEST_MANAGE });
    }

    groups.push({ section: 'Competition', items: competitionItems });

    const outputItems = [
        { label: 'Reports', href: `${base}/reports`, icon: 'file-text', permissions: FEST_VIEW },
        { label: 'Certificates', href: `${base}/certificates`, icon: 'award', permissions: FEST_CERTIFICATES },
        { label: 'ID cards', href: `${base}/id-cards`, icon: 'credit-card', permissions: FEST_VIEW },
    ];

    if (caps.hasEventFees) {
        outputItems.unshift(
            { label: 'Registration fees', href: `${base}/fees`, icon: 'credit-card', permissions: FEST_FINANCE },
            { label: 'Payment ledger', href: `${base}/fees/ledger`, icon: 'layers', permissions: FEST_FINANCE },
        );
    }

    groups.push({ section: 'Reports & finance', items: outputItems });

    const adminItems = [
        { label: 'Judges & staff', href: `${base}/judges`, icon: 'user-check', permissions: FEST_MANAGE },
        { label: 'Appeals', href: `${base}/appeals`, icon: 'inbox', permissions: FEST_MANAGE },
        { label: 'Event staff', href: `${base}/event-staff`, icon: 'users', permissions: FEST_MANAGE },
        { label: 'School invoices', href: `${base}/finance`, icon: 'file-text', permissions: FEST_FINANCE },
    ];

    if (caps.athleticRecords) {
        adminItems.unshift({ label: 'Athletic records', href: `${base}/athletic-records`, icon: 'star', permissions: FEST_MANAGE });
    }
    if (caps.houses) {
        adminItems.push({ label: 'Houses', href: `${base}/houses`, icon: 'building', permissions: FEST_MANAGE });
    }
    if (caps.catering) {
        adminItems.push({ label: 'Catering', href: `${base}/catering`, icon: 'clipboard', permissions: FEST_CATERING });
    }
    if (caps.foodCoupons) {
        adminItems.push({ label: 'Food coupons', href: `${base}/food-coupons`, icon: 'hash', permissions: FEST_CATERING });
    }

    groups.push({ section: 'Administration', items: adminItems });

    if (programEvents.length) {
        const visible = programEvents.slice(0, 3);
        const items = visible.map((ev) => ({
            label: ev.title,
            href: `${tenantBase}/events/${ev.id}`,
            icon: Number(ev.id) === Number(eventId) ? 'star' : 'layers',
            permissions: FEST_VIEW,
        }));
        if (program?.slug && programEvents.length > 3) {
            items.push({
                label: `All ${programEvents.length} events…`,
                href: `${sahodayaProgramHref(sahodayaId, program.slug)}${eq}`,
                icon: 'grid',
                permissions: FEST_VIEW,
            });
        }
        groups.push({ section: 'Switch event', items });
    }

    return groups;
}

/** Resolve active state for a nav href against current page URL. */
export function navItemActive(pageUrl, href, exact = false) {
    const path = pageUrl.split('?')[0];
    const target = href.split('?')[0];

    if (exact) {
        return path === target || path === `${target}/`;
    }

    if (href.includes('?')) {
        return pageUrl.startsWith(target) && pageUrl.includes(href.split('?')[1]);
    }

    return path === target || path.startsWith(`${target}/`);
}

export { capabilitiesForEvent, settingsTabsForEvent, settingsDescriptionForEvent } from './sahodayaEventCapabilities.js';
