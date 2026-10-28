<template>
    <SahodayaAdminLayout :title="exam.title" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="exam.title"
                    :eyebrow="exam.is_model_exam ? 'Sahodaya model exam' : 'Talent Search exam'"
                    :description="`${registrationCounts.total} registrations · ${exam.status}`">
            <template #actions>
                <span v-if="exam.series_title" class="text-xs text-slate-500 mr-2">{{ exam.series_title }}</span>
                <a v-if="!exam.is_model_exam" :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/hall-tickets/preview`"
                   target="_blank" rel="noopener" class="btn-secondary text-sm">Sample hall ticket ↗</a>
                <a :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/certificates/preview`"
                   target="_blank" rel="noopener" class="btn-secondary text-sm">Sample certificate ↗</a>
                <a :href="`/portal/exam/${sahodaya.id}`" target="_blank" rel="noopener" class="btn-secondary text-sm">Exam portal ↗</a>
            </template>
        </PageHeader>

        <div class="flex flex-wrap gap-2 mb-4">
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full"
                  :class="exam.is_model_exam ? 'bg-sky-100 text-sky-800' : 'bg-indigo-100 text-indigo-800'">
                {{ exam.product_mode_label || 'Talent Search' }}
            </span>
            <span v-if="exam.level_label" class="text-xs font-semibold px-2.5 py-1 rounded-full bg-indigo-100 text-indigo-800">{{ exam.level_label }}</span>
            <span class="text-xs px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 capitalize">{{ exam.exam_type_label }}</span>
            <span v-if="(exam.exam_level ?? 1) > 1" class="text-xs px-2.5 py-1 rounded-full bg-amber-50 text-amber-800">
                Promotion: {{ exam.eligibility_mode_label }}
            </span>
            <span v-if="exam.parent_exam_title" class="text-xs px-2.5 py-1 rounded-full bg-slate-50 text-slate-600">
                After: {{ exam.parent_exam_title }}
            </span>
        </div>

        <McqExamSubNav :sahodaya-id="sahodaya.id" :exam-id="exam.id" :delivery-mode="exam.delivery_mode || 'offline'"
                       :results-published="!!exam.results_published" :is-model-exam="!!exam.is_model_exam"
                       :uses-engine="capacityGate?.uses_engine ?? null"
                       active="overview" />

        <McqSahodayaWorkflowBanner
            v-if="!exam.is_model_exam"
            :sahodaya-id="sahodaya.id"
            :exam-id="exam.id"
            :exam="exam"
            :pending-payment-approvals="pendingPaymentApprovals"
            :tickets-issued-count="exam.tickets_issued_count ?? 0"
            :registration-count="registrationCounts.total"
        />

        <div v-if="capacityGate?.message" class="card mb-4 !border-amber-200 !bg-amber-50 !py-3 px-4 text-sm text-amber-900">
            {{ capacityGate.message }}
        </div>

        <div v-if="membershipHealth?.length" class="card mb-4 !border-amber-200 !py-3 px-4 text-sm">
            <p class="font-semibold text-amber-900 mb-2">Membership / roster flags ({{ membershipHealth.length }} school{{ membershipHealth.length === 1 ? '' : 's' }})</p>
            <ul class="space-y-1 text-xs text-slate-700">
                <li v-for="row in visibleMembershipHealth" :key="row.school_id" class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/schools/${row.school_id}`" class="font-semibold link-brand">
                        {{ row.school_name }}
                    </Link>
                    <span>— {{ humanizeMembershipFlags(row.flags) }}</span>
                    <span class="text-slate-400">(roster {{ row.roster_students }}, registered {{ row.registered }})</span>
                    <Link
                        v-if="row.flags.includes('membership_unpaid') || row.flags.includes('rejected')"
                        :href="`/sahodaya-admin/${sahodaya.id}/membership/payments`"
                        class="text-amber-800 underline"
                    >Membership fees</Link>
                </li>
            </ul>
            <button
                v-if="membershipHealth.length > membershipHealthCap && !showAllMembershipHealth"
                type="button"
                class="mt-2 text-xs font-semibold text-amber-900 underline"
                @click="showAllMembershipHealth = true"
            >
                Show all {{ membershipHealth.length }}
            </button>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ registrationCounts.total }}</p>
                <p class="text-xs text-slate-500 mt-1">Registrations</p>
            </div>
            <div v-if="!exam.is_model_exam" class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-amber-700">{{ registrationCounts.pending_approval }}</p>
                <p class="text-xs text-slate-500 mt-1">Candidates pending payment</p>
                <p v-if="registrationCounts.approved > 0" class="text-[11px] text-emerald-700 font-medium mt-0.5">({{ registrationCounts.approved }} confirmed)</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ registrationCounts.present }}</p>
                <p class="text-xs text-slate-500 mt-1">Present</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ registrationCounts.marked }}</p>
                <p class="text-xs text-slate-500 mt-1">Marks entered</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold" :class="exam.results_published ? 'text-emerald-700' : 'text-amber-700'">
                    {{ exam.results_published ? 'Published' : 'Draft' }}
                </p>
                <p class="text-xs text-slate-500 mt-1">Results</p>
            </div>
        </div>

        <template v-if="!exam.is_model_exam">
            <McqSchoolCentreCodes :sahodaya-id="sahodaya.id" :exam-id="exam.id" :schools="centreSchools" />
            <McqRegistrationNumbering :sahodaya-id="sahodaya.id" :exam-id="exam.id" :ranges="classRollRanges" />
        </template>

        <p class="text-sm text-slate-600 mb-4">
            <a :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/results`" class="link-brand">
                Manage marks and publish results → Results & marks
            </a>
            <template v-if="!exam.is_model_exam">
                ·
                <a :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/payments`" class="link-brand">
                    Approve school batch fees → Payments
                </a>
            </template>
            ·
            <a :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/reports`" class="link-brand">
                Association + per-school reports →
            </a>
        </p>

        <form v-if="!exam.is_model_exam" @submit.prevent="saveLedgerAccount" class="card mb-6 space-y-3">
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
            <div class="rounded-xl border border-slate-200 p-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div><h4 class="font-semibold">Class-wise exam timings</h4><p class="text-sm text-slate-500">Set a date, start time and duration for each class. Other classes use the main exam schedule. These timings appear on admit cards.</p></div>
                    <button type="button" class="btn-secondary text-sm" @click="form.class_schedules.push({ class_name: '', scheduled_at: '', duration_minutes: exam.duration_minutes || 60 })">+ Add class timing</button>
                </div>
                <datalist id="exam-timing-classes"><option v-for="cls in timingClasses" :key="cls" :value="cls" /></datalist>
                <div v-for="(row, index) in form.class_schedules" :key="index" class="grid sm:grid-cols-4 gap-3 items-end">
                    <label class="text-sm">Class<input v-model="row.class_name" list="exam-timing-classes" class="field mt-1" placeholder="LKG, UKG, 1…" required></label>
                    <label class="text-sm">Exam date &amp; start time<input v-model="row.scheduled_at" type="datetime-local" class="field mt-1" required></label>
                    <label class="text-sm">Duration (minutes)<input v-model.number="row.duration_minutes" type="number" min="1" max="1440" class="field mt-1" required></label>
                    <button type="button" class="btn-secondary text-sm" @click="form.class_schedules.splice(index, 1)">Remove timing</button>
                </div>
                <p v-for="(error, key) in timingErrors" :key="key" class="text-sm text-red-600">{{ error }}</p>
                <p class="text-xs text-slate-500">Use Save exam below to save timings. Removing a timing restores the main exam schedule for that class.</p>
            </div>
            <FormGrid>
                <FormField label="Exam title" class-extra="sm:col-span-2" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="form.title" class="field" required>
                    </template>
                </FormField>
                <FormField label="Exam code" hint="Short unique code (e.g. TS-2026-L1)">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.code" class="field font-mono uppercase" maxlength="64" placeholder="Optional">
                    </template>
                </FormField>
                <FormField label="Scheduled at">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.scheduled_at" type="datetime-local" class="field">
                    </template>
                </FormField>
                <FormField label="Registration opens">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.registration_opens_at" type="datetime-local" class="field">
                    </template>
                </FormField>
                <FormField label="Registration closes">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.registration_closes_at" type="datetime-local" class="field">
                    </template>
                </FormField>
                <FormField label="Result date" hint="Cue for publishing results">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.result_date" type="date" class="field">
                    </template>
                </FormField>
                <FormField label="Status">
                    <template #default="{ id }">
                        <SearchableSelect
                            :id="id"
                            v-model="form.status"
                            :options="[
                                { value: 'draft', label: 'Draft' },
                                { value: 'published', label: 'Published' },
                                { value: 'ongoing', label: 'Ongoing' },
                                { value: 'completed', label: 'Completed' },
                            ]"
                            :all-option="false"
                        />
                    </template>
                </FormField>
                <FormField label="Delivery mode">
                    <template #default="{ id }">
                        <SearchableSelect
                            :id="id"
                            v-model="form.delivery_mode"
                            :options="[
                                { value: 'offline', label: 'Offline (paper / venue)' },
                                { value: 'online', label: 'Online (student portal)' },
                            ]"
                            :all-option="false"
                        />
                    </template>
                </FormField>
                <p v-if="form.delivery_mode === 'online'" class="text-xs text-indigo-700 sm:col-span-2">
                    Online exams require question banks for auto-grading. Link banks under the Question banks tab before exam day.
                </p>
                <FormField label="Product mode" class-extra="sm:col-span-2">
                    <template #default="{ id }">
                        <SearchableSelect
                            :id="id"
                            v-model="form.product_mode"
                            :options="[
                                { value: 'talent_search', label: 'Talent Search (fees / hall tickets)' },
                                { value: 'sahodaya_model_exam', label: 'Sahodaya model exam (slim)' },
                            ]"
                            :all-option="false"
                        />
                    </template>
                </FormField>
                <CheckboxField
                    v-model="form.allow_member_banks"
                    label="Allow member school question banks on this paper"
                    hint="Off by default: only Sahodaya-owned Ready banks. On: schools’ Ready banks can be attached."
                    class="sm:col-span-2"
                />
                <CheckboxField
                    v-if="form.delivery_mode === 'online' && form.product_mode !== 'sahodaya_model_exam'"
                    v-model="form.requires_hall_ticket"
                    label="Require hall ticket before starting"
                    hint="When enabled, students must have a hall ticket issued and attendance marked present before the online exam can start."
                    class="sm:col-span-2"
                />
                <p v-else-if="form.delivery_mode !== 'online'" class="text-xs text-slate-500 sm:col-span-2">
                    Offline exams use hall tickets and manual mark entry. Question banks are optional.
                </p>
                <template v-if="form.product_mode !== 'sahodaya_model_exam'">
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
                    <FormField label="Payment deadline" hint="Fee due date. Schools paying after this date are charged the late fee/penalty below.">
                        <template #default="{ id }">
                            <input :id="id" v-model="form.payment_deadline" type="date" class="field">
                        </template>
                    </FormField>
                    <FormField label="Late fee (₹)" hint="Flat amount added to the total due if paid after the deadline">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="form.late_fee_amount" type="number" min="0" step="0.01" class="field" placeholder="0">
                        </template>
                    </FormField>
                    <FormField label="Penalty (₹)" hint="Additional penalty amount added on top of the late fee, if any">
                        <template #default="{ id }">
                            <input :id="id" v-model.number="form.penalty_amount" type="number" min="0" step="0.01" class="field" placeholder="0">
                        </template>
                    </FormField>
                </template>
                <p class="text-xs text-slate-500 sm:col-span-2">Late fee and penalty only apply once a payment deadline is set; they're re-checked every time a school's fee is (re)synced.</p>
                <FormField label="Reg. no. starts at" hint="First hall-ticket number when tickets are issued. Use presets or enter any number from 1. Locked after any ticket is issued.">
                    <template #default="{ id }">
                        <McqRegNoStartField :input-id="id" v-model="form.next_hall_ticket_no" :disabled="exam.tickets_issued" />
                    </template>
                </FormField>
            </FormGrid>

            <div class="border-t border-slate-100 pt-4">
                <FormField label="Student verification for registration" class-extra="mb-4">
                    <template #default="{ id }">
                        <SearchableSelect
                            :id="id"
                            v-model="form.student_verification_mode"
                            :options="studentVerificationOptions"
                            :all-option="false"
                        />
                        <p class="text-xs text-slate-500 mt-1">
                            Cluster default is set under Membership → Settings. Applies to school registration for this exam.
                        </p>
                    </template>
                </FormField>
                <McqEligibilityPicker
                    :mode="form.product_mode === 'sahodaya_model_exam' ? 'school_model' : 'talent_search'"
                    v-model="form.eligibility_config"
                    :class-categories="classCategories"
                    :master-classes="masterClasses"
                />
            </div>

            <h3 class="section-title pt-2">Templates & grading</h3>
            <FormGrid>
                <FormField label="Grade master">
                    <template #default="{ id }">
                        <SearchableSelect
                            :id="id"
                            v-model="form.grade_master_id"
                            :options="gradeMasterOptions"
                            :all-option="true"
                            all-label="Default Sahodaya grade master"
                        />
                    </template>
                </FormField>
                <FormField v-if="form.product_mode !== 'sahodaya_model_exam'" label="Hall ticket template">
                    <template #default="{ id }">
                        <SearchableSelect
                            :id="id"
                            v-model="form.hall_ticket_template_id"
                            :options="hallTicketTemplateOptions"
                            :all-option="true"
                            all-label="Default / per-exam design"
                        />
                    </template>
                </FormField>
            </FormGrid>
            <p v-if="gradeBands?.length" class="text-xs text-slate-500">Active grade bands: {{ gradeBands.map(b => b.label).join(', ') }} — percentage-based, so they apply the same regardless of a class's max marks.</p>

            <div class="border-t border-slate-100 pt-4 mt-2">
                <McqResultReleaseSettingsForm
                    v-model="releaseSettings"
                    :results-published="!!exam.results_published"
                    :certificate-templates="certificateTemplates"
                    :grade-bands="gradeBands"
                />
            </div>

            <div class="border-t border-slate-100 pt-4">
                <FormField label="Total marks / questions (default)" hint="Denominator for percentage/grade on offline mark entry when a class below has no override.">
                    <template #default="{ id }">
                        <input :id="id" v-model.number="form.total_questions" type="number" min="1" class="field max-w-[10rem]" placeholder="e.g. 25">
                    </template>
                </FormField>

                <div v-if="classOptions.length" class="mt-3">
                    <p class="text-xs font-semibold text-slate-600 mb-2">Class-wise max marks (optional) — overrides the default above for a specific class.</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-2 max-w-xl">
                        <div v-for="cls in classOptions" :key="cls" class="flex items-center gap-2">
                            <span class="text-xs text-slate-500 w-20 shrink-0">Class {{ cls }}</span>
                            <input v-model.number="form.class_max_marks[cls]" type="number" min="1" class="field text-xs"
                                   :placeholder="form.total_questions ? `default: ${form.total_questions}` : 'default'">
                        </div>
                    </div>
                </div>
                <p v-else class="text-xs text-slate-400 mt-2">Class-wise overrides appear here once students have registered for at least one class.</p>
            </div>

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
import { computed, reactive, ref, watch } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqSchoolCentreCodes from '@/Components/sahodaya/McqSchoolCentreCodes.vue';
import McqRegistrationNumbering from '@/Components/sahodaya/McqRegistrationNumbering.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';
import McqEligibilityPicker from '@/Components/sahodaya/McqEligibilityPicker.vue';
import McqRegNoStartField from '@/Components/sahodaya/McqRegNoStartField.vue';
import McqSahodayaWorkflowBanner from '@/Components/sahodaya/McqSahodayaWorkflowBanner.vue';
import McqResultReleaseSettingsForm from '@/Components/mcq/McqResultReleaseSettingsForm.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    exam: Object,
    registrationCounts: { type: Object, default: () => ({ total: 0, present: 0, marked: 0, pending_approval: 0 }) },
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
    centreSchools: { type: Array, default: () => [] },
    classRollRanges: { type: Array, default: () => [] },
    classOptions: { type: Array, default: () => [] },
    membershipHealth: { type: Array, default: () => [] },
    capacityGate: { type: Object, default: null },
});

