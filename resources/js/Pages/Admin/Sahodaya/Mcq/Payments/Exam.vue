<template>
    <SahodayaAdminLayout :title="`Payments — ${exam.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="Talent Search exam"
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
                            <th>Sl No</th>
                            <th>School</th>
                            <th>Students</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(sf, idx) in schoolFees" :key="sf.id">
                        <tr>
                            <td>{{ idx + 1 }}</td>
                            <td class="font-medium">{{ (sf.school_name || '').toUpperCase() }}</td>
                            <td>{{ sf.student_count }}</td>
                            <td class="font-semibold">
                                <template v-if="sf.status === 'partial'">
                                    ₹{{ sf.amount_paid }} <span class="text-slate-400 font-normal">of ₹{{ sf.total_due }}</span>
                                </template>
                                <template v-else>₹{{ sf.total_due }}</template>
                            </td>
                            <td class="text-xs capitalize">{{ sf.status?.replace('_', ' ') }}</td>
                            <td class="text-xs">
                                <template v-if="sf.fee_receipt">
                                    {{ formatCalendarDate(sf.fee_receipt.payment_date) }}
                                    <span v-if="sf.fee_receipt.transaction_ref" class="text-slate-500"> · {{ sf.fee_receipt.transaction_ref }}</span>
                                </template>
                                <span v-else class="text-slate-400">Not uploaded</span>
                            </td>
                            <td class="text-xs whitespace-nowrap text-right space-x-2">
                                <a v-if="sf.fee_receipt?.proof_url" :href="sf.fee_receipt.proof_url" target="_blank" rel="noopener" class="link-brand">View proof</a>
                                <button v-if="sf.fee_receipt?.status === 'uploaded'" type="button" @click="approve(sf.id)" class="text-green-700 font-semibold">Approve & confirm</button>
                                <button v-if="sf.fee_receipt?.status === 'uploaded'" type="button" @click="reject(sf.id)" class="text-red-700 font-semibold">Reject</button>
                                <span v-if="sf.fee_receipt?.status === 'rejected'" class="text-red-700" :title="sf.fee_receipt?.rejection_reason">Rejected</span>
                                <span v-else-if="sf.status === 'approved'" class="text-green-700 font-semibold">Approved</span>
                                <span v-else-if="sf.status === 'partial'" class="text-amber-700 font-semibold">Partial</span>
                                <button v-if="sf.receipts_history?.length > 1" type="button"
                                        class="block ml-auto mt-1 text-[11px] text-indigo-600 hover:text-indigo-800 font-semibold"
                                        @click="toggleExpand(sf.id)">
                                    {{ expanded[sf.id] ? 'Hide' : 'Show' }} history ({{ sf.receipts_history.length }})
                                </button>
                            </td>
                        </tr>
                        <tr v-if="expanded[sf.id] && sf.receipts_history">
                            <td colspan="7" class="bg-slate-50">
                                <div class="pl-3 border-l-2 border-slate-200 space-y-2 py-2">
                                    <div v-for="r in sf.receipts_history" :key="r.id"
                                         class="text-xs text-slate-600 flex flex-wrap items-center justify-between gap-2 bg-white p-2 rounded border border-slate-100">
                                        <div>
                                            <span v-if="r.receipt_number" class="font-mono text-indigo-700 mr-2">#{{ r.receipt_number }}</span>
                                            <span class="text-[10px] uppercase font-semibold px-1.5 py-0.5 rounded mr-2" :class="statusClass(r.status)">{{ r.status }}</span>
                                            <span class="font-semibold">₹{{ r.amount }}</span>
                                            <span v-if="r.payment_date" class="text-slate-400 ml-2">({{ formatCalendarDate(r.payment_date) }})</span>
                                            <span v-if="r.reviewed_by" class="text-slate-400 ml-2">— reviewed by {{ r.reviewed_by }}</span>
                                            <div v-if="r.rejection_reason" class="text-red-600 mt-0.5 font-medium">Rejected: {{ r.rejection_reason }}</div>
                                            <div v-if="r.reversal_reason" class="text-red-600 mt-0.5 font-medium">Reversed: {{ r.reversal_reason }}</div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <a v-if="r.receipt_url" :href="r.receipt_url" target="_blank" rel="noopener" class="text-indigo-600 font-semibold hover:underline">Receipt ↗</a>
                                            <a v-if="r.proof_url" :href="r.proof_url" target="_blank" rel="noopener" class="text-slate-600 font-semibold hover:underline">Proof ↗</a>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';
import { formatCalendarDate } from '@/support/calendarDates.js';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    exam: Object,
    schoolFees: { type: Array, default: () => [] },
    pendingCount: { type: Number, default: 0 },
});

const { confirm, prompt } = useConfirm();

const expanded = ref({});
function toggleExpand(id) {
    expanded.value = { ...expanded.value, [id]: !expanded.value[id] };
}

function statusClass(status) {
    return {
        approved:   'bg-green-50 text-green-700',
        uploaded:   'bg-amber-50 text-amber-700',
        rejected:   'bg-rose-50 text-rose-700',
        reversed:   'bg-red-100 text-red-800 line-through',
        superseded: 'bg-slate-100 text-slate-500 line-through',
        partial:    'bg-amber-100 text-amber-800',
        pending:    'bg-slate-100 text-slate-600',
    }[status] ?? 'bg-slate-100 text-slate-600';
}

async function approve(schoolFeeId) {
    if (!(await confirm({ message: 'Approve fee and issue hall tickets for all pending registrations from this school?', destructive: false }))) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/payments/${schoolFeeId}/approve`, {}, { preserveScroll: true });
}

async function reject(schoolFeeId) {
    const reason = await prompt({ message: 'Rejection reason for the school:', inputMultiline: true });
    if (!reason?.trim()) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/payments/${schoolFeeId}/reject`, {
        rejection_reason: reason.trim(),
    }, { preserveScroll: true });
}
</script>
