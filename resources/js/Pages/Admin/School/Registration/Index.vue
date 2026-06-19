<template>
    <SchoolAdminLayout title="Annual Registration" :school="school">
        <div class="max-w-3xl space-y-6">
            <!-- Not started -->
            <div v-if="!registration" class="space-y-4">
                <div class="bg-white rounded-xl border border-gray-100 p-6 space-y-3">
                    <h2 class="font-bold text-gray-900">Annual Sahodaya membership — {{ academicYear }}</h2>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        Renew your school's membership each year. You will submit required data (if any), pay the annual fee,
                        and upload payment proof for Sahodaya approval.
                    </p>
                    <p v-if="membershipFeePreview" class="text-sm text-gray-700">
                        Membership fee: <span class="font-bold text-lg">₹{{ formatAmount(membershipFeePreview) }}</span>
                        <span class="text-gray-400 text-xs"> (fixed fee set by Sahodaya)</span>
                    </p>
                </div>

                <div class="bg-white rounded-xl border p-6 text-center space-y-4">
                    <p class="text-gray-600">No registration started for {{ academicYear }} yet.</p>
                    <button v-if="canBegin" @click="begin"
                            class="bg-blue-600 text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-blue-700">
                        Begin Annual Registration
                    </button>
                    <p v-else-if="!school.school_prefix" class="text-xs text-gray-400">
                        <Link :href="`/school-admin/${school.id}/setup/code`" class="text-blue-600 hover:underline font-semibold">Set your school code</Link>
                        before starting.
                    </p>
                </div>
            </div>

            <!-- In progress / payment / complete -->
            <template v-else>
                <div class="bg-white rounded-xl border border-gray-100 p-6 flex flex-wrap justify-between items-start gap-4">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Membership No.</p>
                        <p class="font-mono font-bold text-lg text-gray-900">{{ registration.reg_no }}</p>
                        <p class="text-xs text-gray-400 mt-1">Academic year {{ academicYear }}</p>
                    </div>
                    <span class="text-xs px-3 py-1.5 rounded-full font-semibold capitalize" :class="statusClass(registration.registration_status)">
                        {{ statusLabel(registration.registration_status) }}
                    </span>
                </div>

                <!-- Data steps (before payment unlocks) -->
                <div v-if="needsDataSteps" class="bg-white rounded-xl border border-gray-100 p-6 space-y-4">
                    <h3 class="font-bold text-gray-800">Submit annual data</h3>
                    <p class="text-sm text-gray-500">Complete each section below. Payment unlocks when all required sections are submitted.</p>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <Link v-if="profile?.student_data_mode === 'full_records'"
                              :href="`/school-admin/${school.id}/registration/students`"
                              class="border rounded-xl p-4 hover:bg-gray-50 transition"
                              :class="trackDone(registration.submission?.full_records_status) ? 'border-green-200 bg-green-50/50' : ''">
                            <p class="font-semibold text-gray-800">Student records</p>
                            <p class="text-xs mt-1 capitalize" :class="trackStatusClass(registration.submission?.full_records_status)">
                                {{ trackLabel(registration.submission?.full_records_status) }}
                            </p>
                        </Link>
                        <Link v-if="profile?.student_data_mode === 'counts_only'"
                              :href="`/school-admin/${school.id}/registration/counts`"
                              class="border rounded-xl p-4 hover:bg-gray-50 transition"
                              :class="trackDone(registration.submission?.counts_status) ? 'border-green-200 bg-green-50/50' : ''">
                            <p class="font-semibold text-gray-800">Student counts</p>
                            <p class="text-xs mt-1 capitalize" :class="trackStatusClass(registration.submission?.counts_status)">
                                {{ trackLabel(registration.submission?.counts_status) }}
                            </p>
                        </Link>
                        <Link v-if="profile?.teacher_registration_enabled"
                              :href="`/school-admin/${school.id}/registration/teachers`"
                              class="border rounded-xl p-4 hover:bg-gray-50 transition"
                              :class="trackDone(registration.submission?.teacher_status) ? 'border-green-200 bg-green-50/50' : ''">
                            <p class="font-semibold text-gray-800">Teachers</p>
                            <p class="text-xs mt-1 capitalize" :class="trackStatusClass(registration.submission?.teacher_status)">
                                {{ trackLabel(registration.submission?.teacher_status) }}
                            </p>
                        </Link>
                    </div>
                </div>

                <!-- Pending payment -->
                <div v-if="canPay" class="bg-amber-50 border border-amber-200 rounded-xl p-6 space-y-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold text-amber-800 uppercase tracking-wide">Pending payment</p>
                            <p class="text-3xl font-bold text-amber-900 mt-1">₹{{ formatAmount(registration.membership_fee_amount) }}</p>
                            <p class="text-sm text-amber-800/80 mt-1">Annual membership fee payable to Sahodaya</p>
                        </div>
                        <span v-if="registration.registration_status === 'payment_rejected'"
                              class="text-xs bg-red-100 text-red-700 px-3 py-1 rounded-full font-medium">
                            Payment rejected — re-upload proof
                        </span>
                    </div>

                    <div v-if="profile?.payment_details_text" class="bg-white/70 border border-amber-100 rounded-lg p-4">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">How to pay</p>
                        <pre class="text-sm text-gray-700 whitespace-pre-wrap font-sans leading-relaxed">{{ profile.payment_details_text }}</pre>
                    </div>
                    <p v-else class="text-sm text-amber-800/80 italic">
                        Payment instructions are not configured yet. Please contact your Sahodaya office for bank/UPI details.
                    </p>

                    <form @submit.prevent="uploadPayment" class="bg-white border border-amber-100 rounded-xl p-5 space-y-4">
                        <p class="text-sm font-semibold text-gray-800">Upload payment proof for Sahodaya approval</p>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Payment proof *</label>
                            <input type="file" required accept=".pdf,.jpg,.jpeg,.png" @change="paymentForm.payment_proof = $event.target.files[0]"
                                   class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700">
                            <p class="text-xs text-gray-400 mt-1">PDF, JPG or PNG — max 5 MB</p>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Payment method</label>
                                <input v-model="paymentForm.payment_method" type="text" placeholder="UPI / NEFT / Cash"
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1.5">Transaction reference</label>
                                <input v-model="paymentForm.transaction_ref" type="text" placeholder="UTR / ref no."
                                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                            </div>
                        </div>
                        <button type="submit" :disabled="paymentForm.processing"
                                class="bg-green-600 text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-green-700 disabled:opacity-50">
                            Submit payment for approval
                        </button>
                    </form>

                    <p v-if="latestRejectedPayment" class="text-sm text-red-600">
                        Previous payment rejected: {{ latestRejectedPayment.rejection_reason }}
                    </p>
                </div>

                <!-- Awaiting Sahodaya verification -->
                <div v-if="registration.registration_status === 'payment_submitted'"
                     class="bg-blue-50 border border-blue-200 rounded-xl p-6 space-y-2">
                    <p class="font-semibold text-blue-900">Payment submitted — awaiting Sahodaya approval</p>
                    <p class="text-sm text-blue-800">
                        Your payment proof (₹{{ formatAmount(registration.membership_fee_amount) }}) has been sent to Sahodaya for verification.
                        You will be notified once approved.
                    </p>
                    <div v-if="payments?.length" class="text-sm text-blue-800/80 pt-2 border-t border-blue-100 mt-3 space-y-1">
                        <p v-for="p in payments" :key="p.id">
                            ₹{{ formatAmount(p.amount) }} — {{ p.status }}
                            <span v-if="p.transaction_ref" class="text-xs">({{ p.transaction_ref }})</span>
                        </p>
                    </div>
                </div>

                <!-- Completed -->
                <div v-if="registration.registration_status === 'completed'"
                     class="bg-green-50 border border-green-200 rounded-xl p-6 space-y-4">
                    <div>
                        <p class="font-semibold text-green-900">Membership registration complete</p>
                        <p class="text-sm text-green-800 mt-1">
                            {{ academicYear }} membership is active. Membership No: {{ registration.reg_no }}
                        </p>
                        <p v-if="school.membership_status === 'approved'" class="text-sm text-green-700 mt-2">
                            Your school is approved by Sahodaya. You can now manage students and use the portal.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3 pt-2 border-t border-green-200/80">
                        <Link :href="`/school-admin/${school.id}`"
                              class="inline-flex items-center px-4 py-2 rounded-lg bg-white border border-green-200 text-sm font-semibold text-green-900 hover:bg-green-100/50">
                            Go to Dashboard
                        </Link>
                        <Link :href="`/school-admin/${school.id}/students`"
                              class="inline-flex items-center px-4 py-2 rounded-lg bg-green-700 text-sm font-semibold text-white hover:bg-green-800">
                            Manage Students
                        </Link>
                        <Link :href="`/school-admin/${school.id}/registration/profile`"
                              class="inline-flex items-center px-4 py-2 rounded-lg bg-white border border-green-200 text-sm font-semibold text-green-900 hover:bg-green-100/50">
                            Registration Details
                        </Link>
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

const props = defineProps({
    school: Object,
    academicYear: String,
    registration: Object,
    profile: Object,
    registrationWindow: Object,
    payments: { type: Array, default: () => [] },
    canBegin: Boolean,
    membershipFeePreview: [Number, String, null],
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
    }[s] || 'bg-gray-100 text-gray-600';
}

function trackDone(status) {
    return status === 'approved';
}

function trackLabel(status) {
    if (status === 'approved') return 'Submitted';
    if (status === 'not_applicable') return 'Not required';
    if (status === 'rejected') return 'Rejected — update & resubmit';
    return 'Pending — open to submit';
}

function trackStatusClass(status) {
    if (status === 'approved') return 'text-green-600';
    if (status === 'rejected') return 'text-red-600';
    return 'text-amber-600';
}
</script>
