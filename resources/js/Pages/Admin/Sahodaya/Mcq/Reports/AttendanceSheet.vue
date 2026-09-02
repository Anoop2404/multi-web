<template>
    <SahodayaAdminLayout :title="`Attendance sheet report — ${exam.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="Talent Search exam" description="Hall ticket list for exam-day attendance marking, with student photos on the PDF.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/reports`" class="btn-secondary text-sm">← Reports</Link>
            </template>
        </PageHeader>
        <McqExamSubNav :sahodaya-id="sahodaya.id" :exam-id="exam.id" active="reports" />

        <div class="flex flex-wrap gap-2 mb-4 items-center">
            <input v-model="filterForm.search" type="search" class="field max-w-xs" placeholder="Search ticket, student, or school…">
            <SearchableSelect v-model="filterForm.school_id" :options="schoolOptions" :all-option="true" all-label="All schools" placeholder="All schools" />
            <SearchableSelect v-model="filterForm.class" :options="classOptions.map(c => ({ value: c, label: c }))" :all-option="true" all-label="All classes" placeholder="All classes" />
            <button v-if="hasFilters" type="button" @click="clearFilters" class="text-sm text-slate-400 hover:underline">Clear</button>
            <div class="ml-auto flex flex-wrap gap-2">
                <a :href="reportUrl(excelBase)" class="btn-secondary text-sm">Excel ↓</a>
                <a :href="reportUrl(pdfBase)" target="_blank" class="btn-secondary text-sm">PDF ↓</a>
                <button type="button" @click="openPdfPreview(pdfBase)" class="btn-secondary text-sm">👁 Preview PDF</button>
                <a :href="reportUrl(absentBase)" class="btn-secondary text-sm">Absent list ↓</a>
                <a :href="reportUrl(malpracticeBase)" class="btn-secondary text-sm">Malpractice register ↓</a>
            </div>
        </div>

        <div class="form-section overflow-hidden !p-0">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-12">#</th>
                            <th>Reg. no.</th>
                            <th>Student</th>
                            <th>Class</th>
                            <th>School</th>
                            <th>Attendance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, i) in registrations.data" :key="r.id">
                            <td class="text-xs font-bold text-slate-400">{{ (registrations.current_page - 1) * registrations.per_page + i + 1 }}</td>
                            <td class="font-mono text-xs">{{ r.hall_ticket_no || '—' }}</td>
                            <td class="font-bold text-slate-900">{{ r.student?.name || r.teacher?.name || r.participant_name || '—' }}</td>
                            <td class="text-xs">{{ r.student?.school_class?.name || '—' }}</td>
                            <td class="text-xs">{{ r.school?.name || '—' }}</td>
                            <td class="text-xs capitalize">{{ r.attendance_status || 'pending' }}</td>
                        </tr>
                        <tr v-if="!registrations.data?.length">
                            <td colspan="6" class="p-6 text-center text-slate-400">No matching registrations.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <PaginationLinks :links="registrations.links" :meta="{ from: registrations.from, to: registrations.to, total: registrations.total, last_page: registrations.last_page }" />
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import PaginationLinks from '@/Components/ui/PaginationLinks.vue';
import { useDebouncedInertiaFilters } from '@/composables/useDebouncedInertiaFilters.js';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    exam: Object,
    registrations: Object,
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
const pageUrl = `${examBase}/reports/attendance`;
const excelBase = `${examBase}/reports/attendance/export`;
// The photo-included attendance sheet PDF lives under the exam's ops routes, not /reports/.
const pdfBase = `${examBase}/attendance/sheet.pdf`;
const absentBase = `${examBase}/reports/absent/export`;
const malpracticeBase = `${examBase}/reports/malpractice/export`;

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

function reportUrl(baseUrl, inline = false) {
    const params = [];
    if (inline) params.push('inline=1');
    if (filterForm.school_id) params.push('school_id=' + encodeURIComponent(filterForm.school_id));
    if (filterForm.class) params.push('class=' + encodeURIComponent(filterForm.class));
    if (!params.length) return baseUrl;
    return baseUrl + (baseUrl.includes('?') ? '&' : '?') + params.join('&');
}

function openPdfPreview(baseUrl) {
    window.open(reportUrl(baseUrl, true), '_blank');
}
</script>
