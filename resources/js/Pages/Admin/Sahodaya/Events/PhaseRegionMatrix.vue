<template>
    <SahodayaEventsLayout :title="`${event.title} — Region Matrix`" :sahodaya="sahodaya" :event="event" :show-header-title="false">
        <PageHeader :title="`${event.title} — Region Matrix`" eyebrow="Regional phases"
                    description="See and change which region each school competes in, per regional phase. Changing a locked selection migrates the school's existing registrations for that phase." />

        <EventSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="phase-regions" />

        <div v-if="phases.length === 0" class="card text-sm text-slate-400 max-w-3xl">
            This event has no regional phases configured yet. Mark a phase "Regional" and choose its allowed regions on the
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/phases`" class="link-brand font-semibold">Phases</Link> page first.
        </div>

        <div v-else class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="sticky left-0 bg-white">School</th>
                            <th v-for="phase in phases" :key="phase.id"
                                :class="phase.regions.length === 0 ? 'text-slate-300' : ''"
                                :title="phase.regions.length === 0 ? 'No regions configured for this phase yet — set them up on the Phases page.' : ''">
                                {{ phase.name }}
                                <span v-if="phase.regions.length === 0" class="block text-[10px] font-normal normal-case">not configured</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="school in schools" :key="school.id">
                            <td class="sticky left-0 bg-white font-medium text-slate-700">{{ school.name }}</td>
                            <td v-for="phase in phases" :key="`${school.id}-${phase.id}`"
                                :class="phase.regions.length === 0 ? 'bg-slate-50 text-slate-300' : ''">
                                <template v-if="phase.regions.length === 0">—</template>
                                <template v-else-if="isEditing(school, phase)">
                                    <div class="space-y-1.5 min-w-[13rem] py-1">
                                        <SearchableSelect v-model="overrideForm.region_id"
                                                          :options="regionOptions(phase, currentCell(school, phase)?.region_id)"
                                                          :all-option="false" placeholder="Choose new region…" />
                                        <textarea v-model="overrideForm.reason" class="field !py-1 !text-xs" rows="2" placeholder="Reason (required)"></textarea>
                                        <p v-if="overrideForm.errors.reason" class="text-[11px] text-red-600">{{ overrideForm.errors.reason }}</p>
                                        <p v-if="overrideForm.errors.region_id" class="text-[11px] text-red-600">{{ overrideForm.errors.region_id }}</p>
                                        <div class="flex gap-2">
                                            <button type="button" class="btn-primary text-xs" :disabled="overrideForm.processing" @click="saveOverride(school, phase)">Save</button>
                                            <button type="button" class="btn-ghost text-xs" @click="editingCell = null">Cancel</button>
                                        </div>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="flex items-center gap-1.5">
                                        <span v-if="!currentCell(school, phase)" class="text-slate-400">Not selected</span>
                                        <template v-else>
                                            <span class="font-medium text-slate-700">{{ currentCell(school, phase).region_name }}</span>
                                            <span v-if="currentCell(school, phase).locked" title="Locked — school can no longer self-service change this" class="text-amber-500">🔒</span>
                                            <button type="button" class="text-xs font-semibold text-[#0f3d7a] ml-1" @click="startEdit(school, phase)">Change</button>
                                        </template>
                                    </div>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="!schools.length">
                            <td :colspan="phases.length + 1" class="text-center text-slate-400 py-8">No approved schools yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <section class="mt-8 max-w-5xl">
            <h4 class="section-title mb-3">Pending region-change requests</h4>
            <div class="card overflow-hidden p-0">
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>School</th>
                                <th>Phase</th>
                                <th>Current &rarr; Requested</th>
                                <th>Reason</th>
                                <th>Requested</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in pendingRequests" :key="r.id">
                                <td>{{ r.school_name }}</td>
                                <td>{{ r.phase_name }}</td>
                                <td class="text-sm">{{ r.current_region_name || '—' }} &rarr; <strong>{{ r.requested_region_name }}</strong></td>
                                <td class="text-sm max-w-xs">{{ r.reason }}</td>
                                <td class="text-xs">{{ r.created_at ? new Date(r.created_at).toLocaleDateString() : '—' }}</td>
                                <td class="text-right whitespace-nowrap">
                                    <button type="button" class="btn-primary text-xs mr-1" @click="approveRequest(r)">Approve</button>
                                    <button type="button" class="btn-secondary text-xs" @click="rejectRequest(r)">Reject</button>
                                </td>
                            </tr>
                            <tr v-if="!pendingRequests.length">
                                <td colspan="6" class="text-center text-slate-400 py-8">No pending region-change requests.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </SahodayaEventsLayout>
</template>

<script setup>
import { router, useForm } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object,
    event: Object,
    phases: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
    selections: { type: Object, default: () => ({}) },
    pendingRequests: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const { prompt } = useConfirm();

const editingCell = ref(null);
const overrideForm = useForm({ region_id: null, reason: '' });

function currentCell(school, phase) {
    return props.selections[`${school.id}:${phase.id}`] || null;
}

function isEditing(school, phase) {
    return editingCell.value?.schoolId === school.id && editingCell.value?.phaseId === phase.id;
}

function startEdit(school, phase) {
    editingCell.value = { schoolId: school.id, phaseId: phase.id };
    overrideForm.reset();
    overrideForm.clearErrors();
}

function regionOptions(phase, excludeRegionId) {
    return phase.regions
        .filter((r) => r.region_id !== excludeRegionId)
        .map((r) => ({ value: r.region_id, label: r.name }));
}

function saveOverride(school, phase) {
    overrideForm.post(`${base}/phases/${phase.id}/schools/${school.id}/region`, {
        preserveScroll: true,
        onSuccess: () => { editingCell.value = null; },
    });
}

function approveRequest(r) {
    router.post(`${base}/region-change-requests/${r.id}/approve`, {}, { preserveScroll: true });
}

async function rejectRequest(r) {
    const note = (await prompt({ message: 'Rejection note (optional)', inputMultiline: true, inputRequired: false })) || '';
    router.post(`${base}/region-change-requests/${r.id}/reject`, { resolution_note: note }, { preserveScroll: true });
}
</script>
