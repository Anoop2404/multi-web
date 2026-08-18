<template>
    <SchoolAdminLayout :title="`${bill.school_name} — Food Bill`" :school="school" :show-header-title="false">
        <PageHeader :title="`${bill.school_name} — Food Bill`" eyebrow="Programs" :description="event.title" />

        <Link :href="`/school-admin/${school.id}/fest/${event.id}/food-host-billing`" class="text-sm text-indigo-600 mb-4 inline-block">← All bills</Link>

        <EventHierarchyBadge :hierarchy="hierarchy" />

        <div class="grid grid-cols-3 gap-3 mb-6 max-w-lg">
            <div class="card text-center">
                <p class="text-xl font-bold">₹{{ Number(bill.amount_total).toFixed(2) }}</p>
                <p class="text-xs text-gray-500">Total</p>
            </div>
            <div class="card text-center">
                <p class="text-xl font-bold text-green-700">₹{{ Number(bill.amount_paid).toFixed(2) }}</p>
                <p class="text-xs text-gray-500">Paid</p>
            </div>
            <div class="card text-center">
                <p class="text-xl font-bold" :class="bill.balance_due > 0 ? 'text-amber-700' : 'text-gray-700'">₹{{ Number(bill.balance_due).toFixed(2) }}</p>
                <p class="text-xs text-gray-500">Balance due</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 items-center mb-6">
            <span class="text-xs px-2 py-1 rounded" :class="statusBadgeClass">{{ bill.status }}</span>
            <button v-if="bill.status === 'open'" @click="settle" class="btn-secondary text-xs">Mark settled</button>
            <button v-if="bill.status === 'settled'" @click="reopen" class="text-xs text-indigo-600 font-semibold">Reopen bill</button>
            <a :href="`${base}/pdf`" target="_blank" class="ml-auto px-3 py-1.5 border border-gray-200 rounded-lg text-xs font-semibold text-gray-700">Print PDF</a>
        </div>

        <div class="card card--flush mb-4">
            <div class="p-3 border-b bg-gray-50 font-bold text-sm">Order items</div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">Date</th><th class="p-3">Meal</th><th class="p-3">Item</th>
                        <th class="p-3">Qty</th><th class="p-3">Unit price</th><th class="p-3">Line total</th><th class="p-3 text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="oi in orderItems" :key="oi.id" class="border-t">
                        <td class="p-3">{{ formatCalendarDate(oi.menu_date) }}</td>
                        <td class="p-3 capitalize">{{ oi.meal_type }}</td>
                        <td class="p-3">{{ oi.item_name }}</td>
                        <td class="p-3">{{ oi.quantity }}</td>
                        <td class="p-3">₹{{ Number(oi.unit_price).toFixed(2) }}</td>
                        <td class="p-3">₹{{ Number(oi.line_total).toFixed(2) }}</td>
                        <td class="p-3 text-right">
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
                        <select :id="id" v-model="itemForm.menu_item_id" class="field text-sm">
                            <option value="">— Select item —</option>
                            <option v-for="mi in menuItems" :key="mi.id" :value="mi.id">
                                {{ formatCalendarDate(mi.menu_date) }} · {{ mi.meal_type }} · {{ mi.name }} (₹{{ Number(mi.price).toFixed(2) }})
                            </option>
                        </select>
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

        <div class="card card--flush">
            <div class="p-3 border-b bg-gray-50 font-bold text-sm">Payments</div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr><th class="p-3">Receipt</th><th class="p-3">Amount</th><th class="p-3">Mode</th><th class="p-3">Received</th><th class="p-3">Notes</th><th class="p-3 text-right"></th></tr>
                </thead>
                <tbody>
                    <tr v-for="p in payments" :key="p.id" class="border-t">
                        <td class="p-3 font-mono text-xs">{{ p.receipt_number }}</td>
                        <td class="p-3">₹{{ Number(p.amount).toFixed(2) }}</td>
                        <td class="p-3 capitalize">{{ p.payment_mode.replace('_', ' ') }}</td>
                        <td class="p-3">{{ formatCalendarDate(p.received_at) }}</td>
                        <td class="p-3 text-gray-500">{{ p.notes }}</td>
                        <td class="p-3 text-right">
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
                        <select :id="id" v-model="paymentForm.payment_mode" class="field text-sm">
                            <option value="cash">Cash</option>
                            <option value="upi">UPI</option>
                            <option value="bank_transfer">Bank transfer</option>
                            <option value="other">Other</option>
                        </select>
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
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, useForm, router, usePage } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import EventHierarchyBadge from '@/Components/fest/EventHierarchyBadge.vue';
import { formatCalendarDate } from '@/support/calendarDates.js';
import { useConfirm } from '@/composables/useConfirm';
const { confirm } = useConfirm();

const props = defineProps({
    event: Object, bill: Object,
    hierarchy: { type: Object, default: null },
    orderItems: { type: Array, default: () => [] },
    payments: { type: Array, default: () => [] },
    menuItems: { type: Array, default: () => [] },
});

const school = computed(() => usePage().props.school);
const base = computed(() => `/school-admin/${school.value.id}/fest/${props.event.id}/food-host-billing/${props.bill.id}`);

const statusBadgeClass = computed(() => ({
    'bg-green-100 text-green-700': props.bill.status === 'settled',
    'bg-red-100 text-red-700': props.bill.status === 'cancelled',
    'bg-gray-100 text-gray-700': props.bill.status === 'open',
}));

const itemForm = useForm({ menu_item_id: '', quantity: 1 });

const remainingForSelected = computed(() => {
    const mi = props.menuItems.find((m) => String(m.id) === String(itemForm.menu_item_id));
    if (!mi || !mi.max_per_school) return null;
    const existingQty = props.orderItems.filter((oi) => oi.menu_item_id === mi.id).reduce((sum, oi) => sum + oi.quantity, 0);
    return Math.max(0, mi.max_per_school - existingQty);
});

function addItem() {
    itemForm.post(`${base.value}/items`, { preserveScroll: true, onSuccess: () => itemForm.reset() });
}
async function removeItem(oi) {
    if (!(await confirm({ message: `Remove ${oi.item_name} (x${oi.quantity})?`, destructive: true }))) return;
    router.delete(`${base.value}/items/${oi.id}`, { preserveScroll: true });
}

const paymentForm = useForm({ amount: '', payment_mode: 'cash', notes: '' });
function recordPayment() {
    paymentForm.post(`${base.value}/payments`, { preserveScroll: true, onSuccess: () => paymentForm.reset() });
}

async function settle() {
    if (!(await confirm({ message: 'Mark this bill as settled?', destructive: false }))) return;
    router.post(`${base.value}/settle`, {}, { preserveScroll: true });
}
async function reopen() {
    if (!(await confirm({ message: 'Reopen this bill for editing?', destructive: false }))) return;
    router.post(`${base.value}/reopen`, {}, { preserveScroll: true });
}
async function voidPayment(p) {
    if (!(await confirm({ message: `Void payment ${p.receipt_number} (₹${Number(p.amount).toFixed(2)})? This cannot be undone.`, destructive: true }))) return;
    router.delete(`${base.value}/payments/${p.id}`, { preserveScroll: true });
}
</script>
