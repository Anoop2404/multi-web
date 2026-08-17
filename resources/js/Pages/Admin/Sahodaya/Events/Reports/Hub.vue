<template>
    <SahodayaEventsLayout :title="`${event.title} — Reports`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <div class="reports-shell">
            <PageHeader :title="`${event.title} — Reports`" eyebrow="Analytics"
                        :description="event.event_type === 'sports'
                            ? 'Browse reports by Sport Event, download phase packs, or explore all report types.'
                            : 'Browse reports by Event Head, download phase packs, or explore all report types.'">
                <template #actions>
                    <span v-if="currentPhase" class="status-pill status-pill--published capitalize">{{ currentPhase }} phase</span>
                </template>
            </PageHeader>

            <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="hub" />

            <div v-if="competitionPhases.length" class="card mb-4 !py-4">
                <div class="grid gap-3 md:grid-cols-4 items-end">
                    <label class="text-xs font-semibold text-slate-600">Competition phase
                        <select v-model="scopePhaseId" class="field mt-1 text-sm w-full">
                            <option value="">All published phases</option>
                            <option v-for="item in competitionPhases" :key="item.id" :value="item.id">{{ item.name }}</option>
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-slate-600">Region
                        <select v-model="scopeRegionId" class="field mt-1 text-sm w-full">
                            <option value="">Combined</option>
                            <option v-for="item in regions" :key="item.id" :value="item.id">{{ item.name }}</option>
                        </select>
                    </label>
                    <label class="text-xs font-semibold text-slate-600">Registration / payment level
                        <select v-model="scopeBatchId" class="field mt-1 text-sm w-full">
                            <option value="">All levels</option>
                            <option v-for="item in registrationBatches" :key="item.id" :value="item.id">{{ item.name }}</option>
                        </select>
                    </label>
                    <button type="button" class="btn-primary text-sm" @click="applyReportScope">Apply report scope</button>
                </div>
                <p class="mt-2 text-xs text-slate-500">Every report and export keeps this phase, region and payment-level scope.</p>
            </div>

            <!-- Sport Event / Region Switcher -->
            <div v-if="childEvents.length && !competitionPhases.length" class="card mb-4 !py-3">
                <div class="flex flex-wrap gap-3 items-center">
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Select Sport Event / Region:</label>
                    <select :value="String(event.id)" @change="switchSportEvent" class="field text-xs !py-1 w-64 font-semibold">
                        <option v-for="ev in childEvents" :key="ev.id" :value="String(ev.id)">
                            {{ ev.short_title || ev.title }}
                        </option>
                    </select>
                </div>
            </div>

            <FestEventMetaBar v-if="eventMeta" :meta="eventMeta" />

            <ReportPhasePackCards :reports-base="reportsBase"
                                  :current-phase="currentPhase"
                                  :allowed-phases="allowedPhases" />

            <!-- =====================================================================
                 PARTITIONED PARENT: show per-region sections + combined
                 ===================================================================== -->
            <template v-if="isPartitionedParent && regionChildren.length">

                <!-- One full section per region child -->
                <section
                    v-for="child in regionChildren"
                    :key="child.id"
                    class="mb-10"
                >
                    <!-- Region section header -->
                    <div class="flex items-center gap-3 mb-4 pb-3 border-b border-slate-200">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-white text-xs font-bold shrink-0"
                              style="background: #0d9488;">
                            {{ child.region_code || '◎' }}
                        </span>
                        <div>
                            <h3 class="section-title mb-0">{{ child.region_name }}</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Region-scoped reports</p>
                        </div>
                    </div>

                    <!-- Report tiles for this region (same tile grid, child event URLs) -->
                    <div v-if="regionGroups[child.id]?.length">
                        <section v-for="{ catKey, items } in regionGroups[child.id]" :key="catKey" class="mb-6">
                            <h4 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2">
                                <span aria-hidden="true">{{ categoryMeta[catKey]?.icon }}</span>
                                {{ reportCategoryLabel(catKey, event.event_type === 'sports') }}
                                <span class="text-xs font-normal text-slate-400">({{ items.length }})</span>
                            </h4>
                            <div class="reports-tile-grid">
                                <ReportInteractiveTile v-for="p in items" :key="p.id" :report="p" />
                            </div>
                        </section>
                    </div>
                    <p v-else class="text-sm text-slate-500 italic">No interactive reports available for this region.</p>
                </section>

                <!-- Combined (all regions) section — uses parent event URLs -->
                <section class="mb-10">
                    <div class="flex items-center gap-3 mb-4 pb-3 border-b border-slate-200">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-white text-xs font-bold shrink-0"
                              style="background: #475569;">
                            ALL
                        </span>
                        <div>
                            <h3 class="section-title mb-0">Combined — All Regions</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Merged data across every region</p>
                        </div>
                    </div>

                    <div>
                        <ReportToolbar v-model:query="searchQuery"
                                       v-model:category="activeCategory"
                                       :categories="categoryOptions"
                                       placeholder="Search combined reports…"
                                       class="mb-4" />
                    </div>

                    <div v-if="orderedGroups.length">
                        <section v-for="{ catKey, items } in orderedGroups" :key="catKey" class="mb-6">
                            <h4 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2">
                                <span aria-hidden="true">{{ categoryMeta[catKey]?.icon }}</span>
                                {{ reportCategoryLabel(catKey, event.event_type === 'sports') }}
                                <span class="text-xs font-normal text-slate-400">({{ items.length }})</span>
                            </h4>
                            <div class="reports-tile-grid">
                                <ReportInteractiveTile v-for="p in items" :key="p.id" :report="p" />
                            </div>
                        </section>
                    </div>
                    <EmptyState v-else
                                title="No matching reports"
                                description="Try a different search term or clear filters."
                                icon="🔍"
                                class="mb-8" />
                </section>
            </template>

            <!-- =====================================================================
                 NON-PARTITIONED (standard) event — existing layout unchanged
                 ===================================================================== -->
            <template v-else>
                <section v-if="orderedGroups.length" class="space-y-8 mb-10">
                    <div>
                        <h3 class="section-title mb-1">Interactive reports</h3>
                        <p class="text-sm text-slate-600 mb-4">On-screen views with filters — open any report to explore data before exporting.</p>
                        <ReportToolbar v-model:query="searchQuery"
                                       v-model:category="activeCategory"
                                       :categories="categoryOptions"
                                       placeholder="Search reports by name…" />
                    </div>

                    <section v-for="{ catKey, items } in orderedGroups" :key="catKey">
                        <h4 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2">
                            <span aria-hidden="true">{{ categoryMeta[catKey]?.icon }}</span>
                            {{ reportCategoryLabel(catKey, event.event_type === 'sports') }}
                            <span class="text-xs font-normal text-slate-400">({{ items.length }})</span>
                        </h4>
                        <div class="reports-tile-grid">
                            <ReportInteractiveTile v-for="p in items" :key="p.id" :report="p" />
                        </div>
                    </section>
                </section>

                <EmptyState v-else-if="searchQuery || activeCategory"
                            title="No matching reports"
                            description="Try a different search term or clear filters."
                            icon="🔍"
                            class="mb-8" />

                <ReportHeadHubSection v-if="hasItemHeads && event.event_type !== 'sports'"
                                      compact
                                      :is-sports="false"
                                      :heads="headSummary"
                                      :head-item-groups="headItemGroups"
                                      :head-report-base="headWiseReportBase"
                                      :export-base-url="headWiseExportUrl"
                                      :manage-url="itemHeadsManageUrl" />

                <section v-else-if="event.event_type === 'sports' && headItemGroups.length" class="mb-8">
                    <h3 class="section-title mb-3">By Sport Event</h3>
                    <p class="text-sm text-slate-600 mb-4">Jump to reports filtered for a specific sport event and its items.</p>
                    <div class="reports-tile-grid">
                        <Link v-for="head in headItemGroups" :key="head.head_id"
                              :href="`${reportsBase}/by-head?head=${head.head_id}`"
                              class="reports-head-card group block hover:no-underline">
                            <span v-if="head.participant_count" class="reports-head-card__count">
                                {{ head.participant_count }}
                            </span>
                            <p class="font-semibold text-slate-900 pr-16 group-hover:text-[color:var(--brand-navy)]">{{ head.head_name }}</p>
                            <p class="text-xs text-slate-500 mt-1">{{ head.item_count }} item{{ head.item_count === 1 ? '' : 's' }}</p>
                        </Link>
                    </div>
                </section>
            </template>

            <EventPageActivityLog :logs="activityLogs" />
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import ReportHeadHubSection from '@/Components/reports/ReportHeadHubSection.vue';
import FestEventMetaBar from '@/Components/reports/FestEventMetaBar.vue';
import ReportToolbar from '@/Components/reports/ReportToolbar.vue';
import ReportPhasePackCards from '@/Components/reports/ReportPhasePackCards.vue';
import ReportInteractiveTile from '@/Components/reports/ReportInteractiveTile.vue';
import {
    REPORT_CATEGORIES,
    INTERACTIVE_CATEGORY_MAP,
    groupInteractiveReports,
    REPORT_CATEGORY_ORDER,
    enrichInteractiveReport,
    filterReportsByQuery,
    reportCategoryLabel,
} from '@/support/festReportCatalog.js';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, interactive: Array, currentPhase: String, allowedPhases: Array,
    eventMeta: Object,
    headSummary: { type: Array, default: () => [] },
    headItemGroups: { type: Array, default: () => [] },
    hasItemHeads: Boolean,
    itemHeadsManageUrl: String,
    headWiseReportBase: String,
    headWiseExportUrl: String,
    activityLogs: { type: Array, default: () => [] },
    isPartitionedParent: { type: Boolean, default: false },
    regionChildren: { type: Array, default: () => [] },
    childEvents: { type: Array, default: () => [] },
    regions: { type: Array, default: () => [] },
    competitionPhases: { type: Array, default: () => [] },
    registrationBatches: { type: Array, default: () => [] },
    reportScopeSelection: { type: Object, default: () => ({}) },
});

