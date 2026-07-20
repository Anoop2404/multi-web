<template>
    <div class="space-y-6">
        <!-- Section 1: Age Reference Date & Cutoff Rules -->
        <section class="card !p-5 space-y-4 border border-slate-200">
            <div class="border-b border-slate-100 pb-3">
                <h3 class="section-title !mb-0 flex items-center gap-2 text-base">
                    <span>📅</span> Age Reference Cutoff Date
                </h3>
                <p class="section-desc mt-0.5">
                    Student age for U14, U17, U19, etc. is calculated on this cutoff date — not on the day of the competition.
                </p>
            </div>

            <!-- Effective Date Banner -->
            <div class="rounded-xl border border-indigo-200/80 bg-indigo-50/50 p-4 text-xs text-indigo-950 shadow-sm space-y-1">
                <p class="font-bold text-indigo-900 text-sm">Currently Effective Cutoff</p>
                <p class="text-indigo-900/90 font-medium">{{ ageRuleSummary }}</p>
                <p v-if="!eligibilityForm.sports_age_cutoff_date" class="text-[11px] text-indigo-700/80">
                    No custom date set — using CBSE default standard ({{ defaultCutoffLabel }}).
                </p>
            </div>

            <form @submit.prevent="saveEligibility" class="space-y-3 pt-1">
                <div>
                    <label class="form-label text-xs">Custom Age Reference Date</label>
                    <input v-model="eligibilityForm.sports_age_cutoff_date" type="date" class="field text-xs max-w-sm">
                    <p class="text-[11px] text-slate-500 mt-1">
                        CBSE Default: 31 December of the competition year (e.g. 31 Dec 2026 for a 2026 meet).
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2 pt-1">
                    <button type="button" class="btn-secondary text-xs" @click="applyDefault">
                        Use Suggested Cutoff ({{ suggestedAgeCutoff }})
                    </button>
                    <button type="button" class="btn-secondary text-xs text-slate-600" @click="clearCustom">
                        Reset to System Default
                    </button>
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                    <a :href="`/sahodaya-admin/${sahodaya.id}/sports/age-groups`" class="btn-secondary text-xs font-bold text-indigo-700">
                        Manage Age Categories →
                    </a>
                    <button type="submit" class="btn-primary text-xs !py-1.5 !px-4" :disabled="eligibilityForm.processing">
                        {{ eligibilityForm.processing ? 'Saving...' : 'Save Age Cutoff Settings' }}
                    </button>
                </div>
            </form>
        </section>

        <!-- Section 2: How Age Groups Work -->
        <section class="card !p-5 space-y-3 border border-slate-200 bg-slate-50/50 text-xs">
            <h4 class="font-bold text-slate-900 text-sm flex items-center gap-1.5">
                <span>ℹ️</span> How Age Group Calculations Work
            </h4>
            <ul class="space-y-1.5 text-slate-700">
                <li v-for="row in ageGroupHelp" :key="row.key" class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                    <strong class="text-slate-900">{{ row.label }}</strong> — under {{ row.under }} years old on the cutoff date
                    <span class="text-slate-500 font-mono text-[11px]">(born on or after {{ row.minBirth }})</span>
                </li>
            </ul>
        </section>
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
