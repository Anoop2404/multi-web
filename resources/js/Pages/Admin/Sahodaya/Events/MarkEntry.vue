<template>
    <SahodayaEventsLayout title="Mark entry" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                          :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        
        <!-- Header Actions -->
        <PageHeader :title="`${event.title} — Mark entry`" eyebrow="Mark & Results Entry"
                    :description="filterDescription">
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <Link v-if="competitionUrl" :href="competitionUrl" class="btn-secondary text-xs">
                        ← {{ isSports ? 'By Event Head' : 'By Item Head' }}
                    </Link>
                    <a v-if="cumulativeSheetUrl" :href="cumulativeSheetUrl" target="_blank" class="btn-secondary text-xs">
                        Cumulative Sheet ↓
                    </a>
                    <Link :href="importUrl" class="btn-primary text-xs">
                        Import Marks
                    </Link>
                </div>
            </template>
        </PageHeader>

        <!-- Sub Navigation Bar -->
        <SportsSetupSubNav v-if="isSports" :sahodaya-id="sahodaya.id" :event-id="event.id"
                           :event="event" active="marks" class="mb-4" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="marks" class="mb-4" />

        <!-- Guidance Banner -->
        <div class="mb-4 rounded-xl border border-indigo-200/80 bg-indigo-50/50 p-4 text-xs text-indigo-950 shadow-sm space-y-1">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-bold text-indigo-900 flex items-center gap-1.5 text-sm">
                    <span>✍️</span> Mark Entry &amp; Results Evaluation
                </p>
                <Link v-if="selectedItemId" :href="competitionUrl" class="text-xs font-bold text-indigo-700 hover:underline">
                    ← Back to Item Directory
                </Link>
            </div>
            <p class="text-indigo-900/80 leading-relaxed">
                <template v-if="isSports">
                    Mark attendance, enter time/distance, then pick position/rank. Team points apply automatically when rank is selected.
                </template>
                <template v-else>
                    Enter place/position (1, 2, 3…). Multiple participants can share the same rank (ties allowed). Points and grades are saved per participant.
                </template>
            </p>
        </div>

        <!-- Filter & Item Selector Toolbar Card -->
        <div class="card !p-4 space-y-3.5 mb-5">
            <!-- Item Pill Chips -->
            <div v-if="itemOptions.length > 1" class="space-y-2 border-b border-slate-100 pb-3">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Select Competition Item</p>
                <div class="flex flex-wrap gap-1.5 text-xs">
                    <button v-for="it in itemOptions" :key="it.id" type="button" @click="selectItem(it.id)"
                            :class="selectedItemId == it.id
                                ? 'bg-slate-900 text-white font-bold shadow-sm'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-semibold'"
                            class="px-3.5 py-1.5 rounded-full transition whitespace-nowrap">
                        {{ it.title }}
                    </button>
                </div>
            </div>

            <!-- Judge Criteria Toggle & Save All Bar -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <button type="button" class="btn-secondary text-xs flex items-center gap-1.5" @click="showCriteriaPanel = !showCriteriaPanel">
                    <span>⚖️ Marking Criteria / Judge Columns</span>
                    <span class="text-[11px] font-bold px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700">
                        {{ hasCriteria ? `${criteria.length} configured` : 'Single grade/score' }}
                    </span>
                </button>

                <button v-if="sections.length" type="button" class="btn-primary text-xs !py-1.5 !px-4"
                        :disabled="bulkSaving" @click="saveAll">
                    {{ bulkSaving ? 'Saving all…' : 'Save All Marks ✓' }}
                </button>
            </div>

            <!-- Expandable Criteria Panel -->
            <div v-if="showCriteriaPanel" class="bg-slate-50/80 p-4 rounded-xl border border-slate-200/80 space-y-3 text-xs pt-3">
                <h4 class="font-bold text-slate-900">Configure Judge Criteria / Sub-Scores</h4>
                <p class="text-slate-500 leading-relaxed">
                    Add rows per judge or criterion (e.g. "Judge 1", "Content", "Presentation"). The sum becomes the participant's mark. Leave empty to use plain grade/score entry.
                </p>
                <div v-for="(row, idx) in criteriaDraft" :key="idx" class="flex items-center gap-2">
                    <input v-model="row.label" type="text" class="field text-xs flex-1" placeholder="e.g. Judge 1 / Content">
                    <input v-model.number="row.max_score" type="number" min="0.5" step="0.5" class="field text-xs w-24" placeholder="Max">
                    <button type="button" class="btn-secondary text-xs !text-rose-700 hover:!bg-rose-50 !py-1 !px-2" @click="criteriaDraft.splice(idx, 1)">Remove</button>
                </div>
                <div class="flex items-center gap-2 pt-1">
                    <button type="button" class="btn-secondary text-xs" @click="criteriaDraft.push({ id: null, label: '', max_score: 10 })">+ Add Criterion Row</button>
                    <button type="button" class="btn-primary text-xs !py-1 !px-3" :disabled="savingCriteria" @click="saveCriteriaConfig">
                        {{ savingCriteria ? 'Saving…' : 'Save Criteria Config' }}
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

        <!-- Sections Table -->
        <div v-else class="space-y-6">
            <section v-for="section in sections" :key="section.key" class="card !p-0 overflow-hidden border border-slate-200">
                <!-- Section Header -->
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 bg-slate-50">
                    <div>
                        <h3 class="section-title !mb-0 text-sm font-bold text-slate-900">{{ section.item?.title }}</h3>
                        <p v-if="section.schoolName" class="text-xs text-slate-500 mt-0.5">{{ section.schoolName }}</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <div v-if="section.rows.length > 1" class="flex items-center gap-1.5">
                            <span class="text-slate-500 font-medium text-[11px]">Same rank for all:</span>
                            <template v-if="isSports">
                                <select v-model="bulkRank[section.bulkKey]" class="field text-xs !py-1 min-w-[9rem]">
                                    <option :value="null">—</option>
                                    <option v-for="opt in rankOptionsForItem(section.item)" :key="opt.rank" :value="opt.rank">
                                        {{ opt.label }} ({{ opt.points }} pts)
                                    </option>
                                </select>
                            </template>
                            <template v-else>
                                <input v-model.number="bulkRank[section.bulkKey]" type="number" min="1" class="field text-xs !py-1 w-16" placeholder="#" />
                            </template>
                            <button type="button" class="btn-secondary text-xs !py-1 !px-2.5"
                                    :disabled="!bulkRank[section.bulkKey]"
                                    @click="applyBulkRank(section, markForms)">
                                Apply
                            </button>
                        </div>

                        <button v-if="isSports && section.item?.id" type="button" class="btn-secondary text-xs !py-1 !px-2.5" @click="autoRank(section.item)">
                            Auto-rank
                        </button>
                        <span class="px-2.5 py-0.5 rounded-full bg-slate-200 text-slate-700 font-bold text-[11px]">
                            {{ section.rows.length }} participant(s)
                        </span>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto bg-white">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50/70 text-slate-500 border-b border-slate-200 uppercase tracking-wider text-[10px] font-bold">
                            <tr>
                                <th class="p-3.5">Participant / Squad</th>
                                <th v-if="isSports" class="p-3.5 w-32">Attendance</th>
                                <th v-if="showMeasurement(section.item)" class="p-3.5 w-36">Time / Distance</th>
                                <th v-if="showMeasurement(section.item)" class="p-3.5 w-20">Unit</th>
                                <th class="p-3.5 w-44">Position / Rank</th>
                                <th v-if="!isSports && !hasCriteria" class="p-3.5 w-24">Grade</th>
                                <th v-if="!isSports && !hasCriteria" class="p-3.5 w-24">Points</th>
                                <template v-if="!isSports && hasCriteria">
                                    <th v-for="c in criteria" :key="c.id" class="p-3.5 w-24">
                                        {{ c.label }} <span class="font-normal text-slate-400">(/ {{ c.max_score }})</span>
                                    </th>
                                    <th class="p-3.5 w-24">Total</th>
                                </template>
                                <th class="p-3.5 text-right w-24">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="{ participant, item } in section.rows" :key="participant.id"
                                :class="isAbsent(participant, item) ? 'bg-slate-50/80 opacity-60' : 'hover:bg-slate-50/70 transition'">
                                
                                <!-- Participant -->
                                <td class="p-3.5 font-bold text-slate-900">
                                    <div class="flex items-center gap-2">
                                        <span v-if="participant.chest_no" class="font-mono text-xs font-bold text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-100">
                                            #{{ participant.chest_no }}
                                        </span>
                                        <span>{{ participantName(participant) }}</span>
                                        <span v-if="participant._is_team" class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700 border border-indigo-100">
                                            Team · {{ participant._member_count }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Attendance (Sports) -->
                                <td v-if="isSports" class="p-3.5">
                                    <select :value="attendanceStatus(participant, item)"
                                            class="field text-xs !py-1"
                                            @change="markAttendance(participant, item, $event.target.value)">
                                        <option value="">—</option>
                                        <option value="present">Present ✓</option>
                                        <option value="absent">Absent ✕</option>
                                    </select>
                                </td>

                                <!-- Time / Distance -->
                                <td v-if="showMeasurement(section.item)" class="p-3.5">
                                    <input v-model="markForms[participant.id].measurement_value"
                                           class="field text-xs"
                                           placeholder="e.g. 7.45 / 5.20"
                                           :disabled="isAbsent(participant, item)">
                                </td>
                                <td v-if="showMeasurement(section.item)" class="p-3.5">
                                    <input v-model="markForms[participant.id].measurement_unit"
                                           class="field text-xs"
                                           placeholder="s / m"
                                           :disabled="isAbsent(participant, item)">
                                </td>

                                <!-- Rank -->
                                <td class="p-3.5">
                                    <select v-if="isSports"
                                            :value="markForms[participant.id].position ?? ''"
                                            class="field text-xs !py-1 font-semibold"
                                            :disabled="isAbsent(participant, item)"
                                            @change="setRank(participant.id, item, markForms, $event.target.value)">
                                        <option value="">—</option>
                                        <option v-for="opt in rankOptionsForItem(item)" :key="opt.rank" :value="opt.rank">
                                            {{ opt.label }} ({{ opt.points }} pts)
                                        </option>
                                    </select>
                                    <input v-else
                                           v-model.number="markForms[participant.id].position" type="number" min="1"
                                           class="field text-xs font-semibold" placeholder="Rank (1, 2, 3)" title="Ties allowed"
                                           @input="applyRankPoints(participant.id, item, markForms)">
                                </td>

                                <!-- Grade & Points (Non-Sports, No Criteria) -->
                                <td v-if="!isSports && !hasCriteria" class="p-3.5">
                                    <select v-model="markForms[participant.id].grade" class="field text-xs">
                                        <option value="">—</option>
                                        <option>A</option>
                                        <option>A+</option>
                                        <option>B</option>
                                        <option>C</option>
                                    </select>
                                </td>
                                <td v-if="!isSports && !hasCriteria" class="p-3.5">
                                    <input v-model.number="markForms[participant.id].score" type="number" min="0"
                                           class="field text-xs font-bold tabular-nums" placeholder="Pts">
                                </td>

                                <!-- Judge Criteria Inputs -->
                                <template v-if="!isSports && hasCriteria">
                                    <td v-for="c in criteria" :key="c.id" class="p-3.5">
                                        <input v-model.number="criteriaForms[participant.id][c.id]" type="number" min="0" :max="c.max_score" step="0.5"
                                               class="field text-xs tabular-nums" placeholder="0">
                                    </td>
                                    <td class="p-3.5 font-black text-slate-900 text-sm tabular-nums">{{ criteriaTotal(participant.id) }}</td>
                                </template>

                                <!-- Save Button -->
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
});

const displayCtx = useFestMarkEntryDisplay(props);
const {
    isSports,
    filterDescription,
    cumulativeSheetUrl,
    importUrl,
    registrationsUrl,
    settingsPointsUrl,
    itemOptions,
    sections,
    criteria,
    hasCriteria,
    criteriaDraft,
    showCriteriaPanel,
    savedIds,
    savingIds,
    bulkSaving,
    savingCriteria,
    markForms,
    criteriaForms,
    bulkRank,
    rankOptionsForItem,
    participantName,
    participantRegNo,
    attendanceStatus,
    isAbsent,
    showMeasurement,
    formatDate,
    rankLabel,
    criteriaTotal,
    markAttendance,
    setRank,
    applyRankPoints,
    applyBulkRank,
    autoRank,
    saveMark,
    saveAll,
    saveCriteriaConfig,
} = displayCtx;

function selectItem(id) {
    router.get(window.location.pathname, { item_id: id }, { preserveScroll: true });
}
</script>
