/**
 * School admin sidebar navigation — main hub and program-scoped menus.
 */

import {
    SCHOOL_FEST_PROGRAMS,
    detectSchoolProgramFromUrl,
    schoolAdminHref,
    schoolProgramBySlug,
    schoolProgramHref,
    schoolProgramWorkflowItems,
} from './schoolProgramNav.js';

const SLUG_TO_PREFIX = Object.fromEntries(SCHOOL_FEST_PROGRAMS.map((p) => [p.slug, p.prefix]));

export { detectSchoolProgramFromUrl, SCHOOL_FEST_PROGRAMS };

/** @returns {string|null} */
export function detectSchoolMcqExamIdFromUrl(url) {
    const match = (url ?? '').split('?')[0].match(/\/mcq\/(\d+)/);
    return match ? match[1] : null;
}

/** @returns {boolean} */
export function detectSchoolMcqHubFromUrl(url) {
    const path = (url ?? '').split('?')[0];
    if (detectSchoolMcqExamIdFromUrl(path)) {
        return false;
    }

    return /\/mcq(?:\/|$)/.test(path);
}

function examsTrainingItems(schoolId, canNav) {
    const items = [];
    if (canNav('mcq')) {
        items.push({ label: 'MCQ exams', href: schoolAdminHref(schoolId, 'mcq'), icon: 'clipboard' });
    }
    if (canNav('training')) {
        items.push({ label: 'Teacher training', href: schoolAdminHref(schoolId, 'training'), icon: 'award' });
    }

    return items;
}

/** Sidebar on MCQ hub (/mcq). */
export function schoolMcqHubNav(schoolId, options = {}) {
    const { canNav = () => true } = options;
    const base = schoolAdminHref(schoolId);

    return [
        {
            section: 'School home',
            items: [{ label: 'Dashboard', href: base, icon: 'grid', exact: true }],
        },
        {
            section: 'MCQ exams',
            items: [{ label: 'Available exams', href: schoolAdminHref(schoolId, 'mcq'), icon: 'clipboard' }],
        },
    ];
}

/** Sidebar when managing one MCQ exam. */
export function schoolMcqExamScopedNav(schoolId, examId, options = {}) {
    const { canNav = () => true, resultsPublished = false } = options;
    const base = schoolAdminHref(schoolId);
    const examBase = `${base}/mcq/${examId}`;

    const examItems = [
        { label: 'Register students', href: `${examBase}/register`, icon: 'clipboard' },
        { label: 'Registered students', href: `${examBase}/students`, icon: 'users' },
        { label: 'Hall tickets', href: `${examBase}/hall-tickets`, icon: 'file-text' },
        { label: 'Fee & payment', href: `${examBase}/fee`, icon: 'credit-card' },
        { label: 'Reports', href: `${examBase}/reports`, icon: 'inbox' },
    ];

    if (resultsPublished) {
        examItems.push(
            { label: 'Results', href: `${examBase}/results`, icon: 'bar-chart' },
            { label: 'Toppers', href: `${examBase}/toppers`, icon: 'star' },
        );
    }

    return [
        {
            section: 'School home',
            items: [{ label: 'Dashboard', href: base, icon: 'grid', exact: true }],
        },
        {
            section: 'MCQ exams',
            items: [{ label: 'Available exams', href: schoolAdminHref(schoolId, 'mcq'), icon: 'clipboard' }],
        },
        {
            section: 'This exam',
            items: examItems,
        },
    ];
}

/** @returns {string|null} */
export function detectSchoolFestContextFromUrl(url) {
    const path = (url ?? '').split('?')[0];
    if (path.includes('/fest/reports')) {
        return null;
    }
    if (/\/fest\/hub(?:\/|$)/.test(path) || /\/fest\/[^/]+(?:\/|$)/.test(path)) {
        return 'fest';
    }

    return null;
}

function programLinks(schoolId, excludeSlug = null) {
    return SCHOOL_FEST_PROGRAMS
        .filter((p) => p.slug !== excludeSlug)
        .map((p) => ({
            label: p.label,
            href: schoolProgramHref(schoolId, p.slug),
            icon: p.icon,
        }));
}

