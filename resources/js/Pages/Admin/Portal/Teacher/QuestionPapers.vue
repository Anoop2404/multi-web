<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="Question Bank"
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
                        📄 Upload to Question Bank
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Upload exam papers or study material for school admin review & archival (PDF, Word, Images up to 20MB each).</p>
                </div>
            </div>

            <div v-if="!classes.length || !subjects.length" class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-xs font-medium text-amber-900 leading-relaxed">
                ⚠️ Your school hasn't set up any {{ !classes.length ? 'classes' : 'subjects' }} yet, so there's nothing to select. Contact your school administrator.
            </div>

            <form @submit.prevent="uploadPaper" class="grid sm:grid-cols-2 gap-4" enctype="multipart/form-data">
                <FormField label="Title" required :error="form.errors.title">
                    <input v-model="form.title" class="field" placeholder="e.g. First Term Mathematics Standard 10" required>
                </FormField>
                <FormField label="Exam / Term Name" :error="form.errors.exam_name">
                    <input v-model="form.exam_name" class="field" placeholder="e.g. Mid-Term / Annual Exam">
                </FormField>
                <FormField label="Class / Standard" required :error="form.errors.school_class_id">
                    <SearchableSelect v-model="form.school_class_id" :options="classes" :all-option="true" all-label="Select class" :required="true" />
                </FormField>
                <FormField label="Subject" required :error="form.errors.subject_id">
                    <SearchableSelect v-model="form.subject_id" :options="subjects" :all-option="true" all-label="Select subject" :required="true" />
                </FormField>
                <FormField label="Academic Year" required :error="form.errors.academic_year">
                    <SearchableSelect v-model="form.academic_year" :options="academicYears" :all-option="false" placeholder="Select academic year" :required="true" />
                </FormField>
                <FormField label="Files" required :error="form.errors.files">
                    <input ref="createFileInput" type="file" class="field" multiple accept=".pdf,.doc,.docx,.odt,.rtf,.jpg,.jpeg,.png" required @change="onFilesSelected">
                </FormField>
                <div v-if="selectedFiles.length" class="sm:col-span-2 flex flex-wrap gap-2">
                    <span v-for="(f, idx) in selectedFiles" :key="idx" class="text-[11px] font-mono bg-slate-100 text-slate-600 px-2 py-1 rounded-lg">
                        {{ f.name }}
                    </span>
                </div>
                <FormField label="Notes & Comments (optional)" class-extra="sm:col-span-2" :error="form.errors.description">
                    <textarea v-model="form.description" class="field text-xs" rows="2" placeholder="Optional notes for school administration"></textarea>
                </FormField>
                <div class="sm:col-span-2 flex justify-end">
                    <button class="btn-primary text-xs !min-h-0 !py-2.5 px-5 shadow-sm" :disabled="form.processing || !classes.length || !subjects.length">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        {{ form.processing ? 'Uploading…' : 'Upload' }}
                    </button>
                </div>
            </form>
        </section>

        <!-- Grouped Archive -->
        <section class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div>
                    <h2 class="section-title text-base font-bold text-slate-900 flex items-center gap-2">
                        📚 My Question Bank
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Grouped by class and subject. School administrators can review and access these uploads.</p>
                </div>
                <span class="text-xs font-semibold text-slate-500">Total: {{ papers.length }}</span>
            </div>

            <div v-if="classGroups.length" class="space-y-6">
                <div v-for="classGroup in classGroups" :key="classGroup.className" class="space-y-2">
                    <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                        🏫 {{ classGroup.className }}
                        <span class="text-[11px] font-semibold text-slate-400">({{ classGroup.count }})</span>
                    </h3>
                    <div class="space-y-2 pl-1">
                        <div v-for="subjectGroup in classGroup.subjects" :key="subjectGroup.subjectName" class="rounded-2xl border border-slate-200/90 overflow-hidden">
                            <button type="button" class="w-full flex items-center justify-between gap-3 px-4 py-2.5 bg-slate-50 hover:bg-slate-100 transition text-left"
                                    @click="toggleGroup(classGroup.className, subjectGroup.subjectName)">
                                <span class="text-xs font-bold text-slate-800">{{ subjectGroup.subjectName }}</span>
                                <span class="flex items-center gap-2 text-[11px] font-semibold text-slate-500">
                                    {{ subjectGroup.papers.length }} paper{{ subjectGroup.papers.length === 1 ? '' : 's' }}
                                    <svg class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': !isGroupCollapsed(classGroup.className, subjectGroup.subjectName) }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </span>
                            </button>

                            <div v-if="!isGroupCollapsed(classGroup.className, subjectGroup.subjectName)" class="p-3 space-y-3 bg-white">
                                <article v-for="paper in subjectGroup.papers" :key="paper.id" class="p-4 rounded-2xl border border-slate-200/90 bg-white shadow-sm space-y-3">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <h4 class="font-bold text-slate-900 text-sm leading-snug truncate">{{ paper.title }}</h4>
                                            <p class="text-xs font-medium text-slate-500 mt-0.5">
                                                {{ paper.academic_year }}
                                                <span v-if="paper.exam_name"> · {{ paper.exam_name }}</span>
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2 shrink-0">
                                            <label class="cursor-pointer inline-flex items-center gap-1 text-[11px] font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 px-2.5 py-1.5 rounded-xl transition">
                                                + Add files
                                                <input type="file" class="sr-only" multiple accept=".pdf,.doc,.docx,.odt,.rtf,.jpg,.jpeg,.png" @change="addFiles(paper, $event)">
                                            </label>
                                            <button type="button" class="text-[11px] font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 px-2.5 py-1.5 rounded-xl transition" @click="openEdit(paper)">
                                                Edit
                                            </button>
                                            <button type="button" class="text-[11px] font-bold text-red-600 bg-red-50 hover:bg-red-100 px-2.5 py-1.5 rounded-xl transition" @click="removePaper(paper)">
                                                Remove
                                            </button>
                                        </div>
                                    </div>

                                    <div v-if="paper.description" class="text-xs text-slate-500">{{ paper.description }}</div>

                                    <div class="flex flex-wrap gap-2">
                                        <div v-for="file in paper.files" :key="file.id" class="flex items-center gap-2 pl-3 pr-1.5 py-1.5 rounded-xl bg-blue-50/70 border border-blue-100 text-[11px]">
                                            <span class="font-mono text-slate-700 truncate max-w-[14rem]">{{ file.original_name }}</span>
                                            <span class="text-slate-400">{{ fileSize(file.file_size) }}</span>
                                            <a v-if="isPreviewable(file)" :href="previewUrl(paper, file)" target="_blank" rel="noopener" class="font-bold text-[#0f3d7a] hover:underline px-1">Preview</a>
                                            <a :href="downloadUrl(paper, file)" class="font-bold text-[#0f3d7a] hover:underline px-1">Download</a>
                                            <button type="button" class="w-5 h-5 rounded-full text-red-500 hover:bg-red-100 flex items-center justify-center" title="Remove file" @click="removeFile(paper, file)">
                                                ×
                                            </button>
                                        </div>
                                    </div>
                                </article>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <EmptyState v-else title="No question bank uploads yet" description="Upload the first file using the form above." icon="📄" />
        </section>

        <!-- Edit Modal -->
        <div v-if="editingPaper" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-xs" @click="closeEdit"></div>
            <form class="relative modal-shell w-full max-w-xl p-6 space-y-4 max-h-[90vh] overflow-y-auto rounded-3xl" @submit.prevent="saveEdit">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="font-bold text-lg text-slate-900">Edit Details</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Manage the attached files from the archive list.</p>
                </div>
                <FormField label="Title" required :error="editForm.errors.title">
                    <input v-model="editForm.title" class="field" required>
                </FormField>
                <div class="grid sm:grid-cols-2 gap-4">
                    <FormField label="Class" required :error="editForm.errors.school_class_id">
                        <SearchableSelect v-model="editForm.school_class_id" :options="classes" :all-option="false" placeholder="Select class" :required="true" />
                    </FormField>
                    <FormField label="Subject" required :error="editForm.errors.subject_id">
                        <SearchableSelect v-model="editForm.subject_id" :options="subjects" :all-option="false" placeholder="Select subject" :required="true" />
                    </FormField>
                    <FormField label="Academic Year" required :error="editForm.errors.academic_year">
                        <SearchableSelect v-model="editForm.academic_year" :options="academicYears" :all-option="false" placeholder="Select academic year" :required="true" />
                    </FormField>
                    <FormField label="Exam / Term" :error="editForm.errors.exam_name">
                        <input v-model="editForm.exam_name" class="field">
                    </FormField>
                </div>
                <FormField label="Notes" :error="editForm.errors.description">
                    <textarea v-model="editForm.description" class="field text-xs" rows="2"></textarea>
                </FormField>
                <div class="flex justify-end gap-3 pt-3 border-t border-slate-100">
                    <button type="button" class="btn-secondary text-xs !min-h-0 !py-2" @click="closeEdit">Cancel</button>
                    <button class="btn-primary text-xs !min-h-0 !py-2 px-4 shadow-sm" :disabled="editForm.processing">{{ editForm.processing ? 'Saving…' : 'Save Changes' }}</button>
                </div>
            </form>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { teacherPortalNavItems } from '@/support/teacherPortalNav.js';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    school: Object,
    teacher: Object,
    papers: Array,
    classes: Array,
    subjects: Array,
    academicYears: Array,
    currentAcademicYear: String,
});

