<template>
    <SahodayaAdminLayout title="Full A1 Achievers" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">

        <!-- PRINT HEADER (ONLY VISIBLE IN PRINT MODE) -->
        <div class="hidden print:block mb-6 border-b border-slate-300 pb-4 text-center">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold uppercase tracking-wider text-slate-900">{{ sahodaya?.name || 'Sahodaya Complex' }}</h1>
                    <p class="text-xs text-slate-600 font-semibold">Official Academic Board Result Report — Full A1 Achievers</p>
                </div>
                <div class="text-right text-xs text-slate-500">
                    <p>Academic Year: <strong>{{ selectedYear }}</strong></p>
                    <p>Generated: {{ new Date().toLocaleDateString() }}</p>
                </div>
            </div>
            <div class="mt-3 text-xs text-slate-700 font-medium bg-slate-100 py-1.5 px-3 rounded flex justify-between">
                <span>Filter: {{ selectedClass ? `Class ${selectedClass}` : 'All Classes (10 & 12)' }} • {{ selectedStream || 'All Streams' }}</span>
                <span>Total Achievers: <strong>{{ filteredRows.length }}</strong></span>
            </div>
        </div>

        <!-- HERO BANNER (achievement-themed, distinct from other topper reports) -->
        <div class="print:hidden relative overflow-hidden rounded-2xl mb-6 bg-gradient-to-br from-[#0b2558] via-[#123a7a] to-[#1e4d9e] text-white p-6 sm:p-8">
            <div class="absolute -right-8 -top-8 text-[140px] opacity-10 leading-none select-none">🏅</div>
            <div class="relative flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-widest text-amber-300 mb-1">Academic Results · Excellence Register</p>
                    <h1 class="text-2xl sm:text-3xl font-extrabold">Full A1 Achievers</h1>
                    <p class="text-sm text-blue-100 mt-1.5 max-w-xl">
                        Students who scored A1 in <strong class="text-white">every subject</strong> they were entered for — Class X & Class XII, all streams.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="openHistorySearch" class="btn-secondary text-xs flex items-center gap-1.5 font-bold !bg-white/10 !border-white/20 !text-white hover:!bg-white/20">
                        <span>📜</span> Student History
                    </button>
                    <button type="button" @click="printReport" class="btn-secondary text-xs flex items-center gap-1.5 font-bold !bg-white/10 !border-white/20 !text-white hover:!bg-white/20">
                        <span>🖨</span> Print
                    </button>
                    <button type="button" @click="openPreview" class="btn-secondary text-xs flex items-center gap-1.5 font-bold !bg-white/10 !border-white/20 !text-white hover:!bg-white/20">
                        <span>👁</span> Preview PDF
                    </button>
                    <a :href="pdfDownloadUrl" class="text-xs flex items-center gap-1.5 font-bold px-3 py-2 rounded-lg bg-amber-400 text-[#0b2558] hover:bg-amber-300 transition">
                        <span>📥</span> Download PDF
                    </a>
                </div>
            </div>

            <!-- STATS STRIP -->
            <div class="relative mt-6 grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="rounded-xl bg-white/10 border border-white/15 backdrop-blur-sm p-3.5">
                    <p class="text-[10px] font-semibold text-blue-200 uppercase tracking-wide">Total Achievers</p>
                    <p class="text-2xl font-extrabold text-amber-300 mt-1">{{ filteredRows.length }}</p>
                </div>
                <div class="rounded-xl bg-white/10 border border-white/15 backdrop-blur-sm p-3.5">
                    <p class="text-[10px] font-semibold text-blue-200 uppercase tracking-wide">Schools Represented</p>
                    <p class="text-2xl font-extrabold text-white mt-1">{{ distinctSchoolCount }}</p>
                </div>
                <div class="rounded-xl bg-white/10 border border-white/15 backdrop-blur-sm p-3.5">
                    <p class="text-[10px] font-semibold text-blue-200 uppercase tracking-wide">Class 10</p>
                    <p class="text-2xl font-extrabold text-white mt-1">{{ class10Count }}</p>
                </div>
                <div class="rounded-xl bg-white/10 border border-white/15 backdrop-blur-sm p-3.5">
                    <p class="text-[10px] font-semibold text-blue-200 uppercase tracking-wide">Class 12</p>
                    <p class="text-2xl font-extrabold text-white mt-1">{{ class12Count }}</p>
                </div>
            </div>
        </div>

        <BoardResultsVerificationSubNav :sahodayaId="sahodaya.id" active="full-a1" :currentClass="selectedClass" />
        <BoardResultsReportSubNav :sahodayaId="sahodaya.id" active="full-a1" />

        <!-- FILTER CONTROLS -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm mb-6 space-y-4 print:hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-base font-bold text-gray-900">⚡ Filters</span>
                    <span class="text-xs text-gray-500">({{ filteredRows.length }} achiever(s) found)</span>
                </div>

                <div class="relative w-full sm:w-72">
                    <input
                        v-model="searchQuery"
                        type="text"
                        class="field text-xs pl-8 pr-3 py-2 w-full bg-gray-50 border-gray-200 focus:bg-white"
                        placeholder="Search student, roll no, school..."
                    >
                    <span class="absolute left-2.5 top-2.5 text-gray-400 text-xs">🔍</span>
                </div>
            </div>

            <div class="grid sm:grid-cols-2 md:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="form-label mb-1 text-[11px] font-bold text-gray-600 uppercase">Academic Year</label>
                    <select v-model="selectedYear" class="field text-xs bg-white font-semibold" @change="applyServerFilters">
                        <option v-for="ay in academicYearOptions" :key="ay.id" :value="ay.label">
                            {{ ay.label }}{{ ay.status === 'active' ? ' (Active)' : '' }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="form-label mb-1 text-[11px] font-bold text-gray-600 uppercase">Class</label>
                    <select v-model="selectedClass" class="field text-xs bg-white font-semibold" @change="applyServerFilters">
                        <option :value="null">All Classes (10 & 12)</option>
                        <option v-for="c in classOptions" :key="c" :value="c">Class {{ c }}</option>
                    </select>
                </div>

                <div>
                    <label class="form-label mb-1 text-[11px] font-bold text-gray-600 uppercase">Stream</label>
                    <select v-model="selectedStream" class="field text-xs bg-white font-semibold" @change="applyServerFilters" :disabled="selectedClass !== 12">
                        <option :value="null">All Streams</option>
                        <option v-for="s in streamOptions" :key="s" :value="s">{{ s }}</option>
                    </select>
                </div>

                <div class="flex items-center justify-between text-xs text-gray-500 pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" v-model="includeSubjectMarksInPrint" class="rounded border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500">
                        <span class="text-[11px] font-semibold text-slate-700">Include Subject Breakdown in Print</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- ACHIEVERS TABLE -->
        <div class="card !p-0 overflow-x-auto shadow-sm border border-gray-200 bg-white print:border-0 print:shadow-none">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between print:hidden bg-gradient-to-r from-amber-50 to-white">
                <div>
                    <h2 class="font-bold text-gray-900 text-base flex items-center gap-2">🏅 Full A1 Achievers — Academic Year {{ selectedYear }}</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Click any row or subject badge to preview subject-wise marks breakdown and student history.</p>
                </div>
                <span class="text-xs text-slate-400 font-medium">💡 Tip: Click subject count pill to inspect marks</span>
            </div>

            <table class="data-table min-w-[850px] w-full text-left print:min-w-full">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-xs uppercase font-semibold print:bg-slate-100 print:text-slate-800">
                        <th class="py-3 px-4">Student Name</th>
                        <th class="py-3 px-4">CBSE Roll No</th>
                        <th class="py-3 px-4">School Name</th>
                        <th class="py-3 px-4 text-center">Class / Stream</th>
                        <th class="py-3 px-4 text-center">Subjects (all A1)</th>
                        <th class="py-3 px-4 text-center">Lowest Mark</th>
                        <th class="py-3 px-4 text-center">Marksheet</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right print:hidden">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-xs print:divide-slate-200">
                    <template v-for="(row, i) in filteredRows" :key="i">
                        <tr
                            class="hover:bg-amber-50/40 transition-colors cursor-pointer"
                            @click="previewStudentMarks(row)"
                        >
                            <td class="py-2.5 px-4 font-bold text-gray-900">
                                <div class="flex items-center gap-1.5">
                                    <span>{{ row.student_name }}</span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-800 font-extrabold border border-amber-200 print:hidden">🏅 A1</span>
                                </div>
                            </td>
                            <td class="py-2.5 px-4 font-mono text-gray-600">{{ row.roll_no || '—' }}</td>
                            <td class="py-2.5 px-4 text-gray-700 font-medium">{{ row.school_name }}</td>
                            <td class="py-2.5 px-4 text-center font-semibold">
                                Class {{ row.class }}
                                <span v-if="row.stream" class="text-slate-500 text-[11px] block">{{ row.stream }}</span>
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <button
                                    type="button"
                                    class="px-2.5 py-1 rounded-full text-xs font-extrabold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-100 transition print:bg-none print:p-0 print:border-0"
                                    @click.stop="previewStudentMarks(row)"
                                >
                                    {{ row.subjects_count }} subjects 👁
                                </button>
                            </td>
                            <td class="py-2.5 px-4 text-center font-bold text-emerald-600">{{ row.lowest_mark ?? '—' }}</td>
                            <td class="py-2.5 px-4 text-center">
                                <a v-if="row.marksheet_url" :href="row.marksheet_url" target="_blank" @click.stop class="font-bold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 px-2 py-1 rounded-lg border border-emerald-200 inline-flex items-center gap-1">
                                    📄 Marksheet ↗
                                </a>
                                <span v-else class="text-slate-400 text-[11px]">No PDF</span>
                            </td>
                            <td class="py-2.5 px-4 text-center">
                                <span v-if="row.verification_status === 'verified'" class="font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-[11px]">Verified ✅</span>
                                <span v-else-if="row.verification_status === 'rejected'" class="font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-[11px]" :title="row.rejection_reason">Rejected ❌</span>
                                <span v-else class="font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[11px]">Pending ⏳</span>
                            </td>
                            <td class="py-2.5 px-4 text-right print:hidden">
                                <div class="flex items-center justify-end gap-1" @click.stop>
                                    <button
                                        v-if="row.verification_status !== 'verified'"
                                        type="button"
                                        class="px-2 py-1 rounded bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[11px] transition"
                                        @click="verifyStudent(row)"
                                    >
                                        Verify Marks
                                    </button>
                                    <button
                                        v-if="row.verification_status !== 'rejected'"
                                        type="button"
                                        class="px-2 py-1 rounded border border-red-300 text-red-700 hover:bg-red-50 font-bold text-[11px] transition"
                                        @click="rejectStudent(row)"
                                    >
                                        Reject
                                    </button>
                                    <button
                                        type="button"
                                        class="px-2 py-1 rounded bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-semibold text-[11px] transition"
                                        @click="viewStudentHistory(row)"
                                    >
                                        History
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- INLINE SUBJECT BREAKDOWN FOR PRINTING (WHEN PRINT TOGGLE ENABLED) -->
                        <tr v-if="includeSubjectMarksInPrint && row.subject_marks && row.subject_marks.length" class="hidden print:table-row bg-slate-50/50">
                            <td colspan="7" class="py-2 px-6">
                                <div class="text-[10px] font-bold text-slate-500 mb-1 uppercase">Subject-Wise Marks Breakdown:</div>
                                <div class="flex flex-wrap gap-x-4 gap-y-1 text-[11px]">
                                    <span v-for="(sub, sIdx) in row.subject_marks" :key="sIdx" class="font-medium">
                                        {{ sub.subject_label }}: <strong class="text-indigo-800 font-mono">{{ sub.marks }}</strong> (Grade A1)
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="!filteredRows.length">
                        <td colspan="8" class="py-10 text-center text-gray-400 text-xs">
                            No Full A1 achievers found{{ searchQuery ? ` matching "${searchQuery}"` : '' }} for {{ selectedYear }}.
                        </td>
                    </tr>
                </tbody>
            </table>
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
            :sahodayaId="sahodaya.id"
            @close="showHistoryModal = false"
        />

        <!-- PDF PREVIEW MODAL -->
        <PdfPreviewModal
            :show="showPdfPreview"
            :pdf-url="pdfPreviewUrl"
            title="Full A1 Achievers — PDF Preview"
            @close="showPdfPreview = false"
        />
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PdfPreviewModal from '@/Components/ui/PdfPreviewModal.vue';
import SubjectMarksPreviewModal from '@/Components/BoardResults/SubjectMarksPreviewModal.vue';
import StudentHistoryModal from '@/Components/BoardResults/StudentHistoryModal.vue';
import BoardResultsReportSubNav from '@/Components/BoardResults/BoardResultsReportSubNav.vue';
import BoardResultsVerificationSubNav from '@/Components/BoardResults/BoardResultsVerificationSubNav.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useConfirm } from '@/composables/useConfirm';

const { prompt } = useConfirm();

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    rows: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    classOptions: { type: Array, default: () => [10, 12] },
    streamOptions: { type: Array, default: () => ['Science', 'Commerce', 'Humanities'] },
    academicYearOptions: { type: Array, default: () => [] },
});

