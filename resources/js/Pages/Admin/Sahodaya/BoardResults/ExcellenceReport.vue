<template>
    <SahodayaAdminLayout title="Academic Excellence Report" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <!-- PRINT HEADER -->
        <div class="hidden print:block mb-6 border-b border-slate-300 pb-4 text-center">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold uppercase tracking-wider text-slate-900">{{ sahodaya?.name || 'Sahodaya Complex' }}</h1>
                    <p class="text-xs text-slate-600 font-semibold">Academic Excellence & Historical Comparison Report</p>
                </div>
                <div class="text-right text-xs text-slate-500">
                    <p>Academic Year: <strong>{{ report.academic_year || year }}</strong></p>
                    <p>Generated: {{ new Date().toLocaleDateString() }}</p>
                </div>
            </div>
        </div>

        <PageHeader title="Academic Excellence + Historical Comparison" eyebrow="Academic Results"
                    :description="`Source: ${report.source || 'Board Examination Engine'}. Award rankings and year-over-year pass percentage trends.`">
            <template #actions>
                <div class="flex items-center gap-3 print:hidden">
                    <SearchableSelect v-model="year" @change="apply" :options="academicYearSelectOptions" :all-option="false" class="max-w-[150px]" />
                    <button type="button" @click="openHistorySearch" class="btn-secondary text-xs flex items-center gap-1.5 font-bold">
                        <span>📜</span> Student History Lookup
                    </button>
                    <button type="button" @click="printReport" class="btn-secondary text-xs flex items-center gap-1.5 font-bold">
                        <span>🖨</span> Print Report
                    </button>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/reports`" class="btn-secondary text-xs">← Reports Hub</Link>
                </div>
            </template>
        </PageHeader>

        <!-- AWARDS & RANKINGS CARDS -->
        <div class="grid lg:grid-cols-2 gap-5 mb-6">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                    <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                        <span>🏆</span> Academic Awards ({{ report.academic_year || year }})
                    </h3>
                    <span class="text-xs font-semibold text-amber-700 bg-amber-50 px-2.5 py-0.5 rounded-full border border-amber-100">
                        {{ report.awards?.length || 0 }} Award(s)
                    </span>
                </div>

                <ul class="space-y-3 text-xs">
                    <li v-for="a in report.awards" :key="a.id" class="flex items-center justify-between gap-3 p-3 bg-slate-50/70 rounded-xl border border-slate-100 hover:border-indigo-100 transition">
                        <div>
                            <p class="font-bold text-slate-900 text-sm">{{ a.title }}</p>
                            <p class="text-slate-500 text-[11px] mt-0.5">{{ a.school_name }}</p>
                        </div>
                        <span class="font-mono font-extrabold text-indigo-700 bg-indigo-50 px-3 py-1 rounded-lg border border-indigo-100 text-sm">
                            {{ a.score ?? '—' }}
                        </span>
                    </li>
                    <li v-if="!report.awards?.length" class="text-slate-400 text-center py-8 bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
                        No awards computed yet. Publish results to generate.
                    </li>
                </ul>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                    <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                        <span>📊</span> Top Schools (Pass %)
                    </h3>
                    <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-100">
                        Top Ranked
                    </span>
                </div>

                <ul class="space-y-3 text-xs">
                    <li v-for="s in report.top_schools" :key="s.school_id + '-' + s.rank" class="flex items-center justify-between gap-3 p-3 bg-slate-50/70 rounded-xl border border-slate-100">
                        <div class="flex items-center gap-2.5">
                            <span class="w-6 h-6 rounded-full bg-slate-900 text-white font-bold text-[11px] flex items-center justify-center">
                                #{{ s.rank }}
                            </span>
                            <span class="font-bold text-slate-900">{{ s.school_name }}</span>
                        </div>
                        <span class="font-mono font-extrabold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-100 text-xs">
                            {{ s.pass_percent ?? s.score }}% Pass
                        </span>
                    </li>
                    <li v-if="!report.top_schools?.length" class="text-slate-400 text-center py-8 bg-slate-50/50 rounded-xl border border-dashed border-slate-200">
                        No rankings computed yet for this academic year.
                    </li>
                </ul>
            </div>
        </div>

        <!-- HISTORICAL YEAR COMPARISON TABLE -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="p-5 border-b border-slate-100">
                <h3 class="font-bold text-slate-900 text-base">Year-over-Year Performance Comparison</h3>
                <p class="text-xs text-slate-500 mt-0.5">Historical pass percentages, schools reported, and publication records across academic years.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="data-table w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-50 text-slate-600 font-semibold uppercase text-[11px]">
                            <th class="py-3 px-4">Academic Year</th>
                            <th class="py-3 px-4 text-center">Avg Pass %</th>
                            <th class="py-3 px-4 text-center">Schools Reported</th>
                            <th class="py-3 px-4 text-center">Published</th>
                            <th class="py-3 px-4 text-center">Ranked</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in report.year_comparison" :key="row.academic_year" class="hover:bg-slate-50/50 transition">
                            <td class="py-3 px-4 font-bold text-slate-900">{{ row.academic_year }}</td>
                            <td class="py-3 px-4 text-center font-extrabold font-mono text-emerald-600">{{ row.avg_pass_percent }}%</td>
                            <td class="py-3 px-4 text-center font-semibold text-slate-700">{{ row.schools_reported }}</td>
                            <td class="py-3 px-4 text-center text-slate-600">{{ row.published_count }}</td>
                            <td class="py-3 px-4 text-center text-slate-600">{{ row.ranked_schools }}</td>
                        </tr>
                        <tr v-if="!report.year_comparison?.length">
                            <td colspan="5" class="p-8 text-center text-slate-400">No historical comparison data recorded yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- STUDENT HISTORY MODAL -->
        <StudentHistoryModal
            :show="showHistoryModal"
            :initialStudent="null"
            :sahodayaId="sahodaya.id"
            @close="showHistoryModal = false"
        />
    </SahodayaAdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import StudentHistoryModal from '@/Components/BoardResults/StudentHistoryModal.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    report: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    academicYearOptions: { type: Array, default: () => [] },
});

const year = ref(props.filters.academic_year || '');
const showHistoryModal = ref(false);

// The native select bound its option value to ay.label (not ay.id), so mirror
// that by passing plain label strings through to SearchableSelect.
const academicYearSelectOptions = computed(() => props.academicYearOptions.map(ay => ay.label));

function apply() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/board-results/reports/excellence`, {
        academic_year: year.value,
    }, { preserveState: true });
}

function openHistorySearch() {
    showHistoryModal.value = true;
}

function printReport() {
    window.print();
}
</script>
