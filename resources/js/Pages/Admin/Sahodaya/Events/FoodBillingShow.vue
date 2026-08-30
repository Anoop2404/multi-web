<template>
    <SahodayaEventsLayout :title="`${bill.school_name} — Food Bill`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${bill.school_name} — Food Bill`" eyebrow="Operations"
                    :description="`${event.title}. ${payeeNote}`" />

        <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/food-billing`" class="text-sm text-indigo-600 mb-4 inline-block">← All bills</Link>

        <FoodBillDetail :bill="bill" :order-items="orderItems" :payments="payments" :menu-items="menuItems"
                         :base-path="base" :can-cancel="true" />

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import FoodBillDetail from '@/Components/food/FoodBillDetail.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, bill: Object,
    orderItems: { type: Array, default: () => [] },
    payments: { type: Array, default: () => [] },
    menuItems: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/food-billing/${props.bill.id}`;

const payeeNote = computed(() => (
    props.bill.payee_type === 'host_school'
        ? `Payable to ${props.bill.host_school_name || 'the host school'}.`
        : 'Payable to the Sahodaya.'
));
</script>
