<template>
    <SahodayaAdminLayout :title="pageTitle" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="pageTitle" eyebrow="Academic Results"
                    description="Auto-computed from every school's submitted toppers — no manual curation. Adjust Top-N / tie handling below and the list recomputes to match.">
            <template #actions>
                <button type="button" @click="printReport" class="btn-secondary text-sm font-bold flex items-center gap-1.5 print:hidden">
                    <span>🖨</span> Download Rank Report
                </button>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/masters`" class="btn-secondary text-sm print:hidden">📚 Subject Master</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/verification`" class="btn-secondary text-sm print:hidden">Verification</Link>
                <button type="button" class="btn-primary text-sm print:hidden" @click="recompute">Recompute now</button>
            </template>
        </PageHeader>

        <div class="flex flex-wrap gap-2 mb-4 print:hidden">
            <Link :href="classHref(10)" class="px-3 py-1.5 rounded-lg text-sm font-semibold border"
                  :class="selectedClass === 10 ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'border-slate-200 text-slate-600'">
                Class X
            </Link>
            <Link :href="classHref(12)" class="px-3 py-1.5 rounded-lg text-sm font-semibold border"
                  :class="selectedClass === 12 ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'border-slate-200 text-slate-600'">
                Class XII
            </Link>
        </div>

        <div class="card !p-4 mb-4 print:hidden">
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

        <!-- Sahodaya-wide toppers (Top-N + tie handling applied) -->
        <section class="mb-8">
            <h2 class="text-sm font-bold text-slate-500 uppercase tracking-wide mb-3">Sahodaya toppers</h2>

            <div v-if="selectedClass === 10" class="card card--flush">
                <div class="px-5 py-4 border-b border-slate-50">
                    <h3 class="font-bold text-slate-800">Sahodaya-wide Class X toppers</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Academic year {{ filters.academic_year }}</p>
                </div>
                <TopperTable :rows="overall" empty-text="No eligible Class X toppers yet — schools must submit their board results with toppers first." />
            </div>

            <div v-else class="space-y-4">
                <div v-for="(rows, stream) in byStream" :key="stream" class="card card--flush">
                    <div class="px-5 py-4 border-b border-slate-50">
                        <h3 class="font-bold text-slate-800">{{ stream }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Academic year {{ filters.academic_year }} · grouped by school</p>
                    </div>
                    <SchoolGroupedTable :rows="rows" empty-text="No toppers in this stream yet." />
                </div>
                <p v-if="!Object.keys(byStream).length" class="text-center text-slate-400 py-10">
                    No eligible Class XII toppers yet — schools must submit their board results with stream + toppers first.
                </p>
            </div>
        </section>

        <!-- Class XII Subject-Wise Toppers -->
        <section v-if="selectedClass === 12" class="mb-8">
            <h2 class="text-sm font-bold text-slate-500 uppercase tracking-wide mb-3">Subject-Wise Top Scorers (Class XII)</h2>
            <div class="card p-5 bg-white border border-gray-200">
                <div v-if="subjectLeaders?.length" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="row in subjectLeaders" :key="row.subject" class="rounded-xl border border-indigo-100 bg-gradient-to-br from-indigo-50/40 to-white p-4 shadow-2xs">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 bg-indigo-100 px-2 py-0.5 rounded">
                                {{ row.subject }}
                            </span>
                            <span class="text-sm font-bold text-emerald-600">{{ row.marks }} / 100</span>
                        </div>
                        <p class="font-bold text-gray-900 text-sm mt-2">{{ row.student_name }}</p>
                        <p class="text-xs text-gray-600 mt-0.5">{{ row.school_name }}</p>
                        <p v-if="row.roll_no" class="text-xs text-gray-400">CBSE Roll No: {{ row.roll_no }}</p>
                    </div>
                </div>

                <div v-else class="p-8 text-center text-gray-400 text-xs">
                    No subject-wise toppers recorded across member schools yet for Class XII (Academic year {{ filters.academic_year }}).
                </div>
            </div>
        </section>

        <!-- 90%+ achievers — threshold-based, not rank-limited -->
        <section>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-bold text-slate-500 uppercase tracking-wide">
                    {{ filters.threshold }}%+ achievers
                </h2>
                <p class="text-xs text-slate-400">Every student at or above the threshold — not capped to Top-N.</p>
            </div>

            <div v-if="selectedClass === 10" class="card card--flush">
                <div class="px-5 py-4 border-b border-slate-50">
                    <h3 class="font-bold text-slate-800">Class X · {{ filters.threshold }}%+</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Academic year {{ filters.academic_year }}</p>
                </div>
                <TopperTable :rows="achieversOverall" empty-text="No Class X students at or above this threshold yet." />
            </div>

            <div v-else class="space-y-4">
                <div v-for="(rows, stream) in achieversByStream" :key="stream" class="card card--flush">
                    <div class="px-5 py-4 border-b border-slate-50">
                        <h3 class="font-bold text-slate-800">{{ stream }} · {{ filters.threshold }}%+</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Academic year {{ filters.academic_year }} · grouped by school</p>
                    </div>
                    <SchoolGroupedTable :rows="rows" empty-text="No students in this stream at or above this threshold yet." />
                </div>
                <p v-if="!Object.keys(achieversByStream).length" class="text-center text-slate-400 py-10">
                    No Class XII students at or above this threshold yet.
                </p>
            </div>
        </section>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, h, reactive, watch } from 'vue';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    selectedClass: { type: Number, default: 10 },
    filters: { type: Object, default: () => ({}) },
    overall: { type: Array, default: () => [] },
    byStream: { type: Object, default: () => ({}) },
    subjectLeaders: { type: Array, default: () => [] },
    achieversOverall: { type: Array, default: () => [] },
    achieversByStream: { type: Object, default: () => ({}) },
    settings: {
        type: Object,
        default: () => ({ overall: { top_n: 5, tie_mode: 'include_group' }, stream: { top_n: 5, tie_mode: 'include_group' } }),
    },
});

