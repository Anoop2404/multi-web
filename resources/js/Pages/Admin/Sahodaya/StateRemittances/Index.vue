<template>
    <SahodayaEventsLayout title="State Remittances" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="State remittances" eyebrow="Tools"
                    description="Upload and track state-level remittance payments for Sahodaya programs." />

        <!-- Summary -->
        <div class="grid sm:grid-cols-3 gap-4 mb-5">
            <div class="bg-yellow-50 border border-yellow-100 rounded-xl p-4">
                <p class="text-xs text-yellow-700 font-bold uppercase">Pending</p>
                <p class="text-2xl font-bold text-yellow-900">{{ summary.pending }}</p>
                <p class="text-xs text-yellow-600 mt-1">₹{{ fmt(summary.total_due) }} outstanding</p>
            </div>
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                <p class="text-xs text-blue-700 font-bold uppercase">Awaiting verification</p>
                <p class="text-2xl font-bold text-blue-900">{{ summary.submitted }}</p>
            </div>
            <div class="bg-green-50 border border-green-100 rounded-xl p-4">
                <p class="text-xs text-green-700 font-bold uppercase">Verified</p>
                <p class="text-2xl font-bold text-green-900">₹{{ fmt(summary.total_paid) }}</p>
            </div>
        </div>

        <div class="space-y-3">
            <div v-for="r in remittances" :key="r.id"
                 class="card flex flex-wrap items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <span :class="statusClass(r.status)" class="text-xs font-bold px-2 py-0.5 rounded">{{ r.status }}</span>
                        <span class="font-semibold text-gray-900">{{ r.title }}</span>
                        <span v-if="r.academic_year" class="text-xs text-gray-400">{{ r.academic_year }}</span>
                    </div>
                    <p v-if="r.description" class="text-sm text-gray-600">{{ r.description }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                        <span v-if="r.due_date">Due: {{ formatCalendarDate(r.due_date) }}</span>
                        <span v-if="r.transaction_ref" class="ml-3">Ref: {{ r.transaction_ref }}</span>
                    </p>
                    <p v-if="r.rejection_reason" class="text-xs text-red-600 mt-1">Rejected: {{ r.rejection_reason }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <p class="font-bold text-gray-900">₹{{ fmt(r.amount) }}</p>
                    <!-- Upload proof form (for pending/rejected) -->
                    <div v-if="['pending', 'rejected'].includes(r.status)">
                        <button @click="openProofForm(r)"
                                class="px-3 py-1.5 text-white text-xs font-semibold rounded-lg">
                            Upload proof
                        </button>
                    </div>
                    <span v-else-if="r.status === 'submitted'" class="text-xs text-blue-700 font-semibold">Under review</span>
                    <span v-else-if="r.status === 'verified'" class="text-xs text-green-700 font-semibold">✓ Verified</span>
                </div>
            </div>
            <p v-if="!remittances.length" class="text-center text-gray-400 py-12">No remittance demands from state yet.</p>
        </div>

        <!-- Upload proof modal -->
        <div v-if="proofTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="proofTarget = null">
            <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-2xl space-y-3">
                <h3 class="font-semibold">Upload payment proof</h3>
                <p class="text-sm text-gray-600">{{ proofTarget.title }} — ₹{{ fmt(proofTarget.amount) }}</p>
                <form @submit.prevent="submitProof" class="space-y-2">
                    <input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="e => proofFile = e.target.files[0]" required class="text-sm">
                    <div class="grid grid-cols-2 gap-2">
                        <input v-model="proofRef" class="field" placeholder="Transaction ref">
                        <input v-model="proofBank" class="field" placeholder="Bank name">
                    </div>
                    <input v-model="proofDate" type="date" class="field">
                    <div class="flex gap-2 justify-end pt-1">
                        <button type="button" @click="proofTarget = null" class="px-4 py-2 border rounded-lg text-sm">Cancel</button>
                        <button type="submit" class="btn-primary">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import { formatCalendarDate } from '@/support/calendarDates.js';

const props = defineProps({ sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number, remittances: Array, summary: Object });

const proofTarget = ref(null);
const proofFile   = ref(null);
const proofRef    = ref('');
const proofBank   = ref('');
const proofDate   = ref(new Date().toISOString().slice(0, 10));

function fmt(v) {
    return Number(v ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function statusClass(s) {
    return { pending: 'bg-yellow-50 text-yellow-700', submitted: 'bg-blue-50 text-blue-700', verified: 'bg-green-50 text-green-700', rejected: 'bg-red-50 text-red-600' }[s] ?? 'bg-gray-100';
}

function openProofForm(r) {
    proofTarget.value = r;
    proofFile.value   = null;
    proofRef.value    = '';
    proofBank.value   = '';
    proofDate.value   = new Date().toISOString().slice(0, 10);
}

function submitProof() {
    if (!proofFile.value) return;
    const fd = new FormData();
    fd.append('proof', proofFile.value);
    if (proofRef.value) fd.append('transaction_ref', proofRef.value);
    if (proofBank.value) fd.append('bank_name', proofBank.value);
    if (proofDate.value) fd.append('payment_date', proofDate.value);

    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/state-remittances/${proofTarget.value.id}/proof`,
        fd,
        { preserveScroll: true, onSuccess: () => { proofTarget.value = null; } }
    );
}
</script>

