<template>
    <SahodayaEventsLayout title="Mark entry" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                          :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        
        <!-- Header Actions -->
        <PageHeader :title="`${event.title} — Mark entry`" eyebrow="Mark entry"
                    :description="filterDescription">
            <template #actions>
                <!-- Nowrap + horizontal scroll on mobile: flex-wrap here let long labels
                     ("Print Blank Judge Sheets (Paper)") shrink narrower than their own
                     text, wrapping mid-word inside the button instead of onto a new line.
                     Desktop keeps the original wrap-freely behavior (sm: and up). -->
                <div class="flex flex-nowrap items-center gap-2 overflow-x-auto pb-1 -mb-1 sm:flex-wrap sm:overflow-visible sm:pb-0 sm:mb-0">
                    <Link :href="markSettingsUrl" class="btn-secondary text-xs shrink-0 whitespace-nowrap">
                        🎚️ Mark Settings
                    </Link>
                    <a :href="markEntrySheetUrl" target="_blank" class="btn-secondary text-xs shrink-0 whitespace-nowrap !bg-indigo-50 !text-indigo-800 hover:!bg-indigo-100 font-bold border-indigo-200">
                        🖨️ Print Blank Judge Sheets (Paper)
                    </a>
                    <a v-if="cumulativeSheetUrl" :href="cumulativeSheetUrl" target="_blank" class="btn-secondary text-xs shrink-0 whitespace-nowrap">
                        📊 Digital Sum Sheet (Online Tabulation)
                    </a>
                    <Link :href="importUrl" class="btn-primary text-xs shrink-0 whitespace-nowrap">
                        Import Marks
                    </Link>
                    <Link :href="markEntryReportUrl" class="btn-secondary text-xs shrink-0 whitespace-nowrap">
                        📄 Mark Entry Report (PDF)
                    </Link>
                    <Link :href="marksAuditLogUrl" class="btn-secondary text-xs shrink-0 whitespace-nowrap">
                        🕓 Marks Audit Log
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

        <!-- Item locked: results already published, marks are frozen -->
        <div v-if="itemLocked" class="card !p-4 mb-5 border border-indigo-200 bg-indigo-50 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-indigo-900">
                🔒 <strong>This item's results are published</strong> — marks are locked and can't be edited here. Unpublish it first to make a correction.
            </p>
            <Link :href="resultsUrl" class="btn-secondary text-xs !bg-white shrink-0">Go to Results to unpublish</Link>
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
                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ event.event_type === 'sports' ? 'Sport Event / Region:' : 'Phase / Region:' }}</label>
                <SearchableSelect :model-value="String(event.id)" @update:model-value="switchSportEvent"
                                  :options="childEventOptions" :all-option="false" placeholder="Select region"
                                  class="w-64" />
            </div>

            <!-- Item Picker -->
            <div v-if="itemOptions.length > 1" class="space-y-3">
                <ReportItemSearchSelect :items="flatItems" :model-value="props.selectedItemId"
                                        :all-items-label="`All ${flatItems.length} items`"
                                        search-placeholder="Search by item name or code…"
                                        :status-for="itemConfiguredMark"
                                        @select="onItemSelect" />

                <!-- Nowrap + horizontal scroll on mobile — without this, flex's default
                     shrink let buttons squeeze narrower than "Auto-rank All"'s own text,
                     wrapping it mid-word inside the button (e.g. "Auto-\nrank\nAll"). -->
                <div class="flex flex-nowrap items-center justify-end gap-3 overflow-x-auto pb-1 -mb-1 sm:pb-0 sm:mb-0">
                    <span class="text-[11px] text-slate-400 shrink-0 whitespace-nowrap">
                        ✓ {{ configuredCountInView }}/{{ itemOptions.length }} items fully marked
                    </span>
                    <Link :href="markEntryStatusReportUrl" class="text-[11px] text-indigo-600 hover:underline shrink-0 whitespace-nowrap">
                        View full status report →
                    </Link>
                    <button v-if="sections.length" type="button" class="btn-secondary text-xs !py-1.5 !px-3 shrink-0 whitespace-nowrap" :disabled="itemLocked" @click="autoRankAll">
                        Auto-rank All
                    </button>
                    <button v-if="sections.length && showGradeColumn" type="button" class="btn-secondary text-xs !py-1.5 !px-3 shrink-0 whitespace-nowrap" :disabled="itemLocked" @click="autoGradeAll">
                        Auto-grade All
                    </button>
                    <button v-if="sections.length" type="button" class="btn-primary text-xs !py-1.5 !px-4 shrink-0 whitespace-nowrap"
                            :disabled="bulkSaving || itemLocked" @click="saveAll">
                        {{ bulkSaving ? 'Saving all…' : (itemLocked ? 'Locked — unpublish to edit' : 'Save All Marks ✓') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Region required: this event has region children — marks can only be saved
             against a specific region's own event, so entering marks from the combined
             "All Regions" view above would silently fail. Pick a region first. -->
        <EmptyState
            v-if="needsRegionSelection"
            title="Select your region to begin mark entry"
            description="This event is split into regions. Choose your region from the dropdown above — marks can only be entered and saved one region at a time."
            icon="📍"
            class="py-12"
        />

        <!-- Empty State -->
        <EmptyState
            v-else-if="!sections.length"
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

                    <div class="flex flex-nowrap items-center gap-2 text-xs overflow-x-auto pb-1 -mb-1 sm:flex-wrap sm:overflow-visible sm:pb-0 sm:mb-0">
                        <div v-if="section.rows.length > 1" class="flex items-center gap-1.5 shrink-0">
                            <span class="text-slate-500 font-medium text-[11px] whitespace-nowrap">Same rank for all:</span>
                            <SearchableSelect v-model="bulkRank[section.bulkKey]"
                                              :options="[{ value: null, label: '—' }, ...rankOptionsFor(section).map((opt) => ({ value: opt.rank, label: opt.label }))]"
                                              :all-option="false" class="min-w-[9rem]" />
                            <button type="button" class="btn-secondary text-xs !py-1 !px-2.5 whitespace-nowrap"
                                    :disabled="!bulkRank[section.bulkKey] || itemLocked"
                                    @click="applyBulkRank(section, markForms)">
                                Apply
                            </button>
                        </div>

                        <button v-if="section.item?.id" type="button" class="btn-secondary text-xs !py-1 !px-2.5 shrink-0 whitespace-nowrap" :disabled="itemLocked" @click="autoRankSection(section)">
                            Auto-rank
                        </button>
                        <button v-if="section.item?.id && showGradeColumn" type="button" class="btn-secondary text-xs !py-1 !px-2.5 shrink-0 whitespace-nowrap" :disabled="itemLocked" @click="autoGrade(section)">
                            Auto-grade
                        </button>
                    </div>
                </div>

                <!-- Datatable -->
                <div class="overflow-x-auto bg-white">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50/80 text-slate-500 border-b border-slate-200 uppercase tracking-wider text-[10px] font-bold">
                            <tr>
                                <!-- Sticky on both scroll edges: this table has up to 10
                                     columns (sports items add Time/Distance; judge-panel
                                     items add one column per judge) and routinely overflows
                                     narrow screens — without these, scrolling right to reach
                                     Rank/Score/Grade loses track of which row you're on, and
                                     reaching Save requires scrolling all the way back. -->
                                <th class="p-3.5 w-10 text-center sticky left-0 z-20 bg-slate-50 border-r border-slate-200">#</th>
                                <th class="p-3.5 w-32">Chest No.</th>
                                <th class="p-3.5 w-36">Reg No.</th>
                                <th class="p-3.5 w-32">Attendance</th>
                                <th v-if="showMeasurement(section.item)" class="p-3.5 w-36">Time / Distance</th>
                                <th class="p-3.5 w-44">Rank</th>
                                <template v-if="hasJudgePanel">
                                    <th v-for="j in judgeNumbers" :key="j" class="p-3.5 w-28">
                                        Judge {{ j }}
                                        <span v-if="perJudgeMax" class="block font-normal text-slate-400 normal-case">/ {{ perJudgeMax }}</span>
                                    </th>
                                    <th class="p-3.5 w-24">Grand Total</th>
                                </template>
                                <th v-else class="p-3.5 w-28">Marks / Score</th>
                                <th v-if="showGradeColumn" class="p-3.5 w-24">Grade</th>
                                <th class="p-3.5 text-right w-24 sticky right-0 z-20 bg-slate-50 border-l border-slate-200">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="({ participant, item }, pIdx) in section.rows" :key="participant.id"
                                :class="isAbsent(participant, item) ? 'bg-rose-50/30' : 'hover:bg-slate-50/70 transition'">
                                
                                <!-- Serial No -->
                                <td class="p-3.5 text-slate-400 text-center font-mono font-medium sticky left-0 z-10 border-r border-slate-200"
                                    :class="isAbsent(participant, item) ? 'bg-rose-50' : 'bg-white'">{{ pIdx + 1 }}</td>

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
                                    <SearchableSelect :model-value="attendanceStatus(participant, item)"
                                            :class="isAbsent(participant, item) ? '!border-rose-300 !bg-rose-50 !text-rose-700' : ''"
                                            :disabled="itemLocked"
                                            :options="[{ value: 'present', label: 'Present ✓' }, { value: 'absent', label: 'Absent ✕' }]"
                                            :all-option="true" all-label="Present ✓"
                                            @update:model-value="(value) => markAttendance(participant, item, value)" />
                                </td>

                                <!-- Time / Distance (if applicable) -->
                                <td v-if="showMeasurement(section.item)" class="p-3.5">
                                    <div class="flex items-center gap-1">
                                        <input v-model="markForms[participant.id].measurement_value"
                                               class="field text-xs"
                                               placeholder="7.45"
                                               :disabled="isAbsent(participant, item) || itemLocked">
                                        <input v-model="markForms[participant.id].measurement_unit"
                                               class="field text-xs w-16"
                                               placeholder="s/m"
                                               :disabled="isAbsent(participant, item) || itemLocked">
                                    </div>
                                </td>

                                <!-- Rank Dropdown -->
                                <td class="p-3.5">
                                    <SearchableSelect :model-value="markForms[participant.id].position ?? ''"
                                            :disabled="isAbsent(participant, item) || itemLocked"
                                            :options="rankOptionsFor(section).map((opt) => ({ value: opt.rank, label: opt.label }))"
                                            :all-option="true" all-label="— Select Rank —"
                                            @update:model-value="(value) => setRank(participant.id, item, markForms, value)" />
                                </td>

                                <!-- Per-judge subtotal columns + computed Grand Total -->
                                <template v-if="hasJudgePanel">
                                    <td v-for="j in judgeNumbers" :key="j" class="p-3.5">
                                        <input v-model.number="judgeForms[participant.id][j]" type="number" min="0" step="0.5"
                                               :max="perJudgeMax"
                                               class="field text-xs tabular-nums w-24" placeholder="0"
                                               :disabled="isAbsent(participant, item) || itemLocked">
                                    </td>
                                    <td class="p-3.5 font-mono font-bold text-slate-900 tabular-nums">
                                        {{ participantGrandTotal(participant.id, item) }}
                                    </td>
                                </template>

                                <!-- Marks / Score (Optional) -->
                                <td v-else class="p-3.5">
                                    <input v-model.number="markForms[participant.id].score" type="number" min="0" step="0.5"
                                           class="field text-xs font-bold tabular-nums" placeholder="Pts (Optional)"
                                           :disabled="isAbsent(participant, item) || itemLocked"
                                           @input="onScoreInput(participant.id, item)">
                                </td>

                                <!-- Grade (Optional for Kalolsavam / Fest) -->
                                <td v-if="showGradeColumn" class="p-3.5">
                                    <SearchableSelect v-model="markForms[participant.id].grade"
                                            :disabled="isAbsent(participant, item) || itemLocked"
                                            :options="gradeOptions" :all-option="true" all-label="—"
                                            @change="markForms[participant.id]._user_edited_grade = true" />
                                </td>

                                <!-- Action Button -->
                                <td class="p-3.5 text-right sticky right-0 z-10 border-l border-slate-200"
                                    :class="isAbsent(participant, item) ? 'bg-rose-50' : 'bg-white'">
                                    <div class="flex items-center justify-end gap-2">
                                        <span v-if="savedIds.has(participant.id)" class="text-xs font-bold text-emerald-600">Saved ✓</span>
                                        <span v-else-if="failedIds.has(participant.id)" class="text-xs font-bold text-rose-600">Not saved — see message above</span>
                                        <button type="button" class="btn-primary text-xs !py-1 !px-3"
                                                :disabled="savingIds.has(participant.id) || isAbsent(participant, item) || itemLocked"
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
import { Link, router, usePage } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import ReportItemSearchSelect from '@/Components/reports/ReportItemSearchSelect.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
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
    needsRegionSelection: { type: Boolean, default: false },
    itemHeads: { type: Array, default: () => [] },
    headItemGroups: { type: Array, default: () => [] },
    markProgressByItemId: { type: Object, default: () => ({}) },
    gradeOptions: { type: Array, default: () => ['A+', 'A', 'B', 'C'] },
    gradeRules: { type: Array, default: () => [] },
    judgeCount: { type: Number, default: 1 },
    judgeScores: { type: Object, default: () => ({}) },
    selectedItemTotalMarks: { type: Number, default: null },
    selectedItemPublishedAt: { type: String, default: null },
    cumulativeSheetUrl: { type: String, default: null },
    sheetUploads: { type: Array, default: () => [] },
    missingChestCount: { type: Number, default: 0 },
});

const importUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/marks/import`);
const markEntryReportUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/item-wise`);
const marksAuditLogUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/audit-logs?action=fest.mark.saved`);
const registrationsUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations`);
const chestNumbersUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/chest-numbers?item_id=${props.selectedItemId}`);
const isSports = computed(() => props.event?.event_type === 'sports');

// Maps childEvents (sport-event/region children) to the {value, label} shape
// SearchableSelect expects — the label combines short_title/title, so it can't
// be passed straight through as :options like a plain {id, name} array.
const childEventOptions = computed(() => (props.childEvents ?? []).map((ev) => ({
    value: String(ev.id),
    label: ev.short_title || ev.title,
})));

// Once an item's results are published, EventLifecycleGate rejects any further mark
// save for it server-side — this mirrors that lock in the UI so the grid doesn't look
// editable when it isn't, instead of only finding out after clicking Save.
const itemLocked = computed(() => !!props.selectedItemPublishedAt);
const resultsUrl = computed(() => {
    let url = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/results`;
    if (props.selectedItemId) url += `?item_id=${props.selectedItemId}`;
    return url;
});

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

function switchSportEvent(eventId) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${eventId}/marks`);
}

// Real marks-entered progress, not scoring-criteria setup — previously this checked
// FestMarkCriterion existence, which only means the item's scoring columns were
// configured, not that any marks were actually entered. "X/Y configured" could read
// 100% with zero marks in the whole event.
function itemConfiguredMark(item) {
    if (item.results_published_at) return '🔒';
    return props.markProgressByItemId?.[item.id]?.complete ? '✓' : null;
}

const configuredCountInView = computed(() =>
    itemOptions.value.filter((it) => props.markProgressByItemId?.[it.id]?.complete).length
);

const markEntryStatusReportUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/mark-entry-status`);

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

