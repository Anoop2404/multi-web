/**
 * Sports fest — sidebar sections (Sahodaya admin).
 *
 * After Head = Event unification:
 * - Season hub: config only (age/cutoff/remittance) + link to sport events list
 * - Each sport (Athletics, Chess, …): full competition ops on that FestEvent
 */

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

/**
 * Season hub sidebar — no Items / registrations (those live on child sport
 * events). Unreachable in current builds: `isSportsSeasonEvent()` below is
 * hardcoded to return false after the Head = Event unification, so callers
 * never take this branch. Left in place (not deleted) in case season hub is
 * re-enabled later — see SPORTS_NAV_CLEANUP_PLAN.md #8.
 */
export function sportsSeasonSidebarNav(sahodayaId, eventId) {
    const base = `/sahodaya-admin/${sahodayaId}/events/${eventId}`;
    const sportsHub = `/sahodaya-admin/${sahodayaId}/sports`;

    return [
        {
            section: 'Season hub',
            items: [
                { label: 'Overview', href: `${base}?overview=1`, icon: 'grid', exact: true, permissions: FEST_VIEW },
                { label: 'Setup', href: `${base}/setup`, icon: 'settings', permissions: FEST_SETTINGS },
                { label: 'Settings', href: `${base}/settings`, icon: 'sliders', permissions: FEST_SETTINGS },
                { label: 'Activity log', href: `${base}/activity`, icon: 'clock', permissions: FEST_VIEW },
            ],
        },
        {
            section: 'Sport events',
            items: [
                { label: 'All sports (Chess, …)', href: sportsHub, icon: 'layers', permissions: FEST_VIEW },
            ],
        },
    ];
}

/** @returns {Array<{section: string, items: Array}>} */
export function sportsEventSidebarNav(base, caps) {
    const groups = [];

    groups.push({
        section: 'This event',
        items: [
            { label: 'Overview', href: `${base}?overview=1`, icon: 'grid', exact: true, permissions: FEST_VIEW },
            { label: 'Setup & Settings', href: `${base}/setup`, icon: 'settings', permissions: FEST_SETTINGS },
            { label: 'Activity log', href: `${base}/activity`, icon: 'clock', permissions: FEST_VIEW },
        ],
    });

    groups.push({
        section: 'Competition',
        items: [
            { label: 'Items', href: `${base}/items`, icon: 'list', permissions: FEST_SETTINGS },
            { label: 'Rounds & levels', href: `${base}/levels`, icon: 'repeat', permissions: FEST_MANAGE },
            { label: 'Registrations', href: `${base}/registrations`, icon: 'inbox', permissions: FEST_REGISTRATIONS },
            { label: 'Chest numbers', href: `${base}/chest-numbers`, icon: 'hash', permissions: FEST_MANAGE },
            { label: 'Marks', href: `${base}/marks`, icon: 'edit', permissions: FEST_MARKS },
            { label: 'Results', href: `${base}/results`, icon: 'award', permissions: FEST_RESULTS },
        ],
    });

    groups.push({
        section: 'Requests',
        items: [
            { label: 'Clash requests', href: `${base}/clash-requests`, icon: 'alert-circle', permissions: FEST_REGISTRATIONS },
            { label: 'Substitutions', href: `${base}/substitution-requests`, icon: 'repeat', permissions: FEST_REGISTRATIONS },
        ],
    });

    groups.push({
        section: 'Schedule & Reports',
        items: [
            { label: 'Venue schedule', href: `${base}/schedule`, icon: 'calendar', permissions: FEST_SCHEDULE },
            { label: 'Reports', href: `${base}/reports`, icon: 'file-text', permissions: FEST_VIEW },
            { label: 'Certificates', href: `${base}/certificates`, icon: 'award', permissions: FEST_CERTIFICATES },
            { label: 'ID cards', href: `${base}/id-cards`, icon: 'credit-card', permissions: FEST_VIEW },
        ],
    });

    if (caps.hasEventFees) {
        groups.push({
            section: 'Finance',
            items: [
                { label: 'Registration fees', href: `${base}/fees`, icon: 'credit-card', permissions: FEST_FINANCE },
                { label: 'Payment ledger', href: `${base}/fees/ledger`, icon: 'layers', permissions: FEST_FINANCE },
            ],
        });
    }

    return groups;
}

/** Legacy season hub helper — intentionally inert after Head = Event unification. */
export function isSportsSeasonEvent(event) {
    return false;
}

/** No Event Head mini-nav after Head = Event unification. */
export const SPORTS_HEAD_SIDEBAR_PATHS = [];

export function shouldShowSportsHeadSidebar(path, hasHeads) {
    return false;
}

/** School admin — no head drill-down (sport event is one billable unit). */
export const SCHOOL_SPORTS_HEAD_SIDEBAR_PATHS = [];

export function shouldShowSchoolSportsHeadSidebar(path, hasHeads, isSports) {
    return false;
}