function festToolItems(schoolId) {
    return [
        { label: 'Fest Hub', href: schoolAdminHref(schoolId, 'fest', 'hub'), icon: 'star' },
        { label: 'All fest reports', href: schoolAdminHref(schoolId, 'fest', 'reports'), icon: 'file-text', exact: true },
        { label: 'School Events', href: schoolAdminHref(schoolId, 'fest-programs'), icon: 'calendar' },
        { label: 'Food Coupons', href: schoolAdminHref(schoolId, 'food-coupons'), icon: 'clipboard' },
        { label: 'Circulars', href: schoolAdminHref(schoolId, 'circulars'), icon: 'file-text' },
        { label: 'Notifications', href: schoolAdminHref(schoolId, 'notifications'), icon: 'bell' },
    ];
}

/** Sidebar when viewing a fest program (Kalotsav, Sports Meet, …). */
export function schoolProgramScopedNav(schoolId, programSlug, options = {}) {
    const { canNav = () => true } = options;
    const program = schoolProgramBySlug(programSlug);
    const base = schoolAdminHref(schoolId);

    if (!program || !canNav('fest')) {
        return [];
    }

    const groups = [
        {
            section: 'School home',
            items: [
                { label: 'Dashboard', href: base, icon: 'grid', exact: true },
            ],
        },
        {
            section: program.label,
            items: schoolProgramWorkflowItems(schoolId, programSlug),
        },
    ];

    const otherPrograms = programLinks(schoolId, programSlug);
    if (otherPrograms.length) {
        groups.push({ section: 'Other programs', items: otherPrograms });
    }

    groups.push({ section: 'Fest & tools', items: festToolItems(schoolId) });

    if (canNav('mcq') || canNav('training')) {
        const items = examsTrainingItems(schoolId, canNav);
        if (items.length) {
            groups.push({ section: 'Exams & training', items });
        }
    }

    return groups;
}

/** Sidebar when viewing fest hub or a specific fest event page. */
export function schoolFestScopedNav(schoolId, options = {}) {
    const { canNav = () => true } = options;
    const base = schoolAdminHref(schoolId);
    const groups = [
        {
            section: 'Home',
            items: [{ label: 'Dashboard', href: base, icon: 'grid', exact: true }],
        },
    ];

    if (canNav('fest')) {
        groups.push(
            { section: 'Fest', items: [{ label: 'Fest Hub', href: schoolAdminHref(schoolId, 'fest', 'hub'), icon: 'star' }] },
            { section: 'Programs', items: programLinks(schoolId).map((item) => ({ ...item })) },
            { section: 'Fest & tools', items: festToolItems(schoolId).filter((item) => item.label !== 'Fest Hub') },
        );
    }

    return groups;
}