function ordinalRankLabel(n) {
    const suffixes = ['th', 'st', 'nd', 'rd'];
    const v = n % 100;
    return `${n}${suffixes[(v - 20) % 10] || suffixes[v] || suffixes[0]} Place`;
}

// A hardcoded 1st-6th list left anyone auto-ranked (or manually placed) past 6th
// showing as blank "— Select Rank —" — indistinguishable from truly unranked, and
// impossible to set by hand. Scale to the section's real size instead, so every
// participant has a selectable option regardless of how large the field is.
function rankOptionsFor(section) {
    const count = Math.max(section?.rows?.length ?? 0, 6);
    return Array.from({ length: count }, (_, i) => ({ rank: i + 1, label: ordinalRankLabel(i + 1) }));
}

function computeAutoGrade(score, item) {
    if (score === null || score === undefined || score === '' || isNaN(score)) return '';
    const num = Number(score);
    // total_marks is the item's overall ceiling (e.g. 200), not each judge's own scale —
    // judge inputs are already capped at total_marks / judgeCount each (see the max on the
    // judge score field below), so their sum tops out at total_marks directly.
    const maxPossibleMarks = item?.total_marks ? Number(item.total_marks) : 100;
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
        const hasAPlus = opts.includes('A+');
        if (hasAPlus) {
            if (percent >= 70.0) return opts.includes('A+') ? 'A+' : opts[0];
            if (percent >= 60.0) return opts.includes('A') ? 'A' : (opts[1] || opts[0]);
            if (percent >= 50.0) return opts.includes('B') ? 'B' : (opts[2] || opts[1]);
            if (percent >= 40.0) return opts.includes('C') ? 'C' : (opts[opts.length - 1] || 'C');
        } else {
            if (percent >= 70.0) return opts.includes('A') ? 'A' : opts[0];
            if (percent >= 60.0) return opts.includes('B') ? 'B' : (opts[1] || opts[0]);
            if (percent >= 50.0) return opts.includes('C') ? 'C' : (opts[opts.length - 1] || 'C');
        }
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

        const scoreHasValue = score !== null && score !== '' && score !== undefined;
        const autoG = computeAutoGrade(score, reg.item);
        const grade = scoreHasValue ? autoG : (existing.grade ?? '');

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

// selectedItemTotalMarks is the item's overall ceiling (e.g. 200 across all judges),
// not each judge's own scale — divide across judges so their sum tops out there,
// instead of letting each judge independently score up to the full total.
const perJudgeMax = computed(() => {
    const total = props.selectedItemTotalMarks;
    if (!total) return undefined;
    const n = props.judgeCount && props.judgeCount > 0 ? props.judgeCount : 1;
    return total / n;
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
        markForms[participantId].grade = computeAutoGrade(total, item);
    }
    return any ? total : '—';
}

function onScoreInput(participantId, item) {
    const form = markForms[participantId];
    if (form && !form._user_edited_grade) {
        form.grade = computeAutoGrade(form.score, item);
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
            form.grade = computeAutoGrade(scoreToUse, item);
            form._user_edited_grade = false;
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
const failedIds = ref(new Set());

// A rejected save (e.g. "Marks can only be entered for approved registrations.") is
// rendered by the app-wide exception handler (bootstrap/app.php) as a normal redirect
// back with the reason flashed to page.props.flash.error — not as an Inertia validation
// error — so router.post()'s onSuccess fires exactly the same as it would for a genuine
// save. Without checking the flash here, the row's own "Saved ✓" indicator contradicts
// the error banner already shown at the top of the page. See Documents/Path_breaks.md.
function markSaveOutcome(participantId) {
    const page = usePage();
    const nextSaved = new Set(savedIds.value);
    const nextFailed = new Set(failedIds.value);
    if (page.props.flash?.error) {
        nextSaved.delete(participantId);
        nextFailed.add(participantId);
    } else {
        nextFailed.delete(participantId);
        nextSaved.add(participantId);
    }
    savedIds.value = nextSaved;
    failedIds.value = nextFailed;
}
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
            markSaveOutcome(participant.id);
        },
        onFinish: () => {
            const next = new Set(savingIds.value);
            next.delete(participant.id);
            savingIds.value = next;
        },
    });
}

