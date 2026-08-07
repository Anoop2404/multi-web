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

        <!-- SECTION 1b: PRINCIPAL VERIFICATION -->
        <section class="card space-y-4 border-2 border-emerald-100 bg-gradient-to-br from-emerald-50/30 to-white mb-6">
            <div class="border-b border-emerald-100 pb-3">
                <h2 class="text-base font-bold text-gray-900 flex items-center gap-2"><span>🛡️</span> Principal Verification — {{ selectedYear }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    When required, schools must certify results through Principal/Vice Principal sign-off before they can be submitted to Sahodaya.
                    Recommended: enable only after Principal/Vice Principal accounts are set up for your member schools.
                </p>
            </div>

            <label class="flex items-center gap-2.5 bg-white rounded-xl border border-gray-200 p-3.5 cursor-pointer">
                <input type="checkbox" v-model="certificationForm.required" class="rounded border-gray-300 text-emerald-600 w-4 h-4">
                <span class="text-sm font-bold text-gray-800">Require Principal Verification for {{ selectedYear }}</span>
            </label>
            <p class="text-xs text-gray-500">
                Schools that haven't started certification for a result can still submit it directly until this is enabled. Once a school starts
                Principal Verification for a result, direct submission is always blocked for that result regardless of this setting.
            </p>

            <button type="button" class="btn-primary text-sm" :disabled="certificationForm.processing" @click="saveCertificationRequired">
                Save
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
    certificationRequired: { type: Boolean, default: null },
    streams: { type: Array, default: () => [] },
    classXTotalMarks: { type: Number, default: 500 },
    classXIsYearSpecific: { type: Boolean, default: false },
    streamTotalMarks: { type: Object, default: () => ({}) },
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

// ── Principal Verification (certification) ─────────────────────────────────
const certificationForm = reactive({
    required: props.certificationRequired ?? false,
    processing: false,
});

function saveCertificationRequired() {
    certificationForm.processing = true;
    router.put(`${base.value}/settings/certification-required`, {
        academic_year: selectedYear.value,
        required: certificationForm.required,
    }, {
        preserveScroll: true,
        onFinish: () => { certificationForm.processing = false; },
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
