<template>
    <SahodayaEventsLayout :title="`${event.title} — Judges`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Judges`" eyebrow="Registration"
                    description="Assign judges to event items." />
        <form @submit.prevent="assign" class="card mb-4 flex flex-wrap gap-2">
            <select v-model="form.item_id" class="field" required>
                <option value="">Select item</option>
                <option v-for="item in event.items" :key="item.id" :value="item.id">{{ item.title }}</option>
            </select>
            <select v-model="form.user_id" class="field" required>
                <option value="">Select judge</option>
                <option v-for="j in judges" :key="j.id" :value="j.id">{{ j.name }} ({{ j.email }})</option>
            </select>
            <button class="btn-primary">Assign</button>
        </form>

        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr><th class="p-3">Item</th><th class="p-3">Judge</th><th class="p-3"></th></tr>
                </thead>
                <tbody>
                    <tr v-for="a in assignments" :key="a.id" class="border-t">
                        <td class="p-3">{{ a.item?.title }}</td>
                        <td class="p-3">{{ a.user?.name }} <span class="text-gray-400 text-xs">{{ a.user?.email }}</span></td>
                        <td class="p-3 text-right">
                            <button @click="remove(a.id)" class="text-red-600 text-xs">Remove</button>
                        </td>
                    </tr>
                    <tr v-if="!assignments.length"><td colspan="3" class="p-6 text-center text-gray-400">No judges assigned</td></tr>
                </tbody>
            </table>
        </div>

        <p class="text-xs text-gray-500 mt-4">Judges log in at <code>/portal/judge/{{ sahodaya.id }}</code></p>
            <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { router, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, assignments: Array, judges: Array,
    activityLogs: { type: Array, default: () => [] },
});

const form = useForm({ item_id: '', user_id: '' });

function assign() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/judges`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function remove(id) {
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/judges/${id}`, { preserveScroll: true });
}
</script>

