<template>
    <SahodayaAdminLayout title="Subject-Wise Top Scorers" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Subject-Wise Top Scorers" eyebrow="Academic Results · Class XII"
                    description="Highest scorer per subject, pooled across every member school and all streams.">
            <template #actions>
                <div class="flex rounded-lg border border-slate-200 overflow-hidden text-xs font-semibold print:hidden">
                    <button type="button" @click="setView('rank')" class="px-3 py-1.5" :class="!noRank ? 'bg-[#0f3d7a] text-white' : 'bg-white text-slate-600'">Rank</button>
                    <button type="button" @click="setView('percentage')" class="px-3 py-1.5" :class="noRank ? 'bg-[#0f3d7a] text-white' : 'bg-white text-slate-600'">Percentage</button>
                </div>
                <button type="button" @click="showPdfPreview = true" class="btn-secondary text-xs flex items-center gap-1.5 font-bold print:hidden">
                    <span>👁</span> Preview PDF
                </button>
                <a :href="pdfDownloadUrl" class="btn-primary text-xs flex items-center gap-1.5 font-bold print:hidden">
                    <span>📥</span> Download PDF Report
                </a>
                <button type="button" @click="printReport" class="btn-secondary text-sm font-bold flex items-center gap-1.5 print:hidden">
                    <span>🖨</span> Print
                </button>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/toppers`" class="btn-secondary text-sm print:hidden">⚙ Settings</Link>
            </template>
        </PageHeader>

        <div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4 print:hidden">
            <div class="card !p-4">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Sahodaya</p>
                <p class="text-lg font-bold text-slate-900 mt-1">{{ sahodaya.name }}</p>
            </div>
            <div class="card !p-4">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Class</p>
                <p class="text-lg font-bold text-[#0f3d7a] mt-1">Class 12</p>
            </div>
            <div class="card !p-4">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Report</p>
                <p class="text-lg font-bold text-violet-700 mt-1">Subject-Wise Top Scorers</p>
            </div>
            <div class="card !p-4">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Academic Year</p>
                <p class="text-lg font-bold text-emerald-700 mt-1">{{ filters.academic_year }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 mb-4 print:hidden">
            <div class="flex flex-wrap gap-2">
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/toppers/overall?class=12&academic_year=${filters.academic_year}`" class="text-sm font-semibold text-[#0f3d7a] hover:underline">
                    ← Overall Result
                </Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/toppers/achievers?class=12&academic_year=${filters.academic_year}`" class="text-sm font-semibold text-[#0f3d7a] hover:underline">
                    90%+ Achievers →
                </Link>
                <span class="text-xs text-slate-300 mx-1">|</span>
                <SearchableSelect
                    :model-value="filters.academic_year"
                    :options="academicYearSelectOptions"
                    :all-option="false"
                    placeholder="Select academic year"
                    @update:model-value="switchYear"
                />
            </div>
        </div>

        <div class="card !p-4 mb-4 print:hidden flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-500">Academic year {{ filters.academic_year }} · {{ filteredRows.length }} subject row(s)</p>
            <input v-model="search" type="text" placeholder="Search subject, student, school…" class="field text-xs py-1.5 w-64">
        </div>

        <!-- No-rank mode: each subject rendered as its own section (page-break on print), ordered by percentage. -->
        <div v-if="noRank && groupedBySubject.length" class="space-y-6">
            <div v-for="group in groupedBySubject" :key="group.subject" class="subject-print-page">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 bg-indigo-100 px-2 py-0.5 rounded">{{ group.subject }}</span>
                    <span class="text-xs text-slate-500">{{ group.rows.length }} student(s) · ordered by percentage</span>
                </div>
                <div class="card card--flush overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="p-3 w-16">S.No</th>
                                <th class="p-3">Student</th>
                                <th class="p-3">School</th>
                                <th class="p-3">Roll No</th>
                                <th class="p-3">Marks</th>
                                <th class="p-3">Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, i) in group.rows" :key="group.subject + '-' + row.student_name + '-' + i" class="border-t hover:bg-slate-50/60">
                                <td class="p-3 text-slate-400 font-semibold">{{ i + 1 }}</td>
                                <td class="p-3 font-semibold text-gray-900">{{ row.student_name }}</td>
                                <td class="p-3 text-gray-600">{{ row.school_name }}</td>
                                <td class="p-3 text-xs text-gray-500">{{ row.roll_no || '—' }}</td>
                                <td class="p-3 font-semibold text-emerald-600">{{ row.marks }} / 100</td>
                                <td class="p-3 font-semibold text-[#0f3d7a]">{{ row.percentage != null ? `${row.percentage}%` : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <p v-else-if="noRank" class="card p-10 text-center text-gray-400 text-sm">
            No subject-wise toppers recorded across member schools yet for Class XII (Academic year {{ filters.academic_year }}).
        </p>

        <div v-else class="card card--flush overflow-x-auto">
            <table v-if="filteredRows.length" class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3 w-16">S.No</th>
                        <th class="p-3 w-20">Rank</th>
                        <th class="p-3 cursor-pointer select-none" @click="toggleSort('subject')">Subject{{ sortArrow('subject') }}</th>
                        <th class="p-3 cursor-pointer select-none" @click="toggleSort('student_name')">Student{{ sortArrow('student_name') }}</th>
                        <th class="p-3 cursor-pointer select-none" @click="toggleSort('school_name')">School{{ sortArrow('school_name') }}</th>
                        <th class="p-3">Roll No</th>
                        <th class="p-3 cursor-pointer select-none" @click="toggleSort('marks')">Marks{{ sortArrow('marks') }}</th>
                        <th class="p-3 cursor-pointer select-none" @click="toggleSort('percentage')">Percentage{{ sortArrow('percentage') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in filteredRows" :key="row.subject + '-' + row.student_name" class="border-t hover:bg-slate-50/60">
                        <td class="p-3 text-slate-400 font-semibold">{{ i + 1 }}</td>
                        <td class="p-3 font-semibold text-[#0f3d7a]">#{{ row.rank }}</td>
                        <td class="p-3">
                            <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 bg-indigo-100 px-2 py-0.5 rounded">{{ row.subject }}</span>
                        </td>
                        <td class="p-3 font-semibold text-gray-900">{{ row.student_name }}</td>
                        <td class="p-3 text-gray-600">{{ row.school_name }}</td>
                        <td class="p-3 text-xs text-gray-500">{{ row.roll_no || '—' }}</td>
                        <td class="p-3 font-semibold text-emerald-600">{{ row.marks }} / 100</td>
                        <td class="p-3 font-semibold text-[#0f3d7a]">{{ row.percentage != null ? `${row.percentage}%` : '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="p-10 text-center text-gray-400 text-sm">
                {{ subjectLeaders.length ? 'No subjects match your search.' : `No subject-wise toppers recorded across member schools yet for Class XII (Academic year ${filters.academic_year}).` }}
            </p>
        </div>

        <PdfPreviewModal
            :show="showPdfPreview"
            :pdf-url="pdfPreviewUrl"
            title="Subject-Wise Top Scorers — PDF Preview"
            @close="showPdfPreview = false"
        />
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import PdfPreviewModal from '@/Components/ui/PdfPreviewModal.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    filters: { type: Object, default: () => ({}) },
    academicYearOptions: { type: Array, default: () => [] },
    subjectLeaders: { type: Array, default: () => [] },
    noRank: { type: Boolean, default: false },
});

// The select's option value is the academic-year label itself (switchYear navigates by label,
// not id), so the raw academicYearOptions array can't be passed through as-is.
const academicYearSelectOptions = computed(() => props.academicYearOptions.map((ay) => ({
    value: ay.label,
    label: ay.label,
})));

const pdfPreviewUrl = computed(() => {
    const view = props.noRank ? 'percentage' : 'rank';
    return `/sahodaya-admin/${props.sahodaya.id}/board-results/reports/subject-merit/pdf?class=12&academic_year=${encodeURIComponent(props.filters.academic_year || '')}&view=${view}`;
});
const pdfDownloadUrl = computed(() => `${pdfPreviewUrl.value}&download=1`);
const showPdfPreview = ref(false);

// Preview the other mode for this one request — see TopperCountService::setNoRankOverride.
function setView(mode) {
    const url = new URL(window.location.href);
    url.searchParams.set('view', mode);
    router.get(url.pathname + url.search, {}, { preserveScroll: true, preserveState: true });
}

function switchYear(year) {
    const view = props.noRank ? '&view=percentage' : '';
    window.location.href = `/sahodaya-admin/${props.sahodaya.id}/board-results/toppers/subject-wise?academic_year=${year}${view}`;
}

function printReport() {
    window.print();
}

const search = ref('');
const sortKey = ref(props.noRank ? 'percentage' : 'subject');
const sortDir = ref(props.noRank ? 'desc' : 'asc');

watch(() => props.noRank, (noRank) => {
    sortKey.value = noRank ? 'percentage' : 'subject';
    sortDir.value = noRank ? 'desc' : 'asc';
});

function toggleSort(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'asc';
    }
}

function sortArrow(key) {
    if (sortKey.value !== key) return '';
    return sortDir.value === 'asc' ? ' ▲' : ' ▼';
}

const filteredRows = computed(() => {
    let rows = props.subjectLeaders;

    if (search.value.trim()) {
        const q = search.value.toLowerCase();
        rows = rows.filter((r) =>
            r.subject?.toLowerCase().includes(q)
            || r.student_name?.toLowerCase().includes(q)
            || r.school_name?.toLowerCase().includes(q)
            || r.roll_no?.toLowerCase().includes(q),
        );
    }

    const dir = sortDir.value === 'asc' ? 1 : -1;
    return [...rows].sort((a, b) => {
        const av = a[sortKey.value];
        const bv = b[sortKey.value];
        if (av == null && bv == null) return 0;
        if (av == null) return 1;
        if (bv == null) return -1;
        if (typeof av === 'string') return av.localeCompare(bv) * dir;
        return (av - bv) * dir;
    });
});

const groupedBySubject = computed(() => {
    const groups = new Map();
    for (const row of filteredRows.value) {
        const key = row.subject ?? 'Subject';
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(row);
    }
    return Array.from(groups.entries()).map(([subject, rows]) => ({
        subject,
        rows: [...rows].sort((a, b) => (b.percentage ?? b.marks ?? 0) - (a.percentage ?? a.marks ?? 0)),
    }));
});
</script>

<style scoped>
@media print {
    @page {
        size: landscape;
        margin: 8mm;
    }

    :deep(.card),
    :deep(.card--flush) {
        box-shadow: none !important;
        border-color: #e2e8f0 !important;
    }

    :deep(.field) {
        min-height: 0 !important;
        padding-top: 0.3rem !important;
        padding-bottom: 0.3rem !important;
    }

    :deep(table) {
        font-size: 10px !important;
    }

    :deep(th),
    :deep(td) {
        padding-top: 0.3rem !important;
        padding-bottom: 0.3rem !important;
    }

    .subject-print-page {
        page-break-after: always;
    }
    .subject-print-page:last-child {
        page-break-after: avoid;
    }
}
</style>
