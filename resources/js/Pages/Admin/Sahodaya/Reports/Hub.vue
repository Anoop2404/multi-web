<template>
    <SahodayaAdminLayout title="Reports hub" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Reports hub" eyebrow="Reports"
                    description="Operational reports by purpose, or open event-dedicated reports with head navigation.">
        </PageHeader>

        <div class="flex flex-wrap gap-2 mb-6 border-b border-slate-200 pb-1">
            <button v-for="tab in tabs" :key="tab.id" type="button"
                    class="px-4 py-2 text-sm font-medium rounded-t-lg transition"
                    :class="activeTab === tab.id
                        ? 'bg-white border border-b-white border-slate-200 text-[#0f3d7a] -mb-px'
                        : 'text-slate-600 hover:text-slate-900'"
                    @click="activeTab = tab.id">
                {{ tab.label }}
            </button>
        </div>

        <!-- Operational (schools, finance, Talent Search, etc.) -->
        <div v-show="activeTab === 'operational'">
            <p class="text-sm text-slate-600 mb-4">
                {{ runnableCount }} live CSV exports · membership, schools, finance, Talent Search, training, and more.
            </p>
            <div class="mb-6 flex flex-wrap gap-2">
                <input v-model="search" type="search" placeholder="Search reports…" class="field max-w-xs">
                <select v-model="moduleFilter" class="field max-w-[180px]">
                    <option value="">All modules</option>
                    <option v-for="m in modules" :key="m" :value="m">{{ m }}</option>
                </select>
            </div>
            <ReportModuleGrid :modules="filteredModules" />
        </div>

        <!-- Event-dedicated reports -->
        <div v-show="activeTab === 'event'">
            <p class="text-sm text-slate-600 mb-4">
                Fest, sports, and kalotsav reports are scoped to a single event. Select an event, then open reports by purpose or head.
            </p>

            <form class="card !p-4 mb-6 flex flex-wrap gap-3 items-end" @submit.prevent="loadEvent">
                <FormField label="Event" class="min-w-[280px]">
                    <select v-model="eventId" class="field" required>
                        <option value="">Choose an event…</option>
                        <option v-for="e in events" :key="e.id" :value="e.id">
                            {{ e.title }} ({{ e.event_type }}) · {{ formatDate(e.event_start) }}
                        </option>
                    </select>
                </FormField>
                <button type="submit" class="btn-primary text-sm">Load event reports</button>
            </form>

            <template v-if="selectedEvent">
                <div class="card !p-4 mb-6 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="font-semibold text-[#0f3d7a]">{{ selectedEvent.title }}</p>
                        <p class="text-xs text-slate-500 capitalize">{{ selectedEvent.event_type }} · {{ selectedEvent.status }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link v-if="eventHubUrl" :href="eventHubUrl" class="btn-primary text-sm">Full event reports workspace →</Link>
                        <Link v-if="eventExportsUrl" :href="eventExportsUrl" class="btn-secondary text-sm">Download packs</Link>
                    </div>
                </div>

                <ReportHeadHubSection v-if="headWiseReportBase && (hasItemHeads || headSummary.length)"
                                      compact
                                      :is-sports="selectedEvent?.event_type === 'sports'"
                                      :heads="headSummary"
                                      :head-item-groups="headItemGroups"
                                      :head-report-base="headWiseReportBase"
                                      :export-base-url="headWiseExportUrl"
                                      :manage-url="itemHeadsManageUrl" />

                <section v-for="group in eventPurposeGroups" :key="group.purpose" class="mb-8">
                    <h2 class="section-title mb-1">{{ group.purpose }}</h2>
                    <p class="text-sm text-slate-600 mb-3">{{ group.description }}</p>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <Link v-for="r in group.reports" :key="r.id + r.href"
                              :href="r.href"
                              class="card transition !p-4 hover:border-[#0f3d7a]/30">
                            <p class="text-xs font-mono text-slate-400">{{ r.id }}</p>
                            <p class="font-semibold text-[#0f3d7a] mt-1">{{ r.label }}</p>
                            <p class="text-xs text-slate-500 mt-1 uppercase">{{ r.format ?? 'report' }}</p>
                        </Link>
                    </div>
                </section>
            </template>

            <EmptyState v-else title="Select an event" icon="📅"
                        description="Sports meet and kalotsav reports — registrations, heads, schedules, results — all live inside the event workspace." />
        </div>

        <!-- Cross-event summaries -->
        <div v-show="activeTab === 'cross_event'">
            <p class="text-sm text-slate-600 mb-4">
                Sahodaya-wide summaries across all events (registration windows, appeals index, etc.).
            </p>
            <ReportModuleGrid :modules="{ 'cross-event': crossEventReports }" />
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import FormField from '@/Components/ui/FormField.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import ReportHeadHubSection from '@/Components/reports/ReportHeadHubSection.vue';
import ReportModuleGrid from '@/Components/reports/ReportModuleGrid.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    reportsByModule: Object,
    crossEventReports: { type: Array, default: () => [] },
    events: { type: Array, default: () => [] },
    selectedEventId: Number,
    selectedEvent: Object,
    eventPurposeGroups: { type: Array, default: () => [] },
    eventHubUrl: String,
    eventExportsUrl: String,
    headSummary: { type: Array, default: () => [] },
    headItemGroups: { type: Array, default: () => [] },
    hasItemHeads: Boolean,
    headWiseReportBase: String,
    headWiseExportUrl: String,
    itemHeadsManageUrl: String,
    asyncThreshold: Number,
    runnableCount: Number,
    hubBase: String,
});

const tabs = [
    { id: 'operational', label: 'Operational' },
    { id: 'event', label: 'By event' },
    { id: 'cross_event', label: 'Cross-event' },
];

const activeTab = ref(props.selectedEventId ? 'event' : 'operational');
const search = ref('');
const moduleFilter = ref('');
const eventId = ref(props.selectedEventId ? String(props.selectedEventId) : '');

watch(() => props.selectedEventId, (id) => {
    if (id) {
        eventId.value = String(id);
        activeTab.value = 'event';
    }
});

const modules = computed(() => Object.keys(props.reportsByModule ?? {}));

const filteredModules = computed(() => {
    const term = search.value.trim().toLowerCase();
    const out = {};

    for (const [module, reports] of Object.entries(props.reportsByModule ?? {})) {
        if (moduleFilter.value && module !== moduleFilter.value) continue;

        const filtered = reports.filter(r => {
            if (!term) return true;
            return r.id.toLowerCase().includes(term) || r.label.toLowerCase().includes(term);
        });

        if (filtered.length) out[module] = filtered;
    }

    return out;
});

function loadEvent() {
    if (!eventId.value) return;
    router.get(props.hubBase, { event_id: eventId.value }, { preserveState: true, preserveScroll: true });
}

function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>
