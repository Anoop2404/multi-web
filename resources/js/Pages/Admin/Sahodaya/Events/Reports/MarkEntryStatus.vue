<template>
    <SahodayaEventsLayout :title="`${event.title} — Mark entry status`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Mark entry status`" eyebrow="Reports"
                    :description="event.event_type === 'sports'
                        ? 'Track mark entry progress by Event Head and item.'
                        : 'Track mark entry progress by item head and item.'" />

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="mark-entry-status" />

        <ReportHeadFilter v-if="hasItemHeads"
                          v-model="headFilter"
                          v-model:item-id="itemFilter"
                          :heads="headsForFilter"
                          :head-item-groups="headItemGroups"
                          :is-sports="event.event_type === 'sports'"
                          @apply="applyFilter" />

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ filteredSummary.items }}</p>
                <p class="text-xs text-slate-500 mt-1">Items</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ filteredSummary.participants }}</p>
                <p class="text-xs text-slate-500 mt-1">Participants</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ filteredSummary.marked }}</p>
                <p class="text-xs text-slate-500 mt-1">Marked</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-amber-700">{{ filteredSummary.pending }}</p>
                <p class="text-xs text-slate-500 mt-1">Pending</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ filteredSummary.complete }}/{{ filteredSummary.items }}</p>
                <p class="text-xs text-slate-500 mt-1">Items complete</p>
            </div>
        </div>

        <div class="card overflow-hidden p-0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Head</th>
                        <th>Item</th>
                        <th>Class</th>
                        <th>Judges</th>
                        <th>Participants</th>
                        <th>Marked</th>
                        <th>Pending</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(row, idx) in displayRows" :key="row.item_id">
                        <tr v-if="idx === 0 || row.head_name !== displayRows[idx - 1]?.head_name" class="bg-slate-50/80">
                            <td colspan="8" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {{ row.head_name ?? 'Other items' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="text-xs text-slate-400">{{ row.head_name ?? '—' }}</td>
                            <td class="font-medium">{{ row.title }}</td>
                            <td>{{ row.class_group || '—' }}</td>
                            <td>{{ row.judges }}</td>
                            <td>{{ row.participants }}</td>
                            <td>{{ row.marked }}</td>
                            <td>{{ row.pending }}</td>
                            <td>
                                <span class="status-pill" :class="row.complete ? 'status-pill--completed' : 'status-pill--open'">
                                    {{ row.complete ? 'Complete' : 'Pending' }}
                                </span>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="!displayRows.length">
                        <td colspan="8" class="p-6 text-center text-slate-400">No items match the selected filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import ReportHeadFilter from '@/Components/reports/ReportHeadFilter.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    summary: Object,
    rows: Array,
    csvUrl: String,
    activityLogs: { type: Array, default: () => [] },
});

const page = usePage();
const headItemGroups = computed(() => page.props.headItemGroups ?? []);
const headsForFilter = computed(() => page.props.headsForFilter ?? []);
const hasItemHeads = computed(() => page.props.hasItemHeads ?? false);

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/mark-entry-status`;
const headFilter = ref(new URLSearchParams(window.location.search).get('head_id') ?? '');
const itemFilter = ref(new URLSearchParams(window.location.search).get('item_id') ?? '');

const displayRows = computed(() => {
    let list = props.rows ?? [];
    if (headFilter.value) {
        list = list.filter((r) => String(r.head_id) === String(headFilter.value));
    }
    if (itemFilter.value) {
        list = list.filter((r) => String(r.item_id) === String(itemFilter.value));
    }
    return list;
});

const filteredSummary = computed(() => ({
    items: displayRows.value.length,
    participants: displayRows.value.reduce((n, r) => n + r.participants, 0),
    marked: displayRows.value.reduce((n, r) => n + r.marked, 0),
    pending: displayRows.value.reduce((n, r) => n + r.pending, 0),
    complete: displayRows.value.filter((r) => r.complete).length,
}));

function applyFilter() {
    router.get(base, {
        head_id: headFilter.value || undefined,
        item_id: itemFilter.value || undefined,
    }, { preserveScroll: true, preserveState: true });
}
</script>
