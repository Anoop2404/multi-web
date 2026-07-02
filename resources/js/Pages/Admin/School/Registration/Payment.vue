<template>
    <SchoolAdminLayout title="Membership Payment" :school="school" :show-header-title="false">
        <PageHeader title="Membership Payment" eyebrow="Membership"
            description="Annual Sahodaya membership registration and school profile." />


        <div class="max-w-xl space-y-4">
            <Link :href="`/school-admin/${school.id}/registration`" class="text-sm text-blue-600">← Registration</Link>

            <div class="card space-y-4">
                <div>
                    <p class="text-sm text-gray-500">Amount due</p>
                    <p class="text-2xl font-bold">₹{{ registration.membership_fee_amount }}</p>
                </div>
                <div v-if="paymentDetails" class="border-t pt-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Payment Details</p>
                    <pre class="text-sm text-gray-700 whitespace-pre-wrap font-sans leading-relaxed">{{ paymentDetails }}</pre>
                </div>
            </div>

            <form v-if="['payment_pending','payment_rejected'].includes(registration.registration_status)" @submit.prevent="upload" class="card space-y-3">
                <input type="file" required accept=".pdf,.jpg,.jpeg,.png" @change="form.payment_proof = $event.target.files[0]" class="text-sm">
                <input v-model="form.payment_method" placeholder="Payment method" class="w-full border rounded-lg px-3 py-2 text-sm">
                <input v-model="form.transaction_ref" placeholder="Transaction reference" class="w-full border rounded-lg px-3 py-2 text-sm">
                <button type="submit" class="btn-primary px-4 py-2 rounded-lg text-sm font-semibold">Upload Proof</button>
            </form>

            <div v-for="p in payments" :key="p.id" class="text-sm border rounded-lg p-3">
                ₹{{ p.amount }} — {{ p.status }} <span v-if="p.rejection_reason" class="text-red-500">({{ p.rejection_reason }})</span>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    school: Object, registration: Object, profile: Object, payments: Array,
});
const form = useForm({ payment_proof: null, payment_method: '', transaction_ref: '' });

const paymentDetails = computed(() => props.profile?.payment_details_text || '');

function upload() { form.post(`/school-admin/${props.school.id}/registration/payment`, { forceFormData: true }); }
</script>
