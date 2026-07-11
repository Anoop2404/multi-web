<template>
    <SchoolAdminLayout :title="`${program.title} — Attendance`" :school="school" :show-header-title="false">
        <PageHeader :title="program.title" eyebrow="Training attendance"
                    :description="`${program.sessions?.length ?? 0} session(s) · ${registrations.length} eligible teacher(s)`">
            <template #actions>
                <Link :href="`/school-admin/${school.id}/training`" class="btn-secondary text-sm">Back to programs</Link>
            </template>
        </PageHeader>

        <p v-if="!registrations.length" class="card text-sm text-gray-500 py-6 text-center mb-4">
            No teachers from your school are ready for attendance yet.
            Register teachers first. QR / venue programmes allow attendance before payment is recorded.
        </p>

        <div v-for="session in program.sessions" :key="session.id" class="card mb-4">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <div>
                    <h3 class="font-semibold text-sm">{{ session.title }}</h3>
                    <p class="text-xs text-gray-500">
                        {{ session.scheduled_at ? formatDate(session.scheduled_at) : 'No date' }}
                        <span v-if="session.venue"> · {{ session.venue }}</span>
                    </p>
                </div>
                <button v-if="registrations.length" type="button" @click="markAllPresent(session)"
                        class="text-xs text-indigo-600 font-semibold border border-indigo-200 px-2 py-1 rounded">
                    Mark all present
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="data-table min-w-[560px] text-sm">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>Verification</th>
                            <th>Attendance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in registrations" :key="r.id">
                            <td>
                                <div>{{ r.teacher?.name || `#${r.id}` }}</div>
                                <div class="text-xs text-gray-400">{{ r.teacher?.designation || '' }}</div>
                            </td>
                            <td>
                                <span v-if="r.teacher?.is_verified" class="text-xs text-green-700 font-semibold">Verified</span>
                                <span v-else class="text-xs text-amber-700 font-semibold">Unverified</span>
                            </td>
                            <td>
                                <div class="flex gap-1">
                                    <button type="button" @click="setAttendance(session, r, 'present')"
                                            class="px-2 py-0.5 rounded text-xs font-semibold"
                                            :class="attendanceStatus(session.id, r.id) === 'present'
                                                ? 'btn-primary !min-h-0 !px-2 !py-0.5 text-xs'
                                                : 'bg-gray-100 text-gray-600 hover:bg-green-100'">
                                        Present
                                    </button>
                                    <button type="button" @click="setAttendance(session, r, 'absent')"
                                            class="px-2 py-0.5 rounded text-xs font-semibold"
                                            :class="attendanceStatus(session.id, r.id) === 'absent'
                                                ? 'bg-red-500 text-white'
                                                : 'bg-gray-100 text-gray-600 hover:bg-red-100'">
                                        Absent
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p v-if="!program.sessions?.length" class="card text-sm text-gray-400 py-6 text-center">
            Sahodaya has not added training sessions yet.
        </p>
    </SchoolAdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';

const props = defineProps({
    school: Object,
    program: Object,
    registrations: Array,
    attendanceMap: Object,
});

const localAttendance = reactive({});
for (const [sessionId, regMap] of Object.entries(props.attendanceMap ?? {})) {
    localAttendance[sessionId] = {};
    for (const [regId, rec] of Object.entries(regMap)) {
        localAttendance[sessionId][regId] = rec.status;
    }
}

function formatDate(value) {
    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
}

function attendanceStatus(sessionId, regId) {
    return localAttendance[sessionId]?.[regId] ?? null;
}

function setAttendance(session, registration, status) {
    if (!localAttendance[session.id]) localAttendance[session.id] = {};
    localAttendance[session.id][registration.id] = status;

    router.post(
        `/school-admin/${props.school.id}/training/${props.program.id}/sessions/${session.id}/attendance/${registration.id}`,
        { status },
        { preserveScroll: true }
    );
}

function markAllPresent(session) {
    router.post(
        `/school-admin/${props.school.id}/training/${props.program.id}/sessions/${session.id}/attendance`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                for (const r of props.registrations) {
                    if (!localAttendance[session.id]) localAttendance[session.id] = {};
                    localAttendance[session.id][r.id] = 'present';
                }
            },
        }
    );
}
</script>
