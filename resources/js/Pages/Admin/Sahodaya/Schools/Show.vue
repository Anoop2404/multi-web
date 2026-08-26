<template>
    <SahodayaAdminLayout :title="school.name" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :approvedSchoolsCount="approvedSchoolsCount"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="max-w-3xl space-y-5">
            <InlineAlert :message="alertMessage" type="error" @dismiss="alertMessage = ''" />
            <Link :href="`/sahodaya-admin/${sahodaya.id}/schools`"
                  class="inline-flex items-center gap-1.5 text-xs text-[#0f3d7a] hover:underline font-semibold">
                ← Back to Schools
            </Link>

            <div v-if="school.membership_status === 'pending'"
                 class="text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-xl px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                <div class="space-y-1">
                    <p>
                        This school is <strong>pending approval</strong>. You can approve once payment is verified.
                    </p>
                    <div class="flex items-center gap-3 mt-1">
                        <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium"
                              :class="school.has_payment ? 'bg-green-50 text-green-700 ring-1 ring-green-600/20' : 'bg-red-50 text-red-700 ring-1 ring-red-600/20'">
                            <span class="h-1.5 w-1.5 rounded-full" :class="school.has_payment ? 'bg-green-600' : 'bg-red-600'"></span>
                            {{ school.has_payment ? `Payment Uploaded (₹${Number(school.payment_amount).toLocaleString('en-IN')})` : 'Payment Not Uploaded' }}
                        </span>
                        <a v-if="school.payment_proof_url" :href="school.payment_proof_url" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1 text-xs text-[#0f3d7a] font-semibold hover:underline">
                            View Proof ↗
                        </a>
                    </div>
                </div>
                <div class="flex gap-2 shrink-0">
                    <button type="button" 
                            class="btn-primary text-sm" 
                            :class="!school.has_payment ? 'opacity-40 cursor-not-allowed pointer-events-none' : ''"
                            :disabled="!school.has_payment"
                            @click="approveSchool">
                        Approve school
                    </button>
                    <button type="button" class="btn-secondary text-sm text-red-700 border-red-200" @click="rejectSchool">Reject</button>
                </div>
            </div>

            <div v-else-if="school.can_cancel_membership"
                 class="text-sm text-amber-900 bg-amber-50 border border-amber-100 rounded-xl px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p>
                        This school is <strong>approved</strong> but has <strong>no submitted/verified membership payment</strong>.
                    </p>
                    <p v-if="school.admin_note" class="text-xs text-amber-800 font-semibold mt-1">
                        📝 Admin note: {{ school.admin_note }}
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" class="btn-primary text-xs" @click="showUploadProofModal = true">
                        + Upload / Attach Payment Proof
                    </button>
                    <button type="button" class="btn-secondary text-sm text-red-700 border-red-200 shrink-0" @click="cancelMembership">
                        Cancel membership
                    </button>
                </div>
            </div>

            <div v-else-if="school.can_cancel_with_settlement"
                 class="text-sm text-red-900 bg-red-50 border border-red-100 rounded-xl px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                <div class="space-y-1">
                    <p>
                        This school is <strong>approved</strong> and has a verified payment. 
                    </p>
                    <p v-if="school.admin_note" class="text-xs text-red-800 font-semibold mt-1">
                        📝 Admin note: {{ school.admin_note }}
                    </p>
                    <p class="text-xs text-red-700 font-medium">Cancelling will reject their membership and requires resolving their payment.</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" class="btn-secondary text-xs" @click="showUploadProofModal = true">
                        Update payment proof
                    </button>
                    <button type="button" class="btn-secondary text-sm text-red-700 border-red-200 shrink-0" @click="cancelMembershipWithSettlement">
                        Cancel membership (with settlement)
                    </button>
                </div>
            </div>

            <!-- Header -->
            <div class="card">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-extrabold text-gray-900">{{ school.name }}</h2>
                            <StatusBadge :status="school.membership_status" />
                            <span v-if="school.is_non_affiliated"
                                  class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-amber-200">
                                Non-affiliated
                            </span>
                            <span v-else
                                  class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-800 ring-1 ring-emerald-200">
                                Affiliated
                            </span>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">
                            <span v-if="school.school_prefix" class="font-mono bg-gray-100 px-1.5 py-0.5 rounded text-xs mr-2">{{ school.school_prefix }}</span>
                            Registered {{ formatDate(school.created_at) }}
                        </p>
                    </div>
                    <div class="text-right text-sm space-y-1">
                        <p>
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/schools/${school.id}/students`"
                                  class="font-semibold text-[#0f3d7a] hover:underline">
                                {{ school.student_count }} students →
                            </Link>
                        </p>
                        <p class="text-gray-500"><strong>{{ school.classes_count }}</strong> classes</p>
                        <Link :href="`/sahodaya-admin/${sahodaya.id}/schools/${school.id}/lock-overrides`"
                              class="text-xs text-[#0f3d7a] hover:underline">
                            Lock overrides →
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Application details -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-bold text-gray-900">Application Details</h3>
                </div>
                <dl v-if="detailFields.length" class="divide-y divide-gray-50">
                    <div v-for="field in detailFields" :key="field.label"
                         class="px-5 py-3 flex flex-col sm:flex-row sm:gap-4">
                        <dt class="text-xs font-semibold text-gray-400 uppercase sm:w-40 shrink-0">{{ field.label }}</dt>
                        <dd class="text-sm text-gray-800 break-words">{{ field.value }}</dd>
                    </div>
                </dl>
                <p v-else class="text-sm text-gray-400 text-center py-8">No application data on file.</p>
            </div>

            <!-- Sahodaya Admin Note Card -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold text-gray-900 flex items-center gap-2">
                        <span>📝 Sahodaya Admin Note</span>
                    </h3>
                    <span v-if="adminNoteSaved" class="text-xs font-semibold text-emerald-600">Saved!</span>
                </div>
                <p class="text-xs text-gray-500">
                    Internal note regarding this school's approval status, payment arrangement, or fee terms.
                </p>
                <textarea v-model="adminNoteText" rows="2"
                          placeholder="e.g. Approved conditionally by Sahodaya President; payment proof to be submitted later / Cash collected at office"
                          class="w-full text-sm border border-gray-200 rounded-xl p-3 focus:ring-2 focus:ring-purple-200 focus:outline-none"></textarea>
                <div class="flex justify-end">
                    <button type="button" class="btn-primary text-xs !py-1.5" :disabled="savingAdminNote" @click="saveAdminNote">
                        {{ savingAdminNote ? 'Saving note…' : 'Save Admin Note' }}
                    </button>
                </div>
            </div>

            <!-- Login -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h3 class="font-bold text-gray-900">Portal Access & Credentials</h3>
                    <span :class="school.has_login ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : 'bg-amber-50 text-amber-800 ring-1 ring-amber-200'"
                          class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold">
                        {{ school.has_login ? '✓ Active Login Account' : '⚠️ No Login Created' }}
                    </span>
                </div>

                <div class="space-y-4">
                    <!-- When school HAS a login -->
                    <div v-if="school.has_login" class="rounded-xl border border-emerald-100 bg-emerald-50/40 p-4 space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-emerald-100 pb-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">School Admin Login</p>
                                <p class="text-base font-bold text-slate-900 mt-0.5">{{ school.login_user?.name || school.name }}</p>
                                <p class="text-xs font-mono text-slate-600 mt-0.5">
                                    Username: <span class="font-semibold text-slate-900 select-all">{{ school.login_user?.username || school.login_email }}</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span v-if="school.login_user?.email_verified"
                                      class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                    ✓ Email Verified
                                </span>
                                <span v-else
                                      class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                                    ⏳ Pre-verified / Temporary
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <button type="button"
                                    class="btn-secondary text-xs"
                                    :disabled="credentialProcessing"
                                    @click="resendSchoolCredentials">
                                ✉ Resend credentials email
                            </button>
                            <button type="button"
                                    class="btn-secondary text-xs text-red-700 border-red-200 hover:bg-red-50"
                                    :disabled="credentialProcessing"
                                    @click="resetSchoolPassword">
                                🔑 Reset password & email new temporary key
                            </button>
                        </div>
                    </div>

                    <!-- When school DOES NOT HAVE a login -->
                    <div v-else class="space-y-3 rounded-xl border border-amber-200 bg-amber-50/70 p-4">
                        <div class="flex items-start gap-3">
                            <span class="text-xl">🔑</span>
                            <div>
                                <p class="text-sm font-semibold text-amber-900">No portal login created for this school</p>
                                <p class="text-xs text-amber-700 mt-0.5">
                                    Create a portal login using the target email address below. A temporary password will be generated and emailed directly to the school.
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div class="flex-1">
                                <label class="text-xs font-semibold uppercase tracking-wide text-amber-800">Target Email Address</label>
                                <input v-model="createLoginEmail" type="email" class="field mt-1 bg-white" placeholder="school@example.com">
                            </div>
                            <button type="button"
                                    class="btn-primary text-sm bg-amber-700 hover:bg-amber-800 border-amber-700"
                                    :disabled="creatingLogin || !createLoginEmail.trim()"
                                    @click="createSchoolLogin">
                                {{ creatingLogin ? 'Creating login…' : '🔑 Create Login & Send Credentials' }}
                            </button>
                        </div>
                    </div>

                    <!-- Update School Email Form -->
                    <div class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Update school email address</p>
                            <p class="text-xs text-slate-600">
                                Updates the school's contact email and portal login email together.
                            </p>
                        </div>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div class="flex-1">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Email Address</label>
                                <input v-model="schoolEmail" type="email" class="field mt-1 bg-white" placeholder="school@example.com">
                            </div>
                            <button type="button"
                                    class="btn-primary text-sm"
                                    :disabled="emailProcessing || !schoolEmail.trim()"
                                    @click="saveSchoolEmail">
                                {{ emailProcessing ? 'Saving…' : 'Save email' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fest registration -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-bold text-gray-900 mb-1">Fest Registration</h3>
                        <p class="text-sm text-gray-600">
                            Status:
                            <span :class="school.fest_registration_closed ? 'text-red-700 font-semibold' : 'text-emerald-700 font-semibold'">
                                {{ school.fest_registration_closed ? 'Closed for this school' : 'Open' }}
                            </span>
                        </p>
                    </div>
                    <button type="button" @click="toggleFestRegistration"
                            :class="school.fest_registration_closed ? 'btn-primary' : 'btn-secondary text-red-700 border-red-200 bg-red-50'">
                        {{ school.fest_registration_closed ? 'Reopen fest registration' : 'Close fest registration' }}
                    </button>
                </div>
            </div>

            <!-- Annual registration -->
            <div v-if="registration" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <h3 class="font-bold text-gray-900 mb-3">Annual Registration — {{ academicYear }}</h3>
                <div class="flex flex-wrap gap-4 text-sm">
                    <div>
                        <p class="text-xs text-gray-400">Membership No.</p>
                        <p class="font-mono font-bold">{{ registration.reg_no }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Academic year</p>
                        <p class="font-mono">{{ academicYear }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Status</p>
                        <StatusBadge :status="registration.registration_status" />
                    </div>
                    <div v-if="registration.membership_fee_amount">
                        <p class="text-xs text-gray-400">Fee</p>
                        <p class="font-bold">₹{{ Number(registration.membership_fee_amount).toLocaleString('en-IN') }}</p>
                    </div>
                </div>
            </div>

            <!-- Recent payments -->
            <div v-if="recentPayments?.length" class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-bold text-gray-900">Payment History</h3>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/membership/payments`"
                          class="text-xs font-semibold text-[#0f3d7a] hover:underline">Verify payments →</Link>
                </div>
                <div class="divide-y divide-gray-50">
                    <div v-for="p in recentPayments" :key="p.id" class="px-5 py-3 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">{{ p.academic_year }} — ₹{{ Number(p.amount).toLocaleString('en-IN') }}</p>
                            <p class="text-xs text-gray-400">{{ formatDate(p.created_at) }} · {{ p.payment_method || '—' }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <StatusBadge :status="p.status" />
                            <a v-if="p.proof_url" :href="p.proof_url" target="_blank" rel="noopener"
                               class="text-xs font-semibold text-[#0f3d7a] hover:underline">View upload ↗</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Danger zone -->
            <div class="bg-white rounded-2xl border border-red-200 shadow-sm p-5">
                <h3 class="font-bold text-red-800 mb-1">Delete school permanently</h3>
                <p class="text-sm text-red-700/90 mb-4">
                    Removes this school, all students, fest registrations, payments, portal users, and website content.
                    This cannot be undone.
                    <span v-if="school.student_count > 0" class="block mt-2 font-semibold">
                        Warning: {{ school.student_count }} active student(s) will be deleted.
                    </span>
                </p>
                <div class="space-y-3 max-w-md">
                    <div>
                        <label class="text-xs font-semibold text-gray-500 uppercase">Reason</label>
                        <input v-model="deleteReason" type="text" class="field mt-1"
                               placeholder="Why is this school being removed?">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-500 uppercase">
                            Type <span class="font-mono normal-case">{{ school.name }}</span> to confirm
                        </label>
                        <input v-model="deleteConfirmName" type="text" class="field mt-1"
                               :placeholder="school.name">
                    </div>
                    <button type="button"
                            class="btn-secondary text-sm text-red-700 border-red-300 bg-red-50 hover:bg-red-100"
                            :disabled="deleteProcessing || !canDeleteSchool"
                            @click="deleteSchool">
                        Delete school permanently
                    </button>
                </div>
            </div>
        </div>

        <!-- Upload Payment Proof Modal -->
        <div v-if="showUploadProofModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl border border-gray-100">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <h3 class="font-bold text-gray-900 text-base">Upload / Attach Payment Proof</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600 text-xl font-bold cursor-pointer" @click="showUploadProofModal = false">&times;</button>
                </div>
                <p class="text-xs text-gray-500">
                    Attach proof document or record payment reference details for <strong>{{ school.name }}</strong> ({{ academicYear }}).
                </p>

                <form @submit.prevent="submitAdminPaymentProof" class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Amount (₹)</label>
                        <input v-model="proofForm.amount" type="number" step="0.01" class="field" required>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Reference / Receipt No / Note</label>
                        <input v-model="proofForm.payment_reference" type="text" placeholder="e.g. Bank Ref # / Cash / Receipt No" class="field">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Payment Proof File (PDF or Image)</label>
                        <input type="file" @change="e => proofForm.proof = e.target.files[0]" accept=".pdf,.png,.jpg,.jpeg" class="field text-xs">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Payment Status</label>
                        <SearchableSelect v-model="proofForm.status"
                                          :options="[{ value: 'verified', label: '✓ Verified (Fee cleared immediately)' }, { value: 'submitted', label: '⏳ Submitted (Pending Review)' }]"
                                          :all-option="false"
                                          placeholder="Select payment status" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                        <button type="button" class="btn-ghost text-xs" @click="showUploadProofModal = false">Cancel</button>
                        <button type="submit" class="btn-primary text-xs" :disabled="proofForm.processing">
                            {{ proofForm.processing ? 'Saving proof…' : 'Save & Verify Payment' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, defineComponent, h, ref } from 'vue';
import InlineAlert from '@/Components/ui/InlineAlert.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useConfirm } from '@/composables/useConfirm';

const { confirm, prompt } = useConfirm();
const alertMessage = ref('');

const props = defineProps({
    sahodaya: Object, publicUrl: String,
    approvedSchoolsCount: Number, pendingSchoolsCount: Number,
    pendingSubmissionsCount: Number, pendingPaymentsCount: Number,
    school: Object, detailFields: Array, registration: Object,
    recentPayments: Array, academicYear: String,
});

const deleteReason = ref('');
const deleteConfirmName = ref('');
const deleteProcessing = ref(false);
const schoolEmail = ref(props.school.contact_email || props.school.login_email || '');
const emailProcessing = ref(false);
const credentialProcessing = ref(false);

const createLoginEmail = ref(props.school.contact_email || props.school.login_email || '');
const creatingLogin = ref(false);

async function createSchoolLogin() {
    const email = createLoginEmail.value.trim();
    if (!email) return;
    if (!(await confirm({ message: `Create portal login for ${props.school.name} (${email}) and send login details?`, destructive: false }))) return;

    creatingLogin.value = true;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/schools/${props.school.id}/create-login`, {
        email: email,
    }, {
        preserveScroll: true,
        onFinish: () => { creatingLogin.value = false; },
    });
}

const adminNoteText = ref(props.school.admin_note || '');
const savingAdminNote = ref(false);
const adminNoteSaved = ref(false);

const showUploadProofModal = ref(false);
const proofForm = useForm({
    amount: props.school.payment_amount ?? 4000,
    payment_reference: '',
    proof: null,
    notes: '',
    status: 'verified',
});

function saveAdminNote() {
    savingAdminNote.value = true;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/schools/${props.school.id}/note`, {
        admin_note: adminNoteText.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            savingAdminNote.value = false;
            adminNoteSaved.value = true;
            setTimeout(() => { adminNoteSaved.value = false; }, 3000);
        },
        onError: () => {
            savingAdminNote.value = false;
        },
    });
}

function submitAdminPaymentProof() {
    proofForm.post(`/sahodaya-admin/${props.sahodaya.id}/schools/${props.school.id}/payment-proof`, {
        preserveScroll: true,
        onSuccess: () => {
            showUploadProofModal.value = false;
            proofForm.reset();
        },
    });
}

const canDeleteSchool = computed(() =>
    deleteReason.value.trim() !== '' && deleteConfirmName.value === props.school.name,
);

async function deleteSchool() {
    if (!canDeleteSchool.value || deleteProcessing.value) return;
    if (!(await confirm({ message: `Permanently delete "${props.school.name}" and all related data? This cannot be undone.` }))) return;

    deleteProcessing.value = true;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/schools/${props.school.id}`, {
        data: {
            confirm_name: deleteConfirmName.value,
            reason: deleteReason.value.trim(),
        },
        onFinish: () => { deleteProcessing.value = false; },
    });
}

async function toggleFestRegistration() {
    const action = props.school.fest_registration_closed ? 'reopen' : 'close';
    if (!(await confirm({ message: `${action.charAt(0).toUpperCase() + action.slice(1)} fest registration for this school?`, destructive: action === 'close' }))) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/schools/${props.school.id}/toggle-fest-registration`, {}, { preserveScroll: true });
}

