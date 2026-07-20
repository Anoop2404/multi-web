<template>
    <SahodayaEventsLayout title="Mark entry" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Mark entry`" eyebrow="Mark entry"
                    :description="filterDescription">
            <template #actions>
                <Link v-if="competitionUrl" :href="competitionUrl" class="btn-secondary shrink-0 text-sm">← {{ isSports ? 'By Event Head' : 'By item head' }}</Link>
                <Link :href="importUrl" class="btn-secondary shrink-0">Import marks</Link>
            </template>
        </PageHeader>

        <p v-if="selectedItemId" class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
            Showing marks for one item only.
            <Link :href="competitionUrl" class="font-semibold underline ml-1">Back to item listing</Link>
        </p>

        <SportsSetupSubNav v-if="isSports" :sahodaya-id="sahodaya.id" :event-id="event.id"
                           :event="event" active="marks" class="mb-4" />
        <FestEventWorkflowStepper v-else :sahodaya-id="sahodaya.id" :event-id="event.id"
                                  :event-type="event.event_type" :current-step="'operations'" />

        <p v-if="isSports && event.record_tracking_enabled"
           class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Record tracking is on — new records may trigger automatic prize labels when marks are saved.
        </p>
        <p v-if="isSports && rankPoints.length" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            <strong>Rank points:</strong>
            <span v-for="(row, i) in rankPoints" :key="row.rank">
                {{ rankLabel(row.rank) }} = {{ row.points }} pt{{ row.points === 1 ? '' : 's' }}<span v-if="i < rankPoints.length - 1"> · </span>
            </span>.
            Tied athletes share the same rank and receive the same points.
            <Link :href="settingsPointsUrl" class="font-semibold underline ml-1">Edit rank master →</Link>
        </p>
        <p class="mb-6 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            <template v-if="isSports">
                <strong>Sports scoring:</strong> Mark attendance, enter time/distance, then use <strong>Auto-rank</strong> or pick rank from the dropdown.
                Team points apply automatically when you pick a rank (see rank master above).
            </template>
            <template v-else>
                <strong>Position / rank:</strong> Enter place (1, 2, 3…). The same position can be assigned to multiple participants (ties, heats, or shared places).
                Points and grades are saved per participant individually.
            </template>
        </p>

        <!-- Filters Panel -->
        <div v-if="isSports && childEvents.length" class="card mb-4">
            <div class="flex flex-wrap gap-3 items-center">
                <div>
                    <label class="text-xs font-semibold text-gray-600">Select Sport Event</label>
                    <select :value="event.id" @change="switchSportEvent" class="field text-sm mt-1 w-64">
                        <option v-for="ev in childEvents" :key="ev.id" :value="ev.id">
                            {{ ev.title }} {{ ev.parent_event_id === null ? '(Season Hub)' : '' }}
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <EmptyState
            v-if="!sections.length"
            title="No registrations to mark"
            description="Approve registrations first, then return here to enter marks."
            icon="📊"
        >
            <template #action>
                <Link :href="registrationsUrl" class="btn-primary">Review registrations</Link>
            </template>
        </EmptyState>

        <div v-else class="space-y-6">
            <div class="flex flex-wrap items-center justify-end gap-2">
                <button type="button" class="btn-secondary text-sm"
                        :disabled="bulkSaving || !sections.length"
                        @click="saveAll">
                    {{ bulkSaving ? 'Saving all…' : 'Save all marks' }}
                </button>
            </div>
            <section v-for="section in sections" :key="section.key" class="card overflow-hidden p-0">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 bg-slate-50/80">
                    <div>
                        <h3 class="section-title">{{ section.item?.title }}</h3>
                        <div v-if="isSports" class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-500 mt-1">
                            <span v-if="section.item?.competition_start">
                                📅 {{ formatDate(section.item.competition_start) }}<span v-if="section.item.competition_time"> @ {{ section.item.competition_time.slice(0, 5) }}</span>
                            </span>
                            <span v-if="section.item?.squad_summary">
                                👥 {{ section.item.squad_summary }}
                            </span>
                            <span v-else-if="section.item?.min_group_size" class="text-indigo-600">
                                👥 Squad: {{ section.item.min_group_size }}–{{ section.item.max_group_size || section.item.min_group_size }} athletes
                            </span>
                        </div>
                        <p v-if="section.schoolName" class="section-desc mt-0.5">{{ section.schoolName }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <div v-if="section.rows.length > 1 && isSports" class="flex items-center gap-2 text-xs">
                            <label class="text-slate-600 whitespace-nowrap">Same rank for all:</label>
                            <select v-model="bulkRank[section.bulkKey]" class="field !py-1.5 text-sm min-w-[11rem]">
                                <option :value="null">—</option>
                                <option v-for="opt in rankOptionsForItem(section.item)" :key="opt.rank" :value="opt.rank">
                                    {{ opt.label }} ({{ opt.points }} pts)
                                </option>
                            </select>
                            <button type="button" class="btn-secondary text-xs !min-h-0 px-3 py-2"
                                    :disabled="!bulkRank[section.bulkKey]"
                                    @click="applyBulkRank(section, markForms)">
                                Apply
                            </button>
                        </div>
                        <div v-else-if="section.rows.length > 1 && !isSports" class="flex items-center gap-2 text-xs">
                            <label class="text-slate-600 whitespace-nowrap">Same rank for all:</label>
                            <input v-model.number="bulkRank[section.bulkKey]" type="number" min="1" class="field !py-1 w-16" placeholder="#" />
                            <button type="button" class="btn-secondary text-xs !min-h-0 px-3 py-2"
                                    :disabled="!bulkRank[section.bulkKey]"
                                    @click="applyBulkRank(section, markForms)">
                                Apply
                            </button>
                        </div>
                        <button v-if="isSports && section.item?.id"
                                type="button"
                                class="btn-secondary text-xs !min-h-0"
                                @click="autoRank(section.item)">
                            Auto-rank
                        </button>
                        <span class="status-pill status-pill--published">{{ section.rows.length }} participant(s)</span>
                    </div>
                </div>

                <EmptyState
                    v-if="!section.rows.length"
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
                                <th v-if="isSports" class="min-w-[6.5rem]">Attendance</th>
                                <th v-if="showMeasurement(section.item)" class="min-w-[8rem]">Time / distance</th>
                                <th v-if="showMeasurement(section.item)" class="min-w-[4rem] w-20">Unit</th>
                                <th :class="isSports ? 'min-w-[11rem]' : 'w-28'" title="Same rank allowed for ties">Rank</th>
                                <th v-if="!isSports" class="w-28">Grade</th>
                                <th v-if="!isSports" class="w-28">Points</th>
                                <th class="w-28 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="{ participant, item } in section.rows" :key="participant.id"
                                :class="isAbsent(participant, item) ? 'bg-slate-50/80 opacity-60' : ''">
                                <td class="font-medium text-slate-900">
                                    <span v-if="participant.chest_no" class="font-mono text-xs font-semibold text-indigo-800 mr-2">
                                        Chest #{{ participant.chest_no }}
                                    </span>
                                    <span v-if="participantRegNo(participant)" class="font-mono text-xs text-[#0f3d7a] mr-2">
                                        {{ participantRegNo(participant) }}
                                    </span>
                                    <span v-if="!isSports">{{ participantName(participant) }}</span>
                                    <span v-else class="text-slate-700">{{ participantName(participant) }}</span>
                                    <span v-if="participant._is_team" class="ml-1 inline-flex items-center rounded-full bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700 align-middle">
                                        Team · {{ participant._member_count }}
                                    </span>
                                </td>
                                <td v-if="isSports" class="align-middle">
                                    <select :value="attendanceStatus(participant, item)"
                                            class="field min-w-[6.5rem] w-full text-sm !py-2"
                                            @change="markAttendance(participant, item, $event.target.value)">
                                        <option value="">—</option>
                                        <option value="present">Present</option>
                                        <option value="absent">Absent</option>
                                    </select>
                                </td>
                                <td v-if="showMeasurement(section.item)" class="align-middle">
                                    <input v-model="markForms[participant.id].measurement_value"
                                           class="field w-full min-w-[7rem]"
                                           placeholder="7.45 / 5.20"
                                           :disabled="isAbsent(participant, item)">
                                </td>
                                <td v-if="showMeasurement(section.item)" class="align-middle">
                                    <input v-model="markForms[participant.id].measurement_unit"
                                           class="field w-full min-w-[4rem]"
                                           placeholder="s / m"
                                           :disabled="isAbsent(participant, item)">
                                </td>
                                <td class="align-middle">
                                    <select v-if="isSports"
                                            :value="markForms[participant.id].position ?? ''"
                                            class="field min-w-[11rem] w-full text-sm !py-2"
                                            :disabled="isAbsent(participant, item)"
                                            @change="setRank(participant.id, item, markForms, $event.target.value)">
                                        <option value="">—</option>
                                        <option v-for="opt in rankOptionsForItem(item)" :key="opt.rank" :value="opt.rank">
                                            {{ opt.label }} ({{ opt.points }} pts)
                                        </option>
                                    </select>
                                    <input v-else
                                           v-model.number="markForms[participant.id].position" type="number" min="1"
                                           class="field w-full" placeholder="Rank" title="Ties allowed"
                                           @input="applyRankPoints(participant.id, item, markForms)">
                                </td>
                                <td v-if="!isSports">
                                    <select v-model="markForms[participant.id].grade" class="field w-full">
                                        <option value="">—</option>
                                        <option>A</option>
                                        <option>A+</option>
                                        <option>B</option>
                                        <option>C</option>
                                    </select>
                                </td>
                                <td v-if="!isSports">
                                    <input v-model.number="markForms[participant.id].score" type="number" min="0"
                                           class="field w-full" placeholder="Pts">
                                </td>
                                <td class="text-right align-middle">
                                    <div class="flex items-center justify-end gap-2">
                                        <span v-if="savedIds.has(participant.id)" class="text-xs font-semibold text-emerald-600">Saved ✓</span>
                                        <button type="button" class="btn-primary text-xs !min-h-0 px-3 py-2"
                                                :disabled="savingIds.has(participant.id) || isAbsent(participant, item)"
                                                @click="saveMark(participant, item)">
                                            {{ savingIds.has(participant.id) ? 'Saving…' : 'Save' }}
                                        </button>
                                    </div>
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
import { reactive, computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import FestEventWorkflowStepper from '@/Components/sahodaya/FestEventWorkflowStepper.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import { useFestMarkEntryDisplay } from '@/composables/useFestMarkEntryDisplay.js';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    registrations: Array,
    marks: Object,
    attendance: { type: Object, default: () => ({}) },
    activityLogs: { type: Array, default: () => [] },
    selectedHeadId: { type: [String, Number], default: null },
    selectedItemId: { type: [Number, String], default: null },
    competitionUrl: { type: String, default: null },
    rankPoints: { type: Array, default: () => [] },
    childEvents: { type: Array, default: () => [] },
});

const importUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/marks/import`);
const registrationsUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations`);
const settingsPointsUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/settings/points`);
const isSports = computed(() => props.event?.event_type === 'sports');

