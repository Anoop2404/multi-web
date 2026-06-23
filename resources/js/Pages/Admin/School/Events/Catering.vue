<template>
    <SchoolAdminLayout :title="`${event.title} — Meals`" :school="school">
        <form @submit.prevent="submit" class="bg-white border rounded-xl p-4 mb-4 grid sm:grid-cols-2 gap-2">
            <input v-model="form.meal_date" type="date" class="field" required>
            <select v-model="form.meal_type" class="field" required>
                <option value="breakfast">Breakfast</option>
                <option value="lunch">Lunch</option>
                <option value="dinner">Dinner</option>
                <option value="snacks">Snacks</option>
            </select>
            <input v-model.number="form.head_count" type="number" min="1" class="field" placeholder="Head count" required>
            <input v-model="form.notes" class="field" placeholder="Notes (optional)">
            <button class="sm:col-span-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Request meals</button>
        </form>
        <ul class="bg-white border rounded-xl divide-y text-sm">
            <li v-for="o in orders" :key="o.id" class="p-3 flex justify-between">
                <span>{{ o.meal_date?.slice?.(0,10) ?? o.meal_date }} · {{ o.meal_type }} · {{ o.head_count }} pax</span>
                <span class="text-gray-500">{{ o.status }}</span>
            </li>
            <li v-if="!orders.length" class="p-4 text-gray-400">No meal requests yet</li>
        </ul>
    </SchoolAdminLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';

const props = defineProps({ school: Object, event: Object, orders: Array });
const form = useForm({ meal_date: '', meal_type: 'lunch', head_count: 10, notes: '' });

function submit() {
    form.post(`/school-admin/${props.school.id}/fest/${props.event.id}/catering`, {
        preserveScroll: true, onSuccess: () => form.reset('notes'),
    });
}
</script>

<style scoped>
@reference "../../../../../css/app.css";
.field { @apply border border-gray-200 rounded-lg px-3 py-2 text-sm; }
</style>
