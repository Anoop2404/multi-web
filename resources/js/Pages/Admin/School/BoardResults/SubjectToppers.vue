<template>
    <SchoolAdminLayout title="Subject-Wise Toppers" :school="school" :show-header-title="false">
        <!-- TOP TOOLBAR & HEADER -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">
                    Subject-Wise Toppers Entry
                </h1>
                <p class="text-xs text-gray-500 mt-0.5">
                    Directly enter top rankers for Class XII CBSE subjects (English, Physics, Chemistry, Biology, Mathematics, Accounts, etc.)
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-xs font-bold px-3 py-1 bg-indigo-50 text-indigo-700 rounded-full border border-indigo-100 uppercase">
                    Class XII (AISSCE)
                </span>
            </div>
        </div>

        <div class="max-w-5xl space-y-6">
            <!-- WORKING ACADEMIC YEAR SELECTOR -->
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center font-bold text-sm">
                        🎯
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Selected Examination</p>
                        <p class="text-sm font-bold text-gray-800">
                            Class XII (AISSCE) — Subject-Wise Entry
                        </p>
                    </div>
                </div>

                <form @submit.prevent="onYearChange" class="flex items-center gap-3">
                    <label class="text-xs font-semibold text-gray-600 whitespace-nowrap">Academic Year:</label>
                    <select v-model="selectedYear" class="field text-xs py-1.5 w-48 font-semibold bg-white" @change="onYearChange">
                        <option v-for="ay in academicYearOptions" :key="ay.id" :value="ay.label">
                            {{ ay.label }}{{ ay.status === 'active' ? ' (Active)' : '' }}
                        </option>
                    </select>
                </form>
            </div>

            <!-- ADD SUBJECT TOPPER FORM -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-4">
                <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-gray-900 text-base">Add Subject Top Scorer</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Select subject name, enter student full name, CBSE roll number, and mark scored out of 100.</p>
                    </div>
                </div>

                <form @submit.prevent="submitSubjectTopper" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="form-label mb-1 font-semibold">Subject Name *</label>
                        <select v-model="selectedSubjectOption" required class="field text-sm bg-white" :disabled="!canEdit">
                            <option value="" disabled>Select Subject</option>
                            <option v-for="subj in masterSubjectList" :key="subj" :value="subj">{{ subj }}</option>
                            <option value="__custom__">+ Add Custom Subject...</option>
                        </select>

                        <input
                            v-if="selectedSubjectOption === '__custom__'"
                            v-model="customSubjectInput"
                            type="text"
                            required
                            class="field text-sm mt-2"
                            placeholder="Enter custom subject name..."
                            :disabled="!canEdit"
                        >
                    </div>
                    <div>
                        <label class="form-label mb-1 font-semibold">Student Name *</label>
                        <input v-model="subjectForm.name" type="text" required class="field text-sm" placeholder="Student full name" :disabled="!canEdit">
                    </div>
                    <div>
                        <label class="form-label mb-1 font-semibold">CBSE Roll No *</label>
                        <input v-model="subjectForm.roll_no" type="text" required class="field text-sm" placeholder="e.g. 11182743" :disabled="!canEdit">
                    </div>
                    <div>
                        <label class="form-label mb-1 font-semibold">Mark Scored (out of 100) *</label>
                        <input v-model.number="subjectForm.marks" type="number" min="0" max="100" required class="field text-sm font-bold text-emerald-700" placeholder="e.g. 99" :disabled="!canEdit">
                    </div>

                    <div class="sm:col-span-2 lg:col-span-4 flex justify-end pt-2">
                        <button v-if="canEdit" type="submit" class="btn-primary text-xs px-6 py-2.5 font-bold shadow-sm" :disabled="subjectForm.processing">
                            + Save Subject Topper
                        </button>
                    </div>
                </form>
            </div>

            <!-- COMMON PROOF DOCUMENT UPLOAD -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-4">
                <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-gray-900 text-sm uppercase tracking-wide">Common Result Proof Document</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Upload CBSE Tabulation Sheet or result proof PDF for verification.</p>
                    </div>
                </div>

                <div v-if="boardResult.result_pdf_path" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 flex items-center justify-between shadow-2xs">
                    <div class="flex items-center gap-2 text-xs font-semibold text-emerald-800">
                        <span>✓ Proof Attached</span>
                        <a :href="`/school-admin/${school.id}/board-results/${boardResult.id}/pdf`" target="_blank" class="underline text-indigo-600 hover:text-indigo-800 font-normal">View Attached Document ↗</a>
                    </div>
                    <span class="text-xs text-emerald-700 bg-emerald-100 px-3 py-1 rounded-full font-bold">Ready</span>
                </div>

                <input type="file" accept="application/pdf,image/png,image/jpeg,image/jpg,image/webp" class="field text-sm bg-white max-w-md" :disabled="!canEdit" @change="uploadProof($event.target.files[0])">
                <p class="text-[11px] text-gray-400">Accepts PDF, JPG, PNG, WEBP files up to 20MB.</p>
            </div>

            <!-- DISPLAY SAVED SUBJECT TOP PERFORMERS GRID -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                <div class="border-b border-gray-100 pb-3 mb-4">
                    <h3 class="font-bold text-gray-900 text-sm uppercase tracking-wide">Saved Subject Top Performers</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Highest scorers identified across Class XII subjects for {{ selectedYear }}.</p>
                </div>

                <div v-if="subjectWiseLeaders.length" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="row in subjectWiseLeaders" :key="row.subject"
                         class="rounded-xl border border-indigo-100 bg-gradient-to-br from-indigo-50/40 to-white p-4 shadow-xs">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 bg-indigo-100 px-2 py-0.5 rounded">
                                {{ row.subject }}
                            </span>
                            <span class="text-sm font-bold text-emerald-600">{{ row.marks }} / 100</span>
                        </div>
                        <p class="font-bold text-gray-900 text-sm mt-2">{{ row.name }}</p>
                        <p v-if="row.roll_no" class="text-xs text-gray-500 mt-0.5">CBSE Roll No: {{ row.roll_no }}</p>

                        <button v-if="canEdit" type="button" @click="removeSubjectTopper(row)" class="text-xs text-red-500 hover:text-red-700 font-semibold mt-3 flex items-center gap-1">
                            <span>🗑</span> Remove Subject Topper
                        </button>
                    </div>
                </div>

                <div v-else class="p-10 text-center text-gray-400 text-xs">
                    No subject-wise toppers recorded yet for {{ selectedYear }}. Use the form above to add subject leaders.
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { computed, ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    school:             Object,
    boardResult:        Object,
    academicYear:       String,
    academicYearOptions: { type: Array, default: () => [] },
    standardSubjects:   { type: Array, default: () => [] },
    subjectWiseLeaders: { type: Array, default: () => [] },
    canEdit:            { type: Boolean, default: true },
});

