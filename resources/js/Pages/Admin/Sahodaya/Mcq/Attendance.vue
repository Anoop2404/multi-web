<template>
    <SahodayaAdminLayout :title="`Attendance — ${exam.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="Talent Search exam" description="Mark hall attendance before entering scores." />
        <McqExamSubNav :sahodaya-id="sahodaya.id" :exam-id="exam.id" :delivery-mode="exam.delivery_mode || 'offline'" :results-published="!!exam.results_published" :is-model-exam="!!exam.is_model_exam" active="attendance" />

        <InlineAlert :message="alertMessage" type="error" @dismiss="alertMessage = ''" />

        <a v-if="pendingCorrectionsCount" :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/attendance-corrections`"
           class="card !py-3 mb-4 flex items-center justify-between bg-amber-50 border border-amber-200 text-amber-800 text-sm">
            <span>{{ pendingCorrectionsCount }} attendance correction request(s) awaiting your approval.</span>
            <span class="font-semibold">Review →</span>
        </a>

        <div v-if="summary" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
            <div class="card card--muted !py-3 text-center"><p class="text-lg font-bold">{{ summary.total }}</p><p class="text-[10px] uppercase text-slate-500">Total</p></div>
            <div class="card card--muted !py-3 text-center"><p class="text-lg font-bold">{{ summary.pending }}</p><p class="text-[10px] uppercase text-slate-500">Pending</p></div>
            <div class="card card--muted !py-3 text-center"><p class="text-lg font-bold text-emerald-700">{{ summary.present }}</p><p class="text-[10px] uppercase text-slate-500">Present</p></div>
            <div class="card card--muted !py-3 text-center"><p class="text-lg font-bold text-red-700">{{ summary.absent }}</p><p class="text-[10px] uppercase text-slate-500">Absent</p></div>
            <div class="card card--muted !py-3 text-center"><p class="text-lg font-bold text-amber-700">{{ summary.malpractice }}</p><p class="text-[10px] uppercase text-slate-500">Malpractice</p></div>
            <div class="card card--muted !py-3 text-center"><p class="text-lg font-bold text-amber-700">{{ summary.withheld }}</p><p class="text-[10px] uppercase text-slate-500">Withheld</p></div>
            <div class="card card--muted !py-3 text-center"><p class="text-lg font-bold">{{ summary.marks_entered }}</p><p class="text-[10px] uppercase text-slate-500">Marks entered</p></div>
            <div class="card card--muted !py-3 text-center"><p class="text-lg font-bold">{{ summary.not_marked }}</p><p class="text-[10px] uppercase text-slate-500">Present, not marked</p></div>
        </div>

        <div class="flex flex-wrap gap-2 mb-4 items-center">
            <input v-model="filterForm.search" type="search" class="field max-w-xs" placeholder="Search ticket, student, or school…">
            <SearchableSelect v-model="filterForm.school_id" :options="schoolOptions" :all-option="true" all-label="All schools" placeholder="All schools" />
            <SearchableSelect v-model="filterForm.class" :options="classOptions.map(c => ({ value: c, label: c }))" :all-option="true" all-label="All classes" placeholder="All classes" />
            <button v-if="hasFilters" type="button" @click="clearFilters" class="text-sm text-slate-400 hover:underline">Clear</button>
        </div>

        <div class="flex flex-wrap gap-2 mb-4 items-center">
            <button
                v-if="summary?.pending > 0"
                type="button"
                class="btn-primary text-sm"
                :disabled="markingPending"
                @click="markPendingPresent"
            >
                {{ markingPending ? 'Marking…' : `Mark ${hasExportFilters ? 'filtered ' : ''}pending as present (${summary.pending})` }}
            </button>
            <a :href="exportUrl(`${examBase}/attendance/absent/export`)" class="btn-secondary text-sm">
                Absent Excel {{ hasExportFilters ? '(filtered)' : '' }} ↓
            </a>
            <a :href="exportUrl(`${examBase}/attendance/absent.pdf`)" class="btn-secondary text-sm" target="_blank">
                Absent PDF {{ hasExportFilters ? '(filtered)' : '' }} ↓
            </a>
            <a :href="exportUrl(`${examBase}/attendance/export`)" class="btn-secondary text-sm">Export attendance {{ hasExportFilters ? 'filtered' : '' }} CSV ↓</a>
            <a :href="exportUrl(`${examBase}/attendance/sheet.pdf`)" class="btn-secondary text-sm" target="_blank">Class-wise attendance PDF ↓</a>
            <a :href="`${examBase}/mark-sheet.pdf`" class="btn-secondary text-sm" target="_blank">Mark sheet PDF ↓</a>
            <form @submit.prevent="importAttendance" class="flex flex-wrap gap-2 items-center">
                <input ref="importFile" type="file" accept=".csv,.txt,.xlsx,.xls" class="text-sm" required>
                <button type="submit" class="btn-secondary text-sm">Import attendance (CSV/Excel)</button>
            </form>
            <p class="text-xs text-slate-500 w-full">
                Tap Present / Absent / Malpractice / Withheld to mark and save. Absent Excel/PDF use the school and class filters above.
                Import columns: hall_ticket_no, class, present|absent|malpractice|withheld, note.
            </p>
        </div>
        <div v-if="importErrors?.length" class="card mb-4 !border-amber-200 bg-amber-50 text-sm text-amber-900 space-y-1">
            <p class="font-semibold">Import issues ({{ importErrors.length }})</p>
            <ul class="list-disc pl-5 text-xs space-y-0.5 max-h-40 overflow-y-auto">
                <li v-for="(err, i) in importErrors.slice(0, 20)" :key="i">Row {{ err.row }}: {{ err.message }}</li>
            </ul>
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
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, i) in registrations.data" :key="r.id">
                            <td class="text-xs font-bold text-slate-400">{{ (registrations.current_page - 1) * registrations.per_page + i + 1 }}</td>
                            <td class="font-mono text-xs">{{ r.hall_ticket_no || '—' }}</td>
                            <td class="font-bold text-slate-900">{{ r.student?.name || r.participant_name || '—' }}</td>
                            <td class="text-xs">{{ r.student?.school_class?.name || '—' }}</td>
                            <td class="text-xs">{{ r.school?.name || '—' }}</td>
                            <td>
                                <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden text-xs font-semibold shadow-sm" role="tablist" :aria-label="`Attendance for ${r.student?.name || 'student'}`">
                                    <button
                                        v-for="opt in statusTabs"
                                        :key="opt.value"
                                        type="button"
                                        role="tab"
                                        :aria-selected="forms[r.id].attendance_status === opt.value"
                                        class="px-2.5 py-1.5 border-r border-slate-200 last:border-r-0 transition-colors disabled:opacity-60"
                                        :class="tabClass(forms[r.id].attendance_status, opt.value)"
                                        :disabled="savingId === r.id"
                                        @click="setStatus(r, opt.value)"
                                    >
                                        {{ opt.label }}
                                    </button>
                                </div>
                            </td>
                            <td>
                                <div v-if="['malpractice','withheld'].includes(forms[r.id].attendance_status)" class="flex items-center gap-2 min-w-[12rem]">
                                    <input
                                        v-model="forms[r.id].attendance_note"
                                        type="text"
                                        class="field text-xs"
                                        placeholder="Reason (required)"
                                        :aria-label="`Note for ${r.student?.name}`"
                                        @keydown.enter.prevent="save(r)"
                                    >
                                    <button type="button" class="link-brand text-xs whitespace-nowrap" :disabled="savingId === r.id" @click="save(r)">
                                        Save note
                                    </button>
                                </div>
                                <span v-else class="text-slate-300 text-xs">—</span>
                            </td>
                        </tr>
                        <tr v-if="!registrations.data?.length">
                            <td colspan="7" class="p-6 text-center text-slate-400">No matching registrations.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <PaginationLinks :links="registrations.links" :meta="{ from: registrations.from, to: registrations.to, total: registrations.total, last_page: registrations.last_page }" />
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';
import InlineAlert from '@/Components/ui/InlineAlert.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import PaginationLinks from '@/Components/ui/PaginationLinks.vue';
import { useDebouncedInertiaFilters } from '@/composables/useDebouncedInertiaFilters.js';

const alertMessage = ref('');
const savingId = ref(null);

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    exam: Object,
    registrations: Object,
    summary: Object,
    pendingCorrectionsCount: { type: Number, default: 0 },
    filters: { type: Object, default: () => ({}) },
    schoolOptions: { type: Array, default: () => [] },
    classOptions: { type: Array, default: () => [] },
});
const importFile = ref(null);
const page = usePage();

const statusTabs = [
    { value: 'present', label: 'Present' },
    { value: 'absent', label: 'Absent' },
    { value: 'malpractice', label: 'Malpractice' },
    { value: 'withheld', label: 'Withheld' },
];

const filterForm = reactive({
    search: props.filters?.search ?? '',
    school_id: props.filters?.school_id ?? null,
    class: props.filters?.class ?? null,
});

const hasFilters = computed(() => !!(filterForm.search || filterForm.school_id || filterForm.class));
const hasExportFilters = computed(() => !!(filterForm.school_id || filterForm.class));
const examBase = `/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}`;

function applyFilters() {
    router.get(`${examBase}/attendance`, { ...filterForm }, { preserveState: true, preserveScroll: true });
}

useDebouncedInertiaFilters(filterForm, applyFilters, () => props.filters);

function clearFilters() {
    filterForm.search = '';
    filterForm.school_id = null;
    filterForm.class = null;
    applyFilters();
}

/** Pass school/class filters into CSV/PDF downloads (search is list-only). */
function exportUrl(baseUrl) {
    const params = [];
    if (filterForm.school_id) params.push('school_id=' + encodeURIComponent(filterForm.school_id));
    if (filterForm.class) params.push('class=' + encodeURIComponent(filterForm.class));
    if (!params.length) return baseUrl;
    return baseUrl + (baseUrl.includes('?') ? '&' : '?') + params.join('&');
}

function tabClass(current, value) {
    const active = current === value;
    if (!active) return 'bg-white text-slate-600 hover:bg-slate-50';
    if (value === 'present') return 'bg-emerald-600 text-white';
    if (value === 'absent') return 'bg-rose-600 text-white';
    return 'bg-amber-600 text-white';
}

const importErrors = computed(() => page.props.flash?.import_errors ?? []);
const markingPending = ref(false);

const forms = reactive({});
function syncForms(registrations) {
    for (const r of registrations?.data ?? []) {
        forms[r.id] = { attendance_status: r.attendance_status || 'present', attendance_note: r.attendance_note || '' };
    }
}
syncForms(props.registrations);
watch(() => props.registrations, syncForms);

function markPendingPresent() {
    if (markingPending.value) return;
    const scope = hasExportFilters.value
        ? 'filtered pending students'
        : 'all pending students for this exam';
    if (!window.confirm(`Mark ${scope} as Present? Already marked absent / malpractice / withheld rows are left unchanged.`)) {
        return;
    }
    markingPending.value = true;
    router.post(`${examBase}/attendance/mark-pending-present`, {
        school_id: filterForm.school_id || null,
        class: filterForm.class || null,
    }, {
        preserveScroll: true,
        onFinish: () => { markingPending.value = false; },
    });
}

function setStatus(r, status) {
    if (!forms[r.id]) return;
    forms[r.id].attendance_status = status;
    if (['malpractice', 'withheld'].includes(status) && !forms[r.id].attendance_note?.trim()) {
        // Wait for note before posting.
        return;
    }
    save(r);
}

function save(r) {
    const status = forms[r.id].attendance_status;
    if (!status || status === 'pending') return;
    if (['malpractice', 'withheld'].includes(status) && !forms[r.id].attendance_note?.trim()) {
        alertMessage.value = 'A reason/note is required when marking malpractice or withheld.';
        return;
    }
    savingId.value = r.id;
    router.post(`${examBase}/attendance`, {
        registration_id: r.id,
        attendance_status: status,
        attendance_note: forms[r.id].attendance_note || null,
    }, {
        preserveScroll: true,
        onFinish: () => { savingId.value = null; },
    });
}

function importAttendance() {
    const file = importFile.value?.files?.[0];
    if (!file) return;
    const form = new FormData();
    form.append('file', file);
    router.post(`${examBase}/attendance/import`, form, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { if (importFile.value) importFile.value.value = ''; },
    });
}
</script>
