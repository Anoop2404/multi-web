<template>
    <SahodayaEventsLayout title="Mark entry" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Mark entry`" eyebrow="Scoring"
                    description="Enter grades, points, and athletic measurements for approved participants.">
            <template #actions>
                <Link :href="importUrl" class="btn-secondary shrink-0">Import marks</Link>
            </template>
        </PageHeader>

        <FestEventWorkflowStepper :sahodaya-id="sahodaya.id" :event-id="event.id"
                                  :event-type="event.event_type" :current-step="'operations'" />

        <p v-if="event.record_tracking_enabled"
           class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Athletic record tracking is on — enter time/distance in the measurement field for sports items.
        </p>
        <p class="mb-6 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            <strong>Position / rank:</strong> Enter place (1, 2, 3…). The same position can be assigned to multiple participants (ties, heats, or shared places).
            Points and grades are saved per athlete individually.
        </p>

        <EmptyState
            v-if="!registrations.length"
            title="No registrations to mark"
            description="Approve registrations first, then return here to enter marks."
            icon="📊"
        >
            <template #action>
                <Link :href="registrationsUrl" class="btn-primary">Review registrations</Link>
            </template>
        </EmptyState>

        <div v-else class="space-y-6">
            <section v-for="reg in registrations" :key="reg.id" class="card overflow-hidden p-0">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 bg-slate-50/80">
                    <div>
                        <h3 class="section-title">{{ reg.item?.title }}</h3>
                        <p v-if="reg.school?.name" class="section-desc mt-0.5">{{ reg.school.name }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <div v-if="markableParticipants(reg).length > 1" class="flex items-center gap-2 text-xs">
                            <label class="text-slate-600 whitespace-nowrap">Same rank for all:</label>
                            <input v-model.number="bulkRank[reg.id]" type="number" min="1" class="field !py-1 w-16" placeholder="#" />
                            <button type="button" class="btn-secondary text-xs !min-h-0 px-3 py-2"
                                    :disabled="!bulkRank[reg.id]"
                                    @click="applyBulkRank(reg)">
                                Apply
                            </button>
                        </div>
                        <button v-if="event.event_type === 'sports' && reg.item?.id"
                                type="button"
                                class="btn-secondary text-xs !min-h-0"
                                @click="autoRank(reg.item)">
                            Auto-rank
                        </button>
                        <span class="status-pill status-pill--published">{{ performerCount(reg) }} participant(s)</span>
                    </div>
                </div>

                <EmptyState
                    v-if="!markableParticipants(reg).length"
                    class="!shadow-none !border-0 rounded-none"
                    title="No participants linked"
                    description="This approved registration has no performers. Check the registration or re-approve it."
                    icon="👤"
                />

                <div v-else class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Participant</th>
                                <th class="w-28" title="Same rank allowed for ties">Position</th>
                                <th class="w-28">Grade</th>
                                <th class="w-28">Points</th>
                                <th v-if="showMeasurement(reg.item)" class="w-32">Measurement</th>
                                <th v-if="showMeasurement(reg.item)" class="w-24">Unit</th>
                                <th class="w-28 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in markableParticipants(reg)" :key="p.id">
                                <td class="font-medium text-slate-900">
                                    <span v-if="participantRegNo(p)" class="font-mono text-xs text-[#0f3d7a] mr-2">{{ participantRegNo(p) }}</span>
                                    {{ participantName(p) }}
                                    <span v-if="p.chest_no" class="ml-2 text-xs text-slate-500">Chest #{{ p.chest_no }}</span>
                                </td>
                                <td>
                                    <input v-model.number="markForms[p.id].position" type="number" min="1"
                                           class="field w-full" placeholder="Rank" title="Ties allowed">
                                </td>
                                <td>
                                    <select v-model="markForms[p.id].grade" class="field w-full">
                                        <option value="">—</option>
                                        <option>A</option>
                                        <option>A+</option>
                                        <option>B</option>
                                        <option>C</option>
                                    </select>
                                </td>
                                <td>
                                    <input v-model.number="markForms[p.id].score" type="number" min="0"
                                           class="field w-full" placeholder="Pts">
                                </td>
                                <td v-if="showMeasurement(reg.item)">
                                    <input v-model="markForms[p.id].measurement_value" class="field w-full"
                                           placeholder="Time/dist">
                                </td>
                                <td v-if="showMeasurement(reg.item)">
                                    <input v-model="markForms[p.id].measurement_unit" class="field w-full"
                                           placeholder="s/m">
                                </td>
                                <td class="text-right">
                                    <button type="button" class="btn-primary text-xs !min-h-0 px-3 py-2"
                                            @click="saveMark(p, reg.item)">
                                        Save
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
            <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { reactive, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import FestEventWorkflowStepper from '@/Components/sahodaya/FestEventWorkflowStepper.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    registrations: Array,
    marks: Object,
    activityLogs: { type: Array, default: () => [] },
});

const importUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/marks/import`);
const registrationsUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations`);

const markForms = reactive({});
const bulkRank = reactive({});
for (const reg of props.registrations ?? []) {
    for (const p of reg.participants ?? []) {
        const existing = props.marks[p.id];
        markForms[p.id] = {
            grade: existing?.grade ?? '',
            score: existing?.score ?? null,
            position: existing?.position ?? null,
            measurement_value: existing?.measurement_value ?? '',
            measurement_unit: existing?.measurement_unit ?? '',
        };
    }
}

function participantName(participant) {
    return participant.student?.name ?? participant.teacher?.name ?? 'Unnamed participant';
}

function participantRegNo(participant) {
    return participant.student?.reg_no ?? participant.teacher?.reg_no ?? null;
}

function performerCount(reg) {
    return markableParticipants(reg).length;
}

function markableParticipants(reg) {
    const list = reg.participants ?? [];
    const performers = list.filter((p) => p.participant_role !== 'standby');
    return performers.length ? performers : list;
}

function showMeasurement(item) {
    return props.event.record_tracking_enabled
        && (item?.category === 'sports' || item?.sport_discipline);
}

function applyBulkRank(reg) {
    const rank = bulkRank[reg.id];
    if (!rank || rank < 1) return;
    for (const p of markableParticipants(reg)) {
        if (markForms[p.id]) {
            markForms[p.id].position = rank;
        }
    }
}

function saveMark(participant, item) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/marks`, {
        participant_id: participant.id,
        item_id: item.id,
        ...markForms[participant.id],
    }, { preserveScroll: true });
}

function autoRank(item) {
    if (!item?.id) return;
    if (!confirm(`Auto-rank athletes for "${item.title}" from measurement values?`)) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/items/${item.id}/auto-rank`, {}, { preserveScroll: true });
}
</script>
