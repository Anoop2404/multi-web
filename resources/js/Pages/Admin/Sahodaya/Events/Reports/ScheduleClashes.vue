<template>
    <SahodayaEventsLayout :title="`${event.title} — Schedule clashes`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Schedule clashes`" eyebrow="Reports"
                    description="Participant and stage scheduling conflicts to resolve before publishing.">
            <template #actions>
                <ReportDownloadButtons :pdf-url="pdfUrl" :csv-url="csvUrl" />
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="schedule-clashes" />

        <ReportHeadFilter v-if="hasItemHeads"
                          v-model="headFilter"
                          v-model:item-id="itemFilter"
                          :heads="headsForFilter"
                          :head-item-groups="headItemGroups"
                          :is-sports="event.event_type === 'sports'"
                          @apply="applyFilter">
            <template #extra>
                <FormField label="School" class-extra="mb-0 min-w-[12rem]">
                    <SearchableSelect v-model="schoolFilter" :options="schools" :all-option="true" all-label="All schools" />
                </FormField>
            </template>
        </ReportHeadFilter>

        <form v-else @submit.prevent="applyFilter" class="flex flex-wrap gap-2 my-4">
            <SearchableSelect v-model="schoolFilter" :options="schools" :all-option="true" all-label="All schools" />
            <button type="submit" class="btn-primary">Filter</button>
        </form>

        <div v-if="totalClashes === 0" class="notice-banner notice-banner--success mb-6">
            No schedule clashes detected{{ schoolFilter ? ' for this school' : '' }}.
        </div>
        <div v-else class="notice-banner notice-banner--warning mb-6">
            {{ totalClashes }} clash(es) found — resolve on the Schedule page before publishing.
        </div>

        <section v-if="filteredParticipant.length" class="mb-8">
            <h3 class="section-title mb-3">Participant clashes</h3>
            <div class="card overflow-hidden p-0">
                <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Sl No</th>
                            <th>Student</th>
                            <th>School</th>
                            <th>Item 1</th>
                            <th>Item 2</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(c, i) in filteredParticipant" :key="'p-'+i">
                            <tr v-if="shouldShowDateDivider(c, filteredParticipant[i - 1])" class="bg-slate-100">
                                <td colspan="5" class="px-3 py-2 text-sm font-bold uppercase tracking-wide text-slate-700">
                                    {{ c.date || 'Unscheduled' }}
                                </td>
                            </tr>
                            <tr>
                                <td>{{ i + 1 }}</td>
                                <td>{{ c.student_name }}</td>
                                <td>{{ (c.school_name || '').toUpperCase() }}</td>
                                <td>
                                    <p class="font-medium">{{ c.event1 }}</p>
                                    <p class="text-xs text-slate-500">{{ itemMetaLine(c, 1) }}</p>
                                </td>
                                <td>
                                    <p class="font-medium">{{ c.event2 }}</p>
                                    <p class="text-xs text-slate-500">{{ itemMetaLine(c, 2) }}</p>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                </div>
            </div>
        </section>

        <section v-if="filteredStage.length">
            <h3 class="section-title mb-3">Stage conflicts</h3>
            <div class="card overflow-hidden p-0">
                <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Sl No</th>
                            <th>Stage</th>
                            <th>Item 1</th>
                            <th>Item 2</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(c, i) in filteredStage" :key="'s-'+i">
                            <td>{{ i + 1 }}</td>
                            <td>
                                {{ c.stage }}<span v-if="c.venue" class="text-slate-400"> · {{ c.venue }}</span>
                                <span v-if="c.date" class="block text-xs text-slate-400">{{ c.date }}</span>
                            </td>
                            <td>
                                <p class="font-medium">{{ c.item1 }}</p>
                                <p class="text-xs text-slate-500">{{ itemMetaLine(c, 1) }}</p>
                            </td>
                            <td>
                                <p class="font-medium">{{ c.item2 }}</p>
                                <p class="text-xs text-slate-500">{{ itemMetaLine(c, 2) }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>
        </section>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import ReportHeadFilter from '@/Components/reports/ReportHeadFilter.vue';
import ReportDownloadButtons from '@/Components/reports/ReportDownloadButtons.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { filterClashRows, useReportHeadFilters } from '@/composables/useReportHeadFilters.js';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    schools: Array,
    filters: Object,
    participant: { type: Array, default: () => [] },
    stage: { type: Array, default: () => [] },
    csvUrl: String,
    pdfUrl: String,
    activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/schedule-clashes`;
const schoolFilter = ref(props.filters?.school_id ?? '');

const {
    headFilter,
    itemFilter,
    headItemGroups,
    headsForFilter,
    hasItemHeads,
    applyFilter: applyHeadFilter,
} = useReportHeadFilters(base, () => []);

const filteredParticipant = computed(() => filterClashRows(props.participant, {
    headId: headFilter.value,
    itemId: itemFilter.value,
}));

const filteredStage = computed(() => filterClashRows(props.stage, {
    headId: headFilter.value,
    itemId: itemFilter.value,
}));

const totalClashes = computed(() => filteredParticipant.value.length + filteredStage.value.length);

function applyFilter() {
    applyHeadFilter({ school_id: schoolFilter.value || undefined });
}

// Both clash row shapes carry item1_category/item1_gender/item1_type/item1_time
// (and the item2_* equivalents) from FestScheduleConflictService. item1_stage/item2_stage
// only exist on participant-clash rows — stage conflicts already show a shared stage column.
function itemMetaLine(clash, n) {
    return [
        clash[`item${n}_category`],
        clash[`item${n}_gender`],
        clash[`item${n}_type`],
        clash[`item${n}_stage`],
        clash[`item${n}_time`],
    ].filter(Boolean).join(' · ');
}

// Rows arrive pre-sorted chronologically (FestScheduleConflictService sorts by start_time1),
// so a date-group boundary is just "this row's date differs from the previous row's".
function shouldShowDateDivider(row, prevRow) {
    return (row.date ?? null) !== (prevRow?.date ?? null);
}
</script>
