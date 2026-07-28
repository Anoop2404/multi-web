<template>
    <SahodayaAdminLayout title="Subject-Wise Top Scorers" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Subject-Wise Top Scorers" eyebrow="Academic Results · Class XII"
                    :description="selectedStreamLabel
                        ? `Highest scorer per subject for ${selectedStreamLabel} stream, pooled across every member school.`
                        : 'Highest scorer per subject, pooled across every member school.'">
            <template #actions>
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
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Class / Stream</p>
                <p class="text-lg font-bold text-[#0f3d7a] mt-1">Class 12{{ selectedStreamLabel ? ` · ${selectedStreamLabel}` : '' }}</p>
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
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/toppers/overall?class=12&academic_year=${filters.academic_year}${selectedStream ? `&stream=${selectedStream}` : ''}`" class="text-sm font-semibold text-[#0f3d7a] hover:underline">
                    ← Overall Result
                </Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/toppers/achievers?class=12&academic_year=${filters.academic_year}${selectedStream ? `&stream=${selectedStream}` : ''}`" class="text-sm font-semibold text-[#0f3d7a] hover:underline">
                    90%+ Achievers →
                </Link>
                <span class="text-xs text-slate-300 mx-1">|</span>
                <select class="field text-xs py-1.5" :value="filters.academic_year" @change="switchYear($event.target.value)">
                    <option v-for="ay in academicYearOptions" :key="ay.id" :value="ay.label" :disabled="ay.status === 'closed'">
                        {{ ay.label }}
                    </option>
                </select>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link
                    v-for="[code, label] in streamEntries"
                    :key="code"
                    :href="streamHref(code)"
                    class="px-3 py-1.5 rounded-lg text-sm font-semibold border"
                    :class="selectedStream === code ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'border-slate-200 text-slate-600'"
                >
                    {{ label }}
                </Link>
            </div>
        </div>

        <div class="card !p-4 mb-4 print:hidden flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-500">Academic year {{ filters.academic_year }} · {{ filteredRows.length }} subject row(s)</p>
            <input v-model="search" type="text" placeholder="Search subject, student, school…" class="field text-xs py-1.5 w-64">
        </div>

        <div class="card card--flush overflow-x-auto">
            <table v-if="filteredRows.length" class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3 w-16">S.No</th>
                        <th class="p-3 w-20">Rank</th>
                        <th class="p-3 cursor-pointer select-none" @click="toggleSort('subject')">Subject{{ sortArrow('subject') }}</th>
                        <th class="p-3 cursor-pointer select-none" @click="toggleSort('student_name')">Student{{ sortArrow('student_name') }}</th>
                        <th class="p-3 cursor-pointer select-none" @click="toggleSort('school_name')">School{{ sortArrow('school_name') }}</th>
                        <th class="p-3">Stream</th>
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
                        <td class="p-3 text-gray-600">{{ row.stream || '—' }}</td>
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
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    filters: { type: Object, default: () => ({}) },
    academicYearOptions: { type: Array, default: () => [] },
    subjectLeaders: { type: Array, default: () => [] },
    streamOptions: { type: Object, default: () => ({}) },
    selectedStream: { type: String, default: null },
    selectedStreamLabel: { type: String, default: null },
});

function switchYear(year) {
    const stream = props.selectedStream ? `&stream=${props.selectedStream}` : '';
    window.location.href = `/sahodaya-admin/${props.sahodaya.id}/board-results/toppers/subject-wise?academic_year=${year}${stream}`;
}

function printReport() {
    window.print();
}

const search = ref('');
const sortKey = ref('subject');
const sortDir = ref('asc');

const streamEntries = computed(() => Object.entries(props.streamOptions ?? {}));

function streamHref(code) {
    return `/sahodaya-admin/${props.sahodaya.id}/board-results/toppers/subject-wise?academic_year=${props.filters.academic_year}&stream=${code}`;
}

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
</script>
