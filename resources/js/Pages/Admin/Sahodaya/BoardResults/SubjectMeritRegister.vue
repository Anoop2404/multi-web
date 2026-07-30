<template>
    <SahodayaAdminLayout title="Subject-wise Merit Register" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <!-- PRINT HEADER -->
        <div class="hidden print:block mb-6 border-b border-slate-300 pb-4 text-center">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold uppercase tracking-wider text-slate-900">{{ sahodaya?.name || 'Sahodaya Complex' }}</h1>
                    <p class="text-xs text-slate-600 font-semibold">Official Subject-Wise Merit Register Report</p>
                </div>
                <div class="text-right text-xs text-slate-500">
                    <p>Academic Year: <strong>{{ selectedYear }}</strong></p>
                    <p>Generated: {{ new Date().toLocaleDateString() }}</p>
                </div>
            </div>
        </div>

        <PageHeader title="Subject-wise Merit Register" eyebrow="Academic Results"
                    description="Comprehensive rank-based subject toppers report collected across member schools.">
            <template #actions>
                <div class="flex items-center gap-2 print:hidden">
                    <button type="button" @click="openHistorySearch" class="btn-secondary text-xs flex items-center gap-1.5 font-bold">
                        <span>📜</span> Student History
                    </button>
                    <button type="button" @click="printReport" class="btn-secondary text-xs flex items-center gap-1.5 font-bold">
                        <span>🖨</span> Print Register
                    </button>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/reports`" class="btn-secondary text-xs">← Reports Hub</Link>
                </div>
            </template>
        </PageHeader>

        <!-- ADVANCED FILTER CONTROLS -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm mb-6 space-y-4 print:hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-base font-bold text-gray-900">⚡ Filter & Rank Register</span>
                    <span class="text-xs text-gray-500">({{ filteredRows.length }} total toppers found)</span>
                </div>

                <!-- SEARCH BAR -->
                <div class="relative w-full sm:w-72">
                    <input
                        v-model="searchQuery"
                        type="text"
                        class="field text-xs pl-8 pr-3 py-2 w-full bg-gray-50 border-gray-200 focus:bg-white"
                        placeholder="Search student, roll no, subject, school..."
                    >
                    <span class="absolute left-2.5 top-2.5 text-gray-400 text-xs">🔍</span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Active filters</span>
                <span v-for="chip in activeFilters" :key="chip.label" class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-100">
                    {{ chip.label }}
                </span>
                <button
                    v-if="activeFilters.length"
                    type="button"
                    class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-white text-slate-600 border border-slate-200 hover:bg-slate-50"
                    @click="resetFilters"
                >
                    Reset all
                </button>
            </div>

            <div class="grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 items-end">
                <!-- ACADEMIC YEAR -->
                <div>
                    <label class="form-label mb-1 text-[11px] font-bold text-gray-600 uppercase">Academic Year</label>
                    <select v-model="selectedYear" class="field text-xs bg-white font-semibold" @change="applyServerFilters">
                        <option v-for="ay in academicYearOptions" :key="ay.id" :value="ay.label">
                            {{ ay.label }}{{ ay.status === 'active' ? ' (Active)' : '' }}
                        </option>
                    </select>
                </div>

                <!-- CLASS -->
                <div>
                    <label class="form-label mb-1 text-[11px] font-bold text-gray-600 uppercase">Class</label>
                    <select v-model="selectedClass" class="field text-xs bg-white font-semibold" @change="applyServerFilters">
                        <option :value="null">All Classes (10 & 12)</option>
                        <option v-for="c in classOptions" :key="c" :value="c">Class {{ c }}</option>
                    </select>
                </div>

                <!-- SUBJECT FILTER -->
                <div class="relative">
                    <label class="form-label mb-1 text-[11px] font-bold text-gray-600 uppercase">Subject</label>
                    <button
                        type="button"
                        class="field text-xs bg-white font-semibold w-full flex items-center justify-between gap-2"
                        @click="toggleSubjectDropdown"
                    >
                        <span class="truncate">{{ selectedSubjectLabel || `All Subjects (${subjectChoices.length})` }}</span>
                        <span class="text-slate-400">▾</span>
                    </button>

                    <div
                        v-if="subjectDropdownOpen"
                        class="absolute z-30 mt-2 w-full rounded-xl border border-slate-200 bg-white shadow-xl p-3"
                    >
                        <input
                            v-model="subjectSearch"
                            type="text"
                            class="field text-xs w-full bg-slate-50"
                            placeholder="Search subjects..."
                            @input="subjectDropdownOpen = true"
                        >

                        <div class="mt-2 max-h-60 overflow-y-auto space-y-1">
                            <button
                                type="button"
                                class="w-full text-left px-3 py-2 rounded-lg text-xs font-semibold transition"
                                :class="!selectedSubject ? 'bg-[#0f3d7a] text-white' : 'hover:bg-slate-50 text-slate-600'"
                                @click="selectSubject('')"
                            >
                                All Subjects
                            </button>

                            <button
                                v-for="subj in filteredSubjectOptions"
                                :key="subj.id ?? subj.label"
                                type="button"
                                class="w-full text-left px-3 py-2 rounded-lg text-xs font-semibold transition"
                                :class="selectedSubject === subj.label ? 'bg-[#0f3d7a] text-white' : 'hover:bg-slate-50 text-slate-700'"
                                @click="selectSubject(subj.label)"
                            >
                                {{ subj.label }}
                            </button>

                            <p v-if="!filteredSubjectOptions.length" class="px-3 py-3 text-xs text-slate-400">
                                No matching subjects.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- MEMBER SCHOOL FILTER -->
                <div>
                    <label class="form-label mb-1 text-[11px] font-bold text-gray-600 uppercase">Member School</label>
                    <select v-model="selectedSchoolId" class="field text-xs bg-white">
                        <option value="">All Member Schools</option>
                        <option v-for="sch in schoolOptions" :key="sch.id" :value="sch.id">{{ sch.name }}</option>
                    </select>
                </div>

                <!-- STREAM FILTER -->
                <div>
                    <label class="form-label mb-1 text-[11px] font-bold text-gray-600 uppercase">Stream</label>
                    <select v-model="selectedStream" class="field text-xs bg-white">
                        <option value="">All Streams</option>
                        <option value="Science">Science</option>
                        <option value="Commerce">Commerce</option>
                        <option value="Humanities">Humanities / Arts</option>
                    </select>
                </div>

                <!-- RANK LIMIT FILTER -->
                <div>
                    <label class="form-label mb-1 text-[11px] font-bold text-gray-600 uppercase">Rank Limit</label>
                    <select v-model.number="selectedRankCap" class="field text-xs bg-white font-bold text-indigo-700">
                        <option :value="0">All Ranks</option>
                        <option :value="1">🥇 Rank 1 Only</option>
                        <option :value="3">Top 3 Ranks</option>
                        <option :value="5">Top 5 Ranks</option>
                        <option :value="10">Top 10 Ranks</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 mb-4 print:hidden">
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition"
                    :class="previewMode === 'single' ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                    @click="previewMode = 'single'"
                >
                    Preview selected subject
                </button>
                <button
                    type="button"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition"
                    :class="previewMode === 'all' ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                    @click="previewMode = 'all'"
                >
                    Preview all subjects
                </button>
            </div>

            <button
                type="button"
                class="btn-secondary text-xs flex items-center gap-1.5 font-bold"
                @click="printReport"
            >
                <span>🖨</span> Print / Save PDF
            </button>
        </div>

        <!-- STATS SUMMARY BAR -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6 print:hidden">
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Total Performers</p>
                <p class="text-2xl font-bold text-indigo-600 mt-1">{{ filteredRows.length }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Subjects Covered</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ distinctSubjectCount }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Schools Represented</p>
                <p class="text-2xl font-bold text-violet-600 mt-1">{{ distinctSchoolCount }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-2xs">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Rank 1 Achievers</p>
                <p class="text-2xl font-bold text-amber-500 mt-1">{{ rankOneCount }}</p>
            </div>
        </div>

        <!-- MERIT REGISTER DATA TABLE -->
        <div class="card !p-0 overflow-x-auto shadow-sm border border-gray-200 bg-white">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-gray-900 text-base">Subject Merit Register — Academic Year {{ selectedYear }}</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Rank-ordered per subject based on marks submitted across member schools.</p>
                </div>
            </div>

            <table class="data-table min-w-[850px] w-full text-left">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-xs uppercase font-semibold">
                        <th class="py-3 px-4 w-20 text-center">Rank</th>
                        <th class="py-3 px-4 font-bold text-gray-800">Subject</th>
                        <th class="py-3 px-4">Student Name</th>
                        <th class="py-3 px-4">CBSE Roll No</th>
                        <th class="py-3 px-4">School Name</th>
                        <th class="py-3 px-4 text-center">Marks / 100</th>
                        <th class="py-3 px-4">Stream</th>
                        <th class="py-3 px-4 text-center">Class</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-xs">
                    <tr v-for="(row, i) in filteredRows" :key="i" class="hover:bg-indigo-50/20 transition-colors">
                        <!-- RANK BADGE -->
                        <td class="py-3 px-4 text-center">
                            <span v-if="row.rank === 1" class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-amber-100 text-amber-800 font-bold text-xs border border-amber-300 shadow-2xs" title="Rank 1 Gold">
                                🥇 1
                            </span>
                            <span v-else-if="row.rank === 2" class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-slate-200 text-slate-800 font-bold text-xs border border-slate-300" title="Rank 2 Silver">
                                🥈 2
                            </span>
                            <span v-else-if="row.rank === 3" class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-orange-100 text-amber-900 font-bold text-xs border border-orange-200" title="Rank 3 Bronze">
                                🥉 3
                            </span>
                            <span v-else class="inline-flex items-center justify-center px-2 py-0.5 rounded bg-gray-100 text-gray-700 font-bold">
                                #{{ row.rank || (i + 1) }}
                            </span>
                        </td>

                        <td class="py-3 px-4 font-bold text-indigo-900">
                            {{ row.subject }}
                        </td>
                        <td class="py-3 px-4 font-bold text-gray-900">
                            {{ row.student_name }}
                        </td>
                        <td class="py-3 px-4 text-gray-500 font-mono">
                            {{ row.roll_no || '—' }}
                        </td>
                        <td class="py-3 px-4 font-semibold text-gray-700">
                            {{ (row.school_name || '').toUpperCase() }}
                        </td>
                        <td class="py-3 px-4 text-center font-bold text-emerald-600 text-sm">
                            {{ row.marks }}
                        </td>
                        <td class="py-3 px-4 text-gray-600">
                            <span v-if="row.stream" class="px-2 py-0.5 bg-gray-100 text-gray-700 font-medium rounded text-[11px]">
                                {{ row.stream }}
                            </span>
                            <span v-else>—</span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 font-bold rounded text-[11px]">
                                Class {{ row.class || 'XII' }}
                            </span>
                        </td>
                    </tr>

                    <tr v-if="!filteredRows.length">
                        <td colspan="8" class="p-12 text-center text-gray-400 text-xs">
                            No subject merit toppers found matching the selected filters for {{ selectedYear }}.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-8 space-y-6">
            <div class="flex items-center justify-between gap-3 print:hidden">
                <div>
                    <h2 class="text-sm font-bold text-slate-800">Preview</h2>
                    <p class="text-xs text-slate-500">
                        {{ previewMode === 'all'
                            ? 'All subjects are rendered as separate report pages for print / PDF.'
                            : 'Use the selected subject preview before generating the full bundle.' }}
                    </p>
                </div>
                <p class="text-xs text-slate-400">
                    {{ previewMode === 'all' ? `${previewPages.length} page(s)` : `${previewRows.length} row(s)` }}
                </p>
            </div>

            <template v-if="previewMode === 'single'">
                <section v-if="previewRows.length" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 preview-page">
                    <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-4">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[11px] uppercase font-semibold text-slate-400">Sahodaya</p>
                            <p class="text-sm font-bold text-slate-900 mt-1">{{ sahodaya.name }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[11px] uppercase font-semibold text-slate-400">Class / Subject</p>
                            <p class="text-sm font-bold text-[#0f3d7a] mt-1">Class {{ selectedClass || 'XII' }} · {{ previewSubjectLabel || 'All Subjects' }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[11px] uppercase font-semibold text-slate-400">Report</p>
                            <p class="text-sm font-bold text-violet-700 mt-1">Subject Merit Register</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[11px] uppercase font-semibold text-slate-400">Academic Year</p>
                            <p class="text-sm font-bold text-emerald-700 mt-1">{{ selectedYear }}</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3 mb-3">
                        <div>
                            <h3 class="text-base font-bold text-slate-900">{{ previewSubjectLabel || 'Selected Subject' }}</h3>
                            <p class="text-xs text-slate-500">Page preview for print or PDF generation.</p>
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700">
                            {{ previewRows.length }} row(s)
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-left text-[11px] uppercase text-slate-500">
                                <tr>
                                    <th class="p-3 w-16">S.No</th>
                                    <th class="p-3 w-20">Rank</th>
                                    <th class="p-3">Student</th>
                                    <th class="p-3">School</th>
                                    <th class="p-3">Percentage</th>
                                    <th class="p-3">Marks</th>
                                    <th class="p-3">Roll No</th>
                                    <th class="p-3">Stream</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, index) in previewRows" :key="`${row.subject}-${row.student_name}-${index}`" class="border-t">
                                    <td class="p-3 text-slate-400 font-semibold">{{ index + 1 }}</td>
                                    <td class="p-3 font-semibold text-[#0f3d7a]">#{{ row.rank }}</td>
                                    <td class="p-3 font-semibold text-slate-900">{{ row.student_name }}</td>
                                    <td class="p-3 text-slate-600">{{ row.school_name }}</td>
                                    <td class="p-3 font-semibold text-emerald-600">{{ row.percentage != null ? `${row.percentage}%` : '—' }}</td>
                                    <td class="p-3 font-semibold text-slate-700">{{ row.marks }} / 100</td>
                                    <td class="p-3 text-slate-500">{{ row.roll_no || '—' }}</td>
                                    <td class="p-3 text-slate-500">{{ row.stream || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>

            <template v-else>
                <section
                    v-for="(page, pageIndex) in previewPages"
                    :key="page.subject"
                    class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 preview-page"
                    :style="pageIndex < previewPages.length - 1 ? 'page-break-after: always;' : ''"
                >
                    <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-4">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[11px] uppercase font-semibold text-slate-400">Sahodaya</p>
                            <p class="text-sm font-bold text-slate-900 mt-1">{{ sahodaya.name }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[11px] uppercase font-semibold text-slate-400">Class / Subject</p>
                            <p class="text-sm font-bold text-[#0f3d7a] mt-1">Class {{ selectedClass || 'XII' }} · {{ page.subject }}</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[11px] uppercase font-semibold text-slate-400">Report</p>
                            <p class="text-sm font-bold text-violet-700 mt-1">Subject Merit Register</p>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                            <p class="text-[11px] uppercase font-semibold text-slate-400">Academic Year</p>
                            <p class="text-sm font-bold text-emerald-700 mt-1">{{ selectedYear }}</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3 mb-3">
                        <div>
                            <h3 class="text-base font-bold text-slate-900">{{ page.subject }}</h3>
                            <p class="text-xs text-slate-500">Page {{ pageIndex + 1 }} of {{ previewPages.length }} · {{ page.rows.length }} row(s)</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-left text-[11px] uppercase text-slate-500">
                                <tr>
                                    <th class="p-3 w-16">S.No</th>
                                    <th class="p-3 w-20">Rank</th>
                                    <th class="p-3">Student</th>
                                    <th class="p-3">School</th>
                                    <th class="p-3">Percentage</th>
                                    <th class="p-3">Marks</th>
                                    <th class="p-3">Roll No</th>
                                    <th class="p-3">Stream</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, index) in page.rows" :key="`${page.subject}-${row.student_name}-${index}`" class="border-t">
                                    <td class="p-3 text-slate-400 font-semibold">{{ index + 1 }}</td>
                                    <td class="p-3 font-semibold text-[#0f3d7a]">#{{ row.rank }}</td>
                                    <td class="p-3 font-semibold text-slate-900">{{ row.student_name }}</td>
                                    <td class="p-3 text-slate-600">{{ row.school_name }}</td>
                                    <td class="p-3 font-semibold text-emerald-600">{{ row.percentage != null ? `${row.percentage}%` : '—' }}</td>
                                    <td class="p-3 font-semibold text-slate-700">{{ row.marks }} / 100</td>
                                    <td class="p-3 text-slate-500">{{ row.roll_no || '—' }}</td>
                                    <td class="p-3 text-slate-500">{{ row.stream || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>
        </div>

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
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StudentHistoryModal from '@/Components/BoardResults/StudentHistoryModal.vue';

const showHistoryModal = ref(false);
const historyStudent = ref(null);

function openHistorySearch() {
    historyStudent.value = null;
    showHistoryModal.value = true;
}

function viewStudentHistory(row) {
    historyStudent.value = {
        student_name: row.student_name,
        roll_no: row.roll_no,
        admission_no: row.admission_no,
    };
    showHistoryModal.value = true;
}

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    rows: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    classOptions: { type: Array, default: () => [10, 12] },
    schoolOptions: { type: Array, default: () => [] },
    subjectOptions: { type: Array, default: () => [] },
    academicYearOptions: { type: Array, default: () => [] },
});

const selectedYear = ref(props.filters.academic_year || '');
const selectedClass = ref(props.filters.class ?? null);
const selectedSubject = ref('');
const subjectSearch = ref('');
const subjectDropdownOpen = ref(false);
const selectedSchoolId = ref('');
const selectedStream = ref('');
const selectedRankCap = ref(0);
const searchQuery = ref('');
const previewMode = ref('single');

const availableSubjects = computed(() => {
    const set = new Set();
    props.rows.forEach(r => { if (r.subject) set.add(r.subject); });
    return Array.from(set).sort();
});

const subjectChoices = computed(() => {
    if (props.subjectOptions.length) return props.subjectOptions;
    return availableSubjects.value.map(label => ({ id: label, label }));
});

const filteredSubjectOptions = computed(() => {
    const q = subjectSearch.value.trim().toLowerCase();
    if (!q) return subjectChoices.value;
    return subjectChoices.value.filter((subj) => subj.label?.toLowerCase().includes(q));
});

const selectedSubjectLabel = computed(() => selectedSubject.value || '');

function toggleSubjectDropdown() {
    if (subjectDropdownOpen.value) {
        subjectDropdownOpen.value = false;
        return;
    }

    subjectSearch.value = selectedSubject.value || '';
    subjectDropdownOpen.value = true;
}

function selectSubject(label) {
    selectedSubject.value = label;
    subjectSearch.value = label;
    subjectDropdownOpen.value = false;
}

const filteredRows = computed(() => {
    let result = [...props.rows];

    if (selectedSubject.value) {
        result = result.filter(r => r.subject?.toLowerCase() === selectedSubject.value.toLowerCase());
    }

    if (selectedSchoolId.value) {
        result = result.filter(r => r.school_id === selectedSchoolId.value);
    }

    if (selectedStream.value) {
        result = result.filter(r => r.stream?.toLowerCase().includes(selectedStream.value.toLowerCase()));
    }

    if (selectedRankCap.value > 0) {
        result = result.filter(r => (r.rank ?? 1) <= selectedRankCap.value);
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        result = result.filter(r =>
            r.subject?.toLowerCase().includes(q) ||
            r.student_name?.toLowerCase().includes(q) ||
            r.school_name?.toLowerCase().includes(q) ||
            r.roll_no?.toLowerCase().includes(q)
        );
    }

    return result;
});

const baseRows = computed(() => {
    let result = [...props.rows];

    if (selectedSchoolId.value) {
        result = result.filter(r => r.school_id === selectedSchoolId.value);
    }

    if (selectedStream.value) {
        result = result.filter(r => r.stream?.toLowerCase().includes(selectedStream.value.toLowerCase()));
    }

    if (selectedRankCap.value > 0) {
        result = result.filter(r => (r.rank ?? 1) <= selectedRankCap.value);
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        result = result.filter(r =>
            r.subject?.toLowerCase().includes(q) ||
            r.student_name?.toLowerCase().includes(q) ||
            r.school_name?.toLowerCase().includes(q) ||
            r.roll_no?.toLowerCase().includes(q)
        );
    }

    return result;
});

const previewGroups = computed(() => {
    const map = new Map();
    for (const row of baseRows.value) {
        const subject = row.subject || 'Unknown Subject';
        if (!map.has(subject)) {
            map.set(subject, []);
        }
        map.get(subject).push(row);
    }

    return Array.from(map.entries())
        .sort((a, b) => a[0].localeCompare(b[0]))
        .map(([subject, rows]) => ({
            subject,
            rows: [...rows].sort((a, b) => (a.rank ?? 0) - (b.rank ?? 0) || (b.marks ?? 0) - (a.marks ?? 0)),
        }));
});

const previewSubjectLabel = computed(() => {
    if (selectedSubject.value) return selectedSubject.value;
    return previewGroups.value[0]?.subject || '';
});

const previewRows = computed(() => {
    if (!previewGroups.value.length) return [];
    const target = selectedSubject.value || previewGroups.value[0]?.subject;
    return previewGroups.value.find(group => group.subject?.toLowerCase() === target?.toLowerCase())?.rows || [];
});

const distinctSubjectCount = computed(() => {
    const set = new Set();
    filteredRows.value.forEach(r => { if (r.subject) set.add(r.subject); });
    return set.size;
});

const distinctSchoolCount = computed(() => {
    const set = new Set();
    filteredRows.value.forEach(r => { if (r.school_id) set.add(r.school_id); });
    return set.size;
});

const rankOneCount = computed(() => {
    return filteredRows.value.filter(r => r.rank === 1).length;
});

const activeFilters = computed(() => {
    const chips = [];
    if (selectedYear.value) chips.push({ label: `Year: ${selectedYear.value}` });
    if (selectedClass.value) chips.push({ label: `Class: ${selectedClass.value}` });
    if (selectedSubject.value) chips.push({ label: `Subject: ${selectedSubject.value}` });
    if (selectedSchoolId.value) {
        const school = props.schoolOptions.find(s => s.id === selectedSchoolId.value);
        chips.push({ label: `School: ${school?.name || selectedSchoolId.value}` });
    }
    if (selectedStream.value) chips.push({ label: `Stream: ${selectedStream.value}` });
    if (selectedRankCap.value > 0) chips.push({ label: `Top ${selectedRankCap.value}` });
    if (searchQuery.value.trim()) chips.push({ label: `Search: ${searchQuery.value.trim()}` });
    return chips;
});

function resetFilters() {
    selectedYear.value = props.filters.academic_year || '';
    selectedClass.value = props.filters.class ?? null;
    selectedSubject.value = '';
    subjectSearch.value = '';
    subjectDropdownOpen.value = false;
    selectedSchoolId.value = '';
    selectedStream.value = '';
    selectedRankCap.value = 0;
    searchQuery.value = '';
    applyServerFilters();
}

function applyServerFilters() {
    subjectDropdownOpen.value = false;
    router.get(`/sahodaya-admin/${props.sahodaya.id}/board-results/reports/subject-merit`, {
        academic_year: selectedYear.value,
        class: selectedClass.value || undefined,
    }, { preserveState: true, preserveScroll: true });
}

function printReport() {
    window.print();
}

const previewPages = computed(() => previewGroups.value);
</script>