async function saveSchoolEmail() {
    const nextEmail = schoolEmail.value.trim();
    if (!nextEmail) return;
    if (!(await confirm({ message: `Update the school email to ${nextEmail}? This will also update the login email.`, destructive: false }))) return;

    emailProcessing.value = true;
    router.put(`/sahodaya-admin/${props.sahodaya.id}/schools/${props.school.id}/email`, {
        email: nextEmail,
    }, {
        preserveScroll: true,
        onFinish: () => { emailProcessing.value = false; },
    });
}

async function resendSchoolCredentials() {
    if (!props.school.has_login) return;
    if (!(await confirm({ message: `Resend the current credentials for ${props.school.name}?`, destructive: false }))) return;

    credentialProcessing.value = true;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/schools/${props.school.id}/resend-credentials`, {}, {
        preserveScroll: true,
        onFinish: () => { credentialProcessing.value = false; },
    });
}

async function resetSchoolPassword() {
    if (!props.school.has_login) return;
    if (!(await confirm({ message: `Reset the password for ${props.school.name}? A new temporary password will be emailed.` }))) return;

    credentialProcessing.value = true;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/schools/${props.school.id}/reset-password`, {}, {
        preserveScroll: true,
        onFinish: () => { credentialProcessing.value = false; },
    });
}

