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
            <!-- TOP CONTROLS: ACADEMIC YEAR, SUBJECT SELECTOR & SEARCH -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white flex items-center justify-center font-bold text-lg shadow-sm">
                            🎯
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Class XII Examination</p>
                            <p class="text-base font-bold text-gray-900">
                                Subject-Wise Entry Portal
                            </p>
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
                        <div class="flex gap-2">
                            <select v-model="selectedSubjectOption" class="field text-sm bg-white font-medium" :disabled="!canEdit">
                                <option value="" disabled>-- Select Subject --</option>
                                <option v-for="subj in filteredSubjectOptions" :key="subj" :value="subj">{{ subj }}</option>
                                <option value="__custom__">+ Add Custom Subject...</option>
                            </select>
                        </div>

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
                            <h3 class="font-bold text-gray-900 text-base">Enter Top Rankers for {{ activeSubjectName || 'Selected Subject' }}</h3>
                            <span v-if="activeSubjectName" class="text-xs bg-indigo-100 text-indigo-800 font-bold px-2.5 py-0.5 rounded-full">
                                {{ activeSubjectName }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">Add one or multiple students scoring top marks in {{ activeSubjectName || 'this subject' }}.</p>
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
                    <div class="overflow-x-auto border border-gray-200 rounded-xl">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-50 text-gray-600 font-semibold border-b border-gray-200">
                                <tr>
                                    <th class="py-2.5 px-3 w-12 text-center">#</th>
                                    <th class="py-2.5 px-3">Student Full Name *</th>
                                    <th class="py-2.5 px-3 w-48">CBSE Roll No *</th>
                                    <th class="py-2.5 px-3 w-40">Mark Scored (out of 100) *</th>
                                    <th class="py-2.5 px-3 w-16 text-center" v-if="canEdit">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr v-for="(row, index) in rows" :key="index" class="hover:bg-gray-50/50">
                                    <td class="py-2 px-3 text-center font-bold text-gray-400">
                                        {{ index + 1 }}
                                    </td>
                                    <td class="py-2 px-3">
                                        <input
                                            v-model="row.name"
                                            type="text"
                                            required
                                            class="field text-xs py-1.5"
                                            placeholder="Student full name"
                                            :disabled="!canEdit"
                                        >
                                    </td>
                                    <td class="py-2 px-3">
                                        <input
                                            v-model="row.roll_no"
                                            type="text"
                                            required
                                            class="field text-xs py-1.5"
                                            placeholder="e.g. 11182743"
                                            :disabled="!canEdit"
                                        >
                                    </td>
                                    <td class="py-2 px-3">
                                        <input
                                            v-model.number="row.marks"
                                            type="number"
                                            min="0"
                                            max="100"
                                            required
                                            class="field text-xs py-1.5 font-bold text-emerald-700"
                                            placeholder="e.g. 99"
                                            :disabled="!canEdit"
                                        >
                                    </td>
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
                    <div v-for="row in filteredSubjectWiseLeaders" :key="row.subject + '-' + row.name"
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

const pageTitle = computed(() => `Subject-Wise Toppers — ${props.academicYear}`);
const selectedYear = ref(props.academicYear);
const searchQuery = ref('');

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
        row => row.subject?.toLowerCase().includes(q) || row.name?.toLowerCase().includes(q) || row.roll_no?.toLowerCase().includes(q)
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

const rows = ref([
    { name: '', roll_no: '', marks: '' },
]);

function addRow() {
    rows.value.push({ name: '', roll_no: '', marks: '' });
}

function removeRow(index) {
    if (rows.value.length > 1) {
        rows.value.splice(index, 1);
    }
}

// Pre-fill existing entries for selected subject if available
watch([selectedSubjectOption, customSubjectInput], () => {
    const subj = activeSubjectName.value;
    if (!subj) return;

    const existingForSubject = props.subjectWiseLeaders.filter(
        leader => leader.subject?.toLowerCase() === subj.toLowerCase()
    );

    if (existingForSubject.length) {
        rows.value = existingForSubject.map(item => ({
            name: item.name || '',
            roll_no: item.roll_no || '',
            marks: item.marks ?? '',
        }));
    } else {
        rows.value = [{ name: '', roll_no: '', marks: '' }];
    }
});

async function saveAllRows() {
    const subj = activeSubjectName.value;
    if (!subj) return;

    const validRows = rows.value.filter(r => r.name.trim() && r.roll_no.trim() && r.marks !== '');
    if (!validRows.length) return;

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
                    roll_no: r.roll_no.trim(),
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