const pageTitle = computed(() => props.selectedClass === 12 ? 'Class XII Sahodaya Toppers' : 'Class X Sahodaya Toppers');

function classHref(cls) {
    return `/sahodaya-admin/${props.sahodaya.id}/board-results/toppers?class=${cls}`;
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

function printReport() {
    window.print();
}

// Flat ranked/sorted table — used for Class X lists (no stream to nest under).
const TopperTable = {
    props: { rows: { type: Array, default: () => [] }, emptyText: { type: String, default: 'No toppers yet.' } },
    setup(p) {
        return () => p.rows.length
            ? h('table', { class: 'w-full text-sm' }, [
                h('thead', { class: 'bg-gray-50 text-left text-xs uppercase text-gray-500' }, [
                    h('tr', {}, [
                        h('th', { class: 'p-3' }, 'Rank'),
                        h('th', { class: 'p-3' }, 'Student'),
                        h('th', { class: 'p-3' }, 'School'),
                        h('th', { class: 'p-3' }, 'Admission / Roll'),
                        h('th', { class: 'p-3' }, 'Percentage'),
                        h('th', { class: 'p-3' }, 'Marks'),
                    ]),
                ]),
                h('tbody', {}, p.rows.map((r) => h('tr', { key: r.topper_id ?? r.rank, class: 'border-t' }, [
                    h('td', { class: 'p-3 font-semibold text-[#0f3d7a]' }, `#${r.rank}`),
                    h('td', { class: 'p-3' }, r.student_name ?? '—'),
                    h('td', { class: 'p-3 text-gray-600' }, r.school_name ?? '—'),
                    h('td', { class: 'p-3 text-xs text-gray-500' }, [r.admission_no, r.roll_no].filter(Boolean).join(' · ') || '—'),
                    h('td', { class: 'p-3 font-semibold' }, r.percentage != null ? `${r.percentage}%` : '—'),
                    h('td', { class: 'p-3 text-gray-500' }, (r.marks_obtained != null && r.total_marks != null) ? `${r.marks_obtained}/${r.total_marks}` : '—'),
                ]))),
            ])
            : h('p', { class: 'p-6 text-center text-gray-400 text-sm' }, p.emptyText);
    },
};

// Class XII lists are grouped by school within the (already stream-filtered) rows passed in,
// while still showing each student's rank/percentage inside their school's block.
const SchoolGroupedTable = {
    props: { rows: { type: Array, default: () => [] }, emptyText: { type: String, default: 'No toppers yet.' } },
    setup(p) {
        return () => {
            if (!p.rows.length) {
                return h('p', { class: 'p-6 text-center text-gray-400 text-sm' }, p.emptyText);
            }

            const bySchool = new Map();
            for (const row of p.rows) {
                const key = row.school_name ?? 'Unknown school';
                if (!bySchool.has(key)) bySchool.set(key, []);
                bySchool.get(key).push(row);
            }

            return h('div', { class: 'divide-y divide-slate-50' },
                [...bySchool.entries()].map(([school, rows]) => h('div', { key: school, class: 'px-5 py-3' }, [
                    h('p', { class: 'text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2' }, `${school} (${rows.length})`),
                    h('div', { class: 'space-y-1' }, rows.map((r) => h('div', {
                        key: r.topper_id ?? r.rank,
                        class: 'flex flex-wrap items-center justify-between gap-3 text-sm py-1',
                    }, [
                        h('div', { class: 'flex items-center gap-2' }, [
                            h('span', { class: 'font-semibold text-[#0f3d7a]' }, `#${r.rank}`),
                            h('span', {}, r.student_name ?? '—'),
                            h('span', { class: 'text-xs text-gray-400' }, [r.admission_no, r.roll_no].filter(Boolean).join(' · ')),
                        ]),
                        h('div', { class: 'flex items-center gap-3 text-xs text-gray-500' }, [
                            h('span', { class: 'font-semibold text-gray-700' }, r.percentage != null ? `${r.percentage}%` : '—'),
                            h('span', {}, (r.marks_obtained != null && r.total_marks != null) ? `${r.marks_obtained}/${r.total_marks}` : ''),
                        ]),
                    ]))),
                ])),
            );
        };
    },
};
</script>
