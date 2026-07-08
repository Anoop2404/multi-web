<template>
    <PortalLayout
        role-label="Judge Portal"
        :title="event.title"
        subtitle="Mark entry"
        accent="amber"
        :nav-items="navItems"
    >
        <div class="mb-4">
            <h2 class="text-lg font-bold text-slate-900">{{ event.title }}</h2>
            <p class="text-sm text-slate-500">Mark entry — enter grades and scores for each item below.</p>
        </div>

        <ReportHeadSubNav v-if="hasItemHeads && isSports"
                          :head-item-groups="headItemGroups"
                          :base-url="marksBaseUrl"
                          :selected-head-id="selectedHeadId"
                          :selected-item-id="selectedItemId"
                          :show-item-links="true" />

        <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-3">
            You are entering <strong>your judge scores</strong>. When all judges for an item have scored a participant, the official mark is averaged automatically.
        </p>
        <p class="text-xs text-slate-600 bg-slate-50 border rounded-lg px-3 py-2 mb-3">
            Position / rank: same number can be used for multiple athletes (ties). Enter measurement for sports timing.
        </p>

        <p v-if="!registrations.length" class="text-sm text-slate-500 py-6 text-center">
            No participants in this section. Pick another item head above.
        </p>

        <div v-for="reg in registrations" :key="reg.id" class="card mb-4">
            <h3 class="font-semibold text-sm mb-3">
                <span v-if="reg.item?.head?.name" class="text-xs font-normal text-slate-500 mr-2">{{ reg.item.head.name }}</span>
                {{ reg.item?.title }}
            </h3>
            <div v-for="p in reg.participants" :key="p.id" class="grid md:grid-cols-8 gap-2 items-end border-t py-3">
                <div class="md:col-span-2 text-sm">
                    <span v-if="participantRegNo(p)" class="font-mono text-xs text-[#0f3d7a] mr-2">{{ participantRegNo(p) }}</span>
                    <span class="font-mono text-xs text-gray-400 mr-2">#{{ participantLabels[p.id] ?? p.chest_no ?? '—' }}</span>
                    <span v-if="!maskNames">{{ p.student?.name ?? p.teacher?.name }}</span>
                    <span v-else class="text-gray-400 text-xs">(anonymous)</span>
                </div>
                <select v-if="!isSports" v-model="forms[p.id].grade" class="field">
                    <option value="">Grade</option>
                    <option v-for="g in gradeOptions" :key="g" :value="g">{{ g }}</option>
                </select>
                <input v-model.number="forms[p.id].position" type="number" min="1" placeholder="Rank (ties OK)" class="field" title="Same rank allowed for ties">
                <input v-model.number="forms[p.id].score" type="number" min="0" step="0.01" placeholder="Score" class="field">
                <template v-if="showMeasurement(reg.item)">
                    <input v-model="forms[p.id].measurement_value" placeholder="Time/dist" class="field">
                    <input v-model="forms[p.id].measurement_unit" placeholder="s/m" class="field">
                </template>
                <button @click="save(p, reg.item_id)" class="px-3 py-2 text-white rounded-lg text-sm md:col-span-1 bg-amber-600 hover:bg-amber-700">Save</button>
            </div>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import ReportHeadSubNav from '@/Components/reports/ReportHeadSubNav.vue';
import { judgePortalNavItems } from '@/support/judgePortalNav.js';
import { computed, reactive, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    sahodaya: Object,
    event: Object,
    registrations: Array,
    marks: Object,
    maskNames: Boolean,
    participantLabels: Object,
    judgeMode: { type: Boolean, default: false },
    headItemGroups: { type: Array, default: () => [] },
    hasItemHeads: { type: Boolean, default: false },
    selectedHeadId: { type: [String, Number], default: null },
    selectedItemId: { type: [String, Number], default: null },
});

const gradeOptions = ['A+', 'A', 'B', 'C'];
const forms = reactive({});

const isSports = computed(() => props.event?.event_type === 'sports');
const marksBaseUrl = computed(() => `/portal/judge/${props.sahodaya.id}/events/${props.event.id}/marks`);
const navItems = computed(() => judgePortalNavItems(props.sahodaya.id, props.event.id));

onMounted(() => {
    for (const reg of props.registrations) {
        for (const p of reg.participants) {
            const existing = props.marks?.[p.id];
            forms[p.id] = {
                grade: existing?.grade ?? '',
                position: existing?.position ?? '',
                score: existing?.score ?? '',
                measurement_value: existing?.measurement_value ?? '',
                measurement_unit: existing?.measurement_unit ?? '',
            };
        }
    }
});

function showMeasurement(item) {
    return props.event.record_tracking_enabled
        && (item?.category === 'sports' || item?.sport_discipline);
}

function save(participant, itemId) {
    router.post(`/portal/judge/${props.sahodaya.id}/events/${props.event.id}/marks`, {
        participant_id: participant.id,
        item_id: itemId,
        ...forms[participant.id],
    }, { preserveScroll: true });
}

function participantRegNo(participant) {
    return participant.student?.reg_no ?? participant.teacher?.reg_no ?? null;
}
</script>
