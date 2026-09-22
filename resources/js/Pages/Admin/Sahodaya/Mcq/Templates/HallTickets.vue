<template>
    <SahodayaAdminLayout title="MCQ Hall Ticket Builder" :sahodaya="sahodaya" :show-header-title="false">
        <PageHeader
            title="Dynamic Hall Ticket Builder"
            eyebrow="Talent Search & MCQ Exam Templates"
            description="Configure per-class admit card designs, test paper specifications, OMR instructions, and branding."
        >
            <template #actions>
                <button type="button" class="btn-secondary text-xs sm:text-sm" @click="resetToDefault">
                    Restore Official Defaults
                </button>
                <button type="button" class="btn-primary text-xs sm:text-sm" @click="startNewTemplate">
                    + New Template
                </button>
            </template>
        </PageHeader>

        <!-- Template Selector Tabs -->
        <div class="mb-6 flex flex-wrap items-center gap-2 border-b border-slate-200 pb-3">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 mr-2">Templates:</span>
            <button
                v-for="t in templates"
                :key="t.id"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                :class="selectedTemplate?.id === t.id
                    ? 'bg-blue-900 text-white shadow-sm'
                    : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'"
                @click="selectTemplate(t)"
            >
                <span>{{ t.title }}</span>
                <span v-if="t.is_default" class="rounded px-1.5 py-0.2 text-[9px] uppercase font-bold"
                      :class="selectedTemplate?.id === t.id ? 'bg-blue-800 text-sky-200' : 'bg-emerald-100 text-emerald-800'">
                    Default
                </span>
            </button>
        </div>

        <div class="grid xl:grid-cols-12 gap-6 items-start">
            <!-- Left 6 Columns: The Dynamic Builder Controls -->
            <div class="xl:col-span-6 space-y-4">
                <form @submit.prevent="saveTemplate" class="card space-y-4 shadow-sm border border-slate-200">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">
                                {{ editingId ? 'Edit Template: ' + form.title : 'Create New Template' }}
                            </h2>
                            <p class="text-xs text-slate-500">Live preview updates automatically on the right.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                v-if="editingId && !form.is_default"
                                type="button"
                                class="text-xs text-emerald-700 hover:underline font-semibold"
                                @click="makeDefault(editingId)"
                            >
                                Set as Default
                            </button>
                            <button
                                v-if="editingId && templates.length > 1"
                                type="button"
                                class="text-xs text-red-600 hover:underline"
                                @click="deleteTemplate(editingId)"
                            >
                                Delete
                            </button>
                        </div>
                    </div>

                    <!-- Builder Category Navigation Tabs -->
                    <div class="flex flex-wrap gap-1 bg-slate-100 p-1 rounded-lg text-xs font-semibold">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            type="button"
                            class="flex-1 py-1.5 px-2 rounded-md transition text-center min-w-[90px]"
                            :class="activeTab === tab.id ? 'bg-white text-blue-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                            @click="activeTab = tab.id"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <!-- TAB 1: BRANDING & HEADERS -->
                    <div v-show="activeTab === 'branding'" class="space-y-3 pt-1">
                        <FormField label="Template Title *" hint="Internal name used when assigning to exams.">
                            <input v-model="form.title" class="field" placeholder="e.g. Official Sahodaya MCQ Hall Ticket" required>
                        </FormField>

                        <div class="flex items-center gap-2 pt-1">
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer">
                                <input v-model="form.is_default" type="checkbox" class="rounded border-slate-300 text-blue-900 focus:ring-blue-900">
                                Set as default template for all new MCQ exams
                            </label>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-3 pt-2">
                            <FormField label="Primary Color" hint="Borders & title ribbons">
                                <div class="flex items-center gap-2">
                                    <input v-model="design.primary_color" type="color" class="h-9 w-12 p-0.5 rounded border border-slate-300 cursor-pointer">
                                    <input v-model="design.primary_color" class="field uppercase text-xs font-mono" maxlength="7">
                                </div>
                            </FormField>

                            <FormField label="Accent Color" hint="Roll numbers & alerts">
                                <div class="flex items-center gap-2">
                                    <input v-model="design.accent_color" type="color" class="h-9 w-12 p-0.5 rounded border border-slate-300 cursor-pointer">
                                    <input v-model="design.accent_color" class="field uppercase text-xs font-mono" maxlength="7">
                                </div>
                            </FormField>
                        </div>

                        <FormField label="Organization / Board Name">
                            <input v-model="design.organization_name" class="field" placeholder="SAHODAYA SCHOOLS COMPLEX">
                        </FormField>

                        <FormField label="Sub-Heading">
                            <input v-model="design.sub_heading" class="field" placeholder="CBSE Affiliated Schools Academic Council">
                        </FormField>

                        <FormField label="Sub-Title / Tagline">
                            <input v-model="design.sub_title" class="field" placeholder="Annual Inter-School MCQ Aptitude & Assessment Examination">
                        </FormField>

                        <div class="grid sm:grid-cols-2 gap-3">
                            <FormField label="Title Bar Left Heading">
                                <input v-model="design.title_left" class="field" placeholder="Hall Ticket / Admit Card">
                            </FormField>

                            <FormField label="Layout Mode">
                                <select v-model="design.layout" class="field">
                                    <option value="standard">Standard (Full Page A4)</option>
                                    <option value="compact">Compact (2-up Print)</option>
                                </select>
                            </FormField>
                        </div>
                    </div>

                    <!-- TAB 2: CANDIDATE PARTICULARS & SECURITY -->
                    <div v-show="activeTab === 'candidate'" class="space-y-3 pt-1">
                        <p class="text-xs text-slate-500 font-medium">Select identity fields and biometric security cues to display on the admit card:</p>

                        <div class="grid sm:grid-cols-2 gap-2.5 bg-slate-50 p-3 rounded-lg border border-slate-200">
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer">
                                <input v-model="design.show_roll_no" type="checkbox" class="rounded text-blue-900 focus:ring-blue-900">
                                Roll / Hall Ticket Number
                            </label>
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer">
                                <input v-model="design.show_reg_no" type="checkbox" class="rounded text-blue-900 focus:ring-blue-900">
                                School Admission / Reg. No.
                            </label>
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer">
                                <input v-model="design.show_school" type="checkbox" class="rounded text-blue-900 focus:ring-blue-900">
                                School Name
                            </label>
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer">
                                <input v-model="design.show_photo" type="checkbox" class="rounded text-blue-900 focus:ring-blue-900">
                                Candidate Passport Photo
                            </label>
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer">
                                <input v-model="design.show_qr" type="checkbox" class="rounded text-blue-900 focus:ring-blue-900">
                                Verification QR Code (Mobile Gate Scan)
                            </label>
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer">
                                <input v-model="design.show_audit_bar" type="checkbox" class="rounded text-blue-900 focus:ring-blue-900">
                                Audit Strip (Download Timestamp & IP)
                            </label>
                        </div>
                    </div>

                    <!-- TAB 3: CLASS EXAM SCHEDULE & VENUE -->
                    <div v-show="activeTab === 'schedule'" class="space-y-3 pt-1">
                        <div class="rounded-md bg-blue-50 p-2.5 text-xs text-blue-900 font-medium">
                            <strong>Per-Class Structure:</strong> MCQ exams are conducted as single composite assessment papers per class, not as multi-subject schedules across weeks.
                        </div>

                        <div class="flex items-center gap-2">
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer">
                                <input v-model="design.show_exam_schedule" type="checkbox" class="rounded text-blue-900 focus:ring-blue-900">
                                Show MCQ Examination Schedule & Paper Specifications Table
                            </label>
                        </div>

                        <div class="flex items-center gap-2">
                            <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer">
                                <input v-model="design.show_center_box" type="checkbox" class="rounded text-blue-900 focus:ring-blue-900">
                                Show Exam Center Code, Address & Hall/Seat Box
                            </label>
                        </div>

                        <FormField label="Assessment Mode Label">
                            <input v-model="design.exam_mode_label" class="field" placeholder="OMR Answer Sheet (Pen & Paper)">
                        </FormField>

                        <div class="grid sm:grid-cols-2 gap-3">
                            <FormField label="Reporting Lead Time" hint="Minutes prior to start">
                                <div class="flex items-center gap-2">
                                    <input v-model.number="design.report_before_minutes" type="number" min="0" max="240" class="field">
                                    <span class="text-xs text-slate-500 font-semibold">mins</span>
                                </div>
                            </FormField>

                            <FormField label="Gate Closure Grace" hint="Minutes after start (0 = strict)">
                                <div class="flex items-center gap-2">
                                    <input v-model.number="design.gate_closure_after_minutes" type="number" min="0" max="240" class="field">
                                    <span class="text-xs text-slate-500 font-semibold">mins</span>
                                </div>
                            </FormField>
                        </div>
                    </div>

                    <!-- TAB 4: MCQ / OMR INSTRUCTIONS -->
                    <div v-show="activeTab === 'instructions'" class="space-y-3 pt-1">
                        <div class="flex items-center justify-between">
                            <FormField label="Instructions Section Heading" class="flex-1 mr-2">
                                <input v-model="design.instructions_title" class="field text-xs font-bold">
                            </FormField>
                            <button type="button" class="text-xs text-blue-800 hover:underline font-semibold shrink-0 mt-5" @click="restoreDefaultInstructions">
                                Reset Rules
                            </button>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-xs font-semibold text-slate-700">Exam Rules & Regulations ({{ design.instructions.length }} rules):</label>
                            <div
                                v-for="(inst, idx) in design.instructions"
                                :key="idx"
                                class="flex items-start gap-2 bg-slate-50 p-2 rounded border border-slate-200"
                            >
                                <span class="text-xs font-bold text-slate-400 mt-1.5">{{ idx + 1 }}.</span>
                                <textarea
                                    v-model="design.instructions[idx]"
                                    rows="2"
                                    class="field text-xs flex-1 !p-1.5"
                                    placeholder="Enter examination instruction..."
                                ></textarea>
                                <button
                                    type="button"
                                    class="text-red-500 hover:text-red-700 p-1 text-xs font-bold shrink-0 mt-1"
                                    title="Remove rule"
                                    @click="removeInstruction(idx)"
                                >
                                    ✕
                                </button>
                            </div>

                            <button
                                type="button"
                                class="btn-secondary text-xs w-full py-1.5 mt-1 border-dashed"
                                @click="addInstruction"
                            >
                                + Add Instruction Rule
                            </button>
                        </div>
                    </div>

                    <!-- TAB 5: SIGNATURES & LEGAL -->
                    <div v-show="activeTab === 'signatures'" class="space-y-3 pt-1">
                        <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer">
                            <input v-model="design.show_signature" type="checkbox" class="rounded text-blue-900 focus:ring-blue-900">
                            Enable Signature Blocks
                        </label>

                        <div v-if="design.show_signature" class="grid sm:grid-cols-3 gap-2 bg-slate-50 p-2.5 rounded border border-slate-200">
                            <label class="flex items-center gap-1.5 text-xs font-medium text-slate-700 cursor-pointer">
                                <input v-model="design.show_candidate_sig" type="checkbox" class="rounded text-blue-900">
                                Candidate Signature
                            </label>
                            <label class="flex items-center gap-1.5 text-xs font-medium text-slate-700 cursor-pointer">
                                <input v-model="design.show_invigilator_sig" type="checkbox" class="rounded text-blue-900">
                                Invigilator Signature
                            </label>
                            <label class="flex items-center gap-1.5 text-xs font-medium text-slate-700 cursor-pointer">
                                <input v-model="design.show_controller_sig" type="checkbox" class="rounded text-blue-900">
                                Controller Signature
                            </label>
                        </div>

                        <FormField label="Official Signatory Title">
                            <input v-model="design.signatory_title" class="field" placeholder="Controller of Examinations">
                        </FormField>

                        <FormField label="Footer Note (Optional)">
                            <input v-model="design.footer_note" class="field" placeholder="Optional brief note shown above disclaimer">
                        </FormField>

                        <FormField label="Legal Disclaimer">
                            <textarea v-model="design.disclaimer" rows="2" class="field text-xs"></textarea>
                        </FormField>
                    </div>

                    <FormActions class="pt-3 border-t border-slate-100 flex items-center justify-between">
                        <button type="submit" class="btn-primary text-sm px-6" :disabled="saving">
                            {{ saving ? 'Saving Template...' : (editingId ? 'Update Template' : 'Save as New Template') }}
                        </button>
                        <span v-if="savedNotice" class="text-xs font-semibold text-emerald-600">✓ Template Saved!</span>
                    </FormActions>
                </form>
            </div>

            <!-- Right 6 Columns: Sticky Live Interactive Preview -->
            <div class="xl:col-span-6 sticky top-4">
                <div class="card p-3 sm:p-4 bg-slate-100 border border-slate-200 shadow-sm space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-800 uppercase tracking-wider">
                                Live Admit Card Preview
                            </h3>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider bg-blue-100 text-blue-800 px-2 py-0.5 rounded">
                            Per-Class MCQ Admit Card
                        </span>
                    </div>

                    <div class="overflow-x-auto bg-white p-2 rounded shadow-inner border border-slate-300">
                        <McqHallTicketPreview
                            :design="design"
                            :sample="samplePreviewData"
                            :logo-url="logoUrl"
                        />
                    </div>

                    <p class="text-[11px] text-slate-500 text-center italic">
                        Real-time simulation. Reflects color palettes, field toggles, reporting times, instructions, and signature lines.
                    </p>
                </div>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import FormField from '@/Components/ui/FormField.vue';
