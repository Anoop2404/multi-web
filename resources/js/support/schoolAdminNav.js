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
import { isNavMenuVisible, isNavProgramVisible } from './sahodayaAdminNav.js';
import { TALENT_SEARCH_EXAMS_LABEL, TALENT_SEARCH_LABEL } from './mcqSchoolLabels.js';

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
        items.push({ label: TALENT_SEARCH_EXAMS_LABEL, href: schoolAdminHref(schoolId, 'mcq'), icon: 'book-open' });
    }
    if (canNav('training')) {
        items.push({ label: 'Teacher training', href: schoolAdminHref(schoolId, 'training'), icon: 'award' });
    }

    return items;
}

/** @returns {boolean} */
export function detectSchoolMembershipFromUrl(url) {
    const path = (url ?? '').split('?')[0];

    if (/\/school-admin\/[^/]+\/registration(?:\/|$)/.test(path)) {
        return true;
    }
    if (/\/school-admin\/[^/]+\/payments(?:\/|$)/.test(path) && !path.includes('/mcq')) {
        return true;
    }
    if (/\/school-admin\/[^/]+\/documents(?:\/|$)/.test(path)) {
        return true;
    }

    return /\/school-admin\/[^/]+\/calendar(?:\/|$)/.test(path);
}

/** @returns {boolean} */
export function detectSchoolTrainingFromUrl(url) {
    const path = (url ?? '').split('?')[0];

    return /\/school-admin\/[^/]+\/training(?:\/|$)/.test(path);
}

/** Sidebar when managing annual membership / registration. */
export function schoolMembershipScopedNav(schoolId, options = {}) {
    const { canNav = () => true } = options;
    const base = schoolAdminHref(schoolId);

    if (!canNav('membership')) {
        return [];
    }

    return [
        {
            section: 'School home',
            items: [{ label: 'Dashboard', href: base, icon: 'grid', exact: true }],
        },
        {
            section: 'Membership',
            items: [
                { label: 'Annual registration', href: schoolAdminHref(schoolId, 'registration'), icon: 'clipboard', exact: true },
                { label: 'Profile & account', href: schoolAdminHref(schoolId, 'registration', 'profile'), icon: 'user' },
                { label: 'Student records', href: schoolAdminHref(schoolId, 'registration', 'students'), icon: 'users' },
                { label: 'Student counts', href: schoolAdminHref(schoolId, 'registration', 'counts'), icon: 'layers' },
                { label: 'Teacher records', href: schoolAdminHref(schoolId, 'registration', 'teachers'), icon: 'user-check' },
                { label: 'Membership payment', href: schoolAdminHref(schoolId, 'registration', 'payment'), icon: 'credit-card' },
                { label: 'Payments & receipts', href: schoolAdminHref(schoolId, 'payments'), icon: 'inbox' },
                { label: 'Compliance documents', href: schoolAdminHref(schoolId, 'documents'), icon: 'file-text' },
                { label: 'Program calendar', href: schoolAdminHref(schoolId, 'calendar'), icon: 'calendar' },
            ],
        },
    ];
}

/** Sidebar on teacher training hub (/training). */
export function schoolTrainingHubNav(schoolId, options = {}) {
    const { canNav = () => true } = options;
    const base = schoolAdminHref(schoolId);

    if (!canNav('training')) {
        return [];
    }

    return [
        {
            section: 'School home',
            items: [{ label: 'Dashboard', href: base, icon: 'grid', exact: true }],
        },
        {
            section: 'Teacher training',
            items: [
                { label: 'Available programs', href: schoolAdminHref(schoolId, 'training'), icon: 'award', exact: true },
            ],
        },
    ];
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
            section: TALENT_SEARCH_LABEL,
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
            section: TALENT_SEARCH_LABEL,
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

function programLinks(schoolId, excludeSlug = null, navVisibility = null) {
    return SCHOOL_FEST_PROGRAMS
        .filter((p) => p.slug !== excludeSlug && isNavProgramVisible(navVisibility, p.slug))
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
        { label: 'Meal requests', href: schoolAdminHref(schoolId, 'fest', 'hub'), icon: 'coffee' },
        { label: 'Food Coupons', href: schoolAdminHref(schoolId, 'food-coupons'), icon: 'clipboard' },
        { label: 'Circulars', href: schoolAdminHref(schoolId, 'circulars'), icon: 'file-text' },
        { label: 'Notifications', href: schoolAdminHref(schoolId, 'notifications'), icon: 'bell' },
    ];
}

