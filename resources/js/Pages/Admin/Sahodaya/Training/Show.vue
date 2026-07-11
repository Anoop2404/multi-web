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
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/feedback`"
                      class="btn-secondary text-sm">
                    Feedback
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
                <FormField label="Program code" hint="Unique short code for this Sahodaya (optional)">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.code" class="field" maxlength="50" placeholder="e.g. TPD-2026-01">
                        <p v-if="form.errors.code" class="text-xs text-red-600 mt-1">{{ form.errors.code }}</p>
                    </template>
                </FormField>
                <FormField label="Description" class-extra="sm:col-span-2">
                    <template #default="{ id }">
                        <textarea :id="id" v-model="form.description" class="field" rows="2"></textarea>
                    </template>
                </FormField>
                <FormField label="Banner image" class-extra="sm:col-span-2" hint="Optional poster / banner (JPG, PNG, WebP · max 5 MB)">
                    <template #default="{ id }">
                        <div class="space-y-2">
                            <img v-if="bannerPreview" :src="bannerPreview" alt="Banner preview"
                                 class="max-h-36 rounded border border-slate-200 object-cover">
                            <input :id="id" type="file" accept="image/*" class="field text-sm" @change="onBannerSelected">
                            <label v-if="program.banner_image_url || form.banner_image" class="inline-flex items-center gap-2 text-sm text-slate-600">
                                <input v-model="form.remove_banner_image" type="checkbox" class="rounded"
                                       :disabled="!!form.banner_image">
                                Remove current banner
                            </label>
                            <p v-if="form.errors.banner_image" class="text-xs text-red-600">{{ form.errors.banner_image }}</p>
                        </div>
                    </template>
                </FormField>
                <FormField label="Venue" class-extra="sm:col-span-2">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.venue" class="field" placeholder="e.g. St. Alphonsa Public School, Oorakam">
                    </template>
                </FormField>
                <FormField label="Category">
                    <template #default="{ id }">
                        <select :id="id" v-model="form.category_id" class="field">
                            <option value="">— None —</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.label }}</option>
                        </select>
                    </template>
                </FormField>
                <FormField label="Certificate type" hint="Template matched by type; falls back to participation">
                    <template #default="{ id }">
                        <select :id="id" v-model="form.certificate_type" class="field">
                            <option v-for="t in certificateTypes" :key="t" :value="t">{{ formatCertType(t) }}</option>
                        </select>
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
                <FormField label="School nomination" hint="Schools can nominate / bulk-import teachers">
                    <template #default="{ id }">
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input :id="id" v-model="form.allow_school_nomination" type="checkbox" class="rounded">
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
                            <option value="flat">Flat fee (per teacher)</option>
                            <option value="school">School batch fee</option>
                        </select>
                        <p v-if="form.fee_type === 'school'" class="text-xs text-gray-500 mt-1">
                            School pays one batch amount = nominated teachers × fee amount. Per-teacher uploads are skipped.
                        </p>
                    </template>
                </FormField>
                <FormField v-if="form.fee_type !== 'none'" label="Fee amount (₹)">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.fee_amount" type="number" min="0" step="0.01" class="field">
                    </template>
                </FormField>
                <FormField label="Min attendance % for certificate">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.min_attendance_percent" type="number" min="0" max="100"
                               class="field" placeholder="Blank = ≥1 day">
                        <p class="text-xs text-gray-500 mt-1">Percent of program days required present. Blank or 0 keeps the default (≥1 day).</p>
                    </template>
                </FormField>
            </FormGrid>

            <div class="border-t border-slate-100 pt-4 space-y-4">
                <div>
                    <h4 class="text-sm font-semibold text-slate-800">Eligibility rules</h4>
                    <p class="text-xs text-gray-500 mt-0.5">Leave filters empty to allow all teachers (subject to verification / capacity).</p>
                    <p v-if="form.errors.eligibility_config" class="text-xs text-red-600 mt-1">{{ form.errors.eligibility_config }}</p>
                </div>
                <FormGrid>
                    <FormField label="Teaching categories" hint="Empty = any category" class-extra="sm:col-span-2">
                        <div class="flex flex-wrap gap-3 max-h-32 overflow-y-auto border rounded-md p-2">
                            <label v-for="t in eligibilityOptions.teaching_types || []" :key="t.id"
                                   class="inline-flex items-center gap-1.5 text-sm text-slate-700">
                                <input type="checkbox" class="rounded" :value="t.id"
                                       v-model="form.eligibility_config.teaching_type_ids">
                                {{ t.label }}
                            </label>
                            <p v-if="!(eligibilityOptions.teaching_types || []).length" class="text-xs text-gray-400">No teaching categories configured.</p>
                        </div>
                    </FormField>
                    <FormField label="Subjects" hint="Must teach at least one selected subject" class-extra="sm:col-span-2">
                        <div class="flex flex-wrap gap-3 max-h-32 overflow-y-auto border rounded-md p-2">
                            <label v-for="s in eligibilityOptions.subjects || []" :key="s.id"
                                   class="inline-flex items-center gap-1.5 text-sm text-slate-700">
                                <input type="checkbox" class="rounded" :value="s.id"
                                       v-model="form.eligibility_config.subject_ids">
                                {{ s.label }}
                            </label>
                            <p v-if="!(eligibilityOptions.subjects || []).length" class="text-xs text-gray-400">No subjects configured.</p>
                        </div>
                    </FormField>
                    <FormField label="Exclude designations" hint="These designations cannot register" class-extra="sm:col-span-2">
                        <div class="flex flex-wrap gap-3 max-h-32 overflow-y-auto border rounded-md p-2">
                            <label v-for="d in eligibilityOptions.designations || []" :key="d.id"
                                   class="inline-flex items-center gap-1.5 text-sm text-slate-700">
                                <input type="checkbox" class="rounded" :value="d.id"
                                       v-model="form.eligibility_config.excluded_designation_ids">
                                {{ d.label }}
                            </label>
                            <p v-if="!(eligibilityOptions.designations || []).length" class="text-xs text-gray-400">No designations configured.</p>
                        </div>
                    </FormField>
                    <FormField label="Minimum experience (years)">
                        <template #default="{ id }">
                            <input :id="id" v-model="form.eligibility_config.min_experience_years" type="number"
                                   min="0" max="60" class="field" placeholder="Any">
                        </template>
                    </FormField>
                    <FormField label="Prior training required">
                        <template #default="{ id }">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input :id="id" v-model="form.eligibility_config.prior_training.required"
                                       type="checkbox" class="rounded">
                                Must have completed prior training
                            </label>
                        </template>
                    </FormField>
                    <FormField v-if="form.eligibility_config.prior_training.required"
                               label="Required prior programme"
                               hint="Blank = any prior completed programme"
                               class-extra="sm:col-span-2">
                        <template #default="{ id }">
                            <select :id="id" v-model="form.eligibility_config.prior_training.program_id" class="field">
                                <option :value="null">Any prior completed programme</option>
                                <option v-for="p in eligibilityOptions.prior_programs || []" :key="p.id" :value="p.id">
                                    {{ p.title }}
                                </option>
                            </select>
                        </template>
                    </FormField>
                    <FormField v-if="(eligibilityOptions.regions || []).length"
                               label="Eligible regions"
                               hint="Empty = all regions. Uses school region assignments."
                               class-extra="sm:col-span-2">
                        <div class="flex flex-wrap gap-3 max-h-32 overflow-y-auto border rounded-md p-2">
                            <label v-for="r in eligibilityOptions.regions" :key="r.id"
                                   class="inline-flex items-center gap-1.5 text-sm text-slate-700">
                                <input type="checkbox" class="rounded" :value="r.id"
                                       v-model="form.eligibility_config.region_ids">
                                {{ r.name }}
                            </label>
                        </div>
                    </FormField>
                </FormGrid>
            </div>

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
                <div class="flex flex-wrap gap-3 items-center">
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/training/resource-persons`"
                          class="text-xs font-semibold text-indigo-600">
                        Manage resource persons
                    </Link>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/attendance`"
                          class="text-xs font-semibold text-indigo-600">
                        Open attendance →
                    </Link>
                </div>
            </div>
            <form @submit.prevent="addSession" class="grid gap-2 sm:grid-cols-2 mb-4">
                <input v-model="sessionForm.title" class="field sm:col-span-2" placeholder="Day / session title (e.g. Day 1)" required>
                <input v-model="sessionForm.scheduled_at" type="datetime-local" class="field" placeholder="Date & time">
                <input v-model="sessionForm.venue" class="field" placeholder="Venue (optional override)">
                <input v-model="sessionForm.duration_minutes" type="number" min="15" class="field" placeholder="Duration (minutes)">
                <select v-model="sessionForm.resource_person_id" class="field sm:col-span-2">
                    <option value="">Resource person / trainer (optional)</option>
                    <option v-for="rp in resourcePersons" :key="rp.id" :value="rp.id">
                        {{ rp.name }}{{ rp.designation ? ` · ${rp.designation}` : '' }}
                    </option>
                </select>
                <p v-if="!resourcePersons?.length" class="text-xs text-amber-700 sm:col-span-2">
                    No active resource persons yet.
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/training/resource-persons`" class="font-semibold underline">Add one</Link>
                    to assign trainers to sessions.
                </p>
                <button class="btn-primary px-3 py-1.5 rounded text-xs whitespace-nowrap sm:col-span-2 sm:w-fit">Add training day</button>
            </form>
            <div v-for="session in program.sessions" :key="session.id" class="border rounded-lg p-3 mb-3">
                <div v-if="editingSessionId === session.id" class="space-y-2">
                    <input v-model="editSessionForm.title" class="field" placeholder="Session title" required>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <input v-model="editSessionForm.scheduled_at" type="datetime-local" class="field">
                        <input v-model="editSessionForm.venue" class="field" placeholder="Venue">
                        <input v-model="editSessionForm.duration_minutes" type="number" min="15" class="field" placeholder="Duration (minutes)">
                        <select v-model="editSessionForm.resource_person_id" class="field">
                            <option value="">Resource person (optional)</option>
                            <option v-for="rp in resourcePersons" :key="rp.id" :value="rp.id">
                                {{ rp.name }}{{ rp.designation ? ` · ${rp.designation}` : '' }}
                            </option>
                        </select>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn-primary text-xs" :disabled="editSessionForm.processing"
                                @click="saveSession">Save</button>
                        <button type="button" class="btn-secondary text-xs" @click="cancelEditSession">Cancel</button>
                    </div>
                </div>
                <div v-else class="flex items-center justify-between gap-2">
                    <div>
                        <p class="text-sm font-medium">{{ session.title }}</p>
                        <p class="text-xs text-gray-500">
                            {{ session.scheduled_at || 'No date' }}
                            {{ session.venue ? `· ${session.venue}` : '' }}
                            <span v-if="session.resource_person?.name || sessionResourceName(session)" class="text-indigo-700">
                                · {{ session.resource_person?.name || sessionResourceName(session) }}
                            </span>
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="text-xs text-slate-600 font-semibold border border-slate-200 px-2 py-1 rounded"
                                @click="startEditSession(session)">Edit</button>
                        <button type="button" class="text-xs text-red-600 font-semibold border border-red-100 px-2 py-1 rounded"
                                @click="deleteSession(session)">Delete</button>
                        <a :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/sessions/${session.id}/qr/png`"
                           class="text-xs text-indigo-600 font-semibold border border-indigo-200 px-2 py-1 rounded whitespace-nowrap">
                            Session QR
                        </a>
                    </div>
                </div>
            </div>
            <p v-if="!program.sessions?.length" class="text-xs text-gray-400">No sessions yet. Add a training day above.</p>
        </div>

        <!-- Program resource persons (honorarium) -->
        <div class="card mb-4 scroll-mt-6">
            <div class="flex flex-wrap justify-between items-center gap-2 mb-3">
                <div>
                    <h4 class="font-semibold text-sm">Assigned resource persons</h4>
                    <p class="text-xs text-gray-500 mt-0.5">Program-level trainers with optional honorarium / role</p>
                </div>
            </div>
            <form @submit.prevent="assignPerson" class="grid gap-2 sm:grid-cols-4 mb-4">
                <select v-model="assignForm.resource_person_id" class="field sm:col-span-2" required>
                    <option value="">Select person</option>
                    <option v-for="rp in assignablePersons" :key="rp.id" :value="rp.id">
                        {{ rp.name }}{{ rp.designation ? ` · ${rp.designation}` : '' }}
                    </option>
                </select>
                <input v-model="assignForm.role" class="field" placeholder="Role (trainer / facilitator)">
                <input v-model="assignForm.honorarium" type="number" min="0" step="0.01" class="field" placeholder="Honorarium ₹">
                <button class="btn-secondary px-3 py-1.5 rounded text-xs whitespace-nowrap sm:col-span-4 sm:w-fit">
                    Assign to program
                </button>
            </form>
            <div v-for="rp in program.resource_persons || []" :key="rp.id"
                 class="flex flex-wrap items-center justify-between gap-2 border rounded-lg p-3 mb-2">
                <div>
                    <p class="text-sm font-medium">{{ rp.name }}</p>
                    <p class="text-xs text-gray-500">
                        {{ rp.pivot?.role || '—' }}
                        <span v-if="rp.pivot?.honorarium != null"> · ₹{{ rp.pivot.honorarium }}</span>
                    </p>
                </div>
                <button type="button" class="text-xs text-red-600 font-semibold" @click="unassignPerson(rp)">
                    Remove
                </button>
            </div>
            <p v-if="!(program.resource_persons || []).length" class="text-xs text-gray-400">
                No program-level assignments yet. Selecting a trainer on a session also attaches them here.
            </p>
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
import { computed, ref } from 'vue';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    program: Object,
    categories: { type: Array, default: () => [] },
    certificateTypes: {
        type: Array,
        default: () => ['participation', 'completion', 'appreciation', 'resource_person', 'organizer'],
    },
    resourcePersons: { type: Array, default: () => [] },
    attendanceMap: Object,
    eligibilityOptions: { type: Object, default: () => ({}) },
    qr: Object,
});

const ec = props.program.eligibility_config ?? {};

const assignedIds = computed(() => new Set((props.program.resource_persons || []).map((p) => p.id)));
const assignablePersons = computed(() =>
    (props.resourcePersons || []).filter((p) => !assignedIds.value.has(p.id)),
);

const form = useForm({
    title: props.program.title,
    code: props.program.code ?? '',
    description: props.program.description ?? '',
    banner_image: null,
    remove_banner_image: false,
    venue: props.program.venue ?? '',
    category_id: props.program.category_id ?? '',
    certificate_type: props.program.certificate_type ?? 'participation',
    start_date: props.program.start_date?.slice?.(0, 10) ?? props.program.start_date ?? '',
    end_date: props.program.end_date?.slice?.(0, 10) ?? props.program.end_date ?? '',
    registration_open: props.program.registration_open?.slice?.(0, 10) ?? props.program.registration_open ?? '',
    registration_close: props.program.registration_close?.slice?.(0, 10) ?? props.program.registration_close ?? '',
    max_participants: props.program.max_participants ?? '',
    allow_teacher_self_registration: props.program.allow_teacher_self_registration ?? true,
    allow_school_nomination: props.program.allow_school_nomination ?? true,
    qr_registration_enabled: props.program.qr_registration_enabled ?? true,
    require_verified_teachers: props.program.require_verified_teachers ?? false,
    allow_school_attendance: props.program.allow_school_attendance ?? true,
    status: props.program.status,
    fee_type: props.program.fee_type ?? 'none',
    fee_amount: props.program.fee_amount ?? '',
    min_attendance_percent: props.program.min_attendance_percent ?? '',
    eligibility_config: {
        teaching_type_ids: [...(ec.teaching_type_ids || [])],
        subject_ids: [...(ec.subject_ids || [])],
        excluded_designation_ids: [...(ec.excluded_designation_ids || [])],
        min_experience_years: ec.min_experience_years ?? '',
        prior_training: {
            required: !!(ec.prior_training?.required),
            program_id: ec.prior_training?.program_id ?? null,
        },
        region_ids: [...(ec.region_ids || [])],
    },
});

const bannerPreview = computed(() => {
    if (form.banner_image instanceof File) {
        return URL.createObjectURL(form.banner_image);
    }
    if (form.remove_banner_image) return null;
    return props.program.banner_image_url || null;
});

function formatCertType(type) {
    return String(type || '').replaceAll('_', ' ');
}
const sessionForm = useForm({
    title: '',
    scheduled_at: '',
    venue: '',
    duration_minutes: '',
    resource_person_id: '',
});

const editingSessionId = ref(null);
const editSessionForm = useForm({
    title: '',
    scheduled_at: '',
    venue: '',
    duration_minutes: '',
    resource_person_id: '',
});

const assignForm = useForm({
    resource_person_id: '',
    role: '',
    honorarium: '',
});

function sessionResourceName(session) {
    if (session.resource_person?.name) return session.resource_person.name;
    const match = (props.resourcePersons || []).find((p) => p.id === session.resource_person_id);
    return match?.name ?? null;
}

function onBannerSelected(e) {
    form.banner_image = e.target.files?.[0] ?? null;
    if (form.banner_image) form.remove_banner_image = false;
}

function toDatetimeLocal(value) {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value).slice(0, 16);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function save() {
    form.transform((data) => {
        const payload = { ...data };
        if (!payload.banner_image) delete payload.banner_image;
        if (!payload.remove_banner_image) delete payload.remove_banner_image;
        return payload;
    }).put(`/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}`, {
        preserveScroll: true,
        forceFormData: true,
    });
}

function addSession() {
    sessionForm.post(`/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/sessions`, {
        preserveScroll: true,
        onSuccess: () => sessionForm.reset(),
    });
}

function startEditSession(session) {
    editingSessionId.value = session.id;
    editSessionForm.title = session.title ?? '';
    editSessionForm.scheduled_at = toDatetimeLocal(session.scheduled_at);
    editSessionForm.venue = session.venue ?? '';
    editSessionForm.duration_minutes = session.duration_minutes ?? '';
    editSessionForm.resource_person_id = session.resource_person_id ?? '';
}

function cancelEditSession() {
    editingSessionId.value = null;
    editSessionForm.reset();
}

function saveSession() {
    if (!editingSessionId.value) return;
    editSessionForm.put(
        `/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/sessions/${editingSessionId.value}`,
        {
            preserveScroll: true,
            onSuccess: () => {
                editingSessionId.value = null;
                editSessionForm.reset();
            },
        },
    );
}

function deleteSession(session) {
    if (!window.confirm(`Delete session "${session.title}"? Related session attendance will be removed.`)) return;
    router.delete(
        `/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/sessions/${session.id}`,
        { preserveScroll: true },
    );
}

function assignPerson() {
    assignForm.post(`/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/resource-persons`, {
        preserveScroll: true,
        onSuccess: () => assignForm.reset(),
    });
}

function unassignPerson(person) {
    if (!window.confirm(`Remove ${person.name} from this program?`)) return;
    router.delete(
        `/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/resource-persons/${person.id}`,
        { preserveScroll: true },
    );
}

function regenerateQr() {
    if (!window.confirm('Regenerate QR tokens? Existing printed QR codes and links will stop working.')) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/qr/regenerate`, {}, { preserveScroll: true });
}
</script>