const { confirm } = useConfirm();

const navItems = computed(() => teacherPortalNavItems(props.school.id));
const createFileInput = ref(null);
const editingPaper = ref(null);
const selectedFiles = ref([]);

const form = useForm({
    title: '', exam_name: '', school_class_id: '', subject_id: '',
    academic_year: props.currentAcademicYear, description: '', files: [],
});

const editForm = useForm({
    title: '', exam_name: '', school_class_id: '', subject_id: '',
    academic_year: props.currentAcademicYear, description: '',
});

function onFilesSelected(event) {
    const files = Array.from(event.target.files ?? []);
    form.files = files;
    selectedFiles.value = files;
}

function uploadPaper() {
    form.post(`/portal/teacher/${props.school.id}/question-papers`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.academic_year = props.currentAcademicYear;
            selectedFiles.value = [];
            if (createFileInput.value) createFileInput.value.value = '';
        },
    });
}

function addFiles(paper, event) {
    const files = Array.from(event.target.files ?? []);
    if (!files.length) return;
    const formData = new FormData();
    files.forEach(f => formData.append('files[]', f));
    router.post(`/portal/teacher/${props.school.id}/question-papers/${paper.id}/files`, formData, {
        forceFormData: true,
        preserveScroll: true,
    });
    event.target.value = '';
}

