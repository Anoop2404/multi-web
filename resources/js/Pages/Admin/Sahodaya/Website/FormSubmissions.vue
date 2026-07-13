<template>
    <SahodayaAdminLayout title="Form submissions" :sahodaya="sahodaya" :publicUrl="publicUrl" :show-header-title="false">
        <PageHeader :title="`${form.name} — submissions`" eyebrow="Website">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/website/forms`" class="btn-secondary text-sm">← Forms</Link>
            </template>
        </PageHeader>

        <div class="card card--flush overflow-hidden max-w-5xl">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Payload</th>
                        <th>Spam</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in submissions" :key="row.id">
                        <td class="text-xs whitespace-nowrap">{{ row.created_at }}</td>
                        <td class="text-xs font-mono">{{ JSON.stringify(row.payload_json) }}</td>
                        <td>{{ row.is_spam ? 'Yes' : 'No' }}</td>
                    </tr>
                    <tr v-if="!submissions.length">
                        <td colspan="3" class="p-8 text-center text-slate-400">No submissions yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

defineProps({
    sahodaya: Object,
    publicUrl: String,
    form: Object,
    submissions: { type: Array, default: () => [] },
});
</script>
