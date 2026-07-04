<template>
    <SahodayaAdminLayout :title="`Item category masters`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Item category masters" eyebrow="Catalog"
                    description="Manage dropdown values for sports and arts item forms — disciplines, venue types, formats, participant types, and more.">
            <template #actions>
                <Link :href="sportsAgeGroupsUrl" class="btn-secondary text-sm">Sports age groups →</Link>
            </template>
        </PageHeader>

        <div class="flex flex-wrap gap-2 mb-6 border-b border-slate-200 pb-4">
            <Link v-for="(label, key) in dimensions" :key="key"
                  :href="`/sahodaya-admin/${sahodaya.id}/taxonomy-masters?dimension=${key}`"
                  :class="dimension === key ? 'subnav-link subnav-link--active' : 'subnav-link'">
                {{ label }}
            </Link>
        </div>

        <form @submit.prevent="addEntry" class="card mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 items-end">
            <FormField label="Key (slug)" hint="Lowercase, e.g. track">
                <input v-model="form.entry_key" class="field" required pattern="[a-z0-9_]+" placeholder="track">
            </FormField>
            <FormField label="Display label">
                <input v-model="form.label" class="field" required placeholder="Track (Running)">
            </FormField>
            <FormField label="Sort order">
                <input v-model.number="form.sort_order" type="number" min="0" class="field">
            </FormField>
            <div>
                <button type="submit" class="btn-primary w-full sm:w-auto" :disabled="form.processing">Add entry</button>
            </div>
        </form>

        <div class="flex justify-end mb-3">
            <button type="button" class="btn-secondary text-xs" @click="resetDefaults">Reset to defaults</button>
        </div>

        <div class="card card--flush overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>Label</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in entries" :key="row.id ?? row.entry_key">
                        <td class="font-mono text-xs">{{ row.entry_key }}</td>
                        <td>
                            <input v-if="row.id" v-model="editRows[row.id].label" class="field field--sm">
                            <span v-else>{{ row.label }}</span>
                        </td>
                        <td>
                            <input v-if="row.id" v-model.number="editRows[row.id].sort_order" type="number" min="0" class="field field--sm w-20">
                            <span v-else>{{ row.sort_order }}</span>
                        </td>
                        <td>
                            <label v-if="row.id" class="inline-flex items-center gap-1 text-xs">
                                <input type="checkbox" v-model="editRows[row.id].is_active"> Active
                            </label>
                            <span v-else class="text-xs text-slate-400">Config default</span>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <button v-if="row.id" type="button" class="btn-ghost text-xs text-indigo-600 mr-2" @click="saveRow(row)">Save</button>
                            <button v-if="row.id" type="button" class="btn-ghost text-xs text-red-600" @click="removeRow(row)">Remove</button>
                        </td>
                    </tr>
                    <tr v-if="!entries.length">
                        <td colspan="5" class="p-8 text-center text-slate-400">No entries.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    dimension: String,
    dimensions: Object,
    entries: Array,
    sportsAgeGroupsUrl: String,
});

const base = `/sahodaya-admin/${props.sahodaya.id}/taxonomy-masters`;
const form = useForm({
    dimension: props.dimension,
    entry_key: '',
    label: '',
    sort_order: 100,
});

const editRows = reactive({});

watch(() => props.entries, (rows) => {
    for (const key of Object.keys(editRows)) delete editRows[key];
    for (const row of rows ?? []) {
        if (row.id) {
            editRows[row.id] = { label: row.label, sort_order: row.sort_order, is_active: row.is_active };
        }
    }
}, { immediate: true });

function addEntry() {
    form.dimension = props.dimension;
    form.post(base, { preserveScroll: true, onSuccess: () => form.reset('entry_key', 'label') });
}

function saveRow(row) {
    router.put(`${base}/${row.id}`, editRows[row.id], { preserveScroll: true });
}

function removeRow(row) {
    if (! confirm('Remove this master entry?')) return;
    router.delete(`${base}/${row.id}`, { preserveScroll: true });
}

function resetDefaults() {
    if (! confirm('Reset this category to system defaults? Custom entries will be removed.')) return;
    router.post(`${base}/reset-defaults`, { dimension: props.dimension }, { preserveScroll: true });
}
</script>
