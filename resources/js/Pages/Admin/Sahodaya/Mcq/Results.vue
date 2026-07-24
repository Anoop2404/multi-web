<template>
    <SahodayaAdminLayout :title="`Results — ${exam.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="Talent Search exam"
                    description="Verify school fee payments to confirm registrations and issue hall tickets, then enter marks.">
            <template #actions>
                <a :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/certificates/preview`"
                   target="_blank" rel="noopener" class="btn-secondary text-sm">Sample certificate ↗</a>
                <a :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/hall-tickets/preview`"
                   target="_blank" rel="noopener" class="btn-secondary text-sm">Sample hall ticket ↗</a>
            </template>
        </PageHeader>
        <McqExamSubNav :sahodaya-id="sahodaya.id" :exam-id="exam.id" :delivery-mode="exam.delivery_mode || 'offline'" :results-published="!!exam.results_published" active="results" />

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ registrations.length }}</p>
                <p class="text-xs text-slate-500 mt-1">Registrations</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ presentCount }}</p>
                <p class="text-xs text-slate-500 mt-1">Present</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ markedCount }}</p>
                <p class="text-xs text-slate-500 mt-1">Marks entered</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold" :class="exam.results_published ? 'text-emerald-700' : 'text-amber-700'">
                    {{ exam.results_published ? 'Published' : 'Draft' }}
                </p>
                <p class="text-xs text-slate-500 mt-1">Results</p>
            </div>
        </div>

        <div class="card mb-6 flex flex-wrap items-center gap-3"
             :class="exam.results_published ? 'card--accent !border-emerald-200' : 'card--muted'">
            <div class="flex-1 text-sm">
                <p class="font-semibold" :class="exam.results_published ? 'text-emerald-900' : 'text-amber-900'">
                    {{ exam.results_published ? 'Results published' : 'Results not published' }}
                </p>
                <p class="text-xs text-slate-600 mt-0.5">Schools {{ exam.results_published ? 'can' : 'cannot' }} view scores in their portal.</p>
                <p v-if="exam.results_published" class="text-xs text-amber-700 mt-1">Marks are locked while results are published. Unpublish to reopen for correction.</p>
                <p v-if="gradeBands?.length" class="text-xs text-slate-500 mt-1">
                    Grade bands: {{ gradeBands.map(b => `${b.label} (${b.min_percentage}-${b.max_percentage}%)`).join(' · ') }}
                </p>
            </div>
            <a :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/result-sheet.pdf`" target="_blank" class="btn-secondary text-sm">Result sheet PDF ↓</a>
            <a :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/mark-sheet.pdf`" target="_blank" class="btn-secondary text-sm">Mark sheet PDF ↓</a>
            <button v-if="!exam.results_published" type="button" @click="publishResults" class="btn-primary text-sm">Publish results</button>
            <button v-else type="button" @click="unpublishResults" class="btn-secondary text-sm">Unpublish (reopen for correction)</button>
            <button v-if="exam.results_published" type="button" @click="generateCertificates" class="btn-secondary text-sm">Generate certificates</button>
        </div>

        <div v-if="!exam.results_published" class="card mb-6 space-y-3">
            <h3 class="section-title !mb-0">Bulk import marks</h3>
            <p class="text-xs text-slate-500">CSV or Excel: hall_ticket_no, correct, wrong, unanswered. Optional: score, marks_per_correct, negative_per_wrong.</p>
            <form @submit.prevent="importMarks" class="flex flex-wrap gap-2 items-center">
                <input ref="marksFile" type="file" accept=".csv,.txt,.xlsx,.xls" class="text-sm" required>
                <button type="submit" class="btn-secondary text-sm">Import marks (CSV/Excel)</button>
            </form>
            <div v-if="importErrors?.length" class="text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-lg p-3 space-y-1">
                <p class="font-semibold">Import issues ({{ importErrors.length }})</p>
                <ul class="list-disc pl-5 text-xs space-y-0.5 max-h-40 overflow-y-auto">
                    <li v-for="(err, i) in importErrors.slice(0, 20)" :key="i">Row {{ err.row }}: {{ err.message }}</li>
                </ul>
            </div>
        </div>

        <section class="card overflow-hidden !p-0">
            <div class="p-4 border-b border-slate-100 flex flex-wrap gap-3 items-center justify-between">
                <div>
                    <h3 class="section-title !mb-0">Mark entry</h3>
                    <p class="section-desc">Offline scores after attendance is marked.</p>
                </div>
                <input v-model="regSearch" type="search" class="field max-w-xs" placeholder="Search student or ticket…">
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>School</th>
                            <th>Reg. no.</th>
                            <th>Approval</th>
                            <th>Attendance</th>
                            <th>Fee</th>
                            <th>Score</th>
                            <th>Grade</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in filteredRegistrations" :key="r.id" :class="['absent','malpractice','withheld'].includes(r.attendance_status) ? 'opacity-60' : ''">
                            <td>{{ r.student?.name || ('#' + r.student_id) }}</td>
                            <td class="text-xs">{{ r.school?.name || '—' }}</td>
                            <td class="font-mono text-xs">{{ r.hall_ticket_no || '—' }}</td>
                            <td class="text-xs whitespace-nowrap">
                                <span :class="approvalClass(r.approval_status)">{{ approvalLabel(r.approval_status) }}</span>
                            </td>
                            <td class="text-xs capitalize">{{ r.attendance_status || 'pending' }}</td>
                            <td class="text-xs whitespace-nowrap">
                                <template v-if="r.approval_status === 'approved' || r.fee_receipt?.status === 'approved'"><span class="text-green-700 font-semibold">Paid</span></template>
                                <template v-else-if="r.fee_receipt?.status === 'uploaded'">
                                    <a :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/registrations/${r.id}/fee/proof`" target="_blank" class="link-brand">Proof</a>
                                    <button type="button" @click="approveFee(r.id)" class="text-green-600 font-semibold ml-2">Approve</button>
                                    <button type="button" @click="rejectFee(r.id)" class="text-red-600 font-semibold ml-1">Reject</button>
                                </template>
                                <span v-else class="text-gray-400">Pending</span>
                                <a :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/registrations/${r.id}/invoice`"
                                   class="link-brand ml-2" target="_blank">Invoice</a>
                            </td>
                            <td>
                                <input v-model.number="markForms[r.id].score" type="number" min="0" step="0.01"
                                       class="field w-20" :disabled="!canEnterMarks(r)" :aria-label="`Score for ${r.student?.name || r.id}`">
                            </td>
                            <td>
                                <select v-model="markForms[r.id].grade" class="field w-16" :disabled="!canEnterMarks(r)" :aria-label="`Grade for ${r.student?.name || r.id}`">
                                    <option value="">—</option>
                                    <option v-for="g in gradeOptions" :key="g" :value="g">{{ g }}</option>
                                </select>
                            </td>
                            <td class="text-xs whitespace-nowrap">
                                <button v-if="canEnterMarks(r)" type="button" @click="saveMark(r)" class="link-brand text-xs">Save</button>
                                <span v-else-if="exam.results_published" class="text-amber-700">Results published</span>
                                <span v-else-if="['absent','malpractice','withheld'].includes(r.attendance_status)" class="text-red-600 capitalize">{{ r.attendance_status }}</span>
                                <span v-else class="text-slate-400">Mark present first</span>
                                <a v-if="exam.results_published && r.mark && r.attendance_status !== 'absent'"
                                   :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/registrations/${r.id}/certificate`"
                                   target="_blank" class="link-brand ml-2">Cert</a>
                                <button v-if="canCancelRegistration(r)" type="button" @click="cancelRegistration(r.id)" class="text-red-600 font-semibold text-xs ml-3">Cancel</button>
                            </td>
                        </tr>
                        <tr v-if="!filteredRegistrations.length">
                            <td :colspan="9" class="p-6 text-center text-slate-400">No matching registrations.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';

const props = defineProps({ sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number, exam: Object, registrations: Array, gradeBands: { type: Array, default: () => [] } });

const regSearch = ref('');
const marksFile = ref(null);
const page = usePage();
const importErrors = computed(() => page.props.flash?.import_errors ?? []);

const markForms = reactive({});
for (const r of props.registrations) {
    markForms[r.id] = {
        correct_count: r.mark?.correct_count ?? 0,
        wrong_count: r.mark?.wrong_count ?? 0,
        unanswered_count: r.mark?.unanswered_count ?? 0,
        score: r.mark?.score ?? 0,
        grade: r.mark?.grade ?? '',
    };
}

const presentCount = computed(() => props.registrations.filter((r) => r.attendance_status === 'present').length);
const markedCount = computed(() => props.registrations.filter((r) => r.mark?.score != null).length);
const gradeOptions = computed(() => props.gradeBands?.length ? props.gradeBands.map((b) => b.label) : ['A+', 'A', 'B', 'C', 'D', 'F']);

function canEnterMarks(r) {
    return r.attendance_status === 'present' && !props.exam.results_published;
}

const filteredRegistrations = computed(() => {
    const q = regSearch.value.trim().toLowerCase();
    if (!q) return props.registrations;
    return props.registrations.filter((r) =>
        [r.student?.name, r.hall_ticket_no, r.school?.name].filter(Boolean).join(' ').toLowerCase().includes(q),
    );
});

function importMarks() {
    const file = marksFile.value?.files?.[0];
    if (!file) return;
    const form = new FormData();
    form.append('file', file);
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/marks/bulk-import`, form, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { if (marksFile.value) marksFile.value.value = ''; },
    });
}

