<template>
    <PortalLayout
        role-label="Attendance"
        :title="event.title"
        :subtitle="sahodaya.name"
        accent="sky"
        :nav-items="navItems"
    >
        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">Item</th>
                        <th class="p-3">Participant</th>
                        <th class="p-3">Chest</th>
                        <th class="p-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in participants" :key="p.id" class="border-t">
                        <td class="p-3">{{ p.registration?.item?.title }}</td>
                        <td class="p-3">{{ p.student?.name ?? p.teacher?.name }}</td>
                        <td class="p-3 font-mono text-xs">{{ p.chest_no ?? p.level_registration_number ?? '—' }}</td>
                        <td class="p-3">
                            <select :value="statusFor(p)" @change="save(p, $event.target.value)" class="border rounded px-2 py-1 text-xs">
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                            </select>
                        </td>
                    </tr>
                    <tr v-if="!participants?.length"><td colspan="4" class="p-6 text-center text-gray-400">No participants</td></tr>
                </tbody>
            </table>
        </div>
    </PortalLayout>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { festOpsEventNav } from '@/support/festOpsPortalNav.js';

const props = defineProps({ sahodaya: Object, event: Object, participants: Array, attendance: Object, duties: Array });

const base = computed(() => `/portal/fest-ops/${props.sahodaya.id}/events/${props.event.id}`);

const navItems = computed(() => festOpsEventNav(props.sahodaya.id, props.event.id, props.duties));

function keyFor(p) {
    return `${p.registration?.item_id}-${p.id}`;
}

function statusFor(p) {
    return props.attendance?.[keyFor(p)]?.status ?? 'present';
}

function save(participant, status) {
    router.post(`${base.value}/attendance`, {
        item_id: participant.registration?.item_id,
        participant_id: participant.id,
        status,
    }, { preserveScroll: true });
}
</script>
