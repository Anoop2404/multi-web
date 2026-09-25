<template>
    <div class="space-y-6">
        <!-- Top Bill Overview & Quick Actions -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Bill ID: #{{ bill.id }}</span>
                        <FoodBillStatusBadge v-if="bill.status" :status="bill.status" />
                    </div>
                    <h2 class="text-xl sm:text-2xl font-black text-slate-900">{{ bill.school_name }}</h2>
                </div>

                <!-- Bill Actions -->
                <div class="flex flex-wrap items-center gap-2">
                    <button v-if="bill.status === 'open'" type="button" @click="settle"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-emerald-200 bg-emerald-50 text-xs font-bold text-emerald-800 hover:bg-emerald-100 transition">
                        <span>✓</span> Mark as Settled
                    </button>
                    <button v-if="bill.status === 'settled'" type="button" @click="reopen"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-indigo-200 bg-indigo-50 text-xs font-bold text-indigo-700 hover:bg-indigo-100 transition">
                        <span>↺</span> Reopen Bill
                    </button>
                    <button v-if="canCancel && bill.status === 'open'" type="button" @click="cancelBill"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-rose-200 bg-rose-50 text-xs font-bold text-rose-700 hover:bg-rose-100 transition">
                        <span>✕</span> Cancel Bill
                    </button>
                    <a :href="`${basePath}/pdf`" target="_blank"
                       class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-bold text-slate-700 hover:bg-slate-50 shadow-xs transition">
                        <span>📄</span> Print Bill PDF
                    </a>
                </div>
            </div>

            <!-- Financial Stat Summary Cards -->
            <FoodBillSummary :total="Number(bill.amount_total)" :paid="Number(bill.amount_paid)"
                              :balance="Number(bill.balance_due)" :status="bill.status" />
        </div>

        <!-- Navigation Tabs: Order Items vs Payments & Verification -->
        <div class="border-b border-slate-200">
            <nav class="flex space-x-6" aria-label="Bill Tabs">
                <button type="button" @click="activeTab = 'items'"
                        class="inline-flex items-center gap-2 py-3 px-1 border-b-2 font-bold text-sm transition"
                        :class="activeTab === 'items'
                            ? 'border-indigo-600 text-indigo-600'
                            : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'">
                    <span>📦</span>
                    <span>Ordered Dishes</span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                          :class="activeTab === 'items' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600'">
                        {{ orderItems.length }}
                    </span>
                </button>

                <button type="button" @click="activeTab = 'payments'"
                        class="inline-flex items-center gap-2 py-3 px-1 border-b-2 font-bold text-sm transition"
                        :class="activeTab === 'payments'
                            ? 'border-indigo-600 text-indigo-600'
                            : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'">
                    <span>💳</span>
                    <span>Payment History & Verification</span>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                          :class="pendingCount > 0 ? 'bg-amber-100 text-amber-800 font-bold' : (activeTab === 'payments' ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600')">
                        {{ pendingCount > 0 ? `${pendingCount} review pending` : payments.length }}
                    </span>
                </button>
            </nav>
        </div>

        <!-- ============================================== -->
        <!-- TAB 1: ORDERED DISHES                          -->
        <!-- ============================================== -->
        <div v-if="activeTab === 'items'" class="space-y-6">
            <!-- Add Item Form (When bill is open) -->
            <div v-if="bill.status === 'open'" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                <h4 class="font-bold text-xs uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                    <span>➕</span> Add Item to this School's Order
                </h4>
                <form @submit.prevent="addItem" class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[16rem]">
                        <label class="text-xs font-semibold text-slate-700 block mb-1">Select Menu Item</label>
                        <SearchableSelect v-model="itemForm.menu_item_id" :options="menuItemOptions" :all-option="true" all-label="— Select item —" />
                    </div>
                    <div class="w-24">
                        <label class="text-xs font-semibold text-slate-700 block mb-1">Quantity</label>
                        <input v-model.number="itemForm.quantity" type="number" min="1" :max="remainingForSelected ?? undefined" class="field text-xs w-full">
                    </div>
                    <button type="submit" class="btn-primary text-xs font-bold px-4 py-2"
                            :disabled="itemForm.processing || !itemForm.menu_item_id || remainingForSelected === 0">
                        {{ itemForm.processing ? 'Adding…' : 'Add to Order' }}
                    </button>
                </form>
                <p v-if="remainingForSelected === 0" class="text-xs text-amber-600 font-medium">
                    This school has already reached the per-school limit for that item.
                </p>
                <p v-if="itemForm.errors.menu_item_id" class="text-xs text-rose-600">{{ itemForm.errors.menu_item_id }}</p>
                <p v-if="itemForm.errors.quantity" class="text-xs text-rose-600">{{ itemForm.errors.quantity }}</p>
            </div>

            <!-- Order Items Table -->
            <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm">
                <table class="data-table text-xs">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="py-3">Date</th>
                            <th class="py-3">Meal Slot</th>
                            <th class="py-3">Item</th>
                            <th class="py-3">Qty</th>
                            <th class="py-3">Unit Price</th>
                            <th class="py-3">Line Total</th>
                            <th class="py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="oi in orderItems" :key="oi.id" class="hover:bg-slate-50/70 transition">
                            <td class="font-medium text-slate-700 py-3">{{ formatCalendarDate(oi.menu_date) }}</td>
                            <td class="py-3 capitalize">
                                <span class="inline-flex items-center gap-1 font-semibold text-slate-800">
                                    <span>{{ mealIcon(oi.meal_type) }}</span>
                                    <span>{{ oi.meal_type }}</span>
                                </span>
                            </td>
                            <td class="font-bold text-slate-900 py-3">
                                <div class="flex items-center gap-1.5">
                                    <VegBadge :name="oi.item_name" />
                                    <span>{{ oi.item_name }}</span>
                                </div>
                            </td>
                            <td class="font-semibold text-slate-800 py-3">{{ oi.quantity }}</td>
                            <td class="text-slate-600 py-3">₹{{ Number(oi.unit_price).toFixed(2) }}</td>
                            <td class="font-extrabold text-slate-900 py-3">₹{{ Number(oi.line_total).toFixed(2) }}</td>
                            <td class="text-right py-3">
                                <button v-if="bill.status === 'open'" type="button"
                                        class="text-xs font-semibold text-rose-600 hover:text-rose-800 hover:underline"
                                        @click="removeItem(oi)">
                                    Remove
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!orderItems.length">
                            <td colspan="7" class="p-8 text-center text-slate-400">
                                No items ordered by this school yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- TAB 2: PAYMENTS & VERIFICATION LEDGER          -->
        <!-- ============================================== -->
        <div v-if="activeTab === 'payments'" class="space-y-6">
            <!-- Section A: Pending Review Queue (if any) -->
            <div v-if="pendingPayments.length" class="space-y-3">
                <div class="flex items-center gap-2">
                    <span class="flex h-3 w-3 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                    </span>
                    <h3 class="font-bold text-sm text-slate-900">
                        Payments Awaiting Verification ({{ pendingPayments.length }})
                    </h3>
                </div>

                <div class="grid md:grid-cols-2 gap-4">
                    <div v-for="p in pendingPayments" :key="p.id"
                         class="rounded-2xl border-2 border-amber-300 bg-amber-50/30 p-5 shadow-sm space-y-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <span class="inline-block text-[11px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-200 mb-1.5">
                                    Awaiting Review
                                </span>
                                <p class="text-2xl font-black text-slate-900">₹{{ Number(p.amount).toFixed(2) }}</p>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Submitted {{ formatCalendarDate(p.submitted_at || p.received_at) }}
                                    <span v-if="p.submitted_by_name">by {{ p.submitted_by_name }}</span>
                                </p>
                            </div>
                            <span class="text-2xl" aria-hidden="true">
                                {{ p.payment_mode === 'upi' ? '📱' : (p.payment_mode === 'cash' ? '💵' : '🏦') }}
                            </span>
                        </div>

                        <!-- Payment meta -->
                        <div class="rounded-xl border border-amber-200/80 bg-white p-3 text-xs space-y-1.5">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Mode:</span>
                                <span class="font-bold text-slate-800 capitalize">{{ p.payment_mode.replace('_', ' ') }}</span>
                            </div>
                            <div v-if="p.transaction_ref" class="flex justify-between">
                                <span class="text-slate-500">Ref / UTR:</span>
                                <span class="font-mono font-bold text-slate-900">{{ p.transaction_ref }}</span>
                            </div>
                            <div v-if="p.bank_name" class="flex justify-between">
                                <span class="text-slate-500">Paid from Bank:</span>
                                <span class="font-medium text-slate-800">{{ p.bank_name }}</span>
                            </div>
                            <div v-if="p.notes" class="pt-1 border-t text-slate-600 italic">
                                Note: "{{ p.notes }}"
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <button v-if="p.has_proof" type="button" @click="previewProof(p)"
                                    class="btn-secondary text-xs font-semibold py-1.5">
                                <span>🔍</span> View Proof
                            </button>
                            <button type="button" @click="approvePayment(p)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-xs">
                                <span>✓</span> Approve & Credit
                            </button>
                            <button type="button" @click="rejectPayment(p)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-rose-200 bg-white hover:bg-rose-50 text-rose-700 text-xs font-bold transition">
                                <span>✕</span> Reject
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section B: All Recorded Payments Ledger -->
            <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm">
                <div class="bg-slate-50 p-4 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <h4 class="font-bold text-xs uppercase tracking-wider text-slate-700">Payments Ledger ({{ payments.length }})</h4>
                        <p class="text-xs text-slate-500">Total credited: ₹{{ Number(bill.amount_paid).toFixed(2) }}</p>
                    </div>
                </div>

                <table class="data-table text-xs">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="py-3">Receipt #</th>
                            <th class="py-3">Amount</th>
                            <th class="py-3">Mode</th>
                            <th class="py-3">Status</th>
                            <th class="py-3">Date</th>
                            <th class="py-3">Notes / Ref</th>
                            <th class="py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="p in payments" :key="p.id" class="hover:bg-slate-50/70 transition">
                            <td class="font-mono text-xs font-bold text-slate-900 py-3">{{ p.receipt_number || '—' }}</td>
                            <td class="font-extrabold text-slate-900 py-3">₹{{ Number(p.amount).toFixed(2) }}</td>
                            <td class="capitalize py-3">{{ p.payment_mode.replace('_', ' ') }}</td>
                            <td class="py-3">
                                <span :class="statusBadgeClass(p.status)">{{ statusLabel(p.status) }}</span>
                                <p v-if="p.status === 'rejected' && p.rejection_reason" class="text-[11px] text-rose-600 mt-0.5">
                                    Reason: {{ p.rejection_reason }}
                                </p>
                            </td>
                            <td class="py-3 text-slate-600">{{ formatCalendarDate(p.received_at || p.submitted_at) }}</td>
                            <td class="py-3 text-slate-600 max-w-xs truncate">
                                <span v-if="p.transaction_ref" class="font-mono text-[11px] mr-1">[{{ p.transaction_ref }}]</span>
                                <span>{{ p.notes || '—' }}</span>
                            </td>
                            <td class="text-right py-3 whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <button v-if="p.has_proof" type="button" @click="previewProof(p)"
                                            class="text-xs font-semibold text-indigo-600 hover:underline">
                                        Proof
                                    </button>
                                    <template v-if="p.status === 'pending'">
                                        <button type="button" class="text-xs font-bold text-emerald-600 hover:underline" @click="approvePayment(p)">Approve</button>
                                        <button type="button" class="text-xs font-bold text-rose-600 hover:underline" @click="rejectPayment(p)">Reject</button>
                                    </template>
                                    <button v-else-if="p.status === 'approved'" type="button" class="text-xs font-semibold text-rose-600 hover:underline" @click="voidPayment(p)">
                                        Void
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!payments.length">
                            <td colspan="7" class="p-8 text-center text-slate-400">No payments recorded for this bill yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Section C: Record Offline / Direct Payment Form -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
                <div>
                    <h4 class="font-bold text-sm text-slate-900 flex items-center gap-1.5">
                        <span>💵</span> Record Direct Payment
                    </h4>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Record cash, direct bank transfer, or other payments received directly by staff. Credited immediately without review.
                    </p>
                </div>

                <form @submit.prevent="recordPayment" class="grid sm:grid-cols-3 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-slate-700 block mb-1">Amount (₹) *</label>
                        <input v-model="paymentForm.amount" type="number" min="0.01" step="0.01"
                               :placeholder="Number(bill.balance_due).toFixed(2)" class="field text-xs w-full" required>
                        <p v-if="paymentForm.errors.amount" class="text-xs text-rose-600 mt-1">{{ paymentForm.errors.amount }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-slate-700 block mb-1">Payment Mode *</label>
                        <SearchableSelect v-model="paymentForm.payment_mode"
                                           :options="[{ value: 'cash', label: 'Cash' }, { value: 'upi', label: 'UPI' }, { value: 'bank_transfer', label: 'Bank Transfer' }, { value: 'other', label: 'Other' }]"
                                           :all-option="false" />
                        <p v-if="paymentForm.errors.payment_mode" class="text-xs text-rose-600 mt-1">{{ paymentForm.errors.payment_mode }}</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-slate-700 block mb-1">Notes (Optional)</label>
                        <input v-model="paymentForm.notes" type="text" placeholder="e.g. Received at reception counter" class="field text-xs w-full">
                    </div>

                    <div class="sm:col-span-3 flex justify-end">
                        <button type="submit" class="btn-primary text-xs font-bold px-5 py-2 shadow-xs"
                                :disabled="paymentForm.processing || !paymentForm.amount">
                            {{ paymentForm.processing ? 'Recording…' : 'Record & Credit Payment' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Proof Preview Modal -->
        <Modal :show="proofModalOpen" title="Payment Proof Verification" size="lg" @close="proofModalOpen = false">
            <div v-if="activeProofPayment" class="space-y-4">
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                    <div>
                        <p class="font-bold text-slate-900 text-sm">₹{{ Number(activeProofPayment.amount).toFixed(2) }}</p>
                        <p class="text-slate-500">
                            {{ activeProofPayment.payment_mode.replace('_', ' ') }} · Submitted {{ formatCalendarDate(activeProofPayment.submitted_at || activeProofPayment.received_at) }}
                        </p>
                    </div>
                    <div v-if="activeProofPayment.transaction_ref" class="text-right">
                        <span class="text-slate-500 block">Ref / UTR</span>
                        <span class="font-mono font-bold text-slate-900">{{ activeProofPayment.transaction_ref }}</span>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 overflow-hidden bg-slate-100 flex items-center justify-center min-h-[18rem] max-h-[30rem]">
                    <img :src="`${basePath}/payments/${activeProofPayment.id}/proof`"
                         alt="Proof Document"
                         class="max-h-[30rem] w-auto object-contain mx-auto">
                </div>

                <div class="flex justify-between items-center pt-2">
                    <a :href="`${basePath}/payments/${activeProofPayment.id}/proof`" target="_blank"
                       class="text-xs font-semibold text-indigo-600 hover:underline">
                        Open Full File in New Tab ↗
                    </a>
                    <div v-if="activeProofPayment.status === 'pending'" class="flex gap-2">
                        <button type="button" class="btn-secondary text-xs" @click="rejectPayment(activeProofPayment)">
                            Reject
                        </button>
                        <button type="button" class="btn-primary text-xs font-bold" @click="approvePayment(activeProofPayment)">
                            Approve Payment
                        </button>
                    </div>
                </div>
            </div>
        </Modal>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import FoodBillSummary from '@/Components/food/FoodBillSummary.vue';
import FoodBillStatusBadge from '@/Components/food/FoodBillStatusBadge.vue';
import VegBadge from '@/Components/food/VegBadge.vue';
import Modal from '@/Components/ui/Modal.vue';
import { mealIcon } from '@/support/mealIcons.js';
import { formatCalendarDate } from '@/support/calendarDates.js';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    bill: { type: Object, required: true },
    orderItems: { type: Array, default: () => [] },
    payments: { type: Array, default: () => [] },
    menuItems: { type: Array, default: () => [] },
    basePath: { type: String, required: true },
    canCancel: { type: Boolean, default: false },
});

const { confirm, prompt } = useConfirm();

const activeTab = ref('items'); // 'items' | 'payments'
const proofModalOpen = ref(false);
const activeProofPayment = ref(null);

function previewProof(payment) {
    activeProofPayment.value = payment;
    proofModalOpen.value = true;
}

const pendingPayments = computed(() => props.payments.filter((p) => p.status === 'pending'));
const pendingCount = computed(() => pendingPayments.value.length);

const itemForm = useForm({ menu_item_id: '', quantity: 1 });

const menuItemOptions = computed(() => props.menuItems.map((mi) => ({
    value: mi.id,
    label: `${formatCalendarDate(mi.menu_date)} · ${mi.meal_type} · ${mi.name} (₹${Number(mi.price).toFixed(2)})`,
})));

const remainingForSelected = computed(() => {
    const mi = props.menuItems.find((m) => String(m.id) === String(itemForm.menu_item_id));
    if (!mi || !mi.max_per_school) return null;
    const existingQty = props.orderItems.filter((oi) => oi.menu_item_id === mi.id).reduce((sum, oi) => sum + oi.quantity, 0);
    return Math.max(0, mi.max_per_school - existingQty);
});

function addItem() {
    itemForm.post(`${props.basePath}/items`, { preserveScroll: true, onSuccess: () => itemForm.reset() });
}
async function removeItem(oi) {
    if (!(await confirm({ message: `Remove ${oi.item_name} (x${oi.quantity})?`, destructive: true }))) return;
    router.delete(`${props.basePath}/items/${oi.id}`, { preserveScroll: true });
}

const paymentForm = useForm({ amount: '', payment_mode: 'cash', notes: '' });
function recordPayment() {
    paymentForm.post(`${props.basePath}/payments`, {
        preserveScroll: true,
        onSuccess: () => paymentForm.reset(),
    });
}

async function settle() {
    if (!(await confirm({ message: 'Mark this bill as settled?', destructive: false }))) return;
    router.post(`${props.basePath}/settle`, {}, { preserveScroll: true });
}
async function reopen() {
    if (!(await confirm({ message: 'Reopen this bill for editing?', destructive: false }))) return;
    router.post(`${props.basePath}/reopen`, {}, { preserveScroll: true });
}
async function cancelBill() {
    if (!(await confirm({ message: 'Cancel this bill? This is a terminal action and only allowed while no payments are recorded.', destructive: true }))) return;
    router.post(`${props.basePath}/cancel`, {}, { preserveScroll: true });
}
async function voidPayment(p) {
    if (!(await confirm({ message: `Void payment ${p.receipt_number} (₹${Number(p.amount).toFixed(2)})? This cannot be undone.`, destructive: true }))) return;
    router.delete(`${props.basePath}/payments/${p.id}`, { preserveScroll: true });
}

async function approvePayment(p) {
    if (!(await confirm({ message: `Approve this ₹${Number(p.amount).toFixed(2)} payment? It will count toward the bill's paid total.` }))) return;
    router.post(`${props.basePath}/payments/${p.id}/approve`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            proofModalOpen.value = false;
        },
    });
}
async function rejectPayment(p) {
    const reason = await prompt({
        title: 'Reject payment',
        message: `Reject this ₹${Number(p.amount).toFixed(2)} payment claim?`,
        inputLabel: 'Reason (optional)',
        inputPlaceholder: 'e.g. UTR does not match our bank statement',
        inputRequired: false,
        confirmLabel: 'Reject',
        destructive: true,
    });
    if (reason === null) return;
    router.post(`${props.basePath}/payments/${p.id}/reject`, { reason }, {
        preserveScroll: true,
        onSuccess: () => {
            proofModalOpen.value = false;
        },
    });
}

const STATUS_LABELS = { pending: 'Awaiting review', approved: 'Approved', rejected: 'Rejected' };
function statusLabel(status) {
    return STATUS_LABELS[status] ?? status;
}
function statusBadgeClass(status) {
    const base = 'inline-block text-[11px] font-bold px-2.5 py-0.5 rounded-full';
    if (status === 'approved') return `${base} bg-emerald-50 text-emerald-700 border border-emerald-200`;
    if (status === 'rejected') return `${base} bg-rose-50 text-rose-700 border border-rose-200`;
    return `${base} bg-amber-50 text-amber-700 border border-amber-200`;
}
</script>
