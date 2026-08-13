<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="Question Papers"
        :subtitle="`${teacher.name} · ${school.name}`"
        accent="indigo"
        :nav-items="navItems"
    >
        <section class="card mb-5">
            <div class="mb-4">
                <h2 class="font-semibold text-slate-900">Upload a question paper</h2>
                <p class="text-sm text-slate-500 mt-1">Choose the class and subject, then upload a PDF, Word document, image, ODT, or RTF file (maximum 20 MB).</p>
            </div>

            <div v-if="!classes.length || !subjects.length" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 mb-4">
                Your teacher profile needs at least one subject and an available class before you can upload. Ask the school administrator to update your teacher record.
            </div>

            <form @submit.prevent="uploadPaper" class="grid md:grid-cols-2 gap-4" enctype="multipart/form-data">
                <FormField label="Paper title" required :error="form.errors.title">
                    <input v-model="form.title" class="field" placeholder="Example: First Term Mathematics" required>
                </FormField>
                <FormField label="Exam / term" :error="form.errors.exam_name">
                    <input v-model="form.exam_name" class="field" placeholder="Example: First Term">
                </FormField>
                <FormField label="Class" required :error="form.errors.school_class_id">
                    <select v-model="form.school_class_id" class="field" required>
                        <option value="">Select class</option>
                        <option v-for="item in classes" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </select>
                </FormField>
                <FormField label="Subject" required :error="form.errors.subject_id">
                    <select v-model="form.subject_id" class="field" required>
                        <option value="">Select subject</option>
                        <option v-for="item in subjects" :key="item.id" :value="item.id">{{ item.label }}</option>
                    </select>
                </FormField>
                <FormField label="Academic year" required :error="form.errors.academic_year">
                    <select v-model="form.academic_year" class="field" required>
                        <option v-for="year in academicYears" :key="year" :value="year">{{ year }}</option>
                    </select>
                </FormField>
                <FormField label="Question paper file" required :error="form.errors.file">
                    <input ref="createFileInput" type="file" class="field" accept=".pdf,.doc,.docx,.odt,.rtf,.jpg,.jpeg,.png" required @change="form.file = $event.target.files[0] ?? null">
                </FormField>
                <FormField label="Notes" class-extra="md:col-span-2" :error="form.errors.description">
                    <textarea v-model="form.description" class="field" rows="2" placeholder="Optional notes for the school administrator"></textarea>
                </FormField>
                <div class="md:col-span-2 flex justify-end">
                    <button class="btn-primary" :disabled="form.processing || !classes.length || !subjects.length">
                        {{ form.processing ? 'Uploading…' : 'Upload paper' }}
                    </button>
                </div>
            </form>
        </section>

        <section class="card card--flush overflow-hidden">
            <div class="p-4 border-b">
                <h2 class="font-semibold text-slate-900">My uploaded papers</h2>
                <p class="text-sm text-slate-500">Only you can edit or remove these papers. School administrators can view and download them.</p>
            </div>
            <div v-if="papers.data.length" class="divide-y">
                <article v-for="paper in papers.data" :key="paper.id" class="p-4 flex flex-col sm:flex-row sm:items-center gap-4">
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-slate-900 truncate">{{ paper.title }}</h3>
                        <p class="text-sm text-slate-600 mt-1">
                            {{ paper.school_class?.name || paper.class_name || 'Class' }} · {{ paper.subject_name }} · {{ paper.academic_year }}
                            <span v-if="paper.exam_name"> · {{ paper.exam_name }}</span>
                        </p>
                        <p class="text-xs text-slate-400 mt-1 truncate">{{ paper.original_name }} · {{ fileSize(paper.file_size) }} · Uploaded {{ formatDate(paper.created_at) }}</p>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <a :href="downloadUrl(paper)" class="text-sm font-semibold text-indigo-700">Download</a>
                        <button type="button" class="text-sm font-semibold text-slate-700" @click="openEdit(paper)">Edit</button>
                        <button type="button" class="text-sm font-semibold text-red-600" @click="removePaper(paper)">Remove</button>
                    </div>
                </article>
            </div>
            <EmptyState v-else title="No question papers yet" description="Upload the first paper using the form above." icon="📄" class="py-10" />
        </section>

        <div v-if="editingPaper" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/60" @click="closeEdit"></div>
            <form class="relative modal-shell w-full max-w-xl p-6 space-y-4 max-h-[90vh] overflow-y-auto" enctype="multipart/form-data" @submit.prevent="saveEdit">
                <div>
                    <h2 class="font-bold text-lg">Edit question paper</h2>
                    <p class="text-sm text-slate-500">Leave the file empty to keep the current document.</p>
                </div>
                <FormField label="Paper title" required :error="editForm.errors.title">
                    <input v-model="editForm.title" class="field" required>
                </FormField>
                <div class="grid sm:grid-cols-2 gap-4">
                    <FormField label="Class" required :error="editForm.errors.school_class_id">
                        <select v-model="editForm.school_class_id" class="field" required>
                            <option v-for="item in classes" :key="item.id" :value="item.id">{{ item.name }}</option>
                        </select>
                    </FormField>
                    <FormField label="Subject" required :error="editForm.errors.subject_id">
                        <select v-model="editForm.subject_id" class="field" required>
                            <option v-for="item in subjects" :key="item.id" :value="item.id">{{ item.label }}</option>
                        </select>
                    </FormField>
                    <FormField label="Academic year" required :error="editForm.errors.academic_year">
                        <select v-model="editForm.academic_year" class="field" required>
                            <option v-for="year in academicYears" :key="year" :value="year">{{ year }}</option>
                        </select>
                    </FormField>
                    <FormField label="Exam / term" :error="editForm.errors.exam_name">
                        <input v-model="editForm.exam_name" class="field">
                    </FormField>
                </div>
                <FormField label="Replace file" :error="editForm.errors.file">
                    <input type="file" class="field" accept=".pdf,.doc,.docx,.odt,.rtf,.jpg,.jpeg,.png" @change="editForm.file = $event.target.files[0] ?? null">
                </FormField>
                <FormField label="Notes" :error="editForm.errors.description">
                    <textarea v-model="editForm.description" class="field" rows="2"></textarea>
                </FormField>
                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-secondary" @click="closeEdit">Cancel</button>
                    <button class="btn-primary" :disabled="editForm.processing">{{ editForm.processing ? 'Saving…' : 'Save changes' }}</button>
                </div>
            </form>
        </div>

        <div v-if="papers.links?.length > 3" class="flex justify-center gap-1 mt-5">
            <Link v-for="link in papers.links" :key="link.label" :href="link.url || '#'" preserve-scroll
                  class="px-3 py-1.5 rounded text-xs font-medium"
                  :class="link.active ? 'bg-indigo-700 text-white' : (link.url ? 'bg-white text-slate-700 border' : 'text-slate-300 pointer-events-none')"
                  v-html="link.label" />
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { teacherPortalNavItems } from '@/support/teacherPortalNav.js';

