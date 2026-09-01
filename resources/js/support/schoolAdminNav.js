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
    const { canNav = () => true, navVisibility = null } = options;
    const base = schoolAdminHref(schoolId);

    if (!canNav('membership')) {
        return [];
    }

    const membershipItems = [
        { label: 'Annual registration', href: schoolAdminHref(schoolId, 'registration'), icon: 'clipboard', exact: true },
        { label: 'Profile & account', href: schoolAdminHref(schoolId, 'registration', 'profile'), icon: 'user' },
        { label: 'Student records', href: schoolAdminHref(schoolId, 'registration', 'students'), icon: 'users' },
        { label: 'Student counts', href: schoolAdminHref(schoolId, 'registration', 'counts'), icon: 'layers' },
        { label: 'Teacher records', href: schoolAdminHref(schoolId, 'registration', 'teachers'), icon: 'user-check' },
        { label: 'Membership payment', href: schoolAdminHref(schoolId, 'registration', 'payment'), icon: 'credit-card' },
        { label: 'Payments & receipts', href: schoolAdminHref(schoolId, 'payments'), icon: 'inbox' },
    ];

    if (isNavMenuVisible(navVisibility, 'documents')) {
        membershipItems.push({ label: 'Compliance documents', href: schoolAdminHref(schoolId, 'documents'), icon: 'file-text' });
    }

    membershipItems.push({ label: 'Program calendar', href: schoolAdminHref(schoolId, 'calendar'), icon: 'calendar' });

    return [
        {
            section: 'School home',
            items: [{ label: 'Dashboard', href: base, icon: 'grid', exact: true }],
        },
        {
            section: 'Membership',
            items: membershipItems,
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
        // "Meal requests" (Catering) has no standalone school-wide page — it's
        // per-event only (routes/web.php: /fest/{event}/catering) — so it's
        // reached via a card inside Fest Hub, not a separate sidebar link
        // that would otherwise just duplicate this href. See
        // SCHOOL_ADMIN_CLEANUP_PLAN.md #1/#3.
        { label: 'Fest Hub (meal requests inside)', href: schoolAdminHref(schoolId, 'fest', 'hub'), icon: 'star' },
        { label: 'All fest reports', href: schoolAdminHref(schoolId, 'fest', 'reports'), icon: 'file-text', exact: true },
        { label: 'School Events', href: schoolAdminHref(schoolId, 'fest-programs'), icon: 'calendar' },
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

    const groups = [
        {
            section: coordinatorMode ? 'Assigned program' : 'School home',
            items: coordinatorMode
                ? [{ label: '← My assignments', href: base, icon: 'grid', exact: true }]
                : [{ label: 'Dashboard', href: base, icon: 'grid', exact: true }],
        },
    ];

    // Kalotsav's own hub redirects straight into the single yearly event (or lists every
    // event) and carries its own quick-actions row (Results/Qualifiers/Reports) inline, so
    // this 5-item sub-nav would just duplicate links already reachable from the page itself.
    if (programSlug !== 'kalotsav') {
        groups.push({
            section: program.label,
            items: schoolProgramWorkflowItems(schoolId, programSlug),
        });
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
            { section: 'Fest & tools', items: festToolItems(schoolId).filter((item) => !item.label.startsWith('Fest Hub')) },
        );
    }

    return groups;
}

