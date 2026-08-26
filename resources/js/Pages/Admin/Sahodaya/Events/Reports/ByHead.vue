<template>
    <SahodayaEventsLayout :title="`${event.title} — Reports`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="pageTitle" eyebrow="Reports"
                    :description="headerDescription">
            <template #actions>
                <Link :href="reportsHubUrl" class="btn-secondary text-sm">All report types</Link>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="by-head" />

        <!-- Sport Event / Region Switcher -->
        <div v-if="event.event_type === 'sports' && childEvents.length" class="card mb-4 !py-3">
            <div class="flex flex-wrap gap-3 items-center">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ event.event_type === 'sports' ? 'Select Sport Event / Region:' : 'Select Region:' }}</label>
                <SearchableSelect :model-value="String(event.id)" @update:model-value="switchSportEvent"
                                  :options="sportEventOptions" :all-option="false" class="w-64" />
            </div>
        </div>

        <ReportHeadItemNavigator :groups="headItemGroups"
                                 :base-url="base"
                                 :selected-head-id="selectedHeadId"
                                 :selected-item-id="selectedItemId"
                                 :has-item-heads="hasItemHeads"
                                 :show-item-stats="true"
                                 :is-sports="true"
                                 :hint="'Pick a sport event (Athletics, Chess…), then an item to open filtered reports.'"
                                 empty-heads-text="No sport events configured yet. Add sport events from the Sports Setup hub.">

            <template v-if="selectedHeadId && !selectedItemId" #head-detail="{ head }">
                <FestHeadReportPanel :sahodaya-id="sahodaya.id"
                                     :event-id="event.id"
                                     :head="head" />
            </template>

            <template #default="{ item, head }">
                <FestItemReportPanel :sahodaya-id="sahodaya.id"
                                     :event-id="event.id"
                                     :item="item"
                                     :head="head ?? selectedHeadMeta" />
            </template>
        </ReportHeadItemNavigator>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import ReportHeadItemNavigator from '@/Components/reports/ReportHeadItemNavigator.vue';
import FestHeadReportPanel from '@/Components/reports/FestHeadReportPanel.vue';
import FestItemReportPanel from '@/Components/reports/FestItemReportPanel.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    headItemGroups: { type: Array, default: () => [] },
    hasItemHeads: { type: Boolean, default: false },
    selectedHeadId: { type: [String, Number], default: null },
    selectedItemId: { type: [String, Number], default: null },
    selectedItem: { type: Object, default: null },
    activityLogs: { type: Array, default: () => [] },
    childEvents: { type: Array, default: () => [] },
});

function switchSportEvent(value) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${value}/reports/by-head`);
}

const sportEventOptions = computed(() => props.childEvents.map((ev) => ({
    value: String(ev.id),
    label: ev.short_title || ev.title,
})));

const base = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/by-head`);
const reportsHubUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports`);

const selectedHeadMeta = computed(() => {
    if (!props.selectedHeadId || props.selectedHeadId === 'other') return null;
    return props.headItemGroups.find((g) => String(g.head_id ?? 'other') === String(props.selectedHeadId)) ?? null;
});

const pageTitle = computed(() => {
    if (props.selectedItem?.title) {
        return `${props.event.title} — ${props.selectedItem.title}`;
    }
    if (selectedHeadMeta.value?.head_name) {
        return `${props.event.title} — ${selectedHeadMeta.value.head_name}`;
    }
    return `${props.event.title} — Reports by Sport Event`;
});

const headerDescription = computed(() => {
    if (props.selectedItemId) {
        return 'Open a report below — all links are filtered to this item.';
    }
    if (props.selectedHeadId) {
        return 'Section reports for the whole head, or pick an item for item-specific reports.';
    }
    return 'Navigate by Sport Event, then pick an item — same flow as competition hub.';
});
</script>
