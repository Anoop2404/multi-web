<template>
    <SahodayaEventsLayout title="Event settings" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Settings`" eyebrow="Event settings" :description="settingsDescription">
            <template #actions>
                <Link :href="backHref" class="btn-secondary shrink-0 text-sm">{{ backLabel }}</Link>
            </template>
        </PageHeader>

        <EventSettingsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" :event="event" :active-tab="activeTab" />

        <SportsSetupSubNav v-if="event.event_type === 'sports'"
                           :sahodaya-id="sahodaya.id" :event-id="event.id" :event="event"
                           :active="sportsSetupActive" class="mb-4" />

        <ParticipationTab v-if="activeTab === 'participation'" />
        <EligibilityTab v-else-if="activeTab === 'eligibility'" />
        <LocksTab v-else-if="activeTab === 'locks'" />
        <VenuesTab v-else-if="activeTab === 'venues'" />
        <ComboTab v-else-if="activeTab === 'combo'" />
        <GradesTab v-else-if="activeTab === 'grades'" />
        <PointsTab v-else-if="activeTab === 'points'" />
        <VolunteersTab v-else-if="activeTab === 'volunteers'" />
        <RecordsTab v-else-if="activeTab === 'records'" />
        <LifecycleTab v-else-if="activeTab === 'lifecycle'" />
        <FeesTab v-else-if="activeTab === 'fees'" />
        <RegistrationTab v-else-if="activeTab === 'registration'" />
        <NumberingTab v-else-if="activeTab === 'numbering'" />
        <CloneTab v-else-if="activeTab === 'clone'" />

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { provide } from 'vue';
import { Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSettingsSubNav from '@/Components/sahodaya/EventSettingsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import { useEventSettingsForms } from '@/composables/useEventSettingsForms.js';
import ParticipationTab from './Settings/Tabs/ParticipationTab.vue';
import EligibilityTab from './Settings/Tabs/EligibilityTab.vue';
import LocksTab from './Settings/Tabs/LocksTab.vue';
import VenuesTab from './Settings/Tabs/VenuesTab.vue';
import ComboTab from './Settings/Tabs/ComboTab.vue';
import GradesTab from './Settings/Tabs/GradesTab.vue';
import PointsTab from './Settings/Tabs/PointsTab.vue';
import VolunteersTab from './Settings/Tabs/VolunteersTab.vue';
import RecordsTab from './Settings/Tabs/RecordsTab.vue';
import LifecycleTab from './Settings/Tabs/LifecycleTab.vue';
import FeesTab from './Settings/Tabs/FeesTab.vue';
import RegistrationTab from './Settings/Tabs/RegistrationTab.vue';
import NumberingTab from './Settings/Tabs/NumberingTab.vue';
import CloneTab from './Settings/Tabs/CloneTab.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import { computed } from 'vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    venues: Array,
    stages: Array,
    comboRules: Array,
    gradeConfigs: Array,
    pointRules: Array,
    rankPoints: { type: Array, default: () => [] },
    groupRankPoints: { type: Array, default: () => [] },
    volunteers: Array,
    schools: Array,
    judgeGate: Object,
    lifecycle: Array,
    suggestedStatus: String,
    classGroups: Object,
    feeSchedule: Object,
    feeModels: Object,
    classGroupLabels: Object,
    classGroupScheme: String,
    classGroupSchemeOptions: Object,
    defaultClassGroupFees: Object,
    defaultParticipantTypeFees: Object,
    ageGroupLabels: Object,
    defaultAgeGroupFees: Object,
    numberingSettings: { type: Object, default: () => ({}) },
    initialTab: { type: String, default: 'lifecycle' },
    participationPolicy: Object,
    participationPresets: Object,
    ageRuleSummary: { type: String, default: null },
    suggestedAgeCutoff: { type: String, default: null },
    defaultCutoffLabel: { type: String, default: null },
    ageGroupHelp: { type: Array, default: () => [] },
    schoolVerifications: { type: Array, default: () => [] },
    mandatoryGaps: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
    itemHeads: { type: Array, default: () => [] },
    ledgerAccount: { type: Object, default: () => ({}) },
    clusterRequireStudentVerification: { type: Boolean, default: true },
});

const ctx = useEventSettingsForms(props);
const { settingsDescription, activeTab } = ctx;

const sportsSetupActive = computed(() => {
    const map = {
        fees: 'fees',
        points: 'rank-points',
        registration: 'registration',
        numbering: 'numbering',
    };
    return map[activeTab.value] ?? 'settings';
});

const isSports = computed(() => props.event.event_type === 'sports');
const backHref = computed(() => (
    isSports.value
        ? `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/setup`
        : `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`
));
const backLabel = computed(() => (isSports.value ? '← Setup hub' : '← Event overview'));

provide('eventSettings', {
    ...props,
    ...ctx,
});
</script>
