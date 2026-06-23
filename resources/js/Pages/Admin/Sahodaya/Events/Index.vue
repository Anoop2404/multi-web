<template>
    <SahodayaAdminLayout title="Events" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="space-y-6">
            <form @submit.prevent="createEvent" class="bg-white border rounded-xl p-4 space-y-3">
                <h3 class="font-semibold text-gray-900">Create Event</h3>
                <div class="grid sm:grid-cols-2 gap-3">
                    <input v-model="form.title" class="field" placeholder="Event title" required>
                    <select v-model="form.event_type" class="field">
                        <option v-for="(label, key) in eventTypes" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
                <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium">Create</button>
            </form>

            <div class="bg-white border rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left">
                        <tr>
                            <th class="p-3">Title</th>
                            <th class="p-3">Type</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Items</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="event in events" :key="event.id" class="border-t">
                            <td class="p-3 font-medium">{{ event.title }}</td>
                            <td class="p-3">{{ eventTypes[event.event_type] ?? event.event_type }}</td>
                            <td class="p-3"><span class="px-2 py-0.5 rounded bg-gray-100 text-xs">{{ event.status }}</span></td>
                            <td class="p-3">{{ event.items_count }}</td>
                            <td class="p-3 text-right">
                                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}`" class="text-indigo-600 font-medium">Manage →</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    events: Array,
    eventTypes: Object,
});

const form = useForm({ title: '', event_type: 'kalolsavam' });

function createEvent() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/events`, { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>

<style scoped>
@reference "../../../../../css/app.css";
.field { @apply w-full border border-gray-200 rounded-lg px-3 py-2 text-sm; }
</style>
