/**
 * Sahodaya admin sidebar navigation (data-driven for menu search).
 */

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

function mcqHubItems(base, { trimForHub = false } = {}) {
    return [
        { label: 'MCQ dashboard', href: `${base}/mcq`, icon: 'grid' },
        { label: 'All exams', href: `${base}/mcq-exams`, icon: 'clipboard', hidden: trimForHub },
        { label: 'Exam series', href: `${base}/mcq-series`, icon: 'layers', hidden: trimForHub },
        { label: 'Payments queue', href: `${base}/mcq/payments`, icon: 'credit-card', hidden: trimForHub },
        { label: 'Question banks', href: `${base}/mcq/question-banks`, icon: 'layers', hidden: trimForHub },
    ];
}

/** Sidebar when browsing MCQ hub pages (dashboard, list, series index). */
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
            section: 'MCQ exams',
            items: mcqHubItems(base, { trimForHub: true }),
        },
    ];
}

/** Sidebar when managing a single MCQ exam workspace. */
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
                { label: 'Hall tickets', href: `${examBase}/hall-tickets`, icon: 'clipboard' },
                { label: 'Attendance', href: `${examBase}/attendance`, icon: 'users' },
                { label: 'Results', href: `${examBase}/results`, icon: 'bar-chart' },
                { label: 'Reports', href: `${examBase}/reports`, icon: 'inbox' },
                // Hidden but searchable:
                { label: 'Question banks', href: `${examBase}/question-banks`, icon: 'layers', hidden: true },
                { label: 'Leaderboard', href: `${examBase}/leaderboard`, icon: 'star', hidden: true },
                { label: 'Live session', href: `${examBase}/session`, icon: 'monitor', hidden: true },
                { label: 'Exam staff', href: `${examBase}/staff`, icon: 'users', hidden: true },
                { label: 'Activity log', href: `${examBase}/activity`, icon: 'inbox', hidden: true },
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
            section: 'MCQ exams',
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
        approvedSchoolsCount = 0,
        pendingPaymentsCount = 0,
        pendingSubmissionsCount = 0,
        pendingSchoolsCount = 0,
        pendingChangeRequests = 0,
        setupIncompleteCount = 0,
        stateRemittancesEnabled = true,
    } = options;

    const base = `/sahodaya-admin/${sahodayaId}`;
    const groups = [];

    // ── Home ─────────────────────────────────────────────────────────
    groups.push({
        section: 'Home',
        items: [
            { label: 'Dashboard', href: base, icon: 'grid', exact: true },
        ],
    });

    // ── Website (conditional) ─────────────────────────────────────────
    if (websiteEnabled && canNav('website')) {
        groups.push({
            section: 'Website',
            items: [
                { label: 'Site Builder', href: `${base}/site-builder`, icon: 'layers' },
                { label: 'Content', href: `${base}/public-content`, icon: 'edit' },
                { label: 'Office Bearers', href: `${base}/office-bearers`, icon: 'users' },
                { label: 'Circulars', href: `${base}/circulars`, icon: 'file-text' },
            ],
        });
    }

    // ── Membership ────────────────────────────────────────────────────
    if (canNav('membership')) {
        groups.push({
            section: 'Membership',
            items: [
                { label: 'Schools', href: `${base}/schools`, icon: 'building', badge: approvedSchoolsCount },
                { label: 'Pending applications', href: `${base}/schools/applications`, icon: 'inbox', badge: pendingSchoolsCount, hidden: !pendingSchoolsCount },
                { label: 'Membership fees', href: `${base}/membership/payments`, icon: 'credit-card', badge: pendingPaymentsCount },
                { label: 'Student change requests', href: `${base}/student-change-requests`, icon: 'inbox', badge: pendingChangeRequests },
                { label: 'Academic Years', href: `${base}/academic-years`, icon: 'calendar' },
                { label: 'Student counts', href: `${base}/membership/submissions`, icon: 'inbox', badge: pendingSubmissionsCount },
                { label: 'Membership reports', href: `${base}/membership/reports`, icon: 'bar-chart' },
            ],
        });
    }

    // ── Fest & events ─────────────────────────────────────────────────
    if (canNav('fest')) {
        groups.push({
            section: 'Fest & events',
            items: [
                { label: 'Kalotsav', href: `${base}/kalotsav`, icon: 'star' },
                { label: 'Sports Meet', href: `${base}/sports`, icon: 'award' },
                { label: 'Kids Fest', href: `${base}/kids-fest`, icon: 'users' },
                { label: 'Teacher Fest', href: `${base}/teacher-fest`, icon: 'users' },
                { label: 'All events', href: `${base}/events`, icon: 'calendar', exact: true },
                { label: 'Find certificate', href: `${base}/events/certificates/search`, icon: 'file-text', hidden: true },
                // Hidden — accessible from event pages; still searchable
                { label: 'Custom events', href: `${base}/programs/custom`, icon: 'layers', hidden: true },
                { label: 'Fest payments queue', href: `${base}/fest/payments`, icon: 'credit-card', hidden: true },
                { label: 'Display screens', href: `${base}/display-screens`, icon: 'monitor', hidden: true },
                { label: 'Certificate templates', href: `${base}/certificate-templates`, icon: 'award', hidden: true },
            ],
        });
    }

    // ── Exams & training ──────────────────────────────────────────────
    const examItems = [];
    if (canNav('mcq')) {
        examItems.push(
            { label: 'MCQ exams', href: `${base}/mcq`, icon: 'clipboard' },
            // Hidden — accessible from MCQ hub/exam workspace
            { label: 'All exams', href: `${base}/mcq-exams`, icon: 'clipboard', hidden: true },
            { label: 'Exam series', href: `${base}/mcq-series`, icon: 'layers', hidden: true },
            { label: 'MCQ payments', href: `${base}/mcq/payments`, icon: 'credit-card', hidden: true },
            { label: 'Question banks', href: `${base}/mcq/question-banks`, icon: 'layers', hidden: true },
        );
    }
    if (canNav('training')) {
        examItems.push({ label: 'Training programs', href: `${base}/training`, icon: 'users' });
    }
    if (canNav('ledger')) {
        examItems.push({ label: 'Exam ledger', href: `${base}/ledger`, icon: 'credit-card' });
    }
    if (examItems.length) {
        groups.push({ section: 'Exams & training', items: examItems });
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
            settingsItems.push({ label: 'Setup wizard', href: `${base}/setup`, icon: 'layers', hidden: !setupIncompleteCount });
            if (stateRemittancesEnabled) {
                settingsItems.push({ label: 'State remittances', href: `${base}/state-remittances`, icon: 'credit-card' });
            }
        }
        if (canNav('users')) {
            settingsItems.push({ label: 'Portal users', href: `${base}/users`, icon: 'users' });
            settingsItems.push({ label: 'Notification templates', href: `${base}/notification-templates`, icon: 'file-text' });
        }
        if (settingsItems.length) {
            groups.push({ section: 'Settings', items: settingsItems });
        }
    }

    return groups;
}

/** Resolve active state for admin nav href. */
export function adminNavItemActive(pageUrl, href, exact = false) {
    const path = pageUrl.split('?')[0];
    const target = href.split('?')[0];

    if (exact) {
        return path === target || path === `${target}/`;
    }

    return path === target || path.startsWith(`${target}/`);
}
