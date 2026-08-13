/** Shared teacher portal top navigation. */
export function teacherPortalNavItems(schoolId, options = {}) {
    const base = `/portal/teacher/${schoolId}`;

    const items = [
        { href: base, label: 'Home', exact: true, icon: 'home' },
        { href: `${base}/fest`, label: 'Fest', icon: 'fest' },
        { href: `${base}/fest/schedule`, label: 'Schedule', icon: 'schedule' },
        { href: `${base}/results`, label: 'Results', icon: 'results' },
        { href: `${base}/certificates`, label: 'Certificates', icon: 'certificates' },
        { href: `${base}/training`, label: 'Training', icon: 'training' },
        { href: `${base}/exams`, label: 'Talent Search', icon: 'exams' },
        { href: `${base}/question-banks`, label: 'Question Banks', icon: 'banks' },
        { href: `${base}/question-papers`, label: 'Question Papers', icon: 'papers' },
        { href: `${base}/profile`, label: 'Profile', icon: 'profile' },
    ];

    if (options.bankId) {
        items.push({
            href: `${base}/question-banks/${options.bankId}`,
            label: options.bankLabel || 'Bank',
            icon: 'banks',
        });
    }

    return items;
}

