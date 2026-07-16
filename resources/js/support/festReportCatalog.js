/** Client-side mirror of export → interactive preview routes (Sahodaya event reports). */

export const REPORT_CATEGORIES = {
    heads: { label: 'By item head', sportsLabel: 'By Sport Event', icon: '📂' },
    registration: { label: 'Registration & fees', icon: '📋' },
    schedule: { label: 'Schedule & clashes', icon: '📅' },
    competition: { label: 'Marks & results', icon: '🏆' },
    finance: { label: 'Finance & catering', icon: '💳' },
    ops: { label: 'Operations & print', icon: '🖨️' },
};

/** Resolve a report category label, preferring sports wording when applicable. */
export function reportCategoryLabel(key, isSports = false) {
    const cat = REPORT_CATEGORIES[key];
    if (!cat) return key;
    return isSports && cat.sportsLabel ? cat.sportsLabel : cat.label;
}

export const REPORT_CATEGORY_ORDER = ['heads', 'registration', 'schedule', 'competition', 'finance', 'ops'];

/** Export type id → interactive preview page id */
export const EXPORT_PREVIEW_MAP = {
    'school-wise': 'school-detailed',
    'overall-ranking': 'overall-ranking',
    'house-wise': 'house-detailed',
    'item-list': 'item-counts',
    'mark-entry-status': 'mark-entry-status',
    clashes: 'schedule-clashes',
    'clashes-school': 'schedule-clashes',
    'item-schedule': 'item-schedule',
    'item-schedule-pdf': 'item-schedule',
    'student-participation': 'participation-counts',
    'discipline-registration': 'discipline-registration',
    'age-group-matrix': 'age-group-matrix',
    fees: 'fee-collection',
    'fee-pending-schools': 'fee-collection',
    'fee-breakdown': 'fee-collection',
    'student-event-registrations': 'registration-register',
    registrations: 'registration-register',
    'student-wise-report': 'student-wise',
    'item-participants': 'item-wise',
    'item-wise': 'item-wise',
    results: 'overall-ranking',
    'head-wise-participants': 'head-wise-participants',
    'area-wise-participants': 'area-wise-participants',
};

/** Interactive page id → category */
export const INTERACTIVE_CATEGORY_MAP = {
    'head-wise-participants': 'heads',
    'area-wise-participants': 'heads',
    'school-detailed': 'competition',
    'overall-ranking': 'competition',
    'house-detailed': 'competition',
    'participation-counts': 'registration',
    'registration-register': 'registration',
    'mark-entry-status': 'competition',
    'item-schedule': 'schedule',
    'schedule-clashes': 'schedule',
    'item-counts': 'registration',
    'assignment-completeness': 'registration',
    'numbering-register': 'registration',
    'pending-approvals': 'registration',
    'discipline-registration': 'registration',
    'age-group-matrix': 'registration',
    'fee-collection': 'finance',
    'student-wise': 'competition',
    'item-wise': 'competition',
};

export function previewHrefForExport(exportId, reportsBase) {
    const pageId = EXPORT_PREVIEW_MAP[exportId];
    if (!pageId) return null;
    return `${reportsBase}/${pageId}`;
}

export function groupInteractiveReports(reports) {
    const groups = {};
    for (const report of reports ?? []) {
        const cat = INTERACTIVE_CATEGORY_MAP[report.id] ?? 'ops';
        if (!groups[cat]) groups[cat] = [];
        groups[cat].push(report);
    }
    return groups;
}

export function groupExportsByCategory(exports) {
    const categoryFor = (id) => {
        const previewId = EXPORT_PREVIEW_MAP[id];
        if (previewId) return INTERACTIVE_CATEGORY_MAP[previewId] ?? 'ops';
        if (id.includes('head')) return 'heads';
        if (id.includes('fee') || id.includes('catering')) return 'finance';
        if (id.includes('schedule') || id.includes('clash') || id.includes('attendance')) return 'schedule';
        if (id.includes('mark') || id.includes('result') || id.includes('rank') || id.includes('medal')) return 'competition';
        if (id.includes('registr') || id.includes('student') || id.includes('category') || id.includes('participant') || id.includes('admit')) return 'registration';
        return 'ops';
    };
    const groups = {};
    for (const exp of exports ?? []) {
        const cat = categoryFor(exp.id);
        if (!groups[cat]) groups[cat] = [];
        groups[cat].push(exp);
    }
    return groups;
}

