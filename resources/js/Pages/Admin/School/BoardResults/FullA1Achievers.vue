<template>
    <SchoolAdminLayout title="Full A1 Achievers" :school="school" :show-header-title="false">
        <!-- PRINT HEADER -->
        <div class="hidden print:block mb-6 border-b border-slate-300 pb-4 text-center">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold uppercase tracking-wider text-slate-900">{{ school?.name }}</h1>
                    <p class="text-xs text-slate-600 font-semibold">School Board Result Report — Full A1 Achievers (Class {{ boardResult.class }})</p>
                </div>
                <div class="text-right text-xs text-slate-500">
                    <p>Academic Year: <strong>{{ selectedYear }}</strong></p>
                    <p>Generated: {{ new Date().toLocaleDateString() }}</p>
                </div>
            </div>
            <div class="mt-3 text-xs text-slate-700 font-medium bg-slate-100 py-1.5 px-3 rounded flex justify-between">
                <span>Class {{ boardResult.class }} ({{ boardResult.class === 10 ? 'AISSE' : 'AISSCE' }})</span>
                <span>Total Achievers: <strong>{{ achievers.length }}</strong></span>
            </div>
        </div>

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6 print:hidden">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">
                    Full A1 Achievers
                </h1>
                <p class="text-xs text-gray-500 mt-0.5">
                    Add every student who scored A1 in <strong>every</strong> subject. Saved here separately from
                    Subject-Wise Toppers so this list is always exactly "confirmed Full A1", nothing else.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-for="c in [10, 12]"
                    :key="c"
                    type="button"
                    class="text-xs font-bold px-3 py-1 rounded-full border uppercase transition"
                    :class="c === boardResult.class ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                    @click="onClassChange(c)"
                >
                    Class {{ c === 10 ? 'X (AISSE)' : 'XII (AISSCE)' }}
                </button>

                <button type="button" @click="openHistorySearch" class="btn-secondary text-xs flex items-center gap-1.5 font-bold">
                    <span>📜</span> Student History
                </button>

                <button type="button" @click="printReport" class="btn-secondary text-xs flex items-center gap-1.5 font-bold">
                    <span>🖨</span> Print
                </button>
            </div>
        </div>

        <div v-if="!canEdit" class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-xs font-semibold text-amber-800 mb-6 print:hidden">
            ⚠️ Entry is locked for this result{{ editLockReason ? `: ${editLockReason}` : '.' }}
        </div>

        <div class="max-w-5xl space-y-6">
            <!-- ACADEMIC YEAR -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm print:hidden">
                <label class="form-label mb-1 font-semibold text-xs text-gray-700">Academic Year</label>
                <select v-model="selectedYear" class="field text-sm font-semibold bg-white max-w-xs" @change="onYearChange">
                    <option v-for="ay in academicYearOptions" :key="ay.id" :value="ay.label">
                        {{ academicYearOptionLabel(ay) }}
                    </option>
                </select>
            </div>

            <!-- ADD / EDIT STUDENT FORM -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-4 print:hidden">
                <div class="border-b border-gray-100 pb-3">
                    <h3 class="font-bold text-gray-900 text-base">
                        {{ editingId ? 'Edit Achiever' : 'Add a Full A1 Achiever' }}
                    </h3>
                    <p class="text-xs text-gray-500 mt-0.5">Enter this student's marks for every subject they were examined in (CBSE A1 top 1/8th percentile).</p>
                </div>

                <div v-if="formError" class="rounded-lg bg-red-50 border border-red-200 px-4 py-2.5 text-xs text-red-700 font-semibold whitespace-pre-line">
                    ⚠️ {{ formError }}
                </div>

                <form @submit.prevent="saveStudent" class="space-y-4">
                    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div>
                            <label class="form-label mb-1 text-xs text-gray-600">Student Full Name *</label>
                            <input v-model="form.name" type="text" required class="field text-sm" placeholder="Full name" :disabled="!canEdit">
                        </div>
                        <div>
                            <label class="form-label mb-1 text-xs text-gray-600">Gender *</label>
                            <select v-model="form.gender" required class="field text-sm bg-white" :disabled="!canEdit">
                                <option value="">— Select —</option>
                                <option value="male">♂ Male</option>
                                <option value="female">♀ Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label mb-1 text-xs text-gray-600">CBSE Roll No *</label>
                            <input v-model="form.roll_no" type="text" required class="field text-sm" placeholder="Required" :disabled="!canEdit">
                        </div>
                        <div v-if="boardResult.class === 12">
                            <label class="form-label mb-1 text-xs text-gray-600">Stream *</label>
                            <select v-model="form.stream" required class="field text-sm bg-white" :disabled="!canEdit">
                                <option value="">— Select —</option>
                                <option v-for="(label, key) in streamOptions" :key="key" :value="label">{{ label }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- SUBJECT-WISE MARKS FOR THIS STUDENT -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="form-label text-xs text-gray-600">Subject-wise Marks (A1)</label>
                            <button type="button" class="text-xs font-bold text-indigo-600 hover:text-indigo-800" :disabled="!canEdit" @click="addSubjectRow">+ Add Subject</button>
                        </div>

                        <div class="overflow-x-auto border border-gray-200 rounded-xl">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-gray-50 text-gray-600 font-semibold border-b border-gray-200">
                                    <tr>
                                        <th class="py-2 px-3 w-1/2">Subject</th>
                                        <th class="py-2 px-3 w-32">Marks (/100)</th>
                                        <th class="py-2 px-3 w-24 text-center">Grade</th>
                                        <th class="py-2 px-3 w-12"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-for="(row, i) in form.subjects" :key="i" :class="{ 'bg-red-50': isDuplicateRow(i) }">
                                        <td class="py-2 px-3">
                                            <select v-model="row.subject" class="field text-xs py-1.5 bg-white" :disabled="!canEdit">
                                                <option value="" disabled>-- Select Subject --</option>
                                                <option
                                                    v-for="s in standardSubjects"
                                                    :key="s"
                                                    :value="s"
                                                    :disabled="isSubjectUsedElsewhere(i, s)"
                                                >{{ subjectCodes[s] ? `${s} (${subjectCodes[s]})` : s }}{{ isSubjectUsedElsewhere(i, s) ? ' — already added' : '' }}</option>
                                                <option value="__custom__">+ Custom subject...</option>
                                            </select>
                                            <input
                                                v-if="row.subject === '__custom__'"
                                                v-model="row.customSubject"
                                                type="text"
                                                class="field text-xs py-1.5 mt-1"
                                                placeholder="Subject name"
                                                :disabled="!canEdit"
                                            >
                                            <p v-if="isDuplicateRow(i)" class="text-[11px] text-red-600 font-semibold mt-1">
                                                ⚠️ Duplicate subject — already entered in another row.
                                            </p>
                                        </td>
                                        <td class="py-2 px-3">
                                            <input
                                                v-model.number="row.marks"
                                                type="number" min="0" max="100"
                                                class="field text-xs py-1.5 font-bold"
                                                :class="markClass(row.marks)"
                                                placeholder="e.g. 95"
                                                :disabled="!canEdit"
                                            >
                                        </td>
                                        <td class="py-2 px-3 text-center">
                                            <span class="text-[11px] font-bold px-2 py-0.5 rounded-full" :class="gradeBadgeClass(row.marks)">
                                                {{ gradeLabel(row.marks) }}
                                            </span>
                                        </td>
                                        <td class="py-2 px-3 text-center">
                                            <button type="button" class="text-gray-400 hover:text-red-600" :disabled="!canEdit || form.subjects.length <= 1" @click="removeSubjectRow(i)">🗑</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <button v-if="editingId" type="button" class="text-xs font-bold text-gray-500 hover:text-gray-700" @click="resetForm">
                            Cancel edit
                        </button>
                        <span v-else></span>

                        <button v-if="canEdit" type="submit" class="btn-primary text-xs px-6 py-2.5 font-bold shadow-sm" :disabled="isSubmitting">
                            💾 {{ editingId ? 'Update Achiever' : 'Save Achiever' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- SAVED ACHIEVERS LIST -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 print:border-0 print:shadow-none print:p-0">
                <div class="border-b border-gray-100 pb-3 mb-4 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold text-gray-900 text-sm uppercase tracking-wide">Saved Full A1 Achievers</h3>
                        <p class="text-xs text-gray-500 mt-0.5">For Class {{ boardResult.class }}, {{ selectedYear }}.</p>
                    </div>
                    <span class="text-xs font-semibold text-gray-400 bg-gray-100 px-2.5 py-1 rounded-full print:hidden">{{ achievers.length }} achiever(s)</span>
                </div>

                <div v-if="!achievers.length" class="p-10 text-center text-gray-400 text-xs">
                    No Full A1 achievers saved yet for this class/year.
                </div>

                <div v-else class="divide-y divide-gray-100">
                    <div v-for="t in achievers" :key="t.id" class="py-3 flex items-center gap-4 hover:bg-slate-50/50 p-2 rounded-xl transition">
                        <div class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center overflow-hidden flex-shrink-0 print:hidden">
                            <img v-if="t.photo" :src="t.photo" class="w-full h-full object-cover" alt="">
                            <span v-else class="text-gray-400 text-xs">👤</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-bold text-gray-900 text-sm truncate">{{ t.name }}</p>
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 font-extrabold">A1</span>
                                <span v-if="t.verification_status === 'verified'" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700">Verified ✅</span>
                                <span v-else-if="t.verification_status === 'rejected'" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700" :title="t.rejection_reason">Rejected ❌ ({{ t.rejection_reason || 'See note' }})</span>
                                <span v-else-if="t.marksheet_url" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">Pending Verification ⏳</span>
                                <span v-else class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">No Marksheet Uploaded</span>
                            </div>
                            <p class="text-[11px] text-gray-500 mt-0.5">
                                {{ t.roll_no ? `Roll: ${t.roll_no}` : 'No roll no' }} · {{ subjectCountForTopper(t) }} subject(s) at A1
                                <span v-if="t.stream"> · {{ t.stream }}</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-2 print:hidden flex-wrap justify-end">
                            <a v-if="t.marksheet_url" :href="t.marksheet_url" target="_blank" class="text-xs font-bold px-2.5 py-1 rounded bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition border border-emerald-200">
                                📄 Marksheet ↗
                            </a>
                            <label v-if="canEdit" class="cursor-pointer text-xs font-semibold px-2 py-1 rounded bg-slate-100 text-slate-700 hover:bg-slate-200 transition border border-slate-200">
                                📤 {{ t.marksheet_url ? 'Re-upload' : 'Upload Marksheet' }}
                                <input type="file" class="hidden" accept="image/*,application/pdf" @change="uploadStudentMarksheet(t, $event)" />
                            </label>
                            <button type="button" class="text-xs font-bold px-2 py-1 rounded bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition" @click="previewMarks(t)">
                                Preview Marks 👁
                            </button>
                            <button type="button" class="text-xs font-bold px-2 py-1 rounded bg-slate-100 text-slate-700 hover:bg-slate-200 transition" @click="viewHistory(t)">
                                History
                            </button>
                            <button type="button" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 ml-1" @click="editStudent(t)">Edit</button>
                            <button type="button" class="text-xs font-bold text-red-500 hover:text-red-700" @click="removeStudent(t)">Remove</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SUBJECT MARKS PREVIEW MODAL -->
        <SubjectMarksPreviewModal
            :show="showSubjectModal"
            :student="selectedStudent"
            @close="showSubjectModal = false"
            @viewHistory="onModalViewHistory"
        />

        <!-- STUDENT HISTORY MODAL -->
        <StudentHistoryModal
            :show="showHistoryModal"
            :initialStudent="historyStudent"
            :schoolId="school.id"
            @close="showHistoryModal = false"
        />
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import SubjectMarksPreviewModal from '@/Components/BoardResults/SubjectMarksPreviewModal.vue';
import StudentHistoryModal from '@/Components/BoardResults/StudentHistoryModal.vue';
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    school: Object,
    boardResult: Object,
    academicYear: String,
    academicYearOptions: { type: Array, default: () => [] },
    standardSubjects: { type: Array, default: () => [] },
    subjectCodes: { type: Object, default: () => ({}) },
    streamOptions: { type: Object, default: () => ({}) },
    canEdit: { type: Boolean, default: true },
    editLockReason: { type: String, default: null },
});

const selectedYear = ref(props.academicYear);
const isSubmitting = ref(false);
const formError = ref('');
const editingId = ref(null);

const showSubjectModal = ref(false);
const selectedStudent = ref(null);

const showHistoryModal = ref(false);
const historyStudent = ref(null);

function academicYearOptionLabel(year) {
    if (year.entry_status === 'open') return `${year.label} (Entry Open)`;
    if (year.entry_status === 'upcoming') return `${year.label} (Entry Opens ${year.board_entry_starts_at})`;
    if (year.entry_status === 'closed') return `${year.label} (Entry Closed)`;
    return year.label;
}

function navigate(extra = {}) {
    router.get(`/school-admin/${props.school.id}/board-results/full-a1-achievers`, {
        academic_year: selectedYear.value,
        class: props.boardResult.class,
        ...extra,
    }, { preserveScroll: true });
}

function onYearChange() {
    navigate();
}

function onClassChange(c) {
    if (c === props.boardResult.class) return;
    navigate({ class: c });
}

const achievers = computed(() => props.boardResult.toppers ?? []);

function subjectCountForTopper(t) {
    if (Array.isArray(t.subject_marks)) return t.subject_marks.length;
    if (t.subject_marks && typeof t.subject_marks === 'object') return Object.keys(t.subject_marks).length;
    return 0;
}

function previewMarks(t) {
    let formattedMarks = [];
    if (Array.isArray(t.subject_marks)) {
        formattedMarks = t.subject_marks;
    } else if (t.subject_marks && typeof t.subject_marks === 'object') {
        formattedMarks = Object.entries(t.subject_marks).map(([subject_label, marks]) => ({
            subject_label,
            subject_code: props.subjectCodes[subject_label] || null,
            marks: Number(marks),
            grade: 'A1',
        }));
    }

    selectedStudent.value = {
        student_name: t.name,
        roll_no: t.roll_no,
        admission_no: t.admission_no,
        school_name: props.school.name,
        class: props.boardResult.class,
        stream: t.stream,
        academic_year: selectedYear.value,
        subject_marks: formattedMarks,
    };
    showSubjectModal.value = true;
}

function viewHistory(t) {
    historyStudent.value = {
        student_name: t.name,
        roll_no: t.roll_no,
        admission_no: t.admission_no,
    };
    showHistoryModal.value = true;
}

function openHistorySearch() {
    historyStudent.value = null;
    showHistoryModal.value = true;
}

function onModalViewHistory(student) {
    showSubjectModal.value = false;
    viewHistory(student);
}

function printReport() {
    window.print();
}

function blankSubjectRow() {
    return { subject: '', customSubject: '', marks: '' };
}

function blankForm() {
    return {
        name: '',
        gender: '',
        roll_no: '',
        stream: '',
        subjects: [blankSubjectRow(), blankSubjectRow(), blankSubjectRow(), blankSubjectRow(), blankSubjectRow()],
    };
}

const form = ref(blankForm());

function addSubjectRow() {
    form.value.subjects.push(blankSubjectRow());
}

function removeSubjectRow(i) {
    if (form.value.subjects.length > 1) form.value.subjects.splice(i, 1);
}

function resolvedSubjectLabel(row) {
    const label = row.subject === '__custom__' ? (row.customSubject || '') : (row.subject || '');
    return label.trim();
}

function isSubjectUsedElsewhere(rowIndex, subject) {
    const key = subject.trim().toLowerCase();
    return form.value.subjects.some((row, i) => i !== rowIndex && resolvedSubjectLabel(row).toLowerCase() === key);
}

function isDuplicateRow(rowIndex) {
    const label = resolvedSubjectLabel(form.value.subjects[rowIndex]);
    if (!label) return false;
    return isSubjectUsedElsewhere(rowIndex, label);
}

function markClass(marks) {
    if (marks === '' || marks === null || marks === undefined) return 'text-gray-600';
    return (Number(marks) >= 0 && Number(marks) <= 100) ? 'text-emerald-700' : 'text-red-600 border-red-400';
}

function gradeLabel(marks) {
    if (marks === '' || marks === null || marks === undefined) return '—';
    return 'A1';
}

function gradeBadgeClass(marks) {
    if (marks === '' || marks === null || marks === undefined) return 'bg-gray-100 text-gray-500';
    return (Number(marks) >= 0 && Number(marks) <= 100) ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-600';
}

function resetForm() {
    form.value = blankForm();
    editingId.value = null;
    formError.value = '';
}

function editStudent(t) {
    editingId.value = t.id;
    formError.value = '';
    const subjectMarks = t.subject_marks || {};
    const entries = Array.isArray(subjectMarks)
        ? subjectMarks.map(s => [s.subject_label || s.subject, s.marks])
        : Object.entries(subjectMarks);

    const rows = entries.map(([subject, marks]) => {
        const known = props.standardSubjects.includes(subject);
        return known
            ? { subject, customSubject: '', marks }
            : { subject: '__custom__', customSubject: subject, marks };
    });
    form.value = {
        name: t.name || '',
        gender: t.gender || '',
        roll_no: t.roll_no || '',
        stream: t.stream || '',
        subjects: rows.length ? rows : [blankSubjectRow()],
    };
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function saveStudent() {
    formError.value = '';

    if (!form.value.name.trim() || !form.value.gender) {
        formError.value = 'Student name and gender are required.';
        return;
    }
    if (!form.value.roll_no?.trim()) {
        formError.value = 'CBSE Roll No is required.';
        return;
    }
    if (props.boardResult.class === 12 && !form.value.stream) {
        formError.value = 'Select a stream for this Class XII student.';
        return;
    }

    const subjectMarks = [];
    const seen = new Set();
    const duplicates = new Set();
    for (const row of form.value.subjects) {
        const label = resolvedSubjectLabel(row);
        if (!label || row.marks === '' || row.marks === null) continue;

        const key = label.toLowerCase();
        if (seen.has(key)) {
            duplicates.add(label);
            continue;
        }
        seen.add(key);
        subjectMarks.push({ subject: label, marks: Number(row.marks) });
    }

    if (duplicates.size) {
        formError.value = [...duplicates].map((s) => `"${s}" was entered more than once — remove the duplicate row.`).join('\n');
        return;
    }

    if (subjectMarks.length === 0) {
        formError.value = 'Add at least one subject with marks.';
        return;
    }

    const invalidMarks = subjectMarks.filter(({ marks }) => isNaN(marks) || marks < 0 || marks > 100);
    if (invalidMarks.length) {
        formError.value = invalidMarks
            .map(({ subject, marks }) => `${subject}: ${marks} — Marks must be between 0 and 100.`)
            .join('\n');
        return;
    }

    isSubmitting.value = true;

    router.post(`/school-admin/${props.school.id}/board-results/${props.boardResult.id}/full-a1-achievers/batch`, {
        rows: [{
            topper_id: editingId.value,
            name: form.value.name.trim(),
            gender: form.value.gender,
            roll_no: form.value.roll_no.trim(),
            stream: props.boardResult.class === 12 ? form.value.stream : null,
            subject_marks: subjectMarks,
        }],
    }, {
        preserveScroll: true,
        onSuccess: () => resetForm(),
        onError: (errors) => {
            formError.value = Object.values(errors).flat().join('\n') || 'Could not save — check the form.';
        },
        onFinish: () => { isSubmitting.value = false; },
    });
}

function removeStudent(t) {
    if (!confirm(`Remove Full A1 achiever "${t.name}"?`)) return;

    router.delete(`/school-admin/${props.school.id}/board-results/${props.boardResult.id}/toppers/${t.id}`, {
        preserveScroll: true,
    });
}

function uploadStudentMarksheet(topper, event) {
    const file = event.target.files?.[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('marksheet', file);

    router.post(
        `/school-admin/${props.school.id}/board-results/${props.boardResult.id}/toppers/${topper.id}/marksheet`,
        formData,
        {
            preserveScroll: true,
            onSuccess: () => {
                event.target.value = '';
            },
        }
    );
}
</script>
