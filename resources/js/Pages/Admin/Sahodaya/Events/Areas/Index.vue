<template>
    <SahodayaEventsLayout :title="`${event.title} — Areas`" :sahodaya="sahodaya" :event="event" :show-header-title="false">
        <PageHeader :title="`${event.title} — Competition areas`" eyebrow="Setup"
                    description="Optional subdivisions (e.g. Quiz / Robotics track) with their own windows and fees. Sports Event Heads stay separate.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/eligibility-rules`" class="btn-secondary text-sm">
                    Eligibility rules →
                </Link>
            </template>
        </PageHeader>

        <form @submit.prevent="createArea" class="card mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 items-end">
            <FormField label="Area name">
                <input v-model="form.name" class="field" required placeholder="e.g. Coding">
            </FormField>
            <FormField label="Parent area">
                <select v-model="form.parent_id" class="field">
                    <option :value="null">None (top level)</option>
                    <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }}</option>
                </select>
            </FormField>
            <FormField label="Default item fee (₹)">
                <input v-model.number="form.default_item_fee" type="number" min="0" class="field" placeholder="Optional">
            </FormField>
            <button type="submit" class="btn-primary" :disabled="form.processing">Add area</button>
        </form>

        <div class="card card--flush overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Area</th>
                        <th>Items</th>
                        <th>Reg window</th>
                        <th>Fees</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in editRows" :key="row.id">
                        <td>
                            <input v-model="row.name" class="field field--sm font-medium">
                            <p class="text-[10px] font-mono text-slate-400 mt-0.5">{{ row.slug }}</p>
                        </td>
                        <td>{{ row.items_count ?? 0 }}</td>
                        <td class="text-xs space-y-1">
                            <input v-model="row.reg_start" type="date" class="field field--sm">
                            <input v-model="row.reg_end" type="date" class="field field--sm">
                        </td>
                        <td class="text-xs space-y-1 min-w-[8rem]">
                            <input v-model.number="row.default_item_fee" type="number" min="0" class="field field--sm" placeholder="Item fee">
                            <input v-model.number="row.school_registration_fee" type="number" min="0" class="field field--sm" placeholder="School fee">
                        </td>
                        <td>
                            <label class="inline-flex items-center gap-1 text-xs">
                                <input type="checkbox" v-model="row.is_active"> Active
                            </label>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <button type="button" class="btn-ghost text-xs text-indigo-600 mr-2" @click="saveRow(row)">Save</button>
                            <button type="button" class="btn-ghost text-xs text-red-600" @click="removeRow(row)">Remove</button>
                        </td>
                    </tr>
                    <tr v-if="!editRows.length">
                        <td colspan="6" class="p-8 text-center text-slate-400">No areas yet — add one above.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';

const props = defineProps({
    sahodaya: Object,
    event: Object,
    areas: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/areas`;
const editRows = reactive((props.areas ?? []).map((a) => ({
    ...a,
    reg_start: a.reg_start ? String(a.reg_start).slice(0, 10) : '',
    reg_end: a.reg_end ? String(a.reg_end).slice(0, 10) : '',
})));

const form = useForm({
    name: '',
    parent_id: null,
    default_item_fee: '',
    sort_order: 100,
});

function createArea() {
    form.post(base, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function saveRow(row) {
    router.put(`${base}/${row.id}`, {
        name: row.name,
        parent_id: row.parent_id,
        sort_order: row.sort_order,
        is_active: row.is_active,
        reg_start: row.reg_start || null,
        reg_end: row.reg_end || null,
        default_item_fee: row.default_item_fee === '' ? null : row.default_item_fee,
        school_registration_fee: row.school_registration_fee === '' ? null : row.school_registration_fee,
        student_registration_fee: row.student_registration_fee === '' ? null : row.student_registration_fee,
        verification_policy: row.verification_policy,
        approval_policy: row.approval_policy,
    }, { preserveScroll: true });
}

function removeRow(row) {
    if (!confirm(`Remove area "${row.name}"?`)) return;
    router.delete(`${base}/${row.id}`, { preserveScroll: true });
}
</script>
