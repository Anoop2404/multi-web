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

/** Season hub — no Items / registrations (those live on child sport events). */
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
            { label: 'Setup', href: `${base}/setup`, icon: 'settings', permissions: FEST_SETTINGS },
            { label: 'Settings', href: `${base}/settings`, icon: 'sliders', permissions: FEST_SETTINGS },
            { label: 'Activity log', href: `${base}/activity`, icon: 'clock', permissions: FEST_VIEW },
        ],
    });

    groups.push({
        section: 'Competition',
        items: [
            { label: 'Items', href: `${base}/items`, icon: 'list', permissions: FEST_SETTINGS },
            { label: 'Item listing', href: `${base}/items/list`, icon: 'clipboard', permissions: FEST_VIEW },
            { label: 'Registrations', href: `${base}/registrations`, icon: 'inbox', permissions: FEST_REGISTRATIONS },
            { label: 'Mark entry', href: `${base}/marks`, icon: 'edit', permissions: FEST_MARKS },
            { label: 'Chest numbers', href: `${base}/chest-numbers`, icon: 'hash', permissions: FEST_MANAGE },
            { label: 'Results & publish', href: `${base}/results`, icon: 'award', permissions: FEST_RESULTS },
            { label: 'Leaderboard', href: `${base}/leaderboard`, icon: 'bar-chart', permissions: FEST_RESULTS },
        ],
    });

    groups.push({
        section: 'Requests',
        items: [
            { label: 'Clash requests', href: `${base}/clash-requests`, icon: 'alert-circle', permissions: FEST_REGISTRATIONS },
            { label: 'Substitutions', href: `${base}/substitution-requests`, icon: 'repeat', permissions: FEST_REGISTRATIONS },
            { label: 'Attendance', href: `${base}/attendance`, icon: 'check-square', permissions: FEST_REGISTRATIONS },
        ],
    });

    groups.push({
        section: 'Schedule',
        items: [
            { label: 'Venue schedule', href: `${base}/schedule`, icon: 'calendar', permissions: FEST_SCHEDULE },
            { label: 'Item schedule', href: `${base}/schedule/items`, icon: 'map-pin', permissions: FEST_SCHEDULE },
        ],
    });

    const outputItems = [
        { label: 'Reports', href: `${base}/reports`, icon: 'file-text', permissions: FEST_VIEW },
        { label: 'All report types', href: `${base}/reports?all=1`, icon: 'layers', permissions: FEST_VIEW },
        { label: 'Certificates', href: `${base}/certificates`, icon: 'award', permissions: FEST_CERTIFICATES },
        { label: 'ID cards', href: `${base}/id-cards`, icon: 'credit-card', permissions: FEST_VIEW },
    ];

    if (caps.hasEventFees) {
        outputItems.push(
            { label: 'Registration fees', href: `${base}/fees`, icon: 'credit-card', permissions: FEST_FINANCE },
            { label: 'Payment ledger', href: `${base}/fees/ledger`, icon: 'layers', permissions: FEST_FINANCE },
        );
    }

    groups.push({ section: 'Outputs', items: outputItems });

    const adminItems = [
        { label: 'Event staff', href: `${base}/event-staff`, icon: 'user-check', permissions: FEST_MANAGE },
        { label: 'Appeals', href: `${base}/appeals`, icon: 'inbox', permissions: FEST_MANAGE },
        { label: 'School invoices', href: `${base}/finance`, icon: 'file-text', permissions: FEST_FINANCE },
        { label: 'Athletic records', href: `${base}/athletic-records`, icon: 'star', permissions: FEST_MANAGE },
        { label: 'Houses', href: `${base}/houses`, icon: 'building', permissions: FEST_MANAGE },
        { label: 'Catering', href: `${base}/catering`, icon: 'clipboard', permissions: FEST_CATERING },
        { label: 'Food coupons', href: `${base}/food-coupons`, icon: 'hash', permissions: FEST_CATERING },
        { label: 'Rounds & levels', href: `${base}/levels`, icon: 'repeat', permissions: FEST_MANAGE },
    ];

    groups.push({ section: 'Administration', items: adminItems });

    return groups;
}

/** True when this FestEvent is the season container (not Chess/Aquatics itself). */
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
