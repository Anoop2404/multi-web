/**
 * Events module sidebar navigation (Sahodaya admin).
 * Sports events use a dedicated compact sidebar — see sportsEventNav.js.
 */

import { capabilitiesForEvent } from './sahodayaEventCapabilities.js';
import { sportsEventSidebarNav } from './sportsEventNav.js';
export { sportsEventSidebarNav, shouldShowSportsHeadSidebar, SPORTS_HEAD_SIDEBAR_PATHS, shouldShowSchoolSportsHeadSidebar, SCHOOL_SPORTS_HEAD_SIDEBAR_PATHS } from './sportsEventNav.js';
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
import { isNavProgramVisible } from './sahodayaAdminNav.js';

function eventQuery(eventId) {
    return eventId ? `?event_id=${eventId}` : '';
}

export function eventsModuleNav(sahodayaId, options = {}) {
    const { navVisibility = null } = options;
    const base = `/sahodaya-admin/${sahodayaId}`;

    const programItems = PROGRAM_SLUGS
        .filter((slug) => isNavProgramVisible(navVisibility, slug))
        .map((slug) => {
            const p = SAHODAYA_PROGRAMS[slug];
            return { label: p.label, href: sahodayaProgramHref(sahodayaId, slug), icon: p.icon, permissions: FEST_VIEW };
        });

    return [
        ...(programItems.length ? [{
            section: 'Fest programs',
            items: programItems,
        }] : []),
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
    const isSports = event?.event_type === 'sports';

    if (isSports) {
        const groups = sportsEventSidebarNav(base, caps);

        if (programEvents.length) {
            const visible = programEvents.filter((ev) => String(ev.id) !== String(eventId)).slice(0, 4);
            if (visible.length) {
                const items = visible.map((ev) => ({
                    label: ev.title,
                    href: `${tenantBase}/events/${ev.id}/setup`,
                    icon: 'layers',
                    permissions: FEST_VIEW,
                }));
                if (program?.slug && programEvents.length > 5) {
                    items.push({
                        label: `All ${programEvents.length} events…`,
                        href: `${sahodayaProgramHref(sahodayaId, program.slug)}${eq}`,
                        icon: 'grid',
                        permissions: FEST_VIEW,
                    });
                }
                groups.push({ section: 'Switch event', items });
            }
        }

        return groups;
    }

    const groups = [];

    groups.push({
        section: 'Event home',
        items: [
            { label: 'Overview', href: base, icon: 'grid', exact: true, permissions: FEST_VIEW },
            { label: 'Settings', href: `${base}/settings`, icon: 'settings', permissions: FEST_SETTINGS },
            { label: 'Items & catalog', href: `${base}/items`, icon: 'file-text', permissions: FEST_MANAGE },
            { label: 'Competition areas', href: `${base}/areas`, icon: 'layers', permissions: FEST_MANAGE },
            { label: 'Eligibility rules', href: `${base}/eligibility-rules`, icon: 'check-square', permissions: FEST_MANAGE },
            { label: 'Rounds & levels', href: `${base}/levels`, icon: 'repeat', permissions: FEST_MANAGE },
            { label: 'Activity log', href: `${base}/activity`, icon: 'clock', permissions: FEST_VIEW },
        ],
    });

    groups.push({
        section: 'Registrations',
        items: [
            { label: 'All registrations', href: `${base}/registrations`, icon: 'inbox', permissions: FEST_REGISTRATIONS },
            { label: 'Clash requests', href: `${base}/clash-requests`, icon: 'alert-circle', permissions: FEST_REGISTRATIONS },
            { label: 'Substitutions', href: `${base}/substitution-requests`, icon: 'repeat', permissions: FEST_REGISTRATIONS },
            { label: 'Attendance', href: `${base}/attendance`, icon: 'check-square', permissions: FEST_REGISTRATIONS },
        ],
    });

    groups.push({
        section: 'Schedule',
        items: [
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

    groups.push({ section: 'Competition', items: competitionItems });

    const outputItems = [
        { label: 'Reports hub', href: `${base}/reports`, icon: 'file-text', permissions: FEST_VIEW },
        { label: 'Certificates', href: `${base}/certificates`, icon: 'award', permissions: FEST_CERTIFICATES },
        { label: 'ID cards', href: `${base}/id-cards`, icon: 'credit-card', permissions: FEST_VIEW },
    ];

    if (caps.hasEventFees) {
        outputItems.unshift(
            { label: 'Registration fees', href: `${base}/fees`, icon: 'credit-card', permissions: FEST_FINANCE },
            { label: 'Payment ledger', href: `${base}/fees/ledger`, icon: 'layers', permissions: FEST_FINANCE },
        );
    }

    groups.push({ section: 'Outputs', items: outputItems });

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
        const visible = programEvents.filter((ev) => String(ev.id) !== String(eventId)).slice(0, 4);
        if (visible.length) {
            const items = visible.map((ev) => ({
                label: ev.title,
                href: `${tenantBase}/events/${ev.id}`,
                icon: 'layers',
                permissions: FEST_VIEW,
            }));
            if (program?.slug && programEvents.length > 5) {
                items.push({
                    label: `All ${programEvents.length} events…`,
                    href: `${sahodayaProgramHref(sahodayaId, program.slug)}${eq}`,
                    icon: 'grid',
                    permissions: FEST_VIEW,
                });
            }
            groups.push({ section: 'Switch event', items });
        }
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
