<template>
    <SahodayaAdminLayout title="School-Wise Toppers" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">

        <!-- PRINT HEADER (ONLY VISIBLE IN PRINT MODE) -->
        <div class="hidden print:block mb-6 border-b border-slate-300 pb-4 text-center">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold uppercase tracking-wider text-slate-900">{{ sahodaya?.name || 'Sahodaya Complex' }}</h1>
                    <p class="text-xs text-slate-600 font-semibold">Official Academic Board Result Report — School-Wise Toppers</p>
                </div>
                <div class="text-right text-xs text-slate-500">
                    <p>Academic Year: <strong>{{ selectedYear }}</strong></p>
                    <p>Generated: {{ new Date().toLocaleDateString() }}</p>
                </div>
            </div>
            <div class="mt-3 text-xs text-slate-700 font-medium bg-slate-100 py-1.5 px-3 rounded flex justify-between">
                <span>Class {{ selectedClass }}</span>
                <span>Schools listed: <strong>{{ rows.length }}</strong></span>
            </div>
        </div>

        <!-- HERO BANNER -->
        <div class="print:hidden relative overflow-hidden rounded-2xl mb-6 bg-gradient-to-br from-[#0b2558] via-[#123a7a] to-[#1e4d9e] text-white p-6 sm:p-8">
            <div class="absolute -right-8 -top-8 text-[140px] opacity-10 leading-none select-none">🏫</div>
            <div class="relative flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-widest text-amber-300 mb-1">Academic Results · School Register</p>
                    <h1 class="text-2xl sm:text-3xl font-extrabold">School-Wise Toppers</h1>
                    <p class="text-sm text-blue-100 mt-1.5 max-w-xl">
                        Every member school's own <strong class="text-white">#1 topper</strong>, side by side — distinct from the pooled Sahodaya-wide Top-N, so smaller schools stay represented.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
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
            <div class="relative mt-6 grid grid-cols-2 sm:grid-cols-3 gap-3">
                <div class="rounded-xl bg-white/10 border border-white/15 backdrop-blur-sm p-3.5">
                    <p class="text-[10px] font-semibold text-blue-200 uppercase tracking-wide">Schools Listed</p>
                    <p class="text-2xl font-extrabold text-amber-300 mt-1">{{ rows.length }}</p>
                </div>
                <div class="rounded-xl bg-white/10 border border-white/15 backdrop-blur-sm p-3.5">
                    <p class="text-[10px] font-semibold text-blue-200 uppercase tracking-wide">With a Topper</p>
                    <p class="text-2xl font-extrabold text-white mt-1">{{ withTopperCount }}</p>
                </div>
                <div class="rounded-xl bg-white/10 border border-white/15 backdrop-blur-sm p-3.5">
                    <p class="text-[10px] font-semibold text-blue-200 uppercase tracking-wide">Not Yet Submitted</p>
                    <p class="text-2xl font-extrabold text-white mt-1">{{ rows.length - withTopperCount }}</p>
                </div>
            </div>
        </div>

        <BoardResultsVerificationSubNav :sahodayaId="sahodaya.id" active="toppers" :currentClass="selectedClass" />
        <BoardResultsReportSubNav :sahodayaId="sahodaya.id" active="school-wise-toppers" />

        <!-- FILTER CONTROLS -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm mb-6 space-y-4 print:hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-base font-bold text-gray-900">⚡ Filters & Report Mode</span>
                    <span class="text-xs text-gray-500">({{ filteredRows.length }} school(s)/row(s) found)</span>
                </div>

                <div class="relative w-full sm:w-72">
                    <input
                        v-model="searchQuery"
                        type="text"
                        class="field text-xs pl-8 pr-3 py-2 w-full bg-gray-50 border-gray-200 focus:bg-white"
                        placeholder="Search school or student name..."
                    >
                    <span class="absolute left-2.5 top-2.5 text-gray-400 text-xs">🔍</span>
                </div>
            </div>

            <div class="grid sm:grid-cols-2 md:grid-cols-5 gap-3 items-end">
                <div>
                    <label class="form-label mb-1 text-[11px] font-bold text-gray-600 uppercase">Report Mode</label>
                    <SearchableSelect
                        v-model="selectedMode"
                        :options="[{ value: 'one_per_school', label: '🏫 1 Overall Topper Per School' }, { value: 'all', label: '📋 Total Toppers List' }]"
                        :all-option="false"
                        @change="applyServerFilters"
                    />
                </div>

                <div>
                    <label class="form-label mb-1 text-[11px] font-bold text-gray-600 uppercase">Academic Year</label>
                    <SearchableSelect
                        v-model="selectedYear"
                        :options="academicYearSelectOptions"
                        :all-option="false"
                        @change="applyServerFilters"
                    />
                </div>

                <div>
                    <label class="form-label mb-1 text-[11px] font-bold text-gray-600 uppercase">Class</label>
                    <SearchableSelect
                        v-model="selectedClass"
                        :options="classSelectOptions"
                        :all-option="false"
                        @change="applyServerFilters"
                    />
                </div>

                <div>
                    <label class="form-label mb-1 text-[11px] font-bold text-gray-600 uppercase">Stream</label>
                    <SearchableSelect
                        v-model="selectedStream"
                        :options="streamOptions"
                        :all-option="true"
                        all-label="All Streams"
                        :disabled="selectedClass !== 12"
                        @change="applyServerFilters"
                    />
                </div>

                <div v-if="selectedMode === 'one_per_school'" class="flex items-center gap-2 pt-5">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" v-model="onlyMissing" class="rounded border-gray-300 text-indigo-600 shadow-xs focus:ring-indigo-500">
                        <span class="text-[11px] font-semibold text-slate-700">Missing topper</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- TABLE -->
        <div class="card !p-0 overflow-x-auto shadow-sm border border-gray-200 bg-white print:border-0 print:shadow-none">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between print:hidden bg-gradient-to-r from-amber-50 to-white">
                <div>
                    <h2 class="font-bold text-gray-900 text-base flex items-center gap-2">
                        🏫 {{ selectedMode === 'one_per_school' ? 'School #1 Toppers' : 'Total Toppers List' }} — Class {{ selectedClass }} ({{ selectedYear }})
                    </h2>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ selectedMode === 'one_per_school' ? 'One row per member school showing that school\'s own top-ranked overall student.' : 'Complete list of all submitted toppers across member schools.' }}
                    </p>
                </div>
            </div>

            <table class="data-table min-w-[850px] w-full text-left print:min-w-full">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-xs uppercase font-semibold print:bg-slate-100 print:text-slate-800">
                        <th class="py-3 px-4">School Name</th>
                        <th class="py-3 px-4">Topper Name</th>
                        <th class="py-3 px-4">Roll No</th>
                        <th class="py-3 px-4">Stream</th>
                        <th class="py-3 px-4 text-center">Marks</th>
                        <th class="py-3 px-4 text-center">Percentage</th>
                        <th class="py-3 px-4 text-center">Result Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-xs print:divide-slate-200">
                    <tr v-for="row in filteredRows" :key="row.school_id + (row.id || '')" class="hover:bg-amber-50/40 transition-colors">
                        <td class="py-2.5 px-4 font-bold text-gray-900">{{ row.school_name }}</td>
                        <td class="py-2.5 px-4 text-gray-800 font-semibold">{{ row.student_name || '—' }}</td>
                        <td class="py-2.5 px-4 font-mono text-gray-600">{{ row.roll_no || '—' }}</td>
                        <td class="py-2.5 px-4 text-gray-500">{{ row.stream || '—' }}</td>
                        <td class="py-2.5 px-4 text-center font-semibold">
                            <span v-if="row.marks_obtained !== null">{{ row.marks_obtained }}/{{ row.total_marks }}</span>
                            <span v-else>—</span>
                        </td>
                        <td class="py-2.5 px-4 text-center font-bold text-emerald-600">
                            {{ row.percentage !== null ? `${row.percentage.toFixed(2)}%` : '—' }}
                        </td>
                        <td class="py-2.5 px-4 text-center">
                            <span v-if="row.result_status === 'verified'" class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-extrabold border border-emerald-200">Verified ✅</span>
                            <span v-else-if="row.result_status === 'submitted'" class="text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-extrabold border border-amber-200">Submitted ⏳</span>
                            <span v-else-if="row.has_topper" class="text-[10px] px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 font-extrabold border border-blue-200">{{ row.result_status }}</span>
                            <span v-else class="text-[10px] px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 font-extrabold border border-rose-200">Not submitted</span>
                        </td>
                    </tr>
                    <tr v-if="!filteredRows.length">
                        <td colspan="7" class="py-10 text-center text-gray-400 text-xs">
                            No records match{{ searchQuery ? ` "${searchQuery}"` : '' }} for {{ selectedYear }}.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- PDF PREVIEW MODAL -->
        <PdfPreviewModal
            :show="showPdfPreview"
            :pdf-url="pdfPreviewUrl"
            title="School-Wise Toppers — PDF Preview"
            @close="showPdfPreview = false"
        />
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PdfPreviewModal from '@/Components/ui/PdfPreviewModal.vue';
import BoardResultsReportSubNav from '@/Components/BoardResults/BoardResultsReportSubNav.vue';
import BoardResultsVerificationSubNav from '@/Components/BoardResults/BoardResultsVerificationSubNav.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { router } from '@inertiajs/vue3';
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
const onlyMissing = ref(false);
const selectedYear = ref(props.filters.academic_year);
const selectedClass = ref(props.filters.class || 10);
const selectedStream = ref(props.filters.stream || null);
const selectedMode = ref(props.filters.mode || 'one_per_school');

