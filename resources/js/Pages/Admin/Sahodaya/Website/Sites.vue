<template>
    <SahodayaAdminLayout title="Microsites" :sahodaya="sahodaya" :publicUrl="publicUrl" :show-header-title="false">
        <PageHeader title="Microsites" eyebrow="Website"
                    description="Primary site powers your homepage. Extra microsites are available at /m/{slug}.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/site-builder`" class="btn-secondary text-sm">Site builder</Link>
            </template>
        </PageHeader>

        <form @submit.prevent="create" class="card mb-6 grid gap-3 sm:grid-cols-3 items-end max-w-4xl">
            <FormField label="Name">
                <input v-model="form.name" class="field" required placeholder="Innovation Expo">
            </FormField>
            <FormField label="Slug" hint="Optional">
                <input v-model="form.slug" class="field" placeholder="innovation-expo">
            </FormField>
            <button type="submit" class="btn-primary" :disabled="form.processing">Add microsite</button>
        </form>

        <div class="card card--flush overflow-hidden max-w-4xl">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Site</th>
                        <th>Slug</th>
                        <th>Sections</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in sites" :key="s.id">
                        <td class="font-medium">{{ s.name }} <span v-if="s.is_primary" class="text-xs text-indigo-600">(primary)</span></td>
                        <td class="font-mono text-xs">{{ s.slug }}</td>
                        <td>{{ s.sections_count ?? 0 }}</td>
                        <td>{{ s.is_active ? 'Active' : 'Off' }}</td>
                        <td class="text-right">
                            <button v-if="!s.is_primary" type="button" class="btn-ghost text-xs text-red-600" @click="remove(s)">Remove</button>
                        </td>
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
    sites: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/website/sites`;
const form = useForm({ name: '', slug: '' });

function create() {
    form.post(base, { preserveScroll: true, onSuccess: () => form.reset() });
}
function remove(s) {
    if (!confirm(`Remove microsite "${s.name}"?`)) return;
    router.delete(`${base}/${s.id}`, { preserveScroll: true });
}
</script>
