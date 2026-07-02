<template>
    <SchoolAdminLayout :title="`${event.title} — Marks`" :school="school" :show-header-title="false">
        <PageHeader :title="`${event.title} — Mark entry`" eyebrow="School events"
                    description="Enter positions and scores for your school-round participants." />

        <p class="mb-4">
            <a :href="`/school-admin/${school.id}/fest-programs/${event.id}`" class="link-brand text-sm">← Back to event</a>
        </p>

        <p class="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            <strong>Shared ranks:</strong> Multiple athletes can share the same position (e.g. two athletes both rank 2 after a tie).
            Enter the same number in the Position column for each — then save each row.
        </p>

        <EmptyState v-if="!registrations.length" title="No approved registrations"
                    description="Register and approve participants before entering marks." icon="📊" />

        <div v-else class="space-y-6">
            <section v-for="reg in registrations" :key="reg.id" class="card overflow-hidden p-0">
                <div class="border-b border-slate-100 px-5 py-4 bg-slate-50/80 flex flex-wrap items-center justify-between gap-3">
                    <h3 class="section-title">{{ reg.item?.title }}</h3>
                    <div v-if="performers(reg).length > 1" class="flex items-center gap-2 text-xs">
                        <label class="text-slate-600 whitespace-nowrap">Same rank for all:</label>
                        <input v-model.number="bulkRank[reg.id]" type="number" min="1" class="field !py-1 w-16" placeholder="#" />
                        <button type="button" class="btn-secondary !min-h-0 !px-2 !py-1"
                                :disabled="!bulkRank[reg.id]"
                                @click="applyBulkRank(reg)">
                            Apply
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Participant</th>
                                <th class="w-28">Position</th>
                                <th class="w-28">Score</th>
                                <th class="w-32">Measurement</th>
                                <th class="w-24">Unit</th>
                                <th class="w-28 text-right">Save</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in performers(reg)" :key="p.id">
                                <td class="font-medium">{{ participantName(p) }}</td>
                                <td>
                                    <input v-model.number="forms[p.id].position" type="number" min="1"
                                           class="field !py-1" placeholder="Rank" title="Same rank allowed for ties" />
                                </td>
                                <td><input v-model.number="forms[p.id].score" type="number" min="0" step="0.01" class="field !py-1" /></td>
                                <td><input v-model="forms[p.id].measurement_value" class="field !py-1" /></td>
                                <td><input v-model="forms[p.id].measurement_unit" class="field !py-1" placeholder="s/m" /></td>
                                <td class="text-right">
                                    <button type="button" class="btn-secondary text-xs" @click="save(p, reg.item)">Save</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    school: Object,
    event: Object,
    registrations: { type: Array, default: () => [] },
    marks: { type: Object, default: () => ({}) },
});

const forms = reactive({});
const bulkRank = reactive({});

for (const reg of props.registrations) {
    for (const p of performers(reg)) {
        const existing = props.marks[p.id] ?? {};
        forms[p.id] = {
            participant_id: p.id,
            item_id: reg.item?.id,
            position: existing.position ?? null,
            score: existing.score ?? null,
            measurement_value: existing.measurement_value ?? '',
            measurement_unit: existing.measurement_unit ?? '',
        };
    }
}

function performers(reg) {
    return (reg.participants ?? []).filter((p) => p.participant_role !== 'standby');
}

function participantName(p) {
    return p.student?.name ?? p.teacher?.name ?? 'Participant';
}

function applyBulkRank(reg) {
    const rank = bulkRank[reg.id];
    if (!rank || rank < 1) return;
    for (const p of performers(reg)) {
        if (forms[p.id]) {
            forms[p.id].position = rank;
        }
    }
}

function save(participant, item) {
    const data = forms[participant.id];
    router.post(`/school-admin/${props.school.id}/fest-programs/${props.event.id}/marks`, {
        ...data,
        item_id: item?.id,
    }, { preserveScroll: true });
}
</script>
