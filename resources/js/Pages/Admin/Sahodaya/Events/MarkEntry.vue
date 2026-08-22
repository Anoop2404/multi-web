<template>
    <SahodayaEventsLayout title="Mark entry" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                          :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        
        <!-- Header Actions -->
        <PageHeader :title="`${event.title} — Mark entry`" eyebrow="Mark entry"
                    :description="filterDescription">
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <Link :href="markSettingsUrl" class="btn-secondary text-xs">
                        🎚️ Mark Settings
                    </Link>
                    <a :href="markEntrySheetUrl" target="_blank" class="btn-secondary text-xs !bg-indigo-50 !text-indigo-800 hover:!bg-indigo-100 font-bold border-indigo-200">
                        🖨️ Print Blank Judge Sheets (Paper)
                    </a>
                    <a v-if="cumulativeSheetUrl" :href="cumulativeSheetUrl" target="_blank" class="btn-secondary text-xs">
                        📊 Digital Sum Sheet (Online Tabulation)
                    </a>
                    <Link :href="importUrl" class="btn-primary text-xs">
                        Import Marks
                    </Link>
                </div>
            </template>
        </PageHeader>

        <!-- Missing chest numbers warning -->
        <div v-if="props.missingChestCount" class="card !p-4 mb-5 border border-amber-200 bg-amber-50 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-amber-900">
                <strong>{{ props.missingChestCount }}</strong> participant(s) in this item {{ props.missingChestCount === 1 ? "doesn't" : "don't" }} have a chest number yet — the printed sheet will show a blank for them.
            </p>
            <Link :href="chestNumbersUrl" class="btn-secondary text-xs !bg-white shrink-0">Generate chest numbers</Link>
        </div>

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
            <!-- Child Event / Region Selector -->
            <div v-if="childEvents.length" class="flex flex-wrap items-center gap-2 pb-2 border-b border-slate-100">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ event.event_type === 'sports' ? 'Sport Event / Region:' : 'Region:' }}</label>
                <select :value="String(event.id)" @change="switchSportEvent" class="field text-xs !py-1 w-64 font-semibold">
                    <option v-for="ev in childEvents" :key="ev.id" :value="String(ev.id)">
                        {{ ev.short_title || ev.title }}
                    </option>
                </select>
            </div>

            <!-- Item Picker -->
            <div v-if="itemOptions.length > 1" class="space-y-3">
                <ReportItemSearchSelect :items="flatItems" :model-value="props.selectedItemId"
                                        :all-items-label="`All ${flatItems.length} items`"
                                        search-placeholder="Search by item name or code…"
                                        :status-for="itemConfiguredMark"
                                        @select="onItemSelect" />

                <div class="flex items-center justify-end gap-3">
                    <span class="text-[11px] text-slate-400">
                        ✓ {{ configuredCountInView }}/{{ itemOptions.length }} items configured
                    </span>
                    <button v-if="sections.length" type="button" class="btn-secondary text-xs !py-1.5 !px-3" @click="autoRankAll">
                        Auto-rank All
                    </button>
                    <button v-if="sections.length && showGradeColumn" type="button" class="btn-secondary text-xs !py-1.5 !px-3" @click="autoGradeAll">
                        Auto-grade All
                    </button>
                    <button v-if="sections.length" type="button" class="btn-primary text-xs !py-1.5 !px-4"
                            :disabled="bulkSaving" @click="saveAll">
                        {{ bulkSaving ? 'Saving all…' : 'Save All Marks ✓' }}
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

                        <button v-if="section.item?.id" type="button" class="btn-secondary text-xs !py-1 !px-2.5" @click="autoRankSection(section)">
                            Auto-rank
                        </button>
                        <button v-if="section.item?.id && showGradeColumn" type="button" class="btn-secondary text-xs !py-1 !px-2.5" @click="autoGrade(section)">
                            Auto-grade
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
                                    <th v-for="j in judgeNumbers" :key="j" class="p-3.5 w-20">
                                        Judge {{ j }}
                                        <span v-if="props.selectedItemTotalMarks" class="block font-normal text-slate-400 normal-case">/ {{ props.selectedItemTotalMarks }}</span>
                                    </th>
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
                                               :max="props.selectedItemTotalMarks ?? undefined"
                                               class="field text-xs tabular-nums w-16" placeholder="0"
                                               :disabled="isAbsent(participant, item)">
                                    </td>
                                    <td class="p-3.5 font-mono font-bold text-slate-900 tabular-nums">
                                        {{ participantGrandTotal(participant.id, item) }}
                                    </td>
                                </template>

                                <!-- Marks / Score (Optional) -->
                                <td v-else class="p-3.5">
                                    <input v-model.number="markForms[participant.id].score" type="number" min="0" step="0.5"
                                           class="field text-xs font-bold tabular-nums" placeholder="Pts (Optional)"
                                           :disabled="isAbsent(participant, item)"
                                           @input="onScoreInput(participant.id, item)">
                                </td>

                                <!-- Grade (Optional for Kalolsavam / Fest) -->
                                <td v-if="showGradeColumn" class="p-3.5">
                                    <select v-model="markForms[participant.id].grade" class="field text-xs" :disabled="isAbsent(participant, item)" @change="markForms[participant.id]._user_edited_grade = true">
                                        <option value="">—</option>
                                        <option v-for="g in gradeOptions" :key="g" :value="g">{{ g }}</option>
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
import ReportItemSearchSelect from '@/Components/reports/ReportItemSearchSelect.vue';
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
    rankPointsByType: { type: Object, default: () => ({}) },
    childEvents: { type: Array, default: () => [] },
    itemHeads: { type: Array, default: () => [] },
    headItemGroups: { type: Array, default: () => [] },
    configuredItemIds: { type: Array, default: () => [] },
    gradeOptions: { type: Array, default: () => ['A+', 'A', 'B', 'C'] },
    gradeRules: { type: Array, default: () => [] },
    judgeCount: { type: Number, default: 1 },
    judgeScores: { type: Object, default: () => ({}) },
    selectedItemTotalMarks: { type: Number, default: null },
    cumulativeSheetUrl: { type: String, default: null },
    sheetUploads: { type: Array, default: () => [] },
    missingChestCount: { type: Number, default: 0 },
});

const importUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/marks/import`);
const registrationsUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations`);
const chestNumbersUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/chest-numbers?item_id=${props.selectedItemId}`);
const isSports = computed(() => props.event?.event_type === 'sports');

// Grade is auto-computed from score (and stored) for any non-sports event, regardless of
// event_type — previously this only showed for 'kalolsavam' (plus a dead 'fest' check that
// never matched a real event_type), hiding the grade admins could see/correct on every other
// event type. Matches Judge/MarkEntry.vue's existing `!isSports` condition.
const showGradeColumn = computed(() => ! isSports.value);

const marksBaseUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/marks`);

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

// Flat, head-agnostic item list for the main navigation picker — headItemGroups always
// carries every item across every head (FestHeadItemNavigationService::navigationForEvent()
// doesn't filter by the request's head_id), unlike itemOptions above which still narrows by
// selectedHeadId and only stays flat in practice because this page no longer ever sends
// head_id itself.
const flatItems = computed(() => (props.headItemGroups ?? []).flatMap((h) => h.items ?? []));

function onItemSelect(itemId) {
    // FestMarkEntryController scopes registrations/marks/criteria to a single item
    // server-side, so switching items needs a real round trip. preserveState: false
    // (matching ReportHeadSubNav's old preserve-state="false") resets local form state
    // (markForms/judgeForms/etc.) for the newly-selected item's registrations.
    router.get(marksBaseUrl.value, itemId ? { item_id: itemId } : {}, { preserveScroll: true, preserveState: false });
}

function switchSportEvent(evt) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${evt.target.value}/marks`);
}

function itemConfiguredMark(item) {
    return (props.configuredItemIds ?? []).includes(item.id) ? '✓' : null;
}

const configuredCountInView = computed(() =>
    itemOptions.value.filter((it) => (props.configuredItemIds ?? []).includes(it.id)).length
);

const markSettingsUrl = computed(() => {
    let url = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/mark-settings`;
    if (props.selectedItemId) url += `?item_id=${props.selectedItemId}`;
    return url;
});

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

function computeAutoGrade(score, item) {
    if (score === null || score === undefined || score === '' || isNaN(score)) return '';
    const num = Number(score);
    const jCount = props.judgeCount && props.judgeCount > 0 ? props.judgeCount : 1;
    const marksPerJudge = item?.total_marks ? Number(item.total_marks) : 100;
    const maxPossibleMarks = marksPerJudge * jCount;
    const percent = maxPossibleMarks > 0 ? (num / maxPossibleMarks) * 100 : num;

    const rules = props.gradeRules ?? [];
    if (rules.length > 0) {
        const sorted = [...rules].sort((a, b) => {
            const minA = Number(a.min_percent ?? a.min_score ?? 0);
            const minB = Number(b.min_percent ?? b.min_score ?? 0);
            return minB - minA;
        });

        for (let i = 0; i < sorted.length; i++) {
            const rule = sorted[i];
            const gradeLabel = (rule.grade || '').replace('_plus', '+');
            const min = Number(rule.min_percent ?? rule.min_score ?? 0);
            const max = i === 0
                ? Number(rule.max_percent ?? rule.max_score ?? 100)
                : Number(sorted[i - 1].min_percent ?? sorted[i - 1].min_score ?? 100);

            const matched = i === 0
                ? (percent >= min && percent <= max)
                : (percent >= min && percent < max);

            if (matched) {
                return gradeLabel;
            }
        }
        return '';
    }

    if (percent !== null) {
        const opts = props.gradeOptions ?? ['A+', 'A', 'B', 'C'];
        if (percent >= 70.0) return opts.includes('A+') ? 'A+' : (opts.includes('A') ? 'A' : (opts[0] || 'A'));
        if (percent >= 60.0) return opts.includes('A') ? 'A' : (opts.includes('B') ? 'B' : (opts[0] || 'A'));
        if (percent >= 50.0) return opts.includes('B') ? 'B' : (opts.includes('C') ? 'C' : (opts[1] || 'B'));
        if (percent >= 40.0) return opts.includes('C') ? 'C' : (opts[opts.length - 1] || 'C');
    }
    return '';
}