const searchQuery = ref('');
const selectedYear = ref(props.filters.academic_year);
const selectedClass = ref(props.filters.class || '');
const selectedStream = ref(props.filters.stream || '');

const pdfPreviewUrl = computed(() => {
    let url = `/sahodaya-admin/${props.sahodaya.id}/board-results/reports/full-a1-achievers/pdf?academic_year=${encodeURIComponent(selectedYear.value || '')}`;
    if (selectedClass.value) {
        url += `&class=${selectedClass.value}`;
    }
    if (selectedStream.value) {
        url += `&stream=${encodeURIComponent(selectedStream.value)}`;
    }
    return url;
});

// Explicit download — the same PDF endpoint streams inline for preview by default,
// so the download link forces an actual attachment via &download=1.
const pdfDownloadUrl = computed(() => `${pdfPreviewUrl.value}&download=1`);

const showPdfPreview = ref(false);
function openPreview() {
    showPdfPreview.value = true;
}

const includeSubjectMarksInPrint = ref(true);

const showSubjectModal = ref(false);
const selectedStudent = ref(null);

const showHistoryModal = ref(false);
const historyStudent = ref(null);

function applyServerFilters() {
    router.get(
        `/sahodaya-admin/${props.sahodaya.id}/board-results/reports/full-a1-achievers`,
        {
            academic_year: selectedYear.value,
            class: selectedClass.value,
            stream: selectedClass.value === 12 ? selectedStream.value : null,
        },
        { preserveScroll: true, preserveState: true },
    );
}

