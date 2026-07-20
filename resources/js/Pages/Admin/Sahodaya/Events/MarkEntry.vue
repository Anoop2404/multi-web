<template>
    <SahodayaEventsLayout title="Mark entry" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                          :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        
        <!-- Header Actions -->
        <PageHeader :title="`${event.title} — Mark entry`" eyebrow="Mark entry"
                    :description="filterDescription">
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <Link v-if="competitionUrl" :href="competitionUrl" class="btn-secondary text-xs">
                        ← {{ isSports ? 'By Event Head' : 'By Item Head' }}
                    </Link>
                    <a :href="markEntrySheetUrl" target="_blank" class="btn-secondary text-xs !bg-indigo-50 !text-indigo-800 hover:!bg-indigo-100 font-bold border-indigo-200">
                        🖨️ Mark Entry Sheet PDF
                    </a>
                    <a v-if="cumulativeSheetUrl" :href="cumulativeSheetUrl" target="_blank" class="btn-secondary text-xs">
                        Digital Sum Sheet ↓
                    </a>
                    <Link :href="importUrl" class="btn-primary text-xs">
                        Import Marks
                    </Link>
                </div>
            </template>
        </PageHeader>

        <!-- Signed Mark Sheet Upload -->
        <div v-if="props.selectedItemId" class="card !p-4 mb-5 space-y-3 border border-slate-200">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Signed mark sheet (scanned copy)</h3>
                <div class="flex items-center gap-2">
                    <input ref="uploadInput" type="file" accept=".pdf,.jpg,.jpeg,.png" class="text-xs"
                           @change="onUploadFileChange">
                    <button type="button" class="btn-secondary text-xs !py-1 !px-3"
                            :disabled="!uploadFile || uploading" @click="uploadSheet">
                        {{ uploading ? 'Uploading…' : 'Upload' }}
                    </button>
                </div>
            </div>

            <div v-if="sheetUploads.length" class="divide-y divide-slate-100">
                <div v-for="u in sheetUploads" :key="u.id" class="flex items-center justify-between gap-3 py-2 text-xs">
                    <div class="flex items-center gap-2 text-slate-600">
                        <span>📎</span>
                        <a :href="u.downloadUrl" target="_blank" class="font-semibold text-indigo-700 hover:underline">
                            {{ u.original_name || 'Sheet' }}
                        </a>
                        <span class="text-slate-400">{{ u.uploaded_by }} · {{ u.uploaded_at }}</span>
                    </div>
                    <button type="button" class="text-rose-600 hover:underline font-semibold" @click="deleteUpload(u)">
                        Remove
                    </button>
                </div>
            </div>
            <p v-else class="text-xs text-slate-400">No signed sheet uploaded yet for this item.</p>
        </div>

        <!-- Sub Navigation Bar -->
        <SportsSetupSubNav v-if="isSports" :sahodaya-id="sahodaya.id" :event-id="event.id"
                           :event="event" active="marks" class="mb-4" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="marks" class="mb-4" />

        <!-- Filter & Item Selector Card -->
        <div class="card !p-4 space-y-3 mb-5">
            <!-- Child Event Selector for Sports -->
            <div v-if="isSports && childEvents.length" class="flex flex-wrap items-center gap-2 pb-2 border-b border-slate-100">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Sport Event:</label>
                <select :value="event.id" @change="switchSportEvent" class="field text-xs !py-1 w-64">
                    <option v-for="ev in childEvents" :key="ev.id" :value="ev.id">
                        {{ ev.title }} {{ ev.parent_event_id === null ? '(Season Hub)' : '' }}
                    </option>
                </select>
            </div>

            <!-- Item Pill Chips -->
            <div v-if="itemOptions.length > 1" class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mr-1">Item:</span>
                    <button v-for="it in itemOptions" :key="it.id" type="button" @click="selectItem(it.id)"
                            :class="selectedItemId == it.id
                                ? 'bg-slate-900 text-white font-bold shadow-sm'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-semibold'"
                            class="px-3.5 py-1.5 rounded-full transition whitespace-nowrap">
                        {{ it.title }}
                    </button>
                </div>

                <div class="flex items-center gap-2">
                    <button v-if="props.selectedItemId" type="button" class="btn-secondary text-xs !py-1.5 !px-3"
                            @click="showColumnConfig = !showColumnConfig">
                        {{ showColumnConfig ? 'Close Columns ✕' : '⚙️ Configure Columns' }}
                    </button>
                    <button v-if="sections.length" type="button" class="btn-primary text-xs !py-1.5 !px-4"
                            :disabled="bulkSaving" @click="saveAll">
                        {{ bulkSaving ? 'Saving all…' : 'Save All Marks ✓' }}
                    </button>
                </div>
            </div>

            <!-- Judge count + paper column configuration panel -->
            <div v-if="showColumnConfig" class="border-t border-slate-100 pt-3 space-y-4">
                <div class="flex items-center gap-3">
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-500 whitespace-nowrap">
                        No. of Judges
                    </label>
                    <input v-model.number="judgeCountDraft" type="number" min="1" max="20"
                           class="field text-xs !py-1 w-20">
                    <p class="text-[11px] text-slate-400">
                        1 = single evaluator, entered directly online. 2+ = each judge gets their own printed sheet,
                        plus a Sum Sheet, and you type each judge's paper subtotal into the table below.
                    </p>
                </div>

                <div class="space-y-2">
                    <p class="text-xs text-slate-500">
                        Scoring columns printed on each judge's paper sheet (e.g. "Content", "Presentation"). SL NO,
                        CHEST NO. and TOTAL are always included automatically — name the criteria columns in between.
                    </p>
                    <div v-if="columnDraft.length" class="grid grid-cols-12 gap-2 text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1 px-1">
                        <div class="col-span-1">#</div>
                        <div class="col-span-8">Column / Criterion Name</div>
                        <div class="col-span-2">Max Marks</div>
                        <div class="col-span-1"></div>
                    </div>
                    <div v-for="(row, idx) in columnDraft" :key="row._key" class="flex items-center gap-2">
                        <span class="text-[10px] font-bold text-slate-400 w-5">{{ idx + 1 }}.</span>
                        <input v-model="row.label" type="text" :placeholder="`Criterion ${idx + 1} (e.g. Content / Presentation)`"
                               class="field text-xs flex-1">
                        <input v-model.number="row.max_score" type="number" min="0.5" step="0.5" placeholder="Max"
                               class="field text-xs w-24">
                        <button type="button" class="text-rose-500 hover:underline text-xs font-semibold"
                                @click="removeColumnRow(idx)">
                            Remove
                        </button>
                    </div>
                    <button type="button" class="btn-secondary text-xs !py-1 !px-3" @click="addColumnRow">
                        + Add Column
                    </button>
                </div>

                <div class="flex items-center justify-end gap-2 pt-1 border-t border-slate-100">
                    <button type="button" class="btn-primary text-xs !py-1.5 !px-4"
                            :disabled="savingColumns" @click="saveColumnConfig">
                        {{ savingColumns ? 'Saving…' : 'Save Settings' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <EmptyState
            v-if="!sections.length"
            title="No registrations to mark"
            description="Approve registrations first, then return here to enter marks."
            icon="📊"
            class="py-12"
        >
            <template #action>
                <Link :href="registrationsUrl" class="btn-primary text-xs">Review Registrations</Link>
            </template>
        </EmptyState>

        <!-- Single Unified Datatable List -->
        <div v-else class="space-y-6">
            <section v-for="section in sections" :key="section.key" class="card !p-0 overflow-hidden border border-slate-200">
                <!-- Section Bar -->
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 bg-slate-50">
                    <div class="flex items-center gap-2">
                        <h3 class="section-title !mb-0 text-sm font-bold text-slate-900">{{ section.item?.title }}</h3>
                        <span class="px-2.5 py-0.5 rounded-full bg-slate-200 text-slate-700 font-bold text-[11px]">
                            {{ section.rows.length }} participant(s)
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <div v-if="section.rows.length > 1" class="flex items-center gap-1.5">
                            <span class="text-slate-500 font-medium text-[11px]">Same rank for all:</span>
                            <select v-model="bulkRank[section.bulkKey]" class="field text-xs !py-1 min-w-[9rem]">
                                <option :value="null">—</option>
                                <option v-for="opt in rankOptions" :key="opt.rank" :value="opt.rank">
                                    {{ opt.label }}
                                </option>
                            </select>
                            <button type="button" class="btn-secondary text-xs !py-1 !px-2.5"
                                    :disabled="!bulkRank[section.bulkKey]"
                                    @click="applyBulkRank(section, markForms)">
                                Apply
                            </button>
                        </div>

                        <button v-if="isSports && section.item?.id" type="button" class="btn-secondary text-xs !py-1 !px-2.5" @click="autoRank(section.item)">
                            Auto-rank
                        </button>
                    </div>
                </div>

                <!-- Datatable -->
                <div class="overflow-x-auto bg-white">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50/80 text-slate-500 border-b border-slate-200 uppercase tracking-wider text-[10px] font-bold">
                            <tr>
                                <th class="p-3.5 w-10 text-center">#</th>
                                <th class="p-3.5 w-32">Chest No.</th>
                                <th class="p-3.5 w-36">Reg No.</th>
                                <th class="p-3.5 w-32">Attendance</th>
                                <th v-if="showMeasurement(section.item)" class="p-3.5 w-36">Time / Distance</th>
                                <th class="p-3.5 w-44">Rank</th>
                                <template v-if="hasJudgePanel">
                                    <th v-for="j in judgeNumbers" :key="j" class="p-3.5 w-20">Judge {{ j }}</th>
                                    <th class="p-3.5 w-24">Grand Total</th>
                                </template>
                                <th v-else class="p-3.5 w-28">Marks / Score</th>
                                <th v-if="showGradeColumn" class="p-3.5 w-24">Grade</th>
                                <th class="p-3.5 text-right w-24">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="({ participant, item }, pIdx) in section.rows" :key="participant.id"
                                :class="isAbsent(participant, item) ? 'bg-rose-50/30' : 'hover:bg-slate-50/70 transition'">
                                
                                <!-- Serial No -->
                                <td class="p-3.5 text-slate-400 text-center font-mono font-medium">{{ pIdx + 1 }}</td>

                                <!-- Chest No. -->
                                <td class="p-3.5 font-mono font-bold text-slate-900">
                                    <span v-if="participant.chest_no" class="inline-flex items-center bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded border border-indigo-100 text-xs">
                                        #{{ participant.chest_no }}
                                    </span>
                                    <span v-else class="text-slate-400 font-normal">—</span>
                                </td>

                                <!-- Reg No. -->
                                <td class="p-3.5 font-mono text-slate-700 text-xs">
                                    <span v-if="participantRegNo(participant)" class="bg-slate-100 text-slate-800 px-2 py-0.5 rounded border border-slate-200">
                                        {{ participantRegNo(participant) }}
                                    </span>
                                    <span v-else class="bg-slate-100 text-slate-600 px-2 py-0.5 rounded border border-slate-200">
                                        ID: {{ participant.id }}
                                    </span>
                                </td>

                                <!-- Attendance -->
                                <td class="p-3.5">
                                    <select :value="attendanceStatus(participant, item)"
                                            class="field text-xs !py-1 font-semibold"
                                            :class="isAbsent(participant, item) ? '!border-rose-300 !bg-rose-50 !text-rose-700' : ''"
                                            @change="markAttendance(participant, item, $event.target.value)">
                                        <option value="">Present ✓</option>
                                        <option value="present">Present ✓</option>
                                        <option value="absent">Absent ✕</option>
                                    </select>
                                </td>

                                <!-- Time / Distance (if applicable) -->
                                <td v-if="showMeasurement(section.item)" class="p-3.5">
                                    <div class="flex items-center gap-1">
                                        <input v-model="markForms[participant.id].measurement_value"
                                               class="field text-xs"
                                               placeholder="7.45"
                                               :disabled="isAbsent(participant, item)">
                                        <input v-model="markForms[participant.id].measurement_unit"
                                               class="field text-xs w-16"
                                               placeholder="s/m"
                                               :disabled="isAbsent(participant, item)">
                                    </div>
                                </td>

                                <!-- Rank Dropdown -->
                                <td class="p-3.5">
                                    <select :value="markForms[participant.id].position ?? ''"
                                            class="field text-xs !py-1 font-bold text-slate-900"
                                            :disabled="isAbsent(participant, item)"
                                            @change="setRank(participant.id, item, markForms, $event.target.value)">
                                        <option value="">— Select Rank —</option>
                                        <option v-for="opt in rankOptions" :key="opt.rank" :value="opt.rank">
                                            {{ opt.label }}
                                        </option>
                                    </select>
                                </td>

                                <!-- Per-judge subtotal columns + computed Grand Total -->
                                <template v-if="hasJudgePanel">
                                    <td v-for="j in judgeNumbers" :key="j" class="p-3.5">
                                        <input v-model.number="judgeForms[participant.id][j]" type="number" min="0" step="0.5"
                                               class="field text-xs tabular-nums w-16" placeholder="0"
                                               :disabled="isAbsent(participant, item)">
                                    </td>
                                    <td class="p-3.5 font-mono font-bold text-slate-900 tabular-nums">
                                        {{ participantGrandTotal(participant.id) }}
                                    </td>
                                </template>

                                <!-- Marks / Score (Optional) -->
                                <td v-else class="p-3.5">
                                    <input v-model.number="markForms[participant.id].score" type="number" min="0" step="0.5"
                                           class="field text-xs font-bold tabular-nums" placeholder="Pts (Optional)"
                                           :disabled="isAbsent(participant, item)">
                                </td>

                                <!-- Grade (Optional for Kalolsavam / Fest) -->
                                <td v-if="showGradeColumn" class="p-3.5">
                                    <select v-model="markForms[participant.id].grade" class="field text-xs" :disabled="isAbsent(participant, item)">
                                        <option value="">—</option>
                                        <option>A</option>
                                        <option>A+</option>
                                        <option>B</option>
                                        <option>C</option>
                                    </select>
                                </td>

                                <!-- Action Button -->
                                <td class="p-3.5 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <span v-if="savedIds.has(participant.id)" class="text-xs font-bold text-emerald-600">Saved ✓</span>
                                        <button type="button" class="btn-primary text-xs !py-1 !px-3"
                                                :disabled="savingIds.has(participant.id) || isAbsent(participant, item)"
                                                @click="saveMark(participant, item)">
                                            {{ savingIds.has(participant.id) ? 'Saving...' : 'Save' }}
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
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
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
    itemHeads: { type: Array, default: () => [] },
    initialItemCriteria: { type: Array, default: () => [] },
    criteria: { type: Array, default: () => [] },
    judgeCount: { type: Number, default: 1 },
    judgeScores: { type: Object, default: () => ({}) },
    cumulativeSheetUrl: { type: String, default: null },
    sheetUploads: { type: Array, default: () => [] },
});

const importUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/marks/import`);
const registrationsUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations`);
const isSports = computed(() => props.event?.event_type === 'sports');

const showGradeToggle = ref(false);
const showGradeColumn = computed(() => {
    if (showGradeToggle.value) return true;
    return props.event?.event_type === 'kalolsavam' || props.event?.event_type === 'fest';
});

const markEntrySheetUrl = computed(() => {
    let url = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/mark-entry-sheet`;
    if (props.selectedItemId) {
        url += `?item_id=${props.selectedItemId}`;
    }
    return url;
});

const filterDescription = computed(() => (
    isSports.value
        ? 'Sports event marks, attendance, times/distances, and ranks.'
        : 'Entering marks for competition items — chest no, reg no, rank, score, and grade.'
));

const itemOptions = computed(() => {
    const items = (props.event?.items ?? []).filter((it) => it.is_enabled !== false);
    if (props.selectedHeadId == null) {
        return items;
    }
    if (props.selectedHeadId === 'other') {
        return items.filter((it) => it.head_id == null);
    }
    return items.filter((it) => String(it.head_id) === String(props.selectedHeadId));
});

function selectItem(id) {
    const params = { item_id: id };
    if (props.selectedHeadId != null) {
        params.head_id = props.selectedHeadId;
    }
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/marks`, params, { preserveScroll: true });
}

