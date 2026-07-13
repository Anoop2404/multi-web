<template>
    <SahodayaEventsLayout :title="`${event.title} — Schedule clashes`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Schedule clashes`" eyebrow="Reports"
                    description="Participant and stage scheduling conflicts to resolve before publishing.">
            <template #actions>
                <a :href="csvUrl" class="btn-secondary text-sm">Export CSV ↓</a>
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
                    <select v-model="schoolFilter" class="field text-sm">
                        <option value="">All schools</option>
                        <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </FormField>
            </template>
        </ReportHeadFilter>

        <form v-else @submit.prevent="applyFilter" class="flex flex-wrap gap-2 my-4">
            <select v-model="schoolFilter" class="field">
                <option value="">All schools</option>
                <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
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
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>School</th>
                            <th>Item 1</th>
                            <th>Item 2</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(c, i) in filteredParticipant" :key="'p-'+i">
                            <td>{{ c.student_name }}</td>
                            <td>{{ c.school_name }}</td>
                            <td>{{ c.event1 }}</td>
                            <td>{{ c.event2 }}</td>
                            <td class="text-xs">{{ c.time }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="filteredStage.length">
            <h3 class="section-title mb-3">Stage conflicts</h3>
            <div class="card overflow-hidden p-0">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Stage</th>
                            <th>Item 1</th>
                            <th>Item 2</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(c, i) in filteredStage" :key="'s-'+i">
                            <td>{{ c.stage }}<span v-if="c.venue" class="text-slate-400"> · {{ c.venue }}</span></td>
                            <td>{{ c.item1 }}</td>
                            <td>{{ c.item2 }}</td>
                            <td class="text-xs">{{ c.time }}</td>
                        </tr>
                    </tbody>
                </table>
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
</script>
