<template>
    <SahodayaAdminLayout :title="program.title" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="program.title" eyebrow="Teacher training"
                    :description="`${program.registrations?.length ?? 0} registrations · ${program.status}`">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/attendance`"
                      class="btn-secondary text-sm">
                    Attendance & report
                </Link>
                <a :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/certificate/preview`"
                   target="_blank" rel="noopener" class="btn-secondary text-sm">
                    Sample certificate ↗
                </a>
                <Link v-if="program.fee_type !== 'none' && program.fee_amount"
                      :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/ledger`"
                      class="btn-secondary text-sm">
                    Payment ledger
                </Link>
            </template>
        </PageHeader>

        <form id="overview" @submit.prevent="save" class="card mb-6 space-y-4">
            <h3 class="section-title">Program details</h3>
            <FormGrid>
                <FormField label="Title" class-extra="sm:col-span-2" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="form.title" class="field" required>
                    </template>
                </FormField>
                <FormField label="Description" class-extra="sm:col-span-2">
                    <template #default="{ id }">
                        <textarea :id="id" v-model="form.description" class="field" rows="2"></textarea>
                    </template>
                </FormField>
                <FormField label="Venue" class-extra="sm:col-span-2">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.venue" class="field" placeholder="e.g. St. Alphonsa Public School, Oorakam">
                    </template>
                </FormField>
                <FormField label="Start date">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.start_date" type="date" class="field">
                    </template>
                </FormField>
                <FormField label="End date">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.end_date" type="date" class="field">
                    </template>
                </FormField>
                <FormField label="Registration opens">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.registration_open" type="date" class="field">
                    </template>
                </FormField>
                <FormField label="Registration closes">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.registration_close" type="date" class="field">
                    </template>
                </FormField>
                <FormField label="Max participants">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.max_participants" type="number" min="1" class="field">
                    </template>
                </FormField>
                <FormField label="Teacher self-registration" hint="Teachers can register from their portal">
                    <template #default="{ id }">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input :id="id" v-model="form.allow_teacher_self_registration" type="checkbox" class="rounded">
                            Enabled
                        </label>
                    </template>
                </FormField>
                <FormField label="Status">
                    <template #default="{ id }">
                        <select :id="id" v-model="form.status" class="field">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="completed">Completed</option>
                        </select>
                    </template>
                </FormField>
                <FormField label="Fee type">
                    <template #default="{ id }">
                        <select :id="id" v-model="form.fee_type" class="field">
                            <option value="none">No fee</option>
                            <option value="flat">Flat fee</option>
                        </select>
                    </template>
                </FormField>
                <FormField v-if="form.fee_type !== 'none'" label="Fee amount (₹)">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.fee_amount" type="number" min="0" step="0.01" class="field">
                    </template>
                </FormField>
            </FormGrid>
            <FormActions>
                <button type="submit" class="btn-primary" :disabled="form.processing">Save program</button>
            </FormActions>
        </form>

        <!-- Sessions with per-teacher attendance -->
        <div id="sessions" class="card mb-4 scroll-mt-6">
            <h4 class="font-semibold text-sm mb-3">Sessions</h4>
            <form @submit.prevent="addSession" class="grid gap-2 sm:grid-cols-2 mb-4">
                <input v-model="sessionForm.title" class="field sm:col-span-2" placeholder="Day / session title (e.g. Day 1)" required>
                <input v-model="sessionForm.scheduled_at" type="datetime-local" class="field" placeholder="Date & time">
                <input v-model="sessionForm.venue" class="field" placeholder="Venue (optional override)">
                <input v-model="sessionForm.duration_minutes" type="number" min="15" class="field" placeholder="Duration (minutes)">
                <button class="btn-primary px-3 py-1.5 rounded text-xs whitespace-nowrap sm:col-span-2 sm:w-fit">Add training day</button>
            </form>
            <div v-for="session in program.sessions" :key="session.id" class="border rounded-lg p-3 mb-3">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <p class="text-sm font-medium">{{ session.title }}</p>
                        <p class="text-xs text-gray-500">{{ session.scheduled_at || 'No date' }} {{ session.venue ? `· ${session.venue}` : '' }}</p>
                    </div>
                    <button @click="markAllPresent(session)"
                            class="text-xs text-indigo-600 font-semibold border border-indigo-200 px-2 py-1 rounded">
                        Mark all present
                    </button>
                </div>
                <table v-if="confirmedRegistrations.length" class="w-full text-xs mt-2">
                    <thead class="text-left text-gray-500">
                        <tr>
                            <th class="pb-1">Teacher</th>
                            <th class="pb-1">Attendance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="r in confirmedRegistrations" :key="r.id">
                            <td class="py-1.5">{{ r.teacher?.name || `#${r.id}` }}</td>
                            <td class="py-1.5">
                                <div class="flex gap-1">
                                    <button @click="setAttendance(session, r, 'present')"
                                            class="px-2 py-0.5 rounded text-xs font-semibold transition"
                                            :class="attendanceStatus(session.id, r.id) === 'present' ? 'btn-primary !min-h-0 !px-2 !py-0.5 text-xs' : 'bg-gray-100 text-gray-600 hover:bg-green-100 px-2 py-0.5 rounded text-xs font-semibold'">
                                        Present
                                    </button>
                                    <button @click="setAttendance(session, r, 'absent')"
                                            class="px-2 py-0.5 rounded text-xs font-semibold transition"
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
                <p v-else class="text-xs text-gray-400 mt-2">No confirmed registrations yet.</p>
            </div>
        </div>

        <!-- Registrations -->
        <div id="registrations" class="card scroll-mt-6">
            <div class="flex flex-wrap justify-between items-center gap-2 mb-2">
                <h4 class="font-semibold text-sm">Registrations ({{ program.registrations?.length ?? 0 }})</h4>
                <a v-if="confirmedRegistrations.length"
                   :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/certificates/export`"
                   class="text-xs font-semibold text-indigo-600">
                    Download all certificates (ZIP) ↗
                </a>
            </div>
            <ul class="text-sm divide-y">
                <li v-for="r in program.registrations" :key="r.id" class="py-2 flex justify-between items-center gap-2 flex-wrap">
                    <div>
                        <span>{{ r.teacher?.name || `#${r.id}` }}</span>
                        <span class="text-xs text-gray-400 ml-2">{{ r.teacher?.school_name || '' }}</span>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs text-gray-400 capitalize">{{ r.status }}</span>
                        <template v-if="program.fee_type !== 'none' && program.fee_amount > 0">
                            <span v-if="r.fee_receipt?.status === 'approved'" class="text-xs text-green-700 font-semibold">Fee approved</span>
                            <span v-else-if="r.fee_receipt?.status === 'uploaded'" class="text-xs text-amber-700 font-semibold">Fee pending</span>
                            <span v-else-if="r.fee_receipt?.status === 'rejected'" class="text-xs text-red-600 font-semibold">Fee rejected</span>
                            <span v-else class="text-xs text-gray-400">No fee proof</span>
                            <a v-if="['uploaded', 'approved'].includes(r.fee_receipt?.status)"
                               :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/registrations/${r.id}/fee/proof`"
                               target="_blank" rel="noopener"
                               class="text-xs text-indigo-600 font-semibold">View proof ↗</a>
                            <button v-if="r.fee_receipt?.status === 'uploaded'"
                                    @click="approveFee(r)"
                                    class="text-xs text-green-600 font-semibold">Approve fee</button>
                            <button v-if="r.fee_receipt?.status === 'uploaded'"
                                    @click="rejectFee(r)"
                                    class="text-xs text-red-600 font-semibold">Reject fee</button>
                        </template>
                        <button v-if="r.status === 'registered' && canConfirm(r)" @click="confirm(r)"
                                class="text-xs text-green-600 font-semibold">Confirm</button>
                        <template v-if="r.status === 'confirmed'">
                            <button @click="issueCertificate(r)"
                                    class="text-xs text-purple-600 font-semibold">
                                {{ r.certificate ? 'Reissue cert' : 'Issue cert' }}
                            </button>
                            <a v-if="r.certificate"
                               :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/registrations/${r.id}/certificate/print`"
                               target="_blank" rel="noopener"
                               class="text-xs text-indigo-600 font-semibold">Print cert ↗</a>
                        </template>
                    </div>
                </li>
            </ul>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { useForm, router, Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    program: Object,
    attendanceMap: Object,
});

