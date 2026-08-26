<template>
    <SahodayaEventsLayout :title="`${event.title} — Venue & time schedule`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Venue & time schedule`" eyebrow="Reports"
                    description="View and export item dates, times, and venues. All schedule fields are optional.">
            <template #actions>
                <a :href="editUrl" class="btn-secondary text-sm">Edit schedule →</a>
                <a :href="csvUrl" target="_blank" class="btn-secondary text-sm">Export CSV ↓</a>
                <a :href="pdfUrl" target="_blank" class="btn-primary text-sm">Export PDF ↓</a>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="item-schedule" />

        <ReportHeadFilter v-if="hasItemHeads"
                          v-model="headFilter"
                          v-model:item-id="itemFilter"
                          :heads="headsForFilter"
                          :head-item-groups="headItemGroups"
                          :is-sports="event.event_type === 'sports'"
                          @apply="applyFilters">
            <template #extra>
                <FormField label="Date" class-extra="mb-0">
                    <input v-model="filterDate" type="date" class="field !py-1.5 text-sm">
                </FormField>
                <FormField v-if="stages.length" label="Stage / venue" class-extra="mb-0 min-w-[10rem]">
                    <SearchableSelect v-model="filterStageId" :options="stageOptions" :all-option="true" all-label="All stages" />
                </FormField>
                <button v-if="filterDate || filterStageId" type="button" class="btn-secondary text-sm" @click="clearDateStage">Clear date/stage</button>
            </template>
        </ReportHeadFilter>

        <form v-else class="card mb-4 flex flex-wrap gap-3 items-end p-4" @submit.prevent="applyFilters">
            <div>
                <label class="text-xs font-semibold text-slate-600">Date</label>
                <input v-model="filterDate" type="date" class="field !py-1.5 text-sm mt-1 block">
            </div>
            <div v-if="stages.length">
                <label class="text-xs font-semibold text-slate-600">Stage / venue</label>
                <SearchableSelect v-model="filterStageId" :options="stageOptions" :all-option="true" all-label="All stages" class="mt-1 block min-w-[10rem]" />
            </div>
            <button type="submit" class="btn-secondary text-sm">Apply filters</button>
            <button v-if="filterDate || filterStageId" type="button" class="btn-secondary text-sm" @click="clearDateStage">Clear</button>
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

        <div class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
            <table class="data-table">
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
                        <tr>
                            <td class="text-xs text-slate-400">{{ row.head_name ?? '—' }}</td>
                            <td class="font-medium">{{ row.title }}</td>
                            <td class="text-xs uppercase">{{ categoryLabel(row) }}</td>
                            <td>{{ formatCalendarDate(row.scheduled_date) }}</td>
                            <td>{{ row.scheduled_time || '—' }}</td>
                            <td>{{ row.venue || '—' }}</td>
                            <td>{{ row.stage || '—' }}</td>
                        </tr>
                    </template>
                    <tr v-if="!displayRows.length">
                        <td colspan="7" class="p-6 text-center text-slate-400">No items match the selected filters.</td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import { formatCalendarDate } from '@/support/calendarDates.js';
import ReportHeadFilter from '@/Components/reports/ReportHeadFilter.vue';
import { useReportHeadFilters } from '@/composables/useReportHeadFilters.js';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    rows: Array,
    summary: Object,
    stages: Array,
    filters: Object,
    csvUrl: String,
    pdfUrl: String,
    editUrl: String,
    activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/item-schedule`;
const filterDate = ref(props.filters?.date ?? '');
const filterStageId = ref(props.filters?.stage_id ? String(props.filters.stage_id) : '');

const {
    headFilter,
    itemFilter,
    headItemGroups,
    headsForFilter,
    hasItemHeads,
    displayRows,
    shouldShowHeadDivider,
} = useReportHeadFilters(base, () => props.rows);

const filteredSummary = computed(() => {
    const list = displayRows.value;
    const scheduled = list.filter((r) => r.scheduled_at).length;
    return {
        total: list.length,
        scheduled,
        unscheduled: list.length - scheduled,
    };
});

// FestItemScheduleService's rows only carry age_group today (no class_group
// or category_label yet), but this keeps the same fallback chain used by the
// other report pages so it picks up category_label automatically once the
// backend adds it.
function humanize(value) {
    return String(value).replace(/[_-]/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function categoryLabel(row) {
    if (row.category_label) return row.category_label;
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

function clearDateStage() {
    filterDate.value = '';
    filterStageId.value = '';
    applyFilters();
}
</script>
