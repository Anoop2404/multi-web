<template>
    <div class="space-y-4">
        <div>
            <h3 class="section-title">{{ title }}</h3>
            <p class="section-desc">{{ description }}</p>
        </div>

        <div class="rounded-lg border px-3 py-2 text-sm flex flex-wrap items-center justify-between gap-2"
             :class="statusBadgeClass">
            <span class="font-semibold">{{ statusBadgeTitle }}</span>
            <span class="text-xs opacity-80">{{ visibilityHint }}</span>
        </div>

        <FormGrid>
            <FormField label="When students see results" class-extra="sm:col-span-2"
                       hint="Choose whether students see results immediately after completion of exam or when authorized/published.">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-1">
                    <label
                        class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition"
                        :class="local.results_release_mode === 'on_publish' ? 'border-primary-500 bg-primary-50/40 ring-1 ring-primary-500' : 'border-slate-200 bg-white hover:border-slate-300'"
                    >
                        <input
                            type="radio"
                            v-model="local.results_release_mode"
                            value="on_publish"
                            class="mt-1 text-primary-600 focus:ring-primary-500"
                        />
                        <div class="text-xs">
                            <span class="font-bold text-slate-800 block text-sm">When published</span>
                            <span class="text-slate-500 mt-0.5 block">Held until coordinator publishes results (or scheduled auto-release). Recommended for competitive / official tests.</span>
                        </div>
                    </label>

                    <label
                        class="flex items-start gap-3 p-3 rounded-xl border cursor-pointer transition"
                        :class="local.results_release_mode === 'after_completion' ? 'border-primary-500 bg-primary-50/40 ring-1 ring-primary-500' : 'border-slate-200 bg-white hover:border-slate-300'"
                    >
                        <input
                            type="radio"
                            v-model="local.results_release_mode"
                            value="after_completion"
                            class="mt-1 text-primary-600 focus:ring-primary-500"
                        />
                        <div class="text-xs">
                            <span class="font-bold text-slate-800 block text-sm">After completion of exam</span>
                            <span class="text-slate-500 mt-0.5 block">Shown immediately when the candidate submits their exam. Recommended for practice & school model exams.</span>
                        </div>
                    </label>
                </div>
            </FormField>

            <FormField label="What students see when released" class-extra="sm:col-span-2"
                       hint="Use Score only for high-stakes exams. Review reveals correct answers.">
                <SearchableSelect
                    v-model="local.result_visibility"
                    :options="visibilityOptions"
                    :all-option="false"
                />
            </FormField>
            <FormField v-if="local.results_release_mode === 'on_publish'"
                       label="Auto-release at"
                       class-extra="sm:col-span-2"
                       hint="Optional. Releases scores automatically at this time (server schedule). Leave blank for manual release only.">
                <input v-model="local.results_auto_release_at" type="datetime-local" class="field">
            </FormField>
        </FormGrid>

        <div class="border-t border-slate-100 pt-4 space-y-3">
            <h4 class="text-sm font-semibold text-slate-800">Certificates</h4>
            <label class="flex items-start gap-2 text-sm text-slate-700">
                <input v-model="local.certificates_enabled" type="checkbox" class="mt-1 rounded border-slate-300">
                <span>
                    <span class="font-medium">Enable certificates</span>
                    <span class="block text-xs text-slate-500">Students can download when results are released and they meet eligibility.</span>
                </span>
            </label>

            <template v-if="local.certificates_enabled">
                <FormGrid>
                    <FormField label="Certificate template" hint="Reuses Sahodaya Talent Search / MCQ certificate templates.">
                        <SearchableSelect
                            v-model="local.certificate_template_id"
                            :options="templateOptions"
                            :all-option="true"
                            all-label="Default certificate template"
                        />
                    </FormField>
                    <FormField label="Eligibility">
                        <SearchableSelect
                            v-model="local.certificate_eligibility"
                            :options="eligibilityOptions"
                            :all-option="false"
                        />
                    </FormField>
                </FormGrid>

                <div v-if="local.certificate_eligibility === 'grade_bands'" class="flex flex-wrap gap-2">
                    <label v-for="band in gradeBands" :key="band.label"
                           class="inline-flex items-center gap-1.5 text-xs px-2 py-1 rounded border border-slate-200 bg-white">
                        <input type="checkbox" class="rounded border-slate-300"
                                :value="band.label"
                                :checked="local.certificate_eligible_grades.includes(band.label)"
                                @change="toggleGrade(band.label, $event.target.checked)">
                        {{ band.label }}
                    </label>
                    <p v-if="!gradeBands?.length" class="text-xs text-slate-500">No grade bands configured — set a grade master first.</p>
                </div>

                <label class="flex items-start gap-2 text-sm text-slate-700">
                    <input v-model="local.certificates_auto_issue" type="checkbox" class="mt-1 rounded border-slate-300">
                    <span>
                        <span class="font-medium">Auto-issue on result release</span>
                        <span class="block text-xs text-slate-500">Otherwise generate manually after publishing results.</span>
                    </span>
                </label>
            </template>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, watch } from 'vue';

