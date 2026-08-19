<template>
    <AdminLayout :title="`${event.name} — Attendance`">
        <div class="max-w-5xl mx-auto space-y-4">
            <Link :href="actionUrls.workspace" class="text-sm link-brand">← {{ event.name }}</Link>
            <h1 class="text-xl font-semibold">Attendance — {{ event.name }}</h1>
            <p class="text-sm text-slate-500">Mark present/absent per item. Marking one team member marks the whole team.</p>

            <table class="w-full text-sm border" v-if="rows.length">
                <thead>
                    <tr class="text-left bg-slate-50">
                        <th class="p-2">Item</th>
                        <th class="p-2">School</th>
                        <th class="p-2">Participant</th>
                        <th class="p-2">Chest No</th>
                        <th class="p-2">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.participant.id" class="border-t">
                        <td class="p-2">{{ row.registration.item_code }}</td>
                        <td class="p-2">{{ (row.registration.school_name || '').toUpperCase() || row.registration.school_id }}</td>
                        <td class="p-2">{{ row.participant.student_name }}</td>
                        <td class="p-2 font-mono">{{ row.participant.chest_number || '—' }}</td>
                        <td class="p-2">
                            <div class="flex gap-1">
                                <button type="button"
                                        class="px-2 py-1 rounded text-xs font-semibold"
                                        :class="row.status === 'present' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-emerald-50'"
                                        :disabled="form.processing"
                                        @click="mark(row, 'present')">Present</button>
                                <button type="button"
                                        class="px-2 py-1 rounded text-xs font-semibold"
                                        :class="row.status === 'absent' ? 'bg-red-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-red-50'"
                                        :disabled="form.processing"
                                        @click="mark(row, 'absent')">Absent</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="text-sm text-slate-400">No registrations to mark attendance for yet.</p>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    event: Object,
    registrations: { type: Array, default: () => [] },
    attendance: { type: Object, default: () => ({}) },
    actionUrls: { type: Object, required: true },
});

const form = useForm({});

// Flatten registrations -> one row per participant, annotated with current attendance status.
const rows = computed(() => {
    const out = [];
    for (const registration of props.registrations) {
        for (const participant of registration.participants || []) {
            const key = `${registration.item_id}-${participant.id}`;
            out.push({
                registration,
                participant,
                status: props.attendance?.[key]?.status ?? null,
            });
        }
    }
    return out;
});

function mark(row, status) {
    form.transform(() => ({
        item_id: row.registration.item_id,
        participant_id: row.participant.id,
        status,
    })).post(props.actionUrls.store, {
        preserveScroll: true,
        onSuccess: () => { row.status = status; },
    });
}
</script>
