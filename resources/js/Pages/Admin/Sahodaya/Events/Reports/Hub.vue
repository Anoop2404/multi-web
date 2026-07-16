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

            <FestEventMetaBar v-if="eventMeta" :meta="eventMeta" />

            <ReportPhasePackCards :reports-base="reportsBase"
                                  :current-phase="currentPhase"
                                  :allowed-phases="allowedPhases" />

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

            <EventPageActivityLog :logs="activityLogs" />
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
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
});

const categoryMeta = REPORT_CATEGORIES;
const searchQuery = ref('');
const activeCategory = ref(null);

const reportsBase = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports`);

const categoryOptions = computed(() =>
    REPORT_CATEGORY_ORDER
        .filter((key) => key !== 'heads' && categoryMeta[key])
        .map((key) => ({ key, ...categoryMeta[key] })),
);

const orderedGroups = computed(() => {
    let list = (props.interactive ?? []).map((r) => enrichInteractiveReport(r, props.event?.event_type === 'sports'));
    if (props.hasItemHeads) {
        list = list.filter((p) => p.id !== 'head-wise-participants');
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
</script>
