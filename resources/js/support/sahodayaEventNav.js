/**
 * Events module sidebar navigation (Sahodaya admin).
 * Sports events use a dedicated compact sidebar — see sportsEventNav.js.
 */

import { capabilitiesForEvent } from './sahodayaEventCapabilities.js';
import { isSportsSeasonEvent, sportsEventSidebarNav, sportsSeasonSidebarNav } from './sportsEventNav.js';
export { sportsEventSidebarNav, sportsSeasonSidebarNav, isSportsSeasonEvent, shouldShowSportsHeadSidebar, SPORTS_HEAD_SIDEBAR_PATHS, shouldShowSchoolSportsHeadSidebar, SCHOOL_SPORTS_HEAD_SIDEBAR_PATHS } from './sportsEventNav.js';
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
    const { navVisibility = null, scopedEventTypes = null } = options;
    const base = `/sahodaya-admin/${sahodayaId}`;
    // Same event/region/phase scoping as sahodayaAdminNav.js's Fest & events section — see
    // its own comment for why (every other program hub 403s, "All events" renders empty).
    const isScoped = Array.isArray(scopedEventTypes);

    const programItems = PROGRAM_SLUGS
        .filter((slug) => isNavProgramVisible(navVisibility, slug))
        .filter((slug) => !isScoped || scopedEventTypes.includes(SAHODAYA_PROGRAMS[slug].eventType))
        .map((slug) => {
            const p = SAHODAYA_PROGRAMS[slug];
            return { label: p.label, href: sahodayaProgramHref(sahodayaId, slug), icon: p.icon, permissions: FEST_VIEW };
        });

    return [
        ...(programItems.length ? [{
            section: 'Fest programs',
            items: programItems,
        }] : []),
        ...(isScoped ? [] : [{
            section: 'Directory',
            items: [
                { label: 'All events', href: `${base}/events`, icon: 'layers', exact: true, permissions: FEST_VIEW },
            ],
        }]),
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
        const groups = isSportsSeasonEvent(event)
            ? sportsSeasonSidebarNav(sahodayaId, eventId)
            : sportsEventSidebarNav(base, caps);

        if (programEvents.length && !isSportsSeasonEvent(event)) {
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
            // Structural/config editing (not region_admin's operational scope — mark entry, ID
            // cards, registrations, finance, catering) — gated on fest.settings specifically so
            // fest.manage (which region_admin does hold, for id-cards/food-billing) doesn't
            // unlock these too. See FEST_SETTINGS's own doc in sahodayaEventNavPermissions.js.
            { label: 'Items & catalog', href: `${base}/items`, icon: 'file-text', permissions: FEST_SETTINGS },
            { label: 'Item windows', href: `${base}/settings/registration`, icon: 'calendar', permissions: FEST_SETTINGS },
            { label: 'Competition areas', href: `${base}/areas`, icon: 'layers', permissions: FEST_SETTINGS },
            { label: 'Eligibility rules', href: `${base}/eligibility-rules`, icon: 'check-square', permissions: FEST_SETTINGS },
            { label: 'Rounds & levels', href: `${base}/levels`, icon: 'repeat', permissions: FEST_SETTINGS },
            // Previously only reachable via an in-page button on Rounds & levels, three
            // clicks from the sidebar, and invisible to the sidebar's own search — the most
            // operationally important screen for any phased_regional_billing event.
            { label: 'Phases', href: `${base}/phases`, icon: 'layers', permissions: FEST_SETTINGS },
            { label: 'Activity log', href: `${base}/activity`, icon: 'clock', permissions: FEST_VIEW },
        ],
    });

    groups.push({
        section: 'Registrations',
        items: [
            { label: 'All registrations', href: `${base}/registrations`, icon: 'inbox', permissions: FEST_REGISTRATIONS },
            { label: 'Clash requests', href: `${base}/clash-requests`, icon: 'alert-circle', permissions: FEST_REGISTRATIONS },
            { label: 'Substitutions', href: `${base}/substitution-requests`, icon: 'repeat', permissions: FEST_REGISTRATIONS },
            { label: 'Chest numbers', href: `${base}/chest-numbers`, icon: 'hash', permissions: FEST_MANAGE },
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
        { label: 'Mark settings', href: `${base}/mark-settings`, icon: 'check-square', permissions: FEST_MARKS },
        { label: 'Grade master', href: `${base}/grade-master`, icon: 'tag', permissions: FEST_SETTINGS },
        { label: 'Rank points', href: `${base}/rank-points`, icon: 'arrow-up-circle', permissions: FEST_SETTINGS },
        { label: 'Results & publish', href: `${base}/results`, icon: 'award', permissions: FEST_RESULTS },
        { label: 'Leaderboard', href: `${base}/leaderboard`, icon: 'bar-chart', permissions: FEST_RESULTS },
    ];

    if (caps.championship) {
        competitionItems.push({ label: 'Championship', href: `${base}/championship`, icon: 'star', permissions: FEST_RESULTS });
    }

    groups.push({ section: 'Competition', items: competitionItems });

    const outputItems = [
        { label: 'Reports hub', href: `${base}/reports`, icon: 'file-text', permissions: FEST_VIEW },
        { label: 'Student-wise report', href: `${base}/reports/student-wise`, icon: 'users', permissions: FEST_VIEW },
        { label: 'Item-wise report', href: `${base}/reports/item-wise`, icon: 'list', permissions: FEST_VIEW },
        { label: 'Item counts', href: `${base}/reports/item-counts`, icon: 'bar-chart', permissions: FEST_VIEW },
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
        // Was labeled "Judges & staff" — the page itself is judges-only (Judges.vue), and
        // a genuinely separate "Event staff" item sits right below; the old label made the
        // two adjacent items look redundant.
        { label: 'Judges', href: `${base}/judges`, icon: 'user-check', permissions: FEST_MANAGE },
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
        // "(legacy)" — free headcount-only flow, superseded by Food menu/Food billing below.
        // Kept for events already using it; not offered to new events. See
        // docs/REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md §2.6.
        adminItems.push({ label: 'Catering (legacy)', href: `${base}/catering`, icon: 'clipboard', permissions: FEST_CATERING });
    }
    if (caps.foodCoupons) {
        // Not "(legacy)" — this page now issues from either the old catering flow or the
        // new food-billing flow (issueFromBill()), and is the shared redemption/print UI.
        adminItems.push({ label: 'Food coupons', href: `${base}/food-coupons`, icon: 'hash', permissions: FEST_CATERING });
    }
    adminItems.push(
        { label: 'Food menu', href: `${base}/food-menu`, icon: 'clipboard', permissions: FEST_CATERING },
        { label: 'Food billing', href: `${base}/food-billing`, icon: 'credit-card', permissions: FEST_CATERING },
    );

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
