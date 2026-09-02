<template>
    <SchoolAdminLayout :title="`Student report — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader
            :title="`Student Report — ${event.title}`"
            :eyebrow="programLabel"
            description="One row per student — items registered, and the phase/level each falls under."
        >
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">← Reports</Link>
            </template>
        </PageHeader>

        <div v-if="filterOptions.phases.length || filterOptions.batches.length" class="mb-6 flex flex-wrap gap-3">
            <div v-if="filterOptions.phases.length">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Phase</label>
                <select class="field text-xs" :value="filterPhaseId ?? ''" @change="onPhaseSelect($event.target.value)">
                    <option value="">All phases</option>
                    <option v-for="phase in filterOptions.phases" :key="phase.id" :value="phase.id">{{ phase.name }}</option>
                </select>
            </div>
            <div v-if="filterOptions.batches.length">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Level</label>
                <select class="field text-xs" :value="filterBatchId ?? ''" @change="onBatchSelect($event.target.value)">
                    <option value="">All levels</option>
                    <option v-for="batch in filterOptions.batches" :key="batch.id" :value="batch.id">{{ batch.name }}</option>
                </select>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-3 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ uniqueStudents }}</p>
                <p class="text-xs text-slate-500 mt-1">Unique students</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ totalItemRegs }}</p>
                <p class="text-xs text-slate-500 mt-1">Total item registrations</p>
            </div>
        </div>

        <div class="card card--flush overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="p-3">Student</th>
                            <th class="p-3">Fest ID</th>
                            <th v-if="filterOptions.phases.length" class="p-3">Phase(s)</th>
                            <th v-if="filterOptions.batches.length" class="p-3">Level(s)</th>
                            <th class="p-3 text-center">Items</th>
                            <th class="p-3">Items registered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.key" class="border-t align-top">
                            <td class="p-3">
                                <span class="font-medium">{{ row.name }}</span>
                                <p class="text-xs text-slate-400">{{ row.school_name }}</p>
                            </td>
                            <td class="p-3 font-mono text-xs font-semibold text-[#0f3d7a]">{{ row.level_reg }}</td>
                            <td v-if="filterOptions.phases.length" class="p-3 text-xs">{{ row.phase_names.join(', ') || '—' }}</td>
                            <td v-if="filterOptions.batches.length" class="p-3 text-xs">{{ row.batch_names.join(', ') || '—' }}</td>
                            <td class="p-3 text-center font-bold">{{ row.item_count }}</td>
                            <td class="p-3 text-xs text-slate-600">
                                <span v-for="(item, idx) in row.items" :key="idx">
                                    {{ item.title }}<span v-if="idx < row.items.length - 1">, </span>
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td :colspan="columnCount" class="p-8 text-center text-gray-400">No registrations match the selected filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    rows: { type: Array, default: () => [] },
    uniqueStudents: { type: Number, default: 0 },
    totalItemRegs: { type: Number, default: 0 },
    filterOptions: { type: Object, default: () => ({ phases: [], batches: [] }) },
    filterPhaseId: { type: [String, Number], default: null },
    filterBatchId: { type: [String, Number], default: null },
});

const { programLabel, programBase } = useSchoolProgramContext(props);
const base = `${programBase.value}/reports/${props.event.id}/student-report`;

const columnCount = computed(() => 4 + (props.filterOptions.phases.length ? 1 : 0) + (props.filterOptions.batches.length ? 1 : 0));

function onPhaseSelect(phaseId) {
    router.get(base, phaseId ? { phase_id: phaseId } : {}, { preserveScroll: true, preserveState: true });
}

function onBatchSelect(batchId) {
    router.get(base, batchId ? { registration_batch_id: batchId } : {}, { preserveScroll: true, preserveState: true });
}
</script>
