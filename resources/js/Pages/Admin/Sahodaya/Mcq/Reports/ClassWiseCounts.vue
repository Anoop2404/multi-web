<template>
    <SahodayaAdminLayout :title="`Class-wise counts report — ${exam.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="Talent Search exam" description="School-wise and class-wise breakdown matrix of registered students.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/reports`" class="btn-secondary text-sm">← Reports</Link>
            </template>
        </PageHeader>
        <McqExamSubNav :sahodaya-id="sahodaya.id" :exam-id="exam.id" active="reports" />

        <div class="flex flex-wrap gap-2 mb-4 items-center">
            <SearchableSelect v-model="filterForm.school_id" :options="schoolOptions" :all-option="true" all-label="All schools" placeholder="All schools" />
            <button v-if="filterForm.school_id" type="button" @click="filterForm.school_id = null" class="text-sm text-slate-400 hover:underline">Clear</button>
            <div class="ml-auto flex flex-wrap gap-2">
                <a :href="reportUrl(exportBase + '/export')" class="btn-secondary text-sm">Export Excel ↓</a>
                <a :href="reportUrl(exportBase + '/pdf')" target="_blank" class="btn-secondary text-sm">Download PDF ↓</a>
            </div>
        </div>

        <div class="form-section overflow-hidden !p-0">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-12 text-center">Sl No</th>
                            <th>School Name</th>
                            <th v-for="cls in classWiseCounts.classes" :key="cls" class="text-center">{{ cls }}</th>
                            <th class="text-center font-bold">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(school, i) in classWiseCounts.schools" :key="school.school_id">
                            <td class="text-center text-xs text-slate-500">{{ i + 1 }}</td>
                            <td class="font-medium text-slate-800">{{ (school.school_name || '').toUpperCase() }}</td>
                            <td v-for="cls in classWiseCounts.classes" :key="cls" class="text-center text-slate-600">
                                {{ school.counts[cls] || 0 }}
                            </td>
                            <td class="text-center font-bold text-slate-900 bg-slate-50">{{ school.total }}</td>
                        </tr>
                        <tr v-if="!classWiseCounts.schools?.length">
                            <td :colspan="classWiseCounts.classes.length + 3" class="p-6 text-center text-slate-400">No registrations yet.</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="classWiseCounts.schools?.length">
                        <tr class="bg-slate-100 font-bold border-t-2 border-slate-300">
                            <td colspan="2" class="text-right uppercase px-4 py-2 text-xs text-slate-700">Total All Schools</td>
                            <td v-for="cls in classWiseCounts.classes" :key="cls" class="text-center py-2 text-slate-900">
                                {{ classWiseCounts.totals[cls] || 0 }}
                            </td>
                            <td class="text-center py-2 text-emerald-800 bg-emerald-100 text-sm font-extrabold">{{ classWiseCounts.grand_total }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
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
    classWiseCounts: { type: Object, default: () => ({ classes: [], schools: [], totals: {}, grand_total: 0 }) },
    filters: { type: Object, default: () => ({}) },
    schoolOptions: { type: Array, default: () => [] },
});

const filterForm = reactive({
    school_id: props.filters?.school_id ?? null,
});

const pageUrl = `/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/reports/class-wise-counts`;
const exportBase = `/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/reports/class-wise-counts`;

function applyFilters() {
    router.get(pageUrl, { ...filterForm }, { preserveState: true, preserveScroll: true });
}

useDebouncedInertiaFilters(filterForm, applyFilters, () => props.filters);

function reportUrl(baseUrl) {
    if (!filterForm.school_id) return baseUrl;
    return baseUrl + (baseUrl.includes('?') ? '&' : '?') + 'school_id=' + encodeURIComponent(filterForm.school_id);
}
</script>