/** School fest report definitions for event hub */
export const SCHOOL_EVENT_REPORTS = [
    // Participants & registration
    { id: 'head-wise', label: 'Participant list (by head)', category: 'heads', phase: 'before', hasPreview: true, hasExport: true, pdfExportSuffix: 'head-wise/pdf', exportSuffix: 'export/head-wise-participants', exportLabel: 'Excel', icon: '👥', hint: 'Students with photo, reg no, fest ID, chest and item details', featured: true, program: 'sports-meet' },
    { id: 'item-wise', label: 'Participants per item', category: 'heads', phase: 'before', hasPreview: true, hasExport: true, pdfExportSuffix: 'item-wise/pdf', exportSuffix: 'item-wise/export', exportLabel: 'CSV', icon: '📝', hint: 'Pick an item to see all registered students', featured: true },
    { id: 'item-counts', label: 'Item registration counts', category: 'registration', phase: 'before', hasPreview: true, hasExport: true, pdfExportSuffix: 'item-counts/pdf', exportSuffix: 'item-counts/export', exportLabel: 'Excel', icon: '📊', hint: 'Registrations, fees and limits per item by head', featured: true },
    { id: 'registration-register', label: 'Registration & fees register', category: 'registration', phase: 'before', hasPreview: true, hasExport: true, pdfExportSuffix: 'registration-register/pdf', exportSuffix: 'registration-register/export', exportLabel: 'CSV', icon: '📋', hint: 'All registrations with fee lines and status', featured: true },
    { id: 'fee-summary', label: 'Fee summary', category: 'finance', phase: 'before', hasPreview: true, hasExport: true, exportSuffix: 'export/fee-breakdown', exportLabel: 'Excel', icon: '💳', hint: 'Your school event fee and receipt status', featured: true },
    { id: 'pending-approvals', label: 'Pending approvals', category: 'registration', phase: 'before', hasPreview: true, hasExport: true, exportSuffix: 'pending-approvals/export', exportLabel: 'Excel', icon: '⏳', hint: 'Registrations awaiting Sahodaya approval', featured: true },
    { id: 'participation', label: 'Participation limits', category: 'registration', phase: 'before', hasPreview: true, hasExport: true, pdfExportSuffix: 'participation/pdf', exportSuffix: 'participation/export', exportLabel: 'CSV', icon: '📊', hint: 'Quota usage vs limits for your school', featured: true },
    { id: 'assignment-completeness', label: 'Assignment completeness', category: 'registration', phase: 'before', hasPreview: true, hasExport: true, exportSuffix: 'assignment-completeness/export', exportLabel: 'Excel', icon: '✅', hint: 'Chest, item reg, schedule and marks readiness', featured: true, program: 'sports-meet' },
    { id: 'numbering-register', label: 'Numbering register', category: 'registration', phase: 'before', hasPreview: true, hasExport: true, exportSuffix: 'numbering-register/export', exportLabel: 'Excel', icon: '🔢', hint: 'Fest ID, chest and item reg numbers', featured: true },
    { id: 'discipline-participation', label: 'Discipline breakdown', category: 'registration', phase: 'before', hasPreview: true, hasExport: true, pdfExportSuffix: 'discipline-participation/pdf', exportSuffix: 'export/discipline-registration', exportLabel: 'Excel', icon: '🏃', hint: 'Registrations by sport discipline', featured: true, program: 'sports-meet' },
    { id: 'event-athletes-export', label: 'Event athletes register', category: 'registration', phase: 'before', hasPreview: false, hasExport: true, exportSuffix: 'export/student-event-registrations', exportLabel: 'Excel', icon: '🏃', hint: 'Fest event IDs for your athletes', program: 'sports-meet' },
    { id: 'registrations-export', label: 'All registrations (Excel)', category: 'registration', phase: 'before', hasPreview: false, hasExport: true, exportSuffix: 'export/registrations', exportLabel: 'Excel', icon: '📥', hint: 'Full item registration spreadsheet' },
    { id: 'item-participants-export', label: 'Item participants list', category: 'registration', phase: 'before', hasPreview: false, hasExport: true, exportSuffix: 'export/item-participants', exportLabel: 'Excel', icon: '📝', hint: 'Participants per competition item' },
    { id: 'student-participation-export', label: 'Student participation', category: 'registration', phase: 'before', hasPreview: false, hasExport: true, exportSuffix: 'export/student-participation', exportLabel: 'Excel', icon: '👥', hint: 'Each student\'s items and roles' },
    { id: 'age-group-matrix-export', label: 'Age group matrix', category: 'registration', phase: 'before', hasPreview: false, hasExport: true, exportSuffix: 'export/age-group-matrix', exportLabel: 'Excel', icon: '🔢', hint: 'Athletes by age group', program: 'sports-meet' },
    // Schedule & slots
    { id: 'item-schedule', label: 'Slot allocation & schedule', category: 'schedule', phase: 'during', hasPreview: true, hasExport: true, pdfExportSuffix: 'export/item-schedule-pdf', exportSuffix: 'export/item-schedule', exportLabel: 'CSV', icon: '🗓️', hint: 'Venue, date and time slot for each item', featured: true },
    { id: 'schedule-clashes', label: 'Schedule clashes', category: 'schedule', phase: 'during', hasPreview: true, hasExport: true, pdfExportSuffix: 'export/clashes-school', exportSuffix: 'export/clashes', exportLabel: 'CSV', icon: '⚠️', hint: 'Overlapping schedules for your students', featured: true },
    { id: 'attendance', label: 'Attendance register', category: 'schedule', phase: 'during', hasPreview: true, hasExport: true, pdfExportSuffix: 'attendance-sheet', icon: '✅', hint: 'Students with photos — filter by head/item, print sheet', featured: true },
    { id: 'attendance-sheet', label: 'Attendance sheet (PDF only)', category: 'schedule', phase: 'during', hasPreview: false, hasExport: true, pdfExportSuffix: 'attendance-sheet', icon: '📄', hint: 'Quick PDF download without preview' },
    { id: 'item-schedule-export', label: 'Item schedule export', category: 'schedule', phase: 'during', hasPreview: false, hasExport: true, pdfExportSuffix: 'export/item-schedule-pdf', exportSuffix: 'export/item-schedule', exportLabel: 'CSV', icon: '🗓️', hint: 'Venue and time for each item' },
    // Results
    { id: 'published-results', label: 'Published results', category: 'competition', phase: 'after', hasPreview: true, hasExport: true, pdfExportSuffix: 'export/school-wise', icon: '🏆', hint: 'Official published marks with student details', featured: true },
    { id: 'results-publish-status', label: 'Results publish status', category: 'competition', phase: 'during', hasPreview: true, hasExport: false, icon: '📣', hint: 'Which items have published results by head', featured: true },
    { id: 'results-summary', label: 'Results summary', category: 'competition', phase: 'after', hasPreview: true, hasExport: true, pdfExportSuffix: 'export/school-wise', icon: '📈', hint: 'Medals and scores overview for your school', featured: true },
    { id: 'student-wise', label: 'Student-wise results', category: 'competition', phase: 'after', hasPreview: true, hasExport: true, pdfExportSuffix: 'export/school-wise', exportSuffix: 'student-wise/export', exportLabel: 'CSV', icon: '🎓', hint: 'Results for each student' },
    { id: 'teacher-wise', label: 'Teacher-wise results', category: 'competition', phase: 'after', hasPreview: true, hasExport: true, exportSuffix: 'teacher-wise/export', exportLabel: 'CSV', program: 'teacher-fest', icon: '👩‍🏫', hint: 'Teacher fest results' },
    { id: 'mark-entry-status', label: 'Mark entry status', category: 'competition', phase: 'during', hasPreview: true, hasExport: true, pdfExportSuffix: 'mark-entry-status/pdf', exportSuffix: 'mark-entry-status/export', exportLabel: 'CSV', icon: '✏️', hint: 'Marking progress by item' },
    { id: 'qualifiers', label: 'Promoted qualifiers', category: 'competition', phase: 'after', hasPreview: true, hasExport: true, exportSuffix: 'qualifiers/export', exportLabel: 'CSV', icon: '🏅', hint: 'Students promoted to next level', program: 'sports-meet', externalPath: 'qualifiers' },
    // Print & ops
    { id: 'id-cards', label: 'ID cards', category: 'ops', phase: 'before', hasPreview: true, hasExport: true, pdfExportSuffix: 'id-cards/pdf', icon: '🪪', hint: 'Print student ID cards by head or item', featured: true },
    { id: 'admit-cards', label: 'Admit cards (bulk PDF)', category: 'ops', phase: 'before', hasPreview: false, hasExport: true, pdfExportSuffix: 'export/admit-cards', icon: '🎫', hint: 'Bulk admit cards PDF' },
    { id: 'group-roster', label: 'Group / squad roster', category: 'ops', phase: 'before', hasPreview: false, hasExport: true, pdfExportSuffix: 'export/team-squad-sheets', icon: '👥', hint: 'Team squad sheets' },
    { id: 'certificate-counts-export', label: 'Certificate counts', category: 'ops', phase: 'after', hasPreview: false, hasExport: true, exportSuffix: 'export/certificate-counts', exportLabel: 'Excel', icon: '🎖️', hint: 'Certificates due by item' },
];

