<template>
    <AdminLayout :title="event.name">
        <div class="max-w-5xl mx-auto space-y-4">
            <h1 class="text-xl font-semibold">{{ event.name }}</h1>
            <p class="text-sm text-slate-500">Program {{ event.state_program_id }} · {{ event.status }}</p>
            <h2 class="section-title">State registrations</h2>
            <table class="w-full text-sm border" v-if="registrations?.length">
                <thead>
                    <tr class="text-left bg-slate-50">
                        <th class="p-2">Sl No</th>
                        <th class="p-2">Item</th>
                        <th class="p-2">Participant</th>
                        <th class="p-2">School</th>
                        <th class="p-2">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(registration, idx) in registrations" :key="registration.id" class="border-t">
                        <td class="p-2">{{ idx + 1 }}</td>
                        <td class="p-2">{{ registration.item_code }}</td>
                        <td class="p-2">{{ registration.participants?.[0]?.student_name || 'Participant' }}</td>
                        <td class="p-2">{{ (registration.school_name || '').toUpperCase() || registration.school_id }}</td>
                        <td class="p-2">{{ registration.status }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="text-sm text-slate-400">No materialized state registrations yet. Approve a qualifier intake to populate this event.</p>

            <h2 class="section-title">Approved qualifiers</h2>
            <ul class="text-sm space-y-1">
                <li v-for="q in approvedQualifiers" :key="q.id">{{ q.item_code }} — {{ q.student_name }} ({{ q.school_id }})</li>
            </ul>
        </div>
    </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({ event: Object, approvedQualifiers: Array, registrations: Array });
</script>