const {
    bulkRank,
    sections,
    attendanceStatus,
    isAbsent,
    showMeasurement,
    applyRankPoints,
    applyBulkRank,
    buildMarkPayload,
    iterSaveRows,
    rankLabel,
    rankOptionsForItem,
    setRank,
} = useFestMarkEntryDisplay(props, isSports);

const filterDescription = computed(() => {
    if (props.selectedItemId) {
        return isSports.value
            ? 'Enter time/distance, rank athletes, and mark attendance for this item.'
            : 'Entering marks for a single item — use position/rank, grade, score, or measurement.';
    }
    if (props.selectedHeadId) {
        return isSports.value
            ? 'Filtered by Event Head — one heat sheet per item with attendance and measurements.'
            : 'Filtered by item head — enter grades, points, and measurements for approved participants.';
    }
    return isSports.value
        ? 'One sheet per item — mark attendance, enter times, auto-rank, team points from rank master.'
        : 'Enter grades, points, and measurements for approved participants.';
});

const markForms = reactive({});
const savedIds = ref(new Set());
const savingIds = ref(new Set());
const bulkSaving = ref(false);

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
    if (participant._is_team) {
        return participant._team_name;
    }
    return participant.student?.name ?? participant.teacher?.name ?? 'Unnamed participant';
}