export function schoolReportPdfHref(eventBase, report) {
    if (report.pdfExportSuffix) {
        return `${eventBase}/${report.pdfExportSuffix}`;
    }
    if (report.exportSuffix && (report.exportSuffix.includes('/pdf') || report.exportLabel === 'PDF')) {
        return `${eventBase}/${report.exportSuffix}`;
    }
    return null;
}

export function schoolReportDataHref(eventBase, report) {
    if (!report.exportSuffix) {
        return null;
    }
    const pdf = schoolReportPdfHref(eventBase, report);
    const data = `${eventBase}/${report.exportSuffix}`;
    if (pdf && pdf === data) {
        return null;
    }
    if (report.exportSuffix.includes('/pdf') && report.pdfExportSuffix) {
        return null;
    }
    return data;
}

export function schoolReportsForProgram(programSlug) {
    return SCHOOL_EVENT_REPORTS.filter((r) => !r.program || r.program === programSlug);
}

export function featuredSchoolReports(programSlug) {
    return schoolReportsForProgram(programSlug).filter((r) => r.featured);
}

export function schoolReportHref(eventBase, report) {
    if (report.externalPath) {
        const base = eventBase.replace(/\/reports\/\d+$/, '');
        return `${base}/${report.externalPath}`;
    }
    if (report.hasPreview) {
        return `${eventBase}/${report.id}`;
    }
    if (report.hasExport && report.exportSuffix) {
        return `${eventBase}/${report.exportSuffix}`;
    }
    return null;
}