function switchSportEvent(evt) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${evt.target.value}/reports`);
}

const categoryMeta = REPORT_CATEGORIES;
const searchQuery = ref('');
const activeCategory = ref(null);
const scopePhaseId = ref(props.reportScopeSelection.competition_phase_id || '');
const scopeRegionId = ref(props.reportScopeSelection.region_id || '');
const scopeBatchId = ref(props.reportScopeSelection.registration_batch_id || '');

const reportsBase = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports`);

function scopeParams() {
    return {
        scope_mode: scopeRegionId.value ? 'region' : 'combined',
        competition_phase_id: scopePhaseId.value || undefined,
        registration_batch_id: scopeBatchId.value || undefined,
        region_id: scopeRegionId.value || undefined,
    };
}

function applyReportScope() {
    router.get(reportsBase.value, scopeParams(), { preserveState: false });
}

function withReportScope(report) {
    if (!report?.href || !props.competitionPhases.length) return report;
    const [path, query = ''] = report.href.split('?');
    const params = new URLSearchParams(query);
    Object.entries(scopeParams()).forEach(([key, value]) => {
        if (value !== undefined) params.set(key, value);
    });
    return { ...report, href: `${path}?${params.toString()}` };
}

const categoryOptions = computed(() =>
    REPORT_CATEGORY_ORDER
        .filter((key) => key !== 'heads' && categoryMeta[key])
        .map((key) => ({ key, ...categoryMeta[key] })),
);