const academicYearSelectOptions = computed(() =>
    props.academicYearOptions.map((ay) => ({ value: ay.label, label: ay.label })),
);

const classSelectOptions = computed(() =>
    props.classOptions.map((c) => ({ value: c, label: `Class ${c}` })),
);

const pdfPreviewUrl = computed(() => {
    let url = `/sahodaya-admin/${props.sahodaya.id}/board-results/reports/school-wise-toppers/pdf?academic_year=${encodeURIComponent(selectedYear.value || '')}&class=${selectedClass.value}`;
    if (selectedStream.value) url += `&stream=${encodeURIComponent(selectedStream.value)}`;
    return url;
});

const pdfDownloadUrl = computed(() => `${pdfPreviewUrl.value}&download=1`);

const showPdfPreview = ref(false);
function openPreview() {
    showPdfPreview.value = true;
}

function applyServerFilters() {
    router.get(
        `/sahodaya-admin/${props.sahodaya.id}/board-results/reports/school-wise-toppers`,
        {
            academic_year: selectedYear.value,
            class: selectedClass.value,
            stream: selectedClass.value === 12 ? selectedStream.value : null,
            mode: selectedMode.value,
        },
        { preserveScroll: true, preserveState: true },
    );
}

const filteredRows = computed(() => {
    let list = props.rows;
    if (onlyMissing.value) {
        list = list.filter((row) => !row.has_topper);
    }
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(
            (row) =>
                row.school_name?.toLowerCase().includes(q) ||
                row.student_name?.toLowerCase().includes(q),
        );
    }
    return list;
});

const withTopperCount = computed(() => props.rows.filter((r) => r.has_topper).length);

function printReport() {
    window.print();
}
</script>
