/** Shared teacher portal top navigation. */
export function teacherPortalNavItems(schoolId, options = {}) {
    const base = `/portal/teacher/${schoolId}`;

    const items = [
        { href: base, label: 'Home', exact: true },
        { href: `${base}/fest`, label: 'Fest' },
        { href: `${base}/fest/schedule`, label: 'Schedule' },
        { href: `${base}/results`, label: 'Results' },
        { href: `${base}/certificates`, label: 'Certificates' },
        { href: `${base}/training`, label: 'Training' },
        { href: `${base}/question-banks`, label: 'Talent Search Banks' },
        { href: `${base}/profile`, label: 'Profile' },
    ];

    if (options.bankId) {
        items.push({
            href: `${base}/question-banks/${options.bankId}`,
            label: options.bankLabel || 'Bank',
        });
    }

    return items;
}
