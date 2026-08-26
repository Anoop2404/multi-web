<template>
    <SahodayaAdminLayout title="Topper Reports" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Topper Reports" eyebrow="Academic Results"
                    description="One menu for every topper-style report — pick an academic year, then open a report or jump straight to its PDF.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/toppers`" class="btn-secondary text-sm">⚙ Ranking Settings</Link>
            </template>
        </PageHeader>

        <div class="card !p-4 mb-6 flex flex-wrap items-center gap-3">
            <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Academic Year</label>
            <SearchableSelect
                class="min-w-40"
                :model-value="selectedYear"
                @update:model-value="switchYear"
                :options="academicYearSelectOptions"
                :all-option="false"
                placeholder="Select academic year"
            />
            <p class="text-xs text-slate-400">
                Applies to every report link below. Each report page also has its own Rank / Percentage toggle and PDF download.
            </p>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div class="card p-5">
                <p class="text-2xl mb-2">🏆</p>
                <h3 class="font-bold text-slate-800">Class X Overall</h3>
                <p class="text-xs text-slate-500 mt-1 mb-4">Sahodaya-wide Class X (AISSE) toppers — flat ranked list, or percentage order.</p>
                <div class="flex flex-wrap gap-2">
                    <Link :href="overallHref(10)" class="btn-secondary text-xs">Open report →</Link>
                    <button type="button" class="btn-secondary text-xs" @click="openPreview('toppers', 'Class X Overall — PDF Preview')">👁 Preview</button>
                    <a :href="pdfHref('toppers')" class="btn-secondary text-xs">📥 PDF</a>
                </div>
            </div>

            <div class="card p-5">
                <p class="text-2xl mb-2">🎓</p>
                <h3 class="font-bold text-slate-800">Class XII Stream-Wise</h3>
                <p class="text-xs text-slate-500 mt-1 mb-4">Sahodaya-wide Class XII (AISSCE) toppers, grouped by stream.</p>
                <div class="flex flex-wrap gap-2">
                    <Link :href="overallHref(12)" class="btn-secondary text-xs">Open report →</Link>
                    <button type="button" class="btn-secondary text-xs" @click="openPreview('toppers', 'Class XII Stream-Wise — PDF Preview')">👁 Preview</button>
                    <a :href="pdfHref('toppers')" class="btn-secondary text-xs">📥 PDF</a>
                </div>
                <p class="text-[11px] text-slate-400 mt-2">PDF covers Class X & XII together (same combined report as the Class X card).</p>
            </div>

            <div class="card p-5">
                <p class="text-2xl mb-2">🌟</p>
                <h3 class="font-bold text-slate-800">Full A1 Achievers</h3>
                <p class="text-xs text-slate-500 mt-1 mb-4">Students who scored A1 in every subject entered, Class X & XII, all streams.</p>
                <div class="flex flex-wrap gap-2">
                    <Link :href="fullA1Href" class="btn-secondary text-xs">Open report →</Link>
                    <button type="button" class="btn-secondary text-xs" @click="openPreview('full-a1', 'Full A1 Achievers — PDF Preview')">👁 Preview</button>
                    <a :href="pdfHref('full-a1')" class="btn-secondary text-xs">📥 PDF</a>
                </div>
                <p class="text-[11px] text-slate-400 mt-2">Qualification list (every subject A1) — no rank/percentage ordering applies here.</p>
            </div>

            <div class="card p-5">
                <p class="text-2xl mb-2">🎯</p>
                <h3 class="font-bold text-slate-800">Subject-Wise Top Scorers</h3>
                <p class="text-xs text-slate-500 mt-1 mb-4">Highest scorer per subject, Class XII, across every member school.</p>
                <div class="flex flex-wrap gap-2">
                    <Link :href="subjectWiseHref" class="btn-secondary text-xs">Open report →</Link>
                    <button type="button" class="btn-secondary text-xs" @click="openPreview('subject-wise', 'Subject-Wise Top Scorers — PDF Preview')">👁 Preview</button>
                    <a :href="pdfHref('subject-wise')" class="btn-secondary text-xs">📥 PDF</a>
                </div>
            </div>
        </div>

        <PdfPreviewModal
            :show="showPdfPreview"
            :pdf-url="activePreviewUrl"
            :title="activePreviewTitle"
            @close="showPdfPreview = false"
        />
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import PdfPreviewModal from '@/Components/ui/PdfPreviewModal.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    filters: { type: Object, default: () => ({}) },
    academicYearOptions: { type: Array, default: () => [] },
});

const selectedYear = ref(props.filters.academic_year);

// SearchableSelect's default {id,name}/{value,label} normalization would key options
// by ay.id, but the original <select> used ay.label as the option value — remap so
// selection semantics stay identical.
const academicYearSelectOptions = computed(() =>
    props.academicYearOptions.map(ay => ({ value: ay.label, label: ay.label })));

function switchYear(year) {
    selectedYear.value = year;
    router.get(`/sahodaya-admin/${props.sahodaya.id}/board-results/toppers/reports-menu`, {
        academic_year: year,
    }, { preserveScroll: true, preserveState: true });
}

function overallHref(cls) {
    return `/sahodaya-admin/${props.sahodaya.id}/board-results/toppers/overall?class=${cls}&academic_year=${encodeURIComponent(selectedYear.value || '')}`;
}

const subjectWiseHref = computed(() =>
    `/sahodaya-admin/${props.sahodaya.id}/board-results/toppers/subject-wise?academic_year=${encodeURIComponent(selectedYear.value || '')}`);
const fullA1Href = computed(() =>
    `/sahodaya-admin/${props.sahodaya.id}/board-results/reports/full-a1-achievers?academic_year=${encodeURIComponent(selectedYear.value || '')}`);

// Base PDF URL (streams inline by default — see BoardResultReportController).
function pdfPreviewHref(report) {
    const year = encodeURIComponent(selectedYear.value || '');
    if (report === 'toppers') {
        return `/sahodaya-admin/${props.sahodaya.id}/board-results/reports/toppers/pdf?academic_year=${year}`;
    }
    if (report === 'full-a1') {
        return `/sahodaya-admin/${props.sahodaya.id}/board-results/reports/full-a1-achievers/pdf?academic_year=${year}`;
    }
    if (report === 'subject-wise') {
        return `/sahodaya-admin/${props.sahodaya.id}/board-results/reports/subject-merit/pdf?class=12&academic_year=${year}`;
    }
    return '#';
}

// Explicit download — forces an attachment instead of the inline preview stream.
function pdfHref(report) {
    const base = pdfPreviewHref(report);
    if (base === '#') return base;
    return `${base}&download=1`;
}

const showPdfPreview = ref(false);
const activePreviewUrl = ref('');
const activePreviewTitle = ref('PDF Preview');

function openPreview(report, title) {
    activePreviewUrl.value = pdfPreviewHref(report);
    activePreviewTitle.value = title;
    showPdfPreview.value = true;
}
</script>
