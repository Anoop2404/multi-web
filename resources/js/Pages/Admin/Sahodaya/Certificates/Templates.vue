<template>
    <SahodayaEventsLayout title="Certificate Templates" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Certificate templates" eyebrow="Tools"
                    description="Create training templates with a PDF/image background, then choose one on each training program." />

        <!-- 2-Column Certificate Creation Layout (Form on Left, Live Canvas on Right) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-8 items-start">
            <!-- Left Column: Configuration Form (7 Cols) -->
            <form @submit.prevent="upload" class="lg:col-span-7 card space-y-4 shadow-sm border border-slate-200">
                <div class="flex items-center justify-between">
                    <h3 class="section-title">
                        {{ editingId
                            ? 'Edit Certificate Template'
                            : (form.event_type === 'training' || form.event_type === 'topper' ? 'Certificate Template Builder' : 'Upload Fest Template') }}
                    </h3>
                    <span v-if="editingId" class="text-xs font-semibold text-amber-800 bg-amber-100 px-2.5 py-0.5 rounded-full">
                        Editing Template #{{ editingId }}
                    </span>
                </div>

                <FormGrid>
                    <FormField label="Event type" required>
                        <template #default="{ id }">
                            <select :id="id" v-model="form.event_type" class="field" required>
                                <option value="fest">Fest / Event certificate</option>
                                <option value="training">Teacher Training</option>
                                <option value="topper">Topper (Congratulations)</option>
                            </select>
                        </template>
                    </FormField>

                    <FormField label="Certificate type" hint="e.g. participation, congratulations, winner" required>
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
                                <input :id="id" v-model="form.title" class="field font-medium"
                                       :placeholder="form.event_type === 'topper' ? 'Certificate of Congratulations' : 'Certificate of Participation'">
                            </template>
                        </FormField>

                        <FormField
                            v-if="form.event_type === 'training' || form.event_type === 'fest'"
                            label="Background backdrop (PDF or image)"
                            class-extra="sm:col-span-2"
                            :hint="editingId
                                ? 'PDF or image background. Leave blank to keep current background.'
                                : 'PDF or image used as the backdrop design.'"
                        >
                            <template #default="{ id }">
                                <div v-if="editingId && editingTemplate?.background_url" class="mb-2 flex items-center gap-2">
                                    <img :src="editingTemplate.background_url" alt="Current background"
                                         class="h-16 w-auto rounded border border-slate-200 object-contain bg-slate-50">
                                    <span class="text-xs text-slate-500 font-medium">Current background</span>
                                </div>
                                <input :id="id" type="file" accept=".pdf,.png,.jpg,.jpeg"
                                       class="field"
                                       @change="onFileChange">
                            </template>
                        </FormField>

                        <template v-if="form.event_type === 'training' || form.event_type === 'fest'">
                            <div class="sm:col-span-2 rounded-xl border border-slate-200 bg-slate-50/80 p-4 space-y-3">
                                <p class="text-xs font-bold text-slate-800 uppercase tracking-wider">Background Layout Options</p>
                                <label class="flex items-center gap-2 text-sm text-slate-700 font-medium">
                                    <input v-model="form.layout_json.show_recipient_name" type="checkbox" class="rounded" :true-value="true" :false-value="false">
                                    Show center recipient name
                                </label>
                                <label class="flex items-center gap-2 text-sm text-slate-700 font-medium">
                                    <input v-model="form.layout_json.show_participation_label" type="checkbox" class="rounded" :true-value="true" :false-value="false">
                                    Show “OF PARTICIPATION” on background
                                </label>
                                <label class="flex items-center gap-2 text-sm text-slate-700 font-medium">
                                    <input v-model="form.layout_json.bold_variables" type="checkbox" class="rounded" :true-value="true" :false-value="false">
                                    Bold placeholder values in body text
                                </label>
                            </div>

                            <FormField label="Recipient name — top %" hint="Vertical position on canvas (0–100)">
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
                            <FormField label="Date — left %" hint="Side position">
                                <template #default="{ id }">
                                    <input :id="id" v-model.number="form.layout_json.certificate_date.left" type="number" min="0" max="100" class="field">
                                </template>
                            </FormField>
                        </template>

                        <FormField label="Body text" class-extra="sm:col-span-2">
                            <template #default="{ id }">
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between text-xs text-slate-500">
                                        <span>Click variable to insert into text:</span>
                                    </div>
                                    <div class="flex flex-wrap gap-1.5 p-2 bg-slate-50 border border-slate-200 rounded-lg">
                                        <button v-for="token in placeholderTokens" :key="token" type="button"
                                                class="text-[11px] font-mono font-semibold px-2 py-0.5 rounded bg-white hover:bg-indigo-50 hover:text-indigo-700 border border-slate-200 text-slate-700 transition"
                                                @click="insertPlaceholder(token)">
                                            {{ token }}
                                        </button>
                                    </div>
                                    <textarea :id="id" v-model="form.body" class="field font-mono text-xs" rows="6"></textarea>
                                </div>
                            </template>
                        </FormField>
                    </template>

                    <div class="sm:col-span-2">
                        <label class="flex items-center gap-2 text-sm text-slate-700 font-semibold">
                            <input v-model="form.is_active" type="checkbox" class="rounded" :true-value="true" :false-value="false">
                            Active — use this template for this event type + certificate type combination
                        </label>
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

            <!-- Right Column: Live Interactive Preview Canvas (5 Cols, Sticky) -->
            <div class="lg:col-span-5">
                <div class="sticky top-6 space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold text-slate-800 flex items-center gap-1.5">
                            <span>👁️</span> Live Visual Preview
                        </h3>
                        <span class="text-xs text-emerald-700 font-semibold bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">
                            Updates Before Save
                        </span>
                    </div>

                    <CertificateLiveCanvas
                        :background-url="editingTemplate?.background_url"
                        :local-file-url="localFilePreviewUrl"
                        :layout="form.layout_json"
                        :body-text="form.body"
                        :event-type="form.event_type"
                        :title="form.title"
                    />

                    <div class="p-3 bg-slate-50 border border-slate-200/90 rounded-xl space-y-1.5 text-xs text-slate-600">
                        <p class="font-bold text-slate-800">💡 Live Preview Instructions</p>
                        <p>Adjust <strong>Top %</strong>, <strong>Font Size</strong>, and <strong>Body text</strong> in the form to position placeholders precisely over your backdrop.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Saved Templates Register Table -->
        <div class="card overflow-hidden !p-0 border border-slate-200 shadow-xs rounded-xl">
            <div class="p-4 border-b border-slate-200 bg-slate-50/80 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Saved Certificate Templates</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Persisted templates configured for Sahodaya events, training, and topper awards.</p>
                </div>

                <!-- Category Filter Tabs -->
                <div class="flex items-center gap-1 bg-white border border-slate-200 p-1 rounded-lg text-xs font-semibold">
                    <button type="button" class="px-3 py-1 rounded-md transition"
                            :class="activeCategoryTab === 'all' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'"
                            @click="activeCategoryTab = 'all'">
                        All ({{ templates.length }})
                    </button>
                    <button type="button" class="px-3 py-1 rounded-md transition"
                            :class="activeCategoryTab === 'fest' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'"
                            @click="activeCategoryTab = 'fest'">
                        Fest &amp; Sports
                    </button>
                    <button type="button" class="px-3 py-1 rounded-md transition"
                            :class="activeCategoryTab === 'training' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'"
                            @click="activeCategoryTab = 'training'">
                        Training
                    </button>
                    <button type="button" class="px-3 py-1 rounded-md transition"
                            :class="activeCategoryTab === 'topper' ? 'bg-[#0f3d7a] text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'"
                            @click="activeCategoryTab = 'topper'">
                        Topper
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-100/70 text-xs font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 text-left">Event Type</th>
                            <th class="px-4 py-3 text-left">Scope</th>
                            <th class="px-4 py-3 text-left">Certificate Type</th>
                            <th class="px-4 py-3 text-left">Title</th>
                            <th class="px-4 py-3 text-center">Backdrop</th>
                            <th class="px-4 py-3 text-center">Active</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="t in filteredTemplates" :key="t.id" class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-4 py-3 font-bold text-slate-900 capitalize">{{ t.event_type.replace('_', ' ') }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                <template v-if="t.event_type === 'fest'">
                                    {{ scopeLabel(t) }}
                                </template>
                                <template v-else>All programs</template>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-800 border border-slate-200">
                                    {{ t.certificate_type }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-semibold text-slate-800">{{ t.title || '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                <img v-if="t.background_url" :src="t.background_url" alt="Backdrop"
                                     class="h-10 w-auto mx-auto rounded border border-slate-200 object-contain bg-slate-50">
                                <span v-else class="text-slate-400 text-xs">—</span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="text-xs font-bold px-2 py-0.5 rounded-full"
                                      :class="t.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'">
                                    {{ t.is_active ? 'Active ✓' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <button type="button"
                                        class="btn-secondary text-xs !py-1 !px-2.5"
                                        @click="editTemplate(t)">
                                    Edit
                                </button>
                                <a :href="`/sahodaya-admin/${sahodaya.id}/certificate-templates/${t.id}/preview`"
                                   target="_blank" rel="noopener"
                                   class="btn-secondary text-xs !py-1 !px-2.5 font-semibold text-indigo-700 border-indigo-200 hover:bg-indigo-50">
                                    Preview ↗
                                </a>
                                <button type="button" @click="remove(t)" class="btn-secondary text-xs !py-1 !px-2.5 text-red-700 border-red-200 hover:bg-red-50">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!filteredTemplates.length">
                            <td colspan="7" class="p-8 text-center text-slate-400">
                                No certificate templates match your selected category filter.
                            </td>
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
import CertificateLiveCanvas from '@/Components/certificates/CertificateLiveCanvas.vue';

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

const activeCategoryTab = ref('all');
const localFilePreviewUrl = ref(null);

const filteredTemplates = computed(() => {
    if (activeCategoryTab.value === 'all') return props.templates;
    return props.templates.filter(t => t.event_type === activeCategoryTab.value);
});

const placeholderTokens = computed(() => {
    if (form.event_type === 'topper') {
        return ['{recipient_name}', '{school_name}', '{sahodaya_name}', '{academic_year}', '{class}', '{percentage}', '{rank}'];
    }
    if (form.event_type === 'fest') {
        return ['{recipient_name}', '{school_name}', '{event_title}', '{item_title}', '{event_dates}', '{achievement_line}', '{sahodaya_name}', '{certificate_date}'];
    }
    return ['{salutation}', '{recipient_name}', '{designation}', '{school_name}', '{program_title}', '{sahodaya_name}', '{venue}', '{conducted_on}', '{certificate_date}'];
});

function insertPlaceholder(token) {
    form.body = (form.body || '') + token;
}

function onFileChange(e) {
    const file = e.target.files[0] ?? null;
    form.template_file = file;
    if (file) {
        if (file.type.startsWith('image/')) {
            localFilePreviewUrl.value = URL.createObjectURL(file);
        } else {
            localFilePreviewUrl.value = null;
        }
    } else {
        localFilePreviewUrl.value = null;
    }
}

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
    localFilePreviewUrl.value = null;
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
