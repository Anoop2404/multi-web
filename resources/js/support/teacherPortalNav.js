/** Shared teacher portal top navigation. */
export function teacherPortalNavItems(schoolId) {
    const base = `/portal/teacher/${schoolId}`;

    return [
        { href: base, label: 'Home', exact: true, icon: 'home' },
        { href: `${base}/fest`, label: 'Fest', icon: 'fest' },
        { href: `${base}/fest/schedule`, label: 'Schedule', icon: 'schedule' },
        { href: `${base}/results`, label: 'Results', icon: 'results' },
        { href: `${base}/certificates`, label: 'Certificates', icon: 'certificates' },
        { href: `${base}/training`, label: 'Training', icon: 'training' },
        { href: `${base}/exams`, label: 'Talent Search', icon: 'exams' },
        { href: `${base}/question-papers`, label: 'Question Bank', icon: 'papers' },
        { href: `${base}/profile`, label: 'Profile', icon: 'profile' },
    ];
}

