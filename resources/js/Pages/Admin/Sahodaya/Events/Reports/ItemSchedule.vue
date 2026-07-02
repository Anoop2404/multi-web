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

        <form class="card mb-4 flex flex-wrap gap-3 items-end p-4" @submit.prevent="applyFilters">
            <div>
                <label class="text-xs font-semibold text-slate-600">Date</label>
                <input v-model="filterDate" type="date" class="field !py-1.5 text-sm mt-1 block">
            </div>
            <div v-if="stages.length">
                <label class="text-xs font-semibold text-slate-600">Stage / venue</label>
                <select v-model="filterStageId" class="field !py-1.5 text-sm mt-1 block min-w-[10rem]">
                    <option value="">All stages</option>
                    <option v-for="s in stages" :key="s.id" :value="String(s.id)">{{ stageLabel(s) }}</option>
                </select>
            </div>
            <button type="submit" class="btn-secondary text-sm">Apply filters</button>
            <button v-if="filterDate || filterStageId" type="button" class="btn-secondary text-sm" @click="clearFilters">Clear</button>
        </form>

        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ summary.total }}</p>
                <p class="text-xs text-slate-500 mt-1">Items</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ summary.scheduled }}</p>
                <p class="text-xs text-slate-500 mt-1">Scheduled</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-amber-700">{{ summary.unscheduled }}</p>
                <p class="text-xs text-slate-500 mt-1">Not scheduled</p>
            </div>
        </div>

        <div class="card overflow-hidden p-0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Age</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Venue</th>
                        <th>Stage</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.item_id">
                        <td class="font-medium">{{ row.title }}</td>
                        <td class="text-xs uppercase">{{ row.age_group || '—' }}</td>
                        <td>{{ row.scheduled_date || '—' }}</td>
                        <td>{{ row.scheduled_time || '—' }}</td>
                        <td>{{ row.venue || '—' }}</td>
                        <td>{{ row.stage || '—' }}</td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="6" class="p-6 text-center text-slate-400">No items match the selected filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

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

const filterDate = ref(props.filters?.date ?? '');
const filterStageId = ref(props.filters?.stage_id ? String(props.filters.stage_id) : '');

function stageLabel(stage) {
    return stage.venue?.name ? `${stage.name} · ${stage.venue.name}` : stage.name;
}

function applyFilters() {
    router.get(
        `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/item-schedule`,
        {
            date: filterDate.value || undefined,
            stage_id: filterStageId.value || undefined,
        },
        { preserveScroll: true, preserveState: true },
    );
}

function clearFilters() {
    filterDate.value = '';
    filterStageId.value = '';
    applyFilters();
}
</script>
