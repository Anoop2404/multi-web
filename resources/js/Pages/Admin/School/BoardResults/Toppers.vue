<template>
    <SchoolAdminLayout :title="pageTitle" :school="school" :show-header-title="false">
        <!-- TOP TOOLBAR & HEADER -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <Link :href="`/school-admin/${school.id}/board-results?class=${boardResult.class}&academic_year=${urlEncode(boardResult.academic_year)}`" class="text-xs font-semibold text-indigo-600 hover:underline flex items-center gap-1">
                        ← Back to Results Workspace
                    </Link>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">
                    Toppers Management — Class {{ boardResult.class }} ({{ boardResult.academic_year }})
                </h1>
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ isClass12 ? 'Class XII (AISSCE) — 3 topper categories: Overall Stream Toppers, Subject-Wise Toppers, and 90%+ High Achievers.' : 'Class X (AISSE) — manage overall school toppers and score percentages.' }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-xs font-bold px-3 py-1 bg-indigo-50 text-indigo-700 rounded-full border border-indigo-100 uppercase">
                    Class {{ boardResult.class }} ({{ boardResult.examination_type }})
                </span>
            </div>
        </div>

        <!-- 3 CATEGORY NAVIGATION TABS (CLASS 12 EXCLUSIVE) -->
        <div v-if="isClass12" class="flex items-center bg-white p-1.5 rounded-2xl shadow-xs border border-gray-200 mb-6 space-x-1 max-w-2xl">
            <button
                type="button"
                @click="activeTab = 'overall'"
                class="flex-1 py-2 text-xs font-bold rounded-xl transition-all flex items-center justify-center gap-2"
                :class="activeTab === 'overall' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50'"
            >
                <span>🏆</span> Overall Stream Toppers
            </button>

            <button
                type="button"
                @click="activeTab = 'subject'"
                class="flex-1 py-2 text-xs font-bold rounded-xl transition-all flex items-center justify-center gap-2"
                :class="activeTab === 'subject' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50'"
            >
                <span>🎯</span> Subject-Wise Mark Entry
            </button>

            <button
                type="button"
                @click="activeTab = 'achievers'"
                class="flex-1 py-2 text-xs font-bold rounded-xl transition-all flex items-center justify-center gap-2"
                :class="activeTab === 'achievers' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50'"
            >
                <span>🌟</span> 90%+ Achievers ({{ achievers90.length }})
            </button>
        </div>

        <div class="max-w-5xl space-y-6">
            <!-- TAB 1: OVERALL STREAM TOPPERS -->
            <div v-if="activeTab === 'overall' || !isClass12" class="space-y-6">
                <!-- Bulk add toppers -->
                <div v-if="!editingId" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-5">
                    <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900 text-base">
                                Add {{ isClass12 ? 'Class XII Overall Stream Toppers' : 'Class X School Toppers' }}
                            </h3>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ isClass12 ? 'Enter overall top rankers for Class XII.' : 'Enter overall top rankers for Class X.' }}
                            </p>
                        </div>
                    </div>

                    <form @submit.prevent="submitBatch" class="space-y-5">
                        <div class="max-w-xs">
                            <label class="form-label mb-1 font-semibold">Total Marks (Common Out of) *</label>
                            <input v-model.number="batchForm.total_marks" type="number" min="1" required class="field text-sm" placeholder="e.g. 500" :disabled="!canEdit">
                        </div>

                        <div class="border border-gray-200 rounded-xl overflow-hidden shadow-xs">
                            <table class="w-full text-sm">
                                <thead class="text-left text-xs uppercase font-bold text-gray-500 bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="p-3">Student Name *</th>
                                        <th class="p-3">CBSE Roll No</th>
                                        <th class="p-3">Marks Scored *</th>
                                        <th class="p-3">%</th>
                                        <th class="p-3">Photo (Optional)</th>
                                        <th class="p-3 text-right"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-for="(row, i) in batchForm.toppers" :key="i" class="hover:bg-slate-50/50">
                                        <td class="p-3"><input v-model="row.name" type="text" required class="field text-sm" placeholder="Student name" :disabled="!canEdit"></td>
                                        <td class="p-3"><input v-model="row.roll_no" type="text" class="field text-sm w-36" placeholder="CBSE Roll No" :disabled="!canEdit"></td>
                                        <td class="p-3"><input v-model.number="row.marks_obtained" type="number" min="0" :max="batchForm.total_marks || undefined" required class="field text-sm w-28" placeholder="Marks" :disabled="!canEdit"></td>
                                        <td class="p-3 text-indigo-600 font-bold whitespace-nowrap">{{ rowPercentage(row) }}</td>
                                        <td class="p-3"><input type="file" accept="image/*" class="text-xs w-40" :disabled="!canEdit" @change="row.photo = $event.target.files[0]"></td>
                                        <td class="p-3 text-right">
                                            <button v-if="canEdit && batchForm.toppers.length > 1" type="button" class="text-red-500 hover:text-red-700 text-xs font-semibold" @click="removeRow(i)">Remove</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="Object.keys(batchForm.errors).length" class="rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-600 space-y-0.5">
                            <p v-for="(msg, key) in batchForm.errors" :key="key">• {{ msg }}</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <button v-if="canEdit" type="button" class="btn-secondary text-xs px-3 py-2 font-semibold" @click="addRow">+ Add Row</button>
                            <button v-if="canEdit" type="submit" class="btn-primary text-xs px-5 py-2 font-bold shadow-sm" :disabled="batchForm.processing || wouldExceedCap">
                                Save {{ batchForm.toppers.length }} Overall Topper{{ batchForm.toppers.length > 1 ? 's' : '' }}
                            </button>
                            <p v-if="wouldExceedCap" class="text-xs font-semibold text-amber-700 bg-amber-50 px-3 py-1.5 rounded-lg border border-amber-200">
                                {{ topperCount }} toppers added — adding {{ batchForm.toppers.length }} more exceeds limit ({{ topperCap }}).
                            </p>
                        </div>
                    </form>
                </div>

                <!-- Edit topper (single) -->
                <div v-else class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-5">
                    <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900 text-base">Edit Topper Details</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Update student details, stream, rank or subject marks.</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitEdit" class="space-y-5">
                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div>
                                <label class="form-label mb-1">Student Name *</label>
                                <input v-model="form.name" type="text" required class="field text-sm" :disabled="!canEdit">
                            </div>
                            <div v-if="isClass12">
                                <label class="form-label mb-1">Stream *</label>
                                <select v-model="form.stream_key" required class="field text-sm" @change="onStreamChange">
                                    <option value="science">Science</option>
                                    <option value="commerce">Commerce</option>
                                    <option value="humanities">Humanities</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label mb-1">CBSE Roll No</label>
                                <input v-model="form.roll_no" type="text" class="field text-sm" :disabled="!canEdit" placeholder="CBSE examination roll number">
                            </div>
                            <div>
                                <label class="form-label mb-1">Overall Percentage (%) *</label>
                                <input v-model="form.percentage" type="number" required min="0" max="100" step="0.01" class="field text-sm font-bold text-indigo-700" :disabled="!canEdit">
                            </div>
                            <div>
                                <label class="form-label mb-1">Overall Rank</label>
                                <input v-model="form.rank" type="number" min="1" placeholder="1" class="field text-sm" :disabled="!canEdit">
                            </div>
                            <div>
                                <label class="form-label mb-1">Marks Obtained</label>
                                <input v-model="form.marks_obtained" type="number" min="0" class="field text-sm" placeholder="e.g. 485">
                            </div>
                            <div>
                                <label class="form-label mb-1">Student Photo</label>
                                <input type="file" accept="image/*" class="field text-sm" @change="form.photo = $event.target.files[0]">
                            </div>
                        </div>

                        <div v-if="isClass12 && form.stream_key" class="border-t border-gray-100 pt-5">
                            <h4 class="text-sm font-bold text-gray-800 mb-1">Subject-Wise Marks (Out of 100)</h4>
                            <p class="text-xs text-gray-500 mb-4">Enter marks for each subject. Subject toppers calculate automatically.</p>
                            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <div v-for="subject in activeSubjects" :key="subject">
                                    <label class="form-label mb-1 text-xs font-semibold text-gray-600">{{ subject }}</label>
                                    <input
                                        v-model="form.subject_marks[subject]"
                                        type="number"
                                        min="0"
                                        max="100"
                                        class="field text-sm"
                                        placeholder="—"
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 pt-3">
                            <button v-if="canEdit" type="submit" class="btn-primary text-xs px-5 py-2.5 font-bold shadow-sm" :disabled="form.processing">
                                Save Changes
                            </button>
                            <button v-if="canEdit" type="button" class="btn-secondary text-xs px-4 py-2.5" @click="cancelEdit">Cancel Edit</button>
                        </div>
                    </form>
                </div>

                <!-- OVERALL TOPPERS LIST TABLE -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden divide-y divide-gray-100">
                    <div class="p-5 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900 text-sm uppercase tracking-wide">
                                Overall Toppers List ({{ boardResult.toppers?.length ?? 0 }}{{ topperCap ? ` / ${topperCap}` : '' }})
                            </h3>
                            <p class="text-xs text-gray-500 mt-0.5">Ranked list of toppers for Class {{ boardResult.class }}.</p>
                        </div>
                    </div>

                    <div v-if="sortedToppers.length" class="divide-y divide-gray-100">
                        <div v-for="t in sortedToppers" :key="t.id" class="p-5 hover:bg-slate-50/50 transition">
                            <div class="flex items-start gap-4">
                                <img v-if="t.photo" :src="t.photo" class="w-12 h-12 rounded-full object-cover border border-gray-200 shrink-0 shadow-xs" alt="">
                                <div v-else class="w-12 h-12 rounded-full bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 font-bold shrink-0 text-base shadow-xs">
                                    {{ t.name[0] }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-bold px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded-md">#{{ t.rank ?? '—' }}</span>
                                                <h4 class="font-bold text-gray-900 text-base">{{ t.name }}</h4>
                                                <span v-if="t.stream" class="text-xs font-semibold bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded border border-indigo-100">
                                                    {{ t.stream }}
                                                </span>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-1 flex flex-wrap items-center gap-3">
                                                <span v-if="t.roll_no" class="font-medium text-gray-700">CBSE Roll No: {{ t.roll_no }}</span>
                                                <span v-if="t.marks_obtained && t.total_marks" class="text-gray-600">· {{ t.marks_obtained }} / {{ t.total_marks }} Marks</span>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xl font-bold text-emerald-600 tracking-tight">{{ t.percentage }}%</p>
                                        </div>
                                    </div>

                                    <div v-if="isClass12 && t.subject_marks && Object.keys(t.subject_marks).length" class="mt-3 flex flex-wrap gap-2">
                                        <span v-for="(mark, subject) in t.subject_marks" :key="subject"
                                              class="text-xs px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 border border-slate-200/60">
                                            {{ subject }}: <strong class="font-bold text-indigo-700">{{ mark }}</strong>
                                        </span>
                                    </div>

                                    <div v-if="canEdit" class="mt-3 flex items-center gap-3 text-xs">
                                        <button type="button" class="text-indigo-600 font-semibold hover:underline" @click="startEdit(t)">Edit Details</button>
                                        <button type="button" class="text-red-500 font-semibold hover:underline" @click="remove(t)">Remove</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="p-10 text-center text-gray-400 text-xs">
                        No overall toppers recorded yet.
                    </div>
                </div>
            </div>

            <!-- TAB 2: SUBJECT-WISE MARK ENTRY (CLASS 12 EXCLUSIVE) -->
            <div v-if="isClass12 && activeTab === 'subject'" class="space-y-6">
                <!-- TOP SELECTION BAR: SUBJECT & YEAR -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3 flex-wrap">
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-bold text-gray-700 uppercase tracking-wide">Subject:</label>
                            <select v-model="selectedSubjectOption" class="field text-xs py-1.5 w-56 font-semibold bg-white">
                                <option value="" disabled>Select Subject</option>
                                <option v-for="subj in masterSubjectList" :key="subj" :value="subj">{{ subj }}</option>
                                <option value="__custom__">+ Add Custom Subject...</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-gray-500 uppercase tracking-wide">Academic Year:</span>
                        <span class="text-xs font-bold text-indigo-700 bg-indigo-50 px-3 py-1.5 rounded-lg border border-indigo-100">
                            {{ boardResult.academic_year }}
                        </span>
                    </div>
                </div>

                <!-- Custom Subject Input if selected -->
                <div v-if="selectedSubjectOption === '__custom__'" class="bg-white rounded-xl border border-indigo-200 p-4 shadow-2xs max-w-md">
                    <label class="form-label mb-1 text-xs font-semibold text-indigo-900">Custom Subject Name *</label>
                    <input v-model="customSubjectInput" type="text" required class="field text-sm" placeholder="Enter custom subject name..." :disabled="!canEdit">
                </div>

                <!-- ADD SUBJECT TOPPER FORM -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-4">
                    <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900 text-base">Add Subject Top Scorer</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Enter student name, CBSE roll number, and mark scored out of 100.</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitSubjectTopper" class="grid sm:grid-cols-3 gap-4">
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

                        <div class="sm:col-span-3 flex justify-end pt-2">
                            <button v-if="canEdit" type="submit" class="btn-primary text-xs px-6 py-2.5 font-bold shadow-sm" :disabled="subjectForm.processing">
                                + Save Subject Topper
                            </button>
                        </div>
                    </form>
                </div>

                <!-- COMMON PROOF DOCUMENT UPLOAD -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6 space-y-3">
                    <h3 class="font-bold text-gray-900 text-sm uppercase tracking-wide">Common Proof Document (CBSE Result PDF / Proof)</h3>
                    <div v-if="boardResult.result_pdf_path" class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 flex items-center justify-between shadow-2xs">
                        <div class="flex items-center gap-2 text-xs font-semibold text-emerald-800">
                            <span>✓ Common Proof Attached</span>
                            <a :href="`/school-admin/${school.id}/board-results/${boardResult.id}/pdf`" target="_blank" class="underline text-indigo-600 hover:text-indigo-800 font-normal">View File ↗</a>
                        </div>
                        <span class="text-[11px] text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded font-medium">Ready</span>
                    </div>
                    <p class="text-xs text-gray-500">Upload the common CBSE Tabulation Sheet or result proof PDF for verification.</p>
                </div>

                <!-- DISPLAY SUBJECT TOP PERFORMERS GRID -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                    <h3 class="font-bold text-gray-900 text-sm mb-1 uppercase tracking-wide">Saved Subject Top Performers</h3>
                    <p class="text-xs text-gray-500 mb-4">Highest scorers identified across Class XII subjects.</p>

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

                    <div v-else class="p-8 text-center text-gray-400 text-xs">
                        No subject-wise toppers recorded yet. Use the form above to add subject leaders.
                    </div>
                </div>
            </div>

            <!-- TAB 3: 90%+ HIGH ACHIEVERS -->
            <div v-if="isClass12 && activeTab === 'achievers'" class="space-y-6">
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
                    <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-gray-900 text-base">90% &amp; Above High Achievers</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Every student scoring 90% or above overall (Stream-based grouping).</p>
                        </div>
                        <span class="text-xs font-bold px-3 py-1 bg-emerald-50 text-emerald-700 rounded-full border border-emerald-200">
                            {{ achievers90.length }} High Achiever{{ achievers90.length === 1 ? '' : 's' }}
                        </span>
                    </div>

                    <div v-if="achievers90.length" class="mt-6 space-y-6">
                        <div v-for="(rows, stream) in achievers90ByStream" :key="stream" class="border border-gray-100 rounded-xl p-4 bg-slate-50/50">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-indigo-700 mb-3 flex items-center gap-2">
                                <span>📚</span> {{ stream }} Stream ({{ rows.length }})
                            </h4>
                            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <div v-for="t in rows" :key="t.id" class="bg-white rounded-lg border border-gray-200 p-3 shadow-2xs flex items-center justify-between gap-2">
                                    <div>
                                        <p class="font-bold text-gray-900 text-sm">{{ t.name }}</p>
                                        <p v-if="t.roll_no" class="text-xs text-gray-400">CBSE Roll No: {{ t.roll_no }}</p>
                                    </div>
                                    <span class="text-sm font-bold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-100">
                                        {{ t.percentage }}%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="p-10 text-center text-gray-400 text-xs">
                        No students scoring 90% or above recorded yet.
                    </div>
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { computed, ref } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    school:             Object,
    boardResult:        Object,
    isClass12:          { type: Boolean, default: false },
    streamOptions:      { type: Object, default: () => ({}) },
    standardSubjects:   { type: Array, default: () => [] },
    subjectsByStream:   { type: Object, default: () => ({}) },
    subjectWiseLeaders: { type: Array, default: () => [] },
    canEdit:            { type: Boolean, default: true },
    topperCap:          { type: Number, default: null },
    topperCount:        { type: Number, default: 0 },
});