export function groupSchoolReports(reports) {
    const groups = {};
    for (const r of reports) {
        if (!groups[r.category]) groups[r.category] = [];
        groups[r.category].push(r);
    }
    return groups;
}

/** Short descriptions for interactive Sahodaya report pages */
export const INTERACTIVE_REPORT_META = {
    'head-wise-participants': { icon: '📂', hint: 'Participants grouped by item head', sportsHint: 'Participants grouped by Sport Event' },
    'area-wise-participants': { icon: '🗂️', hint: 'Participants grouped by competition area' },
    'school-detailed': { icon: '🏫', hint: 'Detailed marks by school and item' },
    'overall-ranking': { icon: '🥇', hint: 'School ranking and total points' },
    'house-detailed': { icon: '🏠', hint: 'House-wise performance board' },
    'participation-counts': { icon: '👥', hint: 'Registration counts per school' },
    'registration-register': { icon: '📋', hint: 'Full register with fees status' },
    'mark-entry-status': { icon: '✏️', hint: 'Which items have marks entered' },
    'item-schedule': { icon: '🗓️', hint: 'Venue and time for each item' },
    'schedule-clashes': { icon: '⚠️', hint: 'Overlapping schedules to resolve' },
    'item-counts': { icon: '📊', hint: 'Registration counts per item' },
    'assignment-completeness': { icon: '✅', hint: 'Chest, item reg, schedule and marks readiness' },
    'numbering-register': { icon: '🔢', hint: 'Fest ID, chest and item registration numbers' },
    'pending-approvals': { icon: '⏳', hint: 'Submitted registrations awaiting approval' },
    'discipline-registration': { icon: '🏃', hint: 'Breakdown by sport discipline' },
    'age-group-matrix': { icon: '🔢', hint: 'Schools × age group matrix' },
    'fee-collection': { icon: '💳', hint: 'Fee payment status by school' },
    'student-wise': { icon: '🎓', hint: 'Browse each student\'s items and marks' },
    'item-wise': { icon: '📝', hint: 'Pick an item to view all participants' },
};

export const REPORT_PHASES = [
    { key: 'before', label: 'Before event', icon: '📋', hint: 'Registration, admit cards, schedules' },
    { key: 'during', label: 'During event', icon: '🎯', hint: 'Attendance, mark sheets, ops' },
    { key: 'after', label: 'After event', icon: '🏆', hint: 'Results, rankings, certificates' },
];

export const FORMAT_LABELS = { pdf: 'PDF', csv: 'CSV', xls: 'Excel' };

export function enrichInteractiveReport(report, isSports = false) {
    const meta = INTERACTIVE_REPORT_META[report.id] ?? { icon: '📊', hint: 'Open interactive report' };
    const hint = isSports && meta.sportsHint ? meta.sportsHint : meta.hint;
    return { ...report, ...meta, hint };
}

export function filterReportsByQuery(items, query, labelKey = 'label') {
    const q = (query ?? '').trim().toLowerCase();
    if (!q) return items;
    return items.filter((item) => {
        const label = (item[labelKey] ?? '').toLowerCase();
        const hint = (item.hint ?? '').toLowerCase();
        const phase = (item.phase ?? '').toLowerCase();
        return label.includes(q) || hint.includes(q) || (item.id ?? '').includes(q) || phase.includes(q);
    });
}

export function filterReportsByPhase(items, phase) {
    if (!phase) return items;
    return items.filter((item) => item.phase === phase);
}
