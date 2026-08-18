/** MCQ exam operations portal navigation. */
export function examPortalNavItems(sahodayaId, examId = null, options = {}) {
    const role = typeof options === 'string' ? options : (options.role ?? null);
    const canEnterMarks = typeof options === 'object' && options.canEnterMarks !== undefined 
        ? options.canEnterMarks 
        : (role !== 'exam_staff');

    const base = `/portal/exam/${sahodayaId}`;
    const items = [{ href: base, label: examId ? 'My exams' : 'Dashboard', exact: !examId }];

    if (!examId) {
        return items;
    }

    const examBase = `${base}/exams/${examId}`;
    items.push(
        { href: `${examBase}/attendance`, label: 'Attendance' },
        { href: `${examBase}/supervision`, label: 'Supervision' },
    );

    if (canEnterMarks) {
        items.push({ href: `${examBase}/marks`, label: 'Mark entry' });
    }

    items.push({ href: `${examBase}/results`, label: 'Results' });

    return items;
}
