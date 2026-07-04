/** Shared teacher portal top navigation. */
export function teacherPortalNavItems(schoolId) {
    const base = `/portal/teacher/${schoolId}`;

    return [
        { href: base, label: 'Home', exact: true },
        { href: `${base}/fest`, label: 'Fest', exact: true },
        { href: `${base}/fest/schedule`, label: 'Schedule' },
        { href: `${base}/results`, label: 'Results' },
        { href: `${base}/certificates`, label: 'Certificates' },
        { href: `${base}/training`, label: 'Training' },
        { href: `${base}/question-banks`, label: 'MCQ Banks' },
        { href: `${base}/profile`, label: 'Profile' },
    ];
}
