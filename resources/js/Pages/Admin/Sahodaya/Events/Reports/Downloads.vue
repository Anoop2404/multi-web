<template>
    <SahodayaEventsLayout :title="`${event.title} — Reports`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <div class="reports-shell">
            <PageHeader :title="`${event.title} — Downloads`" eyebrow="Reports"
                        :description="phaseMeta?.hint ?? 'Preview on-screen when available, or download filtered exports.'" />

            <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" :active="phase" />

            <div v-if="competitionPhases.length" class="card mb-4 !py-4">
                <div class="grid gap-3 md:grid-cols-4 items-end">
                    <label class="text-xs font-semibold text-slate-600">Competition phase
                        <SearchableSelect v-model="scopePhaseId" class="mt-1 w-full" :options="competitionPhases" :all-option="true" all-label="All phases" />
                    </label>
                    <label class="text-xs font-semibold text-slate-600">Region
                        <SearchableSelect v-model="scopeRegionId" class="mt-1 w-full" :options="regions" :all-option="true" all-label="Combined" />
                    </label>
                    <label class="text-xs font-semibold text-slate-600">Registration / payment level
                        <SearchableSelect v-model="scopeBatchId" class="mt-1 w-full" :options="registrationBatches" :all-option="true" all-label="All levels" />
                    </label>
                    <button type="button" class="btn-primary text-sm" @click="applyReportScope">Apply report scope</button>
                </div>
            </div>

            <!-- Sport Event / Region Switcher -->
            <div v-if="childEvents.length && !competitionPhases.length" class="card mb-4 !py-3">
                <div class="flex flex-wrap gap-3 items-center">
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ event.event_type === 'sports' ? 'Select Sport Event / Region:' : 'Select Phase / Region:' }}</label>
                    <SearchableSelect :model-value="String(event.id)" @update:model-value="switchSportEvent"
                                      class="w-64" :options="childEventOptions" :all-option="false"
                                      placeholder="Select sport event" />
                </div>
            </div>

            <!-- =============================================================
                 PARTITIONED PARENT: inline region sections + combined
                 ============================================================= -->
            <template v-if="isPartitionedParent && regionChildrenWithExports.length">

                <!-- Per-region export sections -->
                <section
                    v-for="child in regionChildrenWithExports"
                    :key="child.id"
                    class="mb-12"
                >
                    <div class="flex items-center gap-3 mb-4 pb-3 border-b border-slate-200">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-white text-xs font-bold shrink-0"
                              style="background: #0d9488;">
                            {{ child.region_code || '◎' }}
                        </span>
                        <div>
                            <h3 class="section-title mb-0">{{ child.region_name }}</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Region-scoped downloads</p>
                        </div>
                    </div>

                    <template v-if="child.exports?.length">
                        <section v-for="[catKey, items] in groupedChildExports(child.exports)" :key="catKey" class="mb-8">
                            <h4 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2">
                                <span aria-hidden="true">{{ categoryMeta[catKey]?.icon }}</span>
                                {{ reportCategoryLabel(catKey, event.event_type === 'sports') }}
                                <span class="text-xs font-normal text-slate-400">({{ items.length }})</span>
                            </h4>
                            <div class="space-y-3">
                                <ReportExportCard
                                    v-for="exp in items"
                                    :key="exp.id"
                                    :exp="exp"
                                    :reports-base="child.reportsBase"
                                    :param-values="childParams[child.id]?.[exp.id] ?? {}"
                                    :schools="schools"
                                    :items="itemsList"
                                    :heads="heads"
                                    :stages="stages"
                                    :class-groups="classGroups"
                                    :is-sports="event.event_type === 'sports'"
                                    @update:param="({ key, value }) => setChildParam(child.id, exp.id, key, value)"
                                />
                            </div>
                        </section>
                    </template>
                    <EmptyState v-else
                                title="No exports in this pack"
                                :description="`No ${phaseMeta?.label?.toLowerCase() ?? phase} exports are available for this region yet.`"
                                icon="📥" />
                </section>

                <!-- Combined (all regions) exports section -->
                <section class="mb-10">
                    <div class="flex items-center gap-3 mb-4 pb-3 border-b border-slate-200">
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full text-white text-xs font-bold shrink-0"
                              style="background: #475569;">
                            ALL
                        </span>
                        <div>
                            <h3 class="section-title mb-0">Combined — All Regions</h3>
                            <p class="text-xs text-slate-500 mt-0.5">Merged downloads across every region</p>
                        </div>
                    </div>

                    <div v-if="exports.length" class="mb-6">
                        <ReportToolbar v-model:query="searchQuery"
                                       v-model:category="activeCategory"
                                       :categories="categoryOptions"
                                       placeholder="Search combined downloads…" />
                        <p class="text-xs text-slate-500 mt-3">{{ filteredCount }} export{{ filteredCount === 1 ? '' : 's' }} in this pack</p>
                    </div>

                    <EmptyState v-if="!exports.length"
                                title="No exports in this pack"
                                :description="`No ${phaseMeta?.label?.toLowerCase() ?? phase} exports are available for this event yet.`"
                                icon="📥" />

                    <EmptyState v-else-if="!filteredGroups.length"
                                title="No matching exports"
                                description="Try a different search term or clear filters."
                                icon="🔍" />

                    <template v-else>
                        <section v-for="[catKey, items] in filteredGroups" :key="catKey" class="mb-8">
                            <h4 class="text-sm font-semibold text-slate-700 mb-3 flex items-center gap-2">
                                <span aria-hidden="true">{{ categoryMeta[catKey]?.icon }}</span>
                                {{ reportCategoryLabel(catKey, event.event_type === 'sports') }}
                                <span class="text-xs font-normal text-slate-400">({{ items.length }})</span>
                            </h4>
                            <div class="space-y-3">
                                <ReportExportCard v-for="exp in items" :key="exp.id"
                                                  :exp="exp"
                                                  :reports-base="reportsBase"
                                                  :param-values="params[exp.id]"
                                                  :schools="schools"
                                                  :items="itemsList"
                                                  :heads="heads"
                                                  :stages="stages"
                                                  :class-groups="classGroups"
                                                  :is-sports="event.event_type === 'sports'"
                                                  @update:param="({ key, value }) => params[exp.id][key] = value" />
                            </div>
                        </section>
                    </template>
                </section>
            </template>

            <!-- =============================================================
                 STANDARD (non-partitioned) event — original layout
                 ============================================================= -->
            <template v-else>
                <div v-if="exports.length" class="mb-6">
                    <ReportToolbar v-model:query="searchQuery"
                                   v-model:category="activeCategory"
                                   :categories="categoryOptions"
                                   placeholder="Search downloads…" />
                    <p class="text-xs text-slate-500 mt-3">{{ filteredCount }} export{{ filteredCount === 1 ? '' : 's' }} in this pack</p>
                </div>

                <EmptyState v-if="!exports.length"
                            title="No exports in this pack"
                            :description="`No ${phaseMeta?.label?.toLowerCase() ?? phase} exports are available for this event yet.`"
                            icon="📥" />

                <EmptyState v-else-if="!filteredGroups.length"
                            title="No matching exports"
                            description="Try a different search term or clear filters."
                            icon="🔍" />

                <template v-else>
                    <section v-for="[catKey, items] in filteredGroups" :key="catKey" class="mb-8">
                        <h3 class="section-title mb-3 flex items-center gap-2">
                            <span aria-hidden="true">{{ categoryMeta[catKey]?.icon }}</span>
                            {{ reportCategoryLabel(catKey, event.event_type === 'sports') }}
                            <span class="text-xs font-normal text-slate-400">({{ items.length }})</span>
                        </h3>
                        <div class="space-y-3">
                            <ReportExportCard v-for="exp in items" :key="exp.id"
                                              :exp="exp"
                                              :reports-base="reportsBase"
                                              :param-values="params[exp.id]"
                                              :schools="schools"
                                              :items="itemsList"
                                              :heads="heads"
                                              :stages="stages"
                                              :class-groups="classGroups"
                                              :is-sports="event.event_type === 'sports'"
                                              @update:param="({ key, value }) => params[exp.id][key] = value" />
                        </div>
                    </section>
                </template>
            </template>

            <EventPageActivityLog :logs="activityLogs" />
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import ReportExportCard from '@/Components/reports/ReportExportCard.vue';
import ReportToolbar from '@/Components/reports/ReportToolbar.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import {
    REPORT_CATEGORIES,
    REPORT_CATEGORY_ORDER,
    REPORT_PHASES,
    groupExportsByCategory,
    filterReportsByQuery,
    reportCategoryLabel,
} from '@/support/festReportCatalog.js';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, phase: String, exports: Array,
    schools: Array, items: Array, heads: Array, stages: Array, classGroups: Object,
    activityLogs: { type: Array, default: () => [] },
    isPartitionedParent: { type: Boolean, default: false },
    regionChildren: { type: Array, default: () => [] },
    regionChildrenWithExports: { type: Array, default: () => [] },
    childEvents: { type: Array, default: () => [] },
    regions: { type: Array, default: () => [] },
    competitionPhases: { type: Array, default: () => [] },
    registrationBatches: { type: Array, default: () => [] },
    reportScopeSelection: { type: Object, default: () => ({}) },
});

