<template>
    <SchoolAdminLayout title="Annual Registration" :school="school" :show-header-title="false">
        <PageHeader
            title="Annual registration"
            eyebrow="Membership"
            :description="`Sahodaya membership for academic year ${academicYear}. Submit data, pay fees, and upload payment proof for approval.`"
        />

        <div class="max-w-3xl space-y-6">
            <div v-if="registration && registrationSteps.length" class="card !py-4">
                <ol class="flex flex-wrap gap-2 sm:gap-0 sm:flex-nowrap items-center text-xs sm:text-sm">
                    <li v-for="(step, index) in registrationSteps" :key="step.key"
                        class="flex items-center gap-2 sm:flex-1 min-w-0">
                        <span class="step-badge shrink-0"
                              :class="step.state === 'done' ? 'step-badge--done' : step.state === 'current' ? 'step-badge--active' : 'step-badge--pending'">
                            {{ index + 1 }}
                        </span>
                        <span class="truncate font-medium"
                              :class="step.state === 'current' ? 'text-slate-900' : 'text-slate-500'">
                            {{ step.label }}
                        </span>
                        <span v-if="index < registrationSteps.length - 1" class="hidden sm:inline text-slate-300 mx-2">→</span>
                    </li>
                </ol>
            </div>

            <div v-if="registrationClosingSoon" class="notice-banner notice-banner--warning text-sm">
                <p class="font-semibold">Registration closes soon</p>
                <p class="mt-1">
                    Annual membership registration closes on {{ formatDate(windowDisplayEnd(registrationWindow)) }}
                    ({{ registrationClosingDays }} day{{ registrationClosingDays === 1 ? '' : 's' }} left).
                    Complete your submission and payment before the deadline.
                </p>
            </div>

            <!-- Not started -->
            <div v-if="!registration" class="space-y-5">
                <div v-if="isRenewal && priorYearSummary" class="notice-banner notice-banner--info space-y-2">
                    <p class="text-xs font-bold uppercase tracking-wide opacity-80">Annual renewal</p>
                    <h2 class="font-bold text-lg">Renew membership for {{ academicYear }}</h2>
                    <p class="text-sm opacity-90">
                        Last year ({{ priorYearSummary.academic_year }}) you completed registration
                        <span class="font-mono font-semibold">{{ priorYearSummary.reg_no }}</span>.
                        Your profile and school data will carry forward — confirm counts/teachers if anything changed, then pay the renewal fee.
                    </p>
                </div>

                <div class="card space-y-3">
                    <h2 v-if="!isRenewal" class="section-title text-base">Annual Sahodaya membership — {{ academicYear }}</h2>
                    <h2 v-else class="section-title text-base">Continue renewal — {{ academicYear }}</h2>
                    <p class="text-sm leading-relaxed text-slate-600">
                        Renew your school's membership each year. You will submit required data (if any), pay the annual fee,
                        and upload payment proof for Sahodaya approval.
                    </p>
                    <p v-if="membershipFeePreview != null && profile?.membership_fee_type === 'none'" class="text-sm text-slate-700">
                        Membership fee: <span class="text-2xl font-bold text-emerald-700">₹0</span>
                        <span class="text-slate-400 text-xs"> (no fee — registration completes without payment)</span>
                    </p>
                    <p v-else-if="membershipFeePreview != null" class="text-sm text-slate-700">
                        Membership fee: <span class="text-2xl font-bold text-slate-900">₹{{ formatAmount(membershipFeePreview) }}</span>
                        <span class="text-slate-400 text-xs"> (fixed fee set by Sahodaya)</span>
                    </p>
                </div>

                <div v-if="registrationWindow" class="notice-banner notice-banner--info text-sm">
                    <p class="font-semibold">Registration window</p>
                    <p v-if="windowDisplayStart(registrationWindow) && windowDisplayEnd(registrationWindow)">
                        Open {{ formatDate(windowDisplayStart(registrationWindow)) }} —
                        {{ formatDate(windowDisplayEnd(registrationWindow)) }}
                    </p>
                    <p v-else-if="windowDisplayStart(registrationWindow)">
                        Opens {{ formatDate(windowDisplayStart(registrationWindow)) }}
                    </p>
                    <p v-else-if="windowDisplayEnd(registrationWindow)">
                        Closes {{ formatDate(windowDisplayEnd(registrationWindow)) }}
                    </p>
                </div>

                <div v-if="membershipFeeNotConfigured" class="notice-banner notice-banner--warning text-sm">
                    Your Sahodaya has not finished configuring membership fees for {{ academicYear }} yet. Contact the Sahodaya office — registration will open once fees are set.
                </div>

                <div v-if="registrationWindowBlockReason" class="notice-banner notice-banner--warning text-sm">
                    {{ registrationWindowBlockReason }}
                </div>

                <div class="card text-center space-y-4">
                    <p class="text-slate-600">No registration started for {{ academicYear }} yet.</p>
                    <button v-if="canBegin" @click="begin" class="btn-primary">
                        {{ isRenewal ? 'Begin Renewal' : 'Begin Annual Registration' }}
                    </button>
                    <p v-else-if="!school.school_prefix" class="text-xs text-slate-500">
                        <Link :href="`/school-admin/${school.id}/setup/code`" class="link-brand">Set your school code</Link>
                        before starting.
                    </p>
                </div>
            </div>

            <!-- In progress / payment / complete -->
            <template v-else>
                <div class="card flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="stat-tile-label">Membership No.</p>
                        <p class="font-mono text-xl font-bold text-slate-900">{{ registration.reg_no }}</p>
                        <p class="text-xs text-slate-500 mt-1">Academic year {{ academicYear }}</p>
                    </div>
                    <span class="status-pill capitalize" :class="statusClass(registration.registration_status)">
                        {{ statusLabel(registration.registration_status) }}
                    </span>
                </div>

                <div v-if="needsDataSteps" class="card space-y-4">
                    <h3 class="section-title text-base">Submit annual data</h3>
                    <p class="section-desc">Complete each section below. Payment unlocks when all required sections are submitted.</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <Link v-if="profile?.student_data_mode === 'full_records'"
                              :href="`/school-admin/${school.id}/registration/students`"
                              class="track-card"
                              :class="trackDone(registration.submission?.full_records_status) ? 'track-card--done' : ''">
                            <p class="font-semibold text-slate-900">Student records</p>
                            <p class="text-xs mt-1 capitalize" :class="trackStatusClass(registration.submission?.full_records_status)">
                                {{ trackLabel(registration.submission?.full_records_status) }}
                            </p>
                        </Link>
                        <Link v-if="profile?.student_data_mode === 'counts_only'"
                              :href="`/school-admin/${school.id}/registration/counts`"
                              class="track-card"
                              :class="trackDone(registration.submission?.counts_status) ? 'track-card--done' : ''">
                            <p class="font-semibold text-slate-900">Student counts</p>
                            <p class="text-xs mt-1 capitalize" :class="trackStatusClass(registration.submission?.counts_status)">
                                {{ trackLabel(registration.submission?.counts_status) }}
                            </p>
                        </Link>
                        <Link v-if="profile?.teacher_registration_enabled"
                              :href="`/school-admin/${school.id}/registration/teachers`"
                              class="track-card"
                              :class="trackDone(registration.submission?.teacher_status) ? 'track-card--done' : ''">
                            <p class="font-semibold text-slate-900">Teachers</p>
                            <p class="text-xs mt-1 capitalize" :class="trackStatusClass(registration.submission?.teacher_status)">
                                {{ trackLabel(registration.submission?.teacher_status) }}
                            </p>
                        </Link>
                    </div>
                </div>

                <div v-if="canPay" class="notice-banner notice-banner--warning space-y-5 !p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide opacity-80">Pending payment</p>
                            <p class="text-3xl font-bold mt-1">₹{{ formatAmount(registration.membership_fee_amount) }}</p>
                            <p class="text-sm opacity-90 mt-1">Annual membership fee payable to Sahodaya</p>
                        </div>
                        <span v-if="registration.registration_status === 'payment_rejected'"
                              class="status-pill bg-red-100 text-red-700">
                            Payment rejected — re-upload proof
                        </span>
                    </div>

                    <div v-if="profile?.payment_details_text" class="card card--muted !shadow-none">
                        <p class="form-label text-slate-500 mb-2">How to pay</p>
                        <pre class="text-sm text-slate-700 whitespace-pre-wrap font-sans leading-relaxed">{{ profile.payment_details_text }}</pre>
                    </div>
                    <p v-else class="text-sm italic opacity-80">
                        Payment instructions are not configured yet. Please contact your Sahodaya office for bank/UPI details.
                    </p>

                    <form @submit.prevent="uploadPayment" class="card space-y-4 !shadow-none">
                        <p class="section-title">Upload payment proof for Sahodaya approval</p>
                        <FormField label="Payment proof" required hint="PDF, JPG or PNG — max 5 MB">
                            <input type="file" required accept=".pdf,.jpg,.jpeg,.png" @change="paymentForm.payment_proof = $event.target.files[0]" class="field">
                        </FormField>
                        <FormGrid>
                            <FormField label="Payment method">
                                <input v-model="paymentForm.payment_method" type="text" placeholder="UPI / NEFT / Cash" class="field">
                            </FormField>
                            <FormField label="Transaction reference">
                                <input v-model="paymentForm.transaction_ref" type="text" placeholder="UTR / ref no." class="field">
                            </FormField>
                        </FormGrid>
                        <button type="submit" :disabled="paymentForm.processing" class="btn-primary">
                            {{ paymentForm.processing ? 'Submitting…' : 'Submit payment for approval' }}
                        </button>
                    </form>

                    <p v-if="latestRejectedPayment" class="text-sm text-red-700">
                        Previous payment rejected: {{ latestRejectedPayment.rejection_reason }}
                    </p>
                </div>

                <div v-if="registration.registration_status === 'payment_submitted'" class="notice-banner notice-banner--info space-y-2">
                    <p class="font-semibold">Payment submitted — awaiting Sahodaya approval</p>
                    <p class="text-sm opacity-90">
                        Your payment proof (₹{{ formatAmount(registration.membership_fee_amount) }}) has been sent to Sahodaya for verification.
                        You will be notified once approved.
                    </p>
                    <div v-if="payments?.length" class="text-sm opacity-80 pt-2 border-t border-blue-200/60 mt-3 space-y-1">
                        <p v-for="p in payments" :key="p.id">
                            ₹{{ formatAmount(p.amount) }} — {{ p.status }}
                            <span v-if="p.transaction_ref" class="text-xs">({{ p.transaction_ref }})</span>
                        </p>
                    </div>
                </div>

                <div v-if="registration.registration_status === 'completed'" class="notice-banner notice-banner--success space-y-4">
                    <div>
                        <p class="font-semibold">Membership registration complete</p>
                        <p class="text-sm opacity-90 mt-1">
                            {{ academicYear }} membership is active. Membership No: {{ registration.reg_no }}
                        </p>
                        <p v-if="school.membership_status === 'approved'" class="text-sm opacity-90 mt-2">
                            Your school is approved by Sahodaya. You can now manage students and use the portal.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3 pt-2 border-t border-emerald-200/80">
                        <Link :href="`/school-admin/${school.id}`" class="btn-secondary">Go to Dashboard</Link>
                        <Link :href="`/school-admin/${school.id}/students`" class="btn-primary">Manage Students</Link>
                        <Link v-if="membershipReceiptPaymentId"
                              :href="`/school-admin/${school.id}/payments/membership/${membershipReceiptPaymentId}/receipt`"
                              target="_blank"
                              class="btn-secondary">
                            Download receipt
                        </Link>
                        <Link :href="`/school-admin/${school.id}/registration/profile`" class="btn-secondary">Registration Details</Link>
                    </div>
                </div>
            </template>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    windowClosingDays,
    windowClosingSoon,
    windowDisplayEnd,
    windowDisplayStart,
} from '@/support/membershipRegistrationWindow.js';