import FormActions from '@/Components/ui/FormActions.vue';
import McqHallTicketPreview from '@/Components/sahodaya/McqHallTicketPreview.vue';

const props = defineProps({
    sahodaya: { type: Object, required: true },
    templates: { type: Array, default: () => [] },
    defaultDesign: { type: Object, default: () => ({}) },
    samplePreview: { type: Object, default: () => ({}) },
    logoUrl: { type: String, default: null },
});

const tabs = [
    { id: 'branding', label: '1. Branding & Colors' },
    { id: 'candidate', label: '2. Candidate Info' },
    { id: 'schedule', label: '3. Exam Schedule' },
    { id: 'instructions', label: '4. MCQ Rules' },
    { id: 'signatures', label: '5. Signatures' },
];

const activeTab = ref('branding');
const saving = ref(false);
const savedNotice = ref(false);

const initialTemplate = props.templates.find(t => t.is_default) || props.templates[0] || null;
const selectedTemplate = ref(initialTemplate);
const editingId = ref(initialTemplate?.id || null);

const form = reactive({
    title: initialTemplate?.title || 'Official CBSE / Sahodaya MCQ Hall Ticket',
    is_default: initialTemplate?.is_default || false,
});

const design = reactive({
    ...props.defaultDesign,
    ...(initialTemplate?.design_json || {}),
    instructions: Array.isArray(initialTemplate?.design_json?.instructions)
        ? [...initialTemplate.design_json.instructions]
        : [...(props.defaultDesign.instructions || [])],
});

