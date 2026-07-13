<template>
    <SahodayaEventsLayout :title="`${event.title} — Eligibility`" :sahodaya="sahodaya" :event="event" :show-header-title="false">
        <PageHeader :title="`${event.title} — Eligibility rules`" eyebrow="Setup"
                    description="Data-driven rules layered on top of built-in type checks (class/age/gender). Empty = use program defaults only.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/areas`" class="btn-secondary text-sm">
                    Competition areas →
                </Link>
            </template>
        </PageHeader>

        <form @submit.prevent="createRule" class="card mb-6 space-y-3">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <FormField label="Scope">
                    <select v-model="form.scope_type" class="field" required>
                        <option value="event">Whole event</option>
                        <option value="area">Area</option>
                        <option value="item">Item</option>
                    </select>
                </FormField>
                <FormField v-if="form.scope_type === 'event'" label="Event">
                    <input class="field" :value="event.title" disabled>
                </FormField>
                <FormField v-else-if="form.scope_type === 'area'" label="Area">
                    <select v-model.number="form.scope_id" class="field" required>
                        <option v-for="a in areas" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                </FormField>
                <FormField v-else label="Item">
                    <select v-model.number="form.scope_id" class="field" required>
                        <option v-for="i in items" :key="i.id" :value="i.id">{{ i.title }}</option>
                    </select>
                </FormField>
                <FormField label="Rule type">
                    <select v-model="form.rule_type" class="field" required>
                        <option v-for="(label, key) in ruleTypes" :key="key" :value="key">{{ label }}</option>
                    </select>
                </FormField>
                <FormField label="Operator">
                    <select v-model="form.operator" class="field">
                        <option value="in">Allow (in)</option>
                        <option value="not_in">Deny (not in)</option>
                    </select>
                </FormField>
                <FormField label="Values (comma-separated)" classExtra="sm:col-span-2" hint="e.g. male,female or school UUIDs">
                    <input v-model="valuesText" class="field" placeholder="male, female">
                </FormField>
                <FormField label="Logic group" hint="Same group = AND; different = OR">
                    <input v-model.number="form.logic_group" type="number" min="0" class="field">
                </FormField>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="btn-primary" :disabled="form.processing">Add rule</button>
            </div>
        </form>

        <div class="card card--flush overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Scope</th>
                        <th>Rule</th>
                        <th>Operator</th>
                        <th>Values</th>
                        <th>Group</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rules" :key="row.id">
                        <td class="text-xs">
                            <span class="font-mono">{{ row.scope_type }}</span> #{{ row.scope_id }}
                        </td>
                        <td>{{ ruleTypes[row.rule_type] || row.rule_type }}</td>
                        <td class="font-mono text-xs">{{ row.operator }}</td>
                        <td class="text-xs font-mono">{{ formatValues(row.value_json) }}</td>
                        <td>{{ row.logic_group }}</td>
                        <td class="text-right">
                            <button type="button" class="btn-ghost text-xs text-red-600" @click="removeRule(row)">Remove</button>
                        </td>
                    </tr>
                    <tr v-if="!rules.length">
                        <td colspan="6" class="p-8 text-center text-slate-400">No custom rules — built-in program eligibility still applies.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';

const props = defineProps({
    sahodaya: Object,
    event: Object,
    rules: { type: Array, default: () => [] },
    ruleTypes: { type: Object, default: () => ({}) },
    areas: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/eligibility-rules`;
const valuesText = ref('');

const form = useForm({
    scope_type: 'event',
    scope_id: props.event.id,
    rule_type: 'gender',
    operator: 'in',
    value_json: {},
    logic_group: 0,
    sort_order: 0,
});

watch(() => form.scope_type, (type) => {
    if (type === 'event') form.scope_id = props.event.id;
    else if (type === 'area') form.scope_id = props.areas[0]?.id ?? null;
    else form.scope_id = props.items[0]?.id ?? null;
});

function buildValueJson() {
    const parts = valuesText.value.split(',').map((s) => s.trim()).filter(Boolean);
    if (form.rule_type === 'require_verified') {
        return { required: true };
    }
    if (form.rule_type === 'school') {
        return { school_ids: parts };
    }
    if (form.rule_type === 'region') {
        return { region_ids: parts };
    }
    if (form.rule_type === 'custom_ids') {
        return { student_ids: parts.map((p) => (Number.isNaN(Number(p)) ? p : Number(p))) };
    }
    if (form.rule_type === 'audience') {
        return { audience: parts.length ? parts : ['student'] };
    }

    return { in: parts };
}

function createRule() {
    form.value_json = buildValueJson();
    form.post(base, {
        preserveScroll: true,
        onSuccess: () => {
            valuesText.value = '';
            form.reset('value_json');
        },
    });
}

function formatValues(json) {
    if (!json) return '—';
    return JSON.stringify(json);
}

function removeRule(row) {
    if (!confirm('Remove this eligibility rule?')) return;
    router.delete(`${base}/${row.id}`, { preserveScroll: true });
}
</script>
