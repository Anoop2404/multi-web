<template>
    <SchoolAdminLayout :title="`${event.title} — Food Order Report`" :school="school" :show-header-title="false">
        <PageHeader :title="`${event.title} — Food Order Report`" eyebrow="Programs"
                    description="Day-wise breakdown of ordered items across every school's bill payable to you — quantities and revenue, for kitchen planning and reconciliation." />

        <EventHierarchyBadge :hierarchy="hierarchy" />

        <div class="flex flex-wrap gap-2 mb-4">
            <Link :href="`/school-admin/${school.id}/fest/${event.id}/food-host-billing`" class="text-sm text-indigo-600">← Food Billing</Link>
            <a :href="`/school-admin/${school.id}/fest/${event.id}/food-host-billing/report/export`"
               class="ml-auto px-3 py-1.5 border border-gray-200 rounded-lg text-xs font-semibold text-gray-700">Export CSV</a>
        </div>

        <div v-for="day in report" :key="day.date" class="card card--flush mb-4">
            <div class="p-3 border-b bg-gray-50 flex items-center justify-between gap-3">
                <span class="font-bold text-sm">{{ formatCalendarDate(day.date) }}</span>
                <span class="text-xs text-gray-500">{{ day.day_total_quantity }} items · ₹{{ Number(day.day_total_revenue).toFixed(2) }}</span>
            </div>
            <div v-for="meal in day.meals" :key="meal.meal_type" class="border-t first:border-t-0">
                <div class="px-3 py-1.5 bg-gray-50/70 flex items-center justify-between gap-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <span>{{ mealTypeLabel(meal.meal_type) }}</span>
                    <span class="normal-case font-medium text-gray-400">{{ meal.subtotal_quantity }} items · ₹{{ Number(meal.subtotal_revenue).toFixed(2) }}</span>
                </div>
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-gray-400">
                        <tr>
                            <th class="p-3">Item</th>
                            <th class="p-3">Quantity</th>
                            <th class="p-3">Revenue</th>
                            <th class="p-3">Schools ordering</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in meal.items" :key="item.item_name" class="border-t">
                            <td class="p-3">{{ item.item_name }}</td>
                            <td class="p-3 font-semibold">{{ item.quantity }}</td>
                            <td class="p-3">₹{{ Number(item.revenue).toFixed(2) }}</td>
                            <td class="p-3">{{ item.schools_count }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <EmptyState v-if="!report.length" title="No orders yet"
                    description="Once schools start ordering from the food menu, this report breaks their orders down by day and meal." />
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import EventHierarchyBadge from '@/Components/fest/EventHierarchyBadge.vue';
import { formatCalendarDate } from '@/support/calendarDates.js';

const props = defineProps({
    event: Object,
    hierarchy: { type: Object, default: null },
    report: { type: Array, default: () => [] },
});

const school = computed(() => usePage().props.school);

const MEAL_LABELS = { breakfast: 'Breakfast', lunch: 'Lunch', snacks: 'Snacks', tea: 'Tea', dinner: 'Dinner', other: 'Other' };
function mealTypeLabel(type) {
    return MEAL_LABELS[type] || type;
}
</script>