const default23Subjects = [
    'English core', 'Hindi core', 'Hindi elective', 'Malayalam', 'Sanskrit',
    'Physics', 'Chemistry', 'Biology', 'Mathematics', 'Computer science',
    'Psychology', 'Informatics practices', 'History', 'Sociology',
    'Political science', 'Economics', 'Accountancy', 'Business Studies',
    'Home science', 'Fashion studies', 'Physical education', 'Business administration', 'KTPI',
];

const masterSubjectList = computed(() =>
    props.standardSubjects?.length ? props.standardSubjects : default23Subjects
);

const pageTitle = computed(() => `Toppers — Class ${props.boardResult.class} (${props.boardResult.academic_year})`);

const activeTab = ref('overall');
const editingId = ref(null);
const selectedSubjectStream = ref('science');

function urlEncode(val) {
    return encodeURIComponent(val ?? '');
}

const sortedToppers = computed(() =>
    [...(props.boardResult.toppers ?? [])].sort((a, b) => (a.rank ?? 999) - (b.rank ?? 999)),
);

const achievers90 = computed(() =>
    (props.boardResult.toppers ?? [])
        .filter((t) => t.percentage != null && Number(t.percentage) >= 90)
        .sort((a, b) => Number(b.percentage) - Number(a.percentage)),
);

