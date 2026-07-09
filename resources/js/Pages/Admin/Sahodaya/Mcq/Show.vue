<template>
    <SahodayaAdminLayout :title="exam.title" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="Talent Search exam"
                    :description="`${registrations.length} registrations · ${exam.status}`">
            <template #actions>
                <span v-if="exam.series_title" class="text-xs text-slate-500 mr-2">{{ exam.series_title }}</span>
                <a :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/hall-tickets/preview`"
                   target="_blank" rel="noopener" class="btn-secondary text-sm">Sample hall ticket ↗</a>
                <a :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/certificates/preview`"
                   target="_blank" rel="noopener" class="btn-secondary text-sm">Sample certificate ↗</a>
                <a :href="`/portal/exam/${sahodaya.id}`" target="_blank" rel="noopener" class="btn-secondary text-sm">Exam portal ↗</a>
            </template>
        </PageHeader>

        <div v-if="exam.level_label" class="flex flex-wrap gap-2 mb-4">
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-indigo-100 text-indigo-800">{{ exam.level_label }}</span>
            <span class="text-xs px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 capitalize">{{ exam.exam_type_label }}</span>
            <span v-if="(exam.exam_level ?? 1) > 1" class="text-xs px-2.5 py-1 rounded-full bg-amber-50 text-amber-800">
                Promotion: {{ exam.eligibility_mode_label }}
            </span>
            <span v-if="exam.parent_exam_title" class="text-xs px-2.5 py-1 rounded-full bg-slate-50 text-slate-600">
                After: {{ exam.parent_exam_title }}
            </span>
        </div>

        <McqExamSubNav :sahodaya-id="sahodaya.id" :exam-id="exam.id" :delivery-mode="exam.delivery_mode || 'offline'" :results-published="!!exam.results_published" active="overview" />

        <McqSahodayaWorkflowBanner
            :sahodaya-id="sahodaya.id"
            :exam-id="exam.id"
            :exam="exam"
            :pending-payment-approvals="pendingPaymentApprovals"
            :tickets-issued-count="exam.tickets_issued_count ?? 0"
            :registration-count="registrations.length"
        />

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ registrations.length }}</p>
                <p class="text-xs text-slate-500 mt-1">Registrations</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-amber-700">{{ pendingApprovalCount }}</p>
                <p class="text-xs text-slate-500 mt-1">Pending payment</p>
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

        <p class="text-sm text-slate-600 mb-4">
            <a :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/results`" class="link-brand">
                Manage marks and publish results → Results & marks
            </a>
            ·
            <a :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/payments`" class="link-brand">
                Approve school batch fees → Payments
            </a>
        </p>

        <form @submit.prevent="saveLedgerAccount" class="card mb-6 space-y-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="section-title">Ledger account</h3>
                    <p class="section-desc text-xs">Verified Talent Search fees credit this exam’s own income head (code: {{ ledgerAccount?.code }}).</p>
                </div>
                <Link v-if="ledgerAccount?.ledger_url" :href="ledgerAccount.ledger_url" class="btn-secondary text-sm">View ledger →</Link>
            </div>
            <div class="flex flex-wrap gap-2 items-end">
                <FormField label="Account name" class-extra="mb-0 flex-1 min-w-[14rem]">
                    <template #default="{ id }">
                        <input :id="id" v-model="ledgerForm.name" class="field" required>
                    </template>
                </FormField>
                <button type="submit" class="btn-secondary text-sm mb-0.5" :disabled="ledgerForm.processing">Save account name</button>
            </div>
        </form>

        <form @submit.prevent="save" class="card mb-6 space-y-4">
            <h3 class="section-title">Exam details</h3>
            <FormGrid>
                <FormField label="Exam title" class-extra="sm:col-span-2" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="form.title" class="field" required>
                    </template>
                </FormField>
                <FormField label="Scheduled at">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.scheduled_at" type="datetime-local" class="field">
                    </template>
                </FormField>
                <FormField label="Status">
                    <template #default="{ id }">
                        <select :id="id" v-model="form.status" class="field">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="completed">Completed</option>
                        </select>
                    </template>
                </FormField>
                <FormField label="Delivery mode">
                    <template #default="{ id }">
                        <select :id="id" v-model="form.delivery_mode" class="field">
                            <option value="offline">Offline (paper / venue)</option>
                            <option value="online">Online (student portal)</option>
                        </select>
                    </template>
                </FormField>
                <p v-if="form.delivery_mode === 'online'" class="text-xs text-indigo-700 sm:col-span-2">
                    Online exams require question banks for auto-grading. Link banks under the Question banks tab before exam day.
                </p>
                <CheckboxField
                    v-if="form.delivery_mode === 'online'"
                    v-model="form.requires_hall_ticket"
                    label="Require hall ticket before starting"
                    hint="When enabled, students must have a hall ticket issued and attendance marked present before the online exam can start."
                    class="sm:col-span-2"
                />
                <p v-else class="text-xs text-slate-500 sm:col-span-2">
                    Offline exams use hall tickets and manual mark entry. Question banks are optional.
                </p>
                <FormField label="Per-student fee (₹)" hint="Required when status is Published or Ongoing">
                    <template #default="{ id }">
                        <input :id="id" v-model.number="form.fee_amount" type="number" min="0" step="0.01" class="field" placeholder="0">
                    </template>
                </FormField>
                <FormField label="School discount (₹)" hint="Amount Sahodaya discounts per student — school remits fee minus discount">
                    <template #default="{ id }">
                        <input :id="id" v-model.number="form.school_discount_amount" type="number" min="0" step="0.01" class="field" placeholder="0">
                    </template>
                </FormField>
                <p class="text-xs text-slate-500 sm:col-span-2">Example: ₹150 student fee with ₹30 discount → school pays ₹120 per student to Sahodaya.</p>
                <FormField label="Reg. no. starts at" hint="First hall-ticket number when tickets are issued. Use presets or enter any number from 1. Locked after any ticket is issued.">
                    <template #default="{ id }">
                        <McqRegNoStartField :input-id="id" v-model="form.next_hall_ticket_no" :disabled="exam.tickets_issued" />
                    </template>
                </FormField>
            </FormGrid>

            <div class="border-t border-slate-100 pt-4">
                <FormField label="Student verification for registration" class-extra="mb-4">
                    <template #default="{ id }">
                        <select :id="id" v-model="form.student_verification_mode" class="field">
                            <option value="inherit">
                                Use cluster default — {{ clusterRequireStudentVerification ? 'verified students only' : 'unverified allowed' }}
                            </option>
                            <option value="required">Require verified students only</option>
                            <option value="optional">Allow unverified students</option>
                        </select>
                        <p class="text-xs text-slate-500 mt-1">
                            Cluster default is set under Membership → Settings. Applies to school registration for this exam.
                        </p>
                    </template>
                </FormField>
                <McqEligibilityPicker v-model="form.eligibility_config" :class-categories="classCategories" :master-classes="masterClasses" />
            </div>

            <h3 class="section-title pt-2">Templates & grading</h3>
            <FormGrid>
                <FormField label="Grade master">
                    <template #default="{ id }">
                        <select :id="id" v-model="form.grade_master_id" class="field">
                            <option value="">Default Sahodaya grade master</option>
                            <option v-for="m in gradeMasters" :key="m.id" :value="m.id">{{ m.title }}<template v-if="m.is_default"> (default)</template></option>
                        </select>
                    </template>
                </FormField>
                <FormField label="Hall ticket template">
                    <template #default="{ id }">
                        <select :id="id" v-model="form.hall_ticket_template_id" class="field">
                            <option value="">Default / per-exam design</option>
                            <option v-for="t in hallTicketTemplates" :key="t.id" :value="t.id">{{ t.title }}</option>
                        </select>
                    </template>
                </FormField>
                <FormField label="Certificate template">
                    <template #default="{ id }">
                        <select :id="id" v-model="form.certificate_template_id" class="field">
                            <option value="">Default certificate template</option>
                            <option v-for="t in certificateTemplates" :key="t.id" :value="t.id">{{ t.title }}</option>
                        </select>
                    </template>
                </FormField>
            </FormGrid>
            <p v-if="gradeBands?.length" class="text-xs text-slate-500">Active grade bands: {{ gradeBands.map(b => b.label).join(', ') }}</p>

            <h3 class="section-title pt-2">Hall tickets</h3>
            <p class="section-desc mb-3">
                Logo, colors, layout, and admit-card preview are on the
                <a :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/hall-tickets`" class="link-brand">Hall tickets</a>
                tab. Reg. no. starting value is set above and shared with that page.
            </p>
            <FormActions>
                <button type="submit" class="btn-primary" :disabled="form.processing">Save exam</button>
            </FormActions>
        </form>

        <form @submit.prevent="uploadPaper" class="card mb-6 space-y-4">
            <h3 class="section-title">Question paper archive</h3>
            <p class="text-sm text-slate-600">
                Upload a PDF for the public
                <a :href="publicMcqPapersUrl" target="_blank" rel="noopener" class="link-brand">question paper archive ↗</a>.
            </p>
            <p v-if="exam.question_paper_path" class="text-sm text-emerald-700">
                Published: {{ exam.question_paper_label || exam.title }}
            </p>
            <FormField label="Archive label">
                <template #default="{ id }">
                    <input :id="id" v-model="paperForm.question_paper_label" class="field" placeholder="Optional display name">
                </template>
            </FormField>
            <FormField label="PDF file">
                <input type="file" accept="application/pdf" class="field text-sm" @change="onPaperFile">
            </FormField>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn-primary text-sm" :disabled="!paperFile || paperForm.processing">Upload question paper</button>
                <button v-if="exam.question_paper_path" type="button" class="btn-secondary text-sm" @click="removePaper">Remove from archive</button>
            </div>
        </form>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';
import McqEligibilityPicker from '@/Components/sahodaya/McqEligibilityPicker.vue';
import McqRegNoStartField from '@/Components/sahodaya/McqRegNoStartField.vue';
import McqSahodayaWorkflowBanner from '@/Components/sahodaya/McqSahodayaWorkflowBanner.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    exam: Object,
    registrations: Array,
    schoolFees: { type: Array, default: () => [] },
    pendingPaymentApprovals: { type: Number, default: 0 },
    classCategories: { type: Array, default: () => [] },
    masterClasses: { type: Array, default: () => [] },
    classGroupOptions: { type: Array, default: () => [] },
    ledgerAccount: { type: Object, default: () => ({}) },
    gradeMasters: { type: Array, default: () => [] },
    hallTicketTemplates: { type: Array, default: () => [] },
    certificateTemplates: { type: Array, default: () => [] },
    gradeBands: { type: Array, default: () => [] },
    clusterRequireStudentVerification: { type: Boolean, default: true },
});

function studentVerificationModeFromExam(exam) {
    const settings = exam?.settings_json ?? {};
    if (!Object.prototype.hasOwnProperty.call(settings, 'require_verified_students')) {
        return 'inherit';
    }

    return settings.require_verified_students ? 'required' : 'optional';
}

const ledgerForm = useForm({ name: props.ledgerAccount?.name ?? '' });

function saveLedgerAccount() {
    ledgerForm.put(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/ledger-account`, { preserveScroll: true });
}

