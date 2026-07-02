/** Relative admin paths — prefix with /sahodaya-admin/{tenantId} or /school-admin/{tenantId} */

export type PageEntry = { path: string; label: string; gated?: boolean; skipPdf?: boolean };

export const sahodayaStaticPages = (tenantId: string): PageEntry[] => [
    { path: `/sahodaya-admin/${tenantId}`, label: 'Sahodaya dashboard' },
    { path: `/sahodaya-admin/${tenantId}/users`, label: 'Portal users' },
    { path: `/sahodaya-admin/${tenantId}/public-content`, label: 'Portal content' },
    { path: `/sahodaya-admin/${tenantId}/academic-years`, label: 'Academic years' },
    { path: `/sahodaya-admin/${tenantId}/membership/settings`, label: 'Membership settings' },
    { path: `/sahodaya-admin/${tenantId}/membership/submissions`, label: 'Student counts' },
    { path: `/sahodaya-admin/${tenantId}/membership/payments`, label: 'Membership payments' },
    { path: `/sahodaya-admin/${tenantId}/membership/reports`, label: 'Membership reports' },
    { path: `/sahodaya-admin/${tenantId}/schools`, label: 'Member schools' },
    { path: `/sahodaya-admin/${tenantId}/events`, label: 'Events index' },
    { path: `/sahodaya-admin/${tenantId}/kalotsav`, label: 'Kalotsav' },
    { path: `/sahodaya-admin/${tenantId}/sports`, label: 'Sports Meet' },
    { path: `/sahodaya-admin/${tenantId}/kids-fest`, label: 'Kids Fest' },
    { path: `/sahodaya-admin/${tenantId}/teacher-fest`, label: 'Teacher Fest' },
    { path: `/sahodaya-admin/${tenantId}/kalotsav/school-rounds`, label: 'Kalotsav school rounds' },
    { path: `/sahodaya-admin/${tenantId}/sports/records`, label: 'Athletic records' },
    { path: `/sahodaya-admin/${tenantId}/sports/championship`, label: 'House championship' },
    { path: `/sahodaya-admin/${tenantId}/display-screens`, label: 'Display screens' },
    { path: `/sahodaya-admin/${tenantId}/mcq-exams`, label: 'MCQ exams' },
    { path: `/sahodaya-admin/${tenantId}/training`, label: 'Training programs' },
    { path: `/sahodaya-admin/${tenantId}/ledger`, label: 'Ledger' },
    { path: `/sahodaya-admin/${tenantId}/ledger/reports`, label: 'Ledger reports' },
    { path: `/sahodaya-admin/${tenantId}/state-remittances`, label: 'State remittances' },
    { path: `/sahodaya-admin/${tenantId}/certificate-templates`, label: 'Certificate templates' },
    { path: `/sahodaya-admin/${tenantId}/events/certificates/search`, label: 'Certificate search' },
    { path: `/sahodaya-admin/${tenantId}/office-bearers`, label: 'Office bearers', gated: true },
    { path: `/sahodaya-admin/${tenantId}/circulars`, label: 'Circulars', gated: true },
    { path: `/sahodaya-admin/${tenantId}/site-builder`, label: 'Site builder', gated: true },
];

