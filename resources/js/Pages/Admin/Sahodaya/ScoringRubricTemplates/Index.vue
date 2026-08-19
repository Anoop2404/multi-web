<template>
    <SahodayaEventsLayout title="Scoring rubric templates" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader
            title="Scoring rubric templates"
            eyebrow="Mark entry"
            description="Reusable sets of scoring columns (e.g. Content / Presentation) that any item in any event can apply from Mark Entry's Configure Columns panel, instead of typing the same columns in for every item that shares a rubric."
        >
            <template #actions>
                <button type="button" class="btn-primary text-sm" @click="showAdd = !showAdd">+ New template</button>
            </template>
        </PageHeader>

        <form v-if="showAdd" @submit.prevent="createTemplate" class="card mb-6 space-y-3 max-w-2xl">
            <p class="text-sm font-semibold text-slate-800">New rubric template</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <FormField label="Name">
                    <input v-model="form.name" class="field" required placeholder="Standard On-Stage Solo">
                </FormField>
                <FormField label="Description" hint="Optional">
                    <input v-model="form.description" class="field" placeholder="Content / Presentation / Time management">
                </FormField>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="btn-secondary" @click="showAdd = false">Cancel</button>
                <button type="submit" class="btn-primary" :disabled="form.processing">Save template</button>
            </div>
        </form>

        <div v-if="!templates.length" class="card p-8 text-center text-slate-400">
            No rubric templates yet. Create one above, then apply it from any item's Configure Columns panel in Mark Entry.
        </div>

        <div v-for="template in templates" :key="template.id" class="card mb-4 space-y-3">
            <div class="flex items-start justify-between gap-3 flex-wrap">
                <div class="flex-1 min-w-[16rem] space-y-1">
                    <input v-model="template.name" class="field field--sm font-semibold w-full max-w-md" @change="saveTemplate(template)">
                    <input v-model="template.description" class="field field--sm w-full max-w-md text-xs" placeholder="Description (optional)" @change="saveTemplate(template)">
                </div>
                <button type="button" class="btn-ghost text-xs text-red-600" @click="removeTemplate(template)">Remove template</button>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Column / criterion</th>
                        <th class="w-32">Max marks</th>
                        <th class="w-20"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="criterion in template.criteria" :key="criterion.id">
                        <td><input v-model="criterion.label" class="field field--sm" @change="saveCriterion(template, criterion)"></td>
                        <td><input v-model.number="criterion.max_score" type="number" min="0.5" step="0.5" class="field field--sm" @change="saveCriterion(template, criterion)"></td>
                        <td class="text-right">
                            <button type="button" class="btn-ghost text-xs text-red-600" @click="removeCriterion(template, criterion)">Remove</button>
                        </td>
                    </tr>
                    <tr v-if="!template.criteria.length">
                        <td colspan="3" class="text-center text-slate-400 text-xs py-4">No columns yet.</td>
                    </tr>
                </tbody>
            </table>

            <form class="flex items-end gap-2" @submit.prevent="addCriterion(template)">
                <FormField label="Column label" class-extra="flex-1">
                    <input v-model="newCriterionForms[template.id].label" class="field field--sm" placeholder="e.g. Content" required>
                </FormField>
                <FormField label="Max marks">
                    <input v-model.number="newCriterionForms[template.id].max_score" type="number" min="0.5" step="0.5" class="field field--sm w-24" placeholder="10">
                </FormField>
                <button type="submit" class="btn-secondary text-xs">+ Add column</button>
            </form>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    templates: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/scoring-rubric-templates`;
const showAdd = ref(false);
const templates = reactive((props.templates ?? []).map((t) => ({ ...t, criteria: [...(t.criteria ?? [])] })));

const newCriterionForms = reactive(
    Object.fromEntries(templates.map((t) => [t.id, { label: '', max_score: 10 }])),
);

const form = useForm({ name: '', description: '' });

function createTemplate() {
    form.post(base, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showAdd.value = false;
        },
    });
}

function saveTemplate(template) {
    router.put(`${base}/${template.id}`, {
        name: template.name,
        description: template.description,
    }, { preserveScroll: true });
}

async function removeTemplate(template) {
    if (!(await confirm({ message: `Remove template "${template.name}"? Items that already applied it keep their columns — only the reusable template is deleted.` }))) return;
    router.delete(`${base}/${template.id}`, { preserveScroll: true });
}

function addCriterion(template) {
    const draft = newCriterionForms[template.id];
    router.post(`${base}/${template.id}/criteria`, draft, {
        preserveScroll: true,
        onSuccess: () => {
            newCriterionForms[template.id] = { label: '', max_score: 10 };
        },
    });
}

function saveCriterion(template, criterion) {
    router.put(`${base}/${template.id}/criteria/${criterion.id}`, {
        label: criterion.label,
        max_score: criterion.max_score,
    }, { preserveScroll: true });
}

async function removeCriterion(template, criterion) {
    if (!(await confirm({ message: `Remove column "${criterion.label}"?` }))) return;
    router.delete(`${base}/${template.id}/criteria/${criterion.id}`, { preserveScroll: true });
}
</script>
