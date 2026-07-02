<template>
    <SahodayaAdminLayout title="Notification templates" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount"
                         :show-header-title="false">
        <PageHeader title="Notification templates" eyebrow="Communications"
                    description="Customize notification content sent to schools and portal users." />

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
                <FormField label="Title">
                    <template #default="{ id }">
                        <input :id="id" v-model="forms[t.id].title" class="field" required>
                    </template>
                </FormField>
                <FormField label="Body template">
                    <template #default="{ id }">
                        <textarea :id="id" v-model="forms[t.id].body_template" class="field" rows="4" required></textarea>
                    </template>
                </FormField>
                <button type="submit" class="btn-primary text-sm" :disabled="forms[t.id].processing">Save template</button>
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

watch(() => props.templates, (rows) => {
    rows.forEach((t) => {
        forms[t.id] = useForm({
            title: t.title,
            body_template: t.body_template,
            is_active: t.is_active,
            channels_json: t.channels_json ?? [],
        });
    });
}, { immediate: true });

function save(t) {
    forms[t.id].put(`/sahodaya-admin/${props.sahodaya.id}/notification-templates/${t.id}`, { preserveScroll: true });
}
</script>
