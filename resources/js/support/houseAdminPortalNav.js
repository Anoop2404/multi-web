/** House admin portal navigation. */
export function houseAdminPortalNavItems(schoolId) {
    const base = `/portal/house-admin/${schoolId}`;

    return [
        { href: base, label: 'Dashboard', exact: true },
        { href: `${base}/students`, label: 'Students' },
        { href: `${base}/registrations`, label: 'Registrations' },
        { href: `${base}/ranking`, label: 'House ranking' },
    ];
}
