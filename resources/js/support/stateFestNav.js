/**
 * State Kalotsav module navigation.
 *
 * Deliberately not derived from sahodayaEventNav.js. The two modules answer different questions —
 * the Sahodaya event competes School against School, the State event competes Sahodaya against
 * Sahodaya — and sharing a nav file would be the first step toward sharing the queries behind it.
 *
 * Every item carries the state.fest.* permission that gates its screen, so the sidebar shows an
 * operator only what their role can actually open: a mark operator does not see Results or Publish,
 * a certificate operator does not see Mark Entry.
 */

export const STATE_FEST = {
    VIEW: 'state.fest.view',
    SETTINGS: 'state.fest.settings',
    CATALOG: 'state.fest.catalog',
    QUALIFIERS: 'state.fest.qualifiers',
    SCRUTINY: 'state.fest.scrutiny',
    REGISTRATIONS: 'state.fest.registrations',
    SCHEDULE: 'state.fest.schedule',
    ATTENDANCE: 'state.fest.attendance',
    JUDGES: 'state.fest.judges',
    MARKS: 'state.fest.marks',
    RESULTS: 'state.fest.results',
    APPEALS: 'state.fest.appeals',
    FINANCE: 'state.fest.finance',
    CATERING: 'state.fest.catering',
    CERTIFICATES: 'state.fest.certificates',
    REPORTS: 'state.fest.reports',
    AUDIT: 'state.fest.audit',
    PUBLISH: 'state.fest.publish',
};

/** The State admin's main sidebar — the modules, not the inside of an event. */
export function stateModuleNav() {
    return [
        {
            section: 'Overview',
            items: [
                { label: 'State Dashboard', href: '/admin/state-dashboard', icon: 'grid', exact: true, permission: STATE_FEST.VIEW },
            ],
        },
        {
            section: 'Participants',
            items: [
                { label: 'Sahodaya Directory', href: '/admin/state/sahodayas', icon: 'building', permission: STATE_FEST.VIEW },
                { label: 'External Sahodayas', href: '/admin/state-programs', icon: 'inbox', permission: STATE_FEST.VIEW },
            ],
        },
        {
            section: 'Conduct',
            items: [
                { label: 'State Programs', href: '/admin/state-programs', icon: 'clipboard', permission: STATE_FEST.CATALOG },
                { label: 'State Finals', href: '/admin/state-workspace/fest', icon: 'award', permission: STATE_FEST.VIEW },
                { label: 'Qualifier Intake', href: '/admin/state-workspace/qualifiers', icon: 'inbox', permission: STATE_FEST.QUALIFIERS },
            ],
        },
        {
            section: 'Finance',
            items: [
                { label: 'Remittances', href: '/admin/state-remittances', icon: 'credit-card', permission: STATE_FEST.FINANCE },
            ],
        },
        {
            section: 'Administration',
            items: [
                { label: 'State Users', href: '/admin/state-users', icon: 'shield', exact: true, permission: STATE_FEST.VIEW },
            ],
        },
    ];
}

/**
 * Tabs inside one State event workspace.
 *
 * Grouped the way the event is actually run — set it up, take in qualifiers, schedule it, conduct
 * it, settle money, publish — rather than by which table each screen happens to write to.
 */
