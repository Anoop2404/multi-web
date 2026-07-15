<template>
    <SahodayaEventsLayout :title="`${event.title} — Competition`" :sahodaya="sahodaya" :event="event"
                         :show-header-title="false">
        <PageHeader :title="pageTitle" eyebrow="Competition"
                    :description="headerDescription" />

        <SportsSetupSubNav v-if="isSports" :sahodaya-id="sahodaya.id" :event-id="event.id"
                           :event="event" active="competition" class="mb-4" />

        <FestCompetitionSetupBar v-if="isSports && isRoot"
                                 :sahodaya-id="sahodaya.id"
                                 :event-id="event.id"
                                 :disciplines="disciplines"
                                 :taxonomy-masters-url="taxonomyMastersUrl"
                                 :sports-hub-url="sportsHubUrl"
                                 :promote-status="promoteStatus" />

        <div v-if="isSports && isRoot && promoteStatus?.can_promote"
             class="rounded-lg border border-indigo-100 bg-indigo-50/80 px-4 py-3 mb-6 text-sm text-indigo-950">
            <p class="font-semibold">
                {{ promoteStatus.pending_count }} Event Head{{ promoteStatus.pending_count === 1 ? '' : 's' }} ready to promote
            </p>
            <p class="mt-1 text-xs text-indigo-900/80">
                Turn each head into its own discipline event (own registration, fees, marks). Done from the Sports hub.
            </p>
            <Link :href="sportsHubUrl" class="inline-block mt-2 text-xs font-semibold underline">
                Open Sports hub to promote →
            </Link>
        </div>

        <ReportHeadItemNavigator :groups="headItemGroups"
                                 :base-url="base"
                                 :selected-head-id="selectedHeadId"
                                 :selected-item-id="selectedItemId"
                                 :has-item-heads="hasItemHeads"
                                 :show-item-stats="true"
                                 :is-sports="isSports"
                                 :hint="navHint"
                                 empty-heads-text="Sync Event Heads from the catalog using the form above, then open a section.">

            <template v-if="selectedHeadMeta && !selectedItemId" #head-detail="{ head }">
                <FestHeadManagePanel v-if="head.head_id && selectedHeadRecord"
                                     :sahodaya-id="sahodaya.id"
                                     :event-id="event.id"
                                     :head="head"
                                     :head-record="selectedHeadRecord"
                                     :disciplines="disciplines"
                                     :show-head-fees="showHeadFees"
                                     :notification-triggers="notificationTriggers"
                                     :eligible-notification-users="eligibleNotificationUsers" />
            </template>

            <template #default="{ item, head }">
                <FestItemConfigPanel v-if="itemConfig"
                                     :sahodaya-id="sahodaya.id"
                                     :event-id="event.id"
                                     :item-config="itemConfig"
                                     :heads-for-assign="headsForFilter"
                                     :catalog-url="catalogUrl"
                                     :is-sports="isSports" />
                <FestItemOpsPanel :sahodaya-id="sahodaya.id"
                                  :event="event"
                                  :item="item"
                                  :head="head ?? selectedHeadMeta" />
            </template>
        </ReportHeadItemNavigator>
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import ReportHeadItemNavigator from '@/Components/reports/ReportHeadItemNavigator.vue';
import FestCompetitionSetupBar from '@/Components/fest/FestCompetitionSetupBar.vue';
import FestHeadManagePanel from '@/Components/fest/FestHeadManagePanel.vue';
import FestItemConfigPanel from '@/Components/fest/FestItemConfigPanel.vue';
import FestItemOpsPanel from '@/Components/fest/FestItemOpsPanel.vue';

const props = defineProps({
    sahodaya: Object,
    event: Object,
    headItemGroups: { type: Array, default: () => [] },
    headsForFilter: { type: Array, default: () => [] },
    hasItemHeads: { type: Boolean, default: false },
    selectedHeadId: { type: [String, Number], default: null },
    selectedItemId: { type: [String, Number], default: null },
    selectedItem: { type: Object, default: null },
    selectedHeadRecord: { type: Object, default: null },
    itemConfig: { type: Object, default: null },
    disciplines: { type: Object, default: () => ({}) },
    taxonomyMastersUrl: { type: String, default: null },
    catalogUrl: { type: String, default: null },
    showHeadFees: { type: Boolean, default: true },
    sportsHubUrl: { type: String, default: null },
    promoteStatus: { type: Object, default: null },
    notificationTriggers: { type: Array, default: () => [] },
    eligibleNotificationUsers: { type: Array, default: () => [] },
});

const base = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/competition`);
const isSports = computed(() => props.event.event_type === 'sports');
const isRoot = computed(() => !props.selectedHeadId && !props.selectedItemId);

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
    return `${props.event.title} — Competition hub`;
});

const headerDescription = computed(() => {
    if (props.selectedItemId) {
        return 'Configure this item, then use the actions below for registrations, ranks, and results.';
    }
    if (props.selectedHeadId) {
        return 'Set Event Head dates and fees, then pick an item to manage registrations and results.';
    }
    return 'One place for Event Heads, dates, fees, and competition workflow — sync heads, open a section, pick an item.';
});

const navHint = computed(() => {
    if (props.hasItemHeads) {
        return 'Pick an Event Head (Athletics, Chess, …), set dates if needed, then choose an item.';
    }
    return 'Select a competition item to open admin actions.';
});
</script>
