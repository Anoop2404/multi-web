<template>
    <SahodayaAdminLayout :title="pageTitle" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="pageTitle" eyebrow="Academic Results · Achievers"
                    :description="`Every student at or above ${filters.threshold}% — not capped to Top-N.`">
            <template #actions>
                <button type="button" @click="printReport" class="btn-secondary text-sm font-bold flex items-center gap-1.5 print:hidden">
                    <span>🖨</span> Print
                </button>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/toppers`" class="btn-secondary text-sm print:hidden">⚙ Settings</Link>
            </template>
        </PageHeader>

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
            </div>
            <div class="flex flex-wrap gap-2">
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/toppers/overall?class=${selectedClass}`" class="text-sm font-semibold text-[#0f3d7a] hover:underline">
                    ← Overall Result
                </Link>
                <Link v-if="selectedClass === 12" :href="`/sahodaya-admin/${sahodaya.id}/board-results/toppers/subject-wise`" class="text-sm font-semibold text-[#0f3d7a] hover:underline">
                    Subject-Wise Top Scorers →
                </Link>
            </div>
        </div>

        <div class="card !p-4 mb-4 print:hidden flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-500">Academic year {{ filters.academic_year }} · {{ filteredRows.length }} of {{ flatRows.length }} row(s)</p>
            <div class="flex items-center gap-3">
                <select v-if="selectedClass === 12 && streamOptions.length > 1" v-model="streamFilter" class="field text-xs py-1.5 w-48">
                    <option value="">All streams</option>
                    <option v-for="s in streamOptions" :key="s" :value="s">{{ s }}</option>
                </select>
                <input v-model="search" type="text" placeholder="Search student, school, admission/roll no…" class="field text-xs py-1.5 w-64">
            </div>
        </div>

        <div class="card card--flush overflow-x-auto">
            <table v-if="filteredRows.length" class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3 cursor-pointer select-none" @click="toggleSort('percentage')">Percentage{{ sortArrow('percentage') }}</th>
                        <th class="p-3 cursor-pointer select-none" @click="toggleSort('student_name')">Student{{ sortArrow('student_name') }}</th>
                        <th class="p-3 cursor-pointer select-none" @click="toggleSort('school_name')">School{{ sortArrow('school_name') }}</th>
                        <th v-if="selectedClass === 12" class="p-3 cursor-pointer select-none" @click="toggleSort('stream')">Stream{{ sortArrow('stream') }}</th>
                        <th class="p-3">Admission / Roll</th>
                        <th class="p-3">Marks</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in filteredRows" :key="(r.stream ?? '') + '-' + (r.topper_id ?? r.rank)" class="border-t hover:bg-slate-50/60">
                        <td class="p-3 font-semibold text-emerald-600">{{ r.percentage != null ? `${r.percentage}%` : '—' }}</td>
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
    achieversOverall: { type: Array, default: () => [] },
    achieversByStream: { type: Object, default: () => ({}) },
});

const pageTitle = computed(() => props.selectedClass === 12 ? 'Class XII Achievers' : 'Class X Achievers');

function classHref(cls) {
    return `/sahodaya-admin/${props.sahodaya.id}/board-results/toppers/achievers?class=${cls}`;
}

function printReport() {
    window.print();
}

const search = ref('');
const streamFilter = ref('');
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

const flatRows = computed(() => {
    if (props.selectedClass === 10) {
        return props.achieversOverall.map((r) => ({ ...r, stream: null }));
    }
    const out = [];
    for (const [stream, rows] of Object.entries(props.achieversByStream)) {
        for (const r of rows) out.push({ ...r, stream });
    }
    return out;
});

const streamOptions = computed(() => Object.keys(props.achieversByStream ?? {}));

const filteredRows = computed(() => {
    let rows = flatRows.value;

    if (props.selectedClass === 12 && streamFilter.value) {
        rows = rows.filter((r) => r.stream === streamFilter.value);
    }

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
