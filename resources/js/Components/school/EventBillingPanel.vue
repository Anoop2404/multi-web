<template>
    <div v-if="event.fee_required && (event.uses_per_head_billing ? event.school_head_fees?.length : event.school_fee)"
         class="mt-4 border-t border-gray-100 pt-4 space-y-3">
        <div>
            <p class="text-xs font-semibold text-slate-800">Event fees &amp; billing</p>
            <p class="text-xs text-slate-500 mt-0.5">
                <template v-if="event.uses_per_head_billing">
                    Each section is billed separately — paying one does not clear another.
                </template>
                <template v-else-if="event.event_type === 'sports'">
                    Fees for this sport event (school + student + item fees).
                </template>
                <template v-else>
                    Includes per-student event registration (when athletes are registered above) plus item fees.
                </template>
                Annual Sahodaya membership is paid under
                <a :href="`/school-admin/${schoolId}/registration`" class="link-brand font-semibold">Annual Registration</a>.
                <a :href="`${programBase}/reports/${event.id}/fee-summary`" class="link-brand font-semibold ml-1">Fee report →</a>
            </p>
            <p v-if="event.registration_close" class="text-xs font-semibold text-amber-700 mt-1">
                Due by: {{ formatDate(event.registration_close) }} (last registration date)
            </p>
        </div>

        <div v-if="paymentDetails" class="rounded-xl border border-slate-200 bg-slate-50 p-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">How to pay</p>
            <pre class="text-xs text-slate-700 whitespace-pre-wrap font-sans leading-relaxed">{{ paymentDetails }}</pre>
        </div>

        <!-- Per-head invoices (sports_composite) -->
        <HeadBillingInvoices
            v-if="event.uses_per_head_billing"
            :event-id="event.id"
            :head-fees="event.school_head_fees ?? []"
            :school-fee="event.school_fee"
            :program-base="programBase"
            :head-payment-ref-map="headPaymentRefMap"
            @upload-head-payment="$emit('upload-head-payment', $event)"
            @set-head-file="(headId, file) => $emit('set-head-file', headId, file)"
            @update-head-ref="(headId, refVal) => $emit('update-head-ref', headId, refVal)"
        />

        <!-- Single-invoice path (non sports_composite) -->
        <div v-else class="bg-indigo-50 border border-indigo-100 rounded-xl p-3 text-sm">
            <ul v-if="itemFeeLines.length" class="text-xs text-indigo-900 space-y-1">
                <li v-for="(line, i) in itemFeeLines" :key="i" class="flex justify-between gap-4">
                    <span>{{ line.label }}</span>
                    <span class="font-semibold shrink-0">₹{{ formatMoney(line.amount) }}</span>
                </li>
            </ul>
            <p v-else class="text-xs text-indigo-800">Register items above to see item fees here.</p>
            <p class="font-semibold text-indigo-900 mt-2 pt-2 border-t border-indigo-100">
                Item fees due: ₹{{ formatMoney(itemFeesDue) }}
                <span v-if="event.school_fee?.participation_item_count" class="font-normal text-indigo-700">
                    ({{ event.school_fee.participation_item_count }} item{{ event.school_fee.participation_item_count === 1 ? '' : 's' }})
                </span>
            </p>
            <div v-if="isMinFeeApplied" class="mt-2 rounded-lg border border-amber-200 bg-amber-50 p-2 text-xs text-amber-950">
                <strong>Minimum event fee: ₹{{ formatMoney(event.fee_settings?.school_fee_min ?? 1500) }}</strong> — applied because your registered items total less than the minimum event fee.
            </div>
            <div class="mt-2 flex flex-wrap gap-2 items-center">
                <span v-if="event.school_fee?.status === 'approved'" class="text-xs text-green-700 font-semibold">Payment approved</span>
                <span v-else-if="event.school_fee?.status === 'proof_uploaded'" class="text-xs text-amber-700 font-semibold">Payment pending approval</span>
                <span v-else-if="event.school_fee?.status === 'rejected'" class="text-xs text-red-600 font-semibold">
                    Payment rejected — re-upload
                    <span v-if="event.school_fee.rejection_reason" class="font-normal block">Reason: {{ event.school_fee.rejection_reason }}</span>
                </span>
                <form v-if="itemFeesDue > 0 && ['pending', 'rejected'].includes(event.school_fee?.status)"
                      @submit.prevent="$emit('upload-event-payment')" class="flex flex-wrap gap-2 items-center">
                    <input type="file" accept=".pdf,.jpg,.jpeg,.png"
                           @change="e => $emit('set-event-file', e.target.files[0])" class="text-xs" />
                    <input :value="eventPaymentRef"
                           @input="e => $emit('update-event-ref', e.target.value)"
                           class="field text-xs w-36" placeholder="Txn ref (opt)">
                    <button type="submit" class="btn-secondary text-xs !min-h-0 !px-2 !py-1">Upload item fee proof</button>
                </form>
                <a v-if="event.school_fee?.status === 'approved'"
                   :href="`${programBase}/events/${event.id}/receipt`"
                   target="_blank" rel="noopener"
                   class="px-2 py-1 bg-green-50 border border-green-300 text-green-700 text-xs font-semibold rounded">
                    View Receipt ↗
                </a>
                <a v-if="itemFeesDue > 0"
                   :href="`${programBase}/events/${event.id}/invoice?preview=1`"
                   target="_blank" rel="noopener"
                   class="px-2 py-1 bg-white border border-indigo-300 text-indigo-700 text-xs font-semibold rounded">
                    Preview Invoice ↗
                </a>
                <a v-if="itemFeesDue > 0"
                   :href="`${programBase}/events/${event.id}/invoice`"
                   target="_blank" rel="noopener"
                   class="px-2 py-1 bg-indigo-50 border border-indigo-300 text-indigo-700 text-xs font-semibold rounded">
                    Download Invoice ↓
                </a>
            </div>
        </div>
    </div>
</template>

<script setup>
import HeadBillingInvoices from '@/Components/school/HeadBillingInvoices.vue';

const props = defineProps({
    event: { type: Object, required: true },
    schoolId: [String, Number],
    programBase: String,
    paymentDetails: String,
    itemFeeLines: { type: Array, default: () => [] },
    itemFeesDue: { type: Number, default: 0 },
    isMinFeeApplied: { type: Boolean, default: false },
    eventPaymentRef: { type: String, default: '' },
    headPaymentRefMap: { type: Object, default: () => ({}) },
});

defineEmits([
    'upload-event-payment',
    'set-event-file',
    'update-event-ref',
    'upload-head-payment',
    'set-head-file',
    'update-head-ref',
]);

function formatMoney(val) {
    const n = Number(val ?? 0);
    return Number.isFinite(n) ? n.toLocaleString('en-IN') : '0';
}

function formatDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso.replace(' ', 'T'));
    return Number.isNaN(d.getTime()) ? '—' : d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>
