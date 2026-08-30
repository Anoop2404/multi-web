<template>
    <SchoolAdminLayout :title="`${event.title} — Food Order Report`" :school="school" :show-header-title="false">
        <PageHeader :title="`${event.title} — Food Order Report`" eyebrow="Programs"
                    description="Day-wise breakdown of ordered items across every school's bill payable to you — quantities and revenue, for kitchen planning and reconciliation." />

        <EventHierarchyBadge :hierarchy="hierarchy" />

        <FoodOrderReport :report="report"
                          :export-href="`/school-admin/${school.id}/fest/${event.id}/food-host-billing/report/export`"
                          :back-href="`/school-admin/${school.id}/fest/${event.id}/food-host-billing`" />
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import EventHierarchyBadge from '@/Components/fest/EventHierarchyBadge.vue';
import FoodOrderReport from '@/Components/food/FoodOrderReport.vue';

const props = defineProps({
    event: Object,
    hierarchy: { type: Object, default: null },
    report: { type: Array, default: () => [] },
});

const school = computed(() => usePage().props.school);
</script>
