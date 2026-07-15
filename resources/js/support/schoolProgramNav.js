/**
 * School admin — fest program navigation (one sub-menu per program type).
 */

export const SCHOOL_FEST_PROGRAMS = [
    { slug: 'kalotsav', prefix: 'kalotsav', label: 'Kalotsav', icon: 'star' },
    { slug: 'sports-meet', prefix: 'sports', label: 'Sports Meet', icon: 'award' },
    { slug: 'kids-fest', prefix: 'kids-fest', label: 'Kids Fest', icon: 'users' },
    { slug: 'teacher-fest', prefix: 'teacher-fest', label: 'Teacher Fest', icon: 'users' },
    { slug: 'english-fest', prefix: 'english-fest', label: 'English Fest', icon: 'file-text' },
    { slug: 'science-fest', prefix: 'science-fest', label: 'Science Fest', icon: 'layers' },
    { slug: 'custom', prefix: 'custom', label: 'Custom Events', icon: 'calendar' },
];

const SLUG_TO_PREFIX = Object.fromEntries(SCHOOL_FEST_PROGRAMS.map((p) => [p.slug, p.prefix]));
const PREFIX_TO_SLUG = Object.fromEntries(SCHOOL_FEST_PROGRAMS.map((p) => [p.prefix, p.slug]));

/** @returns {string|null} program slug */
export function detectSchoolProgramFromUrl(url) {
    const path = (url ?? '').split('?')[0];
    const dedicated = path.match(/\/(kalotsav|sports|kids-fest|teacher-fest|english-fest|science-fest|custom)(?:\/|$)/);
    if (dedicated?.[1] && PREFIX_TO_SLUG[dedicated[1]]) {
        return PREFIX_TO_SLUG[dedicated[1]];
    }

    const legacy = path.match(/\/programs\/(kalotsav|sports-meet|kids-fest|teacher-fest|english-fest|science-fest|custom)(?:\/|$)/);
    return legacy?.[1] ?? null;
}

/** Build a school-admin URL under `/school-admin/{schoolId}/…`. */
export function schoolAdminHref(schoolId, ...segments) {
    const id = String(schoolId ?? '').trim();
    if (!id) {
        return '/school-admin';
    }

    const tail = segments
        .flat()
        .filter((s) => s != null && String(s).trim() !== '')
        .map((s) => String(s).replace(/^\/+|\/+$/g, ''))
        .join('/');

    return tail ? `/school-admin/${id}/${tail}` : `/school-admin/${id}`;
}

/** Dedicated event-type base path, e.g. `/school-admin/{id}/kalotsav`. */
export function schoolEventTypeHref(schoolId, programSlug, ...segments) {
    const prefix = SLUG_TO_PREFIX[programSlug] ?? programSlug;
    return schoolAdminHref(schoolId, prefix, ...segments);
}

/** `/school-admin/{schoolId}/programs/{slug}/…` legacy — prefer schoolEventTypeHref. */
export function schoolProgramHref(schoolId, programSlug, ...segments) {
    const slug = SCHOOL_FEST_PROGRAMS.some((p) => p.slug === programSlug)
        ? programSlug
        : detectSchoolProgramFromUrl(typeof programSlug === 'string' ? programSlug : '') ?? 'kalotsav';

    return schoolEventTypeHref(schoolId, slug, ...segments);
}

/** Workflow-ordered sidebar items for a fest program hub. */
export function schoolProgramWorkflowItems(schoolId, programSlug) {
    const items = [
        { label: 'Overview', href: schoolProgramHref(schoolId, programSlug), icon: 'grid', exact: true },
        { label: 'Register students', href: schoolProgramHref(schoolId, programSlug, 'registration'), icon: 'clipboard' },
        { label: 'My school events', href: schoolProgramHref(schoolId, programSlug, 'my-events'), icon: 'calendar' },
    ];

    if (programSlug === 'sports-meet') {
        items.push({ label: 'Submit winners', href: schoolProgramHref(schoolId, programSlug, 'submit-winners'), icon: 'award' });
    }

    items.push(
        { label: 'Results', href: schoolProgramHref(schoolId, programSlug, 'results'), icon: 'bar-chart' },
        { label: 'Qualifiers', href: schoolProgramHref(schoolId, programSlug, 'qualifiers'), icon: 'star' },
        { label: 'Reports', href: schoolProgramHref(schoolId, programSlug, 'reports'), icon: 'file-text' },
    );

    return items;
}

/** @returns {list<{label: string, href: string, icon: string}>} */
export function schoolProgramMenuItems(schoolId, programSlug) {
    return schoolProgramWorkflowItems(schoolId, programSlug);
}

export function schoolProgramBySlug(slug) {
    return SCHOOL_FEST_PROGRAMS.find((p) => p.slug === slug) ?? null;
}

export { SLUG_TO_PREFIX, PREFIX_TO_SLUG };
