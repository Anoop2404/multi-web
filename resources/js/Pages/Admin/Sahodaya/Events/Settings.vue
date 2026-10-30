<template>
    <SahodayaEventsLayout title="Event settings" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                          :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Settings`" eyebrow="Event settings" :description="settingsDescription">
            <template #actions>
                <Link :href="backHref" class="btn-secondary shrink-0 text-sm">{{ backLabel }}</Link>
            </template>
        </PageHeader>

        <SportsSetupSubNav v-if="isSports"
                           :sahodaya-id="sahodaya.id" :event-id="event.id"
                           :event="event" active="settings" class="mb-4" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="settings" class="mb-4" />

        <ValidationBanner class="mb-4" />

        <EventSettingsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" :event="event" :active-tab="activeTab" />

        <nav class="mb-6 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-white p-3" aria-label="Settings sections">
            <Link v-for="section in activeCategory.sections" :key="section.id" :href="section.href"
                  :aria-current="activeTab === section.id ? 'page' : undefined"
                  :class="activeTab === section.id ? 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200' : 'text-slate-600 hover:bg-slate-50'"
                  class="rounded-xl px-4 py-2.5 text-sm font-semibold transition">{{ section.label }}</Link>
        </nav>
        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_16rem] gap-6">
            <!-- Left Column: Active Settings Forms. min-w-0 lets this column's content
                 (tables, long form rows) shrink to fit instead of a grid item's default
                 min-width:auto letting a wide child force the whole grid track — and the
                 page along with it — wider than the viewport. -->
            <div class="min-w-0 space-y-6">
                <!-- Group Tab 1: Fees & Windows -->
                <template v-if="['fees', 'registration', 'participation'].includes(activeTab)">
                    <div v-if="!page.props.operationalEventAdmin && activeTab === 'fees'" id="section-fees" class="scroll-mt-6"><FeesTab /></div>
                    <div v-if="activeTab === 'registration'" id="section-registration" class="scroll-mt-6"><RegistrationTab /></div>
                    <div v-if="activeTab === 'participation'" id="section-participation" class="scroll-mt-6"><ParticipationTab /></div>
                </template>

                <!-- Group Tab 2: Scoring & Rules. Rank Points and Grade Master used to live
                     here too (PointsTab/GradesTab) — promoted to their own top-level pages;
                     FestEventSettingsController::settings() redirects the old
                     ?tab=points/?tab=grades URLs there instead of rendering this group. -->
                <template v-else-if="['eligibility', 'combo', 'records'].includes(activeTab)">
                    <div v-if="isSports && activeTab === 'eligibility'" id="section-eligibility" class="scroll-mt-6"><EligibilityTab /></div>
                    <div v-if="!isSports && activeTab === 'combo'" id="section-combo" class="scroll-mt-6"><ComboTab /></div>
                    <div v-if="isSports && activeTab === 'records'" id="section-records" class="scroll-mt-6"><RecordsTab /></div>
                </template>

                <!-- Group Tab 3: Venues & Numbering -->
                <template v-else-if="['venues', 'numbering', 'volunteers'].includes(activeTab)">
                    <div v-if="activeTab === 'venues'" id="section-venues" class="scroll-mt-6"><VenuesTab /></div>
                    <div v-if="activeTab === 'numbering'" id="section-numbering" class="scroll-mt-6"><NumberingTab /></div>
                    <div v-if="activeTab === 'volunteers'" id="section-volunteers" class="scroll-mt-6"><VolunteersTab /></div>
                </template>

                <!-- Group Tab 4: General & Operations -->
                <template v-else>
                    <div v-if="activeTab === 'lifecycle'" id="section-lifecycle" class="scroll-mt-6"><LifecycleTab /></div>
                    <div v-if="activeTab === 'locks'" id="section-locks" class="scroll-mt-6"><LocksTab /></div>
                    <div v-if="!isSports && activeTab === 'overlays'" id="section-overlays" class="scroll-mt-6"><OverlaysTab /></div>
                    <div v-if="activeTab === 'clone'" id="section-clone" class="scroll-mt-6"><CloneTab /></div>
                </template>
            </div>

            <!-- Right Column: Quick Navigation & Shortcuts -->
            <aside class="min-w-0 space-y-4">
                <!-- Shortcuts Card -->
                <div class="card space-y-2.5">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Quick Actions &amp; Navigation</h4>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <Link :href="`${base}/setup`" class="btn-secondary !py-2 justify-center text-slate-700 flex items-center gap-1">
                            <span>⚙️ Setup Hub</span>
                        </Link>
                        <Link :href="`${base}/items`" class="btn-secondary !py-2 justify-center text-slate-700 flex items-center gap-1">
                            <span>🏆 Event Items</span>
                        </Link>
                        <Link :href="`${base}/registrations`" class="btn-secondary !py-2 justify-center text-slate-700 flex items-center gap-1">
                            <span>📝 Registrations</span>
                        </Link>
                        <Link :href="`${base}?overview=1`" class="btn-secondary !py-2 justify-center text-slate-700 flex items-center gap-1">
                            <span>📊 Overview</span>
                        </Link>
                    </div>
                </div>
            </aside>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { provide, computed, toRefs } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
const page = usePage();
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import ValidationBanner from '@/Components/ui/ValidationBanner.vue';
import EventSettingsSubNav from '@/Components/sahodaya/EventSettingsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import { useEventSettingsForms } from '@/composables/useEventSettingsForms.js';
import ParticipationTab from './Settings/Tabs/ParticipationTab.vue';
import EligibilityTab from './Settings/Tabs/EligibilityTab.vue';
import LocksTab from './Settings/Tabs/LocksTab.vue';
import VenuesTab from './Settings/Tabs/VenuesTab.vue';
import ComboTab from './Settings/Tabs/ComboTab.vue';
import VolunteersTab from './Settings/Tabs/VolunteersTab.vue';
import RecordsTab from './Settings/Tabs/RecordsTab.vue';
import LifecycleTab from './Settings/Tabs/LifecycleTab.vue';
import FeesTab from './Settings/Tabs/FeesTab.vue';
import RegistrationTab from './Settings/Tabs/RegistrationTab.vue';
import NumberingTab from './Settings/Tabs/NumberingTab.vue';
import CloneTab from './Settings/Tabs/CloneTab.vue';
import OverlaysTab from './Settings/Tabs/OverlaysTab.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    venues: Array,
    regions: { type: Array, default: () => [] },
    customClassGroups: { type: Array, default: () => [] },
    stages: Array,
    comboRules: Array,
    volunteers: Array,
    schools: Array,
    judgeGate: Object,
    lifecycle: Array,
    suggestedStatus: String,
    classGroups: Object,
    feeSchedule: Object,
    feeModels: Object,
    feePresets: Object,
    classGroupLabels: Object,
    classGroupScheme: String,
    classGroupSchemeOptions: Object,
    classCategorySchemes: { type: Array, default: () => [] },
    defaultClassGroupFees: Object,
    defaultParticipantTypeFees: Object,
    ageGroupLabels: Object,
    defaultAgeGroupFees: Object,
    numberingSettings: { type: Object, default: () => ({}) },
    schoolChestRanges: { type: Object, default: () => ({}) },
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
    notificationTriggers: { type: Array, default: () => [] },
    eligibleNotificationUsers: { type: Array, default: () => [] },
});

const ctx = useEventSettingsForms(props);
const { settingsDescription, activeTab } = ctx;

const base = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`);
const isSports = computed(() => props.event.event_type === 'sports');

