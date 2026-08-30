<template>
    <SahodayaEventsLayout :title="`${event.title} — Food Billing`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Food Billing`" eyebrow="Operations"
                    description="Per-school food bills." />

        <EventHierarchyBadge :hierarchy="hierarchy" :hub-href="hubHref" />

        <div class="flex flex-wrap gap-2 items-center mb-4">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/food-menu`" class="text-sm text-indigo-600">← Food Menu</Link>
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/food-billing/report`" class="text-sm text-indigo-600 ml-auto">Day-wise report →</Link>
            <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/food-billing/export`"
               class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs font-semibold text-gray-700">Export CSV</a>
        </div>

        <div v-if="event.food_payee_type === 'host_school'" class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-800 mb-4">
            <strong>{{ hostSchoolName || 'The host school' }}</strong> is the designated payee for this event and manages billing directly from their own dashboard.
            You can still view and record payments here for oversight, but day-to-day billing is expected to happen on their side.
        </div>
        <div v-else class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 mb-4">
            {{ payeeNote }}
        </div>

        <!-- Region-partitioned hub: bills live on each region's own event, this page's
             own totals are empty by construction — show the cross-region rollup instead. -->
        <div v-if="isPartitionedHub" class="rounded-xl border border-gray-200 bg-white p-4 mb-4">
            <h3 class="font-bold text-sm mb-3">Combined across all regions</h3>
            <div class="grid grid-cols-3 gap-3 max-w-lg mb-3">
                <div class="card text-center">
                    <p class="text-xl font-bold">₹{{ regionFoodSummary.billing.total.toFixed(2) }}</p>
                    <p class="text-xs text-gray-500">Total billed</p>
                </div>
                <div class="card text-center">
                    <p class="text-xl font-bold text-green-700">₹{{ regionFoodSummary.billing.paid.toFixed(2) }}</p>
                    <p class="text-xs text-gray-500">Paid</p>
                </div>
                <div class="card text-center">
                    <p class="text-xl font-bold" :class="regionFoodSummary.billing.balance > 0 ? 'text-amber-700' : 'text-gray-700'">₹{{ regionFoodSummary.billing.balance.toFixed(2) }}</p>
                    <p class="text-xs text-gray-500">Balance due</p>
                </div>
            </div>
            <p class="text-xs text-gray-500 mb-3">
                Catering headcount: {{ regionFoodSummary.catering_head_count }} ·
                Coupons issued: {{ regionFoodSummary.coupons.issued }} ·
                Redeemed: {{ regionFoodSummary.coupons.redeemed }}
            </p>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Region</th><th>Total</th><th>Paid</th><th>Balance</th><th>Headcount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in regionFoodSummary.by_region" :key="r.region">
                        <td>{{ r.region }}</td>
                        <td>₹{{ r.total.toFixed(2) }}</td>
                        <td>₹{{ r.paid.toFixed(2) }}</td>
                        <td>₹{{ r.balance.toFixed(2) }}</td>
                        <td>{{ r.head_count }}</td>
                    </tr>
                </tbody>
            </table>
            <p class="text-xs text-gray-400 mt-3">To manage an individual region's bills, open that region's own event page.</p>
        </div>

        <FoodRegionDrillDown v-if="isPartitionedHub" :sahodaya-id="sahodaya.id" :regions="foodRegionSummary"
                              target-path="food-billing" class="mb-6" />

        <FoodBillingList v-if="!isPartitionedHub" :bills="bills" :summary="summary" :base-path="base + '/food-billing'"
                          show-open-form :school-options="schoolOptions" />

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import EventHierarchyBadge from '@/Components/fest/EventHierarchyBadge.vue';
import FoodRegionDrillDown from '@/Components/sahodaya/FoodRegionDrillDown.vue';
import FoodBillingList from '@/Components/food/FoodBillingList.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, hostSchoolName: { type: String, default: null },
    hierarchy: { type: Object, default: null },
    bills: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({ total: 0, paid: 0, balance: 0 }) },
    schoolOptions: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
    isPartitionedHub: { type: Boolean, default: false },
    regionFoodSummary: { type: Object, default: null },
    foodRegionSummary: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const hubHref = computed(() => (
    props.hierarchy?.parent_event ? `/sahodaya-admin/${props.sahodaya.id}/events/${props.hierarchy.parent_event.id}/food-billing` : null
));

const payeeNote = computed(() => (
    props.event.food_payee_type === 'host_school'
        ? `Payments are payable to ${props.hostSchoolName || 'the host school'}, not the Sahodaya.`
        : 'Payments are payable to the Sahodaya.'
));
</script>
