<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="Question Papers Archive"
        :subtitle="`${teacher.name} · ${school.name}`"
        accent="navy"
        :nav-items="navItems"
        :avatar-url="teacher?.photo_url"
        show-avatar-placeholder
    >
        <!-- Upload Card -->
        <section class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm mb-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="section-title text-base font-bold text-slate-900 flex items-center gap-2">
                        📄 Upload Question Paper
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Upload exam papers for school admin review & archival (PDF, Word, Images up to 20MB).</p>
                </div>
            </div>

            <div v-if="!classes.length || !subjects.length" class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-xs font-medium text-amber-900 leading-relaxed">
                ⚠️ Your school hasn't set up any {{ !classes.length ? 'classes' : 'subjects' }} yet, so there's nothing to select. Contact your school administrator.
            </div>

            <form @submit.prevent="uploadPaper" class="grid sm:grid-cols-2 gap-4" enctype="multipart/form-data">
                <FormField label="Paper Title" required :error="form.errors.title">
                    <input v-model="form.title" class="field" placeholder="e.g. First Term Mathematics Standard 10" required>
                </FormField>
                <FormField label="Exam / Term Name" :error="form.errors.exam_name">
                    <input v-model="form.exam_name" class="field" placeholder="e.g. Mid-Term / Annual Exam">
                </FormField>
                <FormField label="Class / Standard" required :error="form.errors.school_class_id">
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
                <FormField label="Academic Year" required :error="form.errors.academic_year">
                    <select v-model="form.academic_year" class="field" required>
                        <option v-for="year in academicYears" :key="year" :value="year">{{ year }}</option>
                    </select>
                </FormField>
                <FormField label="Question Paper File" required :error="form.errors.file">
                    <input ref="createFileInput" type="file" class="field" accept=".pdf,.doc,.docx,.odt,.rtf,.jpg,.jpeg,.png" required @change="form.file = $event.target.files[0] ?? null">
                </FormField>
                <FormField label="Notes & Comments (optional)" class-extra="sm:col-span-2" :error="form.errors.description">
                    <textarea v-model="form.description" class="field text-xs" rows="2" placeholder="Optional notes for school administration"></textarea>
                </FormField>
                <div class="sm:col-span-2 flex justify-end">
                    <button class="btn-primary text-xs !min-h-0 !py-2.5 px-5 shadow-sm" :disabled="form.processing || !classes.length || !subjects.length">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        {{ form.processing ? 'Uploading…' : 'Upload Question Paper' }}
                    </button>
                </div>
            </form>
        </section>

        <!-- Uploaded Papers List -->
        <section class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="section-title text-base font-bold text-slate-900 flex items-center gap-2">
                        📚 My Uploaded Papers
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">School administrators can review and access these uploaded documents.</p>
                </div>
                <span class="text-xs font-semibold text-slate-500">Total: {{ papers.data?.length ?? 0 }}</span>
            </div>

            <div v-if="papers.data?.length" class="space-y-3">
                <article v-for="paper in papers.data" :key="paper.id" class="p-4 rounded-2xl border border-slate-200/90 bg-white shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4 transition hover:border-[#0f3d7a]/30">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="h-9 w-9 rounded-xl bg-blue-50 text-[#0f3d7a] flex items-center justify-center font-bold text-sm shrink-0">
                                📄
                            </span>
                            <div class="min-w-0">
                                <h3 class="font-bold text-slate-900 text-base leading-snug truncate">{{ paper.title }}</h3>
                                <p class="text-xs font-medium text-slate-600 mt-0.5">
                                    {{ paper.school_class?.name || paper.class_name || 'Class' }} · {{ paper.subject_name }} · {{ paper.academic_year }}
                                    <span v-if="paper.exam_name" class="text-slate-500"> · {{ paper.exam_name }}</span>
                                </p>
                            </div>
                        </div>

                        <p class="text-[11px] text-slate-400 mt-2 truncate font-mono">
                            {{ paper.original_name }} · {{ fileSize(paper.file_size) }} · Uploaded {{ formatDate(paper.created_at) }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <a :href="downloadUrl(paper)" class="inline-flex items-center gap-1 text-xs font-bold text-white bg-[#0f3d7a] hover:bg-[#041525] px-3.5 py-1.5 rounded-xl transition shadow-sm">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Download
                        </a>
                        <button type="button" class="text-xs font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-xl transition" @click="openEdit(paper)">
                            Edit
                        </button>
                        <button type="button" class="text-xs font-bold text-red-600 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-xl transition" @click="removePaper(paper)">
                            Remove
                        </button>
                    </div>
                </article>
            </div>
            <EmptyState v-else title="No question papers yet" description="Upload the first paper using the form above." icon="📄" />
        </section>

        <!-- Edit Modal -->
        <div v-if="editingPaper" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-xs" @click="closeEdit"></div>
            <form class="relative modal-shell w-full max-w-xl p-6 space-y-4 max-h-[90vh] overflow-y-auto rounded-3xl" enctype="multipart/form-data" @submit.prevent="saveEdit">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="font-bold text-lg text-slate-900">Edit Question Paper Details</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Leave file field empty to keep current uploaded document.</p>
                </div>
                <FormField label="Paper Title" required :error="editForm.errors.title">
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
                    <FormField label="Academic Year" required :error="editForm.errors.academic_year">
                        <select v-model="editForm.academic_year" class="field" required>
                            <option v-for="year in academicYears" :key="year" :value="year">{{ year }}</option>
                        </select>
                    </FormField>
                    <FormField label="Exam / Term" :error="editForm.errors.exam_name">
                        <input v-model="editForm.exam_name" class="field">
                    </FormField>
                </div>
                <FormField label="Replace File (optional)" :error="editForm.errors.file">
                    <input type="file" class="field" accept=".pdf,.doc,.docx,.odt,.rtf,.jpg,.jpeg,.png" @change="editForm.file = $event.target.files[0] ?? null">
                </FormField>
                <FormField label="Notes" :error="editForm.errors.description">
                    <textarea v-model="editForm.description" class="field text-xs" rows="2"></textarea>
                </FormField>
                <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
                    <button type="button" class="btn-secondary text-xs !min-h-0 !py-2" @click="closeEdit">Cancel</button>
                    <button class="btn-primary text-xs !min-h-0 !py-2 px-4 shadow-sm" :disabled="editForm.processing">{{ editForm.processing ? 'Saving…' : 'Save Changes' }}</button>
                </div>
            </form>
        </div>

        <div v-if="papers.links?.length > 3" class="flex justify-center gap-1 mt-5">
            <Link v-for="link in papers.links" :key="link.label" :href="link.url || '#'" preserve-scroll
                  class="pagination-link"
                  :class="link.active ? 'pagination-link--active' : (!link.url && 'opacity-40 pointer-events-none')"
                  v-html="link.label" />
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { teacherPortalNavItems } from '@/support/teacherPortalNav.js';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    school: Object,
    teacher: Object,
    papers: Object,
    classes: Array,
    subjects: Array,
    academicYears: Array,
    currentAcademicYear: String,
});

const { confirm } = useConfirm();

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

async function removePaper(paper) {
    if (!(await confirm({ message: `Remove “${paper.title}”?` }))) return;
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

