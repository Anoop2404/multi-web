<template>
    <SahodayaAdminLayout :title="`Lock overrides — ${school.name}`" :sahodaya="sahodaya">
        <PageHeader :title="school.name" eyebrow="Lock overrides"
                    :description="windowState.message || 'Current window state for this school.'">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/schools/${school.id}`" class="btn-secondary text-sm">← School</Link>
            </template>
        </PageHeader>

        <div class="grid lg:grid-cols-2 gap-6">
            <div class="card space-y-2 text-sm">
                <p><strong>Can add:</strong> {{ windowState.can_add ? 'Yes' : 'No' }}</p>
                <p><strong>Can edit:</strong> {{ windowState.can_edit ? 'Yes' : 'No' }}</p>
                <p><strong>Source:</strong> {{ windowState.source }}</p>
            </div>

            <form class="card space-y-3" @submit.prevent="submit">
                <h2 class="font-semibold">Grant override</h2>
                <select v-model="form.override_type" class="field" required>
                    <option v-for="(label, value) in overrideTypes" :key="value" :value="value">{{ label }}</option>
                </select>
                <input v-model="form.expires_at" type="datetime-local" class="field" placeholder="Expires at">
                <textarea v-model="form.reason" class="field" rows="3" placeholder="Reason"></textarea>
                <button type="submit" class="btn-primary" :disabled="form.processing">Save override</button>
            </form>
        </div>

        <div class="card card--flush overflow-hidden mt-6">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3 text-left">Type</th>
                        <th class="p-3 text-left">Expires</th>
                        <th class="p-3 text-left">Reason</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in overrides.data" :key="row.id" class="border-t">
                        <td class="p-3">{{ row.override_type }}</td>
                        <td class="p-3">{{ row.expires_at ? formatDate(row.expires_at) : 'Permanent' }}</td>
                        <td class="p-3">{{ row.reason || '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object,
    school: Object,
    overrides: Object,
    windowState: Object,
    overrideTypes: Object,
});

const form = useForm({
    override_type: 'unlock_all',
    expires_at: '',
    reason: '',
});

function submit() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/schools/${props.school.id}/lock-overrides`);
}

function formatDate(iso) {
    return iso ? new Date(iso).toLocaleString() : '—';
}
</script>
