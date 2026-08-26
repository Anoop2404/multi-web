<template>
    <SahodayaAdminLayout :title="pageTitle" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        
        <!-- HERO BANNER -->
        <div class="print:hidden relative overflow-hidden rounded-2xl mb-6 bg-gradient-to-br from-[#0b2558] via-[#123a7a] to-[#1e4d9e] text-white p-6 sm:p-8">
            <div class="absolute -right-8 -top-8 text-[140px] opacity-10 leading-none select-none">🏆</div>
            <div class="relative flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-widest text-amber-300 mb-1">Academic Results · Stream Merit Register</p>
                    <h1 class="text-2xl sm:text-3xl font-extrabold">{{ pageTitle }}</h1>
                    <p class="text-sm text-blue-100 mt-1.5 max-w-xl">
                        Sahodaya-wide stream toppers auto-computed from member school result submissions for {{ filters.academic_year }}.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="recalculate" :disabled="recalculating" class="btn-secondary text-xs flex items-center gap-1.5 font-bold !bg-white/10 !border-white/20 !text-white hover:!bg-white/20 disabled:opacity-60">
                        <span>🔄</span> {{ recalculating ? 'Recalculating…' : 'Recalculate Rankings' }}
                    </button>
                    <button type="button" @click="printReport" class="btn-secondary text-xs flex items-center gap-1.5 font-bold !bg-white/10 !border-white/20 !text-white hover:!bg-white/20">
                        <span>🖨</span> Print
                    </button>
                    <a :href="`/sahodaya-admin/${sahodaya.id}/board-results/reports/toppers/pdf?academic_year=${encodeURIComponent(filters.academic_year || '')}&class=${selectedClass}`" class="text-xs flex items-center gap-1.5 font-bold px-3 py-2 rounded-lg bg-amber-400 text-[#0b2558] hover:bg-amber-300 transition">
                        <span>📥</span> Download PDF
                    </a>
                </div>
            </div>

            <!-- STATS STRIP -->
            <div class="relative mt-6 grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="rounded-xl bg-white/10 border border-white/15 backdrop-blur-sm p-3.5">
                    <p class="text-[10px] font-semibold text-blue-200 uppercase tracking-wide">Total Stream Toppers</p>
                    <p class="text-2xl font-extrabold text-amber-300 mt-1">{{ filteredRows.length }}</p>
                </div>
                <div class="rounded-xl bg-white/10 border border-white/15 backdrop-blur-sm p-3.5">
                    <p class="text-[10px] font-semibold text-blue-200 uppercase tracking-wide">Schools Represented</p>
                    <p class="text-2xl font-extrabold text-white mt-1">{{ distinctSchoolCount }}</p>
                </div>
                <div class="rounded-xl bg-white/10 border border-white/15 backdrop-blur-sm p-3.5">
                    <p class="text-[10px] font-semibold text-blue-200 uppercase tracking-wide">Top Scorer %</p>
                    <p class="text-2xl font-extrabold text-emerald-300 mt-1">{{ highestPercentage }}%</p>
                </div>
                <div class="rounded-xl bg-white/10 border border-white/15 backdrop-blur-sm p-3.5">
                    <p class="text-[10px] font-semibold text-blue-200 uppercase tracking-wide">Academic Year</p>
                    <p class="text-2xl font-extrabold text-white mt-1">{{ filters.academic_year }}</p>
                </div>
            </div>
        </div>

        <BoardResultsVerificationSubNav :sahodayaId="sahodaya.id" active="toppers" :currentClass="selectedClass" :counts="counts" />
        <BoardResultsReportSubNav :sahodayaId="sahodaya.id" active="toppers" :counts="counts" />

        <!-- FILTER CONTROLS -->
        <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm mb-6 space-y-4 print:hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-base font-bold text-gray-900">⚡ Filters & Class Selection</span>
                    <span class="text-xs text-gray-500">({{ filteredRows.length }} toppers listed)</span>
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

            <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-3 items-end">
                <div>
                    <label class="form-label mb-1 text-[11px] font-bold text-gray-600 uppercase">Class</label>
                    <SearchableSelect
                        :model-value="selectedClass"
                        :options="[{ value: 10, label: 'Class X (AISSE)' }, { value: 12, label: 'Class XII (AISSCE)' }]"
                        :all-option="false"
                        placeholder="Select class"
                        @update:model-value="switchClass"
                    />
                </div>

                <div>
                    <label class="form-label mb-1 text-[11px] font-bold text-gray-600 uppercase">Stream</label>
                    <SearchableSelect
                        :model-value="selectedStreamCode"
                        :options="streamSelectOptions"
                        :disabled="selectedClass !== 12"
                        :all-option="false"
                        placeholder="Select stream"
                        @update:model-value="switchStream"
                    />
                </div>

                <div>
                    <label class="form-label mb-1 text-[11px] font-bold text-gray-600 uppercase">Academic Year</label>
                    <SearchableSelect
                        :model-value="filters.academic_year"
                        :options="academicYearSelectOptions"
                        :all-option="false"
                        placeholder="Select academic year"
                        @update:model-value="switchYear"
                    />
                </div>
            </div>
        </div>

        <!-- TOPPERS TABLE -->
        <div class="card !p-0 overflow-x-auto shadow-sm border border-gray-200 bg-white print:border-0 print:shadow-none">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between print:hidden bg-gradient-to-r from-amber-50 to-white">
                <div>
                    <h2 class="font-bold text-gray-900 text-base flex items-center gap-2">
                        🏆 {{ selectedClass === 12 ? `${selectedStreamLabel || 'Class XII'} Stream Toppers` : 'Class X Overall Toppers' }} — {{ filters.academic_year }}
                    </h2>
                    <p class="text-xs text-gray-500 mt-0.5">Ranked by aggregate marks and percentage obtained across member schools.</p>
                </div>
            </div>

            <table class="data-table min-w-[850px] w-full text-left print:min-w-full">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-xs uppercase font-semibold print:bg-slate-100 print:text-slate-800">
                        <th class="py-3 px-4 text-center">Rank</th>
                        <th class="py-3 px-4">Student Name</th>
                        <th class="py-3 px-4">Roll No</th>
                        <th class="py-3 px-4">School Name</th>
                        <th class="py-3 px-4">Stream</th>
                        <th class="py-3 px-4 text-center">Marks</th>
                        <th class="py-3 px-4 text-center">Percentage</th>
                        <th class="py-3 px-4 text-center">Marksheet</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-xs print:divide-slate-200">
                    <tr v-for="(row, i) in filteredRows" :key="row.id || i" class="hover:bg-amber-50/40 transition-colors">
                        <td class="py-2.5 px-4 text-center font-bold">
                            <span v-if="row.rank === 1" class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-amber-100 text-amber-800 font-extrabold text-xs border border-amber-300 shadow-2xs">🥇 1</span>
                            <span v-else-if="row.rank === 2" class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-slate-200 text-slate-800 font-extrabold text-xs border border-slate-300">🥈 2</span>
                            <span v-else-if="row.rank === 3" class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-orange-100 text-amber-900 font-extrabold text-xs border border-orange-200">🥉 3</span>
                            <span v-else class="px-2 py-0.5 rounded bg-gray-100 text-gray-700 font-bold">#{{ row.rank || (i + 1) }}</span>
                        </td>
                        <td class="py-2.5 px-4 font-bold text-gray-900">{{ row.student_name || row.name }}</td>
                        <td class="py-2.5 px-4 font-mono text-gray-600">{{ row.roll_no || '—' }}</td>
                        <td class="py-2.5 px-4 text-gray-800 font-semibold">{{ row.school_name || row.tenant_id }}</td>
                        <td class="py-2.5 px-4 text-gray-500">{{ row.stream || selectedStreamLabel || '—' }}</td>
                        <td class="py-2.5 px-4 text-center font-semibold">
                            <span v-if="row.marks_obtained !== null">{{ row.marks_obtained }}/{{ row.total_marks }}</span>
                            <span v-else>—</span>
                        </td>
                        <td class="py-2.5 px-4 text-center font-bold text-emerald-600 text-sm">
                            {{ row.percentage !== null ? `${Number(row.percentage).toFixed(2)}%` : '—' }}
                        </td>
                        <td class="py-2.5 px-4 text-center">
                            <a v-if="row.marksheet_url || row.marksheet_path" :href="row.marksheet_url || row.marksheet_path" target="_blank" class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200 inline-flex items-center gap-1">
                                📄 Marksheet ↗
                            </a>
                            <span v-else class="text-gray-400 text-[11px]">No file</span>
                        </td>
                    </tr>
                    <tr v-if="!filteredRows.length">
                        <td colspan="8" class="py-10 text-center text-gray-400 text-xs">
                            No toppers found match{{ searchQuery ? ` "${searchQuery}"` : '' }} for Class {{ selectedClass }} ({{ filters.academic_year }}).
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import BoardResultsReportSubNav from '@/Components/BoardResults/BoardResultsReportSubNav.vue';
import BoardResultsVerificationSubNav from '@/Components/BoardResults/BoardResultsVerificationSubNav.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    selectedClass: { type: Number, default: 10 },
    filters: { type: Object, default: () => ({}) },
    academicYearOptions: { type: Array, default: () => [] },
    streamOptions: { type: Object, default: () => ({}) },
    rows: { type: Array, default: () => [] },
    counts: { type: Object, default: () => ({}) },
    selectedStream: { type: String, default: null },
});