const filteredRows = computed(() => {
    if (!searchQuery.value.trim()) return props.rows;
    const q = searchQuery.value.toLowerCase();
    return props.rows.filter(
        (row) =>
            row.student_name?.toLowerCase().includes(q) ||
            row.roll_no?.toLowerCase().includes(q) ||
            row.school_name?.toLowerCase().includes(q),
    );
});

const distinctSchoolCount = computed(() => new Set(props.rows.map((r) => r.school_id)).size);
const class10Count = computed(() => props.rows.filter((r) => r.class === 10).length);
const class12Count = computed(() => props.rows.filter((r) => r.class === 12).length);

function previewStudentMarks(student) {
    selectedStudent.value = student;
    showSubjectModal.value = true;
}

function viewStudentHistory(student) {
    historyStudent.value = student;
    showHistoryModal.value = true;
}

function openHistorySearch() {
    historyStudent.value = null;
    showHistoryModal.value = true;
}

function onModalViewHistory(student) {
    showSubjectModal.value = false;
    viewStudentHistory(student);
}

function verifyStudent(row) {
    if (!row.board_result_id) return;
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/board-results/${row.board_result_id}/toppers/${row.id}/verify-marksheet`,
        {},
        { preserveScroll: true }
    );
}

async function rejectStudent(row) {
    if (!row.board_result_id) return;
    const reason = await prompt({
        message: `Rejection reason for ${row.student_name}:`,
        inputValue: row.rejection_reason || 'Marksheet mismatch or invalid document.',
        inputMultiline: true,
    });
    if (reason === null) return;

    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/board-results/${row.board_result_id}/toppers/${row.id}/reject-marksheet`,
        { reason },
        { preserveScroll: true }
    );
}

function printReport() {
    window.print();
}
</script>