const achievers90ByStream = computed(() => {
    const groups = {};
    for (const t of achievers90.value) {
        const key = t.stream ?? 'Overall';
        (groups[key] ??= []).push(t);
    }
    return groups;
});

// ── Bulk add ─────────────────────────────────────────────────────────────
function blankRow() {
    return { name: '', stream_key: '', roll_no: '', marks_obtained: '', photo: null };
}

const batchForm = useForm({
    total_marks: props.boardResult.total_marks || 500,
    toppers: [blankRow()],
});

const wouldExceedCap = computed(() =>
    props.topperCap != null && (props.topperCount + batchForm.toppers.length) > props.topperCap,
);

function addRow() {
    batchForm.toppers.push(blankRow());
}

function removeRow(i) {
    batchForm.toppers.splice(i, 1);
}

function rowPercentage(row) {
    if (!batchForm.total_marks || row.marks_obtained === '' || row.marks_obtained == null) return '—';
    const val = Math.round(((row.marks_obtained / batchForm.total_marks) * 100) * 100) / 100;
    return `${val}%`;
}

function submitBatch() {
    batchForm.post(`/school-admin/${props.school.id}/board-results/${props.boardResult.id}/toppers/batch`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            batchForm.reset();
            batchForm.toppers = [blankRow()];
        },
    });
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

