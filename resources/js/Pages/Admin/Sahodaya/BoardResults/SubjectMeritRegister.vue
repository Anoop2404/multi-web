<template>
    <SahodayaAdminLayout title="Subject-wise Merit Register" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Subject-wise Merit Register" eyebrow="Academic Results"
                    description="Comprehensive rank-based subject toppers report collected across member schools.">
            <template #actions>
                <div class="flex items-center gap-2 print:hidden">
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
                <div>
                    <label class="form-label mb-1 text-[11px] font-bold text-gray-600 uppercase">Subject</label>
                    <select v-model="selectedSubject" class="field text-xs bg-white">
                        <option value="">All Subjects ({{ availableSubjects.length }})</option>
                        <option v-for="subj in availableSubjects" :key="subj" :value="subj">{{ subj }}</option>
                    </select>
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
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    rows: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    classOptions: { type: Array, default: () => [10, 12] },
    schoolOptions: { type: Array, default: () => [] },
    academicYearOptions: { type: Array, default: () => [] },
});

const selectedYear = ref(props.filters.academic_year || '');
const selectedClass = ref(props.filters.class ?? null);
const selectedSubject = ref('');
const selectedSchoolId = ref('');
const selectedStream = ref('');
const selectedRankCap = ref(0);
const searchQuery = ref('');

const availableSubjects = computed(() => {
    const set = new Set();
    props.rows.forEach(r => { if (r.subject) set.add(r.subject); });
    return Array.from(set).sort();
});

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

function applyServerFilters() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/board-results/reports/subject-merit`, {
        academic_year: selectedYear.value,
        class: selectedClass.value || undefined,
    }, { preserveState: true, preserveScroll: true });
}

function printReport() {
    window.print();
}
</script>
