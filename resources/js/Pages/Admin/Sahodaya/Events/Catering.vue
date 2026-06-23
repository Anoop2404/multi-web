<template>
    <SahodayaAdminLayout :title="`${event.title} — Catering`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="grid grid-cols-3 gap-3 mb-4 text-center text-sm">
            <div class="bg-white border rounded-xl p-3"><p class="text-xl font-bold">{{ summary.total_heads }}</p><p class="text-gray-500 text-xs">Total meals</p></div>
            <div class="bg-white border rounded-xl p-3"><p class="text-xl font-bold text-green-700">{{ summary.confirmed }}</p><p class="text-gray-500 text-xs">Confirmed</p></div>
            <div class="bg-white border rounded-xl p-3"><p class="text-xl font-bold text-amber-600">{{ summary.requested }}</p><p class="text-gray-500 text-xs">Pending</p></div>
        </div>
        <div class="bg-white border rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr><th class="p-3">School</th><th class="p-3">Date</th><th class="p-3">Meal</th><th class="p-3">Heads</th><th class="p-3">Status</th><th class="p-3"></th></tr>
                </thead>
                <tbody>
                    <tr v-for="o in orders" :key="o.id" class="border-t">
                        <td class="p-3">{{ schools[o.school_id] ?? o.school_id }}</td>
                        <td class="p-3">{{ o.meal_date?.slice?.(0,10) ?? o.meal_date }}</td>
                        <td class="p-3">{{ o.meal_type }}</td>
                        <td class="p-3">{{ o.head_count }}</td>
                        <td class="p-3">{{ o.status }}</td>
                        <td class="p-3 text-right space-x-2">
                            <button v-if="o.status === 'requested'" @click="setStatus(o.id, 'confirmed')" class="text-green-600 text-xs">Confirm</button>
                            <button v-if="o.status !== 'cancelled'" @click="setStatus(o.id, 'cancelled')" class="text-red-600 text-xs">Cancel</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, orders: Array, schools: Object, summary: Object,
});

function setStatus(id, status) {
    router.put(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/catering/${id}`, { status }, { preserveScroll: true });
}
</script>