export function stateEventWorkspaceNav(eventId) {
    const base = `/admin/state/fest/${eventId}`;

    return [
        {
            section: 'Event Home',
            items: [
                { label: 'Overview', href: base, icon: 'grid', exact: true, permission: STATE_FEST.VIEW },
                { label: 'Settings', href: `${base}/settings`, icon: 'settings', permission: STATE_FEST.SETTINGS },
                { label: 'Items & Catalog', href: `${base}/items`, icon: 'list', permission: STATE_FEST.CATALOG },
                { label: 'Sahodaya Slots', href: `${base}/slots`, icon: 'sliders', permission: STATE_FEST.CATALOG },
                { label: 'Categories & Eligibility', href: `${base}/eligibility`, icon: 'filter', permission: STATE_FEST.CATALOG },
                { label: 'Venues & Stages', href: `${base}/venues`, icon: 'map-pin', permission: STATE_FEST.SCHEDULE },
                { label: 'Event Staff', href: `${base}/staff`, icon: 'users', permission: STATE_FEST.SETTINGS },
                { label: 'Activity Log', href: `${base}/activity`, icon: 'clock', permission: STATE_FEST.AUDIT },
            ],
        },
        {
            section: 'Qualifiers & Registrations',
            items: [
                { label: 'Sahodaya Submissions', href: `${base}/submissions`, icon: 'inbox', permission: STATE_FEST.QUALIFIERS },
                { label: 'Scrutiny', href: `${base}/scrutiny`, icon: 'check-circle', permission: STATE_FEST.SCRUTINY },
                { label: 'All Registrations', href: `${base}/registrations`, icon: 'users', permission: STATE_FEST.REGISTRATIONS },
                { label: 'Pending Approvals', href: `${base}/pending`, icon: 'alert-circle', permission: STATE_FEST.SCRUTINY },
                { label: 'Teams & Squads', href: `${base}/teams`, icon: 'users', permission: STATE_FEST.REGISTRATIONS },
                { label: 'Substitutions', href: `${base}/substitutions`, icon: 'refresh', permission: STATE_FEST.REGISTRATIONS },
                { label: 'Chest Numbers', href: `${base}/chest-numbers`, icon: 'hash', permission: STATE_FEST.REGISTRATIONS },
                { label: 'ID & Admit Cards', href: `${base}/reports/participant-cards`, icon: 'credit-card', permission: STATE_FEST.REPORTS },
            ],
        },
        {
            section: 'Scheduling',
            items: [
                { label: 'Item Schedule', href: `${base}/schedule`, icon: 'calendar', permission: STATE_FEST.SCHEDULE },
                { label: 'Performance Order', href: `${base}/performance-order`, icon: 'list', permission: STATE_FEST.SCHEDULE },
                { label: 'Schedule Clashes', href: `${base}/clashes`, icon: 'alert-triangle', permission: STATE_FEST.SCHEDULE },
                { label: 'Green Room', href: `${base}/green-room`, icon: 'monitor', permission: STATE_FEST.SCHEDULE },
                { label: 'Printed Sheets', href: `${base}/reports?group=print`, icon: 'printer', permission: STATE_FEST.REPORTS },
            ],
        },
        {
            section: 'Competition',
            items: [
                { label: 'Attendance', href: `${base}/attendance`, icon: 'check-square', permission: STATE_FEST.ATTENDANCE },
                { label: 'Judges', href: `${base}/judges`, icon: 'user-check', permission: STATE_FEST.JUDGES },
                { label: 'Mark Entry', href: `${base}/marks`, icon: 'edit', permission: STATE_FEST.MARKS },
                { label: 'Mark Settings', href: `${base}/mark-settings`, icon: 'sliders', permission: STATE_FEST.SETTINGS },
                { label: 'Grade Master', href: `${base}/grades`, icon: 'award', permission: STATE_FEST.SETTINGS },
                { label: 'Grade & Rank Points', href: `${base}/points`, icon: 'star', permission: STATE_FEST.SETTINGS },
                { label: 'Results & Publish', href: `${base}/results`, icon: 'award', permission: STATE_FEST.RESULTS },
                { label: 'Leaderboard', href: `${base}/leaderboard`, icon: 'bar-chart', permission: STATE_FEST.RESULTS },
                { label: 'Individual Championship', href: `${base}/individual-championship`, icon: 'star', permission: STATE_FEST.RESULTS },
                { label: 'Appeals', href: `${base}/appeals`, icon: 'flag', permission: STATE_FEST.APPEALS },
            ],
        },
        {
            section: 'Finance & Services',
            items: [
                { label: 'State Event Fees', href: `${base}/fees`, icon: 'credit-card', permission: STATE_FEST.FINANCE },
                { label: 'Payment Ledger', href: `${base}/ledger`, icon: 'book', permission: STATE_FEST.FINANCE },
                { label: 'Catering & Food', href: `${base}/catering`, icon: 'coffee', permission: STATE_FEST.CATERING },
                { label: 'Volunteers & Officials', href: `${base}/volunteers`, icon: 'users', permission: STATE_FEST.SETTINGS },
                { label: 'Judge Portal', href: '/portal/state-fest-judge', icon: 'user-check', permission: STATE_FEST.JUDGES, external: true },
            ],
        },
        {
            section: 'Output',
            items: [
                { label: 'Reports', href: `${base}/reports`, icon: 'file-text', permission: STATE_FEST.REPORTS },
                { label: 'Certificates', href: `${base}/certificates`, icon: 'award', permission: STATE_FEST.CERTIFICATES },
                { label: 'Public Portal', href: `${base}/public-portal`, icon: 'globe', permission: STATE_FEST.PUBLISH },
            ],
        },
    ];
}

/**
 * Drops items the signed-in user cannot open, and then any section left empty.
 *
 * Hiding rather than disabling is deliberate: an operator should not be shown a map of everything
 * they are not trusted with.
 */
export function visibleStateNav(sections, permissions = []) {
    const held = new Set(permissions || []);
    const allows = (item) => !item.permission || held.has(item.permission);

    return (sections || [])
        .map((section) => ({ ...section, items: (section.items || []).filter(allows) }))
        .filter((section) => section.items.length > 0);
}

/** Tabs whose screens are not built yet, so the shell can render them as pending rather than 404. */
export function isStateNavItemReady(href, readyPaths = []) {
    return readyPaths.some((path) => href === path || href.endsWith(path));
}