const pageTitle = computed(() => `Subject-Wise Toppers — ${props.academicYear}`);
const selectedYear = ref(props.academicYear);

const default23Subjects = [
    'English core', 'Hindi core', 'Hindi elective', 'Malayalam', 'Sanskrit',
    'Physics', 'Chemistry', 'Biology', 'Mathematics', 'Computer science',
    'Psychology', 'Informatics practices', 'History', 'Sociology',
    'Political science', 'Economics', 'Accountancy', 'Business Studies',
    'Home science', 'Fashion studies', 'Physical education', 'Business administration', 'KTPI'
];

const masterSubjectList = computed(() =>
    props.standardSubjects?.length ? props.standardSubjects : default23Subjects
);

function onYearChange() {
    router.get(`/school-admin/${props.school.id}/board-results/subject-toppers`, {
        academic_year: selectedYear.value,
    }, { preserveScroll: true });
}

// ── Subject Topper Form ──────────────────────────────────────────────────
const selectedSubjectOption = ref('');
const customSubjectInput = ref('');

const subjectForm = useForm({
    subject: '',
    name: '',
    roll_no: '',
    marks: '',
});

function submitSubjectTopper() {
    const finalSubject = selectedSubjectOption.value === '__custom__'
        ? customSubjectInput.value.trim()
        : selectedSubjectOption.value;

    if (!finalSubject || !subjectForm.name || subjectForm.marks === '') return;

    const existing = (props.boardResult.toppers ?? []).find(
        (t) => t.name.toLowerCase() === subjectForm.name.toLowerCase()
    );

    if (existing) {
        const currentSubjectMarks = { ...(existing.subject_marks ?? {}) };
        currentSubjectMarks[finalSubject] = subjectForm.marks;

        router.post(`/school-admin/${props.school.id}/board-results/${props.boardResult.id}/toppers/${existing.id}`, {
            ...existing,
            _method: 'put',
            subject_marks: currentSubjectMarks,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                subjectForm.reset();
                selectedSubjectOption.value = '';
                customSubjectInput.value = '';
            },
        });
    } else {
        const subjectMarks = {};
        subjectMarks[finalSubject] = subjectForm.marks;

        router.post(`/school-admin/${props.school.id}/board-results/${props.boardResult.id}/toppers/single`, {
            name: subjectForm.name,
            roll_no: subjectForm.roll_no,
            percentage: subjectForm.marks,
            marks_obtained: subjectForm.marks,
            total_marks: 100,
            subject_marks: subjectMarks,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                subjectForm.reset();
                selectedSubjectOption.value = '';
                customSubjectInput.value = '';
            },
        });
    }
}

function removeSubjectTopper(row) {
    if (!confirm(`Remove subject topper "${row.name}" for ${row.subject}?`)) return;

    const existing = (props.boardResult.toppers ?? []).find(
        (t) => t.name.toLowerCase() === row.name.toLowerCase()
    );

    if (!existing) return;

    const updatedSubjectMarks = { ...(existing.subject_marks ?? {}) };
    delete updatedSubjectMarks[row.subject];

    router.post(`/school-admin/${props.school.id}/board-results/${props.boardResult.id}/toppers/${existing.id}`, {
        ...existing,
        _method: 'put',
        subject_marks: updatedSubjectMarks,
    }, {
        preserveScroll: true,
    });
}

function uploadProof(file) {
    if (!file) return;
    const form = useForm({ result_pdf: file });
    form.post(`/school-admin/${props.school.id}/board-results/${props.boardResult.id}/upload-pdf`, {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>
