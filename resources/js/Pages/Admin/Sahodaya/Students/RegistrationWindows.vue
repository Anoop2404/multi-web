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
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 pb-4">
                <div>
                    <label class="form-label mb-1 font-bold text-xs text-slate-700">Academic Year Window</label>
                    <select v-model="selectedYear" @change="onYearChange" class="field text-sm font-bold bg-white w-56">
                        <option v-for="ay in academicYearOptions" :key="ay.id" :value="ay.label">
                            {{ ay.label }}{{ ay.status === 'active' ? ' (Active)' : '' }}
                        </option>
                    </select>
                </div>
                <p class="text-xs text-slate-500 max-w-sm">
                    Configure student registration and editing windows for <strong>{{ academicYear }}</strong>. Outside these windows, schools must submit change requests.
                </p>
            </div>

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

            <button type="submit" class="btn-primary" :disabled="form.processing">Save student windows</button>
        </form>
    </SahodayaAdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
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
    academicYearOptions: { type: Array, default: () => [] },
    window: Object,
    emergencyLock: Boolean,
});

const selectedYear = ref(props.academicYear);

function onYearChange() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/students/registration-windows`, {
        academic_year: selectedYear.value,
    }, { preserveScroll: true });
}

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