const props = defineProps({
    school: Object,
    academicYear: String,
    registration: Object,
    profile: Object,
    registrationWindow: Object,
    payments: { type: Array, default: () => [] },
    canBegin: Boolean,
    isRenewal: Boolean,
    priorYearSummary: Object,
    membershipFeePreview: [Number, String, null],
    registrationWindowBlockReason: { type: String, default: null },
    membershipFeeNotConfigured: { type: Boolean, default: false },
    membershipReceiptPaymentId: { type: Number, default: null },
});

const hasDataTracks = computed(() =>
    props.profile?.student_data_mode === 'full_records'
    || props.profile?.student_data_mode === 'counts_only'
    || props.profile?.teacher_registration_enabled
);

const registrationSteps = computed(() => {
    if (!props.registration) return [];

    const reg = props.registration;
    const steps = [];

    if (hasDataTracks.value) {
        const dataDone = ['payment_pending', 'payment_submitted', 'payment_rejected', 'completed', 'approved'].includes(reg.registration_status)
            || (reg.submission && (
                (props.profile?.student_data_mode !== 'full_records' || ['submitted', 'approved'].includes(reg.submission.full_records_status))
                && (props.profile?.student_data_mode !== 'counts_only' || ['submitted', 'approved'].includes(reg.submission.counts_status))
                && (!props.profile?.teacher_registration_enabled || ['submitted', 'approved'].includes(reg.submission.teacher_status))
            ));
        steps.push({
            key: 'data',
            label: 'Submit data',
            state: reg.registration_status === 'completed' || dataDone ? 'done' : 'current',
        });
    }

    const paymentDone = ['completed', 'approved'].includes(reg.registration_status);
    const paymentCurrent = ['payment_pending', 'payment_submitted', 'payment_rejected'].includes(reg.registration_status);
    steps.push({
        key: 'payment',
        label: 'Pay & upload proof',
        state: paymentDone ? 'done' : paymentCurrent ? 'current' : 'pending',
    });

    steps.push({
        key: 'complete',
        label: 'Sahodaya approval',
        state: paymentDone ? 'done' : paymentCurrent && reg.registration_status === 'payment_submitted' ? 'current' : 'pending',
    });

    return steps;
});