function saveMark(registration) {
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/registrations/${registration.id}/marks`,
        markForms[registration.id],
        { preserveScroll: true },
    );
}

function publishResults() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/publish-results`, {}, { preserveScroll: true });
}

function unpublishResults() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/unpublish-results`, {}, { preserveScroll: true });
}

function generateCertificates() {
    if (!confirm('Generate certificates for all eligible students with published results?')) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/certificates/generate`, {}, { preserveScroll: true });
}

function approveFee(registrationId) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/registrations/${registrationId}/fee/approve`, {}, { preserveScroll: true });
}

function rejectFee(registrationId) {
    const reason = prompt('Rejection reason (optional):');
    if (reason === null) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/registrations/${registrationId}/fee/reject`, { rejection_reason: reason }, { preserveScroll: true });
}

function canCancelRegistration(r) {
    return !['started', 'submitted', 'cancelled'].includes(r.status);
}

function cancelRegistration(registrationId) {
    const reason = prompt('Reason for cancelling this registration (required):');
    if (!reason) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/registrations/${registrationId}/cancel`, { reason }, { preserveScroll: true });
}

function approvalLabel(status) {
    return ({
        pending_payment: 'Pending payment',
        pending_approval: 'Pending approval',
        approved: 'Approved',
        rejected: 'Rejected',
    })[status] || status;
}

function approvalClass(status) {
    if (status === 'approved') return 'text-green-700 font-semibold';
    if (status === 'rejected') return 'text-red-700 font-semibold';
    return 'text-amber-700';
}
</script>