// ── Edit (single) ────────────────────────────────────────────────────────
const form = useForm({
    name: '',
    roll_no: '',
    percentage: '',
    rank: '',
    stream_key: '',
    total_marks: '',
    marks_obtained: '',
    is_perfect_scorer: false,
    photo: null,
    subject_marks: {},
});

const activeSubjects = computed(() => props.subjectsByStream[form.stream_key] ?? []);

function blankSubjectMarks(streamKey) {
    const marks = {};
    for (const subject of props.subjectsByStream[streamKey] ?? []) {
        marks[subject] = '';
    }
    return marks;
}

function onStreamChange() {
    const existing = { ...form.subject_marks };
    form.subject_marks = blankSubjectMarks(form.stream_key);
    for (const subject of activeSubjects.value) {
        if (existing[subject] !== undefined && existing[subject] !== '') {
            form.subject_marks[subject] = existing[subject];
        }
    }
}

function streamKeyFromTopper(t) {
    if (!t.stream) return '';
    const entry = Object.entries(props.streamOptions).find(([, label]) => label === t.stream);
    return entry?.[0] ?? 'other';
}

function startEdit(t) {
    editingId.value = t.id;
    form.name = t.name;
    form.roll_no = t.roll_no ?? '';
    form.percentage = t.percentage;
    form.rank = t.rank ?? '';
    form.stream_key = streamKeyFromTopper(t);
    form.total_marks = t.total_marks ?? '';
    form.marks_obtained = t.marks_obtained ?? '';
    form.is_perfect_scorer = !!t.is_perfect_scorer;
    form.photo = null;
    form.subject_marks = blankSubjectMarks(form.stream_key);
    for (const [subject, mark] of Object.entries(t.subject_marks ?? {})) {
        form.subject_marks[subject] = mark;
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelEdit() {
    editingId.value = null;
    form.reset();
    form.subject_marks = {};
}

function submitEdit() {
    const base = `/school-admin/${props.school.id}/board-results/${props.boardResult.id}/toppers`;
    form.transform((data) => ({ ...data, _method: 'put' }))
        .post(`${base}/${editingId.value}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => cancelEdit(),
        });
}

function remove(t) {
    if (!confirm(`Remove topper "${t.name}"?`)) return;
    router.delete(`/school-admin/${props.school.id}/board-results/${props.boardResult.id}/toppers/${t.id}`);
}
</script>
