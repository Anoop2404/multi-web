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
            </div>
        </div>

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
            <div class="border-t border-slate-100 pt-4">
                <McqEligibilityPicker v-model="form.eligibility_config" :class-categories="classCategories" :master-classes="masterClasses" />
            </div>
            <FormActions>
                <button type="submit" class="btn-primary" :disabled="form.processing">Save</button>
            </FormActions>
        </form>

        <div class="card overflow-hidden p-0">
            <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-semibold text-sm">Enrolled students</h3>
                <span class="text-xs text-slate-500">Showing up to 100</span>
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
                    </tr>
                </tbody>
            </table>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import McqEligibilityPicker from '@/Components/sahodaya/McqEligibilityPicker.vue';

const props = defineProps({
    school: Object,
    exam: { type: Object, required: true },
    registrationCounts: { type: Object, default: () => ({}) },
    registrations: { type: Array, default: () => [] },
    classCategories: { type: Array, default: () => [] },
    masterClasses: { type: Array, default: () => [] },
    gradeMasters: { type: Array, default: () => [] },
    schoolClasses: { type: Array, default: () => [] },
});

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
});

const publishForm = useForm({ auto_enroll: true });
const enrollForm = useForm({});
const resultsForm = useForm({});
const unpublishForm = useForm({});

function save() {
    form.put(`/school-admin/${props.school.id}/model-exams/${props.exam.id}`, { preserveScroll: true });
}

function publish(autoEnroll) {
    publishForm.auto_enroll = autoEnroll;
    publishForm.post(`/school-admin/${props.school.id}/model-exams/${props.exam.id}/publish`, { preserveScroll: true });
}

function enroll() {
    enrollForm.post(`/school-admin/${props.school.id}/model-exams/${props.exam.id}/enroll-eligible`, { preserveScroll: true });
}

function releaseResults() {
    resultsForm.post(`/school-admin/${props.school.id}/model-exams/${props.exam.id}/publish-results`, { preserveScroll: true });
}

function holdResults() {
    unpublishForm.post(`/school-admin/${props.school.id}/model-exams/${props.exam.id}/unpublish-results`, { preserveScroll: true });
}
</script>