const backHref = computed(() => (
    isSports.value
        ? `${base.value}/setup`
        : `${base.value}?overview=1`
));
const backLabel = computed(() => (isSports.value ? '← Setup hub' : '← Event overview'));

const activeCategory = computed(() => {
    const a = activeTab.value;
    if (['fees', 'registration', 'participation'].includes(a)) {
        const sections = page.props.operationalEventAdmin ? [] : [
            { id: 'fees', label: isSports.value ? 'Sport Event Fees' : 'Fest Event Fees', href: `${base.value}/settings/fees` },
        ];
        sections.push({ id: 'registration', label: 'Registration Windows', href: `${base.value}/settings/registration` });
        sections.push({ id: 'participation', label: 'Participation Policies', href: `${base.value}/settings/participation` });
        return { title: page.props.operationalEventAdmin ? 'Registration & Rules' : '💳 Fees & Windows', sections };
    }

    if (['eligibility', 'combo', 'records'].includes(a)) {
        const sections = [];
        if (isSports.value) sections.push({ id: 'eligibility', label: 'Age Cutoff Rules', href: `${base.value}/settings/eligibility` });
        if (!isSports.value) sections.push({ id: 'combo', label: 'Combo Rules', href: `${base.value}/settings/combo` });
        if (isSports.value) sections.push({ id: 'records', label: 'Meet Records', href: `${base.value}/settings/records` });
        return { title: '🏆 Scoring & Rules', sections };
    }

    if (['venues', 'numbering', 'volunteers'].includes(a)) {
        const sections = [
            { id: 'venues', label: 'Venues & Grounds', href: `${base.value}/settings/venues` },
            { id: 'numbering', label: 'Chest Numbering Ranges', href: `${base.value}/settings/numbering` },
        ];
        sections.push({ id: 'volunteers', label: 'Volunteers & Staff', href: `${base.value}/settings/volunteers` });
        return { title: '📍 Venues & Numbering', sections };
    }

    const sections = [
        { id: 'lifecycle', label: 'Lifecycle & Verification', href: `${base.value}/settings/lifecycle` },
        { id: 'locks', label: 'System Locks', href: `${base.value}/settings/locks` },
    ];
    if (!isSports.value) {
        sections.push({ id: 'overlays', label: 'Public Display', href: `${base.value}/settings/overlays` });
    }
    sections.push({ id: 'clone', label: 'Clone Event', href: `${base.value}/settings/clone` });
    return { title: isSports.value ? '⚙️ General & Operations' : '⚙️ General & Display', sections };
});

// `props` is Inertia's reactive page-props object. Spreading it directly (`{...props}`)
// copies out plain snapshots of each key at this exact moment — arrays like `venues`,
// `stages`, `regions` get frozen to whatever they were on first render. Every subsequent
// Inertia visit (add/remove venue, etc.) replaces `props.venues` with a brand-new array,
// but the already-provided plain object never sees it, so every tab injecting
// `eventSettings` and destructuring `venues`/`stages`/etc. shows stale data until a full
// page reload. `toRefs()` turns each prop into a ref that stays linked to the live
// reactive source, so destructuring in a child (`const { venues } = inject(...)`) keeps
// working without every Tab component needing to switch to `.value` access.
provide('eventSettings', {
    ...toRefs(props),
    ...ctx,
});
</script>
