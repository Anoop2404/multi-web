<template>
    <SahodayaEventsLayout title="Certificate Templates" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Certificate templates" eyebrow="Tools"
                    description="Configure training and fest certificate layouts, logos, seals, and signatories." />

        <form @submit.prevent="upload" class="card mb-4 space-y-4">
            <h3 class="section-title">{{ form.event_type === 'training' ? 'Training certificate template' : 'Upload template' }}</h3>
            <FormGrid>
                <FormField label="Event type" required>
                    <template #default="{ id }">
                        <select :id="id" v-model="form.event_type" class="field" required>
                            <option value="kalolsavam">Kalotsav</option>
                            <option value="sports">Sports Meet</option>
                            <option value="kids_fest">Kids Fest</option>
                            <option value="teacher_fest">Teacher Fest</option>
                            <option value="training">Training</option>
                        </select>
                    </template>
                </FormField>
                <FormField label="Certificate type" hint="e.g. participation, winner" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="form.certificate_type" class="field" placeholder="participation" required>
                    </template>
                </FormField>

                <template v-if="form.event_type === 'training'">
                    <FormField label="Certificate title" class-extra="sm:col-span-2">
                        <template #default="{ id }">
                            <input :id="id" v-model="form.title" class="field" placeholder="Certificate of Participation">
                        </template>
                    </FormField>
                    <FormField label="Body text" class-extra="sm:col-span-2"
                               hint="Placeholders: {recipient_name}, {designation}, {school_name}, {program_title}, {sahodaya_name}, {venue}, {conducted_on}, {days_attended}">
                        <template #default="{ id }">
                            <textarea :id="id" v-model="form.body" class="field font-mono text-xs" rows="8"></textarea>
                        </template>
                    </FormField>
                    <FormField label="Logo (optional)">
                        <template #default="{ id }">
                            <input :id="id" type="file" accept="image/*" class="field" @change="e => form.logo = e.target.files[0]">
                        </template>
                    </FormField>
                    <FormField label="Seal (optional)">
                        <template #default="{ id }">
                            <input :id="id" type="file" accept="image/*" class="field" @change="e => form.seal = e.target.files[0]">
                        </template>
                    </FormField>
                    <div class="sm:col-span-2 space-y-3">
                        <p class="text-sm font-semibold text-slate-700">Signatories</p>
                        <div v-for="(sig, i) in form.signatories" :key="i" class="grid gap-2 sm:grid-cols-3 border rounded-lg p-3">
                            <input v-model="sig.name" class="field text-sm" placeholder="Name">
                            <input v-model="sig.designation" class="field text-sm" placeholder="Designation">
                            <input type="file" accept="image/*" class="field text-xs" @change="e => sig.signature = e.target.files[0]">
                        </div>
                    </div>
                </template>

                <FormField v-else label="Template file" class-extra="sm:col-span-2" required>
                    <template #default="{ id }">
                        <input :id="id" type="file" accept=".pdf,.png,.jpg,.jpeg"
                               class="field" :required="form.event_type !== 'training'"
                               @change="e => form.template_file = e.target.files[0]">
                    </template>
                </FormField>
            </FormGrid>
            <FormActions>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? 'Saving…' : 'Save template' }}
                </button>
            </FormActions>
        </form>

        <div class="form-section overflow-hidden !p-0">
            <div class="overflow-x-auto">
                <table class="data-table min-w-[640px]">
                    <thead>
                        <tr>
                            <th>Event type</th>
                            <th>Certificate type</th>
                            <th>Title</th>
                            <th>Active</th>
                            <th class="w-24"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in templates" :key="t.id">
                            <td class="capitalize">{{ t.event_type.replace('_', ' ') }}</td>
                            <td>{{ t.certificate_type }}</td>
                            <td>{{ t.title || '—' }}</td>
                            <td>{{ t.is_active ? 'Yes' : 'No' }}</td>
                            <td class="text-right">
                                <button type="button" @click="remove(t)" class="text-red-600 text-xs font-semibold hover:text-red-800">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!templates.length">
                            <td colspan="5" class="p-6 text-center text-slate-400">No templates yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { watch } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    templates: { type: Array, default: () => [] },
    defaultBody: { type: String, default: '' },
    defaultSignatories: { type: Array, default: () => [] },
});

const form = useForm({
    event_type: 'training',
    certificate_type: 'participation',
    title: 'Certificate of Participation',
    body: props.defaultBody,
    template_file: null,
    logo: null,
    seal: null,
    signatories: (props.defaultSignatories.length ? props.defaultSignatories : [
        { name: '', designation: 'President', signature: null },
        { name: '', designation: 'General Secretary', signature: null },
        { name: '', designation: 'Finance Secretary', signature: null },
        { name: '', designation: 'Venue Director', signature: null },
    ]).map(s => ({ ...s, signature: null })),
});

watch(() => form.event_type, (type) => {
    if (type === 'training' && !form.body) {
        form.body = props.defaultBody;
    }
});

function upload() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/certificate-templates`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('template_file', 'logo', 'seal');
            form.signatories.forEach(s => { s.signature = null; });
        },
    });
}

function remove(template) {
    if (!confirm(`Delete ${template.certificate_type} template for ${template.event_type}?`)) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/certificate-templates/${template.id}`, {
        preserveScroll: true,
    });
}
</script>
