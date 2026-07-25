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
                </FormGrid>
                <p v-if="breakdownExceedsTotal" class="text-xs text-rose-600">
                    On-stage + off-stage + group ({{ breakdownSum }}) exceeds the total per-student limit ({{ policyForm.max_total_per_student }}).
                </p>
            </FormSection>

            <FormSection title="Per-school limits (institutional slots)"
                         hint="How many entries one school can send, event-wide. Leave a number blank for no cap.">
                <FormGrid>
                    <FormField label="On-stage / school">
                        <input v-model.number="policyForm.max_onstage_per_school" type="number" min="0" class="field" placeholder="No cap">
                    </FormField>
                    <FormField label="Off-stage / school">
                        <input v-model.number="policyForm.max_offstage_per_school" type="number" min="0" class="field" placeholder="No cap">
                    </FormField>
                    <FormField label="Group / school">
                        <input v-model.number="policyForm.max_group_per_school" type="number" min="0" class="field" placeholder="No cap">
                    </FormField>
                    <FormField class-extra="sm:col-span-3">
                        <CheckboxField v-model="policyForm.one_entry_per_item_per_school"
                                       label="One entry per item, per school (default on)" />
                        <p class="text-xs text-slate-500 mt-1">
                            Blocks a school from registering a second participant/pair/group for the same item —
                            i.e. one individual for an individual item, one pair for a pair item, one group for a
                            group item. Turn off only if this event deliberately allows multiple entries per school
                            in the same item.
                        </p>
                    </FormField>
                    <FormField class-extra="sm:col-span-3">
                        <CheckboxField v-model="policyForm.require_fee_before_approval" label="Require fee approval before registration approval" />
                    </FormField>
                </FormGrid>
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

