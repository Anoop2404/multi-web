<template>
    <div class="space-y-3">
        <div v-for="phaseFee in phaseFees" :key="phaseFee.phase_id"
             class="bg-indigo-50 border border-indigo-100 rounded-xl p-3 text-sm space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-semibold text-indigo-950">{{ phaseFee.phase_name }}</p>
                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full border"
                      :class="phaseFeeStatusClass(phaseFee.status)">
                    {{ phaseFeeStatusLabel(phaseFee.status) }}
                </span>
            </div>
            <p v-if="phaseFee.status === 'rejected' && phaseFee.rejection_reason"
               class="text-xs text-red-600">
                Reason: {{ phaseFee.rejection_reason }}
            </p>
            <ul v-if="(phaseFee.breakdown?.items ?? []).length" class="text-xs text-indigo-900 space-y-1">
                <li v-for="(line, i) in phaseFee.breakdown.items" :key="i" class="flex justify-between gap-4">
                    <span>{{ line.label }}</span>
                    <span class="font-semibold shrink-0">₹{{ formatMoney(line.amount) }}</span>
                </li>
            </ul>
            <div class="flex flex-wrap justify-between gap-2 text-xs pt-2 border-t border-indigo-100">
                <span class="text-indigo-800">
                    Due ₹{{ formatMoney(phaseFee.total_due) }}
                    <span v-if="phaseFee.amount_paid > 0"> · Paid ₹{{ formatMoney(phaseFee.amount_paid) }}</span>
                </span>
                <span class="font-semibold text-indigo-950">
                    Outstanding ₹{{ formatMoney(phaseFee.outstanding) }}
                </span>
            </div>
            <div class="flex flex-wrap gap-2 items-center">
                <form v-if="canUploadPhaseFee(phaseFee)"
                      @submit.prevent="$emit('upload-phase-payment', phaseFee)"
                      class="flex flex-wrap gap-2 items-center">
                    <!-- multiple: up to 5 images for the SAME payment — still one receipt.
                         Txn ref / bank name / amount are all required — used to reconcile
                         against the Sahodaya's bank statement. Independent per phase: paying
                         one phase never gates or credits another — see
                         docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md §3. -->
                    <input type="file" accept=".pdf,.jpg,.jpeg,.png" multiple required
                           @change="e => $emit('set-phase-file', phaseFee.phase_id, Array.from(e.target.files ?? []))"
                           class="text-xs" />
                    <input :value="phasePaymentRef(phaseFee.phase_id)" required
                           @input="e => $emit('update-phase-ref', phaseFee.phase_id, e.target.value)"
                           class="field text-xs w-28" placeholder="Txn ref *">
                    <input :value="phasePaymentBank(phaseFee.phase_id)" required
                           @input="e => $emit('update-phase-bank', phaseFee.phase_id, e.target.value)"
                           class="field text-xs w-28" placeholder="Bank name *">
                    <input type="number" step="0.01" min="0.01" :max="phaseFee.outstanding" required
                           :value="phasePaymentAmount(phaseFee.phase_id)"
                           @input="e => $emit('update-phase-amount', phaseFee.phase_id, e.target.value)"
                           class="field text-xs w-24" :placeholder="`Amount * (₹${formatMoney(phaseFee.outstanding)} due)`">
                    <button type="submit" class="btn-secondary text-xs !min-h-0 !px-2 !py-1">
                        Upload proof
                    </button>
                </form>
                <a v-if="phaseFee.status === 'approved'"
                   :href="`${programBase}/events/${eventId}/receipt?phase_id=${phaseFee.phase_id}`"
                   target="_blank" rel="noopener"
                   class="px-2 py-1 bg-green-50 border border-green-300 text-green-700 text-xs font-semibold rounded">
                    View Receipt ↗
                </a>
            </div>

            <PaymentHistoryList :history="phaseFee.receipt_history ?? []" />
        </div>
        <div v-if="schoolFee && Number(schoolFee.total_due) > 0"
             class="flex flex-wrap gap-2 items-center text-xs">
            <span class="text-slate-600 font-semibold">
                Combined total: ₹{{ formatMoney(schoolFee.total_due) }}
            </span>
            <a :href="`${programBase}/events/${eventId}/invoice?preview=1`"
               target="_blank" rel="noopener"
               class="px-2 py-1 bg-white border border-indigo-300 text-indigo-700 font-semibold rounded">
                Preview combined invoice ↗
            </a>
            <a :href="`${programBase}/events/${eventId}/invoice`"
               target="_blank" rel="noopener"
               class="px-2 py-1 bg-indigo-50 border border-indigo-300 text-indigo-700 font-semibold rounded">
                Download combined invoice ↓
            </a>
        </div>
    </div>
</template>

<script setup>
import PaymentHistoryList from '@/Components/school/PaymentHistoryList.vue';

const props = defineProps({
    eventId: [String, Number],
    phaseFees: { type: Array, default: () => [] },
    schoolFee: Object,
    programBase: String,
    phasePaymentRefMap: { type: Object, default: () => ({}) },
    phasePaymentBankMap: { type: Object, default: () => ({}) },
    phasePaymentAmountMap: { type: Object, default: () => ({}) },
});

defineEmits([
    'upload-phase-payment',
    'set-phase-file',
    'update-phase-ref',
    'update-phase-bank',
    'update-phase-amount',
]);

function formatMoney(val) {
    const n = Number(val ?? 0);
    return Number.isFinite(n) ? n.toLocaleString('en-IN') : '0';
}

function phaseFeeStatusClass(status) {
    const map = {
        approved: 'bg-emerald-100 text-emerald-800 border-emerald-200',
        proof_uploaded: 'bg-amber-100 text-amber-900 border-amber-200',
        rejected: 'bg-red-100 text-red-800 border-red-200',
    };
    return map[status] ?? 'bg-slate-100 text-slate-700 border-slate-200';
}

function phaseFeeStatusLabel(status) {
    const map = {
        approved: 'Paid',
        proof_uploaded: 'Approval Pending',
        rejected: 'Rejected',
    };
    return map[status] ?? 'Unpaid';
}

function canUploadPhaseFee(pf) {
    // 'partial' must stay in this list — otherwise a school that already had one
    // partial payment approved has no way to submit the remaining balance (matches
    // EventBillingPanel.vue's plain-invoice whitelist).
    return Number(pf.outstanding ?? 0) > 0 && ['pending', 'partial', 'rejected'].includes(pf.status);
}

function phasePaymentRef(phaseId) {
    return props.phasePaymentRefMap?.[phaseId] ?? '';
}

function phasePaymentBank(phaseId) {
    return props.phasePaymentBankMap?.[phaseId] ?? '';
}

function phasePaymentAmount(phaseId) {
    return props.phasePaymentAmountMap?.[phaseId] ?? '';
}
</script>
