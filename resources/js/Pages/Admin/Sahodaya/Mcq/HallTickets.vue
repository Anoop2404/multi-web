<template>
    <SahodayaAdminLayout :title="`Hall Tickets — ${exam.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="Talent Search exam" description="Design admit cards, issue reg. numbers, and print hall tickets.">
            <template #actions>
                <a :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/hall-tickets/preview`"
                   target="_blank" rel="noopener" class="btn-secondary text-sm">Sample hall ticket ↗</a>
                <template v-if="workspaceTab === 'operations'">
                <button type="button" @click="showAddCandidateModal = true" class="btn-primary text-sm flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Candidate
                </button>
                <button type="button" @click="generate" class="btn-secondary text-sm flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                    </svg>
                    Assign missing Reg. Nos
                </button>
                <button type="button" @click="renumberByClass" class="btn-secondary text-sm flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Regenerate all Reg. Nos
                </button>
                <button type="button" @click="allocateSeats(false)" class="btn-secondary text-sm">Allocate seats</button>
                <a :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/hall-tickets/print-all`"
                   target="_blank" rel="noopener" class="btn-secondary text-sm">Print all ↗</a>
                </template>
            </template>
        </PageHeader>
        <McqExamSubNav :sahodaya-id="sahodaya.id" :exam-id="exam.id" :delivery-mode="exam.delivery_mode || 'offline'" :results-published="!!exam.results_published" active="hall-tickets" />

        <div class="flex gap-1 rounded-xl bg-slate-100 p-1 mb-5 w-fit" role="tablist" aria-label="Hall ticket workspace">
            <button v-for="tab in [{id:'design',label:'Design admit cards'},{id:'operations',label:'Issue & print'}]" :key="tab.id" type="button" role="tab" :aria-selected="workspaceTab===tab.id" @click="workspaceTab=tab.id" class="rounded-lg px-4 py-2 text-sm font-semibold" :class="workspaceTab===tab.id ? 'bg-white shadow-sm text-indigo-700' : 'text-slate-500'">{{ tab.label }}</button>
        </div>
        <div v-if="workspaceTab === 'operations'" class="notice-banner mb-6 flex flex-wrap items-center justify-between gap-3"
             :class="hallTicketsPublished ? 'notice-banner--success' : 'notice-banner--warning'">
            <div>
                <p class="font-semibold">
                    {{ hallTicketsPublished ? 'Hall tickets are visible to schools' : 'Hall tickets are hidden from schools' }}
                </p>
                <p class="mt-1 text-sm">
                    <template v-if="hallTicketsPublished">
                        Published {{ hallTicketsPublishedAt }}. Schools and candidates can now see reg. no., hall, and seat.
                    </template>
                    <template v-else>
                        Reg. numbers are still assigned automatically as registrations are approved, but schools and candidates
                        won't see them, and the school Hall Tickets tab/PDF stays disabled, until you publish.
                    </template>
                </p>
            </div>
            <button v-if="hallTicketsPublished" type="button" class="btn-secondary text-sm shrink-0" @click="unpublish">
                Unpublish
            </button>
            <button v-else type="button" class="btn-primary text-sm shrink-0" @click="publish">
                Publish to schools
            </button>
        </div>

        <McqAdmitCardDesigner v-if="workspaceTab === 'design'" :design="designForm" :defaults="defaultHallTicketDesign" :exam="exam"
            :sample="previewSampleData" :background-url="backgroundUrl" :logo-url="logoUrl"
            :preview-url="previewUrl" :saving="designSaving" :saved="designSaved" :errors="designErrors"
            @background="backgroundFile = $event" @save="saveDesign">
            <template #settings>
                <details class="border-t border-slate-200 pt-3 text-xs">
                    <summary class="font-semibold cursor-pointer text-slate-700">Exam card settings</summary>
                    <div class="space-y-3 mt-3">
                        <FormField label="Ticket heading"><input v-model="designForm.header_title" class="field"></FormField>
                        <FormField label="Footer note"><input v-model="designForm.footer_note" class="field"></FormField>
                        <FormField label="Reporting lead time (minutes)"><input v-model.number="designForm.report_before_minutes" class="field" type="number" min="0" max="240"></FormField>
                        <FormField label="Gate closure grace (minutes)"><input v-model.number="designForm.gate_closure_after_minutes" class="field" type="number" min="0" max="240"></FormField>
                        <McqRegNoStartField v-model="designForm.next_hall_ticket_no" :disabled="ticketsIssued" />
                        <FormField label="Exam instructions"><textarea v-model="designForm.hall_instructions" class="field" rows="3"></textarea></FormField>
                        <template v-if="['standard','compact'].includes(designForm.layout)">
                            <label class="block">Primary colour<input v-model="designForm.primary_color" type="color" class="block mt-1"></label>
                            <label class="block">Accent colour<input v-model="designForm.accent_color" type="color" class="block mt-1"></label>
                            <label class="block">Logo<input ref="logoInput" type="file" accept="image/png,image/jpeg,image/webp" class="block mt-1 w-full"></label>
                            <label class="flex gap-2"><input v-model="designForm.show_school" type="checkbox">School name</label>
                            <label class="flex gap-2"><input v-model="designForm.show_reg_no" type="checkbox">School admission number</label>
                            <label class="flex gap-2"><input v-model="designForm.show_photo" type="checkbox">Candidate photo</label>
                            <label class="flex gap-2"><input v-model="designForm.show_qr" type="checkbox">Verification QR</label>
                            <label class="flex gap-2"><input v-model="designForm.show_signature" type="checkbox">Signature blocks</label>
                            <label v-if="isOnline" class="flex gap-2"><input v-model="designForm.show_exam_passcode" type="checkbox">Online exam passcode</label>
                        </template>
                    </div>
                </details>
            </template>
        </McqAdmitCardDesigner>

        <div v-show="workspaceTab === 'operations'">
        <div class="card mb-6 space-y-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="section-title !mb-0">Hall plan & seating</h3>
                    <p class="section-desc">Optional halls with capacity. Auto-allocate seats by school/name within capacity; without halls, seat numbers are 1…N.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn-secondary text-sm" @click="addHall">Add hall</button>
                    <button type="button" class="btn-primary text-sm" :disabled="hallsForm.processing" @click="saveHalls">Save halls</button>
                    <button type="button" class="btn-secondary text-sm" @click="allocateSeats(true)">Re-allocate all</button>
                </div>
            </div>
            <div v-for="(hall, idx) in hallsForm.halls" :key="idx" class="flex flex-wrap gap-2 items-end">
                <FormField label="Hall / room" class-extra="min-w-[180px]">
                    <input v-model="hall.name" class="field" placeholder="Hall A">
                </FormField>
                <FormField label="Capacity" class-extra="w-28">
                    <input v-model.number="hall.capacity" type="number" min="1" class="field">
                </FormField>
                <button type="button" class="text-xs text-red-600 mb-2" @click="removeHall(idx)">Remove</button>
            </div>
            <p v-if="!hallsForm.halls.length" class="text-xs text-slate-500">No halls configured — seat allocation will use a single Main room.</p>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex flex-wrap gap-2 items-center">
                <input v-model="filterForm.search" type="search" class="field max-w-md text-xs" placeholder="Search ticket, name, admission no…">
                <SearchableSelect v-model="filterForm.school_id" :options="schoolOptions" :all-option="true" all-label="All schools" placeholder="All schools" />
                <SearchableSelect v-model="filterForm.class" :options="classOptions.map(c => ({ value: c, label: c }))" :all-option="true" all-label="All classes" placeholder="All classes" />
                <button v-if="hasFilters" type="button" @click="clearFilters" class="text-xs text-slate-400 hover:underline">Clear</button>
            </div>
            <span class="text-xs text-slate-500 font-semibold">Showing {{ registrations.total }} candidate(s)</span>
        </div>

        <div class="form-section overflow-hidden !p-0">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-12">#</th>
                            <th>Reg. no.</th>
                            <th>Candidate</th>
                            <th>Class</th>
                            <th>School</th>
                            <th>Approval</th>
                            <th>Hall / seat</th>
                            <th v-if="isOnline">Passcode</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, i) in registrations.data" :key="r.id">
                            <td class="text-xs font-bold text-slate-400">{{ (registrations.current_page - 1) * registrations.per_page + i + 1 }}</td>
                            <td class="font-mono text-xs font-bold text-indigo-700">
                                <span v-if="r.hall_ticket_no">{{ r.hall_ticket_no }}</span>
                                <button
                                    v-else-if="r.status !== 'cancelled'"
                                    type="button"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-sans font-semibold bg-amber-50 text-amber-800 border border-amber-300 hover:bg-amber-100 transition"
                                    title="Assign Reg. No to this candidate"
                                    @click="assignSingleRegNo(r)"
                                >
                                    + Assign Reg. No
                                </button>
                                <span v-else class="text-slate-400 font-normal">Cancelled</span>
                            </td>
                            <td class="font-bold text-slate-900">{{ r.student?.name || r.teacher?.name || r.participant_name || '—' }}</td>
                            <td class="text-xs">{{ r.student?.school_class?.name || (r.teacher_id ? 'Teacher' : '—') }}</td>
                            <td class="text-xs">{{ r.school?.name || '—' }}</td>
                            <td class="text-xs capitalize">{{ (r.approval_status || 'pending').replaceAll('_', ' ') }}</td>
                            <td class="text-xs">{{ r.hall_room || '—' }} {{ r.seat_no ? `· Seat ${r.seat_no}` : '' }}</td>
                            <td v-if="isOnline" class="text-xs">
                                <button v-if="r.hall_ticket_no" type="button" class="font-semibold text-indigo-700 hover:underline" @click="reissuePasscode(r)">Reissue</button>
                                <span v-else class="text-slate-400">—</span>
                            </td>
                            <td class="text-right text-xs">
                                <div class="flex items-center justify-end gap-3">
                                    <button
                                        v-if="!r.hall_ticket_no && r.status !== 'cancelled'"
                                        type="button"
                                        class="font-semibold text-indigo-600 hover:text-indigo-800 hover:underline"
                                        @click="assignSingleRegNo(r)"
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
                            <td :colspan="isOnline ? 9 : 8" class="p-6 text-center text-slate-400">No matching registrations.</td>
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
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';
import McqAdmitCardDesigner from '@/Components/sahodaya/McqAdmitCardDesigner.vue';
import McqRegNoStartField from '@/Components/sahodaya/McqRegNoStartField.vue';
import McqAddCandidateModal from '@/Components/sahodaya/McqAddCandidateModal.vue';
import FormField from '@/Components/ui/FormField.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import PaginationLinks from '@/Components/ui/PaginationLinks.vue';
import { useConfirm } from '@/composables/useConfirm';
import { useDebouncedInertiaFilters } from '@/composables/useDebouncedInertiaFilters.js';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    exam: Object,
    templates: { type: Array, default: () => [] },
    registrations: Object,
    hallTicketDesign: Object,
    defaultHallTicketDesign: Object,
    backgroundUrl: String,
    logoUrl: String,
    previewSample: Object,
    ticketsIssued: Boolean,
    halls: { type: Array, default: () => [] },
    hallTicketsPublished: { type: Boolean, default: false },
    hallTicketsPublishedAt: { type: String, default: null },
    filters: { type: Object, default: () => ({}) },
    schoolOptions: { type: Array, default: () => [] },
    memberSchools: { type: Array, default: () => [] },
    classOptions: { type: Array, default: () => [] },
});