const form = useForm({
    title: props.program.title,
    description: props.program.description ?? '',
    venue: props.program.venue ?? '',
    start_date: props.program.start_date?.slice?.(0, 10) ?? props.program.start_date ?? '',
    end_date: props.program.end_date?.slice?.(0, 10) ?? props.program.end_date ?? '',
    registration_open: props.program.registration_open?.slice?.(0, 10) ?? props.program.registration_open ?? '',
    registration_close: props.program.registration_close?.slice?.(0, 10) ?? props.program.registration_close ?? '',
    max_participants: props.program.max_participants ?? '',
    allow_teacher_self_registration: props.program.allow_teacher_self_registration ?? true,
    status: props.program.status,
    fee_type: props.program.fee_type ?? 'none',
    fee_amount: props.program.fee_amount ?? '',
});
const sessionForm = useForm({
    title: '',
    scheduled_at: '',
    venue: '',
    duration_minutes: '',
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

function save() {
    form.put(`/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}`, { preserveScroll: true });
}

function addSession() {
    sessionForm.post(`/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/sessions`, {
        preserveScroll: true,
        onSuccess: () => sessionForm.reset(),
    });
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

function confirm(registration) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/registrations/${registration.id}/confirm`, {}, { preserveScroll: true });
}

function canConfirm(registration) {
    if (props.program.fee_type === 'none' || !props.program.fee_amount || props.program.fee_amount <= 0) {
        return true;
    }
    return registration.fee_receipt?.status === 'approved';
}

function approveFee(registration) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/registrations/${registration.id}/fee/approve`, {}, { preserveScroll: true });
}

function rejectFee(registration) {
    const reason = window.prompt('Rejection reason (optional):') ?? '';
    router.post(`/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/registrations/${registration.id}/fee/reject`, {
        rejection_reason: reason || null,
    }, { preserveScroll: true });
}

function issueCertificate(registration) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/registrations/${registration.id}/certificate`, {}, { preserveScroll: true });
}
</script>

