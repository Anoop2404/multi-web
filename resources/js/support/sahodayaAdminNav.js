/**
 * Sahodaya admin sidebar navigation (data-driven for menu search).
 */

/** @param {{ programs?: Record<string, boolean>, menus?: Record<string, boolean> }|null|undefined} navVisibility */
export function isNavProgramVisible(navVisibility, slug) {
    if (!navVisibility?.programs) {
        return true;
    }

    return navVisibility.programs[slug] !== false;
}

/** @param {{ programs?: Record<string, boolean>, menus?: Record<string, boolean> }|null|undefined} navVisibility */
export function isNavMenuVisible(navVisibility, key) {
    if (!navVisibility?.menus) {
        return true;
    }

    return navVisibility.menus[key] !== false;
}

/** @returns {string|null} */
export function detectSahodayaMcqExamIdFromUrl(url) {
    const match = (url ?? '').split('?')[0].match(/\/mcq-exams\/(\d+)/);
    return match ? match[1] : null;
}

/** @returns {string|null} */
export function detectSahodayaMcqSeriesIdFromUrl(url) {
    const match = (url ?? '').split('?')[0].match(/\/mcq-series\/(\d+)/);
    return match ? match[1] : null;
}

/** @returns {boolean} */
export function detectSahodayaMcqHubFromUrl(url) {
    const path = (url ?? '').split('?')[0];
    if (detectSahodayaMcqExamIdFromUrl(path)) {
        return false;
    }
    if (detectSahodayaMcqSeriesIdFromUrl(path)) {
        return false;
    }

    return /\/mcq(?:\/|$)/.test(path)
        || /\/mcq-exams(?:\/|$)/.test(path)
        || /\/mcq-series(?:\/|$)/.test(path);
}

/** @returns {boolean} */
export function detectSahodayaMembershipFromUrl(url) {
    const path = (url ?? '').split('?')[0];

    return /\/membership(?:\/|$)/.test(path)
        || /\/schools(?:\/|$)/.test(path)
        || /\/regions(?:\/|$)/.test(path)
        || /\/students\/registration-windows(?:\/|$)/.test(path)
        || /\/students\/verification(?:\/|$)/.test(path)
        || /\/teachers\/verification(?:\/|$)/.test(path)
        || /\/student-change-requests(?:\/|$)/.test(path)
        || /\/users\/profile-change-requests(?:\/|$)/.test(path)
        || /\/documents\/(?:review|types)(?:\/|$)/.test(path)
        || /\/board-results(?:\/|$)/.test(path)
        || /\/academic-years(?:\/|$)/.test(path)
        || /\/auth\/login-audit(?:\/|$)/.test(path)
        || /\/calendar(?:\/|$)/.test(path)
        || /\/state-remittances(?:\/|$)/.test(path)
        || /\/setup(?:\/|$)/.test(path);
}

/** @returns {string|null} */
export function detectSahodayaTrainingProgramIdFromUrl(url) {
    const match = (url ?? '').split('?')[0].match(/\/training\/(\d+)/);
    return match ? match[1] : null;
}

/** @returns {boolean} */
export function detectSahodayaTrainingHubFromUrl(url) {
    const path = (url ?? '').split('?')[0];
    if (detectSahodayaTrainingProgramIdFromUrl(path)) {
        return false;
    }

    return /\/training(?:\/|$)/.test(path);
}

