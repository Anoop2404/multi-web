/** MCQ exam operations portal navigation. */
export function examPortalNavItems(sahodayaId, examId = null) {
    const base = `/portal/exam/${sahodayaId}`;
    const items = [{ href: base, label: examId ? 'My exams' : 'Dashboard', exact: !examId }];

    if (!examId) {
        return items;
    }

    const examBase = `${base}/exams/${examId}`;
    items.push(
        { href: `${examBase}/attendance`, label: 'Attendance' },
        { href: `${examBase}/supervision`, label: 'Supervision' },
        { href: `${examBase}/marks`, label: 'Mark entry' },
    );

    return items;
}
