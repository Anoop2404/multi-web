<template>
    <SahodayaAdminLayout :title="pageTitle" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="pageTitle" eyebrow="Academic Results"
                    description="Auto-computed from every school's submitted toppers — no manual curation. Adjust Top-N / tie handling below, then open a report to view the results.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/masters`" class="btn-secondary text-sm">📚 Subject Master</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/verification`" class="btn-secondary text-sm">Verification</Link>
                <button type="button" class="btn-primary text-sm" @click="recompute">Recompute now</button>
            </template>
        </PageHeader>

        <div class="flex flex-wrap items-center gap-3 mb-4">
            <Link :href="classHref(10)" class="px-3 py-1.5 rounded-lg text-sm font-semibold border"
                  :class="selectedClass === 10 ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'border-slate-200 text-slate-600'">
                Class X
            </Link>
            <Link :href="classHref(12)" class="px-3 py-1.5 rounded-lg text-sm font-semibold border"
                  :class="selectedClass === 12 ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'border-slate-200 text-slate-600'">
                Class XII
            </Link>
            <span class="text-xs text-slate-300 mx-1">|</span>
            <select class="field text-xs py-1.5" :value="filters.academic_year" @change="switchYear($event.target.value)">
                <option v-for="ay in academicYearOptions" :key="ay.id" :value="ay.label" :disabled="ay.status === 'closed'">
                    {{ ay.label }}
                </option>
            </select>
            <select v-if="selectedClass === 12 && streamEntries.length" class="field text-xs py-1.5 min-w-40" :value="selectedStreamCode" @change="switchStream($event.target.value)">
                <option v-for="[code, label] in streamEntries" :key="code" :value="code">{{ label }}</option>
            </select>
        </div>

        <div class="card !p-4 mb-6">
            <h3 class="text-sm font-semibold text-slate-800 mb-2">
                {{ selectedClass === 12 ? `${selectedStreamLabel || 'Selected stream'} Top-N settings` : 'Overall Top-N settings' }}
            </h3>
            <p class="text-xs text-slate-500 mb-3">
                Top-N is a target, not a hard count — "Include rank cutoff" keeps every student whose rank is within the Top-N cutoff (list may exceed Top-N); "Hard cap" always truncates to exactly Top-N rows. Rank style controls whether tied scores appear as competition, dense, or sequential ranks.
            </p>
            <form class="flex flex-wrap gap-3 items-end" @submit.prevent="saveSettings">
                <div>
                    <label class="form-label mb-1 text-xs">Top N</label>
                    <input v-model.number="settingsForm.top_n" type="number" min="1" max="50" class="field text-sm w-24" required>
                </div>
                <div>
                    <label class="form-label mb-1 text-xs">Tie handling</label>
                    <select v-model="settingsForm.tie_mode" class="field text-sm">
                        <option value="include_group">Include rank cutoff</option>
                        <option value="hard_cap">Hard cap</option>
                    </select>
                </div>
                <div>
                    <label class="form-label mb-1 text-xs">Rank style</label>
                    <select v-model="settingsForm.rank_style" class="field text-sm">
                        <option v-for="opt in rankStyleOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                </div>
                <button type="submit" class="btn-secondary text-xs">Save settings</button>
            </form>

            <div v-if="selectedClass === 12" class="mt-4 grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <button
                    v-for="[code, label] in streamEntries"
                    :key="code"
                    type="button"
                    class="text-left rounded-xl border p-3 transition"
                    :class="selectedStreamCode === code ? 'border-[#0f3d7a] bg-[#0f3d7a]/5' : 'border-slate-200 hover:border-slate-300'"
                    @click="switchStream(code)"
                >
                    <p class="text-sm font-bold text-slate-900">{{ label }}</p>
                    <p class="text-[11px] text-slate-500 mt-1">
                        Top-N {{ streamSettings[code]?.top_n ?? 5 }} · {{ streamSettings[code]?.tie_mode === 'hard_cap' ? 'Hard cap' : 'Include rank cutoff' }} · {{ rankStyleLabel(streamSettings[code]?.rank_style) }}
                    </p>
                </button>
            </div>
        </div>

        <div class="card !p-4 mb-6" v-if="subjectEntries.length">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div>
                    <h3 class="text-sm font-semibold text-slate-800 mb-1">
                        Subject-wise rank settings
                    </h3>
                    <p class="text-xs text-slate-500">
                        Configure Top-N and tie handling per subject for the selected class.
                    </p>
                </div>
                <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-slate-100 text-slate-600">
                    {{ selectedClass === 12 ? 'Class XII' : 'Class X' }}
                </span>
            </div>

            <div class="grid gap-3 lg:grid-cols-[minmax(0,2fr)_1fr_1fr_1fr_auto] items-end">
                <div class="relative">
                    <label class="form-label mb-1 text-xs">Subject</label>
                    <button
                        type="button"
                        class="field text-sm bg-white font-semibold w-full flex items-center justify-between gap-2"
                        @click="toggleSubjectDropdown"
                    >
                        <span class="truncate">{{ selectedSubjectLabel || 'Select subject' }}</span>
                        <span class="text-slate-400">▾</span>
                    </button>

                    <div
                        v-if="subjectDropdownOpen"
                        class="absolute z-30 mt-2 w-full rounded-xl border border-slate-200 bg-white shadow-xl p-3"
                    >
                        <input
                            v-model="subjectSearch"
                            type="text"
                            class="field text-xs w-full bg-slate-50"
                            placeholder="Search subjects..."
                            @input="subjectDropdownOpen = true"
                        >

                        <div class="mt-2 max-h-56 overflow-y-auto space-y-1">
                            <button
                                v-for="subject in filteredSubjectEntries"
                                :key="subject.id"
                                type="button"
                                class="w-full text-left px-3 py-2 rounded-lg text-xs font-semibold transition"
                                :class="selectedSubjectKey === subject.id ? 'bg-[#0f3d7a] text-white' : 'hover:bg-slate-50 text-slate-700'"
                                @click="selectSubject(subject.id)"
                            >
                                {{ subject.label }}
                            </button>

                            <p v-if="!filteredSubjectEntries.length" class="px-3 py-2 text-xs text-slate-400">
                                No matching subjects.
                            </p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="form-label mb-1 text-xs">Top N</label>
                    <input v-model.number="subjectSettingsForm.top_n" type="number" min="1" max="50" class="field text-sm w-full" required>
                </div>

                <div>
                    <label class="form-label mb-1 text-xs">Tie handling</label>
                    <select v-model="subjectSettingsForm.tie_mode" class="field text-sm w-full">
                        <option value="include_group">Include rank cutoff</option>
                        <option value="hard_cap">Hard cap</option>
                    </select>
                </div>

                <div>
                    <label class="form-label mb-1 text-xs">Rank style</label>
                    <select v-model="subjectSettingsForm.rank_style" class="field text-sm w-full">
                        <option v-for="opt in rankStyleOptions" :key="`subject-${opt.value}`" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                </div>

                <button type="button" class="btn-secondary text-xs" @click="saveSubjectSettings">
                    Save subject settings
                </button>
            </div>
        </div>

        <h2 class="text-sm font-bold text-slate-500 uppercase tracking-wide mb-3">Reports</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/toppers/overall?class=${selectedClass}&academic_year=${filters.academic_year}${selectedClass === 12 && selectedStreamCode ? `&stream=${selectedStreamCode}` : ''}`"
                  class="card p-5 hover:shadow-md transition block">
                <p class="text-2xl mb-2">🏆</p>
                <h3 class="font-bold text-slate-800">Overall Result</h3>
                <p class="text-xs text-slate-500 mt-1">
                    Sahodaya-wide toppers — {{ selectedClass === 12 ? 'per stream, grouped by school' : 'flat ranked list' }}.
                </p>
            </Link>

            <Link v-if="selectedClass === 12" :href="`/sahodaya-admin/${sahodaya.id}/board-results/toppers/subject-wise?academic_year=${filters.academic_year}&stream=${selectedStreamCode}`"
                  class="card p-5 hover:shadow-md transition block">
                <p class="text-2xl mb-2">🎯</p>
                <h3 class="font-bold text-slate-800">Subject-Wise Top Scorers</h3>
                <p class="text-xs text-slate-500 mt-1">Highest scorer per subject, across every member school.</p>
            </Link>
            <div v-else class="card p-5 opacity-50">
                <p class="text-2xl mb-2">🎯</p>
                <h3 class="font-bold text-slate-800">Subject-Wise Top Scorers</h3>
                <p class="text-xs text-slate-500 mt-1">Class XII only.</p>
            </div>

            <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/toppers/achievers?class=${selectedClass}&academic_year=${filters.academic_year}${selectedClass === 12 && selectedStreamCode ? `&stream=${selectedStreamCode}` : ''}`"
                  class="card p-5 hover:shadow-md transition block">
                <p class="text-2xl mb-2">🌟</p>
                <h3 class="font-bold text-slate-800">90%+ Achievers</h3>
                <p class="text-xs text-slate-500 mt-1">Every student at or above the threshold — not capped to Top-N.</p>
            </Link>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    selectedClass: { type: Number, default: 10 },
    filters: { type: Object, default: () => ({}) },
    academicYearOptions: { type: Array, default: () => [] },
    settings: {
        type: Object,
        default: () => ({ overall: { top_n: 5, tie_mode: 'include_group', rank_style: 'competition' }, streams: {} }),
    },
    streamOptions: { type: Object, default: () => ({}) },
    subjectOptions: { type: Array, default: () => [] },
    selectedStream: { type: String, default: null },
    selectedSubjectId: { type: [String, Number], default: null },
});

const pageTitle = computed(() => props.selectedClass === 12 ? 'Class XII Sahodaya Toppers' : 'Class X Sahodaya Toppers');

function classHref(cls) {
    const stream = cls === 12 && selectedStreamCode.value ? `&stream=${selectedStreamCode.value}` : '';
    const subject = selectedSubjectKey.value ? `&subject_id=${selectedSubjectKey.value}` : '';
    return `/sahodaya-admin/${props.sahodaya.id}/board-results/toppers?class=${cls}&academic_year=${props.filters.academic_year}${stream}${subject}`;
}

function switchYear(year) {
    const stream = props.selectedClass === 12 && selectedStreamCode.value ? `&stream=${selectedStreamCode.value}` : '';
    const subject = selectedSubjectKey.value ? `&subject_id=${selectedSubjectKey.value}` : '';
    router.get(`/sahodaya-admin/${props.sahodaya.id}/board-results/toppers?class=${props.selectedClass}&academic_year=${year}${stream}${subject}`);
}

const streamEntries = computed(() => Object.entries(props.streamOptions ?? {}));
const subjectEntries = computed(() => (props.subjectOptions ?? []).map(subject => ({
    id: String(subject.id),
    label: subject.label,
})));
const selectedStreamCode = computed(() => {
    if (props.selectedClass !== 12 || !streamEntries.value.length) return null;
    if (props.selectedStream && props.streamOptions?.[props.selectedStream]) return props.selectedStream;
    return streamEntries.value[0]?.[0] ?? null;
});
const selectedStreamLabel = computed(() => selectedStreamCode.value ? props.streamOptions?.[selectedStreamCode.value] ?? null : null);
const selectedSubjectKey = ref(null);
const selectedSubjectLabel = computed(() => {
    const entry = subjectEntries.value.find(subject => subject.id === selectedSubjectKey.value);
    return entry?.label ?? null;
});
const scopeKey = computed(() => props.selectedClass === 12 ? 'stream' : 'overall');
const streamSettings = computed(() => props.settings.streams ?? {});
const subjectSettings = computed(() => props.settings.subjects ?? {});
const rankStyleOptions = [
    { value: 'competition', label: 'Competition (1,2,2,2,5)' },
    { value: 'dense', label: 'Dense (1,2,2,2,3)' },
    { value: 'sequential', label: 'Sequential (1,2,3,4,5)' },
];

function rankStyleLabel(value) {
    return rankStyleOptions.find(opt => opt.value === value)?.label ?? 'Competition (1,2,2,2,5)';
}

const subjectSearch = ref('');
const subjectDropdownOpen = ref(false);
const filteredSubjectEntries = computed(() => {
    const q = subjectSearch.value.trim().toLowerCase();
    if (!q) return subjectEntries.value;
    return subjectEntries.value.filter(subject => subject.label.toLowerCase().includes(q));
});

const settingsForm = reactive({
    top_n: props.selectedClass === 12
        ? (streamSettings.value[selectedStreamCode.value || '']?.top_n ?? 5)
        : (props.settings[scopeKey.value]?.top_n ?? 5),
    tie_mode: props.selectedClass === 12
        ? (streamSettings.value[selectedStreamCode.value || '']?.tie_mode ?? 'include_group')
    : (props.settings[scopeKey.value]?.tie_mode ?? 'include_group'),
    rank_style: props.selectedClass === 12
        ? (streamSettings.value[selectedStreamCode.value || '']?.rank_style ?? 'competition')
        : (props.settings[scopeKey.value]?.rank_style ?? 'competition'),
});

const subjectSettingsForm = reactive({
    top_n: 5,
    tie_mode: 'include_group',
    rank_style: 'competition',
});

watch(subjectEntries, () => {
    if (subjectEntries.value.length === 0) {
        selectedSubjectKey.value = null;
        return;
    }

    const candidate = props.selectedSubjectId == null ? null : String(props.selectedSubjectId);
    const next = candidate && subjectEntries.value.some(subject => subject.id === candidate)
        ? candidate
        : subjectEntries.value[0].id;
    selectedSubjectKey.value = next;
}, { immediate: true });

watch([() => props.selectedClass, selectedStreamCode], () => {
    settingsForm.top_n = props.selectedClass === 12
        ? (streamSettings.value[selectedStreamCode.value || '']?.top_n ?? 5)
        : (props.settings[scopeKey.value]?.top_n ?? 5);
    settingsForm.tie_mode = props.selectedClass === 12
        ? (streamSettings.value[selectedStreamCode.value || '']?.tie_mode ?? 'include_group')
        : (props.settings[scopeKey.value]?.tie_mode ?? 'include_group');
    settingsForm.rank_style = props.selectedClass === 12
        ? (streamSettings.value[selectedStreamCode.value || '']?.rank_style ?? 'competition')
        : (props.settings[scopeKey.value]?.rank_style ?? 'competition');
});

watch(selectedSubjectKey, () => {
    subjectSettingsForm.top_n = subjectSettings.value[selectedSubjectKey.value || '']?.top_n ?? 5;
    subjectSettingsForm.tie_mode = subjectSettings.value[selectedSubjectKey.value || '']?.tie_mode ?? 'include_group';
    subjectSettingsForm.rank_style = subjectSettings.value[selectedSubjectKey.value || '']?.rank_style ?? 'competition';
    subjectSearch.value = selectedSubjectLabel.value || '';
}, { immediate: true });

watch(() => props.subjectOptions, () => {
    subjectSearch.value = selectedSubjectLabel.value || '';
}, { immediate: true });

function saveSettings() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/board-results/topper-cap`, {
        class: props.selectedClass,
        scope: scopeKey.value,
        stream_id: props.selectedClass === 12 && selectedStreamCode.value
            ? (streamSettings.value[selectedStreamCode.value]?.stream_id ?? null)
            : null,
        top_n: settingsForm.top_n,
        tie_mode: settingsForm.tie_mode,
        rank_style: settingsForm.rank_style,
    }, { preserveScroll: true });
}

