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
        </div>

        <div class="card !p-4 mb-6">
            <h3 class="text-sm font-semibold text-slate-800 mb-2">
                {{ selectedClass === 12 ? 'Stream Top-N settings' : 'Overall Top-N settings' }}
            </h3>
            <p class="text-xs text-slate-500 mb-3">
                Top-N is a target, not a hard count — "Include tie group" keeps every student sharing the cutoff rank (list may exceed Top-N); "Hard cap" always truncates to exactly Top-N.
            </p>
            <form class="flex flex-wrap gap-3 items-end" @submit.prevent="saveSettings">
                <div>
                    <label class="form-label mb-1 text-xs">Top N</label>
                    <input v-model.number="settingsForm.top_n" type="number" min="1" max="50" class="field text-sm w-24" required>
                </div>
                <div>
                    <label class="form-label mb-1 text-xs">Tie handling</label>
                    <select v-model="settingsForm.tie_mode" class="field text-sm">
                        <option value="include_group">Include tie group</option>
                        <option value="hard_cap">Hard cap</option>
                    </select>
                </div>
                <button type="submit" class="btn-secondary text-xs">Save settings</button>
            </form>
        </div>

        <h2 class="text-sm font-bold text-slate-500 uppercase tracking-wide mb-3">Reports</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/toppers/overall?class=${selectedClass}&academic_year=${filters.academic_year}`"
                  class="card p-5 hover:shadow-md transition block">
                <p class="text-2xl mb-2">🏆</p>
                <h3 class="font-bold text-slate-800">Overall Result</h3>
                <p class="text-xs text-slate-500 mt-1">
                    Sahodaya-wide toppers — {{ selectedClass === 12 ? 'per stream, grouped by school' : 'flat ranked list' }}.
                </p>
            </Link>

            <Link v-if="selectedClass === 12" :href="`/sahodaya-admin/${sahodaya.id}/board-results/toppers/subject-wise?academic_year=${filters.academic_year}`"
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

            <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/toppers/achievers?class=${selectedClass}&academic_year=${filters.academic_year}`"
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
import { computed, reactive, watch } from 'vue';
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
        default: () => ({ overall: { top_n: 5, tie_mode: 'include_group' }, stream: { top_n: 5, tie_mode: 'include_group' } }),
    },
});

const pageTitle = computed(() => props.selectedClass === 12 ? 'Class XII Sahodaya Toppers' : 'Class X Sahodaya Toppers');

function classHref(cls) {
    return `/sahodaya-admin/${props.sahodaya.id}/board-results/toppers?class=${cls}&academic_year=${props.filters.academic_year}`;
}

function switchYear(year) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/board-results/toppers?class=${props.selectedClass}&academic_year=${year}`);
}

const scopeKey = computed(() => props.selectedClass === 12 ? 'stream' : 'overall');

const settingsForm = reactive({
    top_n: props.settings[scopeKey.value]?.top_n ?? 5,
    tie_mode: props.settings[scopeKey.value]?.tie_mode ?? 'include_group',
});

watch(() => props.selectedClass, () => {
    settingsForm.top_n = props.settings[scopeKey.value]?.top_n ?? 5;
    settingsForm.tie_mode = props.settings[scopeKey.value]?.tie_mode ?? 'include_group';
});

function saveSettings() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/board-results/topper-cap`, {
        class: props.selectedClass,
        scope: scopeKey.value,
        top_n: settingsForm.top_n,
        tie_mode: settingsForm.tie_mode,
    }, { preserveScroll: true });
}

function recompute() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/board-results/toppers/recompute`, {
        academic_year: props.filters.academic_year,
    }, { preserveScroll: true });
}
</script>