const props = defineProps({
    school: Object,
    teacher: Object,
    papers: Object,
    classes: Array,
    subjects: Array,
    academicYears: Array,
    currentAcademicYear: String,
});

const navItems = computed(() => teacherPortalNavItems(props.school.id));
const createFileInput = ref(null);
const editingPaper = ref(null);

const form = useForm({
    title: '', exam_name: '', school_class_id: '', subject_id: '',
    academic_year: props.currentAcademicYear, description: '', file: null,
});

const editForm = useForm({
    title: '', exam_name: '', school_class_id: '', subject_id: '',
    academic_year: props.currentAcademicYear, description: '', file: null,
});

function uploadPaper() {
    form.post(`/portal/teacher/${props.school.id}/question-papers`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.academic_year = props.currentAcademicYear;
            if (createFileInput.value) createFileInput.value.value = '';
        },
    });
}

function openEdit(paper) {
    editingPaper.value = paper;
    editForm.clearErrors();
    editForm.title = paper.title;
    editForm.exam_name = paper.exam_name || '';
    editForm.school_class_id = paper.school_class_id;
    editForm.subject_id = paper.subject_id;
    editForm.academic_year = paper.academic_year;
    editForm.description = paper.description || '';
    editForm.file = null;
}

function closeEdit() {
    editingPaper.value = null;
    editForm.reset();
}

function saveEdit() {
    editForm
        .transform(data => ({ ...data, _method: 'put' }))
        .post(`/portal/teacher/${props.school.id}/question-papers/${editingPaper.value.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: closeEdit,
            onFinish: () => editForm.transform(data => data),
        });
}

function removePaper(paper) {
    if (!confirm(`Remove “${paper.title}”?`)) return;
    router.delete(`/portal/teacher/${props.school.id}/question-papers/${paper.id}`, { preserveScroll: true });
}

function downloadUrl(paper) {
    return `/portal/teacher/${props.school.id}/question-papers/${paper.id}/download`;
}

function fileSize(bytes) {
    const value = Number(bytes || 0);
    if (!value) return 'Size unavailable';
    if (value < 1024 * 1024) return `${Math.max(1, Math.round(value / 1024))} KB`;
    return `${(value / 1024 / 1024).toFixed(1)} MB`;
}

function formatDate(value) {
    return value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value)) : '';
}
</script>