/** Main school admin sidebar (dashboard, students, membership, program list). */
export function schoolAdminNav(schoolId, options = {}) {
    const {
        canNav = () => true,
        websiteEnabled = false,
        schoolHasPrefix = true,
    } = options;

    const base = schoolAdminHref(schoolId);
    const groups = [];

    groups.push({
        section: 'Home',
        items: [{ label: 'Dashboard', href: base, icon: 'grid', exact: true }],
    });

    if (canNav('students')) {
        const items = [];
        if (!schoolHasPrefix) {
            items.push({ label: 'School Code', href: schoolAdminHref(schoolId, 'setup', 'code'), icon: 'hash' });
        }
        items.push(
            { label: 'Students', href: schoolAdminHref(schoolId, 'students'), icon: 'users' },
            { label: 'School houses', href: schoolAdminHref(schoolId, 'houses'), icon: 'award' },
            { label: 'Teachers', href: schoolAdminHref(schoolId, 'teachers'), icon: 'users' },
        );
        if (canNav('users')) {
            items.push({ label: 'Portal users', href: schoolAdminHref(schoolId, 'users'), icon: 'users' });
        }
        groups.push({ section: 'Students', items });
    } else if (canNav('users')) {
        groups.push({
            section: 'Administration',
            items: [{ label: 'Portal users', href: schoolAdminHref(schoolId, 'users'), icon: 'users' }],
        });
    }

    if (canNav('membership')) {
        groups.push({
            section: 'Membership',
            items: [
                { label: 'Registration Details', href: schoolAdminHref(schoolId, 'registration', 'profile'), icon: 'user' },
                { label: 'Annual Registration', href: schoolAdminHref(schoolId, 'registration'), icon: 'clipboard' },
                { label: 'Payments & Receipts', href: schoolAdminHref(schoolId, 'payments'), icon: 'credit-card' },
            ],
        });
    }

    if (canNav('fest')) {
        groups.push({
            section: 'Fest programs',
            items: [
                ...SCHOOL_FEST_PROGRAMS.map((p) => ({
                    label: p.label,
                    href: schoolProgramHref(schoolId, p.slug),
                    icon: p.icon,
                })),
                { label: 'School events', href: schoolAdminHref(schoolId, 'fest-programs'), icon: 'calendar' },
            ],
        });

        groups.push({
            section: 'Fest tools',
            items: festToolItems(schoolId),
        });
    }
    if (canNav('mcq') || canNav('training')) {
        const items = examsTrainingItems(schoolId, canNav);
        if (items.length) {
            groups.push({ section: 'Exams & training', items });
        }
    }

    if (websiteEnabled && canNav('website')) {
        groups.push({
            section: 'Website',
            items: [
                { label: 'Site Builder', href: `${base}/site-builder`, icon: 'layers' },
                { label: 'News', href: `${base}/news`, icon: 'file-text' },
                { label: 'Events', href: `${base}/events`, icon: 'calendar' },
                { label: 'Gallery', href: `${base}/gallery`, icon: 'image' },
                { label: 'Staff', href: `${base}/staff`, icon: 'users' },
                { label: 'Achievements', href: `${base}/achievements`, icon: 'star' },
                { label: 'Downloads', href: `${base}/downloads`, icon: 'folder' },
                { label: 'Job Vacancies', href: `${base}/job-vacancies`, icon: 'briefcase' },
                { label: 'Board Results', href: `${base}/board-results`, icon: 'bar-chart' },
                { label: 'Alumni', href: `${base}/alumni`, icon: 'award' },
                { label: 'Testimonials', href: `${base}/testimonials`, icon: 'star' },
                { label: 'Contact Page', href: `${base}/contact`, icon: 'file-text' },
            ],
        });

        groups.push({
            section: 'Admissions',
            items: [
                { label: 'Enquiries', href: `${base}/enquiries`, icon: 'inbox' },
                { label: 'TC Requests', href: `${base}/tc-requests`, icon: 'file-text' },
            ],
        });

        groups.push({
            section: 'School',
            items: [{ label: 'Settings', href: `${base}/settings`, icon: 'settings' }],
        });
    }

    return groups;
}

/** Resolve active state for school nav href. */
export function schoolNavItemActive(pageUrl, href, exact = false) {
    const path = pageUrl.split('?')[0];
    const target = href.split('?')[0];

    if (exact) {
        return path === target || path === `${target}/`;
    }

    if (path === target || path.startsWith(`${target}/`)) {
        return true;
    }

    // Highlight program entry when anywhere in that program's routes.
    const dedicatedMatch = target.match(/\/(kalotsav|sports|kids-fest|teacher-fest)(?:\/|$)/);
    if (dedicatedMatch) {
        const prefix = dedicatedMatch[1];
        return path.includes(`/${prefix}/`) || path.endsWith(`/${prefix}`);
    }

    const programMatch = target.match(/\/programs\/(kalotsav|sports-meet|kids-fest|teacher-fest)\//);
    if (programMatch) {
        const slug = programMatch[1];
        return path.includes(`/programs/${slug}/`) || path.includes(`/${SLUG_TO_PREFIX?.[slug] ?? slug}/`);
    }

    return false;
}
