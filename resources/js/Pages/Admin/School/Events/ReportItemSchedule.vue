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

        <section v-if="hasItemHeads" class="mb-6">
            <div class="flex flex-wrap items-end justify-between gap-2 mb-3">
                <div>
                    <h3 class="text-sm font-semibold text-slate-800">Filter by item</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Search or pick an item — the schedule below updates instantly.</p>
                </div>
                <Link :href="`${programBase}/reports/${event.id}`" class="text-xs font-semibold text-indigo-600 hover:underline">← All sections</Link>
            </div>
            <ReportItemSearchSelect :items="flatItems"
                                    :model-value="itemFilter"
                                    :all-items-label="`All ${flatItems.length} items`"
                                    search-placeholder="Search by item name or code…"
                                    @select="onItemSelect" />
        </section>

        <form class="card mb-4 flex flex-wrap gap-3 items-end p-4" @submit.prevent="applyFilters">
            <FormField label="Date" class-extra="mb-0">
                <input v-model="filterDate" type="date" class="field !py-1.5 text-sm">
            </FormField>
            <FormField v-if="stages.length" label="Stage / venue" class-extra="mb-0">
                <SearchableSelect v-model="filterStageId" :options="stageOptions" :all-option="true" all-label="All stages" class="min-w-[10rem]" />
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
            <div class="overflow-x-auto">
                <table class="data-table w-full text-sm">
                    <thead>
                        <tr>
                            <th>Head</th>
                            <th>Item</th>
                            <th>Category</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Venue</th>
                            <th>Stage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(row, idx) in displayRows" :key="row.item_id">
                            <tr v-if="shouldShowHeadDivider(row, displayRows[idx - 1])" class="bg-slate-50/80">
                                <td colspan="7" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                    {{ row.head_name ?? 'Other items' }}
                                </td>
                            </tr>
                            <tr class="border-t">
                                <td class="text-xs text-slate-500">{{ row.head_name ?? '—' }}</td>
                                <td class="font-medium">{{ row.title }}</td>
                                <td class="text-xs">{{ categoryLabel(row) }}</td>
                                <td>{{ formatCalendarDate(row.scheduled_date) }}</td>
                                <td>{{ row.scheduled_time ?? '—' }}</td>
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
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import ReportItemSearchSelect from '@/Components/reports/ReportItemSearchSelect.vue';
import ReportDownloadButtons from '@/Components/reports/ReportDownloadButtons.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';
import { useReportHeadFilters } from '@/composables/useReportHeadFilters.js';
import { formatCalendarDate } from '@/support/calendarDates.js';

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

const flatItems = computed(() => props.headItemGroups.flatMap((h) => h.items ?? []));

// Item selection is client-side only here (no server round trip): itemScheduleRows()
// is scoped by date/stage only, never by head_id/item_id, so the item filter just
// narrows the already-loaded rows in-memory via useReportHeadFilters' displayRows.
function onItemSelect(itemId) {
    itemFilter.value = itemId;
}

const filteredSummary = computed(() => {
    const list = displayRows.value;
    const scheduled = list.filter((r) => r.scheduled_at).length;
    return {
        total: list.length,
        scheduled,
        unscheduled: list.length - scheduled,
    };
});

// Same fallback chain as the Sahodaya ItemSchedule.vue report: prefer a
// backend-supplied category_label, else humanize whichever raw grouping
// field the row carries. Kept row.age_group_label in the chain since the
// pre-existing template referenced it (harmless if the backend never sets it).
function humanize(value) {
    return String(value).replace(/[_-]/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function categoryLabel(row) {
    if (row.category_label) return row.category_label;
    if (row.age_group_label) return row.age_group_label;
    if (row.age_group) return humanize(row.age_group);
    if (row.class_group && row.class_group !== 'open') return humanize(row.class_group);
    return '—';
}

function stageLabel(stage) {
    return stage.venue?.name ? `${stage.name} · ${stage.venue.name}` : stage.name;
}

const stageOptions = computed(() => props.stages.map((s) => ({ value: String(s.id), label: stageLabel(s) })));

function applyFilters() {
    router.get(base, {
        head_id: headFilter.value || undefined,
        item_id: itemFilter.value || undefined,
        date: filterDate.value || undefined,
        stage_id: filterStageId.value || undefined,
    }, { preserveScroll: true, preserveState: true });
}
</script>
