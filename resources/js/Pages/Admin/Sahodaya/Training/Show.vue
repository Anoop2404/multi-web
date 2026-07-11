<template>
    <SahodayaAdminLayout :title="program.title" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="program.title" eyebrow="Teacher training"
                    :description="`${program.registrations?.length ?? 0} registrations · ${program.status}`">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/qr-reports`"
                      class="btn-secondary text-sm">
                    QR reports
                </Link>
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
                <FormField label="QR registration" hint="Public QR form; expires after registration closes">
                    <template #default="{ id }">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input :id="id" v-model="form.qr_registration_enabled" type="checkbox" class="rounded">
                            Enabled
                        </label>
                    </template>
                </FormField>
                <FormField label="Require verified teachers" hint="Off = unverified teachers may register and attend">
                    <template #default="{ id }">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input :id="id" v-model="form.require_verified_teachers" type="checkbox" class="rounded">
                            Required
                        </label>
                    </template>
                </FormField>
                <FormField label="School attendance" hint="Schools can mark present/absent for their teachers">
                    <template #default="{ id }">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input :id="id" v-model="form.allow_school_attendance" type="checkbox" class="rounded">
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

        <div id="qr" class="card mb-6 scroll-mt-6">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div>
                    <h3 class="section-title">QR registration & attendance</h3>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ qr?.registration_open ? 'Registration QR is open' : 'Registration QR is closed (window / status / toggle)' }}
                    </p>
                </div>
                <button type="button" class="btn-secondary text-xs" @click="regenerateQr">Regenerate QR tokens</button>
            </div>
            <div class="grid gap-6 sm:grid-cols-2">
                <div class="space-y-2">
                    <p class="text-sm font-semibold">Registration QR</p>
                    <img v-if="qr?.registration_png" :src="qr.registration_png" alt="Registration QR" class="w-40 h-40 border rounded bg-white p-2">
                    <p class="text-xs text-gray-500 break-all">{{ qr?.registration_url }}</p>
                    <div class="flex flex-wrap gap-2">
                        <a :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/qr/registration/png`" class="text-xs font-semibold text-indigo-600">PNG</a>
                        <a :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/qr/registration/svg`" class="text-xs font-semibold text-indigo-600">SVG</a>
                        <a :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/qr/registration/pdf`" class="text-xs font-semibold text-indigo-600">PDF</a>
                    </div>
                </div>
                <div class="space-y-2">
                    <p class="text-sm font-semibold">Attendance QR (program)</p>
                    <img v-if="qr?.attendance_png" :src="qr.attendance_png" alt="Attendance QR" class="w-40 h-40 border rounded bg-white p-2">
                    <p class="text-xs text-gray-500 break-all">{{ qr?.attendance_url }}</p>
                    <div class="flex flex-wrap gap-2">
                        <a :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/qr/attendance/png`" class="text-xs font-semibold text-indigo-600">PNG</a>
                        <a :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/qr/attendance/svg`" class="text-xs font-semibold text-indigo-600">SVG</a>
                        <a :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/qr/attendance/pdf`" class="text-xs font-semibold text-indigo-600">PDF</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sessions -->
        <div id="sessions" class="card mb-4 scroll-mt-6">
            <div class="flex flex-wrap justify-between items-center gap-2 mb-3">
                <h4 class="font-semibold text-sm">Sessions</h4>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/attendance`"
                      class="text-xs font-semibold text-indigo-600">
                    Open attendance →
                </Link>
            </div>
            <form @submit.prevent="addSession" class="grid gap-2 sm:grid-cols-2 mb-4">
                <input v-model="sessionForm.title" class="field sm:col-span-2" placeholder="Day / session title (e.g. Day 1)" required>
                <input v-model="sessionForm.scheduled_at" type="datetime-local" class="field" placeholder="Date & time">
                <input v-model="sessionForm.venue" class="field" placeholder="Venue (optional override)">
                <input v-model="sessionForm.duration_minutes" type="number" min="15" class="field" placeholder="Duration (minutes)">
                <button class="btn-primary px-3 py-1.5 rounded text-xs whitespace-nowrap sm:col-span-2 sm:w-fit">Add training day</button>
            </form>
            <div v-for="session in program.sessions" :key="session.id" class="border rounded-lg p-3 mb-3">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <p class="text-sm font-medium">{{ session.title }}</p>
                        <p class="text-xs text-gray-500">{{ session.scheduled_at || 'No date' }} {{ session.venue ? `· ${session.venue}` : '' }}</p>
                    </div>
                    <a :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/sessions/${session.id}/qr/png`"
                       class="text-xs text-indigo-600 font-semibold border border-indigo-200 px-2 py-1 rounded whitespace-nowrap">
                        Session QR
                    </a>
                </div>
            </div>
            <p v-if="!program.sessions?.length" class="text-xs text-gray-400">No sessions yet. Add a training day above.</p>
        </div>

        <!-- Registrations -->
        <div class="card scroll-mt-6">
            <div class="flex flex-wrap justify-between items-center gap-2">
                <div>
                    <h4 class="font-semibold text-sm">Registrations</h4>
                    <p class="text-xs text-gray-500 mt-0.5">{{ program.registrations?.length ?? 0 }} registration(s)</p>
                </div>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/registrations`"
                      class="btn-primary text-sm">
                    Open registrations →
                </Link>
            </div>
        </div>

        <!-- Attendance -->
        <div class="card scroll-mt-6">
            <div class="flex flex-wrap justify-between items-center gap-2">
                <div>
                    <h4 class="font-semibold text-sm">Attendance</h4>
                    <p class="text-xs text-gray-500 mt-0.5">Mark present/absent by session</p>
                </div>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/attendance`"
                      class="btn-primary text-sm">
                    Open attendance →
                </Link>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { useForm, router, Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    program: Object,
    attendanceMap: Object,
    qr: Object,
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
    qr_registration_enabled: props.program.qr_registration_enabled ?? true,
    require_verified_teachers: props.program.require_verified_teachers ?? false,
    allow_school_attendance: props.program.allow_school_attendance ?? true,
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

function save() {
    form.put(`/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}`, { preserveScroll: true });
}

function addSession() {
    sessionForm.post(`/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/sessions`, {
        preserveScroll: true,
        onSuccess: () => sessionForm.reset(),
    });
}

function regenerateQr() {
    if (!window.confirm('Regenerate QR tokens? Existing printed QR codes and links will stop working.')) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/qr/regenerate`, {}, { preserveScroll: true });
}
</script>