/** Sidebar when browsing membership workflow pages. */
export function sahodayaMembershipScopedNav(sahodayaId, options = {}) {
    const {
        canNav = () => true,
        approvedSchoolsCount = 0,
        pendingPaymentsCount = 0,
        pendingSubmissionsCount = 0,
        pendingSchoolsCount = 0,
        pendingChangeRequests = 0,
        unverifiedStudentsCount = 0,
        setupIncompleteCount = 0,
        stateRemittancesEnabled = true,
    } = options;

    if (!canNav('membership')) {
        return [];
    }

    const base = `/sahodaya-admin/${sahodayaId}`;

    const settingsItems = [];
    if (canNav('membership')) {
        settingsItems.push({
            label: 'Configuration',
            href: `${base}/membership/settings`,
            icon: 'settings',
            badge: setupIncompleteCount,
        });
        settingsItems.push({ label: 'Document types', href: `${base}/documents/types`, icon: 'file-text' });
        if (stateRemittancesEnabled) {
            settingsItems.push({ label: 'State remittances', href: `${base}/state-remittances`, icon: 'credit-card' });
        }
    }

    return [
        {
            section: 'Sahodaya',
            items: [{ label: 'Main dashboard', href: base, icon: 'grid', exact: true }],
        },
        {
            section: 'Membership',
            items: [
                { label: 'Member schools', href: `${base}/schools`, icon: 'building', badge: approvedSchoolsCount },
                { label: 'Regions', href: `${base}/regions`, icon: 'map-pin' },
                { label: 'Pending applications', href: `${base}/schools/applications`, icon: 'inbox', badge: pendingSchoolsCount, hidden: !pendingSchoolsCount },
                { label: 'Membership fees', href: `${base}/membership/payments`, icon: 'credit-card', badge: pendingPaymentsCount },
                { label: 'Student change requests', href: `${base}/student-change-requests`, icon: 'inbox', badge: pendingChangeRequests },
                { label: 'Registration windows', href: `${base}/students/registration-windows`, icon: 'calendar' },
                { label: 'Profile change requests', href: `${base}/users/profile-change-requests`, icon: 'inbox' },
                { label: 'Student verification', href: `${base}/students/verification${unverifiedStudentsCount ? '?verification=unverified' : ''}`, icon: 'users', badge: unverifiedStudentsCount },
                { label: 'Teacher verification', href: `${base}/teachers/verification`, icon: 'users' },
                { label: 'Academic years', href: `${base}/academic-years`, icon: 'calendar' },
                { label: 'Student counts', href: `${base}/membership/submissions`, icon: 'inbox', badge: pendingSubmissionsCount },
                { label: 'Membership reports', href: `${base}/membership/reports`, icon: 'bar-chart' },
                { label: 'Document review', href: `${base}/documents/review`, icon: 'file-text' },
                { label: 'Board results', href: `${base}/board-results/verification`, icon: 'bar-chart' },
                { label: 'Board reports', href: `${base}/board-results/reports`, icon: 'file-text' },
                { label: 'Program calendar', href: `${base}/calendar`, icon: 'calendar' },
                { label: 'Login audit', href: `${base}/auth/login-audit`, icon: 'shield' },
            ],
        },
        ...(settingsItems.length ? [{ section: 'Settings', items: settingsItems }] : []),
    ];
}

/** Sidebar on training programs hub. */
export function sahodayaTrainingHubNav(sahodayaId, options = {}) {
    const { canNav = () => true } = options;
    if (!canNav('training')) {
        return [];
    }

    const base = `/sahodaya-admin/${sahodayaId}`;

    return [
        {
            section: 'Sahodaya',
            items: [{ label: 'Main dashboard', href: base, icon: 'grid', exact: true }],
        },
        {
            section: 'Teacher training',
            items: [
                { label: 'Programs dashboard', href: `${base}/training`, icon: 'grid', exact: true },
                { label: 'Resource persons', href: `${base}/training/resource-persons`, icon: 'users' },
            ],
        },
    ];
}