function saveSubjectSettings() {
    if (!selectedSubjectKey.value) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/board-results/topper-cap`, {
        class: props.selectedClass,
        scope: 'subject',
        subject_id: Number(selectedSubjectKey.value),
        top_n: subjectSettingsForm.top_n,
        tie_mode: subjectSettingsForm.tie_mode,
        rank_style: subjectSettingsForm.rank_style,
    }, { preserveScroll: true });
}

function switchStream(code) {
    const subject = selectedSubjectKey.value ? `&subject_id=${selectedSubjectKey.value}` : '';
    router.get(`/sahodaya-admin/${props.sahodaya.id}/board-results/toppers?class=12&academic_year=${props.filters.academic_year}&stream=${code}${subject}`);
}

function toggleSubjectDropdown() {
    if (subjectDropdownOpen.value) {
        subjectDropdownOpen.value = false;
        return;
    }
    subjectSearch.value = selectedSubjectLabel.value || '';
    subjectDropdownOpen.value = true;
}

function selectSubject(id) {
    subjectSearch.value = subjectEntries.value.find(subject => subject.id === id)?.label || '';
    subjectDropdownOpen.value = false;
    selectedSubjectKey.value = id || null;
    subjectSettingsForm.top_n = subjectSettings.value[id]?.top_n ?? 5;
    subjectSettingsForm.tie_mode = subjectSettings.value[id]?.tie_mode ?? 'include_group';
    subjectSettingsForm.rank_style = subjectSettings.value[id]?.rank_style ?? 'competition';
}

function recompute() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/board-results/toppers/recompute`, {
        academic_year: props.filters.academic_year,
    }, { preserveScroll: true });
}
</script>
