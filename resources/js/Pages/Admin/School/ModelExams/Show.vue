<template>
    <SchoolAdminLayout :title="exam.title" :school="school" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="Model exam"
                    :description="`${registrationCounts.total} enrolled · ${exam.status}`">
            <template #actions>
                <Link :href="`/school-admin/${school.id}/model-exams`" class="btn-secondary text-sm">← All model exams</Link>
                <Link :href="`/school-admin/${school.id}/model-exams/${exam.id}/paper`" class="btn-secondary text-sm">Paper builder</Link>
            </template>
        </PageHeader>

        <div class="flex flex-wrap gap-2 mb-4">
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-sky-100 text-sky-800">School model exam</span>
            <span class="text-xs px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 capitalize">{{ exam.delivery_mode || 'online' }}</span>
            <span class="text-xs px-2.5 py-1 rounded-full bg-slate-50 text-slate-600">{{ exam.eligibility_summary || 'All classes' }}</span>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ registrationCounts.total }}</p>
                <p class="text-xs text-slate-500 mt-1">Enrolled</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-indigo-700">{{ registrationCounts.started }}</p>
                <p class="text-xs text-slate-500 mt-1">Started</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ registrationCounts.submitted }}</p>
                <p class="text-xs text-slate-500 mt-1">Submitted</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold" :class="exam.results_published ? 'text-emerald-700' : 'text-amber-700'">
                    {{ exam.results_published ? 'Released' : 'Held' }}
                </p>
                <p class="text-xs text-slate-500 mt-1">Results</p>
            </div>
        </div>

        <div class="card mb-6 space-y-3">
            <h3 class="section-title">Publish & enroll</h3>
            <p class="text-sm text-slate-600">
                Paper: <strong>{{ exam.paper?.total ?? 0 }}</strong> questions ·
                <strong>{{ exam.paper?.max_score ?? 0 }}</strong> marks
                <Link :href="`/school-admin/${school.id}/model-exams/${exam.id}/paper`" class="link-brand ml-2 text-xs">Edit paper →</Link>
            </p>
            <div class="flex flex-wrap gap-2">
                <button v-if="exam.status === 'draft'" type="button" class="btn-primary text-sm" :disabled="publishForm.processing" @click="publish(true)">
                    Publish & auto-enroll eligible
                </button>
                <button v-if="exam.status === 'draft'" type="button" class="btn-secondary text-sm" :disabled="publishForm.processing" @click="publish(false)">
                    Publish only
                </button>
                <button type="button" class="btn-secondary text-sm" :disabled="enrollForm.processing" @click="enroll">
                    Enroll eligible students
                </button>
                <button v-if="!exam.results_published" type="button" class="btn-secondary text-sm" :disabled="resultsForm.processing" @click="releaseResults">
                    Release results
                </button>
                <button v-else type="button" class="btn-ghost text-sm !text-amber-700" :disabled="unpublishForm.processing" @click="holdResults">
                    Hold results
                </button>
                <button v-if="exam.results_published && form.certificates_enabled" type="button" class="btn-secondary text-sm"
                        :disabled="certForm.processing" @click="generateCertificates">
                    Generate certificates
                </button>
                <a v-if="form.certificates_enabled"
                   :href="`/school-admin/${school.id}/model-exams/${exam.id}/certificates/preview`"
                   target="_blank" rel="noopener" class="btn-ghost text-sm">Sample certificate ↗</a>
            </div>
        </div>

        <form @submit.prevent="save" class="card mb-6 space-y-4">
            <McqResultReleaseSettingsForm
                v-model="releaseSettings"
                :results-published="!!exam.results_published"
                :certificate-templates="certificateTemplates"
                :grade-bands="gradeBands"
            />
            <FormActions>
                <button type="submit" class="btn-primary" :disabled="form.processing">Save result & certificate settings</button>
            </FormActions>
        </form>

        <form @submit.prevent="save" class="card mb-6 space-y-4">
            <h3 class="section-title">Exam details</h3>
            <FormGrid>
                <FormField label="Title" class-extra="sm:col-span-2" required>
                    <input v-model="form.title" class="field" required>
                </FormField>
                <FormField label="Code">
                    <input v-model="form.code" class="field font-mono uppercase" maxlength="64">
                </FormField>
                <FormField label="Status">
                    <SearchableSelect v-model="form.status"
                                       :options="[
                                           { value: 'draft', label: 'Draft' },
                                           { value: 'published', label: 'Published' },
                                           { value: 'ongoing', label: 'Ongoing' },
                                           { value: 'completed', label: 'Completed' },
                                           { value: 'cancelled', label: 'Cancelled' },
                                       ]"
                                       :all-option="false" />
                </FormField>
                <FormField label="Scheduled start">
                    <input v-model="form.scheduled_at" type="datetime-local" class="field">
                </FormField>
                <FormField label="Duration (min)">
                    <input v-model.number="form.duration_minutes" type="number" min="5" max="480" class="field">
                </FormField>
                <FormField label="Delivery">
                    <SearchableSelect v-model="form.delivery_mode"
                                       :options="[{ value: 'online', label: 'Online' }, { value: 'offline', label: 'Offline' }]"
                                       :all-option="false" />
                </FormField>
                <FormField label="Grade master">
                    <SearchableSelect v-model="form.grade_master_id" :options="gradeMasterOptions" />
                </FormField>
                <FormField label="Pass mark">
                    <input v-model.number="form.pass_mark" type="number" min="0" class="field">
                </FormField>
            </FormGrid>
            <div v-if="form.delivery_mode === 'online'" class="space-y-3">
                <label class="flex items-start gap-2 text-sm text-slate-700">
                    <input v-model="form.require_fullscreen" type="checkbox" class="mt-1 rounded border-slate-300">
                    <span>
                        <span class="font-medium">Require fullscreen during the exam</span>
                        <span class="block text-xs text-slate-500">Students must enter fullscreen to continue. Exit is recorded as a signal — not auto-fail. Does not detect extra monitors.</span>
                    </span>
                </label>
                <label class="flex items-start gap-2 text-sm text-slate-700">
                    <input v-model="form.venue_keycode_join" type="checkbox" class="mt-1 rounded border-slate-300">
                    <span>
                        <span class="font-medium">Venue keycode join</span>
                        <span class="block text-xs text-slate-500">Issue unique keycodes. Students open /attend-exam and join without a portal password.</span>
                    </span>
                </label>
                <label class="flex items-start gap-2 text-sm text-slate-700">
                    <input v-model="form.allow_reattempt" type="checkbox" class="mt-1 rounded border-slate-300">
                    <span>
                        <span class="font-medium">Allow reattempts</span>
                        <span class="block text-xs text-slate-500">Students may start another attempt after submitting while the exam is open.</span>
                    </span>
                </label>
                <label v-if="form.allow_reattempt" class="flex items-center gap-2 text-sm text-slate-700">
                    <span class="font-medium w-28">Max attempts</span>
                    <input v-model.number="form.max_attempts" type="number" min="1" max="10" class="field w-24">
                </label>
                <label class="flex items-start gap-2 text-sm text-slate-700">
                    <input v-model="strictTabProctor" type="checkbox" class="mt-1 rounded border-slate-300">
                    <span>
                        <span class="font-medium">Hard tab-switch auto-submit</span>
                        <span class="block text-xs text-slate-500">After enough tab leaves, submit automatically from saved answers.</span>
                    </span>
                </label>
                <label v-if="strictTabProctor" class="flex items-center gap-2 text-sm text-slate-700">
                    <span class="font-medium w-28">Tab limit</span>
                    <input v-model.number="form.proctor_max_tab_violations" type="number" min="1" max="10" class="field w-24">
                </label>
            </div>
            <div class="border-t border-slate-100 pt-4">
                <McqEligibilityPicker mode="school_model" v-model="form.eligibility_config" :class-categories="classCategories" :master-classes="masterClasses" />
            </div>
            <FormActions>
                <button type="submit" class="btn-primary" :disabled="form.processing">Save</button>
            </FormActions>
        </form>

        <div v-if="exam.delivery_mode === 'online' && (proctorTotals.events > 0 || registrations.some(r => r.started_at))"
             class="card mb-4 !py-3 px-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-800">Live signals</h3>
                <p class="text-xs text-slate-500">Signals for review only — not automatic malpractice.</p>
            </div>
            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
                <span><strong class="text-slate-900">{{ proctorTotals.events }}</strong> events</span>
                <span><strong class="text-slate-900">{{ proctorTotals.students_with_signals }}</strong> students</span>
                <span v-if="proctorTotals.by_type.tab_hidden">Tab hidden {{ proctorTotals.by_type.tab_hidden }}</span>
                <span v-if="proctorTotals.by_type.window_blur">Window blur {{ proctorTotals.by_type.window_blur }}</span>
                <span v-if="proctorTotals.by_type.fullscreen_exit">Fullscreen exit {{ proctorTotals.by_type.fullscreen_exit }}</span>
                <span v-if="proctorTotals.by_type.offline">Offline {{ proctorTotals.by_type.offline }}</span>
            </div>
        </div>

        <div class="card overflow-hidden p-0">
            <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-semibold text-sm">Enrolled students</h3>
                <span class="text-xs text-slate-500">Showing up to 100 · Signals for review only — not automatic malpractice.</span>
            </div>
            <EmptyState v-if="!registrations.length" title="No students enrolled yet"
                        description="Publish with auto-enroll, or use Enroll eligible students." icon="👥" class="py-8" />
            <table v-else class="data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Class</th>
                        <th>Status</th>
                        <th>Score</th>
                        <th>Proctor</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="reg in registrations" :key="reg.id">
                        <td class="font-medium">{{ reg.student?.name || '—' }}</td>
                        <td class="text-xs text-slate-600">{{ reg.student?.class_name || '—' }}</td>
                        <td class="text-xs capitalize">{{ reg.status }}</td>
                        <td class="text-xs">
                            <span v-if="reg.mark">{{ reg.mark.score }}{{ reg.mark.grade ? ` · ${reg.mark.grade}` : '' }}</span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="text-xs">
                            <span
                                v-if="reg.proctor_events_count > 0"
                                class="font-semibold text-amber-700 cursor-help"
                                :title="proctorEventsTooltip(reg.proctor_events)"
                            >
                                {{ reg.proctor_events_count }} signal{{ reg.proctor_events_count === 1 ? '' : 's' }}
                            </span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import McqEligibilityPicker from '@/Components/sahodaya/McqEligibilityPicker.vue';
