<template>
    <SahodayaEventsLayout :title="`${event.title} — Catering`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Catering`" eyebrow="Operations"
                    :description="isPartitionedHub
                        ? 'Catering orders happen per region — pick a region below.'
                        : 'Manage catering orders from schools.'" />

        <EventHierarchyBadge :hierarchy="hierarchy" :hub-href="hubHref" />

        <FoodRegionDrillDown v-if="isPartitionedHub" :sahodaya-id="sahodaya.id" :regions="foodRegionSummary"
                              target-path="catering" class="mb-6" />

        <template v-if="!isPartitionedHub">
        <div class="grid grid-cols-3 gap-3 mb-4 text-center text-sm">
            <div class="bg-white border rounded-xl p-3"><p class="text-xl font-bold">{{ summary.total_heads }}</p><p class="text-gray-500 text-xs">Total meals</p></div>
            <div class="bg-white border rounded-xl p-3"><p class="text-xl font-bold text-green-700">{{ summary.confirmed }}</p><p class="text-gray-500 text-xs">Confirmed</p></div>
            <div class="bg-white border rounded-xl p-3"><p class="text-xl font-bold text-amber-600">{{ summary.requested }}</p><p class="text-gray-500 text-xs">Pending</p></div>
        </div>
        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr><th class="p-3">School</th><th class="p-3">Date</th><th class="p-3">Meal</th><th class="p-3">Heads</th><th class="p-3">Status</th><th class="p-3"></th></tr>
                </thead>
                <tbody>
                    <tr v-for="o in orders" :key="o.id" class="border-t">
                        <td class="p-3">{{ schools[o.school_id] ?? o.school_id }}</td>
                        <td class="p-3">{{ formatCalendarDate(o.meal_date) }}</td>
                        <td class="p-3">{{ o.meal_type }}</td>
                        <td class="p-3">{{ o.head_count }}</td>
                        <td class="p-3">
                            <span class="text-xs px-2 py-0.5 rounded"
                                  :class="o.status === 'confirmed' ? 'bg-green-100 text-green-800' : o.status === 'cancelled' ? 'bg-gray-100 text-gray-500' : 'bg-amber-100 text-amber-800'">
                                {{ o.status }}
                            </span>
                        </td>
                        <td class="p-3 text-right space-x-2">
                            <button v-if="o.status === 'requested'" @click="setStatus(o.id, 'confirmed')" class="text-green-600 text-xs">Confirm</button>
                            <button v-if="o.status !== 'cancelled'" @click="setStatus(o.id, 'cancelled')" class="text-red-600 text-xs">Cancel</button>
                        </td>
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
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import EventHierarchyBadge from '@/Components/fest/EventHierarchyBadge.vue';
import FoodRegionDrillDown from '@/Components/sahodaya/FoodRegionDrillDown.vue';
import { formatCalendarDate } from '@/support/calendarDates.js';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, orders: Array, schools: Object, summary: Object,
    hierarchy: { type: Object, default: null },
    isPartitionedHub: { type: Boolean, default: false },
    foodRegionSummary: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
});

const hubHref = computed(() => (
    props.hierarchy?.parent_event ? `/sahodaya-admin/${props.sahodaya.id}/events/${props.hierarchy.parent_event.id}/catering` : null
));

function setStatus(id, status) {
    router.put(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/catering/${id}`, { status }, { preserveScroll: true });
}
</script>