const { confirm, prompt } = useConfirm();
const showAddCandidateModal = ref(false);
const workspaceTab = ref('design');
const schoolSelectOptions = computed(() => props.memberSchools?.length ? props.memberSchools : props.schoolOptions);
const logoInput = ref(null);
const localLogoPreview = ref(null);

const filterForm = reactive({
    search: props.filters?.search ?? '',
    school_id: props.filters?.school_id ?? null,
    class: props.filters?.class ?? null,
});

const hasFilters = computed(() => !!(filterForm.search || filterForm.school_id || filterForm.class));

function applyFilters() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/hall-tickets`, { ...filterForm }, { preserveState: true, preserveScroll: true });
}

useDebouncedInertiaFilters(filterForm, applyFilters, () => props.filters);

function clearFilters() {
    filterForm.search = '';
    filterForm.school_id = null;
    filterForm.class = null;
    applyFilters();
}

const hallsForm = useForm({
    halls: (props.halls?.length ? props.halls : []).map(h => ({ name: h.name, capacity: h.capacity })),
});

function addHall() {
    hallsForm.halls.push({ name: '', capacity: 40 });
}
function removeHall(idx) {
    hallsForm.halls.splice(idx, 1);
}
function saveHalls() {
    hallsForm.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/hall-tickets/halls`, { preserveScroll: true });
}
function allocateSeats(reallocate = false) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/hall-tickets/allocate-seats`, {
        reallocate: reallocate ? 1 : 0,
    }, { preserveScroll: true });
}

const backgroundFile = ref(null);
const designSaving = ref(false);
const designSaved = ref(false);
const designErrors = ref({});
const designForm = useForm({
    ...JSON.parse(JSON.stringify(props.hallTicketDesign || {})),
    field_positions: JSON.parse(JSON.stringify(props.hallTicketDesign?.field_positions || props.defaultHallTicketDesign?.field_positions || {})),
    hall_ticket_template_id: props.exam.hall_ticket_template_id ?? null,
    header_title: props.hallTicketDesign?.header_title ?? '',
    footer_note: props.hallTicketDesign?.footer_note ?? '',
    show_reg_no: props.hallTicketDesign?.show_reg_no ?? true,
    show_school: props.hallTicketDesign?.show_school ?? true,
    show_exam_passcode: props.hallTicketDesign?.show_exam_passcode ?? true,
    primary_color: props.hallTicketDesign?.primary_color ?? '#1e3a8a',
    accent_color: props.hallTicketDesign?.accent_color ?? '#dc2626',
    layout: props.hallTicketDesign?.layout ?? 'standard',
    next_hall_ticket_no: props.exam.next_hall_ticket_no ?? 100,
    hall_instructions: props.exam.hall_instructions ?? '',
    remove_logo: false,
    show_photo: props.hallTicketDesign?.show_photo ?? true,
    show_qr: props.hallTicketDesign?.show_qr ?? true,
    show_signature: props.hallTicketDesign?.show_signature ?? true,
    report_before_minutes: props.hallTicketDesign?.report_before_minutes ?? 30,
    gate_closure_after_minutes: props.hallTicketDesign?.gate_closure_after_minutes ?? 0,
});

watch(() => logoInput.value?.files?.[0], (file) => {
    if (!file) {
        localLogoPreview.value = null;
        return;
    }
    localLogoPreview.value = URL.createObjectURL(file);
});

const previewSampleData = computed(() => ({
    ...props.previewSample,
    hall_instructions: designForm.hall_instructions || props.previewSample?.hall_instructions,
}));

const previewUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/hall-tickets/preview`);
watch(() => designForm.data(), () => { designSaved.value = false; }, { deep: true });
watch(backgroundFile, () => { designSaved.value = false; });