import McqResultReleaseSettingsForm from '@/Components/mcq/McqResultReleaseSettingsForm.vue';

const props = defineProps({
    school: Object,
    exam: { type: Object, required: true },
    registrationCounts: { type: Object, default: () => ({}) },
    registrations: { type: Array, default: () => [] },
    proctorTotals: {
        type: Object,
        default: () => ({
            events: 0,
            students_with_signals: 0,
            by_type: { tab_hidden: 0, window_blur: 0, fullscreen_exit: 0, offline: 0 },
        }),
    },
    classCategories: { type: Array, default: () => [] },
    masterClasses: { type: Array, default: () => [] },
    gradeMasters: { type: Array, default: () => [] },
    schoolClasses: { type: Array, default: () => [] },
    certificateTemplates: { type: Array, default: () => [] },
    gradeBands: { type: Array, default: () => [] },
});

const PROCTOR_EVENT_LABELS = {
    tab_hidden: 'Tab hidden',
    window_blur: 'Window lost focus',
    fullscreen_exit: 'Exited fullscreen',
    offline: 'Went offline',
};

function formatProctorAt(value) {
    if (!value) return '';
    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
}

function proctorEventsTooltip(events) {
    if (!events?.length) return '';
    const counts = {};
    for (const e of events) {
        counts[e.type] = (counts[e.type] || 0) + 1;
    }
    const breakdown = Object.entries(counts)
        .map(([type, n]) => `${PROCTOR_EVENT_LABELS[type] || type}: ${n}`)
        .join('\n');
    const recent = events.slice(-5).map((e) => `${PROCTOR_EVENT_LABELS[e.type] || e.type} — ${formatProctorAt(e.occurred_at)}`).join('\n');
    return `${breakdown}\n\nRecent:\n${recent}`;
}