/** Sidebar when managing one training program. */
export function sahodayaTrainingProgramScopedNav(sahodayaId, programId, options = {}) {
    const { canNav = () => true } = options;
    if (!canNav('training')) {
        return [];
    }

    const base = `/sahodaya-admin/${sahodayaId}`;
    const programBase = `${base}/training/${programId}`;

    return [
        {
            section: 'Sahodaya',
            items: [{ label: 'Main dashboard', href: base, icon: 'grid', exact: true }],
        },
        {
            section: 'Teacher training',
            items: [{ label: 'All programs', href: `${base}/training`, icon: 'award' }],
        },
        {
            section: 'This program',
            items: [
                { label: 'Overview & settings', href: programBase, icon: 'file-text', exact: true },
                { label: 'Payment ledger', href: `${programBase}/ledger`, icon: 'layers' },
                { label: 'Fee approvals', href: `${programBase}/payments`, icon: 'credit-card' },
                { label: 'Sessions', href: `${programBase}#sessions`, icon: 'calendar' },
                { label: 'Registrations', href: `${programBase}/registrations`, icon: 'users' },
                { label: 'Attendance', href: `${programBase}/attendance`, icon: 'clipboard', exact: true },
                { label: 'Attendance sheet', href: `${programBase}/attendance/sheet`, icon: 'file-text' },
                { label: 'Attendance report', href: `${programBase}/attendance/report`, icon: 'bar-chart' },
                { label: 'Certificates (ZIP)', href: `${programBase}/certificates/export`, icon: 'award' },
            ],
        },
    ];
}

function mcqHubItems(base) {
    return [
        { label: 'Talent Search dashboard', href: `${base}/mcq`, icon: 'grid' },
        { label: 'All exams', href: `${base}/mcq-exams`, icon: 'clipboard' },
        { label: 'Exam series', href: `${base}/mcq-series`, icon: 'layers' },
        { label: 'Grade masters', href: `${base}/mcq/grade-masters`, icon: 'bar-chart' },
        { label: 'Hall ticket templates', href: `${base}/mcq/templates/hall-tickets`, icon: 'clipboard' },
        { label: 'Certificate templates', href: `${base}/mcq/templates/certificates`, icon: 'award' },
        { label: 'Payments queue', href: `${base}/mcq/payments`, icon: 'credit-card' },
        { label: 'Question banks', href: `${base}/mcq/question-banks`, icon: 'book-open' },
    ];
}

/** Sidebar when browsing Talent Search hub pages (dashboard, list, series index). */
export function sahodayaMcqHubNav(sahodayaId, options = {}) {
    const { canNav = () => true } = options;
    if (!canNav('mcq')) {
        return [];
    }

    const base = `/sahodaya-admin/${sahodayaId}`;

    return [
        {
            section: 'Sahodaya',
            items: [{ label: 'Main dashboard', href: base, icon: 'grid', exact: true }],
        },
        {
            section: 'Talent Search',
            items: mcqHubItems(base),
        },
    ];
}

/** Sidebar when managing a single Talent Search exam workspace. */
export function sahodayaMcqExamScopedNav(sahodayaId, examId, options = {}) {
    const { canNav = () => true } = options;
    if (!canNav('mcq')) {
        return [];
    }

    const base = `/sahodaya-admin/${sahodayaId}`;
    const examBase = `${base}/mcq-exams/${examId}`;

    return [
        {
            section: 'Sahodaya',
            items: [{ label: '← Dashboard', href: base, icon: 'grid', exact: true }],
        },
        {
            section: 'This exam',
            items: [
                { label: 'Overview', href: examBase, icon: 'file-text' },
                { label: 'Payments', href: `${examBase}/payments`, icon: 'credit-card' },
                { label: 'Ledger', href: `${examBase}/ledger`, icon: 'layers' },
                { label: 'Question banks', href: `${examBase}/question-banks`, icon: 'book-open' },
                { label: 'Hall tickets', href: `${examBase}/hall-tickets`, icon: 'clipboard' },
                { label: 'Attendance', href: `${examBase}/attendance`, icon: 'users' },
                { label: 'Results', href: `${examBase}/results`, icon: 'bar-chart' },
                { label: 'Reports', href: `${examBase}/reports`, icon: 'inbox' },
                { label: 'Live session', href: `${examBase}/session`, icon: 'monitor' },
                { label: 'Leaderboard', href: `${examBase}/leaderboard`, icon: 'star' },
                { label: 'Exam staff', href: `${examBase}/staff`, icon: 'users' },
                { label: 'Activity log', href: `${examBase}/activity`, icon: 'inbox' },
            ],
        },
    ];
}

