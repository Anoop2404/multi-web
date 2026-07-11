<template>
    <SahodayaAdminLayout :title="`${program.title} — Attendance`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="program.title" eyebrow="Mark attendance"
                    :description="`${program.sessions?.length ?? 0} session(s) · ${confirmedRegistrations.length} eligible`">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}`" class="btn-secondary text-sm">
                    ← Program
                </Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/attendance/sheet`"
                      class="btn-secondary text-sm">
                    Attendance sheet
                </Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/attendance/report`"
                      class="btn-primary text-sm">
                    Attendance report
                </Link>
            </template>
        </PageHeader>

        <div v-if="pendingCorrections.length" class="card mb-4 border border-amber-200 bg-amber-50/60">
            <h3 class="font-semibold text-sm text-amber-900 mb-2">Pending corrections ({{ pendingCorrections.length }})</h3>
            <ul class="space-y-2 text-sm">
                <li v-for="item in pendingCorrections" :key="`${item.sessionId}-${item.regId}`"
                    class="flex flex-wrap items-center justify-between gap-2">
                    <span>
                        <strong>{{ item.teacher }}</strong>
                        · {{ item.sessionTitle }}
                        · <span class="capitalize">{{ item.status.replace('_', ' ') }}</span>
                        <span v-if="item.reason" class="text-xs text-amber-800"> — {{ item.reason }}</span>
                    </span>
                    <span class="flex gap-2">
                        <button type="button" class="text-xs font-semibold text-green-700"
                                @click="reviewCorrection(item, 'approved')">Approve</button>
                        <button type="button" class="text-xs font-semibold text-red-600"
                                @click="reviewCorrection(item, 'rejected')">Reject</button>
                    </span>
                </li>
            </ul>
        </div>

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
                <table class="data-table min-w-[720px] text-sm">
                    <thead>
                        <tr>
                            <th>Teacher</th>
                            <th>School</th>
                            <th>Attendance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in confirmedRegistrations" :key="r.id">
                            <td>
                                <div>{{ r.teacher?.name || `#${r.id}` }}</div>
                                <span v-if="r.teacher && !r.teacher.verified_at"
                                      class="text-[10px] uppercase tracking-wide text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded">
                                    Unverified
                                </span>
                            </td>
                            <td class="text-gray-500">{{ r.school?.name || r.teacher?.school_name || '—' }}</td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    <button v-for="opt in statusOptions" :key="opt.value" type="button"
                                            @click="setAttendance(session, r, opt.value)"
                                            class="px-2 py-0.5 rounded text-xs font-semibold"
                                            :class="statusButtonClass(session.id, r.id, opt.value)">
                                        {{ opt.label }}
                                    </button>
                                </div>
                                <p v-if="attendanceMeta(session.id, r.id)?.approval_status === 'pending'"
                                   class="text-[10px] text-amber-700 mt-1">Correction pending approval</p>
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
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    program: Object,
    attendanceMap: Object,
});

const statusOptions = [
    { value: 'present', label: 'Present' },
    { value: 'late', label: 'Late' },
    { value: 'absent', label: 'Absent' },
    { value: 'with_permission', label: 'With permission' },
];

const localAttendance = reactive({});

for (const [sessionId, regMap] of Object.entries(props.attendanceMap ?? {})) {
    localAttendance[sessionId] = {};
    for (const [regId, rec] of Object.entries(regMap)) {
        localAttendance[sessionId][regId] = {
            status: rec.status,
            approval_status: rec.approval_status ?? null,
            correction_reason: rec.correction_reason ?? null,
        };
    }
}

const confirmedRegistrations = computed(() =>
    (props.program.registrations ?? []).filter(r =>
        ['confirmed', 'completed'].includes(r.status)
        || r.registration_source === 'qr'
        || (r.status === 'registered' && (props.program.fee_type === 'none' || !props.program.fee_amount)),
    )
);

const pendingCorrections = computed(() => {
    const rows = [];
    for (const [sessionId, regMap] of Object.entries(localAttendance)) {
        const session = (props.program.sessions ?? []).find(s => String(s.id) === String(sessionId));
        for (const [regId, meta] of Object.entries(regMap)) {
            if (meta?.approval_status !== 'pending') continue;
            const reg = confirmedRegistrations.value.find(r => String(r.id) === String(regId));
            rows.push({
                sessionId,
                regId,
                teacher: reg?.teacher?.name || `#${regId}`,
                sessionTitle: session?.title || `Session ${sessionId}`,
                status: meta.status,
                reason: meta.correction_reason,
            });
        }
    }
    return rows;
});

function attendanceStatus(sessionId, regId) {
    return localAttendance[sessionId]?.[regId]?.status ?? null;
}

function attendanceMeta(sessionId, regId) {
    return localAttendance[sessionId]?.[regId] ?? null;
}

function statusButtonClass(sessionId, regId, value) {
    const current = attendanceStatus(sessionId, regId);
    if (current === value) {
        if (value === 'absent') return 'bg-red-500 text-white';
        if (value === 'late') return 'bg-amber-500 text-white';
        if (value === 'with_permission') return 'bg-slate-600 text-white';
        return 'btn-primary !min-h-0 !px-2 !py-0.5 text-xs';
    }
    return 'bg-gray-100 text-gray-600 hover:bg-gray-200';
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
                    localAttendance[session.id][r.id] = { status: 'present', approval_status: null, correction_reason: null };
                }
            },
        }
    );
}

function setAttendance(session, registration, status) {
    if (!localAttendance[session.id]) localAttendance[session.id] = {};
    localAttendance[session.id][registration.id] = {
        ...(localAttendance[session.id][registration.id] || {}),
        status,
        approval_status: null,
    };

    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/sessions/${session.id}/attendance/${registration.id}`,
        { status },
        { preserveScroll: true }
    );
}

function reviewCorrection(item, decision) {
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/sessions/${item.sessionId}/attendance/${item.regId}/review`,
        { decision },
        {
            preserveScroll: true,
            onSuccess: () => {
                if (localAttendance[item.sessionId]?.[item.regId]) {
                    localAttendance[item.sessionId][item.regId].approval_status = decision;
                }
            },
        }
    );
}
</script>
