<template>
    <SchoolAdminLayout title="Submission Teachers" :school="school" :show-header-title="false">
        <PageHeader title="Teacher records for membership" eyebrow="Membership"
                    description="Add teachers required for your annual Sahodaya submission. Submit when the list is complete." />

        <div class="max-w-3xl space-y-5">
            <MembershipWorkflowNav :school="school"
                                   :profile="profile"
                                   :registration="registration"
                                   current="teachers" />

            <div class="flex flex-wrap items-center gap-3">
                <TrackStatusPill :status="submission.teacher_status" />
                <span class="text-xs text-slate-500">{{ teachers.length }} teacher{{ teachers.length === 1 ? '' : 's' }} listed</span>
            </div>

            <div v-if="canEdit" class="flex gap-2">
                <button type="button" class="btn-secondary" :class="mode === 'single' ? 'ring-2 ring-[#041525]/20' : ''" @click="mode = 'single'">Add one</button>
                <button type="button" class="btn-secondary" :class="mode === 'bulk' ? 'ring-2 ring-[#041525]/20' : ''" @click="mode = 'bulk'">Add many</button>
            </div>

            <!-- Single add -->
            <form v-if="canEdit && mode === 'single'" @submit.prevent="add" class="card space-y-4">
                <h3 class="section-title text-base">Add teacher</h3>
                <FormGrid>
                    <FormField label="Full name" required :error="form.errors.name">
                        <input v-model="form.name" required class="field" placeholder="Teacher name">
                    </FormField>
                    <FormField label="Teaching type" :error="form.errors.teaching_type_id">
                        <select v-model="form.teaching_type_id" class="field">
                            <option value="">Select type</option>
                            <option v-for="t in teachingTypes" :key="t.id" :value="t.id">{{ t.label }}</option>
                        </select>
                    </FormField>
                </FormGrid>
                <FormField label="Subjects" :error="form.errors.subject_ids">
                    <SubjectPicker v-model="form.subject_ids" :subjects="subjects" />
                </FormField>
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary" :disabled="form.processing">Add teacher</button>
                </div>
            </form>

            <!-- Bulk add -->
            <form v-if="canEdit && mode === 'bulk'" @submit.prevent="addMany" class="card space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="section-title text-base">Add multiple teachers</h3>
                    <button type="button" class="text-xs font-semibold text-[#041525] hover:underline" @click="addRow">+ Add row</button>
                </div>
                <div v-for="(row, i) in bulkForm.teachers" :key="i" class="rounded-xl border border-slate-100 p-3 space-y-3">
                    <div class="flex items-start gap-3">
                        <div class="flex-1 space-y-3">
                            <FormGrid>
                                <FormField :label="`Teacher ${i + 1} name`" required>
                                    <input v-model="row.name" class="field" placeholder="Teacher name">
                                </FormField>
                                <FormField label="Teaching type">
                                    <select v-model="row.teaching_type_id" class="field">
                                        <option value="">Select type</option>
                                        <option v-for="t in teachingTypes" :key="t.id" :value="t.id">{{ t.label }}</option>
                                    </select>
                                </FormField>
                            </FormGrid>
                            <FormField label="Subjects">
                                <SubjectPicker v-model="row.subject_ids" :subjects="subjects" />
                            </FormField>
                        </div>
                        <button v-if="bulkForm.teachers.length > 1" type="button"
                                class="text-xs font-semibold text-red-600 hover:text-red-700 mt-6" @click="removeRow(i)">Remove</button>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary" :disabled="bulkForm.processing">Add {{ bulkForm.teachers.length }} teacher(s)</button>
                </div>
            </form>

            <div class="card card--flush overflow-hidden">
                <ul class="divide-y text-sm">
                    <li v-for="t in teachers" :key="t.id" class="flex items-center justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="font-medium text-slate-900">{{ t.name }}</p>
                            <p class="text-xs text-slate-500">
                                {{ (t.subject_labels && t.subject_labels.length ? t.subject_labels.join(', ') : (t.subject || 'No subject')) }}
                                · {{ t.teaching_type?.label || 'Type not set' }}
                            </p>
                        </div>
                        <button v-if="canEdit"
                                type="button"
                                class="text-xs font-semibold text-red-600 hover:text-red-700 shrink-0"
                                @click="remove(t)">
                            Remove
                        </button>
                    </li>
                    <li v-if="!teachers.length" class="px-4 py-8 text-center text-slate-400">
                        No teachers added yet. Use the form above to build your submission list.
                    </li>
                </ul>
            </div>

            <button v-if="canEdit"
                    type="button"
                    class="btn-primary"
                    :disabled="!teachers.length"
                    @click="submit">
                Submit teachers for Sahodaya review
            </button>
            <p v-else-if="submission.teacher_status === 'submitted'" class="text-sm text-amber-700">
                Awaiting Sahodaya approval…
            </p>
            <p v-else-if="submission.teacher_status === 'approved'" class="text-sm text-emerald-700">
                Teacher records approved.
            </p>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import FormField from '@/Components/ui/FormField.vue';
import FormGrid from '@/Components/ui/FormGrid.vue';
import MembershipWorkflowNav from '@/Components/school/MembershipWorkflowNav.vue';
import TrackStatusPill from '@/Components/ui/TrackStatusPill.vue';
import SubjectPicker from '@/Components/school/SubjectPicker.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useScrollToFirstError } from '@/composables/useScrollToFirstError.js';

const props = defineProps({
    school: Object,
    registration: Object,
    submission: Object,
    profile: { type: Object, default: null },
    teachers: { type: Array, default: () => [] },
    teachingTypes: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
});

const { scrollToFirstError } = useScrollToFirstError();

const canEdit = computed(() =>
    ['pending', 'rejected'].includes(props.submission?.teacher_status),
);

const mode = ref('single');

const form = useForm({ name: '', subject_ids: [], teaching_type_id: '' });

function newRow() {
    return { name: '', subject_ids: [], teaching_type_id: '' };
}

const bulkForm = useForm({ teachers: [newRow(), newRow(), newRow()] });

function addRow() {
    bulkForm.teachers.push(newRow());
}

function removeRow(i) {
    bulkForm.teachers.splice(i, 1);
}

function add() {
    form.post(`/school-admin/${props.school.id}/registration/teachers`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => scrollToFirstError(form.errors),
    });
}

function addMany() {
    bulkForm
        .transform((data) => ({
            teachers: data.teachers.filter((r) => r.name && r.name.trim() !== ''),
        }))
        .post(`/school-admin/${props.school.id}/registration/teachers/bulk`, {
            preserveScroll: true,
            onSuccess: () => {
                bulkForm.teachers = [newRow(), newRow(), newRow()];
            },
        });
}

function remove(t) {
    if (!confirm(`Remove ${t.name} from this submission?`)) return;
    router.delete(`/school-admin/${props.school.id}/registration/teachers/${t.id}`, { preserveScroll: true });
}

function submit() {
    if (!confirm(`Submit ${props.teachers.length} teacher(s) for Sahodaya review?`)) return;
    router.post(`/school-admin/${props.school.id}/registration/submit-track`, { track: 'teachers' });
}
</script>
