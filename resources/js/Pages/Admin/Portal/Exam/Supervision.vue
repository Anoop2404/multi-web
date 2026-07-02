<template>
    <PortalLayout
        role-label="Exam Portal"
        :title="exam.title"
        subtitle="Live supervision"
        accent="emerald"
        :nav-items="navItems"
    >
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-4">
            <div class="card text-center py-3"><p class="text-xs text-gray-500">Registered</p><p class="text-xl font-bold">{{ summary.total }}</p></div>
            <div class="card text-center py-3"><p class="text-xs text-gray-500">Present</p><p class="text-xl font-bold text-green-700">{{ summary.present }}</p></div>
            <div class="card text-center py-3"><p class="text-xs text-gray-500">Started</p><p class="text-xl font-bold text-indigo-700">{{ summary.started }}</p></div>
            <div class="card text-center py-3"><p class="text-xs text-gray-500">Submitted</p><p class="text-xl font-bold text-emerald-700">{{ summary.submitted }}</p></div>
            <div class="card text-center py-3"><p class="text-xs text-gray-500">Absent</p><p class="text-xl font-bold text-red-600">{{ summary.absent }}</p></div>
        </div>

        <div class="card overflow-x-auto">
            <table class="data-table min-w-[640px] text-sm">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>School</th>
                        <th>Reg. no.</th>
                        <th>Attendance</th>
                        <th>Status</th>
                        <th>Started</th>
                        <th>Submitted</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in registrations" :key="r.id">
                        <td>{{ r.student_name }}</td>
                        <td>{{ r.school_name }}</td>
                        <td>{{ r.hall_ticket_no || '—' }}</td>
                        <td class="capitalize">{{ r.attendance_status }}</td>
                        <td class="capitalize">{{ r.status }}</td>
                        <td>{{ r.started_at ? new Date(r.started_at).toLocaleTimeString() : '—' }}</td>
                        <td>{{ r.submitted_at ? new Date(r.submitted_at).toLocaleTimeString() : '—' }}</td>
                        <td>{{ r.score ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed } from 'vue';

const props = defineProps({
    sahodaya: Object,
    exam: Object,
    registrations: Array,
    summary: Object,
});

const navItems = computed(() => [
    { href: `/portal/exam/${props.sahodaya.id}`, label: 'Exams' },
    { href: `/portal/exam/${props.sahodaya.id}/exams/${props.exam.id}/supervision`, label: 'Supervision' },
]);
</script>
