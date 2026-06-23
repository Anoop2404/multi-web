<template>
    <SahodayaAdminLayout :title="`${event.title} — Attendance`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="flex flex-wrap gap-3 mb-4">
            <select v-model="itemFilter" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">All items</option>
                <option v-for="item in event.items" :key="item.id" :value="item.id">{{ item.title }}</option>
            </select>
            <button v-if="itemFilter" type="button" @click="bulkMark('present')"
                    class="px-3 py-2 bg-green-600 text-white rounded-lg text-sm">Mark all present</button>
            <button v-if="itemFilter" type="button" @click="bulkMark('absent')"
                    class="px-3 py-2 bg-red-600 text-white rounded-lg text-sm">Mark all absent</button>
        </div>

        <div class="bg-white border rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        <th class="p-3">Chest</th>
                        <th class="p-3">Participant</th>
                        <th class="p-3">Item</th>
                        <th class="p-3">Status</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in filteredParticipants" :key="p.id" class="border-t">
                        <td class="p-3 font-mono text-xs">{{ p.chest_no ?? '—' }}</td>
                        <td class="p-3 font-medium">{{ p.student?.name ?? p.teacher?.name }}</td>
                        <td class="p-3 text-gray-500">{{ p.registration?.item?.title }}</td>
                        <td class="p-3">
                            <span v-if="statusFor(p) === 'present'" class="text-green-700 text-xs font-medium">Present</span>
                            <span v-else-if="statusFor(p) === 'absent'" class="text-red-600 text-xs font-medium">Absent</span>
                            <span v-else class="text-gray-400 text-xs">Not marked</span>
                        </td>
                        <td class="p-3 text-right space-x-2">
                            <button @click="mark(p, 'present')" class="text-green-600 text-xs">Present</button>
                            <button @click="mark(p, 'absent')" class="text-red-600 text-xs">Absent</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, participants: Array, attendance: Object,
});

const itemFilter = ref('');

const filteredParticipants = computed(() => {
    if (!itemFilter.value) return props.participants;
    return props.participants.filter(p => p.registration?.item_id == itemFilter.value);
});

function attendanceKey(p) {
    return `${p.registration.item_id}-${p.id}`;
}

function statusFor(p) {
    return props.attendance?.[attendanceKey(p)]?.status ?? null;
}

function mark(participant, status) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/attendance`, {
        participant_id: participant.id,
        item_id: participant.registration.item_id,
        status,
    }, { preserveScroll: true });
}

function bulkMark(status) {
    const ids = filteredParticipants.value.map(p => p.id);
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/attendance`, {
        bulk: true,
        item_id: itemFilter.value,
        participant_ids: ids,
        status,
    }, { preserveScroll: true });
}
</script>
