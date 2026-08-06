<template>
    <SahodayaAdminLayout title="Board Results Settings" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Board Results Settings" eyebrow="Academic Results"
                    description="One place for this academic year's data-entry window, marks totals, topper caps, and ranking rules.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/masters`" class="btn-secondary text-sm">Subjects &amp; Streams</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/verification`" class="btn-secondary text-sm">Verification →</Link>
            </template>
        </PageHeader>

        <!-- YEAR SELECTOR -->
        <div class="card !p-4 mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <label class="form-label text-xs font-bold text-slate-700 mb-0">Academic Year</label>
                <select v-model="selectedYear" @change="onYearChange" class="field text-sm font-bold bg-white w-44">
                    <option v-for="ay in academicYearOptions" :key="ay.id" :value="ay.label">
                        {{ ay.label }}{{ ay.status === 'active' ? ' (Active)' : '' }}
                    </option>
                    <option v-if="!academicYearOptions.some(a => a.label === selectedYear)" :value="selectedYear">{{ selectedYear }}</option>
                </select>
            </div>
            <span class="text-[11px] px-2.5 py-1 rounded-full font-bold uppercase tracking-wide" :class="entryStatusBadgeClass">
                {{ entryStatusLabel }}
            </span>
        </div>

        <!-- SECTION 1: DATA ENTRY WINDOW -->
        <section class="card space-y-4 border-2 border-blue-100 bg-gradient-to-br from-blue-50/30 to-white mb-6">
            <div class="border-b border-blue-100 pb-3">
                <h2 class="text-base font-bold text-gray-900 flex items-center gap-2"><span>🗓</span> Data Entry Window — {{ selectedYear }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    Controls when schools can submit or edit Class X &amp; XII board results and toppers for this year.
                </p>
            </div>

            <label class="flex items-center gap-2.5 bg-white rounded-xl border border-gray-200 p-3.5 cursor-pointer">
                <input type="checkbox" v-model="entryForm.enabled" class="rounded border-gray-300 text-indigo-600 w-4 h-4">
                <span class="text-sm font-bold text-gray-800">Enable board-result data entry for {{ selectedYear }}</span>
            </label>

            <div v-if="entryForm.enabled" class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="form-label mb-1 text-[11px] font-bold text-gray-600 uppercase">Opens</label>
                    <input v-model="entryForm.starts_at" type="date" class="field text-sm" required>
                </div>
                <div>
                    <label class="form-label mb-1 text-[11px] font-bold text-gray-600 uppercase">Closes</label>
                    <input v-model="entryForm.ends_at" type="date" class="field text-sm" required>
                </div>
                <p v-if="entryForm.enabled && (!entryForm.starts_at || !entryForm.ends_at)" class="sm:col-span-2 text-xs font-semibold text-rose-600">
                    Both dates are required to enable entry — without them, entry stays disabled.
                </p>
            </div>
            <p v-else class="text-xs text-gray-500">
                Entry is disabled — schools cannot submit or edit board results for {{ selectedYear }} until this is turned on with a date range.
            </p>

            <button type="button" class="btn-primary text-sm" :disabled="entryForm.processing" @click="saveEntryWindow">
                Save entry window
            </button>
        </section>

        <!-- SECTION 2: MARKS SETTINGS -->
        <section class="card space-y-4 mb-6">
            <div class="border-b border-gray-100 pb-3">
                <h2 class="text-base font-bold text-gray-900 flex items-center gap-2"><span>💯</span> Marks Settings — "out of" totals</h2>
                <p class="text-xs text-gray-500 mt-0.5">Locked per class/stream — schools can't type a different total. Every percentage is computed from this.</p>
            </div>

            <div class="max-w-xs">
                <label class="text-xs font-semibold text-slate-700">Class X — out of</label>
                <input v-model.number="marksForm.class_x_total_marks" type="number" min="1" required class="field mt-1 text-sm">
                <span v-if="classXIsYearSpecific" class="text-[10px] font-bold text-indigo-600 uppercase">Year-specific override</span>
                <span v-else class="text-[10px] font-bold text-gray-400 uppercase">Using global default</span>
            </div>

            <div v-if="streams.length">
                <p class="text-xs font-semibold text-slate-700 mb-2">Class XII — out of, per stream</p>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div v-for="s in streams" :key="s.id" class="border border-slate-200 rounded-lg p-3">
                        <label class="text-xs text-slate-500">{{ s.label }}</label>
                        <input v-model.number="marksForm.streams[s.id]" type="number" min="1" required class="field mt-1 text-sm">
                        <span v-if="streamTotalMarks[s.id]?.is_year_specific" class="text-[10px] font-bold text-indigo-600 uppercase">Year-specific</span>
                        <span v-else class="text-[10px] font-bold text-gray-400 uppercase">Global default</span>
                    </div>
                </div>
            </div>

            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="marksForm.apply_to_all_years" class="rounded border-gray-300 text-indigo-600">
                <span class="text-xs font-semibold text-slate-700">Apply to all academic years (global default) instead of just {{ selectedYear }}</span>
            </label>

            <button type="button" class="btn-primary text-sm" @click="saveMarksConfig">Save marks settings</button>
        </section>

        <!-- SECTION 3: TOPPER CAPS -->
        <section class="card space-y-4 mb-6">
            <div class="border-b border-gray-100 pb-3">
                <h2 class="text-base font-bold text-gray-900 flex items-center gap-2"><span>🏆</span> Topper Caps &amp; Ranking Rules</h2>
                <p class="text-xs text-gray-500 mt-0.5">Top-N, tie-break mode, and rank numbering style — per class, and optionally per stream/subject.</p>
            </div>

            <div v-if="topperConfigs.length" class="overflow-x-auto">
                <table class="data-table w-full text-left text-xs">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 uppercase font-semibold">
                            <th class="py-2 px-3">Class</th>
                            <th class="py-2 px-3">Scope</th>
                            <th class="py-2 px-3">Stream/Subject</th>
                            <th class="py-2 px-3 text-center">Top-N</th>
                            <th class="py-2 px-3">Tie mode</th>
                            <th class="py-2 px-3">Rank style</th>
                            <th class="py-2 px-3">Applies to</th>
                            <th class="py-2 px-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="c in topperConfigs" :key="c.id">
                            <td class="py-2 px-3 font-semibold">{{ c.class ?? 'Both' }}</td>
                            <td class="py-2 px-3 capitalize">{{ c.scope }}</td>
                            <td class="py-2 px-3 text-gray-500">{{ c.stream_id ?? c.subject_id ?? '—' }}</td>
                            <td class="py-2 px-3 text-center font-bold">{{ c.top_n }}</td>
                            <td class="py-2 px-3">{{ c.tie_mode }}</td>
                            <td class="py-2 px-3">{{ c.rank_style }}</td>
                            <td class="py-2 px-3">
                                <span v-if="c.academic_year" class="text-[10px] font-bold text-indigo-600 uppercase">{{ c.academic_year }} only</span>
                                <span v-else class="text-[10px] font-bold text-gray-400 uppercase">All years</span>
                            </td>
                            <td class="py-2 px-3">
                                <button type="button" class="text-indigo-600 hover:underline font-semibold" @click="editCap(c)">Edit</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <form class="bg-white rounded-xl border border-gray-200 p-4 grid sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end" @submit.prevent="saveCap">
                <div>
                    <label class="text-xs font-semibold text-slate-700">Class</label>
                    <select v-model="capForm.class" class="field mt-1 text-sm">
                        <option :value="null">Both</option>
                        <option :value="10">Class X</option>
                        <option :value="12">Class XII</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-700">Scope</label>
                    <select v-model="capForm.scope" class="field mt-1 text-sm">
                        <option value="overall">Overall</option>
                        <option value="stream">Stream</option>
                        <option value="subject">Subject</option>
                    </select>
                </div>
                <div v-if="capForm.scope === 'stream'">
                    <label class="text-xs font-semibold text-slate-700">Stream</label>
                    <select v-model.number="capForm.stream_id" class="field mt-1 text-sm">
                        <option :value="null">— select —</option>
                        <option v-for="s in streams" :key="s.id" :value="s.id">{{ s.label }}</option>
                    </select>
                </div>
                <div v-if="capForm.scope === 'subject'">
                    <label class="text-xs font-semibold text-slate-700">Subject ID</label>
                    <input v-model.number="capForm.subject_id" type="number" class="field mt-1 text-sm" placeholder="Subject ID">
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-700">Top-N</label>
                    <input v-model.number="capForm.top_n" type="number" min="1" max="50" required class="field mt-1 text-sm">
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-700">Tie mode</label>
                    <select v-model="capForm.tie_mode" class="field mt-1 text-sm">
                        <option value="include_group">Include tied group</option>
                        <option value="hard_cap">Hard cap</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-700">Rank style</label>
                    <select v-model="capForm.rank_style" class="field mt-1 text-sm">
                        <option value="competition">Competition (1,2,2,4)</option>
                        <option value="dense">Dense (1,2,2,3)</option>
                        <option value="sequential">Sequential (1,2,3,4)</option>
                    </select>
                </div>
                <div class="lg:col-span-4 flex items-center justify-between gap-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" v-model="capForm.apply_to_all_years" class="rounded border-gray-300 text-indigo-600">
                        <span class="text-xs font-semibold text-slate-700">Apply to all academic years instead of just {{ selectedYear }}</span>
                    </label>
                    <button type="submit" class="btn-primary text-sm shrink-0">Save topper cap</button>
                </div>
            </form>
        </section>

        <!-- SECTION 4: RANKING TOGGLES (GLOBAL) -->
        <section class="card space-y-4 mb-6">
            <div class="border-b border-gray-100 pb-3">
                <h2 class="text-base font-bold text-gray-900 flex items-center gap-2"><span>⚙️</span> Ranking Rules (Sahodaya-wide)</h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    These are structural, not per-year — they apply the same way across every academic year for this Sahodaya.
                </p>
            </div>
            <label class="flex items-center gap-2.5 bg-white rounded-xl border border-gray-200 p-3.5 cursor-pointer">
                <input type="checkbox" v-model="rankingForm.use_common_ranking" class="rounded border-gray-300 text-indigo-600 w-4 h-4">
                <span class="text-sm font-semibold text-gray-800">Use one common ranking for every stream/subject (ignore per-stream/subject overrides)</span>
            </label>
            <label class="flex items-center gap-2.5 bg-white rounded-xl border border-gray-200 p-3.5 cursor-pointer">
                <input type="checkbox" v-model="rankingForm.no_rank" class="rounded border-gray-300 text-indigo-600 w-4 h-4">
                <span class="text-sm font-semibold text-gray-800">No-rank mode — reports drop rank numbers and just order by percentage</span>
            </label>
            <button type="button" class="btn-primary text-sm" @click="saveRankingSettings">Save ranking rules</button>
        </section>

        <!-- SECTION 5: COPY FROM PREVIOUS YEAR -->
        <section class="card space-y-4">
            <div class="border-b border-gray-100 pb-3">
                <h2 class="text-base font-bold text-gray-900 flex items-center gap-2"><span>📋</span> Copy Settings Between Years</h2>
                <p class="text-xs text-gray-500 mt-0.5">Clones marks totals and topper caps that were explicitly set on one year onto another.</p>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="text-xs font-semibold text-slate-700">From year</label>
                    <select v-model="copyForm.from_year" class="field mt-1 text-sm">
                        <option v-for="ay in academicYearOptions" :key="ay.id" :value="ay.label">{{ ay.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-slate-700">To year</label>
                    <select v-model="copyForm.to_year" class="field mt-1 text-sm">
                        <option v-for="ay in academicYearOptions" :key="ay.id" :value="ay.label">{{ ay.label }}</option>
                    </select>
                </div>
                <button type="button" class="btn-secondary text-sm" :disabled="copyForm.from_year === copyForm.to_year" @click="copyPreviousYear">
                    Copy settings →
                </button>
            </div>
        </section>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    academicYear: String,
    academicYearOptions: { type: Array, default: () => [] },
    entryWindow: { type: Object, default: () => ({}) },
    streams: { type: Array, default: () => [] },
    topperConfigs: { type: Array, default: () => [] },
    defaultTopN: { type: Number, default: 500 },
    classXTotalMarks: { type: Number, default: 500 },
    classXIsYearSpecific: { type: Boolean, default: false },
    streamTotalMarks: { type: Object, default: () => ({}) },
    rankingSettings: { type: Object, default: () => ({}) },
});

const base = computed(() => `/sahodaya-admin/${props.sahodaya.id}/board-results`);
const selectedYear = ref(props.academicYear);

function onYearChange() {
    router.get(`${base.value}/settings`, { academic_year: selectedYear.value }, { preserveScroll: true });
}

const currentYearOption = computed(() => props.academicYearOptions.find(a => a.label === props.academicYear));
const entryStatusLabel = computed(() => {
    const status = currentYearOption.value?.entry_status ?? 'open';
    return { open: 'Entry Open', upcoming: 'Entry Upcoming', closed: 'Entry Closed', disabled: 'Entry Disabled' }[status] ?? status;
});
const entryStatusBadgeClass = computed(() => ({
    open: 'bg-emerald-100 text-emerald-800',
    upcoming: 'bg-amber-100 text-amber-800',
    closed: 'bg-rose-100 text-rose-700',
    disabled: 'bg-gray-200 text-gray-700',
}[currentYearOption.value?.entry_status ?? 'open'] ?? 'bg-gray-100 text-gray-700'));

// ── Entry window ─────────────────────────────────────────────────────────
const entryForm = reactive({
    enabled: props.entryWindow?.enabled ?? false,
    starts_at: props.entryWindow?.starts_at ?? '',
    ends_at: props.entryWindow?.ends_at ?? '',
    processing: false,
});

function saveEntryWindow() {
    entryForm.processing = true;
    router.put(`${base.value}/settings/entry-window`, {
        academic_year: selectedYear.value,
        enabled: entryForm.enabled,
        starts_at: entryForm.starts_at || null,
        ends_at: entryForm.ends_at || null,
    }, {
        preserveScroll: true,
        onFinish: () => { entryForm.processing = false; },
    });
}

// ── Marks settings ───────────────────────────────────────────────────────
const marksForm = reactive({
    class_x_total_marks: props.classXTotalMarks ?? 500,
    apply_to_all_years: false,
    streams: {},
});
for (const s of props.streams ?? []) {
    marksForm.streams[s.id] = props.streamTotalMarks?.[s.id]?.total_marks ?? 500;
}

function saveMarksConfig() {
    router.put(`${base.value}/settings/marks-config`, {
        academic_year: selectedYear.value,
        apply_to_all_years: marksForm.apply_to_all_years,
        class_x_total_marks: marksForm.class_x_total_marks,
        streams: Object.entries(marksForm.streams).map(([stream_id, total_marks]) => ({
            stream_id: Number(stream_id),
            total_marks,
        })),
    }, { preserveScroll: true });
}

// ── Topper caps ──────────────────────────────────────────────────────────
const capForm = reactive({
    class: null,
    scope: 'overall',
    stream_id: null,
    subject_id: null,
    top_n: props.defaultTopN ?? 500,
    tie_mode: 'include_group',
    rank_style: 'competition',
    apply_to_all_years: false,
});

function editCap(c) {
    capForm.class = c.class;
    capForm.scope = c.scope;
    capForm.stream_id = c.stream_id;
    capForm.subject_id = c.subject_id;
    capForm.top_n = c.top_n;
    capForm.tie_mode = c.tie_mode;
    capForm.rank_style = c.rank_style;
    capForm.apply_to_all_years = !c.academic_year;
}

function saveCap() {
    router.post(`${base.value}/settings/topper-cap`, {
        academic_year: selectedYear.value,
        apply_to_all_years: capForm.apply_to_all_years,
        class: capForm.class,
        scope: capForm.scope,
        stream_id: capForm.scope === 'stream' ? capForm.stream_id : null,
        subject_id: capForm.scope === 'subject' ? capForm.subject_id : null,
        top_n: capForm.top_n,
        tie_mode: capForm.tie_mode,
        rank_style: capForm.rank_style,
    }, { preserveScroll: true });
}

// ── Ranking toggles (global, existing endpoint) ─────────────────────────
const rankingForm = reactive({
    use_common_ranking: props.rankingSettings?.use_common_ranking ?? false,
    no_rank: props.rankingSettings?.no_rank ?? false,
});

function saveRankingSettings() {
    router.put(`${base.value}/toppers/ranking-settings`, { ...rankingForm }, { preserveScroll: true });
}

// ── Copy from previous year ──────────────────────────────────────────────
const sortedYearLabels = computed(() => props.academicYearOptions.map(a => a.label));
const copyForm = reactive({
    from_year: sortedYearLabels.value[1] ?? sortedYearLabels.value[0] ?? props.academicYear,
    to_year: props.academicYear,
});

function copyPreviousYear() {
    if (copyForm.from_year === copyForm.to_year) return;
    router.post(`${base.value}/settings/copy-previous-year`, { ...copyForm }, { preserveScroll: true });
}
</script>
