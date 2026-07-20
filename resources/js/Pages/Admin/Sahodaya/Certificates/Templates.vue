<template>
    <SahodayaEventsLayout title="Certificate Templates" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Certificate templates" eyebrow="Tools"
                    description="Create training templates with a PDF/image background, then choose one on each training program." />

        <form @submit.prevent="upload" class="card mb-4 space-y-4">
            <h3 class="section-title">
                {{ editingId
                    ? 'Edit certificate template'
                    : (form.event_type === 'training' || form.event_type === 'topper' ? 'Certificate template' : 'Upload template') }}
            </h3>
            <FormGrid>
                <FormField label="Event type" required>
                    <template #default="{ id }">
                        <select :id="id" v-model="form.event_type" class="field" required>
                            <option value="fest">Fest / Event certificate</option>
                            <option value="training">Training</option>
                            <option value="topper">Topper (Congratulations)</option>
                        </select>
                    </template>
                </FormField>
                <FormField label="Certificate type" hint="e.g. participation, congratulations" required>
                    <template #default="{ id }">
                        <select v-if="form.event_type === 'training'" :id="id" v-model="form.certificate_type" class="field" required>
                            <option v-for="t in trainingCertificateTypes" :key="t" :value="t">{{ t.replaceAll('_', ' ') }}</option>
                        </select>
                        <select v-else-if="form.event_type === 'topper'" :id="id" v-model="form.certificate_type" class="field" required>
                            <option value="congratulations">congratulations</option>
                        </select>
                        <select v-else-if="form.event_type === 'fest'" :id="id" v-model="form.certificate_type" class="field" required>
                            <option v-for="t in festCertificateTypes" :key="t" :value="t">{{ t.replaceAll('_', ' ') }}</option>
                        </select>
                        <input v-else :id="id" v-model="form.certificate_type" class="field" placeholder="participation" required>
                    </template>
                </FormField>

                <template v-if="form.event_type === 'fest'">
                    <FormField label="Event" hint="Leave blank to make this the Sahodaya-wide default for this certificate type.">
                        <template #default="{ id }">
                            <select :id="id" v-model="form.event_id" class="field" @change="form.item_id = null">
                                <option :value="null">All events (default)</option>
                                <option v-for="e in festEvents" :key="e.id" :value="e.id">{{ e.title }}</option>
                            </select>
                        </template>
                    </FormField>
                    <FormField label="Item" hint="Leave blank to cover every item in the selected event.">
                        <template #default="{ id }">
                            <select :id="id" v-model="form.item_id" class="field" :disabled="!form.event_id">
                                <option :value="null">All items in event</option>
                                <option v-for="i in selectedEventItems" :key="i.id" :value="i.id">{{ i.title }}</option>
                            </select>
                        </template>
                    </FormField>
                </template>

                <template v-if="form.event_type === 'training' || form.event_type === 'topper' || form.event_type === 'fest'">
                    <FormField label="Certificate title" class-extra="sm:col-span-2">
                        <template #default="{ id }">
                            <input :id="id" v-model="form.title" class="field"
                                   :placeholder="form.event_type === 'topper' ? 'Certificate of Congratulations' : 'Certificate of Participation'">
                        </template>
                    </FormField>
                    <FormField
                        v-if="form.event_type === 'training' || form.event_type === 'fest'"
                        label="Background (PDF or image)"
                        class-extra="sm:col-span-2"
                        :hint="editingId
                            ? 'PDF first page is converted to an image and used as the certificate backdrop. Leave blank to keep the current background — only choose a file to replace it.'
                            : 'PDF first page is converted to an image and used as the certificate backdrop. Recipient/body text is placed on top.'"
                    >
                        <template #default="{ id }">
                            <div v-if="editingId && editingTemplate?.background_url" class="mb-2 flex items-center gap-2">
                                <img :src="editingTemplate.background_url" alt="Current background"
                                     class="h-16 w-auto rounded border border-slate-200 object-contain bg-slate-50">
                                <span class="text-xs text-slate-500">Current background</span>
                            </div>
                            <input :id="id" type="file" accept=".pdf,.png,.jpg,.jpeg"
                                   class="field"
                                   @change="e => form.template_file = e.target.files[0]">
                        </template>
                    </FormField>
                    <template v-if="form.event_type === 'training' || form.event_type === 'fest'">
                        <div class="sm:col-span-2 rounded-xl border border-slate-100 bg-slate-50/80 p-4 space-y-3">
                            <p class="text-sm font-semibold text-slate-700">Background layout options</p>
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input v-model="form.layout_json.show_recipient_name" type="checkbox" class="rounded" :true-value="true" :false-value="false">
                                Show center recipient name
                            </label>
                            <p class="text-xs text-slate-500 -mt-1 pl-6">
                                Off by default — name already appears in the body text.
                            </p>
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input v-model="form.layout_json.show_participation_label" type="checkbox" class="rounded" :true-value="true" :false-value="false">
                                Show “OF PARTICIPATION” on background
                            </label>
                            <p class="text-xs text-slate-500 -mt-1 pl-6">
                                Uncheck to cover that subtitle on the uploaded design (adjust cover position below if needed).
                            </p>
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input v-model="form.layout_json.bold_variables" type="checkbox" class="rounded" :true-value="true" :false-value="false">
                                Bold placeholder values in body text
                            </label>
                        </div>
                        <FormField label="Recipient name — top %" hint="Vertical position on the background (0–100)">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="form.layout_json.recipient_name.top" type="number" min="0" max="100" class="field"
                                       :disabled="!isTruthy(form.layout_json.show_recipient_name)">
                            </template>
                        </FormField>
                        <FormField label="Recipient — font size (px)">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="form.layout_json.recipient_name.font_size" type="number" min="6" max="96" class="field"
                                       :disabled="!isTruthy(form.layout_json.show_recipient_name)">
                            </template>
                        </FormField>
                        <FormField label="Recipient — font family">
                            <template #default="{ id }">
                                <select :id="id" v-model="form.layout_json.recipient_name.font_family" class="field"
                                        :disabled="!isTruthy(form.layout_json.show_recipient_name)">
                                    <option v-for="font in fontFamilies" :key="font" :value="font">{{ font }}</option>
                                </select>
                            </template>
                        </FormField>
                        <FormField label="Recipient — style">
                            <template #default="{ id }">
                                <div :id="id" class="flex flex-wrap gap-4 pt-2 text-sm">
                                    <label class="flex items-center gap-2">
                                        <input v-model="form.layout_json.recipient_name.font_weight" type="checkbox"
                                               true-value="bold" false-value="normal"
                                               :disabled="!isTruthy(form.layout_json.show_recipient_name)">
                                        Bold
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input v-model="form.layout_json.recipient_name.font_style" type="checkbox"
                                               true-value="italic" false-value="normal"
                                               :disabled="!isTruthy(form.layout_json.show_recipient_name)">
                                        Italic
                                    </label>
                                </div>
                            </template>
                        </FormField>
                        <FormField label="Body text — top %">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="form.layout_json.body.top" type="number" min="0" max="100" class="field">
                            </template>
                        </FormField>
                        <FormField label="Body — font size (px)">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="form.layout_json.body.font_size" type="number" min="6" max="96" class="field">
                            </template>
                        </FormField>
                        <FormField label="Body — font family">
                            <template #default="{ id }">
                                <select :id="id" v-model="form.layout_json.body.font_family" class="field">
                                    <option v-for="font in fontFamilies" :key="font" :value="font">{{ font }}</option>
                                </select>
                            </template>
                        </FormField>
                        <FormField label="Body — style">
                            <template #default="{ id }">
                                <div :id="id" class="flex flex-wrap gap-4 pt-2 text-sm">
                                    <label class="flex items-center gap-2">
                                        <input v-model="form.layout_json.body.font_weight" type="checkbox"
                                               true-value="bold" false-value="normal">
                                        Bold
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input v-model="form.layout_json.body.font_style" type="checkbox"
                                               true-value="italic" false-value="normal">
                                        Italic
                                    </label>
                                </div>
                            </template>
                        </FormField>
                        <FormField label="Date — top %">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="form.layout_json.certificate_date.top" type="number" min="0" max="100" class="field">
                            </template>
                        </FormField>
                        <FormField label="Date — left %" hint="Side position (lower = further left)">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="form.layout_json.certificate_date.left" type="number" min="0" max="100" class="field">
                            </template>
                        </FormField>
                        <FormField label="Date — font size (px)">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="form.layout_json.certificate_date.font_size" type="number" min="6" max="96" class="field">
                            </template>
                        </FormField>
                        <FormField label="Date — font family">
                            <template #default="{ id }">
                                <select :id="id" v-model="form.layout_json.certificate_date.font_family" class="field">
                                    <option v-for="font in fontFamilies" :key="font" :value="font">{{ font }}</option>
                                </select>
                            </template>
                        </FormField>
                        <FormField label="Date — style">
                            <template #default="{ id }">
                                <div :id="id" class="flex flex-wrap gap-4 pt-2 text-sm">
                                    <label class="flex items-center gap-2">
                                        <input v-model="form.layout_json.certificate_date.font_weight" type="checkbox"
                                               true-value="bold" false-value="normal">
                                        Bold
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input v-model="form.layout_json.certificate_date.font_style" type="checkbox"
                                               true-value="italic" false-value="normal">
                                        Italic
                                    </label>
                                </div>
                            </template>
                        </FormField>
                        <FormField label="OF PARTICIPATION cover — top %"
                                   hint="Only used when “Show OF PARTICIPATION” is off">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="form.layout_json.participation_label_cover.top" type="number" min="0" max="100" class="field"
                                       :disabled="isTruthy(form.layout_json.show_participation_label)">
                            </template>
                        </FormField>
                        <FormField label="Cover height %">
                            <template #default="{ id }">
                                <input :id="id" v-model.number="form.layout_json.participation_label_cover.height" type="number" min="1" max="30" class="field"
                                       :disabled="isTruthy(form.layout_json.show_participation_label)">
                            </template>
                        </FormField>
                    </template>
                    <FormField label="Body text" class-extra="sm:col-span-2"
                               :hint="form.event_type === 'topper'
                                   ? 'Placeholders: {recipient_name}, {school_name}, {sahodaya_name}, {academic_year}, {class}, {examination_type}, {percentage}, {rank}'
                                   : form.event_type === 'fest'
                                       ? 'Placeholders: {recipient_name}, {school_name}, {event_title}, {item_title}, {event_dates}, {achievement_line}, {sahodaya_name}, {certificate_date}. With a background PDF, title/logo/signatories in the design are used instead of HTML chrome.'
                                       : 'Placeholders: {salutation} (Mr./Mrs. from gender), {recipient_name}, {designation}, {school_name}, {program_title}, {sahodaya_name}, {venue}, {conducted_on}, {days_attended}, {training_hours}. With a background PDF, title/logo/signatories in the design are used instead of HTML chrome.'">
                        <template #default="{ id }">
                            <textarea :id="id" v-model="form.body" class="field font-mono text-xs" rows="8"></textarea>
                        </template>
                    </FormField>
                    <FormField label="Logo (optional — unused when background is set)"
                               :hint="editingId && editingTemplate?.logo_url ? 'Leave blank to keep the current logo.' : null">
                        <template #default="{ id }">
                            <div v-if="editingId && editingTemplate?.logo_url" class="mb-2 flex items-center gap-2">
                                <img :src="editingTemplate.logo_url" alt="Current logo"
                                     class="h-10 w-auto rounded border border-slate-200 object-contain bg-slate-50">
                                <span class="text-xs text-slate-500">Current logo</span>
                            </div>
                            <input :id="id" type="file" accept="image/*" class="field" @change="e => form.logo = e.target.files[0]">
                        </template>
                    </FormField>
                    <FormField label="Seal (optional — unused when background is set)"
                               :hint="editingId && editingTemplate?.seal_url ? 'Leave blank to keep the current seal.' : null">
                        <template #default="{ id }">
                            <div v-if="editingId && editingTemplate?.seal_url" class="mb-2 flex items-center gap-2">
                                <img :src="editingTemplate.seal_url" alt="Current seal"
                                     class="h-10 w-auto rounded border border-slate-200 object-contain bg-slate-50">
                                <span class="text-xs text-slate-500">Current seal</span>
                            </div>
                            <input :id="id" type="file" accept="image/*" class="field" @change="e => form.seal = e.target.files[0]">
                        </template>
                    </FormField>
                    <div class="sm:col-span-2 space-y-3">
                        <p class="text-sm font-semibold text-slate-700">Signatories (unused when background PDF includes officers)</p>
                        <p v-if="editingId" class="text-xs text-slate-500 -mt-2">Leave a signature file blank to keep the existing signature image.</p>
                        <div v-for="(sig, i) in form.signatories" :key="i" class="grid gap-2 sm:grid-cols-3 border rounded-lg p-3">
                            <input v-model="sig.name" class="field text-sm" placeholder="Name">
                            <input v-model="sig.designation" class="field text-sm" placeholder="Designation">
                            <div>
                                <div v-if="editingId && editingTemplate?.signatories?.[i]?.signature_url" class="mb-1 flex items-center gap-2">
                                    <img :src="editingTemplate.signatories[i].signature_url" alt="Current signature"
                                         class="h-8 w-auto rounded border border-slate-200 object-contain bg-slate-50">
                                    <span class="text-xs text-slate-500">Current</span>
                                </div>
                                <input type="file" accept="image/*" class="field text-xs" @change="e => sig.signature = e.target.files[0]">
                            </div>
                        </div>
                    </div>
                </template>

                <FormField v-else label="Template file" class-extra="sm:col-span-2" required>
                    <template #default="{ id }">
                        <input :id="id" type="file" accept=".pdf,.png,.jpg,.jpeg"
                               class="field" required
                               @change="e => form.template_file = e.target.files[0]">
                    </template>
                </FormField>

                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.is_active" type="checkbox" class="rounded" :true-value="true" :false-value="false">
                        Active — use this template for this event type + certificate type combination
                    </label>
                    <p class="text-xs text-slate-500 mt-1">
                        Only one template can be active per event type + certificate type. Activating this one automatically deactivates any other.
                    </p>
                </div>
            </FormGrid>
            <FormActions>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? 'Saving…' : (editingId ? 'Update template' : 'Save template') }}
                </button>
                <button v-if="editingId" type="button" class="btn-secondary" :disabled="form.processing" @click="cancelEdit">
                    Cancel edit
                </button>
            </FormActions>
        </form>

        <div class="form-section overflow-hidden !p-0">
            <div class="overflow-x-auto">
                <table class="data-table min-w-[720px]">
                    <thead>
                        <tr>
                            <th>Event type</th>
                            <th>Scope</th>
                            <th>Certificate type</th>
                            <th>Title</th>
                            <th>Background</th>
                            <th>Active</th>
                            <th class="w-40"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in templates" :key="t.id">
                            <td class="capitalize">{{ t.event_type.replace('_', ' ') }}</td>
                            <td class="text-xs text-slate-600">
                                <template v-if="t.event_type === 'fest'">
                                    {{ scopeLabel(t) }}
                                </template>
                                <template v-else>—</template>
                            </td>
                            <td>{{ t.certificate_type }}</td>
                            <td>{{ t.title || '—' }}</td>
                            <td>
                                <img v-if="t.background_url" :src="t.background_url" alt=""
                                     class="h-12 w-auto rounded border border-slate-200 object-contain bg-slate-50">
                                <span v-else class="text-slate-400 text-xs">None</span>
                            </td>
                            <td>{{ t.is_active ? 'Yes' : 'No' }}</td>
                            <td class="text-right space-x-3">
                                <button v-if="t.event_type === 'training' || t.event_type === 'fest'" type="button"
                                        class="text-slate-700 text-xs font-semibold hover:text-slate-900"
                                        @click="editTemplate(t)">
                                    Edit
                                </button>
                                <a v-if="t.event_type === 'training'"
                                   :href="`/sahodaya-admin/${sahodaya.id}/certificate-templates/${t.id}/preview`"
                                   target="_blank" rel="noopener"
                                   class="text-indigo-700 text-xs font-semibold hover:text-indigo-900">
                                    Preview ↗
                                </a>
                                <button type="button" @click="remove(t)" class="text-red-600 text-xs font-semibold hover:text-red-800">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!templates.length">
                            <td colspan="7" class="p-6 text-center text-slate-400">No templates yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    templates: { type: Array, default: () => [] },
    festEvents: { type: Array, default: () => [] },
    defaultBody: { type: String, default: '' },
    defaultTopperBody: { type: String, default: '' },
    defaultFestBody: { type: String, default: '' },
    defaultSignatories: { type: Array, default: () => [] },
    defaultLayout: { type: Object, default: () => ({}) },
    fontFamilyOptions: { type: Array, default: () => [
        'Times New Roman', 'Georgia', 'Arial', 'Helvetica', 'Verdana', 'Courier New', 'Palatino Linotype', 'Garamond',
    ] },
});

