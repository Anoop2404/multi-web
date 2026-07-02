<template>
    <SchoolAdminLayout :title="exam.title" :school="school" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="MCQ exams" :description="examHeaderDesc">
            <template #actions>
                <Link :href="`/school-admin/${school.id}/mcq`" class="btn-secondary text-sm">← All exams</Link>
            </template>
        </PageHeader>

        <div v-if="exam.level_label || exam.series_title" class="flex flex-wrap gap-2 mb-4">
            <span v-if="exam.level_label" class="text-xs font-semibold px-2.5 py-1 rounded-full bg-indigo-100 text-indigo-800">{{ exam.level_label }}</span>
            <span v-if="exam.exam_type_label" class="text-xs px-2.5 py-1 rounded-full bg-slate-100 text-slate-700">{{ exam.exam_type_label }}</span>
            <span class="status-pill capitalize text-xs" :class="statusClass(exam.status)">{{ exam.status_label || exam.status }}</span>
            <span v-if="exam.delivery_mode_label" class="text-xs px-2.5 py-1 rounded-full bg-slate-50 text-slate-600">{{ exam.delivery_mode_label }}</span>
            <span v-if="exam.series_title" class="text-xs text-slate-500">{{ exam.series_title }}</span>
        </div>

        <SchoolMcqSubNav :school-id="school.id" :exam-id="exam.id" :active="tab" :results-published="exam.results_published" />

        <McqSchoolWorkflowStepper
            :school-id="school.id"
            :exam-id="exam.id"
            :exam="exam"
            :active-tab="tab"
            :registration-count="registerStats.registered ?? registrations.length"
            :school-fee="schoolFee"
            :tickets-issued-count="ticketsIssuedCount"
        />

        <StudentPortalCredentialsBanner :credentials="studentPortalCredentials" />

        <!-- Register tab -->
        <div v-if="tab === 'register'" class="space-y-4">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="card card--muted !py-3 text-center">
                    <p class="text-lg font-bold" :class="examHasFee ? 'text-emerald-700' : 'text-amber-700'">{{ feeLabel }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Fee / student</p>
                </div>
                <div class="card card--muted !py-3 text-center">
                    <p class="text-lg font-bold">{{ registerStats.available ?? 0 }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Eligible to add</p>
                </div>
                <div class="card card--muted !py-3 text-center">
                    <p class="text-lg font-bold text-indigo-700">{{ registerStats.registered ?? registrations.length }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Registered</p>
                </div>
                <div class="card card--muted !py-3 text-center">
                    <p class="text-lg font-bold">{{ batchDueLabel }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Batch fee due</p>
                </div>
            </div>

            <div v-if="!canRegister" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">{{ registrationBlockTitle }}</p>
                <p class="text-xs mt-1 text-amber-800">{{ registrationBlockDetail }}</p>
            </div>

            <div v-else-if="!examHasFee" class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                <p class="font-semibold">You can register students now</p>
                <p class="text-xs mt-1 text-sky-800">
                    Per-student fee is not set yet — register first, then pay the batch amount on
                    <Link :href="`${base}/fee`" class="link-brand font-semibold">Fee & payment</Link>
                    once Sahodaya configures the fee. Sahodaya approves payment before hall tickets are issued.
                </p>
            </div>

            <div class="grid lg:grid-cols-3 gap-4">
                <div class="lg:col-span-2 space-y-4">
                    <div class="card space-y-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h3 class="section-title !mb-0">Register one student</h3>
                                <p class="text-xs text-slate-500 mt-1">Pick from eligible students not yet registered for this exam.</p>
                            </div>
                        </div>
                        <form @submit.prevent="registerStudent" class="flex flex-wrap gap-2 items-end">
                            <div class="min-w-[220px] flex-1 max-w-md">
                                <select v-model="studentId" class="field w-full" :disabled="!canRegister || !availableStudents.length" required>
                                    <option value="">Choose eligible student…</option>
                                    <option v-for="s in availableStudents" :key="s.id" :value="s.id">
                                        {{ s.name }}<template v-if="s.reg_no"> · {{ s.reg_no }}</template><template v-if="s.class_name"> · {{ s.class_name }}</template>
                                    </option>
                                </select>
                            </div>
                            <button type="submit" class="btn-primary" :disabled="!canRegister || !studentId">Register</button>
                        </form>
                    </div>

                    <div v-if="classOptions.length" class="card space-y-3">
                        <h3 class="section-title !mb-0">Bulk register by class</h3>
                        <p class="text-xs text-slate-500">Registers all eligible students in the class who are not already on this exam.</p>
                        <div class="flex flex-wrap gap-2 items-end">
                            <div class="min-w-[220px] flex-1 max-w-md">
                                <select v-model="classId" class="field w-full" :disabled="!canRegister">
                                    <option value="">Choose class…</option>
                                    <option v-for="c in classOptions" :key="c.id" :value="c.id" :disabled="!c.eligible_count">
                                        {{ c.name }}<template v-if="c.eligible_count"> ({{ c.eligible_count }} eligible)</template><template v-else> (none left)</template>
                                    </option>
                                </select>
                            </div>
                            <button type="button" class="btn-secondary" :disabled="!canRegister || !classId || !selectedClassEligible" @click="bulkRegister">
                                Register class
                            </button>
                        </div>
                    </div>

                    <div class="card card--flush overflow-hidden">
                        <div class="p-4 border-b border-slate-100 flex flex-wrap gap-3 items-center justify-between">
                            <div>
                                <h3 class="section-title !mb-0">Eligible students</h3>
                                <p class="text-xs text-slate-500 mt-0.5">{{ exam.eligibility_summary || 'All active students in eligible classes' }}</p>
                            </div>
                            <input v-model="studentSearch" type="search" class="field max-w-xs text-sm" placeholder="Search name or reg. no…">
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Reg. no.</th>
                                    <th>Class</th>
                                    <th>Portal</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="s in filteredStudents" :key="s.id">
                                    <td class="font-medium">{{ s.name }}</td>
                                    <td class="font-mono text-xs">{{ s.reg_no || '—' }}</td>
                                    <td class="text-xs">{{ s.class_name || '—' }}</td>
                                    <td class="text-xs">{{ s.has_portal_login ? 'Has login' : 'New on register' }}</td>
                                    <td>
                                        <span v-if="s.registered" class="text-xs font-semibold text-emerald-700">Registered</span>
                                        <span v-else class="text-xs text-slate-500">Not registered</span>
                                    </td>
                                    <td class="text-right">
                                        <button v-if="!s.registered && canRegister" type="button" class="link-brand text-xs font-semibold"
                                                @click="registerStudentById(s.id)">Register</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <EmptyState v-if="!filteredStudents.length" title="No eligible students" description="Check class/gender rules or Level 2 qualification." icon="👥" class="py-8" />
                    </div>
                </div>

                <aside class="space-y-4">
                    <div class="card space-y-3 bg-indigo-50/40 border-indigo-100">
                        <h3 class="section-title !mb-0">Student portal logins</h3>
                        <p class="text-xs text-slate-600 leading-relaxed">
                            Each newly registered student gets a portal account automatically:
                        </p>
                        <ul class="text-xs text-slate-700 space-y-2 list-disc list-inside">
                            <li><strong>Username</strong> = student reg. no.</li>
                            <li><strong>Password</strong> = random temp password (shown once after you register)</li>
                            <li>Students must change password on first login</li>
                        </ul>
                        <p class="text-xs text-slate-600">
                            Portal URL:
                            <a :href="portalLoginUrl" target="_blank" rel="noopener" class="link-brand font-mono break-all">{{ portalLoginUrl }}</a>
                        </p>
                    </div>

                    <div class="card space-y-2">
                        <h3 class="section-title !mb-0">After registering</h3>
                        <ol class="text-xs text-slate-600 space-y-2 list-decimal list-inside">
                            <li>Register students on this tab</li>
                            <li>Upload batch fee proof on <Link :href="`${base}/fee`" class="link-brand">Fee & payment</Link> (after Sahodaya sets fee amount)</li>
                            <li>Sahodaya verifies payment and approves registrations</li>
                            <li>Download hall tickets from <Link :href="`${base}/hall-tickets`" class="link-brand">Hall tickets</Link></li>
                        </ol>
                    </div>

                    <div v-if="registrations.length" class="card space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="section-title !mb-0">Registered ({{ registrations.length }})</h3>
                            <Link :href="`${base}/students`" class="link-brand text-xs font-semibold">View all →</Link>
                        </div>
                        <div class="space-y-2 max-h-48 overflow-y-auto">
                            <div v-for="r in registrations.slice(0, 8)" :key="r.id"
                                 class="flex justify-between gap-2 text-sm border border-slate-100 rounded-lg px-3 py-2">
                                <span class="truncate">{{ r.student?.name }}</span>
                                <span class="text-xs capitalize shrink-0 text-slate-500">{{ r.approval_status_label || r.approval_status }}</span>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        <!-- Students tab -->
        <div v-else-if="tab === 'students'" class="card card--flush overflow-hidden">
            <div class="p-4 border-b border-slate-100">
                <h3 class="section-title !mb-0">Registered students</h3>
                <p class="text-xs text-slate-500 mt-1">{{ registrations.length }} student(s) · portal username is their reg. no.</p>
            </div>
            <table class="data-table">
                <thead><tr><th>Student</th><th>Portal username</th><th>Approval</th><th>Exam reg. no.</th><th>Seat</th><th>Status</th></tr></thead>
                <tbody>
                    <tr v-for="r in registrations" :key="r.id">
                        <td>{{ r.student?.name }}</td>
                        <td class="font-mono text-xs">{{ r.portal_username || r.student?.reg_no || '—' }}</td>
                        <td><span class="text-xs capitalize">{{ r.approval_status_label || r.approval_status }}</span></td>
                        <td class="font-mono text-xs">{{ r.hall_ticket_no || '—' }}</td>
                        <td>{{ r.seat_no || '—' }}</td>
                        <td class="text-xs capitalize">{{ r.status }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-if="!registrations.length" title="No registrations yet" description="Register students from the Register tab." icon="👥" class="py-8" />
        </div>

        <!-- Hall tickets tab -->
        <div v-else-if="tab === 'hall-tickets'" class="space-y-4">
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
                <div class="card card--muted !py-3 text-center">
                    <p class="text-lg font-bold text-indigo-700">{{ ticketsIssuedCount }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Tickets issued</p>
                </div>
                <div class="card card--muted !py-3 text-center">
                    <p class="text-lg font-bold">{{ registrations.length - ticketsIssuedCount }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Pending issue</p>
                </div>
                <div class="card card--muted !py-3 text-center lg:col-span-1 col-span-2">
                    <p class="text-sm font-semibold capitalize">{{ schoolFee?.status?.replace('_', ' ') || 'No fee batch' }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Fee status</p>
                </div>
            </div>
            <div class="card p-4 space-y-3">
                <p class="text-sm text-slate-700">
                    Hall tickets are generated by Sahodaya after your batch fee is approved. Exam registration numbers appear on each ticket.
                </p>
                <div class="flex flex-wrap gap-2">
                    <a v-if="ticketsIssuedCount" :href="pdfUrl" target="_blank" class="btn-primary text-sm">Download hall tickets PDF</a>
                    <Link v-if="schoolFee?.status !== 'approved'" :href="`${base}/fee`" class="btn-secondary text-sm">Check fee status</Link>
                </div>
                <p v-if="!ticketsIssuedCount" class="text-sm text-amber-700">No hall tickets yet — complete fee payment and wait for Sahodaya approval.</p>
            </div>
        </div>

        <!-- Fee tab -->
        <div v-else-if="tab === 'fee'" class="space-y-4">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="card card--muted !py-3 text-center">
                    <p class="text-lg font-bold" :class="examHasFee ? 'text-emerald-700' : 'text-amber-700'">{{ feeLabel }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Per student</p>
                </div>
                <div class="card card--muted !py-3 text-center">
                    <p class="text-lg font-bold">{{ schoolFee?.student_count ?? registerStats.registered ?? 0 }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Students</p>
                </div>
                <div class="card card--muted !py-3 text-center">
                    <p class="text-lg font-bold">{{ batchDueLabel }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Total due</p>
                </div>
                <div class="card card--muted !py-3 text-center">
                    <p class="text-sm font-semibold capitalize">{{ schoolFee?.status?.replace('_', ' ') || 'Not calculated' }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-slate-500 mt-1">Status</p>
                </div>
            </div>
            <div class="card p-4 space-y-4">
                <p class="text-sm text-slate-600">
                    Pay the total batch amount to Sahodaya and upload proof here. After verification, registrations are confirmed and hall tickets are issued.
                </p>
                <p v-if="!examHasFee && registrations.length" class="text-sm text-sky-800 bg-sky-50 border border-sky-100 rounded-lg px-3 py-2">
                    Batch fee total will be calculated when Sahodaya sets the per-student exam fee.
                </p>
                <p v-else-if="!registrations.length" class="text-sm text-amber-700">Register students first, then upload payment here.</p>
                <form v-else-if="examHasFee && schoolFee && schoolFee.total_due > 0 && schoolFee.status !== 'approved'"
                      @submit.prevent="uploadBatchFee" class="flex flex-wrap gap-2 items-end border-t border-slate-100 pt-4">
                    <div>
                        <label class="text-xs font-semibold text-slate-600 block mb-1">Payment proof</label>
                        <input ref="proofInput" type="file" accept=".pdf,.jpg,.jpeg,.png" class="text-sm" required>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-600 block mb-1">Transaction ref (optional)</label>
                        <input v-model="transactionRef" class="field max-w-xs text-sm" placeholder="UTR / ref no.">
                    </div>
                    <button type="submit" class="btn-primary text-sm">Upload proof</button>
                </form>
                <p v-else-if="schoolFee?.status === 'approved'" class="text-sm font-semibold text-emerald-700">Fee approved — hall tickets can be issued by Sahodaya.</p>
                <p v-else-if="schoolFee?.status === 'proof_uploaded'" class="text-sm text-amber-800">Proof uploaded — awaiting Sahodaya verification.</p>
            </div>
        </div>

        <!-- Results tab -->
        <div v-else-if="tab === 'results'" class="card card--flush overflow-hidden">
            <div class="p-4 border-b border-slate-100">
                <h3 class="section-title !mb-0">Exam results</h3>
                <p class="text-xs text-slate-500 mt-1">Published by Sahodaya for your registered students.</p>
            </div>
            <table class="data-table">
                <thead><tr><th>Student</th><th>Score</th><th>Rank</th><th>Grade</th></tr></thead>
                <tbody>
                    <tr v-for="r in registrations" :key="r.id">
                        <td>{{ r.student?.name }}</td>
                        <td>{{ r.mark?.score ?? '—' }}</td>
                        <td>{{ r.mark?.rank ?? '—' }}</td>
                        <td>{{ r.mark?.grade ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-if="!registrations.length" title="No results" description="No registered students for this exam." icon="📊" class="py-8" />
        </div>

        <!-- Toppers tab -->
        <div v-else-if="tab === 'toppers'" class="card card--flush overflow-hidden">
            <div class="p-4 border-b border-slate-100">
                <h3 class="section-title !mb-0">School toppers</h3>
                <p class="text-xs text-slate-500 mt-1">Top performers from your school in this exam.</p>
            </div>
            <table class="data-table">
                <thead><tr><th>Rank</th><th>Student</th><th>Class</th><th>Score</th><th>Grade</th></tr></thead>
                <tbody>
                    <tr v-for="(t, i) in toppers" :key="i">
                        <td class="font-semibold">{{ t.rank ?? '—' }}</td>
                        <td>{{ t.name }} <span class="text-slate-400 text-xs">{{ t.reg_no }}</span></td>
                        <td class="text-xs">{{ t.class_name || '—' }}</td>
                        <td>{{ t.score ?? '—' }}</td>
                        <td>{{ t.grade ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-if="!toppers.length" title="No toppers yet" description="Results must be published by Sahodaya." icon="🏆" class="py-8" />
        </div>

        <!-- Reports tab -->
        <div v-else-if="tab === 'reports'" class="space-y-4">
            <div class="grid md:grid-cols-2 gap-4">
                <div class="card">
                    <h3 class="section-title">Registration register</h3>
                    <p class="section-desc">Your school's registrations with hall tickets and approval status.</p>
                    <a :href="reportExports.registration" class="btn-secondary text-sm mt-3 inline-block">Export Excel ↓</a>
                </div>
                <div class="card">
                    <h3 class="section-title">Attendance sheet</h3>
                    <p class="section-desc">Hall ticket list for exam-day attendance.</p>
                    <a :href="reportExports.attendance" class="btn-secondary text-sm mt-3 inline-block">Export Excel ↓</a>
                </div>
            </div>
            <div class="card card--flush overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <h3 class="section-title !mb-0">Preview ({{ reportRows.length }})</h3>
                </div>
                <table class="data-table">
                    <thead><tr><th>Hall ticket</th><th>Student</th><th>Class</th><th>Approval</th><th>Attendance</th></tr></thead>
                    <tbody>
                        <tr v-for="(row, i) in reportRows.slice(0, 50)" :key="i">
                            <td>{{ row.hall_ticket_no || '—' }}</td>
                            <td>{{ row.student_name }}</td>
                            <td class="text-xs">{{ row.class_name || '—' }}</td>
                            <td class="text-xs">{{ row.approval_status }}</td>
                            <td class="text-xs">{{ row.attendance_status || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import SchoolMcqSubNav from '@/Components/school/SchoolMcqSubNav.vue';
import McqSchoolWorkflowStepper from '@/Components/school/McqSchoolWorkflowStepper.vue';
import StudentPortalCredentialsBanner from '@/Components/school/StudentPortalCredentialsBanner.vue';

const props = defineProps({
    school: Object,
    exam: Object,
    tab: { type: String, default: 'register' },
    registrations: { type: Array, default: () => [] },
    schoolFee: Object,
    students: { type: Array, default: () => [] },
    classOptions: { type: Array, default: () => [] },
    registeredStudentIds: { type: Array, default: () => [] },
    ticketsIssuedCount: { type: Number, default: 0 },
    studentPortalCredentials: { type: Array, default: null },
    registerStats: { type: Object, default: () => ({}) },
    portalLoginUrl: { type: String, default: '/portal/login' },
    reportRows: { type: Array, default: () => [] },
    toppers: { type: Array, default: () => [] },
    reportExports: { type: Object, default: () => ({}) },
});

const studentId = ref('');
const classId = ref('');
const studentSearch = ref('');
const proofInput = ref(null);
const transactionRef = ref('');
const base = computed(() => `/school-admin/${props.school.id}/mcq/${props.exam.id}`);
const pdfUrl = computed(() => `${base.value}/hall-tickets/pdf`);

const availableStudents = computed(() => props.students.filter(s => !s.registered));

const filteredStudents = computed(() => {
    const q = studentSearch.value.trim().toLowerCase();
    if (!q) return props.students;
    return props.students.filter(s =>
        s.name?.toLowerCase().includes(q)
        || s.reg_no?.toLowerCase().includes(q)
        || s.class_name?.toLowerCase().includes(q),
    );
});

const selectedClassEligible = computed(() => {
    if (!classId.value) return 0;
    const c = props.classOptions.find(x => String(x.id) === String(classId.value));
    return c?.eligible_count ?? 0;
});

const examHasFee = computed(() => Boolean(props.exam?.has_fee) || (Number(props.exam?.fee_amount) > 0 && (props.exam?.fee_type ?? 'none') !== 'none'));
const canRegister = computed(() => props.registerStats?.can_register ?? props.exam?.registration_open !== false);

const feeLabel = computed(() => {
    if (props.exam?.fee_label) return props.exam.fee_label;
    if (!examHasFee.value) return 'Not set';
    const amount = Number(props.exam.fee_amount);
    return amount % 1 === 0 ? `₹${amount}` : `₹${amount.toFixed(2)}`;
});

const batchDueLabel = computed(() => {
    const due = props.registerStats?.batch_due ?? props.schoolFee?.total_due ?? 0;
    if (!due) return '₹0';
    return due % 1 === 0 ? `₹${due}` : `₹${Number(due).toFixed(2)}`;
});

const registrationBlockTitle = computed(() => 'Registration closed');

const registrationBlockDetail = computed(() => {
    if (props.exam?.registration_open !== false && ['published', 'ongoing'].includes(props.exam?.status)) {
        return '';
    }
    return 'This exam is not open for registration (status: ' + (props.exam?.status_label || props.exam?.status) + ').';
});

const examHeaderDesc = computed(() => {
    const parts = [];
    if (props.exam?.scheduled_at_label) parts.push(props.exam.scheduled_at_label);
    else if (props.exam?.scheduled_at) {
        parts.push(new Date(props.exam.scheduled_at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }));
    }
    if (examHasFee.value) parts.push(`${feeLabel.value} per student`);
    if (props.exam?.eligibility_summary) parts.push(props.exam.eligibility_summary);
    return parts.length ? parts.join(' · ') : 'Register students, pay batch fee, download hall tickets.';
});

function statusClass(status) {
    if (status === 'published' || status === 'ongoing') return 'status-pill--published';
    if (status === 'completed') return 'status-pill--success';
    return 'status-pill--draft';
}

function registerStudentById(id) {
    router.post(`${base.value}/register`, { student_id: id }, { preserveScroll: true });
}

function registerStudent() {
    router.post(`${base.value}/register`, { student_id: studentId.value }, {
        preserveScroll: true,
        onSuccess: () => { studentId.value = ''; },
    });
}

function bulkRegister() {
    router.post(`${base.value}/register-by-class`, {
        school_class_id: classId.value,
    }, { preserveScroll: true });
}

function uploadBatchFee() {
    const file = proofInput.value?.files?.[0];
    if (!file) return;
    router.post(`${base.value}/school-payment`, {
        payment_proof: file,
        transaction_ref: transactionRef.value || null,
    }, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            transactionRef.value = '';
            if (proofInput.value) proofInput.value.value = '';
        },
    });
}
</script>