function switchSportEvent(value) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${value}/reports/downloads/${props.phase}`);
}

const childEventOptions = computed(() =>
    (props.childEvents ?? []).map((ev) => ({ value: String(ev.id), label: ev.short_title || ev.title })),
);

const categoryMeta = REPORT_CATEGORIES;
const searchQuery = ref('');
const activeCategory = ref(null);
const scopePhaseId = ref(props.reportScopeSelection.competition_phase_id || '');
const scopeRegionId = ref(props.reportScopeSelection.region_id || '');
const scopeBatchId = ref(props.reportScopeSelection.registration_batch_id || '');

const itemsList = computed(() => props.items ?? []);
const reportsBase = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports`);
const phaseMeta = computed(() => REPORT_PHASES.find((p) => p.key === props.phase));

function scopeParams() {
    return {
        scope_mode: scopeRegionId.value ? 'region' : 'combined',
        competition_phase_id: scopePhaseId.value || undefined,
        registration_batch_id: scopeBatchId.value || undefined,
        region_id: scopeRegionId.value || undefined,
    };
}

function applyReportScope() {
    router.get(`${reportsBase.value}/downloads/${props.phase}`, scopeParams(), { preserveState: false });
}

const categoryOptions = computed(() =>
    REPORT_CATEGORY_ORDER
        .filter((key) => categoryMeta[key])
        .map((key) => ({ key, ...categoryMeta[key] })),
);

