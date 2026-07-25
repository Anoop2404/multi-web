<template>
    <SahodayaAdminLayout title="Subject-Wise Top Scorers" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Subject-Wise Top Scorers" eyebrow="Academic Results · Class XII"
                    description="Highest scorer per subject, pooled across every member school.">
            <template #actions>
                <button type="button" @click="printReport" class="btn-secondary text-sm font-bold flex items-center gap-1.5 print:hidden">
                    <span>🖨</span> Print
                </button>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/toppers`" class="btn-secondary text-sm print:hidden">⚙ Settings</Link>
            </template>
        </PageHeader>

        <div class="flex flex-wrap items-center justify-between gap-3 mb-4 print:hidden">
            <div class="flex flex-wrap gap-2">
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/toppers/overall?class=12`" class="text-sm font-semibold text-[#0f3d7a] hover:underline">
                    ← Overall Result
                </Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/toppers/achievers?class=12`" class="text-sm font-semibold text-[#0f3d7a] hover:underline">
                    90%+ Achievers →
                </Link>
            </div>
        </div>

        <div class="card !p-4 mb-4 print:hidden flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-500">Academic year {{ filters.academic_year }} · {{ filteredRows.length }} of {{ subjectLeaders.length }} subject(s)</p>
            <input v-model="search" type="text" placeholder="Search subject, student, school…" class="field text-xs py-1.5 w-64">
        </div>

        <div class="card card--flush overflow-x-auto">
            <table v-if="filteredRows.length" class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3 cursor-pointer select-none" @click="toggleSort('subject')">Subject{{ sortArrow('subject') }}</th>
                        <th class="p-3 cursor-pointer select-none" @click="toggleSort('student_name')">Student{{ sortArrow('student_name') }}</th>
                        <th class="p-3 cursor-pointer select-none" @click="toggleSort('school_name')">School{{ sortArrow('school_name') }}</th>
                        <th class="p-3">Roll No</th>
                        <th class="p-3 cursor-pointer select-none" @click="toggleSort('marks')">Marks{{ sortArrow('marks') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in filteredRows" :key="row.subject" class="border-t hover:bg-slate-50/60">
                        <td class="p-3">
                            <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 bg-indigo-100 px-2 py-0.5 rounded">{{ row.subject }}</span>
                        </td>
                        <td class="p-3 font-semibold text-gray-900">{{ row.student_name }}</td>
                        <td class="p-3 text-gray-600">{{ row.school_name }}</td>
                        <td class="p-3 text-xs text-gray-500">{{ row.roll_no || '—' }}</td>
                        <td class="p-3 font-semibold text-emerald-600">{{ row.marks }} / 100</td>
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
    subjectLeaders: { type: Array, default: () => [] },
});

function printReport() {
    window.print();
}

const search = ref('');
const sortKey = ref('subject');
const sortDir = ref('asc');

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
