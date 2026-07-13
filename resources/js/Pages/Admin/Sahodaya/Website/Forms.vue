<template>
    <SahodayaAdminLayout title="Website forms" :sahodaya="sahodaya" :publicUrl="publicUrl" :show-header-title="false">
        <PageHeader title="Forms builder" eyebrow="Website"
                    description="Create public forms with honeypot spam protection. Public URL: /forms/{slug}.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/site-builder`" class="btn-secondary text-sm">Site builder</Link>
            </template>
        </PageHeader>

        <form @submit.prevent="create" class="card mb-6 grid gap-3 sm:grid-cols-2 items-end max-w-3xl">
            <FormField label="Form name">
                <input v-model="form.name" class="field" required placeholder="Contact enquiry">
            </FormField>
            <FormField label="Notify email">
                <input v-model="form.notify_email" type="email" class="field" placeholder="office@example.org">
            </FormField>
            <button type="submit" class="btn-primary sm:col-span-2 w-fit" :disabled="form.processing">Create form</button>
        </form>

        <div class="card card--flush overflow-hidden max-w-4xl">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Form</th>
                        <th>Public path</th>
                        <th>Submissions</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="f in forms" :key="f.id">
                        <td class="font-medium">{{ f.name }}</td>
                        <td class="font-mono text-xs">/forms/{{ f.slug }}</td>
                        <td>{{ f.submissions_count ?? 0 }}</td>
                        <td>{{ f.is_active ? 'Active' : 'Off' }}</td>
                        <td class="text-right whitespace-nowrap">
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/website/forms/${f.id}/submissions`" class="btn-ghost text-xs text-indigo-600 mr-2">Submissions</Link>
                            <button type="button" class="btn-ghost text-xs text-red-600" @click="remove(f)">Remove</button>
                        </td>
                    </tr>
                    <tr v-if="!forms.length">
                        <td colspan="5" class="p-8 text-center text-slate-400">No forms yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    forms: { type: Array, default: () => [] },
    sites: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/website/forms`;
const form = useForm({ name: '', notify_email: '' });

function create() {
    form.post(base, { preserveScroll: true, onSuccess: () => form.reset() });
}
function remove(f) {
    if (!confirm(`Remove form "${f.name}"?`)) return;
    router.delete(`${base}/${f.id}`, { preserveScroll: true });
}
</script>