const props = defineProps({
    modelValue: { type: Object, required: true },
    resultsPublished: { type: Boolean, default: false },
    certificateTemplates: { type: Array, default: () => [] },
    gradeBands: { type: Array, default: () => [] },
    title: { type: String, default: 'Result publish settings' },
    description: {
        type: String,
        default: 'Control when scores appear for students and whether certificates are issued.',
    },
});

const emit = defineEmits(['update:modelValue']);

const local = reactive({
    result_visibility: props.modelValue.result_visibility ?? 'score_grade',
    results_release_mode: props.modelValue.results_release_mode ?? 'on_publish',
    results_auto_release_at: props.modelValue.results_auto_release_at ?? '',
    certificates_enabled: !!props.modelValue.certificates_enabled,
    certificates_auto_issue: !!props.modelValue.certificates_auto_issue,
    certificate_eligibility: props.modelValue.certificate_eligibility ?? 'participation',
    certificate_eligible_grades: [...(props.modelValue.certificate_eligible_grades ?? [])],
    certificate_template_id: props.modelValue.certificate_template_id ?? null,
});

watch(local, () => {
    emit('update:modelValue', { ...local, certificate_eligible_grades: [...local.certificate_eligible_grades] });
}, { deep: true });

watch(() => props.modelValue, (next) => {
    if (!next) return;
    if (next.results_release_mode !== undefined && next.results_release_mode !== local.results_release_mode) {
        local.results_release_mode = next.results_release_mode;
    }
    if (next.result_visibility !== undefined && next.result_visibility !== local.result_visibility) {
        local.result_visibility = next.result_visibility;
    }
    if (next.results_auto_release_at !== undefined && next.results_auto_release_at !== local.results_auto_release_at) {
        local.results_auto_release_at = next.results_auto_release_at ?? '';
    }
}, { deep: true });

const statusBadgeClass = computed(() => {
    if (props.resultsPublished) {
        return 'border-emerald-200 bg-emerald-50 text-emerald-900';
    }
    if (local.results_release_mode === 'after_completion') {
        return 'border-sky-200 bg-sky-50 text-sky-900';
    }
    return 'border-amber-200 bg-amber-50 text-amber-900';
});

const statusBadgeTitle = computed(() => {
    if (props.resultsPublished) {
        return 'Results published — all students can see scores';
    }
    if (local.results_release_mode === 'after_completion') {
        return 'Instant release — students see results immediately after completion of exam';
    }
    return 'Results held — students cannot see scores until published';
});

const visibilityOptions = [
    { value: 'score', label: 'Score only (no grade / rank / review)' },
    { value: 'score_grade', label: 'Score & grade band' },
    { value: 'review', label: 'Score, grade & per-question review' },
];

const eligibilityOptions = [
    { value: 'participation', label: 'Participation (any submitted mark)' },
    { value: 'pass', label: 'Pass mark / passing grade' },
    { value: 'grade_bands', label: 'Selected grade bands only' },
];

const templateOptions = computed(() => props.certificateTemplates.map((t) => ({
    value: t.id,
    label: t.is_default ? `${t.title} (default)` : t.title,
})));

const visibilityHint = computed(() => {
    const map = {
        score: 'Students see score only',
        score_grade: 'Students see score + grade',
        review: 'Students see score, grade & answer review',
    };
    return map[local.result_visibility] || '';
});

function toggleGrade(label, checked) {
    const set = new Set(local.certificate_eligible_grades);
    if (checked) set.add(label);
    else set.delete(label);
    local.certificate_eligible_grades = [...set];
}
</script>
