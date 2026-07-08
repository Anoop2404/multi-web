<template>
    <SchoolAdminLayout title="Membership Payment" :school="school" :show-header-title="false">
        <PageHeader title="Membership payment" eyebrow="Membership"
                    description="Pay the annual fee and upload proof for Sahodaya verification." />

        <div class="max-w-xl space-y-5">
            <MembershipWorkflowNav :school="school"
                                   :profile="profile"
                                   :registration="registration"
                                   current="payment" />

            <div class="card space-y-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Amount due</p>
                    <p class="text-3xl font-bold text-slate-900 mt-1">₹{{ formatAmount(registration.membership_fee_amount) }}</p>
                </div>
                <div v-if="paymentDetails" class="border-t border-slate-100 pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">How to pay</p>
                    <pre class="text-sm text-slate-700 whitespace-pre-wrap font-sans leading-relaxed bg-slate-50 rounded-xl p-4">{{ paymentDetails }}</pre>
                </div>
            </div>

            <form v-if="canUpload" @submit.prevent="upload" class="card space-y-4">
                <h3 class="section-title text-base">Upload payment proof</h3>
                <FormField label="Payment proof" required hint="PDF, JPG, or PNG" :error="form.errors.payment_proof">
                    <input type="file" required accept=".pdf,.jpg,.jpeg,.png" class="field"
                           @change="form.payment_proof = $event.target.files[0]">
                </FormField>
                <FormField label="Payment method" :error="form.errors.payment_method">
                    <input v-model="form.payment_method" class="field" placeholder="e.g. NEFT, UPI, Cheque">
                </FormField>
                <FormField label="Transaction reference" :error="form.errors.transaction_ref">
                    <input v-model="form.transaction_ref" class="field" placeholder="UTR / reference number">
                </FormField>
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary" :disabled="form.processing">Submit proof</button>
                </div>
            </form>

            <div v-if="payments.length" class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Payment history</p>
                <div v-for="p in payments" :key="p.id"
                     class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm flex flex-wrap items-center justify-between gap-2">
                    <span class="font-semibold">₹{{ formatAmount(p.amount) }}</span>
                    <TrackStatusPill :status="paymentStatus(p.status)" />
                    <p v-if="p.rejection_reason" class="w-full text-xs text-red-700 mt-1">{{ p.rejection_reason }}</p>
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import FormField from '@/Components/ui/FormField.vue';
import MembershipWorkflowNav from '@/Components/school/MembershipWorkflowNav.vue';
import TrackStatusPill from '@/Components/ui/TrackStatusPill.vue';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useScrollToFirstError } from '@/composables/useScrollToFirstError.js';

const props = defineProps({
    school: Object,
    registration: Object,
    profile: { type: Object, default: null },
    payments: { type: Array, default: () => [] },
});

const { scrollToFirstError } = useScrollToFirstError();

const form = useForm({ payment_proof: null, payment_method: '', transaction_ref: '' });

const paymentDetails = computed(() => props.profile?.payment_details_text || '');

const canUpload = computed(() =>
    ['payment_pending', 'payment_rejected'].includes(props.registration?.registration_status),
);

function formatAmount(value) {
    const n = Number(value);
    return Number.isFinite(n) ? n.toLocaleString('en-IN') : value;
}

function paymentStatus(status) {
    if (status === 'approved') return 'approved';
    if (status === 'rejected') return 'rejected';
    if (status === 'pending') return 'submitted';
    return 'pending';
}

function upload() {
    form.post(`/school-admin/${props.school.id}/registration/payment`, {
        forceFormData: true,
        preserveScroll: true,
        onError: () => scrollToFirstError(form.errors),
    });
}
</script>
