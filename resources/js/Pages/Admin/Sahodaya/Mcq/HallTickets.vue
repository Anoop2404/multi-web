<template>
    <SahodayaAdminLayout :title="`Hall Tickets — ${exam.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="Talent Search exam" description="Design admit cards, issue reg. numbers, and print hall tickets.">
            <template #actions>
                <a :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/hall-tickets/preview`"
                   target="_blank" rel="noopener" class="btn-secondary text-sm">Sample hall ticket ↗</a>
                <a :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/certificates/preview`"
                   target="_blank" rel="noopener" class="btn-secondary text-sm">Sample certificate ↗</a>
                <button type="button" @click="generate" class="btn-primary text-sm">Issue missing (approved only)</button>
                <button v-if="!hallTicketsPublished" type="button" @click="renumberByClass" class="btn-secondary text-sm">Renumber by class</button>
                <button type="button" @click="allocateSeats(false)" class="btn-secondary text-sm">Allocate seats</button>
                <a :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/hall-tickets/print-all`"
                   target="_blank" rel="noopener" class="btn-secondary text-sm">Print all ↗</a>
            </template>
        </PageHeader>
        <McqExamSubNav :sahodaya-id="sahodaya.id" :exam-id="exam.id" :delivery-mode="exam.delivery_mode || 'offline'" :results-published="!!exam.results_published" active="hall-tickets" />

        <div class="notice-banner mb-6 flex flex-wrap items-center justify-between gap-3"
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

        <div class="grid lg:grid-cols-2 gap-6 mb-6">
            <form @submit.prevent="saveDesign" class="card space-y-4">
                <div>
                    <h3 class="section-title">Hall ticket design</h3>
                    <p class="section-desc">Logo, colors, and layout apply to printed and student portal tickets.</p>
                </div>

                <FormGrid>
                    <FormField label="Ticket header title" class-extra="sm:col-span-2">
                        <input v-model="designForm.header_title" class="field" placeholder="Talent Search Examination — Hall Ticket">
                    </FormField>
                    <FormField label="Footer note" class-extra="sm:col-span-2">
                        <input v-model="designForm.footer_note" class="field" placeholder="Optional note on admit card">
                    </FormField>
                    <FormField label="Primary color (border / title)">
                        <input v-model="designForm.primary_color" type="color" class="field h-10 p-1">
                    </FormField>
                    <FormField label="Accent color (reg. no.)">
                        <input v-model="designForm.accent_color" type="color" class="field h-10 p-1">
                    </FormField>
                    <FormField label="Layout">
                        <SearchableSelect v-model="designForm.layout" :options="[{ value: 'standard', label: 'Standard' }, { value: 'compact', label: 'Compact (2-up print)' }]" :all-option="false" />
                    </FormField>
                    <FormField label="Logo">
                        <input ref="logoInput" type="file" accept=".jpg,.jpeg,.png,.webp,.svg" class="text-sm">
                        <label v-if="logoUrl && !designForm.remove_logo" class="flex items-center gap-2 text-xs mt-2">
                            <input v-model="designForm.remove_logo" type="checkbox"> Remove current logo
                        </label>
                    </FormField>
                    <FormField label="Show on ticket" class-extra="sm:col-span-2">
                        <label class="flex items-center gap-2 text-sm mr-4">
                            <input v-model="designForm.show_reg_no" type="checkbox"> School admission no.
                        </label>
                        <label class="flex items-center gap-2 text-sm mr-4">
                            <input v-model="designForm.show_school" type="checkbox"> School name
                        </label>
                        <label class="flex items-center gap-2 text-sm mr-4">
                            <input v-model="designForm.show_photo" type="checkbox"> Candidate photo
                        </label>
                        <label class="flex items-center gap-2 text-sm mr-4">
                            <input v-model="designForm.show_qr" type="checkbox"> Verification QR code
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="designForm.show_signature" type="checkbox"> Signature lines
                        </label>
                    </FormField>
                    <FormField label="Reg. no. starts at" hint="Any whole number from 1. Quick presets below, or type your own (e.g. 10001).">
                        <McqRegNoStartField v-model="designForm.next_hall_ticket_no" :disabled="ticketsIssued" />
                    </FormField>
                    <FormField label="Reporting time" hint="Minutes before exam start candidates should arrive.">
                        <input v-model.number="designForm.report_before_minutes" type="number" min="0" max="240" class="field">
                    </FormField>
                    <FormField label="Gate closure" hint="Minutes after exam start when late entry is no longer allowed (0 = at start).">
                        <input v-model.number="designForm.gate_closure_after_minutes" type="number" min="0" max="240" class="field">
                    </FormField>
                    <FormField label="Hall instructions" class-extra="sm:col-span-2">
                        <textarea v-model="designForm.hall_instructions" class="field" rows="2" placeholder="Shown on admit card"></textarea>
                    </FormField>
                </FormGrid>

                <FormActions>
                    <button type="submit" class="btn-primary" :disabled="designForm.processing">Save design</button>
                    <a :href="previewUrl" target="_blank" rel="noopener" class="btn-secondary text-sm">Open print preview ↗</a>
                </FormActions>
            </form>

            <div class="card">
                <h3 class="section-title">Live preview</h3>
                <p class="section-desc mb-4">Sample ticket — updates as you edit (logo preview after save unless file selected).</p>
                <McqHallTicketPreview :design="designForm" :sample="previewSampleData" :logo-url="logoUrl" :logo-preview="localLogoPreview" />
            </div>
        </div>

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
                            <th>School</th>
                            <th>Approval</th>
                            <th>Hall / seat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(r, i) in registrations.data" :key="r.id">
                            <td class="text-xs font-bold text-slate-400">{{ (registrations.current_page - 1) * registrations.per_page + i + 1 }}</td>
                            <td class="font-mono text-xs font-bold text-indigo-700">{{ r.hall_ticket_no || '—' }}</td>
                            <td class="font-bold text-slate-900">{{ r.student?.name || r.teacher?.name || r.participant_name || '—' }}</td>
                            <td class="text-xs">{{ r.school?.name || '—' }}</td>
                            <td class="text-xs capitalize">{{ (r.approval_status || 'pending').replaceAll('_', ' ') }}</td>
                            <td class="text-xs">{{ r.hall_room || '—' }} {{ r.seat_no ? `· Seat ${r.seat_no}` : '' }}</td>
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
import { computed, reactive, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';
import McqHallTicketPreview from '@/Components/sahodaya/McqHallTicketPreview.vue';
import McqRegNoStartField from '@/Components/sahodaya/McqRegNoStartField.vue';
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
    registrations: Object,
    hallTicketDesign: Object,
    logoUrl: String,
    previewSample: Object,
    ticketsIssued: Boolean,
    halls: { type: Array, default: () => [] },
    hallTicketsPublished: { type: Boolean, default: false },
    hallTicketsPublishedAt: { type: String, default: null },
    filters: { type: Object, default: () => ({}) },
    schoolOptions: { type: Array, default: () => [] },
    classOptions: { type: Array, default: () => [] },
});

const { confirm } = useConfirm();
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

const designForm = useForm({
    header_title: props.hallTicketDesign?.header_title ?? '',
    footer_note: props.hallTicketDesign?.footer_note ?? '',
    show_reg_no: props.hallTicketDesign?.show_reg_no ?? true,
    show_school: props.hallTicketDesign?.show_school ?? true,
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

const previewUrl = computed(() => {
    const params = new URLSearchParams({
        header_title: designForm.header_title || '',
        footer_note: designForm.footer_note || '',
        show_reg_no: designForm.show_reg_no ? '1' : '0',
        show_school: designForm.show_school ? '1' : '0',
        primary_color: designForm.primary_color,
        accent_color: designForm.accent_color,
        layout: designForm.layout,
    });
    return `/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/hall-tickets/preview?${params}`;
});

function saveDesign() {
    const fd = new FormData();
    fd.append('header_title', designForm.header_title || '');
    fd.append('footer_note', designForm.footer_note || '');
    fd.append('show_reg_no', designForm.show_reg_no ? '1' : '0');
    fd.append('show_school', designForm.show_school ? '1' : '0');
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

    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/hall-tickets/design`, fd, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            designForm.remove_logo = false;
            if (logoInput.value) logoInput.value.value = '';
            localLogoPreview.value = null;
        },
    });
}

function generate() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/hall-tickets/generate`, {}, { preserveScroll: true });
}

async function renumberByClass() {
    if (!(await confirm({
        message: 'This changes every candidate\'s reg. number into a fresh roll number per class — each class (shared across every school) gets its own 1..N sequence starting from the same starting number, so different classes will show the same roll no. Numbers already shared or printed elsewhere will no longer match. Continue?',
        destructive: true,
    }))) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/hall-tickets/renumber`, {}, { preserveScroll: true });
}

function publish() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/hall-tickets/publish`, {}, { preserveScroll: true });
}

async function unpublish() {
    if (!(await confirm({ message: 'Hide hall tickets from schools and candidates again?', destructive: true }))) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/hall-tickets/unpublish`, {}, { preserveScroll: true });
}
</script>
