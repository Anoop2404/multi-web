<template>
    <SahodayaEventsLayout :title="`${event.title} — School participation`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — School participation counts`" eyebrow="Reports"
                    :description="usesPhases
                        ? 'Schools with an active registration in this event, broken down by phase, with unique student counts.'
                        : 'Schools with an active registration in this event, and how many items/students they\'ve entered.'">
            <template #actions>
                <ReportDownloadButtons :pdf-url="pdfUrl" :xls-url="xlsUrl" />
            </template>
        </PageHeader>

        <div v-if="competitionPhases.length" class="card mb-4 !py-4">
            <div class="grid gap-3 md:grid-cols-3 items-end">
                <label class="text-xs font-semibold text-slate-600">Competition phase
                    <SearchableSelect v-model="scopePhaseId" :options="competitionPhases" :all-option="true" all-label="All published phases" class="mt-1 w-full" />
                </label>
                <label class="text-xs font-semibold text-slate-600">Region
                    <SearchableSelect v-model="scopeRegionId" :options="regions" :all-option="true" all-label="Combined" class="mt-1 w-full" />
                </label>
                <button type="button" class="btn-primary text-sm" @click="applyReportScope">Apply</button>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ totals.schools }}</p>
                <p class="text-xs text-slate-500 mt-1">Schools participating</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ totals.active_registrations }}</p>
                <p class="text-xs text-slate-500 mt-1">Active registrations</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ totals.unique_students }}</p>
                <p class="text-xs text-slate-500 mt-1">Unique students</p>
            </div>
        </div>

        <div class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>School</th>
                            <th v-if="usesPhases">Phase</th>
                            <th>Active registrations</th>
                            <th>Items</th>
                            <th>Unique students</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="`${row.school_id}-${row.phase_id ?? 'none'}`">
                            <td class="font-medium">{{ row.school_name }}</td>
                            <td v-if="usesPhases">{{ row.phase_name }}</td>
                            <td>{{ row.active_count }}</td>
                            <td>{{ row.item_count }}</td>
                            <td>{{ row.unique_student_count }}</td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td :colspan="usesPhases ? 5 : 4" class="p-6 text-center text-slate-400">No schools have an active registration for this event yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import ReportDownloadButtons from '@/Components/reports/ReportDownloadButtons.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    rows: Array,
    totals: Object,
    usesPhases: { type: Boolean, default: false },
    pdfUrl: String,
    xlsUrl: String,
    regions: { type: Array, default: () => [] },
    competitionPhases: { type: Array, default: () => [] },
    reportScopeSelection: { type: Object, default: () => ({}) },
    activityLogs: { type: Array, default: () => [] },
});

const reportsBase = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports`;

const scopePhaseId = ref(props.reportScopeSelection.competition_phase_id || '');
const scopeRegionId = ref(props.reportScopeSelection.region_id || '');

function applyReportScope() {
    router.get(`${reportsBase}/school-participation`, {
        scope_mode: scopeRegionId.value ? 'region' : 'combined',
        competition_phase_id: scopePhaseId.value || undefined,
        region_id: scopeRegionId.value || undefined,
    }, { preserveState: false });
}
</script>
