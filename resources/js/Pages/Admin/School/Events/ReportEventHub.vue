<template>
    <SchoolAdminLayout :title="`${event.title} — Reports Catalog`" :school="school" :show-header-title="false">
        <div class="reports-shell">
            <PageHeader :title="`${event.title} — Reports Catalog`" :eyebrow="`${programLabel} Event Reports`"
                        description="Browse and export every report for this event — admit cards, chest numbers, registration registers, score sheets, and certificates.">
                <template #actions>
                    <Link :href="`${programBase}/reports`" class="btn-secondary text-sm">← All events</Link>
                </template>
            </PageHeader>

            <FestEventMetaBar v-if="eventMeta" :meta="eventMeta" :show-edit-hint="false" />

            <section v-if="featuredReports.length" class="mb-6">
                <h3 class="section-title mb-3">Quick access</h3>
                <div class="grid gap-3 sm:grid-cols-2">
                    <ReportSchoolTile v-for="report in featuredReports"
                                      :key="report.id"
                                      :report="report"
                                      :preview-href="report.hasPreview ? previewHref(report) : null"
                                      :pdf-href="report.hasExport ? pdfHref(report) : null"
                                      :data-href="report.hasExport ? dataHref(report) : null"
                                      :data-label="report.exportLabel ?? 'Export'" />
                </div>
            </section>

            <ReportHeadHubSection v-if="hasItemHeads"
                                  :is-sports="isSports"
                                  :heads="headSummary"
                                  :head-item-groups="headItemGroups"
                                  :head-report-base="headWiseReportBase"
                                  :export-base-url="headWiseExportUrl"
                                  :all-heads-url="headWiseReportBase" />

            <section v-if="orderedGroups.length" class="space-y-6">
                <div>
                    <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
                        <div>
                            <h3 class="section-title mb-1">All reports</h3>
                            <p class="text-sm text-slate-600">
                                {{ totalReportCount }} reports for your school — preview on-screen or download exports.
                            </p>
                        </div>
                    </div>
                    <ReportToolbar v-model:query="searchQuery"
                                   v-model:category="activeCategory"
                                   :categories="categoryOptions"
                                   placeholder="Search reports…" />
                    <div class="flex flex-wrap gap-2 mt-3">
                        <button type="button"
                                class="text-xs px-3 py-1.5 rounded-full border transition-colors"
                                :class="!activePhase ? 'bg-indigo-600 text-white border-indigo-600' : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300'"
                                @click="activePhase = null">
                            All phases
                        </button>
                        <button v-for="phase in reportPhases"
                                :key="phase.key"
                                type="button"
                                class="text-xs px-3 py-1.5 rounded-full border transition-colors"
                                :class="activePhase === phase.key ? 'bg-indigo-600 text-white border-indigo-600' : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300'"
                                @click="activePhase = phase.key">
                            {{ phase.icon }} {{ phase.label }}
                        </button>
                    </div>
                </div>

                <section v-for="{ catKey, items } in orderedGroups" :key="catKey">
                    <h4 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2">
                        <span aria-hidden="true">{{ categoryMeta[catKey]?.icon }}</span>
                        {{ reportCategoryLabel(catKey, isSports) }}
                        <span class="text-xs font-normal text-slate-400">({{ items.length }})</span>
                    </h4>
                    <div class="space-y-3">
                        <ReportSchoolTile v-for="report in items"
                                          :key="report.id"
                                          :report="report"
                                          :preview-href="report.hasPreview ? previewHref(report) : null"
                                          :pdf-href="report.hasExport ? pdfHref(report) : null"
                                          :data-href="report.hasExport ? dataHref(report) : null"
                                          :data-label="report.exportLabel ?? 'Export'" />
                    </div>
                </section>
            </section>

            <EmptyState v-else-if="searchQuery || activeCategory"
                        title="No matching reports"
                        description="Try a different search term or clear filters."
                        icon="🔍" />
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import ReportHeadHubSection from '@/Components/reports/ReportHeadHubSection.vue';
import FestEventMetaBar from '@/Components/reports/FestEventMetaBar.vue';
import ReportToolbar from '@/Components/reports/ReportToolbar.vue';
import ReportSchoolTile from '@/Components/reports/ReportSchoolTile.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';
import {
    REPORT_CATEGORIES,
    REPORT_PHASES,
    groupSchoolReports,
    schoolReportsForProgram,
    featuredSchoolReports,
    REPORT_CATEGORY_ORDER,
    filterReportsByQuery,
    filterReportsByPhase,
    schoolReportPdfHref,
    schoolReportDataHref,
    reportCategoryLabel,
} from '@/support/festReportCatalog.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    eventMeta: Object,
    headSummary: { type: Array, default: () => [] },
    headItemGroups: { type: Array, default: () => [] },
    hasItemHeads: Boolean,
    headWiseReportBase: String,
    headWiseExportUrl: String,
});

const { programSlug, programLabel, programBase } = useSchoolProgramContext(props);
const isSports = computed(() => props.event?.event_type === 'sports' || programSlug.value === 'sports-meet');
const categoryMeta = REPORT_CATEGORIES;
const reportPhases = REPORT_PHASES;
const searchQuery = ref('');
const activeCategory = ref(null);
const activePhase = ref(null);

const eventBase = computed(() => `${programBase.value}/reports/${props.event.id}`);

const featuredReports = computed(() => {
    let reports = featuredSchoolReports(programSlug.value)
        .filter((r) => r.id !== 'head-wise' || !props.hasItemHeads);
    return filterReportsByPhase(reports, activePhase.value);
});

const totalReportCount = computed(() => {
    let reports = schoolReportsForProgram(programSlug.value)
        .filter((r) => r.id !== 'head-wise' || !props.hasItemHeads);
    return reports.length;
});

const categoryOptions = computed(() =>
    REPORT_CATEGORY_ORDER
        .filter((key) => key !== 'heads' && categoryMeta[key])
        .map((key) => ({ key, ...categoryMeta[key] })),
);

const orderedGroups = computed(() => {
    let reports = schoolReportsForProgram(programSlug.value)
        .filter((r) => r.id !== 'head-wise' || !props.hasItemHeads);
    reports = filterReportsByQuery(reports, searchQuery.value);
    reports = filterReportsByPhase(reports, activePhase.value);
    if (activeCategory.value) {
        reports = reports.filter((r) => r.category === activeCategory.value);
    }
    const grouped = groupSchoolReports(reports);
    return REPORT_CATEGORY_ORDER
        .filter((key) => grouped[key]?.length)
        .map((catKey) => ({ catKey, items: grouped[catKey] }));
});

function previewHref(report) {
    return `${eventBase.value}/${report.id}`;
}

function pdfHref(report) {
    return schoolReportPdfHref(eventBase.value, report);
}

function dataHref(report) {
    return schoolReportDataHref(eventBase.value, report);
}
</script>