const samplePreviewData = computed(() => {
    return {
        ...props.samplePreview,
        exam_title: props.samplePreview.exam_title || 'Sahodaya Talent Search Examination',
        paper_name: props.samplePreview.paper_name || 'Class 10 MCQ Aptitude & Composite Assessment',
        class_name: props.samplePreview.class_name || 'Class 10',
    };
});

function selectTemplate(t) {
    selectedTemplate.value = t;
    editingId.value = t.id;
    form.title = t.title;
    form.is_default = Boolean(t.is_default);

    const merged = { ...props.defaultDesign, ...(t.design_json || {}) };
    Object.assign(design, merged);
    if (Array.isArray(t.design_json?.instructions)) {
        design.instructions = [...t.design_json.instructions];
    } else {
        design.instructions = [...(props.defaultDesign.instructions || [])];
    }
}

function startNewTemplate() {
    selectedTemplate.value = null;
    editingId.value = null;
    form.title = 'New MCQ Hall Ticket Template';
    form.is_default = false;
    Object.assign(design, props.defaultDesign);
    design.instructions = [...(props.defaultDesign.instructions || [])];
    activeTab.value = 'branding';
}

function resetToDefault() {
    Object.assign(design, props.defaultDesign);
    design.instructions = [...(props.defaultDesign.instructions || [])];
}

