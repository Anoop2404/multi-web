<template>
    <div class="space-y-6 max-w-3xl">
        <!-- Participation Policy Section -->
        <FormSection title="Student Participation Policy" hint="Limits per student for individual and group competitions.">
            <div class="space-y-5">
                <!-- Preset Selector -->
                <div class="p-4 bg-slate-50 border border-slate-200/80 rounded-2xl space-y-2">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700">Policy Preset</label>
                    <SearchableSelect v-model="policyForm.preset_key" :options="participationPresetOptions"
                                      :all-option="true" all-label="Custom limits" />
                    <p class="text-[11px] text-slate-500">Choosing a preset auto-populates standard limits. You can customize any value below.</p>
                </div>

                <!-- Overall Total Items Card -->
                <div class="p-4 bg-white border border-slate-200/80 rounded-2xl shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="text-base">🎯</span>
                            <h4 class="font-bold text-sm text-slate-900">Overall Participation Cap</h4>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-purple-50 text-purple-700 border border-purple-100">All Formats</span>
                    </div>
                    <FormGrid>
                        <FormField label="Total Items Across All Formats / Student" class-extra="sm:col-span-2" hint="Absolute ceiling combining individual, pair, group, and common items (e.g. 2 for Language Fest)">
                            <input v-model.number="policyForm.max_overall_per_student" type="number" min="0" class="field font-semibold text-purple-950" placeholder="0 (No overall cap)">
                        </FormField>
                    </FormGrid>
                </div>

                <!-- Individual Items Policy Card -->
                <div class="p-4 bg-white border border-slate-200/80 rounded-2xl shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="text-base">🎭</span>
                            <h4 class="font-bold text-sm text-slate-900">Individual Competition Limits</h4>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-100">Solo Items</span>
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

                    <FormGrid>
                        <FormField label="Writing / Student" hint="Max off-stage writing items (essay, story, versification)">
                            <input v-model.number="policyForm.max_offstage_writing_per_student" type="number" min="0" class="field" placeholder="0 (No cap)">
                        </FormField>
                        <FormField label="Drawing / Student" hint="Max off-stage drawing items (pencil, painting, cartoon)">
                            <input v-model.number="policyForm.max_offstage_drawing_per_student" type="number" min="0" class="field" placeholder="0 (No cap)">
                        </FormField>
                    </FormGrid>
                    <p class="text-[11px] text-slate-500">
                        Writing and Drawing are sub-limits within the Off-Stage cap above — they narrow how many of
                        the off-stage total a student can spend on that item type, not an additional allowance.
                    </p>
                </div>

                <!-- Pair, Group & Common Items Policy Card -->
                <div class="p-4 bg-white border border-slate-200/80 rounded-2xl shadow-sm space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="text-base">👥</span>
                            <h4 class="font-bold text-sm text-slate-900">Pair, Group & Common Formats</h4>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">Multi-person & Common</span>
                    </div>

                    <FormGrid>
                        <FormField label="Pair Items / Student" hint="Max pair competitions (e.g. 1)">
                            <input v-model.number="policyForm.max_pair_per_student" type="number" min="0" class="field font-semibold" placeholder="0 (No cap)">
                        </FormField>
                        <FormField label="Group / Team Items / Student" hint="Max group or team events per student (e.g. 1 or 2)">
                            <input v-model.number="policyForm.max_group_per_student" type="number" min="0" class="field font-semibold" placeholder="0 (No cap)">
                        </FormField>
                        <FormField label="Common Items / Student" hint="Max open/common pool items per student">
                            <input v-model.number="policyForm.max_common_per_student" type="number" min="0" class="field font-semibold" placeholder="0 (No cap)">
                        </FormField>
                    </FormGrid>

                    <!-- Pair Items Scoring Mode -->
                    <div class="pt-2 border-t border-slate-100">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 mb-2">Pair Items Point Calculation</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label :class="['flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition', policyForm.pair_points_mode === 'individual' ? 'border-indigo-400 bg-indigo-50/50' : 'border-slate-200 bg-white hover:bg-slate-50']">
                                <input type="radio" v-model="policyForm.pair_points_mode" value="individual" class="mt-0.5 text-indigo-600">
                                <div>
                                    <span class="text-xs font-semibold text-slate-900 block">Individual Points Scale</span>
                                    <span class="text-[11px] text-slate-500 block">Awards 1st/2nd/3rd and grades using the individual item scale (Sahodaya Kalotsav Manual rule).</span>
                                </div>
                            </label>
                            <label :class="['flex items-start gap-2.5 p-3 rounded-xl border cursor-pointer transition', policyForm.pair_points_mode === 'group' ? 'border-indigo-400 bg-indigo-50/50' : 'border-slate-200 bg-white hover:bg-slate-50']">
                                <input type="radio" v-model="policyForm.pair_points_mode" value="group" class="mt-0.5 text-indigo-600">
                                <div>
                                    <span class="text-xs font-semibold text-slate-900 block">Group Points Scale</span>
                                    <span class="text-[11px] text-slate-500 block">Awards points using the group/team scale (higher weight).</span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Guidance Info Box -->
                <div class="p-3.5 bg-indigo-50/70 border border-indigo-100 rounded-xl text-xs text-indigo-900 flex items-start gap-2.5">
                    <span class="text-indigo-500 text-sm mt-0.5">ℹ️</span>
                    <div class="space-y-1">
                        <p class="font-semibold text-indigo-950">How dynamic per-student rules work:</p>
                        <ul class="list-disc list-inside space-y-0.5 text-indigo-800 text-[11.5px]">
                            <li><strong>Overall Cap:</strong> Restricts the total number of items a student can enter across any format (solo, pair, group, common).</li>
                            <li><strong>Individual, Pair, Group, Common Caps:</strong> Enforces category-specific ceilings. Setting Overall = 2, Individual = 1, Pair = 1, Group = 1, Common = 1 automatically enforces the exact 6 Sahodaya Language Fest combination options.</li>
                            <li><strong>Pair Scoring Scale:</strong> Determines whether pair item victories award individual points or group points for school championship standings.</li>
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
import { inject, computed } from 'vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const { policyForm, participationPresets, savePolicy } = inject('eventSettings');

const participationPresetOptions = computed(() =>
    Object.entries(participationPresets.value ?? {}).map(([key, label]) => ({ value: key, label }))
);
</script>