const fontFamilies = props.fontFamilyOptions;

const trainingCertificateTypes = [
    'participation',
    'completion',
    'appreciation',
    'resource_person',
    'organizer',
];

const festCertificateTypes = [
    'winner',
    'participation',
    'record_break',
    'volunteer',
    'organizer',
];

const selectedEventItems = computed(() => {
    const event = props.festEvents.find(e => e.id === form.event_id);
    return event?.items || [];
});

function scopeLabel(t) {
    const event = props.festEvents.find(e => e.id === t.event_id);
    if (!event) return 'All events (default)';
    const item = event.items.find(i => i.id === t.item_id);
    return item ? `${event.title} — ${item.title}` : `${event.title} (all items)`;
}

const editingId = ref(null);
const editingTemplate = ref(null);

function isTruthy(value) {
    return value === true || value === 1 || value === '1' || value === 'true';
}

function textFieldDefaults(src = {}, def = {}, fallback = {}) {
    return {
        top: src.top ?? def.top ?? fallback.top ?? 0,
        left: src.left ?? def.left ?? fallback.left ?? 10,
        width: src.width ?? def.width ?? fallback.width ?? 80,
        font_size: src.font_size ?? def.font_size ?? fallback.font_size ?? 13,
        font_family: src.font_family ?? def.font_family ?? fallback.font_family ?? 'Times New Roman',
        font_weight: src.font_weight ?? def.font_weight ?? fallback.font_weight ?? 'normal',
        font_style: src.font_style ?? def.font_style ?? fallback.font_style ?? 'normal',
    };
}

