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

        <ReportHeadItemNavigator :groups="headItemGroups"
                                 :base-url="base"
                                 :selected-head-id="selectedHeadId"
                                 :selected-item-id="selectedItemId"
                                 :has-item-heads="hasItemHeads"
                                 :show-item-stats="true"
                                 :is-sports="true"
                                 :hint="'Pick an Event Head (Athletics, Chess…), then an item to open filtered reports.'"
                                 empty-heads-text="Sync Event Heads from the competition hub, then return here.">

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
import { Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import ReportHeadItemNavigator from '@/Components/reports/ReportHeadItemNavigator.vue';
import FestHeadReportPanel from '@/Components/reports/FestHeadReportPanel.vue';
import FestItemReportPanel from '@/Components/reports/FestItemReportPanel.vue';

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
});

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
    return `${props.event.title} — Reports by Event Head`;
});

const headerDescription = computed(() => {
    if (props.selectedItemId) {
        return 'Open a report below — all links are filtered to this item.';
    }
    if (props.selectedHeadId) {
        return 'Section reports for the whole head, or pick an item for item-specific reports.';
    }
    return 'Navigate by Event Head, then pick an item — same flow as competition hub.';
});
</script>
