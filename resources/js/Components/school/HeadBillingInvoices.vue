<template>
    <div class="space-y-3">
        <div v-for="headFee in headFees" :key="headFee.head_id"
             class="bg-indigo-50 border border-indigo-100 rounded-xl p-3 text-sm space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-semibold text-indigo-950">{{ headFee.head_name }}</p>
                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full border"
                      :class="headFeeStatusClass(headFee.status)">
                    {{ headFeeStatusLabel(headFee.status) }}
                </span>
            </div>
            <p v-if="headFee.status === 'rejected' && headFee.rejection_reason"
               class="text-xs text-red-600">
                Reason: {{ headFee.rejection_reason }}
            </p>
            <ul v-if="(headFee.breakdown?.items ?? []).length" class="text-xs text-indigo-900 space-y-1">
                <li v-for="(line, i) in headFee.breakdown.items" :key="i" class="flex justify-between gap-4">
                    <span>{{ line.label }}</span>
                    <span class="font-semibold shrink-0">₹{{ formatMoney(line.amount) }}</span>
                </li>
            </ul>
            <div class="flex flex-wrap justify-between gap-2 text-xs pt-2 border-t border-indigo-100">
                <span class="text-indigo-800">
                    Due ₹{{ formatMoney(headFee.total_due) }}
                    <span v-if="headFee.amount_paid > 0"> · Paid ₹{{ formatMoney(headFee.amount_paid) }}</span>
                </span>
                <span class="font-semibold text-indigo-950">
                    Outstanding ₹{{ formatMoney(headFee.outstanding) }}
                </span>
            </div>
            <div class="flex flex-wrap gap-2 items-center">
                <form v-if="canUploadHeadFee(headFee)"
                      @submit.prevent="$emit('upload-head-payment', headFee)"
                      class="flex flex-wrap gap-2 items-center">
                    <input type="file" accept=".pdf,.jpg,.jpeg,.png"
                           @change="e => $emit('set-head-file', headFee.head_id, e.target.files[0])"
                           class="text-xs" />
                    <input :value="headPaymentRef(headFee.head_id)"
                           @input="e => $emit('update-head-ref', headFee.head_id, e.target.value)"
                           class="field text-xs w-36" placeholder="Txn ref (opt)">
                    <button type="submit" class="btn-secondary text-xs !min-h-0 !px-2 !py-1">
                        Upload proof
                    </button>
                </form>
                <a v-if="headFee.status === 'approved'"
                   :href="`${programBase}/events/${eventId}/receipt?head_id=${headFee.head_id}`"
                   target="_blank" rel="noopener"
                   class="px-2 py-1 bg-green-50 border border-green-300 text-green-700 text-xs font-semibold rounded">
                    View Receipt ↗
                </a>
            </div>

            <PaymentHistoryList :history="headFee.receipt_history ?? []" />
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

defineProps({
    eventId: [String, Number],
    headFees: { type: Array, default: () => [] },
    schoolFee: Object,
    programBase: String,
    headPaymentRefMap: { type: Object, default: () => ({}) },
});

defineEmits(['upload-head-payment', 'set-head-file', 'update-head-ref']);

function formatMoney(val) {
    const n = Number(val ?? 0);
    return Number.isFinite(n) ? n.toLocaleString('en-IN') : '0';
}

function headFeeStatusClass(status) {
    const map = {
        approved: 'bg-emerald-100 text-emerald-800 border-emerald-200',
        proof_uploaded: 'bg-amber-100 text-amber-900 border-amber-200',
        rejected: 'bg-red-100 text-red-800 border-red-200',
    };
    return map[status] ?? 'bg-slate-100 text-slate-700 border-slate-200';
}

function headFeeStatusLabel(status) {
    const map = {
        approved: 'Paid',
        proof_uploaded: 'Approval Pending',
        rejected: 'Rejected',
    };
    return map[status] ?? 'Unpaid';
}

function canUploadHeadFee(hf) {
    return Number(hf.outstanding ?? 0) > 0 && ['pending', 'rejected'].includes(hf.status);
}

function headPaymentRef(headId) {
    return props.headPaymentRefMap?.[headId] ?? '';
}
</script>
