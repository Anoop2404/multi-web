<template>
    <PortalLayout
        role-label="Fest Mark Entry"
        :title="event.title"
        accent="indigo"
        :nav-items="navItems"
    >
        <ReportHeadSubNav v-if="hasItemHeads && isSports"
                          :head-item-groups="headItemGroups"
                          :base-url="marksBaseUrl"
                          :selected-head-id="selectedHeadId"
                          :selected-item-id="selectedItemId"
                          :show-item-links="true" />

        <p v-if="isSports && event.record_tracking_enabled" class="text-xs text-amber-800 bg-amber-50 border rounded-lg px-3 py-2 mb-3">
            Record tracking is on — new records may trigger prize labels when marks are saved.
        </p>
        <p class="text-xs text-slate-600 bg-slate-50 border rounded-lg px-3 py-2 mb-3">
            <template v-if="isSports">
                Mark attendance, enter time/distance, pick rank or use Auto-rank.
            </template>
            <template v-else>
                Position / rank: same number can be assigned to multiple participants (ties).
            </template>
        </p>

        <p v-if="!sections.length" class="text-sm text-slate-500 py-6 text-center">
            No participants in this section. Pick another item head above.
        </p>

        <div class="space-y-4">
            <section v-for="section in sections" :key="section.key" class="card overflow-hidden p-0">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3 bg-slate-50/80">
                    <p class="font-semibold text-sm">
                        <span v-if="section.item?.head?.name" class="text-xs font-normal text-slate-500 mr-2">{{ section.item.head.name }}</span>
                        {{ section.item?.title }}
                    </p>
                    <button v-if="isSports && section.item?.id"
                            type="button"
                            class="btn-secondary text-xs !min-h-0"
                            @click="autoRank(section.item)">
                        Auto-rank
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="p-2">Participant</th>
                                <th v-if="isSports" class="p-2 min-w-[6.5rem]">Attendance</th>
                                <th v-if="showMeasurement(section.item)" class="p-2 min-w-[7rem]">Time / dist</th>
                                <th v-if="showMeasurement(section.item)" class="p-2 min-w-[4rem]">Unit</th>
                                <th class="p-2 min-w-[11rem]">Rank</th>
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
                                    <select :value="attendanceStatus(participant, item)"
                                            class="field text-sm !py-2 min-w-[6.5rem] w-full"
                                            @change="markAttendance(participant, item, $event.target.value)">
                                        <option value="">—</option>
                                        <option value="present">Present</option>
                                        <option value="absent">Absent</option>
                                    </select>
                                </td>
                                <td v-if="showMeasurement(section.item)" class="p-2 align-middle">
                                    <input v-model="markForms[participant.id].measurement_value"
                                           class="field !py-2 text-sm min-w-[7rem] w-full"
                                           placeholder="Time/dist"
                                           :disabled="isAbsent(participant, item)">
                                </td>
                                <td v-if="showMeasurement(section.item)" class="p-2 align-middle">
                                    <input v-model="markForms[participant.id].measurement_unit"
                                           class="field !py-2 text-sm min-w-[4rem] w-full"
                                           placeholder="s/m"
                                           :disabled="isAbsent(participant, item)">
                                </td>
                                <td class="p-2 align-middle">
                                    <select v-if="isSports"
                                            :value="markForms[participant.id].position ?? ''"
                                            class="field !py-2 text-sm min-w-[11rem] w-full"
                                            :disabled="isAbsent(participant, item)"
                                            @change="setRank(participant.id, item, markForms, $event.target.value)">
                                        <option value="">—</option>
                                        <option v-for="opt in rankOptionsForItem(item)" :key="opt.rank" :value="opt.rank">
                                            {{ opt.label }} ({{ opt.points }} pts)
                                        </option>
                                    </select>
                                    <input v-else
                                           v-model.number="markForms[participant.id].position"
                                           type="number" min="1"
                                           class="field !py-1 text-xs w-16"
                                           placeholder="#"
                                           @input="applyRankPoints(participant.id, item, markForms)">
                                </td>
                                <td class="p-2 text-right align-middle">
                                    <button type="button"
                                            class="btn-primary text-xs !min-h-0 !px-2 !py-1"
                                            :disabled="isAbsent(participant, item)"
                                            @click="saveMark(participant, item)">
                                        Save
                                    </button>
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
import { reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import ReportHeadSubNav from '@/Components/reports/ReportHeadSubNav.vue';
import { festMarkPortalPaths, festOpsEventNav } from '@/support/festOpsPortalNav.js';
import { festCoordinatorPortalNavItems } from '@/support/festCoordinatorPortalNav.js';
import { useFestMarkEntryDisplay } from '@/composables/useFestMarkEntryDisplay.js';

const props = defineProps({
    sahodaya: Object,
    event: Object,
    registrations: Array,
    marks: Object,
    attendance: { type: Object, default: () => ({}) },
    rankPoints: { type: Array, default: () => [] },
    festOpsBase: { type: String, default: null },
    headItemGroups: { type: Array, default: () => [] },
    hasItemHeads: { type: Boolean, default: false },
    selectedHeadId: { type: [String, Number], default: null },
    selectedItemId: { type: [String, Number], default: null },
});

const portalPaths = computed(() =>
    festMarkPortalPaths(props.sahodaya.id, props.event.id, props.festOpsBase),
);

const marksBaseUrl = computed(() => portalPaths.value.marksHref);

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
    applyRankPoints,
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
    router.post(portalPaths.value.marksPostUrl, buildMarkPayload(participant, item, markForms), { preserveScroll: true });
}

function autoRank(item) {
    if (!item?.id || !confirm(`Auto-rank "${item.title}" from measurements? Absent athletes are skipped.`)) {
        return;
    }

    router.post(portalPaths.value.autoRankUrl(item.id), {}, { preserveScroll: true });
}
</script>