function saveDesign() {
    designSaving.value = true;
    designSaved.value = false;
    designErrors.value = {};
    const fd = new FormData();
    fd.append('header_title', designForm.header_title || '');
    fd.append('footer_note', designForm.footer_note || '');
    fd.append('show_reg_no', designForm.show_reg_no ? '1' : '0');
    fd.append('show_school', designForm.show_school ? '1' : '0');
    fd.append('show_exam_passcode', designForm.show_exam_passcode ? '1' : '0');
    fd.append('primary_color', designForm.primary_color);
    fd.append('accent_color', designForm.accent_color);
    fd.append('layout', designForm.layout);
    fd.append('next_hall_ticket_no', String(designForm.next_hall_ticket_no));
    fd.append('hall_instructions', designForm.hall_instructions || '');
    fd.append('remove_logo', designForm.remove_logo ? '1' : '0');
    fd.append('show_photo', designForm.show_photo ? '1' : '0');
    fd.append('show_qr', designForm.show_qr ? '1' : '0');
    fd.append('show_signature', designForm.show_signature ? '1' : '0');
    fd.append('report_before_minutes', String(designForm.report_before_minutes));
    fd.append('gate_closure_after_minutes', String(designForm.gate_closure_after_minutes));
    const file = logoInput.value?.files?.[0];
    if (file) fd.append('logo', file);
    if (backgroundFile.value) fd.append('background', backgroundFile.value);
    fd.append('show_student_photo', designForm.show_student_photo ? '1' : '0');
    fd.append('remarks_text', designForm.remarks_text || '');
    fd.append('remarks_height_mm', String(designForm.remarks_height_mm || 35));
    fd.append('remarks_format', designForm.remarks_format || 'paragraph');
    fd.append('remarks_title', designForm.remarks_title ?? 'Remarks / Instructions');
    fd.append('remarks_font_size', String(designForm.remarks_font_size || 11));
    for (const [key,value] of Object.entries(designForm.remarks_text_style || {})) fd.append(`remarks_text_style[${key}]`,String(value));
    for(const key of ['remarks_theme_color','remarks_paper_color','remarks_text_color']) fd.append(key,designForm[key] || (key==='remarks_theme_color'?'#101b6f':key==='remarks_paper_color'?'#ffffff':'#111111'));
    for (const [index, field] of (designForm.custom_fields || []).entries()) {
        for (const [key, value] of Object.entries(field)) fd.append(`custom_fields[${index}][${key}]`, String(value));
    }
    for (const key of ['card_width_mm','card_height_mm','page_orientation','card_gap_mm','page_margin_mm','background_fit']) fd.append(key, String(designForm[key]));
    for (const [key, position] of Object.entries(designForm.field_positions)) {
        for (const [axis, value] of Object.entries(position)) fd.append(`field_positions[${key}][${axis}]`, String(value));
    }

    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/hall-tickets/design`, fd, {
        preserveScroll: true,
        forceFormData: true,
        onError: errors => { designErrors.value = errors; },
        onFinish: () => { designSaving.value = false; },
        onSuccess: () => {
            designSaved.value = true;
            backgroundFile.value = null;
            designForm.remove_logo = false;
            if (logoInput.value) logoInput.value.value = '';
            localLogoPreview.value = null;
        },
    });
}

const isOnline = computed(() => (props.exam.delivery_mode || 'offline') === 'online');

async function reissuePasscode(registration) {
    const name = registration.student?.name || registration.teacher?.name || 'this candidate';
    if (!(await confirm({
        title: 'Reissue passcode?',
        message: `${name} gets a new online exam passcode and the old one stops working. Reprint their hall ticket afterwards.`,
        confirmLabel: 'Reissue',
    }))) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/registrations/${registration.id}/passcode`, {}, { preserveScroll: true });
}

function generate() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/hall-tickets/generate`, {}, { preserveScroll: true });
}

function assignSingleRegNo(registration) {
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/registrations/${registration.id}/issue-reg-no`,
        {},
        { preserveScroll: true }
    );
}

async function renumberByClass() {
    if (!(await confirm({
        title: 'Regenerate all Reg. Nos?',
        message: 'This will renumber ALL active candidates into fresh sequential roll numbers per class starting from the starting number. Any newly registered or missing candidates will also receive a number. Proceed?',
        destructive: true,
        confirmLabel: 'Regenerate all numbers',
    }))) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/hall-tickets/renumber`, { force: true }, { preserveScroll: true });
}

function publish() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/hall-tickets/publish`, {}, { preserveScroll: true });
}

async function unpublish() {
    if (!(await confirm({ message: 'Hide hall tickets from schools and candidates again?', destructive: true }))) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/hall-tickets/unpublish`, {}, { preserveScroll: true });
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