function switchSportEvent(evt) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${evt.target.value}/marks`);
}

const displayCtx = useFestMarkEntryDisplay(props, isSports);
const {
    bulkRank,
    sections,
    attendanceStatus,
    isAbsent,
    showMeasurement,
    setRank,
    applyRankPoints,
    applyBulkRank,
    buildMarkPayload,
    iterSaveRows,
} = displayCtx;

const rankOptions = computed(() => [
    { rank: 1, label: '1st Place' },
    { rank: 2, label: '2nd Place' },
    { rank: 3, label: '3rd Place' },
    { rank: 4, label: '4th Place' },
    { rank: 5, label: '5th Place' },
    { rank: 6, label: '6th Place' },
]);

// Form state per participant
const markForms = reactive({});
for (const reg of props.registrations ?? []) {
    for (const p of reg.participants ?? []) {
        const existing = props.marks?.[p.id] ?? {};
        markForms[p.id] = {
            position: existing.position ?? null,
            grade: existing.grade ?? '',
            score: existing.score ?? null,
            measurement_value: existing.measurement_value ?? '',
            measurement_unit: existing.measurement_unit ?? '',
        };
    }
}

// Judge-panel scoring: one input column per judge (that judge's paper
// subtotal), plus a computed Grand Total (sum across judges).
const hasJudgePanel = computed(() => (props.judgeCount ?? 1) > 1);
const judgeNumbers = computed(() => {
    const n = props.judgeCount ?? 1;
    return Array.from({ length: n }, (_, i) => i + 1);
});

const judgeForms = reactive({});
for (const reg of props.registrations ?? []) {
    for (const p of reg.participants ?? []) {
        const existing = props.judgeScores?.[p.id] ?? {};
        const row = {};
        for (let j = 1; j <= (props.judgeCount ?? 1); j++) {
            row[j] = existing[j] ?? null;
        }
        judgeForms[p.id] = row;
    }
}

function participantGrandTotal(participantId) {
    const row = judgeForms[participantId] ?? {};
    let total = 0;
    let any = false;
    for (let j = 1; j <= (props.judgeCount ?? 1); j++) {
        const v = row[j];
        if (v !== null && v !== '' && v !== undefined) {
            total += Number(v);
            any = true;
        }
    }
    return any ? total : '—';
}

function judgeScoresPayload(participantId) {
    return { ...(judgeForms[participantId] ?? {}) };
}

// Judge count + paper-column configuration
let draftKeySeq = 0;
const showColumnConfig = ref(false);
const savingColumns = ref(false);
const judgeCountDraft = ref(props.judgeCount ?? 1);
const columnDraft = reactive(
    (props.criteria ?? []).map((c) => ({
        _key: draftKeySeq++,
        id: c.id,
        label: c.label,
        max_score: c.max_score ?? 10,
    }))
);

function addColumnRow() {
    columnDraft.push({ _key: draftKeySeq++, id: null, label: '', max_score: 10 });
}

function removeColumnRow(idx) {
    columnDraft.splice(idx, 1);
}

function saveColumnConfig() {
    if (!props.selectedItemId) return;
    savingColumns.value = true;
    const rows = columnDraft.map((r, idx) => ({
        id: r.id,
        label: (r.label ?? '').trim() || `Criterion ${idx + 1}`,
        max_score: r.max_score || 10,
    }));

    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/items/${props.selectedItemId}/mark-criteria`,
        { judge_count: judgeCountDraft.value || 1, criteria: rows },
        {
            preserveScroll: true,
            onFinish: () => {
                savingColumns.value = false;
            },
        }
    );
}

