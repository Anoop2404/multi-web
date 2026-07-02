<template>
    <PortalLayout
        role-label="Fest Mark Entry"
        :title="event.title"
        accent="indigo"
        :nav-items="navItems"
    >
        <p v-if="event.record_tracking_enabled" class="text-xs text-amber-800 bg-amber-50 border rounded-lg px-3 py-2 mb-3">
            Sports measurements trigger record-break prizes when tracking is enabled.
        </p>
        <p class="text-xs text-slate-600 bg-slate-50 border rounded-lg px-3 py-2 mb-3">
            Position / rank: same number can be assigned to multiple participants (ties).
        </p>
        <div class="space-y-4">
            <div v-for="reg in registrations" :key="reg.id" class="card">
                <p class="font-semibold text-sm mb-2">{{ reg.item?.title }}</p>
                <div v-for="p in reg.participants" :key="p.id" class="flex flex-wrap items-end gap-2 border-t py-2">
                    <span class="text-sm flex-1 min-w-[140px]">
                        <span v-if="p.student?.reg_no ?? p.teacher?.reg_no" class="font-mono text-xs text-[#0f3d7a] mr-2">
                            {{ p.student?.reg_no ?? p.teacher?.reg_no }}
                        </span>
                        {{ p.student?.name ?? p.teacher?.name }}
                        <span v-if="p.chest_no" class="text-xs text-gray-400 ml-1">#{{ p.chest_no }}</span>
                    </span>
                    <form @submit.prevent="saveMark(p, reg.item)" class="flex flex-wrap gap-2">
                        <input v-model.number="markForms[p.id].position" type="number" min="1" class="field w-16" placeholder="Rank" title="Ties allowed">
                        <select v-model="markForms[p.id].grade" class="field w-20">
                            <option value="">—</option>
                            <option>A</option><option>A+</option><option>B</option><option>C</option>
                        </select>
                        <input v-model.number="markForms[p.id].score" type="number" min="0" class="field w-20" placeholder="Pts">
                        <template v-if="showMeasurement(reg.item)">
                            <input v-model="markForms[p.id].measurement_value" class="field w-28" placeholder="Time/dist">
                            <input v-model="markForms[p.id].measurement_unit" class="field w-14" placeholder="s/m">
                        </template>
                        <button class="btn-primary text-xs !min-h-0 !px-2 !py-1">Save</button>
                    </form>
                </div>
            </div>
        </div>
    </PortalLayout>
</template>

<script setup>
import { reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';

const props = defineProps({
    sahodaya: Object,
    event: Object,
    registrations: Array,
    marks: Object,
});

const navItems = computed(() => [
    { href: `/portal/fest-coordinator/${props.sahodaya.id}`, label: 'Dashboard' },
    { href: `/portal/fest-coordinator/${props.sahodaya.id}/events/${props.event.id}/marks`, label: 'Mark entry' },
]);

const markForms = reactive({});
for (const reg of props.registrations) {
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

function showMeasurement(item) {
    return props.event.record_tracking_enabled
        && (item?.category === 'sports' || item?.sport_discipline);
}

function saveMark(participant, item) {
    router.post(`/portal/fest-coordinator/${props.sahodaya.id}/events/${props.event.id}/marks`, {
        participant_id: participant.id,
        item_id: item.id,
        ...markForms[participant.id],
    }, { preserveScroll: true });
}
</script>

