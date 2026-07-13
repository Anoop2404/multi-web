<template>
    <SahodayaEventsLayout title="Competition types" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader
            title="Competition types"
            eyebrow="Programs"
            description="Master list of fest programs. Add a type (e.g. Robotics) and open its hub under Fest & events — catalog and events work without code changes."
        >
            <template #actions>
                <Link :href="taxonomyMastersUrl" class="btn-secondary text-sm">Item category masters →</Link>
                <button type="button" class="btn-secondary text-sm" @click="resetDefaults">Reset system types</button>
                <button type="button" class="btn-primary text-sm" @click="showAdd = !showAdd">+ Add type</button>
            </template>
        </PageHeader>

        <form v-if="showAdd" @submit.prevent="createType" class="card mb-6 space-y-3 max-w-3xl">
            <p class="text-sm font-semibold text-slate-800">New competition type</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <FormField label="Type key" hint="Stable slug, e.g. robotics">
                    <input v-model="form.type_key" class="field font-mono" required pattern="[a-z][a-z0-9_]*" placeholder="robotics">
                </FormField>
                <FormField label="Display label">
                    <input v-model="form.label" class="field" required placeholder="Robotics Meet">
                </FormField>
                <FormField label="Nav slug" hint="Optional URL segment">
                    <input v-model="form.nav_slug" class="field font-mono" pattern="[a-z0-9\-]*" placeholder="robotics">
                </FormField>
                <FormField label="Sort order">
                    <input v-model.number="form.sort_order" type="number" min="0" class="field" placeholder="200">
                </FormField>
                <FormField label="Description" class-extra="sm:col-span-2">
                    <input v-model="form.description" class="field" placeholder="Short description">
                </FormField>
                <label class="flex items-center gap-2 text-sm sm:col-span-2">
                    <input type="checkbox" v-model="form.is_singleton">
                    One hub event per academic year (singleton)
                </label>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn-secondary" @click="showAdd = false">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="form.processing">Save type</button>
            </div>
        </form>

        <div class="card card--flush overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Key</th>
                        <th>Label</th>
                        <th>Nav</th>
                        <th>Singleton</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in editRows" :key="row.id ?? row.type_key">
                        <td class="font-mono text-xs">
                            {{ row.type_key }}
                            <span v-if="row.is_system" class="ml-1 text-[10px] uppercase tracking-wide text-slate-400">system</span>
                        </td>
                        <td>
                            <input v-if="row.id" v-model="row.label" class="field field--sm">
                            <span v-else>{{ row.label }}</span>
                        </td>
                        <td>
                            <input v-if="row.id" v-model="row.nav_slug" class="field field--sm font-mono">
                            <span v-else class="font-mono text-xs">{{ row.nav_slug }}</span>
                        </td>
                        <td class="text-xs">{{ row.is_singleton ? 'Yes' : 'Many' }}</td>
                        <td>
                            <input v-if="row.id" v-model.number="row.sort_order" type="number" min="0" class="field field--sm w-20">
                            <span v-else>{{ row.sort_order }}</span>
                        </td>
                        <td>
                            <label v-if="row.id" class="inline-flex items-center gap-1 text-xs">
                                <input type="checkbox" v-model="row.is_active"> Active
                            </label>
                            <span v-else class="text-xs text-slate-400">Config</span>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <button v-if="row.id" type="button" class="btn-ghost text-xs text-indigo-600 mr-2" @click="saveRow(row)">Save</button>
                            <button v-if="row.id && !row.is_system" type="button" class="btn-ghost text-xs text-red-600" @click="removeRow(row)">Remove</button>
                        </td>
                    </tr>
                    <tr v-if="!editRows.length">
                        <td colspan="7" class="p-8 text-center text-slate-400">No competition types.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    types: { type: Array, default: () => [] },
    taxonomyMastersUrl: String,
});

const base = `/sahodaya-admin/${props.sahodaya.id}/competition-types`;
const showAdd = ref(false);
const editRows = reactive((props.types ?? []).map((t) => ({ ...t })));

const form = useForm({
    type_key: '',
    label: '',
    nav_slug: '',
    description: '',
    is_singleton: false,
    sort_order: 200,
});

function createType() {
    form.post(base, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showAdd.value = false;
        },
    });
}

function saveRow(row) {
    router.put(`${base}/${row.id}`, {
        label: row.label,
        nav_slug: row.nav_slug,
        description: row.description,
        icon: row.icon,
        is_singleton: row.is_singleton,
        sort_order: row.sort_order,
        is_active: row.is_active,
    }, { preserveScroll: true });
}

function removeRow(row) {
    if (!confirm(`Remove competition type "${row.label}"?`)) return;
    router.delete(`${base}/${row.id}`, { preserveScroll: true });
}

function resetDefaults() {
    if (!confirm('Reset system competition types to defaults? Custom types are kept.')) return;
    router.post(`${base}/reset-defaults`, {}, { preserveScroll: true });
}
</script>
