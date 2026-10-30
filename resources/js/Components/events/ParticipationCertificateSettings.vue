<template>
    <form class="card !p-4 mb-5" @submit.prevent="save">
        <h3 class="font-semibold text-slate-800">Participation certificate settings</h3>
        <p class="text-xs text-slate-500 mt-1">Uses the participation template, independently of merit certificates.</p>
        <div class="flex flex-wrap items-end gap-3 mt-3">
            <FormField label="Issue participation certificates">
                <select v-model="form.participation_certificate_mode" class="field">
                    <option value="per_person">One per student — list all items</option>
                    <option value="per_item">One per student per item — separate certificates</option>
                </select>
            </FormField>
            <button type="submit" class="btn-secondary text-sm" :disabled="form.processing || !form.isDirty">{{ form.processing ? 'Saving…' : 'Save participation settings' }}</button>
        </div>
        <p v-if="form.errors.participation_certificate_mode" class="text-xs text-rose-600 mt-2">{{ form.errors.participation_certificate_mode }}</p>
        <p class="text-xs text-slate-500 mt-2">After changing this, generate participation certificates again to apply it. Existing certificates may be regrouped; merit certificates are unaffected. Absent, disqualified and standby entries are excluded.</p>
    </form>
</template>
<script setup>
import { useForm } from '@inertiajs/vue3';
const props = defineProps({ base: { type: String, required: true }, mode: { type: String, default: 'per_person' } });
const form = useForm({ participation_certificate_mode: props.mode });
function save() {
    form.post(`${props.base}/participation-settings`, { preserveScroll: true, onSuccess: () => form.defaults() });
}
</script>
