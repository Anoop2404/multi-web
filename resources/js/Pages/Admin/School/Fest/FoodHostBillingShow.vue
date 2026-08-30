<template>
    <SchoolAdminLayout :title="`${bill.school_name} — Food Bill`" :school="school" :show-header-title="false">
        <PageHeader :title="`${bill.school_name} — Food Bill`" eyebrow="Programs" :description="event.title" />

        <Link :href="`/school-admin/${school.id}/fest/${event.id}/food-host-billing`" class="text-sm text-indigo-600 mb-4 inline-block">← All bills</Link>

        <EventHierarchyBadge :hierarchy="hierarchy" />

        <!-- canCancel intentionally omitted (defaults false): this controller exposes no
             cancel route at all — see FestFoodHostBillingControllerTest::
             test_the_host_billing_controller_exposes_no_cancel_action_unlike_the_sahodaya_side. -->
        <FoodBillDetail :bill="bill" :order-items="orderItems" :payments="payments" :menu-items="menuItems" :base-path="base" />
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import EventHierarchyBadge from '@/Components/fest/EventHierarchyBadge.vue';
import FoodBillDetail from '@/Components/food/FoodBillDetail.vue';

const props = defineProps({
    event: Object, bill: Object,
    hierarchy: { type: Object, default: null },
    orderItems: { type: Array, default: () => [] },
    payments: { type: Array, default: () => [] },
    menuItems: { type: Array, default: () => [] },
});

const school = computed(() => usePage().props.school);
const base = computed(() => `/school-admin/${school.value.id}/fest/${props.event.id}/food-host-billing/${props.bill.id}`);
</script>