/** Sidebar when editing an exam series. */
export function sahodayaMcqSeriesScopedNav(sahodayaId, seriesId, options = {}) {
    const { canNav = () => true } = options;
    if (!canNav('mcq')) {
        return [];
    }

    const base = `/sahodaya-admin/${sahodayaId}`;

    return [
        {
            section: 'Sahodaya',
            items: [{ label: 'Main dashboard', href: base, icon: 'grid', exact: true }],
        },
        {
            section: 'Talent Search',
            items: mcqHubItems(base),
        },
        {
            section: 'This series',
            items: [
                { label: 'Series levels', href: `${base}/mcq-series/${seriesId}`, icon: 'layers' },
            ],
        },
    ];
}

export function sahodayaAdminNav(sahodayaId, options = {}) {
    const {
        canNav = () => true,
        websiteEnabled = false,
        publicWebsiteEnabled = true,
        approvedSchoolsCount = 0,
        pendingPaymentsCount = 0,
        pendingSubmissionsCount = 0,
        pendingSchoolsCount = 0,
        pendingChangeRequests = 0,
        pendingFestAppealsCount = 0,
        unverifiedStudentsCount = 0,
        setupIncompleteCount = 0,
        stateRemittancesEnabled = true,
        navVisibility = null,
        competitionPrograms = {},
    } = options;

    const base = `/sahodaya-admin/${sahodayaId}`;
    const groups = [];
    const menuOn = (key) => isNavMenuVisible(navVisibility, key);
    const programOn = (slug) => isNavProgramVisible(navVisibility, slug);

    // ── Home ─────────────────────────────────────────────────────────
    groups.push({
        section: 'Home',
        items: [
            { label: 'Dashboard', href: base, icon: 'grid', exact: true },
        ],
    });

    // ── Website (conditional) ─────────────────────────────────────────
    if (websiteEnabled && publicWebsiteEnabled && canNav('website') && menuOn('website')) {
        groups.push({
            section: 'Website',
            items: [
                { label: 'Site Builder', href: `${base}/site-builder`, icon: 'layers' },
                { label: 'Domains', href: `${base}/website/domains`, icon: 'globe' },
                { label: 'Microsites', href: `${base}/website/sites`, icon: 'grid' },
                { label: 'Forms', href: `${base}/website/forms`, icon: 'clipboard' },
                { label: 'Content', href: `${base}/public-content`, icon: 'edit' },
                { label: 'Office Bearers', href: `${base}/office-bearers`, icon: 'users' },
                { label: 'Circulars', href: `${base}/circulars`, icon: 'file-text' },
            ],
        });
    }

    // ── Membership ────────────────────────────────────────────────────
    if (canNav('membership') && menuOn('membership')) {
        groups.push({
            section: 'Membership',
            items: [
                { label: 'Schools', href: `${base}/schools`, icon: 'building', badge: approvedSchoolsCount },
                { label: 'Regions', href: `${base}/regions`, icon: 'map-pin' },
                { label: 'Pending applications', href: `${base}/schools/applications`, icon: 'inbox', badge: pendingSchoolsCount, hidden: !pendingSchoolsCount },
                { label: 'Membership fees', href: `${base}/membership/payments`, icon: 'credit-card', badge: pendingPaymentsCount },
                { label: 'Student change requests', href: `${base}/student-change-requests`, icon: 'inbox', badge: pendingChangeRequests },
                { label: 'Registration windows', href: `${base}/students/registration-windows`, icon: 'calendar' },
                { label: 'Profile change requests', href: `${base}/users/profile-change-requests`, icon: 'inbox' },
                { label: 'Student verification', href: `${base}/students/verification${unverifiedStudentsCount ? '?verification=unverified' : ''}`, icon: 'users', badge: unverifiedStudentsCount },
                { label: 'Teacher verification', href: `${base}/teachers/verification`, icon: 'users' },
                { label: 'Unverified students', href: `${base}/students/verification?verification=unverified`, icon: 'users', hidden: true },
                { label: 'Academic Years', href: `${base}/academic-years`, icon: 'calendar' },
                { label: 'Student counts', href: `${base}/membership/submissions`, icon: 'inbox', badge: pendingSubmissionsCount },
                { label: 'Membership reports', href: `${base}/membership/reports`, icon: 'bar-chart' },
                { label: 'Reports hub', href: `${base}/reports/hub`, icon: 'inbox' },
                { label: 'Login audit', href: `${base}/auth/login-audit`, icon: 'shield' },
                { label: 'Document review', href: `${base}/documents/review`, icon: 'file-text' },
                { label: 'Board results', href: `${base}/board-results/verification`, icon: 'bar-chart' },
                { label: 'Board reports', href: `${base}/board-results/reports`, icon: 'file-text' },
                { label: 'Program calendar', href: `${base}/calendar`, icon: 'calendar' },
            ],
        });
    }

    // ── Fest & events ─────────────────────────────────────────────────
    if (canNav('fest')) {
        const festItems = [
            programOn('kalotsav') ? { label: 'Kalotsav', href: `${base}/kalotsav`, icon: 'star' } : null,
            programOn('sports-meet') ? { label: 'Sports Meet', href: `${base}/sports`, icon: 'award' } : null,
            programOn('kids-fest') ? { label: 'Kids Fest', href: `${base}/kids-fest`, icon: 'users' } : null,
            programOn('teacher-fest') ? { label: 'Teacher Fest', href: `${base}/teacher-fest`, icon: 'users' } : null,
            programOn('english-fest') ? { label: 'English Fest', href: `${base}/english-fest`, icon: 'file-text' } : null,
            programOn('science-fest') ? { label: 'Science Fest', href: `${base}/science-fest`, icon: 'layers' } : null,
            { label: 'All events', href: `${base}/events`, icon: 'calendar', exact: true },
            { label: 'Competition types', href: `${base}/competition-types`, icon: 'layers' },
            menuOn('fest_appeals') ? { label: 'Appeals queue', href: `${base}/fest/appeals`, icon: 'inbox', badge: pendingFestAppealsCount } : null,
            menuOn('fest_payments') ? { label: 'Fest payments', href: `${base}/fest/payments`, icon: 'credit-card' } : null,
            menuOn('display_screens') ? { label: 'Display screens', href: `${base}/display-screens`, icon: 'monitor' } : null,
            menuOn('certificate_templates') ? { label: 'Certificate templates', href: `${base}/certificate-templates`, icon: 'award' } : null,
            { label: 'Find certificate', href: `${base}/events/certificates/search`, icon: 'file-text' },
            programOn('custom') ? { label: 'Custom events', href: `${base}/programs/custom`, icon: 'layers', hidden: true } : null,
        ].filter(Boolean);

        // Dynamic competition types (non-system) from Inertia shared props.
        Object.values(competitionPrograms || {}).forEach((p) => {
            if (!p?.slug || p.is_system || p.slug === 'custom') return;
            const insertAt = festItems.findIndex((i) => i.label === 'All events');
            festItems.splice(insertAt >= 0 ? insertAt : festItems.length, 0, {
                label: p.label,
                href: `${base}/${p.prefix || `programs/${p.slug}`}`,
                icon: p.icon || 'calendar',
            });
        });

        if (festItems.length) {
            groups.push({
                section: 'Fest & events',
                items: festItems,
            });
        }
    }

    // ── Exams & training ──────────────────────────────────────────────
    const examItems = [];
    if (canNav('mcq') && menuOn('mcq')) {
        examItems.push({ label: 'Talent Search exams', href: `${base}/mcq`, icon: 'clipboard' });
        examItems.push({ label: 'Talent Search payments', href: `${base}/mcq/payments`, icon: 'credit-card' });
        examItems.push({ label: 'Talent Search question banks', href: `${base}/mcq/question-banks`, icon: 'book-open', hidden: true });
        examItems.push({ label: 'Talent Search series', href: `${base}/mcq-series`, icon: 'layers', hidden: true });
        examItems.push({ label: 'All Talent Search exams', href: `${base}/mcq-exams`, icon: 'clipboard', hidden: true });
    }
    if (canNav('training') && menuOn('training')) {
        examItems.push({ label: 'Training programs', href: `${base}/training`, icon: 'users' });
    }
    if (examItems.length) {
        groups.push({ section: 'Exams & training', items: examItems });
    }

    if (canNav('ledger') && menuOn('finance')) {
        groups.push({
            section: 'Finance',
            items: [
                { label: 'Finance hub', href: `${base}/finance`, icon: 'credit-card' },
                { label: 'Unified payments', href: `${base}/finance/payments`, icon: 'credit-card' },
                { label: 'Receipt emails', href: `${base}/finance/receipt-emails`, icon: 'file-text' },
                { label: 'Email delivery log', href: `${base}/finance/email-delivery`, icon: 'file-text' },
                { label: 'Accounts ledger', href: `${base}/ledger`, icon: 'layers' },
                { label: 'Payables', href: `${base}/finance/payables`, icon: 'credit-card' },
                { label: 'Receivables', href: `${base}/finance/receivables`, icon: 'bar-chart' },
                { label: 'Opening balances', href: `${base}/ledger/opening-balances`, icon: 'credit-card' },
            ],
        });
    }

    // ── Settings (lower visual weight — config + admin) ───────────────
    if (canNav('users') || canNav('membership')) {
        const settingsItems = [];
        if (canNav('membership')) {
            settingsItems.push({
                label: 'Configuration',
                href: `${base}/membership/settings`,
                icon: 'settings',
                badge: setupIncompleteCount,
            });
            settingsItems.push({ label: 'Document types', href: `${base}/documents/types`, icon: 'file-text' });
            settingsItems.push({ label: 'Migrate to S3', href: `${base}/settings/storage-migration`, icon: 'cloud' });
            settingsItems.push({ label: 'Setup wizard', href: `${base}/setup`, icon: 'layers', hidden: !setupIncompleteCount });
            if (stateRemittancesEnabled) {
                settingsItems.push({ label: 'State remittances', href: `${base}/state-remittances`, icon: 'credit-card' });
            }
        }
        if (canNav('users')) {
            settingsItems.push({ label: 'Sidebar visibility', href: `${base}/settings/nav-visibility`, icon: 'layers' });
            settingsItems.push({ label: 'Portal users', href: `${base}/users`, icon: 'users' });
            if (websiteEnabled && publicWebsiteEnabled) {
                settingsItems.push({ label: 'Notification templates', href: `${base}/notification-templates`, icon: 'file-text' });
            }
        }
        if (websiteEnabled && !publicWebsiteEnabled && canNav('website')) {
            settingsItems.push({ label: 'Portal landing', href: `${base}/public-content`, icon: 'globe' });
        }
        if (settingsItems.length) {
            groups.push({ section: 'Settings', items: settingsItems });
        }
    }

    return groups;
}

/** Resolve active state for admin nav href. */
export function adminNavItemActive(pageUrl, href, exact = false) {
    const pageHash = pageUrl.includes('#') ? pageUrl.split('#')[1]?.split('?')[0] ?? '' : '';
    const hrefHash = href.includes('#') ? href.split('#')[1]?.split('?')[0] ?? '' : '';
    const path = pageUrl.split('#')[0].split('?')[0];
    const target = href.split('#')[0].split('?')[0];

    if (hrefHash) {
        const pathMatches = exact
            ? (path === target || path === `${target}/`)
            : (path === target || path.startsWith(`${target}/`));

        return pathMatches && pageHash === hrefHash;
    }

    if (pageHash && (path === target || path.startsWith(`${target}/`))) {
        return false;
    }

    if (exact) {
        return path === target || path === `${target}/`;
    }

    return path === target || path.startsWith(`${target}/`);
}
