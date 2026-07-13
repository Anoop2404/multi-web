<template>
    <SchoolAdminLayout :title="`Item schedule — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader
            :title="`Item schedule — ${event.title}`"
            :eyebrow="programLabel"
            description="Venue and time for each competition item."
        >
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">← Reports</Link>
                <ReportDownloadButtons :pdf-url="pdfUrl" :csv-url="csvUrl" />
            </template>
        </PageHeader>

        <ReportHeadSubNav v-if="hasItemHeads"
                          :head-item-groups="headItemGroups"
                          :base-url="base"
                          :selected-head-id="headFilter"
                          :selected-item-id="itemFilter"
                          :hub-url="`${programBase}/reports/${event.id}`"
                          :is-sports="event.event_type === 'sports'"
                          :preserve-query="scheduleQuery" />

        <form class="card mb-4 flex flex-wrap gap-3 items-end p-4" @submit.prevent="applyFilters">
            <FormField label="Date" class-extra="mb-0">
                <input v-model="filterDate" type="date" class="field !py-1.5 text-sm">
            </FormField>
            <FormField v-if="stages.length" label="Stage / venue" class-extra="mb-0">
                <select v-model="filterStageId" class="field !py-1.5 text-sm min-w-[10rem]">
                    <option value="">All stages</option>
                    <option v-for="s in stages" :key="s.id" :value="String(s.id)">{{ stageLabel(s) }}</option>
                </select>
            </FormField>
            <button type="submit" class="btn-secondary text-sm">Apply date/stage</button>
        </form>

        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ filteredSummary.total }}</p>
                <p class="text-xs text-slate-500 mt-1">Items</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ filteredSummary.scheduled }}</p>
                <p class="text-xs text-slate-500 mt-1">Scheduled</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-amber-700">{{ filteredSummary.unscheduled }}</p>
                <p class="text-xs text-slate-500 mt-1">Not scheduled</p>
            </div>
        </div>

        <div class="card card--flush overflow-hidden">
            <table class="data-table w-full text-sm">
                <thead>
                    <tr>
                        <th>Head</th>
                        <th>Item</th>
                        <th>Age</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Venue</th>
                        <th>Stage</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(row, idx) in displayRows" :key="row.item_id">
                        <tr v-if="shouldShowHeadDivider(idx)" class="bg-slate-50/80">
                            <td colspan="7" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {{ row.head_name ?? 'Other items' }}
                            </td>
                        </tr>
                        <tr class="border-t">
                            <td class="text-xs text-slate-500">{{ row.head_name ?? '—' }}</td>
                            <td class="font-medium">{{ row.item_title }}</td>
                            <td class="text-xs">{{ row.age_group_label ?? row.age_group ?? '—' }}</td>
                            <td>{{ row.date ?? '—' }}</td>
                            <td>{{ row.time ?? '—' }}</td>
                            <td class="text-xs">{{ row.venue ?? '—' }}</td>
                            <td class="text-xs">{{ row.stage ?? '—' }}</td>
                        </tr>
                    </template>
                    <tr v-if="!displayRows.length">
                        <td colspan="7" class="p-8 text-center text-slate-400">No schedule rows match filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import ReportHeadSubNav from '@/Components/reports/ReportHeadSubNav.vue';
import ReportDownloadButtons from '@/Components/reports/ReportDownloadButtons.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';
import { useReportHeadFilters } from '@/composables/useReportHeadFilters.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    rows: Array,
    summary: Object,
    stages: Array,
    filters: Object,
    pdfUrl: String,
    csvUrl: String,
    headItemGroups: { type: Array, default: () => [] },
    headsForFilter: { type: Array, default: () => [] },
    hasItemHeads: Boolean,
});

const { programLabel, programBase } = useSchoolProgramContext(props);
const base = `${programBase.value}/reports/${props.event.id}/item-schedule`;
const filterDate = ref(props.filters?.date ?? '');
const filterStageId = ref(props.filters?.stage_id ? String(props.filters.stage_id) : '');

const {
    headFilter,
    itemFilter,
    displayRows,
    shouldShowHeadDivider,
} = useReportHeadFilters(base, () => props.rows);

const filteredSummary = computed(() => {
    const list = displayRows.value;
    const scheduled = list.filter((r) => r.scheduled_at || r.date).length;
    return {
        total: list.length,
        scheduled,
        unscheduled: list.length - scheduled,
    };
});

const scheduleQuery = computed(() => ({
    date: filterDate.value || undefined,
    stage_id: filterStageId.value || undefined,
}));

function stageLabel(stage) {
    return stage.venue?.name ? `${stage.name} · ${stage.venue.name}` : stage.name;
}

function applyFilters() {
    router.get(base, {
        head_id: headFilter.value || undefined,
        item_id: itemFilter.value || undefined,
        date: filterDate.value || undefined,
        stage_id: filterStageId.value || undefined,
    }, { preserveScroll: true, preserveState: true });
}
</script>
