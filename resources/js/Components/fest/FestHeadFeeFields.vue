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
                <select :value="modelValue.verification_policy || 'all_students'" class="field"
                        @change="patch('verification_policy', $event.target.value)">
                    <option value="all_students">All students</option>
                    <option value="verified_only">Verified students only</option>
                </select>
            </FormField>
            <FormField label="Approval">
                <select :value="modelValue.approval_policy || 'auto'" class="field"
                        @change="patch('approval_policy', $event.target.value)">
                    <option value="auto">Auto (on full payment)</option>
                    <option value="manual">Manual review</option>
                </select>
            </FormField>
        </div>
    </div>
</template>

<script setup>
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
