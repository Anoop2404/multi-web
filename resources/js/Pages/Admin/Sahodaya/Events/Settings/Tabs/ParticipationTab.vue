<template>
    <div class="card max-w-2xl space-y-4">
<FormSection title="Participation policy" hint="Limits per student and fee approval rules.">
                <FormGrid>
                    <FormField label="Preset" class-extra="sm:col-span-3">
                        <select v-model="policyForm.preset_key" class="field">
                            <option value="">Custom limits</option>
                            <option v-for="(label, key) in participationPresets" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </FormField>
                    <FormField label="Total items / student">
                        <input v-model.number="policyForm.max_total_per_student" type="number" min="0" class="field" placeholder="0">
                    </FormField>
                    <FormField label="On-stage / student">
                        <input v-model.number="policyForm.max_onstage_per_student" type="number" min="0" class="field" placeholder="0">
                    </FormField>
                    <FormField label="Off-stage / student">
                        <input v-model.number="policyForm.max_offstage_per_student" type="number" min="0" class="field" placeholder="0">
                    </FormField>
                    <FormField label="Group / student">
                        <input v-model.number="policyForm.max_group_per_student" type="number" min="0" class="field" placeholder="0">
                    </FormField>
                    <FormField class-extra="sm:col-span-3">
                        <CheckboxField v-model="policyForm.require_fee_before_approval" label="Require fee approval before registration approval" />
                    </FormField>
                </FormGrid>
                <p v-if="breakdownExceedsTotal" class="text-xs text-rose-600">
                    On-stage + off-stage + group ({{ breakdownSum }}) exceeds the total per-student limit ({{ policyForm.max_total_per_student }}).
                </p>
                <FormActions>
                    <button type="button" @click="savePolicy" class="btn-primary" :disabled="policyForm.processing || breakdownExceedsTotal">Save policy</button>
                </FormActions>
            </FormSection>
    </div>
</template>

<script setup>
import { computed, inject } from 'vue';

const { policyForm, participationPresets, savePolicy } = inject('eventSettings');

const breakdownSum = computed(() => (
    (Number(policyForm.max_onstage_per_student) || 0)
    + (Number(policyForm.max_offstage_per_student) || 0)
    + (Number(policyForm.max_group_per_student) || 0)
));

const breakdownExceedsTotal = computed(() => (
    policyForm.max_total_per_student !== null
    && policyForm.max_total_per_student !== ''
    && breakdownSum.value > Number(policyForm.max_total_per_student)
));
</script>

