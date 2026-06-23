<template>
    <SahodayaAdminLayout :title="`${event.title} — Schedule`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="flex flex-wrap gap-2 mb-4">
            <button type="button" @click="autoGenerate"
                    class="px-4 py-2 bg-gray-900 text-white rounded-lg text-sm">Auto-order by chest no.</button>
        </div>

        <form @submit.prevent="save" class="bg-white border rounded-xl p-4 mb-4 grid md:grid-cols-5 gap-2">
            <select v-model="form.item_id" class="field" required>
                <option value="">Item</option>
                <option v-for="item in event.items" :key="item.id" :value="item.id">{{ item.title }}</option>
            </select>
            <select v-model="form.participant_id" class="field">
                <option value="">All item (block)</option>
                <option v-for="p in participantsForItem" :key="p.id" :value="p.id">
                    #{{ p.chest_no }} {{ p.student?.name ?? p.teacher?.name }}
                </option>
            </select>
            <input v-model="form.scheduled_at" type="datetime-local" class="field">
            <input v-model="form.stage" class="field" placeholder="Stage">
            <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Save slot</button>
        </form>

        <div class="bg-white border rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        <th class="p-3">Order</th>
                        <th class="p-3">Time</th>
                        <th class="p-3">Stage</th>
                        <th class="p-3">Item</th>
                        <th class="p-3">Participant</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in schedules" :key="row.id" class="border-t">
                        <td class="p-3">{{ row.sort_order }}</td>
                        <td class="p-3">{{ formatTime(row.scheduled_at) }}</td>
                        <td class="p-3">{{ row.stage || '—' }}</td>
                        <td class="p-3">{{ row.item?.title }}</td>
                        <td class="p-3">{{ participantLabel(row) }}</td>
                    </tr>
                    <tr v-if="!schedules.length"><td colspan="5" class="p-6 text-center text-gray-400">No schedule yet</td></tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, participants: Array, schedules: Array,
});

const form = useForm({ item_id: '', participant_id: '', scheduled_at: '', stage: '' });

const participantsForItem = computed(() =>
    props.participants.filter(p => p.registration?.item_id == form.item_id)
);

function formatTime(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString();
}

function participantLabel(row) {
    if (!row.participant_id) return '—';
    const p = row.participant;
    return `#${p?.chest_no ?? '?'} ${p?.student?.name ?? p?.teacher?.name ?? ''}`;
}

function save() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/schedule`, {
        preserveScroll: true,
        onSuccess: () => form.reset('participant_id', 'scheduled_at', 'stage'),
    });
}

function autoGenerate() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/schedule/auto`, {}, { preserveScroll: true });
}
</script>

<style scoped>
@reference "../../../../../css/app.css";
.field { @apply border border-gray-200 rounded-lg px-3 py-2 text-sm; }
</style>
