<template>
    <div class="card max-w-2xl space-y-5">
        <FormSection title="Age reference date" hint="Student age for U14, U17, U19, etc. is counted on this date — not on the day of the event.">
            <div class="rounded-xl border border-indigo-100 bg-indigo-50/60 px-4 py-3 text-sm text-indigo-900">
                <p class="font-semibold">Currently effective</p>
                <p class="mt-1">{{ ageRuleSummary }}</p>
                <p v-if="!eligibilityForm.sports_age_cutoff_date" class="text-xs text-indigo-700/80 mt-1">
                    No custom date set — using system default ({{ defaultCutoffLabel }}).
                </p>
            </div>

            <FormGrid class="mt-4">
                <FormField label="Custom age reference date" class-extra="sm:col-span-2"
                           hint="CBSE default: 31 December of the competition year. Example: 31 Dec 2026 for a 2026 meet.">
                    <template #default="{ id }">
                        <input :id="id" v-model="eligibilityForm.sports_age_cutoff_date" type="date" class="field">
                    </template>
                </FormField>
            </FormGrid>

            <div class="flex flex-wrap gap-2 mt-3">
                <button type="button" class="btn-secondary text-xs" @click="applyDefault">
                    Use default ({{ suggestedAgeCutoff }})
                </button>
                <button type="button" class="btn-secondary text-xs" @click="clearCustom">
                    Clear — use system default
                </button>
            </div>

            <FormActions class="mt-4">
                <button type="button" class="btn-primary" :disabled="eligibilityForm.processing" @click="saveEligibility">
                    {{ eligibilityForm.processing ? 'Saving…' : 'Save age cutoff' }}
                </button>
                <a :href="`/sahodaya-admin/${sahodaya.id}/sports/age-groups`" class="btn-secondary text-sm">
                    Manage age categories →
                </a>
            </FormActions>
        </FormSection>

        <FormSection title="How age groups work" hint="Applied when registering for items with U14, U17, etc.">
            <ul class="text-sm text-slate-600 space-y-2 list-disc pl-5">
                <li v-for="row in ageGroupHelp" :key="row.key">
                    <strong>{{ row.label }}</strong> — under {{ row.under }} on the reference date
                    <span class="text-slate-400">(born on or after {{ row.minBirth }})</span>
                </li>
            </ul>
            <p class="text-xs text-slate-500 mt-3">
                Default reference date is <strong>31 December</strong> of the competition year (CBSE standard).
                Item registration uses <strong>under-N</strong> rules (U17 = age under 17 on this date).
                Age group definitions (U8–U19) are managed under
                <a :href="`/sahodaya-admin/${sahodaya?.id}/sports/age-groups`" class="link-brand font-semibold">Sports age categories</a>.
            </p>
        </FormSection>
    </div>
</template>

<script setup>
import { inject, unref } from 'vue';

const {
    eligibilityForm,
    ageRuleSummary,
    suggestedAgeCutoff,
    defaultCutoffLabel,
    ageGroupHelp,
    saveEligibility,
    sahodaya,
} = inject('eventSettings');

function applyDefault() {
    eligibilityForm.sports_age_cutoff_date = unref(suggestedAgeCutoff) ?? '';
}

function clearCustom() {
    eligibilityForm.sports_age_cutoff_date = '';
}
</script>
