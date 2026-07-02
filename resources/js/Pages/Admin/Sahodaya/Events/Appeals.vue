<template>
    <SahodayaEventsLayout :title="`${event.title} — Appeals`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Appeals`" eyebrow="Operations"
                    description="Review school appeals, mark fees paid, disqualify or reinstate participants." />

        <FestEventWorkflowStepper :sahodaya-id="sahodaya.id" :event-id="event.id"
                                  :event-type="event.event_type" :current-step="'results'" />

        <div v-if="!event.appeals_open" class="notice-banner notice-banner--warning mb-4 text-sm">
            Appeals are closed for this event. Open them under Event settings → Locks.
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-4">
                <div class="card !p-4 flex flex-wrap gap-2 items-center">
                    <select v-model="filterStatus" class="field text-sm w-40">
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <span class="text-xs text-slate-500 ml-auto">{{ filteredAppeals.length }} appeal(s)</span>
                </div>

                <div class="card card--flush overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="p-3">Participant</th>
                                <th class="p-3">School / Item</th>
                                <th class="p-3">Reason</th>
                                <th class="p-3">Fee</th>
                                <th class="p-3">Status</th>
                                <th class="p-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="a in filteredAppeals" :key="a.id" class="border-t align-top">
                                <td class="p-3 font-medium">
                                    {{ participantName(a) }}
                                    <p v-if="a.participant?.student?.reg_no" class="text-xs font-mono text-[#0f3d7a]">{{ a.participant.student.reg_no }}</p>
                                </td>
                                <td class="p-3 text-xs">
                                    <p>{{ a.participant?.registration?.school?.name ?? '—' }}</p>
                                    <p class="text-slate-500">{{ a.participant?.registration?.item?.title }}</p>
                                </td>
                                <td class="p-3 text-xs max-w-xs">
                                    <p>{{ a.reason }}</p>
                                    <p v-if="a.resolution_note" class="text-slate-500 mt-1 italic">Note: {{ a.resolution_note }}</p>
                                </td>
                                <td class="p-3 text-xs whitespace-nowrap">
                                    <template v-if="a.fee_amount != null && Number(a.fee_amount) > 0">
                                        ₹{{ a.fee_amount }}
                                        <span v-if="a.fee_paid_at" class="text-emerald-700 block">Paid</span>
                                        <button v-else-if="a.status === 'pending'" type="button"
                                                class="text-[#0f3d7a] font-semibold block mt-0.5"
                                                @click="markFeePaid(a.id)">Mark paid</button>
                                    </template>
                                    <span v-else class="text-slate-300">—</span>
                                </td>
                                <td class="p-3">
                                    <span :class="statusClass(a.status)" class="text-xs font-semibold px-2 py-0.5 rounded capitalize">{{ a.status }}</span>
                                </td>
                                <td class="p-3 text-right">
                                    <button v-if="a.status === 'pending'" type="button"
                                            class="text-green-700 text-xs font-semibold mr-2"
                                            @click="openResolve(a, 'approved')">Approve</button>
                                    <button v-if="a.status === 'pending'" type="button"
                                            class="text-red-600 text-xs font-semibold"
                                            @click="openResolve(a, 'rejected')">Reject</button>
                                </td>
                            </tr>
                            <tr v-if="!filteredAppeals.length">
                                <td colspan="6" class="p-8 text-center text-gray-400">No appeals</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <aside class="space-y-4">
                <FormSection title="Disqualify participant" hint="Administrative disqualification (not an appeal).">
                    <form @submit.prevent="submitDisqualify" class="space-y-3">
                        <select v-model="disqualifyForm.participant_id" class="field text-sm" required>
                            <option value="">Select participant</option>
                            <option v-for="p in disqualifyCandidates" :key="p.id" :value="p.id">
                                {{ p.label }}
                            </option>
                        </select>
                        <textarea v-model="disqualifyForm.reason" class="field text-sm h-20" placeholder="Reason *" required></textarea>
                        <button type="submit" class="btn-secondary w-full text-sm text-red-700 border-red-200">Disqualify</button>
                    </form>
                </FormSection>

                <div v-if="disqualified?.length" class="card space-y-2">
                    <h3 class="font-semibold text-sm">Disqualified</h3>
                    <ul class="text-sm divide-y">
                        <li v-for="p in disqualified" :key="p.id" class="py-2 flex justify-between gap-2">
                            <span class="min-w-0">
                                <span class="font-medium">{{ p.student?.name ?? p.teacher?.name }}</span>
                                <span class="block text-xs text-slate-500 truncate">{{ p.disqualification_reason }}</span>
                            </span>
                            <button type="button" @click="reinstate(p.id)" class="text-indigo-600 text-xs font-semibold shrink-0">Reinstate</button>
                        </li>
                    </ul>
                </div>
            </aside>
        </div>

        <div v-if="resolveModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" @click="resolveModal = null"></div>
            <form @submit.prevent="submitResolve" class="relative modal-shell max-w-md w-full p-6 space-y-4">
                <h3 class="font-bold capitalize">{{ resolveModal.status }} appeal</h3>
                <p class="text-xs text-slate-500">{{ participantName(resolveModal.appeal) }}</p>
                <textarea v-model="resolveForm.resolution_note" class="field text-sm h-24" placeholder="Resolution note (optional)"></textarea>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-ghost text-sm" @click="resolveModal = null">Cancel</button>
                    <button type="submit" class="btn-primary text-sm">Confirm</button>
                </div>
            </form>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import FestEventWorkflowStepper from '@/Components/sahodaya/FestEventWorkflowStepper.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, appeals: Array, disqualified: Array,
    disqualifyCandidates: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const filterStatus = ref('');
const resolveModal = ref(null);
const resolveForm = reactive({ resolution_note: '' });
const disqualifyForm = reactive({ participant_id: '', reason: '' });

const filteredAppeals = computed(() => {
    if (!filterStatus.value) return props.appeals ?? [];
    return (props.appeals ?? []).filter(a => a.status === filterStatus.value);
});

function participantName(a) {
    const p = a.participant ?? a;
    return p.student?.name ?? p.teacher?.name ?? 'Participant';
}

function statusClass(status) {
    return {
        pending: 'bg-amber-100 text-amber-800',
        approved: 'bg-emerald-100 text-emerald-800',
        rejected: 'bg-red-100 text-red-700',
    }[status] ?? 'bg-slate-100 text-slate-600';
}

function openResolve(appeal, status) {
    resolveModal.value = { appeal, status };
    resolveForm.resolution_note = '';
}

function submitResolve() {
    router.post(`${base}/appeals/${resolveModal.value.appeal.id}/resolve`, {
        status: resolveModal.value.status,
        resolution_note: resolveForm.resolution_note || null,
    }, { preserveScroll: true, onSuccess: () => { resolveModal.value = null; } });
}

function markFeePaid(id) {
    router.post(`${base}/appeals/${id}/mark-fee-paid`, {}, { preserveScroll: true });
}

function submitDisqualify() {
    router.post(`${base}/participants/${disqualifyForm.participant_id}/disqualify`, {
        reason: disqualifyForm.reason,
    }, {
        preserveScroll: true,
        onSuccess: () => { disqualifyForm.participant_id = ''; disqualifyForm.reason = ''; },
    });
}

function reinstate(participantId) {
    router.post(`${base}/participants/${participantId}/reinstate`, {}, { preserveScroll: true });
}
</script>