const { confirm } = useConfirm();

const MEMBERSHIP_FLAG_LABELS = {
    inactive: 'School inactive',
    rejected: 'Membership rejected',
    membership_unpaid: 'Membership unpaid',
    empty_roster: 'Empty roster',
    registered_while_blocked: 'Registered while membership blocked',
};

const membershipHealthCap = 12;
const showAllMembershipHealth = ref(false);
const visibleMembershipHealth = computed(() =>
    showAllMembershipHealth.value
        ? props.membershipHealth
        : props.membershipHealth.slice(0, membershipHealthCap),
);

function humanizeMembershipFlags(flags) {
    return (flags || [])
        .map(f => MEMBERSHIP_FLAG_LABELS[f] || String(f).replace(/_/g, ' '))
        .join(', ');
}

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

const initialClassMaxMarks = {};
for (const cls of props.classOptions) {
    initialClassMaxMarks[cls] = props.exam.settings_json?.class_max_marks?.[cls] ?? null;
}

const timingClasses = computed(() => [...new Set(['LKG', 'UKG', ...Array.from({ length: 12 }, (_, i) => String(i + 1)), ...props.classOptions])]);
const timingErrors = computed(() => Object.fromEntries(Object.entries(form.errors).filter(([key]) => key.startsWith('class_schedules'))));
const form = useForm({
    class_schedules: (props.exam.settings_json?.class_schedules ?? []).map(row => ({ ...row })),
    title: props.exam.title,
    code: props.exam.code ?? '',
    status: props.exam.status,
    product_mode: props.exam.product_mode ?? 'talent_search',
    allow_member_banks: !!(props.exam.allow_member_banks ?? props.exam.settings_json?.allow_member_banks),
    delivery_mode: props.exam.delivery_mode ?? 'offline',
    requires_hall_ticket: !!(props.exam.settings_json?.requires_hall_ticket),
    student_verification_mode: studentVerificationModeFromExam(props.exam),
    scheduled_at: props.exam.scheduled_at ? props.exam.scheduled_at.slice(0, 16) : '',
    registration_opens_at: props.exam.registration_opens_at ? String(props.exam.registration_opens_at).slice(0, 16) : '',
    registration_closes_at: props.exam.registration_closes_at ? String(props.exam.registration_closes_at).slice(0, 16) : '',
    result_date: props.exam.result_date ? String(props.exam.result_date).slice(0, 10) : '',
    fee_amount: props.exam.fee_amount ?? '',
    school_discount_amount: props.exam.school_discount_amount ?? '',
    payment_deadline: props.exam.payment_deadline ?? '',
    late_fee_amount: props.exam.late_fee_amount ?? '',
    penalty_amount: props.exam.penalty_amount ?? '',
    next_hall_ticket_no: props.exam.next_hall_ticket_no ?? 100,
    eligibility_config: {
        audience: eligibilityDefaults.audience ?? 'students',
        scope: eligibilityDefaults.scope ?? 'all',
        assignment_type: eligibilityDefaults.assignment_type
            ?? (eligibilityDefaults.class_category_ids?.length ? 'category'
            : (eligibilityDefaults.master_class_ids?.length ? 'class' : 'all')),
        class_category_ids: [...(eligibilityDefaults.class_category_ids ?? [])],
        master_class_ids: [...(eligibilityDefaults.master_class_ids ?? [])],
        class_groups: [...(eligibilityDefaults.class_groups ?? [])],
        gender: eligibilityDefaults.gender ?? 'open',
        min_experience_years: eligibilityDefaults.min_experience_years ?? null,
        allow_teacher_self_registration: eligibilityDefaults.allow_teacher_self_registration ?? true,
        teaching_type_ids: [...(eligibilityDefaults.teaching_type_ids ?? [])],
        subject_ids: [...(eligibilityDefaults.subject_ids ?? [])],
        excluded_designation_ids: [...(eligibilityDefaults.excluded_designation_ids ?? [])],
    },
    grade_master_id: props.exam.grade_master_id ?? '',
    hall_ticket_template_id: props.exam.hall_ticket_template_id ?? '',
    certificate_template_id: props.exam.result_release?.certificate_template_id ?? props.exam.certificate_template_id ?? '',
    total_questions: props.exam.total_questions || '',
    class_max_marks: initialClassMaxMarks,
    result_visibility: props.exam.result_release?.result_visibility ?? 'score_grade',
    results_auto_release_at: props.exam.result_release?.results_auto_release_at
        ? String(props.exam.result_release.results_auto_release_at).slice(0, 16)
        : '',
    certificates_enabled: props.exam.result_release?.certificates_enabled
        ?? (props.exam.product_mode !== 'sahodaya_model_exam'),
    certificates_auto_issue: !!props.exam.result_release?.certificates_auto_issue,
    certificate_eligibility: props.exam.result_release?.certificate_eligibility ?? 'participation',
    certificate_eligible_grades: [...(props.exam.result_release?.certificate_eligible_grades ?? [])],
});