const gradeMasterOptions = computed(() => [
    { value: null, label: 'None' },
    ...props.gradeMasters.map((m) => ({
        value: m.id,
        label: m.is_default ? `${m.title} (default)` : m.title,
    })),
]);

function toLocalInput(value) {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '';
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

const release = props.exam.result_release ?? {};

const form = useForm({
    title: props.exam.title ?? '',
    code: props.exam.code ?? '',
    status: props.exam.status ?? 'draft',
    scheduled_at: toLocalInput(props.exam.scheduled_at),
    duration_minutes: props.exam.duration_minutes ?? 60,
    delivery_mode: props.exam.delivery_mode ?? 'online',
    grade_master_id: props.exam.grade_master_id ?? null,
    pass_mark: props.exam.pass_mark ?? null,
    exam_type: props.exam.exam_type ?? 'assessment',
    eligibility_config: props.exam.eligibility_config ?? {},
    shuffle_questions: props.exam.settings?.shuffle_questions ?? true,
    shuffle_options: props.exam.settings?.shuffle_options ?? true,
    multi_marking: props.exam.settings?.multi_marking ?? 'partial',
    require_fullscreen: !!props.exam.settings?.require_fullscreen,
    venue_keycode_join: !!props.exam.settings?.venue_keycode_join,
    allow_reattempt: !!props.exam.settings?.allow_reattempt,
    max_attempts: props.exam.settings?.max_attempts ?? 1,
    proctor_mode: props.exam.settings?.proctor_mode ?? 'log',
    proctor_max_tab_violations: props.exam.settings?.proctor_max_tab_violations ?? 3,
    result_visibility: release.result_visibility ?? 'score_grade',
    results_release_mode: release.results_release_mode ?? 'on_publish',
    results_auto_release_at: toLocalInput(release.results_auto_release_at),
    certificates_enabled: !!release.certificates_enabled,
    certificates_auto_issue: !!release.certificates_auto_issue,
    certificate_eligibility: release.certificate_eligibility ?? 'participation',
    certificate_eligible_grades: [...(release.certificate_eligible_grades ?? [])],
    certificate_template_id: release.certificate_template_id ?? props.exam.certificate_template_id ?? null,
});

const strictTabProctor = computed({
    get: () => form.proctor_mode === 'strict_tab',
    set: (on) => { form.proctor_mode = on ? 'strict_tab' : 'log'; },
});

const releaseSettings = reactive({
    result_visibility: form.result_visibility,
    results_release_mode: form.results_release_mode,
    results_auto_release_at: form.results_auto_release_at,
    certificates_enabled: form.certificates_enabled,
    certificates_auto_issue: form.certificates_auto_issue,
    certificate_eligibility: form.certificate_eligibility,
    certificate_eligible_grades: [...form.certificate_eligible_grades],
    certificate_template_id: form.certificate_template_id,
});

watch(releaseSettings, (val) => {
    Object.assign(form, val);
}, { deep: true });

const publishForm = useForm({ auto_enroll: true });
const enrollForm = useForm({});
const resultsForm = useForm({});
const unpublishForm = useForm({});
const certForm = useForm({});

function save() {
    form.put(`/school-admin/${props.school.id}/model-exams/${props.exam.id}`, { preserveScroll: true });
}

function publish(autoEnroll) {
    const paperCount = props.exam.paper?.total ?? 0;
    if (paperCount < 1) {
        window.alert('Add questions to the paper before publishing.');
        return;
    }
    const msg = autoEnroll
        ? `Publish this exam and auto-enroll eligible students? Paper has ${paperCount} question(s).`
        : `Publish this exam without enrolling? Paper has ${paperCount} question(s).`;
    if (!window.confirm(msg)) return;
    publishForm.auto_enroll = autoEnroll;
    publishForm.post(`/school-admin/${props.school.id}/model-exams/${props.exam.id}/publish`, { preserveScroll: true });
}

function enroll() {
    if (!window.confirm(`Enroll eligible students now? Currently ${props.registrationCounts.total ?? 0} enrolled.`)) return;
    enrollForm.post(`/school-admin/${props.school.id}/model-exams/${props.exam.id}/enroll-eligible`, { preserveScroll: true });
}

function releaseResults() {
    const submitted = props.registrationCounts.submitted ?? 0;
    if (!window.confirm(`Release results to students? ${submitted} submitted attempt(s). Visibility: ${form.result_visibility}.`)) return;
    resultsForm.post(`/school-admin/${props.school.id}/model-exams/${props.exam.id}/publish-results`, { preserveScroll: true });
}

function holdResults() {
    if (!window.confirm('Hold results? Students will no longer see scores until you release again.')) return;
    unpublishForm.post(`/school-admin/${props.school.id}/model-exams/${props.exam.id}/unpublish-results`, { preserveScroll: true });
}

function generateCertificates() {
    if (!window.confirm('Generate certificates for eligible students with released results?')) return;
    certForm.post(`/school-admin/${props.school.id}/model-exams/${props.exam.id}/certificates/generate`, { preserveScroll: true });
}
</script>