function restoreDefaultInstructions() {
    design.instructions = [...(props.defaultDesign.instructions || [])];
}

function addInstruction() {
    design.instructions.push('All candidates must strictly adhere to the examination rules specified by the Board.');
}

function removeInstruction(idx) {
    design.instructions.splice(idx, 1);
}

function saveTemplate() {
    saving.value = true;
    savedNotice.value = false;

    const payload = {
        title: form.title,
        is_default: form.is_default,
        design_json: { ...design },
    };

    const url = editingId.value
        ? `/sahodaya-admin/${props.sahodaya.id}/mcq/templates/hall-tickets/${editingId.value}`
        : `/sahodaya-admin/${props.sahodaya.id}/mcq/templates/hall-tickets`;

    const method = editingId.value ? 'put' : 'post';

    router[method](url, payload, {
        preserveScroll: true,
        onSuccess: () => {
            saving.value = false;
            savedNotice.value = true;
            setTimeout(() => { savedNotice.value = false; }, 3000);
        },
        onError: () => {
            saving.value = false;
        },
    });
}

function makeDefault(templateId) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq/templates/hall-tickets/${templateId}/default`, {}, {
        preserveScroll: true,
    });
}

function deleteTemplate(templateId) {
    if (!confirm('Are you sure you want to delete this template?')) return;

    router.delete(`/sahodaya-admin/${props.sahodaya.id}/mcq/templates/hall-tickets/${templateId}`, {
        preserveScroll: true,
    });
}
</script>

