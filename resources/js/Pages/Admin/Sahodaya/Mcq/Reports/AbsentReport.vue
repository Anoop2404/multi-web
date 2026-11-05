<template>
    <SahodayaAdminLayout :title="`Absent report — ${exam.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="Talent Search exam"
                    description="School-wise and class-wise absentee rollups, plus the full absentee list. Filter by school or class, then download Excel.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/reports`" class="btn-secondary text-sm">← Reports</Link>
            </template>
        </PageHeader>
        <McqExamSubNav :sahodaya-id="sahodaya.id" :exam-id="exam.id"
                       :delivery-mode="exam.delivery_mode || 'offline'"
                       :results-published="!!exam.results_published"
                       :is-model-exam="!!exam.is_model_exam"
                       active="reports" />

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div class="card card--muted !py-3 text-center">
                <p class="text-lg font-bold text-red-700">{{ stats.absent_total }}</p>
                <p class="text-[10px] uppercase text-slate-500">Absentees</p>
            </div>
            <div class="card card--muted !py-3 text-center">
                <p class="text-lg font-bold">{{ stats.schools_with_absent }}</p>
                <p class="text-[10px] uppercase text-slate-500">Schools with absentees</p>
            </div>
            <div class="card card--muted !py-3 text-center">
                <p class="text-lg font-bold">{{ stats.classes_with_absent }}</p>
                <p class="text-[10px] uppercase text-slate-500">Classes with absentees</p>
            </div>
            <div class="card card--muted !py-3 text-center">
                <p class="text-lg font-bold">{{ stats.registered_in_scope }}</p>
                <p class="text-[10px] uppercase text-slate-500">Hall tickets in scope</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 mb-4 items-center">
            <input v-model="filterForm.search" type="search" class="field max-w-xs" placeholder="Search student, ticket, or school…">
            <SearchableSelect v-model="filterForm.school_id" :options="schoolOptions" :all-option="true" all-label="All schools" placeholder="All schools" />
            <SearchableSelect v-model="filterForm.class" :options="classOptions.map(c => ({ value: c, label: c }))" :all-option="true" all-label="All classes" placeholder="All classes" />
            <button v-if="hasFilters" type="button" @click="clearFilters" class="text-sm text-slate-400 hover:underline">Clear</button>
            <div class="ml-auto flex flex-wrap gap-2">
                <a :href="reportUrl(schoolWiseExportBase)" class="btn-secondary text-sm">School-wise Excel ↓</a>
                <a :href="reportUrl(classWiseExportBase)" class="btn-secondary text-sm">Class-wise Excel ↓</a>
                <a :href="reportUrl(absentExportBase)" class="btn-secondary text-sm">Absent list ↓</a>
            </div>
        </div>

        <div class="form-section overflow-hidden !p-0 mb-6">
            <div class="px-5 py-3 border-b bg-slate-50/80">
                <h3 class="section-title text-sm !mb-0">School-wise absent summary</h3>
                <p class="text-xs text-slate-500 mt-0.5">Only schools with at least one absentee (hall-ticket holders).</p>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table w-full text-sm">
                    <thead>
                        <tr>
                            <th class="w-12">#</th>
                            <th>School</th>
                            <th class="text-center">Registered</th>
                            <th class="text-center">Present</th>
                            <th class="text-center">Absent</th>
                            <th class="text-center">Absent %</th>
                            <th class="text-center">Malpractice</th>
                            <th class="text-center">Withheld</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in schoolRows" :key="row.school_id">
                            <td class="text-xs text-slate-400">{{ i + 1 }}</td>
                            <td class="font-medium">{{ (row.school_name || '').toUpperCase() }}</td>
                            <td class="text-center">{{ row.registered }}</td>
                            <td class="text-center text-emerald-700">{{ row.present }}</td>
                            <td class="text-center font-semibold text-red-700">{{ row.absent }}</td>
                            <td class="text-center">{{ row.absent_rate ?? '—' }}{{ row.absent_rate != null ? '%' : '' }}</td>
                            <td class="text-center">{{ row.malpractice }}</td>
                            <td class="text-center">{{ row.withheld }}</td>
                        </tr>
                        <tr v-if="!schoolRows.length">
                            <td colspan="8" class="p-8 text-center text-slate-400">No absentees for the current filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="form-section overflow-hidden !p-0 mb-6">
            <div class="px-5 py-3 border-b bg-slate-50/80">
                <h3 class="section-title text-sm !mb-0">Class-wise absent summary</h3>
                <p class="text-xs text-slate-500 mt-0.5">Only classes with at least one absentee (hall-ticket holders).</p>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table w-full text-sm">
                    <thead>
                        <tr>
                            <th class="w-12">#</th>
                            <th>Class</th>
                            <th class="text-center">Registered</th>
                            <th class="text-center">Present</th>
                            <th class="text-center">Absent</th>
                            <th class="text-center">Absent %</th>
                            <th class="text-center">Malpractice</th>
                            <th class="text-center">Withheld</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, i) in classRows" :key="row.class_name">
                            <td class="text-xs text-slate-400">{{ i + 1 }}</td>
                            <td class="font-medium">{{ row.class_name }}</td>
                            <td class="text-center">{{ row.registered }}</td>
                            <td class="text-center text-emerald-700">{{ row.present }}</td>
                            <td class="text-center font-semibold text-red-700">{{ row.absent }}</td>
                            <td class="text-center">{{ row.absent_rate ?? '—' }}{{ row.absent_rate != null ? '%' : '' }}</td>
                            <td class="text-center">{{ row.malpractice }}</td>
                            <td class="text-center">{{ row.withheld }}</td>
                        </tr>
                        <tr v-if="!classRows.length">
                            <td colspan="8" class="p-8 text-center text-slate-400">No absentees for the current filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="form-section overflow-hidden !p-0">
            <div class="px-5 py-3 border-b bg-slate-50/80">
                <h3 class="section-title text-sm !mb-0">{{ absentees.length }} absentee{{ absentees.length === 1 ? '' : 's' }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table w-full text-sm">
                    <thead>
                        <tr>
                            <th class="w-12">#</th>
                            <th>Hall ticket</th>
                            <th>Student</th>
                            <th>Reg. no</th>
                            <th>Class</th>
                            <th>School</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, i) in absentees" :key="`${r.hall_ticket_no}-${i}`">
                            <td class="text-xs text-slate-400">{{ i + 1 }}</td>
                            <td class="font-mono text-xs">{{ r.hall_ticket_no || '—' }}</td>
                            <td class="font-medium">{{ r.student_name || '—' }}</td>
                            <td class="font-mono text-xs">{{ r.reg_no || '—' }}</td>
                            <td class="text-xs">{{ r.class_name || '—' }}</td>
                            <td class="text-xs">{{ (r.school_name || '').toUpperCase() }}</td>
                        </tr>
                        <tr v-if="!absentees.length">
                            <td colspan="6" class="p-8 text-center text-slate-400">No absentees match the current filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useDebouncedInertiaFilters } from '@/composables/useDebouncedInertiaFilters.js';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    exam: Object,
    schoolRows: { type: Array, default: () => [] },
    classRows: { type: Array, default: () => [] },
    absentees: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    schoolOptions: { type: Array, default: () => [] },
    classOptions: { type: Array, default: () => [] },
});