const releaseSettings = reactive({
    result_visibility: form.result_visibility,
    results_auto_release_at: form.results_auto_release_at,
    certificates_enabled: form.certificates_enabled,
    certificates_auto_issue: form.certificates_auto_issue,
    certificate_eligibility: form.certificate_eligibility,
    certificate_eligible_grades: [...form.certificate_eligible_grades],
    certificate_template_id: form.certificate_template_id,
});

watch(releaseSettings, (val) => {
    Object.assign(form, val);
}, { deep: true });

const studentVerificationOptions = computed(() => [
    { value: 'inherit', label: `Use cluster default — ${props.clusterRequireStudentVerification ? 'verified students only' : 'unverified allowed'}` },
    { value: 'required', label: 'Require verified students only' },
    { value: 'optional', label: 'Allow unverified students' },
]);

const gradeMasterOptions = computed(() => props.gradeMasters.map((m) => ({
    value: m.id,
    label: m.is_default ? `${m.title} (default)` : m.title,
})));

const hallTicketTemplateOptions = computed(() => props.hallTicketTemplates.map((t) => ({ value: t.id, label: t.title })));

const certificateTemplateOptions = computed(() => props.certificateTemplates.map((t) => ({ value: t.id, label: t.title })));


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

async function removePaper() {
    if (!(await confirm({ message: 'Remove this question paper from the public archive?' }))) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/question-paper`, { preserveScroll: true });
}
</script>