/** Sidebar when viewing a fest program (Kalotsav, Sports Meet, …). */
export function schoolProgramScopedNav(schoolId, programSlug, options = {}) {
    const { canNav = () => true, coordinatorMode = false } = options;
    const program = schoolProgramBySlug(programSlug);
    const base = schoolAdminHref(schoolId);

    if (!program || !canNav('fest')) {
        return [];
    }

    return [
        {
            section: coordinatorMode ? 'Assigned program' : 'School home',
            items: coordinatorMode
                ? [{ label: '← My assignments', href: base, icon: 'grid', exact: true }]
                : [{ label: 'Dashboard', href: base, icon: 'grid', exact: true }],
        },
        {
            section: program.label,
            items: schoolProgramWorkflowItems(schoolId, programSlug),
        },
    ];
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
        publicWebsiteEnabled = true,
        schoolHasPrefix = true,
        pendingChangeRequests = 0,
        navVisibility = null,
        membershipPaid = true,
    } = options;

    const base = schoolAdminHref(schoolId);
    const groups = [];

    // ── Home ──────────────────────────────────────────────────────────
    groups.push({
        section: 'Home',
        items: [{ label: 'Dashboard', href: base, icon: 'grid', exact: true }],
    });

    // ── School (students + core records) ──────────────────────────────
    if (canNav('students')) {
        const schoolItems = [];
        if (!schoolHasPrefix) {
            schoolItems.push({ label: 'Set school code', href: schoolAdminHref(schoolId, 'setup', 'code'), icon: 'alert-circle' });
        }
        schoolItems.push(
            { label: 'Students', href: schoolAdminHref(schoolId, 'students'), icon: 'users', badge: pendingChangeRequests },
            { label: 'Import history', href: schoolAdminHref(schoolId, 'imports'), icon: 'clock' },
            { label: 'Teachers', href: schoolAdminHref(schoolId, 'teachers'), icon: 'user-check' },
            { label: 'School houses', href: schoolAdminHref(schoolId, 'houses'), icon: 'layers' },
            { label: 'Payment history', href: schoolAdminHref(schoolId, 'payments'), icon: 'credit-card' },
            { label: 'Settings', href: schoolAdminHref(schoolId, 'settings'), icon: 'settings' },
        );
        // User management links
        if (canNav('users')) {
            schoolItems.push({ label: 'Profile requests', href: `${schoolAdminHref(schoolId, 'users')}/profile-change-requests`, icon: 'user-check', badge: pendingChangeRequests });
            schoolItems.push({ label: 'Portal users', href: schoolAdminHref(schoolId, 'users'), icon: 'shield', hidden: true });
            schoolItems.push({ label: 'Event coordinators', href: `${schoolAdminHref(schoolId, 'users')}?coordinators=1`, icon: 'users' });
        }
        groups.push({ section: 'School', items: schoolItems });
    }

    // ── Membership ────────────────────────────────────────────────────
    if (canNav('membership')) {
        groups.push({
            section: 'Membership',
            items: [
                { label: 'Annual Registration', href: schoolAdminHref(schoolId, 'registration'), icon: 'clipboard' },
                { label: 'Payments & Receipts', href: schoolAdminHref(schoolId, 'payments'), icon: 'credit-card' },
                { label: 'Compliance documents', href: schoolAdminHref(schoolId, 'documents'), icon: 'file-text' },
                { label: 'Program calendar', href: schoolAdminHref(schoolId, 'calendar'), icon: 'calendar' },
                // Hidden — tab on Annual Registration page
                { label: 'Registration Details', href: schoolAdminHref(schoolId, 'registration', 'profile'), icon: 'user', hidden: true },
            ],
        });
    }

    // Programs (Fest / Talent Search / Training) unlock only after membership payment.
    if (!membershipPaid && (canNav('fest') || canNav('mcq') || canNav('training'))) {
        groups.push({
            section: 'Programs',
            items: [{
                label: 'Complete membership to unlock',
                href: schoolAdminHref(schoolId, 'registration', 'payment'),
                icon: 'lock',
            }],
        });
    }

    // ── Fest ──────────────────────────────────────────────────────────
    if (membershipPaid && canNav('fest')) {
        const festProgramItems = SCHOOL_FEST_PROGRAMS
            .filter((p) => isNavProgramVisible(navVisibility, p.slug))
            .map((p) => ({
                label: p.label,
                href: schoolProgramHref(schoolId, p.slug),
                icon: p.icon,
            }));

        const festItems = [
            ...festProgramItems,
            { label: 'Fest Hub', href: schoolAdminHref(schoolId, 'fest', 'hub'), icon: 'star' },
            { label: 'Reports', href: schoolAdminHref(schoolId, 'fest', 'reports'), icon: 'file-text', exact: true },
            { label: 'School events', href: schoolAdminHref(schoolId, 'fest-programs'), icon: 'calendar' },
            { label: 'Food coupons', href: schoolAdminHref(schoolId, 'food-coupons'), icon: 'clipboard' },
            { label: 'Circulars', href: schoolAdminHref(schoolId, 'circulars'), icon: 'file-text' },
            { label: 'Notifications', href: schoolAdminHref(schoolId, 'notifications'), icon: 'bell' },
        ];

        if (festItems.length) {
            groups.push({ section: 'Fest', items: festItems });
        }
    }

    // ── Exams & training ──────────────────────────────────────────────
    const examItems = [];
    if (membershipPaid && canNav('mcq') && isNavMenuVisible(navVisibility, 'mcq')) {
        examItems.push({ label: TALENT_SEARCH_EXAMS_LABEL, href: schoolAdminHref(schoolId, 'mcq'), icon: 'book-open' });
    }
    if (membershipPaid && canNav('training') && isNavMenuVisible(navVisibility, 'training')) {
        examItems.push({ label: 'Teacher training', href: schoolAdminHref(schoolId, 'training'), icon: 'award' });
    }
    if (examItems.length) {
        groups.push({ section: 'Exams & training', items: examItems });
    }

    // ── Website (collapses to single hub entry) ────────────────────────
    if (websiteEnabled && publicWebsiteEnabled && canNav('website')) {
        groups.push({
            section: 'Website',
            items: [
                { label: 'School Website →', href: `${base}/site-builder`, icon: 'layers' },
                // Hidden — all accessible from site-builder hub; searchable
                { label: 'News', href: `${base}/news`, icon: 'file-text', hidden: true },
                { label: 'Events', href: `${base}/events`, icon: 'calendar', hidden: true },
                { label: 'Gallery', href: `${base}/gallery`, icon: 'image', hidden: true },
                { label: 'Staff', href: `${base}/staff`, icon: 'users', hidden: true },
                { label: 'Achievements', href: `${base}/achievements`, icon: 'star', hidden: true },
                { label: 'Downloads', href: `${base}/downloads`, icon: 'folder', hidden: true },
                { label: 'Job Vacancies', href: `${base}/job-vacancies`, icon: 'briefcase', hidden: true },
                { label: 'Board Results', href: `${base}/board-results`, icon: 'bar-chart', hidden: true },
                { label: 'Alumni', href: `${base}/alumni`, icon: 'award', hidden: true },
                { label: 'Testimonials', href: `${base}/testimonials`, icon: 'star', hidden: true },
                { label: 'Contact Page', href: `${base}/contact`, icon: 'file-text', hidden: true },
                { label: 'Enquiries', href: `${base}/enquiries`, icon: 'inbox', hidden: true },
            ],
        });
    }

    if (websiteEnabled && !publicWebsiteEnabled && canNav('website')) {
        groups.push({
            section: 'Website',
            items: [
                { label: 'Portal settings', href: `${base}/settings`, icon: 'globe' },
            ],
        });
    }

    return groups;
}

