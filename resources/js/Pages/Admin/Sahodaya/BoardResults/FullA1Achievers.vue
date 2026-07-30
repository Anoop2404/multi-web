<template>
    <SahodayaAdminLayout title="Full A1 Achievers" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Full A1 Achievers" eyebrow="Academic Results"
                    description="Students who scored A1 (91-100) in every subject they were entered for, across Class X and Class XII, all streams.">
            <template #actions>
                <div class="flex items-center gap-2 print:hidden">
                    <button type="button" @click="printReport" class="btn-secondary text-xs flex items-center gap-1.5 font-bold">
                        <span>🖨</span> Print
                    </button>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/reports`" class="btn-secondary text-xs">← Reports Hub</Link>
                </div>
            </template>
        </PageHeader>

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

                <div class="text-[11px] text-gray-400">
                    A1 threshold: <span class="font-bold text-emerald-600">91-100</span> in every subject entered.
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
        <div class="card !p-0 overflow-x-auto shadow-sm border border-gray-200 bg-white">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-bold text-gray-900 text-base">Full A1 Achievers — Academic Year {{ selectedYear }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">Every row here entered marks that were validated at save time to all be 91-100.</p>
            </div>

            <table class="data-table min-w-[850px] w-full text-left">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-xs uppercase font-semibold">
                        <th class="py-3 px-4">Student Name</th>
                        <th class="py-3 px-4">CBSE Roll No</th>
                        <th class="py-3 px-4">School Name</th>
                        <th class="py-3 px-4 text-center">Class</th>
                        <th class="py-3 px-4">Stream</th>
                        <th class="py-3 px-4 text-center">Subjects (all A1)</th>
                        <th class="py-3 px-4 text-center">Lowest Mark</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-xs">
                    <tr v-for="(row, i) in filteredRows" :key="i" class="hover:bg-indigo-50/20 transition-colors">
                        <td class="py-2.5 px-4 font-bold text-gray-900">{{ row.student_name }}</td>
                        <td class="py-2.5 px-4 text-gray-500">{{ row.roll_no || '—' }}</td>
                        <td class="py-2.5 px-4 text-gray-700">{{ row.school_name }}</td>
                        <td class="py-2.5 px-4 text-center font-semibold">{{ row.class }}</td>
                        <td class="py-2.5 px-4 text-gray-500">{{ row.stream || '—' }}</td>
                        <td class="py-2.5 px-4 text-center font-bold text-indigo-600">{{ row.subjects_count }}</td>
                        <td class="py-2.5 px-4 text-center font-bold text-emerald-600">{{ row.lowest_mark ?? '—' }}</td>
                    </tr>
                    <tr v-if="!filteredRows.length">
                        <td colspan="7" class="py-10 text-center text-gray-400 text-xs">
                            No Full A1 achievers found{{ searchQuery ? ` matching "${searchQuery}"` : '' }} for {{ selectedYear }}.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
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

function printReport() {
    window.print();
}
</script>