/** Main school admin sidebar (dashboard, students, membership, program list). */
export function schoolAdminNav(schoolId, options = {}) {
    const {
        canNav = () => true,
        publicWebsiteEnabled = true,
        schoolHasPrefix = true,
        pendingChangeRequests = 0,
        navVisibility = null,
        membershipPaid = true,
        isStandalone = false,
    } = options;

    const base = schoolAdminHref(schoolId);
    const groups = [];

    // ── Home ──────────────────────────────────────────────────────────
    groups.push({
        section: 'Home',
        items: [
            { label: 'Dashboard', href: base, icon: 'grid', exact: true },
            { label: 'Notifications', href: schoolAdminHref(schoolId, 'notifications'), icon: 'bell' },
        ],
    });

    // ── School (students + core records) ──────────────────────────────
    // Independent schools get a website/CMS product, not the student-records SIS —
    // Academic Results (CBSE board results) stays since it's genuinely website content.
    if (!isStandalone && canNav('students')) {
        const schoolItems = [];
        if (!schoolHasPrefix) {
            schoolItems.push({ label: 'Set school code', href: schoolAdminHref(schoolId, 'setup', 'code'), icon: 'alert-circle' });
        }
        schoolItems.push(
            { label: 'Students', href: schoolAdminHref(schoolId, 'students'), icon: 'users', badge: pendingChangeRequests },
            { label: 'Import history', href: schoolAdminHref(schoolId, 'imports'), icon: 'clock' },
            { label: 'Teachers', href: schoolAdminHref(schoolId, 'teachers'), icon: 'user-check' },
            ...(canNav('questionPapers') ? [{ label: 'Question papers', href: schoolAdminHref(schoolId, 'question-papers'), icon: 'book-open' }] : []),
            { label: 'School houses', href: schoolAdminHref(schoolId, 'houses'), icon: 'layers' },
            { label: 'Payment history', href: schoolAdminHref(schoolId, 'payments'), icon: 'credit-card' },
            { label: 'Settings', href: schoolAdminHref(schoolId, 'settings'), icon: 'settings' },
            { label: 'Activity log', href: `${base}/audit-logs`, icon: 'file-text' },
        );
        // User management links
        if (canNav('users')) {
            schoolItems.push({ label: 'Profile requests', href: `${schoolAdminHref(schoolId, 'users')}/profile-change-requests`, icon: 'user-check', badge: pendingChangeRequests });
            schoolItems.push({ label: 'Portal users', href: schoolAdminHref(schoolId, 'users'), icon: 'shield' });
            schoolItems.push({ label: 'Event coordinators', href: `${schoolAdminHref(schoolId, 'users')}?coordinators=1`, icon: 'users' });
        }
        groups.push({ section: 'School', items: schoolItems });
    }

    // ── Membership ────────────────────────────────────────────────────
    // No Sahodaya cluster means no annual membership/registration cycle to manage.
    if (!isStandalone && canNav('membership')) {
        const membershipItems = [
            { label: 'Annual Registration', href: schoolAdminHref(schoolId, 'registration'), icon: 'clipboard' },
            { label: 'Payments & Receipts', href: schoolAdminHref(schoolId, 'payments'), icon: 'credit-card' },
        ];

        if (isNavMenuVisible(navVisibility, 'documents')) {
            membershipItems.push({ label: 'Compliance documents', href: schoolAdminHref(schoolId, 'documents'), icon: 'file-text' });
        }

        membershipItems.push(
            { label: 'Program calendar', href: schoolAdminHref(schoolId, 'calendar'), icon: 'calendar' },
            // Hidden — tab on Annual Registration page
            { label: 'Registration Details', href: schoolAdminHref(schoolId, 'registration', 'profile'), icon: 'user', hidden: true }
        );

        groups.push({
            section: 'Membership',
            items: membershipItems,
        });
    }

    // Programs (Fest / Talent Search / Training) unlock only after membership payment.
    if (!isStandalone && !membershipPaid && (canNav('fest') || canNav('mcq') || canNav('training'))) {
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
    // Fest programs are Sahodaya-cluster events — nothing to run standalone.
    if (!isStandalone && membershipPaid && canNav('fest')) {
        const festProgramItems = SCHOOL_FEST_PROGRAMS
            .filter((p) => isNavProgramVisible(navVisibility, p.slug))
            .map((p) => ({
                label: p.label,
                href: schoolProgramHref(schoolId, p.slug),
                icon: p.icon,
            }));

        // Reports/School events/Food coupons/Circulars aren't listed directly here —
        // once inside Fest Hub, schoolFestScopedNav() switches the sidebar to a
        // "Fest & tools" section with direct links to all of them (see
        // detectSchoolFestContextFromUrl). Notifications is already in Home above.
        const festItems = [
            ...festProgramItems,
            { label: 'Fest Hub', href: schoolAdminHref(schoolId, 'fest', 'hub'), icon: 'star' },
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

    // ── Board Results (Academic Results) ──────────────────────────────
    // Independent schools get a website/CMS product only — no results workflow.
    if (!isStandalone) {
        groups.push({
            section: 'Academic Results',
            items: [
                { label: 'Class X Results', href: `${base}/board-results?class=10`, icon: 'bar-chart', matchQuery: { class: '10' } },
                { label: 'Class XII Results', href: `${base}/board-results?class=12`, icon: 'bar-chart', matchQuery: { class: '12' } },
                { label: 'Subject-Wise Toppers', href: `${base}/board-results/subject-toppers`, icon: 'award' },
                { label: 'Full A1 Achievers', href: `${base}/board-results/full-a1-achievers`, icon: 'star' },
                { label: 'Principal Verification', href: `${base}/board-results/principal-verification`, icon: 'shield' },
                { label: 'Reports', href: `${base}/board-results/reports`, icon: 'file-text' },
            ],
        });
    }

    // ── Website (collapses to single hub entry) ────────────────────────
    if (publicWebsiteEnabled && canNav('website')) {
        // Unlike the old site-builder-only entry point (which covered just Page
        // Sections/Navigation/Footer and left the rest unreachable except via nav
        // search), Website/Hub.vue actually links out to all 12 of these — safe to
        // collapse to one sidebar entry.
        groups.push({
            section: 'Website',
            items: [
                { label: 'Website hub', href: `${base}/website/hub`, icon: 'layers' },
            ],
        });
    }

    if (!publicWebsiteEnabled && canNav('website')) {
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
        return `${schoolAdminHref(schoolId, prefix)}/events/${scope.event_id}/registration`;
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
