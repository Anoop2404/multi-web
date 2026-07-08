<template>
    <SahodayaEventsLayout :title="`${event.title} — Reports`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <div class="reports-shell">
            <PageHeader :title="`${event.title} — Downloads`" eyebrow="Reports"
                        :description="phaseMeta?.hint ?? 'Preview on-screen when available, or download filtered exports.'" />

            <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" :active="phase" />

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
                        {{ categoryMeta[catKey]?.label ?? catKey }}
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
                                          @update:param="({ key, value }) => params[exp.id][key] = value" />
                    </div>
                </section>
            </template>

            <EventPageActivityLog :logs="activityLogs" />
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import ReportExportCard from '@/Components/reports/ReportExportCard.vue';
import ReportToolbar from '@/Components/reports/ReportToolbar.vue';
import {
    REPORT_CATEGORIES,
    REPORT_CATEGORY_ORDER,
    REPORT_PHASES,
    groupExportsByCategory,
    filterReportsByQuery,
} from '@/support/festReportCatalog.js';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, phase: String, exports: Array,
    schools: Array, items: Array, heads: Array, stages: Array, classGroups: Object,
    activityLogs: { type: Array, default: () => [] },
});

const categoryMeta = REPORT_CATEGORIES;
const searchQuery = ref('');
const activeCategory = ref(null);

const itemsList = computed(() => props.items ?? []);
const reportsBase = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports`);
const phaseMeta = computed(() => REPORT_PHASES.find((p) => p.key === props.phase));

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

const params = reactive({});
for (const exp of props.exports ?? []) {
    params[exp.id] = {};
    for (const p of exp.params ?? []) params[exp.id][p] = '';
}
</script>
