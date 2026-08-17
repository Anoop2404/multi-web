<template>
    <SchoolAdminLayout :title="`${event.title} — Food Order`" :school="school" :show-header-title="false">
        <PageHeader :title="`${event.title} — Food Order`" eyebrow="Programs"
            :description="`Preorder food for your contingent. ${payeeLabel}.`" />

        <div v-if="bill" class="grid grid-cols-3 gap-3 mb-6 max-w-lg">
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
        <p v-if="bill && bill.status !== 'open'" class="text-xs text-amber-700 bg-amber-50 px-3 py-2 rounded-lg border border-amber-200 mb-4">
            This bill has been settled. Contact the Sahodaya if you need to change your order.
        </p>

        <div v-for="group in groupedMenu" :key="group.date" class="card card--flush mb-4">
            <div class="p-3 border-b bg-gray-50 font-bold text-sm">{{ formatCalendarDate(group.date) }}</div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">Meal</th>
                        <th class="p-3">Item</th>
                        <th class="p-3">Price</th>
                        <th class="p-3">Ordered</th>
                        <th class="p-3 text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in group.items" :key="item.id" class="border-t">
                        <td class="p-3 capitalize">{{ item.meal_type }}</td>
                        <td class="p-3">
                            {{ item.name }}
                            <p v-if="item.description" class="text-xs text-gray-400">{{ item.description }}</p>
                            <p v-if="item.max_per_school" class="text-xs" :class="remainingFor(item) <= 0 ? 'text-amber-600 font-semibold' : 'text-gray-400'">
                                {{ orderedQty(item.id) }} / {{ item.max_per_school }} ordered
                                <span v-if="remainingFor(item) <= 0">— limit reached</span>
                            </p>
                        </td>
                        <td class="p-3">₹{{ Number(item.price).toFixed(2) }}</td>
                        <td class="p-3">{{ orderedQty(item.id) }}</td>
                        <td class="p-3 text-right" v-if="canOrder">
                            <div v-if="!item.max_per_school || remainingFor(item) > 0" class="flex items-center gap-2 justify-end">
                                <input type="number" min="1" :max="item.max_per_school ? remainingFor(item) : undefined"
                                       v-model="qty[item.id]" class="field text-xs w-16">
                                <button class="btn-secondary text-xs" :disabled="itemForm.processing" @click="addItem(item)">Add</button>
                            </div>
                            <span v-else class="text-xs text-gray-400">Limit reached</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-if="!menuItems.length" title="No menu published yet" description="The Sahodaya hasn't added food items for this event yet." />

        <div v-if="orderItems.length" class="card card--flush mt-6">
            <div class="p-3 border-b bg-gray-50 font-bold text-sm">Your order</div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">Date</th>
                        <th class="p-3">Item</th>
                        <th class="p-3">Qty</th>
                        <th class="p-3">Line total</th>
                        <th class="p-3 text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="oi in orderItems" :key="oi.id" class="border-t">
                        <td class="p-3">{{ formatCalendarDate(oi.menu_date) }}</td>
                        <td class="p-3">{{ oi.item_name }}</td>
                        <td class="p-3">{{ oi.quantity }}</td>
                        <td class="p-3">₹{{ Number(oi.line_total).toFixed(2) }}</td>
                        <td class="p-3 text-right">
                            <button v-if="canOrder" class="text-xs font-semibold text-red-500" @click="removeItem(oi)">Remove</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="payments.length" class="card card--flush mt-6">
            <div class="p-3 border-b bg-gray-50 font-bold text-sm">Payments received</div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr><th class="p-3">Receipt</th><th class="p-3">Amount</th><th class="p-3">Mode</th><th class="p-3">Date</th></tr>
                </thead>
                <tbody>
                    <tr v-for="p in payments" :key="p.id" class="border-t">
                        <td class="p-3 font-mono text-xs">{{ p.receipt_number }}</td>
                        <td class="p-3">₹{{ Number(p.amount).toFixed(2) }}</td>
                        <td class="p-3 capitalize">{{ p.payment_mode.replace('_', ' ') }}</td>
                        <td class="p-3">{{ formatCalendarDate(p.received_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, reactive } from 'vue';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { formatCalendarDate } from '@/support/calendarDates.js';
import { useConfirm } from '@/composables/useConfirm';
const { confirm, prompt } = useConfirm();

const props = defineProps({
    event: Object,
    menuItems: { type: Array, default: () => [] },
    bill: { type: Object, default: null },
    orderItems: { type: Array, default: () => [] },
    payments: { type: Array, default: () => [] },
    payeeLabel: { type: String, default: '' },
});

const school = computed(() => usePage().props.school);
const base = `/school-admin/${school.value.id}/fest/${props.event.id}/food-order`;

const canOrder = computed(() => !props.bill || props.bill.status === 'open');

const qty = reactive({});
const itemForm = useForm({ menu_item_id: '', quantity: 1 });

function orderedQty(menuItemId) {
    return props.orderItems.filter((oi) => oi.menu_item_id === menuItemId).reduce((sum, oi) => sum + oi.quantity, 0);
}

// Live remaining-quantity check against max_per_school, computed from the school's own
// already-ordered items — mirrors the server-side check in FestFoodOrderController::addItem
// so a school sees the limit before submitting, not just as an error after.
function remainingFor(item) {
    if (!item.max_per_school) return Infinity;
    return item.max_per_school - orderedQty(item.id);
}

function addItem(item) {
    const requested = Math.min(Number(qty[item.id]) || 1, remainingFor(item));
    if (requested < 1) return;
    itemForm.menu_item_id = item.id;
    itemForm.quantity = requested;
    itemForm.post(`${base}/items`, { preserveScroll: true });
}
async function removeItem(oi) {
    if (!(await confirm({ message: `Remove ${oi.item_name} (x${oi.quantity})?`, destructive: true }))) return;
    router.delete(`${base}/items/${oi.id}`, { preserveScroll: true });
}

const groupedMenu = computed(() => {
    const byDate = {};
    for (const item of props.menuItems) {
        const d = item.menu_date;
        if (!byDate[d]) byDate[d] = [];
        byDate[d].push(item);
    }
    return Object.keys(byDate).sort().map((date) => ({ date, items: byDate[date] }));
});
</script>
