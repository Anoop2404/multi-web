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

        <PageHeader title="Full A1 Achievers" eyebrow="Academic Results"
                    description="Students who scored A1 (91-100) in every subject they were entered for, across Class X and Class XII, all streams.">
            <template #actions>
                <div class="flex flex-wrap items-center gap-2 print:hidden">
                    <button type="button" @click="openHistorySearch" class="btn-secondary text-xs flex items-center gap-1.5 font-bold">
                        <span>📜</span> Student History Lookup
                    </button>
                    <button type="button" @click="printReport" class="btn-secondary text-xs flex items-center gap-1.5 font-bold">
                        <span>🖨</span> Print Report
                    </button>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/reports`" class="btn-secondary text-xs">← Reports Hub</Link>
                </div>
            </template>
        </PageHeader>

        <!-- FILTER CONTROLS -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm mb-6 space-y-4 print:hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-base font-bold text-gray-900">⚡ Filters & Achievers</span>
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

        <!-- STATS SUMMARY -->
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6 print:hidden">
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Total Achievers</p>
                <p class="text-2xl font-bold text-indigo-600 mt-1">{{ filteredRows.length }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Schools Represented</p>
                <p class="text-2xl font-bold text-violet-600 mt-1">{{ distinctSchoolCount }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Class 10 / Class 12</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ class10Count }} / {{ class12Count }}</p>
            </div>
        </div>

        <!-- ACHIEVERS TABLE -->
        <div class="card !p-0 overflow-x-auto shadow-sm border border-gray-200 bg-white print:border-0 print:shadow-none">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between print:hidden">
                <div>
                    <h2 class="font-bold text-gray-900 text-base">Full A1 Achievers — Academic Year {{ selectedYear }}</h2>
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
                        <th class="py-3 px-4 text-center">Class</th>
                        <th class="py-3 px-4">Stream</th>
                        <th class="py-3 px-4 text-center">Subjects (all A1)</th>
                        <th class="py-3 px-4 text-center">Lowest Mark</th>
                        <th class="py-3 px-4 text-right print:hidden">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-xs print:divide-slate-200">
                    <template v-for="(row, i) in filteredRows" :key="i">
                        <tr
                            class="hover:bg-indigo-50/30 transition-colors cursor-pointer"
                            @click="previewStudentMarks(row)"
                        >
                            <td class="py-2.5 px-4 font-bold text-gray-900">
                                <div class="flex items-center gap-1.5">
                                    <span>{{ row.student_name }}</span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 font-extrabold print:hidden">A1</span>
                                </div>
                            </td>
                            <td class="py-2.5 px-4 font-mono text-gray-600">{{ row.roll_no || '—' }}</td>
                            <td class="py-2.5 px-4 text-gray-700">{{ row.school_name }}</td>
                            <td class="py-2.5 px-4 text-center font-semibold">{{ row.class }}</td>
                            <td class="py-2.5 px-4 text-gray-500">{{ row.stream || '—' }}</td>
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
                            <td class="py-2.5 px-4 text-right print:hidden">
                                <div class="flex items-center justify-end gap-1.5" @click.stop>
                                    <button
                                        type="button"
                                        class="px-2 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-[11px] transition"
                                        @click="previewStudentMarks(row)"
                                    >
                                        Subject Marks
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
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SubjectMarksPreviewModal from '@/Components/BoardResults/SubjectMarksPreviewModal.vue';
import StudentHistoryModal from '@/Components/BoardResults/StudentHistoryModal.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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
const selectedClass = ref(props.filters.class ?? null);
const selectedStream = ref(props.filters.stream ?? null);
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

function printReport() {
    window.print();
}
</script>