const PROGRAM_ICONS = {
    kalotsav: 'star',
    'sports-meet': 'award',
    'kids-fest': 'users',
    'teacher-fest': 'users',
    'english-fest': 'file-text',
    'science-fest': 'layers',
    mcq: 'book-open',
    training: 'award',
};

/** Build href for a coordinator scope row from the API. */
export function schoolCoordinatorScopeHref(schoolId, scope) {
    const slug = scope?.program_slug;
    if (!slug) {
        return schoolAdminHref(schoolId);
    }

    if (slug === 'mcq') {
        if (scope.scope_type === 'mcq_exam' && scope.event_id) {
            return schoolAdminHref(schoolId, 'mcq', scope.event_id, 'register');
        }

        return schoolAdminHref(schoolId, 'mcq');
    }

    if (slug === 'training') {
        return schoolAdminHref(schoolId, 'training');
    }

    const prefix = SLUG_TO_PREFIX[slug] ?? slug;
    if (scope.scope_type === 'fest_event' && scope.event_id) {
        return `${schoolAdminHref(schoolId, prefix)}/events/${scope.event_id}/overview`;
    }

    return `${schoolAdminHref(schoolId, prefix)}/registration`;
}

/** Sidebar for school_event_coordinator — only assigned programs/events. */
export function schoolEventCoordinatorNav(schoolId, eventScopes = []) {
    const scopes = Array.isArray(eventScopes) ? eventScopes : [];

    const items = scopes.map((scope) => ({
        label: scope.label ?? scope.program_slug,
        href: schoolCoordinatorScopeHref(schoolId, scope),
        icon: PROGRAM_ICONS[scope.program_slug] ?? 'calendar',
    }));

    if (!items.length) {
        items.push({
            label: 'No assignments yet',
            href: schoolAdminHref(schoolId),
            icon: 'alert-circle',
        });
    }

    return [
        {
            section: 'My assignments',
            items,
        },
    ];
}