async function removeFile(paper, file) {
    if (!(await confirm({ message: `Remove “${file.original_name}”?` }))) return;
    router.delete(`/portal/teacher/${props.school.id}/question-papers/${paper.id}/files/${file.id}`, { preserveScroll: true });
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
}

function closeEdit() {
    editingPaper.value = null;
    editForm.reset();
}

function saveEdit() {
    editForm
        .transform(data => ({ ...data, _method: 'put' }))
        .post(`/portal/teacher/${props.school.id}/question-papers/${editingPaper.value.id}`, {
            preserveScroll: true,
            onSuccess: closeEdit,
            onFinish: () => editForm.transform(data => data),
        });
}

async function removePaper(paper) {
    if (!(await confirm({ message: `Remove “${paper.title}” and all its files?` }))) return;
    router.delete(`/portal/teacher/${props.school.id}/question-papers/${paper.id}`, { preserveScroll: true });
}

function downloadUrl(paper, file) {
    return `/portal/teacher/${props.school.id}/question-papers/${paper.id}/files/${file.id}/download`;
}

function previewUrl(paper, file) {
    return `/portal/teacher/${props.school.id}/question-papers/${paper.id}/files/${file.id}/preview`;
}

function isPreviewable(file) {
    const mime = file.mime_type || '';
    return mime.startsWith('image/') || mime === 'application/pdf';
}

function fileSize(bytes) {
    const value = Number(bytes || 0);
    if (!value) return 'Size unavailable';
    if (value < 1024 * 1024) return `${Math.max(1, Math.round(value / 1024))} KB`;
    return `${(value / 1024 / 1024).toFixed(1)} MB`;
}

// Sub-groups start collapsed; a key in this set has been explicitly expanded by the teacher.
const expandedGroups = ref(new Set());

function groupKey(className, subjectName) {
    return `${className}::${subjectName}`;
}

function isGroupCollapsed(className, subjectName) {
    return !expandedGroups.value.has(groupKey(className, subjectName));
}

function toggleGroup(className, subjectName) {
    const key = groupKey(className, subjectName);
    const next = new Set(expandedGroups.value);
    if (next.has(key)) next.delete(key);
    else next.add(key);
    expandedGroups.value = next;
}

const classGroups = computed(() => {
    const byClass = new Map();
    for (const paper of props.papers) {
        const className = paper.school_class?.name || paper.class_name || 'Class';
        if (!byClass.has(className)) byClass.set(className, new Map());
        const bySubject = byClass.get(className);
        const subjectName = paper.subject_name || 'Subject';
        if (!bySubject.has(subjectName)) bySubject.set(subjectName, []);
        bySubject.get(subjectName).push(paper);
    }

    return Array.from(byClass.entries()).map(([className, bySubject]) => ({
        className,
        count: Array.from(bySubject.values()).reduce((sum, papers) => sum + papers.length, 0),
        subjects: Array.from(bySubject.entries()).map(([subjectName, papers]) => ({ subjectName, papers })),
    }));
});
</script>