const filteredExports = computed(() => {
    let list = props.exports ?? [];
    list = filterReportsByQuery(list, searchQuery.value);
    return list;
});

const filteredGroups = computed(() => {
    const grouped = groupExportsByCategory(filteredExports.value);
    const keys = activeCategory.value
        ? [activeCategory.value]
        : REPORT_CATEGORY_ORDER.filter((k) => grouped[k]?.length);
    return keys
        .filter((k) => grouped[k]?.length)
        .map((catKey) => [catKey, grouped[catKey]]);
});

const filteredCount = computed(() =>
    filteredGroups.value.reduce((n, [, arr]) => n + arr.length, 0),
);

/** Group a child's export list by category — no filtering applied (keep all exports). */
function groupedChildExports(childExports) {
    const grouped = groupExportsByCategory(childExports ?? []);
    return REPORT_CATEGORY_ORDER
        .filter((k) => grouped[k]?.length)
        .map((catKey) => [catKey, grouped[catKey]]);
}

// Params state for combined exports
const params = reactive({});
for (const exp of props.exports ?? []) {
    params[exp.id] = { ...scopeParams() };
    for (const p of exp.params ?? []) params[exp.id][p] = '';
}

// Params state for per-region child exports: childParams[childId][exportId][paramKey]
const childParams = reactive({});
for (const child of props.regionChildrenWithExports ?? []) {
    childParams[child.id] = {};
    for (const exp of child.exports ?? []) {
        childParams[child.id][exp.id] = {};
        for (const p of exp.params ?? []) childParams[child.id][exp.id][p] = '';
    }
}

function setChildParam(childId, expId, key, value) {
    if (!childParams[childId]) childParams[childId] = {};
    if (!childParams[childId][expId]) childParams[childId][expId] = {};
    childParams[childId][expId][key] = value;
}
</script>
