<template>
    <PortalLayout
        role-label="Kitchen board"
        :title="event.title"
        :subtitle="sahodaya.name"
        accent="emerald"
        :nav-items="navItems"
    >
        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">Date</th>
                        <th class="p-3">Meal</th>
                        <th class="p-3">School</th>
                        <th class="p-3">Heads</th>
                        <th class="p-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="o in orders" :key="o.id" class="border-t">
                        <td class="p-3">{{ o.meal_date }}</td>
                        <td class="p-3 capitalize">{{ o.meal_type }}</td>
                        <td class="p-3 text-xs">{{ schools[o.school_id] || o.school_id }}</td>
                        <td class="p-3">{{ o.head_count }}</td>
                        <td class="p-3">
                            <select v-model="forms[o.id]" class="field text-xs" @change="save(o)">
                                <option value="requested">Requested</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </td>
                    </tr>
                    <tr v-if="!orders.length">
                        <td colspan="5" class="p-6 text-center text-gray-400">No meal orders.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({ sahodaya: Object, event: Object, orders: Array, schools: Object });

const forms = reactive({});
for (const o of props.orders) {
    forms[o.id] = o.status;
}

function save(order) {
    router.post(`/portal/fest-ops/${props.sahodaya.id}/events/${props.event.id}/kitchen/${order.id}`, {
        status: forms[order.id],
    }, { preserveScroll: true });
}

const navItems = computed(() => [
    { href: `/portal/fest-ops/${props.sahodaya.id}`, label: 'Dashboard' },
    { href: `/portal/fest-ops/${props.sahodaya.id}/events/${props.event.id}`, label: 'Event' },
    { href: `/portal/fest-ops/${props.sahodaya.id}/events/${props.event.id}/kitchen`, label: 'Kitchen' },
]);
</script>