// Form state per participant
const markForms = reactive({});
for (const reg of props.registrations ?? []) {
    for (const p of reg.participants ?? []) {
        const existing = props.marks?.[p.id] ?? {};
        let score = existing.score ?? null;

        const jScores = props.judgeScores?.[p.id] ?? {};
        let judgeSum = 0;
        let hasAnyJudge = false;
        for (let j = 1; j <= (props.judgeCount ?? 1); j++) {
            const v = jScores[j];
            if (v !== null && v !== '' && v !== undefined) {
                judgeSum += Number(v);
                hasAnyJudge = true;
            }
        }
        if (hasAnyJudge) {
            score = judgeSum;
        }

        const autoG = computeAutoGrade(score, reg.item);
        const grade = autoG || (existing.grade ?? '');

        markForms[p.id] = {
            position: existing.position ?? null,
            grade: grade,
            score: score,
            measurement_value: existing.measurement_value ?? '',
            measurement_unit: existing.measurement_unit ?? '',
            _user_edited_grade: false,
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

function participantGrandTotal(participantId, item = null) {
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
    if (any && markForms[participantId] && !markForms[participantId]._user_edited_grade) {
        const autoG = computeAutoGrade(total, item);
        if (autoG) {
            markForms[participantId].grade = autoG;
        }
    }
    return any ? total : '—';
}

function onScoreInput(participantId, item) {
    const form = markForms[participantId];
    if (form && !form._user_edited_grade) {
        const autoG = computeAutoGrade(form.score, item);
        if (autoG) {
            form.grade = autoG;
        }
    }
}

function autoGrade(section) {
    for (const { participant, item } of section.rows) {
        if (isAbsent(participant, item)) continue;
        const form = markForms[participant.id];
        if (!form) continue;

        let scoreToUse = form.score;
        if (hasJudgePanel.value) {
            const grandTotal = participantGrandTotal(participant.id, item);
            if (grandTotal !== '—' && grandTotal !== null && grandTotal !== undefined) {
                scoreToUse = grandTotal;
            }
        }

        if (scoreToUse !== null && scoreToUse !== undefined && scoreToUse !== '') {
            const autoG = computeAutoGrade(scoreToUse, item);
            if (autoG) {
                form.grade = autoG;
                form._user_edited_grade = false;
            }
        }
    }
}

function autoGradeAll() {
    for (const section of sections.value) {
        autoGrade(section);
    }
}

function judgeScoresPayload(participantId) {
    return { ...(judgeForms[participantId] ?? {}) };
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
    const form = markForms[participant.id];
    if (form) {
        let scoreToUse = form.score;
        if (hasJudgePanel.value) {
            const grandTotal = participantGrandTotal(participant.id, item);
            if (grandTotal !== '—' && grandTotal !== null && grandTotal !== undefined) {
                scoreToUse = grandTotal;
            }
        }
        if (!form._user_edited_grade) {
            form.grade = computeAutoGrade(scoreToUse, item);
        }
    }
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

function autoRankSection(section) {
    const scoredRows = [];
    for (const { participant, item } of section.rows) {
        if (isAbsent(participant, item)) continue;
        let scoreVal = markForms[participant.id]?.score;
        if (hasJudgePanel.value) {
            const grandTotal = participantGrandTotal(participant.id, item);
            if (grandTotal !== '—' && grandTotal !== null && grandTotal !== undefined) {
                scoreVal = grandTotal;
            }
        }
        if (scoreVal !== null && scoreVal !== undefined && scoreVal !== '' && !isNaN(scoreVal)) {
            scoredRows.push({
                participantId: participant.id,
                item,
                score: Number(scoreVal),
            });
        }
    }

    if (!scoredRows.length) {
        if (section.item?.id) {
            router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/items/${section.item.id}/auto-rank`, {}, { preserveScroll: true });
        }
        return;
    }

    scoredRows.sort((a, b) => b.score - a.score);

    let currentRank = 1;
    for (let i = 0; i < scoredRows.length; i++) {
        if (i > 0 && scoredRows[i].score < scoredRows[i - 1].score) {
            currentRank = i + 1;
        }
        if (currentRank <= 6) {
            setRank(scoredRows[i].participantId, scoredRows[i].item, markForms, currentRank);
        }
    }
}

function autoRankAll() {
    for (const section of sections.value) {
        autoRankSection(section);
    }
}

function autoRank(item) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/items/${item.id}/auto-rank`, {}, { preserveScroll: true });
}
</script>
