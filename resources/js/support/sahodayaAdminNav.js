/**
 * Sahodaya admin sidebar navigation (data-driven for menu search).
 */

import { PROGRAM_SLUGS, SAHODAYA_PROGRAMS, sahodayaProgramHref } from './sahodayaPrograms.js';

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

function mcqHubItems(base) {
    return [
        { label: 'MCQ dashboard', href: `${base}/mcq`, icon: 'grid' },
        { label: 'All exams', href: `${base}/mcq-exams`, icon: 'clipboard' },
        { label: 'Exam series', href: `${base}/mcq-series`, icon: 'layers' },
        { label: 'Payments queue', href: `${base}/mcq/payments`, icon: 'credit-card' },
        { label: 'Question banks', href: `${base}/mcq/question-banks`, icon: 'layers' },
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
            items: mcqHubItems(base),
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
            items: [{ label: 'Main dashboard', href: base, icon: 'grid', exact: true }],
        },
        {
            section: 'MCQ exams',
            items: mcqHubItems(base),
        },
        {
            section: 'This exam',
            items: [
                { label: 'Overview', href: examBase, icon: 'file-text' },
                { label: 'Payments', href: `${examBase}/payments`, icon: 'credit-card' },
                { label: 'Question banks', href: `${examBase}/question-banks`, icon: 'layers' },
                { label: 'Hall tickets', href: `${examBase}/hall-tickets`, icon: 'clipboard' },
                { label: 'Attendance', href: `${examBase}/attendance`, icon: 'users' },
                { label: 'Results & marks', href: `${examBase}/results`, icon: 'bar-chart' },
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
    } = options;

    const base = `/sahodaya-admin/${sahodayaId}`;
    const groups = [];

    groups.push({
        section: 'Home',
        items: [
            { label: 'Dashboard', href: base, icon: 'grid', exact: true },
        ],
    });

    if (websiteEnabled && canNav('website')) {
        groups.push({
            section: 'Website',
            items: [
                { label: 'Sahodaya Site Builder', href: `${base}/site-builder`, icon: 'layers' },
                { label: 'Website Content', href: `${base}/public-content`, icon: 'edit' },
                { label: 'Office Bearers', href: `${base}/office-bearers`, icon: 'users' },
                { label: 'Circulars', href: `${base}/circulars`, icon: 'file-text' },
            ],
        });
    }

    if (canNav('membership')) {
        groups.push({
            section: 'Membership',
            items: [
                { label: 'Academic Years', href: `${base}/academic-years`, icon: 'calendar' },
                { label: 'Configuration', href: `${base}/membership/settings`, icon: 'settings' },
                { label: 'Schools', href: `${base}/schools`, icon: 'building', badge: approvedSchoolsCount },
                { label: 'Student change requests', href: `${base}/student-change-requests`, icon: 'inbox' },
                { label: 'Student Counts', href: `${base}/membership/submissions`, icon: 'inbox' },
                { label: 'Membership fees', href: `${base}/membership/payments`, icon: 'credit-card', badge: pendingPaymentsCount },
                { label: 'Reports', href: `${base}/membership/reports`, icon: 'bar-chart' },
            ],
        });
    }

    if (canNav('users')) {
        groups.push({
            section: 'Administration',
            items: [
                { label: 'Portal users', href: `${base}/users`, icon: 'users' },
                { label: 'Notification templates', href: `${base}/notification-templates`, icon: 'file-text' },
            ],
        });
    }

    if (canNav('fest')) {
        const programItems = PROGRAM_SLUGS.filter((slug) => slug !== 'custom').map((slug) => {
            const p = SAHODAYA_PROGRAMS[slug];
            return { label: p.label, href: sahodayaProgramHref(sahodayaId, slug), icon: p.icon };
        });

        programItems.push(
            { label: 'Custom events', href: sahodayaProgramHref(sahodayaId, 'custom'), icon: 'layers' },
            { label: 'All events', href: `${base}/events`, icon: 'calendar', exact: true },
        );

        groups.push({
            section: 'Fest programs',
            items: programItems,
        });

        groups.push({
            section: 'Fest tools',
            items: [
                { label: 'Fest payments queue', href: `${base}/fest/payments`, icon: 'credit-card' },
                { label: 'Display screens', href: `${base}/display-screens`, icon: 'layers' },
                { label: 'Certificate templates', href: `${base}/certificate-templates`, icon: 'award' },
                { label: 'Certificate search', href: `${base}/events/certificates/search`, icon: 'file-text' },
                { label: 'State remittances', href: `${base}/state-remittances`, icon: 'credit-card' },
            ],
        });
    }

    const examItems = [];
    if (canNav('mcq')) {
        examItems.push(...mcqHubItems(base));
    }
    if (canNav('training')) {
        examItems.push({ label: 'Training programs', href: `${base}/training`, icon: 'users' });
    }
    if (canNav('ledger')) {
        examItems.push({ label: 'Ledger', href: `${base}/ledger`, icon: 'credit-card' });
    }
    if (examItems.length) {
        groups.push({ section: 'Exams & training', items: examItems });
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
