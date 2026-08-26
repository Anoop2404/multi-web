<template>
    <SchoolAdminLayout title="Board Result Reports" :school="school" :show-header-title="false">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">
                    Board Result Reports
                </h1>
                <p class="text-xs text-gray-500 mt-0.5">
                    View and download school performance summaries, toppers lists, and achievers registers.
                </p>
            </div>

            <div class="flex items-center gap-3 self-start md:self-auto print:hidden">
                <!-- Class segmented switch -->
                <div class="flex items-center bg-gray-100 p-1 rounded-xl border border-gray-200/80">
                    <Link
                        :href="`/school-admin/${school.id}/board-results/reports?class=10&academic_year=${selectedAcademicYear}`"
                        class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all"
                        :class="selectedClass === 10 ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                    >
                        Class X
                    </Link>
                    <Link
                        :href="`/school-admin/${school.id}/board-results/reports?class=12&academic_year=${selectedAcademicYear}`"
                        class="px-4 py-1.5 text-xs font-semibold rounded-lg transition-all"
                        :class="selectedClass === 12 ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
                    >
                        Class XII
                    </Link>
                </div>
                
                <!-- Academic Year Selector -->
                <div class="relative">
                    <SearchableSelect
                        :model-value="selectedAcademicYear"
                        @update:model-value="switchYear"
                        :options="academicYearSelectOptions"
                        :all-option="false"
                        placeholder="Select year"
                        class="w-32"
                    />
                </div>
            </div>
        </div>

        <div v-if="!activeResult" class="text-center py-20 bg-white rounded-2xl border border-gray-200 shadow-sm">
            <span class="text-4xl">📭</span>
            <h3 class="text-lg font-bold text-gray-900 mt-4">No Data Available</h3>
            <p class="text-sm text-gray-500 mt-1 max-w-sm mx-auto">
                No board result has been created for Class {{ selectedClass }} in the {{ selectedAcademicYear }} academic year.
            </p>
            <Link :href="`/school-admin/${school.id}/board-results?class=${selectedClass}&academic_year=${selectedAcademicYear}`" class="btn-primary mt-4 inline-flex">
                Go to Data Entry
            </Link>
        </div>

        <div v-else class="space-y-6">
            <!-- Summary Stats Report Card -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-white">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center text-xl shadow-xs">📊</div>
                        <div>
                            <h2 class="text-base font-bold text-gray-900">Performance Summary</h2>
                            <p class="text-xs text-gray-500">Aggregate statistics and pass percentage.</p>
                        </div>
                    </div>
                    <a :href="`/school-admin/${school.id}/board-results/reports/summary/pdf?class=${selectedClass}&academic_year=${selectedAcademicYear}`" class="btn-secondary text-xs font-bold" target="_blank">
                        Download PDF
                    </a>
                </div>
                <div class="p-5 grid sm:grid-cols-4 gap-4">
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                        <p class="text-[10px] font-bold text-gray-400 uppercase">Appeared</p>
                        <p class="text-xl font-extrabold text-gray-900 mt-0.5">{{ activeResult.total_appeared || '—' }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                        <p class="text-[10px] font-bold text-gray-400 uppercase">Passed</p>
                        <p class="text-xl font-extrabold text-gray-900 mt-0.5">{{ activeResult.pass_count || '—' }}</p>
                    </div>
                    <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-100">
                        <p class="text-[10px] font-bold text-emerald-600 uppercase">Pass Percentage</p>
                        <p class="text-xl font-extrabold text-emerald-700 mt-0.5">{{ activeResult.pass_percent ? `${activeResult.pass_percent}%` : '—' }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                        <p class="text-[10px] font-bold text-gray-400 uppercase">Status</p>
                        <p class="text-sm font-bold mt-1.5 uppercase" :class="{
                            'text-amber-600': activeResult.status === 'draft',
                            'text-blue-600': activeResult.status === 'submitted',
                            'text-emerald-600': activeResult.status === 'verified' || activeResult.status === 'approved' || activeResult.status === 'published',
                            'text-red-600': activeResult.status === 'rejected',
                        }">{{ activeResult.status }}</p>
                    </div>
                </div>
            </div>

            <!-- Overall Toppers / Stream Toppers Report -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-white">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-violet-50 text-violet-700 flex items-center justify-center text-xl shadow-xs">🏆</div>
                        <div>
                            <h2 class="text-base font-bold text-gray-900">{{ selectedClass === 12 ? 'Stream Toppers' : 'Overall Toppers' }}</h2>
                            <p class="text-xs text-gray-500">Top ranking students by aggregate percentage.</p>
                        </div>
                    </div>
                    <a :href="`/school-admin/${school.id}/board-results/reports/toppers/pdf?class=${selectedClass}&academic_year=${selectedAcademicYear}`" class="btn-secondary text-xs font-bold" target="_blank">
                        Download PDF
                    </a>
                </div>
                <div class="p-0 overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-gray-600 font-semibold border-b border-gray-100">
                            <tr>
                                <th class="py-2.5 px-4 w-12 text-center">Rank</th>
                                <th class="py-2.5 px-4">Student Name</th>
                                <th class="py-2.5 px-4">Roll No</th>
                                <th v-if="selectedClass === 12" class="py-2.5 px-4">Stream</th>
                                <th class="py-2.5 px-4 text-center">Marks</th>
                                <th class="py-2.5 px-4 text-center">Percentage</th>
                                <th class="py-2.5 px-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(t, i) in overallToppers" :key="t.id" class="hover:bg-gray-50/50">
                                <td class="py-2.5 px-4 text-center font-bold text-gray-900">#{{ t.rank || (i + 1) }}</td>
                                <td class="py-2.5 px-4 font-bold text-gray-900">{{ t.name }}</td>
                                <td class="py-2.5 px-4 font-mono text-gray-600">{{ t.roll_no || '—' }}</td>
                                <td v-if="selectedClass === 12" class="py-2.5 px-4 text-gray-700">{{ t.stream || '—' }}</td>
                                <td class="py-2.5 px-4 text-center font-semibold">{{ t.marks_obtained }}/{{ t.total_marks }}</td>
                                <td class="py-2.5 px-4 text-center font-bold text-emerald-600">{{ Number(t.percentage).toFixed(2) }}%</td>
                                <td class="py-2.5 px-4 text-center">
                                    <span v-if="t.verification_status === 'verified'" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700">Verified</span>
                                    <span v-else-if="t.verification_status === 'rejected'" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700">Rejected</span>
                                    <span v-else class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">Pending</span>
                                </td>
                            </tr>
                            <tr v-if="!overallToppers.length">
                                <td :colspan="selectedClass === 12 ? 7 : 6" class="py-6 text-center text-gray-400 text-xs">No toppers recorded yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Subject Toppers Report -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mt-6">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-white">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center text-xl shadow-xs">🎯</div>
                        <div>
                            <h2 class="text-base font-bold text-gray-900">Subject-wise Toppers</h2>
                            <p class="text-xs text-gray-500">Top ranking students in individual subjects.</p>
                        </div>
                    </div>
                </div>
                <div class="p-0 overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-gray-600 font-semibold border-b border-gray-100">
                            <tr>
                                <th class="py-2.5 px-4">Subject</th>
                                <th class="py-2.5 px-4">Student Name</th>
                                <th class="py-2.5 px-4">Roll No</th>
                                <th class="py-2.5 px-4 text-center">Marks</th>
                                <th class="py-2.5 px-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="t in subjectToppers" :key="t.id" class="hover:bg-gray-50/50">
                                <td class="py-2.5 px-4 font-bold text-gray-700">{{ Object.keys(t.subject_marks || {})[0] || '—' }}</td>
                                <td class="py-2.5 px-4 font-bold text-gray-900">{{ t.name }}</td>
                                <td class="py-2.5 px-4 font-mono text-gray-600">{{ t.roll_no || '—' }}</td>
                                <td class="py-2.5 px-4 text-center font-bold text-emerald-600">{{ Object.values(t.subject_marks || {})[0] || '—' }}</td>
                                <td class="py-2.5 px-4 text-center">
                                    <span v-if="t.verification_status === 'verified'" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700">Verified</span>
                                    <span v-else-if="t.verification_status === 'rejected'" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700">Rejected</span>
                                    <span v-else class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">Pending</span>
                                </td>
                            </tr>
                            <tr v-if="!subjectToppers.length">
                                <td colspan="5" class="py-6 text-center text-gray-400 text-xs">No subject toppers recorded yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Full A1 Achievers Report -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mt-6">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-emerald-50 to-white border-emerald-100">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-xl shadow-xs border border-emerald-200">🌟</div>
                        <div>
                            <h2 class="text-base font-bold text-gray-900">Full A1 Achievers</h2>
                            <p class="text-xs text-gray-500">Students scoring A1 in all entered subjects.</p>
                        </div>
                    </div>
                </div>
                <div class="p-0 overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-gray-600 font-semibold border-b border-gray-100">
                            <tr>
                                <th class="py-2.5 px-4">Student Name</th>
                                <th class="py-2.5 px-4">Roll No</th>
                                <th class="py-2.5 px-4">Subjects & Marks</th>
                                <th class="py-2.5 px-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="t in fullA1Achievers" :key="t.id" class="hover:bg-gray-50/50">
                                <td class="py-2.5 px-4 font-bold text-gray-900">{{ t.name }}</td>
                                <td class="py-2.5 px-4 font-mono text-gray-600">{{ t.roll_no || '—' }}</td>
                                <td class="py-2.5 px-4">
                                    <div class="flex flex-wrap gap-1">
                                        <span v-for="(marks, subject) in (t.subject_marks || {})" :key="subject"
                                              class="text-[10px] bg-slate-100 border border-slate-200 text-slate-700 px-1.5 py-0.5 rounded">
                                            {{ subject }}: <strong class="text-emerald-700">{{ marks }}</strong>
                                        </span>
                                    </div>
                                </td>
                                <td class="py-2.5 px-4 text-center">
                                    <span v-if="t.verification_status === 'verified'" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-green-100 text-green-700">Verified</span>
                                    <span v-else-if="t.verification_status === 'rejected'" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-red-700">Rejected</span>
                                    <span v-else class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800">Pending</span>
                                </td>
                            </tr>
                            <tr v-if="!fullA1Achievers.length">
                                <td colspan="4" class="py-6 text-center text-gray-400 text-xs">No Full A1 Achievers recorded yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    school: Object,
    results: Array,
    academicYearOptions: Array,
    selectedClass: Number,
    selectedAcademicYear: String,
    activeResult: Object,
});

function switchYear(year) {
    router.get(`/school-admin/${props.school.id}/board-results/reports?class=${props.selectedClass}&academic_year=${year}`);
}

const academicYearSelectOptions = computed(() => {
    return (props.academicYearOptions || []).map(ay => ({ value: ay.label, label: ay.label }));
});

const overallToppers = computed(() => {
    return (props.activeResult?.toppers || [])
        .filter(t => t.entry_type === 'overall')
        .sort((a, b) => b.percentage - a.percentage);
});

const subjectToppers = computed(() => {
    return (props.activeResult?.toppers || [])
        .filter(t => t.entry_type === 'subject')
        .sort((a, b) => {
            const subjectA = Object.keys(a.subject_marks || {})[0] || '';
            const subjectB = Object.keys(b.subject_marks || {})[0] || '';
            return subjectA.localeCompare(subjectB);
        });
});

const fullA1Achievers = computed(() => {
    return (props.activeResult?.toppers || [])
        .filter(t => t.entry_type === 'full_a1')
        .sort((a, b) => a.name.localeCompare(b.name));
});
</script>
