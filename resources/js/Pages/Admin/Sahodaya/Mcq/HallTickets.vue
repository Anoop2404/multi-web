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
                <a :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/hall-tickets/print-all`"
                   target="_blank" rel="noopener" class="btn-secondary text-sm">Print all ↗</a>
            </template>
        </PageHeader>
        <McqExamSubNav :sahodaya-id="sahodaya.id" :exam-id="exam.id" :delivery-mode="exam.delivery_mode || 'offline'" :results-published="!!exam.results_published" active="hall-tickets" />

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
                        <select v-model="designForm.layout" class="field">
                            <option value="standard">Standard</option>
                            <option value="compact">Compact (2-up print)</option>
                        </select>
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
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="designForm.show_school" type="checkbox"> School name
                        </label>
                    </FormField>
                    <FormField label="Reg. no. starts at" hint="Any whole number from 1. Quick presets below, or type your own (e.g. 10001).">
                        <McqRegNoStartField v-model="designForm.next_hall_ticket_no" :disabled="ticketsIssued" />
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

        <input v-model="searchQuery" type="search" class="field max-w-md mb-4" placeholder="Search ticket or student…">

        <div class="form-section overflow-hidden !p-0">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Reg. no.</th>
                            <th>Student</th>
                            <th>School</th>
                            <th>Approval</th>
                            <th>Hall / seat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in filteredRegistrations" :key="r.id">
                            <td class="font-mono">{{ r.hall_ticket_no || '—' }}</td>
                            <td>{{ r.student?.name }}</td>
                            <td class="text-xs">{{ r.school?.name }}</td>
                            <td class="text-xs capitalize">{{ (r.approval_status || 'pending').replaceAll('_', ' ') }}</td>
                            <td class="text-xs">{{ r.hall_room || '—' }} {{ r.seat_no ? `· Seat ${r.seat_no}` : '' }}</td>
                        </tr>
                        <tr v-if="!filteredRegistrations.length">
                            <td colspan="5" class="p-6 text-center text-slate-400">No matching registrations.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';
import McqHallTicketPreview from '@/Components/sahodaya/McqHallTicketPreview.vue';
import McqRegNoStartField from '@/Components/sahodaya/McqRegNoStartField.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    exam: Object,
    registrations: Array,
    hallTicketDesign: Object,
    logoUrl: String,
    previewSample: Object,
    ticketsIssued: Boolean,
});

const searchQuery = ref('');
const logoInput = ref(null);
const localLogoPreview = ref(null);

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

const filteredRegistrations = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return props.registrations;
    return props.registrations.filter((r) =>
        [r.hall_ticket_no, r.student?.name, r.school?.name].filter(Boolean).join(' ').toLowerCase().includes(q),
    );
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
</script>
