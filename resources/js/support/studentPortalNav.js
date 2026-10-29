/** Shared student portal navigation (sidebar + mobile bottom bar). */

export function studentPortalNavItems(schoolId) {
    const base = `/portal/student/${schoolId}`;

    return [
        { href: base, label: 'Home', icon: 'home', exact: true, primary: true },
        { href: `${base}/mcq`, label: 'Exams', icon: 'exams', primary: true },
        { href: `${base}/documents`, label: 'Documents', icon: 'documents', primary: true },
        { href: `${base}/profile`, label: 'Profile', icon: 'profile', primary: true },
        { href: `${base}/fest-registrations`, label: 'Registrations', icon: 'fest', group: 'more' },
        { href: `${base}/fest/schedule`, label: 'Fest schedule', icon: 'schedule', group: 'more' },
        { href: `${base}/results`, label: 'Fest results', icon: 'results', group: 'more' },
        { href: `${base}/sports-results`, label: 'Sports results', icon: 'results', group: 'more' },
        { href: `${base}/certificates`, label: 'Fest certificates', icon: 'certificates', group: 'more' },
        { href: `${base}/mcq/system-check`, label: 'System check', icon: 'check', group: 'more' },
    ];
}

export function studentPortalPrimaryNav(schoolId) {
    return studentPortalNavItems(schoolId).filter((item) => item.primary);
}