// Signed mark sheet upload
const uploadInput = ref(null);
const uploadFile = ref(null);
const uploading = ref(false);

function onUploadFileChange(evt) {
    uploadFile.value = evt.target.files?.[0] ?? null;
}

function uploadSheet() {
    if (!uploadFile.value || !props.selectedItemId) return;
    uploading.value = true;
    const form = new FormData();
    form.append('file', uploadFile.value);
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/items/${props.selectedItemId}/mark-sheet-uploads`,
        form,
        {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => {
                uploading.value = false;
                uploadFile.value = null;
                if (uploadInput.value) uploadInput.value.value = '';
            },
        }
    );
}

function deleteUpload(upload) {
    router.delete(upload.downloadUrl, { preserveScroll: true });
}

function participantRegNo(participant) {
    return participant.student?.fest_registration_id ?? participant.event_reg_id ?? null;
}

function markAttendance(participant, item, status) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/attendance`, {
        participant_id: participant.id,
        item_id: item.id,
        status,
    }, { preserveScroll: true });
}

const savedIds = ref(new Set());
const savingIds = ref(new Set());
const bulkSaving = ref(false);

function payloadFor(participant, item) {
    const payload = buildMarkPayload(participant, item, markForms);
    if (hasJudgePanel.value) {
        payload.judge_scores = judgeScoresPayload(participant.id);
    }
    return payload;
}

function saveMark(participant, item) {
    if (isAbsent(participant, item)) return;

    savingIds.value = new Set([...savingIds.value, participant.id]);
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/marks`, payloadFor(participant, item), {
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
    bulkSaving.value = true;
    for (const { participant, item } of iterSaveRows()) {
        if (isAbsent(participant, item)) continue;

        await new Promise((resolve) => {
            savingIds.value = new Set([...savingIds.value, participant.id]);
            router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/marks`, payloadFor(participant, item), {
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
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/marks/auto-rank`, {
        item_id: item.id,
    }, { preserveScroll: true });
}
</script>
