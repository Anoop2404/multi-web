<template>
    <SahodayaEventsLayout :title="`${event.title} — Item-wise`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Item-wise browser`" eyebrow="Reports"
                    :description="event.event_type === 'sports'
                        ? 'Pick an Event Head and item to view all participants and marks.'
                        : 'Pick an item head and item to view all participants and marks.'">
            <template #actions>
                <Link v-if="event.event_type === 'sports'"
                      :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/reports/by-head`"
                      class="btn-secondary text-sm">
                    ← By Event Head
                </Link>
                <ReportDownloadButtons v-if="filterItemId" :pdf-url="pdfUrl" :xls-url="xlsUrl" />
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="item-wise" />

        <ReportHeadItemNavigator
            :groups="headItemGroups"
            :base-url="base"
            :selected-head-id="filterHeadId"
            :selected-item-id="filterItemId"
            :has-item-heads="hasItemHeads"
            :is-sports="event.event_type === 'sports'"
            hint="Select a competition item to view participants from all schools."
        >
            <template #default="{ item }">
                <div class="card card--flush overflow-hidden">
                    <div class="px-5 py-3 border-b bg-slate-50/80">
                        <h3 class="section-title text-sm">{{ item.title }}</h3>
                        <p v-if="item.item_code" class="text-xs font-mono text-slate-500">{{ item.item_code }}</p>
                    </div>
                    <table class="data-table w-full text-sm">
                        <thead>
                            <tr>
                                <th>Sl No</th>
                                <th>School</th>
                                <th>Participant</th>
                                <th>Reg no</th>
                                <th>Fest ID</th>
                                <th>Item reg</th>
                                <th>Chest</th>
                                <th>Status</th>
                                <th>Grade</th>
                                <th>Rank</th>
                                <th>Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(p, idx) in participants" :key="p.id">
                                <td class="text-xs text-slate-400">{{ idx + 1 }}</td>
                                <td class="text-xs">{{ (p.school || '').toUpperCase() }}</td>
                                <td class="font-medium">{{ p.participant }}</td>
                                <td class="font-mono text-xs">{{ p.reg_no ?? '—' }}</td>
                                <td class="font-mono text-xs">{{ p.fest_id ?? '—' }}</td>
                                <td class="font-mono text-xs">{{ p.item_reg ?? '—' }}</td>
                                <td class="font-mono text-xs">{{ p.chest_no ?? '—' }}</td>
                                <td class="text-xs capitalize">{{ p.status }}</td>
                                <td>{{ p.grade ?? '—' }}</td>
                                <td>{{ p.position ?? '—' }}</td>
                                <td>{{ p.score ?? '—' }}</td>
                            </tr>
                            <tr v-if="!participants.length">
                                <td colspan="11" class="p-8 text-center text-slate-400">No participants for this item.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </ReportHeadItemNavigator>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import ReportHeadItemNavigator from '@/Components/reports/ReportHeadItemNavigator.vue';
import ReportDownloadButtons from '@/Components/reports/ReportDownloadButtons.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, participants: Array,
    filterHeadId: { type: [String, Number], default: null },
    filterItemId: { type: Number, default: null },
    headItemGroups: { type: Array, default: () => [] },
    hasItemHeads: Boolean,
    pdfUrl: String, xlsUrl: String,
    activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/item-wise`;
</script>
