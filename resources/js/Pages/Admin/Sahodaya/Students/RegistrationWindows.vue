<template>
    <SahodayaAdminLayout title="Student registration windows" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <PageHeader title="Student registration windows" eyebrow="Students"
                    description="Control when schools can add new students or edit existing records.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/student-change-requests`" class="btn-secondary text-sm">
                    Change requests →
                </Link>
            </template>
        </PageHeader>

        <div v-if="emergencyLock" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 mb-6">
            Emergency lock is active — all schools are frozen regardless of window dates.
            <Link :href="`/sahodaya-admin/${sahodaya.id}/membership/settings`" class="underline ml-1">Open lock settings</Link>
        </div>

        <form class="card space-y-6" @submit.prevent="save">
            <p class="text-sm text-slate-600">
                Academic year <strong>{{ academicYear }}</strong>. Outside these windows, schools must submit change requests.
            </p>

            <FormSection title="Add students window"
                         hint="When schools can register new students in their roster.">
                <FormGrid>
                    <FormField label="Opens">
                        <input v-model="form.add_open" type="datetime-local" class="field">
                    </FormField>
                    <FormField label="Closes">
                        <input v-model="form.add_close" type="datetime-local" class="field">
                    </FormField>
                </FormGrid>
            </FormSection>

            <FormSection title="Edit students window"
                         hint="When schools can edit or delete existing student records directly.">
                <FormGrid>
                    <FormField label="Opens">
                        <input v-model="form.edit_open" type="datetime-local" class="field">
                    </FormField>
                    <FormField label="Closes">
                        <input v-model="form.edit_close" type="datetime-local" class="field">
                    </FormField>
                </FormGrid>
            </FormSection>

            <button type="submit" class="btn-primary">Save student windows</button>
        </form>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import FormSection from '@/Components/ui/FormSection.vue';
import FormGrid from '@/Components/ui/FormGrid.vue';
import FormField from '@/Components/ui/FormField.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    academicYear: String,
    window: Object,
    emergencyLock: Boolean,
});

const form = useForm({
    academic_year: props.academicYear,
    add_open: props.window?.add_open_local ?? '',
    add_close: props.window?.add_close_local ?? '',
    edit_open: props.window?.edit_open_local ?? '',
    edit_close: props.window?.edit_close_local ?? '',
});

function save() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/students/registration-windows`);
}
</script>
