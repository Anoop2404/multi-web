/** Shared student portal top navigation. */
export function studentPortalNavItems(schoolId) {
    const base = `/portal/student/${schoolId}`;

    return [
        { href: base, label: 'Home', exact: true },
        { href: `${base}/mcq`, label: 'Talent Search Exams' },
        { href: `${base}/fest-registrations`, label: 'Registrations' },
        { href: `${base}/fest/schedule`, label: 'Fest schedule' },
        { href: `${base}/results`, label: 'Fest results' },
        { href: `${base}/sports-results`, label: 'Sports results' },
        { href: `${base}/certificates`, label: 'Certificates' },
        { href: `${base}/profile`, label: 'Profile' },
    ];
}
