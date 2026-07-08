<template>
    <SahodayaAdminLayout :title="`${program.title} — Attendance`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="program.title" eyebrow="Training attendance"
                    :description="`${program.sessions?.length ?? 0} session(s) · ${confirmedRegistrations.length} confirmed`">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}`" class="btn-secondary text-sm">
                    Back to program
                </Link>
                <a :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/attendance/export`"
                   class="btn-primary text-sm">
                    Download report
                </a>
            </template>
        </PageHeader>

        <div v-for="session in program.sessions" :key="session.id" class="card mb-4">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <div>
                    <h3 class="font-semibold text-sm">{{ session.title }}</h3>
                    <p class="text-xs text-gray-500">
                        {{ session.scheduled_at ? formatDate(session.scheduled_at) : 'No date' }}
                        <span v-if="session.venue"> · {{ session.venue }}</span>
                        <span v-if="session.duration_minutes"> · {{ session.duration_minutes }} min</span>
                    </p>
                </div>
                <button type="button" @click="markAllPresent(session)"
                        class="text-xs text-indigo-600 font-semibold border border-indigo-200 px-2 py-1 rounded">
                    Mark all present
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="data-table min-w-[640px] text-sm">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>School</th>
                            <th>Attendance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in confirmedRegistrations" :key="r.id">
                            <td>{{ r.teacher?.name || `#${r.id}` }}</td>
                            <td class="text-gray-500">{{ r.school?.name || r.teacher?.school_name || '—' }}</td>
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
                        <tr v-if="!confirmedRegistrations.length">
                            <td colspan="3" class="text-center text-gray-400 py-4">No confirmed registrations.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p v-if="!program.sessions?.length" class="card text-sm text-gray-400 py-6 text-center">
            Add training days/sessions on the program page first.
        </p>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    program: Object,
    attendanceMap: Object,
});

const localAttendance = reactive({});

for (const [sessionId, regMap] of Object.entries(props.attendanceMap ?? {})) {
    localAttendance[sessionId] = {};
    for (const [regId, rec] of Object.entries(regMap)) {
        localAttendance[sessionId][regId] = rec.status;
    }
}

const confirmedRegistrations = computed(() =>
    (props.program.registrations ?? []).filter(r => r.status === 'confirmed')
);

function attendanceStatus(sessionId, regId) {
    return localAttendance[sessionId]?.[regId] ?? null;
}

function formatDate(value) {
    return new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

function markAllPresent(session) {
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/sessions/${session.id}/attendance`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                for (const r of confirmedRegistrations.value) {
                    if (!localAttendance[session.id]) localAttendance[session.id] = {};
                    localAttendance[session.id][r.id] = 'present';
                }
            },
        }
    );
}

function setAttendance(session, registration, status) {
    if (!localAttendance[session.id]) localAttendance[session.id] = {};
    localAttendance[session.id][registration.id] = status;

    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/sessions/${session.id}/attendance/${registration.id}`,
        { status },
        { preserveScroll: true }
    );
}
</script>
