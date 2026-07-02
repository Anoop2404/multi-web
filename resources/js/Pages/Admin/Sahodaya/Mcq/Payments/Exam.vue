<template>
    <SahodayaAdminLayout :title="`Payments — ${exam.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="MCQ exam"
                    description="Verify school batch payments and confirm registrations with hall tickets." />
        <McqExamSubNav :sahodaya-id="sahodaya.id" :exam-id="exam.id" active="payments" />

        <div v-if="pendingCount" class="card card--accent !border-amber-200 mb-4 text-sm">
            <p class="font-semibold text-amber-900">{{ pendingCount }} school batch fee(s) awaiting approval</p>
        </div>

        <div class="card card--flush overflow-hidden">
            <EmptyState v-if="!schoolFees.length" title="No school fees yet" description="Fees appear when schools register students and upload batch payment proof." icon="💳" class="py-10" />
            <div v-else class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>School</th>
                            <th>Students</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="sf in schoolFees" :key="sf.id">
                            <td class="font-medium">{{ sf.school_name }}</td>
                            <td>{{ sf.student_count }}</td>
                            <td class="font-semibold">₹{{ sf.total_due }}</td>
                            <td class="text-xs capitalize">{{ sf.status?.replace('_', ' ') }}</td>
                            <td class="text-xs">
                                <template v-if="sf.fee_receipt">
                                    {{ sf.fee_receipt.payment_date || '—' }}
                                    <span v-if="sf.fee_receipt.transaction_ref" class="text-slate-500"> · {{ sf.fee_receipt.transaction_ref }}</span>
                                </template>
                                <span v-else class="text-slate-400">Not uploaded</span>
                            </td>
                            <td class="text-xs whitespace-nowrap text-right space-x-2">
                                <a v-if="sf.fee_receipt?.proof_url" :href="sf.fee_receipt.proof_url" target="_blank" rel="noopener" class="link-brand">View proof</a>
                                <button v-if="sf.fee_receipt?.status === 'uploaded'" type="button" @click="approve(sf.id)" class="text-green-700 font-semibold">Approve & confirm</button>
                                <span v-else-if="sf.status === 'approved'" class="text-green-700 font-semibold">Approved</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    exam: Object,
    schoolFees: { type: Array, default: () => [] },
    pendingCount: { type: Number, default: 0 },
});

function approve(schoolFeeId) {
    if (!confirm('Approve fee and issue hall tickets for all pending registrations from this school?')) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/payments/${schoolFeeId}/approve`, {}, { preserveScroll: true });
}
</script>