const paymentForm = useForm({
    payment_proof: null,
    payment_method: '',
    transaction_ref: '',
});

const canPay = computed(() =>
    props.registration && ['payment_pending', 'payment_rejected'].includes(props.registration.registration_status)
);

const needsDataSteps = computed(() =>
    props.registration && ['data_pending', 'data_rejected'].includes(props.registration.registration_status)
);

const latestRejectedPayment = computed(() =>
    props.payments?.find(p => p.status === 'rejected')
);

const registrationClosingDays = computed(() => windowClosingDays(props.registrationWindow));

const registrationClosingSoon = computed(() => windowClosingSoon(props.registrationWindow));

function formatDate(value) {
    if (! value) return '';
    return new Date(value).toLocaleDateString('en-IN', { day: 'numeric', month: 'long', year: 'numeric' });
}

function begin() {
    router.post(`/school-admin/${props.school.id}/registration/begin`);
}

function uploadPayment() {
    paymentForm.post(`/school-admin/${props.school.id}/registration/payment`, { forceFormData: true });
}

function formatAmount(amount) {
    if (amount == null || amount === '') return '—';
    return Number(amount).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function statusLabel(status) {
    return {
        data_pending: 'Data pending',
        data_rejected: 'Data rejected',
        payment_pending: 'Payment pending',
        payment_submitted: 'Awaiting approval',
        payment_rejected: 'Payment rejected',
        completed: 'Completed',
    }[status] || status?.replace(/_/g, ' ');
}

function statusClass(s) {
    return {
        data_pending: 'bg-amber-100 text-amber-700',
        data_rejected: 'bg-red-100 text-red-700',
        payment_pending: 'bg-amber-100 text-amber-800',
        payment_submitted: 'bg-blue-100 text-blue-700',
        payment_rejected: 'bg-red-100 text-red-700',
        completed: 'bg-green-100 text-green-700',
    }[s] || 'bg-slate-100 text-slate-600';
}

function trackDone(status) {
    return status === 'approved';
}

function trackLabel(status) {
    if (status === 'approved') return 'Approved';
    if (status === 'submitted') return 'Awaiting Sahodaya review';
    if (status === 'not_applicable') return 'Not required';
    if (status === 'rejected') return 'Rejected — update & resubmit';
    return 'Pending — open to submit';
}

function trackStatusClass(status) {
    if (status === 'approved') return 'text-green-600';
    if (status === 'submitted') return 'text-blue-600';
    if (status === 'rejected') return 'text-red-600';
    return 'text-amber-600';
}
</script>