const eligibilityDefaults = props.exam.eligibility_config ?? {};

const publicMcqPapersUrl = computed(() => {
    const root = (props.publicUrl ?? '').replace(/\/$/, '');
    return root ? `${root}/mcq/papers` : '/mcq/papers';
});

const form = useForm({
    title: props.exam.title,
    status: props.exam.status,
    delivery_mode: props.exam.delivery_mode ?? 'offline',
    requires_hall_ticket: !!(props.exam.settings_json?.requires_hall_ticket),
    student_verification_mode: studentVerificationModeFromExam(props.exam),
    scheduled_at: props.exam.scheduled_at ? props.exam.scheduled_at.slice(0, 16) : '',
    fee_amount: props.exam.fee_amount ?? '',
    school_discount_amount: props.exam.school_discount_amount ?? '',
    next_hall_ticket_no: props.exam.next_hall_ticket_no ?? 100,
    eligibility_config: {
        scope: eligibilityDefaults.scope ?? 'all',
        assignment_type: eligibilityDefaults.assignment_type
            ?? (eligibilityDefaults.class_category_ids?.length ? 'category'
            : (eligibilityDefaults.master_class_ids?.length ? 'class' : 'all')),
        class_category_ids: [...(eligibilityDefaults.class_category_ids ?? [])],
        master_class_ids: [...(eligibilityDefaults.master_class_ids ?? [])],
        class_groups: [...(eligibilityDefaults.class_groups ?? [])],
        gender: eligibilityDefaults.gender ?? 'open',
    },
    grade_master_id: props.exam.grade_master_id ?? '',
    hall_ticket_template_id: props.exam.hall_ticket_template_id ?? '',
    certificate_template_id: props.exam.certificate_template_id ?? '',
});

const presentCount = computed(() => props.registrations.filter((r) => r.attendance_status === 'present').length);
const markedCount = computed(() => props.registrations.filter((r) => r.mark?.score != null).length);
const pendingApprovalCount = computed(() => props.registrations.filter((r) => r.approval_status === 'pending_payment').length);

function save() {
    form.put(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}`, { preserveScroll: true });
}

const paperFile = ref(null);
const paperForm = useForm({
    question_paper: null,
    question_paper_label: props.exam.question_paper_label ?? '',
});

function onPaperFile(e) {
    paperFile.value = e.target.files?.[0] ?? null;
}

function uploadPaper() {
    if (!paperFile.value) return;
    paperForm.transform(() => ({
        question_paper: paperFile.value,
        question_paper_label: paperForm.question_paper_label,
    })).post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/question-paper`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { paperFile.value = null; },
    });
}

function removePaper() {
    if (!confirm('Remove this question paper from the public archive?')) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/question-paper`, { preserveScroll: true });
}
</script>
