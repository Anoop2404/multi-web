<template>
    <PortalLayout
        role-label="Judge Portal"
        :title="event.title"
        subtitle="Mark entry"
        accent="amber"
        :nav-items="navItems"
    >
        <p class="text-xs text-slate-600 bg-slate-50 border rounded-lg px-3 py-2 mb-3">
            Position / rank: same number can be used for multiple athletes (ties). Enter measurement for sports timing.
        </p>
        <div v-for="reg in registrations" :key="reg.id" class="card mb-4">
            <h3 class="font-semibold text-sm mb-3">{{ reg.item?.title }}</h3>
                <div v-for="p in reg.participants" :key="p.id" class="grid md:grid-cols-8 gap-2 items-end border-t py-3">
                <div class="md:col-span-2 text-sm">
                    <span v-if="participantRegNo(p)" class="font-mono text-xs text-[#0f3d7a] mr-2">{{ participantRegNo(p) }}</span>
                    <span class="font-mono text-xs text-gray-400 mr-2">#{{ participantLabels[p.id] ?? p.chest_no ?? '—' }}</span>
                    <span v-if="!maskNames">{{ p.student?.name ?? p.teacher?.name }}</span>
                    <span v-else class="text-gray-400 text-xs">(anonymous)</span>
                </div>
                <input v-model="forms[p.id].grade" placeholder="Grade" class="field">
                <input v-model.number="forms[p.id].position" type="number" min="1" placeholder="Rank (ties OK)" class="field" title="Same rank allowed for ties">
                <input v-model.number="forms[p.id].score" type="number" min="0" step="0.01" placeholder="Score" class="field">
                <template v-if="showMeasurement(reg.item)">
                    <input v-model="forms[p.id].measurement_value" placeholder="Time/dist" class="field">
                    <input v-model="forms[p.id].measurement_unit" placeholder="s/m" class="field">
                </template>
                <button @click="save(p, reg.item_id)" class="px-3 py-2 text-white rounded-lg text-sm md:col-span-1">Save</button>
            </div>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed, reactive, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    sahodaya: Object, event: Object, registrations: Array, marks: Object,
    maskNames: Boolean, participantLabels: Object,
});

const forms = reactive({});

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

const navItems = computed(() => [
    { href: `/portal/judge/${props.sahodaya.id}`, label: 'Dashboard' },
    { href: `/portal/judge/${props.sahodaya.id}/events/${props.event.id}/marks`, label: 'Mark entry' },
]);
</script>

