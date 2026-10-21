<template>
    <PortalLayout
        role-label="Fest Mark Entry"
        :title="event.title"
        accent="indigo"
        :nav-items="navItems"
    >
        <ReportItemSearchSelect v-if="flatItems.length"
                                :items="flatItems"
                                :model-value="selectedItemId"
                                label="Competition Item"
                                :all-items-label="`All ${flatItems.length} items`"
                                search-placeholder="Search by item name or code…"
                                @select="onItemSelect" />

        <div v-if="$page.props.flash?.success" class="notice-banner notice-banner--success text-sm mb-3">
            {{ $page.props.flash.success }}
        </div>
        <div v-if="$page.props.flash?.error || $page.props.errors?.measurement" class="notice-banner notice-banner--error text-sm mb-3">
            {{ $page.props.flash?.error || $page.props.errors?.measurement }}
        </div>

        <p v-if="isSports && event.record_tracking_enabled" class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-3">
            🏆 <strong>Record tracking is on:</strong> Breaking existing records will trigger special recognition when marks are saved.
        </p>

        <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-3.5 text-xs text-slate-700 mb-4 shadow-xs">
            <div class="flex items-start justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="text-base">📋</span>
                    <div>
                        <strong class="text-slate-900 text-xs">Evaluator & Coordinator Entry Guide</strong>
                        <p class="text-[11px] text-slate-500">Quick format guidelines for attendance, time, distance, and auto-ranking.</p>
                    </div>
                </div>
                <button type="button" class="text-indigo-700 hover:text-indigo-900 font-medium underline shrink-0" @click="showInstructions = !showInstructions">
                    {{ showInstructions ? 'Hide guide ▲' : 'Show full guide ▼' }}
                </button>
            </div>

            <!-- Quick pills -->
            <div class="mt-2.5 flex flex-wrap gap-2 text-[11px]">
                <span v-if="isSports" class="inline-flex items-center gap-1 rounded bg-white px-2 py-1 border border-slate-200 shadow-2xs">
                    ⏱️ <strong>Time:</strong> <code class="font-mono text-indigo-700">11.45</code> or <code class="font-mono text-indigo-700">1:02.50</code>
                </span>
                <span v-if="isSports" class="inline-flex items-center gap-1 rounded bg-white px-2 py-1 border border-slate-200 shadow-2xs">
                    📏 <strong>Distance:</strong> <code class="font-mono text-indigo-700">5.45</code> (m)
                </span>
                <span class="inline-flex items-center gap-1 rounded bg-white px-2 py-1 border border-slate-200 shadow-2xs">
                    🏅 <strong>Auto-rank:</strong> Lower time or higher score/distance ranks 1st
                </span>
                <span class="inline-flex items-center gap-1 rounded bg-white px-2 py-1 border border-slate-200 shadow-2xs">
                    🤝 <strong>Ties:</strong> Same position number can be assigned to ties
                </span>
            </div>

            <!-- Collapsible full guide -->
            <div v-show="showInstructions" class="mt-3 pt-2.5 border-t border-slate-200/80 grid sm:grid-cols-2 gap-3 text-slate-600 leading-relaxed">
                <div>
                    <h5 class="font-semibold text-slate-800 mb-1">⏱️ Measurement Formats</h5>
                    <ul class="list-disc pl-4 space-y-1">
                        <li><strong>Sprints / Track:</strong> Enter decimal seconds (<code class="font-mono">11.45</code>), minutes & seconds (<code class="font-mono">1:02.50</code>), or natural (<code class="font-mono">1m 23s</code>).</li>
                        <li><strong>Field / Distance:</strong> Enter meters (<code class="font-mono">5.45</code>). Distance auto-ranks in descending order (highest wins).</li>
                        <li><strong>Attendance:</strong> Mark 'Absent' to exclude a participant from ranking and results.</li>
                    </ul>
                </div>
                <div>
                    <h5 class="font-semibold text-slate-800 mb-1">🏅 Auto-Rank & Verification</h5>
                    <ul class="list-disc pl-4 space-y-1">
                        <li><strong>Auto-rank:</strong> Computes standard positions (1st, 2nd, 3rd) according to official competition rules.</li>
                        <li><strong>Manual Overrides:</strong> You can edit any rank directly and click <em>Save</em> to preserve specific judge rulings or disqualifications.</li>
                    </ul>
                </div>
            </div>
        </div>

        <p v-if="!sections.length" class="text-sm text-slate-500 py-6 text-center">
            No participants in this section. Pick another Event Head above.
        </p>

        <div class="space-y-4">
            <section v-for="section in sections" :key="section.key" class="card overflow-hidden p-0">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3 bg-slate-50/80">
                    <p class="font-semibold text-sm">
                        <span v-if="section.item?.head?.name" class="text-xs font-normal text-slate-500 mr-2">{{ section.item.head.name }}</span>
                        {{ section.item?.title }}
                    </p>
                    <template v-if="section.item?.id && !rankEntryOnly(section.item)">
                        <div class="flex items-center gap-1.5">
                            <select v-if="isSports"
                                    :value="getAutoRankBasis(section.item.id, section.item)"
                                    @change="setAutoRankBasis(section.item.id, $event.target.value)"
                                    class="field !py-1 !px-2 !text-xs w-36"
                                    title="Auto-rank criteria">
                                <option value="time">⏱️ Time (Faster 1st)</option>
                                <option value="distance">📏 Length / Dist</option>
                            </select>
                            <button type="button"
                                    class="btn-secondary text-xs !min-h-0"
                                    :disabled="autoRankingItem === section.item.id"
                                    @click="autoRank(section.item)">
                                {{ autoRankingItem === section.item.id ? 'Ranking…' : 'Auto-rank' }}
                            </button>
                        </div>
                    </template>
                    <span v-else-if="section.item?.id" class="text-[11px] text-slate-400">Rank-only — enter ranks directly</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[44rem]">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="p-2">Participant</th>
                                <th v-if="isSports" class="p-2 min-w-[6.5rem]">Attendance</th>
                                <th v-if="showMeasurement(section.item)" class="p-2 min-w-[7rem]">{{ measurementUnitHint(section.item) === 'm' ? 'Distance (m)' : 'Time / dist' }}</th>
                                <th v-if="showMeasurement(section.item)" class="p-2 min-w-[4rem]">Unit</th>
                                <th v-if="showScore(section.item)" class="p-2 min-w-[7rem]">Score</th>
                                <th v-if="showRankColumn(section.item)" class="p-2 min-w-[11rem]">Rank</th>
                                <th class="p-2 w-20"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="{ participant, item } in section.rows" :key="participant.id"
                                class="border-t"
                                :class="isAbsent(participant, item) ? 'opacity-60 bg-slate-50' : ''">
                                <td class="p-2">
                                    <span v-if="participant.chest_no" class="font-mono text-xs font-semibold text-indigo-800 mr-1">
                                        #{{ participant.chest_no }}
                                    </span>
                                    <span v-if="participant.student?.reg_no ?? participant.teacher?.reg_no"
                                          class="font-mono text-xs text-[#0f3d7a] mr-1">
                                        {{ participant.student?.reg_no ?? participant.teacher?.reg_no }}
                                    </span>
                                    {{ participant.student?.name ?? participant.teacher?.name }}
                                </td>
                                <td v-if="isSports" class="p-2 align-middle">
                                    <SearchableSelect :model-value="attendanceStatus(participant, item)"
                                            class="min-w-[6.5rem] w-full"
                                            :options="[{ value: 'present', label: 'Present' }, { value: 'absent', label: 'Absent' }]"
                                            :all-option="false" placeholder="Not marked" :searchable="false" escape-overflow
                                            @update:model-value="(status) => markAttendance(participant, item, status)" />
                                </td>
                                <td v-if="showMeasurement(section.item)" class="p-2 align-middle">
                                    <input v-model="markForms[participant.id].measurement_value"
                                           class="field !py-2 text-sm min-w-[7rem] w-full"
                                           :placeholder="measurementPlaceholder(item)"
                                           :disabled="isAbsent(participant, item)">
                                </td>
                                <td v-if="showMeasurement(section.item)" class="p-2 align-middle">
                                    <input v-model="markForms[participant.id].measurement_unit"
                                           class="field !py-2 text-sm min-w-[4rem] w-full"
                                           :placeholder="measurementUnitHint(item)"
                                           :disabled="isAbsent(participant, item)">
                                </td>
                                <td v-if="showScore(item)" class="p-2 align-middle">
                                    <input v-model.number="markForms[participant.id].score" type="number" min="0" step="0.01" class="field" :aria-label="`Score for ${participant.student?.name ?? participant.teacher?.name ?? participant.id}`" />
                                </td>
                                <td v-if="showRankColumn(item)" class="p-2 align-middle">
                                    <SearchableSelect v-if="isSports"
                                            :model-value="markForms[participant.id].position ?? ''"
                                            class="min-w-[11rem] w-full"
                                            :options="rankSelectOptions(item)"
                                            :all-option="true"
                                            all-label="—"
                                            escape-overflow
                                            :disabled="isAbsent(participant, item)"
                                            @update:model-value="(value) => setRank(participant.id, item, markForms, value)" />
                                    <input v-else
                                           v-model.number="markForms[participant.id].position"
                                           type="number" min="1"
                                           class="field !py-1 text-xs w-16"
                                           placeholder="#"
                                           >
                                </td>
                                <td class="p-2 text-right align-middle whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <span v-if="savedIds.has(participant.id)" class="text-xs font-bold text-emerald-600">Saved ✓</span>
                                        <span v-else-if="failedIds.has(participant.id)" class="text-xs font-bold text-rose-600" title="Not saved — see message above">Failed ✕</span>
                                        <button type="button"
                                                class="btn-primary text-xs !min-h-0 !px-2.5 !py-1"
                                                :disabled="isAbsent(participant, item) || savingIds.has(participant.id)"
                                                @click="saveMark(participant, item)">
                                            {{ savingIds.has(participant.id) ? '…' : 'Save' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </PortalLayout>
</template>

<script setup>
import { reactive, computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import ReportItemSearchSelect from '@/Components/reports/ReportItemSearchSelect.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { festMarkPortalPaths, festOpsEventNav } from '@/support/festOpsPortalNav.js';
import { festCoordinatorPortalNavItems } from '@/support/festCoordinatorPortalNav.js';
import { useFestMarkEntryDisplay } from '@/composables/useFestMarkEntryDisplay.js';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object,
    event: Object,
    registrations: Array,
    marks: Object,
    attendance: { type: Object, default: () => ({}) },
    rankPointsByType: { type: Object, default: () => ({}) },
    festOpsBase: { type: String, default: null },
    headItemGroups: { type: Array, default: () => [] },
    hasItemHeads: { type: Boolean, default: false },
    selectedHeadId: { type: [String, Number], default: null },
    selectedItemId: { type: [String, Number], default: null },
});

const { confirm } = useConfirm();

const savingIds = reactive(new Set());
const savedIds = reactive(new Set());
const failedIds = reactive(new Set());
const autoRankingItem = ref(null);
const showInstructions = ref(false);
const autoRankBasis = reactive({});

function getAutoRankBasis(itemId, item) {
    if (!autoRankBasis[itemId]) {
        autoRankBasis[itemId] = (item?.result_method === 'distance' || item?.ranking_direction === 'desc') ? 'distance' : 'time';
    }
    return autoRankBasis[itemId];
}

function setAutoRankBasis(itemId, val) {
    autoRankBasis[itemId] = val;
}

const portalPaths = computed(() =>
    festMarkPortalPaths(props.sahodaya.id, props.event.id, props.festOpsBase),
);

const marksBaseUrl = computed(() => portalPaths.value.marksHref);

const flatItems = computed(() => (props.headItemGroups ?? []).flatMap((h) => h.items ?? []));

function onItemSelect(itemId) {
    router.get(marksBaseUrl.value, itemId ? { item_id: itemId } : {}, { preserveScroll: true, preserveState: false });
}

const navItems = computed(() => {
    if (props.festOpsBase) {
        return festOpsEventNav(props.sahodaya.id, props.event.id, ['marks']);
    }

    return festCoordinatorPortalNavItems(props.sahodaya.id, props.event.id);
});

const isSports = computed(() => props.event?.event_type === 'sports');

const {
    sections,
    attendanceStatus,
    isAbsent,
    showMeasurement,
    rankEntryOnly,
    showRankColumn,
    measurementUnitHint,
    buildMarkPayload,
    rankOptionsForItem,
    setRank,
} = useFestMarkEntryDisplay(props, isSports);

const markForms = reactive({});

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

function rankSelectOptions(item) {
    return rankOptionsForItem(item).map((opt) => ({ value: opt.rank, label: `${opt.label} (${opt.points} pts)` }));
}

function showScore(item) {
    return !isSports.value && !rankEntryOnly(item) && !['time', 'distance'].includes((item?.result_method || '').toLowerCase());
}

function measurementPlaceholder(item) {
    return (item?.result_method || '').toLowerCase() === 'time' ? 'Time (s)' : 'Dist (m)';
}

function markAttendance(participant, item, status) {
    if (!status) {
        return;
    }

    router.post(portalPaths.value.attendancePostUrl, {
        participant_id: participant.id,
        item_id: item.id,
        status,
    }, { preserveScroll: true });
}

function saveMark(participant, item) {
    const id = participant.id;
    savingIds.add(id);
    router.post(portalPaths.value.marksPostUrl, buildMarkPayload(participant, item, markForms), {
        preserveScroll: true,
        onSuccess: () => {
            savedIds.add(id);
            failedIds.delete(id);
        },
        onError: () => {
            failedIds.add(id);
            savedIds.delete(id);
        },
        onFinish: () => {
            savingIds.delete(id);
        },
    });
}

async function autoRank(item) {
    if (!item?.id || !(await confirm({ message: `Auto-rank "${item.title}" from saved measurements or scores? Save your changes first. Absent participants are skipped.`, destructive: false }))) {
        return;
    }

    autoRankingItem.value = item.id;
    const basis = isSports.value ? getAutoRankBasis(item.id, item) : null;
    router.post(portalPaths.value.autoRankUrl(item.id), { rank_basis: basis }, {
        preserveScroll: true,
        preserveState: false,
        onFinish: () => {
            autoRankingItem.value = null;
        },
    });
}
</script>