async function approveSchool() {
    if (!(await confirm({ message: `Approve ${props.school.name}?`, destructive: false }))) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/schools/${props.school.id}/approve`, {}, { preserveScroll: true });
}

async function rejectSchool() {
    const reason = await prompt({ message: 'Rejection reason:', inputMultiline: true });
    if (!reason?.trim()) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/schools/${props.school.id}/reject`, { reason }, { preserveScroll: true });
}

async function cancelMembership() {
    const reason = await prompt({ message: `Reason for cancelling membership — ${props.school.name}?`, inputMultiline: true });
    if (!reason?.trim()) return;
    if (!(await confirm({ message: `Cancel membership for "${props.school.name}"? They will be removed from approved member schools.` }))) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/schools/${props.school.id}/cancel-membership`, {
        reason: reason.trim(),
    }, { preserveScroll: true });
}

async function cancelMembershipWithSettlement() {
    const settlementInput = await prompt({
        message:
            `Cancel membership for "${props.school.name}"?\n\n` +
            `This school has a verified payment. How should this be settled?\n` +
            `Type "1" for Credit (toward next year)\n` +
            `Type "2" for Forfeit (no credit)`,
        inputPlaceholder: '1 or 2',
    });

    if (!settlementInput) return;

    let settlement = null;
    if (settlementInput.trim() === '1') {
        settlement = 'credit_next_year';
    } else if (settlementInput.trim() === '2') {
        settlement = 'forfeit';
    } else {
        alertMessage.value = 'Invalid option selected.';
        return;
    }

    const reason = await prompt({ message: `Reason for cancelling membership — ${props.school.name}?`, inputMultiline: true });
    if (!reason?.trim()) return;

    router.post(`/sahodaya-admin/${props.sahodaya.id}/schools/${props.school.id}/cancel-membership`, {
        reason: reason.trim(),
        settlement: settlement,
    }, { preserveScroll: true });
}

function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

const statusColors = {
    approved: 'bg-green-100 text-green-700',
    pending:  'bg-amber-100 text-amber-700',
    rejected: 'bg-red-100 text-red-700',
    submitted: 'bg-amber-100 text-amber-700',
    verified:  'bg-green-100 text-green-700',
    completed: 'bg-green-100 text-green-700',
    payment_pending: 'bg-blue-100 text-blue-700',
    payment_submitted: 'bg-amber-100 text-amber-700',
};

const StatusBadge = defineComponent({
    props: { status: String },
    setup(p) {
        return () => h('span', {
            class: ['inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold capitalize',
                    statusColors[p.status] || 'bg-gray-100 text-gray-600'],
        }, (p.status || '').replace(/_/g, ' '));
    },
});
</script>
