<template>
    <SahodayaEventsLayout :title="`${event.title} — Food Coupons`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Food Coupons`" eyebrow="Operations"
                    :description="isPartitionedHub
                        ? 'Coupons are issued per region — pick a region below.'
                        : 'Issue and redeem meal coupons from catering orders or paid food bills.'" />

        <EventHierarchyBadge :hierarchy="hierarchy" :hub-href="hubHref" />

        <div v-if="!isPartitionedHub" class="flex flex-wrap gap-2 mb-4">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/catering`" class="text-sm text-indigo-600">← Catering</Link>
            <button @click="issueCoupons" class="btn-primary ml-auto">
                Issue from confirmed catering
            </button>
            <button @click="issueFromBill" class="btn-primary">
                Issue from food billing
            </button>
            <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/food-coupons/print`" target="_blank"
               class="px-4 py-2 border rounded-lg text-sm font-semibold">Print issued PDF</a>
        </div>

        <FoodRegionDrillDown v-if="isPartitionedHub" :sahodaya-id="sahodaya.id" :regions="foodRegionSummary"
                              target-path="food-coupons" class="mb-6" />

        <template v-if="!isPartitionedHub">
        <div class="grid grid-cols-2 gap-3 mb-4 max-w-sm">
            <div class="stat-tile text-center">
                <p class="stat-tile-value">{{ summary.issued }}</p>
                <p class="stat-tile-label">Issued</p>
            </div>
            <div class="stat-tile text-center">
                <p class="stat-tile-value text-emerald-700">{{ summary.redeemed }}</p>
                <p class="stat-tile-label">Redeemed</p>
            </div>
        </div>

        <div class="card card--flush">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sl No</th>
                        <th>Code</th>
                        <th>School</th>
                        <th>Meal</th>
                        <th>Date</th>
                        <th>Heads</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(c, idx) in coupons" :key="c.id">
                        <td>{{ idx + 1 }}</td>
                        <td class="font-mono text-xs">{{ c.coupon_code }}</td>
                        <td>{{ (c.school_name || '').toUpperCase() }}</td>
                        <td class="capitalize">{{ c.meal_type }}</td>
                        <td>{{ formatCalendarDate(c.valid_date) }}</td>
                        <td>{{ c.head_count }}</td>
                        <td>
                            <span class="status-pill" :class="couponStatusPillClass(c.status)">{{ couponStatusLabel(c.status) }}</span>
                        </td>
                        <td class="text-right">
                            <button v-if="c.status === 'issued'" @click="redeem(c.id)" class="text-green-600 text-xs font-semibold">Redeem</button>
                        </td>
                    </tr>
                    <tr v-if="!coupons.length">
                        <td colspan="8" class="p-8 text-center text-gray-400">No coupons yet. Confirm catering orders or settle a food bill first, then issue.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        </template>
            <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import EventHierarchyBadge from '@/Components/fest/EventHierarchyBadge.vue';
import FoodRegionDrillDown from '@/Components/sahodaya/FoodRegionDrillDown.vue';
import { formatCalendarDate } from '@/support/calendarDates.js';
import { couponStatusLabel, couponStatusPillClass } from '@/support/foodBillStatus.js';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, coupons: Array, summary: Object,
    hierarchy: { type: Object, default: null },
    isPartitionedHub: { type: Boolean, default: false },
    foodRegionSummary: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const hubHref = computed(() => (
    props.hierarchy?.parent_event ? `/sahodaya-admin/${props.sahodaya.id}/events/${props.hierarchy.parent_event.id}/food-coupons` : null
));

function issueCoupons() {
    router.post(`${base}/food-coupons/issue`, {}, { preserveScroll: true });
}
function issueFromBill() {
    router.post(`${base}/food-coupons/issue-from-bill`, {}, { preserveScroll: true });
}
function redeem(id) {
    router.post(`${base}/food-coupons/${id}/redeem`, {}, { preserveScroll: true });
}
</script>
