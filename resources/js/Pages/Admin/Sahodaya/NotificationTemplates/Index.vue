<template>
    <SahodayaAdminLayout title="Notification templates" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount"
                         :show-header-title="false">
        <PageHeader title="Notification templates" eyebrow="Communications"
                    description="Customize notification content sent to schools and portal users. Insert a variable, preview it with sample data, then send yourself a test before relying on it." />

        <div class="space-y-4">
            <form v-for="t in templates" :key="t.id" @submit.prevent="save(t)" class="card space-y-3">
                <div class="flex justify-between items-start gap-3">
                    <div>
                        <p class="font-semibold text-sm">{{ t.slug }}</p>
                        <p class="text-xs text-gray-500">Channels: {{ (t.channels_json || []).join(', ') || 'default' }}</p>
                    </div>
                    <label class="text-xs flex items-center gap-2">
                        <input type="checkbox" v-model="forms[t.id].is_active">
                        Active
                    </label>
                </div>

                <div v-if="t.available_variables?.length" class="flex flex-wrap items-center gap-1.5">
                    <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mr-1">Insert:</span>
                    <button v-for="v in t.available_variables" :key="v" type="button"
                            class="font-mono text-[11px] px-1.5 py-0.5 rounded bg-indigo-50 border border-indigo-100 text-indigo-700 hover:bg-indigo-100"
                            @click="insertVariable(t, v)">
                        {{ placeholderText(v) }}
                    </button>
                </div>

                <FormField label="Title">
                    <template #default="{ id }">
                        <input :id="id" v-model="forms[t.id].title" class="field" required>
                    </template>
                </FormField>
                <FormField label="Body template">
                    <template #default="{ id }">
                        <textarea :ref="el => setBodyRef(t.id, el)" :id="id" v-model="forms[t.id].body_template"
                                  class="field" rows="4" required></textarea>
                    </template>
                </FormField>

                <div class="rounded-lg border border-slate-200 bg-slate-50/80 p-3">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400 mb-1">Preview (sample data)</p>
                    <p class="text-sm font-semibold text-slate-800">{{ preview(t, forms[t.id].title) }}</p>
                    <p class="text-xs text-slate-600 mt-1 whitespace-pre-line">{{ preview(t, forms[t.id].body_template) }}</p>
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="btn-primary text-sm" :disabled="forms[t.id].processing">Save template</button>
                    <button type="button" class="btn-secondary text-sm" :disabled="testForms[t.id]?.processing"
                            @click="sendTest(t)">
                        {{ testForms[t.id]?.processing ? 'Sending…' : 'Send test to my email' }}
                    </button>
                </div>
            </form>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import FormField from '@/Components/ui/FormField.vue';
import { reactive, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingSchoolsCount: Number,
    pendingSubmissionsCount: Number,
    pendingPaymentsCount: Number,
    templates: { type: Array, default: () => [] },
});

const forms = reactive({});
const testForms = reactive({});
const bodyRefs = reactive({});

watch(() => props.templates, (rows) => {
    rows.forEach((t) => {
        forms[t.id] = useForm({
            title: t.title,
            body_template: t.body_template,
            is_active: t.is_active,
            channels_json: t.channels_json ?? [],
        });
        testForms[t.id] = useForm({
            title: t.title,
            body_template: t.body_template,
        });
    });
}, { immediate: true });

function setBodyRef(id, el) {
    if (el) bodyRefs[id] = el;
}

// Kept out of the template as a literal string build — a raw "{{" inside a
// Vue mustache interpolation confuses the template compiler's tokenizer
// (it starts looking for the interpolation's own closing "}}" from there),
// causing an "Unterminated string constant" build error.
function placeholderText(variable) {
    return '{{' + variable + '}}';
}

function insertVariable(t, variable) {
    const token = `{{${variable}}}`;
    const el = bodyRefs[t.id];
    const form = forms[t.id];
    if (el && typeof el.selectionStart === 'number') {
        const start = el.selectionStart;
        const end = el.selectionEnd;
        form.body_template = form.body_template.slice(0, start) + token + form.body_template.slice(end);
        // restore focus/cursor after Vue re-renders the value
        requestAnimationFrame(() => {
            el.focus();
            el.selectionStart = el.selectionEnd = start + token.length;
        });
    } else {
        form.body_template += token;
    }
}

function preview(t, text) {
    let out = text || '';
    (t.available_variables || []).forEach((v) => {
        out = out.split(`{{${v}}}`).join(sampleFor(v));
    });
    return out;
}

// Mirrors App\Support\NotificationTemplateVariables::sampleValue() — kept in
// sync manually since this is just for the live client-side preview; the
// authoritative sample values used for the actual test email are computed
// server-side in NotificationTemplateController::sendTest().
const SAMPLES = {
    event_title: 'Sample Sports Meet 2026', program_title: 'Sample Sports Meet 2026',
    exam_title: 'Sample Talent Search Exam', item_title: '100m Sprint',
    competition_label: 'Sahodaya round', close_date: '31 Jul 2026', start_date: '31 Jul 2026',
    days_left: '3', amount: '500.00', venue: 'Sample Auditorium', count: '5',
    school_name: 'Sample School', student_name: 'Sample Student', teacher_name: 'Sample Teacher',
    participant_name: 'Sample Participant', reason: 'Sample reason text', academic_year: '2025-26',
    circular_title: 'Sample Circular', login_url: 'https://example.org/portal/login',
    login_email: 'sample@example.org', hall_ticket_no: 'HT-0001', requested_by: 'Sample Teacher',
    requested_status: 'present', scheduled_at: '31 Jul 2026, 10:00 AM', session_title: 'Session 1',
    status: 'confirmed', from_title: 'School round', new_value: '11.2', record_unit: 'seconds',
    prize_label: 'Gold', title: 'Sample Remittance', context_label: 'Sample context',
    class: '10', examination_type: 'SSLC', pass_percent: '95',
};

function sampleFor(variable) {
    return SAMPLES[variable] ?? 'Sample value';
}

function save(t) {
    forms[t.id].put(`/sahodaya-admin/${props.sahodaya.id}/notification-templates/${t.id}`, { preserveScroll: true });
}

function sendTest(t) {
    // Send whatever's currently in the (possibly unsaved) form fields, not
    // necessarily what's persisted — so admins can preview edits before saving.
    testForms[t.id].title = forms[t.id].title;
    testForms[t.id].body_template = forms[t.id].body_template;
    testForms[t.id].post(`/sahodaya-admin/${props.sahodaya.id}/notification-templates/${t.id}/test`, {
        preserveScroll: true,
    });
}
</script>