/** Resolve active state for school nav href. */
export function schoolNavItemActive(pageUrl, href, exact = false, matchQuery = null) {
    const pageHash = pageUrl.includes('#') ? pageUrl.split('#')[1]?.split('?')[0] ?? '' : '';
    const hrefHash = href.includes('#') ? href.split('#')[1]?.split('?')[0] ?? '' : '';
    const [path, queryString = ''] = pageUrl.split('#')[0].split('?');
    const [target, targetQuery = ''] = href.split('#')[0].split('?');
    const params = new URLSearchParams(queryString);

    if (hrefHash) {
        const pathMatches = exact
            ? (path === target || path === `${target}/`)
            : (path === target || path.startsWith(`${target}/`));

        return pathMatches && pageHash === hrefHash;
    }

    if (pageHash && (path === target || path.startsWith(`${target}/`))) {
        return false;
    }

    if (matchQuery) {
        const pathMatches = exact
            ? (path === target || path === `${target}/`)
            : (path === target || path.startsWith(`${target}/`));

        if (!pathMatches) {
            return false;
        }

        for (const [key, expected] of Object.entries(matchQuery)) {
            const actual = params.get(key) ?? '';
            if (expected === '' || expected == null) {
                if (actual !== '') {
                    return false;
                }
            } else if (String(actual) !== String(expected)) {
                return false;
            }
        }

        return true;
    }

    if (exact) {
        return path === target || path === `${target}/`;
    }

    if (path === target || path.startsWith(`${target}/`)) {
        return true;
    }

    if (target.endsWith('/item-registration') && path.includes('/events/') && path.includes('/items')) {
        return true;
    }

    // Highlight program entry when anywhere in that program's routes.
    const dedicatedMatch = target.match(/\/(kalotsav|sports|kids-fest|teacher-fest|english-fest|science-fest)(?:\/|$)/);
    if (dedicatedMatch) {
        const prefix = dedicatedMatch[1];
        return path.includes(`/${prefix}/`) || path.endsWith(`/${prefix}`);
    }

    const programMatch = target.match(/\/programs\/(kalotsav|sports-meet|kids-fest|teacher-fest|english-fest|science-fest)\//);
    if (programMatch) {
        const slug = programMatch[1];
        return path.includes(`/programs/${slug}/`) || path.includes(`/${SLUG_TO_PREFIX?.[slug] ?? slug}/`);
    }

    return false;
}
