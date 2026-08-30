<template>
    <SchoolAdminLayout :title="`${event.title} — Food Billing (Host)`" :school="school" :show-header-title="false">
        <PageHeader :title="`${event.title} — Food Billing`" eyebrow="Programs"
                    description="Your school is the designated payee for this event's food orders — manage every participating school's bill here." />

        <EventHierarchyBadge :hierarchy="hierarchy" />

        <div class="flex flex-wrap gap-2 items-center mb-3">
            <Link :href="`/school-admin/${school.id}/fest/${event.id}/food-host-billing/report`" class="text-sm text-indigo-600 ml-auto">Day-wise report →</Link>
            <a :href="`/school-admin/${school.id}/fest/${event.id}/food-host-billing/export`"
               class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs font-semibold text-gray-700">Export CSV</a>
        </div>

        <!-- No showOpenForm here: unlike the Sahodaya side, host-billing bills only ever
             appear once a school orders — there is no "open a bill in advance" flow. -->
        <FoodBillingList :bills="bills" :summary="summary" :base-path="`/school-admin/${school.id}/fest/${event.id}/food-host-billing`" />
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import EventHierarchyBadge from '@/Components/fest/EventHierarchyBadge.vue';
import FoodBillingList from '@/Components/food/FoodBillingList.vue';

const props = defineProps({
    event: Object,
    hierarchy: { type: Object, default: null },
    bills: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({ total: 0, paid: 0, balance: 0 }) },
});

const school = computed(() => usePage().props.school);
</script>