function layoutDefaults(from = null) {
    const d = props.defaultLayout || {};
    const src = from || {};
    return {
        show_recipient_name: src.show_recipient_name ?? d.show_recipient_name ?? false,
        show_participation_label: src.show_participation_label ?? d.show_participation_label ?? true,
        bold_variables: src.bold_variables ?? d.bold_variables ?? true,
        recipient_name: textFieldDefaults(src.recipient_name, d.recipient_name, {
            top: 38, left: 10, width: 80, font_size: 28, font_family: 'Georgia', font_weight: 'bold',
        }),
        body: textFieldDefaults(src.body, d.body, {
            top: 48, left: 12, width: 76, font_size: 13, font_family: 'Times New Roman',
        }),
        certificate_date: {
            ...textFieldDefaults(src.certificate_date, d.certificate_date, {
                top: 72, left: 8, width: 42, font_size: 12, font_family: 'Times New Roman',
            }),
            align: src.certificate_date?.align ?? d.certificate_date?.align ?? 'left',
        },
        participation_label_cover: {
            top: src.participation_label_cover?.top ?? d.participation_label_cover?.top ?? 28,
            height: src.participation_label_cover?.height ?? d.participation_label_cover?.height ?? 7,
        },
    };
}

const form = useForm({
    event_type: 'fest',
    certificate_type: 'participation',
    event_id: null,
    item_id: null,
    title: 'Certificate of Participation',
    body: props.defaultFestBody,
    is_active: true,
    template_file: null,
    logo: null,
    seal: null,
    layout_json: layoutDefaults(),
    signatories: (props.defaultSignatories.length ? props.defaultSignatories : [
        { name: '', designation: 'President', signature: null },
        { name: '', designation: 'General Secretary', signature: null },
        { name: '', designation: 'Finance Secretary', signature: null },
        { name: '', designation: 'Venue Director', signature: null },
    ]).map(s => ({ ...s, signature: null, signature_path: s.signature_path ?? null })),
});

