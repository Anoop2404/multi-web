<template>
    <SahodayaAdminLayout :title="`Registration report — ${exam.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="Talent Search exam" description="All registrations with approval, attendance, and marks.">
            <template #actions>
                <button type="button" @click="showAddCandidateModal = true" class="btn-primary text-sm flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Candidate
                </button>
                <button type="button" @click="generateMissing" class="btn-secondary text-sm flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                    </svg>
                    Assign Missing Reg. Nos
                </button>
                <button type="button" @click="confirmRegenerateAll" class="btn-secondary text-sm flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Regenerate All Reg. Nos
                </button>
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
                <a :href="reportUrl(exportBase + '/export')" class="btn-secondary text-sm">Excel ↓</a>
                <a :href="reportUrl(exportBase + '/pdf')" target="_blank" class="btn-secondary text-sm">PDF ↓</a>
                <button type="button" @click="openPdfPreview(exportBase + '/pdf')" class="btn-secondary text-sm">👁 Preview PDF</button>
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
                            <th>Approval</th>
                            <th>Attendance</th>
                            <th>Score</th>
                            <th>Grade</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, i) in registrations.data" :key="r.id">
                            <td class="text-xs font-bold text-slate-400">{{ (registrations.current_page - 1) * registrations.per_page + i + 1 }}</td>
                            <td class="font-mono text-xs">
                                <span v-if="r.hall_ticket_no" class="font-bold text-slate-900">{{ r.hall_ticket_no }}</span>
                                <button
                                    v-else-if="r.status !== 'cancelled'"
                                    type="button"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-sans font-semibold bg-amber-50 text-amber-800 border border-amber-300 hover:bg-amber-100 transition"
                                    title="Assign Reg. No to this student"
                                    @click="assignSingle(r)"
                                >
                                    + Assign Reg. No
                                </button>
                                <span v-else class="text-slate-400">Cancelled</span>
                            </td>
                            <td class="font-bold text-slate-900">{{ r.student?.name || r.teacher?.name || r.participant_name || '—' }}</td>
                            <td class="text-xs">{{ r.student?.school_class?.name || '—' }}</td>
                            <td class="text-xs">{{ r.school?.name || '—' }}</td>
                            <td class="text-xs capitalize">{{ (r.approval_status || 'pending').replaceAll('_', ' ') }}</td>
                            <td class="text-xs capitalize">{{ r.attendance_status || 'pending' }}</td>
                            <td class="text-xs">{{ r.mark?.score ?? '—' }}</td>
                            <td class="text-xs">{{ r.mark?.grade ?? '—' }}</td>
                            <td class="text-right text-xs">
                                <div class="flex items-center justify-end gap-3">
                                    <button
                                        v-if="!r.hall_ticket_no && r.status !== 'cancelled'"
                                        type="button"
                                        class="font-semibold text-indigo-600 hover:text-indigo-800 hover:underline"
                                        @click="assignSingle(r)"
                                    >
                                        Assign No.
                                    </button>
                                    <button
                                        v-if="!['started', 'submitted'].includes(r.status)"
                                        type="button"
                                        class="font-medium text-rose-600 hover:text-rose-800 hover:underline"
                                        @click="cancelRegistration(r)"
                                    >
                                        Remove
                                    </button>
                                    <span v-else class="text-slate-400">—</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!registrations.data?.length">
                            <td colspan="10" class="p-6 text-center text-slate-400">No matching registrations.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <PaginationLinks :links="registrations.links" :meta="{ from: registrations.from, to: registrations.to, total: registrations.total, last_page: registrations.last_page }" />
        </div>

        <McqAddCandidateModal
            :show="showAddCandidateModal"
            :sahodaya-id="sahodaya.id"
            :exam="exam"
            :schools="schoolSelectOptions"
            @close="showAddCandidateModal = false"
        />
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';
import McqAddCandidateModal from '@/Components/sahodaya/McqAddCandidateModal.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import PaginationLinks from '@/Components/ui/PaginationLinks.vue';
import { useConfirm } from '@/composables/useConfirm';
import { useDebouncedInertiaFilters } from '@/composables/useDebouncedInertiaFilters.js';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    exam: Object,
    registrations: Object,
    filters: { type: Object, default: () => ({}) },
    schoolOptions: { type: Array, default: () => [] },
    memberSchools: { type: Array, default: () => [] },
    classOptions: { type: Array, default: () => [] },
});

const { prompt, confirm } = useConfirm();
const showAddCandidateModal = ref(false);
const schoolSelectOptions = computed(() => props.memberSchools?.length ? props.memberSchools : props.schoolOptions);

function generateMissing() {
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/hall-tickets/generate`,
        {},
        { preserveScroll: true }
    );
}

async function confirmRegenerateAll() {
    const ok = await confirm({
        title: 'Regenerate All Reg. Nos?',
        message: 'This will renumber ALL active candidates into fresh sequential roll numbers per class (1..N starting from the exam start number). Any newly registered or missing students will also receive a number. Proceed?',
        destructive: true,
        confirmLabel: 'Regenerate all numbers',
    });
    if (!ok) return;

    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/hall-tickets/renumber`,
        { force: true },
        { preserveScroll: true }
    );
}

function assignSingle(registration) {
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/registrations/${registration.id}/issue-reg-no`,
        {},
        { preserveScroll: true }
    );
}

const filterForm = reactive({
    search: props.filters?.search ?? '',
    school_id: props.filters?.school_id ?? null,
    class: props.filters?.class ?? null,
});

const hasFilters = computed(() => !!(filterForm.search || filterForm.school_id || filterForm.class));

const pageUrl = `/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/reports/registration`;
const exportBase = `/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/reports/registration`;

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

async function cancelRegistration(registration) {
    const name = registration.student?.name || registration.teacher?.name || registration.participant_name || 'this candidate';
    const reason = await prompt({
        title: `Remove ${name}?`,
        message: 'This will cancel the registration, release their hall ticket number & seat allocation, and adjust school fees.',
        inputPlaceholder: 'Reason for removal (required)',
        inputRequired: true,
        destructive: true,
        confirmLabel: 'Remove candidate',
    });
    if (!reason || !reason.trim()) return;

    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/registrations/${registration.id}/cancel`, {
        reason: reason.trim(),
    }, { preserveScroll: true });
}
</script>
