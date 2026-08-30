<template>
    <SahodayaEventsLayout :title="`${event.title} — Food Order Report`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Food Order Report`" eyebrow="Operations"
                    description="Day-wise breakdown of ordered items across every school's bill — quantities and revenue, for kitchen planning and reconciliation." />

        <EventHierarchyBadge :hierarchy="hierarchy" :hub-href="hubHref" />

        <FoodRegionDrillDown v-if="isPartitionedHub" :sahodaya-id="sahodaya.id" :regions="foodRegionSummary"
                              target-path="food-billing/report" class="mb-6" />

        <FoodOrderReport v-if="!isPartitionedHub" :report="report"
                          :export-href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/food-billing/report/export`"
                          :back-href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/food-billing`" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventHierarchyBadge from '@/Components/fest/EventHierarchyBadge.vue';
import FoodRegionDrillDown from '@/Components/sahodaya/FoodRegionDrillDown.vue';
import FoodOrderReport from '@/Components/food/FoodOrderReport.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object,
    hierarchy: { type: Object, default: null },
    isPartitionedHub: { type: Boolean, default: false },
    foodRegionSummary: { type: Array, default: () => [] },
    report: { type: Array, default: () => [] },
});

const hubHref = computed(() => (
    props.hierarchy?.parent_event
        ? `/sahodaya-admin/${props.sahodaya.id}/events/${props.hierarchy.parent_event.id}/food-billing/report`
        : null
));
</script>