const filterForm = reactive({
    search: props.filters?.search ?? '',
    school_id: props.filters?.school_id ?? null,
    class: props.filters?.class ?? null,
});

const hasFilters = computed(() => !!(filterForm.search || filterForm.school_id || filterForm.class));

const examBase = `/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}`;
const pageUrl = `${examBase}/reports/absent`;
const schoolWiseExportBase = `${examBase}/reports/absent/school-wise/export`;
const classWiseExportBase = `${examBase}/reports/absent/class-wise/export`;
const absentExportBase = `${examBase}/reports/absent/export`;

function applyFilters() {
    router.get(pageUrl, { ...filterForm }, { preserveState: true, preserveScroll: true });
}

useDebouncedInertiaFilters(filterForm, applyFilters, () => props.filters);

function clearFilters() {
    filterForm.search = '';
    filterForm.school_id = null;
    filterForm.class = null;
    applyFilters();
}

function reportUrl(baseUrl) {
    const params = [];
    if (filterForm.school_id) params.push('school_id=' + encodeURIComponent(filterForm.school_id));
    if (filterForm.class) params.push('class=' + encodeURIComponent(filterForm.class));
    if (!params.length) return baseUrl;
    return baseUrl + (baseUrl.includes('?') ? '&' : '?') + params.join('&');
}
</script>
