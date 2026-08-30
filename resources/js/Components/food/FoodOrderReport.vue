<template>
    <div>
        <div class="flex flex-wrap gap-2 mb-4">
            <Link :href="backHref" class="text-sm text-indigo-600">{{ backLabel }}</Link>
            <a :href="exportHref" class="ml-auto px-3 py-1.5 border border-gray-200 rounded-lg text-xs font-semibold text-gray-700">Export CSV</a>
        </div>

        <div v-for="day in report" :key="day.date" class="card card--flush mb-4">
            <div class="p-3 border-b bg-gray-50 flex items-center justify-between gap-3">
                <span class="font-bold text-sm">{{ formatCalendarDate(day.date) }}</span>
                <span class="text-xs text-gray-500">{{ day.day_total_quantity }} items · ₹{{ Number(day.day_total_revenue).toFixed(2) }}</span>
            </div>
            <div v-for="meal in day.meals" :key="meal.meal_type" class="border-t first:border-t-0">
                <div class="px-3 py-1.5 bg-gray-50/70 flex items-center justify-between gap-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <span class="flex items-center gap-1.5"><span aria-hidden="true">{{ mealIcon(meal.meal_type) }}</span>{{ mealTypeLabel(meal.meal_type) }}</span>
                    <span class="normal-case font-medium text-gray-400">{{ meal.subtotal_quantity }} items · ₹{{ Number(meal.subtotal_revenue).toFixed(2) }}</span>
                </div>
                <table class="data-table">
                    <thead>
                        <tr><th>Item</th><th>Quantity</th><th>Revenue</th><th>Schools ordering</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in meal.items" :key="item.item_name">
                            <td>{{ item.item_name }}</td>
                            <td class="font-semibold">{{ item.quantity }}</td>
                            <td>₹{{ Number(item.revenue).toFixed(2) }}</td>
                            <td>{{ item.schools_count }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <EmptyState v-if="!report.length" title="No orders yet"
                    description="Once schools start ordering from the food menu, this report breaks their orders down by day and meal." />
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { formatCalendarDate } from '@/support/calendarDates.js';
import { mealIcon } from '@/support/mealIcons.js';

defineProps({
    report: { type: Array, default: () => [] },
    exportHref: { type: String, required: true },
    backHref: { type: String, required: true },
    backLabel: { type: String, default: '← Food Billing' },
});

const MEAL_LABELS = { breakfast: 'Breakfast', lunch: 'Lunch', snacks: 'Snacks', tea: 'Tea', dinner: 'Dinner', other: 'Other' };
function mealTypeLabel(type) {
    return MEAL_LABELS[type] || type;
}
</script>
