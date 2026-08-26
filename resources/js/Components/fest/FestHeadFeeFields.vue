<template>
    <div class="space-y-3">
        <div v-if="showHelp" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-3 text-xs text-slate-700 space-y-1">
            <p><strong>School fee</strong> — once per school, per head.</p>
            <p><strong>Student fee</strong> — once per student registered under this head.</p>
            <p><strong>Team fee</strong> — once per team entry (relay / group items).</p>
            <p><strong>Quotas</strong> — free item / team entries per student before item fees apply (0 = none free).</p>
            <p><strong>Approval</strong> — Auto on full payment, or Manual review.</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <FormField label="School fee (₹)" hint="Once per school">
                <input :value="modelValue.school_registration_fee" type="number" min="0" class="field" placeholder="0"
                       @input="patch('school_registration_fee', $event.target.value)">
            </FormField>
            <FormField label="Student fee (₹)" hint="Per student under this head">
                <input :value="modelValue.student_registration_fee" type="number" min="0" class="field" placeholder="0"
                       @input="patch('student_registration_fee', $event.target.value)">
            </FormField>
            <FormField label="Team fee (₹)" hint="Per team entry">
                <input :value="modelValue.team_registration_fee" type="number" min="0" class="field" placeholder="0"
                       @input="patch('team_registration_fee', $event.target.value)">
            </FormField>
            <FormField label="Free quota (items/student)" hint="0 = no free items">
                <input :value="modelValue.included_items_per_student" type="number" min="0" class="field" placeholder="0"
                       @input="patch('included_items_per_student', $event.target.value)">
            </FormField>
            <FormField label="Free quota (teams/student)" hint="0 = no free teams">
                <input :value="modelValue.included_teams" type="number" min="0" class="field" placeholder="0"
                       @input="patch('included_teams', $event.target.value)">
            </FormField>
            <FormField label="Max participants" hint="Leave blank for no cap">
                <input :value="modelValue.max_participants" type="number" min="0" class="field" placeholder="—"
                       @input="patch('max_participants', $event.target.value)">
            </FormField>
            <FormField label="Max teams" hint="Leave blank for no cap">
                <input :value="modelValue.max_teams" type="number" min="0" class="field" placeholder="—"
                       @input="patch('max_teams', $event.target.value)">
            </FormField>
            <FormField label="Students eligible">
                <SearchableSelect :model-value="modelValue.verification_policy || 'all_students'"
                        :all-option="false" placeholder="Select eligibility"
                        :options="[{ value: 'all_students', label: 'All students' }, { value: 'verified_only', label: 'Verified students only' }]"
                        @update:model-value="value => patch('verification_policy', value)" />
            </FormField>
            <FormField label="Approval">
                <SearchableSelect :model-value="modelValue.approval_policy || 'auto'"
                        :all-option="false" placeholder="Select approval policy"
                        :options="[{ value: 'auto', label: 'Auto (on full payment)' }, { value: 'manual', label: 'Manual review' }]"
                        @update:model-value="value => patch('approval_policy', value)" />
            </FormField>
        </div>
    </div>
</template>

<script setup>
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    modelValue: { type: Object, required: true },
    showHelp: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

function patch(key, raw) {
    const numericKeys = [
        'school_registration_fee',
        'student_registration_fee',
        'team_registration_fee',
        'included_items_per_student',
        'included_teams',
        'max_participants',
        'max_teams',
    ];
    let value = raw;
    if (numericKeys.includes(key)) {
        value = raw === '' || raw === null || raw === undefined ? '' : Number(raw);
    }
    emit('update:modelValue', { ...props.modelValue, [key]: value });
}
</script>
