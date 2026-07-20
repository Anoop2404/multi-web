<template>
    <SahodayaEventsLayout title="Event settings" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                          :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Settings`" eyebrow="Event settings" :description="settingsDescription">
            <template #actions>
                <Link :href="backHref" class="btn-secondary shrink-0 text-sm">{{ backLabel }}</Link>
            </template>
        </PageHeader>

        <EventSettingsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" :event="event" :active-tab="activeTab" />

        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Left Column: Active Settings Forms -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Group Tab 1: Fees & Windows -->
                <template v-if="['fees', 'registration', 'participation'].includes(activeTab)">
                    <div id="section-fees" class="scroll-mt-6"><FeesTab /></div>
                    <div v-if="isSports" id="section-registration" class="scroll-mt-6"><RegistrationTab /></div>
                    <div id="section-participation" class="scroll-mt-6"><ParticipationTab /></div>
                </template>

                <!-- Group Tab 2: Scoring & Rules -->
                <template v-else-if="['points', 'eligibility', 'grades', 'combo', 'records'].includes(activeTab)">
                    <div id="section-points" class="scroll-mt-6"><PointsTab /></div>
                    <div v-if="isSports" id="section-eligibility" class="scroll-mt-6"><EligibilityTab /></div>
                    <div v-if="!isSports" id="section-grades" class="scroll-mt-6"><GradesTab /></div>
                    <div v-if="!isSports" id="section-combo" class="scroll-mt-6"><ComboTab /></div>
                    <div v-if="isSports" id="section-records" class="scroll-mt-6"><RecordsTab /></div>
                </template>

                <!-- Group Tab 3: Venues & Numbering -->
                <template v-else-if="['venues', 'numbering', 'volunteers'].includes(activeTab)">
                    <div id="section-venues" class="scroll-mt-6"><VenuesTab /></div>
                    <div v-if="isSports" id="section-numbering" class="scroll-mt-6"><NumberingTab /></div>
                    <div id="section-volunteers" class="scroll-mt-6"><VolunteersTab /></div>
                </template>

                <!-- Group Tab 4: General & Operations -->
                <template v-else>
                    <div id="section-lifecycle" class="scroll-mt-6"><LifecycleTab /></div>
                    <div id="section-locks" class="scroll-mt-6"><LocksTab /></div>
                    <div id="section-clone" class="scroll-mt-6"><CloneTab /></div>
                </template>
            </div>

            <!-- Right Column: Quick Navigation & Shortcuts -->
            <aside class="lg:col-span-1 space-y-4">
                <div class="card space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                            {{ activeCategory.title }} Sections
                        </h4>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100">
                            {{ activeCategory.sections.length }} sections
                        </span>
                    </div>

                    <p class="text-xs text-slate-500">Quickly navigate sections under this category:</p>

                    <nav class="divide-y divide-slate-100 text-xs" aria-label="Settings section navigation">
                        <Link v-for="sec in activeCategory.sections" :key="sec.id"
                              :href="sec.href"
                              class="py-2.5 flex items-center justify-between group transition">
                            <div class="flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full transition"
                                      :class="activeTab === sec.id ? 'bg-indigo-600 ring-2 ring-indigo-200' : 'bg-slate-300 group-hover:bg-indigo-400'"></span>
                                <span class="font-medium text-slate-800 group-hover:text-indigo-600 transition"
                                      :class="{ 'font-bold text-indigo-600': activeTab === sec.id }">
                                    {{ sec.label }}
                                </span>
                            </div>
                            <span class="text-slate-400 group-hover:text-indigo-600 text-xs transition">→</span>
                        </Link>
                    </nav>
                </div>

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
import { provide, computed, onMounted, watch, nextTick } from 'vue';
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
        const sections = [
            { id: 'fees', label: isSports.value ? 'Sport Event Fees' : 'Fest Event Fees', href: `${base.value}/settings/fees` },
        ];
        if (isSports.value) sections.push({ id: 'registration', label: 'Registration Windows', href: `${base.value}/settings/registration` });
        sections.push({ id: 'participation', label: 'Participation Policies', href: `${base.value}/settings/participation` });
        return { title: '💳 Fees & Windows', sections };
    }

    if (['points', 'eligibility', 'grades', 'combo', 'records'].includes(a)) {
        const sections = [
            { id: 'points', label: 'Rank Points Table', href: `${base.value}/settings/points` },
        ];
        if (isSports.value) sections.push({ id: 'eligibility', label: 'Age Cutoff Rules', href: `${base.value}/settings/eligibility` });
        if (!isSports.value) sections.push({ id: 'grades', label: 'Grade Bands (A, B, C)', href: `${base.value}/settings/grades` });
        if (!isSports.value) sections.push({ id: 'combo', label: 'Combo Rules', href: `${base.value}/settings/combo` });
        if (isSports.value) sections.push({ id: 'records', label: 'Meet Records', href: `${base.value}/settings/records` });
        return { title: '🏆 Scoring & Rules', sections };
    }

    if (['venues', 'numbering', 'volunteers'].includes(a)) {
        const sections = [
            { id: 'venues', label: 'Venues & Grounds', href: `${base.value}/settings/venues` },
        ];
        if (isSports.value) sections.push({ id: 'numbering', label: 'Chest Numbering Ranges', href: `${base.value}/settings/numbering` });
        sections.push({ id: 'volunteers', label: 'Volunteers & Staff', href: `${base.value}/settings/volunteers` });
        return { title: '📍 Venues & Numbering', sections };
    }

    const sections = [
        { id: 'lifecycle', label: 'Lifecycle & Verification', href: `${base.value}/settings/lifecycle` },
        { id: 'locks', label: 'System Locks', href: `${base.value}/settings/locks` },
        { id: 'clone', label: 'Clone Event', href: `${base.value}/settings/clone` },
    ];
    return { title: '⚙️ General & Operations', sections };
});

function scrollToCurrentSection() {
    nextTick(() => {
        const el = document.getElementById(`section-${activeTab.value}`);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
}

onMounted(() => {
    scrollToCurrentSection();
});

watch(activeTab, () => {
    scrollToCurrentSection();
});

provide('eventSettings', {
    ...props,
    ...ctx,
});
</script>
