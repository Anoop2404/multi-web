/**
 * Superadmin / state-admin sidebar navigation (data-driven for menu search),
 * same {section, items} shape as sahodayaAdminNav.js/schoolAdminNav.js so
 * filterNavGroups.js works unchanged.
 */

export function superadminNav(options = {}) {
    const { websiteEnabled = false, pendingReceiptsCount = 0 } = options;

    const groups = [
        {
            section: 'Overview',
            items: [
                { label: 'Dashboard', href: '/admin/dashboard', icon: 'grid', exact: true },
            ],
        },
        {
            section: 'Sahodaya Clusters',
            items: [
                { label: 'All Sahodayas', href: '/admin/sahodayas', icon: 'building', exact: true },
                { label: 'Add Sahodaya', href: '/admin/sahodayas/create', icon: 'plus' },
            ],
        },
        {
            section: 'Member Schools',
            items: [
                { label: 'All Schools', href: '/admin/schools', icon: 'building', exact: true },
                { label: 'Add School', href: '/admin/schools/create', icon: 'plus' },
            ],
        },
        {
            section: 'State Workspace & Results',
            items: [
                { label: 'Qualifier Intakes', href: '/admin/state-workspace/qualifiers', icon: 'inbox' },
                { label: 'State Finals', href: '/admin/state-workspace/fest', icon: 'award' },
                { label: 'Kalotsav View', href: '/admin/kalotsav', icon: 'star' },
                { label: 'Sports Results', href: '/admin/sports', icon: 'award' },
                { label: 'MCQ Results', href: '/admin/mcq-results', icon: 'clipboard' },
                { label: 'Board Results', href: '/admin/board-results', icon: 'bar-chart' },
            ],
        },
        {
            section: 'Subscriptions',
            items: [
                { label: 'Billing & Subscriptions', href: '/admin/billing', icon: 'credit-card', badge: pendingReceiptsCount },
            ],
        },
        {
            section: 'Security & Tools',
            items: [
                { label: 'States', href: '/admin/states', icon: 'map-pin' },
                { label: 'State Users', href: '/admin/state-users', icon: 'users' },
                { label: 'Announcements', href: '/admin/announcements', icon: 'bell' },
                { label: 'Audit Log', href: '/admin/audit-logs', icon: 'file-text' },
                { label: 'Reports', href: '/admin/reports', icon: 'bar-chart' },
                { label: 'S3 Migration', href: '/admin/storage-migration', icon: 'cloud' },
                { label: 'Dev Pass Token', href: '/admin/dev-pass-token', icon: 'key' },
            ],
        },
        {
            section: 'Platform Rules',
            items: [
                { label: 'State Programs', href: '/admin/state-programs', icon: 'clipboard' },
                { label: 'State Remittances', href: '/admin/state-remittances', icon: 'credit-card' },
                { label: 'Class Categories', href: '/admin/master-data/class-categories', icon: 'layers' },
                { label: 'Teaching Types', href: '/admin/master-data/teaching-types', icon: 'users' },
                { label: 'Subjects', href: '/admin/master-data/subjects', icon: 'book-open' },
                { label: 'Designations', href: '/admin/master-data/designations', icon: 'tag' },
                { label: 'Age Categories', href: '/admin/master-data/age-categories', icon: 'calendar' },
            ],
        },
    ];

    if (websiteEnabled) {
        groups.push({
            section: 'Site Builder & Themes',
            items: [
                { label: 'Sections', href: '/admin/builder/sections', icon: 'layers' },
                { label: 'Theme & Skin', href: '/admin/builder/theme', icon: 'settings' },
                { label: 'Navigation', href: '/admin/builder/nav', icon: 'grid' },
                { label: 'Footer', href: '/admin/builder/footer', icon: 'layers' },
                { label: 'Widgets', href: '/admin/builder/widgets', icon: 'settings' },
                { label: 'Skin Presets', href: '/admin/skin-presets', icon: 'edit' },
            ],
        });
    }

    return groups;
}

export function stateAdminNav() {
    return [
        {
            section: 'Overview',
            items: [
                { label: 'Dashboard', href: '/admin/state-dashboard', icon: 'grid', exact: true },
            ],
        },
        {
            section: 'State Workspace',
            items: [
                { label: 'Qualifier Intakes', href: '/admin/state-workspace/qualifiers', icon: 'inbox' },
                { label: 'State Finals', href: '/admin/state-workspace/fest', icon: 'award' },
                { label: 'Cross-Cluster View', href: '/admin/kalotsav', icon: 'star' },
                { label: 'Sports Results', href: '/admin/sports', icon: 'award' },
                { label: 'MCQ Results', href: '/admin/mcq-results', icon: 'clipboard' },
                { label: 'Board Results', href: '/admin/board-results', icon: 'bar-chart' },
            ],
        },
        {
            section: 'State Configuration',
            items: [
                { label: 'State Programs', href: '/admin/state-programs', icon: 'clipboard' },
            ],
        },
        {
            section: 'Finance',
            items: [
                { label: 'Remittances', href: '/admin/state-remittances', icon: 'credit-card' },
            ],
        },
        {
            section: 'Clusters',
            items: [
                { label: 'Sahodaya Clusters', href: '/admin/sahodayas', icon: 'building', exact: true },
            ],
        },
        {
            section: 'Administration',
            items: [
                { label: 'State Users', href: '/admin/state-users', icon: 'shield', exact: true },
            ],
        },
    ];
}

/** Resolve active state for admin nav href — mirrors sahodayaAdminNav.js's adminNavItemActive(). */
export function adminNavItemActive(pageUrl, href, exact = false) {
    const path = pageUrl.split('#')[0].split('?')[0];
    const target = href.split('#')[0].split('?')[0];

    if (exact) {
        return path === target || path === `${target}/`;
    }

    return path === target || path.startsWith(`${target}/`);
}