watch(() => form.event_type, (type) => {
    if (editingId.value) return;
    if (type === 'training') {
        if (!trainingCertificateTypes.includes(form.certificate_type)) {
            form.certificate_type = 'participation';
        }
        form.title = 'Certificate of Participation';
        form.body = props.defaultBody;
        form.layout_json = layoutDefaults();
    } else if (type === 'topper') {
        form.certificate_type = 'congratulations';
        form.title = 'Certificate of Congratulations';
        form.body = props.defaultTopperBody || '';
    } else if (type === 'fest') {
        if (!festCertificateTypes.includes(form.certificate_type)) {
            form.certificate_type = 'participation';
        }
        form.title = 'Certificate of Participation';
        form.body = props.defaultFestBody || '';
        form.layout_json = layoutDefaults();
    }
});

function editTemplate(template) {
    editingId.value = template.id;
    editingTemplate.value = template;
    form.event_type = template.event_type || 'training';
    form.certificate_type = template.certificate_type || 'participation';
    form.event_id = template.event_id ?? null;
    form.item_id = template.item_id ?? null;
    form.title = template.title || 'Certificate of Participation';
    form.body = template.body || props.defaultBody;
    form.is_active = template.is_active ?? true;
    form.template_file = null;
    form.logo = null;
    form.seal = null;
    form.layout_json = layoutDefaults(template.layout_json || {});
    const sigs = Array.isArray(template.signatories) && template.signatories.length
        ? template.signatories
        : props.defaultSignatories;
    form.signatories = sigs.map((s) => ({
        name: s.name || '',
        designation: s.designation || '',
        signature: null,
        signature_path: s.signature_path || null,
    }));
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelEdit() {
    editingId.value = null;
    editingTemplate.value = null;
    form.event_type = 'fest';
    form.certificate_type = 'participation';
    form.event_id = null;
    form.item_id = null;
    form.title = 'Certificate of Participation';
    form.body = props.defaultFestBody;
    form.is_active = true;
    form.template_file = null;
    form.logo = null;
    form.seal = null;
    form.layout_json = layoutDefaults();
    form.signatories = (props.defaultSignatories.length ? props.defaultSignatories : [
        { name: '', designation: 'President', signature: null },
        { name: '', designation: 'General Secretary', signature: null },
        { name: '', designation: 'Finance Secretary', signature: null },
        { name: '', designation: 'Venue Director', signature: null },
    ]).map(s => ({ ...s, signature: null, signature_path: null }));
    form.clearErrors();
}

function upload() {
    const options = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('template_file', 'logo', 'seal');
            form.signatories.forEach(s => { s.signature = null; });
            if (editingId.value) {
                cancelEdit();
            }
        },
    };

    if (editingId.value) {
        form.transform((data) => ({
            ...data,
            _method: 'put',
        })).post(`/sahodaya-admin/${props.sahodaya.id}/certificate-templates/${editingId.value}`, options);
        return;
    }

    form.post(`/sahodaya-admin/${props.sahodaya.id}/certificate-templates`, options);
}

function remove(template) {
    if (!confirm(`Delete ${template.certificate_type} template for ${template.event_type}?`)) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/certificate-templates/${template.id}`, {
        preserveScroll: true,
    });
}
</script>