export const sahodayaEventPages = (tenantId: string, eventId: string | number): PageEntry[] => [
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}`, label: 'Event hub' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/settings`, label: 'Event settings' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/registrations`, label: 'Registrations' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/fees`, label: 'Event fees' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/finance`, label: 'Finance' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/leaderboard`, label: 'Leaderboard' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/food-coupons`, label: 'Food coupons' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/athletic-records`, label: 'Athletic records' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/championship`, label: 'Championship' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/marks`, label: 'Mark entry' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/marks/import`, label: 'Mark import' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/results`, label: 'Results' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/reports`, label: 'Reports hub' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/reports/school-detailed`, label: 'School detailed report' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/reports/overall-ranking`, label: 'Overall ranking report' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/reports/house-detailed`, label: 'House detailed report' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/reports/participation-counts`, label: 'Participation counts' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/attendance`, label: 'Attendance' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/schedule`, label: 'Schedule' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/judges`, label: 'Judges' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/event-staff`, label: 'Event staff' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/chest-numbers`, label: 'Chest numbers' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/certificates`, label: 'Certificates' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/houses`, label: 'Houses' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/appeals`, label: 'Appeals' },
    { path: `/sahodaya-admin/${tenantId}/events/${eventId}/catering`, label: 'Catering' },
];

export const sahodayaMcqPages = (tenantId: string, examId?: string | number): PageEntry[] => {
    const base: PageEntry[] = [
        { path: `/sahodaya-admin/${tenantId}/mcq-exams`, label: 'MCQ list' },
    ];
    if (!examId) {
        return base;
    }
    return [
        ...base,
        { path: `/sahodaya-admin/${tenantId}/mcq-exams/${examId}`, label: 'MCQ show' },
        { path: `/sahodaya-admin/${tenantId}/mcq-exams/${examId}/attendance`, label: 'MCQ attendance' },
        { path: `/sahodaya-admin/${tenantId}/mcq-exams/${examId}/hall-tickets`, label: 'MCQ hall tickets' },
        { path: `/sahodaya-admin/${tenantId}/mcq-exams/${examId}/staff`, label: 'MCQ staff' },
        { path: `/sahodaya-admin/${tenantId}/mcq-exams/${examId}/question-banks`, label: 'MCQ question banks' },
    ];
};

export const sahodayaTrainingPages = (tenantId: string, programId: string | number): PageEntry[] => [
    { path: `/sahodaya-admin/${tenantId}/training`, label: 'Training list' },
    { path: `/sahodaya-admin/${tenantId}/training/${programId}`, label: 'Training show' },
];

export const sahodayaSchoolPages = (tenantId: string, schoolId: string): PageEntry[] => [
    { path: `/sahodaya-admin/${tenantId}/schools/${schoolId}`, label: 'Member school detail' },
];

export const schoolStaticPages = (tenantId: string): PageEntry[] => [
    { path: `/school-admin/${tenantId}`, label: 'School dashboard' },
    { path: `/school-admin/${tenantId}/users`, label: 'Portal users' },
    { path: `/school-admin/${tenantId}/students`, label: 'Students' },
    { path: `/school-admin/${tenantId}/students/setup`, label: 'Class setup' },
    { path: `/school-admin/${tenantId}/teachers`, label: 'Teachers' },
    { path: `/school-admin/${tenantId}/houses`, label: 'School houses' },
    { path: `/school-admin/${tenantId}/registration/profile`, label: 'Registration profile' },
    { path: `/school-admin/${tenantId}/registration`, label: 'Annual registration' },
    { path: `/school-admin/${tenantId}/kalotsav`, label: 'Kalotsav hub' },
    { path: `/school-admin/${tenantId}/kalotsav/registration`, label: 'Kalotsav registration' },
    { path: `/school-admin/${tenantId}/kalotsav/results`, label: 'Kalotsav results' },
    { path: `/school-admin/${tenantId}/sports/registration`, label: 'Sports registration' },
    { path: `/school-admin/${tenantId}/sports/results`, label: 'Sports results' },
    { path: `/school-admin/${tenantId}/kids-fest/registration`, label: 'Kids fest registration' },
    { path: `/school-admin/${tenantId}/kids-fest/results`, label: 'Kids fest results' },
    { path: `/school-admin/${tenantId}/fest-programs`, label: 'School events' },
    { path: `/school-admin/${tenantId}/fest/hub`, label: 'Fest hub' },
    { path: `/school-admin/${tenantId}/food-coupons`, label: 'Food coupons' },
    { path: `/school-admin/${tenantId}/mcq`, label: 'MCQ registration hub' },
    { path: `/school-admin/${tenantId}/training`, label: 'Training registration' },
    { path: `/school-admin/${tenantId}/circulars`, label: 'Circulars' },
    { path: `/school-admin/${tenantId}/notifications`, label: 'Notifications' },
    { path: `/school-admin/${tenantId}/payments`, label: 'Payment history' },
    { path: `/school-admin/${tenantId}/site-builder`, label: 'Site builder', gated: true },
    { path: `/school-admin/${tenantId}/news`, label: 'News', gated: true },
    { path: `/school-admin/${tenantId}/events`, label: 'School events CMS', gated: true },
    { path: `/school-admin/${tenantId}/gallery`, label: 'Gallery', gated: true },
    { path: `/school-admin/${tenantId}/staff`, label: 'Staff', gated: true },
    { path: `/school-admin/${tenantId}/achievements`, label: 'Achievements', gated: true },
    { path: `/school-admin/${tenantId}/downloads`, label: 'Downloads', gated: true },
    { path: `/school-admin/${tenantId}/job-vacancies`, label: 'Job vacancies', gated: true },
    { path: `/school-admin/${tenantId}/board-results`, label: 'Board results', gated: true },
    { path: `/school-admin/${tenantId}/alumni`, label: 'Alumni', gated: true },
    { path: `/school-admin/${tenantId}/enquiries`, label: 'Enquiries', gated: true },
    { path: `/school-admin/${tenantId}/tc-requests`, label: 'TC requests', gated: true },
    { path: `/school-admin/${tenantId}/settings`, label: 'Settings', gated: true },
];

export const schoolFestProgramPages = (tenantId: string, programId: string | number): PageEntry[] => [
    { path: `/school-admin/${tenantId}/fest-programs/${programId}`, label: 'School event detail' },
];

export const schoolFestEventPages = (
    tenantId: string,
    eventId: string | number,
    program = 'kalotsav',
): PageEntry[] => [
    { path: `/school-admin/${tenantId}/fest/${eventId}/catering`, label: 'School fest catering' },
    { path: `/school-admin/${tenantId}/fest/${eventId}/house`, label: 'School fest house' },
    { path: `/school-admin/${tenantId}/${program}/reports/${eventId}/participation`, label: 'Participation report' },
    { path: `/school-admin/${tenantId}/${program}/reports/${eventId}/student-wise`, label: 'Student-wise report' },
    { path: `/school-admin/${tenantId}/${program}/reports/${eventId}/item-wise`, label: 'Item-wise report' },
    { path: `/school-admin/${tenantId}/${program}/reports/${eventId}/admit-cards`, label: 'Admit cards' },
];

export const portalPages = (role: string, tenantId: string): PageEntry[] => {
    switch (role) {
        case 'judge':
            return [{ path: `/portal/judge/${tenantId}`, label: 'Judge dashboard' }];
        case 'teacher':
            return [
                { path: `/portal/teacher/${tenantId}`, label: 'Teacher dashboard' },
                { path: `/portal/teacher/${tenantId}/question-banks`, label: 'Question banks' },
            ];
        case 'exam':
            return [{ path: `/portal/exam/${tenantId}`, label: 'Exam ops dashboard' }];
        case 'student':
            return [{ path: `/portal/student/${tenantId}`, label: 'Student dashboard' }];
        case 'group':
            return [
                { path: `/portal/group/${tenantId}`, label: 'Group admin dashboard' },
                { path: `/portal/group/${tenantId}/students`, label: 'Group students' },
            ];
        case 'festops':
            return [{ path: `/portal/fest-ops/${tenantId}`, label: 'Fest ops dashboard' }];
        case 'fest_coordinator':
            return [{ path: `/portal/fest-coordinator/${tenantId}`, label: 'Mark coordinator dashboard' }];
        case 'house_admin':
            return [{ path: `/portal/house-admin/${tenantId}`, label: 'House admin dashboard' }];
        default:
            return [];
    }
};

export const portalJudgeEventPages = (tenantId: string, eventId: string | number): PageEntry[] => [
    { path: `/portal/judge/${tenantId}/events/${eventId}/marks`, label: 'Judge mark entry' },
];

export const portalFestCoordinatorEventPages = (tenantId: string, eventId: string | number): PageEntry[] => [
    { path: `/portal/fest-coordinator/${tenantId}/events/${eventId}/marks`, label: 'Coordinator mark entry' },
];

export const portalFestOpsEventPages = (tenantId: string, eventId: string | number): PageEntry[] => [
    { path: `/portal/fest-ops/${tenantId}/events/${eventId}`, label: 'Fest ops event hub' },
    { path: `/portal/fest-ops/${tenantId}/events/${eventId}/coordinator`, label: 'Fest ops coordinator' },
    { path: `/portal/fest-ops/${tenantId}/events/${eventId}/registrations`, label: 'Fest ops registrations' },
    { path: `/portal/fest-ops/${tenantId}/events/${eventId}/appeals`, label: 'Fest ops appeals' },
    { path: `/portal/fest-ops/${tenantId}/events/${eventId}/certificates`, label: 'Fest ops certificates' },
    { path: `/portal/fest-ops/${tenantId}/events/${eventId}/stage`, label: 'Fest ops stage' },
    { path: `/portal/fest-ops/${tenantId}/events/${eventId}/attendance`, label: 'Fest ops attendance' },
    { path: `/portal/fest-ops/${tenantId}/events/${eventId}/kitchen`, label: 'Fest ops kitchen' },
];

export const portalExamPages = (tenantId: string, examId: string | number): PageEntry[] => [
    { path: `/portal/exam/${tenantId}/exams/${examId}/attendance`, label: 'Exam attendance' },
    { path: `/portal/exam/${tenantId}/exams/${examId}/marks`, label: 'Exam mark entry' },
];

export const superadminPages: PageEntry[] = [
    { path: '/admin/dashboard', label: 'Superadmin dashboard' },
    { path: '/admin/tenants', label: 'Tenants' },
    { path: '/admin/sahodayas', label: 'Sahodayas list' },
    { path: '/admin/sahodayas/create', label: 'Add Sahodaya' },
    { path: '/admin/schools', label: 'Schools list' },
    { path: '/admin/schools/create', label: 'Add school' },
    { path: '/admin/state-programs', label: 'State programs' },
    { path: '/admin/state-remittances', label: 'State remittances' },
    { path: '/admin/state-users', label: 'State users' },
    { path: '/admin/billing', label: 'Billing' },
    { path: '/admin/master-data/class-categories', label: 'Class categories' },
    { path: '/admin/master-data/teaching-types', label: 'Teaching types' },
    { path: '/admin/skin-presets', label: 'Skin presets', gated: true },
    { path: '/admin/builder/sections', label: 'Builder sections', gated: true },
    { path: '/admin/builder/theme', label: 'Builder theme', gated: true },
];

export const stateAdminPages: PageEntry[] = [
    { path: '/admin/state-programs', label: 'State programs' },
    { path: '/admin/kalotsav', label: 'State Kalotsav hub' },
    { path: '/admin/sports', label: 'State sports results' },
    { path: '/admin/state-remittances', label: 'State remittances' },
    { path: '/admin/sahodayas', label: 'Sahodaya clusters' },
];

export const publicPages: PageEntry[] = [
    { path: '/', label: 'Sahodaya home' },
    { path: '/login', label: 'Login page' },
];
