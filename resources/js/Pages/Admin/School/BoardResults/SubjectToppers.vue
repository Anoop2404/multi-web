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

        <div class="max-w-6xl space-y-6">
            <!-- TOP CONTROLS: ACADEMIC YEAR, SUBJECT SELECTOR & SEARCH -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                            🎯
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Class XII Examination</p>
                            <p class="text-base font-bold text-gray-900">Subject-Wise Entry Portal</p>
                        </div>
                    </div>

                    <!-- SEARCH BAR -->
                    <div class="relative w-full sm:w-64">
                        <input
                            v-model="searchQuery"
                            type="text"
                            class="field text-xs pl-8 pr-3 py-2 w-full bg-gray-50 border-gray-200 focus:bg-white"
                            placeholder="Search subject or student..."
                        >
                        <span class="absolute left-2.5 top-2.5 text-gray-400 text-xs">🔍</span>
                    </div>
                </div>

                <!-- CONTROLS ROW: ACADEMIC YEAR & SUBJECT DROP-DOWN -->
                <div class="grid md:grid-cols-2 gap-4 items-end">
                    <div>
                        <label class="form-label mb-1 font-semibold text-xs text-gray-700">1. Academic Year *</label>
                        <select v-model="selectedYear" class="field text-sm font-semibold bg-white" @change="onYearChange">
                            <option v-for="ay in academicYearOptions" :key="ay.id" :value="ay.label">
                                {{ ay.label }}{{ ay.status === 'active' ? ' (Active)' : '' }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label mb-1 font-semibold text-xs text-gray-700">2. Select Subject *</label>
                        <select v-model="selectedSubjectOption" class="field text-sm bg-white font-medium" :disabled="!canEdit">
                            <option value="" disabled>-- Select Subject --</option>
                            <option v-for="subj in filteredSubjectOptions" :key="subj" :value="subj">{{ subj }}</option>
                            <option value="__custom__">+ Add Custom Subject...</option>
                        </select>

                        <input
                            v-if="selectedSubjectOption === '__custom__'"
                            v-model="customSubjectInput"
                            type="text"
                            class="field text-sm mt-2"
                            placeholder="Enter custom subject name..."
                            :disabled="!canEdit"
                        >
                    </div>
                </div>
            </div>

            <!-- MULTI-ROW SUBJECT TOPPER ENTRY FORM -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-4">
                <div class="border-b border-gray-100 pb-3 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-gray-900 text-base">
                                Enter Top Rankers for {{ activeSubjectName || 'Selected Subject' }}
                            </h3>
                            <span v-if="activeSubjectName" class="text-xs bg-indigo-100 text-indigo-800 font-bold px-2.5 py-0.5 rounded-full">
                                {{ activeSubjectName }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">
                            <strong>Student Name</strong>, <strong>Gender</strong> and <strong>Mark Scored</strong> are required. Roll No is optional.
                        </p>
                    </div>

                    <button
                        v-if="canEdit"
                        type="button"
                        @click="addRow"
                        class="btn-secondary text-xs font-bold px-3 py-1.5 flex items-center gap-1.5 text-indigo-600 bg-indigo-50 hover:bg-indigo-100 border-indigo-200"
                    >
                        <span>+</span> Add Row
                    </button>
                </div>

                <div v-if="!activeSubjectName" class="p-8 text-center text-gray-400 text-xs bg-gray-50 rounded-xl border border-dashed border-gray-200">
                    👆 Please select a Subject from the dropdown above to add toppers.
                </div>

                <form v-else @submit.prevent="saveAllRows" class="space-y-4">
                    <!-- Validation error display -->
                    <div v-if="rowError" class="rounded-lg bg-red-50 border border-red-200 px-4 py-2.5 text-xs text-red-700 font-semibold">
                        ⚠️ {{ rowError }}
                    </div>

                    <div class="overflow-x-auto border border-gray-200 rounded-xl">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-50 text-gray-600 font-semibold border-b border-gray-200">
                                <tr>
                                    <th class="py-2.5 px-3 w-10 text-center">#</th>
                                    <th class="py-2.5 px-3">Student Full Name <span class="text-red-500">*</span></th>
                                    <th class="py-2.5 px-3 w-36">Gender <span class="text-red-500">*</span></th>
                                    <th class="py-2.5 px-3 w-40">CBSE Roll No</th>
                                    <th class="py-2.5 px-3 w-40">Mark Scored (/100) <span class="text-red-500">*</span></th>
                                    <th class="py-2.5 px-3 w-14 text-center" v-if="canEdit">Del</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr
                                    v-for="(row, index) in rows"
                                    :key="index"
                                    class="hover:bg-gray-50/50 transition-colors"
                                    :class="{ 'bg-red-50/40 ring-1 ring-inset ring-red-200': rowHasError(row) }"
                                >
                                    <td class="py-2 px-3 text-center font-bold text-gray-400">
                                        {{ index + 1 }}
                                    </td>

                                    <!-- STUDENT NAME (required) -->
                                    <td class="py-2 px-3">
                                        <input
                                            v-model="row.name"
                                            type="text"
                                            required
                                            class="field text-xs py-1.5"
                                            :class="{ 'border-red-400': row._touched && !row.name.trim() }"
                                            placeholder="Student full name *"
                                            :disabled="!canEdit"
                                            @blur="row._touched = true"
                                        >
                                        <p v-if="row._touched && !row.name.trim()" class="text-[10px] text-red-500 mt-0.5">Name is required</p>
                                    </td>

                                    <!-- GENDER (required) -->
                                    <td class="py-2 px-3">
                                        <select
                                            v-model="row.gender"
                                            class="field text-xs py-1.5 bg-white"
                                            :class="{ 'border-red-400': row._touched && !row.gender }"
                                            :disabled="!canEdit"
                                            @blur="row._touched = true"
                                        >
                                            <option value="">— Select Gender —</option>
                                            <option value="male">♂ Male</option>
                                            <option value="female">♀ Female</option>
                                            <option value="other">Other</option>
                                        </select>
                                        <p v-if="row._touched && !row.gender" class="text-[10px] text-red-500 mt-0.5">Gender is required</p>
                                    </td>

                                    <!-- ROLL NO (optional) -->
                                    <td class="py-2 px-3">
                                        <input
                                            v-model="row.roll_no"
                                            type="text"
                                            class="field text-xs py-1.5"
                                            placeholder="e.g. 11182743 (optional)"
                                            :disabled="!canEdit"
                                        >
                                    </td>

                                    <!-- MARKS (required) -->
                                    <td class="py-2 px-3">
                                        <input
                                            v-model.number="row.marks"
                                            type="number"
                                            min="0"
                                            max="100"
                                            required
                                            class="field text-xs py-1.5 font-bold text-emerald-700"
                                            :class="{ 'border-red-400': row._touched && (row.marks === '' || row.marks === null) }"
                                            placeholder="e.g. 99 *"
                                            :disabled="!canEdit"
                                            @blur="row._touched = true"
                                        >
                                        <p v-if="row._touched && (row.marks === '' || row.marks === null)" class="text-[10px] text-red-500 mt-0.5">Mark is required</p>
                                    </td>

                                    <!-- DELETE ROW -->
                                    <td class="py-2 px-3 text-center" v-if="canEdit">
                                        <button
                                            type="button"
                                            @click="removeRow(index)"
                                            class="p-1 text-gray-400 hover:text-red-600 rounded transition-colors"
                                            title="Remove row"
                                            :disabled="rows.length <= 1"
                                        >
                                            🗑
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <button
                            v-if="canEdit"
                            type="button"
                            @click="addRow"
                            class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1"
                        >
                            <span>+ Add another student row</span>
                        </button>

                        <button
                            v-if="canEdit"
                            type="submit"
                            class="btn-primary text-xs px-6 py-2.5 font-bold shadow-sm"
                            :disabled="isSubmitting"
                        >
                            💾 Save {{ activeSubjectName }} Entries
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
                <div class="border-b border-gray-100 pb-3 mb-4 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-gray-900 text-sm uppercase tracking-wide">Saved Subject Top Performers</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Highest scorers identified across Class XII subjects for {{ selectedYear }}.</p>
                    </div>
                    <span class="text-xs font-semibold text-gray-400 bg-gray-100 px-2.5 py-1 rounded-full">
                        {{ filteredSubjectWiseLeaders.length }} record(s)
                    </span>
                </div>

                <div v-if="filteredSubjectWiseLeaders.length" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="row in filteredSubjectWiseLeaders"
                        :key="row.subject + '-' + row.name"
                        class="rounded-xl border border-indigo-100 bg-gradient-to-br from-indigo-50/40 to-white p-4 shadow-xs"
                    >
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 bg-indigo-100 px-2 py-0.5 rounded">
                                {{ row.subject }}
                            </span>
                            <span class="text-sm font-bold text-emerald-600">{{ row.marks }} / 100</span>
                        </div>

                        <p class="font-bold text-gray-900 text-sm mt-2">{{ row.name }}</p>

                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1">
                            <!-- GENDER BADGE -->
                            <span
                                v-if="row.gender"
                                class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                                :class="{
                                    'bg-blue-100 text-blue-700': row.gender === 'male',
                                    'bg-pink-100 text-pink-700': row.gender === 'female',
                                    'bg-gray-100 text-gray-700': row.gender === 'other',
                                }"
                            >
                                {{ row.gender === 'male' ? '♂ Male' : row.gender === 'female' ? '♀ Female' : 'Other' }}
                            </span>
                            <span v-if="row.roll_no" class="text-xs text-gray-500">Roll: {{ row.roll_no }}</span>
                        </div>

                        <button v-if="canEdit" type="button" @click="removeSubjectTopper(row)" class="text-xs text-red-500 hover:text-red-700 font-semibold mt-3 flex items-center gap-1">
                            <span>🗑</span> Remove
                        </button>
                    </div>
                </div>

                <div v-else class="p-10 text-center text-gray-400 text-xs">
                    No subject-wise toppers found {{ searchQuery ? 'matching "' + searchQuery + '"' : 'recorded yet for ' + selectedYear }}.
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { computed, ref, watch } from 'vue';
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

const selectedYear = ref(props.academicYear);
const searchQuery = ref('');
const rowError = ref('');

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

const filteredSubjectOptions = computed(() => {
    if (!searchQuery.value.trim()) return masterSubjectList.value;
    const q = searchQuery.value.toLowerCase();
    return masterSubjectList.value.filter(s => s.toLowerCase().includes(q));
});

const filteredSubjectWiseLeaders = computed(() => {
    if (!searchQuery.value.trim()) return props.subjectWiseLeaders;
    const q = searchQuery.value.toLowerCase();
    return props.subjectWiseLeaders.filter(
        row => row.subject?.toLowerCase().includes(q)
            || row.name?.toLowerCase().includes(q)
            || row.roll_no?.toLowerCase().includes(q)
            || row.gender?.toLowerCase().includes(q)
    );
});

function onYearChange() {
    router.get(`/school-admin/${props.school.id}/board-results/subject-toppers`, {
        academic_year: selectedYear.value,
    }, { preserveScroll: true });
}

// ── Multi-Row Subject Topper Form ─────────────────────────────────────────
const selectedSubjectOption = ref('');
const customSubjectInput = ref('');
const isSubmitting = ref(false);

const activeSubjectName = computed(() => {
    if (selectedSubjectOption.value === '__custom__') {
        return customSubjectInput.value.trim();
    }
    return selectedSubjectOption.value;
});

function blankRow() {
    return { name: '', gender: '', roll_no: '', marks: '', _touched: false };
}

const rows = ref([blankRow()]);

function addRow() {
    rows.value.push(blankRow());
}

function removeRow(index) {
    if (rows.value.length > 1) {
        rows.value.splice(index, 1);
    }
}

function rowHasError(row) {
    return row._touched && (
        !row.name.trim() ||
        !row.gender ||
        row.marks === '' || row.marks === null
    );
}

// Pre-fill existing entries for selected subject
watch([selectedSubjectOption, customSubjectInput], () => {
    const subj = activeSubjectName.value;
    rowError.value = '';
    if (!subj) return;

    const existingForSubject = props.subjectWiseLeaders.filter(
        leader => leader.subject?.toLowerCase() === subj.toLowerCase()
    );

    if (existingForSubject.length) {
        rows.value = existingForSubject.map(item => ({
            name: item.name || '',
            gender: item.gender || '',
            roll_no: item.roll_no || '',
            marks: item.marks ?? '',
            _touched: false,
        }));
    } else {
        rows.value = [blankRow()];
    }
});

async function saveAllRows() {
    const subj = activeSubjectName.value;
    if (!subj) return;

    rowError.value = '';

    // Mark all rows as touched to show validation UI
    rows.value.forEach(r => { r._touched = true; });

    // Validate: name + gender + marks required, roll_no optional
    const incompleteRows = rows.value.filter(
        r => !r.name.trim() || !r.gender || r.marks === '' || r.marks === null
    );
    if (incompleteRows.length) {
        rowError.value = `${incompleteRows.length} row(s) are incomplete. Student Name, Gender and Mark Scored are required.`;
        return;
    }

    const validRows = rows.value.filter(
        r => r.name.trim() && r.gender && (r.marks !== '' && r.marks !== null)
    );
    if (!validRows.length) {
        rowError.value = 'Add at least one student with a name, gender and mark scored.';
        return;
    }

    isSubmitting.value = true;

    for (const r of validRows) {
        const existing = (props.boardResult.toppers ?? []).find(
            (t) => t.name.toLowerCase() === r.name.trim().toLowerCase()
        );

        if (existing) {
            const currentSubjectMarks = { ...(existing.subject_marks ?? {}) };
            currentSubjectMarks[subj] = r.marks;

            await new Promise((resolve) => {
                router.put(`/school-admin/${props.school.id}/board-results/${props.boardResult.id}/toppers/${existing.id}`, {
                    ...existing,
                    gender: r.gender || null,
                    roll_no: r.roll_no || null,
                    subject_marks: currentSubjectMarks,
                }, {
                    preserveScroll: true,
                    onFinish: resolve,
                });
            });
        } else {
            const subjectMarks = {};
            subjectMarks[subj] = r.marks;

            await new Promise((resolve) => {
                router.post(`/school-admin/${props.school.id}/board-results/${props.boardResult.id}/toppers/single`, {
                    name: r.name.trim(),
                    gender: r.gender || null,
                    roll_no: r.roll_no?.trim() || null,
                    percentage: r.marks,
                    marks_obtained: r.marks,
                    total_marks: 100,
                    subject_marks: subjectMarks,
                }, {
                    preserveScroll: true,
                    onFinish: resolve,
                });
            });
        }
    }

    isSubmitting.value = false;
}

function removeSubjectTopper(row) {
    if (!confirm(`Remove subject topper "${row.name}" for ${row.subject}?`)) return;

    const existing = (props.boardResult.toppers ?? []).find(
        (t) => t.name.toLowerCase() === row.name.toLowerCase()
    );

    if (!existing) return;

    const updatedSubjectMarks = { ...(existing.subject_marks ?? {}) };
    delete updatedSubjectMarks[row.subject];

    router.put(`/school-admin/${props.school.id}/board-results/${props.boardResult.id}/toppers/${existing.id}`, {
        ...existing,
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
