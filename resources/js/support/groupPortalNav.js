/** Group admin portal navigation. */
export function groupPortalNavItems(schoolId) {
    const base = `/portal/group/${schoolId}`;

    return [
        { href: base, label: 'Dashboard', exact: true },
        { href: `${base}/students`, label: 'Students' },
        { href: `${base}/fest/registrations`, label: 'Fest registrations' },
        { href: `${base}/fest/schedule`, label: 'Fest schedule' },
        { href: `${base}/fest/clashes`, label: 'Clashes' },
        { href: `${base}/fest/admit-cards`, label: 'Admit cards' },
    ];
}
