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
            <div class="card text-center">
                <p class="text-2xl font-bold">{{ summary.issued }}</p>
                <p class="text-xs text-gray-500">Issued</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-green-700">{{ summary.redeemed }}</p>
                <p class="text-xs text-gray-500">Redeemed</p>
            </div>
        </div>

        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        <th class="p-3">Sl No</th>
                        <th class="p-3">Code</th>
                        <th class="p-3">School</th>
                        <th class="p-3">Meal</th>
                        <th class="p-3">Date</th>
                        <th class="p-3">Heads</th>
                        <th class="p-3">Status</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(c, idx) in coupons" :key="c.id" class="border-t">
                        <td class="p-3">{{ idx + 1 }}</td>
                        <td class="p-3 font-mono text-xs">{{ c.coupon_code }}</td>
                        <td class="p-3">{{ (c.school_name || '').toUpperCase() }}</td>
                        <td class="p-3 capitalize">{{ c.meal_type }}</td>
                        <td class="p-3">{{ formatCalendarDate(c.valid_date) }}</td>
                        <td class="p-3">{{ c.head_count }}</td>
                        <td class="p-3">
                            <span class="text-xs px-2 py-0.5 rounded"
                                  :class="c.status === 'redeemed' ? 'bg-green-100 text-green-800' : c.status === 'void' ? 'bg-gray-100 text-gray-500' : 'bg-amber-100 text-amber-800'">
                                {{ c.status }}
                            </span>
                        </td>
                        <td class="p-3 text-right">
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
