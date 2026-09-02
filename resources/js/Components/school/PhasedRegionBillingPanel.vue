<template>
    <section v-if="event?.uses_registration_batch_billing" class="space-y-6">
        <!-- Section Header -->
        <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-200">
            <div>
                <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <span>💳 Competition Level Billing & Statement</span>
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">
                    Fees for this competition level (School Base Fee + Student Item Participation Fees).
                    Annual Sahodaya membership is paid under <a :href="`/school-admin/${schoolId}/registration`" class="link-brand font-semibold">Annual Registration</a>.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <a :href="`${eventBase}/invoice?registration_batch_id=${currentFee?.registration_batch_id || ''}`"
                   target="_blank"
                   class="btn-secondary text-xs inline-flex items-center gap-1.5 font-semibold">
                    <span>📄 Download Proforma Invoice</span>
                </a>
            </div>
        </div>

        <!-- Combined (Whole-Event) Total -->
        <div v-if="combinedFee && Number(combinedFee.total_due) > 0" class="card border-indigo-200 bg-indigo-50/60 shadow-sm p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h4 class="text-sm font-bold text-indigo-950 flex items-center gap-2">
                        <span>🧾 Combined Total — All Levels</span>
                    </h4>
                    <p class="text-xs text-indigo-900/80 mt-0.5">Sum of every payment level above, for your records.</p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide border shadow-2xs"
                      :class="statusBadgeStyle(combinedFee.status)">
                    {{ statusBadgeText(combinedFee.status) }}
                </span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-4">
                <div class="p-3 rounded-xl bg-white border border-indigo-100">
                    <dt class="text-xs font-medium text-slate-500">Combined Total Due</dt>
                    <dd class="text-base font-black text-indigo-950 mt-0.5">₹{{ money(combinedFee.total_due) }}</dd>
                </div>
                <div class="p-3 rounded-xl bg-white border border-indigo-100">
                    <dt class="text-xs font-medium text-slate-500">Total Paid</dt>
                    <dd class="text-base font-extrabold text-emerald-700 mt-0.5">₹{{ money(combinedFee.amount_paid) }}</dd>
                </div>
                <div class="p-3 rounded-xl bg-white border border-indigo-100">
                    <dt class="text-xs font-medium text-slate-500">Outstanding</dt>
                    <dd class="text-base font-extrabold mt-0.5" :class="Number(combinedFee.outstanding) > 0 ? 'text-amber-700' : 'text-emerald-700'">
                        ₹{{ money(combinedFee.outstanding) }}
                    </dd>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <a :href="`${eventBase}/invoice?preview=1`" target="_blank" rel="noopener"
                   class="btn-secondary text-xs font-semibold inline-flex items-center gap-1">
                    <span>📄 Preview Combined Invoice</span>
                </a>
                <a :href="`${eventBase}/invoice`" target="_blank" rel="noopener"
                   class="btn-secondary text-xs font-semibold inline-flex items-center gap-1">
                    <span>⬇️ Download Combined Invoice</span>
                </a>
            </div>
        </div>

        <!-- Level Fee Summary Cards -->
        <div v-for="fee in relevantFees" :key="fee.registration_batch_id" class="card border-slate-200 bg-white shadow-sm p-5">
            <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 pb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider px-2 py-0.5 rounded-md bg-indigo-100 text-indigo-800">
                            {{ fee.batch_code }}
                        </span>
                        <h4 class="text-lg font-extrabold text-slate-900">{{ fee.batch_name }}</h4>
                    </div>
                    <p v-if="fee.registration_close" class="text-xs text-slate-500 mt-1">
                        Registration closes: <strong class="text-slate-700">{{ formatDate(fee.registration_close) }}</strong>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide border shadow-2xs"
                          :class="statusBadgeStyle(fee.status)">
                        {{ statusBadgeText(fee.status) }}
                    </span>
                </div>
            </div>

            <!-- Key Financial Breakdown Metrics -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4">
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                    <dt class="text-xs font-medium text-slate-500">School Base Fee</dt>
                    <dd class="text-base font-extrabold text-slate-900 mt-0.5">₹{{ money(fee.school_registration_fee) }}</dd>
                </div>
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-100">
                    <dt class="text-xs font-medium text-slate-500">Participation Items</dt>
                    <dd class="text-base font-extrabold text-slate-900 mt-0.5">₹{{ money(fee.participation_fee) }}</dd>
                    <p class="text-[10px] text-slate-500 mt-0.5">
                        <span v-if="studentCountForFee(fee) != null" class="font-semibold text-slate-700">
                            {{ studentCountForFee(fee) }} student{{ studentCountForFee(fee) === 1 ? '' : 's' }} ({{ itemCountForFee(fee) }} item{{ itemCountForFee(fee) === 1 ? '' : 's' }})
                        </span>
                        <span v-else>
                            {{ fee.participation_item_count || 0 }} registered item{{ fee.participation_item_count === 1 ? '' : 's' }}
                        </span>
                    </p>
                </div>
                <div class="p-3 rounded-xl bg-indigo-50/60 border border-indigo-100">
                    <dt class="text-xs font-semibold text-indigo-900">Total Due</dt>
                    <dd class="text-base font-black text-indigo-950 mt-0.5">₹{{ money(fee.total_due) }}</dd>
                </div>
                <div class="p-3 rounded-xl border"
                     :class="Number(fee.outstanding) > 0 ? 'bg-amber-50/80 border-amber-200' : 'bg-emerald-50/80 border-emerald-200'">
                    <dt class="text-xs font-semibold" :class="Number(fee.outstanding) > 0 ? 'text-amber-900' : 'text-emerald-900'">
                        Outstanding Balance
                    </dt>
                    <dd class="text-base font-black mt-0.5" :class="Number(fee.outstanding) > 0 ? 'text-amber-700' : 'text-emerald-700'">
                        ₹{{ money(fee.outstanding) }}
                    </dd>
                </div>
            </div>

            <!-- Invoice Line Item Details Table -->
            <div v-if="fee.lines?.length" class="mt-5 border-t border-slate-100 pt-4">
                <h5 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-2">Invoice Line Items</h5>
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                            <tr>
                                <th class="px-3 py-2">Line Item Description</th>
                                <th class="px-3 py-2">Category</th>
                                <th class="px-3 py-2 text-center">Qty / Count</th>
                                <th class="px-3 py-2 text-right">Unit Rate (₹)</th>
                                <th class="px-3 py-2 text-right">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="(line, idx) in fee.lines" :key="idx" class="hover:bg-slate-50/50">
                                <td class="px-3 py-2 font-medium text-slate-800">{{ line.label }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ line.category || '—' }}</td>
                                <td class="px-3 py-2 text-center text-slate-700 font-semibold">
                                    {{ line.quantity != null ? line.quantity : 1 }}
                                    <span v-if="line.line_type === 'student_registration'" class="text-[10px] text-slate-500 font-normal ml-0.5">student{{ line.quantity === 1 ? '' : 's' }}</span>
                                </td>
                                <td class="px-3 py-2 text-right font-mono text-slate-600">
                                    {{ line.unit_amount != null ? `₹${money(line.unit_amount)}` : '—' }}
                                </td>
                                <td class="px-3 py-2 text-right font-mono font-bold text-slate-900">₹{{ money(line.amount) }}</td>
                            </tr>
                            <tr class="bg-slate-50/80 font-bold border-t border-slate-200">
                                <td colspan="4" class="px-3 py-2 text-slate-900">Total Invoice Amount</td>
                                <td class="px-3 py-2 text-right font-mono text-indigo-900">₹{{ money(fee.total_due) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Action Buttons Bar -->
            <div class="mt-5 flex flex-wrap items-center gap-2 pt-3 border-t border-slate-100">
                <a :href="`${eventBase}/invoice?registration_batch_id=${fee.registration_batch_id}`"
                   target="_blank"
                   class="btn-secondary text-xs font-semibold inline-flex items-center gap-1">
                    <span>📄 View / Print Invoice</span>
                </a>
                <a v-if="fee.status === 'approved'"
                   :href="`${eventBase}/receipt?registration_batch_id=${fee.registration_batch_id}`"
                   target="_blank"
                   class="btn-secondary text-xs font-semibold inline-flex items-center gap-1 text-emerald-700 border-emerald-300 bg-emerald-50 hover:bg-emerald-100">
                    <span>🧾 Official Receipt</span>
                </a>
                <button v-if="Number(fee.outstanding) > 0 && paymentBatch?.registration_batch_id !== fee.registration_batch_id"
                        type="button"
                        class="btn-primary text-xs font-semibold inline-flex items-center gap-1 shadow-xs"
                        @click="openPayment(fee)">
                    <span>💳 Upload Payment Proof</span>
                </button>
            </div>

            <!-- Payment History — every proof already uploaded for this level, and its review status -->
            <div v-if="fee.receipt_history?.length" class="mt-5 border-t border-slate-100 pt-4">
                <h5 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-2">Payment History</h5>
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                            <tr>
                                <th class="px-3 py-2">Uploaded</th>
                                <th class="px-3 py-2">Transaction Ref</th>
                                <th class="px-3 py-2">Bank</th>
                                <th class="px-3 py-2 text-right">Amount (₹)</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Reviewed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr v-for="receipt in fee.receipt_history" :key="receipt.id" class="hover:bg-slate-50/50 align-top">
                                <td class="px-3 py-2 text-slate-700">{{ formatDate(receipt.uploaded_at) }}</td>
                                <td class="px-3 py-2 font-mono text-slate-700">{{ receipt.transaction_ref || '—' }}</td>
                                <td class="px-3 py-2 text-slate-600">{{ receipt.bank_name || '—' }}</td>
                                <td class="px-3 py-2 text-right font-mono font-semibold text-slate-900">₹{{ money(receipt.amount) }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide border"
                                          :class="receiptStatusBadge(receipt.status).class">
                                        {{ receiptStatusBadge(receipt.status).text }}
                                    </span>
                                    <p v-if="receipt.status === 'rejected' && receipt.rejection_reason" class="text-[10px] text-red-700 mt-1">
                                        {{ receipt.rejection_reason }}
                                    </p>
                                </td>
                                <td class="px-3 py-2 text-slate-500">
                                    <span v-if="receipt.reviewed_at">{{ formatDate(receipt.reviewed_at) }}<span v-if="receipt.reviewed_by"> · {{ receipt.reviewed_by }}</span></span>
                                    <span v-else>—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Itemized Registered Items Details -->
        <div v-if="registeredItemBreakdown.length" class="card border-slate-200 bg-white shadow-sm p-5">
            <h4 class="text-sm font-bold text-slate-900 mb-3 flex items-center justify-between">
                <span>📋 Itemized Registered Items Breakdown</span>
                <span class="text-xs font-normal text-slate-500">{{ registeredItemBreakdown.length }} item{{ registeredItemBreakdown.length === 1 ? '' : 's' }} registered</span>
            </h4>
            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="w-full text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                        <tr>
                            <th class="px-3 py-2.5 text-left">Code</th>
                            <th class="px-3 py-2.5 text-left">Event Item Title</th>
                            <th class="px-3 py-2.5 text-left">Category / Eligibility</th>
                            <th class="px-3 py-2.5 text-center">Registered Students</th>
                            <th class="px-3 py-2.5 text-right">Fee Rate</th>
                            <th class="px-3 py-2.5 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <tr v-for="row in registeredItemBreakdown" :key="row.id" class="hover:bg-slate-50/50">
                            <td class="px-3 py-2 font-mono font-bold text-indigo-700">{{ row.code }}</td>
                            <td class="px-3 py-2 font-bold text-slate-900">{{ row.title }}</td>
                            <td class="px-3 py-2 text-slate-600 capitalize">{{ row.group.replace('_', ' ') }}</td>
                            <td class="px-3 py-2 text-center font-bold text-slate-800">{{ row.participants }} student{{ row.participants === 1 ? '' : 's' }}</td>
                            <td class="px-3 py-2 text-right text-slate-600">₹{{ money(row.rate) }}</td>
                            <td class="px-3 py-2 text-right font-mono font-bold text-slate-900">₹{{ money(row.subtotal) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-slate-50 font-bold text-slate-900 border-t border-slate-200">
                        <tr>
                            <td colspan="3" class="px-3 py-2 text-right">Total Item Participation Charges:</td>
                            <td class="px-3 py-2 text-center">{{ totalRegisteredParticipants }} students</td>
                            <td></td>
                            <td class="px-3 py-2 text-right font-mono text-indigo-900">₹{{ money(totalItemFeesSubtotal) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Official Bank Account Details Box -->
        <div class="card border-blue-200 bg-blue-50/50 p-5 rounded-2xl">
            <div class="flex flex-col md:flex-row items-start gap-4">
                <div class="flex items-start gap-3 flex-1 w-full">
                    <span class="text-2xl shrink-0">🏦</span>
                    <div class="flex-1 text-xs text-slate-700">
                        <h4 class="font-bold text-slate-900 text-sm mb-1">Official Sahodaya Bank Account & Payment Instructions</h4>
                        <div v-if="activePaymentDetails" class="mt-2 bg-white/95 p-4 rounded-xl border border-blue-200/90 shadow-2xs font-sans text-sm font-bold leading-relaxed text-slate-900 tracking-wide">
                            <pre class="whitespace-pre-wrap font-sans text-sm font-bold leading-relaxed text-slate-900 tracking-wide">{{ activePaymentDetails }}</pre>
                        </div>
                        <div v-else class="mt-2 bg-amber-50 p-3 rounded-xl border border-amber-200 text-amber-900 text-xs font-medium">
                            Payment details haven't been configured by your Sahodaya yet. Contact your Sahodaya admin for bank/UPI details before making any transfer &mdash; do not use details from anywhere else.
                        </div>
                        <p v-if="activePaymentDetails" class="text-xs text-blue-900 mt-2 font-medium">
                            💡 <strong>Note:</strong> Include your school prefix in the transaction remarks when transferring funds. Upload the payment reference number and screenshot proof above after completing the transfer.
                        </p>
                    </div>
                </div>

                <!-- Payment QR Code Image Box -->
                <div v-if="activePaymentQrCodeUrl" class="shrink-0 flex flex-col items-center p-4 bg-white rounded-2xl border border-blue-200 shadow-sm text-center md:max-w-md w-full md:w-auto self-stretch md:self-auto justify-center">
                    <span class="text-xs font-extrabold text-slate-900 mb-2 flex items-center gap-1">📱 Scan & Pay via UPI</span>
                    <img :src="activePaymentQrCodeUrl" alt="Payment QR Code" class="w-72 sm:w-80 md:w-96 h-80 sm:h-96 md:h-[26rem] object-contain rounded-xl border border-slate-100 p-2 bg-white">
                    <span class="text-xs text-slate-600 mt-2 font-semibold">Accepts GPay, PhonePe, Paytm, etc.</span>
                </div>
            </div>
        </div>

        <!-- Upload Payment Proof Modal -->
        <Modal :show="!!paymentBatch" :title="`💳 Upload Payment Proof for ${paymentBatch?.batch_name ?? ''}`" size="lg" @close="paymentBatch = null">
            <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submitPayment">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Transaction Reference Number *</label>
                    <input v-model="paymentForm.transaction_ref" class="field text-xs w-full" placeholder="e.g. UTR123456789 / IMPS987654" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Bank Paid From *</label>
                    <input v-model="paymentForm.bank_name" class="field text-xs w-full" placeholder="e.g. State Bank of India / HDFC Bank" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Amount Paid (₹) *</label>
                    <input v-model.number="paymentForm.amount" type="number" min="0.01" step="0.01" class="field text-xs w-full font-mono font-bold" placeholder="Amount" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Payment Proof File(s) (PDF / Image) *</label>
                    <input type="file" accept=".pdf,.jpg,.jpeg,.png" class="field text-xs w-full" multiple required @change="paymentFiles = Array.from($event.target.files || [])">
                </div>
                <div class="sm:col-span-2 flex items-center justify-end gap-2 pt-2">
                    <button type="button" class="btn-ghost text-xs" @click="paymentBatch = null">Cancel</button>
                    <button type="submit" class="btn-primary text-xs font-bold px-4 py-2" :disabled="paymentForm.processing">
                        {{ paymentForm.processing ? 'Uploading...' : 'Submit Payment Proof' }}
                    </button>
                </div>
            </form>
        </Modal>
    </section>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Modal from '@/Components/ui/Modal.vue';

const props = defineProps({
    event: { type: Object, required: true },
    schoolId: { type: String, required: true },
    programPrefix: { type: String, required: true },
    paymentDetails: { type: String, default: '' },
});

const eventBase = `/school-admin/${props.schoolId}/${props.programPrefix}/events/${props.event.id}`;

// Every payment level shows together (Level 1 and Level 2 side by side), not narrowed down
// to just the level the current operational leaf page happens to belong to — a school needs
// to see its whole-event picture to judge whether an earlier level still needs paying.
const relevantFees = computed(() => props.event?.school_registration_batch_fees || []);

const currentFee = computed(() => {
    const eventBatchId = props.event?.registration_batch_id;
    if (eventBatchId) {
        const matched = relevantFees.value.find(fee => String(fee.registration_batch_id) === String(eventBatchId));
        if (matched) return matched;
    }
    return relevantFees.value[0] || null;
});

// The combined (whole-event) rollup — same row FestSchoolEventFee already keeps in sync
// across every level, shown alongside the per-level cards above so a school always sees
// both views together.
const combinedFee = computed(() => props.event?.school_fee || null);

const registeredItemBreakdown = computed(() => {
    const items = props.event?.items || [];
    const regs = props.event?.event_registrations || [];

    const regMap = new Map();
    regs.forEach(r => {
        const count = (r.participants || []).length || (r.student_ids || []).length || 1;
        regMap.set(r.item_id, count);
    });

    return items
        .filter(item => regMap.has(item.id))
        .map(item => {
            const count = regMap.get(item.id);
            const rate = Number(item.item_fee || 50);
            return {
                id: item.id,
                code: item.item_code || '---',
                title: item.title,
                group: item.class_group || 'category_1',
                participants: count,
                rate: rate,
                subtotal: count * rate,
            };
        });
});

const totalRegisteredParticipants = computed(() => {
    return registeredItemBreakdown.value.reduce((sum, row) => sum + row.participants, 0);
});

const totalItemFeesSubtotal = computed(() => {
    return registeredItemBreakdown.value.reduce((sum, row) => sum + row.subtotal, 0);
});

const paymentBatch = ref(null);
const paymentFiles = ref([]);
const paymentForm = useForm({ transaction_ref: '', bank_name: '', amount: null });

const activePaymentDetails = computed(() => {
    return paymentBatch.value?.payment_details_text
        || props.event?.payment_details_text
        || props.paymentDetails;
});

const activePaymentQrCodeUrl = computed(() => {
    return paymentBatch.value?.payment_qr_code_url
        || props.event?.payment_qr_code_url;
});

function openPayment(fee) {
    paymentBatch.value = fee;
    paymentForm.amount = Number(fee.outstanding || 0);
}

function submitPayment() {
    const payload = new FormData();
    payload.append('registration_batch_id', paymentBatch.value.registration_batch_id);
    payload.append('transaction_ref', paymentForm.transaction_ref);
    payload.append('bank_name', paymentForm.bank_name);
    payload.append('amount', paymentForm.amount);
    paymentFiles.value.forEach((file) => payload.append('payment_proof[]', file));
    router.post(`${eventBase}/payment`, payload, {
        preserveScroll: true,
        onSuccess: () => {
            paymentBatch.value = null;
            paymentForm.reset();
            paymentFiles.value = [];
        },
    });
}

function studentCountForFee(fee) {
    if (!fee) return null;
    // 'student_registration' — the non-composite per-phase/per-batch student rate line.
    // 'student_reg' — the composite (kalolsavam_composite/sports_composite) quota-engine's
    // own line, see FestRegistrationBatchFeeService::compositeAttributionForBatch().
    const studentLine = (fee.lines || []).find((l) => l.line_type === 'student_registration' || l.line_type === 'student_reg');
    if (studentLine && studentLine.quantity != null) {
        return studentLine.quantity;
    }
    return null;
}

function itemCountForFee(fee) {
    if (!fee) return 0;
    const itemLines = (fee.lines || []).filter((l) => l.line_type === 'item_fee' || l.line_type === 'extra_item');
    if (itemLines.length) {
        return itemLines.length;
    }
    return fee.participation_item_count || 0;
}

function money(value) { return Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function formatDate(value) { return new Date(value).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' }); }

function statusBadgeText(status) {
    if (status === 'approved') return 'Paid & Approved';
    if (status === 'proof_uploaded' || status === 'partial') return 'Proof Uploaded — Pending Review';
    if (status === 'rejected') return 'Returned / Rejected';
    return 'Payment Pending';
}

function statusBadgeStyle(status) {
    if (status === 'approved') return 'bg-emerald-100 text-emerald-800 border-emerald-200';
    if (status === 'proof_uploaded' || status === 'partial') return 'bg-blue-100 text-blue-800 border-blue-200';
    if (status === 'rejected') return 'bg-red-100 text-red-800 border-red-200';
    return 'bg-amber-100 text-amber-800 border-amber-200';
}

// FeeReceipt's own status vocabulary (uploaded/approved/rejected/superseded/reversed) —
// distinct from the fee-level status above (pending/proof_uploaded/partial/approved/rejected).
function receiptStatusBadge(status) {
    if (status === 'approved') return { text: 'Approved', class: 'bg-emerald-100 text-emerald-800 border-emerald-200' };
    if (status === 'rejected') return { text: 'Rejected', class: 'bg-red-100 text-red-800 border-red-200' };
    if (status === 'superseded') return { text: 'Superseded', class: 'bg-slate-100 text-slate-600 border-slate-200' };
    if (status === 'reversed') return { text: 'Reversed', class: 'bg-slate-100 text-slate-600 border-slate-200' };
    return { text: 'Pending Review', class: 'bg-blue-100 text-blue-800 border-blue-200' };
}
</script>
