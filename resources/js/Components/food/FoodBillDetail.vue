<template>
    <div>
        <FoodBillSummary :total="Number(bill.amount_total)" :paid="Number(bill.amount_paid)"
                          :balance="Number(bill.balance_due)" :status="bill.status" class="mb-4 max-w-lg" />

        <div class="flex flex-wrap gap-2 items-center mb-6">
            <button v-if="bill.status === 'open'" @click="settle" class="btn-secondary text-xs">Mark settled</button>
            <button v-if="bill.status === 'settled'" @click="reopen" class="text-xs text-indigo-600 font-semibold">Reopen bill</button>
            <!-- canCancel is false by default because the host-school billing controller
                 exposes no cancel route at all (see FestFoodHostBillingControllerTest::
                 test_the_host_billing_controller_exposes_no_cancel_action_unlike_the_sahodaya_side).
                 Only FoodBillingShow.vue (Sahodaya side) opts in with :can-cancel="true". -->
            <button v-if="canCancel && bill.status === 'open'" @click="cancelBill" class="text-xs text-red-600 font-semibold">Cancel bill</button>
            <a :href="`${basePath}/pdf`" target="_blank" class="ml-auto px-3 py-1.5 border border-gray-200 rounded-lg text-xs font-semibold text-gray-700">Print PDF</a>
        </div>

        <!-- Order items -->
        <div class="card card--flush mb-4">
            <div class="p-3 border-b bg-gray-50 font-bold text-sm">Order items</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th><th>Meal</th><th>Item</th><th>Qty</th><th>Unit price</th><th>Line total</th><th class="text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="oi in orderItems" :key="oi.id">
                        <td>{{ formatCalendarDate(oi.menu_date) }}</td>
                        <td class="capitalize">{{ oi.meal_type }}</td>
                        <td>{{ oi.item_name }}</td>
                        <td>{{ oi.quantity }}</td>
                        <td>₹{{ Number(oi.unit_price).toFixed(2) }}</td>
                        <td>₹{{ Number(oi.line_total).toFixed(2) }}</td>
                        <td class="text-right">
                            <button v-if="bill.status === 'open'" class="text-xs font-semibold text-red-500" @click="removeItem(oi)">Remove</button>
                        </td>
                    </tr>
                    <tr v-if="!orderItems.length">
                        <td colspan="7" class="p-6 text-center text-gray-400">No items ordered yet.</td>
                    </tr>
                </tbody>
            </table>
            <form v-if="bill.status === 'open'" @submit.prevent="addItem" class="p-3 border-t flex flex-wrap items-end gap-3">
                <FormField label="Menu item" :error="itemForm.errors.menu_item_id">
                    <template #default="{ id }">
                        <SearchableSelect :id="id" v-model="itemForm.menu_item_id" :options="menuItemOptions" :all-option="true" all-label="— Select item —" />
                    </template>
                </FormField>
                <FormField label="Qty" :error="itemForm.errors.quantity">
                    <template #default="{ id }">
                        <input :id="id" v-model="itemForm.quantity" type="number" min="1" :max="remainingForSelected ?? undefined" class="field text-sm w-20">
                    </template>
                </FormField>
                <button type="submit" class="btn-secondary text-sm" :disabled="itemForm.processing || !itemForm.menu_item_id || remainingForSelected === 0">Add</button>
                <p v-if="remainingForSelected === 0" class="text-xs text-amber-600 w-full">This school has already reached the per-school limit for that item.</p>
            </form>
        </div>

        <!-- Payments -->
        <div class="card card--flush">
            <div class="p-3 border-b bg-gray-50 font-bold text-sm">Payments</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Receipt</th><th>Amount</th><th>Mode</th><th>Received</th><th>Notes</th><th class="text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in payments" :key="p.id">
                        <td class="font-mono text-xs">{{ p.receipt_number }}</td>
                        <td>₹{{ Number(p.amount).toFixed(2) }}</td>
                        <td class="capitalize">{{ p.payment_mode.replace('_', ' ') }}</td>
                        <td>{{ formatCalendarDate(p.received_at) }}</td>
                        <td class="text-gray-500">{{ p.notes }}</td>
                        <td class="text-right">
                            <button class="text-xs font-semibold text-red-500" @click="voidPayment(p)">Void</button>
                        </td>
                    </tr>
                    <tr v-if="!payments.length">
                        <td colspan="6" class="p-6 text-center text-gray-400">No payments recorded yet.</td>
                    </tr>
                </tbody>
            </table>
            <form @submit.prevent="recordPayment" class="p-3 border-t flex flex-wrap items-end gap-3">
                <FormField label="Amount (₹)" :error="paymentForm.errors.amount">
                    <template #default="{ id }">
                        <input :id="id" v-model="paymentForm.amount" type="number" min="0.01" step="0.01" class="field text-sm w-28">
                    </template>
                </FormField>
                <FormField label="Mode" :error="paymentForm.errors.payment_mode">
                    <template #default="{ id }">
                        <SearchableSelect :id="id" v-model="paymentForm.payment_mode" :options="[{ value: 'cash', label: 'Cash' }, { value: 'upi', label: 'UPI' }, { value: 'bank_transfer', label: 'Bank transfer' }, { value: 'other', label: 'Other' }]" :all-option="false" />
                    </template>
                </FormField>
                <FormField label="Notes (optional)" :error="paymentForm.errors.notes" class-extra="flex-1 min-w-[10rem]">
                    <template #default="{ id }">
                        <input :id="id" v-model="paymentForm.notes" type="text" class="field text-sm w-full">
                    </template>
                </FormField>
                <button type="submit" class="btn-primary text-sm" :disabled="paymentForm.processing || !paymentForm.amount">Record payment</button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import FoodBillSummary from '@/Components/food/FoodBillSummary.vue';
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

const { confirm } = useConfirm();

const itemForm = useForm({ menu_item_id: '', quantity: 1 });

const menuItemOptions = computed(() => props.menuItems.map((mi) => ({
    value: mi.id,
    label: `${formatCalendarDate(mi.menu_date)} · ${mi.meal_type} · ${mi.name} (₹${Number(mi.price).toFixed(2)})`,
})));

// Live remaining-quantity check against max_per_school for the currently selected item,
// mirroring the server-side check in FestFoodBillingController::addItem.
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
    paymentForm.post(`${props.basePath}/payments`, { preserveScroll: true, onSuccess: () => paymentForm.reset() });
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
</script>