function participantRegNo(participant) {
    return participant.student?.reg_no ?? participant.teacher?.reg_no ?? null;
}

function markAttendance(participant, item, status) {
    if (!status) {
        return;
    }

    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/attendance`, {
        participant_id: participant.id,
        item_id: item.id,
        status,
    }, { preserveScroll: true });
}

function saveMark(participant, item) {
    savingIds.value = new Set([...savingIds.value, participant.id]);
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/marks`, buildMarkPayload(participant, item, markForms), {
        preserveScroll: true,
        onSuccess: () => {
            savedIds.value = new Set([...savedIds.value, participant.id]);
        },
        onFinish: () => {
            const next = new Set(savingIds.value);
            next.delete(participant.id);
            savingIds.value = next;
        },
    });
}

async function saveAll() {
    if (bulkSaving.value) {
        return;
    }

    bulkSaving.value = true;

    for (const { participant, item } of iterSaveRows()) {
        await new Promise((resolve) => {
            savingIds.value = new Set([...savingIds.value, participant.id]);
            router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/marks`, buildMarkPayload(participant, item, markForms), {
                preserveScroll: true,
                onSuccess: () => {
                    savedIds.value = new Set([...savedIds.value, participant.id]);
                },
                onFinish: () => {
                    const next = new Set(savingIds.value);
                    next.delete(participant.id);
                    savingIds.value = next;
                    resolve();
                },
            });
        });
    }

    bulkSaving.value = false;
}

function autoRank(item) {
    if (!item?.id) {
        return;
    }

    if (!confirm(`Auto-rank athletes for "${item.title}" from measurement values? Absent athletes are skipped.`)) {
        return;
    }

    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/items/${item.id}/auto-rank`, {}, { preserveScroll: true });
}

function switchSportEvent(evt) {
    const nextEventId = evt.target.value;
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${nextEventId}/marks`);
}

function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(`${iso}T12:00:00`);
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
}
</script>
