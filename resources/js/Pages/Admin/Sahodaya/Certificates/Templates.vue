<template>
    <SahodayaEventsLayout title="Certificate Templates" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Certificate templates" eyebrow="Tools"
                    description="Upload PDF or image templates used when generating fest and training certificates." />

        <form @submit.prevent="upload" class="card mb-4 space-y-4">
            <h3 class="section-title">Upload template</h3>
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
                <FormField label="Certificate type" hint="e.g. winner, participation, merit" required>
                    <template #default="{ id }">
                        <input :id="id" v-model="form.certificate_type" class="field" placeholder="winner" required>
                    </template>
                </FormField>
                <FormField label="Template file" class-extra="sm:col-span-2" required>
                    <template #default="{ id }">
                        <input :id="id" type="file" accept=".pdf,.png,.jpg,.jpeg"
                               class="field" required
                               @change="e => form.template_file = e.target.files[0]">
                    </template>
                </FormField>
            </FormGrid>
            <FormActions>
                <button type="submit" class="btn-primary" :disabled="form.processing || !form.template_file">
                    {{ form.processing ? 'Uploading…' : 'Upload template' }}
                </button>
            </FormActions>
        </form>

        <div class="form-section overflow-hidden !p-0">
            <div class="overflow-x-auto">
                <table class="data-table min-w-[480px]">
                    <thead>
                        <tr>
                            <th>Event type</th>
                            <th>Certificate type</th>
                            <th class="w-24"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in templates" :key="t.id">
                            <td class="capitalize">{{ t.event_type.replace('_', ' ') }}</td>
                            <td>{{ t.certificate_type }}</td>
                            <td class="text-right">
                                <button type="button" @click="remove(t)" class="text-red-600 text-xs font-semibold hover:text-red-800">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!templates.length">
                            <td colspan="3" class="p-6 text-center text-slate-400">No templates uploaded yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { useForm, router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    templates: { type: Array, default: () => [] },
});

const form = useForm({
    event_type: 'kalolsavam',
    certificate_type: '',
    template_file: null,
});

function upload() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/certificate-templates`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset('certificate_type', 'template_file'),
    });
}

function remove(template) {
    if (!confirm(`Delete ${template.certificate_type} template for ${template.event_type}?`)) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/certificate-templates/${template.id}`, {
        preserveScroll: true,
    });
}
</script>
