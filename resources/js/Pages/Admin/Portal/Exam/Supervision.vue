<template>
    <PortalLayout
        role-label="Exam Portal"
        :title="exam.title"
        subtitle="Live supervision"
        accent="emerald"
        :nav-items="navItems"
    >
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 mb-4">
            <div class="card text-center py-3"><p class="text-xs text-gray-500">Registered</p><p class="text-xl font-bold">{{ summary.total }}</p></div>
            <div class="card text-center py-3"><p class="text-xs text-gray-500">Present</p><p class="text-xl font-bold text-green-700">{{ summary.present }}</p></div>
            <div class="card text-center py-3"><p class="text-xs text-gray-500">Started</p><p class="text-xl font-bold text-indigo-700">{{ summary.started }}</p></div>
            <div class="card text-center py-3"><p class="text-xs text-gray-500">Submitted</p><p class="text-xl font-bold text-emerald-700">{{ summary.submitted }}</p></div>
            <div class="card text-center py-3"><p class="text-xs text-gray-500">Absent</p><p class="text-xl font-bold text-red-600">{{ summary.absent }}</p></div>
            <div class="card text-center py-3"><p class="text-xs text-gray-500">Malpractice</p><p class="text-xl font-bold text-amber-600">{{ summary.malpractice }}</p></div>
            <div class="card text-center py-3"><p class="text-xs text-gray-500">Withheld</p><p class="text-xl font-bold text-amber-600">{{ summary.withheld }}</p></div>
            <div class="card text-center py-3"><p class="text-xs text-gray-500">Proctor signals</p><p class="text-xl font-bold text-slate-800">{{ summary.proctor_signals ?? 0 }}</p></div>
        </div>

        <p class="text-xs text-slate-600 mb-3">
            Proctor signals (tab hidden, window blur, fullscreen exit) are for review only — not automatic malpractice.
            Mark Malpractice on
            <a v-if="attendanceUrl" :href="attendanceUrl" class="link-brand font-semibold">Attendance</a>
            <span v-else>Attendance</span>
            only after confirmation.
        </p>

        <div class="card overflow-x-auto">
            <table class="data-table min-w-[720px] text-sm">
                <thead>
                    <tr>
                        <th>Sl No</th>
                        <th>Student</th>
                        <th>School</th>
                        <th>Reg. no.</th>
                        <th>Attendance</th>
                        <th>Status</th>
                        <th>Started</th>
                        <th>Submitted</th>
                        <th>Score</th>
                        <th>Proctor</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(r, idx) in registrations.data" :key="r.id">
                        <td>{{ (registrations.from ?? 0) + idx }}</td>
                        <td>{{ r.student_name }}</td>
                        <td>{{ (r.school_name || '').toUpperCase() }}</td>
                        <td>{{ r.hall_ticket_no || '—' }}</td>
                        <td class="capitalize">{{ r.attendance_status }}</td>
                        <td class="capitalize">{{ r.status }}</td>
                        <td>{{ r.started_at ? new Date(r.started_at).toLocaleTimeString() : '—' }}</td>
                        <td>{{ r.submitted_at ? new Date(r.submitted_at).toLocaleTimeString() : '—' }}</td>
                        <td>{{ r.score ?? '—' }}</td>
                        <td class="text-xs">
                            <span
                                v-if="r.proctor_events_count > 0"
                                class="font-semibold text-amber-800 cursor-help"
                                :title="proctorTooltip(r.proctor_events)"
                            >
                                {{ r.proctor_events_count }} signal{{ r.proctor_events_count === 1 ? '' : 's' }}
                            </span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <PaginationLinks :links="registrations.links" :meta="{ from: registrations.from, to: registrations.to, total: registrations.total }" />
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import PaginationLinks from '@/Components/ui/PaginationLinks.vue';
import { examPortalNavItems } from '@/support/examPortalNav.js';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    sahodaya: Object,
    exam: Object,
    registrations: Object,
    summary: Object,
    attendanceUrl: { type: String, default: null },
});

const PROCTOR_LABELS = {
    tab_hidden: 'Tab hidden',
    window_blur: 'Window blur',
    fullscreen_exit: 'Fullscreen exit',
    offline: 'Offline',
    device_switch: 'Device switch',
};

const currentRole = computed(() => usePage().props.auth?.user?.roles?.includes('exam_staff') ? 'exam_staff' : null);
const navItems = computed(() => examPortalNavItems(props.sahodaya.id, props.exam.id, { role: currentRole.value }));

function proctorTooltip(events) {
    if (!events?.length) return '';
    const counts = {};
    for (const e of events) {
        const key = e.type || 'unknown';
        counts[key] = (counts[key] || 0) + 1;
    }
    const breakdown = Object.entries(counts)
        .map(([type, n]) => `${PROCTOR_LABELS[type] || type}: ${n}`)
        .join(' · ');
    const recent = events.slice(0, 5).map((e) => {
        const when = e.occurred_at ? new Date(e.occurred_at).toLocaleTimeString() : '';
        return `${PROCTOR_LABELS[e.type] || e.type}${when ? ` — ${when}` : ''}`;
    }).join('\n');
    return `${breakdown}\n${recent}`;
}
</script>
