/** MCQ exam operations portal navigation. */
export function examPortalNavItems(sahodayaId, examId = null, options = {}) {
    const role = typeof options === 'string' ? options : (options.role ?? null);
    const canEnterMarks = typeof options === 'object' && options.canEnterMarks !== undefined 
        ? options.canEnterMarks 
        : (role !== 'exam_staff');

    // Question banks hold answer keys: exam controllers (and Sahodaya admins) only, never exam staff.
    const canManageQuestions = typeof options === 'object' && options.canManageQuestions !== undefined
        ? options.canManageQuestions
        : (role !== 'exam_staff');

    const base = `/portal/exam/${sahodayaId}`;
    const items = [{ href: base, label: examId ? 'My exams' : 'Dashboard', exact: !examId }];

    if (canManageQuestions) {
        items.push({ href: `${base}/question-banks`, label: 'Question banks' });
    }

    if (!examId) {
        return items;
    }

    const examBase = `${base}/exams/${examId}`;
    items.push(
        { href: `${examBase}/attendance`, label: 'Attendance' },
        { href: `${examBase}/supervision`, label: 'Supervision' },
    );

    // Online exam engine live monitor. Pages that know the exam is not on the engine pass
    // usesEngine: false; otherwise it's shown and the monitor explains when it doesn't apply.
    if (typeof options !== 'object' || options.usesEngine !== false) {
        items.push({ href: `${examBase}/live`, label: 'Live monitor' });
    }

    if (canEnterMarks) {
        items.push({ href: `${examBase}/marks`, label: 'Mark entry' });
    }

    items.push({ href: `${examBase}/results`, label: 'Results' });

    return items;
}
