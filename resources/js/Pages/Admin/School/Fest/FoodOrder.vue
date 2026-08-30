<template>
    <SchoolAdminLayout :title="`${event.title} — Food Order`" :school="school" :show-header-title="false">
        <PageHeader :title="`${event.title} — Food Order`" eyebrow="Programs"
            :description="`Preorder food for your contingent. ${payeeLabel}.`" />

        <EventHierarchyBadge :hierarchy="hierarchy" />

        <p v-if="bill && bill.status !== 'open'" class="text-xs text-amber-700 bg-amber-50 px-3 py-2 rounded-lg border border-amber-200 mb-4">
            This bill has been settled. Contact the Sahodaya if you need to change your order.
        </p>

        <div class="grid lg:grid-cols-3 gap-6 items-start">
            <!-- Menu, grouped by day then meal -->
            <div class="lg:col-span-2 space-y-6">
                <div v-for="group in groupedMenu" :key="group.date">
                    <h3 class="section-title mb-3">{{ formatCalendarDate(group.date) }}</h3>
                    <div v-for="mealGroup in group.meals" :key="mealGroup.mealType" class="mb-5 last:mb-0">
                        <div class="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <span aria-hidden="true">{{ mealIcon(mealGroup.mealType) }}</span>
                            <span>{{ mealTypes[mealGroup.mealType] || mealGroup.mealType }}</span>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <FoodItemCard v-for="item in mealGroup.items" :key="item.id"
                                          :name="item.name" :description="item.description" :price="Number(item.price)"
                                          :icon="mealIcon(mealGroup.mealType)" :badges="badgesFor(item)">
                                <template v-if="canOrder" #actions>
                                    <template v-if="!item.max_per_school || remainingFor(item) > 0">
                                        <QuantityStepper :model-value="qty[item.id] ?? 1" :max="item.max_per_school ? remainingFor(item) : null"
                                                          @update:model-value="(val) => (qty[item.id] = val)" />
                                        <button class="btn-secondary text-xs" :disabled="itemForm.processing" @click="addItem(item)">Add</button>
                                    </template>
                                    <span v-else class="text-xs text-gray-400">Limit reached</span>
                                </template>
                            </FoodItemCard>
                        </div>
                    </div>
                </div>
                <EmptyState v-if="!menuItems.length" title="No menu published yet" description="The Sahodaya hasn't added food items for this event yet." />
            </div>

            <!-- Cart panel -->
            <div id="your-order" class="lg:sticky lg:top-6 space-y-4">
                <FoodBillSummary v-if="bill" :total="Number(bill.amount_total)" :paid="Number(bill.amount_paid)"
                                  :balance="Number(bill.balance_due)" :status="bill.status" />

                <div class="card-list">
                    <div class="p-3 border-b bg-gray-50 font-bold text-sm">Your order</div>
                    <div v-if="!orderItems.length" class="p-4 text-center text-sm text-slate-400">Nothing ordered yet.</div>
                    <div v-for="oi in orderItems" :key="oi.id" class="card-list-row">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-900">{{ oi.item_name }} <span class="text-slate-400">×{{ oi.quantity }}</span></p>
                            <p class="text-xs text-slate-500">{{ formatCalendarDate(oi.menu_date) }} · ₹{{ Number(oi.line_total).toFixed(2) }}</p>
                        </div>
                        <button v-if="canOrder" class="shrink-0 text-xs font-semibold text-red-500" @click="removeItem(oi)">Remove</button>
                    </div>
                </div>

                <details v-if="payments.length" class="card card--flush">
                    <summary class="p-3 cursor-pointer select-none font-bold text-sm">Payments received ({{ payments.length }})</summary>
                    <table class="data-table">
                        <thead><tr><th>Receipt</th><th>Amount</th><th>Mode</th><th>Date</th></tr></thead>
                        <tbody>
                            <tr v-for="p in payments" :key="p.id">
                                <td class="font-mono text-xs">{{ p.receipt_number }}</td>
                                <td>₹{{ Number(p.amount).toFixed(2) }}</td>
                                <td class="capitalize">{{ p.payment_mode.replace('_', ' ') }}</td>
                                <td>{{ formatCalendarDate(p.received_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </details>
            </div>
        </div>

        <!-- Mobile-only sticky total, so the running balance stays visible while scrolling the menu -->
        <div v-if="bill" class="lg:hidden sticky bottom-3 mt-6 mx-1 z-10">
            <a href="#your-order" class="flex items-center justify-between rounded-2xl bg-[color:var(--brand-navy)] px-4 py-3 text-white shadow-lg">
                <span class="text-sm font-semibold">Total ₹{{ Number(bill.amount_total).toFixed(2) }}</span>
                <span class="text-xs" :class="Number(bill.balance_due) > 0 ? 'text-amber-300' : 'text-emerald-300'">
                    Balance ₹{{ Number(bill.balance_due).toFixed(2) }}
                </span>
            </a>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, reactive } from 'vue';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import EventHierarchyBadge from '@/Components/fest/EventHierarchyBadge.vue';
import FoodBillSummary from '@/Components/food/FoodBillSummary.vue';
import FoodItemCard from '@/Components/food/FoodItemCard.vue';
import QuantityStepper from '@/Components/food/QuantityStepper.vue';
import { mealIcon } from '@/support/mealIcons.js';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { formatCalendarDate } from '@/support/calendarDates.js';
import { useConfirm } from '@/composables/useConfirm';
const { confirm } = useConfirm();

const props = defineProps({
    event: Object,
    hierarchy: { type: Object, default: null },
    menuItems: { type: Array, default: () => [] },
    mealTypes: { type: Object, default: () => ({}) },
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

function badgesFor(item) {
    if (!item.max_per_school) return [];
    const remaining = remainingFor(item);
    const badges = [{ label: `${orderedQty(item.id)} / ${item.max_per_school} ordered`, tone: remaining <= 0 ? 'amber' : 'slate' }];
    if (remaining <= 0) badges.push({ label: 'Limit reached', tone: 'amber' });
    return badges;
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

// Canonical meal order comes from the mealTypes prop (breakfast/lunch/snacks/tea/dinner/
// other — see FestFoodMenuItem::MEAL_TYPES), not from however the items array arrives.
const mealTypeOrder = computed(() => Object.keys(props.mealTypes));

const groupedMenu = computed(() => {
    const byDate = {};
    for (const item of props.menuItems) {
        const d = item.menu_date;
        if (!byDate[d]) byDate[d] = {};
        if (!byDate[d][item.meal_type]) byDate[d][item.meal_type] = [];
        byDate[d][item.meal_type].push(item);
    }
    return Object.keys(byDate).sort().map((date) => ({
        date,
        meals: mealTypeOrder.value
            .filter((mt) => byDate[date][mt]?.length)
            .map((mt) => ({ mealType: mt, items: byDate[date][mt] })),
    }));
});
</script>
