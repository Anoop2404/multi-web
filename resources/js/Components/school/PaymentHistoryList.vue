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
                <div v-if="row.status === 'approved' && row.receipt_number" class="text-emerald-700 mt-0.5">
                    Receipt #{{ row.receipt_number }}
                </div>
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
    };
    return classes[status] ?? 'text-slate-700';
}
</script>