const searchQuery = ref('');
const recalculating = ref(false);

function recalculate() {
    recalculating.value = true;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/board-results/toppers/recompute`, {
        academic_year: props.filters.academic_year,
    }, {
        preserveScroll: true,
        onFinish: () => { recalculating.value = false; },
    });
}

const pageTitle = computed(() => props.selectedClass === 12 ? 'Class XII Stream Toppers' : 'Class X Sahodaya Toppers');
const streamEntries = computed(() => Object.entries(props.streamOptions ?? {}));
const selectedStreamCode = computed(() => {
    if (props.selectedClass !== 12 || !streamEntries.value.length) return null;
    if (props.selectedStream && props.streamOptions?.[props.selectedStream]) return props.selectedStream;
    return streamEntries.value[0]?.[0] ?? null;
});
const selectedStreamLabel = computed(() => selectedStreamCode.value ? props.streamOptions?.[selectedStreamCode.value] ?? null : null);
const streamSelectOptions = computed(() => streamEntries.value.map(([code, label]) => ({ value: code, label })));
const academicYearSelectOptions = computed(() => (props.academicYearOptions ?? []).map(ay => ({ value: ay.label, label: ay.label })));

function switchClass(cls) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/board-results/toppers?class=${cls}&academic_year=${props.filters.academic_year}`);
}

function switchYear(year) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/board-results/toppers?class=${props.selectedClass}&academic_year=${year}`);
}

function switchStream(code) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/board-results/toppers?class=12&academic_year=${props.filters.academic_year}&stream=${code}`);
}

function printReport() {
    window.print();
}

const filteredRows = computed(() => {
    let list = props.rows ?? [];
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(r =>
            r.student_name?.toLowerCase().includes(q) ||
            r.name?.toLowerCase().includes(q) ||
            r.school_name?.toLowerCase().includes(q) ||
            r.roll_no?.toString().includes(q)
        );
    }
    return list;
});

const distinctSchoolCount = computed(() => new Set(filteredRows.value.map(r => r.school_name || r.tenant_id)).size);
const highestPercentage = computed(() => {
    if (!filteredRows.value.length) return '0';
    const max = Math.max(...filteredRows.value.map(r => Number(r.percentage || 0)));
    return max.toFixed(2);
});
</script>
