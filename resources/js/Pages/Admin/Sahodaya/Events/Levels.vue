<template>
    <SahodayaEventsLayout :title="`${event.title} — Rounds & Levels`" :sahodaya="sahodaya" :event="event"
                          :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">

        <!-- Header -->
        <PageHeader :title="`${event.title} — Rounds & Levels`" eyebrow="Topology & Partitioning"
                    :description="isPartitionedHub ? 'Regional preliminary partitions, school rounds, and overall championship aggregation.' : (event.event_type === 'kids_fest' ? 'Kids Fest clusters, school rounds, and promotions.' : 'Configure region-based preliminaries, school rounds, and child event routing.')">
            <template #actions>
                <div class="flex items-center gap-2">
                    <Link :href="`${base}/phases`" class="btn-secondary text-xs flex items-center gap-1">
                        <span>⚡ Manage Phases</span>
                    </Link>
                </div>
            </template>
        </PageHeader>

        <SportsSetupSubNav v-if="event.event_type === 'sports'" :sahodaya-id="sahodaya.id" :event-id="event.id" active="levels" :event="event" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="levels" />

        <div class="space-y-6 max-w-6xl">
            <!-- Conduct Choice Cards — shown only before a generic event has committed to
                 either system (see showConductChoice/conductSystemLocked above). Equal
                 visual weight on purpose: the old hero gave Region Split a full-width bold
                 CTA and Phases two small text links, which is how a Sahodaya ended up with
                 both systems half-configured on the same event in the first place. -->
            <div v-if="showConductChoice && conductSystemLocked === null" class="space-y-3">
                <p class="text-xs text-slate-500 max-w-2xl">
                    Pick <strong>Region Split</strong> if the event runs as one stage split only by region, with one flat registration fee. Pick <strong>Phases &amp; Payment Levels</strong> if it runs as several named stages (e.g. a district Kalotsav with Digi Fest, Off Stage, Sargadhara, District days) that schools pay for separately, level by level. These are mutually exclusive — whichever gets its first region/batch first locks in for this event.
                </p>
                <div class="grid sm:grid-cols-2 gap-4">
                <div class="card space-y-3 border-2 border-slate-200 hover:border-indigo-300 transition">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-slate-100 flex items-center justify-center text-xl shrink-0">🗺️</div>
                        <h3 class="text-base font-bold text-slate-900">Region Split</h3>
                    </div>
                    <p class="text-sm text-slate-600">One region choice for the whole event — splits it into one child event per active Sahodaya region.</p>
                    <button type="button" class="btn-primary text-xs w-full justify-center !py-2.5" :disabled="topologyForm.processing" @click="toggleQuickMode">
                        Choose Region Split
                    </button>
                </div>
                <div class="card space-y-3 border-2 border-slate-200 hover:border-indigo-300 transition">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-indigo-50 flex items-center justify-center text-xl shrink-0">⚡</div>
                        <h3 class="text-base font-bold text-slate-900">Phases &amp; Payment Levels</h3>
                    </div>
                    <p class="text-sm text-slate-600">Multiple named conduct phases (e.g. Digi Fest, Off Stage, Sargadhara), each with its own independent regions and payment level.</p>
                    <Link :href="`${base}/phases`" class="btn-primary text-xs w-full justify-center !py-2.5 block text-center">
                        Set Up Phases &amp; Levels
                    </Link>
                </div>
                </div>
            </div>

            <!-- Compact summary once locked to Phases & Payment Levels — the old sync
                 button/custom-partition form below stay hidden (showPartitionUi is
                 conductMode-driven and this event's conduct_mode is 'partitioned' too by
                 now, so gate on conductSystemLocked specifically to avoid showing both). -->
            <div v-else-if="showConductChoice && conductSystemLocked === 'phased'" class="card !p-5 flex flex-wrap items-center justify-between gap-4 bg-gradient-to-r from-slate-50 to-indigo-50/40 border border-indigo-100">
                <div class="flex items-center gap-3.5">
                    <div class="h-11 w-11 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-xl shrink-0 shadow">⚡</div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Using Phases &amp; Payment Levels</h3>
                        <p class="text-xs text-slate-500 mt-0.5">{{ phaseCount }} phase{{ phaseCount === 1 ? '' : 's' }} · {{ batchCount }} payment batch{{ batchCount === 1 ? '' : 'es' }}</p>
                    </div>
                </div>
                <Link :href="`${base}/phases`" class="btn-primary text-xs shrink-0">Manage on Phases page →</Link>
            </div>

            <!-- Hero Topology Overview Card — unchanged content, now shown for: kids_fest/
                 sports/child-events (always, via showConductChoice), and any generic event
                 already locked to region partitioning. -->
            <div v-else class="card bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white !p-6 shadow-xl rounded-2xl border border-indigo-900/50">
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-white/10 pb-5">
                    <div class="flex items-center gap-3.5">
                        <div class="h-12 w-12 rounded-xl bg-white/10 backdrop-blur flex items-center justify-center text-2xl shadow-inner border border-white/20">
                            🗺️
                        </div>
                        <div>
                            <div class="flex items-center gap-2.5">
                                <h3 class="text-lg font-bold text-white tracking-tight">Competition Structure</h3>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold uppercase tracking-wider"
                                      :class="conductMode === 'partitioned' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30'">
                                    {{ conductMode === 'partitioned' ? '⚡ Region-Wise / Multi-Region' : 'Single Event / Centralized' }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-300 mt-1 flex items-center gap-3">
                                <span>Current Round: <strong class="text-white">{{ levelLabels[event.level_round] ?? event.level_round }}</strong></span>
                                <span>·</span>
                                <span>Partitions: <strong class="text-white">{{ activePartitions.length }}</strong></span>
                                <span v-if="memberSchools?.length">· Assigned Schools: <strong class="text-white">{{ assignedSchoolsCount }} / {{ memberSchools.length }}</strong></span>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" @click="toggleQuickMode" class="btn-primary text-xs !bg-indigo-600 hover:!bg-indigo-500 text-white border-none shadow-md" :disabled="topologyForm.processing">
                            Switch to {{ conductMode === 'partitioned' ? 'Standard Single-Event' : 'Region-Based Mode' }}
                        </button>
                    </div>
                </div>

                <!-- Conduct Mode Settings Details -->
                <form @submit.prevent="updateTopology" class="pt-4 grid sm:grid-cols-3 gap-4 items-end text-xs">
                    <div>
                        <label class="font-bold text-slate-300 block mb-1.5 uppercase text-[10px] tracking-wider">Conduct Topology Mode</label>
                        <select v-model="topologyForm.conduct_mode" class="field text-xs bg-white/10 text-white border-white/20 focus:bg-slate-900 focus:text-white">
                            <option value="standard" class="text-slate-900">Single Venue / Single Event (Standard)</option>
                            <option value="partitioned" class="text-slate-900">Region-Wise Partitions / Multi-Region</option>
                        </select>
                    </div>

                    <div class="flex items-center">
                        <label v-if="topologyForm.conduct_mode === 'partitioned'" class="flex items-center gap-2 text-slate-300 cursor-pointer select-none">
                            <input type="checkbox" v-model="topologyForm.combine_regions_at_finale" class="rounded border-white/30 text-indigo-500 focus:ring-indigo-400 bg-white/10">
                            <span class="font-medium text-xs">Combine region scores into overall finale leaderboard</span>
                        </label>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="btn-secondary text-xs !bg-white/10 hover:!bg-white/20 !text-white !border-white/20 w-full sm:w-auto" :disabled="topologyForm.processing">
                            {{ topologyForm.processing ? 'Saving...' : 'Save Topology Settings' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Region Drill-Down Panel — Phase 4 / §2.5 of
                 docs/REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md. Only for a hub with
                 actual region children; lets a full admin see every region's status
                 at a glance instead of navigating into each region's own page. -->
            <RegionDrillDownPanel v-if="isPartitionedHub && !event.parent_event_id && regionDrillDown.length"
                                  :sahodaya-id="sahodaya.id" :regions="regionDrillDown" />

            <!-- Two-Column Operations Layout -->
            <div class="grid lg:grid-cols-12 gap-6">

                <!-- Left Column: Regional Partitions & Child Events (Span 7) — this whole
                     card is the OLD region-partition system's management UI (list/create
                     region-split children). Once a generic event has committed to Phases &
                     Payment Levels it has no purpose: its own children are phase leaves, not
                     legacy partitions, so activePartitions/partitions here are always empty,
                     and the "Add Custom Region Partition" form would just be rejected by
                     FestPartitionService::assertLegacyPartitioningAllowed() if submitted. -->
                <div v-if="showLegacyPartitionCard" class="lg:col-span-7 space-y-6">

                    <!-- Region Partitions & Sync Card -->
                    <div class="card space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div>
                                <h4 class="section-title !mb-0 flex items-center gap-2 text-base">
                                    <span>📍</span> {{ showPartitionUi ? 'Region Partitions & Preliminary Events' : (event.event_type === 'kids_fest' && !event.parent_event_id ? 'Geographic Clusters' : 'Child Events') }}
                                </h4>
                                <p class="section-desc mt-0.5">Manage preliminary region rounds, venues, and school assignments.</p>
                            </div>
                            <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 border border-slate-200">
                                {{ activePartitions.length }} {{ activePartitions.length === 1 ? 'Partition' : 'Partitions' }}
                            </span>
                        </div>

                        <!-- 1-Click Sync Partitions Banner -->
                        <div v-if="showPartitionUi" class="rounded-xl border border-indigo-200 bg-gradient-to-br from-indigo-50/90 via-blue-50/50 to-white p-4 space-y-3 shadow-sm">
                            <div class="flex items-start gap-3">
                                <div class="h-9 w-9 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-lg shrink-0 shadow">
                                    🔄
                                </div>
                                <div>
                                    <h5 class="text-xs font-bold uppercase tracking-wider text-indigo-950">Auto-Sync Membership Regions</h5>
                                    <p class="text-xs text-slate-600 mt-0.5 leading-relaxed">
                                        Generate a partition for every active Sahodaya region and automatically assign member schools based on their annual region assignment in 1 click.
                                    </p>
                                </div>
                            </div>
                            <button type="button" class="btn-primary text-xs w-full justify-center !py-2.5 bg-indigo-600 hover:bg-indigo-700 font-bold shadow" :disabled="regionSync.processing" @click="syncRegionPartitions">
                                <span>{{ regionSync.processing ? 'Syncing Partitions…' : '⚡ Sync Partitions from Sahodaya Regions' }}</span>
                            </button>
                        </div>

                        <!-- Active Partitions List Grid -->
                        <div v-if="activePartitions.length" class="space-y-3">
                            <h5 class="text-xs font-bold uppercase tracking-wider text-slate-400">Configured Partitions / Sub-Events</h5>
                            <div class="grid sm:grid-cols-2 gap-3">
                                <div v-for="c in activePartitions" :key="c.id" class="rounded-xl border border-slate-200 bg-white p-3.5 space-y-2 hover:border-indigo-300 hover:shadow-md transition">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${c.id}`" class="font-bold text-slate-900 text-sm hover:text-indigo-600 transition flex items-center gap-1.5">
                                                <span>{{ c.title }}</span>
                                                <span class="text-xs text-indigo-600">↗</span>
                                            </Link>
                                            <p v-if="c.venue" class="text-[11px] text-slate-500 mt-0.5">📍 {{ c.venue }}</p>
                                        </div>
                                        <span v-if="c.partition_role" class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100 shrink-0">
                                            {{ c.partition_role }}
                                        </span>
                                    </div>
                                    <div v-if="c.cluster_label" class="text-[11px] text-slate-600 bg-slate-50 px-2 py-1 rounded border border-slate-100 flex items-center justify-between">
                                        <span>Display Label:</span>
                                        <span class="font-medium text-slate-800">{{ c.cluster_label }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="rounded-xl border border-dashed border-slate-200 p-6 text-center text-slate-400 text-xs">
                            No region partitions created yet. Use the sync button above to auto-create them.
                        </div>

                        <!-- Custom Manual Partition Form (Collapsible/Accordion style) -->
                        <div class="pt-2 border-t border-slate-100">
                            <button type="button" @click="showCustomForm = !showCustomForm" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                                <span>{{ showCustomForm ? '− Hide Manual Partition Form' : '+ Add Custom Region Partition Manually' }}</span>
                            </button>

                            <form v-if="showCustomForm" @submit.prevent="spawnPartition" class="mt-3 bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-3 text-xs">
                                <h5 class="font-bold text-slate-700 uppercase tracking-wider text-[11px]">Add Custom Partition</h5>
                                <div>
                                    <label class="form-label text-xs">Partition Title *</label>
                                    <input v-model="partitionForm.title" class="field text-xs" placeholder="e.g. Tirur Region Fest" required>
                                </div>
                                <div class="grid sm:grid-cols-2 gap-2">
                                    <div>
                                        <label class="form-label text-xs">Partition Key</label>
                                        <input v-model="partitionForm.partition_key" class="field text-xs" placeholder="e.g. tirur">
                                    </div>
                                    <div>
                                        <label class="form-label text-xs">Role</label>
                                        <select v-model="partitionForm.partition_role" class="field text-xs">
                                            <option value="region">Region</option>
                                            <option value="finale">District Finale</option>
                                            <option value="cluster">Cluster</option>
                                            <option value="digi_fest">Digi Fest</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="grid sm:grid-cols-2 gap-2">
                                    <div>
                                        <label class="form-label text-xs">Display Label</label>
                                        <input v-model="partitionForm.cluster_label" class="field text-xs" placeholder="e.g. Tirur Zone">
                                    </div>
                                    <div>
                                        <label class="form-label text-xs">Venue</label>
                                        <input v-model="partitionForm.venue" class="field text-xs" placeholder="e.g. MES Central School">
                                    </div>
                                </div>
                                <button class="btn-primary text-xs w-full justify-center !py-2">Create Partition</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Competition Rounds & Promotions — full width when the
                     left column above is hidden. -->
                <div :class="showLegacyPartitionCard ? 'lg:col-span-5' : 'lg:col-span-12'" class="space-y-6">
                    
                    <!-- School Rounds & Promotions -->
                    <div class="card space-y-4">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h4 class="section-title !mb-0 flex items-center gap-2 text-base">
                                <span>🏆</span> Round Promotion &amp; Qualifiers
                            </h4>
                            <span class="text-xs font-semibold text-slate-500">
                                {{ schoolRoundCount }} school round{{ schoolRoundCount === 1 ? '' : 's' }}
                            </span>
                        </div>

                        <!-- Promotion Actions -->
                        <div class="space-y-3">
                            <div v-if="event.conduct_levels?.includes('school')" class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/80 space-y-2">
                                <h5 class="text-xs font-bold text-slate-800">School Level Competitions</h5>
                                <p class="text-[11px] text-slate-500 leading-relaxed">
                                    Spawn intra-school selection rounds or promote top winners directly to the Sahodaya/Region event.
                                </p>
                                <div class="grid gap-2 pt-1">
                                    <button v-if="schoolRoundCount > 0" type="button" @click="promoteAllSchoolRounds" class="btn-primary text-xs justify-center !py-2 w-full">
                                        Promote All School Round Winners
                                    </button>
                                    <button type="button" @click="spawnSchoolRounds" class="btn-secondary text-xs justify-center !py-2 w-full">
                                        Create School Selection Rounds
                                    </button>
                                </div>
                            </div>

                            <!-- State Qualifier Submission -->
                            <div v-if="event.state_program_id && !event.parent_event_id" class="p-3.5 rounded-xl border border-amber-200 bg-amber-50/60 space-y-2">
                                <h5 class="text-xs font-bold text-amber-950 flex items-center gap-1.5">
                                    <span>🏛️</span> State Qualification Outbox
                                </h5>
                                <p class="text-[11px] text-amber-800/90 leading-relaxed">
                                    Submit regional/district winners to the Central State Competition portal. If a committee has certified a
                                    nomination batch below, that curated list is used instead of raw results.
                                </p>
                                <Link :href="`${base}/state-nomination`" class="btn-secondary text-xs justify-center !py-2 w-full !bg-white hover:!bg-amber-100 !border-amber-300 !text-amber-900 block text-center">
                                    Review &amp; Nominate for State
                                </Link>
                                <button type="button" @click="submitStateQualifiers" class="btn-secondary text-xs justify-center !py-2 w-full !bg-white hover:!bg-amber-100 !border-amber-300 !text-amber-900">
                                    Submit Qualifiers to State
                                </button>
                            </div>

                            <!-- Conduct Presets -->
                            <div v-if="!event.parent_event_id && conductPresets?.length" class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/80 space-y-2">
                                <h5 class="text-xs font-bold text-slate-800">Conduct Presets</h5>
                                <form @submit.prevent="applyPreset" class="flex gap-2">
                                    <select v-model="presetForm.preset" class="field text-xs flex-1">
                                        <option value="">Apply conduct preset…</option>
                                        <option v-for="p in conductPresets" :key="p" :value="p">{{ p }}</option>
                                    </select>
                                    <button class="btn-secondary text-xs shrink-0" :disabled="!presetForm.preset">Apply</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Event Phases Link Card — redundant with the compact summary card
                         above once conductSystemLocked === 'phased', so hidden then. -->
                    <div v-if="conductSystemLocked !== 'phased'" class="card p-4 flex items-center justify-between gap-3 bg-gradient-to-r from-slate-50 to-indigo-50/30 border border-indigo-100">
                        <div>
                            <h5 class="text-xs font-bold uppercase tracking-wider text-indigo-950 flex items-center gap-1.5">
                                <span>⚡</span> Event Phases
                            </h5>
                            <p class="text-[11px] text-slate-500 mt-0.5">Split items into named phases (Off-stage, On-stage...)</p>
                        </div>
                        <Link :href="`${base}/phases`" class="btn-secondary text-xs shrink-0">
                            Manage Phases →
                        </Link>
                    </div>
                </div>
            </div>

            <!-- School Region Assignments Matrix Table -->
            <div v-if="isPartitionedHub && memberSchools?.length" class="card space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h4 class="section-title !mb-0 flex items-center gap-2 text-base">
                            <span>🏫</span> Member School Region Mapping
                        </h4>
                        <p class="section-desc mt-0.5">Assign each participating school to its corresponding region partition.</p>
                    </div>
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100">
                        {{ assignedSchoolsCount }} / {{ memberSchools.length }} Schools Assigned
                    </span>
                </div>

                <form @submit.prevent="saveAssignments" class="space-y-4">
                    <div class="rounded-xl border border-slate-200 overflow-hidden bg-white max-h-96 overflow-y-auto divide-y divide-slate-100">
                        <div v-for="school in memberSchools" :key="school.id" class="p-3 flex items-center justify-between gap-4 hover:bg-slate-50/70 transition">
                            <span class="font-medium text-slate-900 text-xs">{{ school.name }}</span>
                            <div class="w-64">
                                <select v-model="assignmentMap[school.id]" class="field text-xs !py-1.5">
                                    <option value="">— Select Region —</option>
                                    <option v-for="p in partitions" :key="p.partition_key" :value="p.partition_key">
                                        {{ p.cluster_label || p.title }}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end pt-2">
                        <button class="btn-primary text-xs font-bold px-5 py-2">Save School Assignments</button>
                    </div>
                </form>
            </div>

            <!-- Activity Logs -->
            <EventPageActivityLog :logs="activityLogs" class="pt-4" />
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import RegionDrillDownPanel from '@/Components/sahodaya/RegionDrillDownPanel.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, levelLabels: Object, schoolRoundCount: Number,
    activityLogs: { type: Array, default: () => [] },
    conductMode: { type: String, default: 'standard' },
    isPartitionedHub: { type: Boolean, default: false },
    partitions: { type: Array, default: () => [] },
    conductPresets: { type: Array, default: () => [] },
    memberSchools: { type: Array, default: () => [] },
    schoolPartitions: { type: Object, default: () => ({}) },
    regionDrillDown: { type: Array, default: () => [] },
    conductSystemLocked: { type: String, default: null },
    phaseCount: { type: Number, default: 0 },
    batchCount: { type: Number, default: 0 },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const { confirm } = useConfirm();
// kids_fest/sports have their own bespoke cluster/season flows (spawnCluster(),
// SportsSetupSubNav) that predate and don't map onto the generic "Region Split vs Phases
// & Payment Levels" choice below -- leave their experience exactly as it was. Child
// events (region/phase leaves) never show conduct-topology controls of their own either.
const showConductChoice = computed(() => !['kids_fest', 'sports'].includes(props.event.event_type) && !props.event.parent_event_id);
// No longer force-shown for every kalolsavam event regardless of its actual conduct
// choice -- that bias would silently defeat the decision cards below for exactly the
// event type this whole redesign is about. Purely conductMode-driven now.
const showPartitionUi = computed(() => props.conductMode === 'partitioned');
// The old region-partition management card (list/create region-split children) has no
// purpose once a generic event is locked to Phases & Payment Levels -- see the template
// comment above where this gates the whole left column. Always shown for kids_fest/
// sports/child-events (their own bespoke flows, showConductChoice false) or for a generic
// event not yet locked to phased.
const showLegacyPartitionCard = computed(() => !showConductChoice.value || props.conductSystemLocked !== 'phased');
const showCustomForm = ref(false);

const activePartitions = computed(() => props.partitions?.length ? props.partitions : (props.event.child_events || []));
const assignedSchoolsCount = computed(() => Object.values(assignmentMap).filter(Boolean).length);

const cascadeForm = useForm({ title: '' });
const clusterForm = useForm({ title: '', cluster_key: '', cluster_label: '', venue: '', event_start: '', event_end: '' });
const partitionForm = useForm({ title: '', partition_key: '', partition_role: 'region', cluster_label: '', venue: '' });
const presetForm = useForm({ preset: '' });
const regionSync = useForm({});
const topologyForm = useForm({
    conduct_mode: props.event.conduct_mode || 'standard',
    combine_regions_at_finale: props.event.combine_regions_at_finale ?? true,
});
const assignmentMap = reactive({ ...props.schoolPartitions });

function toggleQuickMode() {
    const nextMode = props.conductMode === 'partitioned' ? 'standard' : 'partitioned';
    topologyForm.conduct_mode = nextMode;
    updateTopology();
}

function updateTopology() {
    topologyForm.post(`${base}/conduct-topology`, { preserveScroll: true });
}

function spawnChild() {
    cascadeForm.post(`${base}/spawn`, { preserveScroll: true, onSuccess: () => cascadeForm.reset() });
}
function spawnCluster() {
    clusterForm.post(`${base}/spawn-cluster`, { preserveScroll: true, onSuccess: () => clusterForm.reset() });
}
function spawnPartition() {
    partitionForm.post(`${base}/spawn-partition`, { preserveScroll: true, onSuccess: () => { partitionForm.reset(); showCustomForm.value = false; } });
}
async function syncRegionPartitions() {
    if (!(await confirm({ message: 'Create a partition per membership region and assign schools by their region? Existing region partitions are kept.', destructive: false }))) return;
    regionSync.post(`${base}/sync-region-partitions`, { preserveScroll: true });
}
function applyPreset() {
    presetForm.post(`${base}/apply-conduct-preset`, { preserveScroll: true });
}
function saveAssignments() {
    const assignments = Object.entries(assignmentMap)
        .filter(([, key]) => key)
        .map(([school_id, partition_key]) => ({ school_id, partition_key }));
    router.post(`${base}/assign-school-partitions`, { assignments }, { preserveScroll: true });
}
function spawnSchoolRounds() {
    router.post(`${base}/spawn-school-rounds`, {}, { preserveScroll: true });
}
async function promoteAllSchoolRounds() {
    if (!(await confirm({ message: 'Promote winners from all school rounds with published results into this cluster event?', destructive: false }))) return;
    router.post(`${base}/promote-all-school-rounds`, {}, { preserveScroll: true });
}
async function submitStateQualifiers() {
    if (!(await confirm({ message: 'Submit current qualifiers to State? This uses the API outbox and may be retried.', destructive: false }))) return;
    router.post(`${base}/submit-state-qualifiers`, {}, { preserveScroll: true });
}
</script>
