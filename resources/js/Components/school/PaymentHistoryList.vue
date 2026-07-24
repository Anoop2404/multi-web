<template>
    <div v-if="history.length" class="mt-2 pt-2 border-t border-indigo-100">
        <button type="button" class="text-xs font-semibold text-indigo-800 flex items-center gap-1"
                @click="open = !open">
            <span>{{ open ? '▾' : '▸' }}</span>
            Payment history ({{ history.length }})
        </button>
        <ul v-if="open" class="mt-1.5 text-xs space-y-1.5">
            <li v-for="row in history" :key="row.id"
                class="rounded-lg border border-indigo-100 bg-white/60 px-2 py-1.5">
                <div class="flex flex-wrap justify-between gap-2">
                    <span class="font-semibold" :class="statusClass(row.status)">
                        {{ statusLabel(row.status) }}
                    </span>
                    <span class="font-semibold text-indigo-900">₹{{ formatMoney(row.amount) }}</span>
                </div>
                <div class="text-slate-500 mt-0.5">
                    Uploaded {{ formatDateTime(row.uploaded_at) }}
                    <span v-if="row.transaction_ref"> · Ref {{ row.transaction_ref }}</span>
                </div>
                <div v-if="row.reviewed_at" class="text-slate-500">
                    Reviewed {{ formatDateTime(row.reviewed_at) }}
                    <span v-if="row.reviewed_by"> by {{ row.reviewed_by }}</span>
                </div>
                <div v-if="row.status === 'rejected' && row.rejection_reason" class="text-red-600 mt-0.5">
                    Reason: {{ row.rejection_reason }}
                </div>
                <div v-if="row.status === 'reversed' && row.reversal_reason" class="text-red-600 mt-0.5">
                    Reversal reason: {{ row.reversal_reason }}
                </div>
                <div v-if="row.status === 'approved' && row.receipt_number" class="text-emerald-700 mt-0.5">
                    Receipt #{{ row.receipt_number }}
                    <a v-if="row.receipt_url" :href="row.receipt_url" target="_blank" class="underline ml-1">view</a>
                </div>
                <!-- A credit row is not a receipt — it's money owed BACK to the school after a
                     rejected/cancelled paid item. Kept in the same timeline (not a separate
                     list) so the full "what happened to this money" story reads in one place.
                     See docs/FLOW_GAP_FIX_PLAN.md Phase 3b.2. -->
                <template v-if="row.status === 'credit'">
                    <div class="text-amber-700 mt-0.5">{{ row.credit_reason || 'Fee credit issued' }}</div>
                    <div v-if="row.applied_at" class="text-slate-500 mt-0.5">Applied {{ formatDateTime(row.applied_at) }}</div>
                    <div v-else class="text-slate-500 mt-0.5">Outstanding — can be applied to a future fee</div>
                    <div v-if="row.credit_note_number" class="text-amber-700 mt-0.5">
                        Credit note #{{ row.credit_note_number }}
                        <a v-if="row.credit_note_url" :href="row.credit_note_url" target="_blank" class="underline ml-1">view</a>
                    </div>
                </template>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({
    history: { type: Array, default: () => [] },
});

const open = ref(false);

function formatMoney(val) {
    const n = Number(val ?? 0);
    return Number.isFinite(n) ? n.toLocaleString('en-IN') : '0';
}

function formatDateTime(iso) {
    if (!iso) return '—';
    const d = new Date(iso.replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
        + ', ' + d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
}

function statusLabel(status) {
    const labels = {
        uploaded: 'Proof uploaded',
        approved: 'Approved',
        rejected: 'Rejected',
        superseded: 'Superseded (re-uploaded)',
        reversed: 'Reversed',
        credit: 'Fee credit',
    };
    return labels[status] ?? status;
}

function statusClass(status) {
    const classes = {
        approved: 'text-emerald-700',
        rejected: 'text-red-600',
        reversed: 'text-red-600',
        superseded: 'text-slate-500',
        uploaded: 'text-amber-700',
        credit: 'text-amber-700',
    };
    return classes[status] ?? 'text-slate-700';
}
</script>
