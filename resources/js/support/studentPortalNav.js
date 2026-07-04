/** Shared student portal top navigation. */
export function studentPortalNavItems(schoolId) {
    const base = `/portal/student/${schoolId}`;

    return [
        { href: base, label: 'Home', exact: true },
        { href: `${base}/mcq`, label: 'MCQ Exams' },
        { href: `${base}/fest/schedule`, label: 'Fest schedule' },
        { href: `${base}/results`, label: 'Results' },
        { href: `${base}/certificates`, label: 'Certificates' },
        { href: `${base}/profile`, label: 'Profile' },
    ];
}