/** Build ordered category groups from a flat list of interactive pages. */
function buildOrderedGroups(pages, isSports) {
    let list = (pages ?? []).map((r) => withReportScope(enrichInteractiveReport(r, isSports)));
    if (!props.hasItemHeads) {
        list = list.filter((p) => p.id !== 'head-wise-participants' && p.id !== 'discipline-registration');
    }
    const grouped = groupInteractiveReports(list);
    return REPORT_CATEGORY_ORDER
        .filter((key) => grouped[key]?.length)
        .map((catKey) => ({ catKey, items: grouped[catKey] }));
}

/** Combined (parent event) interactive groups, with search/category filter applied. */
const orderedGroups = computed(() => {
    let list = (props.interactive ?? []).map((r) => withReportScope(enrichInteractiveReport(r, props.event?.event_type === 'sports')));
    if (!props.hasItemHeads) {
        list = list.filter((p) => p.id !== 'head-wise-participants' && p.id !== 'discipline-registration');
    }
    list = filterReportsByQuery(list, searchQuery.value);
    if (activeCategory.value) {
        list = list.filter((p) => (INTERACTIVE_CATEGORY_MAP[p.id] ?? 'ops') === activeCategory.value);
    }
    const grouped = groupInteractiveReports(list);
    return REPORT_CATEGORY_ORDER
        .filter((key) => grouped[key]?.length)
        .map((catKey) => ({ catKey, items: grouped[catKey] }));
});

/**
 * Per-region grouped report tiles keyed by child.id.
 * Each region child already has its interactivePages with its own event URLs.
 */
const regionGroups = computed(() => {
    const isSports = props.event?.event_type === 'sports';
    return Object.fromEntries(
        (props.regionChildren ?? []).map((child) => [
            child.id,
            buildOrderedGroups(child.interactivePages ?? [], isSports),
        ]),
    );
});
</script>