// One request for every visible row instead of one router.post() per participant —
// each save used to trigger its own full-event points recalculation server-side, so
// saving N participants sequentially reran that whole recalculation N times. See
// FestMarkEntryController::bulkStore().
async function saveAll() {
    bulkSaving.value = true;

    const rows = [];
    for (const { participant, item } of iterSaveRows()) {
        if (isAbsent(participant, item)) continue;
        rows.push({ participant, payload: payloadFor(participant, item) });
    }

    if (!rows.length) {
        bulkSaving.value = false;
        return;
    }

    savingIds.value = new Set(rows.map(({ participant }) => participant.id));

    await new Promise((resolve) => {
        router.post(
            `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/marks/bulk`,
            { rows: rows.map(({ payload }) => payload) },
            {
                preserveScroll: true,
                onSuccess: () => {
                    const result = usePage().props.flash?.bulkMarkSaveResult;
                    const nextSaved = new Set(savedIds.value);
                    const nextFailed = new Set(failedIds.value);

                    for (const { participant } of rows) {
                        if (result?.results?.[participant.id]?.ok) {
                            nextSaved.add(participant.id);
                            nextFailed.delete(participant.id);
                        } else {
                            nextFailed.add(participant.id);
                            nextSaved.delete(participant.id);
                        }
                    }

                    savedIds.value = nextSaved;
                    failedIds.value = nextFailed;
                },
                onFinish: () => {
                    savingIds.value = new Set();
                    resolve();
                },
            }
        );
    });

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

    // Dense ranking, matching FestSportsAutoRankService::assignDenseRanks() exactly: a
    // tie shares one rank and the next distinct score continues right after it (1,1,2,3
    // — never 1,1,3). Previously this incremented rank by sorted INDEX on any score drop
    // (competition/skip-style: 1,1,3,4) and stopped assigning past rank 6 entirely,
    // silently leaving every participant ranked 7th or lower unranked.
    let currentRank = 0;
    let lastScore = null;
    for (let i = 0; i < scoredRows.length; i++) {
        if (lastScore === null || Math.abs(scoredRows[i].score - lastScore) > 0.000001) {
            currentRank++;
            lastScore = scoredRows[i].score;
        }
        setRank(scoredRows[i].participantId, scoredRows[i].item, markForms, currentRank);
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
