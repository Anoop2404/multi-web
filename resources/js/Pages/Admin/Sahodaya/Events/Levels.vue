<template>
    <SahodayaEventsLayout :title="`${event.title} — Levels`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Rounds & promotion`" eyebrow="Multi-level"
                    :description="isPartitionedHub ? 'Regional partitions, school rounds, and overall championship aggregation.' : (event.event_type === 'kids_fest' ? 'Kids Fest clusters, school rounds, and promotions.' : 'School rounds, promotions, and child events.')" />

        <EventSubNav v-if="event.event_type !== 'sports'" :sahodaya-id="sahodaya.id" :event-id="event.id" active="levels" />

        <div class="grid lg:grid-cols-2 gap-6 max-w-5xl">
            <div class="card space-y-4">
                <h4 class="section-title">Current round</h4>
                <p class="section-desc">
                    Round: <strong class="text-slate-700">{{ levelLabels[event.level_round] ?? event.level_round }}</strong>
                    <span v-if="conductMode" class="ml-2 text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">{{ conductMode }}</span>
                </p>
                <form v-if="event.state_program_id && !event.parent_event_id" @submit.prevent="submitStateQualifiers" class="space-y-1">
                    <button type="submit" class="btn-secondary w-full text-sm">Submit qualifiers to State</button>
                    <p class="form-hint">Sends regional/district winners via API outbox.</p>
                </form>
                <form v-if="event.conduct_levels?.includes('school') && schoolRoundCount > 0" @submit.prevent="promoteAllSchoolRounds" class="space-y-1">
                    <button type="submit" class="btn-primary w-full text-sm">Promote all school-round winners</button>
                    <p class="form-hint">Only rounds with published results are included.</p>
                </form>
                <form v-if="event.conduct_levels?.includes('school')" @submit.prevent="spawnSchoolRounds">
                    <button type="submit" class="btn-secondary w-full text-sm">Create school rounds ({{ schoolRoundCount }} exist)</button>
                </form>
            </div>

            <div class="card space-y-4">
                <h4 class="section-title">{{ showPartitionUi ? 'Regions & partitions' : (event.event_type === 'kids_fest' && !event.parent_event_id ? 'Geographic clusters' : 'Child events') }}</h4>

                <form v-if="!event.parent_event_id && conductPresets?.length" @submit.prevent="applyPreset" class="flex gap-2">
                    <select v-model="presetForm.preset" class="field flex-1 text-sm">
                        <option value="">Apply conduct preset…</option>
                        <option v-for="p in conductPresets" :key="p" :value="p">{{ p }}</option>
                    </select>
                    <button class="btn-secondary text-sm shrink-0" :disabled="!presetForm.preset">Apply</button>
                </form>

                <div v-if="showPartitionUi && event.event_type === 'kalolsavam'" class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-3 space-y-2">
                    <p class="text-xs text-slate-600">
                        Create a partition per membership region and assign every school by its
                        <a :href="`/sahodaya-admin/${sahodaya.id}/regions`" class="link-brand font-semibold">region assignment</a> in one step.
                    </p>
                    <button type="button" class="btn-secondary text-sm w-full" :disabled="regionSync.processing" @click="syncRegionPartitions">
                        {{ regionSync.processing ? 'Syncing…' : 'Sync partitions from membership regions' }}
                    </button>
                </div>

                <form v-if="showPartitionUi" @submit.prevent="spawnPartition" class="space-y-3">
                    <input v-model="partitionForm.title" class="field" placeholder="Partition title (e.g. Tirur Region)" required>
                    <div class="grid sm:grid-cols-2 gap-2">
                        <input v-model="partitionForm.partition_key" class="field text-sm" placeholder="Partition key (tirur)">
                        <select v-model="partitionForm.partition_role" class="field text-sm">
                            <option value="region">Region</option>
                            <option value="finale">District finale</option>
                            <option value="cluster">Cluster</option>
                            <option value="digi_fest">Digi Fest</option>
                        </select>
                    </div>
                    <input v-model="partitionForm.cluster_label" class="field text-sm" placeholder="Display label">
                    <input v-model="partitionForm.venue" class="field text-sm" placeholder="Venue">
                    <button class="btn-primary text-sm w-full">Create partition</button>
                </form>

                <form v-else-if="event.event_type === 'kids_fest' && !event.parent_event_id" @submit.prevent="spawnCluster" class="space-y-3">
                    <input v-model="clusterForm.title" class="field" placeholder="Cluster title (e.g. Nilambur Cluster)" required>
                    <div class="grid sm:grid-cols-2 gap-2">
                        <input v-model="clusterForm.cluster_key" class="field text-sm" placeholder="Cluster key (nilambur)">
                        <input v-model="clusterForm.cluster_label" class="field text-sm" placeholder="Display label">
                    </div>
                    <input v-model="clusterForm.venue" class="field text-sm" placeholder="Venue">
                    <button class="btn-primary text-sm w-full">Create cluster event</button>
                </form>

                <form v-else @submit.prevent="spawnChild" class="flex gap-2">
                    <input v-model="cascadeForm.title" class="field flex-1" placeholder="Child event title" required>
                    <button class="btn-primary text-sm shrink-0">Spawn child</button>
                </form>

                <ul v-if="partitions?.length || event.child_events?.length" class="text-sm space-y-2">
                    <li v-for="c in (partitions?.length ? partitions : event.child_events)" :key="c.id" class="flex flex-wrap items-center gap-2">
                        <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${c.id}`" class="link-brand">{{ c.title }}</Link>
                        <span v-if="c.partition_role" class="text-xs px-2 py-0.5 rounded-full bg-violet-50 text-violet-700">{{ c.partition_role }}</span>
                        <span v-if="c.cluster_label" class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">{{ c.cluster_label }}</span>
                    </li>
                </ul>
                <p v-else class="text-sm text-slate-400">No child events yet.</p>
            </div>
        </div>

        <div v-if="isPartitionedHub && memberSchools?.length" class="card mt-6 max-w-5xl space-y-4">
            <h4 class="section-title">School region assignments</h4>
            <p class="section-desc">Each member school must be assigned to exactly one region before registration.</p>
            <form @submit.prevent="saveAssignments" class="space-y-2 max-h-96 overflow-y-auto">
                <div v-for="school in memberSchools" :key="school.id" class="grid sm:grid-cols-2 gap-2 items-center text-sm">
                    <span>{{ school.name }}</span>
                    <select v-model="assignmentMap[school.id]" class="field text-sm">
                        <option value="">— Select region —</option>
                        <option v-for="p in partitions" :key="p.partition_key" :value="p.partition_key">{{ p.cluster_label || p.title }}</option>
                    </select>
                </div>
                <button class="btn-primary text-sm">Save assignments</button>
            </form>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8 max-w-5xl" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

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
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const showPartitionUi = computed(() => props.conductMode === 'partitioned' || props.event.event_type === 'kalolsavam');

const cascadeForm = useForm({ title: '' });
const clusterForm = useForm({ title: '', cluster_key: '', cluster_label: '', venue: '', event_start: '', event_end: '' });
const partitionForm = useForm({ title: '', partition_key: '', partition_role: 'region', cluster_label: '', venue: '' });
const presetForm = useForm({ preset: '' });
const regionSync = useForm({});
const assignmentMap = reactive({ ...props.schoolPartitions });

function spawnChild() {
    cascadeForm.post(`${base}/spawn`, { preserveScroll: true, onSuccess: () => cascadeForm.reset() });
}
function spawnCluster() {
    clusterForm.post(`${base}/spawn-cluster`, { preserveScroll: true, onSuccess: () => clusterForm.reset() });
}
function spawnPartition() {
    partitionForm.post(`${base}/spawn-partition`, { preserveScroll: true, onSuccess: () => partitionForm.reset() });
}
function syncRegionPartitions() {
    if (!confirm('Create a partition per membership region and assign schools by their region? Existing region partitions are kept.')) return;
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
function promoteAllSchoolRounds() {
    if (!confirm('Promote winners from all school rounds with published results into this cluster event?')) return;
    router.post(`${base}/promote-all-school-rounds`, {}, { preserveScroll: true });
}
function submitStateQualifiers() {
    if (!confirm('Submit current qualifiers to State? This uses the API outbox and may be retried.')) return;
    router.post(`${base}/submit-state-qualifiers`, {}, { preserveScroll: true });
}
</script>
