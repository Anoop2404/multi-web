<template>
    <SahodayaAdminLayout :title="pageTitle" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="pageTitle" eyebrow="Academic Results · Achievers"
                    :description="selectedClass === 12 && selectedStreamLabel
                        ? `Every student at or above ${filters.threshold}% in ${selectedStreamLabel} stream — not capped to Top-N.`
                        : `Every student at or above ${filters.threshold}% — not capped to Top-N.`">
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
                <p class="text-lg font-bold text-[#0f3d7a] mt-1">
                    Class {{ selectedClass }}{{ selectedClass === 12 && selectedStreamLabel ? ` · ${selectedStreamLabel}` : '' }}
                </p>
            </div>
            <div class="card !p-4">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Report</p>
                <p class="text-lg font-bold text-violet-700 mt-1">90%+ Achievers</p>
            </div>
            <div class="card !p-4">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Academic Year</p>
                <p class="text-lg font-bold text-emerald-700 mt-1">{{ filters.academic_year }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 mb-4 print:hidden">
            <div class="flex flex-wrap gap-2">
                <Link :href="classHref(10)" class="px-3 py-1.5 rounded-lg text-sm font-semibold border"
                      :class="selectedClass === 10 ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'border-slate-200 text-slate-600'">
                    Class X
                </Link>
                <Link :href="classHref(12)" class="px-3 py-1.5 rounded-lg text-sm font-semibold border"
                      :class="selectedClass === 12 ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'border-slate-200 text-slate-600'">
                    Class XII
                </Link>
                <span class="text-xs text-slate-300 mx-1">|</span>
                <select class="field text-xs py-1.5" :value="filters.academic_year" @change="switchYear($event.target.value)">
                    <option v-for="ay in academicYearOptions" :key="ay.id" :value="ay.label" :disabled="ay.status === 'closed'">
                        {{ ay.label }}
                    </option>
                </select>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/toppers/overall?class=${selectedClass}&academic_year=${filters.academic_year}${selectedClass === 12 && selectedStream ? `&stream=${selectedStream}` : ''}`" class="text-sm font-semibold text-[#0f3d7a] hover:underline">
                    ← Overall Result
                </Link>
                <Link v-if="selectedClass === 12" :href="`/sahodaya-admin/${sahodaya.id}/board-results/toppers/subject-wise?academic_year=${filters.academic_year}${selectedStream ? `&stream=${selectedStream}` : ''}`" class="text-sm font-semibold text-[#0f3d7a] hover:underline">
                    Subject-Wise Top Scorers →
                </Link>
            </div>
        </div>

        <div v-if="selectedClass === 12 && streamEntries.length > 1" class="flex flex-wrap gap-2 mb-4 print:hidden">
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

        <div class="card !p-4 mb-4 print:hidden flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-500">
                {{ pageTitle }} · {{ filteredRows.length }} row(s) {{ selectedClass === 12 && selectedStreamLabel ? `for ${selectedStreamLabel}` : '' }}
            </p>
            <input v-model="search" type="text" placeholder="Search student, school, admission/roll no…" class="field text-xs py-1.5 w-64">
        </div>

        <div class="card card--flush overflow-x-auto">
            <table v-if="filteredRows.length" class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3 w-16">S.No</th>
                        <th class="p-3 cursor-pointer select-none" @click="toggleSort('percentage')">Percentage{{ sortArrow('percentage') }}</th>
                        <th class="p-3 cursor-pointer select-none" @click="toggleSort('rank')">Rank{{ sortArrow('rank') }}</th>
                        <th class="p-3 cursor-pointer select-none" @click="toggleSort('student_name')">Student{{ sortArrow('student_name') }}</th>
                        <th class="p-3 cursor-pointer select-none" @click="toggleSort('school_name')">School{{ sortArrow('school_name') }}</th>
                        <th v-if="selectedClass === 12" class="p-3 cursor-pointer select-none" @click="toggleSort('stream')">Stream{{ sortArrow('stream') }}</th>
                        <th class="p-3">Admission / Roll</th>
                        <th class="p-3">Marks</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(r, i) in filteredRows" :key="(r.stream ?? '') + '-' + (r.topper_id ?? r.rank)" class="border-t hover:bg-slate-50/60">
                        <td class="p-3 text-slate-400 font-semibold">{{ i + 1 }}</td>
                        <td class="p-3 font-semibold text-emerald-600">{{ r.percentage != null ? `${r.percentage}%` : '—' }}</td>
                        <td class="p-3 font-semibold text-[#0f3d7a]">#{{ r.rank }}</td>
                        <td class="p-3">{{ r.student_name ?? '—' }}</td>
                        <td class="p-3 text-gray-600">{{ r.school_name ?? '—' }}</td>
                        <td v-if="selectedClass === 12" class="p-3 text-gray-600">{{ r.stream }}</td>
                        <td class="p-3 text-xs text-gray-500">{{ [r.admission_no, r.roll_no].filter(Boolean).join(' · ') || '—' }}</td>
                        <td class="p-3 text-gray-500">{{ (r.marks_obtained != null && r.total_marks != null) ? `${r.marks_obtained}/${r.total_marks}` : '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="p-10 text-center text-gray-400 text-sm">
                {{ flatRows.length ? 'No rows match your search.' : `No students at or above this threshold yet.` }}
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
    selectedClass: { type: Number, default: 10 },
    filters: { type: Object, default: () => ({}) },
    academicYearOptions: { type: Array, default: () => [] },
    streamOptions: { type: Object, default: () => ({}) },
    selectedStream: { type: String, default: null },
    selectedStreamLabel: { type: String, default: null },
    achieversOverall: { type: Array, default: () => [] },
    achieversByStream: { type: Object, default: () => ({}) },
    rows: { type: Array, default: () => [] },
});

const pageTitle = computed(() => props.selectedClass === 12 ? 'Class XII Achievers' : 'Class X Achievers');

function normalizeStreamKey(value) {
    return String(value ?? '').trim().toLowerCase();
}

function streamDisplayLabel(value) {
    const normalized = normalizeStreamKey(value);
    if (normalized === 'science') return 'Science';
    if (normalized === 'commerce') return 'Commerce';
    if (normalized === 'humanities' || normalized === 'arts') return 'Humanities';
    return String(value ?? '').trim() || 'Unspecified';
}

function classHref(cls) {
    const stream = cls === 12 && props.selectedStream ? `&stream=${props.selectedStream}` : '';
    return `/sahodaya-admin/${props.sahodaya.id}/board-results/toppers/achievers?class=${cls}&academic_year=${props.filters.academic_year}${stream}`;
}

function streamHref(code) {
    const threshold = props.filters.threshold ? `&threshold=${props.filters.threshold}` : '';
    return `/sahodaya-admin/${props.sahodaya.id}/board-results/toppers/achievers?class=12&academic_year=${props.filters.academic_year}&stream=${code}${threshold}`;
}

function switchYear(year) {
    const threshold = props.filters.threshold ? `&threshold=${props.filters.threshold}` : '';
    const stream = props.selectedClass === 12 && props.selectedStream ? `&stream=${props.selectedStream}` : '';
    window.location.href = `/sahodaya-admin/${props.sahodaya.id}/board-results/toppers/achievers?class=${props.selectedClass}&academic_year=${year}${threshold}${stream}`;
}

function printReport() {
    window.print();
}

const search = ref('');
const sortKey = ref('percentage');
const sortDir = ref('desc');

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

const streamEntries = computed(() => Object.entries(props.streamOptions ?? {}));

const flatRows = computed(() => props.rows ?? []);

const filteredRows = computed(() => {
    let rows = flatRows.value;

    if (search.value.trim()) {
        const q = search.value.toLowerCase();
        rows = rows.filter((r) =>
            r.student_name?.toLowerCase().includes(q)
            || r.school_name?.toLowerCase().includes(q)
            || r.stream?.toLowerCase().includes(q)
            || r.admission_no?.toLowerCase().includes(q)
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
