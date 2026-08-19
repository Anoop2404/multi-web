<template>
    <div class="space-y-6 max-w-3xl">
        <!-- Participation Policy Section -->
        <FormSection title="Student Participation Policy" hint="Limits per student for individual and group competitions.">
            <div class="space-y-5">
                <!-- Preset Selector -->
                <div class="p-4 bg-slate-50 border border-slate-200/80 rounded-2xl space-y-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Policy Preset</label>
                    <select v-model="policyForm.preset_key" class="field bg-white">
                        <option value="">Custom limits</option>
                        <option v-for="(label, key) in participationPresets" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <p class="text-[11px] text-slate-500">Choosing a preset auto-populates standard limits. You can customize any value below.</p>
                </div>

                <!-- Individual Items Policy Card -->
                <div class="p-4 bg-white border border-slate-200/80 rounded-2xl shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="text-base">🎭</span>
                            <h4 class="font-bold text-sm text-slate-900">Individual Competition Limits</h4>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-100">Individual Items</span>
                    </div>

                    <FormGrid>
                        <FormField label="Total Individual / Student" hint="Overall cap across all solo items">
                            <input v-model.number="policyForm.max_total_per_student" type="number" min="0" class="field font-semibold" placeholder="0 (No cap)">
                        </FormField>
                        <FormField label="On-Stage Individual / Student" hint="Max on-stage solo items">
                            <input v-model.number="policyForm.max_onstage_per_student" type="number" min="0" class="field" placeholder="0 (No cap)">
                        </FormField>
                        <FormField label="Off-Stage Individual / Student" hint="Max off-stage solo items">
                            <input v-model.number="policyForm.max_offstage_per_student" type="number" min="0" class="field" placeholder="0 (No cap)">
                        </FormField>
                    </FormGrid>
                </div>

                <!-- Group & Team Items Policy Card -->
                <div class="p-4 bg-white border border-slate-200/80 rounded-2xl shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="text-base">👥</span>
                            <h4 class="font-bold text-sm text-slate-900">Group & Team Competition Limits</h4>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">Standalone Limit</span>
                    </div>

                    <FormGrid>
                        <FormField label="Group / Team / Pair / Trio / Student" class-extra="sm:col-span-2" hint="Max group or team events per student">
                            <input v-model.number="policyForm.max_group_per_student" type="number" min="0" class="field font-semibold" placeholder="0 (No cap)">
                        </FormField>
                    </FormGrid>
                    <p class="text-[11px] text-slate-500">Group items are tracked independently and do not reduce the student's individual item cap above.</p>
                </div>

                <!-- Guidance Info Box -->
                <div class="p-3.5 bg-indigo-50/70 border border-indigo-100 rounded-xl text-xs text-indigo-900 flex items-start gap-2.5">
                    <span class="text-indigo-500 text-sm mt-0.5">ℹ️</span>
                    <div class="space-y-1">
                        <p class="font-semibold text-indigo-950">How per-student limits work:</p>
                        <ul class="list-disc list-inside space-y-0.5 text-indigo-800 text-[11.5px]">
                            <li><strong>Individual Cap:</strong> Overall limit for solo items (On-stage + Off-stage combined).</li>
                            <li><strong>On-Stage & Off-Stage:</strong> Max allowed items from each menu.</li>
                            <li><strong>Group Cap:</strong> Separate limit for group/team/pair items per student.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </FormSection>

        <!-- Per-School Institutional Slots -->
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
                <button type="button" @click="savePolicy" class="btn-primary" :disabled="policyForm.processing">Save policy</button>
            </FormActions>
        </FormSection>
    </div>
</template>

<script setup>
import { inject } from 'vue';

const { policyForm, participationPresets, savePolicy } = inject('eventSettings');
</script>
